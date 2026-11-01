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

    /**
     * Server-sent events (feature 34). The stream answers at once with the
     * current state and a retry hint, then closes; EventSource reconnects
     * after the hint, sending Last-Event-ID. Short-lived streams keep a
     * worker free on servers that cannot hold many open connections.
     */
    public function stream(Request $request): Response
    {
        $last = (int) ($request->header('last-event-id') ?: $request->query('last', '0'));
        $state = $this->app->service(NotificationService::class)->since($this->userId(), max(0, $last));
        $retry = max(5, (int) $this->app->config['notification_poll_seconds']) * 1000;
        $body = 'retry: ' . $retry . "\n";
        $body .= 'id: ' . $state['last'] . "\n";
        $body .= 'event: ' . ($state['new'] > 0 && $last > 0 ? 'notification' : 'state') . "\n";
        $body .= 'data: ' . json_encode(['unread' => $state['unread'], 'new' => $last > 0 ? $state['new'] : 0]) . "\n\n";
        return (new Response($body, 200, [
            'Content-Type' => 'text/event-stream; charset=UTF-8',
            'Cache-Control' => 'no-store',
            'X-Accel-Buffering' => 'no',
        ]));
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
