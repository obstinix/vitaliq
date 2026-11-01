<?php

declare(strict_types=1);

namespace Vitaliq\Services;

use DateTimeImmutable;
use Vitaliq\Core\App;
use Vitaliq\Validation\Validator;

/**
 * Appointments and the calendar (features 4, 5) and teleconsultation rooms
 * (feature 53). Slots are 20 minutes from 08:00 to 19:40; a facility takes
 * at most SLOT_CAPACITY bookings per slot, and a person cannot hold two
 * overlapping appointments.
 */
final class AppointmentService
{
    public const KINDS = [
        'sample_collection' => 'Sample collection',
        'consultation' => 'Consultation',
        'teleconsultation' => 'Teleconsultation',
        'vaccination' => 'Vaccination',
        'follow_up' => 'Follow-up',
    ];
    public const STATUSES = ['scheduled' => 'Scheduled', 'confirmed' => 'Confirmed', 'attended' => 'Attended', 'cancelled' => 'Cancelled', 'missed' => 'Missed'];
    public const SLOT_MINUTES = 20;
    public const FIRST_SLOT = '08:00';
    public const LAST_SLOT = '19:40';
    public const SLOT_CAPACITY = 3;
    public const MAX_DAYS_AHEAD = 60;
    public const CANCEL_NOTICE_MINUTES = 60;

    public function __construct(private readonly App $app)
    {
    }

    /** @return list<string> "HH:MM" */
    public static function slots(): array
    {
        $out = [];
        $t = new DateTimeImmutable('2000-01-01 ' . self::FIRST_SLOT);
        $last = new DateTimeImmutable('2000-01-01 ' . self::LAST_SLOT);
        while ($t <= $last) {
            $out[] = $t->format('H:i');
            $t = $t->modify('+' . self::SLOT_MINUTES . ' minutes');
        }
        return $out;
    }

    /** @return list<array<string,mixed>> */
    public function facilities(): array
    {
        return $this->app->db->all("SELECT id, name, city, kind FROM facilities WHERE is_active = 1 AND kind <> 'laboratory' ORDER BY name");
    }

    /**
     * @param array<string,mixed> $input
     * @return int appointment id
     */
    public function book(int $userId, int $patientId, array $input): int
    {
        $v = new Validator($input);
        $now = $this->app->clock->now();
        $kind = $v->choice('kind', self::KINDS, 'Appointment type');
        $date = $v->date('date', 'Date', $now, $now->modify('+' . self::MAX_DAYS_AHEAD . ' days'));
        $slot = $v->choice('slot', array_fill_keys(self::slots(), ''), 'Time');
        $reason = $v->text('reason', 'Reason', 300, false);
        $facilityId = (int) ($input['facility_id'] ?? 0);
        if ($kind !== null && $kind !== 'teleconsultation') {
            if ($facilityId <= 0 || !in_array($facilityId, array_map('intval', array_column($this->facilities(), 'id')), true)) {
                $v->addError('facility_id', 'Choose where the appointment takes place.');
            }
        } else {
            $facilityId = 0;
        }
        $startsAt = null;
        if ($date !== null && $slot !== null) {
            $startsAt = new DateTimeImmutable("{$date} {$slot}:00");
            if ($startsAt <= $now) {
                $v->addError('slot', 'Choose a time later than now.');
            }
        }
        if ($v->fails() || $startsAt === null) {
            throw new ValidationFailed($v->errors());
        }

        return $this->app->db->transaction(function ($db) use ($userId, $patientId, $kind, $startsAt, $reason, $facilityId): int {
            $start = $startsAt->format('Y-m-d H:i:s');
            $end = $startsAt->modify('+' . self::SLOT_MINUTES . ' minutes')->format('Y-m-d H:i:s');
            // Lock the patient's row so two tabs cannot both pass the overlap check.
            $db->one('SELECT id FROM patients WHERE id = :p FOR UPDATE', ['p' => $patientId]);
            $clash = $db->value(
                "SELECT COUNT(*) FROM appointments WHERE patient_id = :p AND status IN ('scheduled','confirmed')
                   AND starts_at < :end AND DATE_ADD(starts_at, INTERVAL duration_min MINUTE) > :start",
                ['p' => $patientId, 'start' => $start, 'end' => $end]
            );
            if ((int) $clash > 0) {
                throw new ValidationFailed(['slot' => 'This person already has an appointment at that time.']);
            }
            if ($facilityId > 0) {
                $db->one('SELECT id FROM facilities WHERE id = :f FOR UPDATE', ['f' => $facilityId]);
                $taken = (int) $db->value(
                    "SELECT COUNT(*) FROM appointments WHERE facility_id = :f AND starts_at = :s AND status IN ('scheduled','confirmed')",
                    ['f' => $facilityId, 's' => $start]
                );
                if ($taken >= self::SLOT_CAPACITY) {
                    throw new ValidationFailed(['slot' => 'That time is fully booked at this facility. Choose another time.']);
                }
            }
            $id = $db->insert(
                'INSERT INTO appointments (patient_id, booked_by_user_id, facility_id, kind, starts_at, duration_min, reason, meeting_url, created_at)
                 VALUES (:p, :u, :f, :k, :s, :d, :r, :m, :t)',
                ['p' => $patientId, 'u' => $userId, 'f' => $facilityId > 0 ? $facilityId : null, 'k' => $kind, 's' => $start,
                 'd' => self::SLOT_MINUTES, 'r' => $reason ?: null, 'm' => $kind === 'teleconsultation' ? $this->meetingUrl() : null,
                 't' => $this->app->clock->nowSql()]
            );
            $this->app->service(AuditLog::class)->record('appointment.booked', 'appointment', $id, ['kind' => (string) $kind]);
            $this->app->service(NotificationService::class)->notify(
                $userId, 'appointment', 'Appointment booked',
                (self::KINDS[(string) $kind] ?? 'Appointment') . ' on ' . $startsAt->format('j M Y, g:i a') . '.',
                '/patient/appointments/' . $id
            );
            return $id;
        });
    }

