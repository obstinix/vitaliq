<?php

declare(strict_types=1);

namespace Vitaliq\Services;

use Vitaliq\Core\App;

/**
 * Report verification codes (feature 56). Each released report gets one
 * code, printed with a QR link. Anyone holding the printed report can
 * confirm at /verify that it is genuine and unaltered: the page shows the
 * reference, the test, the outcome, the issue date and the person's
 * initials, nothing more. Codes are random; only their hash is stored.
 * A per-installation HMAC makes codes impossible to forge from a
 * reference number.
 */
final class ReportVerificationService
{
    public function __construct(private readonly App $app)
    {
    }

    /** The code for a report, created on first use. Shown as XXXX-XXXX-XXXX-XXXX. */
    public function codeFor(string $kind, int $refId): string
    {
        // Derived, so the same report always shows the same code without storing it.
        $raw = \Vitaliq\Auth\Totp::base32Encode(substr(hash_hmac('sha256', $kind . ':' . $refId, 'report-verify|' . $this->app->config['key'], true), 0, 10));
        $code = implode('-', str_split($raw, 4));
        $this->app->db->execute(
            'INSERT IGNORE INTO report_verifications (kind, ref_id, code_hash, issued_at) VALUES (:k, :r, :h, :t)',
            ['k' => $kind, 'r' => $refId, 'h' => hash('sha256', $raw), 't' => $this->app->clock->nowSql()]
        );
        return $code;
    }

    public function url(string $code): string
    {
        return $this->app->config['url'] . '/verify/' . str_replace('-', '', $code);
    }

    /** @return array<string,mixed>|null what a verifier may see */
    public function check(string $code): ?array
    {
        $raw = strtoupper((string) preg_replace('/[^A-Za-z0-9]/', '', $code));
        if (!preg_match('/^[A-Z2-7]{16}$/', $raw)) {
            return null;
        }
        $row = $this->app->db->one('SELECT * FROM report_verifications WHERE code_hash = :h', ['h' => hash('sha256', $raw)]);
        if ($row === null) {
            return null;
        }
        $this->app->db->execute('UPDATE report_verifications SET checks = checks + 1 WHERE id = :id', ['id' => $row['id']]);
        $initials = static fn (string $name): string => implode('', array_map(static fn ($w) => mb_strtoupper(mb_substr($w, 0, 1)) . '.', array_slice(preg_split('/\s+/', trim($name)) ?: [], 0, 3)));
        if ($row['kind'] === 'test_request') {
            $r = $this->app->db->one(
                "SELECT r.reference, r.test_type, r.status, res.outcome, res.released_at, p.full_name, p.date_of_birth FROM test_requests r
                   JOIN test_results res ON res.request_id = r.id JOIN patients p ON p.id = r.patient_id WHERE r.id = :id",
                ['id' => $row['ref_id']]
            );
            if ($r === null || $r['status'] !== 'result_available') {
                return ['valid' => false, 'reason' => 'This report has been withdrawn.'];
            }
            return ['valid' => $row['revoked_at'] === null, 'reason' => $row['revoked_at'] !== null ? 'This report has been withdrawn.' : null,
                    'reference' => $r['reference'], 'test' => \Vitaliq\Support\Labels::get(\Vitaliq\Support\Labels::TEST_TYPES, (string) $r['test_type']),
                    'outcome' => \Vitaliq\Support\Labels::get(\Vitaliq\Support\Labels::OUTCOMES, (string) $r['outcome']), 'issued' => $r['released_at'],
                    'initials' => $initials((string) $r['full_name']), 'birth_year' => substr((string) $r['date_of_birth'], 0, 4), 'checks' => (int) $row['checks'] + 1];
        }
        $o = $this->app->db->one(
            "SELECT o.order_no, o.status, o.released_at, p.full_name, p.date_of_birth,
                    (SELECT GROUP_CONCAT(t.name ORDER BY t.name SEPARATOR ', ') FROM lab_order_items i JOIN lab_tests t ON t.id = i.test_id WHERE i.order_id = o.id AND i.status = 'verified') AS tests
               FROM lab_orders o JOIN patients p ON p.id = o.patient_id WHERE o.id = :id",
            ['id' => $row['ref_id']]
        );
        if ($o === null || $o['status'] !== 'released') {
            return ['valid' => false, 'reason' => 'This report has been withdrawn.'];
        }
        return ['valid' => $row['revoked_at'] === null, 'reason' => $row['revoked_at'] !== null ? 'This report has been withdrawn.' : null,
                'reference' => $o['order_no'], 'test' => (string) $o['tests'], 'outcome' => 'See the printed report', 'issued' => $o['released_at'],
                'initials' => $initials((string) $o['full_name']), 'birth_year' => substr((string) $o['date_of_birth'], 0, 4), 'checks' => (int) $row['checks'] + 1];
    }
}
