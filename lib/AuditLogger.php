<?php
// In lib/AuditLogger.php

// This line is crucial! It loads Monolog and makes it available to our script.
require_once __DIR__ . '/../vendor/autoload.php';

use Monolog\Logger;
use Monolog\Handler\StreamHandler;
use Monolog\Formatter\JsonFormatter;
use Monolog\Processor\WebProcessor;

class AuditLogger {
    private static $logger = null;

    // A private constructor prevents anyone from creating a new instance with 'new AuditLogger()'.
    private function __construct() {}

    /**
     * This is the main function you will call from other files.
     * It uses the "Singleton" pattern to ensure only one logger instance is ever created.
     */
    public static function getLogger() {
        if (self::$logger === null) {
            // 1. Create the logger instance
            // The name 'IDM_AUDIT' will appear in each log entry to identify its source.
            self::$logger = new Logger('IDM_AUDIT');

            // 2. Create a "Handler" to tell the logger WHERE to send the logs.
            // We'll log everything with a level of INFO or higher to a file named 'audit.log'
            // in the project's root directory.
            $logFile = __DIR__ . '/../audit.log';
            $stream = new StreamHandler($logFile, Logger::INFO);

            // 3. (Highly Recommended) Create a "Formatter" to define HOW the logs look.
            // JSON format is excellent for auditing because it's structured and easy to parse later.
            $formatter = new JsonFormatter();
            $stream->setFormatter($formatter);

            // 4. Push the configured handler to the logger.
            self::$logger->pushHandler($stream);
            
            // 5. (Optional but useful) Add a "Processor" to automatically include data
            // like IP address, URL, and HTTP method in every log record.
            self::$logger->pushProcessor(new WebProcessor());
        }

        return self::$logger;
    }

    /**
     * A helper function to build a standard array of context data for our audit logs.
     * This ensures consistency across all log messages.
     * 
     * @param string|null $target_entity The user, role, or resource being acted upon (e.g., 'john.doe').
     * @param array $extra_data Any additional key-value data to include.
     * @return array The complete context array.
     */
    public static function buildContext($target_entity = null, $extra_data = []) {
        // Start with the user performing the action (the "actor").
        // We assume their details are stored in the PHP session after they log in.
        $context = [
            'actor_username' => $_SESSION['username'] ?? 'UNKNOWN_OR_SYSTEM',
            'actor_id'       => $_SESSION['user_id'] ?? 'UNKNOWN_OR_SYSTEM',
        ];

        // If a target was specified, add it.
        if ($target_entity) {
            $context['target_entity'] = $target_entity;
        }
        
        // Merge any extra, specific data provided for this particular log event.
        return array_merge($context, $extra_data);
    }
}
