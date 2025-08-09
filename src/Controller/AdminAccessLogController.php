<?php

namespace App\Controller;

use App\Service\AccessLogService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Controller for managing access logs in the admin interface
 */
#[Route('/admin/access-logs')]
#[IsGranted('ROLE_ADMIN')]
class AdminAccessLogController extends AbstractController
{
    private AccessLogService $accessLogService;

    public function __construct(AccessLogService $accessLogService)
    {
        $this->accessLogService = $accessLogService;
    }

    /**
     * Display access logs with filtering and pagination
     */
    #[Route('', name: 'app_admin_access_logs_index')]
    public function index(Request $request): Response
    {
        // Get pagination parameters
        $page = max(1, $request->query->getInt('page', 1));
        $limit = min(100, max(10, $request->query->getInt('limit', 50))); // Between 10-100 items per page

        // Get filter parameters
        $filters = [];
        if ($action = $request->query->get('action')) {
            $filters['action'] = $action;
        }
        if ($userId = $request->query->get('user_id')) {
            $filters['userId'] = $userId;
        }
        if ($severity = $request->query->get('severity')) {
            $filters['severity'] = $severity;
        }
        if ($dateFrom = $request->query->get('date_from')) {
            $filters['dateFrom'] = $dateFrom;
        }
        if ($dateTo = $request->query->get('date_to')) {
            $filters['dateTo'] = $dateTo;
        }

        // Get access logs
        $accessLogs = $this->accessLogService->getAccessLogs($page, $limit, $filters);
        
        // Get statistics for dashboard
        $statistics = $this->accessLogService->getAccessStatistics($filters);

        return $this->render('admin/access_logs/index.html.twig', [
            'access_logs' => $accessLogs,
            'statistics' => $statistics,
            'current_page' => $page,
            'limit' => $limit,
            'filters' => $filters,
            'severity_options' => ['info', 'success', 'warning', 'failure'],
            'has_more' => count($accessLogs) === $limit, // Simple pagination indicator
        ]);
    }

    /**
     * Display access log statistics and analytics
     */
    #[Route('/statistics', name: 'app_admin_access_logs_statistics')]
    public function statistics(Request $request): Response
    {
        // Get date range filters
        $filters = [];
        if ($dateFrom = $request->query->get('date_from')) {
            $filters['dateFrom'] = $dateFrom;
        }
        if ($dateTo = $request->query->get('date_to')) {
            $filters['dateTo'] = $dateTo;
        }

        // Default to last 30 days if no date range specified
        if (empty($filters['dateFrom'])) {
            $filters['dateFrom'] = date('Y-m-d', strtotime('-30 days'));
        }
        if (empty($filters['dateTo'])) {
            $filters['dateTo'] = date('Y-m-d');
        }

        $statistics = $this->accessLogService->getAccessStatistics($filters);

        return $this->render('admin/access_logs/statistics.html.twig', [
            'statistics' => $statistics,
            'filters' => $filters,
        ]);
    }

    /**
     * Clean up old access logs
     */
    #[Route('/cleanup', name: 'app_admin_access_logs_cleanup', methods: ['POST'])]
    public function cleanup(Request $request): Response
    {
        // CSRF protection
        if (!$this->isCsrfTokenValid('access_logs_cleanup', $request->request->get('_token'))) {
            $this->addFlash('danger', 'Invalid CSRF token.');
            return $this->redirectToRoute('app_admin_access_logs_index');
        }

        $daysToKeep = max(30, min(3650, $request->request->getInt('days_to_keep', 365))); // Between 30 days and 10 years
        
        try {
            $deletedCount = $this->accessLogService->cleanupOldLogs($daysToKeep);
            
            $this->addFlash('success', sprintf(
                'Successfully cleaned up %d old access logs (older than %d days).',
                $deletedCount,
                $daysToKeep
            ));
        } catch (\Exception $e) {
            $this->addFlash('danger', 'Error cleaning up access logs: ' . $e->getMessage());
        }

        return $this->redirectToRoute('app_admin_access_logs_index');
    }

    /**
     * Export access logs to CSV
     */
    #[Route('/export', name: 'app_admin_access_logs_export')]
    public function export(Request $request): Response
    {
        // Get filter parameters (same as index)
        $filters = [];
        if ($action = $request->query->get('action')) {
            $filters['action'] = $action;
        }
        if ($userId = $request->query->get('user_id')) {
            $filters['userId'] = $userId;
        }
        if ($severity = $request->query->get('severity')) {
            $filters['severity'] = $severity;
        }
        if ($dateFrom = $request->query->get('date_from')) {
            $filters['dateFrom'] = $dateFrom;
        }
        if ($dateTo = $request->query->get('date_to')) {
            $filters['dateTo'] = $dateTo;
        }

        // Get access logs (limit to reasonable amount for export)
        $accessLogs = $this->accessLogService->getAccessLogs(1, 10000, $filters);

        // Create CSV content
        $csvContent = "Timestamp,User ID,IP Address (Hashed),Action,Resource,HTTP Method,Response Code,Severity,User Agent\n";
        
        foreach ($accessLogs as $log) {
            $csvContent .= sprintf(
                "%s,%s,%s,%s,%s,%s,%s,%s,%s\n",
                $log->getTimestamp()->format('Y-m-d H:i:s'),
                $this->escapeCsv($log->getUserId() ?? ''),
                $this->escapeCsv($log->getIpAddress()),
                $this->escapeCsv($log->getAction()),
                $this->escapeCsv($log->getResource() ?? ''),
                $this->escapeCsv($log->getHttpMethod() ?? ''),
                $log->getResponseCode() ?? '',
                $this->escapeCsv($log->getSeverity()),
                $this->escapeCsv($log->getUserAgent() ?? '')
            );
        }

        // Create response
        $response = new Response($csvContent);
        $response->headers->set('Content-Type', 'text/csv');
        $response->headers->set('Content-Disposition', sprintf(
            'attachment; filename="access_logs_%s.csv"',
            date('Y-m-d_H-i-s')
        ));

        return $response;
    }

    /**
     * Escape CSV field values
     */
    private function escapeCsv(?string $value): string
    {
        if ($value === null) {
            return '';
        }
        
        // Escape quotes and wrap in quotes if necessary
        if (str_contains($value, '"') || str_contains($value, ',') || str_contains($value, "\n")) {
            return '"' . str_replace('"', '""', $value) . '"';
        }
        
        return $value;
    }
}