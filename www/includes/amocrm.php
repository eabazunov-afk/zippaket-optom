<?php
/**
 * Интеграция с AmoCRM
 */

require_once 'config.php';

class AmoCRM {
    private $accessToken;
    private $domain;
    
    // ID полей в AmoCRM - обновленные на основе ваших данных
    private $fieldIds = [
        'name' => 'name',
        'phone' => 1154580,           // Телефон контакта
        'email' => 1154582,           // Email контакта
        'position' => 1154578,        // Должность
        'source_phone' => 1154628,    // Source phone контакта
        'source_phone_lead' => 1154626, // Source phone сделки
        
        // UTM поля сделки (tracking_data)
        'utm_source' => 1154594,
        'utm_medium' => 1154590,
        'utm_campaign' => 1154592,
        'utm_content' => 1154588,
        'utm_term' => 1154596,
        'utm_referrer' => 1154598,
        
        // UTM поля сделки (text)
        'utm_source_text' => 1154684,
        'utm_medium_text' => 1154692,
        'utm_campaign_text' => 1154686,
        'utm_content_text' => 1154688,
        'utm_term_text' => 1154690,
        
        'gclid' => 1154620,
        'yclid' => 1156212,           // yclid из ваших данных
        'fbclid' => 1154624,
    ];
    
    private $pipelineId = 10572526;
    private $statusId = 83387862;     // Первичный контакт
    
    public function __construct() {
        if (!defined('AMOCRM_DOMAIN') || !defined('AMOCRM_ACCESS_TOKEN')) {
            throw new Exception('AmoCRM credentials not configured');
        }
        
        $this->domain = AMOCRM_DOMAIN;
        $this->accessToken = AMOCRM_ACCESS_TOKEN;
        
        error_log("AmoCRM: Initialized with domain: {$this->domain}");
    }
    
    /**
     * Отправка лида в AmoCRM
     */
    public function sendLead($data) {
        try {
            error_log("==========================================");
            error_log("AmoCRM: Sending lead data. Type: " . ($data['type'] ?? 'unknown') . ", Name: " . ($data['name'] ?? 'unknown'));
            error_log("AmoCRM: Полные данные заявки:");
            error_log("Тип: " . ($data['type'] ?? 'не указано'));
            error_log("Источник: " . ($data['source'] ?? 'website'));
            error_log("Имя: " . ($data['name'] ?? 'не указано'));
            error_log("Телефон: " . ($data['phone'] ?? 'не указано'));
            error_log("Email: " . ($data['email'] ?? 'не указано'));
            error_log("Сообщение: " . ($data['message'] ?? 'не указано'));
            
            if (isset($data['parameters'])) {
                error_log("Параметры тип: " . gettype($data['parameters']));
                if (is_string($data['parameters'])) {
                    error_log("Параметры (строка): " . substr($data['parameters'], 0, 500));
                } else {
                    error_log("Параметры (массив): " . json_encode($data['parameters'], JSON_UNESCAPED_UNICODE));
                }
            }
            
            // Создаем сделку
            $leadId = $this->createLead($data);
            
            if ($leadId) {
                error_log("AmoCRM: Lead created with ID: $leadId");
                
                // Создаем контакт
                $contactId = $this->createContact($data);
                
                if ($contactId) {
                    error_log("AmoCRM: Contact created with ID: $contactId");
                    // Привязываем контакт к сделке
                    $this->linkContactToLead($leadId, $contactId);
                } else {
                    error_log("AmoCRM: Failed to create contact");
                }
                
                // Добавляем примечание с деталями заказа
                $this->addNote($leadId, $data);
                
                return $leadId;
            } else {
                error_log("AmoCRM: Failed to create lead");
            }
            
            return false;
        } catch (Exception $e) {
            error_log("AmoCRM Error: " . $e->getMessage());
            error_log("AmoCRM Trace: " . $e->getTraceAsString());
            return false;
        }
    }
    
