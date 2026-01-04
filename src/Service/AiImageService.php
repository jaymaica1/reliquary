<?php

namespace App\Service;

use Symfony\Contracts\HttpClient\HttpClientInterface;

class AiImageService
{
    private HttpClientInterface $httpClient;
    private string $apiKey;
    private string $baseUrl;
    private string $model;

    public function __construct(
        HttpClientInterface $httpClient,
        string $aiApiKey,
        string $aiBaseUrl = 'https://api.openai.com/v1',
        string $aiModel = 'dall-e-3'
    ) {
        $this->httpClient = $httpClient;
        $this->apiKey = $aiApiKey;
        $this->baseUrl = rtrim($aiBaseUrl, '/');
        $this->model = $aiModel;
    }

    public function generatePortrait(string $prompt, string $size = '1024x1024'): ?string
    {
        try {
            $response = $this->httpClient->request('POST', $this->baseUrl . '/images/generations', [
                'headers' => [
                    'Authorization' => 'Bearer ' . $this->apiKey,
                    'Content-Type' => 'application/json',
                ],
                'json' => [
                    'model' => $this->model,
                    'prompt' => $prompt,
                    'n' => 1,
                    'size' => $size,
                ],
            ]);

            if ($response->getStatusCode() !== 200) {
                return null;
            }

            $data = $response->toArray();
            return $data['data'][0]['url'] ?? null;
        } catch (\Exception $e) {
            // Log error or handle it
            return null;
        }
    }
}
