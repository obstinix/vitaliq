<?php

declare(strict_types=1);

namespace Vitaliq\Core;

/**
 * Synchroniser-token CSRF protection. One token per session, rotated at
 * login/logout. Accepted from the `_token` form field or the
 * `X-CSRF-Token` header (used by fetch requests).
 */
final class Csrf
{
    public function __construct(private readonly Session $session)
    {
    }

    public function token(): string
    {
        $token = $this->session->get('_csrf');
        if (!is_string($token) || strlen($token) !== 64) {
            $token = bin2hex(random_bytes(32));
            $this->session->set('_csrf', $token);
        }
        return $token;
    }

    public function rotate(): void
    {
        $this->session->set('_csrf', bin2hex(random_bytes(32)));
    }

    public function verify(Request $request): bool
    {
        $sent = $request->body['_token'] ?? $request->header('x-csrf-token');
        $expected = $this->session->get('_csrf');
        return is_string($sent) && is_string($expected) && $expected !== ''
            && hash_equals($expected, $sent);
    }
}
