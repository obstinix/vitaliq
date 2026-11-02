<?php

declare(strict_types=1);

namespace Vitaliq\Services;

use DateTimeImmutable;
use Vitaliq\Core\App;
use Vitaliq\Validation\Validator;

/**
 * Staff shifts (feature 42). A person cannot hold overlapping shifts; a
 * shift lasts 1 to 14 hours and can be booked up to 90 days ahead.
 */
final class ScheduleService
{
    public const DUTIES = ['bench' => 'Laboratory bench', 'collection' => 'Sample collection', 'reception' => 'Reception', 'on_call' => 'On call', 'surveillance' => 'Surveillance desk', 'admin' => 'Administration'];

    public function __construct(private readonly App $app)
    {
    }

    /** @return list<array<string,mixed>> */
    public function staff(): array
    {
        return $this->app->db->all("SELECT id, full_name, role FROM users WHERE role NOT IN ('patient') AND is_active = 1 ORDER BY role, full_name");
    }

    /** @param array<string,mixed> $input */
    public function create(array $input, int $actorId): int
    {
        $v = new Validator($input);
        $userId = (int) ($input['user_id'] ?? 0);
        if (!in_array($userId, array_map(static fn ($s) => (int) $s['id'], $this->staff()), true)) {
            $v->addError('user_id', 'Choose a staff member.');
        }
        $date = $v->date('date', 'Date', $this->app->clock->now()->modify('-1 day'), $this->app->clock->now()->modify('+90 days'));
        $duty = $v->choice('duty', self::DUTIES, 'Duty');
        $start = (string) ($input['start'] ?? '');
        $end = (string) ($input['end'] ?? '');
        if (!preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $start) || !preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $end)) {
            $v->addError('start', 'Enter start and end times as HH:MM.');
        }
        $facility = (int) ($input['facility_id'] ?? 0);
        if ($facility > 0 && $this->app->db->value('SELECT id FROM facilities WHERE id = :f AND is_active = 1', ['f' => $facility]) === null) {
            $v->addError('facility_id', 'Choose an open facility.');
        }
        $note = $v->text('note', 'Note', 200, false);
        if ($v->fails()) {
            throw new ValidationFailed($v->errors());
        }
        $from = new DateTimeImmutable("{$date} {$start}:00");
        $to = new DateTimeImmutable("{$date} {$end}:00");
        if ($to <= $from) {
            $to = $to->modify('+1 day'); // a night shift ends the next morning
        }
        $hours = ($to->getTimestamp() - $from->getTimestamp()) / 3600;
        if ($hours < 1 || $hours > 14) {
            throw new ValidationFailed(['end' => 'A shift lasts from 1 to 14 hours.']);
        }
        return $this->app->db->transaction(function ($db) use ($userId, $facility, $from, $to, $duty, $note, $actorId): int {
            $db->one('SELECT id FROM users WHERE id = :u FOR UPDATE', ['u' => $userId]);
            $clash = $db->value('SELECT COUNT(*) FROM staff_shifts WHERE user_id = :u AND starts_at < :e AND ends_at > :s',
                ['u' => $userId, 's' => $from->format('Y-m-d H:i:s'), 'e' => $to->format('Y-m-d H:i:s')]);
            if ((int) $clash > 0) {
                throw new ValidationFailed(['start' => 'This person already has a shift that overlaps these hours.']);
            }
            $id = $db->insert(
                'INSERT INTO staff_shifts (user_id, facility_id, starts_at, ends_at, duty, note, created_by_user_id, created_at) VALUES (:u, :f, :s, :e, :d, :n, :by, :t)',
                ['u' => $userId, 'f' => $facility > 0 ? $facility : null, 's' => $from->format('Y-m-d H:i:s'), 'e' => $to->format('Y-m-d H:i:s'),
                 'd' => $duty, 'n' => $note ?: null, 'by' => $actorId, 't' => $this->app->clock->nowSql()]
            );
            $this->app->service(NotificationService::class)->notify($userId, 'schedule', 'New shift', self::DUTIES[(string) $duty] . ' on ' . $from->format('D j M, H:i') . '–' . $to->format('H:i') . '.', '/staff/schedule');
            return $id;
        });
    }

    public function delete(int $id, int $actorId): void
    {
        $shift = $this->app->db->one('SELECT * FROM staff_shifts WHERE id = :id', ['id' => $id]);
        if ($shift === null) {
            throw new WorkflowException('Shift not found.');
        }
        $this->app->db->execute('DELETE FROM staff_shifts WHERE id = :id', ['id' => $id]);
        $this->app->service(AuditLog::class)->record('schedule.shift_removed', 'user', (int) $shift['user_id'], ['shift' => $id], $actorId);
        $this->app->service(NotificationService::class)->notify((int) $shift['user_id'], 'schedule', 'Shift removed',
            'Your shift on ' . (new DateTimeImmutable((string) $shift['starts_at']))->format('D j M, H:i') . ' was removed.', '/staff/schedule');
    }

    /**
     * A week from Monday: people down the side, days across.
     *
     * @return array{start:string,days:list<string>,rows:list<array<string,mixed>>,prev:string,next:string}
     */
    public function week(string $anyDay, ?int $userId = null): array
    {
        $d = DateTimeImmutable::createFromFormat('!Y-m-d', $anyDay) ?: $this->app->clock->now()->setTime(0, 0);
        $monday = $d->modify('-' . ((int) $d->format('N') - 1) . ' days');
        $days = [];
        for ($i = 0; $i < 7; $i++) {
            $days[] = $monday->modify("+{$i} days")->format('Y-m-d');
        }
        $params = ['s' => $monday->format('Y-m-d 00:00:00'), 'e' => $monday->modify('+7 days')->format('Y-m-d 00:00:00')];
        $where = '';
        if ($userId !== null) {
            $where = 'AND s.user_id = :u';
            $params['u'] = $userId;
        }
        $shifts = $this->app->db->all(
            "SELECT s.*, u.full_name, u.role, f.name AS facility_name FROM staff_shifts s JOIN users u ON u.id = s.user_id
               LEFT JOIN facilities f ON f.id = s.facility_id WHERE s.starts_at < :e AND s.ends_at > :s {$where} ORDER BY u.full_name, s.starts_at",
            $params
        );
        $rows = [];
        foreach ($shifts as $s) {
            $key = (int) $s['user_id'];
            $rows[$key] ??= ['name' => $s['full_name'], 'role' => $s['role'], 'days' => array_fill_keys($days, [])];
            $day = substr((string) $s['starts_at'], 0, 10);
            if (isset($rows[$key]['days'][$day])) {
                $rows[$key]['days'][$day][] = $s;
            }
        }
        return ['start' => $monday->format('Y-m-d'), 'days' => $days, 'rows' => array_values($rows),
                'prev' => $monday->modify('-7 days')->format('Y-m-d'), 'next' => $monday->modify('+7 days')->format('Y-m-d')];
    }
}
