<?php

namespace App\Tests\Service\AiImage;

use App\Exception\AiImageGenerationException;
use App\Service\AiImage\GeminiProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

class GeminiProviderTest extends TestCase
{
    private $httpClient;
    private $response;

    protected function setUp(): void
    {
        $this->httpClient = $this->createMock(HttpClientInterface::class);
        $this->response = $this->createMock(ResponseInterface::class);
    }

    public function testGeneratePortraitWithImagenModelThrowsExceptionOnEmptyResponseData(): void
    {
        $this->response->method('getStatusCode')->willReturn(200);
        $this->response->method('toArray')->willReturn([]);
        $this->response->method('getContent')->willReturn('{}');
        $this->response->method('getHeaders')->willReturn(['content-type' => ['application/json']]);

        $this->httpClient->method('request')->willReturn($this->response);

        // Use default model which is imagen-3.0-generate-001 (Imagen API)
        $provider = new GeminiProvider($this->httpClient, 'test_api_key');

        $prompt = 'test prompt';
        $enhancedPrompt = 'A realistic portrait of ' . $prompt;
        $expectedPayload = [
            'instances' => [
                ['prompt' => $enhancedPrompt],
            ],
            'parameters' => [
                'sampleCount' => 1,
                'aspectRatio' => '1:1',
                'outputMimeType' => 'image/png',
            ],
        ];

        $this->expectException(AiImageGenerationException::class);
        $expectedMessage = 'Gemini response did not contain image data. Data: [] Raw: {} Headers: ' . json_encode(['content-type' => ['application/json']]) . ' Request: ' . json_encode($expectedPayload);
        $this->expectExceptionMessage($expectedMessage);

        $provider->generatePortrait($prompt);
    }

    public function testGeneratePortraitWithGeminiModelThrowsExceptionOnEmptyResponseData(): void
    {
        $this->response->method('getStatusCode')->willReturn(200);
        $this->response->method('toArray')->willReturn([]);
        $this->response->method('getContent')->willReturn('{}');
        $this->response->method('getHeaders')->willReturn(['content-type' => ['application/json']]);

        $this->httpClient->method('request')->willReturn($this->response);

        $provider = new GeminiProvider($this->httpClient, 'test_api_key');

        $prompt = 'test prompt';
        $enhancedPrompt = 'A realistic portrait of ' . $prompt;
        $expectedPayload = [
            'contents' => [
                [
                    'parts' => [
                        ['text' => $enhancedPrompt]
                    ]
                ]
            ],
            'generationConfig' => [
                'responseModalities' => ['TEXT', 'IMAGE'],
            ],
        ];

        $this->expectException(AiImageGenerationException::class);
        $expectedMessage = 'Gemini response did not contain image data. Data: [] Raw: {} Headers: ' . json_encode(['content-type' => ['application/json']]) . ' Request: ' . json_encode($expectedPayload);
        $this->expectExceptionMessage($expectedMessage);

        // Use a Gemini model to trigger the Gemini API path
        $provider->generatePortrait($prompt, '1024x1024', 'gemini-2.0-flash-preview-image-generation');
    }

    public function testIsGeminiModelDetection(): void
    {
        $provider = new GeminiProvider($this->createMock(HttpClientInterface::class), 'test_api_key');

        // Use reflection to test private method
        $reflection = new \ReflectionClass($provider);
        $method = $reflection->getMethod('isGeminiModel');
        $method->setAccessible(true);

        $this->assertTrue($method->invoke($provider, 'gemini-3-pro-image-preview'));
        $this->assertTrue($method->invoke($provider, 'gemini-2.5-flash-image'));
        $this->assertFalse($method->invoke($provider, 'imagen-4.0-generate-001'));
        $this->assertFalse($method->invoke($provider, 'imagen-4.0-ultra-generate-001'));
        $this->assertFalse($method->invoke($provider, 'imagen-4.0-fast-generate-001'));
    }
}
