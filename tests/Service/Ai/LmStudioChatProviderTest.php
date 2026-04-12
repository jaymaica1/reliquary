<?php

namespace App\Tests\Service\Ai;

use App\Exception\Ai\AiResponseTruncatedException;
use App\Service\Ai\LmStudioChatProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

class LmStudioChatProviderTest extends TestCase
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
                    'message' => ['content' => 'Hello from LM!'],
                    'finish_reason' => 'stop'
                ]
            ]
        ];

        $this->response->method('getStatusCode')->willReturn(200);
        $this->response->method('toArray')->willReturn($responseData);
        $this->httpClient->method('request')->willReturn($this->response);

        $provider = new LmStudioChatProvider($this->httpClient, 'http://localhost:1234/v1');
        $result = $provider->chat([['role' => 'user', 'content' => 'Hi']]);

        $this->assertEquals('Hello from LM!', $result);
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

        $provider = new LmStudioChatProvider($this->httpClient, 'http://localhost:1234/v1');

        $this->expectException(AiResponseTruncatedException::class);
        $this->expectExceptionMessage('LM Studio response was truncated (finish_reason: length)');

        $provider->chat([['role' => 'user', 'content' => 'Give me a long list']]);
    }
}
