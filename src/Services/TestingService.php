<?php

declare(strict_types=1);

namespace Vitaliq\Services;

use PDOException;
use Vitaliq\Core\App;
use Vitaliq\Core\Database;
use Vitaliq\Support\Labels;
use Vitaliq\Validation\Validator;

/**
 * Testing requests and their lifecycle. All status changes go through
 * move(), which enforces TRANSITIONS, uses a compare-and-set UPDATE, writes
 * status history and sends notifications, inside the caller's transaction.
 *
 * Actor arrays have the shape ['id' => ?int, 'role' => string].
 */
final class TestingService
{
    public const TRANSITIONS = [
        'submitted' => ['assigned', 'cancelled', 'rejected'],
        'assigned' => ['sample_collected', 'cancelled', 'rejected'],
        'sample_collected' => ['in_progress', 'rejected'],
        'in_progress' => ['completed'],
        'completed' => ['result_available'],
        'result_available' => [],
        'cancelled' => [],
        'rejected' => [],
    ];

    /** Normal path, used for progress timelines. */
    public const STAGES = ['submitted', 'assigned', 'sample_collected', 'in_progress', 'completed', 'result_available'];
    public const ACTIVE = ['submitted', 'assigned', 'sample_collected', 'in_progress', 'completed'];
    public const TERMINAL = ['result_available', 'cancelled', 'rejected'];

    private const REFERENCE_ALPHABET = '0123456789ABCDEFGHJKMNPQRSTVWXYZ';

    public function __construct(private readonly App $app)
    {
    }

    public static function canTransition(string $from, string $to): bool
    {
        return in_array($to, self::TRANSITIONS[$from] ?? [], true);
    }

    public function generateReference(): string
    {
        $suffix = '';
        for ($i = 0; $i < 5; $i++) {
            $suffix .= self::REFERENCE_ALPHABET[random_int(0, 31)];
        }
        return 'VQ-' . $this->app->clock->now()->format('ymd') . '-' . $suffix;
    }

    // ------------------------------------------------------------------
    // Submission
    // ------------------------------------------------------------------

    /**
     * @param array<string,mixed> $input
     * @return array<string,mixed>
     */
    public function validateTestDetails(Validator $v, array $input): array
    {
        $today = $this->app->clock->now();
        $data = [
            'test_type' => $v->choice('test_type', Labels::TEST_TYPES, 'Test type'),
            'reason' => $v->choice('reason', Labels::REASONS, 'Reason for testing'),
            'symptoms' => $v->choices('symptoms', Labels::SYMPTOMS, 'Symptoms'),
            'exposure' => $v->choice('exposure', Labels::EXPOSURE, 'Contact with a confirmed case'),
            'collection_mode' => $v->choice('collection_mode', Labels::COLLECTION_MODES, 'Collection method'),
            'preferred_date' => $v->date('preferred_date', 'Preferred date', $today, $today->modify('+14 days')),
            'preferred_slot' => $v->choice('preferred_slot', Labels::SLOTS, 'Preferred time slot'),
            'city' => $v->text('city', 'City', 80, true, 2),
            'state_code' => $v->choice('state_code', $this->app->service(PatientService::class)->states(), 'State or union territory'),
            'pincode' => $v->rule('pincode', 'pincode'),
            'notes' => $v->text('notes', 'Notes', 500, false),
            'symptom_onset' => null,
            'collection_address' => null,
        ];
        $hasSymptoms = $data['symptoms'] !== [] || $data['reason'] === 'symptoms';
        if ($hasSymptoms) {
            $data['symptom_onset'] = $v->date('symptom_onset', 'Symptom start date', $today->modify('-30 days'), $today);
        }
        if ($data['reason'] === 'symptoms' && $data['symptoms'] === [] && !$v->hasError('symptoms')) {
            $v->addError('symptoms', 'Select at least one symptom, or choose a different reason.');
        }
        if ($data['collection_mode'] === 'home') {
            $data['collection_address'] = $v->text('collection_address', 'Collection address', 200, true, 5);
        }
        return $data;
    }

