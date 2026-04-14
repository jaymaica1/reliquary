<?php

namespace App\Tests\Controller;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class RelicControllerTest extends WebTestCase
{
    public function testIndex(): void
    {
        $client = static::createClient();
        $client->request('GET', '/relic');

        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('h1', 'Relíquias');
    }
}
