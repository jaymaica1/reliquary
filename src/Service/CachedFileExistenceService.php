<?php

namespace App\Service;

use League\Flysystem\FilesystemOperator;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;

class CachedFileExistenceService
{
    private const CACHE_TTL = 300; // 5 minutes
    private const LOCAL_CACHE_PREFIX = 'file_exists_local_';
    private const REMOTE_CACHE_PREFIX = 'file_exists_remote_';

    public function __construct(
        private CacheInterface $cache
    ) {
    }

    /**
     * Check if a local file exists with caching
     */
    public function localFileExists(string $filePath): bool
    {
        $cacheKey = self::LOCAL_CACHE_PREFIX . md5($filePath);
        
        return $this->cache->get($cacheKey, function (ItemInterface $item) use ($filePath) {
            $item->expiresAfter(self::CACHE_TTL);
            return file_exists($filePath);
        });
    }

    /**
     * Check if a remote file exists (via FilesystemOperator) with caching
     */
    public function remoteFileExists(FilesystemOperator $filesystem, string $filename): bool
    {
        $cacheKey = self::REMOTE_CACHE_PREFIX . md5($filename);
        
        return $this->cache->get($cacheKey, function (ItemInterface $item) use ($filesystem, $filename) {
            $item->expiresAfter(self::CACHE_TTL);
            try {
                return $filesystem->fileExists($filename);
            } catch (\Exception $e) {
                // If there's an error checking remote storage, return false
                return false;
            }
        });
    }

    /**
     * Clear cache for a specific local file
     */
    public function clearLocalFileCache(string $filePath): void
    {
        $cacheKey = self::LOCAL_CACHE_PREFIX . md5($filePath);
        $this->cache->delete($cacheKey);
    }

    /**
     * Clear cache for a specific remote file
     */
    public function clearRemoteFileCache(string $filename): void
    {
        $cacheKey = self::REMOTE_CACHE_PREFIX . md5($filename);
        $this->cache->delete($cacheKey);
    }

    /**
     * Clear all file existence cache
     */
    public function clearAllCache(): void
    {
        // Note: This is a simple implementation. For production, you might want
        // to use cache tags or a more sophisticated cache invalidation strategy
        $this->cache->clear();
    }
}