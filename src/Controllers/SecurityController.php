<?php

declare(strict_types=1);

namespace Vitaliq\Controllers;

use Vitaliq\Auth\Totp;
use Vitaliq\Core\Request;
use Vitaliq\Core\Response;
use Vitaliq\Services\AccountSecurityService;
use Vitaliq\Services\HealthIdService;
use Vitaliq\Services\RetentionService;
use Vitaliq\Services\ValidationFailed;
use Vitaliq\Services\WorkflowException;

/** Account security page (features 46, 47, 49, 50) and the admin retention screen. */
final class SecurityController extends Controller
{
    private function sec(): AccountSecurityService
    {
        return $this->app->service(AccountSecurityService::class);
    }

    /**
     * @param array<string,string> $errors
     * @param list<string>|null $recovery
     */
    public function index(Request $request, array $errors = [], ?array $recovery = null, int $status = 200): Response
    {
        $uid = $this->userId();
        $user = $this->app->auth()->user() ?? [];
        $pending = $this->sec()->pendingSecret($uid);
        $requested = $this->app->db->one("SELECT * FROM deletion_requests WHERE user_id = :u ORDER BY id DESC LIMIT 1", ['u' => $uid]);
        return $this->view('account/security', [
            'title' => t('Security'),
            'enabled' => $this->sec()->twoFactorEnabled($uid),
            'pending' => $pending,
            'qr' => $pending !== null ? HealthIdService::qrSvg(Totp::uri($pending, (string) $user['email'])) : null,
            'recovery' => $recovery,
            'codesLeft' => $this->sec()->recoveryCodesLeft($uid),
            'sessions' => $this->sec()->sessions($uid, (string) $this->app->session()->get('sk', '')),
            'deletion' => $requested,
            'errors' => $errors,
        ], $status, 'app');
    }

    public function startTwoFactor(Request $request): Response
    {
        try {
            $this->sec()->beginEnrolment($this->userId());
        } catch (WorkflowException $e) {
            $this->flash('error', $e->getMessage());
        }
        return $this->redirect('/account/security#two-step');
    }

    public function confirmTwoFactor(Request $request): Response
    {
        try {
            $codes = $this->sec()->confirmEnrolment($this->userId(), $request->input('code'));
        } catch (ValidationFailed $e) {
            return $this->index($request, $e->errors, null, 422);
        } catch (WorkflowException $e) {
            $this->flash('error', $e->getMessage());
            return $this->redirect('/account/security');
        }
        return $this->index($request, [], $codes);
    }

    public function disableTwoFactor(Request $request): Response
    {
        try {
            $this->sec()->disable($this->userId(), (string) ($request->body['password'] ?? ''));
            $this->flash('success', 'Two-step sign-in is off.');
        } catch (ValidationFailed $e) {
            return $this->index($request, $e->errors, null, 422);
        }
        return $this->redirect('/account/security');
    }

    public function revokeSession(Request $request, string $id): Response
    {
        if (!$this->sec()->revoke($this->userId(), (int) $id)) {
            $this->notFound();
        }
        $this->flash('success', 'That session has been signed out.');
        return $this->redirect('/account/security');
    }

    public function revokeOthers(Request $request): Response
    {
        $n = $this->sec()->revokeOthers($this->userId(), (string) $this->app->session()->get('sk', ''));
        $this->flash('success', $n === 0 ? 'No other sessions were open.' : "{$n} other " . ($n === 1 ? 'session was' : 'sessions were') . ' signed out.');
        return $this->redirect('/account/security');
    }

    public function requestDeletion(Request $request): Response
    {
        if ($request->input('confirm') !== '1') {
            return $this->index($request, ['confirm' => 'Tick the box to confirm you want your account deleted.'], null, 422);
        }
        try {
            $this->app->service(RetentionService::class)->request($this->userId(), $request->input('reason'));
            $this->flash('success', 'Your request has been sent. An administrator will complete it and you will be signed out when it is done.');
        } catch (WorkflowException $e) {
            $this->flash('error', $e->getMessage());
        }
        return $this->redirect('/account/security');
    }

    /* ----------------------------------------------------- admin retention */

    /** @param array<string,string> $errors */
    public function retention(Request $request, array $errors = [], int $status = 200): Response
    {
        $svc = $this->app->service(RetentionService::class);
        return $this->view('admin/retention', [
            'title' => 'Data retention', 'policies' => $svc->policies(), 'labels' => RetentionService::POLICIES,
            'requests' => $svc->requests(), 'errors' => $errors,
        ], $status, 'app');
    }

    public function savePolicies(Request $request): Response
    {
        try {
            $this->app->service(RetentionService::class)->savePolicies($request->body, $this->userId());
        } catch (ValidationFailed $e) {
            return $this->retention($request, $e->errors, 422);
        }
        $this->flash('success', 'Retention periods saved.');
        return $this->redirect('/admin/retention');
    }

    public function purge(Request $request): Response
    {
        $r = $this->app->service(RetentionService::class)->purge($this->userId());
        $this->flash('success', 'Removed: ' . implode(', ', array_map(static fn ($k, $v) => "{$v} " . str_replace('_', ' ', $k), array_keys($r), $r)) . '.');
        return $this->redirect('/admin/retention');
    }

    public function decide(Request $request, string $id): Response
    {
        $svc = $this->app->service(RetentionService::class);
        try {
            if ($request->input('decision') === 'complete') {
                $done = $svc->complete((int) $id, $this->userId());
                $this->flash('success', 'Account deleted: ' . $done['documents'] . ' documents destroyed, ' . $done['profiles'] . ' profiles detached.');
            } else {
                $svc->decline((int) $id, $request->input('note'), $this->userId());
                $this->flash('success', 'Request declined and the person told why.');
            }
        } catch (WorkflowException $e) {
            $this->flash('error', $e->getMessage());
        } catch (ValidationFailed $e) {
            $this->flash('error', implode(' ', $e->errors));
        }
        return $this->redirect('/admin/retention');
    }
}
