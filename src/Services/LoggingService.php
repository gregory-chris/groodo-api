<?php
declare(strict_types=1);

namespace App\Services;

use Monolog\Logger;
use Monolog\Handler\StreamHandler;
use Monolog\Handler\RotatingFileHandler;
use Monolog\Formatter\LineFormatter;
use Psr\Log\LoggerInterface;

class LoggingService
{
    private LoggerInterface $logger;

    public function __construct(LoggerInterface $logger)
    {
        $this->logger = $logger;
    }

    public static function createLogger(): LoggerInterface
    {
        $config = require __DIR__ . '/../../config/logging.php';
        
        // Ensure log directory exists
        $logDir = dirname($config['path']);
        if (!is_dir($logDir)) {
            mkdir($logDir, 0755, true);
        }

        $logger = new Logger('groodo-api');

        // Create rotating file handler
        $handler = new RotatingFileHandler(
            $config['path'],
            $config['max_files'],
            $config['level']
        );

        // Set custom formatter
        $formatter = new LineFormatter(
            $config['format'],
            $config['date_format'],
            true, // Allow inline line breaks
            true  // Ignore empty context and extra
        );
        $handler->setFormatter($formatter);

        $logger->pushHandler($handler);

        return $logger;
    }

    public function logRequest(string $method, string $uri, ?int $userId = null, array $context = []): void
    {
        $this->logger->info('HTTP Request received', array_merge([
            'method' => $method,
            'uri' => $uri,
            'user_id' => $userId,
            'timestamp' => date('c'),
        ], $context));
    }

    public function logResponse(int $statusCode, float $executionTime, array $context = []): void
    {
        $this->logger->info('HTTP Response sent', array_merge([
            'status_code' => $statusCode,
            'execution_time_ms' => round($executionTime * 1000, 2),
            'timestamp' => date('c'),
        ], $context));
    }

    public function logAuthAttempt(string $email, bool $success, string $reason = '', array $context = []): void
    {
        $level = $success ? 'info' : 'warning';
        $message = $success ? 'Authentication successful' : 'Authentication failed';
        
        $this->logger->log($level, $message, array_merge([
            'email' => $email,
            'success' => $success,
            'reason' => $reason,
            'timestamp' => date('c'),
        ], $context));
    }

    public function logEmailSent(string $to, string $subject, bool $success, string $error = ''): void
    {
        $level = $success ? 'info' : 'error';
        $message = $success ? 'Email sent successfully' : 'Email sending failed';
        
        $this->logger->log($level, $message, [
            'to' => $to,
            'subject' => $subject,
            'success' => $success,
            'error' => $error,
            'timestamp' => date('c'),
        ]);
    }

    public function logBusinessLogic(string $operation, array $data, bool $success, string $error = ''): void
    {
        $level = $success ? 'info' : 'error';
        $message = $success ? "Business operation completed: {$operation}" : "Business operation failed: {$operation}";
        
        $this->logger->log($level, $message, [
            'operation' => $operation,
            'data' => $this->sanitizeLogData($data),
            'success' => $success,
            'error' => $error,
            'timestamp' => date('c'),
        ]);
    }

    public function logSecurityEvent(string $event, string $severity, array $context = []): void
    {
        $level = match($severity) {
            'low' => 'info',
            'medium' => 'warning',
            'high' => 'error',
            'critical' => 'critical',
            default => 'warning'
        };

        $this->logger->log($level, "Security event: {$event}", array_merge([
            'severity' => $severity,
            'timestamp' => date('c'),
        ], $context));
    }

    private function sanitizeLogData(array $data): array
    {
        $sanitized = [];
        foreach ($data as $key => $value) {
            // Don't log sensitive data
            if (is_string($key) && (
                stripos($key, 'password') !== false ||
                stripos($key, 'token') !== false ||
                stripos($key, 'secret') !== false
            )) {
                $sanitized[$key] = '[REDACTED]';
            } elseif (is_array($value)) {
                $sanitized[$key] = $this->sanitizeLogData($value);
            } else {
                $sanitized[$key] = $value;
            }
        }
        return $sanitized;
    }
}
