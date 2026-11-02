<?php

declare(strict_types=1);

namespace Vitaliq\Controllers;

use Vitaliq\Core\Request;
use Vitaliq\Core\Response;
use Vitaliq\Services\AlertEngine;
use Vitaliq\Services\ApiKeyService;
use Vitaliq\Services\ReportVerificationService;
use Vitaliq\Services\ValidationFailed;
use Vitaliq\Services\WorkflowException;

/**
 * Public report verification (56), the offline portal files (58), the
 * developer page, and the admin screens for alert rules (55) and API keys (60).
 */
final class PlatformController extends Controller
{
    public function verifyForm(Request $request): Response
    {
        return $this->view('public/verify', ['title' => 'Verify a report', 'result' => null, 'code' => '']);
    }

    public function verifyPost(Request $request): Response
    {
        $code = strtoupper((string) preg_replace('/[^A-Za-z0-9]/', '', $request->input('code')));
        return $code === '' ? $this->redirect('/verify') : $this->redirect('/verify/' . $code);
    }

    public function verify(Request $request, string $code): Response
    {
        if (!$this->app->rateLimiter()->hit('verify:' . $this->app->ipHash(), 30, 600)) {
            return $this->view('public/verify', ['title' => 'Verify a report', 'result' => ['valid' => false, 'reason' => 'Too many checks. Please wait a few minutes.'], 'code' => $code], 429);
        }
        $r = $this->app->service(ReportVerificationService::class)->check($code);
        return $this->view('public/verify', ['title' => 'Verify a report', 'result' => $r ?? ['valid' => false, 'reason' => 'No report has this code. Check each character.'], 'code' => $code], $r === null ? 404 : 200)
            ->withHeader('X-Robots-Tag', 'noindex, nofollow');
    }

    public function developers(Request $request): Response
    {
        return $this->view('public/developers', ['title' => 'Developers', 'scopes' => ApiKeyService::SCOPES]);
    }

    /* ------------------------------------------------------------- offline */

    public function serviceWorker(Request $request): Response
    {
        $js = (string) file_get_contents(VQ_ROOT . '/public/assets/js/sw.js');
        $js = str_replace('__VERSION__', (string) filemtime(VQ_ROOT . '/public/assets/js/sw.js'), $js);
        return new Response($js, 200, ['Content-Type' => 'text/javascript; charset=UTF-8', 'Cache-Control' => 'no-cache', 'Service-Worker-Allowed' => url('/')]);
    }

    public function manifest(Request $request): Response
    {
        return new Response((string) json_encode([
            'name' => 'Vitaliq', 'short_name' => 'Vitaliq', 'start_url' => url('/patient'), 'scope' => url('/'), 'display' => 'standalone',
            'background_color' => '#eef2f5', 'theme_color' => '#13243a', 'lang' => 'en-IN',
            'icons' => [['src' => url('/assets/img/favicon.svg'), 'sizes' => 'any', 'type' => 'image/svg+xml', 'purpose' => 'any']],
        ], JSON_UNESCAPED_SLASHES), 200, ['Content-Type' => 'application/manifest+json']);
    }

    public function offline(Request $request): Response
    {
        return $this->view('public/offline', ['title' => 'You are offline']);
    }

    /* ---------------------------------------------------------- alert rules */

    /**
     * @param array<string,string> $errors
     * @param array<string,mixed> $old
     */
    public function alertRules(Request $request, array $errors = [], array $old = [], int $status = 200): Response
    {
        $engine = $this->app->service(AlertEngine::class);
        return $this->view('admin/alert-rules', [
            'title' => 'Alert rules', 'rules' => $engine->rules(), 'hits' => $engine->recentHits(),
            'events' => AlertEngine::EVENTS, 'roles' => AlertEngine::ROLES,
            'staff' => $this->app->db->all("SELECT id, full_name, role FROM users WHERE role <> 'patient' AND is_active = 1 ORDER BY full_name"),
            'errors' => $errors, 'old' => $old,
        ], $status, 'app');
    }

    public function createAlertRule(Request $request): Response
    {
        try {
            $this->app->service(AlertEngine::class)->create($request->body, $this->userId());
        } catch (ValidationFailed $e) {
            return $this->alertRules($request, $e->errors, $request->body, 422);
        }
        $this->flash('success', 'Rule added.');
        return $this->redirect('/admin/alert-rules');
    }

    public function toggleAlertRule(Request $request, string $id): Response
    {
        try {
            $this->app->service(AlertEngine::class)->toggle((int) $id, $this->userId());
        } catch (WorkflowException $e) {
            $this->notFound();
        }
        return $this->redirect('/admin/alert-rules');
    }

    /* ------------------------------------------------------------- API keys */

    /**
     * @param array<string,string> $errors
     * @param array<string,mixed> $old
     * @param array{id:int,key:string}|null $issued
     */
    public function apiKeys(Request $request, array $errors = [], array $old = [], ?array $issued = null, int $status = 200): Response
    {
        return $this->view('admin/api-keys', [
            'title' => 'API keys', 'keys' => $this->app->service(ApiKeyService::class)->list(), 'scopes' => ApiKeyService::SCOPES,
            'issued' => $issued, 'errors' => $errors, 'old' => $old,
        ], $status, 'app');
    }

    public function issueApiKey(Request $request): Response
    {
        try {
            $issued = $this->app->service(ApiKeyService::class)->issue($request->body, $this->userId());
        } catch (ValidationFailed $e) {
            return $this->apiKeys($request, $e->errors, $request->body, null, 422);
        }
        // Shown once; only the hash is kept.
        return $this->apiKeys($request, [], [], $issued);
    }

    public function revokeApiKey(Request $request, string $id): Response
    {
        try {
            $this->app->service(ApiKeyService::class)->revoke((int) $id, $this->userId());
            $this->flash('success', 'Key revoked. Calls using it now fail.');
        } catch (WorkflowException $e) {
            $this->flash('error', $e->getMessage());
        }
        return $this->redirect('/admin/api-keys');
    }
}
