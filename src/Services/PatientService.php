<?php

declare(strict_types=1);

namespace Vitaliq\Services;

use DateTimeImmutable;
use PDOException;
use Vitaliq\Auth\Passwords;
use Vitaliq\Core\App;
use Vitaliq\Support\Labels;
use Vitaliq\Validation\Validator;

/**
 * Patient accounts and demographic records.
 */
final class PatientService
{
    public function __construct(private readonly App $app)
    {
    }

    /** @return array<string,string> code => name */
    public function states(): array
    {
        $rows = $this->app->db->all('SELECT code, name FROM states ORDER BY name');
        return array_column($rows, 'name', 'code');
    }

    /**
     * Shared demographic validation for registration, profile and guest forms.
     *
     * @param array<string,mixed> $input
     * @return array<string,?string>
     */
    public function validateDemographics(Validator $v, array $input, bool $requireAddress): array
    {
        $today = $this->app->clock->now();
        return [
            'full_name' => $v->rule('full_name', 'full_name'),
            'date_of_birth' => $v->date('date_of_birth', 'Date of birth', $today->modify('-120 years'), $today),
            'sex' => $v->choice('sex', Labels::SEX, 'Sex', false) ?? 'undisclosed',
            'mobile' => $v->rule('mobile', 'indian_mobile', true, 'Mobile number'),
            'address_line' => $v->text('address_line', 'Address', 200, $requireAddress, 5),
            'city' => $v->text('city', 'City', 80, $requireAddress, 2),
            'state_code' => $v->choice('state_code', $this->states(), 'State or union territory', $requireAddress),
            'pincode' => $v->rule('pincode', 'pincode', $requireAddress),
        ];
    }

    /**
     * Create a patient account and its demographic record in one transaction.
     *
     * @param array<string,mixed> $input
     * @throws ValidationFailed
     */
    public function register(array $input): int
    {
        $v = new Validator($input);
        $data = $this->validateDemographics($v, $input, false);
        $email = $v->rule('email', 'email');
        $password = (string) ($input['password'] ?? '');
        $policy = Passwords::policyError($password, (string) $email);
        if ($policy !== null) {
            $v->addError('password', $policy);
        } elseif (!hash_equals($password, (string) ($input['password_confirmation'] ?? ''))) {
            $v->addError('password_confirmation', 'The two passwords do not match.');
        }
        $v->accepted('consent', 'Please confirm you have read how Vitaliq uses your information.');

        if (!$v->hasError('email') && $email !== null && $this->emailTaken($email)) {
            $v->addError('email', 'An account with this email already exists. Sign in or reset your password.');
        }
        if (!$v->hasError('mobile') && $data['mobile'] !== null && $this->mobileTaken((string) $data['mobile'])) {
            $v->addError('mobile', 'This mobile number is already linked to an account.');
        }
        if ($v->fails()) {
            throw new ValidationFailed($v->errors());
        }

        try {
            return $this->app->db->transaction(function ($db) use ($data, $email, $password): int {
                $userId = $db->insert(
                    'INSERT INTO users (role, full_name, email, mobile, password_hash) VALUES (\'patient\', :n, :e, :m, :h)',
                    ['n' => $data['full_name'], 'e' => $email, 'm' => $data['mobile'], 'h' => Passwords::hash($password)]
                );
                $db->insert(
                    'INSERT INTO patients (user_id, full_name, date_of_birth, sex, mobile, email, address_line, city, state_code, pincode)
                     VALUES (:u, :n, :d, :s, :m, :e, :a, :c, :st, :p)',
                    [
                        'u' => $userId, 'n' => $data['full_name'], 'd' => $data['date_of_birth'], 's' => $data['sex'],
                        'm' => $data['mobile'], 'e' => $email, 'a' => $data['address_line'], 'c' => $data['city'],
                        'st' => $data['state_code'], 'p' => $data['pincode'],
                    ]
                );
                $this->app->service(AuditLog::class)->record('patient.registered', 'user', $userId, [], $userId);
                return $userId;
            });
        } catch (PDOException $e) {
            if ($e->getCode() === '23000') {
                // A concurrent registration won the unique key race.
                throw new ValidationFailed(['email' => 'An account with these details already exists.']);
            }
            throw $e;
        }
    }

    public function emailTaken(string $email, ?int $exceptUserId = null): bool
    {
        return $this->app->db->value(
            'SELECT 1 FROM users WHERE email = :e AND id <> :id',
            ['e' => mb_strtolower($email), 'id' => $exceptUserId ?? 0]
        ) !== null;
    }

    public function mobileTaken(string $mobile, ?int $exceptUserId = null): bool
    {
        return $this->app->db->value(
            'SELECT 1 FROM users WHERE mobile = :m AND id <> :id',
            ['m' => $mobile, 'id' => $exceptUserId ?? 0]
        ) !== null;
    }

