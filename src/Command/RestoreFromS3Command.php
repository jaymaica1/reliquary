<?php

declare(strict_types=1);

namespace App\Command;

use App\Service\Backup\MongoBackupUriHelper;
use App\Service\Backup\S3DatabaseBackupService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Process\Process;

#[AsCommand(
    name: 'app:restore:s3',
    description: 'Download a backup from private S3 and restore PostgreSQL and MongoDB',
)]
final class RestoreFromS3Command extends Command
{
    public function __construct(
        private readonly S3DatabaseBackupService $backupService,
        #[Autowire(env: 'DATABASE_URL')]
        private readonly string $databaseUrl,
        #[Autowire(env: 'resolve:MONGODB_URL')]
        private readonly string $mongoUrl,
        #[Autowire(env: 'MONGODB_DATABASE')]
        private readonly string $mongoDatabase,
        #[Autowire('%kernel.project_dir%')]
        private readonly string $projectDir,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('latest', null, InputOption::VALUE_NONE, 'Use the most recent backup (by manifest.json LastModified)')
            ->addOption('backup-id', null, InputOption::VALUE_REQUIRED, 'Backup folder id under AWS_BACKUP_PREFIX, e.g. 2026-04-12T15-30-45Z')
            ->addOption('force', null, InputOption::VALUE_NONE, 'Required. Confirms you intend to overwrite data in the target databases')
            ->addOption('drop-mongo', null, InputOption::VALUE_NONE, 'Pass --drop to mongorestore (removes existing collections in the DB)')
            ->addOption('clean-postgres', null, InputOption::VALUE_NONE, 'Pass --clean --if-exists to pg_restore')
            ->addOption('with-local-uploads', null, InputOption::VALUE_NONE, 'If the backup contains uploads.tar.gz, extract into public/uploads/images')
            ->setHelp(
                <<<'HELP'
Destructive: overwrites data in the databases pointed to by DATABASE_URL and MONGODB_*.

Requires --force. Use --latest or --backup-id to choose an artifact set.

Restore order: PostgreSQL, then MongoDB (unless the backup was made with app:backup:s3 --postgres-only).

Optional: --with-local-uploads restores the tar archive of public/uploads/images when present.
HELP
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        if (!$input->getOption('force')) {
            $io->error('Refusing to run without --force. This operation overwrites database content.');

            return Command::FAILURE;
        }

        if (!$this->backupService->isBucketConfigured()) {
            $io->error('AWS_BACKUP_BUCKET is not set. See docs/DEPLOYMENT.md for configuration.');

            return Command::FAILURE;
        }

        $latest = (bool) $input->getOption('latest');
        $backupIdRaw = $input->getOption('backup-id');
        $hasBackupId = \is_string($backupIdRaw) && $backupIdRaw !== '';

        if ($latest === $hasBackupId) {
            $io->error('Specify exactly one of --latest or --backup-id.');

            return Command::FAILURE;
        }

        $this->backupService->requireBucket();

        if ($latest) {
            $keyPrefix = $this->backupService->findLatestBackupKeyPrefix();
            if ($keyPrefix === null) {
                $io->error('No backup with manifest.json found under the configured prefix.');

                return Command::FAILURE;
            }
            $io->note('Using latest backup prefix: ' . $keyPrefix);
        } else {
            $keyPrefix = $this->backupService->backupFolderKey($backupIdRaw);
        }

        $dropMongo = (bool) $input->getOption('drop-mongo');
        $cleanPostgres = (bool) $input->getOption('clean-postgres');
        $withUploads = (bool) $input->getOption('with-local-uploads');

        $tmpBase = sys_get_temp_dir() . '/reliquary-restore-' . bin2hex(random_bytes(8));
        if (!mkdir($tmpBase, 0o700, true) && !is_dir($tmpBase)) {
            $io->error(sprintf('Cannot create temp directory "%s".', $tmpBase));

            return Command::FAILURE;
        }

        try {
            $io->title('Downloading backup from S3');
            $this->backupService->downloadBackupFolder($keyPrefix, $tmpBase);

            $pgGz = $tmpBase . '/postgres.dump.gz';
            $mongoGz = $tmpBase . '/mongo.archive.gz';
            $manifestPath = $tmpBase . '/manifest.json';
            $postgresOnly = false;
            if (is_file($manifestPath)) {
                try {
                    $decoded = json_decode((string) file_get_contents($manifestPath), true, 512, JSON_THROW_ON_ERROR);
                    if (\is_array($decoded)) {
                        $postgresOnly = (bool) ($decoded['postgres_only'] ?? false);
                    }
                } catch (\JsonException) {
                    // Malformed manifest: require mongo artifact like legacy backups.
                }
            }
            if (!is_file($pgGz)) {
                $io->error('postgres.dump.gz missing in backup folder.');

                return Command::FAILURE;
            }
            if (!is_file($mongoGz) && !$postgresOnly) {
                $io->error('mongo.archive.gz missing in backup folder.');

                return Command::FAILURE;
            }

            $pg = $this->backupService->parsePostgresUrl($this->databaseUrl);
            $pgPlain = $tmpBase . '/postgres.dump';

            $io->title('Restoring PostgreSQL');
            $decompress = new Process(['bash', '-c', 'gunzip -c -- ' . escapeshellarg($pgGz) . ' > ' . escapeshellarg($pgPlain)]);
            $decompress->setTimeout(3600.0);
            $decompress->mustRun();
            if (!is_file($pgPlain) || filesize($pgPlain) === 0) {
                throw new \RuntimeException('Decompressed PostgreSQL dump is empty.');
            }

            $pgRestoreArgs = [
                'pg_restore',
                '-h', $pg['host'],
                '-p', (string) $pg['port'],
                '-U', $pg['user'],
                '--no-owner',
                '--no-acl',
                '-d', $pg['dbname'],
            ];
            if ($cleanPostgres) {
                $pgRestoreArgs[] = '--clean';
                $pgRestoreArgs[] = '--if-exists';
            }
            $pgRestoreArgs[] = $pgPlain;

            $restoreProcess = new Process($pgRestoreArgs);
            $restoreProcess->setTimeout(3600.0);
            $restoreProcess->setEnv(['PGPASSWORD' => $pg['password']]);
            $restoreProcess->run();

            $exitCode = $restoreProcess->getExitCode();
            $errorOutput = $restoreProcess->getErrorOutput();
            $benignPg17DumpOnOlderServer = self::isOnlyIgnoredTransactionTimeoutRestoreError($errorOutput, $exitCode);

            if ($benignPg17DumpOnOlderServer) {
                $io->success('PostgreSQL restore finished.');
                $io->note(
                    'pg_dump from PostgreSQL 17+ embeds SET transaction_timeout; older servers reject that GUC, '
                    . 'so pg_restore reported one ignored error. The rest of the restore completed. '
                    . 'Use PostgreSQL 17+ on the restore target to avoid this, or verify data if unsure.'
                );
            } elseif (!$restoreProcess->isSuccessful()) {
                $io->warning('pg_restore exited with code: ' . (string) $exitCode);
                $io->writeln('');

                if ($errorOutput !== '') {
                    $io->section('STDERR Output:');
                    $io->writeln($errorOutput);
                } else {
                    $io->warning('No error output captured on stderr.');
                }

                $stdout = $restoreProcess->getOutput();
                if ($stdout !== '') {
                    $io->section('STDOUT Output:');
                    $io->writeln($stdout);
                }

                $io->note('Tip: This can happen if objects already exist without --clean-postgres flag.');
                $io->note('Database: ' . $pg['dbname'] . ' | Host: ' . $pg['host'] . ':' . $pg['port']);
            } else {
                $io->success('PostgreSQL restore finished.');
            }

            if ($postgresOnly) {
                $io->title('Restoring MongoDB');
                $io->note('Skipped: this backup was created with --postgres-only (manifest.json).');
            } else {
                $io->title('Restoring MongoDB');
                $mongoUriForTools = MongoBackupUriHelper::forMongoTools($this->mongoUrl);
                $mongoArgs = [
                    'mongorestore',
                    '--uri=' . $mongoUriForTools,
                    '--gzip',
                    '--archive=' . $mongoGz,
                    '--nsInclude=' . $this->mongoDatabase . '.*',
                ];
                if ($dropMongo) {
                    $mongoArgs[] = '--drop';
                }

                $mongoProcess = new Process($mongoArgs);
                $mongoProcess->setTimeout(3600.0);
                $mongoProcess->mustRun();
                $io->success('MongoDB restore finished.');
            }

            if ($withUploads) {
                $uploadsTar = $tmpBase . '/uploads.tar.gz';
                if (!is_file($uploadsTar)) {
                    $io->warning('No uploads.tar.gz in this backup; skipping local uploads restore.');
                } else {
                    $io->title('Restoring local uploads');
                    $uploadsDir = $this->projectDir . '/public/uploads/images';
                    if (!is_dir($uploadsDir) && !mkdir($uploadsDir, 0o775, true) && !is_dir($uploadsDir)) {
                        throw new \RuntimeException(sprintf('Cannot create uploads directory "%s".', $uploadsDir));
                    }
                    $tar = new Process(['tar', '-xzf', $uploadsTar, '-C', $uploadsDir]);
                    $tar->setTimeout(3600.0);
                    $tar->mustRun();
                    $io->success('Extracted uploads.tar.gz into public/uploads/images');
                }
            }

            $io->success('Restore completed for prefix: ' . $keyPrefix);
        } catch (\Throwable $e) {
            $io->error($e->getMessage());

            return Command::FAILURE;
        } finally {
            $this->removeTree($tmpBase);
        }

        return Command::SUCCESS;
    }

    /**
     * PostgreSQL 17+ pg_dump emits SET transaction_timeout in the archive; restoring into an older major version
     * fails that statement while pg_restore continues and exits 1 with exactly one ignored error.
     */
    private static function isOnlyIgnoredTransactionTimeoutRestoreError(string $stderr, ?int $exitCode): bool
    {
        if ($stderr === '' || $exitCode !== 1) {
            return false;
        }

        if (!str_contains($stderr, 'transaction_timeout')) {
            return false;
        }

        if (!preg_match('/errors ignored on restore:\s*(\d+)/', $stderr, $m)) {
            return false;
        }

        if ((int) $m[1] !== 1) {
            return false;
        }

        return 1 === preg_match_all('/^pg_restore: error:/m', $stderr);
    }

    private function removeTree(string $path): void
    {
        if (!is_dir($path)) {
            return;
        }
        $it = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($path, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($it as $fileInfo) {
            $p = $fileInfo->getPathname();
            if ($fileInfo->isDir()) {
                @rmdir($p);
            } else {
                @unlink($p);
            }
        }
        @rmdir($path);
    }
}