    /**
     * Создание сделки
     */
    private function createLead($data) {
        $leadName = $this->generateLeadName($data);
        
        $leadData = [
            [
                'name' => $leadName,
                'pipeline_id' => $this->pipelineId,
                'status_id' => $this->statusId,
                'price' => 0,
                'custom_fields_values' => [],
                'tags' => $this->generateTags($data),
                'metadata' => [
                    'form_id' => $data['source'] ?? 'website',
                    'form_type' => $data['type'] ?? 'contact_form'
                ]
            ]
        ];
        
        // Добавляем UTM метки
        $this->addUTMFields($leadData[0], $data);
        
        // Добавляем source_phone в сделку если есть телефон
        if (!empty($data['phone'])) {
            $leadData[0]['custom_fields_values'][] = [
                'field_id' => $this->fieldIds['source_phone_lead'],
                'values' => [
                    ['value' => $this->cleanPhone($data['phone'])]
                ]
            ];
        }
        
        error_log("AmoCRM: Sending lead data: " . json_encode($leadData, JSON_UNESCAPED_UNICODE));
        
        $response = $this->sendRequest('/api/v4/leads', 'POST', $leadData);
        
        if ($response && isset($response['_embedded']['leads'][0]['id'])) {
            $leadId = $response['_embedded']['leads'][0]['id'];
            error_log("AmoCRM: Lead created successfully with ID: $leadId");
            return $leadId;
        } else {
            if ($response && isset($response['validation-errors'])) {
                error_log("AmoCRM: Validation errors: " . json_encode($response['validation-errors'], JSON_UNESCAPED_UNICODE));
            }
            error_log("AmoCRM: Failed to create lead. Response: " . json_encode($response, JSON_UNESCAPED_UNICODE));
        }
        
        return false;
    }
    
    /**
     * Добавление UTM полей
     */
    private function addUTMFields(&$leadData, $data) {
        if (!empty($data['parameters'])) {
            $params = is_string($data['parameters']) ? json_decode($data['parameters'], true) : $data['parameters'];
            
            if (is_array($params)) {
                // Добавляем tracking_data UTM поля
                $trackingFields = [
                    'utm_source' => $params['utm_source'] ?? null,
                    'utm_medium' => $params['utm_medium'] ?? null,
                    'utm_campaign' => $params['utm_campaign'] ?? null,
                    'utm_content' => $params['utm_content'] ?? null,
                    'utm_term' => $params['utm_term'] ?? null,
                ];
                
                foreach ($trackingFields as $fieldKey => $value) {
                    if (!empty($value) && isset($this->fieldIds[$fieldKey])) {
                        $leadData['custom_fields_values'][] = [
                            'field_id' => $this->fieldIds[$fieldKey],
                            'values' => [
                                ['value' => $value]
                            ]
                        ];
                    }
                }
                
                // Добавляем текстовые UTM поля
                $textFields = [
                    'utm_source_text' => $params['utm_source'] ?? null,
                    'utm_medium_text' => $params['utm_medium'] ?? null,
                    'utm_campaign_text' => $params['utm_campaign'] ?? null,
                    'utm_content_text' => $params['utm_content'] ?? null,
                    'utm_term_text' => $params['utm_term'] ?? null,
                    'yclid' => $params['yclid'] ?? null,
                ];
                
                foreach ($textFields as $fieldKey => $value) {
                    if (!empty($value) && isset($this->fieldIds[$fieldKey])) {
                        $leadData['custom_fields_values'][] = [
                            'field_id' => $this->fieldIds[$fieldKey],
                            'values' => [
                                ['value' => $value]
                            ]
                        ];
                    }
                }
            }
        }
    }
    