    /** @return array<string,mixed>|null patient row joined with user email */
    public function forUser(int $userId): ?array
    {
        return $this->app->db->one(
            'SELECT p.*, u.email AS account_email, u.created_at AS member_since, s.name AS state_name
               FROM patients p JOIN users u ON u.id = p.user_id
               LEFT JOIN states s ON s.code = p.state_code
              WHERE p.user_id = :u',
            ['u' => $userId]
        );
    }

    /**
     * @param array<string,mixed> $input
     * @throws ValidationFailed
     */
    public function updateProfile(int $userId, array $input): void
    {
        $v = new Validator($input);
        $data = $this->validateDemographics($v, $input, false);
        if (!$v->hasError('mobile') && $data['mobile'] !== null && $this->mobileTaken((string) $data['mobile'], $userId)) {
            $v->addError('mobile', 'This mobile number is already linked to another account.');
        }
        if ($v->fails()) {
            throw new ValidationFailed($v->errors());
        }
        $this->app->db->transaction(function ($db) use ($userId, $data): void {
            $db->execute('UPDATE users SET full_name = :n, mobile = :m WHERE id = :id', [
                'n' => $data['full_name'], 'm' => $data['mobile'], 'id' => $userId,
            ]);
            $db->execute(
                'UPDATE patients SET full_name = :n, date_of_birth = :d, sex = :s, mobile = :m, address_line = :a,
                        city = :c, state_code = :st, pincode = :p WHERE user_id = :u',
                [
                    'n' => $data['full_name'], 'd' => $data['date_of_birth'], 's' => $data['sex'], 'm' => $data['mobile'],
                    'a' => $data['address_line'], 'c' => $data['city'], 'st' => $data['state_code'], 'p' => $data['pincode'],
                    'u' => $userId,
                ]
            );
            $this->app->service(AuditLog::class)->record('profile.updated', 'user', $userId);
        });
    }

    /**
     * Staff profile (name and mobile only).
     *
     * @param array<string,mixed> $input
     * @throws ValidationFailed
     */
    public function updateStaffProfile(int $userId, array $input): void
    {
        $v = new Validator($input);
        $name = $v->rule('full_name', 'full_name');
        $mobile = $v->rule('mobile', 'indian_mobile', false, 'Mobile number');
        if ($mobile !== null && $this->mobileTaken($mobile, $userId)) {
            $v->addError('mobile', 'This mobile number is already linked to another account.');
        }
        if ($v->fails()) {
            throw new ValidationFailed($v->errors());
        }
        $this->app->db->execute('UPDATE users SET full_name = :n, mobile = :m WHERE id = :id', [
            'n' => $name, 'm' => $mobile, 'id' => $userId,
        ]);
        $this->app->service(AuditLog::class)->record('profile.updated', 'user', $userId);
    }

    /**
     * Change password after verifying the current one. Other sessions are
     * signed out; the current session is kept.
     *
     * @param array<string,mixed> $input
     * @throws ValidationFailed
     */
    public function changePassword(int $userId, array $input): void
    {
        $user = $this->app->db->one('SELECT email, password_hash FROM users WHERE id = :id', ['id' => $userId]);
        $current = (string) ($input['current_password'] ?? '');
        $new = (string) ($input['password'] ?? '');
        $errors = [];
        if ($user === null || $user['password_hash'] === null || !Passwords::verify($current, (string) $user['password_hash'])) {
            $errors['current_password'] = 'Your current password is incorrect.';
        }
        $policy = Passwords::policyError($new, (string) ($user['email'] ?? ''));
        if ($policy !== null) {
            $errors['password'] = $policy;
        } elseif (!hash_equals($new, (string) ($input['password_confirmation'] ?? ''))) {
            $errors['password_confirmation'] = 'The two passwords do not match.';
        } elseif ($errors === [] && hash_equals($current, $new)) {
            $errors['password'] = 'Choose a password different from your current one.';
        }
        if ($errors !== []) {
            throw new ValidationFailed($errors);
        }
        $this->app->db->execute(
            'UPDATE users SET password_hash = :h, session_version = session_version + 1 WHERE id = :id',
            ['h' => Passwords::hash($new), 'id' => $userId]
        );
        $version = (int) $this->app->db->value('SELECT session_version FROM users WHERE id = :id', ['id' => $userId]);
        $this->app->session()->regenerate();
        $this->app->session()->set('session_version', $version);
        $this->app->auth()->refresh();
        $this->app->service(AuditLog::class)->record('auth.password_changed', 'user', $userId);
    }

    /** Age in whole years for display on reports. */
    public static function age(string $dob, DateTimeImmutable $on): int
    {
        return (new DateTimeImmutable($dob))->diff($on)->y;
    }
}
