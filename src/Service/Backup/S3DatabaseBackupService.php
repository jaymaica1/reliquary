<?php

declare(strict_types=1);

namespace App\Service\Backup;

use Aws\S3\S3Client;
use Doctrine\DBAL\Exception\MalformedDsnException;
use Doctrine\DBAL\Tools\DsnParser;

final class S3DatabaseBackupService
{
    public function __construct(
        private readonly S3Client $s3Client,
        private readonly string $backupBucket,
        private readonly string $backupPrefix,
    ) {
    }

    public function isBucketConfigured(): bool
    {
        return $this->backupBucket !== '';
    }

    public function requireBucket(): void
    {
        if (!$this->isBucketConfigured()) {
            throw new \RuntimeException(
                'AWS_BACKUP_BUCKET is not set. Configure a private S3 bucket for backups (see docs/DEPLOYMENT.md).'
            );
        }
    }

    /**
     * @return array{host: string, port: int, user: string, password: string, dbname: string}
     */
    public function parsePostgresUrl(string $databaseUrl): array
    {
        $parser = new DsnParser([
            'postgresql' => 'pdo_pgsql',
            'postgres' => 'pdo_pgsql',
        ]);

        try {
            $params = $parser->parse($databaseUrl);
        } catch (MalformedDsnException $e) {
            throw new \RuntimeException('Invalid DATABASE_URL for PostgreSQL: ' . $e->getMessage(), 0, $e);
        }

        $dbname = $params['dbname'] ?? $params['path'] ?? null;
        if ($dbname !== null) {
            $dbname = ltrim((string) $dbname, '/');
        }
        if ($dbname === null || $dbname === '') {
            throw new \RuntimeException('DATABASE_URL must include a database name (path component).');
        }

        $host = $params['host'] ?? '127.0.0.1';
        $port = isset($params['port']) ? (int) $params['port'] : 5432;
        $user = $params['user'] ?? '';
        $password = $params['password'] ?? '';

        return [
            'host' => $host,
            'port' => $port,
            'user' => $user,
            'password' => $password,
            'dbname' => $dbname,
        ];
    }

    public function normalizedPrefix(): string
    {
        return trim($this->backupPrefix, '/') . '/';
    }

    /**
     * @return array<string, mixed>
     */
    private function serverSideEncryptionParams(): array
    {
        return ['ServerSideEncryption' => 'AES256'];
    }

    public function uploadLocalFile(string $localPath, string $objectKey): void
    {
        $this->requireBucket();

        $params = array_merge([
            'Bucket' => $this->backupBucket,
            'Key' => $objectKey,
            'Body' => fopen($localPath, 'rb'),
            'ContentType' => 'application/octet-stream',
        ], $this->serverSideEncryptionParams());

        try {
            $this->s3Client->putObject($params);
        } finally {
            if (\is_resource($params['Body'])) {
                fclose($params['Body']);
            }
        }
    }

    public function uploadJsonManifest(array $data, string $objectKey): void
    {
        $this->requireBucket();

        $body = json_encode($data, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

        $params = array_merge([
            'Bucket' => $this->backupBucket,
            'Key' => $objectKey,
            'Body' => $body,
            'ContentType' => 'application/json',
        ], $this->serverSideEncryptionParams());

        $this->s3Client->putObject($params);
    }

    /**
     * Full S3 key prefix for a backup folder, e.g. "reliquary-backups/2026-04-12T15-30-45Z/".
     */
    public function backupFolderKey(string $backupId): string
    {
        return $this->normalizedPrefix() . trim($backupId, '/') . '/';
    }

    /**
     * Finds the key prefix of the most recently modified manifest.json under the configured prefix.
     */
    public function findLatestBackupKeyPrefix(): ?string
    {
        $this->requireBucket();

        $searchPrefix = $this->normalizedPrefix();
        $manifests = [];
        $token = null;

        do {
            $args = [
                'Bucket' => $this->backupBucket,
                'Prefix' => $searchPrefix,
            ];
            if ($token !== null) {
                $args['ContinuationToken'] = $token;
            }

            $result = $this->s3Client->listObjectsV2($args);

            foreach ($result['Contents'] ?? [] as $obj) {
                $key = $obj['Key'] ?? '';
                if ($key === '' || !str_ends_with($key, 'manifest.json')) {
                    continue;
                }
                $mtime = $obj['LastModified'] ?? new \DateTimeImmutable('@0');
                $manifests[] = ['key' => $key, 'mtime' => $mtime];
            }

            $truncated = (bool) ($result['IsTruncated'] ?? false);
            $token = $truncated ? ($result['NextContinuationToken'] ?? null) : null;
        } while ($token !== null);

        if ($manifests === []) {
            return null;
        }

        usort($manifests, static function (array $a, array $b): int {
            $ta = $a['mtime'] instanceof \DateTimeInterface ? $a['mtime']->getTimestamp() : 0;
            $tb = $b['mtime'] instanceof \DateTimeInterface ? $b['mtime']->getTimestamp() : 0;

            return $tb <=> $ta;
        });

        $manifestKey = $manifests[0]['key'];

        return substr($manifestKey, 0, -\strlen('manifest.json'));
    }

    public function downloadObjectToFile(string $objectKey, string $localPath): void
    {
        $this->requireBucket();

        $dir = \dirname($localPath);
        if (!is_dir($dir) && !mkdir($dir, 0o700, true) && !is_dir($dir)) {
            throw new \RuntimeException(sprintf('Cannot create directory "%s".', $dir));
        }

        $this->s3Client->getObject([
            'Bucket' => $this->backupBucket,
            'Key' => $objectKey,
            'SaveAs' => $localPath,
        ]);
    }

    /**
     * @return list<string> downloaded file paths (basenames)
     */
    public function downloadBackupFolder(string $keyPrefix, string $localDir): array
    {
        $this->requireBucket();

        if (!is_dir($localDir) && !mkdir($localDir, 0o700, true) && !is_dir($localDir)) {
            throw new \RuntimeException(sprintf('Cannot create directory "%s".', $localDir));
        }

        $prefix = $keyPrefix;
        if ($prefix !== '' && !str_ends_with($prefix, '/')) {
            $prefix .= '/';
        }

        $downloaded = [];
        $token = null;

        do {
            $args = [
                'Bucket' => $this->backupBucket,
                'Prefix' => $prefix,
            ];
            if ($token !== null) {
                $args['ContinuationToken'] = $token;
            }

            $result = $this->s3Client->listObjectsV2($args);

            foreach ($result['Contents'] ?? [] as $obj) {
                $key = $obj['Key'] ?? '';
                if ($key === '' || str_ends_with($key, '/')) {
                    continue;
                }
                $basename = basename($key);
                $target = $localDir . '/' . $basename;
                $this->downloadObjectToFile($key, $target);
                $downloaded[] = $target;
            }

            $truncated = (bool) ($result['IsTruncated'] ?? false);
            $token = $truncated ? ($result['NextContinuationToken'] ?? null) : null;
        } while ($token !== null);

        return $downloaded;
    }
}
