<?php
require_once __DIR__ . '/PaymentGateway.php';

/**
 * Реализация PaymentGateway для ЮKassa (https://yookassa.ru/developers/api).
 *
 * HTTP-клиент инъектируется (callable), чтобы шлюз можно было юнит-тестировать
 * без реальной сети. Клиент: function(string $method, string $url, array $headers,
 * ?string $body): array{status:int, body:string}.
 */
class YooKassaGateway implements PaymentGateway
{
    private string $shopId;
    private string $secretKey;
    private string $apiUrl;
    /** @var callable */
    private $http;

    /** Диапазоны IP, с которых ЮKassa шлёт уведомления (docs: «Уведомления»). */
    private const NOTIFY_CIDRS = [
        '185.71.76.0/27',
        '185.71.77.0/27',
        '77.75.153.0/25',
        '77.75.156.11/32',
        '77.75.156.35/32',
        '77.75.154.128/25',
        '2a02:5180::/32',
    ];

    public function __construct(string $shopId, string $secretKey, string $apiUrl = 'https://api.yookassa.ru/v3', ?callable $http = null)
    {
        $this->shopId = $shopId;
        $this->secretKey = $secretKey;
        $this->apiUrl = rtrim($apiUrl, '/');
        $this->http = $http ?? [$this, 'curlRequest'];
    }

    public function createPayment(array $order, string $returnUrl): array
    {
        $payload = [
            'amount' => [
                'value' => number_format((float)$order['total'], 2, '.', ''),
                'currency' => 'RUB',
            ],
            'capture' => true,
            'confirmation' => [
                'type' => 'redirect',
                'return_url' => $returnUrl,
            ],
            'description' => 'Заказ ' . ($order['order_number'] ?? $order['id']),
            'metadata' => [
                'order_id' => (string)($order['id'] ?? ''),
                'order_number' => (string)($order['order_number'] ?? ''),
            ],
        ];

        // Идемпотентность по номеру заказа: повтор не создаёт второй платёж.
        $idempotenceKey = 'order-' . ($order['order_number'] ?? $order['id']);

        $resp = ($this->http)(
            'POST',
            $this->apiUrl . '/payments',
            $this->authHeaders(['Idempotence-Key: ' . $idempotenceKey]),
            json_encode($payload, JSON_UNESCAPED_UNICODE)
        );

        $data = $this->decodeOrFail($resp, 'createPayment');
        $url = $data['confirmation']['confirmation_url'] ?? null;
        if (empty($data['id']) || empty($url)) {
            throw new RuntimeException('YooKassa: некорректный ответ createPayment');
        }
        return ['payment_id' => $data['id'], 'confirmation_url' => $url];
    }

    /** Запросить актуальное состояние платежа у провайдера (для верификации webhook). */
    public function getPayment(string $paymentId): array
    {
        $resp = ($this->http)('GET', $this->apiUrl . '/payments/' . rawurlencode($paymentId), $this->authHeaders(), null);
        return $this->decodeOrFail($resp, 'getPayment');
    }

    public function parseCallback(array $request, string $rawBody): array
    {
        $data = json_decode($rawBody, true);
        $obj = $data['object'] ?? [];
        return [
            'payment_id' => (string)($obj['id'] ?? ''),
            'status' => (string)($obj['status'] ?? ''),
            'paid' => (bool)($obj['paid'] ?? false),
        ];
    }

    /**
     * ЮKassa не подписывает уведомления — подлинность проверяется по IP-источнику
     * (и дополнительно повторным запросом getPayment в обработчике).
     * IP берётся из $headers['REMOTE_ADDR'] (передаём явно для тестируемости).
     */
    public function verifySignature(array $headers, string $rawBody): bool
    {
        $ip = $headers['REMOTE_ADDR'] ?? ($_SERVER['REMOTE_ADDR'] ?? '');
        foreach (self::NOTIFY_CIDRS as $cidr) {
            if (self::ipInCidr($ip, $cidr)) {
                return true;
            }
        }
        return false;
    }

    // --- внутреннее ---

    private function authHeaders(array $extra = []): array
    {
        return array_merge([
            'Authorization: Basic ' . base64_encode($this->shopId . ':' . $this->secretKey),
            'Content-Type: application/json',
        ], $extra);
    }

    private function decodeOrFail(array $resp, string $ctx): array
    {
        $status = $resp['status'] ?? 0;
        if ($status < 200 || $status >= 300) {
            throw new RuntimeException("YooKassa $ctx: HTTP $status " . ($resp['body'] ?? ''));
        }
        $data = json_decode($resp['body'] ?? '', true);
        if (!is_array($data)) {
            throw new RuntimeException("YooKassa $ctx: невалидный JSON");
        }
        return $data;
    }

    private function curlRequest(string $method, string $url, array $headers, ?string $body): array
    {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_POSTFIELDS => $body ?? '',
        ]);
        $respBody = curl_exec($ch);
        if ($respBody === false) {
            $err = curl_error($ch);
            curl_close($ch);
            throw new RuntimeException('YooKassa cURL: ' . $err);
        }
        $status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        return ['status' => $status, 'body' => (string)$respBody];
    }

    /** Проверка вхождения IP (v4/v6) в CIDR. */
    public static function ipInCidr(string $ip, string $cidr): bool
    {
        if (strpos($cidr, '/') === false) {
            return false;
        }
        [$subnet, $bits] = explode('/', $cidr, 2);
        $bits = (int)$bits;
        $ipBin = @inet_pton($ip);
        $subnetBin = @inet_pton($subnet);
        if ($ipBin === false || $subnetBin === false || strlen($ipBin) !== strlen($subnetBin)) {
            return false; // разные семейства адресов или мусор
        }
        $bytes = intdiv($bits, 8);
        $rem = $bits % 8;
        if ($bytes > 0 && substr($ipBin, 0, $bytes) !== substr($subnetBin, 0, $bytes)) {
            return false;
        }
        if ($rem === 0) {
            return true;
        }
        $mask = chr(0xff << (8 - $rem) & 0xff);
        return (ord($ipBin[$bytes]) & ord($mask)) === (ord($subnetBin[$bytes]) & ord($mask));
    }
}
