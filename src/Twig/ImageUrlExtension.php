<?php

namespace App\Twig;

use App\Service\CachedFileExistenceService;
use League\Flysystem\FilesystemOperator;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

class ImageUrlExtension extends AbstractExtension
{

    public function __construct(
        private ?string             $cloudfrontDomain = null,
        private ?string             $s3Prefix = null,
        private ?FilesystemOperator $localStorage = null,
        private ?CachedFileExistenceService $cachedFileExistence = null
    )
    {
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('image_url', [$this, 'generateImageUrl']),
        ];
    }

    public function generateImageUrl(string $imagePath): string
    {
        // Check if image exists in local storage first
        if ($this->localStorage !== null && $this->cachedFileExistence !== null) {
            try {
                // Use cached file existence check instead of direct call
                if ($this->cachedFileExistence->remoteFileExists($this->localStorage, $imagePath)) {
                    return "/uploads/images/$imagePath";
                }
            } catch (\Exception $e) {
                // If there's an error checking local storage, continue to CloudFront fallback
            }
        } elseif ($this->localStorage !== null) {
            // Fallback to direct check if cache service is not available
            try {
                if ($this->localStorage->fileExists($imagePath)) {
                    return "/uploads/images/$imagePath";
                }
            } catch (\Exception $e) {
                // If there's an error checking local storage, continue to CloudFront fallback
            }
        }

        // Image not found locally or local storage not configured
        // Use CloudFront if available
        if (!empty($this->cloudfrontDomain)) {
            $fullPath = !empty($this->s3Prefix) ? $this->s3Prefix . '/' . $imagePath : $imagePath;
            return 'https://' . $this->cloudfrontDomain . '/' . ltrim($fullPath, '/');
        }

        // Final fallback to local route (for backwards compatibility)
//        return $this->urlGenerator->generate('app_image_serve', ['path' => $imagePath]);
        return '';
    }
}