<?php

namespace App\Tests\Controller;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class ErrorPageTest extends WebTestCase
{
    public function test404Page(): void
    {
        $client = static::createClient();
        $crawler = $client->request('GET', '/non-existent-page-that-should-trigger-404');

        $this->assertEquals(404, $client->getResponse()->getStatusCode());
    }
}
