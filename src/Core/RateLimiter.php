<?php

declare(strict_types=1);

namespace Vitaliq\Core;

/**
 * Fixed-window rate limiter stored in the database, so limits survive
 * across PHP processes and session resets.
 */
final class RateLimiter
{
    public function __construct(private readonly Database $db, private readonly Clock $clock)
    {
    }

    /** Record one attempt. Returns false when the bucket is over its limit. */
    public function hit(string $bucket, int $max, int $windowSeconds): bool
    {
        $now = $this->clock->now();
        $nowSql = $now->format('Y-m-d H:i:s');
        $ends = $now->modify('+' . $windowSeconds . ' seconds')->format('Y-m-d H:i:s');
        $key = substr(hash('sha256', $bucket), 0, 64);

        $this->db->execute(
            'INSERT INTO rate_limits (bucket, hits, window_ends_at) VALUES (:b, 1, :ends)
             ON DUPLICATE KEY UPDATE
               hits = IF(window_ends_at <= :now1, 1, hits + 1),
               window_ends_at = IF(window_ends_at <= :now2, :ends2, window_ends_at)',
            ['b' => $key, 'ends' => $ends, 'now1' => $nowSql, 'now2' => $nowSql, 'ends2' => $ends]
        );
        $hits = (int) $this->db->value('SELECT hits FROM rate_limits WHERE bucket = :b', ['b' => $key]);
        return $hits <= $max;
    }

    public function tooMany(string $bucket, int $max): bool
    {
        $key = substr(hash('sha256', $bucket), 0, 64);
        $row = $this->db->one('SELECT hits, window_ends_at FROM rate_limits WHERE bucket = :b', ['b' => $key]);
        if ($row === null || $row['window_ends_at'] <= $this->clock->nowSql()) {
            return false;
        }
        return (int) $row['hits'] >= $max;
    }

    public function clear(string $bucket): void
    {
        $this->db->execute('DELETE FROM rate_limits WHERE bucket = :b', ['b' => substr(hash('sha256', $bucket), 0, 64)]);
    }
}
