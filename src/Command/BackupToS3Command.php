<?php

declare(strict_types=1);

namespace App\Command;

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
    name: 'app:backup:s3',
    description: 'Dump PostgreSQL and MongoDB, then upload artifacts to a private S3 bucket',
)]
final class BackupToS3Command extends Command
{
    public function __construct(
        private readonly S3DatabaseBackupService $backupService,
        #[Autowire(env: 'DATABASE_URL')]
        private readonly string $databaseUrl,
        #[Autowire(env: 'MONGODB_URL')]
        private readonly string $mongoUrl,
        #[Autowire(env: 'MONGODB_DATABASE')]
        private readonly string $mongoDatabase,
        #[Autowire('%kernel.project_dir%')]
        private readonly string $projectDir,
        #[Autowire('%app.version%')]
        private readonly string $appVersion,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Print actions without dumping or uploading')
            ->addOption('with-local-uploads', null, InputOption::VALUE_NONE, 'Include public/uploads/images as uploads.tar.gz (can be large)')
            ->setHelp(
                <<<'HELP'
Creates a timestamped folder under AWS_BACKUP_PREFIX containing:
  - postgres.dump.gz (custom-format pg_dump)
  - mongo.archive.gz (mongodump --archive --gzip)
  - manifest.json (metadata)

Requires AWS_BACKUP_BUCKET and standard AWS credentials (see docs/DEPLOYMENT.md).
The production Docker image includes pg_dump and mongodump.
HELP
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $dryRun = (bool) $input->getOption('dry-run');
        $withUploads = (bool) $input->getOption('with-local-uploads');

        if (!$dryRun && !$this->backupService->isBucketConfigured()) {
            $io->error('AWS_BACKUP_BUCKET is not set. See docs/DEPLOYMENT.md for configuration.');

            return Command::FAILURE;
        }

        $backupId = gmdate('Y-m-d') . 'T' . gmdate('H-i-s') . 'Z';
        $folderPrefix = $this->backupService->backupFolderKey($backupId);

        $pg = $this->backupService->parsePostgresUrl($this->databaseUrl);

        $tmpBase = sys_get_temp_dir() . '/reliquary-backup-' . bin2hex(random_bytes(8));
        $pgDumpPath = $tmpBase . '/postgres.dump';
        $pgGzPath = $tmpBase . '/postgres.dump.gz';
        $mongoArchivePath = $tmpBase . '/mongo.archive.gz';
        $uploadsTarPath = $tmpBase . '/uploads.tar.gz';

        $pgDumpCmd = [
            'pg_dump',
            '-h', $pg['host'],
            '-p', (string) $pg['port'],
            '-U', $pg['user'],
            '-Fc',
            '-f', $pgDumpPath,
            $pg['dbname'],
        ];

        $mongoCmd = [
            'mongodump',
            '--uri=' . $this->mongoUrl,
            '--db=' . $this->mongoDatabase,
            '--archive=' . $mongoArchivePath,
            '--gzip',
        ];

        $keys = [
            'postgres' => $folderPrefix . 'postgres.dump.gz',
            'mongo' => $folderPrefix . 'mongo.archive.gz',
            'manifest' => $folderPrefix . 'manifest.json',
        ];

        if ($withUploads) {
            $keys['uploads'] = $folderPrefix . 'uploads.tar.gz';
        }

        if ($dryRun) {
            if (!$this->backupService->isBucketConfigured()) {
                $io->warning('AWS_BACKUP_BUCKET is not set; the real run would fail until it is configured.');
            }
            $io->title('Dry run: app:backup:s3');
            $io->section('Backup folder (S3 key prefix)');
            $io->writeln($folderPrefix);
            $io->section('PostgreSQL');
            $io->writeln($this->escapeArgv($pgDumpCmd));
            $io->section('MongoDB');
            $io->writeln($this->escapeArgv($mongoCmd));
            if ($withUploads) {
                $uploadsDir = $this->projectDir . '/public/uploads/images';
                $io->section('Local uploads archive');
                $io->writeln(sprintf('tar -czf uploads.tar.gz -C %s .', $uploadsDir));
            }
            $io->section('S3 object keys');
            foreach ($keys as $label => $key) {
                $io->writeln(sprintf('  [%s] %s', $label, $key));
            }

            return Command::SUCCESS;
        }

        $this->backupService->requireBucket();

        if (!mkdir($tmpBase, 0o700, true) && !is_dir($tmpBase)) {
            $io->error(sprintf('Cannot create temp directory "%s".', $tmpBase));

            return Command::FAILURE;
        }

        try {
            $io->title('Backing up PostgreSQL');
            $dumpProcess = new Process($pgDumpCmd);
            $dumpProcess->setTimeout(3600.0);
            $dumpProcess->setEnv(['PGPASSWORD' => $pg['password']]);
            $dumpProcess->mustRun();
            if (!is_file($pgDumpPath) || filesize($pgDumpPath) === 0) {
                throw new \RuntimeException('pg_dump did not produce a non-empty file.');
            }

            $gzipPg = new Process(['gzip', '-9', '-n', $pgDumpPath]);
            $gzipPg->setTimeout(3600.0);
            $gzipPg->mustRun();
            if (!is_file($pgGzPath)) {
                throw new \RuntimeException('gzip did not produce postgres.dump.gz.');
            }

            $pgGzBytes = filesize($pgGzPath);
            if ($pgGzBytes === false) {
                throw new \RuntimeException('Could not read postgres.dump.gz size.');
            }
            $io->success(sprintf('PostgreSQL dump: %s bytes', number_format((float) $pgGzBytes)));

            $io->title('Backing up MongoDB');
            $mongoProcess = new Process($mongoCmd);
            $mongoProcess->setTimeout(3600.0);
            $mongoProcess->mustRun();
            if (!is_file($mongoArchivePath) || filesize($mongoArchivePath) === 0) {
                throw new \RuntimeException('mongodump did not produce a non-empty archive.');
            }

            $mongoBytes = filesize($mongoArchivePath);
            if ($mongoBytes === false) {
                throw new \RuntimeException('Could not read mongo.archive.gz size.');
            }
            $io->success(sprintf('MongoDB archive: %s bytes', number_format((float) $mongoBytes)));

            $uploadsKey = null;
            if ($withUploads) {
                $io->title('Archiving local uploads');
                $uploadsDir = $this->projectDir . '/public/uploads/images';
                if (!is_dir($uploadsDir)) {
                    throw new \RuntimeException(sprintf('Upload directory missing: %s', $uploadsDir));
                }
                $tar = new Process(['tar', '-czf', $uploadsTarPath, '-C', $uploadsDir, '.']);
                $tar->setTimeout(3600.0);
                $tar->mustRun();
                $uploadsBytes = filesize($uploadsTarPath);
                if ($uploadsBytes === false) {
                    throw new \RuntimeException('Could not read uploads.tar.gz size.');
                }
                $io->success(sprintf('uploads.tar.gz: %s bytes', number_format((float) $uploadsBytes)));
                $uploadsKey = $keys['uploads'];
            }

            $io->title('Uploading to S3');
            $this->backupService->uploadLocalFile($pgGzPath, $keys['postgres']);
            $io->writeln('  Uploaded postgres.dump.gz');
            $this->backupService->uploadLocalFile($mongoArchivePath, $keys['mongo']);
            $io->writeln('  Uploaded mongo.archive.gz');

            if ($withUploads && isset($keys['uploads'])) {
                $this->backupService->uploadLocalFile($uploadsTarPath, $keys['uploads']);
                $io->writeln('  Uploaded uploads.tar.gz');
            }

            $manifest = [
                'backup_id' => $backupId,
                'created_at' => gmdate('c'),
                'app_version' => $this->appVersion,
                'postgres_key' => $keys['postgres'],
                'mongo_key' => $keys['mongo'],
                'includes_local_uploads' => $withUploads,
            ];
            if ($withUploads) {
                $manifest['uploads_key'] = $keys['uploads'];
            }

            $this->backupService->uploadJsonManifest($manifest, $keys['manifest']);
            $io->writeln('  Uploaded manifest.json');

            $io->success('Backup completed.');
            $io->note('S3 prefix: ' . $folderPrefix);
        } catch (\Throwable $e) {
            $io->error($e->getMessage());

            return Command::FAILURE;
        } finally {
            $this->removeTree($tmpBase);
        }

        return Command::SUCCESS;
    }

    /**
     * @param list<string> $argv
     */
    private function escapeArgv(array $argv): string
    {
        return implode(' ', array_map(static function (string $part): string {
            return preg_match('/[\s\'"]/', $part) ? escapeshellarg($part) : $part;
        }, $argv));
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
