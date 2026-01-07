<?php

namespace App\Service;

use App\Service\AiImage\AiImageProviderInterface;
use Symfony\Component\DependencyInjection\Attribute\TaggedIterator;

class AiImageService
{
    public const PROVIDER_OPENAI = 'openai';
    public const PROVIDER_GEMINI = 'gemini';

    /**
     * @var iterable<AiImageProviderInterface>
     */
    private iterable $providers;

    public function __construct(
        #[TaggedIterator('app.ai_image_provider')] iterable $providers,
        private ConfigurationService $configurationService
    ) {
        $this->providers = $providers;
    }

    /**
     * @throws \App\Exception\AiImageGenerationException
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

        throw new \App\Exception\AiImageGenerationException(sprintf('AI image provider "%s" not found or not supported', $provider));
    }

    /**
     * @return AiImageProviderInterface[]
     */
    public function getProviders(): array
    {
        $providers = [];
        foreach ($this->providers as $provider) {
            $providers[] = $provider;
        }
        return $providers;
    }
}