    /** @return array<string,mixed>|null */
    public function findByIdempotencyKey(string $key): ?array
    {
        return $this->app->db->one(
            'SELECT id, reference, patient_id FROM test_requests WHERE idempotency_key = :k',
            ['k' => hash('sha256', $key)]
        );
    }

    /**
     * New-patient (guest) request: demographics and test details together.
     *
     * @param array<string,mixed> $input
     * @return array{id:int,reference:string}
     * @throws ValidationFailed
     */
    public function submitGuest(array $input, string $idempotencyKey): array
    {
        $v = new Validator($input);
        $patients = $this->app->service(PatientService::class);
        $person = $patients->validateDemographics($v, $input, false);
        $person['email'] = $v->rule('email', 'email', false);
        $test = $this->validateTestDetails($v, $input);
        $v->accepted('consent', 'Please confirm the information is accurate and consent to sample collection.');
        if ($v->fails()) {
            throw new ValidationFailed($v->errors());
        }

        return $this->app->db->transaction(function (Database $db) use ($person, $test, $idempotencyKey): array {
            $existing = $db->one(
                'SELECT id FROM patients WHERE user_id IS NULL AND mobile = :m AND date_of_birth = :d AND full_name = :n
                 ORDER BY id LIMIT 1 FOR UPDATE',
                ['m' => $person['mobile'], 'd' => $person['date_of_birth'], 'n' => $person['full_name']]
            );
            if ($existing !== null) {
                $patientId = (int) $existing['id'];
                $db->execute('UPDATE patients SET email = COALESCE(:e, email), sex = :s WHERE id = :id', [
                    'e' => $person['email'], 's' => $person['sex'], 'id' => $patientId,
                ]);
            } else {
                $patientId = $db->insert(
                    'INSERT INTO patients (user_id, full_name, date_of_birth, sex, mobile, email, address_line, city, state_code, pincode)
                     VALUES (NULL, :n, :d, :s, :m, :e, :a, :c, :st, :p)',
                    [
                        'n' => $person['full_name'], 'd' => $person['date_of_birth'], 's' => $person['sex'],
                        'm' => $person['mobile'], 'e' => $person['email'], 'a' => $test['collection_address'],
                        'c' => $test['city'], 'st' => $test['state_code'], 'p' => $test['pincode'],
                    ]
                );
            }
            return $this->create($db, $patientId, null, 'guest', $test, $idempotencyKey, ['id' => null, 'role' => 'guest']);
        });
    }

    /**
     * Existing-patient request: demographics come from the account.
     *
     * @param array<string,mixed> $input
     * @return array{id:int,reference:string}
     * @throws ValidationFailed
     */
    public function submitForUser(int $userId, array $input, string $idempotencyKey): array
    {
        $patient = $this->app->service(PatientService::class)->forUser($userId);
        if ($patient === null) {
            throw new WorkflowException('No patient record is linked to this account.');
        }
        $v = new Validator($input);
        $test = $this->validateTestDetails($v, $input);
        $v->accepted('consent', 'Please confirm the information is accurate and consent to sample collection.');
        if ($v->fails()) {
            throw new ValidationFailed($v->errors());
        }
        return $this->app->db->transaction(fn (Database $db) => $this->create(
            $db,
            (int) $patient['id'],
            $userId,
            'account',
            $test,
            $idempotencyKey,
            ['id' => $userId, 'role' => 'patient']
        ));
    }

