<?php

declare(strict_types=1);

namespace Vitaliq\Controllers\Admin;

use Vitaliq\Controllers\Controller;
use Vitaliq\Core\Request;
use Vitaliq\Core\Response;
use Vitaliq\Services\StatsService;

final class AdminDashboardController extends Controller
{
    public function index(Request $request): Response
    {
        $stats = $this->app->service(StatsService::class);
        return $this->view('admin/dashboard', [
            'title' => 'Operations dashboard',
            'metrics' => $stats->dashboard(),
            'activity' => $stats->recentActivity(12),
            'workload' => $stats->workload(),
            'series' => $stats->dailySeries(30),
            'scripts' => ['charts'],
        ], 200, 'app');
    }

    public function series(Request $request): Response
    {
        $days = max(7, min(90, (int) $request->query('days', '30')));
        return Response::json(['daily' => $this->app->service(StatsService::class)->dailySeries($days)]);
    }
}
