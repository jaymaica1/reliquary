<?php

namespace App\Command;

use App\Entity\Saint;
use App\Enum\CanonicalStatus;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Contracts\HttpClient\HttpClientInterface;

#[AsCommand(
    name: 'app:discover-vatican',
    description: 'Discovers new saints from the Vatican website and adds them as incomplete.',
)]
class DiscoverVaticanCommand extends Command
{
    public function __construct(
        protected HttpClientInterface $client,
        protected EntityManagerInterface $entityManager,
    ) {
        parent::__construct();
    }

    protected function configure(): void {}

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('Discovering Saints from Vatican');

        try {
            $html = $this->getCanonizationHtml();
            
            $dom = new \DOMDocument();
            @$dom->loadHTML($html);
            $xpath = new \DOMXPath($dom);
            
            $nodes = $xpath->query("//div[contains(@class, 'saint-block')]//div[contains(@class, 'saint-block')]//a[contains(@href, '/it/santi-e-beati/')]");
            
            if ($nodes->length === 0) {
                $io->warning('No saints found with primary selector. Trying fallback...');
                $nodes = $xpath->query("//div[contains(@class, 'saint-block')]//a[contains(@href, '/it/santi-e-beati/')]");
            }

            $count = 0;
            $newCount = 0;
            $updatedCount = 0;

            $urls = [];
            foreach ($nodes as $node) {
                $url = $node->getAttribute('href');
                if ($url && !in_array($url, $urls)) {
                    $urls[] = $url;
                }
            }

            $io->progressStart(count($urls));

            foreach ($urls as $url) {
                if (!str_starts_with($url, 'http')) {
                    $url = 'https://www.causesanti.va' . $url;
                }

                $saintData = $this->scrapeSaintDetail($url, $io);
                if (!$saintData) {
                    $io->progressAdvance();
                    continue;
                }

                $name = $saintData['name'];

                // Check if already exists in DB
                $existingSaint = $this->entityManager->getRepository(Saint::class)->findOneBy(['url' => $url]);
                if (!$existingSaint) {
                    $existingSaint = $this->entityManager->getRepository(Saint::class)->findOneBy(['name' => $name]);
                }

                $isNew = false;
                if (!$existingSaint) {
                    $saint = new Saint();
                    $saint->setName($name);
                    $saint->setUrl($url);
                    $saint->setIsIncomplete(true);
                    $this->entityManager->persist($saint);
                    $isNew = true;
                } elseif ($existingSaint->isIncomplete()) {
                    // It's already incomplete, so we just continue to build it
                    $saint = $existingSaint;
                } else {
                    // It's already COMPLETE. Create a NEW incomplete saint to stage these changes
                    $saint = new Saint();
                    $saint->setName($name . ' (Update)');
                    $saint->setUrl($url); // Keep same URL to track what it updates
                    $saint->setIsIncomplete(true);
                    $this->entityManager->persist($saint);
                    $isNew = true;
                }

                // Update data
                if ($saintData['status']) {
                    $saint->setCanonicalStatus($saintData['status']);
                }
                if ($saintData['feastDate']) {
                    $saint->setFeastDate($saintData['feastDate']);
                }
                if ($saintData['phrase']) {
                    $saint->setSaintPhrase($saintData['phrase']);
                }
                if ($saintData['canonizationDate']) {
                    $saint->setCanonizationDate($saintData['canonizationDate']);
                }
                if ($saintData['pope']) {
                    $saint->setCanonizingPope($saintData['pope']);
                }

                // If we have name, status and feast date, it's no longer incomplete
                // But only if it's NOT an update to an existing complete saint
                if ($saint->getName() && $saint->getCanonicalStatus() && $saint->getFeastDate()) {
                    if (!str_contains($saint->getName(), '(Update)')) {
                        $saint->setIsIncomplete(false);
                    }
                }

                if ($isNew) {
                    $newCount++;
                } else {
                    $updatedCount++;
                }
                
                $count++;
                $io->progressAdvance();
            }

            $io->progressFinish();
            $this->entityManager->flush();

            $io->success(sprintf('Finished discovery. Processed %d entries, found %d new saints, updated %d.', $count, $newCount, $updatedCount));

        } catch (\Exception $e) {
            $io->error(sprintf('Error during discovery: %s', $e->getMessage()));
            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }

    protected function getCanonizationHtml(): string
    {
        $response = $this->client->request('GET', 'https://www.causesanti.va/it/celebrazioni/canonizzazioni.html');
        return $response->getContent();
    }