    /**
     * @param array<string,mixed> $test
     * @param array{id:?int,role:string} $actor
     * @return array{id:int,reference:string}
     */
    private function create(Database $db, int $patientId, ?int $userId, string $channel, array $test, string $idempotencyKey, array $actor): array
    {
        $db->value('SELECT id FROM patients WHERE id = :id FOR UPDATE', ['id' => $patientId]);
        $active = $db->one(
            "SELECT reference FROM test_requests WHERE patient_id = :p AND test_type = :t
               AND status IN ('submitted','assigned','sample_collected','in_progress','completed') LIMIT 1",
            ['p' => $patientId, 't' => $test['test_type']]
        );
        if ($active !== null) {
            $type = Labels::TEST_TYPES_SHORT[$test['test_type']];
            // Only account holders see the existing reference; guests could be
            // anyone who knows a name, mobile number and date of birth.
            $message = $channel === 'account'
                ? "You already have an active {$type} request ({$active['reference']}). Track it from your dashboard, or cancel it before requesting another."
                : "An active {$type} request already exists for this person. Use the tracking page with the reference you received, or contact the helpdesk.";
            throw new ValidationFailed(['test_type' => $message]);
        }

        $now = $this->app->clock->nowSql();
        $id = 0;
        $reference = '';
        for ($attempt = 0; $attempt < 5 && $id === 0; $attempt++) {
            $reference = $this->generateReference();
            try {
                $id = $db->insert(
                    'INSERT INTO test_requests (reference, patient_id, submitted_by_user_id, channel, test_type, reason,
                        symptom_onset, exposure, collection_mode, collection_address, city, state_code, pincode,
                        preferred_date, preferred_slot, notes, status, idempotency_key, submitted_at)
                     VALUES (:ref, :p, :u, :ch, :tt, :r, :so, :ex, :cm, :ca, :c, :st, :pin, :pd, :ps, :n, \'submitted\', :ik, :at)',
                    [
                        'ref' => $reference, 'p' => $patientId, 'u' => $userId, 'ch' => $channel,
                        'tt' => $test['test_type'], 'r' => $test['reason'], 'so' => $test['symptom_onset'],
                        'ex' => $test['exposure'], 'cm' => $test['collection_mode'], 'ca' => $test['collection_address'],
                        'c' => $test['city'], 'st' => $test['state_code'], 'pin' => $test['pincode'],
                        'pd' => $test['preferred_date'], 'ps' => $test['preferred_slot'], 'n' => $test['notes'],
                        'ik' => hash('sha256', $idempotencyKey), 'at' => $now,
                    ]
                );
            } catch (PDOException $e) {
                if ($e->getCode() === '23000' && str_contains($e->getMessage(), 'uq_requests_reference')) {
                    continue;
                }
                throw $e;
            }
        }
        if ($id === 0) {
            throw new WorkflowException('Could not allocate a unique reference. Please try again.');
        }

        foreach ($test['symptoms'] as $symptom) {
            $db->execute('INSERT INTO test_request_symptoms (request_id, symptom) VALUES (:r, :s)', ['r' => $id, 's' => $symptom]);
        }
        $this->history($db, $id, null, 'submitted', $actor, $channel === 'guest' ? 'Submitted online as a new patient' : 'Submitted from patient account');

        $notifier = $this->app->service(NotificationService::class);
        $typeLabel = Labels::TEST_TYPES_SHORT[$test['test_type']];
        $notifier->notifyRole('admin', 'request.new', 'New testing request ' . $reference,
            "{$typeLabel} · " . Labels::COLLECTION_MODES[$test['collection_mode']] . " · {$test['city']}",
            '/admin/requests/' . $id);
        if ($userId !== null) {
            $notifier->notify($userId, 'request.received', 'Request ' . $reference . ' received',
                'We will assign a phlebotomist and keep you updated here.', '/patient/requests/' . $id);
        }
        $this->app->service(AuditLog::class)->record('request.submitted', 'test_request', $id, ['reference' => $reference, 'channel' => $channel], $userId);

        return ['id' => $id, 'reference' => $reference];
    }

    // ------------------------------------------------------------------
    // Workflow
    // ------------------------------------------------------------------