    /**
     * Создание контакта
     */
    private function createContact($data) {
        $contactData = [
            [
                'name' => $data['name'] ?? 'Клиент с сайта',
                'custom_fields_values' => []
            ]
        ];
        
        // Телефон
        if (!empty($data['phone'])) {
            $contactData[0]['custom_fields_values'][] = [
                'field_id' => $this->fieldIds['phone'],
                'values' => [
                    [
                        'value' => $this->cleanPhone($data['phone']),
                        'enum_code' => 'WORK'
                    ]
                ]
            ];
            
            // Также добавляем source_phone в контакт
            $contactData[0]['custom_fields_values'][] = [
                'field_id' => $this->fieldIds['source_phone'],
                'values' => [
                    ['value' => $this->cleanPhone($data['phone'])]
                ]
            ];
        }
        
        // Email
        if (!empty($data['email'])) {
            $contactData[0]['custom_fields_values'][] = [
                'field_id' => $this->fieldIds['email'],
                'values' => [
                    [
                        'value' => $data['email'],
                        'enum_code' => 'WORK'
                    ]
                ]
            ];
        }
        
        // Позиция (должность) если есть
        if (!empty($data['parameters']['position']) || !empty($data['position'])) {
            $position = $data['parameters']['position'] ?? $data['position'] ?? '';
            if (!empty($position)) {
                $contactData[0]['custom_fields_values'][] = [
                    'field_id' => $this->fieldIds['position'],
                    'values' => [
                        ['value' => $position]
                    ]
                ];
            }
        }
        
        $response = $this->sendRequest('/api/v4/contacts', 'POST', $contactData);
        
        if ($response && isset($response['_embedded']['contacts'][0]['id'])) {
            $contactId = $response['_embedded']['contacts'][0]['id'];
            error_log("AmoCRM: Contact created successfully with ID: $contactId");
            return $contactId;
        } else {
            error_log("AmoCRM: Failed to create contact. Response: " . json_encode($response, JSON_UNESCAPED_UNICODE));
        }
        
        return false;
    }
    
    /**
     * Привязка контакта к сделке
     */
    private function linkContactToLead($leadId, $contactId) {
        $linkData = [
            [
                'to_entity_id' => $contactId,
                'to_entity_type' => 'contacts'
            ]
        ];
        
        $response = $this->sendRequest("/api/v4/leads/$leadId/link", 'POST', $linkData);
        
        if ($response) {
            error_log("AmoCRM: Contact $contactId linked to lead $leadId");
            return true;
        } else {
            error_log("AmoCRM: Failed to link contact to lead");
            return false;
        }
    }
    
