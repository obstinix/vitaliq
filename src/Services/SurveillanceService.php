<?php

declare(strict_types=1);

namespace Vitaliq\Services;

use DateTimeImmutable;
use Vitaliq\Core\App;
use Vitaliq\Support\Stats;

/**
 * Surveillance analytics (features 21, 22, 24, 25, 26, 27, 30). Counts come
 * from three places, combined in one derived table:
 *  - released COVID-19 results from the testing workflow;
 *  - verified laboratory results for notifiable tests (COVID-19 PCR and
 *    antigen, dengue NS1), definitive outcomes only;
 *  - aggregate rows imported from CSV or JSON files.
 * Every figure is a count of tests and positives; no individual is exposed.
 */
final class SurveillanceService
{
    public const DISEASES = [
        'covid19' => 'COVID-19',
        'dengue' => 'Dengue',
        'influenza' => 'Influenza-like illness',
        'malaria' => 'Malaria',
        'tuberculosis' => 'Tuberculosis',
    ];
    public const AGE_BANDS = ['0-17' => '0–17', '18-44' => '18–44', '45-64' => '45–64', '65+' => '65 and over'];
    /** Smallest denominator for which a state's or facility's rate is shown. */
    public const MIN_TESTS = 10;

    public function __construct(private readonly App $app)
    {
    }

    private static function ageBand(string $dob, string $at): string
    {
        return "CASE WHEN TIMESTAMPDIFF(YEAR, {$dob}, {$at}) < 18 THEN '0-17' WHEN TIMESTAMPDIFF(YEAR, {$dob}, {$at}) < 45 THEN '18-44'
                     WHEN TIMESTAMPDIFF(YEAR, {$dob}, {$at}) < 65 THEN '45-64' ELSE '65+' END";
    }

