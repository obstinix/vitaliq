<?php

declare(strict_types=1);

namespace Vitaliq\Services;

use DateTimeImmutable;
use Vitaliq\Core\App;

/**
 * Lab workload (feature 19) and performance analytics (feature 20).
 * Every figure is computed from the lab tables when the page loads.
 */
final class LabAnalyticsService
{
    public function __construct(private readonly App $app)
    {
    }

    /** @return array<string,mixed> */
    public function workload(): array
    {
        $db = $this->app->db;
        $now = $this->app->clock->now();
        $today = $now->format('Y-m-d');
        $sections = [];
        foreach ($db->all(
            "SELECT t.section, SUM(i.status = 'pending' AND s.status IN ('received','in_analysis')) AS to_enter,
                    SUM(i.status = 'entered') AS to_verify, SUM(i.status = 'pending' AND s.status NOT IN ('received','in_analysis','rejected')) AS waiting_sample
               FROM lab_order_items i JOIN lab_tests t ON t.id = i.test_id JOIN lab_orders o ON o.id = i.order_id
               LEFT JOIN lab_samples s ON s.id = i.sample_id
              WHERE o.status IN ('ordered','in_progress') GROUP BY t.section ORDER BY t.section"
        ) as $r) {
            $sections[] = ['section' => $r['section'], 'to_enter' => (int) $r['to_enter'], 'to_verify' => (int) $r['to_verify'], 'waiting_sample' => (int) $r['waiting_sample']];
        }
        $overdue = $db->all(
            "SELECT o.id, o.order_no, o.priority, o.created_at, p.full_name, MAX(t.turnaround_hours) AS tat
               FROM lab_orders o JOIN patients p ON p.id = o.patient_id JOIN lab_order_items i ON i.order_id = o.id JOIN lab_tests t ON t.id = i.test_id
              WHERE o.status IN ('ordered','in_progress','verified')
              GROUP BY o.id HAVING DATE_ADD(o.created_at, INTERVAL tat HOUR) < :now ORDER BY o.created_at LIMIT 20",
            ['now' => $now->format('Y-m-d H:i:s')]
        );
        $staff = $db->all(
            "SELECT u.full_name, SUM(x.kind = 'entered') AS entered, SUM(x.kind = 'verified') AS verified FROM (
               SELECT entered_by_user_id AS uid, 'entered' AS kind FROM lab_order_items WHERE entered_at >= :d1
               UNION ALL SELECT verified_by_user_id, 'verified' FROM lab_order_items WHERE verified_at >= :d2
             ) x JOIN users u ON u.id = x.uid GROUP BY u.id ORDER BY COUNT(*) DESC",
            ['d1' => $today . ' 00:00:00', 'd2' => $today . ' 00:00:00']
        );
        $priority = array_fill_keys(['stat', 'urgent', 'routine'], 0);
        foreach ($db->all("SELECT priority, COUNT(*) AS n FROM lab_orders WHERE status IN ('ordered','in_progress','verified') GROUP BY priority") as $r) {
            $priority[$r['priority']] = (int) $r['n'];
        }
        return [
            'sections' => $sections,
            'overdue' => $overdue,
            'staff' => $staff,
            'priority' => $priority,
            'to_release' => (int) $db->value("SELECT COUNT(*) FROM lab_orders WHERE status = 'verified'"),
            'covid_awaiting' => (int) $db->value("SELECT COUNT(*) FROM test_requests WHERE status IN ('sample_collected','in_progress','completed')"),
        ];
    }

    /** @return array<string,mixed> */
    public function performance(int $days = 30): array
    {
        $db = $this->app->db;
        $days = max(7, min(365, $days));
        $now = $this->app->clock->now();
        $since = $now->modify("-{$days} days")->setTime(0, 0)->format('Y-m-d H:i:s');

        $byTest = [];
        foreach ($db->all(
            "SELECT t.code, t.name, t.turnaround_hours, TIMESTAMPDIFF(MINUTE, o.created_at, i.verified_at) AS minutes
               FROM lab_order_items i JOIN lab_tests t ON t.id = i.test_id JOIN lab_orders o ON o.id = i.order_id
              WHERE i.status = 'verified' AND i.verified_at >= :s",
            ['s' => $since]
        ) as $r) {
            $byTest[$r['code']]['name'] = $r['name'];
            $byTest[$r['code']]['target'] = (int) $r['turnaround_hours'];
            $byTest[$r['code']]['minutes'][] = (int) $r['minutes'];
        }
        $tat = [];
        foreach ($byTest as $code => $t) {
            sort($t['minutes']);
            $n = count($t['minutes']);
            $within = count(array_filter($t['minutes'], static fn ($m) => $m <= $t['target'] * 60));
            $tat[] = ['code' => $code, 'name' => $t['name'], 'n' => $n, 'target_h' => $t['target'],
                      'median_h' => self::percentile($t['minutes'], 0.5) / 60, 'p90_h' => self::percentile($t['minutes'], 0.9) / 60,
                      'within_pct' => $within / $n * 100];
        }
        usort($tat, static fn ($a, $b) => $b['n'] <=> $a['n']);

        $samples = (int) $db->value('SELECT COUNT(*) FROM lab_samples WHERE created_at >= :s', ['s' => $since]);
        $rejections = $db->all(
            "SELECT rejection_reason AS reason, COUNT(*) AS n FROM lab_samples WHERE status = 'rejected' AND created_at >= :s GROUP BY rejection_reason ORDER BY n DESC",
            ['s' => $since]
        );
        $rejected = array_sum(array_map(static fn ($r) => (int) $r['n'], $rejections));

        $daily = [];
        $start = new DateTimeImmutable(substr($since, 0, 10));
        for ($d = $start; $d <= $now; $d = $d->modify('+1 day')) {
            $daily[$d->format('Y-m-d')] = 0;
        }
        foreach ($db->all("SELECT DATE(verified_at) AS d, COUNT(*) AS n FROM lab_order_items WHERE status = 'verified' AND verified_at >= :s GROUP BY DATE(verified_at)", ['s' => $since]) as $r) {
            $daily[(string) $r['d']] = (int) $r['n'];
        }

        return [
            'days' => $days,
            'tat' => $tat,
            'samples' => $samples,
            'rejected' => $rejected,
            'rejection_rate' => $samples > 0 ? $rejected / $samples * 100 : null,
            'rejections' => $rejections,
            'verified' => array_sum($daily),
            'returned' => (int) $db->value('SELECT COALESCE(SUM(returned_count), 0) FROM lab_order_items i JOIN lab_orders o ON o.id = i.order_id WHERE o.created_at >= :s', ['s' => $since]),
            'critical' => (int) $db->value("SELECT COUNT(*) FROM lab_order_items WHERE flag IN ('critical_low','critical_high') AND verified_at >= :s", ['s' => $since]),
            'daily' => array_map(static fn ($d, $n) => ['date' => $d, 'n' => $n], array_keys($daily), array_values($daily)),
        ];
    }

    /** @param list<int> $sorted ascending */
    public static function percentile(array $sorted, float $p): float
    {
        $n = count($sorted);
        if ($n === 0) {
            return 0.0;
        }
        $rank = $p * ($n - 1);
        $lo = (int) floor($rank);
        $hi = (int) ceil($rank);
        return $sorted[$lo] + ($sorted[$hi] - $sorted[$lo]) * ($rank - $lo);
    }
}
