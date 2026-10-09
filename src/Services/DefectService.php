<?php

declare(strict_types=1);

namespace Vitaliq\Services;

use Vitaliq\Core\App;
use Vitaliq\Core\Database;
use Vitaliq\Support\Labels;
use Vitaliq\Validation\Validator;

final class DefectService
{
    public function __construct(private readonly App $app)
    {
    }

    /**
     * @param array<string,string> $f status, severity, origin
     * @return list<array<string,mixed>>
     */
    public function list(array $f): array
    {
        $where = [];
        $params = [];
        foreach (['status' => Labels::DEFECT_STATUSES, 'severity' => Labels::SEVERITIES, 'origin' => Labels::DEFECT_ORIGINS] as $key => $allowed) {
            if (isset($allowed[$f[$key] ?? ''])) {
                $where[] = "d.{$key} = :{$key}";
                $params[$key] = $f[$key];
            }
        }
        $sql = $where === [] ? '' : 'WHERE ' . implode(' AND ', $where);
        return $this->app->db->all(
            "SELECT d.*, (SELECT COUNT(*) FROM qa_test_cases c WHERE c.defect_id = d.id) AS linked_cases
               FROM defects d {$sql}
              ORDER BY FIELD(d.status, 'open', 'in_progress', 'resolved', 'closed', 'wont_fix'),
                       FIELD(d.severity, 'critical', 'major', 'minor', 'trivial'), d.id DESC
              LIMIT 200",
            $params
        );
    }

    /** @return array<string,mixed>|null */
    public function find(int $id): ?array
    {
        $defect = $this->app->db->one(
            'SELECT d.*, u.full_name AS reporter FROM defects d LEFT JOIN users u ON u.id = d.reported_by_user_id WHERE d.id = :id',
            ['id' => $id]
        );
        if ($defect !== null) {
            $defect['cases'] = $this->app->db->all(
                'SELECT id, case_code, title, module FROM qa_test_cases WHERE defect_id = :id ORDER BY case_code',
                ['id' => $id]
            );
        }
        return $defect;
    }

    /** @return array<int,string> */
    public function options(): array
    {
        $rows = $this->app->db->all('SELECT id, code, title FROM defects ORDER BY id DESC LIMIT 200');
        $out = [];
        foreach ($rows as $r) {
            $out[(int) $r['id']] = $r['code'] . ' — ' . $r['title'];
        }
        return $out;
    }

    /**
     * @param array<string,mixed> $input
     * @throws ValidationFailed
     */
    public function create(array $input, int $userId): int
    {
        $v = new Validator($input);
        $data = [
            'title' => $v->text('title', 'Title', 160, true, 5),
            'description' => $v->text('description', 'Description', 4000, true, 10),
            'severity' => $v->choice('severity', Labels::SEVERITIES, 'Severity'),
            'priority' => $v->choice('priority', Labels::PRIORITIES, 'Priority'),
            'module' => $v->text('module', 'Module', 40, true, 2),
            'steps' => $v->text('steps_to_reproduce', 'Steps to reproduce', 4000, true, 10),
            'expected' => $v->text('expected_behaviour', 'Expected behaviour', 2000, true, 5),
            'actual' => $v->text('actual_behaviour', 'Actual behaviour', 2000, true, 5),
        ];
        if ($v->fails()) {
            throw new ValidationFailed($v->errors());
        }
        return $this->app->db->transaction(function (Database $db) use ($data, $userId): int {
            $max = (int) $db->value("SELECT COALESCE(MAX(CAST(SUBSTRING(code, 5) AS UNSIGNED)), 0) FROM defects FOR UPDATE");
            $code = sprintf('DEF-%03d', $max + 1);
            $id = $db->insert(
                'INSERT INTO defects (code, title, description, severity, priority, module, steps_to_reproduce, expected_behaviour,
                    actual_behaviour, status, origin, reported_by_user_id, created_at)
                 VALUES (:c, :t, :d, :s, :p, :m, :st, :e, :a, \'open\', \'user_report\', :u, :at)',
                ['c' => $code, 't' => $data['title'], 'd' => $data['description'], 's' => $data['severity'], 'p' => $data['priority'],
                 'm' => $data['module'], 'st' => $data['steps'], 'e' => $data['expected'], 'a' => $data['actual'], 'u' => $userId,
                 'at' => $this->app->clock->nowSql()]
            );
            $this->app->service(AuditLog::class)->record('defect.created', 'defect', $id, ['code' => $code], $userId);
            return $id;
        });
    }

    /**
     * @param array<string,mixed> $input status, resolution_notes
     * @throws ValidationFailed
     */
    public function updateStatus(int $id, array $input): void
    {
        if ($this->find($id) === null) {
            throw new WorkflowException('Defect not found.');
        }
        $v = new Validator($input);
        $status = $v->choice('status', Labels::DEFECT_STATUSES, 'Status');
        $notes = $v->text('resolution_notes', 'Resolution notes', 2000, in_array($status, ['resolved', 'closed', 'wont_fix'], true), 5);
        if ($v->fails()) {
            throw new ValidationFailed($v->errors());
        }
        $resolved = in_array($status, ['resolved', 'closed', 'wont_fix'], true);
        $this->app->db->execute(
            'UPDATE defects SET status = :s, resolution_notes = COALESCE(:n, resolution_notes),
                    resolved_at = CASE WHEN :r1 = 1 THEN COALESCE(resolved_at, :t) ELSE NULL END
              WHERE id = :id',
            ['s' => $status, 'n' => $notes, 'r1' => $resolved ? 1 : 0, 't' => $this->app->clock->nowSql(), 'id' => $id]
        );
        $this->app->service(AuditLog::class)->record('defect.status', 'defect', $id, ['status' => $status]);
    }
}
