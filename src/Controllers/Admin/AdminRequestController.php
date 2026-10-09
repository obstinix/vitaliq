<?php

declare(strict_types=1);

namespace Vitaliq\Controllers\Admin;

use Vitaliq\Controllers\Controller;
use Vitaliq\Core\Request;
use Vitaliq\Core\Response;
use Vitaliq\Services\PhlebotomistService;
use Vitaliq\Services\ReportService;
use Vitaliq\Services\StatsService;
use Vitaliq\Services\TestingService;
use Vitaliq\Services\ValidationFailed;
use Vitaliq\Services\WorkflowException;

final class AdminRequestController extends Controller
{
    public function index(Request $request): Response
    {
        $reports = $this->app->service(ReportService::class);
        $filters = $reports->filters($request->query + ['status' => $request->query('status', 'submitted')]);
        if ($request->query('status') === 'all') {
            $filters['status'] = '';
        }
        return $this->view('admin/requests', [
            'title' => 'Testing requests',
            'filters' => $filters,
            'tab' => $request->query('status', 'submitted'),
            'result' => $reports->search($filters, $this->page($request), 25),
            'counts' => $this->app->service(StatsService::class)->statusCounts(),
        ], 200, 'app');
    }

    /**
     * @param array<string,string> $errors
     * @param array<string,mixed> $old
     */
    private function renderShow(int $id, array $errors = [], array $old = [], int $status = 200): Response
    {
        $detail = $this->app->service(TestingService::class)->detail($id);
        if ($detail === null) {
            $this->notFound();
        }
        return $this->view('admin/request-show', [
            'title' => 'Request ' . $detail['reference'],
            'request' => $detail,
            'phlebotomists' => $this->app->service(PhlebotomistService::class)->activeOptions(),
            'errors' => $errors,
            'old' => $old,
        ], $status, 'app');
    }

    public function show(Request $request, string $id): Response
    {
        return $this->renderShow((int) $id);
    }

    /** @param callable():void $action */
    private function act(Request $request, int $id, callable $action, string $success): Response
    {
        try {
            $action();
        } catch (ValidationFailed $e) {
            return $this->renderShow($id, $e->errors, $request->body, 422);
        } catch (WorkflowException $e) {
            $this->flash('error', $e->getMessage());
            return $this->redirect('/admin/requests/' . $id);
        }
        $this->flash('success', $success);
        return $this->redirect('/admin/requests/' . $id);
    }

    public function assign(Request $request, string $id): Response
    {
        return $this->act($request, (int) $id, fn () => $this->app->service(TestingService::class)->assign(
            (int) $id, (int) $request->input('phlebotomist_id'), $this->actor(), trim($request->input('note')) ?: null
        ), 'Phlebotomist assignment saved.');
    }

    public function collect(Request $request, string $id): Response
    {
        return $this->act($request, (int) $id, fn () => $this->app->service(TestingService::class)->recordCollection(
            (int) $id, $request->body, $this->actor()
        ), 'Sample collection recorded.');
    }

    public function process(Request $request, string $id): Response
    {
        return $this->act($request, (int) $id, fn () => $this->app->service(TestingService::class)->startProcessing(
            (int) $id, $this->actor(), trim($request->input('note')) ?: null
        ), 'Marked as in progress at the laboratory.');
    }

    public function result(Request $request, string $id): Response
    {
        return $this->act($request, (int) $id, fn () => $this->app->service(TestingService::class)->recordResult(
            (int) $id, $request->body, $this->actor()
        ), 'Result recorded. Release it to make it visible to the patient.');
    }

    public function release(Request $request, string $id): Response
    {
        return $this->act($request, (int) $id, fn () => $this->app->service(TestingService::class)->releaseResult(
            (int) $id, $this->actor()
        ), 'Result released to the patient.');
    }

    public function reject(Request $request, string $id): Response
    {
        return $this->act($request, (int) $id, fn () => $this->app->service(TestingService::class)->reject(
            (int) $id, $this->actor(), $request->input('reason')
        ), 'Request rejected.');
    }

    public function cancel(Request $request, string $id): Response
    {
        return $this->act($request, (int) $id, fn () => $this->app->service(TestingService::class)->cancel(
            (int) $id, $this->actor(), $request->input('reason')
        ), 'Request cancelled.');
    }
}
