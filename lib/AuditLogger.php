<?php
/**
 * AuditLogger - Logs to both database and audit.log file
 */

class AuditLogger
{
    private static $pdo = null;
    private static $loggers = [];
    private static $logFile = null;
    private $channel;

    public static function setPdo(PDO $pdo): void
    {
        self::$pdo = $pdo;
    }

    public static function setLogFile(string $path): void
    {
        self::$logFile = $path;
        
        // Ensure directory exists
        $dir = dirname($path);
        if ($dir && !is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
    }

    public static function getLogger(string $channel = 'app'): self
    {
        if (!isset(self::$loggers[$channel])) {
            self::$loggers[$channel] = new self($channel);
        }
        return self::$loggers[$channel];
    }

    private function __construct(string $channel)
    {
        $this->channel = $channel;
    }

    public function info(string $msg, array $context = []) { $this->log('info', $msg, $context); }
    public function warning(string $msg, array $context = []) { $this->log('warning', $msg, $context); }
    public function error(string $msg, array $context = []) { $this->log('error', $msg, $context); }
    public function critical(string $msg, array $context = []) { $this->log('critical', $msg, $context); }

    public function log(string $level, string $message, array $context = []): void
    {
        $meta = [
            'user_id'     => $_SESSION['user_id'] ?? null,
            'username'    => $_SESSION['username'] ?? null,
            'ip_address'  => $_SERVER['REMOTE_ADDR'] ?? null,
            'request_uri' => $_SERVER['REQUEST_URI'] ?? null,
            'php_sapi'    => PHP_SAPI
        ];

        $fullContext = array_merge($meta, $context);
        $timestamp = date('Y-m-d H:i:s');

        // 1. Write to DATABASE
        if (self::$pdo instanceof PDO) {
            try {
                $stmt = self::$pdo->prepare("
                    INSERT INTO audit_logs (
                        created_at, channel, level, message,
                        context_json, user_id, username, ip_address, request_uri
                    ) VALUES (
                        NOW(), :channel, :level, :message,
                        :context_json, :user_id, :username, :ip_address, :request_uri
                    )
                ");
                $stmt->execute([
                    ':channel'      => $this->channel,
                    ':level'        => $level,
                    ':message'      => $message,
                    ':context_json' => json_encode($fullContext),
                    ':user_id'      => $fullContext['user_id'],
                    ':username'     => $fullContext['username'],
                    ':ip_address'   => $fullContext['ip_address'],
                    ':request_uri'  => $fullContext['request_uri']
                ]);
            } catch (Throwable $e) {
                error_log("AuditLogger DB error: " . $e->getMessage());
            }
        }

        // 2. Write to FILE (audit.log)
        $this->writeToFile($timestamp, $level, $message, $fullContext);

        // 3. Also log to PHP error log (optional, for debugging)
        $short = "[AUDIT][{$this->channel}][{$level}] $message";
        error_log($short);
    }

    /**
     * Write log entry to audit.log file
     */
    private function writeToFile(string $timestamp, string $level, string $message, array $context): void
    {
        // Default log file path if not set
        if (self::$logFile === null) {
            self::$logFile = __DIR__ . '/../logs/audit.log';
            
            // Ensure directory exists
            $dir = dirname(self::$logFile);
            if ($dir && !is_dir($dir)) {
                mkdir($dir, 0755, true);
            }
        }

        try {
            // Format: [timestamp] [channel] [LEVEL] [user] [ip] message | context_json
            $username = $context['username'] ?? 'anonymous';
            $ip = $context['ip_address'] ?? 'unknown';
            $uri = $context['request_uri'] ?? '';
            
            // Compact context (remove redundant fields for file)
            $fileContext = array_filter($context, function($key) {
                return !in_array($key, ['user_id', 'username', 'ip_address', 'request_uri', 'php_sapi']);
            }, ARRAY_FILTER_USE_KEY);
            
            $contextJson = !empty($fileContext) ? json_encode($fileContext, JSON_UNESCAPED_SLASHES) : '';
            
            // Build log line
            $logLine = sprintf(
                "[%s] [%s] [%s] [%s] [%s] %s",
                $timestamp,
                $this->channel,
                strtoupper($level),
                $username,
                $ip,
                $message
            );
            
            if ($contextJson) {
                $logLine .= " | " . $contextJson;
            }
            
            $logLine .= PHP_EOL;
            
            // Append to file
            file_put_contents(self::$logFile, $logLine, FILE_APPEND | LOCK_EX);
            
        } catch (Throwable $e) {
            error_log("AuditLogger file write error: " . $e->getMessage());
        }
    }
}
