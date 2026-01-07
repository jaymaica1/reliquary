<?php

namespace App\Service\AiImage;

use App\Exception\AiImageGenerationException;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class GeminiProvider implements AiImageProviderInterface
{
    public function __construct(
        private HttpClientInterface $httpClient,
        private ?string $apiKey,
        private string $baseUrl = 'https://generativelanguage.googleapis.com/v1beta',
        private string $model = 'imagen-3.0-generate-001'
    ) {
        $this->baseUrl = rtrim($baseUrl, '/');
    }

    public function getName(): string
    {
        return 'gemini';
    }

    public function getAvailableModels(): array
    {
        return [
            'gemini-3-pro-image-preview' => [
                'name' => 'Gemini 3 Pro Image Preview',
                'pricing' => 'Input: ~$0.0011/img; Output: ~$0.134/1K/2K, ~$0.24/4K'
            ],
            'gemini-2.5-flash-image' => [
                'name' => 'Gemini 2.5 Flash Image',
                'pricing' => 'Output: $0.039 per image'
            ],
            'imagen-4.0-generate-001' => [
                'name' => 'Imagen 4 Standard',
                'pricing' => '$0.04 per image'
            ],
            'imagen-4.0-ultra-generate-001' => [
                'name' => 'Imagen 4 Ultra',
                'pricing' => '$0.06 per image'
            ],
            'imagen-4.0-fast-generate-001' => [
                'name' => 'Imagen 4 Fast',
                'pricing' => '$0.02 per image'
            ],
        ];
    }

    public function supports(string $providerName): bool
    {
        return $providerName === $this->getName();
    }

    public function generatePortrait(string $prompt, string $size = '1024x1024', ?string $model = null): string
    {
        if (!$this->apiKey) {
            throw new AiImageGenerationException('Gemini API key is not configured');
        }

        $targetModel = $model ?? $this->model;

        try {
            $response = $this->httpClient->request('POST', $this->baseUrl . '/models/' . $targetModel . ':predict?key=' . $this->apiKey, [
                'headers' => [
                    'Content-Type' => 'application/json',
                ],
                'json' => [
                    'instances' => [
                        ['prompt' => $prompt],
                    ],
                    'parameters' => [
                        'sampleCount' => 1,
                    ],
                ],
            ]);

            if ($response->getStatusCode() !== 200) {
                $errorData = $response->toArray(false);
                $errorMessage = $errorData['error']['message'] ?? 'Unknown Gemini error';
                throw new AiImageGenerationException('Gemini error: ' . $errorMessage);
            }

            $data = $response->toArray();
            $base64Image = $data['predictions'][0]['bytesBase64Encoded'] ?? null;
            $mimeType = $data['predictions'][0]['mimeType'] ?? 'image/png';

            if (!$base64Image) {
                throw new AiImageGenerationException('Gemini response did not contain image data. Data: ' . json_encode($data));
            }

            return 'data:' . $mimeType . ';base64,' . $base64Image;
        } catch (AiImageGenerationException $e) {
            throw $e;
        } catch (\Exception $e) {
            throw new AiImageGenerationException('Gemini request failed: ' . $e->getMessage(), 0, $e);
        }
    }
}
