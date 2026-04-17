<?php

declare(strict_types=1);

namespace App\Tests\Service\Backup;

use App\Service\Backup\MongoBackupUriHelper;
use PHPUnit\Framework\TestCase;

final class MongoBackupUriHelperTest extends TestCase
{
    public function testAddsAuthSourceForAdminWhenMissing(): void
    {
        $in = 'mongodb://admin:secret@mongodb:27017';
        self::assertSame(
            'mongodb://admin:secret@mongodb:27017?authSource=admin',
            MongoBackupUriHelper::forMongoTools($in)
        );
    }

    public function testAppendsAuthSourceWhenQueryExists(): void
    {
        $in = 'mongodb://admin:secret@mongodb:27017/?retryWrites=true';
        self::assertSame(
            'mongodb://admin:secret@mongodb:27017/?retryWrites=true&authSource=admin',
            MongoBackupUriHelper::forMongoTools($in)
        );
    }

    public function testDoesNotDuplicateAuthSource(): void
    {
        $in = 'mongodb://admin:secret@mongodb:27017/?authSource=admin';
        self::assertSame($in, MongoBackupUriHelper::forMongoTools($in));
    }

    public function testLeavesNonAdminUserUnchanged(): void
    {
        $in = 'mongodb://reliquary_user:secret@mongodb:27017';
        self::assertSame($in, MongoBackupUriHelper::forMongoTools($in));
    }

    public function testEmptyString(): void
    {
        self::assertSame('', MongoBackupUriHelper::forMongoTools(''));
    }
}
