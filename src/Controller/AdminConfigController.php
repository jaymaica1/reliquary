<?php

namespace App\Controller;

use App\Service\AiImageService;
use App\Service\ConfigurationService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\TaggedIterator;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/config')]
#[IsGranted('ROLE_ADMIN')]
class AdminConfigController extends AbstractController
{
    #[Route('/', name: 'app_admin_config')]
    public function index(
        #[TaggedIterator('app.ai_image_provider')] iterable $providers,
        ConfigurationService $configurationService
    ): Response
    {
        $currentProvider = $configurationService->get('ai_image_provider', AiImageService::PROVIDER_OPENAI);
        $currentModel = $configurationService->get('ai_image_model');

        $providersJson = [];
        foreach ($providers as $provider) {
            $providersJson[$provider->getName()] = $provider->getAvailableModels();
        }

        return $this->render('admin/config/index.html.twig', [
            'providers' => $providers,
            'providers_json' => json_encode($providersJson),
            'currentProvider' => $currentProvider,
            'currentModel' => $currentModel,
        ]);
    }

    #[Route('/save-ai', name: 'app_admin_config_save_ai', methods: ['POST'])]
    public function saveAi(Request $request, ConfigurationService $configurationService): Response
    {
        $provider = $request->request->get('ai_provider');
        $model = $request->request->get('ai_model');

        if ($provider) {
            $configurationService->set('ai_image_provider', $provider, 'ai');
        }
        if ($model) {
            $configurationService->set('ai_image_model', $model, 'ai');
        }

        $this->addFlash('success', 'AI configuration updated successfully.');

        return $this->redirectToRoute('app_admin_config');
    }
}
