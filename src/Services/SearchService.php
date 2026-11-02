<?php

declare(strict_types=1);

namespace Vitaliq\Services;

use Vitaliq\Core\App;
use Vitaliq\Core\Database;

/**
 * Global search (feature 45), scoped by role: each person searches only
 * what their role can already open. At most 8 results per group.
 */
final class SearchService
{
    private const LIMIT = 8;

    public function __construct(private readonly App $app)
    {
    }

    /**
     * @param array<string,mixed> $user
     * @return array<string,list<array{label:string,detail:string,url:string}>>
     */
    public function search(array $user, string $q): array
    {
        $q = trim(mb_substr($q, 0, 80));
        if (mb_strlen($q) < 2) {
            return [];
        }
        $like = '%' . Database::likeEscape($q) . '%';
        $exact = strtoupper(preg_replace('/\s+/', '', $q) ?? '');
        $db = $this->app->db;
        $role = (string) $user['role'];
        $uid = (int) $user['id'];
        $out = [];
        $map = static fn (array $rows, callable $f): array => array_map($f, $rows);

        if (in_array($role, ['admin', 'lab'], true)) {
            $out['Patients'] = $map($db->all(
                'SELECT id, full_name, mobile, date_of_birth FROM patients WHERE full_name LIKE :a OR mobile LIKE :b ORDER BY full_name LIMIT ' . self::LIMIT,
                ['a' => $like, 'b' => $like]
            ), static fn ($r) => ['label' => (string) $r['full_name'], 'detail' => format_mobile((string) $r['mobile']) . ' · born ' . $r['date_of_birth'],
                'url' => $role === 'lab' ? '/lab/orders/new?q=' . rawurlencode((string) $r['full_name']) : '/admin/requests?name=' . rawurlencode((string) $r['full_name'])]);
            $out['Lab orders'] = $map($db->all(
                'SELECT o.id, o.order_no, o.status, p.full_name FROM lab_orders o JOIN patients p ON p.id = o.patient_id WHERE o.order_no LIKE :a ORDER BY o.id DESC LIMIT ' . self::LIMIT,
                ['a' => $like]
            ), static fn ($r) => ['label' => (string) $r['order_no'], 'detail' => $r['full_name'] . ' · ' . $r['status'], 'url' => '/lab/orders/' . $r['id']]);
            $out['Samples'] = $map($db->all(
                'SELECT s.id, s.barcode, s.status, o.order_no FROM lab_samples s JOIN lab_orders o ON o.id = s.order_id WHERE s.barcode LIKE :a LIMIT ' . self::LIMIT,
                ['a' => preg_replace('/\D/', '', $q) === '' ? 'x' : '%' . preg_replace('/\D/', '', $q) . '%']
            ), static fn ($r) => ['label' => (string) $r['barcode'], 'detail' => $r['order_no'] . ' · ' . str_replace('_', ' ', (string) $r['status']), 'url' => '/lab/samples/' . $r['id']]);
        }
        if ($role === 'admin') {
            $out['Testing requests'] = $map($db->all(
                'SELECT r.id, r.reference, r.status, p.full_name FROM test_requests r JOIN patients p ON p.id = r.patient_id WHERE r.reference LIKE :a ORDER BY r.id DESC LIMIT ' . self::LIMIT,
                ['a' => '%' . Database::likeEscape($exact) . '%']
            ), static fn ($r) => ['label' => (string) $r['reference'], 'detail' => $r['full_name'] . ' · ' . status_label((string) $r['status']), 'url' => '/admin/requests/' . $r['id']]);
            $out['Users'] = $map($db->all(
                'SELECT id, full_name, email, role FROM users WHERE full_name LIKE :a OR email LIKE :b ORDER BY full_name LIMIT ' . self::LIMIT,
                ['a' => $like, 'b' => $like]
            ), static fn ($r) => ['label' => (string) $r['full_name'], 'detail' => $r['email'] . ' · ' . $r['role'], 'url' => '/admin/users/' . $r['id'] . '/permissions']);
        }
        if (in_array($role, ['admin', 'officer'], true)) {
            $out['Facilities'] = $map($db->all(
                'SELECT id, code, name, city FROM facilities WHERE name LIKE :a OR code LIKE :b OR city LIKE :c ORDER BY name LIMIT ' . self::LIMIT,
                ['a' => $like, 'b' => $like, 'c' => $like]
            ), static fn ($r) => ['label' => (string) $r['name'], 'detail' => $r['code'] . ' · ' . $r['city'], 'url' => $role === 'admin' ? '/admin/facilities/' . $r['id'] : '/surveillance/facilities']);
        }
        if ($role === 'phlebotomist') {
            $out['Your collections'] = $map($db->all(
                'SELECT r.id, r.reference, r.status, p.full_name FROM test_requests r JOIN patients p ON p.id = r.patient_id
                   JOIN test_assignments a ON a.request_id = r.id AND a.is_current = 1 JOIN phlebotomists ph ON ph.id = a.phlebotomist_id
                  WHERE ph.user_id = :u AND (r.reference LIKE :a OR p.full_name LIKE :b) LIMIT ' . self::LIMIT,
                ['u' => $uid, 'a' => '%' . Database::likeEscape($exact) . '%', 'b' => $like]
            ), static fn ($r) => ['label' => (string) $r['reference'], 'detail' => $r['full_name'] . ' · ' . status_label((string) $r['status']), 'url' => '/staff/tasks/' . $r['id']]);
        }
        if ($role === 'patient') {
            $ids = implode(',', array_map(static fn ($p) => (int) $p['id'], $this->app->service(PatientPlatformService::class)->profiles($uid))) ?: '0';
            $out['Your tests'] = $map($db->all(
                "SELECT id, reference, status FROM test_requests WHERE patient_id IN ({$ids}) AND reference LIKE :a ORDER BY id DESC LIMIT " . self::LIMIT,
                ['a' => '%' . Database::likeEscape($exact) . '%']
            ), static fn ($r) => ['label' => (string) $r['reference'], 'detail' => status_label((string) $r['status']), 'url' => '/patient/requests/' . $r['id']]);
            $out['Your documents'] = $map($db->all(
                "SELECT id, title, patient_id, created_at FROM patient_documents WHERE patient_id IN ({$ids}) AND deleted_at IS NULL AND title LIKE :a ORDER BY id DESC LIMIT " . self::LIMIT,
                ['a' => $like]
            ), static fn ($r) => ['label' => (string) $r['title'], 'detail' => 'Added ' . substr((string) $r['created_at'], 0, 10), 'url' => '/patient/documents']);
        }
        if (in_array($role, ['qa', 'admin'], true)) {
            $out['Defects'] = $map($db->all(
                'SELECT id, title, status FROM defects WHERE title LIKE :a ORDER BY id DESC LIMIT ' . self::LIMIT,
                ['a' => $like]
            ), static fn ($r) => ['label' => (string) $r['title'], 'detail' => (string) $r['status'], 'url' => '/qa/defects/' . $r['id']]);
        }
        return array_filter($out, static fn ($g) => $g !== []);
    }
}
