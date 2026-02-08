<?php

namespace App\Command;

use App\Entity\Saint;
use App\Entity\SaintTranslation;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Yaml\Yaml;

#[AsCommand(
    name: 'app:import:saint-translations',
    description: 'Import saint translations from YAML files',
)]
class ImportSaintTranslationsCommand extends Command
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
            ->addArgument('locale', InputArgument::REQUIRED, 'The locale code (e.g., pt_BR)')
            ->addArgument('file', InputArgument::OPTIONAL, 'Path to the YAML file (default: data/saints_info_{locale}.yaml)')
            ->setHelp(<<<'HELP'
The <info>%command.name%</info> command imports saint translations from YAML files.

<info>php %command.full_name% pt_BR</info>

This will import translations from data/saints_info_pt_BR.yaml for the pt_BR locale.
You can also specify a custom file path:

<info>php %command.full_name% pt_BR /path/to/custom/file.yaml</info>
HELP
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $locale = $input->getArgument('locale');
        $filePath = $input->getArgument('file') ?? sprintf('data/saints_info_%s.yaml', $locale);

        if (!file_exists($filePath)) {
            $io->error(sprintf('File not found: %s', $filePath));
            return Command::FAILURE;
        }

        $io->title(sprintf('Importing saint translations for locale "%s" from "%s"', $locale, $filePath));

        try {
            $data = Yaml::parseFile($filePath);
        } catch (\Exception $e) {
            $io->error(sprintf('Error parsing YAML file: %s', $e->getMessage()));
            return Command::FAILURE;
        }

        if (!isset($data['saints']) || !is_array($data['saints'])) {
            $io->error('Invalid YAML structure: "saints" key not found or not an array');
            return Command::FAILURE;
        }

        $saintRepository = $this->entityManager->getRepository(Saint::class);
        $translationRepository = $this->entityManager->getRepository(SaintTranslation::class);

        $totalSaints = count($data['saints']);
        $io->progressStart($totalSaints);

        $imported = 0;
        $skipped = 0;
        $errors = [];

        foreach ($data['saints'] as $saintData) {
            $io->progressAdvance();

            if (!isset($saintData['id'])) {
                $errors[] = sprintf('Saint without ID: %s', $saintData['name'] ?? 'Unknown');
                continue;
            }

            $saint = $saintRepository->find($saintData['id']);
            if (!$saint) {
                $errors[] = sprintf('Saint with ID %d not found: %s', $saintData['id'], $saintData['name'] ?? 'Unknown');
                $skipped++;
                continue;
            }

            // Check if translation already exists
            $existingTranslation = $translationRepository->findOneBy([
                'saint' => $saint,
                'locale' => $locale,
            ]);

            if ($existingTranslation) {
                // Update existing translation
                if (isset($saintData['name'])) {
                    $existingTranslation->setName($saintData['name']);
                }
                if (isset($saintData['saint_phrase'])) {
                    $existingTranslation->setSaintPhrase($saintData['saint_phrase']);
                }
                if (isset($saintData['biography'])) {
                    $existingTranslation->setBiography($saintData['biography']);
                }
                if (isset($saintData['abstract'])) {
                    $existingTranslation->setAbstract($saintData['abstract']);
                }
                $this->entityManager->persist($existingTranslation);
            } else {
                // Create new translation
                $translation = new SaintTranslation();
                $translation->setSaint($saint);
                $translation->setLocale($locale);
                
                if (isset($saintData['name'])) {
                    $translation->setName($saintData['name']);
                }
                
                if (isset($saintData['saint_phrase'])) {
                    $translation->setSaintPhrase($saintData['saint_phrase']);
                }

                if (isset($saintData['biography'])) {
                    $translation->setBiography($saintData['biography']);
                }

                if (isset($saintData['abstract'])) {
                    $translation->setAbstract($saintData['abstract']);
                }
                
                $this->entityManager->persist($translation);
                $saint->addTranslation($translation);
            }

            $imported++;

            // Flush periodically to avoid memory issues
            if ($imported % 50 === 0) {
                $this->entityManager->flush();
                $this->entityManager->clear(SaintTranslation::class);
            }
        }

        // Final flush
        $this->entityManager->flush();
        $io->progressFinish();

        $io->success([
            sprintf('Import completed for locale "%s"', $locale),
            sprintf('Imported: %d', $imported),
            sprintf('Skipped: %d', $skipped),
        ]);

        if (count($errors) > 0) {
            $io->warning('The following errors occurred:');
            $io->listing($errors);
        }

        return Command::SUCCESS;
    }
}