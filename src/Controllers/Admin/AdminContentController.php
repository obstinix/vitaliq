<?php

declare(strict_types=1);

namespace Vitaliq\Controllers\Admin;

use Vitaliq\Controllers\Controller;
use Vitaliq\Core\Request;
use Vitaliq\Core\Response;
use Vitaliq\Services\AnnouncementService;
use Vitaliq\Services\ValidationFailed;
use Vitaliq\Services\WorkflowException;

final class AdminContentController extends Controller
{
    /**
     * @param array<string,string> $errors
     * @param array<string,mixed> $old
     */
    private function render(array $errors = [], array $old = [], int $status = 200): Response
    {
        return $this->view('admin/announcements', [
            'title' => 'Announcements',
            'list' => $this->app->service(AnnouncementService::class)->all(),
            'categories' => AnnouncementService::CATEGORIES,
            'errors' => $errors,
            'old' => $old + ['published_on' => $this->app->clock->now()->format('Y-m-d')],
        ], $status, 'app');
    }

    public function index(Request $request): Response
    {
        return $this->render();
    }

    public function create(Request $request): Response
    {
        try {
            $this->app->service(AnnouncementService::class)->create($request->body);
        } catch (ValidationFailed $e) {
            return $this->render($e->errors, $request->body, 422);
        }
        $this->flash('success', 'Announcement published.');
        return $this->redirect('/admin/announcements');
    }

    public function toggle(Request $request, string $id): Response
    {
        try {
            $this->app->service(AnnouncementService::class)->togglePublished((int) $id);
            $this->flash('success', 'Visibility updated.');
        } catch (WorkflowException $e) {
            $this->flash('error', $e->getMessage());
        }
        return $this->redirect('/admin/announcements');
    }
}
