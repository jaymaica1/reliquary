<?php

namespace App\EventListener;

use App\Service\AccessLogService;
use App\Service\AccessLogPIIProtection;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\Event\TerminateEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\HttpFoundation\Response;

class AccessLogEventListener implements EventSubscriberInterface
{
    private AccessLogService $accessLogService;
    private AccessLogPIIProtection $piiProtection;

    public function __construct(
        AccessLogService $accessLogService,
        AccessLogPIIProtection $piiProtection
    ) {
        $this->accessLogService = $accessLogService;
        $this->piiProtection = $piiProtection;
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::TERMINATE => 'onKernelTerminate',
        ];
    }

    public function onKernelTerminate(TerminateEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();
        $response = $event->getResponse();

        // Skip logging for certain routes to avoid noise
        $route = $request->attributes->get('_route');
        if ($this->shouldSkipLogging($request, $route)) {
            return;
        }

        // Determine action based on route and method
        $action = $this->determineAction($request, $route);
        
        // Get resource from route parameters
        $resource = $this->determineResource($request, $route);
        
        // Prepare metadata - sanitize user inputs to prevent injection
        $userAgent = $request->headers->get('User-Agent');
        $metadata = [
            'route' => $route, // Route is controlled by application, should be safe
            'method' => $request->getMethod(), // HTTP method is controlled, should be safe
            'response_code' => $response->getStatusCode(),
            'user_agent' => $userAgent ? $this->sanitizeString($userAgent) : null,
        ];

        // Add route parameters if available - apply PII protection
        if ($routeParams = $request->attributes->get('_route_params')) {
            $metadata['route_params'] = $this->piiProtection->protectRouteParams($routeParams);
        }

        // Determine severity based on response code
        $severity = $this->determineSeverity($response->getStatusCode());

        // Log the access
        $this->accessLogService->logAccess(
            $action,
            $request,
            $resource,
            $metadata,
            $severity
        );
    }

    private function shouldSkipLogging(\Symfony\Component\HttpFoundation\Request $request, ?string $route): bool
    {
        // 1) Skip known internal/maintenance routes by name when present
        if ($route) {
            $skipRoutes = [
                '_profiler',
                '_wdt',
                'app_admin_access_logs_index',
                'app_admin_access_logs_statistics',
                'app_admin_access_logs_export',
                '_fragment',
            ];

            foreach ($skipRoutes as $skipRoute) {
                if (str_starts_with($route, $skipRoute)) {
                    return true;
                }
            }
        }

        // 2) Skip noise by request method (common preflight/no-content requests)
        $method = $request->getMethod();
        if ($method === 'OPTIONS') {
            return true;
        }

        // 3) Skip common static/asset and browser auto-request paths (whether routed or not)
        $path = $request->getPathInfo() ?? '';
        $pathLower = strtolower($path);

        // Exact filenames commonly requested by browsers/crawlers
        $exactSkips = [
            '/favicon.ico',
            '/robots.txt',
            '/site.webmanifest',
            '/manifest.json',
        ];
        if (in_array($pathLower, $exactSkips, true)) {
            return true;
        }

        // Prefix-based static content (even if missing -> would otherwise yield unknown_route)
        $prefixSkips = [
            '/assets',
            '/build',
            '/bundles',
            '/images',
            '/img',
            '/css',
            '/js',
            '/fonts',
        ];
        foreach ($prefixSkips as $prefix) {
            if (str_starts_with($pathLower, $prefix)) {
                return true;
            }
        }

        // Health/readiness endpoints commonly probed by infra
        $healthSkips = [
            '/health', '/healthz', '/ready', '/readyz', '/live', '/livez', '/status', '/ping'
        ];
        if (in_array($pathLower, $healthSkips, true)) {
            return true;
        }

        return false;
    }

    private function determineAction($request, ?string $route): string
    {
        if (!$route) {
            return 'unknown_route';
        }

        // Map specific routes to meaningful actions
        $routeActionMap = [
            'app_login' => 'auth.login_attempt',
            'app_logout' => 'auth.logout',
            'app_register' => 'auth.register_attempt',
            'app_relic_show' => 'relic.view',
            'app_relic_index' => 'relic.list',
            'app_relic_new' => 'relic.create',
            'app_relic_edit' => 'relic.edit',
            'app_relic_delete' => 'relic.delete',
        ];

        if (isset($routeActionMap[$route])) {
            return $routeActionMap[$route];
        }

        // Generate action based on route pattern
        if (str_contains($route, 'admin')) {
            return 'admin.' . str_replace(['app_', '_'], ['', '.'], $route);
        }

        return str_replace(['app_', '_'], ['', '.'], $route);
    }

    private function determineResource($request, ?string $route): ?string
    {
        if (!$route) {
            return null;
        }

        // Get resource ID from route parameters
        $resourceId = $request->attributes->get('id');
        
        if (str_contains($route, 'relic') && $resourceId) {
            return "relic:{$resourceId}";
        }

        if (str_contains($route, 'user') && $resourceId) {
            return "user:{$resourceId}";
        }

        return null;
    }

    private function determineSeverity(int $responseCode): string
    {
        if ($responseCode >= 200 && $responseCode < 300) {
            return 'success';
        }

        if ($responseCode >= 300 && $responseCode < 400) {
            return 'info';
        }

        if ($responseCode >= 400 && $responseCode < 500) {
            return 'warning';
        }

        if ($responseCode >= 500) {
            return 'failure';
        }

        return 'info';
    }

    /**
     * Sanitize an array recursively to prevent injection attacks
     */
    private function sanitizeArray(array $data): array
    {
        $sanitized = [];
        foreach ($data as $key => $value) {
            $sanitizedKey = is_string($key) ? $this->sanitizeString($key) : $key;
            
            if (is_array($value)) {
                $sanitized[$sanitizedKey] = $this->sanitizeArray($value);
            } elseif (is_string($value)) {
                $sanitized[$sanitizedKey] = $this->sanitizeString($value);
            } else {
                $sanitized[$sanitizedKey] = $value;
            }
        }
        return $sanitized;
    }

    /**
     * Comprehensive string sanitization for metadata and other user inputs
     * Mirrors the sanitization logic from AccessLog document
     */
    private function sanitizeString(string $value): string
    {
        if (empty($value)) {
            return $value;
        }

        // Step 1: Remove HTML/XML tags
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