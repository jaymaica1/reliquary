<?php

namespace App\Service\Ai;

use Symfony\Contracts\HttpClient\HttpClientInterface;

class OpenAiChatProvider implements AiChatProviderInterface
{
    public function __construct(
        private HttpClientInterface $httpClient,
        private string $apiKey,
        private string $baseUrl = 'https://api.openai.com/v1',
        private string $defaultModel = 'gpt-4o-mini'
    ) {
        $this->baseUrl = rtrim($baseUrl, '/');
    }

    public function getName(): string
    {
        return 'openai';
    }

    public function supports(string $providerName): bool
    {
        return $providerName === $this->getName();
    }

    public function chat(array $messages, array $options = []): string
    {
        $model = $options['model'] ?? $this->defaultModel;
        
        $json = [
            'model' => $model,
            'messages' => $messages,
            'temperature' => $options['temperature'] ?? 0.7,
        ];

        if (isset($options['response_format'])) {
            $json['response_format'] = $options['response_format'];
        }

        try {
            $response = $this->httpClient->request('POST', $this->baseUrl . '/chat/completions', [
                'headers' => [
                    'Authorization' => 'Bearer ' . $this->apiKey,
                    'Content-Type' => 'application/json',
                ],
                'json' => $json,
            ]);

            if ($response->getStatusCode() !== 200) {
                $errorData = $response->toArray(false);
                $errorMessage = $errorData['error']['message'] ?? 'Unknown OpenAI error';
                throw new \RuntimeException('OpenAI error: ' . $errorMessage);
            }

            $data = $response->toArray();
            $content = $data['choices'][0]['message']['content'] ?? null;

            if ($content === null) {
                throw new \RuntimeException('OpenAI response did not contain content');
            }

            return $content;
        } catch (\Exception $e) {
            throw new \RuntimeException('OpenAI request failed: ' . $e->getMessage(), 0, $e);
        }
    }
}
