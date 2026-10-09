<?php

declare(strict_types=1);

namespace Vitaliq\Tests;

use Vitaliq\Core\Request;
use Vitaliq\Core\Response;
use Vitaliq\Core\Session;

/**
 * Drives the real kernel in-process, like a browser with one cookie jar:
 * the session array persists across requests within a test.
 */
abstract class HttpTestCase extends DatabaseTestCase
{
    /** @var array<string,mixed> */
    protected array $jar = [];

    protected function newBrowser(): void
    {
        $this->jar = [];
    }

    protected function request(string $method, string $path, array $query = [], array $body = [], array $headers = []): Response
    {
        $session = new Session($this->jar);
        $request = new Request($method, Request::normalisePath($path), $query, $body, ['REMOTE_ADDR' => '127.0.0.1'], $headers);
        return $this->app->handle($request, $session);
    }

    protected function get(string $path, array $query = [], array $headers = []): Response
    {
        return $this->request('GET', $path, $query, [], $headers);
    }

    /** POST with the session's CSRF token unless $withToken is false. */
    protected function post(string $path, array $body = [], bool $withToken = true, array $headers = []): Response
    {
        if ($withToken) {
            if (!isset($this->jar['_csrf'])) {
                $this->get('/login');
            }
            $body['_token'] ??= $this->jar['_csrf'];
        }
        return $this->request('POST', $path, [], $body, $headers);
    }

    protected function signIn(string $email, string $password = 'correct horse 42'): void
    {
        $this->get('/login');
        $response = $this->post('/login', ['email' => $email, 'password' => $password]);
        $this->assertSame(303, $response->status, 'Sign-in failed: ' . strip_tags(substr($response->body, 0, 300)));
    }

    protected function hiddenField(Response $response, string $name): string
    {
        $this->assertSame(1, preg_match('/name="' . preg_quote($name, '/') . '" value="([^"]*)"/', $response->body, $m), "Hidden field {$name} not found");
        return html_entity_decode($m[1], ENT_QUOTES | ENT_HTML5);
    }

    protected function assertRedirectsTo(string $path, Response $response): void
    {
        $this->assertContains($response->status, [302, 303], 'Expected a redirect, got ' . $response->status);
        $this->assertSame($path, parse_url((string) $response->header('Location'), PHP_URL_PATH));
    }
}
