<?php

declare(strict_types=1);

namespace Vitaliq\Core;

/**
 * Builds the application configuration array from the environment.
 */
final class Config
{
    /** @return array<string,mixed> */
    public static function fromEnv(): array
    {
        $url = rtrim((string) Env::get('APP_URL', 'http://127.0.0.1:8000'), '/');
        $basePath = rtrim((string) (parse_url($url, PHP_URL_PATH) ?? ''), '/');

        return [
            'name' => (string) Env::get('APP_NAME', 'Vitaliq'),
            'env' => (string) Env::get('APP_ENV', 'production'),
            'debug' => Env::bool('APP_DEBUG'),
            'url' => $url,
            'base_path' => $basePath,
            'key' => (string) Env::get('APP_KEY', ''),
            'timezone' => (string) Env::get('APP_TIMEZONE', 'Asia/Kolkata'),
            'db' => [
                'host' => (string) Env::get('DB_HOST', '127.0.0.1'),
                'port' => (int) Env::get('DB_PORT', 3306),
                'name' => (string) Env::get('DB_NAME', 'vitaliq'),
                'user' => (string) Env::get('DB_USER', 'vitaliq'),
                'pass' => (string) Env::get('DB_PASS', ''),
            ],
            'session' => [
                'name' => (string) Env::get('SESSION_NAME', 'vitaliq_session'),
                'idle_minutes' => max(5, (int) Env::get('SESSION_IDLE_MINUTES', 30)),
                'secure' => (string) Env::get('SESSION_SECURE_COOKIE', 'auto'),
            ],
            'mail' => [
                'driver' => (string) Env::get('MAIL_DRIVER', 'none'),
                'from' => (string) Env::get('MAIL_FROM', 'no-reply@vitaliq.example'),
            ],
            'notification_poll_seconds' => max(10, (int) Env::get('NOTIFICATION_POLL_SECONDS', 30)),
            'teleconsult' => [
                // Jitsi-compatible server; rooms are created on first join.
                'base_url' => rtrim((string) Env::get('TELECONSULT_BASE_URL', 'https://meet.jit.si'), '/'),
            ],
        ];
    }
}
