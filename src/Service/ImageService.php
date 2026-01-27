<?php

namespace App\Service;

use App\Entity\AbstractImage;
use App\Entity\RelicImage;
use App\Entity\SaintImage;
use App\Entity\UserImage;
use App\Entity\Relic;
use App\Entity\Saint;
use App\Entity\User;
use Intervention\Image\ImageManager;
use League\Flysystem\FilesystemOperator;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\String\Slugger\SluggerInterface;

class ImageService
{
    private FilesystemOperator $filesystem;
    private SluggerInterface $slugger;
    private ImageManager $imageManager;

    public function __construct(FilesystemOperator $defaultStorage, SluggerInterface $slugger, ImageManager $imageManager)
    {
        $this->filesystem = $defaultStorage;
        $this->slugger = $slugger;
        $this->imageManager = $imageManager;
    }

    public function createRelicImage(UploadedFile $file, Relic $relic, User $uploader = null): RelicImage
    {
        $image = new RelicImage();
        $image->setOriginalFilename($file->getClientOriginalName());
        $image->setMimeType($file->getMimeType());
        $image->setSize($file->getSize());
        $image->setRelic($relic);

        // Set the uploader if provided, otherwise use the relic creator
        if ($uploader) {
            $image->setUploader($uploader);
        } else {
            $image->setUploader($relic->getCreator());
        }

        $fileData = $this->processUploadedFile($file);

        $image->setFilename($fileData['filename']);
        $image->setThumbnailFilename($fileData['thumbnailFilename']);

        return $image;
    }

    public function createUserImage(UploadedFile $file, User $user, User $uploader = null): UserImage
    {
        $image = new UserImage();
        $image->setOriginalFilename($file->getClientOriginalName());
        $image->setMimeType($file->getMimeType());
        $image->setSize($file->getSize());
        $image->setUser($user);

        // Set the uploader if provided, otherwise use the user themselves
        if ($uploader) {
            $image->setUploader($uploader);
        } else {
            $image->setUploader($user);
        }

        $fileData = $this->processUploadedFile($file);

        $image->setFilename($fileData['filename']);
        $image->setThumbnailFilename($fileData['thumbnailFilename']);

        return $image;
    }
    
    public function createSaintImage(UploadedFile $file, Saint $saint, User $uploader = null): SaintImage
    {
        $image = new SaintImage();
        $image->setOriginalFilename($file->getClientOriginalName());
        $image->setMimeType($file->getMimeType());
        $image->setSize($file->getSize());
        $image->setSaint($saint);

        // Set the uploader if provided
        if ($uploader) {
            $image->setUploader($uploader);
        }

        $fileData = $this->processUploadedFile($file);

        $image->setFilename($fileData['filename']);
        $image->setThumbnailFilename($fileData['thumbnailFilename']);

        return $image;
    }

    public function createSaintImageFromUrl(string $url, Saint $saint, User $uploader = null): SaintImage
    {
        $image = new SaintImage();
        $image->setOriginalFilename(basename($url));
        $image->setSaint($saint);

        if ($uploader) {
            $image->setUploader($uploader);
        }

        // Download the file to a temporary location
        $tempFile = tempnam(sys_get_temp_dir(), 'ai_image_');
        file_put_contents($tempFile, file_get_contents($url));

        // Get file info
        $mimeType = mime_content_type($tempFile) ?: 'image/png';
        $size = filesize($tempFile);

        $image->setMimeType($mimeType);
        $image->setSize($size);

        $fileData = $this->processFileData($tempFile, $image->getOriginalFilename(), 'png');

        $image->setFilename($fileData['filename']);
        $image->setThumbnailFilename($fileData['thumbnailFilename']);

        // Clean up temporary file
        unlink($tempFile);

        return $image;
    }

    public function deleteImage(AbstractImage $image): void
    {
        // Delete original file
        if ($this->filesystem->fileExists($image->getFilename())) {
            $this->filesystem->delete($image->getFilename());
        }
        
        // Delete thumbnail file if it exists
        if ($image->getThumbnailFilename() && $this->filesystem->fileExists($image->getThumbnailFilename())) {
            $this->filesystem->delete($image->getThumbnailFilename());
        }

        // Note: S3 doesn't have directories, so no cleanup needed
    }

    /**
     * Generate a thumbnail for an image and upload it to S3
     * 
     * @param string $sourcePath Local path to the source image
     * @param string $thumbnailPath S3 path where the thumbnail should be saved
     */
    private function generateAndUploadThumbnail(string $sourcePath, string $thumbnailPath): void
    {
        // Create a temporary file for the thumbnail
        $tempThumbnailPath = tempnam(sys_get_temp_dir(), 'thumb_');
        
        $this->imageManager->read($sourcePath)
            ->orient()
            ->coverDown(600, 600)
            ->save($tempThumbnailPath);
            
        // Upload thumbnail to S3
        $thumbnailStream = fopen($tempThumbnailPath, 'r');
        $this->filesystem->writeStream($thumbnailPath, $thumbnailStream);
        fclose($thumbnailStream);
        
        // Clean up temporary file
        unlink($tempThumbnailPath);
    }

    private function processUploadedFile(UploadedFile $file): array
    {
        return $this->processFileData($file->getPathname(), $file->getClientOriginalName(), $file->guessExtension());
    }

    private function processFileData(string $pathname, string $originalFilename, ?string $extension): array
    {
        $safeFilename = $this->slugger->slug(pathinfo($originalFilename, PATHINFO_FILENAME));
        $newFilename = $safeFilename . '-' . uniqid() . '.' . ($extension ?: 'png');
        $thumbnailFilename = 'thumb_' . $newFilename;

        $subDir = $this->getUploadPathFromFilename($originalFilename);
        $filePath = $subDir . '/' . $newFilename;
        $thumbnailPath = $subDir . '/' . $thumbnailFilename;

        // Upload original file to S3
        $fileStream = fopen($pathname, 'r');
        $this->filesystem->writeStream($filePath, $fileStream);
        fclose($fileStream);
        
        // Generate and upload thumbnail
        $this->generateAndUploadThumbnail($pathname, $thumbnailPath);

        return [
            'filename' => $filePath,
            'thumbnailFilename' => $thumbnailPath
        ];
    }

    private function getUploadPath(UploadedFile $file): string
    {
        return $this->getUploadPathFromFilename($file->getClientOriginalName());
    }

    private function getUploadPathFromFilename(string $filename): string
    {
        $hash = substr(md5($filename . time()), 0, 2);
        $subDir = $hash[0] . '/' . $hash[1];

        return $subDir;
    }
}
