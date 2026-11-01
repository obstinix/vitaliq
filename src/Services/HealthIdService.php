<?php

declare(strict_types=1);

namespace Vitaliq\Services;

use chillerlan\QRCode\Common\EccLevel;
use chillerlan\QRCode\Output\QROutputInterface;
use chillerlan\QRCode\QRCode;
use chillerlan\QRCode\QROptions;
use Vitaliq\Core\App;

/**
 * Revocable QR health IDs (feature 6). A token opens a read-only emergency
 * card (name, age, blood group, allergies, conditions, medicines, emergency
 * contacts) at /h/{token}. Only a SHA-256 hash of the token is stored, so
 * the plain token is shown once, at creation, as a QR code and a link.
 */
final class HealthIdService
{
    public const MAX_ACTIVE = 3;

    public function __construct(private readonly App $app)
    {
    }

    /** @return array{id:int,token:string,url:string} */
    public function issue(int $patientId, int $userId, string $label, ?int $days): array
    {
        $label = trim($label) === '' ? 'Health ID card' : mb_substr(trim($label), 0, 60);
        if ($days !== null && ($days < 1 || $days > 365)) {
            throw new ValidationFailed(['days' => 'Choose an expiry from 1 to 365 days, or no expiry.']);
        }
        $active = (int) $this->app->db->value(
            'SELECT COUNT(*) FROM health_id_tokens WHERE patient_id = :p AND revoked_at IS NULL AND (expires_at IS NULL OR expires_at > :now)',
            ['p' => $patientId, 'now' => $this->app->clock->nowSql()]
        );
        if ($active >= self::MAX_ACTIVE) {
            throw new ValidationFailed(['label' => 'Revoke one of your ' . self::MAX_ACTIVE . ' active health IDs first.']);
        }
        $token = rtrim(strtr(base64_encode(random_bytes(24)), '+/', '-_'), '=');
        $id = $this->app->db->insert(
            'INSERT INTO health_id_tokens (patient_id, token_hash, label, expires_at, created_by_user_id, created_at) VALUES (:p, :h, :l, :e, :u, :t)',
            ['p' => $patientId, 'h' => hash('sha256', $token), 'l' => $label,
             'e' => $days === null ? null : $this->app->clock->now()->modify("+{$days} days")->format('Y-m-d H:i:s'),
             'u' => $userId, 't' => $this->app->clock->nowSql()]
        );
        $this->app->service(AuditLog::class)->record('health_id.issued', 'patient', $patientId, ['token_id' => $id]);
        return ['id' => $id, 'token' => $token, 'url' => $this->app->config['url'] . '/h/' . $token];
    }

    public function revoke(int $patientId, int $tokenId): bool
    {
        $done = $this->app->db->execute(
            'UPDATE health_id_tokens SET revoked_at = :t WHERE id = :id AND patient_id = :p AND revoked_at IS NULL',
            ['t' => $this->app->clock->nowSql(), 'id' => $tokenId, 'p' => $patientId]
        ) === 1;
        if ($done) {
            $this->app->service(AuditLog::class)->record('health_id.revoked', 'patient', $patientId, ['token_id' => $tokenId]);
        }
        return $done;
    }

    /** @return list<array<string,mixed>> */
    public function tokens(int $patientId): array
    {
        return $this->app->db->all('SELECT id, label, expires_at, revoked_at, last_used_at, use_count, created_at FROM health_id_tokens WHERE patient_id = :p ORDER BY id DESC', ['p' => $patientId]);
    }

    /**
     * Resolve a presented token to its emergency card, or null when unknown,
     * revoked or expired. Each successful view is counted and audited.
     *
     * @return array<string,mixed>|null
     */
    public function resolve(string $token): ?array
    {
        if (!preg_match('/^[A-Za-z0-9_-]{32}$/', $token)) {
            return null;
        }
        $row = $this->app->db->one('SELECT * FROM health_id_tokens WHERE token_hash = :h', ['h' => hash('sha256', $token)]);
        $now = $this->app->clock->nowSql();
        if ($row === null || $row['revoked_at'] !== null || ($row['expires_at'] !== null && $row['expires_at'] <= $now)) {
            return null;
        }
        $this->app->db->execute('UPDATE health_id_tokens SET last_used_at = :t, use_count = use_count + 1 WHERE id = :id', ['t' => $now, 'id' => $row['id']]);
        $this->app->service(AuditLog::class)->record('health_id.viewed', 'patient', (int) $row['patient_id'], ['token_id' => (int) $row['id']], null);
        $patient = $this->app->db->one('SELECT id, full_name, date_of_birth, sex FROM patients WHERE id = :id', ['id' => $row['patient_id']]);
        if ($patient === null) {
            return null;
        }
        $platform = $this->app->service(PatientPlatformService::class);
        $passport = $platform->passport((int) $patient['id']);
        return [
            'patient' => $patient,
            'age' => PatientService::age((string) $patient['date_of_birth'], $this->app->clock->now()),
            'profile' => $passport['profile'],
            'contacts' => $passport['contacts'],
            'token' => $row,
        ];
    }

    /** Inline SVG of a QR code for $data. */
    public static function qrSvg(string $data): string
    {
        $options = new QROptions([
            'outputType' => QROutputInterface::MARKUP_SVG,
            'outputBase64' => false,
            'eccLevel' => EccLevel::M,
            'addQuietzone' => true,
            'svgAddXmlHeader' => false,
            'drawLightModules' => false,
            'connectPaths' => true,
        ]);
        return (new QRCode($options))->render($data);
    }
}
