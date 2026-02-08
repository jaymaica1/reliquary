<?php

namespace App\Command;

use App\Entity\Saint;
use App\Enum\CanonicalStatus;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Yaml\Yaml;

#[AsCommand(
    name: 'app:import-saints',
    description: 'Import saints from YAML file into the database',
)]
class ImportSaintsCommand extends Command
{
    private EntityManagerInterface $entityManager;

    public function __construct(EntityManagerInterface $entityManager)
    {
        parent::__construct();
        $this->entityManager = $entityManager;
    }

    protected function configure(): void
    {
        $this
            ->setHelp('This command imports saints from the saints_info.yaml file into the database')
            ->addOption(
                'file',
                'f',
                InputOption::VALUE_OPTIONAL,
                'Path to the YAML file containing saint information',
                __DIR__ . '/../../data/saints_info.yaml'
            )
            ->addOption(
                'limit',
                'l',
                InputOption::VALUE_OPTIONAL,
                'Limit the number of saints to import',
                null
            )
            ->addOption(
                'batch-size',
                'b',
                InputOption::VALUE_OPTIONAL,
                'Number of saints to process in each batch',
                50
            )
            ->addOption(
                'reset-sequence',
                'r',
                InputOption::VALUE_NONE,
                'Reset the ID sequence before importing'
            )
            ->addOption(
                'dry-run',
                'd',
                InputOption::VALUE_NONE,
                'Run without making any actual changes to the database'
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('Importing saints from YAML file into the database');

        $filePath = $input->getOption('file');
        $limit = $input->getOption('limit');
        $batchSize = (int) $input->getOption('batch-size');
        $resetSequence = $input->getOption('reset-sequence');
        $dryRun = $input->getOption('dry-run');

        if (!file_exists($filePath)) {
            $io->error('File not found: ' . $filePath);
            return Command::FAILURE;
        }

        // Load saints from YAML file
        $yamlContent = file_get_contents($filePath);
        $data = Yaml::parse($yamlContent);

        if (!isset($data['saints']) || empty($data['saints'])) {
            $io->error('No saints found in the YAML file');
            return Command::FAILURE;
        }

        $saints = $data['saints'];
        $io->info(sprintf('Found %d saints in the YAML file', count($saints)));

        // Apply limit if specified
        if ($limit !== null) {
            $limit = (int) $limit;
            if ($limit > 0 && $limit < count($saints)) {
                $saints = array_slice($saints, 0, $limit);
                $io->info(sprintf('Processing only the first %d saints due to limit option', $limit));
            }
        }

        // Reset sequence if requested
        if (!$dryRun && $resetSequence) {
            $this->resetIdSequence($io);
        }

        $io->info(sprintf('Processing %d saints in batches of %d', count($saints), $batchSize));

        // Process saints in batches
        $importedCount = 0;
        $errorCount = 0;
        $batchCount = 0;
        $totalBatches = ceil(count($saints) / $batchSize);

        foreach (array_chunk($saints, $batchSize) as $batch) {
            $batchCount++;
            $io->section(sprintf('Processing batch %d/%d (%d saints)', $batchCount, $totalBatches, count($batch)));

            foreach ($batch as $saintData) {
                try {
                    $name = $saintData['name'] ?? null;

                    if (!$name) {
                        $io->warning('Skipping saint with no name');
                        $errorCount++;
                        continue;
                    }

                    // Check if saint already exists
                    $existingSaint = $this->entityManager->getRepository(Saint::class)->findOneBy(['name' => $name]);

                    if ($existingSaint) {
                        $io->writeln(sprintf('Saint already exists: %s (ID: %d)', $name, $existingSaint->getId()));
                        $saint = $existingSaint;
                    } else {
                        $io->writeln(sprintf('Creating new saint: %s', $name));
                        $saint = new Saint();
                        $saint->setName($name);
                    }

                    // Set other properties
                    if (isset($saintData['url'])) {
                        $saint->setUrl($saintData['url']);
                    }

                    if (isset($saintData['canonical_status'])) {
                        try {
                            $saint->setCanonicalStatusFromString($saintData['canonical_status']);
                        } catch (\ValueError $e) {
                            $io->warning(sprintf('Invalid canonical status for %s: %s', $name, $e->getMessage()));
                        }
                    }

                    if (isset($saintData['canonization_date'])) {
                        try {
                            // Parse the date string
                            $dateString = $saintData['canonization_date'];
                            // Try to parse the Italian date format (e.g., "22 aprile 1629")
                            $date = $this->parseItalianDate($dateString);
                            $saint->setCanonizationDate($date);
                        } catch (\Exception $e) {
                            $io->warning(sprintf('Could not parse canonization date for %s: %s', $name, $e->getMessage()));
                        }
                    }
                    
                    if (isset($saintData['feast_day'])) {
                        try {
                            // Parse the feast day string
                            $dateString = $saintData['feast_day'];
                            // Try to parse the Italian date format (e.g., "6 gennaio")
                            $date = $this->parseItalianDate($dateString);
                            $saint->setFeastDate($date);
                        } catch (\Exception $e) {
                            $io->warning(sprintf('Could not parse feast date for %s: %s', $name, $e->getMessage()));
                        }
                    }

                    if (isset($saintData['canonizing_pope'])) {
                        $saint->setCanonizingPope($saintData['canonizing_pope']);
                    }

                    if (isset($saintData['saint_phrase'])) {
                        $saint->setSaintPhrase($saintData['saint_phrase']);
                    }

                    if (isset($saintData['abstract'])) {
                        $saint->setAbstract($saintData['abstract']);
                    }

                    if (isset($saintData['biography'])) {
                        $saint->setBiography($saintData['biography']);
                    }

                    if (isset($saintData['image_link'])) {
                        $saint->setImageLink($saintData['image_link']);
                    }

                    if (!$dryRun) {
                        $this->entityManager->persist($saint);
                    }

                    $importedCount++;

                } catch (\Exception $e) {
                    $io->error(sprintf('Error processing saint %s: %s', $saintData['name'] ?? 'Unknown', $e->getMessage()));
                    $errorCount++;
                }
            }

            // Flush after each batch
            if (!$dryRun) {
                try {
                    $io->info('Flushing batch to database...');
                    $this->entityManager->flush();
                    $this->entityManager->clear(); // Clear the entity manager to free up memory
                } catch (\Exception $e) {
                    $io->error(sprintf('Error flushing batch to database: %s', $e->getMessage()));
                    $errorCount += count($batch);
                    $importedCount -= count($batch);
                }
            }
        }

        if ($dryRun) {
            $io->info('Dry run - no changes were made to the database');
        }

        $io->success(sprintf(
            'Import completed: %d saints processed, %d imported, %d errors',
            count($saints),
            $importedCount,
            $errorCount
        ));

        return Command::SUCCESS;
    }

    /**
     * Reset the ID sequence for the Saint entity
     */
    private function resetIdSequence(SymfonyStyle $io): void
    {
        try {
            $connection = $this->entityManager->getConnection();
            $platform = $connection->getDatabasePlatform()->getName();

            $io->info('Resetting ID sequence for Saint entity');

            // First, truncate the table to remove all existing records
            if ($platform === 'postgresql') {
                // For PostgreSQL
                $connection->executeStatement('TRUNCATE TABLE saint RESTART IDENTITY CASCADE');
                $io->success('Successfully reset Saint table and ID sequence for PostgreSQL');
            } else {
                // For other database platforms, just provide a warning
                $io->warning(sprintf(
                    'Automatic sequence reset for %s is not implemented. You may need to manually reset the sequence.',
                    $platform
                ));
            }
        } catch (\Exception $e) {
            $io->error(sprintf('Error resetting ID sequence: %s', $e->getMessage()));
        }
    }

    /**
     * Parse an Italian date string into a DateTime object
     */
    private function parseItalianDate(string $dateString): \DateTime
    {
        // Map of Italian month names to numbers
        $italianMonths = [
            'gennaio' => '01',
            'febbraio' => '02',
            'marzo' => '03',
            'aprile' => '04',
            'maggio' => '05',
            'giugno' => '06',
            'luglio' => '07',
            'agosto' => '08',
            'settembre' => '09',
            'ottobre' => '10',
            'novembre' => '11',
            'dicembre' => '12',
        ];

        // Try to extract day, month, and year (full date format)
        if (preg_match('/(\d{1,2})\s+(\w+)\s+(\d{4})/', $dateString, $matches)) {
            $day = str_pad($matches[1], 2, '0', STR_PAD_LEFT);
            $monthName = strtolower($matches[2]);
            $year = $matches[3];

            if (isset($italianMonths[$monthName])) {
                $month = $italianMonths[$monthName];
                $formattedDate = sprintf('%s-%s-%s', $year, $month, $day);
                return new \DateTime($formattedDate);
            }
        }
        
        // Try to extract just day and month (for feast days that don't include year)
        if (preg_match('/(\d{1,2})\s+(\w+)/', $dateString, $matches)) {
            $day = str_pad($matches[1], 2, '0', STR_PAD_LEFT);
            $monthName = strtolower($matches[2]);
            
            if (isset($italianMonths[$monthName])) {
                $month = $italianMonths[$monthName];
                // Use current year as a placeholder, since we only care about month and day for feast dates
                $currentYear = date('Y');
                $formattedDate = sprintf('%s-%s-%s', $currentYear, $month, $day);
                return new \DateTime($formattedDate);
            }
        }

        throw new \InvalidArgumentException("Could not parse date: $dateString");
    }
}
