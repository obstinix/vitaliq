<?php

declare(strict_types=1);

namespace Vitaliq\Core;

/**
 * Session bound to PHP's native session handler with hardened cookie settings.
 */
final class NativeSession extends Session
{
    /** @param array{name:string,idle_minutes:int,secure:string} $config */
    public static function start(array $config, Request $request, string $basePath): self
    {
        $secure = match ($config['secure']) {
            'true' => true,
            'false' => false,
            default => $request->isSecure(),
        };
        ini_set('session.use_strict_mode', '1');
        ini_set('session.use_only_cookies', '1');
        ini_set('session.use_trans_sid', '0');
        ini_set('session.gc_maxlifetime', (string) ($config['idle_minutes'] * 60 * 2));
        session_name($config['name']);
        session_set_cookie_params([
            'lifetime' => 0,
            'path' => $basePath === '' ? '/' : $basePath . '/',
            'secure' => $secure,
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }
        return new self($_SESSION);
    }

    public function regenerate(): void
    {
        parent::regenerate();
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_regenerate_id(true);
        }
    }
}
