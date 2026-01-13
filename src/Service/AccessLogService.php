<?php

namespace App\Service;

use App\Document\AccessLog;
use App\Service\AccessLogPIIProtection;
use DateTime;
use Doctrine\ODM\MongoDB\DocumentManager;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Throwable;

class AccessLogService
{
    private DocumentManager $documentManager;
    private LoggerInterface $logger;
    private TokenStorageInterface $tokenStorage;
    private AccessLogPIIProtection $piiProtection;

    public function __construct(
        DocumentManager $documentManager,
        LoggerInterface $logger,
        TokenStorageInterface $tokenStorage,
        AccessLogPIIProtection $piiProtection
    ) {
        $this->documentManager = $documentManager;
        $this->logger = $logger;
        $this->tokenStorage = $tokenStorage;
        $this->piiProtection = $piiProtection;
    }

    /**
     * Log access event with security best practices
     */
    public function logAccess(
        string $action,
        ?Request $request = null,
        ?string $resource = null,
        array $metadata = [],
        string $severity = 'info'
    ): void {
        try {
            $accessLog = new AccessLog();
            $accessLog->setAction($action);
            $accessLog->setSeverity($severity);

            // Set user information if available (with PII protection)
            $token = $this->tokenStorage->getToken();
            if ($token && $token->getUser() instanceof UserInterface) {
                $user = $token->getUser();
                $userId = $user->getUserIdentifier();
                $protectedUserId = $this->piiProtection->protectUserId($userId);
                $accessLog->setUserId($protectedUserId);
            }

            // Extract request information if provided
            if ($request) {
                $this->extractRequestInfo($accessLog, $request);
            }

            // Set resource and metadata
            if ($resource) {
                $accessLog->setResource($resource);
            }

            if (!empty($metadata)) {
                $protectedMetadata = $this->piiProtection->protectMetadata($metadata);
                $accessLog->setMetadata($protectedMetadata);
            }

            // Persist the access log
            $this->documentManager->persist($accessLog);
            $this->documentManager->flush();

        } catch (Throwable $e) {
            // Log error but don't throw to prevent breaking application flow
            $this->logger->error('Failed to log access event', [
                'action' => $action,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
        }
    }

    /**
     * Log authentication events
     */
    public function logAuthenticationEvent(
        string $event,
        ?string $username = null,
        ?Request $request = null,
        bool $success = true
    ): void {
        $severity = $success ? 'info' : 'warning';
        $metadata = [];

        if ($username) {
            $metadata['username'] = $username;
        }

        $metadata['success'] = $success;

        $this->logAccess("auth.{$event}", $request, null, $metadata, $severity);
    }

    /**
     * Log security events (failed login attempts, etc.)
     */
    public function logSecurityEvent(
        string $event,
        ?Request $request = null,
        array $metadata = []
    ): void {
        $this->logAccess("security.{$event}", $request, null, $metadata, 'warning');
    }

    /**
     * Log resource access events
     */
    public function logResourceAccess(
        string $resource,
        string $action,
        ?Request $request = null,
        array $metadata = []
    ): void {
        $this->logAccess("resource.{$action}", $request, $resource, $metadata);
    }

    /**
     * Get access logs with pagination and filtering
     */
    public function getAccessLogs(
        int $page = 1,
        int $limit = 50,
        ?array $filters = null
    ): array {
        try {
            $queryBuilder = $this->documentManager->createQueryBuilder(AccessLog::class);

            // Apply filters
            if ($filters) {
                if (isset($filters['action'])) {
                    $queryBuilder->field('action')->equals($filters['action']);
                }

                if (isset($filters['userId'])) {
                    $queryBuilder->field('userId')->equals($filters['userId']);
                }

                if (isset($filters['severity'])) {
                    $queryBuilder->field('severity')->equals($filters['severity']);
                }

                if (isset($filters['dateFrom'])) {
                    $queryBuilder->field('timestamp')->gte(new DateTime($filters['dateFrom'] . ' 00:00:00'));
                }

                if (isset($filters['dateTo'])) {
                    $queryBuilder->field('timestamp')->lte(new DateTime($filters['dateTo'] . ' 23:59:59'));
                }
            }

            // Order by timestamp desc and apply pagination
            $query = $queryBuilder
                ->sort('timestamp', 'desc')
                ->skip(($page - 1) * $limit)
                ->limit($limit)
                ->getQuery();

            return $query->execute()->toArray();

        } catch (Throwable $e) {
            $this->logger->error('Failed to retrieve access logs', [
                'error' => $e->getMessage(),
                'filters' => $filters
            ]);

            return [];
        }
    }

    /**
     * Get access log statistics
     */
    public function getAccessStatistics(?array $filters = null): array
    {
        try {
            $pipeline = [];

            // Add match stage for filters
            if ($filters) {
                $matchConditions = [];

                if (isset($filters['dateFrom'])) {
                    $matchConditions['timestamp']['$gte'] = new \MongoDB\BSON\UTCDateTime(new DateTime($filters['dateFrom'] . ' 00:00:00'));
                }

                if (isset($filters['dateTo'])) {
                    $matchConditions['timestamp']['$lte'] = new \MongoDB\BSON\UTCDateTime(new DateTime($filters['dateTo'] . ' 23:59:59'));
                }

                if (!empty($matchConditions)) {
                    $pipeline[] = ['$match' => $matchConditions];
                }
            }

            // Aggregate statistics
            $pipeline[] = [
                '$group' => [
                    '_id' => null,
                    'totalAccesses' => ['$sum' => 1],
                    'uniqueUsers' => ['$addToSet' => '$userId'],
                    'actionBreakdown' => [
                        '$push' => [
                            'action' => '$action',
                            'severity' => '$severity'
                        ]
                    ]
                ]
            ];

            $result = $this->documentManager
                ->getDocumentCollection(AccessLog::class)
                ->aggregate($pipeline)
                ->toArray();

            if (empty($result)) {
                return [
                    'totalAccesses' => 0,
                    'uniqueUsers' => 0,
                    'actionBreakdown' => []
                ];
            }

            $stats = $result[0];
            $stats['uniqueUsers'] = count(array_filter($stats['uniqueUsers']));

            return $stats;

        } catch (Throwable $e) {
            $this->logger->error('Failed to retrieve access statistics', [
                'error' => $e->getMessage()
            ]);

            return [
                'totalAccesses' => 0,
                'uniqueUsers' => 0,
                'actionBreakdown' => []
            ];
        }
    }

    /**
     * Extract request information and populate access log
     */
    private function extractRequestInfo(AccessLog $accessLog, Request $request): void
    {
        // Set IP address (protected for privacy)
        $ipAddress = $request->getClientIp() ?? '0.0.0.0';
        $protectedIpAddress = $this->piiProtection->protectIpAddress($ipAddress);
        $accessLog->setIpAddress($protectedIpAddress);

        // Set user agent (sanitized)
        $userAgent = $request->headers->get('User-Agent');
        if ($userAgent) {
            $accessLog->setUserAgent($userAgent);
        }

        // Set HTTP method
        $accessLog->setHttpMethod($request->getMethod());

        // Set request path
        $accessLog->setPath($request->getPathInfo());

        // Set session ID hash if session exists (with PII protection)
        if ($request->hasSession()) {
            $session = $request->getSession();
            if ($session->isStarted()) {
                $sessionId = $session->getId();
                $protectedSessionId = $this->piiProtection->protectSessionId($sessionId);
                $accessLog->setSessionId($protectedSessionId);
            }
        }
    }

    /**
     * Clean up old access logs based on retention policy
     */
    public function cleanupOldLogs(int $daysToKeep = 365): int
    {
        try {
            $cutoffDate = new DateTime("-{$daysToKeep} days");

            $queryBuilder = $this->documentManager->createQueryBuilder(AccessLog::class);
            $query = $queryBuilder
                ->remove()
                ->field('timestamp')->lt($cutoffDate)
                ->getQuery();

            $result = $query->execute();

            $this->logger->info('Access logs cleanup completed', [
                'deleted_count' => $result->getDeletedCount(),
                'cutoff_date' => $cutoffDate->format('Y-m-d H:i:s')
            ]);

            return $result->getDeletedCount();

        } catch (Throwable $e) {
            $this->logger->error('Failed to cleanup old access logs', [
                'error' => $e->getMessage()
            ]);

            return 0;
        }
    }
}