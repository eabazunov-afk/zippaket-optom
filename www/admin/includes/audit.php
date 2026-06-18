<?php
// /admin/includes/audit.php

// Проверяем, не объявлена ли уже функция logAction
if (!function_exists('logAdminAction')) {
    /**
     * Логирование действий администратора
     */
    function logAdminAction($action, $entity_type = null, $entity_id = null, $details = null) {
        try {
            $db = getDbConnection();
            
            // Проверяем существование таблицы
            $stmt = $db->query("SHOW TABLES LIKE 'admin_audit_log'");
            $table_exists = $stmt->fetch();
            
            if ($table_exists) {
                $stmt = $db->prepare("
                    INSERT INTO admin_audit_log 
                    (admin_id, action, entity_type, entity_id, details, ip_address, user_agent) 
                    VALUES (?, ?, ?, ?, ?, ?, ?)
                ");
                
                $stmt->execute([
                    $_SESSION['admin_id'] ?? 0,
                    $action,
                    $entity_type,
                    $entity_id,
                    $details ? json_encode($details, JSON_UNESCAPED_UNICODE) : null,
                    $_SERVER['REMOTE_ADDR'],
                    $_SERVER['HTTP_USER_AGENT'] ?? ''
                ]);
            }
            
            // Всегда логируем в файл для надежности
            return logAdminActionToFile($action, $entity_type, $entity_id, $details);
            
        } catch (Exception $e) {
            // В случае ошибки БД логируем только в файл
            error_log("Admin audit DB error: " . $e->getMessage());
            return logAdminActionToFile($action, $entity_type, $entity_id, $details);
        }
    }
}

if (!function_exists('logAdminActionToFile')) {
    /**
     * Логирование действий администратора в файл
     */
    function logAdminActionToFile($action, $entity_type = null, $entity_id = null, $details = null) {
        $log_entry = sprintf(
            "[%s] ADMIN_AUDIT: %s | AdminID: %s | User: %s | Entity: %s:%s | Details: %s | IP: %s | Agent: %s\n",
            date('Y-m-d H:i:s'),
            $action,
            $_SESSION['admin_id'] ?? 0,
            $_SESSION['admin_username'] ?? 'unknown',
            $entity_type ?? '',
            $entity_id ?? '',
            $details ? json_encode($details, JSON_UNESCAPED_UNICODE) : '{}',
            $_SERVER['REMOTE_ADDR'],
            substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 100)
        );
        
        // Логируем в файл
        $log_dir = dirname(__DIR__) . '/logs/';
        if (!file_exists($log_dir)) {
            mkdir($log_dir, 0755, true);
        }
        
        $log_file = $log_dir . 'admin_audit.log';
        @file_put_contents($log_file, $log_entry, FILE_APPEND | LOCK_EX);
        
        // Также логируем в системный лог
        error_log("Admin audit: " . $action . " by " . ($_SESSION['admin_username'] ?? 'unknown'));
        
        return true;
    }
}