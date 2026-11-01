<?php

declare(strict_types=1);

namespace Vitaliq\Services;

use Vitaliq\Core\App;
use Vitaliq\Core\HttpException;
use Vitaliq\Validation\Validator;

/**
 * Patient-side records: family profiles, health passport, immunisations,
 * emergency contacts, the health timeline and feedback (features 2, 3, 8,
 * 9, 10). Every method that takes a patient id first checks that the
 * signed-in account holder owns or manages that profile.
 */
final class PatientPlatformService
{
    public const RELATIONSHIPS = [
        'spouse' => 'Spouse or partner', 'child' => 'Child', 'parent' => 'Parent',
        'sibling' => 'Sibling', 'grandparent' => 'Grandparent', 'other' => 'Other',
    ];
    public const BLOOD_GROUPS = ['A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-', 'unknown'];
    public const FEEDBACK_CATEGORIES = [
        'booking' => 'Booking', 'sample_collection' => 'Sample collection', 'results' => 'Results',
        'staff' => 'Staff', 'website' => 'Website', 'other' => 'Other',
    ];
    public const MAX_FAMILY = 8;
    public const MAX_CONTACTS = 5;

    public function __construct(private readonly App $app)
    {
    }

    /**
     * The holder's own record first, then the family profiles they manage.
     *
     * @return list<array<string,mixed>>
     */
    public function profiles(int $userId): array
    {
        return $this->app->db->all(
            "SELECT p.*, IF(p.user_id = :u1, 'self', p.relationship) AS rel
               FROM patients p
              WHERE p.user_id = :u2 OR p.managed_by_user_id = :u3
              ORDER BY (p.user_id = :u4) DESC, p.full_name",
            ['u1' => $userId, 'u2' => $userId, 'u3' => $userId, 'u4' => $userId]
        );
    }

    /**
     * Resolve the profile to work on: the requested one if the user owns or
     * manages it, otherwise the user's own record. A foreign id is a 404.
     *
     * @return array<string,mixed>
     */
    public function profile(int $userId, ?int $patientId = null): array
    {
        foreach ($this->profiles($userId) as $p) {
            if ($patientId === null ? $p['rel'] === 'self' : (int) $p['id'] === $patientId) {
                return $p;
            }
        }
        throw new HttpException(404);
    }

    /** @param array<string,mixed> $input */
    public function addFamilyMember(int $userId, array $input): int
    {
        $holder = $this->profile($userId);
        $v = new Validator($input);
        $today = $this->app->clock->now();
        $name = $v->rule('full_name', 'full_name');
        $dob = $v->date('date_of_birth', 'Date of birth', $today->modify('-120 years'), $today);
        $sex = $v->choice('sex', ['female' => 'Female', 'male' => 'Male', 'other' => 'Other', 'undisclosed' => 'Undisclosed'], 'Sex', false) ?? 'undisclosed';
        $rel = $v->choice('relationship', self::RELATIONSHIPS, 'Relationship');
        $mobile = $v->rule('mobile', 'indian_mobile', false, 'Mobile number');
        if (count($this->profiles($userId)) > self::MAX_FAMILY) {
            $v->addError('full_name', 'You can manage up to ' . self::MAX_FAMILY . ' family profiles.');
        }
        if ($v->fails()) {
            throw new ValidationFailed($v->errors());
        }
        $id = $this->app->db->insert(
            'INSERT INTO patients (user_id, managed_by_user_id, relationship, full_name, date_of_birth, sex, mobile, city, state_code, pincode)
             VALUES (NULL, :m, :r, :n, :d, :s, :mob, :c, :st, :pin)',
            ['m' => $userId, 'r' => $rel, 'n' => $name, 'd' => $dob, 's' => $sex, 'mob' => $mobile ?? $holder['mobile'],
             'c' => $holder['city'], 'st' => $holder['state_code'], 'pin' => $holder['pincode']]
        );
        $this->app->service(AuditLog::class)->record('family.added', 'patient', $id, ['relationship' => $rel]);
        return $id;
    }

    public function removeFamilyMember(int $userId, int $patientId): void
    {
        $p = $this->profile($userId, $patientId);
        if ($p['rel'] === 'self') {
            throw new WorkflowException('Your own record cannot be removed here.');
        }
        // Detach rather than delete: test history must survive.
        $this->app->db->execute('UPDATE patients SET managed_by_user_id = NULL WHERE id = :id', ['id' => $patientId]);
        $this->app->service(AuditLog::class)->record('family.removed', 'patient', $patientId);
    }

    /* ------------------------------------------------------------ passport */