    /**
     * @param array{id:?int,role:string} $actor
     */
    public function assign(int $requestId, int $phlebotomistId, array $actor, ?string $note = null): void
    {
        $this->requireRole($actor, ['admin']);
        $this->app->db->transaction(function (Database $db) use ($requestId, $phlebotomistId, $actor, $note): void {
            $request = $this->lock($db, $requestId);
            if (!in_array($request['status'], ['submitted', 'assigned'], true)) {
                throw new WorkflowException('A phlebotomist can only be assigned before the sample is collected.');
            }
            $phleb = $db->one(
                'SELECT id, user_id, full_name, employee_code FROM phlebotomists
                  WHERE id = :id AND is_active = 1 AND deleted_at IS NULL',
                ['id' => $phlebotomistId]
            );
            if ($phleb === null) {
                throw new WorkflowException('Choose an active phlebotomist.');
            }
            $current = $db->one(
                'SELECT id, phlebotomist_id FROM test_assignments WHERE request_id = :r AND is_current = 1',
                ['r' => $requestId]
            );
            if ($current !== null && (int) $current['phlebotomist_id'] === $phlebotomistId) {
                throw new WorkflowException('That phlebotomist is already assigned to this request.');
            }
            $now = $this->app->clock->nowSql();
            if ($current !== null) {
                $db->execute('UPDATE test_assignments SET is_current = NULL, ended_at = :t WHERE id = :id', [
                    't' => $now, 'id' => (int) $current['id'],
                ]);
            }
            $db->insert(
                'INSERT INTO test_assignments (request_id, phlebotomist_id, assigned_by_user_id, assigned_at, is_current, note)
                 VALUES (:r, :p, :u, :t, 1, :n)',
                ['r' => $requestId, 'p' => $phlebotomistId, 'u' => $actor['id'], 't' => $now, 'n' => $note]
            );
            $label = $phleb['full_name'] . ' (' . $phleb['employee_code'] . ')';
            if ($request['status'] === 'submitted') {
                $this->move($db, $request, 'assigned', $actor, 'Assigned to ' . $label . ($note ? ' — ' . $note : ''));
            } else {
                $this->history($db, $requestId, 'assigned', 'assigned', $actor, 'Reassigned to ' . $label . ($note ? ' — ' . $note : ''));
            }
            if ($phleb['user_id'] !== null) {
                $this->app->service(NotificationService::class)->notify(
                    (int) $phleb['user_id'],
                    'assignment.new',
                    'New collection: ' . $request['reference'],
                    $request['city'] . ' · ' . fmt_date($request['preferred_date']) . ' · ' . Labels::get(Labels::SLOTS, $request['preferred_slot']),
                    '/staff'
                );
            }
            $this->app->service(AuditLog::class)->record('request.assigned', 'test_request', $requestId, ['phlebotomist_id' => $phlebotomistId]);
        });
    }

    /**
     * @param array<string,mixed> $input specimen, barcode, note
     * @param array{id:?int,role:string} $actor
     * @throws ValidationFailed
     */
    public function recordCollection(int $requestId, array $input, array $actor): void
    {
        $this->requireRole($actor, ['admin', 'phlebotomist']);
        $v = new Validator($input);
        $specimen = $v->choice('specimen', Labels::SPECIMENS, 'Specimen type');
        $barcode = $v->rule('barcode', 'sample_barcode');
        $note = $v->text('note', 'Collection note', 300, false);
        if ($v->fails()) {
            throw new ValidationFailed($v->errors());
        }
        try {
            $this->app->db->transaction(function (Database $db) use ($requestId, $specimen, $barcode, $note, $actor): void {
                $request = $this->lock($db, $requestId);
                $assignment = $db->one(
                    'SELECT a.phlebotomist_id, p.user_id FROM test_assignments a
                       JOIN phlebotomists p ON p.id = a.phlebotomist_id
                      WHERE a.request_id = :r AND a.is_current = 1',
                    ['r' => $requestId]
                );
                if ($assignment === null) {
                    throw new WorkflowException('Assign a phlebotomist before recording collection.');
                }
                if ($actor['role'] === 'phlebotomist' && (int) $assignment['user_id'] !== $actor['id']) {
                    throw new WorkflowException('This collection is not assigned to you.');
                }
                $db->insert(
                    'INSERT INTO sample_collections (request_id, phlebotomist_id, recorded_by_user_id, specimen, barcode, collected_at, note)
                     VALUES (:r, :p, :u, :s, :b, :t, :n)',
                    [
                        'r' => $requestId, 'p' => (int) $assignment['phlebotomist_id'], 'u' => $actor['id'],
                        's' => $specimen, 'b' => $barcode, 't' => $this->app->clock->nowSql(), 'n' => $note,
                    ]
                );
                $this->move($db, $request, 'sample_collected', $actor, Labels::SPECIMENS[$specimen] . ', tube ' . $barcode);
                if ($actor['role'] === 'phlebotomist') {
                    $this->app->service(NotificationService::class)->notifyRole('admin', 'sample.collected',
                        'Sample collected for ' . $request['reference'], 'Ready to send for laboratory processing.',
                        '/admin/requests/' . $requestId);
                }
            });
        } catch (PDOException $e) {
            if ($e->getCode() === '23000' && str_contains($e->getMessage(), 'uq_sample_barcode')) {
                throw new ValidationFailed(['barcode' => 'This barcode is already recorded against another sample.']);
            }
            throw $e;
        }
    }

