<?php

namespace Dcplibrary\Requests\Support;

/**
 * Rules for building a zip of {@code storage/app}: omit server backup artifacts so each export
 * does not nest prior JSON/SQL/ZIP backups (which caused unbounded growth).
 */
final class StorageAppBackupArchive
{
    /**
     * Whether a path relative to {@code storage/app} should be skipped when zipping the tree.
     */
    public static function shouldExcludeRelativePath(string $relativePath): bool
    {
        $relativePath = str_replace('\\', '/', $relativePath);

        if ($relativePath === 'requests-backups' || str_starts_with($relativePath, 'requests-backups/')) {
            return true;
        }

        if (preg_match('#(^|/)requests-storage-[\w\-]+\.zip$#', $relativePath) === 1) {
            return true;
        }

        return false;
    }
}
