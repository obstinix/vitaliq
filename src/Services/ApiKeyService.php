<?php

declare(strict_types=1);

namespace Vitaliq\Services;

use Vitaliq\Core\App;

/**
 * API keys for the versioned public API and FHIR endpoints (features 57,
 * 60). A key looks like vq_<12-char prefix>_<secret>; only its SHA-256 is
 * stored. Keys carry scopes and can expire or be revoked.
 */
final class ApiKeyService
{
    public const SCOPES = [
        'surveillance.read' => 'Aggregate surveillance counts (no personal data)',
        'facilities.read' => 'Facility directory',
        'reports.verify' => 'Check report verification codes',
        'fhir.read' => 'FHIR R4 Patient, Observation and DiagnosticReport (personal health data)',
    ];
    public const PER_MINUTE = 120;

    public function __construct(private readonly App $app)
    {
    }

    /**
     * @param array<string,mixed> $input name, scopes[], days
     * @return array{id:int,key:string}
     */
    public function issue(array $input, int $actorId): array
    {
        $errors = [];
        $name = trim((string) ($input['name'] ?? ''));
        if (mb_strlen($name) < 3 || mb_strlen($name) > 80) {
            $errors['name'] = 'Name the key after who uses it (3 to 80 characters).';
        }
        $scopes = array_values(array_intersect(is_array($input['scopes'] ?? null) ? array_map('strval', $input['scopes']) : [], array_keys(self::SCOPES)));
        if ($scopes === []) {
            $errors['scopes'] = 'Choose at least one scope.';
        }
        $days = (int) ($input['days'] ?? 90);
        if (!in_array($days, [30, 90, 365], true)) {
            $errors['days'] = 'Choose 30, 90 or 365 days.';
        }
        if ($errors !== []) {
            throw new ValidationFailed($errors);
        }
        $prefix = substr(bin2hex(random_bytes(6)), 0, 12);
        $key = 'vq_' . $prefix . '_' . rtrim(strtr(base64_encode(random_bytes(30)), '+/', '-_'), '=');
        $id = $this->app->db->insert(
            'INSERT INTO api_keys (name, key_prefix, key_hash, scopes, created_by_user_id, created_at, expires_at) VALUES (:n, :p, :h, :s, :u, :t, :e)',
            ['n' => $name, 'p' => $prefix, 'h' => hash('sha256', $key), 's' => implode(' ', $scopes), 'u' => $actorId,
             't' => $this->app->clock->nowSql(), 'e' => $this->app->clock->now()->modify("+{$days} days")->format('Y-m-d H:i:s')]
        );
        $this->app->service(AuditLog::class)->record('api_key.issued', 'api_key', $id, ['scopes' => implode(' ', $scopes)], $actorId);
        return ['id' => $id, 'key' => $key];
    }

    public function revoke(int $id, int $actorId): void
    {
        if ($this->app->db->execute('UPDATE api_keys SET revoked_at = :t WHERE id = :id AND revoked_at IS NULL', ['t' => $this->app->clock->nowSql(), 'id' => $id]) !== 1) {
            throw new WorkflowException('Key not found or already revoked.');
        }
        $this->app->service(AuditLog::class)->record('api_key.revoked', 'api_key', $id, [], $actorId);
    }

    /** @return list<array<string,mixed>> */
    public function list(): array
    {
        return $this->app->db->all('SELECT k.id, k.name, k.key_prefix, k.scopes, k.created_at, k.expires_at, k.last_used_at, k.revoked_at, u.full_name FROM api_keys k LEFT JOIN users u ON u.id = k.created_by_user_id ORDER BY k.id DESC');
    }

    /**
     * Check a bearer key for a scope.
     *
     * @return array{ok:bool,status:int,error:?string,key_id:?int}
     */
    public function authorise(string $authorization, string $scope): array
    {
        if (!preg_match('/^Bearer\s+(vq_[0-9a-f]{12}_[A-Za-z0-9_-]{30,60})$/', trim($authorization), $m)) {
            return ['ok' => false, 'status' => 401, 'error' => 'Send an API key as "Authorization: Bearer vq_...".', 'key_id' => null];
        }
        $row = $this->app->db->one('SELECT * FROM api_keys WHERE key_hash = :h', ['h' => hash('sha256', $m[1])]);
        $now = $this->app->clock->nowSql();
        if ($row === null || $row['revoked_at'] !== null || ($row['expires_at'] !== null && $row['expires_at'] <= $now)) {
            return ['ok' => false, 'status' => 401, 'error' => 'The API key is not valid, has expired or was revoked.', 'key_id' => null];
        }
        if (!in_array($scope, explode(' ', (string) $row['scopes']), true)) {
            return ['ok' => false, 'status' => 403, 'error' => "This key does not have the {$scope} scope.", 'key_id' => (int) $row['id']];
        }
        if (!$this->app->rateLimiter()->hit('api:' . $row['id'], self::PER_MINUTE, 60)) {
            return ['ok' => false, 'status' => 429, 'error' => 'Rate limit: ' . self::PER_MINUTE . ' requests a minute.', 'key_id' => (int) $row['id']];
        }
        if ($row['last_used_at'] === null || $row['last_used_at'] < $this->app->clock->now()->modify('-1 minute')->format('Y-m-d H:i:s')) {
            $this->app->db->execute('UPDATE api_keys SET last_used_at = :t WHERE id = :id', ['t' => $now, 'id' => $row['id']]);
        }
        return ['ok' => true, 'status' => 200, 'error' => null, 'key_id' => (int) $row['id']];
    }
}
