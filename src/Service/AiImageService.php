<?php

namespace App\Service;

use App\Exception\AiImageGenerationException;
use App\Service\AiImage\AiImageProviderInterface;
use Symfony\Component\DependencyInjection\Attribute\TaggedIterator;

class AiImageService
{
    public const PROVIDER_OPENAI = 'openai';
    public const PROVIDER_GEMINI = 'gemini';

    public function __construct(
        #[TaggedIterator('app.ai_image_provider')]
        private readonly iterable $providers,
        private readonly ConfigurationService $configurationService
    ) {
    }

    /**
     * @throws AiImageGenerationException
     */
    public function generatePortrait(string $prompt, string $size = '1024x1024', ?string $provider = null): string
    {
        $provider = $provider ?? $this->configurationService->get('ai_image_provider', self::PROVIDER_OPENAI);
        $model = $this->configurationService->get('ai_image_model');

        foreach ($this->providers as $providerInstance) {
            if ($providerInstance->supports($provider)) {
                return $providerInstance->generatePortrait($prompt, $size, $model);
            }
        }

        throw new AiImageGenerationException(sprintf('AI image provider "%s" not found or not supported', $provider));
    }
}
