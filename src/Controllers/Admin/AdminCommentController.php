<?php

declare(strict_types=1);

namespace Vitaliq\Controllers\Admin;

use Vitaliq\Controllers\Controller;
use Vitaliq\Core\Request;
use Vitaliq\Core\Response;
use Vitaliq\Services\CommentService;
use Vitaliq\Services\WorkflowException;

final class AdminCommentController extends Controller
{
    public function index(Request $request): Response
    {
        $service = $this->app->service(CommentService::class);
        $status = $request->query('status', 'pending');
        return $this->view('admin/comments', [
            'title' => 'Comment moderation',
            'status' => $status,
            'list' => $service->forModeration($status, $this->page($request)),
            'counts' => $service->statusCounts(),
            'page' => $this->page($request),
        ], 200, 'app');
    }

    public function moderate(Request $request, string $id): Response
    {
        try {
            $this->app->service(CommentService::class)->moderate((int) $id, $request->input('decision'), $request->input('note') ?: null);
            $this->flash('success', 'Moderation decision saved.');
        } catch (WorkflowException $e) {
            $this->flash('error', $e->getMessage());
        }
        $status = $request->input('return_status');
        return $this->redirect('/admin/comments' . ($status !== '' ? '?status=' . rawurlencode($status) : ''));
    }
}
