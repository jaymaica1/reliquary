<?php

namespace App\Tests\Service\Ai;

use App\Exception\Ai\AiResponseTruncatedException;
use App\Service\Ai\OpenAiChatProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

class OpenAiChatProviderTest extends TestCase
{
    private $httpClient;
    private $response;

    protected function setUp(): void
    {
        $this->httpClient = $this->createMock(HttpClientInterface::class);
        $this->response = $this->createMock(ResponseInterface::class);
    }

    public function testChatSuccess(): void
    {
        $responseData = [
            'choices' => [
                [
                    'message' => ['content' => 'Hello!'],
                    'finish_reason' => 'stop'
                ]
            ]
        ];

        $this->response->method('getStatusCode')->willReturn(200);
        $this->response->method('toArray')->willReturn($responseData);
        $this->httpClient->method('request')->willReturn($this->response);

        $provider = new OpenAiChatProvider($this->httpClient, 'test_key');
        $result = $provider->chat([['role' => 'user', 'content' => 'Hi']]);

        $this->assertEquals('Hello!', $result);
    }

    public function testChatThrowsTruncatedException(): void
    {
        $responseData = [
            'choices' => [
                [
                    'message' => ['content' => '{"results":['],
                    'finish_reason' => 'length'
                ]
            ]
        ];

        $this->response->method('getStatusCode')->willReturn(200);
        $this->response->method('toArray')->willReturn($responseData);
        $this->httpClient->method('request')->willReturn($this->response);

        $provider = new OpenAiChatProvider($this->httpClient, 'test_key');

        $this->expectException(AiResponseTruncatedException::class);
        $this->expectExceptionMessage('OpenAI response was truncated (finish_reason: length)');

        $provider->chat([['role' => 'user', 'content' => 'Give me a long list']]);
    }
}
