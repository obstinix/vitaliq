<?php

declare(strict_types=1);

namespace Vitaliq\Core;

use PDO;
use PDOException;
use PDOStatement;
use Throwable;

/**
 * Thin PDO wrapper. Every query goes through a prepared statement;
 * native (non-emulated) prepares keep parameters out of the SQL text.
 */
class Database
{
    private ?PDO $pdo = null;
    private int $depth = 0;

    /** @param array{host:string,port:int,name:string,user:string,pass:string} $config */
    public function __construct(private readonly array $config)
    {
    }

    public function pdo(): PDO
    {
        if ($this->pdo === null) {
            $dsn = sprintf(
                'mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4',
                $this->config['host'],
                $this->config['port'],
                $this->config['name']
            );
            try {
                $this->pdo = new PDO($dsn, $this->config['user'], $this->config['pass'], [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false,
                    PDO::ATTR_STRINGIFY_FETCHES => false,
                    PDO::ATTR_TIMEOUT => 5,
                ]);
                // Keep DATETIME values in the same offset PHP uses for the app.
                $this->pdo->exec("SET time_zone = '" . date('P') . "'");
                $this->pdo->exec("SET SESSION sql_mode = 'STRICT_ALL_TABLES,NO_ZERO_DATE,NO_ZERO_IN_DATE,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION'");
            } catch (PDOException $e) {
                throw new DatabaseUnavailable('Database connection failed.', 0, $e);
            }
        }
        return $this->pdo;
    }

    public function databaseName(): string
    {
        return $this->config['name'];
    }

    /** @param array<string|int,mixed> $params */
    public function run(string $sql, array $params = []): PDOStatement
    {
        $stmt = $this->pdo()->prepare($sql);
        foreach ($params as $key => $value) {
            $name = is_int($key) ? $key + 1 : (str_starts_with($key, ':') ? $key : ':' . $key);
            $type = match (true) {
                is_int($value), is_bool($value) => PDO::PARAM_INT,
                $value === null => PDO::PARAM_NULL,
                default => PDO::PARAM_STR,
            };
            $stmt->bindValue($name, is_bool($value) ? (int) $value : $value, $type);
        }
        $stmt->execute();
        return $stmt;
    }

    /**
     * @param array<string|int,mixed> $params
     * @return array<string,mixed>|null
     */
    public function one(string $sql, array $params = []): ?array
    {
        $row = $this->run($sql, $params)->fetch();
        return $row === false ? null : $row;
    }

    /**
     * @param array<string|int,mixed> $params
     * @return list<array<string,mixed>>
     */
    public function all(string $sql, array $params = []): array
    {
        return $this->run($sql, $params)->fetchAll();
    }

    /** @param array<string|int,mixed> $params */
    public function value(string $sql, array $params = []): mixed
    {
        $value = $this->run($sql, $params)->fetchColumn();
        return $value === false ? null : $value;
    }

    /** @param array<string|int,mixed> $params */
    public function execute(string $sql, array $params = []): int
    {
        return $this->run($sql, $params)->rowCount();
    }

    /** @param array<string|int,mixed> $params */
    public function insert(string $sql, array $params = []): int
    {
        $this->run($sql, $params);
        return (int) $this->pdo()->lastInsertId();
    }

    /**
     * Run $work inside a transaction. Nested calls join the outer
     * transaction so services can compose without partial commits.
     *
     * @template T
     * @param callable(Database):T $work
     * @return T
     */
    public function transaction(callable $work): mixed
    {
        $pdo = $this->pdo();
        if ($this->depth > 0) {
            $this->depth++;
            try {
                return $work($this);
            } finally {
                $this->depth--;
            }
        }
        $pdo->beginTransaction();
        $this->depth = 1;
        try {
            $result = $work($this);
            $pdo->commit();
            return $result;
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        } finally {
            $this->depth = 0;
        }
    }

    public function inTransaction(): bool
    {
        return $this->depth > 0;
    }

    /** Escape LIKE wildcards in user input before wrapping it in %...%. */
    public static function likeEscape(string $value): string
    {
        return addcslashes($value, '\\%_');
    }
}
