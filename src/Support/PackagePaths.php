<?php

namespace Dcplibrary\Requests\Support;

/**
 * Resolved filesystem paths inside the dcplibrary/requests package.
 */
final class PackagePaths
{
    /**
     * Absolute path to package database migrations, or null if missing.
     */
    public static function migrations(): ?string
    {
        $path = realpath(__DIR__ . '/../../database/migrations');

        return ($path !== false && is_dir($path)) ? $path : null;
    }
}
