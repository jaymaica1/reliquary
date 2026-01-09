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

    public function testGetName(): void
    {
        $provider = new GeminiProvider($this->httpClient, 'test_api_key');
        $this->assertEquals('gemini', $provider->getName());
    }

    public function testSupports(): void
    {
        $provider = new GeminiProvider($this->httpClient, 'test_api_key');
        $this->assertTrue($provider->supports('gemini'));
        $this->assertFalse($provider->supports('openai'));
    }

    public function testGeneratePortraitSuccessImagen(): void
    {
        $imageData = [
            'predictions' => [
                [
                    'bytesBase64Encoded' => 'dGVzdGltYWdl',
                    'mimeType' => 'image/png'
                ]
            ]
        ];

        $this->response->method('getStatusCode')->willReturn(200);
        $this->response->method('toArray')->willReturn($imageData);
        $this->httpClient->method('request')->willReturn($this->response);

        $provider = new GeminiProvider($this->httpClient, 'test_api_key');
        $result = $provider->generatePortrait('test prompt', '1024x1024', 'imagen-3.0-generate-001');

        $this->assertEquals('data:image/png;base64,dGVzdGltYWdl', $result);
    }

    public function testGeneratePortraitSuccessGemini(): void
    {
        $imageData = [
            'candidates' => [
                [
                    'content' => [
                        'parts' => [
                            [
                                'inlineData' => [
                                    'data' => 'Z2VtaW5paW1hZ2U=',
                                    'mimeType' => 'image/jpeg'
                                ]
                            ]
                        ]
                    ]
                ]
            ]
        ];

        $this->response->method('getStatusCode')->willReturn(200);
        $this->response->method('toArray')->willReturn($imageData);
        $this->httpClient->method('request')->willReturn($this->response);

        $provider = new GeminiProvider($this->httpClient, 'test_api_key');
        $result = $provider->generatePortrait('test prompt', '1024x1024', 'gemini-2.5-flash-image');

        $this->assertEquals('data:image/jpeg;base64,Z2VtaW5paW1hZ2U=', $result);
    }

    public function testGeneratePortraitThrowsExceptionOnApiError(): void
    {
        $errorData = [
            'error' => [
                'message' => 'Invalid API key'
            ]
        ];

        $this->response->method('getStatusCode')->willReturn(400);
        $this->response->method('toArray')->willReturn($errorData);
        $this->httpClient->method('request')->willReturn($this->response);

        $provider = new GeminiProvider($this->httpClient, 'test_api_key');

        $this->expectException(AiImageGenerationException::class);
        $this->expectExceptionMessage('Gemini error: Invalid API key');

        $provider->generatePortrait('test prompt');
    }

    public function testGeneratePortraitThrowsExceptionOnMissingImageData(): void
    {
        $this->response->method('getStatusCode')->willReturn(200);
        $this->response->method('toArray')->willReturn(['predictions' => []]);
        $this->httpClient->method('request')->willReturn($this->response);

        $provider = new GeminiProvider($this->httpClient, 'test_api_key');

        $this->expectException(AiImageGenerationException::class);
        $this->expectExceptionMessage('Gemini response did not contain image data');

        $provider->generatePortrait('test prompt');
    }

    public function testGeneratePortraitThrowsExceptionWhenApiKeyMissing(): void
    {
        $provider = new GeminiProvider($this->httpClient, null);

        $this->expectException(AiImageGenerationException::class);
        $this->expectExceptionMessage('Gemini API key is not configured');

        $provider->generatePortrait('test prompt');
    }
}