    protected function scrapeSaintDetail(string $url, SymfonyStyle $io): ?array
    {
        try {
            $response = $this->client->request('GET', $url);
            $html = $response->getContent();
            
            $dom = new \DOMDocument();
            @$dom->loadHTML($html);
            $xpath = new \DOMXPath($dom);

            // Name
            $nameNode = $xpath->query("//h1")->item(0);
            if (!$nameNode) return null;
            $name = trim(preg_replace('/\(.*\)/', '', $nameNode->nodeValue));

            // Status - check in order of importance
            $status = null;
            $canonizationDate = null;
            $pope = null;

            $statusNodes = [
                'canonization' => ['enum' => CanonicalStatus::CANONIZATION, 'selector' => "//div[contains(@class, 'canonization')]"],
                'beatification' => ['enum' => CanonicalStatus::BEATIFICATION, 'selector' => "//div[contains(@class, 'beatification')]"],
                'veneration' => ['enum' => CanonicalStatus::VENERATION, 'selector' => "//div[contains(@class, 'venerable')]"],
            ];

            foreach ($statusNodes as $key => $info) {
                $node = $xpath->query($info['selector'])->item(0);
                if ($node) {
                    if ($status === null) { // Take the first (highest) one found
                        $status = $info['enum'];
                        
                        // Try to get date and pope
                        $pNodes = $xpath->query(".//p", $node);
                        foreach ($pNodes as $p) {
                            $text = trim($p->nodeValue, " \t\n\r\0\x0B-");
                            if (preg_match('/\d{1,2}\s+\w+\s+\d{4}/', $text, $matches)) {
                                $canonizationDate = $this->parseItalianDate($matches[0]);
                            } elseif (str_contains($text, 'Papa')) {
                                $pope = str_replace('Papa', '', $text);
                                $pope = trim($pope, " \t\n\r\0\x0B\xc2\xa0"); // Remove non-breaking space too
                            }
                        }
                    }
                }
            }

            // Feast Date
            $feastDate = null;
            $feastNode = $xpath->query("//div[contains(@class, 'liturgy-date')]//p")->item(0);
            if ($feastNode) {
                $feastDateText = trim($feastNode->nodeValue, " \t\n\r\0\x0B-");
                $feastDate = $this->parseItalianDate($feastDateText);
            }

            // Phrase
            $phrase = null;
            $phraseNode = $xpath->query("//div[contains(@class, 'saint-phrase')]")->item(0);
            if ($phraseNode) {
                $phrase = trim($phraseNode->nodeValue, " \t\n\r\0\x0B“”\" ");
            }

            return [
                'name' => $name,
                'status' => $status,
                'feastDate' => $feastDate,
                'phrase' => $phrase,
                'canonizationDate' => $canonizationDate,
                'pope' => $pope,
            ];

        } catch (\Exception $e) {
            $io->warning(sprintf('Failed to scrape %s: %s', $url, $e->getMessage()));
            return null;
        }
    }

    protected function parseItalianDate(string $dateString): ?\DateTime
    {
        $months = [
            'gennaio' => '01', 'febbraio' => '02', 'marzo' => '03', 'aprile' => '04',
            'maggio' => '05', 'giugno' => '06', 'luglio' => '07', 'agosto' => '08',
            'settembre' => '09', 'ottobre' => '10', 'novembre' => '11', 'dicembre' => '12'
        ];

        $dateString = mb_strtolower(trim($dateString));
        
        // Handle format like "12 ottobre"
        if (preg_match('/^(\d{1,2})\s+(\w+)$/', $dateString, $matches)) {
            $day = str_pad($matches[1], 2, '0', STR_PAD_LEFT);
            $monthName = $matches[2];
            if (isset($months[$monthName])) {
                $month = $months[$monthName];
                return \DateTime::createFromFormat('Y-m-d', date('Y') . "-$month-$day") ?: null;
            }
        }

        // Handle format like "05 luglio 2018"
        if (preg_match('/^(\d{1,2})\s+(\w+)\s+(\d{4})$/', $dateString, $matches)) {
            $day = str_pad($matches[1], 2, '0', STR_PAD_LEFT);
            $monthName = $matches[2];
            $year = $matches[3];
            if (isset($months[$monthName])) {
                $month = $months[$monthName];
                return \DateTime::createFromFormat('Y-m-d', "$year-$month-$day") ?: null;
            }
        }

        return null;
    }
}
