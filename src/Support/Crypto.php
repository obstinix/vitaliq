<?php

declare(strict_types=1);

namespace Vitaliq\Support;

use RuntimeException;

/**
 * Authenticated symmetric encryption for data at rest (feature 48).
 * XSalsa20-Poly1305 (libsodium secretbox). Each purpose gets its own key
 * derived from APP_KEY with HKDF, so a key for one purpose never decrypts
 * another. Output: "v1:" . base64(nonce . ciphertext).
 */
final class Crypto
{
    private const PREFIX = 'v1:';

    public function __construct(private readonly string $appKey)
    {
        if (strlen($appKey) < 32) {
            throw new RuntimeException('APP_KEY must be at least 32 characters.');
        }
    }

    private function key(string $purpose): string
    {
        return hash_hkdf('sha256', $this->appKey, SODIUM_CRYPTO_SECRETBOX_KEYBYTES, 'vitaliq:' . $purpose);
    }

    public function encrypt(string $plaintext, string $purpose): string
    {
        $nonce = random_bytes(SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
        return self::PREFIX . base64_encode($nonce . sodium_crypto_secretbox($plaintext, $nonce, $this->key($purpose)));
    }

    public function decrypt(string $payload, string $purpose): string
    {
        if (!str_starts_with($payload, self::PREFIX)) {
            throw new RuntimeException('Unknown ciphertext format.');
        }
        $raw = base64_decode(substr($payload, strlen(self::PREFIX)), true);
        if ($raw === false || strlen($raw) < SODIUM_CRYPTO_SECRETBOX_NONCEBYTES + SODIUM_CRYPTO_SECRETBOX_MACBYTES) {
            throw new RuntimeException('Ciphertext is damaged.');
        }
        $plain = sodium_crypto_secretbox_open(
            substr($raw, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES),
            substr($raw, 0, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES),
            $this->key($purpose)
        );
        if ($plain === false) {
            throw new RuntimeException('Ciphertext failed authentication.');
        }
        return $plain;
    }

    /** Binary file variant: raw nonce . ciphertext, no base64. */
    public function encryptBytes(string $plaintext, string $purpose): string
    {
        $nonce = random_bytes(SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
        return $nonce . sodium_crypto_secretbox($plaintext, $nonce, $this->key($purpose));
    }

    public function decryptBytes(string $blob, string $purpose): string
    {
        $plain = sodium_crypto_secretbox_open(
            substr($blob, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES),
            substr($blob, 0, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES),
            $this->key($purpose)
        );
        if ($plain === false) {
            throw new RuntimeException('Stored file failed authentication.');
        }
        return $plain;
    }
}
