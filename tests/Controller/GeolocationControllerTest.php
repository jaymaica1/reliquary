<?php

namespace App\Tests\Controller;

use App\Entity\User;
use App\Repository\UserRepository;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;

class GeolocationControllerTest extends WebTestCase
{
    public function testStoreGeolocationForAnonymousUser(): void
    {
        $client = static::createClient([], [
            'HTTPS' => true,
        ]);

        // Test with valid data
        $client->request(
            'POST',
            '/api/geolocation',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode([
                'latitude' => 41.9022,
                'longitude' => 12.4539
            ])
        );

        $this->assertResponseIsSuccessful();
        $this->assertJson($client->getResponse()->getContent());

        $responseData = json_decode($client->getResponse()->getContent(), true);
        $this->assertTrue($responseData['success']);

        // Verify session has the data for anonymous users
        $session = $client->getRequest()->getSession();
        $geolocation = $session->get('user_geolocation');

        $this->assertNotNull($geolocation);
        $this->assertEquals(41.9022, $geolocation['latitude']);
        $this->assertEquals(12.4539, $geolocation['longitude']);
        $this->assertInstanceOf(\DateTime::class, $geolocation['timestamp']);

        // Test with invalid data (missing latitude)
        $client->request(
            'POST',
            '/api/geolocation',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode([
                'longitude' => 12.4539
            ])
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_BAD_REQUEST);
        $responseData = json_decode($client->getResponse()->getContent(), true);
        $this->assertFalse($responseData['success']);

        // Test with invalid data (non-numeric latitude)
        $client->request(
            'POST',
            '/api/geolocation',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode([
                'latitude' => 'invalid',
                'longitude' => 12.4539
            ])
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_BAD_REQUEST);
        $responseData = json_decode($client->getResponse()->getContent(), true);
        $this->assertFalse($responseData['success']);
    }

    public function testStoreGeolocationForAuthenticatedUser(): void
    {
        $client = static::createClient([], [
            'HTTPS' => true,
        ]);
        $container = $client->getContainer();

        // Create a test user
        $entityManager = $container->get('doctrine.orm.entity_manager');
        $userRepository = $container->get(UserRepository::class);

        // Check if test user already exists and remove it
        $existingUser = $userRepository->findOneBy(['username' => 'testuser']);
        if ($existingUser) {
            $entityManager->remove($existingUser);
            $entityManager->flush();
        }

        $testUser = new User();
        $testUser->setUsername('testuser');
        $testUser->setEmail('test@example.com');
        $testUser->setPassword('$2y$13$hK85CeXHFpSGKJg.HD7EOuuIm42ksj1lDjUVjQtQKJJOZKxJcL7rO'); // 'password'
        $testUser->setRoles(['ROLE_USER']);
        $testUser->setIsVerified(true);

        $entityManager->persist($testUser);
        $entityManager->flush();

        // Log in the user
        $client->loginUser($testUser);

        // Test with valid data
        $client->request(
            'POST',
            '/api/geolocation',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode([
                'latitude' => 41.9022,
                'longitude' => 12.4539
            ])
        );

        $this->assertResponseIsSuccessful();
        $this->assertJson($client->getResponse()->getContent());

        $responseData = json_decode($client->getResponse()->getContent(), true);
        $this->assertTrue($responseData['success']);

        // Refresh the user entity
        $entityManager->refresh($testUser);

        // Verify user entity has the geolocation data
        $this->assertEquals(41.9022, $testUser->getLatitude());
        $this->assertEquals(12.4539, $testUser->getLongitude());
        $this->assertNotNull($testUser->getGeolocationTimestamp());

        // Clean up
        $entityManager->remove($testUser);
        $entityManager->flush();
    }
}