    /** @param array{id:?int,role:string} $actor */
    public function startProcessing(int $requestId, array $actor, ?string $note = null): void
    {
        $this->requireRole($actor, ['admin', 'lab']);
        $this->app->db->transaction(function (Database $db) use ($requestId, $actor, $note): void {
            $request = $this->lock($db, $requestId);
            $this->move($db, $request, 'in_progress', $actor, $note ?: 'Sample received by the laboratory');
        });
    }

    /**
     * @param array<string,mixed> $input outcome, ct_value, remarks
     * @param array{id:?int,role:string} $actor
     * @throws ValidationFailed
     */
    public function recordResult(int $requestId, array $input, array $actor): void
    {
        $this->requireRole($actor, ['admin', 'lab']);
        $v = new Validator($input);
        $outcome = $v->choice('outcome', Labels::OUTCOMES, 'Result');
        $ct = $v->rule('ct_value', 'ct_value', false);
        $remarks = $v->text('remarks', 'Remarks', 500, false);
        if ($v->fails()) {
            throw new ValidationFailed($v->errors());
        }
        $this->app->db->transaction(function (Database $db) use ($requestId, $outcome, $ct, $remarks, $actor): void {
            $request = $this->lock($db, $requestId);
            if ($ct !== null && $request['test_type'] !== 'rt_pcr') {
                throw new ValidationFailed(['ct_value' => 'A Ct value applies only to RT-PCR tests.']);
            }
            if ($request['status'] !== 'in_progress') {
                throw new WorkflowException('Results can only be recorded while testing is in progress.');
            }
            $db->insert(
                'INSERT INTO test_results (request_id, outcome, ct_value, remarks, recorded_by_user_id, recorded_at)
                 VALUES (:r, :o, :ct, :rm, :u, :t)',
                ['r' => $requestId, 'o' => $outcome, 'ct' => $ct, 'rm' => $remarks, 'u' => $actor['id'], 't' => $this->app->clock->nowSql()]
            );
            $this->move($db, $request, 'completed', $actor, 'Result recorded; awaiting authorisation');
        });
    }

    /** @param array{id:?int,role:string} $actor */
    public function releaseResult(int $requestId, array $actor): void
    {
        $this->requireRole($actor, ['admin', 'lab']);
        $this->app->db->transaction(function (Database $db) use ($requestId, $actor): void {
            $request = $this->lock($db, $requestId);
            $db->execute(
                'UPDATE test_results SET released_by_user_id = :u, released_at = :t WHERE request_id = :r AND released_at IS NULL',
                ['u' => $actor['id'], 't' => $this->app->clock->nowSql(), 'r' => $requestId]
            );
            $this->move($db, $request, 'result_available', $actor, 'Result authorised and released to the patient');
            if ($db->value('SELECT outcome FROM test_results WHERE request_id = :r', ['r' => $requestId]) === 'positive') {
                $this->app->service(AlertEngine::class)->raise('covid.positive_released', ['state' => (string) $request['state_code']],
                    'Positive result released for ' . $request['reference'] . ' (' . $request['state_code'] . ').', '/admin/requests/' . $requestId);
            }
        });
    }

    /** @param array{id:?int,role:string} $actor */
    public function reject(int $requestId, array $actor, string $reason): void
    {
        $this->requireRole($actor, ['admin', 'lab']);
        $reason = trim($reason);
        if (mb_strlen($reason) < 5 || mb_strlen($reason) > 300) {
            throw new ValidationFailed(['reason' => 'Give a reason of 5–300 characters.']);
        }
        $this->app->db->transaction(function (Database $db) use ($requestId, $actor, $reason): void {
            $request = $this->lock($db, $requestId);
            $this->move($db, $request, 'rejected', $actor, $reason, $reason);
            $this->endAssignment($db, $requestId);
        });
    }

