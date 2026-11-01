<?php

declare(strict_types=1);

namespace Vitaliq\Services;

use finfo;
use RuntimeException;
use Vitaliq\Core\App;
use Vitaliq\Support\Crypto;

/**
 * Encrypted document vault (feature 7). Uploads are checked by content
 * (not by name or browser-supplied type), encrypted with a vault-specific
 * key and written under storage/vault with a random name. Downloads are
 * decrypted only after the caller's ownership check and verified against
 * the stored SHA-256 of the plaintext.
 */
final class DocumentVaultService
{
    public const MAX_BYTES = 5 * 1024 * 1024;
    public const MAX_PER_PATIENT = 50;
    public const TYPES = ['application/pdf' => 'pdf', 'image/png' => 'png', 'image/jpeg' => 'jpg'];
    public const CATEGORIES = [
        'lab_report' => 'Lab report', 'prescription' => 'Prescription', 'imaging' => 'Imaging',
        'discharge_summary' => 'Discharge summary', 'insurance' => 'Insurance', 'vaccination' => 'Vaccination record', 'other' => 'Other',
    ];
    private const PURPOSE = 'vault';

    private string $dir;

    public function __construct(private readonly App $app)
    {
        $this->dir = (string) ($app->config['vault_dir'] ?? VQ_ROOT . '/storage/vault');
    }

    private function crypto(): Crypto
    {
        return new Crypto((string) $this->app->config['key']);
    }

    /**
     * @param array{name?:string,tmp_name?:string,size?:int,error?:int}|null $file an entry from $_FILES
     * @param array<string,mixed> $input
     */
    public function store(int $patientId, int $userId, ?array $file, array $input): int
    {
        $errors = [];
        $title = trim((string) preg_replace('/[^\P{Cc}]/u', '', (string) ($input['title'] ?? '')));
        if (mb_strlen($title) < 2 || mb_strlen($title) > 120) {
            $errors['title'] = 'Give the document a title of 2 to 120 characters.';
        }
        $category = (string) ($input['category'] ?? '');
        if (!isset(self::CATEGORIES[$category])) {
            $errors['category'] = 'Choose a category.';
        }
        $code = $file === null ? UPLOAD_ERR_NO_FILE : (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE);
        if ($code === UPLOAD_ERR_NO_FILE) {
            $errors['document'] = 'Choose a file to upload.';
        } elseif ($code === UPLOAD_ERR_INI_SIZE || $code === UPLOAD_ERR_FORM_SIZE || (int) ($file['size'] ?? 0) > self::MAX_BYTES) {
            $errors['document'] = 'The file is larger than 5 MB.';
        } elseif ($code !== UPLOAD_ERR_OK || !is_file((string) ($file['tmp_name'] ?? ''))) {
            $errors['document'] = 'The upload did not complete. Please try again.';
        }
        $count = (int) $this->app->db->value('SELECT COUNT(*) FROM patient_documents WHERE patient_id = :p AND deleted_at IS NULL', ['p' => $patientId]);
        if ($count >= self::MAX_PER_PATIENT) {
            $errors['document'] = 'This profile already holds ' . self::MAX_PER_PATIENT . ' documents. Delete one first.';
        }
        if ($errors !== []) {
            throw new ValidationFailed($errors);
        }

        $bytes = (string) file_get_contents((string) $file['tmp_name']);
        $mime = (new finfo(FILEINFO_MIME_TYPE))->buffer($bytes) ?: '';
        if (!isset(self::TYPES[$mime]) || $bytes === '' || strlen($bytes) > self::MAX_BYTES) {
            throw new ValidationFailed(['document' => 'Only PDF, PNG and JPEG files up to 5 MB can be stored.']);
        }
        $original = mb_substr(basename(str_replace('\\', '/', (string) ($file['name'] ?? 'document'))), 0, 160);
        $original = (string) preg_replace('/[^\p{L}\p{N} ._()-]/u', '_', $original);

        if (!is_dir($this->dir) && !mkdir($this->dir, 0700, true) && !is_dir($this->dir)) {
            throw new RuntimeException('Vault directory cannot be created.');
        }
        $storage = bin2hex(random_bytes(20));
        if (file_put_contents($this->dir . '/' . $storage . '.bin', $this->crypto()->encryptBytes($bytes, self::PURPOSE . ':' . $storage), LOCK_EX) === false) {
            throw new RuntimeException('Vault write failed.');
        }
        try {
            $id = $this->app->db->insert(
                'INSERT INTO patient_documents (patient_id, uploaded_by_user_id, title, category, original_name, mime_type, size_bytes, storage_name, sha256, created_at)
                 VALUES (:p, :u, :t, :c, :o, :m, :s, :n, :h, :at)',
                ['p' => $patientId, 'u' => $userId, 't' => $title, 'c' => $category, 'o' => $original, 'm' => $mime,
                 's' => strlen($bytes), 'n' => $storage, 'h' => hash('sha256', $bytes), 'at' => $this->app->clock->nowSql()]
            );
        } catch (\Throwable $e) {
            @unlink($this->dir . '/' . $storage . '.bin');
            throw $e;
        }
        $this->app->service(AuditLog::class)->record('document.uploaded', 'document', $id, ['category' => $category]);
        return $id;
    }

    /** @return list<array<string,mixed>> */
    public function list(int $patientId): array
    {
        return $this->app->db->all(
            'SELECT id, title, category, original_name, mime_type, size_bytes, created_at FROM patient_documents
              WHERE patient_id = :p AND deleted_at IS NULL ORDER BY created_at DESC, id DESC',
            ['p' => $patientId]
        );
    }

    /** @return array{meta:array<string,mixed>,bytes:string}|null */
    public function read(int $patientId, int $id): ?array
    {
        $meta = $this->app->db->one('SELECT * FROM patient_documents WHERE id = :id AND patient_id = :p AND deleted_at IS NULL', ['id' => $id, 'p' => $patientId]);
        if ($meta === null) {
            return null;
        }
        $path = $this->dir . '/' . $meta['storage_name'] . '.bin';
        if (!is_file($path)) {
            throw new RuntimeException('Vault file is missing.');
        }
        $bytes = $this->crypto()->decryptBytes((string) file_get_contents($path), self::PURPOSE . ':' . $meta['storage_name']);
        if (!hash_equals((string) $meta['sha256'], hash('sha256', $bytes))) {
            throw new RuntimeException('Vault file failed its integrity check.');
        }
        $this->app->service(AuditLog::class)->record('document.downloaded', 'document', $id);
        return ['meta' => $meta, 'bytes' => $bytes];
    }

    /** Soft-delete the record and destroy the encrypted file. */
    public function delete(int $patientId, int $id): bool
    {
        $meta = $this->app->db->one('SELECT storage_name FROM patient_documents WHERE id = :id AND patient_id = :p AND deleted_at IS NULL', ['id' => $id, 'p' => $patientId]);
        if ($meta === null) {
            return false;
        }
        $this->app->db->execute('UPDATE patient_documents SET deleted_at = :t WHERE id = :id', ['t' => $this->app->clock->nowSql(), 'id' => $id]);
        @unlink($this->dir . '/' . $meta['storage_name'] . '.bin');
        $this->app->service(AuditLog::class)->record('document.deleted', 'document', $id);
        return true;
    }

    public function directory(): string
    {
        return $this->dir;
    }
}
