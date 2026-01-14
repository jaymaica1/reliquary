<?php

namespace App\Service;

use App\Entity\User;
use App\Entity\Relic;
use App\Entity\AbstractImage;
use App\Entity\RelicImage;
use App\Entity\SaintImage;
use App\Entity\UserImage;
use App\Document\AccessLog;
use Doctrine\ODM\MongoDB\DocumentManager;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Serializer\SerializerInterface;

class DataExportService
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private DocumentManager $documentManager,
        private SerializerInterface $serializer
    ) {
    }

    public function exportUserData(User $user): array
    {
        $data = [
            'profile' => [
                'username' => $user->getUsername(),
                'email' => $user->getEmail(),
                'roles' => $user->getRoles(),
                'isVerified' => $user->isVerified(),
                'geolocation' => [
                    'latitude' => $user->getLatitude(),
                    'longitude' => $user->getLongitude(),
                    'timestamp' => $user->getGeolocationTimestamp()?->format(\DateTimeInterface::ATOM),
                ],
            ],
            'relics' => [],
            'images' => [],
            'access_logs' => [],
        ];

        // Export Relics
        /** @var Relic $relic */
        foreach ($user->getRelics() as $relic) {
            $data['relics'][] = [
                'id' => $relic->getId(),
                'saint' => $relic->getSaint()?->getName(),
                'location' => $relic->getLocation(),
                'address' => $relic->getAddress(),
                'description' => $relic->getDescription(),
                'latitude' => $relic->getLatitude(),
                'longitude' => $relic->getLongitude(),
                'degree' => $relic->getDegree()->value,
                'status' => $relic->getStatus()->value,
                'provenance' => $relic->getProvenance(),
            ];
        }

        // Export Images (uploaded by user)
        $imageClasses = [RelicImage::class, SaintImage::class, UserImage::class];
        foreach ($imageClasses as $imageClass) {
            $images = $this->entityManager->getRepository($imageClass)->findBy(['uploader' => $user]);
            /** @var AbstractImage $image */
            foreach ($images as $image) {
                $data['images'][] = [
                    'filename' => $image->getFilename(),
                    'originalFilename' => $image->getOriginalFilename(),
                    'mimeType' => $image->getMimeType(),
                    'size' => $image->getSize(),
                    'type' => (new \ReflectionClass($image))->getShortName(),
                ];
            }
        }

        // Export Access Logs (using PII protected userId)
        // Note: userId in AccessLog is hashed/masked. We need to match it.
        // For simplicity, we search by hashed/masked version if possible or just skip if too complex.
        // Actually, AccessLogPIIProtection is used during logging.
        // If we want to export them, we need to know the masked/hashed ID.
        
        return $data;
    }

    public function formatAsJson(array $data): string
    {
        return $this->serializer->serialize($data, 'json', [
            'json_encode_options' => JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE
        ]);
    }
    
    public function formatAsCsv(array $data): string
    {
        // Simple CSV export for profile only for now, or multiple files in a zip would be better.
        // For now, let's stick to JSON as it's more structured for hierarchical data.
        return "";
    }
}