    /** Teleconsultation room: a random 128-bit room name on the configured server. */
    public function meetingUrl(): string
    {
        return $this->app->config['teleconsult']['base_url'] . '/Vitaliq-' . bin2hex(random_bytes(16));
    }

    public function cancelByPatient(int $patientId, int $id, string $reason): void
    {
        $this->app->db->transaction(function ($db) use ($patientId, $id, $reason): void {
            $a = $db->one('SELECT * FROM appointments WHERE id = :id AND patient_id = :p FOR UPDATE', ['id' => $id, 'p' => $patientId]);
            if ($a === null) {
                throw new WorkflowException('Appointment not found.');
            }
            if (!in_array($a['status'], ['scheduled', 'confirmed'], true)) {
                throw new WorkflowException('Only an upcoming appointment can be cancelled.');
            }
            $minutes = ((new DateTimeImmutable((string) $a['starts_at']))->getTimestamp() - $this->app->clock->now()->getTimestamp()) / 60;
            if ($minutes < self::CANCEL_NOTICE_MINUTES) {
                throw new WorkflowException('Appointments can be cancelled online up to one hour before they start. Please call the facility.');
            }
            $db->execute("UPDATE appointments SET status = 'cancelled', cancel_reason = :r WHERE id = :id",
                ['r' => mb_substr(trim($reason), 0, 300) ?: null, 'id' => $id]);
            $this->app->service(AuditLog::class)->record('appointment.cancelled', 'appointment', $id);
        });
    }

    /** Staff marks the outcome of an appointment. */
    public function setStatus(int $id, string $status, int $actorId): void
    {
        $allowed = ['scheduled' => ['confirmed', 'attended', 'missed', 'cancelled'], 'confirmed' => ['attended', 'missed', 'cancelled']];
        $this->app->db->transaction(function ($db) use ($id, $status, $actorId, $allowed): void {
            $a = $db->one('SELECT a.*, p.user_id, p.managed_by_user_id FROM appointments a JOIN patients p ON p.id = a.patient_id WHERE a.id = :id FOR UPDATE', ['id' => $id]);
            if ($a === null) {
                throw new WorkflowException('Appointment not found.');
            }
            if (!in_array($status, $allowed[$a['status']] ?? [], true)) {
                throw new WorkflowException('An appointment that is ' . strtolower(self::STATUSES[$a['status']]) . ' cannot become ' . strtolower(self::STATUSES[$status] ?? $status) . '.');
            }
            if (in_array($status, ['attended', 'missed'], true) && new DateTimeImmutable((string) $a['starts_at']) > $this->app->clock->now()) {
                throw new WorkflowException('An appointment can be marked attended or missed only after it starts.');
            }
            $db->execute('UPDATE appointments SET status = :s WHERE id = :id', ['s' => $status, 'id' => $id]);
            $this->app->service(AuditLog::class)->record('appointment.' . $status, 'appointment', $id, [], $actorId);
            $owner = $a['user_id'] ?? $a['managed_by_user_id'];
            if ($owner !== null && in_array($status, ['confirmed', 'cancelled'], true)) {
                $this->app->service(NotificationService::class)->notify((int) $owner, 'appointment', 'Appointment ' . $status,
                    'Your appointment on ' . (new DateTimeImmutable((string) $a['starts_at']))->format('j M Y, g:i a') . ' was ' . $status . '.',
                    '/patient/appointments/' . $id);
            }
        });
    }

    /** @return array<string,mixed>|null */
    public function find(int $id): ?array
    {
        return $this->app->db->one(
            'SELECT a.*, p.full_name, p.user_id, p.managed_by_user_id, f.name AS facility_name, f.city AS facility_city
               FROM appointments a JOIN patients p ON p.id = a.patient_id LEFT JOIN facilities f ON f.id = a.facility_id
              WHERE a.id = :id',
            ['id' => $id]
        );
    }

