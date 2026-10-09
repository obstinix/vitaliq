<?php

declare(strict_types=1);

namespace Vitaliq\Controllers\Admin;

use Vitaliq\Controllers\Controller;
use Vitaliq\Core\Request;
use Vitaliq\Core\Response;
use Vitaliq\Services\PhlebotomistService;
use Vitaliq\Services\ReportService;

final class AdminReportController extends Controller
{
    public function index(Request $request): Response
    {
        $reports = $this->app->service(ReportService::class);
        $filters = $reports->filters($request->query);
        $perPage = (int) $request->query('per_page', '25');
        return $this->view('admin/reports', [
            'title' => 'Testing reports',
            'filters' => $filters,
            'result' => $reports->search($filters, $this->page($request), $perPage),
            'summary' => $reports->statusSummary($filters),
            'phlebotomists' => $this->allPhlebotomists(),
            'states' => $this->app->db->all('SELECT code, name FROM states ORDER BY name'),
            'query' => array_intersect_key($request->query, array_flip(['reference', 'name', 'mobile', 'from', 'to', 'status', 'phlebotomist', 'test_type', 'state', 'sort', 'per_page'])),
        ], 200, 'app');
    }

    /** @return array<int,string> includes inactive phlebotomists for historical filtering */
    private function allPhlebotomists(): array
    {
        $rows = $this->app->db->all('SELECT id, full_name, employee_code FROM phlebotomists ORDER BY full_name');
        $out = [];
        foreach ($rows as $r) {
            $out[(int) $r['id']] = $r['full_name'] . ' · ' . $r['employee_code'];
        }
        return $out;
    }

    public function export(Request $request): Response
    {
        $reports = $this->app->service(ReportService::class);
        $csv = $reports->csv($reports->filters($request->query));
        $name = 'vitaliq-requests-' . $this->app->clock->now()->format('Ymd-His') . '.csv';
        return new Response($csv, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="' . $name . '"',
        ]);
    }
}
