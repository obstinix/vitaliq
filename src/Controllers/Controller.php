<?php

declare(strict_types=1);

namespace Vitaliq\Controllers;

use Vitaliq\Core\App;
use Vitaliq\Core\HttpException;
use Vitaliq\Core\Request;
use Vitaliq\Core\Response;
use Vitaliq\Services\NotificationService;
use Vitaliq\Validation\Rules;

abstract class Controller
{
    public function __construct(protected readonly App $app)
    {
    }

    /** @param array<string,mixed> $data */
    protected function view(string $template, array $data = [], int $status = 200, string $layout = 'public'): Response
    {
        $user = $this->app->auth()->user();
        $data += [
            'currentUser' => $user,
            'unreadCount' => $user !== null ? $this->app->service(NotificationService::class)->unreadCount((int) $user['id']) : 0,
            'flash' => [
                'success' => $this->app->session()->getFlash('success'),
                'notice' => $this->app->session()->getFlash('notice'),
                'error' => $this->app->session()->getFlash('error'),
            ],
            'errors' => [],
            'old' => [],
            'currentPath' => $this->app->request()->path,
            'pollSeconds' => (int) $this->app->config['notification_poll_seconds'],
            'clientRules' => Rules::forClient(),
        ];
        return Response::html($this->app->view->render($template, $data, $layout), $status);
    }

    protected function redirect(string $path): Response
    {
        return Response::redirect(url($path));
    }

    protected function back(Request $request, string $fallback): Response
    {
        return $this->redirect($fallback);
    }

    protected function flash(string $type, string $message): void
    {
        $this->app->session()->flash($type, $message);
    }

    /** @return array{id:int,role:string} */
    protected function actor(): array
    {
        $user = $this->app->auth()->user();
        if ($user === null) {
            throw new HttpException(403);
        }
        return ['id' => (int) $user['id'], 'role' => (string) $user['role']];
    }

    protected function userId(): int
    {
        return $this->actor()['id'];
    }

    protected function page(Request $request): int
    {
        return max(1, min(10000, (int) $request->query('page', '1')));
    }

    /** Issue a one-time submission token for a form (duplicate-submit guard). */
    protected function formToken(string $form): string
    {
        $tokens = (array) $this->app->session()->get('form_tokens', []);
        $token = bin2hex(random_bytes(32));
        $tokens[$token] = $form;
        if (count($tokens) > 20) {
            $tokens = array_slice($tokens, -20, null, true);
        }
        $this->app->session()->set('form_tokens', $tokens);
        return $token;
    }

    protected function consumeFormToken(string $form, string $token): bool
    {
        $tokens = (array) $this->app->session()->get('form_tokens', []);
        if (($tokens[$token] ?? null) !== $form) {
            return false;
        }
        unset($tokens[$token]);
        $this->app->session()->set('form_tokens', $tokens);
        return true;
    }

    protected function notFound(): never
    {
        throw new HttpException(404);
    }
}