    /**
     * Cancel by the owning patient (before collection) or by an admin.
     *
     * @param array{id:?int,role:string} $actor
     */
    public function cancel(int $requestId, array $actor, string $reason = ''): void
    {
        $this->requireRole($actor, ['admin', 'patient']);
        $reason = trim($reason) !== '' ? mb_substr(trim($reason), 0, 300) : 'Cancelled at the patient’s request';
        $this->app->db->transaction(function (Database $db) use ($requestId, $actor, $reason): void {
            $request = $this->lock($db, $requestId);
            if ($actor['role'] === 'patient') {
                $owner = $db->value('SELECT user_id FROM patients WHERE id = :id', ['id' => (int) $request['patient_id']]);
                if ($owner === null || (int) $owner !== $actor['id']) {
                    throw new WorkflowException('Request not found.');
                }
            }
            $this->move($db, $request, 'cancelled', $actor, $reason, $reason);
            $this->endAssignment($db, $requestId);
            if ($actor['role'] === 'patient') {
                $this->app->service(NotificationService::class)->notifyRole('admin', 'request.cancelled',
                    $request['reference'] . ' cancelled by patient', $reason, '/admin/requests/' . $requestId);
            }
        });
    }

    /**
     * Attach a guest request to the signed-in patient's account. The guest
     * record must have the same mobile number as the account and the date of
     * birth must match.
     */
    public function linkToAccount(int $userId, string $reference, string $dob): ?string
    {
        $patient = $this->app->service(PatientService::class)->forUser($userId);
        if ($patient === null) {
            return null;
        }
        return $this->app->db->transaction(function (Database $db) use ($patient, $userId, $reference, $dob): ?string {
            $row = $db->one(
                'SELECT r.id, r.reference, r.status, r.patient_id FROM test_requests r JOIN patients p ON p.id = r.patient_id
                  WHERE r.reference = :ref AND p.user_id IS NULL AND p.mobile = :m AND p.date_of_birth = :d FOR UPDATE',
                ['ref' => $reference, 'm' => $patient['mobile'], 'd' => $dob]
            );
            if ($row === null) {
                return null;
            }
            $db->execute('UPDATE test_requests SET patient_id = :p WHERE id = :id', ['p' => (int) $patient['id'], 'id' => (int) $row['id']]);
            $this->history($db, (int) $row['id'], $row['status'], $row['status'], ['id' => $userId, 'role' => 'patient'], 'Linked to the patient’s account');
            $this->app->service(AuditLog::class)->record('request.linked', 'test_request', (int) $row['id'], ['from_patient' => (int) $row['patient_id']], $userId);
            return (string) $row['reference'];
        });
    }

    // ------------------------------------------------------------------
    // Queries
    // ------------------------------------------------------------------

    /** @return array<string,mixed>|null */
    public function detail(int $requestId): ?array
    {
        $db = $this->app->db;
        $request = $db->one(
            'SELECT r.*, p.full_name, p.date_of_birth, p.sex, p.mobile, p.email, p.user_id AS patient_user_id,
                    s.name AS state_name
               FROM test_requests r JOIN patients p ON p.id = r.patient_id
               JOIN states s ON s.code = r.state_code
              WHERE r.id = :id',
            ['id' => $requestId]
        );
        if ($request === null) {
            return null;
        }
        $request['symptoms'] = array_column($db->all('SELECT symptom FROM test_request_symptoms WHERE request_id = :id', ['id' => $requestId]), 'symptom');
        $request['assignment'] = $db->one(
            'SELECT a.*, ph.full_name AS phlebotomist_name, ph.employee_code, ph.mobile AS phlebotomist_mobile
               FROM test_assignments a JOIN phlebotomists ph ON ph.id = a.phlebotomist_id
              WHERE a.request_id = :id AND a.is_current = 1',
            ['id' => $requestId]
        );
        $request['sample'] = $db->one(
            'SELECT sc.*, ph.full_name AS phlebotomist_name FROM sample_collections sc
               JOIN phlebotomists ph ON ph.id = sc.phlebotomist_id WHERE sc.request_id = :id',
            ['id' => $requestId]
        );
        $request['result'] = $db->one(
            'SELECT tr.*, ru.full_name AS recorded_by_name, rl.full_name AS released_by_name
               FROM test_results tr
               LEFT JOIN users ru ON ru.id = tr.recorded_by_user_id
               LEFT JOIN users rl ON rl.id = tr.released_by_user_id
              WHERE tr.request_id = :id',
            ['id' => $requestId]
        );
        $request['history'] = $db->all(
            'SELECT h.*, u.full_name AS actor_name FROM test_status_history h
               LEFT JOIN users u ON u.id = h.actor_user_id
              WHERE h.request_id = :id ORDER BY h.created_at, h.id',
            ['id' => $requestId]
        );
        return $request;
    }

