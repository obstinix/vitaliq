<?php

declare(strict_types=1);

namespace Vitaliq\Controllers\Admin;

use Vitaliq\Controllers\Controller;
use Vitaliq\Core\Request;
use Vitaliq\Core\Response;
use Vitaliq\Services\PatientService;
use Vitaliq\Services\PhlebotomistService;
use Vitaliq\Services\ValidationFailed;
use Vitaliq\Services\WorkflowException;

final class AdminPhlebotomistController extends Controller
{
    public function index(Request $request): Response
    {
        $status = in_array($request->query('status'), ['active', 'inactive', 'all'], true) ? $request->query('status') : 'all';
        $search = mb_substr($request->query('q'), 0, 80);
        return $this->view('admin/phlebotomists', [
            'title' => 'Phlebotomists',
            'list' => $this->app->service(PhlebotomistService::class)->list($search, $status, $this->page($request)),
            'status' => $status,
            'search' => $search,
            'page' => $this->page($request),
        ], 200, 'app');
    }

    /**
     * @param array<string,mixed>|null $record
     * @param array<string,string> $errors
     * @param array<string,mixed> $old
     */
    private function form(?array $record, array $errors = [], array $old = [], int $status = 200): Response
    {
        return $this->view('admin/phlebotomist-form', [
            'title' => $record === null ? 'Add phlebotomist' : 'Edit ' . $record['full_name'],
            'record' => $record,
            'states' => $this->app->service(PatientService::class)->states(),
            'errors' => $errors,
            'old' => $old !== [] ? $old : ($record ?? []),
            'openCollections' => $record === null ? 0 : $this->app->service(PhlebotomistService::class)->openCollections((int) $record['id']),
        ], $status, 'app');
    }

    public function createForm(Request $request): Response
    {
        return $this->form(null);
    }

    public function create(Request $request): Response
    {
        try {
            $id = $this->app->service(PhlebotomistService::class)->create($request->body);
        } catch (ValidationFailed $e) {
            return $this->form(null, $e->errors, $request->body, 422);
        }
        $this->flash('success', 'Phlebotomist added.');
        return $this->redirect('/admin/phlebotomists/' . $id);
    }

    public function edit(Request $request, string $id): Response
    {
        $record = $this->app->service(PhlebotomistService::class)->find((int) $id);
        if ($record === null) {
            $this->notFound();
        }
        return $this->form($record);
    }

    public function update(Request $request, string $id): Response
    {
        $service = $this->app->service(PhlebotomistService::class);
        $record = $service->find((int) $id);
        if ($record === null) {
            $this->notFound();
        }
        try {
            $service->update((int) $id, $request->body);
        } catch (ValidationFailed $e) {
            return $this->form($record, $e->errors, $request->body, 422);
        }
        $this->flash('success', 'Details saved.');
        return $this->redirect('/admin/phlebotomists/' . (int) $id);
    }

    public function toggle(Request $request, string $id): Response
    {
        $service = $this->app->service(PhlebotomistService::class);
        $record = $service->find((int) $id);
        if ($record === null) {
            $this->notFound();
        }
        try {
            $service->setActive((int) $id, (int) $record['is_active'] !== 1);
            $this->flash('success', (int) $record['is_active'] === 1 ? 'Phlebotomist deactivated.' : 'Phlebotomist activated.');
        } catch (WorkflowException $e) {
            $this->flash('error', $e->getMessage());
        }
        return $this->redirect('/admin/phlebotomists/' . (int) $id);
    }

    public function delete(Request $request, string $id): Response
    {
        try {
            $outcome = $this->app->service(PhlebotomistService::class)->delete((int) $id);
        } catch (WorkflowException $e) {
            $this->flash('error', $e->getMessage());
            return $this->redirect('/admin/phlebotomists/' . (int) $id);
        }
        $this->flash('success', $outcome === 'deleted'
            ? 'Phlebotomist record deleted.'
            : 'This phlebotomist appears in testing history, so the record was archived instead of deleted.');
        return $this->redirect('/admin/phlebotomists');
    }
}
