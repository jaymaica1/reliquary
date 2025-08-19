<?php

namespace App\Command;

use App\Entity\AbstractImage;
use App\Entity\RelicImage;
use App\Entity\SaintImage;
use App\Entity\UserImage;
use Doctrine\ORM\EntityManagerInterface;
use League\Flysystem\FilesystemOperator;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\String\Slugger\SluggerInterface;

#[AsCommand(
    name: 'app:migrate-images-to-s3',
    description: 'Migrate local images to S3 storage',
)]
class MigrateImagesToS3Command extends Command
{
    private EntityManagerInterface $entityManager;
    private FilesystemOperator $filesystem;
    private SluggerInterface $slugger;
    private string $uploadDir;

    public function __construct(
        EntityManagerInterface $entityManager,
        FilesystemOperator $defaultStorage,
        SluggerInterface $slugger,
        string $uploadDir
    ) {
        parent::__construct();
        $this->entityManager = $entityManager;
        $this->filesystem = $defaultStorage;
        $this->slugger = $slugger;
        $this->uploadDir = $uploadDir;
    }

    protected function configure(): void
    {
        $this
            ->addOption(
                'dry-run',
                'd',
                InputOption::VALUE_NONE,
                'Show what would be migrated without actually performing the migration'
            )
            ->addOption(
                'force',
                'f',
                InputOption::VALUE_NONE,
                'Force migration even if images already exist on S3'
            )
            ->addOption(
                'delete-local',
                't',
                InputOption::VALUE_NONE,
                'Delete local files after successful migration (includes integrity verification)'
            )
            ->setHelp('This command migrates local images to S3 storage. Local images are identified by checking if they exist in the local uploads directory. Use --dry-run to see what would be migrated without performing the actual migration. Use --delete-local to remove local files after successful migration with verification.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $dryRun = $input->getOption('dry-run');
        $force = $input->getOption('force');
        $deleteLocal = $input->getOption('delete-local');

        // Safety check for delete-local option
        if ($deleteLocal && $dryRun) {
            $io->warning('--delete-local option is ignored in dry-run mode');
            $deleteLocal = false;
        }

        if ($deleteLocal && !$dryRun) {
            $io->warning('You have enabled --delete-local. Local files will be permanently deleted after successful S3 migration.');
            if (!$io->confirm('Are you sure you want to proceed? This action cannot be undone.', false)) {
                $io->note('Operation cancelled by user.');
                return Command::SUCCESS;
            }
        }

        if ($dryRun) {
            $io->title('DRY RUN: Checking which images would be migrated to S3');
        } else {
            $title = 'Migrating local images to S3';
            if ($deleteLocal) {
                $title .= ' (with local file cleanup)';
            }
            $io->title($title);
        }

        $totalMigrated = 0;
        $totalSkipped = 0;
        $totalErrors = 0;
        $totalDeleted = 0;

        // Process RelicImages
        $stats = $this->processImageMigration(RelicImage::class, $io, $dryRun, $force, $deleteLocal);
        $totalMigrated += $stats['migrated'];
        $totalSkipped += $stats['skipped'];
        $totalErrors += $stats['errors'];
        $totalDeleted += $stats['deleted'];

        // Process SaintImages
        $stats = $this->processImageMigration(SaintImage::class, $io, $dryRun, $force, $deleteLocal);
        $totalMigrated += $stats['migrated'];
        $totalSkipped += $stats['skipped'];
        $totalErrors += $stats['errors'];
        $totalDeleted += $stats['deleted'];

        // Process UserImages
        $stats = $this->processImageMigration(UserImage::class, $io, $dryRun, $force, $deleteLocal);
        $totalMigrated += $stats['migrated'];
        $totalSkipped += $stats['skipped'];
        $totalErrors += $stats['errors'];
        $totalDeleted += $stats['deleted'];

        // Summary
        if ($deleteLocal && !$dryRun) {
            $io->table(
                ['Total Migrated', 'Total Skipped', 'Total Errors', 'Total Deleted'],
                [[$totalMigrated, $totalSkipped, $totalErrors, $totalDeleted]]
            );
        } else {
            $io->table(
                ['Total Migrated', 'Total Skipped', 'Total Errors'],
                [[$totalMigrated, $totalSkipped, $totalErrors]]
            );
        }

        if ($dryRun) {
            $io->success('Dry run completed. Use without --dry-run to perform the actual migration.');
        } elseif ($totalErrors === 0) {
            $message = 'All eligible images have been migrated successfully.';
            if ($deleteLocal && $totalDeleted > 0) {
                $message .= sprintf(' %d local files were deleted.', $totalDeleted);
            }
            $io->success($message);
        } else {
            $io->warning(sprintf('Migration completed with %d errors. Check the output above for details.', $totalErrors));
        }

        return $totalErrors === 0 ? Command::SUCCESS : Command::FAILURE;
    }

    private function processImageMigration(string $entityClass, SymfonyStyle $io, bool $dryRun, bool $force, bool $deleteLocal): array
    {
        $io->section(sprintf('Processing %s', $entityClass));

        $repository = $this->entityManager->getRepository($entityClass);
        $images = $repository->findAll();

        $totalImages = count($images);
        $migratedImages = 0;
        $skippedImages = 0;
        $skippedNoLocalFile = 0;
        $skippedAlreadyOnS3 = 0;
        $errorImages = 0;
        $deletedFiles = 0;

        if ($totalImages === 0) {
            $io->text('No images found.');
            return ['migrated' => 0, 'skipped' => 0, 'errors' => 0, 'deleted' => 0];
        }

        $io->progressStart($totalImages);

        foreach ($images as $image) {
            $io->progressAdvance();

            try {
                $result = $this->processImage($image, $dryRun, $force, $deleteLocal);
                
                switch ($result['status']) {
                    case 'migrated':
                        $migratedImages++;
                        $deletedFiles += $result['deleted'];
                        break;
                    case 'skipped-no-local':
                        $skippedImages++;
                        $skippedNoLocalFile++;
                        break;
                    case 'skipped-already-s3':
                        $skippedImages++;
                        $skippedAlreadyOnS3++;
                        break;
                    case 'error':
                        $errorImages++;
                        break;
                }
            } catch (\Exception $e) {
                $errorImages++;
                if (!$dryRun) {
                    $io->error(sprintf('Error processing image %s: %s', $image->getFilename(), $e->getMessage()));
                }
            }
        }

        $io->progressFinish();

        if ($deleteLocal && !$dryRun) {
            $io->table(
                ['Total', 'Migrated', 'Skipped', '- No Local', '- Already S3', 'Errors', 'Deleted'],
                [[$totalImages, $migratedImages, $skippedImages, $skippedNoLocalFile, $skippedAlreadyOnS3, $errorImages, $deletedFiles]]
            );
        } else {
            $io->table(
                ['Total', 'Migrated', 'Skipped', '- No Local', '- Already S3', 'Errors'],
                [[$totalImages, $migratedImages, $skippedImages, $skippedNoLocalFile, $skippedAlreadyOnS3, $errorImages]]
            );
        }

        return ['migrated' => $migratedImages, 'skipped' => $skippedImages, 'errors' => $errorImages, 'deleted' => $deletedFiles];
    }

    private function processImage(AbstractImage $image, bool $dryRun, bool $force, bool $deleteLocal): array
    {
        $filename = $image->getFilename();
        $thumbnailFilename = $image->getThumbnailFilename();
        
        // The filename already includes the subdirectory path (e.g., "e/5/filename.jpg")
        $localImagePath = $this->uploadDir . '/images/' . $filename;
        $localThumbnailPath = $thumbnailFilename ? $this->uploadDir . '/images/' . $thumbnailFilename : null;

        // Check if image exists locally (indicating it needs migration)
        if (!file_exists($localImagePath)) {
            return ['status' => 'skipped-no-local', 'deleted' => 0];
        }

        // Check if already on S3 (unless force is used)
        if (!$force && $this->filesystem->fileExists($filename)) {
            return ['status' => 'skipped-already-s3', 'deleted' => 0];
        }

        if ($dryRun) {
            return ['status' => 'migrated', 'deleted' => 0]; // Would be migrated
        }

        $deletedCount = 0;

        try {
            // Upload original image to S3
            $originalSize = filesize($localImagePath);
            $imageStream = fopen($localImagePath, 'r');
            if (!$imageStream) {
                throw new \Exception(sprintf('Cannot open local file: %s', $localImagePath));
            }

            $this->filesystem->writeStream($filename, $imageStream);
            fclose($imageStream);

            // Verify upload integrity
            if ($this->filesystem->fileExists($filename)) {
                $uploadedSize = $this->filesystem->fileSize($filename);
                if ($uploadedSize !== $originalSize) {
                    throw new \Exception(sprintf('Upload verification failed for %s: size mismatch (local: %d, S3: %d)', $filename, $originalSize, $uploadedSize));
                }
            } else {
                throw new \Exception(sprintf('Upload verification failed for %s: file not found on S3 after upload', $filename));
            }

            // Upload thumbnail if it exists locally
            if ($thumbnailFilename && $localThumbnailPath && file_exists($localThumbnailPath)) {
                $thumbnailOriginalSize = filesize($localThumbnailPath);
                $thumbnailStream = fopen($localThumbnailPath, 'r');
                if ($thumbnailStream) {
                    $this->filesystem->writeStream($thumbnailFilename, $thumbnailStream);
                    fclose($thumbnailStream);

                    // Verify thumbnail upload
                    if ($this->filesystem->fileExists($thumbnailFilename)) {
                        $thumbnailUploadedSize = $this->filesystem->fileSize($thumbnailFilename);
                        if ($thumbnailUploadedSize !== $thumbnailOriginalSize) {
                            throw new \Exception(sprintf('Thumbnail upload verification failed for %s: size mismatch (local: %d, S3: %d)', $thumbnailFilename, $thumbnailOriginalSize, $thumbnailUploadedSize));
                        }
                    } else {
                        throw new \Exception(sprintf('Thumbnail upload verification failed for %s: file not found on S3 after upload', $thumbnailFilename));
                    }
                }
            }

            // Delete local files only after successful upload and verification
            if ($deleteLocal) {
                if (unlink($localImagePath)) {
                    $deletedCount++;
                }
                if ($thumbnailFilename && $localThumbnailPath && file_exists($localThumbnailPath)) {
                    if (unlink($localThumbnailPath)) {
                        $deletedCount++;
                    }
                }
            }

            return ['status' => 'migrated', 'deleted' => $deletedCount];

        } catch (\Exception $e) {
            // If there was an error and we were supposed to delete, make sure we don't delete
            throw new \Exception(sprintf('Migration failed for %s: %s', $filename, $e->getMessage()));
        }
    }

    private function getUploadPath(string $originalFilename): string
    {
        $hash = substr(md5($originalFilename . time()), 0, 2);
        $subDir = $hash[0] . '/' . $hash[1];
        return $subDir;
    }
}