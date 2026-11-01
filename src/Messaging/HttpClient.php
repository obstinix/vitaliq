<?php

declare(strict_types=1);

namespace Vitaliq\Messaging;

use RuntimeException;

/**
 * Minimal HTTPS client for provider APIs (SMS, WhatsApp, AI). TLS
 * certificates are always verified. A transport callable can replace the
 * network in tests: fn(string $method, string $url, array $headers, string $body): array{status:int,body:string}.
 */
final class HttpClient
{
    /** @var (callable(string,string,array<string,string>,string):array{status:int,body:string})|null */
    private static $fake = null;

    /** @param (callable(string,string,array<string,string>,string):array{status:int,body:string})|null $transport */
    public static function fake(?callable $transport): void
    {
        self::$fake = $transport;
    }

    /**
     * @param array<string,string> $headers
     * @return array{status:int,body:string}
     */
    public static function request(string $method, string $url, array $headers, string $body, int $timeout = 15): array
    {
        if (self::$fake !== null) {
            return (self::$fake)($method, $url, $headers, $body);
        }
        if (!str_starts_with($url, 'https://')) {
            throw new RuntimeException('Provider URLs must use HTTPS.');
        }
        if (!function_exists('curl_init')) {
            throw new RuntimeException('The curl extension is required for outside providers.');
        }
        $ch = curl_init($url);
        $lines = [];
        foreach ($headers as $k => $v) {
            $lines[] = $k . ': ' . str_replace(["\r", "\n"], '', $v);
        }
        curl_setopt_array($ch, [
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_POSTFIELDS => $body,
            CURLOPT_HTTPHEADER => $lines,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => $timeout,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_PROTOCOLS => CURLPROTO_HTTPS,
        ]);
        $response = curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $error = curl_error($ch);
        curl_close($ch);
        if ($response === false) {
            throw new RuntimeException('Provider request failed: ' . $error);
        }
        return ['status' => $status, 'body' => (string) $response];
    }
}
