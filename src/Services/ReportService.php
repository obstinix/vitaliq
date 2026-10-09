<?php

declare(strict_types=1);

namespace Vitaliq\Services;

use Vitaliq\Core\App;
use Vitaliq\Core\Database;
use Vitaliq\Support\Labels;
use Vitaliq\Validation\Rules;

/**
 * Search, filtering and CSV export of testing requests (admin only).
 * Filters are whitelisted or bound as parameters; sort keys map to fixed SQL.
 */
final class ReportService
{
    public const MAX_PER_PAGE = 100;
    public const EXPORT_LIMIT = 5000;

    private const SORTS = [
        'newest' => 'r.submitted_at DESC, r.id DESC',
        'oldest' => 'r.submitted_at ASC, r.id ASC',
        'preferred' => 'r.preferred_date ASC, r.id ASC',
        'reference' => 'r.reference ASC',
        'patient' => 'p.full_name ASC, r.id DESC',
    ];

    public function __construct(private readonly App $app)
    {
    }

    /**
     * Normalise raw query-string filters. Invalid values are dropped and
     * reported in 'errors' rather than reaching SQL.
     *
     * @param array<string,mixed> $query
     * @return array{reference:string,name:string,mobile:string,from:string,to:string,status:string,phlebotomist:int,test_type:string,state:string,sort:string,errors:array<string,string>}
     */
    public function filters(array $query): array
    {
        $get = static fn (string $k): string => is_scalar($query[$k] ?? null) ? trim((string) $query[$k]) : '';
        $errors = [];

        $reference = mb_strtoupper($get('reference'));
        if ($reference !== '' && !preg_match('/^[A-Z0-9-]{2,15}$/', $reference)) {
            $errors['reference'] = 'References contain only letters, digits and hyphens.';
            $reference = '';
        }
        $name = mb_substr((string) preg_replace('/[\h\v]+/u', ' ', $get('name')), 0, 100);
        $mobile = (string) preg_replace('/[^0-9]/', '', $get('mobile'));
        if (strlen($mobile) > 10) {
            $mobile = substr($mobile, -10);
        }
        if ($get('mobile') !== '' && strlen($mobile) < 3) {
            $errors['mobile'] = 'Enter at least three digits of the mobile number.';
            $mobile = '';
        }
        $from = $get('from');
        $to = $get('to');
        foreach (['from' => &$from, 'to' => &$to] as $key => &$value) {
            if ($value !== '' && Rules::check('iso_date', $value) !== null) {
                $errors[$key] = 'Use a valid date.';
                $value = '';
            }
        }
        unset($value);
        if ($from !== '' && $to !== '' && $from > $to) {
            $errors['to'] = 'The end date is before the start date.';
        }
        $status = $get('status');
        if ($status !== '' && !isset(Labels::STATUSES[$status])) {
            $status = '';
        }
        $type = $get('test_type');
        if ($type !== '' && !isset(Labels::TEST_TYPES[$type])) {
            $type = '';
        }
        $state = mb_strtoupper($get('state'));
        if ($state !== '' && !preg_match('/^[A-Z]{2}$/', $state)) {
            $state = '';
        }
        $sort = array_key_exists($get('sort'), self::SORTS) ? $get('sort') : 'newest';

        return [
            'reference' => $reference, 'name' => $name, 'mobile' => $mobile, 'from' => $from, 'to' => $to,
            'status' => $status, 'phlebotomist' => max(0, (int) $get('phlebotomist')), 'test_type' => $type,
            'state' => $state, 'sort' => $sort, 'errors' => $errors,
        ];
    }

    /**
     * @param array<string,mixed> $f
     * @return array{0:string,1:array<string,mixed>}
     */
    private function where(array $f): array
    {
        $where = [];
        $params = [];
        if ($f['reference'] !== '') {
            $where[] = 'r.reference LIKE :ref';
            $params['ref'] = Database::likeEscape($f['reference']) . '%';
        }
        if ($f['name'] !== '') {
            $where[] = 'p.full_name LIKE :name';
            $params['name'] = '%' . Database::likeEscape($f['name']) . '%';
        }
        if ($f['mobile'] !== '') {
            $where[] = 'p.mobile LIKE :mobile';
            $params['mobile'] = '%' . Database::likeEscape($f['mobile']) . '%';
        }
        if ($f['from'] !== '') {
            $where[] = 'r.submitted_at >= :from';
            $params['from'] = $f['from'] . ' 00:00:00';
        }
        if ($f['to'] !== '') {
            $where[] = 'r.submitted_at < :to';
            $params['to'] = date('Y-m-d', strtotime($f['to'] . ' +1 day')) . ' 00:00:00';
        }
        if ($f['status'] !== '') {
            $where[] = 'r.status = :status';
            $params['status'] = $f['status'];
        }
        if ($f['test_type'] !== '') {
            $where[] = 'r.test_type = :type';
            $params['type'] = $f['test_type'];
        }
        if ($f['state'] !== '') {
            $where[] = 'r.state_code = :state';
            $params['state'] = $f['state'];
        }
        if ($f['phlebotomist'] > 0) {
            $where[] = 'a.phlebotomist_id = :phleb';
            $params['phleb'] = $f['phlebotomist'];
        }
        return [$where === [] ? '' : 'WHERE ' . implode(' AND ', $where), $params];
    }

