<?php

namespace App\Tests\Service;

use App\Service\AiImage\AiImageProviderInterface;
use App\Service\AiImageService;
use App\Service\ConfigurationService;
use PHPUnit\Framework\TestCase;

class AiImageServiceTest extends TestCase
{
    private function createConfigurationServiceMock($provider = 'openai', $model = null, $prompt = '{name}')
    {
        $mock = $this->createMock(ConfigurationService::class);
        $mock->method('get')->willReturnCallback(function($key, $default = null) use ($provider, $model, $prompt) {
            if ($key === 'ai_image_provider') return $provider;
            if ($key === 'ai_image_model') return $model;
            if ($key === 'ai_image_prompt') return $prompt;
            return $default;
        });
        return $mock;
    }

    public function testGeneratePortraitDelegatesToCorrectProvider(): void
    {
        $openAiProvider = $this->createMock(AiImageProviderInterface::class);
        $geminiProvider = $this->createMock(AiImageProviderInterface::class);

        $openAiProvider->method('supports')->willReturnCallback(fn($p) => $p === 'openai');
        $geminiProvider->method('supports')->willReturnCallback(fn($p) => $p === 'gemini');

        $openAiProvider->expects($this->once())
            ->method('generatePortrait')
            ->with('St. Francis', '1024x1024', null)
            ->willReturn('https://example.com/openai.png');

        $geminiProvider->expects($this->never())
            ->method('generatePortrait');

        $service = new AiImageService([$openAiProvider, $geminiProvider], $this->createConfigurationServiceMock());
        $url = $service->generatePortrait('St. Francis', '1024x1024');

        $this->assertEquals('https://example.com/openai.png', $url);
    }

    public function testGeneratePortraitGeminiSuccess(): void
    {
        $openAiProvider = $this->createMock(AiImageProviderInterface::class);
        $geminiProvider = $this->createMock(AiImageProviderInterface::class);

        $openAiProvider->method('supports')->willReturnCallback(fn($p) => $p === 'openai');
        $geminiProvider->method('supports')->willReturnCallback(fn($p) => $p === 'gemini');

        $geminiProvider->expects($this->once())
            ->method('generatePortrait')
            ->with('St. Francis', '1024x1024', null)
            ->willReturn('data:image/png;base64,base64data');

        $service = new AiImageService([$openAiProvider, $geminiProvider], $this->createConfigurationServiceMock('gemini'));
        $url = $service->generatePortrait('St. Francis', '1024x1024');

        $this->assertEquals('data:image/png;base64,base64data', $url);
    }

    public function testGeneratePortraitWithCustomPrompt(): void
    {
        $openAiProvider = $this->createMock(AiImageProviderInterface::class);
        $openAiProvider->method('supports')->willReturn(true);

        $openAiProvider->expects($this->once())
            ->method('generatePortrait')
            ->with('A beautiful portrait of St. Francis in gold leaf', '1024x1024', null)
            ->willReturn('https://example.com/custom.png');

        $service = new AiImageService([$openAiProvider], $this->createConfigurationServiceMock('openai', null, 'A beautiful portrait of {name} in gold leaf'));
        $url = $service->generatePortrait('St. Francis');

        $this->assertEquals('https://example.com/custom.png', $url);
    }

    public function testGeneratePortraitThrowsExceptionWhenNoProviderSupports(): void
    {
        $provider = $this->createMock(AiImageProviderInterface::class);
        $provider->method('supports')->willReturn(false);

        $service = new AiImageService([$provider], $this->createConfigurationServiceMock('unknown'));
        
        $this->expectException(\App\Exception\AiImageGenerationException::class);
        $this->expectExceptionMessage('AI image provider "unknown" not found or not supported');
        
        $service->generatePortrait('St. Francis');
    }
}
