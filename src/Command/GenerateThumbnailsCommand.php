<?php

namespace App\Command;

use App\Entity\AbstractImage;
use App\Entity\RelicImage;
use App\Entity\SaintImage;
use App\Entity\UserImage;
use App\Service\ImageService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:generate-thumbnails',
    description: 'Generate thumbnails for existing images',
)]
class GenerateThumbnailsCommand extends Command
{
    private EntityManagerInterface $entityManager;
    private ImageService $imageService;

    public function __construct(
        EntityManagerInterface $entityManager,
        ImageService $imageService
    ) {
        parent::__construct();
        $this->entityManager = $entityManager;
        $this->imageService = $imageService;
    }

    protected function configure(): void
    {
        $this
            ->addOption(
                'force',
                'f',
                InputOption::VALUE_NONE,
                'Force regeneration of all thumbnails, even if they already exist'
            )
            ->addOption(
                'relic',
                'r',
                InputOption::VALUE_REQUIRED,
                'Generate thumbnails only for images related to this relic ID'
            )
            ->addOption(
                'saint',
                's',
                InputOption::VALUE_REQUIRED,
                'Generate thumbnails only for images related to this saint ID'
            )
            ->addOption(
                'user',
                'u',
                InputOption::VALUE_REQUIRED,
                'Generate thumbnails only for images related to this user ID'
            )
            ->setHelp('This command generates thumbnails for all existing images that do not have thumbnails yet. Use the --force option to regenerate all thumbnails. You can also target a specific entity using --relic, --saint, or --user.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $force = $input->getOption('force');
        $relicId = $input->getOption('relic');
        $saintId = $input->getOption('saint');
        $userId = $input->getOption('user');
        
        if ($force) {
            $io->title('Force regenerating thumbnails');
        } else {
            $io->title('Generating thumbnails for existing images without thumbnails');
        }

        // Process RelicImages
        if (!$saintId && !$userId) {
            $this->processThumbnails(RelicImage::class, $io, $force, $relicId ? ['relic' => $relicId] : []);
        }

        // Process SaintImages
        if (!$relicId && !$userId) {
            $this->processThumbnails(SaintImage::class, $io, $force, $saintId ? ['saint' => $saintId] : []);
        }

        // Process UserImages
        if (!$relicId && !$saintId) {
            $this->processThumbnails(UserImage::class, $io, $force, $userId ? ['user' => $userId] : []);
        }

        $io->success('All thumbnails have been generated successfully.');

        return Command::SUCCESS;
    }

    private function processThumbnails(string $entityClass, SymfonyStyle $io, bool $force = false, array $criteria = []): void
    {
        $io->section(sprintf('Processing %s', $entityClass));

        $repository = $this->entityManager->getRepository($entityClass);
        $images = $repository->findBy($criteria);

        $totalImages = count($images);
        $processedImages = 0;
        $skippedImages = 0;
        $errorImages = 0;
        $regeneratedImages = 0;

        $io->progressStart($totalImages);

        foreach ($images as $image) {
            $io->progressAdvance();

            if ($image->getThumbnailFilename() !== null && !$force) {
                $skippedImages++;
                continue;
            }

            try {
                // If force is true and thumbnail already exists, we're regenerating
                $isRegeneration = $force && $image->getThumbnailFilename() !== null;
                
                $this->generateThumbnail($image);
                
                if ($isRegeneration) {
                    $regeneratedImages++;
                } else {
                    $processedImages++;
                }
            } catch (\Exception $e) {
                $errorImages++;
                $io->error(sprintf('Error processing image %s: %s', $image->getFilename(), $e->getMessage()));
            }
        }

        $io->progressFinish();
        
        if ($force) {
            $io->table(
                ['Total', 'Processed', 'Regenerated', 'Skipped', 'Errors'],
                [[$totalImages, $processedImages, $regeneratedImages, $skippedImages, $errorImages]]
            );
        } else {
            $io->table(
                ['Total', 'Processed', 'Skipped', 'Errors'],
                [[$totalImages, $processedImages, $skippedImages, $errorImages]]
            );
        }
    }

    private function generateThumbnail(AbstractImage $image): void
    {
        $filename = $image->getFilename();
        $filesystem = $this->imageService->getFilesystem();

        if (!$filesystem->fileExists($filename)) {
            throw new \Exception(sprintf('Original image file not found in storage: %s', $filename));
        }

        // Download the original image to a temporary file
        $tempOriginalFile = tempnam(sys_get_temp_dir(), 'orig_');
        file_put_contents($tempOriginalFile, $filesystem->read($filename));

        $pathInfo = pathinfo($filename);
        $thumbnailFilename = 'thumb_' . basename($filename);
        $thumbnailPath = ($pathInfo['dirname'] !== '.' ? $pathInfo['dirname'] . '/' : '') . $thumbnailFilename;
        
        // Generate and upload thumbnail using the ImageService
        $this->imageService->generateAndUploadThumbnail($tempOriginalFile, $thumbnailPath);
        
        // Clean up temporary file
        unlink($tempOriginalFile);
        
        // Update the entity
        $image->setThumbnailFilename($thumbnailPath);
        
        $this->entityManager->persist($image);
        $this->entityManager->flush();
    }
}