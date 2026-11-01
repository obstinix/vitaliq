<?php

declare(strict_types=1);

namespace Vitaliq\Controllers\Admin;

use Vitaliq\Controllers\Controller;
use Vitaliq\Core\Request;
use Vitaliq\Core\Response;
use Vitaliq\Services\BroadcastService;
use Vitaliq\Services\DeliveryService;
use Vitaliq\Services\ValidationFailed;

/** Broadcast messages and the outside-delivery queue (features 31-37). */
final class AdminBroadcastController extends Controller
{
    /**
     * @param array<string,string> $errors
     * @param array<string,mixed> $old
     */
    public function index(Request $request, array $errors = [], array $old = [], int $status = 200): Response
    {
        $delivery = $this->app->service(DeliveryService::class);
        $channels = $delivery->channels();
        return $this->view('admin/broadcasts', [
            'title' => 'Broadcasts',
            'recent' => $this->app->service(BroadcastService::class)->recent(),
            'queue' => $delivery->queueSummary(),
            'drivers' => ['email' => $channels->driver('email'), 'sms' => $channels->driver('sms'), 'whatsapp' => $channels->driver('whatsapp')],
            'states' => array_column($this->app->db->all('SELECT code, name FROM states ORDER BY name'), 'name', 'code'),
            'errors' => $errors, 'old' => $old,
        ], $status, 'app');
    }

    public function send(Request $request): Response
    {
        try {
            $r = $this->app->service(BroadcastService::class)->send($request->body, $this->userId());
        } catch (ValidationFailed $e) {
            return $this->index($request, $e->errors, $request->body, 422);
        }
        $this->flash('success', 'Broadcast sent to ' . number_format($r['recipients']) . ' people.');
        return $this->redirect('/admin/broadcasts');
    }
}
