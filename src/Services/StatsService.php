<?php

declare(strict_types=1);

namespace Vitaliq\Services;

use Vitaliq\Core\App;
use Vitaliq\Support\Labels;

/**
 * Aggregates computed from the database on every request. Nothing here is
 * hard-coded or randomised; an empty database yields zeros.
 */
final class StatsService
{
    public function __construct(private readonly App $app)
    {
    }

    /** @return array<string,int> */
    public function statusCounts(): array
    {
        $out = array_fill_keys(array_keys(Labels::STATUSES), 0);
        foreach ($this->app->db->all('SELECT status, COUNT(*) AS n FROM test_requests GROUP BY status') as $row) {
            $out[$row['status']] = (int) $row['n'];
        }
        return $out;
    }

    /** @return array<string,mixed> */
    public function dashboard(): array
    {
        $db = $this->app->db;
        $now = $this->app->clock->now();
        $counts = $this->statusCounts();
        $released = $db->one(
            "SELECT COUNT(*) AS total, SUM(outcome = 'positive') AS positive
               FROM test_results WHERE released_at IS NOT NULL"
        );
        $turnaround = $db->value(
            "SELECT AVG(TIMESTAMPDIFF(MINUTE, r.submitted_at, tr.released_at)) / 60
               FROM test_requests r JOIN test_results tr ON tr.request_id = r.id
              WHERE tr.released_at IS NOT NULL AND tr.released_at >= :since",
            ['since' => $now->modify('-30 days')->format('Y-m-d H:i:s')]
        );
        return [
            'counts' => $counts,
            'total_requests' => array_sum($counts),
            'patients' => (int) $db->value('SELECT COUNT(*) FROM patients'),
            'accounts' => (int) $db->value("SELECT COUNT(*) FROM users WHERE role = 'patient'"),
            'new_24h' => (int) $db->value('SELECT COUNT(*) FROM test_requests WHERE submitted_at >= :t', [
                't' => $now->modify('-24 hours')->format('Y-m-d H:i:s'),
            ]),
            'released_total' => (int) ($released['total'] ?? 0),
            'released_positive' => (int) ($released['positive'] ?? 0),
            'avg_turnaround_hours' => $turnaround === null ? null : round((float) $turnaround, 1),
            'active_phlebotomists' => (int) $db->value('SELECT COUNT(*) FROM phlebotomists WHERE is_active = 1 AND deleted_at IS NULL'),
            'pending_comments' => (int) $db->value("SELECT COUNT(*) FROM comments WHERE status = 'pending'"),
        ];
    }

    /**
     * Requests submitted and results released per day, oldest first, with
     * zero-filled days so charts show gaps honestly.
     *
     * @return list<array{date:string,submitted:int,released:int}>
     */
    public function dailySeries(int $days = 30): array
    {
        $end = $this->app->clock->now()->setTime(0, 0);
        $start = $end->modify('-' . ($days - 1) . ' days');
        $params = ['s' => $start->format('Y-m-d 00:00:00')];
        $submitted = array_column($this->app->db->all(
            'SELECT DATE(submitted_at) AS d, COUNT(*) AS n FROM test_requests WHERE submitted_at >= :s GROUP BY d', $params
        ), 'n', 'd');
        $released = array_column($this->app->db->all(
            'SELECT DATE(released_at) AS d, COUNT(*) AS n FROM test_results WHERE released_at >= :s GROUP BY d', $params
        ), 'n', 'd');
        $out = [];
        for ($day = $start; $day <= $end; $day = $day->modify('+1 day')) {
            $key = $day->format('Y-m-d');
            $out[] = ['date' => $key, 'submitted' => (int) ($submitted[$key] ?? 0), 'released' => (int) ($released[$key] ?? 0)];
        }
        return $out;
    }

    /** @return list<array<string,mixed>> */
    public function recentActivity(int $limit = 10): array
    {
        return $this->app->db->all(
            'SELECT h.created_at, h.from_status, h.to_status, h.actor_role, h.note, r.id AS request_id, r.reference,
                    u.full_name AS actor_name
               FROM test_status_history h
               JOIN test_requests r ON r.id = h.request_id
               LEFT JOIN users u ON u.id = h.actor_user_id
              ORDER BY h.created_at DESC, h.id DESC LIMIT :lim',
            ['lim' => $limit]
        );
    }

    /** @return list<array<string,mixed>> */
    public function workload(): array
    {
        return $this->app->db->all(
            "SELECT ph.id, ph.full_name, ph.employee_code, ph.base_city,
                    COUNT(CASE WHEN r.status = 'assigned' THEN 1 END) AS open_collections
               FROM phlebotomists ph
               LEFT JOIN test_assignments a ON a.phlebotomist_id = ph.id AND a.is_current = 1
               LEFT JOIN test_requests r ON r.id = a.request_id
              WHERE ph.is_active = 1 AND ph.deleted_at IS NULL
              GROUP BY ph.id, ph.full_name, ph.employee_code, ph.base_city
              ORDER BY open_collections DESC, ph.full_name
              LIMIT 8"
        );
    }

    /**
     * Requests grouped by the state of the collection address. Uses only the
     * state actually recorded on each request.
     *
     * @return list<array{code:string,name:string,requests:int,released:int,positive:int}>
     */
    public function stateSummary(): array
    {
        $rows = $this->app->db->all(
            "SELECT s.code, s.name, COUNT(r.id) AS requests,
                    COUNT(tr.released_at) AS released,
                    COUNT(CASE WHEN tr.released_at IS NOT NULL AND tr.outcome = 'positive' THEN 1 END) AS positive
               FROM test_requests r
               JOIN states s ON s.code = r.state_code
               LEFT JOIN test_results tr ON tr.request_id = r.id
              GROUP BY s.code, s.name
              ORDER BY requests DESC, s.name"
        );
        return array_map(static fn ($r) => [
            'code' => $r['code'], 'name' => $r['name'], 'requests' => (int) $r['requests'],
            'released' => (int) $r['released'], 'positive' => (int) $r['positive'],
        ], $rows);
    }

    /** @return array<string,mixed> */
    public function publicSummary(): array
    {
        $db = $this->app->db;
        $counts = $this->statusCounts();
        $byType = array_column($db->all('SELECT test_type, COUNT(*) AS n FROM test_requests GROUP BY test_type'), 'n', 'test_type');
        return [
            'counts' => $counts,
            'total' => array_sum($counts),
            'released' => $counts['result_available'],
            'in_pipeline' => $counts['submitted'] + $counts['assigned'] + $counts['sample_collected'] + $counts['in_progress'] + $counts['completed'],
            'by_type' => array_map('intval', $byType),
            'demo_seeded_at' => $db->value("SELECT value FROM app_settings WHERE name = 'demo_data_seeded_at'"),
            'updated_at' => $db->value('SELECT MAX(updated_at) FROM test_requests'),
        ];
    }
}
