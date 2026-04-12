<?php

namespace App\Service\Ai;

use App\Exception\Ai\AiResponseTruncatedException;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * OpenAI-compatible chat API as exposed by LM Studio (default http://127.0.0.1:1234/v1).
 */
class LmStudioChatProvider implements AiChatProviderInterface
{
    public function __construct(
        private HttpClientInterface $httpClient,
        private string $baseUrl = 'http://127.0.0.1:1234/v1',
        private string $apiKey = '',
        private string $defaultModel = '',
    ) {
        $this->baseUrl = rtrim($baseUrl, '/');
    }

    public function getName(): string
    {
        return 'lmstudio';
    }

    public function supports(string $providerName): bool
    {
        return $providerName === $this->getName();
    }

    public function chat(array $messages, array $options = []): string
    {
        $model = $options['model'] ?? $this->defaultModel;

        $json = [
            'messages' => $messages,
            'temperature' => $options['temperature'] ?? 0.7,
        ];

        if ($model !== '') {
            $json['model'] = $model;
        }

        if (!empty($options['response_format'])) {
            $json['response_format'] = $options['response_format'];
        }

        $headers = [
            'Content-Type' => 'application/json',
        ];
        if ($this->apiKey !== '') {
            $headers['Authorization'] = 'Bearer '.$this->apiKey;
        }

        try {
            $response = $this->httpClient->request('POST', $this->baseUrl . '/chat/completions', [
                'headers' => $headers,
                'json' => $json,
                'timeout' => 300,
            ]);
            if ($response->getStatusCode() !== 200) {
                $errorData = $response->toArray(false);
                $errorMessage = $errorData['error']['message'] ?? $errorData['error'] ?? 'Unknown LM Studio error';
                if (is_array($errorMessage)) {
                    $errorMessage = json_encode($errorMessage);
                }
                throw new \RuntimeException('LM Studio error: '.$errorMessage);
            }

            $data = $response->toArray();
            $choice = $data['choices'][0] ?? null;
            $content = $choice['message']['content'] ?? null;

            if ($content === null) {
                throw new \RuntimeException('LM Studio response did not contain content');
            }

            if (($choice['finish_reason'] ?? '') === 'length') {
                throw new AiResponseTruncatedException('LM Studio response was truncated (finish_reason: length)');
            }

            return $content;
        } catch (AiResponseTruncatedException|\RuntimeException $e) {
            throw $e;
        } catch (\Exception $e) {
            throw new \RuntimeException('LM Studio request failed: '.$e->getMessage(), 0, $e);
        }
    }
}
