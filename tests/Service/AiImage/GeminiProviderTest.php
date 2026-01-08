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

    protected function setUp(): void
    {
        $this->httpClient = $this->createMock(HttpClientInterface::class);
    }

    private function createMockResponse(int $statusCode, array $data, string $rawContent = '{}', array $headers = ['content-type' => ['application/json']]): ResponseInterface
    {
        $response = $this->createMock(ResponseInterface::class);
        $response->method('getStatusCode')->willReturn($statusCode);
        $response->method('toArray')->willReturn($data);
        $response->method('getContent')->willReturn($rawContent);
        $response->method('getHeaders')->willReturn($headers);
        return $response;
    }

    public function testGeneratePortraitWithImagenModelThrowsExceptionOnEmptyImageResponseData(): void
    {
        // First call: prompt generation (successful)
        $promptResponse = $this->createMockResponse(200, [
            'candidates' => [
                [
                    'content' => [
                        'parts' => [
                            ['text' => 'A serene portrait of a Catholic saint in prayer.']
                        ]
                    ]
                ]
            ]
        ]);

        // Second call: image generation (empty response)
        $imageResponse = $this->createMockResponse(200, [], '{}', ['content-type' => ['application/json']]);

        $this->httpClient->method('request')
            ->willReturnOnConsecutiveCalls($promptResponse, $imageResponse);

        $provider = new GeminiProvider($this->httpClient, 'test_api_key');

        $this->expectException(AiImageGenerationException::class);
        $this->expectExceptionMessageMatches('/Gemini response did not contain image data/');

        $provider->generatePortrait('test saint');
    }

    public function testGeneratePortraitWithGeminiModelThrowsExceptionOnEmptyImageResponseData(): void
    {
        // First call: prompt generation (successful)
        $promptResponse = $this->createMockResponse(200, [
            'candidates' => [
                [
                    'content' => [
                        'parts' => [
                            ['text' => 'A serene portrait of a Catholic saint in prayer.']
                        ]
                    ]
                ]
            ]
        ]);

        // Second call: image generation (empty response)
        $imageResponse = $this->createMockResponse(200, [], '{}', ['content-type' => ['application/json']]);

        $this->httpClient->method('request')
            ->willReturnOnConsecutiveCalls($promptResponse, $imageResponse);

        $provider = new GeminiProvider($this->httpClient, 'test_api_key');

        $this->expectException(AiImageGenerationException::class);
        $this->expectExceptionMessageMatches('/Gemini response did not contain image data/');

        // Use a Gemini model to trigger the Gemini API path
        $provider->generatePortrait('test saint', '1024x1024', 'gemini-2.0-flash-preview-image-generation');
    }

    public function testGeneratePortraitThrowsExceptionOnPromptGenerationFailure(): void
    {
        // First call: prompt generation fails
        $promptResponse = $this->createMockResponse(200, []);

        $this->httpClient->method('request')->willReturn($promptResponse);

        $provider = new GeminiProvider($this->httpClient, 'test_api_key');

        $this->expectException(AiImageGenerationException::class);
        $this->expectExceptionMessage('Gemini did not return a text prompt');

        $provider->generatePortrait('test saint');
    }

    public function testGeneratePortraitSuccessWithImagenModel(): void
    {
        // First call: prompt generation (successful)
        $promptResponse = $this->createMockResponse(200, [
            'candidates' => [
                [
                    'content' => [
                        'parts' => [
                            ['text' => 'A serene portrait of a Catholic saint in prayer.']
                        ]
                    ]
                ]
            ]
        ]);

        // Second call: image generation (successful)
        $imageResponse = $this->createMockResponse(200, [
            'predictions' => [
                [
                    'bytesBase64Encoded' => 'dGVzdGltYWdl',
                    'mimeType' => 'image/png'
                ]
            ]
        ]);

        $this->httpClient->method('request')
            ->willReturnOnConsecutiveCalls($promptResponse, $imageResponse);

        $provider = new GeminiProvider($this->httpClient, 'test_api_key');

        $result = $provider->generatePortrait('test saint');

        $this->assertEquals('data:image/png;base64,dGVzdGltYWdl', $result);
    }

    public function testGeneratePortraitSuccessWithGeminiModel(): void
    {
        // First call: prompt generation (successful)
        $promptResponse = $this->createMockResponse(200, [
            'candidates' => [
                [
                    'content' => [
                        'parts' => [
                            ['text' => 'A serene portrait of a Catholic saint in prayer.']
                        ]
                    ]
                ]
            ]
        ]);

        // Second call: image generation (successful)
        $imageResponse = $this->createMockResponse(200, [
            'candidates' => [
                [
                    'content' => [
                        'parts' => [
                            [
                                'inlineData' => [
                                    'data' => 'dGVzdGltYWdl',
                                    'mimeType' => 'image/png'
                                ]
                            ]
                        ]
                    ]
                ]
            ]
        ]);

        $this->httpClient->method('request')
            ->willReturnOnConsecutiveCalls($promptResponse, $imageResponse);

        $provider = new GeminiProvider($this->httpClient, 'test_api_key');

        $result = $provider->generatePortrait('test saint', '1024x1024', 'gemini-3-pro-image-preview');

        $this->assertEquals('data:image/png;base64,dGVzdGltYWdl', $result);
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

    public function testGenerateOptimizedPromptUsesCorrectMetaPrompt(): void
    {
        $saintName = 'Francisco e Jacinta Marto';

        $promptResponse = $this->createMockResponse(200, [
            'candidates' => [
                [
                    'content' => [
                        'parts' => [
                            ['text' => 'Optimized prompt for the saint']
                        ]
                    ]
                ]
            ]
        ]);

        $imageResponse = $this->createMockResponse(200, [
            'predictions' => [
                [
                    'bytesBase64Encoded' => 'dGVzdGltYWdl',
                    'mimeType' => 'image/png'
                ]
            ]
        ]);

        $expectedMetaPrompt = 'Write a prompt that describe Santo(a) ' . $saintName . ', with minimal word count, to be used to generate an image that will populate a catalog of catholic saints. Make it safe against the content restrictions. Reply only with the prompt.';

        $this->httpClient->expects($this->exactly(2))
            ->method('request')
            ->willReturnCallback(function ($method, $url, $options) use ($promptResponse, $imageResponse, $expectedMetaPrompt) {
                static $callCount = 0;
                $callCount++;

                if ($callCount === 1) {
                    // First call should be prompt generation
                    $this->assertStringContainsString('gemini-2.0-flash', $url);
                    $this->assertEquals($expectedMetaPrompt, $options['json']['contents'][0]['parts'][0]['text']);
                    return $promptResponse;
                }

                // Second call should be image generation
                return $imageResponse;
            });

        $provider = new GeminiProvider($this->httpClient, 'test_api_key');
        $provider->generatePortrait($saintName);
    }
}
