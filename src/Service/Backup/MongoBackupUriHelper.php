<?php

declare(strict_types=1);

namespace App\Service\Backup;

/**
 * Normalizes MongoDB URIs for CLI tools (mongodump, mongorestore).
 *
 * Doctrine ODM uses the PHP driver; MongoDB tools sometimes default auth
 * differently. Root users in Docker are always in the admin database.
 */
final class MongoBackupUriHelper
{
    /**
     * Adds authSource=admin when the URI user is admin and no authSource is set,
     * so CLI tools authenticate like the app.
     */
    public static function forMongoTools(string $mongoUrl): string
    {
        $mongoUrl = trim($mongoUrl);
        if ($mongoUrl === '') {
            return $mongoUrl;
        }

        if (preg_match('/[&?]authSource=/', $mongoUrl)) {
            return $mongoUrl;
        }

        $parts = parse_url($mongoUrl);
        if ($parts === false || ($parts['scheme'] ?? '') !== 'mongodb') {
            return $mongoUrl;
        }

        $user = isset($parts['user']) ? rawurldecode($parts['user']) : '';
        if (strtolower($user) !== 'admin') {
            return $mongoUrl;
        }

        $joiner = str_contains($mongoUrl, '?') ? '&' : '?';

        return $mongoUrl . $joiner . 'authSource=admin';
    }
}