    /** @return array<string,mixed> */
    public function passport(int $patientId): array
    {
        $profile = $this->app->db->one('SELECT * FROM health_profiles WHERE patient_id = :p', ['p' => $patientId]);
        return [
            'profile' => $profile ?? ['blood_group' => 'unknown', 'height_cm' => null, 'weight_kg' => null, 'allergies' => null,
                'conditions' => null, 'medications' => null, 'organ_donor' => 'undecided', 'updated_at' => null],
            'immunisations' => $this->app->db->all('SELECT * FROM immunisations WHERE patient_id = :p ORDER BY given_on DESC, id DESC', ['p' => $patientId]),
            'contacts' => $this->contacts($patientId),
            'results' => $this->app->db->all(
                "SELECT r.id, r.reference, r.test_type, res.outcome, res.released_at
                   FROM test_requests r JOIN test_results res ON res.request_id = r.id
                  WHERE r.patient_id = :p AND r.status = 'result_available'
                  ORDER BY res.released_at DESC LIMIT 5",
                ['p' => $patientId]
            ),
        ];
    }

    /** @param array<string,mixed> $input */
    public function savePassport(int $patientId, array $input): void
    {
        $v = new Validator($input);
        $blood = $v->choice('blood_group', array_fill_keys(self::BLOOD_GROUPS, ''), 'Blood group');
        $height = $this->number($v, $input, 'height_cm', 'Height', 30, 250, 0);
        $weight = $this->number($v, $input, 'weight_kg', 'Weight', 1, 400, 1);
        $allergies = $v->text('allergies', 'Allergies', 500, false);
        $conditions = $v->text('conditions', 'Long-term conditions', 500, false);
        $medications = $v->text('medications', 'Current medicines', 500, false);
        $donor = $v->choice('organ_donor', ['yes' => 'Yes', 'no' => 'No', 'undecided' => 'Undecided'], 'Organ donor', false) ?? 'undecided';
        if ($v->fails()) {
            throw new ValidationFailed($v->errors());
        }
        $this->app->db->execute(
            'INSERT INTO health_profiles (patient_id, blood_group, height_cm, weight_kg, allergies, conditions, medications, organ_donor, updated_at)
             VALUES (:p, :b, :h, :w, :a, :c, :m, :o, :t)
             ON DUPLICATE KEY UPDATE blood_group = VALUES(blood_group), height_cm = VALUES(height_cm), weight_kg = VALUES(weight_kg),
               allergies = VALUES(allergies), conditions = VALUES(conditions), medications = VALUES(medications),
               organ_donor = VALUES(organ_donor), updated_at = VALUES(updated_at)',
            ['p' => $patientId, 'b' => $blood, 'h' => $height, 'w' => $weight, 'a' => $allergies ?: null, 'c' => $conditions ?: null,
             'm' => $medications ?: null, 'o' => $donor, 't' => $this->app->clock->nowSql()]
        );
        $this->app->service(AuditLog::class)->record('passport.updated', 'patient', $patientId);
    }

    /** @param array<string,mixed> $input */
    public function addImmunisation(int $patientId, array $input): int
    {
        $v = new Validator($input);
        $today = $this->app->clock->now();
        $vaccine = $v->text('vaccine', 'Vaccine', 80, true, 2);
        $dose = $v->text('dose_label', 'Dose', 30, false);
        $given = $v->date('given_on', 'Date given', $today->modify('-120 years'), $today);
        $provider = $v->text('provider', 'Given by', 120, false);
        if ($v->fails()) {
            throw new ValidationFailed($v->errors());
        }
        return $this->app->db->insert(
            'INSERT INTO immunisations (patient_id, vaccine, dose_label, given_on, provider, created_at) VALUES (:p, :v, :d, :g, :pr, :t)',
            ['p' => $patientId, 'v' => $vaccine, 'd' => $dose ?: null, 'g' => $given, 'pr' => $provider ?: null, 't' => $this->app->clock->nowSql()]
        );
    }

    public function deleteImmunisation(int $patientId, int $id): bool
    {
        return $this->app->db->execute('DELETE FROM immunisations WHERE id = :id AND patient_id = :p', ['id' => $id, 'p' => $patientId]) === 1;
    }

    /* ------------------------------------------------- emergency contacts */

    /** @return list<array<string,mixed>> */
    public function contacts(int $patientId): array
    {
        return $this->app->db->all('SELECT * FROM emergency_contacts WHERE patient_id = :p ORDER BY is_primary DESC, id', ['p' => $patientId]);
    }