    private const FROM = 'FROM test_requests r
        JOIN patients p ON p.id = r.patient_id
        LEFT JOIN test_assignments a ON a.request_id = r.id AND a.is_current = 1
        LEFT JOIN phlebotomists ph ON ph.id = a.phlebotomist_id
        LEFT JOIN test_results tr ON tr.request_id = r.id';

    /**
     * @param array<string,mixed> $f
     * @return array{rows:list<array<string,mixed>>,total:int,page:int,per_page:int,pages:int}
     */
    public function search(array $f, int $page = 1, int $perPage = 25): array
    {
        $perPage = max(5, min(self::MAX_PER_PAGE, $perPage));
        [$where, $params] = $this->where($f);
        $total = (int) $this->app->db->value('SELECT COUNT(*) ' . self::FROM . " {$where}", $params);
        $pages = max(1, (int) ceil($total / $perPage));
        $page = max(1, min($page, $pages));
        $rows = $this->app->db->all(
            'SELECT r.id, r.reference, r.test_type, r.status, r.submitted_at, r.preferred_date, r.preferred_slot,
                    r.collection_mode, r.city, r.state_code, p.full_name, p.mobile, ph.full_name AS phlebotomist_name,
                    ph.employee_code, tr.outcome, tr.released_at
             ' . self::FROM . " {$where} ORDER BY " . self::SORTS[$f['sort']] . ' LIMIT :lim OFFSET :off',
            $params + ['lim' => $perPage, 'off' => ($page - 1) * $perPage]
        );
        return ['rows' => $rows, 'total' => $total, 'page' => $page, 'per_page' => $perPage, 'pages' => $pages];
    }

    /**
     * @param array<string,mixed> $f
     * @return array<string,int> status => count within the filtered set
     */
    public function statusSummary(array $f): array
    {
        $f['status'] = '';
        [$where, $params] = $this->where($f);
        $rows = $this->app->db->all('SELECT r.status, COUNT(*) AS n ' . self::FROM . " {$where} GROUP BY r.status", $params);
        $out = array_fill_keys(array_keys(Labels::STATUSES), 0);
        foreach ($rows as $row) {
            $out[$row['status']] = (int) $row['n'];
        }
        return $out;
    }

    /** @param array<string,mixed> $f */
    public function csv(array $f): string
    {
        [$where, $params] = $this->where($f);
        $rows = $this->app->db->all(
            'SELECT r.reference, r.submitted_at, p.full_name, p.mobile, r.test_type, r.reason, r.collection_mode,
                    r.city, r.state_code, r.preferred_date, r.preferred_slot, r.status, ph.employee_code,
                    ph.full_name AS phlebotomist_name, tr.outcome, tr.released_at
             ' . self::FROM . " {$where} ORDER BY " . self::SORTS[$f['sort']] . ' LIMIT :lim',
            $params + ['lim' => self::EXPORT_LIMIT]
        );
        $out = fopen('php://temp', 'r+');
        fwrite($out, "\xEF\xBB\xBF");
        fputcsv($out, ['Reference', 'Submitted', 'Patient', 'Mobile', 'Test', 'Reason', 'Collection', 'City', 'State',
            'Preferred date', 'Slot', 'Status', 'Phlebotomist code', 'Phlebotomist', 'Outcome', 'Released'], ',', '"', '');
        foreach ($rows as $row) {
            $row['test_type'] = Labels::get(Labels::TEST_TYPES_SHORT, $row['test_type']);
            $row['reason'] = Labels::get(Labels::REASONS, $row['reason']);
            $row['collection_mode'] = Labels::get(Labels::COLLECTION_MODES, $row['collection_mode']);
            $row['status'] = Labels::get(Labels::STATUSES, $row['status']);
            $row['outcome'] = $row['released_at'] !== null ? Labels::get(Labels::OUTCOMES, $row['outcome']) : '';
            fputcsv($out, array_map([self::class, 'csvCell'], array_values($row)), ',', '"', '');
        }
        rewind($out);
        $csv = (string) stream_get_contents($out);
        fclose($out);
        $this->app->service(AuditLog::class)->record('report.exported', 'test_request', null, ['rows' => count($rows)]);
        return $csv;
    }

    /**
     * Neutralise spreadsheet formula injection: a cell starting with
     * = + - @ tab or carriage return is prefixed with an apostrophe.
     */
    public static function csvCell(mixed $value): string
    {
        $value = (string) ($value ?? '');
        if ($value !== '' && in_array($value[0], ['=', '+', '-', '@', "\t", "\r"], true)) {
            return "'" . $value;
        }
        return $value;
    }
}
