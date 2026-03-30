<?php

namespace Tests\Unit;

use Dcplibrary\Requests\Support\StorageAppBackupArchive;
use PHPUnit\Framework\TestCase;

class StorageAppBackupArchiveTest extends TestCase
{
    public function test_excludes_requests_backups_tree(): void
    {
        $this->assertTrue(StorageAppBackupArchive::shouldExcludeRelativePath('requests-backups'));
        $this->assertTrue(StorageAppBackupArchive::shouldExcludeRelativePath('requests-backups/foo.json'));
        $this->assertTrue(StorageAppBackupArchive::shouldExcludeRelativePath('requests-backups/requests-storage-2020-01-01-000000.zip'));
    }

    public function test_excludes_storage_zip_names_anywhere_under_app(): void
    {
        $this->assertTrue(StorageAppBackupArchive::shouldExcludeRelativePath('tmp/requests-storage-2020-01-01-000000.zip'));
        $this->assertTrue(StorageAppBackupArchive::shouldExcludeRelativePath('nested/requests-storage-x.zip'));
    }

    public function test_does_not_exclude_normal_upload_paths(): void
    {
        $this->assertFalse(StorageAppBackupArchive::shouldExcludeRelativePath('public/cover.jpg'));
        $this->assertFalse(StorageAppBackupArchive::shouldExcludeRelativePath('requests-not-a-backup.zip'));
    }
}
