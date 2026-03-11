<?php

namespace Dcplibrary\Requests\Tests\Unit;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

/**
 * Guard against stale old-package references that should have been renamed
 * during the package rename migration.
 *
 * These tests scan source, view, and config files for patterns that indicate
 * an incomplete rename. Intentional data-level uses of "sfp" (form slugs,
 * request_kind values, setting keys, route constraints, SVG CSS classes) are
 * excluded via an allowlist.
 */
class PackageRenameConsistencyTest extends TestCase
{
    /** Root of the package. */
    private static string $root;

    public static function setUpBeforeClass(): void
    {
        self::$root = realpath(__DIR__ . '/../../');
    }

    // ── Helpers ────────────────────────────────────────────────────────────

    /**
     * Recursively collect files matching an extension under a directory.
     *
     * @param  string   $dir
     * @param  string[] $extensions
     * @return string[]
     */
    private function filesIn(string $dir, array $extensions): array
    {
        $dir = self::$root . '/' . ltrim($dir, '/');
        if (! is_dir($dir)) {
            return [];
        }

        $files = [];
        $it = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS)
        );

        foreach ($it as $file) {
            if (! $file->isFile()) {
                continue;
            }
            foreach ($extensions as $ext) {
                if (str_ends_with($file->getFilename(), $ext)) {
                    $files[] = $file->getPathname();
                    break;
                }
            }
        }

        return $files;
    }

    /**
     * Scan files for a regex pattern and return violations.
     *
     * @param  string[] $files
     * @param  string   $pattern   PCRE pattern
     * @param  string[] $allowlist Substrings to whitelist (matched against the full line)
     * @return array<string, int[]>  file → line numbers
     */
    private function scanFor(array $files, string $pattern, array $allowlist = []): array
    {
        $violations = [];

        foreach ($files as $file) {
            $lines = file($file, FILE_IGNORE_NEW_LINES);
            foreach ($lines as $i => $line) {
                if (! preg_match($pattern, $line)) {
                    continue;
                }

                // Check allowlist.
                $allowed = false;
                foreach ($allowlist as $ok) {
                    if (str_contains($line, $ok)) {
                        $allowed = true;
                        break;
                    }
                }

                if (! $allowed) {
                    $rel = str_replace(self::$root . '/', '', $file);
                    $violations[$rel][] = $i + 1;
                }
            }
        }

        return $violations;
    }

    /**
     * Format violation map into a readable assertion message.
     *
     * @param  array<string, int[]> $violations
     * @return string
     */
    private function formatViolations(array $violations): string
    {
        $lines = [];
        foreach ($violations as $file => $lineNos) {
            $lines[] = "  {$file}: line(s) " . implode(', ', $lineNos);
        }
        return implode("\n", $lines);
    }

    // ── Allowlist ──────────────────────────────────────────────────────────

    /**
     * Lines containing any of these substrings are exempt from the old-name
     * scan. These represent intentional data-level uses of "sfp".
     *
     * @return string[]
     */
    private function allowlist(): array
    {
        return [
            // Form slug (database value)
            "bySlug('sfp')",
            "'slug' => 'sfp'",
            "slug('sfp')",
            "'sfp')",                // in match() / in_array
            "\"sfp\"",               // JSON or validation rule value
            "sfp|ill",               // route constraint regex
            "sfp,ill",               // validation in:sfp,ill
            "sfp →",                 // status note: "workflow: sfp → ill"
            "workflow: sfp",
            // request_kind data value
            "request_kind",
            "'kind' => 'sfp'",
            "= 'sfp'",
            // Setting keys (stored in DB)
            "sfp_limit",
            "sfp_catalog",
            "sfp_isbndb",
            // SVG logo CSS classes
            "sfp-h", "sfp-i", "sfp-e", "sfp-g", "sfp-f",
            // Test data
            "seedForm('sfp')",
            "Genre (SFP)",
            // PHPDoc @param type hint
            "'sfp'|'ill'",
        ];
    }

    // ── Tests ──────────────────────────────────────────────────────────────

    #[Test]
    public function no_old_namespace_in_php_files(): void
    {
        $files = array_merge(
            $this->filesIn('src', ['.php']),
            $this->filesIn('tests', ['.php']),
            $this->filesIn('database', ['.php']),
            $this->filesIn('config', ['.php']),
            $this->filesIn('resources/views', ['.blade.php']),
        );

        $violations = $this->scanFor($files, '/Dcplibrary\\\\Sfp|dcplibrary\/sfp/i');

        $this->assertEmpty(
            $violations,
            "Old namespace Dcplibrary\\Sfp found:\n" . $this->formatViolations($violations)
        );
    }

    #[Test]
    public function no_old_config_key_in_php_files(): void
    {
        $files = array_merge(
            $this->filesIn('src', ['.php']),
            $this->filesIn('config', ['.php']),
        );

        $violations = $this->scanFor($files, "/config\(\s*['\"]sfp\./");

        $this->assertEmpty(
            $violations,
            "Old config key config('sfp.*') found:\n" . $this->formatViolations($violations)
        );
    }

    #[Test]
    public function no_old_view_namespace_in_php_or_blade(): void
    {
        $files = array_merge(
            $this->filesIn('src', ['.php']),
            $this->filesIn('resources/views', ['.blade.php']),
        );

        $violations = $this->scanFor($files, "/sfp::/");

        $this->assertEmpty(
            $violations,
            "Old view namespace sfp:: found:\n" . $this->formatViolations($violations)
        );
    }

    #[Test]
    public function no_old_livewire_aliases_in_blade_templates(): void
    {
        $files = $this->filesIn('resources/views', ['.blade.php']);

        // Match sfp- when preceded by livewire( or <livewire: — i.e. a Livewire component reference
        $violations = $this->scanFor(
            $files,
            "/livewire[:(].*sfp-/i",
            $this->allowlist()
        );

        $this->assertEmpty(
            $violations,
            "Old sfp-* Livewire alias in blade template:\n" . $this->formatViolations($violations)
        );
    }

    #[Test]
    public function no_old_sfp_users_table_reference(): void
    {
        $files = array_merge(
            $this->filesIn('src', ['.php']),
            $this->filesIn('tests', ['.php']),
            $this->filesIn('database', ['.php']),
        );

        $violations = $this->scanFor($files, '/sfp_users/', [
            'no_old_sfp_users', // allow this test's own method name
            'scanFor',          // allow this test file's own method calls
            'sfp_users found',  // allow assertion messages in this file
        ]);

        $this->assertEmpty(
            $violations,
            "Old table name sfp_users found (should be staff_users):\n" . $this->formatViolations($violations)
        );
    }

    #[Test]
    public function no_old_sfp_prefix_in_blade_asset_refs(): void
    {
        $files = $this->filesIn('resources/views', ['.blade.php']);

        // Catch references like sfp-backups, sfp-settings-help.html, sfp:backup, sfp-backup.log
        $violations = $this->scanFor(
            $files,
            '/sfp-/',
            $this->allowlist()
        );

        $this->assertEmpty(
            $violations,
            "Old sfp- prefix in blade template:\n" . $this->formatViolations($violations)
        );
    }

    #[Test]
    public function service_provider_registers_all_livewire_components_with_requests_prefix(): void
    {
        $content = file_get_contents(self::$root . '/src/RequestsServiceProvider.php');

        // Every Livewire::component() call should use a requests- or ill- prefix
        preg_match_all("/Livewire::component\(\s*'([^']+)'/", $content, $matches);

        $badAliases = [];
        foreach ($matches[1] as $alias) {
            if (! str_starts_with($alias, 'requests-') && ! str_starts_with($alias, 'ill-')) {
                $badAliases[] = $alias;
            }
        }

        $this->assertEmpty(
            $badAliases,
            "Livewire aliases not using 'requests-' prefix: " . implode(', ', $badAliases)
        );
    }
}
