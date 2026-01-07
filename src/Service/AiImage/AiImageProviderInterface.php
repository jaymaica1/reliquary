<?php

namespace App\Service\AiImage;

interface AiImageProviderInterface
{
    public function getName(): string;

    /**
     * @return array<string, array{name: string, pricing: string}> Available models for this provider
     */
    public function getAvailableModels(): array;

    /**
     * @throws \App\Exception\AiImageGenerationException
     */
    public function generatePortrait(string $prompt, string $size = '1024x1024', ?string $model = null): string;

    public function supports(string $providerName): bool;
}
