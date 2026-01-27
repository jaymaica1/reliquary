<?php

namespace App\Monolog;

use App\Service\AccessLogPIIProtection;
use Monolog\LogRecord;
use Monolog\Processor\ProcessorInterface;

/**
 * Monolog processor to mask PII in log records as a safety net
 */
class LogPIIProcessor implements ProcessorInterface
{
    private AccessLogPIIProtection $piiProtection;

    public function __construct(AccessLogPIIProtection $piiProtection)
    {
        $this->piiProtection = $piiProtection;
    }

    public function __invoke(LogRecord $record): LogRecord
    {
        $context = $record->context;
        $extra = $record->extra;

        if (!empty($context)) {
            $context = $this->maskPII($context);
        }

        if (!empty($extra)) {
            $extra = $this->maskPII($extra);
        }

        return $record->with(context: $context, extra: $extra);
    }

    private function maskPII(array $data): array
    {
        foreach ($data as $key => $value) {
            if (is_array($value)) {
                $data[$key] = $this->maskPII($value);
                continue;
            }

            if (!is_string($value)) {
                continue;
            }

            $keyLower = strtolower((string)$key);

            // IP Addresses
            if ($keyLower === 'ip' || $keyLower === 'ip_address' || $keyLower === 'client_ip') {
                $data[$key] = $this->piiProtection->protectIpAddress($value);
                continue;
            }

            // Emails
            if ($keyLower === 'email' || $keyLower === 'user_email' || str_contains($keyLower, 'email')) {
                // Email is sensitive, we should hash or mask it
                // AccessLogPIIProtection doesn't have a specific protectEmail, but we can use maskValue
                $data[$key] = $this->maskEmail($value);
                continue;
            }

            // Session IDs
            if ($keyLower === 'session_id' || $keyLower === 'session' || $keyLower === 'phpsessid') {
                $data[$key] = $this->piiProtection->protectSessionId($value);
                continue;
            }
        }

        return $data;
    }

    private function maskEmail(string $email): string
    {
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return '***@***.***';
        }

        $parts = explode('@', $email);
        $name = $parts[0];
        $domain = $parts[1];

        $maskedName = mb_substr($name, 0, 1) . str_repeat('*', max(1, mb_strlen($name) - 1));
        
        return $maskedName . '@' . $domain;
    }
}
