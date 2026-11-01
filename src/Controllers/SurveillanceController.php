<?php

declare(strict_types=1);

namespace Vitaliq\Controllers;

use Vitaliq\Core\Request;
use Vitaliq\Core\Response;
use Vitaliq\Services\OutbreakService;
use Vitaliq\Services\SurveillanceImportService;
use Vitaliq\Services\SurveillanceService;
use Vitaliq\Services\ValidationFailed;
use Vitaliq\Services\WorkflowException;
use Vitaliq\Support\PdfDocument;
use Vitaliq\Support\Stats;

/** Public-health surveillance workspace (features 21-30) for officers and administrators. */
final class SurveillanceController extends Controller
{
    private function svc(): SurveillanceService
    {
        return $this->app->service(SurveillanceService::class);
    }

    /** @return array{disease:string,days:int,state:string} */
    private function scope(Request $request, int $defaultDays = 28): array
    {
        $disease = $request->query('disease', 'covid19');
        $days = (int) $request->query('days', (string) $defaultDays);
        $state = strtoupper($request->query('state'));
        return [
            'disease' => isset(SurveillanceService::DISEASES[$disease]) ? $disease : 'covid19',
            'days' => in_array($days, [7, 14, 28, 90, 365], true) ? $days : $defaultDays,
            'state' => isset($this->svc()->stateNames()[$state]) ? $state : '',
        ];
    }

    /**
     * @param array{disease:string,days:int,state:string} $scope
     * @param array<string,mixed> $data
     */
    private function screen(string $template, string $title, array $scope, array $data = [], int $status = 200): Response
    {
        return $this->view('surveillance/' . $template, $data + $scope + [
            'title' => $title,
            'diseases' => SurveillanceService::DISEASES,
            'states' => $this->svc()->stateNames(),
            'openAlerts' => (int) $this->app->db->value("SELECT COUNT(*) FROM outbreak_alerts WHERE status = 'open'"),
        ], $status, 'app');
    }

    public function overview(Request $request): Response
    {
        $s = $this->scope($request);
        return $this->screen('overview', 'Surveillance', $s, [
            'data' => $this->svc()->overview($s['disease'], $s['days'], $s['state']),
            'alerts' => array_slice($this->app->service(OutbreakService::class)->alerts('open'), 0, 5),
        ]);
    }

    public function map(Request $request): Response
    {
        $s = $this->scope($request);
        return $this->screen('map', 'Map by state', $s, ['tiles' => $this->svc()->map($s['disease'], $s['days'])]);
    }

    public function trends(Request $request): Response
    {
        $s = $this->scope($request);
        return $this->screen('trends', 'Trends and seasons', $s, ['data' => $this->svc()->trends($s['disease'], $s['state'])]);
    }

    public function demographics(Request $request): Response
    {
        $s = $this->scope($request, 90);
        return $this->screen('demographics', 'Demographics', $s, ['data' => $this->svc()->demographics($s['disease'], $s['days'], $s['state'])]);
    }

    public function facilities(Request $request): Response
    {
        $s = $this->scope($request, 90);
        return $this->screen('facilities', 'Facility comparison', $s, ['data' => $this->svc()->facilities($s['disease'], $s['days'])]);
    }

    public function quality(Request $request): Response
    {
        return $this->screen('quality', 'Data quality', $this->scope($request), ['q' => $this->svc()->quality()]);
    }

    /* -------------------------------------------------------------- alerts */

    /**
     * @param array<string,string> $errors
     * @param array<string,mixed> $old
     */
    public function alerts(Request $request, array $errors = [], array $old = [], int $status = 200): Response
    {
        $out = $this->app->service(OutbreakService::class);
        $filter = $request->query('status');
        return $this->screen('alerts', 'Outbreak alerts', $this->scope($request), [
            'alerts' => $out->alerts($filter), 'filter' => $filter, 'rules' => $out->thresholds(), 'methods' => OutbreakService::METHODS,
            'errors' => $errors, 'old' => $old,
        ], $status);
    }

    public function createRule(Request $request): Response
    {
        try {
            $this->app->service(OutbreakService::class)->createThreshold($request->body, $this->userId());
        } catch (ValidationFailed $e) {
            return $this->alerts($request, $e->errors, $request->body, 422);
        }
        $this->flash('success', 'Rule added. It is checked every time the alert check runs.');
        return $this->redirect('/surveillance/alerts');
    }