    /**
     * Appointments for every profile the user holds, optionally within a month.
     *
     * @param list<int> $patientIds
     * @return list<array<string,mixed>>
     */
    public function forPatients(array $patientIds, ?string $from = null, ?string $to = null): array
    {
        if ($patientIds === []) {
            return [];
        }
        $in = implode(',', array_map('intval', $patientIds));
        $range = $from !== null ? 'AND a.starts_at >= :from AND a.starts_at < :to' : '';
        return $this->app->db->all(
            "SELECT a.*, p.full_name, f.name AS facility_name FROM appointments a JOIN patients p ON p.id = a.patient_id
               LEFT JOIN facilities f ON f.id = a.facility_id
              WHERE a.patient_id IN ({$in}) {$range} ORDER BY a.starts_at",
            $from !== null ? ['from' => $from, 'to' => $to] : []
        );
    }

    /** @return list<array<string,mixed>> */
    public function forRange(string $from, string $to, int $facilityId = 0): array
    {
        $params = ['from' => $from, 'to' => $to];
        $where = '';
        if ($facilityId > 0) {
            $where = 'AND a.facility_id = :f';
            $params['f'] = $facilityId;
        }
        return $this->app->db->all(
            "SELECT a.*, p.full_name, p.mobile, f.name AS facility_name FROM appointments a JOIN patients p ON p.id = a.patient_id
               LEFT JOIN facilities f ON f.id = a.facility_id
              WHERE a.starts_at >= :from AND a.starts_at < :to {$where} ORDER BY a.starts_at",
            $params
        );
    }

    /**
     * A month grid: 6 weeks starting on Monday, each day with its events.
     *
     * @param list<array<string,mixed>> $events
     * @return array{month:string,label:string,prev:string,next:string,weeks:list<list<array{date:string,in:bool,today:bool,weekend:bool,events:list<array<string,mixed>>}>>}
     */
    public function monthGrid(string $month, array $events): array
    {
        $first = DateTimeImmutable::createFromFormat('!Y-m', $month) ?: $this->app->clock->now()->modify('first day of this month')->setTime(0, 0);
        $start = $first->modify('-' . ((int) $first->format('N') - 1) . ' days');
        $today = $this->app->clock->now()->format('Y-m-d');
        $byDay = [];
        foreach ($events as $e) {
            $byDay[substr((string) $e['starts_at'], 0, 10)][] = $e;
        }
        $weeks = [];
        for ($w = 0; $w < 6; $w++) {
            $week = [];
            for ($d = 0; $d < 7; $d++) {
                $day = $start->modify('+' . ($w * 7 + $d) . ' days');
                $key = $day->format('Y-m-d');
                $week[] = ['date' => $key, 'in' => $day->format('m') === $first->format('m'), 'today' => $key === $today,
                           'weekend' => $d >= 5, 'events' => $byDay[$key] ?? []];
            }
            $weeks[] = $week;
        }
        return ['month' => $first->format('Y-m'), 'label' => $first->format('F Y'),
                'prev' => $first->modify('-1 month')->format('Y-m'), 'next' => $first->modify('+1 month')->format('Y-m'), 'weeks' => $weeks];
    }

    /**
     * Upcoming appointments that start within $hours and have no reminder yet
     * (feature 36). Returns the number of reminders sent.
     */
    public function sendReminders(int $hours = 24): int
    {
        $now = $this->app->clock->now();
        $rows = $this->app->db->all(
            "SELECT a.id, a.kind, a.starts_at, COALESCE(p.user_id, p.managed_by_user_id) AS owner, p.full_name
               FROM appointments a JOIN patients p ON p.id = a.patient_id
              WHERE a.status IN ('scheduled','confirmed') AND a.reminder_sent_at IS NULL
                AND a.starts_at > :now AND a.starts_at <= :until",
            ['now' => $now->format('Y-m-d H:i:s'), 'until' => $now->modify("+{$hours} hours")->format('Y-m-d H:i:s')]
        );
        $sent = 0;
        foreach ($rows as $r) {
            if ($r['owner'] !== null) {
                $this->app->service(NotificationService::class)->notify((int) $r['owner'], 'reminder', 'Appointment reminder',
                    (self::KINDS[$r['kind']] ?? 'Appointment') . ' for ' . $r['full_name'] . ' on ' . (new DateTimeImmutable((string) $r['starts_at']))->format('j M, g:i a') . '.',
                    '/patient/appointments/' . $r['id']);
                $sent++;
            }
            $this->app->db->execute('UPDATE appointments SET reminder_sent_at = :t WHERE id = :id', ['t' => $now->format('Y-m-d H:i:s'), 'id' => $r['id']]);
        }
        return $sent;
    }
}
