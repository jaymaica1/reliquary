<?php

namespace App\Command;

use Symfony\Component\Cache\Adapter\FilesystemAdapter;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

#[AsCommand(
    name: 'ScrapeVatican',
    description: 'Add a short description for your command',
)]
class ScrapeVaticanCommand extends Command
{
    public function __construct(
        protected HttpClientInterface $client,
    ) {
        $this->client = $client->withOptions([
            'base_uri' => 'https://www.causesanti.va/it/celebrazioni/',
        ]);
        parent::__construct();
    }

    protected function configure(): void {}

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $index = $this->loadIndex();


        return Command::SUCCESS;
    }

    protected function saveIndex(mixed $index): void
    {
        $index = json_encode($index);
        file_put_contents('index.json', $index);
    }


    protected function loadIndex(): array
    {
        $index = @file_get_contents('index.json');
        if (!empty($index)) {
            return json_decode($index);
        }
        $cache = new FilesystemAdapter();
        $html = $this->getCanonizationHtml($cache);



        $crawler = new Crawler($html);
        $nodes = $crawler->filterXPath('//main//section//div//div//div[1]//div[1]//a')->extract(['href']);

        $this->saveIndex($nodes);

        return $nodes;
    }

    protected function getCanonizationHtml(FilesystemAdapter $cache): string
    {
        return $cache->get('canonizzazioni.html', function(ItemInterface $item): string {
            $response = $this->client->request('GET', 'canonizzazioni.html');
            return $response->getContent();
        });
    }
}
