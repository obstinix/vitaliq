<?php

declare(strict_types=1);

namespace Vitaliq\Services;

use PDOException;
use Vitaliq\Core\App;
use Vitaliq\Core\Database;
use Vitaliq\Validation\Validator;

final class PhlebotomistService
{
    public function __construct(private readonly App $app)
    {
    }

    /** @return array{rows:list<array<string,mixed>>,total:int} */
    public function list(string $search, string $status, int $page, int $perPage = 20): array
    {
        $where = ['ph.deleted_at IS NULL'];
        $params = [];
        if ($status === 'active') {
            $where[] = 'ph.is_active = 1';
        } elseif ($status === 'inactive') {
            $where[] = 'ph.is_active = 0';
        }
        if ($search !== '') {
            $where[] = '(ph.full_name LIKE :q1 OR ph.employee_code LIKE :q2 OR ph.base_city LIKE :q3)';
            $like = '%' . Database::likeEscape($search) . '%';
            $params += ['q1' => $like, 'q2' => $like, 'q3' => $like];
        }
        $sqlWhere = 'WHERE ' . implode(' AND ', $where);
        $total = (int) $this->app->db->value("SELECT COUNT(*) FROM phlebotomists ph {$sqlWhere}", $params);
        $rows = $this->app->db->all(
            "SELECT ph.*, s.name AS state_name, u.email AS login_email,
                    (SELECT COUNT(*) FROM test_assignments a JOIN test_requests r ON r.id = a.request_id
                      WHERE a.phlebotomist_id = ph.id AND a.is_current = 1 AND r.status = 'assigned') AS open_collections,
                    (SELECT COUNT(*) FROM sample_collections sc WHERE sc.phlebotomist_id = ph.id) AS total_collections
               FROM phlebotomists ph
               JOIN states s ON s.code = ph.state_code
               LEFT JOIN users u ON u.id = ph.user_id
               {$sqlWhere}
              ORDER BY ph.is_active DESC, ph.full_name
              LIMIT :lim OFFSET :off",
            $params + ['lim' => $perPage, 'off' => max(0, ($page - 1) * $perPage)]
        );
        return ['rows' => $rows, 'total' => $total];
    }

    /** @return array<string,mixed>|null */
    public function find(int $id): ?array
    {
        return $this->app->db->one(
            'SELECT ph.*, s.name AS state_name FROM phlebotomists ph JOIN states s ON s.code = ph.state_code
              WHERE ph.id = :id AND ph.deleted_at IS NULL',
            ['id' => $id]
        );
    }

    /** @return array<int,string> id => label, active only */
    public function activeOptions(): array
    {
        $rows = $this->app->db->all(
            "SELECT ph.id, ph.full_name, ph.employee_code, ph.base_city,
                    (SELECT COUNT(*) FROM test_assignments a JOIN test_requests r ON r.id = a.request_id
                      WHERE a.phlebotomist_id = ph.id AND a.is_current = 1 AND r.status = 'assigned') AS open_collections
               FROM phlebotomists ph WHERE ph.is_active = 1 AND ph.deleted_at IS NULL ORDER BY ph.base_city, ph.full_name"
        );
        $out = [];
        foreach ($rows as $r) {
            $out[(int) $r['id']] = sprintf('%s · %s · %s · %d open', $r['full_name'], $r['employee_code'], $r['base_city'], $r['open_collections']);
        }
        return $out;
    }

    /** @return array<int,string> phlebotomists without a portal login */
    public function unlinkedOptions(): array
    {
        $rows = $this->app->db->all(
            'SELECT id, full_name, employee_code FROM phlebotomists WHERE user_id IS NULL AND deleted_at IS NULL ORDER BY full_name'
        );
        $out = [];
        foreach ($rows as $r) {
            $out[(int) $r['id']] = $r['full_name'] . ' · ' . $r['employee_code'];
        }
        return $out;
    }

    /**
     * @param array<string,mixed> $input
     * @return array<string,mixed>
     * @throws ValidationFailed
     */
    private function validate(array $input, ?int $id): array
    {
        $v = new Validator($input);
        $data = [
            'full_name' => $v->rule('full_name', 'full_name'),
            'mobile' => $v->rule('mobile', 'indian_mobile', true, 'Mobile number'),
            'email' => $v->rule('email', 'email', false),
            'base_city' => $v->text('base_city', 'Base city', 80, true, 2),
            'state_code' => $v->choice('state_code', $this->app->service(PatientService::class)->states(), 'State or union territory'),
            'employee_code' => $v->rule('employee_code', 'employee_code', false),
        ];
        if ($data['mobile'] !== null && $this->app->db->value(
            'SELECT 1 FROM phlebotomists WHERE mobile = :m AND id <> :id', ['m' => $data['mobile'], 'id' => $id ?? 0]
        ) !== null) {
            $v->addError('mobile', 'Another phlebotomist already uses this mobile number.');
        }
        if ($data['employee_code'] !== null && $this->app->db->value(
            'SELECT 1 FROM phlebotomists WHERE employee_code = :c AND id <> :id', ['c' => $data['employee_code'], 'id' => $id ?? 0]
        ) !== null) {
            $v->addError('employee_code', 'This employee code is already in use.');
        }
        if ($v->fails()) {
            throw new ValidationFailed($v->errors());
        }
        return $data;
    }

