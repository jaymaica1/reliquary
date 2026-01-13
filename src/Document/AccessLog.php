<?php

namespace App\Document;

use DateTime;
use Doctrine\ODM\MongoDB\Mapping\Annotations as ODM;
use Symfony\Component\Validator\Constraints as Assert;

#[ODM\Document(collection: 'access_logs')]
#[ODM\Index(keys: ['timestamp' => 'desc'])]
#[ODM\Index(keys: ['userId' => 1])]
#[ODM\Index(keys: ['ipAddress' => 1])]
#[ODM\Index(keys: ['action' => 1])]
#[ODM\Index(keys: ['resource' => 1])]
class AccessLog
{
    #[ODM\Id]
    private ?string $id = null;

    #[ODM\Field(type: 'date')]
    #[Assert\NotNull]
    private DateTime $timestamp;

    #[ODM\Field(type: 'string')]
    #[Assert\Length(max: 255)]
    private ?string $userId = null;

    #[ODM\Field(type: 'string')]
    #[Assert\NotBlank]
    #[Assert\Length(max: 45)] // Max length for IPv6
    private string $ipAddress;

    #[ODM\Field(type: 'string')]
    #[Assert\Length(max: 255)]
    private ?string $userAgent = null;

    #[ODM\Field(type: 'string')]
    #[Assert\NotBlank]
    #[Assert\Length(max: 100)]
    private string $action;

    #[ODM\Field(type: 'string')]
    #[Assert\Length(max: 500)]
    private ?string $resource = null;

    #[ODM\Field(type: 'string')]
    #[Assert\Choice(choices: ['GET', 'POST', 'PUT', 'DELETE', 'PATCH', 'OPTIONS', 'HEAD'])]
    private ?string $httpMethod = null;

    #[ODM\Field(type: 'int')]
    #[Assert\Range(min: 100, max: 599)]
    private ?int $responseCode = null;

    #[ODM\Field(type: 'hash')]
    private array $metadata = [];

    #[ODM\Field(type: 'string')]
    #[Assert\Choice(choices: ['success', 'failure', 'warning', 'info'])]
    private string $severity = 'info';

    #[ODM\Field(type: 'string')]
    #[Assert\Length(max: 64)] // SHA-256 hash length
    private ?string $sessionHash = null;

    #[ODM\Field(type: 'string')]
    #[Assert\Length(max: 2048)]
    private ?string $path = null;

    public function __construct()
    {
        $this->timestamp = new DateTime();
    }

    public function getId(): ?string
    {
        return $this->id;
    }

    public function getTimestamp(): DateTime
    {
        return $this->timestamp;
    }

    public function setTimestamp(DateTime $timestamp): self
    {
        $this->timestamp = $timestamp;
        return $this;
    }

    public function getUserId(): ?string
    {
        return $this->userId;
    }

    public function setUserId(?string $userId): self
    {
        $this->userId = $userId;
        return $this;
    }

    public function getIpAddress(): string
    {
        return $this->ipAddress;
    }

    public function setIpAddress(string $ipAddress): self
    {
        // IP address is already protected by AccessLogPIIProtection service
        $this->ipAddress = $ipAddress;
        return $this;
    }

    public function getUserAgent(): ?string
    {
        return $this->userAgent;
    }

    public function setUserAgent(?string $userAgent): self
    {
        // Truncate and sanitize user agent
        if ($userAgent) {
            $sanitized = $this->sanitizeString($userAgent);
            $this->userAgent = substr($sanitized, 0, 255);
        }
        return $this;
    }

    public function getAction(): string
    {
        return $this->action;
    }

    public function setAction(string $action): self
    {
        $this->action = $action;
        return $this;
    }

    public function getResource(): ?string
    {
        return $this->resource;
    }

    public function setResource(?string $resource): self
    {
        $this->resource = $resource;
        return $this;
    }

    public function getHttpMethod(): ?string
    {
        return $this->httpMethod;
    }

    public function setHttpMethod(?string $httpMethod): self
    {
        $this->httpMethod = $httpMethod;
        return $this;
    }

    public function getResponseCode(): ?int
    {
        return $this->responseCode;
    }

    public function setResponseCode(?int $responseCode): self
    {
        $this->responseCode = $responseCode;
        return $this;
    }

    public function getMetadata(): array
    {
        return $this->metadata;
    }

    public function setMetadata(array $metadata): self
    {
        // Sanitize metadata to prevent injection
        $this->metadata = array_map(function ($value) {
            return is_string($value) ? $this->sanitizeString($value) : $value;
        }, $metadata);
        return $this;
    }

    public function addMetadata(string $key, mixed $value): self
    {
        $this->metadata[$key] = is_string($value) ? $this->sanitizeString($value) : $value;
        return $this;
    }

    public function getSeverity(): string
    {
        return $this->severity;
    }

    public function setSeverity(string $severity): self
    {
        $this->severity = $severity;
        return $this;
    }

    public function getSessionHash(): ?string
    {
        return $this->sessionHash;
    }

    public function setSessionId(?string $sessionId): self
    {
        // Session ID is already protected by AccessLogPIIProtection service
        $this->sessionHash = $sessionId;
        return $this;
    }

    public function getPath(): ?string
    {
        return $this->path;
    }

    public function setPath(?string $path): self
    {
        $this->path = $path ? $this->sanitizeString($path) : null;
        return $this;
    }

    /**
     * Comprehensive string sanitization for metadata and other user inputs
     * Handles multiple security concerns beyond simple strip_tags
     */
    private function sanitizeString(string $value): string
    {
        if (empty($value)) {
            return $value;
        }

        // Step 1: Remove HTML/XML tags (original behavior)
        $sanitized = strip_tags($value);
        
        // Step 2: Decode HTML entities to prevent double encoding issues
        $sanitized = html_entity_decode($sanitized, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        
        // Step 3: Re-encode special HTML characters to prevent XSS
        $sanitized = htmlspecialchars($sanitized, ENT_QUOTES | ENT_HTML5, 'UTF-8', false);
        
        // Step 4: Remove/replace control characters (except common whitespace)
        $sanitized = preg_replace('/[\x00-\x08\x0B-\x0C\x0E-\x1F\x7F]/', '', $sanitized);
        
        // Step 5: Normalize Unicode characters to prevent homograph attacks
        if (class_exists('Normalizer')) {
            $sanitized = \Normalizer::normalize($sanitized, \Normalizer::FORM_C) ?: $sanitized;
        }
        
        // Step 6: Trim whitespace and limit length for practical purposes
        $sanitized = trim($sanitized);
        
        // Step 7: Additional security - escape JSON special characters if needed
        // This helps when metadata might be serialized to JSON downstream
        $sanitized = addcslashes($sanitized, '"\\');
        
        return $sanitized;
    }
}