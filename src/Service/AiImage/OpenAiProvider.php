<?php

namespace App\Service\AiImage;

use Symfony\Contracts\HttpClient\HttpClientInterface;

class OpenAiProvider implements AiImageProviderInterface
{
    public function __construct(
        private HttpClientInterface $httpClient,
        private string $apiKey,
        private string $baseUrl = 'https://api.openai.com/v1',
        private string $model = 'dall-e-3'
    ) {
        $this->baseUrl = rtrim($baseUrl, '/');
    }

    public function getName(): string
    {
        return 'openai';
    }

    public function getAvailableModels(): array
    {
        return [
            'gpt-image-1.5' => [
                'name' => 'GPT Image 1.5',
                'pricing' => 'Low: $0.009 (1024x1024), $0.013 (other); Medium: $0.034 (1024x1024), $0.05 (other); High: $0.133 (1024x1024), $0.2 (other)'
            ],
            'gpt-image-latest' => [
                'name' => 'GPT Image Latest',
                'pricing' => 'Low: $0.009 (1024x1024), $0.013 (other); Medium: $0.034 (1024x1024), $0.05 (other); High: $0.133 (1024x1024), $0.2 (other)'
            ],
            'gpt-image-1' => [
                'name' => 'GPT Image 1',
                'pricing' => 'Low: $0.011 (1024x1024), $0.016 (other); Medium: $0.042 (1024x1024), $0.063 (other); High: $0.167 (1024x1024), $0.25 (other)'
            ],
            'gpt-image-1-mini' => [
                'name' => 'GPT Image 1 Mini',
                'pricing' => 'Low: $0.005 (1024x1024), $0.006 (other); Medium: $0.011 (1024x1024), $0.015 (other); High: $0.036 (1024x1024), $0.052 (other)'
            ],
            'dall-e-3' => [
                'name' => 'DALL-E 3',
                'pricing' => 'Standard: $0.04 (1024x1024), $0.08 (other); HD: $0.08 (1024x1024), $0.12 (other)'
            ],
            'dall-e-2' => [
                'name' => 'DALL-E 2',
                'pricing' => 'Standard: $0.016 (256x256), $0.018 (512x512), $0.02 (1024x1024)'
            ],
        ];
    }

    public function supports(string $providerName): bool
    {
        return $providerName === $this->getName();
    }

    public function generatePortrait(string $prompt, string $size = '1024x1024', ?string $model = null): string
    {
        try {
            $response = $this->httpClient->request('POST', $this->baseUrl . '/images/generations', [
                'headers' => [
                    'Authorization' => 'Bearer ' . $this->apiKey,
                    'Content-Type' => 'application/json',
                ],
                'json' => [
                    'model' => $model ?? $this->model,
                    'prompt' => $prompt,
                    'n' => 1,
                    'size' => $size,
                ],
            ]);

            if ($response->getStatusCode() !== 200) {
                $errorData = $response->toArray(false);
                $errorMessage = $errorData['error']['message'] ?? 'Unknown OpenAI error';
                throw new \App\Exception\AiImageGenerationException('OpenAI error: ' . $errorMessage);
            }

            $data = $response->toArray();
            $url = $data['data'][0]['url'] ?? null;

            if (!$url) {
                throw new \App\Exception\AiImageGenerationException('OpenAI response did not contain an image URL');
            }

            return $url;
        } catch (\App\Exception\AiImageGenerationException $e) {
            throw $e;
        } catch (\Exception $e) {
            throw new \App\Exception\AiImageGenerationException('OpenAI request failed: ' . $e->getMessage(), 0, $e);
        }
    }
}