    public function toggleRule(Request $request, string $id): Response
    {
        try {
            $this->app->service(OutbreakService::class)->toggleThreshold((int) $id, $this->userId());
        } catch (WorkflowException $e) {
            $this->notFound();
        }
        return $this->redirect('/surveillance/alerts');
    }

    public function runCheck(Request $request): Response
    {
        $raised = $this->app->service(OutbreakService::class)->evaluate();
        $this->flash(count($raised) > 0 ? 'notice' : 'success', count($raised) === 0 ? 'Check complete: no new alerts.' : count($raised) . ' new ' . (count($raised) === 1 ? 'alert' : 'alerts') . ' raised.');
        return $this->redirect('/surveillance/alerts');
    }

    public function alertStatus(Request $request, string $id): Response
    {
        try {
            $this->app->service(OutbreakService::class)->setAlertStatus((int) $id, $request->input('status'), $request->input('note'), $this->userId());
            $this->flash('success', 'Alert updated.');
        } catch (WorkflowException $e) {
            $this->flash('error', $e->getMessage());
        } catch (ValidationFailed $e) {
            $this->flash('error', implode(' ', $e->errors));
        }
        return $this->redirect('/surveillance/alerts');
    }

    /* ------------------------------------------------------------- imports */

    /**
     * @param array<string,string> $errors
     * @param array<string,mixed> $old
     */
    public function imports(Request $request, array $errors = [], array $old = [], int $status = 200): Response
    {
        return $this->screen('imports', 'Imports', $this->scope($request), [
            'imports' => $this->app->service(SurveillanceImportService::class)->imports(), 'errors' => $errors, 'old' => $old,
        ], $status);
    }

    public function upload(Request $request): Response
    {
        try {
            $id = $this->app->service(SurveillanceImportService::class)->preview($request->file('file'), $request->input('source_name'), $this->userId());
        } catch (ValidationFailed $e) {
            return $this->imports($request, $e->errors, $request->body, 422);
        }
        return $this->redirect('/surveillance/imports/' . $id);
    }

    public function import(Request $request, string $id): Response
    {
        $imp = $this->app->service(SurveillanceImportService::class)->find((int) $id);
        if ($imp === null) {
            $this->notFound();
        }
        return $this->screen('import', 'Import ' . $imp['filename'], $this->scope($request), ['imp' => $imp]);
    }

    public function importAction(Request $request, string $id): Response
    {
        $svc = $this->app->service(SurveillanceImportService::class);
        try {
            switch ($request->input('action')) {
                case 'commit':
                    $r = $svc->commit((int) $id, $this->userId());
                    $this->flash('success', $r['added'] . ' rows added, ' . $r['replaced'] . ' existing rows replaced.');
                    break;
                case 'discard':
                    $svc->discard((int) $id, $this->userId());
                    $this->flash('success', 'Import discarded. Nothing was written.');
                    break;
                case 'rollback':
                    $n = $svc->rollback((int) $id, $this->userId());
                    $this->flash('success', $n . ' rows from this import were removed.');
                    break;
                default:
                    $this->flash('error', 'Unknown action.');
            }
        } catch (WorkflowException $e) {
            $this->flash('error', $e->getMessage());
        }
        return $this->redirect('/surveillance/imports/' . (int) $id);
    }

    public function template(Request $request): Response
    {
        $csv = "disease,date,state,tests,positives,sex,age_band,facility\n"
            . "dengue,2026-10-05,KA,120,9,,,\n"
            . "dengue,2026-10-05,Kerala,88,14,female,18-44,\n";
        return Response::download($csv, 'text/csv; charset=UTF-8', 'surveillance-import-template.csv');
    }

    /* ------------------------------------------------------------- reports */

    public function reports(Request $request): Response
    {
        return $this->screen('reports', 'Reports', $this->scope($request));
    }