    /** @param array<string,mixed> $input */
    public function create(array $input): int
    {
        $data = $this->validate($input, null);
        return $this->app->db->transaction(function (Database $db) use ($data): int {
            if ($data['employee_code'] === null) {
                $max = (int) $db->value("SELECT COALESCE(MAX(CAST(SUBSTRING(employee_code, 5) AS UNSIGNED)), 0) FROM phlebotomists FOR UPDATE");
                $data['employee_code'] = sprintf('PHL-%04d', $max + 1);
            }
            try {
                $id = $db->insert(
                    'INSERT INTO phlebotomists (employee_code, full_name, mobile, email, base_city, state_code)
                     VALUES (:c, :n, :m, :e, :b, :s)',
                    ['c' => $data['employee_code'], 'n' => $data['full_name'], 'm' => $data['mobile'], 'e' => $data['email'],
                     'b' => $data['base_city'], 's' => $data['state_code']]
                );
            } catch (PDOException $e) {
                if ($e->getCode() === '23000') {
                    throw new ValidationFailed(['employee_code' => 'That employee code or mobile number is already in use.']);
                }
                throw $e;
            }
            $this->app->service(AuditLog::class)->record('phlebotomist.created', 'phlebotomist', $id, ['code' => $data['employee_code']]);
            return $id;
        });
    }

    /** @param array<string,mixed> $input */
    public function update(int $id, array $input): void
    {
        $existing = $this->find($id);
        if ($existing === null) {
            throw new WorkflowException('Phlebotomist not found.');
        }
        $data = $this->validate($input, $id);
        $this->app->db->execute(
            'UPDATE phlebotomists SET full_name = :n, mobile = :m, email = :e, base_city = :b, state_code = :s,
                    employee_code = :c WHERE id = :id',
            ['n' => $data['full_name'], 'm' => $data['mobile'], 'e' => $data['email'], 'b' => $data['base_city'],
             's' => $data['state_code'], 'c' => $data['employee_code'] ?? $existing['employee_code'], 'id' => $id]
        );
        $this->app->service(AuditLog::class)->record('phlebotomist.updated', 'phlebotomist', $id);
    }

    public function openCollections(int $id): int
    {
        return (int) $this->app->db->value(
            "SELECT COUNT(*) FROM test_assignments a JOIN test_requests r ON r.id = a.request_id
              WHERE a.phlebotomist_id = :id AND a.is_current = 1 AND r.status = 'assigned'",
            ['id' => $id]
        );
    }

    public function setActive(int $id, bool $active): void
    {
        if ($this->find($id) === null) {
            throw new WorkflowException('Phlebotomist not found.');
        }
        if (!$active && ($open = $this->openCollections($id)) > 0) {
            throw new WorkflowException("Reassign this phlebotomist’s {$open} open collection(s) before deactivating.");
        }
        $this->app->db->execute('UPDATE phlebotomists SET is_active = :a WHERE id = :id', ['a' => $active ? 1 : 0, 'id' => $id]);
        $this->app->service(AuditLog::class)->record($active ? 'phlebotomist.activated' : 'phlebotomist.deactivated', 'phlebotomist', $id);
    }

    /**
     * Hard-delete when the record has never been used; otherwise soft-delete
     * so testing history keeps its references.
     *
     * @return 'deleted'|'archived'
     */
    public function delete(int $id): string
    {
        return $this->app->db->transaction(function (Database $db) use ($id): string {
            $row = $db->one('SELECT id, user_id FROM phlebotomists WHERE id = :id AND deleted_at IS NULL FOR UPDATE', ['id' => $id]);
            if ($row === null) {
                throw new WorkflowException('Phlebotomist not found.');
            }
            if ($this->openCollections($id) > 0) {
                throw new WorkflowException('Reassign this phlebotomist’s open collections before removing the record.');
            }
            $used = (int) $db->value('SELECT COUNT(*) FROM test_assignments WHERE phlebotomist_id = :id', ['id' => $id])
                + (int) $db->value('SELECT COUNT(*) FROM sample_collections WHERE phlebotomist_id = :id', ['id' => $id]);
            if ($used > 0) {
                $db->execute('UPDATE phlebotomists SET is_active = 0, deleted_at = :t WHERE id = :id', [
                    't' => $this->app->clock->nowSql(), 'id' => $id,
                ]);
                if ($row['user_id'] !== null) {
                    $db->execute('UPDATE users SET is_active = 0, session_version = session_version + 1 WHERE id = :u', ['u' => (int) $row['user_id']]);
                }
                $this->app->service(AuditLog::class)->record('phlebotomist.archived', 'phlebotomist', $id);
                return 'archived';
            }
            $db->execute('DELETE FROM phlebotomists WHERE id = :id', ['id' => $id]);
            $this->app->service(AuditLog::class)->record('phlebotomist.deleted', 'phlebotomist', $id);
            return 'deleted';
        });
    }
}
