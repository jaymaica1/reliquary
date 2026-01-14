<?php

namespace App\Controller;

use App\Service\AccessLogService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/gdpr')]
class GDPRController extends AbstractController
{
    public function __construct(
        private AccessLogService $accessLogService
    ) {
    }

    #[Route('/log-consent', name: 'app_gdpr_log_consent', methods: ['POST'])]
    public function logConsent(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        
        if (!isset($data['preferences'])) {
            return new JsonResponse(['status' => 'error', 'message' => 'Missing preferences'], 400);
        }

        $preferences = $data['preferences'];
        $action = $data['action'] ?? 'update';

        $this->accessLogService->logConsentEvent($action, $preferences, $request);

        return new JsonResponse(['status' => 'success']);
    }
}
