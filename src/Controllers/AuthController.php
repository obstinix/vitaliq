<?php

declare(strict_types=1);

namespace Vitaliq\Controllers;

use Vitaliq\Auth\Passwords;
use Vitaliq\Auth\PasswordTokens;
use Vitaliq\Core\Request;
use Vitaliq\Core\Response;
use Vitaliq\Services\PatientService;
use Vitaliq\Services\ValidationFailed;
use Vitaliq\Validation\Rules;

final class AuthController extends Controller
{
    public const RESET_MAX_PER_EMAIL = 3;
    public const RESET_MAX_PER_IP = 10;
    public const REGISTER_MAX_PER_IP = 10;

    public function registerForm(Request $request): Response
    {
        return $this->view('auth/register', [
            'title' => 'Create your account',
            'states' => $this->app->service(PatientService::class)->states(),
        ]);
    }

    public function register(Request $request): Response
    {
        $input = $request->body;
        // Every attempt counts, so the form cannot be scripted to create
        // accounts or to probe which emails and mobile numbers are registered.
        if (!$this->app->rateLimiter()->hit('register-ip:' . $this->app->ipHash(), self::REGISTER_MAX_PER_IP, 3600)) {
            unset($input['password'], $input['password_confirmation']);
            return $this->view('auth/register', [
                'title' => 'Create your account',
                'states' => $this->app->service(PatientService::class)->states(),
                'errors' => ['form' => 'Too many registration attempts from this connection. Please try again in an hour.'],
                'old' => $input,
            ], 429);
        }
        try {
            $userId = $this->app->service(PatientService::class)->register($input);
        } catch (ValidationFailed $e) {
            unset($input['password'], $input['password_confirmation']);
            return $this->view('auth/register', [
                'title' => 'Create your account',
                'states' => $this->app->service(PatientService::class)->states(),
                'errors' => $e->errors,
                'old' => $input,
            ], 422);
        }
        $this->app->auth()->login($userId, 1);
        $this->flash('success', 'Welcome to Vitaliq. Your account has been created.');
        return $this->redirect('/patient');
    }

    public function loginForm(Request $request): Response
    {
        return $this->view('auth/login', ['title' => 'Sign in']);
    }

    public function login(Request $request): Response
    {
        $email = $request->input('email');
        $result = $this->app->auth()->attempt($email, (string) ($request->body['password'] ?? ''));
        if (!$result['ok']) {
            return $this->view('auth/login', [
                'title' => 'Sign in',
                'errors' => ['form' => (string) $result['error']],
                'old' => ['email' => $email],
            ], 422);
        }
        $intended = (string) $this->app->session()->get('intended', '');
        $this->app->session()->remove('intended');
        $home = $this->app->auth()->homePath();
        // Only follow an internal path that the role's area owns, never an absolute URL.
        $target = preg_match('#^/[A-Za-z0-9/_-]*$#', $intended) && str_starts_with($intended, $home) ? $intended : $home;
        return $this->redirect($target);
    }

    public function logout(Request $request): Response
    {
        $this->app->auth()->logout();
        $this->flash('success', 'You have been signed out.');
        return $this->redirect('/login');
    }

    public function forgotForm(Request $request): Response
    {
        return $this->view('auth/forgot', ['title' => 'Reset your password']);
    }

    public function forgot(Request $request): Response
    {
        $raw = $request->input('email');
        $email = Rules::normalise('email', $raw);
        if (Rules::check('email', $email) !== null) {
            return $this->view('auth/forgot', [
                'title' => 'Reset your password',
                'errors' => ['email' => 'Enter the email address you registered with.'],
                'old' => ['email' => $raw],
            ], 422);
        }
        $limiter = $this->app->rateLimiter();
        $allowed = $limiter->hit('reset-ip:' . $this->app->ipHash(), self::RESET_MAX_PER_IP, 3600)
            && $limiter->hit('reset:' . $email, self::RESET_MAX_PER_EMAIL, 3600);

        if ($allowed) {
            $user = $this->app->db->one(
                'SELECT id, full_name FROM users WHERE email = :e AND is_active = 1 AND password_hash IS NOT NULL',
                ['e' => $email]
            );
            if ($user !== null) {
                $token = $this->app->service(PasswordTokens::class)->issue((int) $user['id'], 'reset');
                $link = rtrim((string) $this->app->config['url'], '/') . '/reset-password/' . $token;
                $this->app->mailer()->send($email, 'Reset your Vitaliq password',
                    "Hello {$user['full_name']},\n\nUse this link within " . PasswordTokens::RESET_TTL_MINUTES
                    . " minutes to choose a new password:\n{$link}\n\nIf you did not ask for this, ignore this message.");
            }
        }
        // Same response whether or not the account exists, and whether or not
        // the request was rate-limited, so the page cannot be used to probe.
        $this->flash('success', 'If an account exists for that address, a reset link has been prepared. It expires in '
            . PasswordTokens::RESET_TTL_MINUTES . ' minutes and can be used once.');
        return $this->redirect('/forgot-password');
    }

    public function resetForm(Request $request, string $token): Response
    {
        $record = $this->app->service(PasswordTokens::class)->find($token);
        return $this->view('auth/reset', [
            'title' => $record !== null && $record['purpose'] === 'invite' ? 'Set your password' : 'Choose a new password',
            'valid' => $record !== null,
            'record' => $record,
            'token' => $token,
        ], $record === null ? 410 : 200)->withHeader('Referrer-Policy', 'no-referrer');
    }

    public function reset(Request $request, string $token): Response
    {
        $tokens = $this->app->service(PasswordTokens::class);
        $record = $tokens->find($token);
        if ($record === null) {
            return $this->view('auth/reset', ['title' => 'Link expired', 'valid' => false, 'record' => null, 'token' => ''], 410)
                ->withHeader('Referrer-Policy', 'no-referrer');
        }
        $password = (string) ($request->body['password'] ?? '');
        $error = Passwords::policyError($password, $record['email']);
        $errors = [];
        if ($error !== null) {
            $errors['password'] = $error;
        } elseif (!hash_equals($password, (string) ($request->body['password_confirmation'] ?? ''))) {
            $errors['password_confirmation'] = 'The two passwords do not match.';
        }
        if ($errors !== [] || !$tokens->consume($token, $password)) {
            return $this->view('auth/reset', [
                'title' => 'Choose a new password',
                'valid' => $errors !== [],
                'record' => $record,
                'token' => $token,
                'errors' => $errors ?: ['form' => 'This link has already been used or has expired.'],
            ], 422)->withHeader('Referrer-Policy', 'no-referrer');
        }
        if ($this->app->auth()->check()) {
            $this->app->auth()->logout();
        }
        $this->flash('success', 'Your password has been set. Please sign in.');
        return $this->redirect('/login');
    }
}