    /**
     * Detail only if the request belongs to this patient account.
     *
     * @return array<string,mixed>|null
     */
    public function detailForPatient(int $userId, int $requestId): ?array
    {
        $owned = $this->app->db->value(
            'SELECT r.id FROM test_requests r JOIN patients p ON p.id = r.patient_id WHERE r.id = :id AND p.user_id = :u',
            ['id' => $requestId, 'u' => $userId]
        );
        return $owned === null ? null : $this->detail($requestId);
    }

    /** @return list<array<string,mixed>> */
    public function forPatientUser(int $userId, ?bool $activeOnly = null): array
    {
        $filter = match ($activeOnly) {
            true => " AND r.status IN ('submitted','assigned','sample_collected','in_progress','completed')",
            false => " AND r.status IN ('result_available','cancelled','rejected')",
            null => '',
        };
        return $this->app->db->all(
            "SELECT r.id, r.reference, r.test_type, r.status, r.submitted_at, r.preferred_date, r.preferred_slot,
                    r.collection_mode, r.city, tr.outcome, tr.released_at
               FROM test_requests r JOIN patients p ON p.id = r.patient_id
               LEFT JOIN test_results tr ON tr.request_id = r.id
              WHERE p.user_id = :u{$filter}
              ORDER BY r.submitted_at DESC, r.id DESC",
            ['u' => $userId]
        );
    }

    /** Guest tracking: the reference, mobile and date of birth must all match. */
    public function trackGuest(string $reference, string $mobile, string $dob): ?int
    {
        $id = $this->app->db->value(
            'SELECT r.id FROM test_requests r JOIN patients p ON p.id = r.patient_id
              WHERE r.reference = :ref AND p.mobile = :m AND p.date_of_birth = :d',
            ['ref' => $reference, 'm' => $mobile, 'd' => $dob]
        );
        return $id === null ? null : (int) $id;
    }

    /** @return list<array<string,mixed>> current assignments for a phlebotomist user */
    public function tasksForPhlebotomistUser(int $userId, bool $open = true): array
    {
        if ($open) {
            // Current assignments still waiting for collection, in visit order.
            return $this->app->db->all(
                "SELECT r.id, r.reference, r.status, r.test_type, r.collection_mode, r.collection_address, r.city, r.pincode,
                        r.preferred_date, r.preferred_slot, r.notes, p.full_name AS patient_name, p.mobile AS patient_mobile,
                        a.assigned_at
                   FROM test_assignments a
                   JOIN phlebotomists ph ON ph.id = a.phlebotomist_id
                   JOIN test_requests r ON r.id = a.request_id
                   JOIN patients p ON p.id = r.patient_id
                  WHERE ph.user_id = :u AND a.is_current = 1 AND r.status = 'assigned'
                  ORDER BY r.preferred_date, FIELD(r.preferred_slot, 'morning','afternoon','evening'), r.id
                  LIMIT 100",
                ['u' => $userId]
            );
        }
        // Samples this phlebotomist collected, newest first.
        return $this->app->db->all(
            'SELECT r.id, r.reference, r.status, r.test_type, sc.collected_at
               FROM sample_collections sc
               JOIN phlebotomists ph ON ph.id = sc.phlebotomist_id
               JOIN test_requests r ON r.id = sc.request_id
              WHERE ph.user_id = :u
              ORDER BY sc.collected_at DESC, r.id DESC
              LIMIT 100',
            ['u' => $userId]
        );
    }