    public function export(Request $request): Response
    {
        $s = $this->scope($request);
        $by = $request->query('by', 'state');
        $dims = ['state' => 'state_code', 'day' => 'd', 'sex' => 'sex', 'age' => 'age_band', 'facility' => 'facility_key', 'source' => 'src'];
        $dim = $dims[$by] ?? 'state_code';
        $w = $this->svc()->window($s['days']);
        $rows = $this->svc()->counts($s['disease'], $w['from'], $w['to'], [$dim], $s['state']);
        $states = $this->svc()->stateNames();
        $facilities = array_column($this->app->db->all('SELECT id, name FROM facilities'), 'name', 'id');
        $label = static function (array $r) use ($dim, $states, $facilities): string {
            $v = (string) $r[$dim];
            return match ($dim) {
                'state_code' => $states[$v] ?? $v,
                'facility_key' => $v === '0' ? 'Not linked to a facility' : ($facilities[(int) $v] ?? $v),
                'sex' => $v === 'any' ? 'Not recorded' : ucfirst($v),
                'age_band' => $v === 'all' ? 'Not recorded' : $v,
                default => $v,
            };
        };
        $disease = SurveillanceService::DISEASES[$s['disease']];
        $title = $disease . ' by ' . $by . ', ' . $w['from'] . ' to ' . $w['to'] . ($s['state'] !== '' ? ', ' . $states[$s['state']] : '');
        $this->app->service(\Vitaliq\Services\AuditLog::class)->record('surveillance.report_exported', 'report', null, ['format' => $request->query('format'), 'by' => $by]);

        if ($request->query('format') === 'pdf') {
            $pdf = new PdfDocument($title, 'Vitaliq surveillance report · generated ' . $this->app->clock->now()->format('Y-m-d H:i'));
            $pdf->heading($title);
            $tot = ['tests' => array_sum(array_column($rows, 'tests')), 'positives' => array_sum(array_column($rows, 'positives'))];
            $ci = Stats::wilson($tot['positives'], $tot['tests']);
            $pdf->paragraph(sprintf('%s tests, %s positive%s.', number_format($tot['tests']), number_format($tot['positives']),
                $ci['p'] === null ? '' : sprintf(' (positivity %.1f%%, 95%% CI %.1f–%.1f%%)', $ci['p'] * 100, $ci['low'] * 100, $ci['high'] * 100)));
            if ($dim === 'd') {
                $pdf->columns(array_map(static fn ($r) => ['label' => substr((string) $r['d'], 5), 'value' => $r['positives']], $rows), 'Positives per day.');
            }
            $table = array_map(static function ($r) use ($label) {
                $ci = Stats::wilson($r['positives'], $r['tests']);
                return [$label($r), number_format($r['tests']), number_format($r['positives']),
                        $ci['p'] === null ? '—' : number_format($ci['p'] * 100, 1) . '%', $ci['p'] === null ? '—' : sprintf('%.1f–%.1f%%', $ci['low'] * 100, $ci['high'] * 100)];
            }, $rows);
            $pdf->table([ucfirst($by), 'Tests', 'Positives', 'Positivity', '95% CI'], $table, [0.40, 0.14, 0.14, 0.14, 0.18], [1, 2, 3, 4]);
            $pdf->paragraph('Counts combine released results from the testing workflow, verified laboratory results and imported aggregate returns. Positivity intervals are Wilson score intervals. Figures describe testing, not the true number of infections.', 8.5, true);
            return Response::download($pdf->output(), 'application/pdf', 'vitaliq-' . $s['disease'] . '-' . $by . '-' . $w['to'] . '.pdf');
        }

        $fh = fopen('php://temp', 'r+');
        fputcsv($fh, [$by, 'tests', 'positives', 'positivity_pct', 'ci95_low_pct', 'ci95_high_pct'], ',', '"', '');
        foreach ($rows as $r) {
            $ci = Stats::wilson($r['positives'], $r['tests']);
            $name = $label($r);
            // Neutralise spreadsheet formulas in text cells.
            if (preg_match('/^[=+\-@\t\r]/', $name)) {
                $name = "'" . $name;
            }
            fputcsv($fh, [$name, $r['tests'], $r['positives'],
                $ci['p'] === null ? '' : number_format($ci['p'] * 100, 2, '.', ''),
                $ci['p'] === null ? '' : number_format($ci['low'] * 100, 2, '.', ''),
                $ci['p'] === null ? '' : number_format($ci['high'] * 100, 2, '.', '')], ',', '"', '');
        }
        rewind($fh);
        $csv = (string) stream_get_contents($fh);
        fclose($fh);
        return Response::download("\xEF\xBB\xBF" . $csv, 'text/csv; charset=UTF-8', 'vitaliq-' . $s['disease'] . '-' . $by . '-' . $w['to'] . '.csv');
    }
}
