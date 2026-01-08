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
        private string $model = 'imagen-4.0-generate-001'
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
                'pricing' => 'Output: $0.039 per image'
            ],
            'gemini-2.5-flash-image' => [
                'name' => 'Gemini 2.5 Flash Image',
                'pricing' => 'Output: $0.039 per image'
            ],
            'imagen-4.0-generate-001' => [
                'name' => 'Imagen 4.0 Generate',
                'pricing' => '$0.04 per image'
            ],
            'imagen-4.0-ultra-generate-001' => [
                'name' => 'Imagen 4.0 Ultra Generate',
                'pricing' => '$0.08 per image'
            ],
            'imagen-4.0-fast-generate-001' => [
                'name' => 'Imagen 4.0 Fast Generate',
                'pricing' => '$0.02 per image'
            ],
        ];
    }

    public function supports(string $providerName): bool
    {
        return $providerName === $this->getName();
    }

    /**
     * Check if the model is a Gemini model (uses generateContent API) or Imagen model (uses predict API)
     */
    private function isGeminiModel(string $model): bool
    {
        return str_starts_with($model, 'gemini-');
    }

    public function generatePortrait(string $prompt, string $size = '1024x1024', ?string $model = null): string
    {
        if (!$this->apiKey) {
            throw new AiImageGenerationException('Gemini API key is not configured');
        }

        $targetModel = $model ?? $this->model;

        // Enhance prompt to be more descriptive and avoid silent filtering
        if (!str_contains(strtolower($prompt), 'portrait')) {
            $prompt = 'A realistic portrait of ' . $prompt;
        }

        if ($this->isGeminiModel($targetModel)) {
            return $this->generateWithGeminiApi($prompt, $targetModel);
        }

        return $this->generateWithImagenApi($prompt, $targetModel);
    }

    /**
     * Generate image using Gemini API (generateContent endpoint)
     * Used for models like gemini-2.0-flash-preview-image-generation
     */
    private function generateWithGeminiApi(string $prompt, string $model): string
    {
        $jsonPayload = [
            'contents' => [
                [
                    'parts' => [
                        ['text' => $prompt]
                    ]
                ]
            ],
            'generationConfig' => [
                'responseModalities' => ['TEXT', 'IMAGE'],
            ],
        ];

        try {
            $response = $this->httpClient->request('POST', $this->baseUrl . '/models/' . $model . ':generateContent?key=' . $this->apiKey, [
                'headers' => [
                    'Content-Type' => 'application/json',
                ],
                'json' => $jsonPayload,
            ]);

            if ($response->getStatusCode() !== 200) {
                $errorData = $response->toArray(false);
                $errorMessage = $errorData['error']['message'] ?? 'Unknown Gemini error';
                throw new AiImageGenerationException('Gemini error: ' . $errorMessage . ' Request: ' . json_encode($jsonPayload));
            }

            $data = $response->toArray(false);

            // Gemini API returns image in candidates[0].content.parts[].inlineData
            $candidates = $data['candidates'] ?? [];
            if (!empty($candidates)) {
                $parts = $candidates[0]['content']['parts'] ?? [];
                foreach ($parts as $part) {
                    if (isset($part['inlineData']['data'])) {
                        $base64Image = $part['inlineData']['data'];
                        $mimeType = $part['inlineData']['mimeType'] ?? 'image/png';
                        return 'data:' . $mimeType . ';base64,' . $base64Image;
                    }
                }
            }

            $rawContent = $response->getContent(false);
            $headers = $response->getHeaders(false);
            throw new AiImageGenerationException('Gemini response did not contain image data. Data: ' . json_encode($data) . ' Raw: ' . $rawContent . ' Headers: ' . json_encode($headers) . ' Request: ' . json_encode($jsonPayload));
        } catch (AiImageGenerationException $e) {
            throw $e;
        } catch (\Exception $e) {
            throw new AiImageGenerationException('Gemini request failed: ' . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Generate image using Imagen API (predict endpoint)
     * Used for models like imagen-3.0-generate-002, imagen-3.0-fast-generate-001
     */
    private function generateWithImagenApi(string $prompt, string $model): string
    {
        $jsonPayload = [
            'instances' => [
                ['prompt' => $prompt],
            ],
            'parameters' => [
                'sampleCount' => 1,
                'aspectRatio' => '1:1',
                'outputMimeType' => 'image/png',
            ],
        ];

        try {
            $response = $this->httpClient->request('POST', $this->baseUrl . '/models/' . $model . ':predict?key=' . $this->apiKey, [
                'headers' => [
                    'Content-Type' => 'application/json',
                ],
                'json' => $jsonPayload,
            ]);

            if ($response->getStatusCode() !== 200) {
                $errorData = $response->toArray(false);
                $errorMessage = $errorData['error']['message'] ?? 'Unknown Gemini error';
                throw new AiImageGenerationException('Gemini error: ' . $errorMessage . ' Request: ' . json_encode($jsonPayload));
            }

            $data = $response->toArray(false);
            $base64Image = $data['predictions'][0]['bytesBase64Encoded'] ?? null;
            $mimeType = $data['predictions'][0]['mimeType'] ?? 'image/png';

            if (!$base64Image) {
                $rawContent = $response->getContent(false);
                $headers = $response->getHeaders(false);
                throw new AiImageGenerationException('Gemini response did not contain image data. Data: ' . json_encode($data) . ' Raw: ' . $rawContent . ' Headers: ' . json_encode($headers) . ' Request: ' . json_encode($jsonPayload));
            }

            return 'data:' . $mimeType . ';base64,' . $base64Image;
        } catch (AiImageGenerationException $e) {
            throw $e;
        } catch (\Exception $e) {
            throw new AiImageGenerationException('Gemini request failed: ' . $e->getMessage(), 0, $e);
        }
    }
}
