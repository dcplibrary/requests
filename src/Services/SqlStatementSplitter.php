<?php

namespace Dcplibrary\Requests\Services;

/**
 * Splits a multi-statement SQL dump into individual statements.
 *
 * Required because SQLite's PDO::exec() only processes the first statement
 * in a multi-statement string. Handles semicolons inside quoted strings
 * (including backslash-escaped quotes) and skips single-line (--) comments.
 */
class SqlStatementSplitter
{
    /**
     * Split SQL into executable statements.
     *
     * @param  string  $sql  Raw SQL dump (one or more statements).
     * @return array<int, string>  Non-empty trimmed statements.
     */
    public static function split(string $sql): array
    {
        $statements = [];
        $current    = '';
        $i          = 0;
        $len        = strlen($sql);

        while ($i < $len) {
            $ch = $sql[$i];

            // Skip line comments (-- ... \n)
            if ($ch === '-' && $i + 1 < $len && $sql[$i + 1] === '-') {
                while ($i < $len && $sql[$i] !== "\n") {
                    $i++;
                }
                continue;
            }

            // Quoted strings/identifiers: copy verbatim until matching close quote
            if ($ch === "'" || $ch === '"') {
                $quote    = $ch;
                $current .= $ch;
                $i++;
                while ($i < $len) {
                    $c        = $sql[$i];
                    $current .= $c;
                    $i++;

                    // Backslash-escaped character (e.g. \' \" \\): skip the next character.
                    if ($c === '\\' && $i < $len) {
                        $current .= $sql[$i++];
                        continue;
                    }

                    if ($c === $quote) {
                        // Doubled quote ('' or ""): still inside the string.
                        if ($i < $len && $sql[$i] === $quote) {
                            $current .= $sql[$i++];
                        } else {
                            break;
                        }
                    }
                }
                continue;
            }

            if ($ch === ';') {
                $stmt = trim($current);
                if ($stmt !== '') {
                    $statements[] = $stmt;
                }
                $current = '';
                $i++;
                continue;
            }

            $current .= $ch;
            $i++;
        }

        $stmt = trim($current);
        if ($stmt !== '') {
            $statements[] = $stmt;
        }

        return array_values(array_filter($statements));
    }

    /**
     * Filter out statements that are not valid for the target driver.
     *
     * Used for same-driver SQL restore (e.g. MySQL dump → MySQL, SQLite dump → SQLite):
     *
     * - When target is SQLite: skip MySQL-only statements (SET FOREIGN_KEY_CHECKS,
     *   SET NAMES, etc.).
     * - When target is MySQL/MariaDB: skip SQLite-only statements (PRAGMA
     *   foreign_keys, etc.).
     *
     * For cross-driver restore (e.g. MySQL → SQLite), use JSON export/import instead.
     *
     * @param  array<int, string>  $statements  Output of split().
     * @param  string  $driver  Connection driver name (e.g. 'sqlite', 'mysql').
     * @return array<int, string>  Statements safe to execute on the driver.
     */
    public static function filterForDriver(array $statements, string $driver): array
    {
        $driver = strtolower($driver);

        return array_values(array_filter($statements, static function (string $stmt) use ($driver): bool {
            $trimmed = trim($stmt);
            if ($driver === 'sqlite') {
                // Skip MySQL session / compatibility statements.
                if (preg_match('/^\s*SET\s+/i', $trimmed)) {
                    return false;
                }
            }
            if ($driver === 'mysql' || $driver === 'mariadb') {
                // Skip SQLite PRAGMA statements.
                if (preg_match('/^\s*PRAGMA\s+/i', $trimmed)) {
                    return false;
                }
            }
            return true;
        }));
    }
}
