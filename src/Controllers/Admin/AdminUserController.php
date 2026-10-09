<?php

declare(strict_types=1);

namespace Vitaliq\Controllers\Admin;

use Vitaliq\Controllers\Controller;
use Vitaliq\Core\Request;
use Vitaliq\Core\Response;
use Vitaliq\Services\AuditLog;
use Vitaliq\Services\PhlebotomistService;
use Vitaliq\Services\UserAdminService;
use Vitaliq\Services\ValidationFailed;
use Vitaliq\Services\WorkflowException;

final class AdminUserController extends Controller
{
    public function index(Request $request): Response
    {
        $role = $request->query('role');
        $search = mb_substr($request->query('q'), 0, 80);
        return $this->view('admin/users', [
            'title' => 'Users',
            'list' => $this->app->service(UserAdminService::class)->list($role, $search, $this->page($request)),
            'role' => $role,
            'search' => $search,
            'page' => $this->page($request),
            'inviteLink' => $this->app->session()->getFlash('invite_link'),
        ], 200, 'app');
    }

    /**
     * @param array<string,string> $errors
     * @param array<string,mixed> $old
     */
    private function form(array $errors = [], array $old = [], int $status = 200): Response
    {
        return $this->view('admin/user-invite', [
            'title' => 'Invite a staff member',
            'phlebotomists' => $this->app->service(PhlebotomistService::class)->unlinkedOptions(),
            'errors' => $errors,
            'old' => $old,
        ], $status, 'app');
    }

    public function inviteForm(Request $request): Response
    {
        return $this->form();
    }

    public function invite(Request $request): Response
    {
        try {
            $result = $this->app->service(UserAdminService::class)->inviteStaff($request->body);
        } catch (ValidationFailed $e) {
            return $this->form($e->errors, $request->body, 422);
        }
        $this->showLinkOnce($result['token']);
        $this->flash('success', 'Account created. Share the one-time link below with the new staff member through a trusted channel.');
        return $this->redirect('/admin/users');
    }

    public function reinvite(Request $request, string $id): Response
    {
        try {
            $token = $this->app->service(UserAdminService::class)->reissueInvite((int) $id);
            $this->showLinkOnce($token);
            $this->flash('success', 'A new one-time link was created; earlier links no longer work.');
        } catch (WorkflowException $e) {
            $this->flash('error', $e->getMessage());
        }
        return $this->redirect('/admin/users');
    }

    /** The raw link is kept only in the session flash, shown once, never stored. */
    private function showLinkOnce(string $token): void
    {
        $this->app->session()->flash('invite_link', rtrim((string) $this->app->config['url'], '/') . '/reset-password/' . $token);
    }

    public function toggle(Request $request, string $id): Response
    {
        $service = $this->app->service(UserAdminService::class);
        $user = $service->find((int) $id);
        if ($user === null) {
            $this->notFound();
        }
        try {
            $service->setActive((int) $id, (int) $user['is_active'] !== 1);
            $this->flash('success', (int) $user['is_active'] === 1 ? 'Account deactivated and signed out.' : 'Account activated.');
        } catch (WorkflowException $e) {
            $this->flash('error', $e->getMessage());
        }
        return $this->redirect('/admin/users');
    }

    public function audit(Request $request): Response
    {
        $action = preg_replace('/[^a-z_.]/', '', $request->query('action')) ?? '';
        return $this->view('admin/audit', [
            'title' => 'Audit log',
            'list' => $this->app->service(AuditLog::class)->recent($this->page($request), 40, $action),
            'action' => $action,
            'page' => $this->page($request),
        ], 200, 'app');
    }
}
