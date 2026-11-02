<?php

declare(strict_types=1);

namespace Vitaliq\Services;

use RuntimeException;
use Vitaliq\Core\App;
use Vitaliq\Support\Crypto;

/**
 * Encrypted logical backups (feature 43). Every table's definition and rows
 * are written as SQL (string values as hex literals, so no quoting can
 * break), gzip-compressed and encrypted with a key derived from APP_KEY.
 * A backup restores only into an empty database, from the command line
 * (bin/backup-restore.php); the web interface never restores.
 */
final class BackupService
{
    public const SEPARATOR = "\n-- @@\n";
    public const MAX_BYTES = 200 * 1024 * 1024;
    public const KEEP = 14;

    private string $dir;

    public function __construct(private readonly App $app)
    {
        $this->dir = VQ_ROOT . '/storage/backups';
    }

    private function crypto(): Crypto
    {
        return new Crypto((string) $this->app->config['key']);
    }

    /** @return array{id:int,filename:string,tables:int,rows:int,bytes:int} */
    public function create(?int $actorId): array
    {
        $tables = 0;
        $rows = 0;
        $sql = $this->dump($tables, $rows);
        $gz = gzencode($sql, 6);
        if ($gz === false) {
            throw new RuntimeException('Compression failed.');
        }
        $filename = 'vitaliq-' . $this->app->clock->now()->format('Ymd-His') . '-' . bin2hex(random_bytes(3)) . '.sql.gz.enc';
        $blob = $this->crypto()->encryptBytes($gz, 'backup:' . $filename);
        if (!is_dir($this->dir) && !mkdir($this->dir, 0700, true) && !is_dir($this->dir)) {
            throw new RuntimeException('Backup directory cannot be created.');
        }
        if (file_put_contents($this->dir . '/' . $filename, $blob, LOCK_EX) === false) {
            throw new RuntimeException('Backup could not be written.');
        }
        $id = $this->app->db->insert(
            'INSERT INTO backups (filename, size_bytes, tables_count, rows_count, sha256, created_by_user_id, created_at) VALUES (:f, :s, :t, :r, :h, :u, :c)',
            ['f' => $filename, 's' => strlen($blob), 't' => $tables, 'r' => $rows, 'h' => hash('sha256', $blob), 'u' => $actorId, 'c' => $this->app->clock->nowSql()]
        );
        $this->app->service(AuditLog::class)->record('backup.created', 'backup', $id, ['tables' => $tables, 'rows' => $rows], $actorId);
        $this->prune();
        return ['id' => $id, 'filename' => $filename, 'tables' => $tables, 'rows' => $rows, 'bytes' => strlen($blob)];
    }

    /**
     * Build the SQL text.
     *
     * @param-out int $tables
     * @param-out int $rows
     */
    public function dump(int &$tables = 0, int &$rows = 0): string
    {
        $db = $this->app->db;
        $pdo = $db->pdo();
        $names = array_column($db->all(
            "SELECT table_name AS t FROM information_schema.tables WHERE table_schema = :s AND table_type = 'BASE TABLE' ORDER BY table_name",
            ['s' => $db->databaseName()]
        ), 't');
        $out = ['-- Vitaliq backup of ' . $db->databaseName() . ' at ' . $this->app->clock->nowSql(), 'SET FOREIGN_KEY_CHECKS = 0', "SET NAMES utf8mb4"];
        $tables = 0;
        $rows = 0;
        $size = 0;
        foreach ($names as $t) {
            $t = str_replace('`', '', (string) $t);
            $create = $pdo->query('SHOW CREATE TABLE `' . $t . '`')->fetch(\PDO::FETCH_NUM);
            $out[] = (string) $create[1];
            $tables++;
            $stmt = $pdo->query('SELECT * FROM `' . $t . '`');
            $batch = [];
            $cols = null;
            while (($row = $stmt->fetch(\PDO::FETCH_ASSOC)) !== false) {
                $cols ??= '(`' . implode('`, `', array_keys($row)) . '`)';
                $batch[] = '(' . implode(', ', array_map(static function ($v): string {
                    if ($v === null) {
                        return 'NULL';
                    }
                    if (is_int($v) || is_float($v)) {
                        return (string) $v;
                    }
                    return $v === '' ? "''" : '0x' . bin2hex((string) $v);
                }, $row)) . ')';
                $rows++;
                if (count($batch) === 200) {
                    $out[] = 'INSERT INTO `' . $t . '` ' . $cols . ' VALUES ' . implode(', ', $batch);
                    $size += strlen(end($out));
                    $batch = [];
                }
                if ($size > self::MAX_BYTES) {
                    throw new RuntimeException('The database is too large for an in-application backup; use mysqldump.');
                }
            }
            if ($batch !== []) {
                $out[] = 'INSERT INTO `' . $t . '` ' . $cols . ' VALUES ' . implode(', ', $batch);
            }
        }
        $out[] = 'SET FOREIGN_KEY_CHECKS = 1';
        return implode(self::SEPARATOR, $out) . "\n";
    }

    /** Decrypt and decompress a backup file to SQL text. */
    public static function decryptFile(string $path, string $appKey): string
    {
        $blob = (string) file_get_contents($path);
        $gz = (new Crypto($appKey))->decryptBytes($blob, 'backup:' . basename($path));
        $sql = gzdecode($gz);
        if ($sql === false) {
            throw new RuntimeException('The backup could not be decompressed.');
        }
        return $sql;
    }

    /** @return list<string> */
    public static function statements(string $sql): array
    {
        return array_values(array_filter(array_map('trim', explode(trim(self::SEPARATOR), $sql)), static fn ($s) => $s !== '' && !str_starts_with($s, '-- Vitaliq backup')));
    }

    /** @return list<array<string,mixed>> */
    public function list(): array
    {
        return $this->app->db->all('SELECT b.*, u.full_name FROM backups b LEFT JOIN users u ON u.id = b.created_by_user_id WHERE b.deleted_at IS NULL ORDER BY b.id DESC');
    }

    /** @return array{path:string,filename:string} */
    public function file(int $id): array
    {
        $b = $this->app->db->one('SELECT * FROM backups WHERE id = :id AND deleted_at IS NULL', ['id' => $id]);
        $path = $b !== null ? $this->dir . '/' . basename((string) $b['filename']) : '';
        if ($b === null || !is_file($path)) {
            throw new WorkflowException('Backup not found.');
        }
        if (!hash_equals((string) $b['sha256'], hash_file('sha256', $path) ?: '')) {
            throw new WorkflowException('The backup file has changed since it was written and cannot be trusted.');
        }
        return ['path' => $path, 'filename' => (string) $b['filename']];
    }

    /** Keep the newest KEEP backups. */
    public function prune(): int
    {
        $old = $this->app->db->all('SELECT id, filename FROM backups WHERE deleted_at IS NULL ORDER BY id DESC LIMIT 1000 OFFSET ' . self::KEEP);
        foreach ($old as $b) {
            @unlink($this->dir . '/' . basename((string) $b['filename']));
            $this->app->db->execute('UPDATE backups SET deleted_at = :t WHERE id = :id', ['t' => $this->app->clock->nowSql(), 'id' => $b['id']]);
        }
        return count($old);
    }

    public function lastBackupAt(): ?string
    {
        $v = $this->app->db->value('SELECT MAX(created_at) FROM backups WHERE deleted_at IS NULL');
        return $v === null ? null : (string) $v;
    }
}