    /** @param array<string,mixed> $input */
    public function addContact(int $patientId, array $input): int
    {
        $v = new Validator($input);
        $name = $v->rule('full_name', 'full_name', true, 'Contact name');
        $rel = $v->text('relationship', 'Relationship', 40, true, 2);
        $mobile = $v->rule('mobile', 'indian_mobile', true, 'Mobile number');
        $primary = ($input['is_primary'] ?? '') === '1';
        if (count($this->contacts($patientId)) >= self::MAX_CONTACTS) {
            $v->addError('full_name', 'You can keep up to ' . self::MAX_CONTACTS . ' emergency contacts.');
        }
        if ($v->fails()) {
            throw new ValidationFailed($v->errors());
        }
        return $this->app->db->transaction(function ($db) use ($patientId, $name, $rel, $mobile, $primary): int {
            $first = (int) $db->value('SELECT COUNT(*) FROM emergency_contacts WHERE patient_id = :p', ['p' => $patientId]) === 0;
            if ($primary) {
                $db->execute('UPDATE emergency_contacts SET is_primary = 0 WHERE patient_id = :p', ['p' => $patientId]);
            }
            return $db->insert(
                'INSERT INTO emergency_contacts (patient_id, full_name, relationship, mobile, is_primary, created_at) VALUES (:p, :n, :r, :m, :pr, :t)',
                ['p' => $patientId, 'n' => $name, 'r' => $rel, 'm' => $mobile, 'pr' => ($primary || $first) ? 1 : 0, 't' => $this->app->clock->nowSql()]
            );
        });
    }

    public function makePrimaryContact(int $patientId, int $id): bool
    {
        return $this->app->db->transaction(function ($db) use ($patientId, $id): bool {
            if ($db->value('SELECT id FROM emergency_contacts WHERE id = :id AND patient_id = :p', ['id' => $id, 'p' => $patientId]) === null) {
                return false;
            }
            $db->execute('UPDATE emergency_contacts SET is_primary = (id = :id) WHERE patient_id = :p', ['id' => $id, 'p' => $patientId]);
            return true;
        });
    }

    public function deleteContact(int $patientId, int $id): bool
    {
        return $this->app->db->transaction(function ($db) use ($patientId, $id): bool {
            $wasPrimary = (int) $db->value('SELECT is_primary FROM emergency_contacts WHERE id = :id AND patient_id = :p', ['id' => $id, 'p' => $patientId]);
            $deleted = $db->execute('DELETE FROM emergency_contacts WHERE id = :id AND patient_id = :p', ['id' => $id, 'p' => $patientId]) === 1;
            if ($deleted && $wasPrimary === 1) {
                $db->execute('UPDATE emergency_contacts SET is_primary = 1 WHERE patient_id = :p ORDER BY id LIMIT 1', ['p' => $patientId]);
            }
            return $deleted;
        });
    }

    /* ------------------------------------------------------------ timeline */

    /**
     * One dated list of everything on a profile, newest first (feature 9).
     *
     * @return list<array{at:string,kind:string,title:string,detail:string,link:?string}>
     */
    public function timeline(int $patientId, int $limit = 100): array
    {
        $db = $this->app->db;
        $p = ['p' => $patientId];
        $events = [];
        foreach ($db->all('SELECT id, reference, test_type, submitted_at FROM test_requests WHERE patient_id = :p', $p) as $r) {
            $events[] = ['at' => $r['submitted_at'], 'kind' => 'request', 'title' => 'Test requested', 'detail' => $r['reference'], 'link' => '/patient/requests/' . $r['id']];
        }
        foreach ($db->all('SELECT r.id, r.reference, s.collected_at FROM sample_collections s JOIN test_requests r ON r.id = s.request_id WHERE r.patient_id = :p', $p) as $r) {
            $events[] = ['at' => $r['collected_at'], 'kind' => 'sample', 'title' => 'Sample collected', 'detail' => $r['reference'], 'link' => '/patient/requests/' . $r['id']];
        }
        foreach ($db->all("SELECT r.id, r.reference, res.released_at, res.outcome FROM test_results res JOIN test_requests r ON r.id = res.request_id WHERE r.patient_id = :p AND res.released_at IS NOT NULL", $p) as $r) {
            $events[] = ['at' => $r['released_at'], 'kind' => 'result', 'title' => 'Result released', 'detail' => $r['reference'] . ' · ' . ucfirst((string) $r['outcome']), 'link' => '/patient/requests/' . $r['id']];
        }
        foreach ($db->all('SELECT id, kind, starts_at, status FROM appointments WHERE patient_id = :p', $p) as $a) {
            $events[] = ['at' => $a['starts_at'], 'kind' => 'appointment', 'title' => AppointmentService::KINDS[$a['kind']] ?? 'Appointment', 'detail' => ucfirst((string) $a['status']), 'link' => '/patient/appointments/' . $a['id']];
        }
        foreach ($db->all('SELECT vaccine, dose_label, given_on FROM immunisations WHERE patient_id = :p', $p) as $i) {
            $events[] = ['at' => $i['given_on'] . ' 00:00:00', 'kind' => 'immunisation', 'title' => 'Immunisation', 'detail' => trim($i['vaccine'] . ' ' . ($i['dose_label'] ?? '')), 'link' => null];
        }
        foreach ($db->all('SELECT id, title, created_at FROM patient_documents WHERE patient_id = :p AND deleted_at IS NULL', $p) as $d) {
            $events[] = ['at' => $d['created_at'], 'kind' => 'document', 'title' => 'Document added', 'detail' => $d['title'], 'link' => null];
        }
        usort($events, static fn ($a, $b) => strcmp((string) $b['at'], (string) $a['at']));
        return array_slice($events, 0, $limit);
    }

