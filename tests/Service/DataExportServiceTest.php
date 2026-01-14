<?php

namespace App\Tests\Service;

use App\Entity\Relic;
use App\Entity\Saint;
use App\Entity\User;
use App\Entity\RelicImage;
use App\Entity\SaintImage;
use App\Entity\UserImage;
use App\Enum\RelicDegree;
use App\Enum\RelicStatus;
use App\Service\DataExportService;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ODM\MongoDB\DocumentManager;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Serializer\Encoder\JsonEncoder;
use Symfony\Component\Serializer\Normalizer\ObjectNormalizer;
use Symfony\Component\Serializer\Serializer;

class DataExportServiceTest extends TestCase
{
    public function testExportUserData()
    {
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $documentManager = $this->createMock(DocumentManager::class);
        
        // Manual serializer setup since we are unit testing
        $encoders = [new JsonEncoder()];
        $normalizers = [new ObjectNormalizer()];
        $serializer = new Serializer($normalizers, $encoders);

        $service = new DataExportService($entityManager, $documentManager, $serializer);

        $user = $this->createMock(User::class);
        $user->method('getUsername')->willReturn('testuser');
        $user->method('getEmail')->willReturn('test@example.com');
        $user->method('getRoles')->willReturn(['ROLE_USER']);
        $user->method('isVerified')->willReturn(true);
        $user->method('getRelics')->willReturn(new ArrayCollection([]));
        $user->method('getImages')->willReturn(new ArrayCollection([]));

        $relicImageRepo = $this->createMock(EntityRepository::class);
        $saintImageRepo = $this->createMock(EntityRepository::class);
        $userImageRepo = $this->createMock(EntityRepository::class);

        $relicImage = $this->createMock(RelicImage::class);
        $relicImage->method('getFilename')->willReturn('relic.jpg');
        $relicImage->method('getOriginalFilename')->willReturn('orig_relic.jpg');
        $relicImage->method('getMimeType')->willReturn('image/jpeg');
        $relicImage->method('getSize')->willReturn(1024);

        $relicImageRepo->method('findBy')->with(['uploader' => $user])->willReturn([$relicImage]);
        $saintImageRepo->method('findBy')->with(['uploader' => $user])->willReturn([]);
        $userImageRepo->method('findBy')->with(['uploader' => $user])->willReturn([]);

        $entityManager->method('getRepository')
            ->willReturnMap([
                [RelicImage::class, $relicImageRepo],
                [SaintImage::class, $saintImageRepo],
                [UserImage::class, $userImageRepo],
            ]);

        $data = $service->exportUserData($user);

        $this->assertEquals('testuser', $data['profile']['username']);
        $this->assertEquals('test@example.com', $data['profile']['email']);
        $this->assertArrayHasKey('relics', $data);
        $this->assertArrayHasKey('images', $data);
        $this->assertCount(1, $data['images']);
        $this->assertEquals('relic.jpg', $data['images'][0]['filename']);
    }

    public function testFormatAsJson()
    {
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $documentManager = $this->createMock(DocumentManager::class);
        
        $encoders = [new JsonEncoder()];
        $normalizers = [new ObjectNormalizer()];
        $serializer = new Serializer($normalizers, $encoders);

        $service = new DataExportService($entityManager, $documentManager, $serializer);

        $data = ['test' => 'value'];
        $json = $service->formatAsJson($data);

        $this->assertJson($json);
        $this->assertStringContainsString('"test": "value"', $json);
    }
}
