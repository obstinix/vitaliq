<?php

declare(strict_types=1);

namespace Vitaliq\Controllers;

use Vitaliq\Core\Request;
use Vitaliq\Core\Response;
use Vitaliq\Services\ScheduleService;
use Vitaliq\Services\SearchService;
use Vitaliq\Services\ValidationFailed;
use Vitaliq\Services\WorkflowException;

/** Search for every signed-in role (feature 45) and staff scheduling (feature 42). */
final class WorkspaceController extends Controller
{
    public function search(Request $request): Response
    {
        $q = $request->query('q');
        $user = $this->app->auth()->user() ?? [];
        $limited = !$this->app->rateLimiter()->hit('search:' . $this->userId(), 60, 60);
        return $this->view('account/search', [
            'title' => 'Search',
            'searchQuery' => $q,
            'q' => $q,
            'groups' => $limited ? [] : $this->app->service(SearchService::class)->search($user, $q),
            'limited' => $limited,
        ], $limited ? 429 : 200, 'app');
    }

    /**
     * @param array<string,string> $errors
     * @param array<string,mixed> $old
     */
    public function schedule(Request $request, array $errors = [], array $old = [], int $status = 200): Response
    {
        $svc = $this->app->service(ScheduleService::class);
        $day = preg_match('/^\d{4}-\d{2}-\d{2}$/', $request->query('week')) ? $request->query('week') : $this->app->clock->now()->format('Y-m-d');
        $admin = $this->app->auth()->is('admin');
        return $this->view('staff/schedule', [
            'title' => $admin ? 'Staff schedule' : 'Your shifts',
            'week' => $svc->week($day, $admin ? null : $this->userId()),
            'admin' => $admin,
            'staff' => $admin ? $svc->staff() : [],
            'facilities' => $this->app->db->all('SELECT id, name FROM facilities WHERE is_active = 1 ORDER BY name'),
            'duties' => ScheduleService::DUTIES,
            'today' => $this->app->clock->now()->format('Y-m-d'),
            'errors' => $errors, 'old' => $old,
        ], $status, 'app');
    }

    public function addShift(Request $request): Response
    {
        try {
            $this->app->service(ScheduleService::class)->create($request->body, $this->userId());
        } catch (ValidationFailed $e) {
            return $this->schedule($request, $e->errors, $request->body, 422);
        }
        $this->flash('success', 'Shift added and the staff member notified.');
        return $this->redirect('/admin/schedule?week=' . rawurlencode($request->input('date')));
    }

    public function removeShift(Request $request, string $id): Response
    {
        try {
            $this->app->service(ScheduleService::class)->delete((int) $id, $this->userId());
            $this->flash('success', 'Shift removed.');
        } catch (WorkflowException $e) {
            $this->notFound();
        }
        return $this->redirect('/admin/schedule' . ($request->input('week') !== '' ? '?week=' . rawurlencode($request->input('week')) : ''));
    }
}
