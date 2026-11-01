<?php

declare(strict_types=1);

namespace Vitaliq\Services;

use Vitaliq\Core\App;
use Vitaliq\Validation\Validator;

/**
 * Broadcast messages to a chosen audience (feature 37). Each recipient gets
 * an in-app notification of type "broadcast"; outside channels follow their
 * own preferences for the "broadcast" category. Ten broadcasts a day.
 */
final class BroadcastService
{
    public const AUDIENCES = ['everyone' => 'Everyone with an active account', 'patients' => 'All patients', 'staff' => 'All staff', 'role' => 'One role', 'state' => 'Patients in one state'];
    public const MAX_PER_DAY = 10;

    public function __construct(private readonly App $app)
    {
    }

    /**
     * @return array{sql:string,params:array<string,string>}
     */
    private function audienceQuery(string $audience, string $value): array
    {
        return match ($audience) {
            'everyone' => ['sql' => 'SELECT id FROM users WHERE is_active = 1', 'params' => []],
            'patients' => ['sql' => "SELECT id FROM users WHERE is_active = 1 AND role = 'patient'", 'params' => []],
            'staff' => ['sql' => "SELECT id FROM users WHERE is_active = 1 AND role <> 'patient'", 'params' => []],
            'role' => ['sql' => 'SELECT id FROM users WHERE is_active = 1 AND role = :v', 'params' => ['v' => $value]],
            'state' => ['sql' => "SELECT u.id FROM users u JOIN patients p ON p.user_id = u.id WHERE u.is_active = 1 AND u.role = 'patient' AND p.state_code = :v", 'params' => ['v' => $value]],
            default => throw new ValidationFailed(['audience' => 'Choose who receives the message.']),
        };
    }

    public function count(string $audience, string $value): int
    {
        $q = $this->audienceQuery($audience, $value);
        return (int) $this->app->db->value('SELECT COUNT(*) FROM (' . $q['sql'] . ') x', $q['params']);
    }

    /**
     * @param array<string,mixed> $input
     * @return array{id:int,recipients:int}
     */
    public function send(array $input, int $actorId): array
    {
        $v = new Validator($input);
        $title = $v->text('title', 'Title', 140, true, 5);
        $body = $v->text('body', 'Message', 300, true, 10);
        $audience = $v->choice('audience', self::AUDIENCES, 'Audience');
        $value = trim((string) ($input['audience_value'] ?? ''));
        if ($audience === 'role' && !in_array($value, ['patient', 'phlebotomist', 'admin', 'qa', 'lab', 'officer'], true)) {
            $v->addError('audience_value', 'Choose the role.');
        }
        if ($audience === 'state' && $this->app->db->value('SELECT code FROM states WHERE code = :c', ['c' => $value]) === null) {
            $v->addError('audience_value', 'Choose the state.');
        }
        $link = trim((string) ($input['link_path'] ?? ''));
        if ($link !== '' && !preg_match('#^/[A-Za-z0-9/_-]{0,190}$#', $link)) {
            $v->addError('link_path', 'Use a path on this site, such as /announcements.');
        }
        $sentToday = (int) $this->app->db->value('SELECT COUNT(*) FROM broadcasts WHERE created_at >= :d', ['d' => $this->app->clock->now()->format('Y-m-d 00:00:00')]);
        if ($sentToday >= self::MAX_PER_DAY) {
            $v->addError('title', 'The limit of ' . self::MAX_PER_DAY . ' broadcasts a day has been reached.');
        }
        if ($v->fails()) {
            throw new ValidationFailed($v->errors());
        }
        $q = $this->audienceQuery((string) $audience, $value);
        $ids = array_map(static fn ($r) => (int) $r['id'], $this->app->db->all($q['sql'], $q['params']));
        if ($ids === []) {
            throw new ValidationFailed(['audience' => 'Nobody matches this audience.']);
        }
        $notes = $this->app->service(NotificationService::class);
        $id = $this->app->db->transaction(function ($db) use ($ids, $title, $body, $link, $audience, $value, $actorId, $notes): int {
            foreach ($ids as $uid) {
                $notes->notify($uid, 'broadcast', (string) $title, (string) $body, $link !== '' ? $link : null);
            }
            return $db->insert(
                'INSERT INTO broadcasts (title, body, link_path, audience, audience_value, recipients, created_by_user_id, created_at) VALUES (:t, :b, :l, :a, :v, :n, :u, :c)',
                ['t' => $title, 'b' => $body, 'l' => $link !== '' ? $link : null, 'a' => $audience, 'v' => $value !== '' ? $value : null,
                 'n' => count($ids), 'u' => $actorId, 'c' => $this->app->clock->nowSql()]
            );
        });
        $this->app->service(AuditLog::class)->record('broadcast.sent', 'broadcast', $id, ['recipients' => count($ids), 'audience' => (string) $audience], $actorId);
        return ['id' => $id, 'recipients' => count($ids)];
    }

    /** @return list<array<string,mixed>> */
    public function recent(): array
    {
        return $this->app->db->all('SELECT b.*, u.full_name FROM broadcasts b LEFT JOIN users u ON u.id = b.created_by_user_id ORDER BY b.id DESC LIMIT 30');
    }
}
