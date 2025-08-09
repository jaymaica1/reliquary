<?php

namespace App\Service;

/**
 * Service for protecting PII (Personally Identifiable Information) in access logs
 * Provides environment-aware masking and anonymization capabilities
 */
class AccessLogPIIProtection
{
    private string $protectionLevel;
    private array $fieldProtection;
    private string $environment;

    public function __construct(
        string $protectionLevel,
        array $fieldProtection,
        string $environment
    ) {
        $this->protectionLevel = $protectionLevel;
        $this->fieldProtection = $fieldProtection;
        $this->environment = $environment;
    }

    /**
     * Protect user ID based on configuration
     */
    public function protectUserId(?string $userId): ?string
    {
        if (!$userId || !$this->isFieldProtectionEnabled('user_id')) {
            return $userId;
        }

        $config = $this->fieldProtection['user_id'];
        return $this->applyProtectionStrategy($userId, $config);
    }

    /**
     * Protect session ID based on configuration
     */
    public function protectSessionId(?string $sessionId): ?string
    {
        if (!$sessionId || !$this->isFieldProtectionEnabled('session_id')) {
            return $sessionId;
        }

        $config = $this->fieldProtection['session_id'];
        return $this->applyProtectionStrategy($sessionId, $config);
    }

    /**
     * Protect IP address based on configuration
     */
    public function protectIpAddress(?string $ipAddress): ?string
    {
        if (!$ipAddress || !$this->isFieldProtectionEnabled('ip_address')) {
            return $ipAddress;
        }

        $config = $this->fieldProtection['ip_address'];
        return $this->applyProtectionStrategy($ipAddress, $config);
    }

    /**
     * Protect route parameters that may contain sensitive data
     */
    public function protectRouteParams(array $routeParams): array
    {
        if (empty($routeParams) || !$this->isFieldProtectionEnabled('route_params')) {
            return $routeParams;
        }

        $config = $this->fieldProtection['route_params'];
        
        if ($config['strategy'] !== 'selective') {
            return $this->sanitizeArray($routeParams);
        }

        $protected = [];
        $sensitivePatterns = $config['sensitive_patterns'] ?? [];

        foreach ($routeParams as $key => $value) {
            if ($this->isSensitiveParameter($key, $sensitivePatterns)) {
                $protected[$key] = $this->applyProtectionStrategy((string)$value, [
                    'strategy' => 'hash',
                    'mask_length' => 8
                ]);
            } else {
                $protected[$key] = $this->sanitizeString((string)$value);
            }
        }

        return $protected;
    }

    /**
     * Protect metadata by filtering and sanitizing sensitive information
     */
    public function protectMetadata(array $metadata): array
    {
        if (empty($metadata) || !$this->isFieldProtectionEnabled('metadata')) {
            return $this->sanitizeArray($metadata);
        }

        $config = $this->fieldProtection['metadata'];
        $protected = [];

        // Only keep allowed fields if specified
        if (isset($config['allowed_fields']) && is_array($config['allowed_fields'])) {
            foreach ($config['allowed_fields'] as $allowedField) {
                if (isset($metadata[$allowedField])) {
                    $protected[$allowedField] = $this->sanitizeValue($metadata[$allowedField]);
                }
            }
        } else {
            $protected = $this->sanitizeArray($metadata);
        }

        // Remove blocked headers if they somehow got included
        $blockedHeaders = $config['blocked_headers'] ?? [];
        foreach ($blockedHeaders as $blockedHeader) {
            unset($protected[$blockedHeader]);
            unset($protected[strtolower($blockedHeader)]);
            unset($protected[strtoupper($blockedHeader)]);
        }

        return $protected;
    }

    /**
     * Check if protection should be applied based on environment and configuration
     */
    public function shouldProtectField(string $fieldName): bool
    {
        // In production, always protect sensitive data
        if ($this->environment === 'prod') {
            return true;
        }

        // In development, respect configuration
        if ($this->protectionLevel === 'none') {
            return false;
        }

        return $this->isFieldProtectionEnabled($fieldName);
    }

    /**
     * Apply protection strategy based on configuration
     */
    private function applyProtectionStrategy(string $value, array $config): ?string
    {
        if (!isset($config['strategy'])) {
            return $this->sanitizeString($value);
        }

        switch ($config['strategy']) {
            case 'none':
                return $value;

            case 'remove':
                return null;

            case 'hash':
                return $this->hashValue($value);

            case 'mask':
                $maskLength = $config['mask_length'] ?? 8;
                return $this->maskValue($value, $maskLength);

            case 'sanitize':
            default:
                return $this->sanitizeString($value);
        }
    }

    /**
     * Hash a value for anonymization
     */
    private function hashValue(string $value): string
    {
        return hash('sha256', $value . $this->getHashSalt());
    }

    /**
     * Mask a value by showing only partial characters
     */
    private function maskValue(string $value, int $visibleLength = 4): string
    {
        if (strlen($value) <= $visibleLength) {
            return str_repeat('*', strlen($value));
        }

        $start = substr($value, 0, min(2, $visibleLength / 2));
        $end = substr($value, -min(2, $visibleLength / 2));
        $masked = str_repeat('*', max(4, strlen($value) - $visibleLength));
        
        return $start . $masked . $end;
    }

    /**
     * Check if a parameter name suggests sensitive data
     */
    private function isSensitiveParameter(string $paramName, array $sensitivePatterns): bool
    {
        $paramLower = strtolower($paramName);
        
        foreach ($sensitivePatterns as $pattern) {
            if (str_contains($paramLower, strtolower($pattern))) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if field protection is enabled
     */
    private function isFieldProtectionEnabled(string $fieldName): bool
    {
        return isset($this->fieldProtection[$fieldName]) 
            && ($this->fieldProtection[$fieldName]['enabled'] ?? false);
    }

    /**
     * Get hash salt for anonymization
     */
    private function getHashSalt(): string
    {
        // Use environment-specific salt to make hashes consistent within environment
        // but different across environments
        return $this->environment . '_access_log_salt_2024';
    }

    /**
     * Sanitize a single value
     */
    private function sanitizeValue($value)
    {
        if (is_string($value)) {
            return $this->sanitizeString($value);
        }
        
        if (is_array($value)) {
            return $this->sanitizeArray($value);
        }
        
        return $value;
    }

    /**
     * Sanitize array values recursively
     */
    private function sanitizeArray(array $data): array
    {
        $sanitized = [];
        
        foreach ($data as $key => $value) {
            $sanitizedKey = $this->sanitizeString((string)$key);
            
            if (is_array($value)) {
                $sanitized[$sanitizedKey] = $this->sanitizeArray($value);
            } else {
                $sanitized[$sanitizedKey] = $this->sanitizeString((string)$value);
            }
        }
        
        return $sanitized;
    }

    /**
     * Sanitize string to prevent injection and limit length
     */
    private function sanitizeString(string $value): string
    {
        // Remove null bytes and control characters except newlines/tabs
        $sanitized = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', $value);
        
        // Limit length to prevent log bloat
        $sanitized = mb_substr($sanitized, 0, 500, 'UTF-8');
        
        // Trim whitespace
        return trim($sanitized);
    }
}