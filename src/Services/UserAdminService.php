<?php

declare(strict_types=1);

namespace Vitaliq\Services;

use Vitaliq\Auth\PasswordTokens;
use Vitaliq\Core\App;
use Vitaliq\Core\Database;
use Vitaliq\Support\Labels;
use Vitaliq\Validation\Validator;

/**
 * Administrator management of user accounts. Password hashes are never
 * selected here; staff accounts receive a one-time set-password link.
 */
final class UserAdminService
{
    private const COLUMNS = 'u.id, u.role, u.full_name, u.email, u.mobile, u.is_active, u.last_login_at, u.created_at,
                             (u.password_hash IS NOT NULL) AS has_password';

    public function __construct(private readonly App $app)
    {
    }

    /** @return array{rows:list<array<string,mixed>>,total:int} */
    public function list(string $role, string $search, int $page, int $perPage = 25): array
    {
        $where = [];
        $params = [];
        if (isset(Labels::ROLES[$role])) {
            $where[] = 'u.role = :r';
            $params['r'] = $role;
        }
        if ($search !== '') {
            $where[] = '(u.full_name LIKE :q1 OR u.email LIKE :q2)';
            $like = '%' . Database::likeEscape($search) . '%';
            $params += ['q1' => $like, 'q2' => $like];
        }
        $sql = $where === [] ? '' : 'WHERE ' . implode(' AND ', $where);
        $total = (int) $this->app->db->value("SELECT COUNT(*) FROM users u {$sql}", $params);
        $rows = $this->app->db->all(
            'SELECT ' . self::COLUMNS . " FROM users u {$sql} ORDER BY u.created_at DESC, u.id DESC LIMIT :lim OFFSET :off",
            $params + ['lim' => $perPage, 'off' => max(0, ($page - 1) * $perPage)]
        );
        return ['rows' => $rows, 'total' => $total];
    }

    /** @return array<string,mixed>|null */
    public function find(int $id): ?array
    {
        return $this->app->db->one('SELECT ' . self::COLUMNS . ' FROM users u WHERE u.id = :id', ['id' => $id]);
    }

    public function setActive(int $id, bool $active): void
    {
        if ($id === $this->app->auth()->id()) {
            throw new WorkflowException('You cannot deactivate your own account.');
        }
        $user = $this->find($id);
        if ($user === null) {
            throw new WorkflowException('User not found.');
        }
        if (!$active && $user['role'] === 'admin') {
            $admins = (int) $this->app->db->value("SELECT COUNT(*) FROM users WHERE role = 'admin' AND is_active = 1");
            if ($admins <= 1) {
                throw new WorkflowException('At least one active administrator is required.');
            }
        }
        // Bumping session_version signs the user out everywhere on deactivation.
        $this->app->db->execute(
            'UPDATE users SET is_active = :a, session_version = session_version + :bump WHERE id = :id',
            ['a' => $active ? 1 : 0, 'bump' => $active ? 0 : 1, 'id' => $id]
        );
        $this->app->service(AuditLog::class)->record($active ? 'user.activated' : 'user.deactivated', 'user', $id);
    }

    /**
     * Create a staff account (admin, QA analyst or phlebotomist) and return
     * a one-time set-password token. Phlebotomist accounts are linked to an
     * existing phlebotomist record.
     *
     * @param array<string,mixed> $input
     * @return array{id:int,token:string}
     * @throws ValidationFailed
     */
    public function inviteStaff(array $input): array
    {
        $v = new Validator($input);
        $role = $v->choice('role', ['admin' => 'Administrator', 'qa' => 'QA analyst', 'phlebotomist' => 'Phlebotomist', 'lab' => 'Laboratory staff', 'officer' => 'Public-health officer'], 'Role');
        $name = $v->rule('full_name', 'full_name');
        $email = $v->rule('email', 'email');
        $phlebId = null;
        if ($role === 'phlebotomist') {
            $options = $this->app->service(PhlebotomistService::class)->unlinkedOptions();
            $phlebId = (int) ($input['phlebotomist_id'] ?? 0);
            if (!isset($options[$phlebId])) {
                $v->addError('phlebotomist_id', 'Choose the phlebotomist record this login belongs to.');
            }
        }
        if ($email !== null && $this->app->service(PatientService::class)->emailTaken($email)) {
            $v->addError('email', 'An account with this email already exists.');
        }
        if ($v->fails()) {
            throw new ValidationFailed($v->errors());
        }
        $id = $this->app->db->transaction(function (Database $db) use ($role, $name, $email, $phlebId): int {
            $id = $db->insert(
                'INSERT INTO users (role, full_name, email, password_hash) VALUES (:r, :n, :e, NULL)',
                ['r' => $role, 'n' => $name, 'e' => $email]
            );
            if ($phlebId !== null) {
                $db->execute('UPDATE phlebotomists SET user_id = :u WHERE id = :p AND user_id IS NULL', ['u' => $id, 'p' => $phlebId]);
            }
            $this->app->service(AuditLog::class)->record('user.invited', 'user', $id, ['role' => $role]);
            return $id;
        });
        return ['id' => $id, 'token' => $this->app->service(PasswordTokens::class)->issue($id, 'invite')];
    }

    /** New invitation link for a staff user who has not set a password yet. */
    public function reissueInvite(int $id): string
    {
        $user = $this->find($id);
        if ($user === null || $user['role'] === 'patient' || (int) $user['has_password'] === 1 || (int) $user['is_active'] !== 1) {
            throw new WorkflowException('An invitation can only be reissued for an active staff account without a password.');
        }
        $this->app->service(AuditLog::class)->record('user.invite_reissued', 'user', $id);
        return $this->app->service(PasswordTokens::class)->issue($id, 'invite');
    }
}
