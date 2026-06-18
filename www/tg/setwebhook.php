<?php
// tg/setwebhook.php
require_once 'config.php';

$webhook_url = "https://zippaket-optom.ru/tg/bot.php";
$url = "https://api.telegram.org/bot" . BOT_TOKEN . "/setWebhook";

$data = ['url' => $webhook_url];
$options = [
    'http' => [
        'header' => "Content-Type: application/x-www-form-urlencoded\r\n",
        'method' => 'POST',
        'content' => http_build_query($data)
    ]
];

$context = stream_context_create($options);
$result = file_get_contents($url, false, $context);

echo "<pre>";
echo "Webhook URL: {$webhook_url}\n";
echo "Result:\n";
print_r(json_decode($result, true));
echo "</pre>";
?>