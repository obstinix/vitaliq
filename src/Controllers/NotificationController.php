<?php

declare(strict_types=1);

namespace Vitaliq\Controllers;

use Vitaliq\Core\Request;
use Vitaliq\Core\Response;
use Vitaliq\Services\NotificationService;

final class NotificationController extends Controller
{
    public function index(Request $request): Response
    {
        $unreadOnly = $request->query('filter') === 'unread';
        return $this->view('account/notifications', [
            'title' => 'Notifications',
            'list' => $this->app->service(NotificationService::class)->page($this->userId(), $this->page($request), 20, $unreadOnly),
            'page' => $this->page($request),
            'unreadOnly' => $unreadOnly,
        ], 200, 'app');
    }

    /** Opening a notification marks it read and follows its link. */
    public function open(Request $request, string $id): Response
    {
        $service = $this->app->service(NotificationService::class);
        $note = $service->find($this->userId(), (int) $id);
        if ($note === null) {
            $this->notFound();
        }
        $service->markRead($this->userId(), (int) $id);
        $link = (string) ($note['link_path'] ?? '');
        return $this->redirect(preg_match('#^/[A-Za-z0-9/_-]*$#', $link) ? $link : '/notifications');
    }

    public function markRead(Request $request, string $id): Response
    {
        $ok = $this->app->service(NotificationService::class)->markRead($this->userId(), (int) $id);
        if ($request->wantsJson()) {
            return Response::json(['ok' => $ok, 'unread' => $this->app->service(NotificationService::class)->unreadCount($this->userId())], $ok ? 200 : 404);
        }
        if (!$ok) {
            $this->notFound();
        }
        return $this->redirect('/notifications');
    }

    public function markAllRead(Request $request): Response
    {
        $count = $this->app->service(NotificationService::class)->markAllRead($this->userId());
        if ($request->wantsJson()) {
            return Response::json(['ok' => true, 'marked' => $count, 'unread' => 0]);
        }
        $this->flash('success', $count === 1 ? '1 notification marked as read.' : "{$count} notifications marked as read.");
        return $this->redirect('/notifications');
    }

    /** Polled by the notification bell. */
    public function poll(Request $request): Response
    {
        $service = $this->app->service(NotificationService::class);
        $items = array_map(static fn (array $n) => [
            'id' => (int) $n['id'],
            'title' => $n['title'],
            'body' => $n['body'],
            'url' => url('/notifications/' . (int) $n['id']),
            'read' => $n['read_at'] !== null,
            'created_at' => iso_datetime($n['created_at']),
            'created_label' => fmt_datetime($n['created_at']),
        ], $service->latest($this->userId(), 8));
        return Response::json(['unread' => $service->unreadCount($this->userId()), 'items' => $items]);
    }
}