    /* ------------------------------------------------------------ feedback */

    /** @param array<string,mixed> $input */
    public function submitFeedback(int $userId, array $input): int
    {
        $v = new Validator($input);
        $rating = (int) ($input['rating'] ?? 0);
        if ($rating < 1 || $rating > 5) {
            $v->addError('rating', 'Choose a rating from 1 to 5.');
        }
        $category = $v->choice('category', self::FEEDBACK_CATEGORIES, 'Topic');
        $comments = $v->text('comments', 'Comments', 1000, false);
        $requestId = (int) ($input['request_id'] ?? 0);
        if ($requestId > 0 && $this->app->service(TestingService::class)->detailForPatient($userId, $requestId) === null) {
            $v->addError('request_id', 'Choose one of your own requests.');
        }
        if (!$this->app->rateLimiter()->hit('feedback:' . $userId, 5, 86400)) {
            $v->addError('comments', 'You can send up to 5 pieces of feedback a day.');
        }
        if ($v->fails()) {
            throw new ValidationFailed($v->errors());
        }
        return $this->app->db->insert(
            'INSERT INTO patient_feedback (user_id, request_id, rating, category, comments, created_at) VALUES (:u, :r, :ra, :c, :co, :t)',
            ['u' => $userId, 'r' => $requestId > 0 ? $requestId : null, 'ra' => $rating, 'c' => $category, 'co' => $comments ?: null, 't' => $this->app->clock->nowSql()]
        );
    }

    /** @return list<array<string,mixed>> */
    public function feedbackForUser(int $userId): array
    {
        return $this->app->db->all('SELECT f.*, r.reference FROM patient_feedback f LEFT JOIN test_requests r ON r.id = f.request_id WHERE f.user_id = :u ORDER BY f.id DESC LIMIT 20', ['u' => $userId]);
    }

    /** @return array{rows:list<array<string,mixed>>,summary:array<string,mixed>} */
    public function feedbackForAdmin(string $status = ''): array
    {
        $where = $status !== '' ? 'WHERE f.status = :s' : '';
        $params = $status !== '' ? ['s' => $status] : [];
        $rows = $this->app->db->all(
            "SELECT f.*, u.full_name, r.reference FROM patient_feedback f
               LEFT JOIN users u ON u.id = f.user_id LEFT JOIN test_requests r ON r.id = f.request_id
               {$where} ORDER BY f.id DESC LIMIT 100",
            $params
        );
        $summary = $this->app->db->one(
            "SELECT COUNT(*) AS total, AVG(rating) AS avg_rating, SUM(status = 'new') AS unread,
                    SUM(rating <= 2) AS low FROM patient_feedback"
        ) ?? [];
        $byCategory = $this->app->db->all('SELECT category, COUNT(*) AS n, AVG(rating) AS avg_rating FROM patient_feedback GROUP BY category ORDER BY n DESC');
        return ['rows' => $rows, 'summary' => $summary + ['by_category' => $byCategory]];
    }

    public function reviewFeedback(int $id, string $status, string $note, int $adminId): bool
    {
        if (!in_array($status, ['reviewed', 'actioned'], true)) {
            throw new ValidationFailed(['status' => 'Choose reviewed or actioned.']);
        }
        $note = mb_substr(trim($note), 0, 500);
        return $this->app->db->execute(
            'UPDATE patient_feedback SET status = :s, admin_note = :n, reviewed_by_user_id = :a, reviewed_at = :t WHERE id = :id',
            ['s' => $status, 'n' => $note !== '' ? $note : null, 'a' => $adminId, 't' => $this->app->clock->nowSql(), 'id' => $id]
        ) === 1;
    }

    /** @param array<string,mixed> $input */
    private function number(Validator $v, array $input, string $field, string $label, float $min, float $max, int $decimals): ?string
    {
        $raw = trim((string) ($input[$field] ?? ''));
        if ($raw === '') {
            return null;
        }
        $pattern = $decimals === 0 ? '/^\d{1,3}$/' : '/^\d{1,3}(\.\d)?$/';
        if (!preg_match($pattern, $raw) || (float) $raw < $min || (float) $raw > $max) {
            $v->addError($field, "{$label} must be a number from {$min} to {$max}.");
            return null;
        }
        return $raw;
    }
}