    /**
     * Добавление примечания
     */
    private function addNote($leadId, $data) {
        $noteText = $this->generateNoteText($data);
        
        $noteData = [
            [
                'entity_id' => $leadId,
                'note_type' => 'common',
                'params' => [
                    'text' => $noteText
                ]
            ]
        ];
        
        $response = $this->sendRequest('/api/v4/leads/notes', 'POST', $noteData);
        
        if ($response) {
            error_log("AmoCRM: Note added to lead $leadId");
            return true;
        } else {
            error_log("AmoCRM: Failed to add note to lead $leadId");
            return false;
        }
    }
    






private function generateNoteText($data) {
    $text = "📋 Заявка с сайта\n\n";
    $text .= "👤 Имя: " . ($data['name'] ?? 'Не указано') . "\n";
    $text .= "📞 Телефон: " . ($data['phone'] ?? 'Не указан') . "\n";
    
    if (!empty($data['email'])) {
        $text .= "📧 Email: " . $data['email'] . "\n";
    }
    
    $text .= "\n🎯 Тип заявки: " . ($data['type'] ?? 'contact_form');
    $text .= "\n🔗 Источник: " . ($data['source'] ?? 'website');
    
    // Добавляем детали из параметров
    if (!empty($data['parameters'])) {
        $params = is_string($data['parameters']) ? json_decode($data['parameters'], true) : $data['parameters'];
        
        if (is_array($params)) {
            // Добавляем данные из калькулятора если есть
            if (isset($params['calculation_data'])) {
                $calcData = $params['calculation_data'];
                $text .= "\n\n🧮 ПАРАМЕТРЫ РАСЧЁТА:\n";
                if (isset($calcData['type'])) $text .= "Тип пакета: " . $calcData['type'] . "\n";
                if (isset($calcData['size'])) $text .= "Размер: " . $calcData['size'] . "\n";
                if (isset($calcData['thickness'])) $text .= "Толщина: " . $calcData['thickness'] . "\n";
                if (isset($calcData['material'])) $text .= "Материал: " . $calcData['material'] . "\n";
                if (isset($calcData['quantity'])) $text .= "Тираж: " . $calcData['quantity'] . "\n";
                if (isset($calcData['estimated_price'])) $text .= "Стоимость: " . $calcData['estimated_price'] . "\n";
            }
            
            // Также показываем сырые данные расчета если есть
            if (isset($params['width']) && isset($params['height'])) {
                $text .= "Размер (сырые данные): " . $params['width'] . "×" . $params['height'] . " см\n";
            }
            if (isset($params['quantity'])) {
                $text .= "Количество: " . number_format($params['quantity'], 0, '', ' ') . " шт\n";
            }
            if (isset($params['total_price'])) {
                $text .= "Общая стоимость: " . number_format($params['total_price'], 2, ',', ' ') . " ₽\n";
            }
            
            // Остальной код остается без изменений...
            if (isset($params['traffic_type']) || isset($params['visit_id'])) {
                $text .= "\n🌐 ИНФОРМАЦИЯ О ТРАФИКЕ:\n";
                if (isset($params['traffic_type'])) {
                    $text .= "Тип трафика: " . $params['traffic_type'] . "\n";
                }
                if (isset($params['traffic_source'])) {
                    $text .= "Источник: " . $params['traffic_source'] . "\n";
                }
                if (isset($params['visit_id'])) {
                    $text .= "Визит ID: " . $params['visit_id'] . "\n";
                }
            }
            
            // Показываем UTM параметры
            $utmShown = false;
            $utmFields = ['utm_source', 'utm_medium', 'utm_campaign', 'utm_term', 'utm_content'];
            foreach ($utmFields as $field) {
                if (!empty($params[$field])) {
                    if (!$utmShown) {
                        $text .= "\n🎯 UTM МЕТКИ:\n";
                        $utmShown = true;
                    }
                    $text .= ucfirst(str_replace('utm_', '', $field)) . ": " . $params[$field] . "\n";
                }
            }
            
            // Показываем товары из корзины если есть
            if (isset($params['cart_items']) && is_array($params['cart_items'])) {
                $text .= "\n🛒 ТОВАРЫ В ЗАЯВКЕ:\n";
                $totalItems = 0;
                foreach ($params['cart_items'] as $index => $item) {
                    $text .= ($index + 1) . ". " . ($item['type'] ?? 'Товар') . "\n";
                    $text .= "   Размер: " . ($item['size'] ?? 'не указан') . "\n";
                    $text .= "   Толщина: " . ($item['thickness'] ?? 'не указана') . "\n";
                    $text .= "   Количество: " . number_format($item['quantity'] ?? 0, 0, '', ' ') . " шт\n";
                    $text .= "   Цена опт: " . ($item['prices']['opt300k'] ?? '0') . " ₽/шт\n\n";
                    $totalItems += $item['quantity'] ?? 0;
                }
                $text .= "📦 Всего товаров: " . number_format($totalItems, 0, '', ' ') . " шт\n";
            }
            
            // Показываем комментарий/сообщение клиента - БЕЗ ДУБЛИРОВАНИЯ
            $comment = '';
            if (!empty($params['comment'])) {
                $comment = $params['comment'];
            } elseif (!empty($data['message'])) {
                $comment = $data['message'];
            }
            
            if (!empty($comment)) {
                $text .= "\n💬 Сообщение клиента:\n" . $comment . "\n";
            }
        }
    }
    
    $text .= "\n⏰ Дата: " . date('d.m.Y H:i:s');
    $text .= "\n🔧 Отправлено через API сайта";
    
    return $text;
}






















    
    /**
     * Генерация имени сделки
     */
    private function generateLeadName($data) {
        $name = $data['name'] ?? 'Клиент';
        $type = $data['type'] ?? 'contact_form';
        
        $typeNames = [
            'contact_form' => 'Заявка с сайта',
            'callback' => 'Заказ звонка',
            'offer_request' => 'Запрос КП',
            'cart' => 'Заявка из корзины',
            'calculator' => 'Расчет калькулятора'
        ];
        
        $leadType = $typeNames[$type] ?? 'Заявка';
        return "$leadType от $name";
    }
    
