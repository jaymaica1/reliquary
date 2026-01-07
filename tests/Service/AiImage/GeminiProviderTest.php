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

    public function testGeneratePortraitThrowsExceptionOnEmptyResponseData(): void
    {
        $this->response->method('getStatusCode')->willReturn(200);
        $this->response->method('toArray')->willReturn([]);
        $this->response->method('getContent')->willReturn('{}');

        $this->httpClient->method('request')->willReturn($this->response);

        $provider = new GeminiProvider($this->httpClient, 'test_api_key');

        $this->expectException(AiImageGenerationException::class);
        $this->expectExceptionMessage('Gemini response did not contain image data. Data: [] Raw: {}');

        $provider->generatePortrait('test prompt');
    }
}