    // ------------------------------------------------------------------
    // Internals
    // ------------------------------------------------------------------

    /** @return array<string,mixed> */
    private function lock(Database $db, int $requestId): array
    {
        $row = $db->one('SELECT * FROM test_requests WHERE id = :id FOR UPDATE', ['id' => $requestId]);
        if ($row === null) {
            throw new WorkflowException('Request not found.');
        }
        return $row;
    }

    /**
     * @param array<string,mixed> $request locked row
     * @param array{id:?int,role:string} $actor
     */
    private function move(Database $db, array $request, string $to, array $actor, ?string $note, ?string $closedReason = null): void
    {
        $from = (string) $request['status'];
        if (!self::canTransition($from, $to)) {
            throw new WorkflowException(sprintf('A request cannot move from “%s” to “%s”.', status_label($from), status_label($to)));
        }
        $changed = $db->execute(
            'UPDATE test_requests SET status = :to, closed_reason = COALESCE(:cr, closed_reason) WHERE id = :id AND status = :from',
            ['to' => $to, 'cr' => $closedReason, 'id' => (int) $request['id'], 'from' => $from]
        );
        if ($changed !== 1) {
            throw new WorkflowException('This request was changed by someone else. Reload the page and try again.');
        }
        $this->history($db, (int) $request['id'], $from, $to, $actor, $note);
        $this->app->service(AuditLog::class)->record('request.status', 'test_request', (int) $request['id'], ['from' => $from, 'to' => $to], $actor['id']);

        $owner = $db->value('SELECT user_id FROM patients WHERE id = :id', ['id' => (int) $request['patient_id']]);
        $messages = [
            'assigned' => ['Phlebotomist assigned', 'Your sample collection for ' . $request['reference'] . ' has been scheduled.'],
            'sample_collected' => ['Sample collected', 'Your sample for ' . $request['reference'] . ' is on its way to the laboratory.'],
            'result_available' => ['Your result is ready', 'The authorised result for ' . $request['reference'] . ' is available to view.'],
            'cancelled' => ['Request cancelled', 'Request ' . $request['reference'] . ' has been cancelled.'],
            'rejected' => ['Request could not be processed', 'Request ' . $request['reference'] . ' was rejected. See the request for details.'],
        ];
        if ($owner !== null && isset($messages[$to]) && (int) $owner !== $actor['id']) {
            $this->app->service(NotificationService::class)->notify((int) $owner, 'request.' . $to,
                $messages[$to][0], $messages[$to][1], '/patient/requests/' . (int) $request['id']);
        }
    }

    /** @param array{id:?int,role:string} $actor */
    private function history(Database $db, int $requestId, ?string $from, string $to, array $actor, ?string $note): void
    {
        $db->insert(
            'INSERT INTO test_status_history (request_id, from_status, to_status, actor_user_id, actor_role, note, created_at)
             VALUES (:r, :f, :t, :u, :role, :n, :at)',
            [
                'r' => $requestId, 'f' => $from, 't' => $to, 'u' => $actor['id'], 'role' => $actor['role'],
                'n' => $note === null ? null : mb_substr($note, 0, 500), 'at' => $this->app->clock->nowSql(),
            ]
        );
    }

    private function endAssignment(Database $db, int $requestId): void
    {
        $db->execute('UPDATE test_assignments SET is_current = NULL, ended_at = :t WHERE request_id = :r AND is_current = 1', [
            't' => $this->app->clock->nowSql(), 'r' => $requestId,
        ]);
    }

    /**
     * @param array{id:?int,role:string} $actor
     * @param list<string> $roles
     */
    private function requireRole(array $actor, array $roles): void
    {
        if (!in_array($actor['role'], $roles, true) || $actor['id'] === null) {
            throw new WorkflowException('You are not allowed to perform this action.');
        }
    }
}
