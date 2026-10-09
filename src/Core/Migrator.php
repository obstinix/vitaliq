<?php

declare(strict_types=1);

namespace Vitaliq\Core;

use RuntimeException;

/**
 * Applies database/migrations/*.sql in filename order and records each one
 * in schema_migrations, so running it twice is safe.
 */
final class Migrator
{
    public function __construct(private readonly Database $db, private readonly string $directory)
    {
    }

    /** @return list<string> filenames applied in this call */
    public function migrate(): array
    {
        $this->db->pdo()->exec(
            'CREATE TABLE IF NOT EXISTS schema_migrations (
               filename VARCHAR(120) NOT NULL PRIMARY KEY,
               applied_at DATETIME NOT NULL
             ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );
        $done = array_column($this->db->all('SELECT filename FROM schema_migrations'), 'filename');
        $applied = [];
        foreach ($this->files() as $file) {
            $name = basename($file);
            if (in_array($name, $done, true)) {
                continue;
            }
            foreach (self::statements((string) file_get_contents($file)) as $sql) {
                $this->db->pdo()->exec($sql);
            }
            $this->db->execute('INSERT INTO schema_migrations (filename, applied_at) VALUES (:f, :t)', [
                'f' => $name,
                't' => date('Y-m-d H:i:s'),
            ]);
            $applied[] = $name;
        }
        return $applied;
    }

    /**
     * Drop every table in the configured database. Refuses to run unless the
     * database name ends in "_test" or $allowNonTest is set explicitly.
     */
    public function dropAll(bool $allowNonTest = false): void
    {
        $name = $this->db->databaseName();
        if (!$allowNonTest && !str_ends_with($name, '_test')) {
            throw new RuntimeException("Refusing to drop tables in '{$name}': not a _test database.");
        }
        $tables = $this->db->all(
            'SELECT table_name AS t FROM information_schema.tables WHERE table_schema = :s',
            ['s' => $name]
        );
        $pdo = $this->db->pdo();
        $pdo->exec('SET FOREIGN_KEY_CHECKS = 0');
        foreach ($tables as $row) {
            $pdo->exec('DROP TABLE IF EXISTS `' . str_replace('`', '', (string) $row['t']) . '`');
        }
        $pdo->exec('SET FOREIGN_KEY_CHECKS = 1');
    }

    /** @return list<string> */
    private function files(): array
    {
        $files = glob($this->directory . '/*.sql') ?: [];
        sort($files, SORT_STRING);
        return $files;
    }

    /** @return list<string> */
    public static function statements(string $sql): array
    {
        $lines = array_filter(
            preg_split('/\R/', $sql) ?: [],
            static fn (string $line): bool => !str_starts_with(ltrim($line), '--')
        );
        $parts = preg_split('/;\s*$/m', implode("\n", $lines)) ?: [];
        return array_values(array_filter(array_map('trim', $parts), static fn ($s) => $s !== ''));
    }
}
