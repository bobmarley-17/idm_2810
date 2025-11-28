<?php
// lib/LogHelper.php
require_once __DIR__ . '/AuditLogger.php';

class LogHelper {
    
    // Get common context for all logs
    private static function getContext($additionalContext = []) {
        $baseContext = [
            'user_id' => $_SESSION['user_id'] ?? null,
            'username' => $_SESSION['username'] ?? 'anonymous',
            'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
            'user_agent' => substr($_SERVER['HTTP_USER_AGENT'] ?? 'unknown', 0, 100)
        ];
        return array_merge($baseContext, $additionalContext);
    }
    
    // Log authentication events
    public static function logAuth($action, $username, $success = true, $details = []) {
        $logger = AuditLogger::getLogger('auth');
        $context = self::getContext(array_merge([
            'action' => $action,
            'target_user' => $username,
            'success' => $success
        ], $details));
        
        if ($success) {
            $logger->info($action, $context);
        } else {
            $logger->warning($action . ' failed', $context);
        }
    }
    
    // Log user management actions
    public static function logUserAction($action, $targetUserId, $details = []) {
        $logger = AuditLogger::getLogger('users');
        $context = self::getContext(array_merge([
            'action' => $action,
            'target_user_id' => $targetUserId
        ], $details));
        
        $logger->info($action, $context);
    }
    
    // Log data access
    public static function logDataAccess($resource, $action, $details = []) {
        $logger = AuditLogger::getLogger('access');
        $context = self::getContext(array_merge([
            'resource' => $resource,
            'action' => $action
        ], $details));
        
        $logger->info("Data access: {$resource} - {$action}", $context);
    }
    
    // Log sync operations
    public static function logSync($action, $sourceId, $details = []) {
        $logger = AuditLogger::getLogger('sync');
        $context = self::getContext(array_merge([
            'action' => $action,
            'source_id' => $sourceId
        ], $details));
        
        $logger->info($action, $context);
    }
    
    // Log report generation
    public static function logReport($reportType, $details = []) {
        $logger = AuditLogger::getLogger('reports');
        $context = self::getContext(array_merge([
            'report_type' => $reportType
        ], $details));
        
        $logger->info("Report generated: {$reportType}", $context);
    }
    
    // Log configuration changes
    public static function logConfig($action, $details = []) {
        $logger = AuditLogger::getLogger('config');
        $context = self::getContext(array_merge([
            'action' => $action
        ], $details));
        
        $logger->warning("Configuration change: {$action}", $context);
    }
    
    // Log security events
    public static function logSecurity($event, $severity = 'warning', $details = []) {
        $logger = AuditLogger::getLogger('security');
        $context = self::getContext($details);
        
        if ($severity === 'critical') {
            $logger->critical($event, $context);
        } elseif ($severity === 'error') {
            $logger->error($event, $context);
        } else {
            $logger->warning($event, $context);
        }
    }
    
    // Log errors
    public static function logError($message, $details = []) {
        $logger = AuditLogger::getLogger('errors');
        $context = self::getContext($details);
        $logger->error($message, $context);
    }
    
    // Log database changes
    public static function logDatabaseChange($table, $action, $recordId, $changes = []) {
        $logger = AuditLogger::getLogger('database');
        $context = self::getContext(array_merge([
            'table' => $table,
            'action' => $action,
            'record_id' => $recordId,
            'changes' => $changes
        ]));
        
        $logger->info("Database {$action}: {$table}#{$recordId}", $context);
    }
}