    /**
     * Генерация тегов
     */
    private function generateTags($data) {
        $tags = ['Сайт', date('Y-m-d')];
        
        // Добавляем тег по типу заявки
        $type = $data['type'] ?? '';
        if ($type === 'cart') {
            $tags[] = 'Корзина';
        } elseif ($type === 'calculator' || $type === 'offer_request') {
            $tags[] = 'Калькулятор';
        } elseif ($type === 'callback') {
            $tags[] = 'Звонок';
        }
        
        // Добавляем тег по источнику
        $source = $data['source'] ?? '';
        if ($source === 'main_form') {
            $tags[] = 'Главная форма';
        } elseif ($source === 'modal') {
            $tags[] = 'Модальное окно';
        } elseif ($source === 'calculator') {
            $tags[] = 'Калькулятор';
        }
        
        return $tags;
    }
    
    /**
     * Очистка телефона
     */
    private function cleanPhone($phone) {
        $phone = preg_replace('/[^0-9]/', '', $phone);
        
        if (strlen($phone) === 11 && $phone[0] === '8') {
            $phone = '7' . substr($phone, 1);
        }
        
        if (strlen($phone) === 10) {
            $phone = '7' . $phone;
        }
        
        return $phone;
    }
    
    /**
     * Отправка запроса к API
     */
    private function sendRequest($endpoint, $method = 'GET', $data = null) {
        $url = "https://{$this->domain}{$endpoint}";
        
        $headers = [
            'Authorization: Bearer ' . $this->accessToken,
            'Content-Type: application/json'
        ];
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        
        if ($method === 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
            if ($data) {
                $jsonData = json_encode($data, JSON_UNESCAPED_UNICODE);
                curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonData);
                error_log("AmoCRM: Отправка данных: " . $jsonData);
            }
        } elseif ($method === 'PATCH') {
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PATCH');
            if ($data) {
                curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data, JSON_UNESCAPED_UNICODE));
            }
        }
        
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        
        // Убираем curl_close() так как он deprecated в PHP 8.5+
        // curl_close($ch);
        
        error_log("AmoCRM Response [$httpCode] for $endpoint");
        
        if ($httpCode >= 400) {
            error_log("AmoCRM Error Response: " . $response);
            
            // Детализация ошибок
            if ($httpCode === 401) {
                error_log("AmoCRM: Ошибка авторизации! Проверьте токен доступа.");
            } elseif ($httpCode === 403) {
                error_log("AmoCRM: Доступ запрещен! Проверьте права токена.");
            } elseif ($httpCode === 404) {
                error_log("AmoCRM: Ресурс не найден! Проверьте URL: $url");
            } elseif ($httpCode === 422) {
                error_log("AmoCRM: Ошибка валидации данных. Проверьте структуру отправляемых данных.");
            }
        }
        
        if (curl_errno($ch)) {
            error_log("AmoCRM CURL Error: " . curl_error($ch));
            return false;
        }
        
        return json_decode($response, true);
    }
}

/**
 * Функция для отправки в AmoCRM
 */
function sendToAmoCRM($data) {
    try {
        error_log("sendToAmoCRM: Starting...");
        error_log("sendToAmoCRM: Данные для отправки: " . json_encode($data, JSON_UNESCAPED_UNICODE));
        
        $amoCRM = new AmoCRM();
        $result = $amoCRM->sendLead($data);
        error_log("sendToAmoCRM: Result: " . ($result ? "Success, ID: $result" : "Failed"));
        return $result;
    } catch (Exception $e) {
        error_log("sendToAmoCRM Error: " . $e->getMessage());
        error_log("sendToAmoCRM Trace: " . $e->getTraceAsString());
        return false;
    }
}
?>