    /** The combined event table as SQL (no parameters inside). */
    private function source(): string
    {
        $band1 = self::ageBand('p.date_of_birth', 'res.released_at');
        $band2 = self::ageBand('p.date_of_birth', 'i.verified_at');
        return "(
            SELECT DATE(res.released_at) AS d, 'covid19' AS disease, r.state_code, COALESCE(r.facility_id, 0) AS facility_key,
                   IF(p.sex IN ('female','male'), p.sex, 'any') AS sex, {$band1} AS age_band,
                   1 AS tests, IF(res.outcome = 'positive', 1, 0) AS positives, 'workflow' AS src
              FROM test_results res JOIN test_requests r ON r.id = res.request_id JOIN patients p ON p.id = r.patient_id
             WHERE res.released_at IS NOT NULL AND res.outcome IN ('positive','negative')
            UNION ALL
            SELECT DATE(i.verified_at), IF(t.code = 'DENNS1', 'dengue', 'covid19'), COALESCE(p.state_code, f.state_code), COALESCE(o.facility_id, 0),
                   IF(p.sex IN ('female','male'), p.sex, 'any'), {$band2},
                   1, IF(i.value_text IN ('Detected','Positive'), 1, 0), 'laboratory'
              FROM lab_order_items i JOIN lab_tests t ON t.id = i.test_id JOIN lab_orders o ON o.id = i.order_id
              JOIN patients p ON p.id = o.patient_id LEFT JOIN facilities f ON f.id = o.facility_id
             WHERE i.status = 'verified' AND t.code IN ('COVPCR','COVRAT','DENNS1')
               AND i.value_text IN ('Detected','Not detected','Positive','Negative')
               AND COALESCE(p.state_code, f.state_code) IS NOT NULL
            UNION ALL
            SELECT period_start, disease, state_code, facility_key, sex, age_band, tests, positives, 'imported'
              FROM surveillance_records
        ) ev";
    }

    /**
     * Aggregate counts grouped by the given columns of the event table.
     *
     * @param list<string> $by any of d, state_code, facility_key, sex, age_band, src, ym
     * @return list<array<string,mixed>>
     */
    public function counts(string $disease, string $from, string $to, array $by, string $state = ''): array
    {
        $cols = ['d' => 'ev.d', 'state_code' => 'ev.state_code', 'facility_key' => 'ev.facility_key', 'sex' => 'ev.sex',
                 'age_band' => 'ev.age_band', 'src' => 'ev.src', 'ym' => "DATE_FORMAT(ev.d, '%Y-%m')"];
        $select = [];
        foreach ($by as $b) {
            if (!isset($cols[$b])) {
                throw new \InvalidArgumentException("Unknown dimension {$b}");
            }
            $select[] = $cols[$b] . ' AS ' . $b;
        }
        $where = 'ev.disease = :dis AND ev.d BETWEEN :f AND :t';
        $params = ['dis' => $disease, 'f' => $from, 't' => $to];
        if ($state !== '') {
            $where .= ' AND ev.state_code = :st';
            $params['st'] = $state;
        }
        $group = $by === [] ? '' : ' GROUP BY ' . implode(', ', $by) . ' ORDER BY ' . implode(', ', $by);
        $sel = $select === [] ? '' : implode(', ', $select) . ', ';
        return array_map(static function ($r) {
            $r['tests'] = (int) $r['tests'];
            $r['positives'] = (int) $r['positives'];
            return $r;
        }, $this->app->db->all("SELECT {$sel}SUM(ev.tests) AS tests, SUM(ev.positives) AS positives FROM {$this->source()} WHERE {$where}{$group}", $params));
    }

    /** @return array{from:string,to:string,prevFrom:string,prevTo:string} */
    public function window(int $days, ?DateTimeImmutable $end = null): array
    {
        $end ??= $this->app->clock->now();
        $to = $end->format('Y-m-d');
        $from = $end->modify('-' . ($days - 1) . ' days')->format('Y-m-d');
        return ['from' => $from, 'to' => $to, 'prevFrom' => $end->modify('-' . (2 * $days - 1) . ' days')->format('Y-m-d'), 'prevTo' => $end->modify("-{$days} days")->format('Y-m-d')];
    }

    /**
     * Daily series with every day present (zeros, not gaps).
     *
     * @return list<array{date:string,tests:int,positives:int}>
     */
    public function daily(string $disease, string $from, string $to, string $state = ''): array
    {
        $byDay = [];
        foreach ($this->counts($disease, $from, $to, ['d'], $state) as $r) {
            $byDay[(string) $r['d']] = $r;
        }
        $out = [];
        for ($d = new DateTimeImmutable($from); $d->format('Y-m-d') <= $to; $d = $d->modify('+1 day')) {
            $k = $d->format('Y-m-d');
            $out[] = ['date' => $k, 'tests' => $byDay[$k]['tests'] ?? 0, 'positives' => $byDay[$k]['positives'] ?? 0];
        }
        return $out;
    }

    /** @return array<string,mixed> */
    public function overview(string $disease, int $days = 28, string $state = ''): array
    {
        $w = $this->window($days);
        $cur = $this->counts($disease, $w['from'], $w['to'], [], $state)[0] ?? ['tests' => 0, 'positives' => 0];
        $prev = $this->counts($disease, $w['prevFrom'], $w['prevTo'], [], $state)[0] ?? ['tests' => 0, 'positives' => 0];
        $states = $this->stateNames();
        $byState = [];
        foreach ($this->counts($disease, $w['from'], $w['to'], ['state_code']) as $r) {
            $ci = Stats::wilson($r['positives'], $r['tests']);
            $byState[] = $r + ['name' => $states[$r['state_code']] ?? $r['state_code'], 'rate' => $ci['p'], 'low' => $ci['low'], 'high' => $ci['high']];
        }
        usort($byState, static fn ($a, $b) => $b['positives'] <=> $a['positives'] ?: $b['tests'] <=> $a['tests']);
        $sources = [];
        foreach ($this->counts($disease, $w['from'], $w['to'], ['src'], $state) as $r) {
            $sources[$r['src']] = $r;
        }
        return [
            'window' => $w, 'days' => $days,
            'current' => $cur + ['ci' => Stats::wilson($cur['positives'], $cur['tests'])],
            'previous' => $prev + ['ci' => Stats::wilson($prev['positives'], $prev['tests'])],
            'daily' => $this->daily($disease, $w['from'], $w['to'], $state),
            'byState' => $byState,
            'sources' => $sources,
        ];
    }

    /**
     * Positivity by state in five levels for the tile map (feature 22).
     *
     * @return list<array<string,mixed>>
     */
    public function map(string $disease, int $days = 28): array
    {
        $w = $this->window($days);
        $counts = [];
        foreach ($this->counts($disease, $w['from'], $w['to'], ['state_code']) as $r) {
            $counts[$r['state_code']] = $r;
        }
        $out = [];
        foreach ($this->stateNames() as $code => $name) {
            $c = $counts[$code] ?? ['tests' => 0, 'positives' => 0];
            $rate = $c['tests'] >= self::MIN_TESTS ? $c['positives'] / $c['tests'] : null;
            $level = $rate === null ? 0 : ($rate < 0.02 ? 1 : ($rate < 0.05 ? 2 : ($rate < 0.10 ? 3 : 4)));
            $out[] = ['code' => $code, 'name' => $name, 'tests' => $c['tests'], 'positives' => $c['positives'], 'rate' => $rate, 'level' => $level];
        }
        return $out;
    }

    /** @return array<string,mixed> trend estimate and seasonal tables (features 24, 26) */
    public function trends(string $disease, string $state = ''): array
    {
        $now = $this->app->clock->now();
        // Complete days only: today is still filling, so the series ends yesterday.
        $daily = $this->daily($disease, $now->modify('-56 days')->format('Y-m-d'), $now->modify('-1 day')->format('Y-m-d'), $state);
        $positives = array_map(static fn ($d) => $d['positives'], $daily);
        $ma = Stats::movingAverage($positives, 7);
        $fitDays = array_slice($positives, -21);
        $trend = array_sum($fitDays) >= 10 ? Stats::logLinearTrend($fitDays, 7) : null;

        $monthly = $this->counts($disease, $now->modify('-3 years')->format('Y-m-01'), $now->format('Y-m-d'), ['ym'], $state);
        $byMonthOfYear = array_fill(1, 12, ['tests' => 0, 'positives' => 0, 'years' => []]);
        foreach ($monthly as $m) {
            $mo = (int) substr((string) $m['ym'], 5, 2);
            $byMonthOfYear[$mo]['tests'] += $m['tests'];
            $byMonthOfYear[$mo]['positives'] += $m['positives'];
            $byMonthOfYear[$mo]['years'][substr((string) $m['ym'], 0, 4)] = $m['positives'];
        }
        return ['daily' => $daily, 'ma' => $ma, 'trend' => $trend, 'monthly' => $monthly, 'byMonthOfYear' => $byMonthOfYear];
    }

    /** @return array{sex:list<array<string,mixed>>,age:list<array<string,mixed>>,unknown:array<string,int>} feature 25 */
    public function demographics(string $disease, int $days = 90, string $state = ''): array
    {
        $w = $this->window($days);
        $sex = $age = [];
        $unknown = ['sex' => 0, 'age' => 0];
        foreach ($this->counts($disease, $w['from'], $w['to'], ['sex'], $state) as $r) {
            if ($r['sex'] === 'any') {
                $unknown['sex'] += $r['tests'];
                continue;
            }
            $sex[] = $r + Stats::wilson($r['positives'], $r['tests']);
        }
        foreach ($this->counts($disease, $w['from'], $w['to'], ['age_band'], $state) as $r) {
            if ($r['age_band'] === 'all') {
                $unknown['age'] += $r['tests'];
                continue;
            }
            $age[] = $r + Stats::wilson($r['positives'], $r['tests']);
        }
        usort($age, static fn ($a, $b) => array_search($a['age_band'], array_keys(self::AGE_BANDS), true) <=> array_search($b['age_band'], array_keys(self::AGE_BANDS), true));
        return ['sex' => $sex, 'age' => $age, 'unknown' => $unknown];
    }

    /**
     * Facility comparison against the overall rate (feature 27). A facility is
     * flagged only when its 95% interval excludes the overall rate.
     *
     * @return array{overall:array<string,mixed>,rows:list<array<string,mixed>>}
     */
    public function facilities(string $disease, int $days = 90): array
    {
        $w = $this->window($days);
        $all = $this->counts($disease, $w['from'], $w['to'], [])[0] ?? ['tests' => 0, 'positives' => 0];
        $overall = $all + Stats::wilson($all['positives'], $all['tests']);
        $names = array_column($this->app->db->all('SELECT id, name, city FROM facilities'), null, 'id');
        $tat = [];
        foreach ($this->app->db->all(
            "SELECT COALESCE(r.facility_id, 0) AS fk, TIMESTAMPDIFF(MINUTE, r.submitted_at, res.released_at) AS m FROM test_results res
               JOIN test_requests r ON r.id = res.request_id WHERE res.released_at BETWEEN :f AND :t",
            ['f' => $w['from'] . ' 00:00:00', 't' => $w['to'] . ' 23:59:59']
        ) as $r) {
            $tat[(int) $r['fk']][] = (int) $r['m'];
        }
        $rows = [];
        foreach ($this->counts($disease, $w['from'], $w['to'], ['facility_key']) as $r) {
            $fk = (int) $r['facility_key'];
            $ci = Stats::wilson($r['positives'], $r['tests']);
            $flag = 'none';
            if ($r['tests'] >= self::MIN_TESTS && $overall['p'] !== null) {
                $flag = $ci['low'] > $overall['p'] ? 'above' : ($ci['high'] < $overall['p'] ? 'below' : 'within');
            }
            $minutes = $tat[$fk] ?? [];
            sort($minutes);
            $rows[] = $r + $ci + [
                'name' => $fk === 0 ? 'Not linked to a facility' : ($names[$fk]['name'] ?? 'Facility ' . $fk),
                'city' => $names[$fk]['city'] ?? '',
                'flag' => $flag,
                'tat_h' => $minutes === [] ? null : LabAnalyticsService::percentile($minutes, 0.5) / 60,
            ];
        }
        usort($rows, static fn ($a, $b) => $b['tests'] <=> $a['tests']);
        return ['overall' => $overall, 'rows' => $rows];
    }

    /** @return array<string,mixed> completeness and timeliness (feature 30) */
    public function quality(): array
    {
        $db = $this->app->db;
        $patients = (int) $db->value('SELECT COUNT(*) FROM patients');
        $requests = (int) $db->value('SELECT COUNT(*) FROM test_requests');
        $completeness = [
            ['label' => 'Patients with a state recorded', 'n' => (int) $db->value('SELECT COUNT(*) FROM patients WHERE state_code IS NOT NULL'), 'of' => $patients],
            ['label' => 'Patients with sex recorded', 'n' => (int) $db->value("SELECT COUNT(*) FROM patients WHERE sex IN ('female','male','other')"), 'of' => $patients],
            ['label' => 'Requests linked to a facility', 'n' => (int) $db->value('SELECT COUNT(*) FROM test_requests WHERE facility_id IS NOT NULL'), 'of' => $requests],
            ['label' => 'Released results with a Ct value (RT-PCR)', 'n' => (int) $db->value("SELECT COUNT(*) FROM test_results res JOIN test_requests r ON r.id = res.request_id WHERE r.test_type = 'rt_pcr' AND res.released_at IS NOT NULL AND res.ct_value IS NOT NULL"),
             'of' => (int) $db->value("SELECT COUNT(*) FROM test_results res JOIN test_requests r ON r.id = res.request_id WHERE r.test_type = 'rt_pcr' AND res.released_at IS NOT NULL")],
        ];
        $minutes = array_map('intval', array_column($db->all(
            'SELECT TIMESTAMPDIFF(MINUTE, s.collected_at, res.released_at) AS m FROM test_results res JOIN sample_collections s ON s.request_id = res.request_id WHERE res.released_at IS NOT NULL'
        ), 'm'));
        sort($minutes);
        $now = $this->app->clock->now();
        $stuck = (int) $db->value(
            "SELECT COUNT(*) FROM test_requests WHERE status IN ('sample_collected','in_progress','completed') AND updated_at < :t",
            ['t' => $now->modify('-3 days')->format('Y-m-d H:i:s')]
        );
        $imports = $db->one("SELECT COUNT(*) AS files, COALESCE(SUM(rows_total), 0) AS total, COALESCE(SUM(rows_failed), 0) AS failed FROM surveillance_imports WHERE status <> 'discarded'") ?? [];
        $latest = [];
        foreach (self::DISEASES as $k => $l) {
            $row = $db->one('SELECT MAX(period_start) AS last FROM surveillance_records WHERE disease = :d', ['d' => $k]);
            $latest[$k] = $row['last'] ?? null;
        }
        $inconclusive = (int) $db->value("SELECT COUNT(*) FROM test_results WHERE outcome IN ('inconclusive','invalid') AND released_at IS NOT NULL");
        $released = (int) $db->value('SELECT COUNT(*) FROM test_results WHERE released_at IS NOT NULL');
        return [
            'completeness' => $completeness,
            'timeliness' => ['n' => count($minutes), 'median_h' => $minutes === [] ? null : LabAnalyticsService::percentile($minutes, 0.5) / 60,
                             'p90_h' => $minutes === [] ? null : LabAnalyticsService::percentile($minutes, 0.9) / 60],
            'stuck' => $stuck,
            'imports' => $imports,
            'latestImported' => $latest,
            'inconclusive' => ['n' => $inconclusive, 'of' => $released],
        ];
    }

    /** @return array<string,string> */
    public function stateNames(): array
    {
        return array_column($this->app->db->all('SELECT code, name FROM states ORDER BY name'), 'name', 'code');
    }
}
