<?php

declare(strict_types=1);

namespace Vitaliq\Services;

use DateTimeImmutable;
use Vitaliq\Core\App;
use Vitaliq\Core\Database;
use Vitaliq\Validation\Validator;

/**
 * Lab inventory by lot with expiry (feature 12) and equipment with
 * calibration and maintenance logs (feature 15).
 */
final class LabResourcesService
{
    public const CATEGORIES = ['reagent' => 'Reagent', 'consumable' => 'Consumable', 'kit' => 'Test kit', 'control' => 'Quality control'];
    public const REASONS = ['receipt' => 'Receipt', 'consumption' => 'Used', 'adjustment' => 'Count adjustment', 'expired' => 'Expired', 'wastage' => 'Wasted'];
    public const EQUIPMENT_STATUSES = ['operational' => 'Operational', 'maintenance' => 'In maintenance', 'out_of_service' => 'Out of service', 'retired' => 'Retired'];
    public const LOG_KINDS = ['calibration' => 'Calibration', 'maintenance' => 'Maintenance', 'repair' => 'Repair', 'qc_failure' => 'QC failure', 'status_change' => 'Status change', 'note' => 'Note'];
    public const EXPIRY_WARNING_DAYS = 30;

    public function __construct(private readonly App $app)
    {
    }

    /* ----------------------------------------------------------- inventory */

    /** @return list<array<string,mixed>> items with stock, nearest expiry and a state */
    public function inventory(): array
    {
        $today = $this->app->clock->now()->format('Y-m-d');
        $soon = $this->app->clock->now()->modify('+' . self::EXPIRY_WARNING_DAYS . ' days')->format('Y-m-d');
        $rows = $this->app->db->all(
            "SELECT i.*,
                    COALESCE(SUM(CASE WHEN l.expires_on IS NULL OR l.expires_on >= :t1 THEN l.quantity END), 0) AS usable,
                    COALESCE(SUM(CASE WHEN l.expires_on < :t2 THEN l.quantity END), 0) AS expired_qty,
                    MIN(CASE WHEN l.quantity > 0 AND (l.expires_on IS NULL OR l.expires_on >= :t3) THEN l.expires_on END) AS next_expiry
               FROM lab_inventory_items i LEFT JOIN lab_inventory_lots l ON l.item_id = i.id
              WHERE i.is_active = 1 GROUP BY i.id ORDER BY i.name",
            ['t1' => $today, 't2' => $today, 't3' => $today]
        );
        foreach ($rows as &$r) {
            $r['state'] = match (true) {
                (int) $r['usable'] === 0 => 'out',
                (int) $r['usable'] <= (int) $r['reorder_level'] => 'low',
                $r['next_expiry'] !== null && $r['next_expiry'] <= $soon => 'expiring',
                default => 'ok',
            };
        }
        return $rows;
    }

    /** @return array<string,mixed>|null */
    public function item(int $id): ?array
    {
        $item = $this->app->db->one('SELECT * FROM lab_inventory_items WHERE id = :id', ['id' => $id]);
        if ($item === null) {
            return null;
        }
        $item['lots'] = $this->app->db->all('SELECT * FROM lab_inventory_lots WHERE item_id = :id ORDER BY expires_on IS NULL, expires_on, id', ['id' => $id]);
        $item['movements'] = $this->app->db->all(
            'SELECT m.*, l.lot_no, u.full_name FROM lab_inventory_movements m LEFT JOIN lab_inventory_lots l ON l.id = m.lot_id
               LEFT JOIN users u ON u.id = m.actor_user_id WHERE m.item_id = :id ORDER BY m.id DESC LIMIT 50',
            ['id' => $id]
        );
        return $item;
    }

    /** @param array<string,mixed> $input */
    public function createItem(array $input, int $actorId): int
    {
        $v = new Validator($input);
        $sku = strtoupper(trim((string) ($input['sku'] ?? '')));
        if (!preg_match('/^[A-Z0-9-]{3,24}$/', $sku)) {
            $v->addError('sku', 'Use 3–24 letters, digits or hyphens for the SKU.');
        } elseif ($this->app->db->value('SELECT id FROM lab_inventory_items WHERE sku = :s', ['s' => $sku]) !== null) {
            $v->addError('sku', 'That SKU already exists.');
        }
        $name = $v->text('name', 'Name', 120, true, 2);
        $category = $v->choice('category', self::CATEGORIES, 'Category');
        $unit = $v->text('unit', 'Unit', 20, true, 1);
        $reorder = $this->int($v, $input, 'reorder_level', 'Reorder level', 0, 1000000);
        if ($v->fails()) {
            throw new ValidationFailed($v->errors());
        }
        $id = $this->app->db->insert(
            'INSERT INTO lab_inventory_items (sku, name, category, unit, reorder_level, created_at) VALUES (:s, :n, :c, :u, :r, :t)',
            ['s' => $sku, 'n' => $name, 'c' => $category, 'u' => $unit, 'r' => $reorder, 't' => $this->app->clock->nowSql()]
        );
        $this->app->service(AuditLog::class)->record('inventory.item_created', 'inventory_item', $id, ['sku' => $sku], $actorId);
        return $id;
    }

    /** @param array<string,mixed> $input lot_no, expires_on, quantity */
    public function receive(int $itemId, array $input, int $actorId): void
    {
        $v = new Validator($input);
        $lot = trim((string) ($input['lot_no'] ?? ''));
        if (!preg_match('/^[A-Za-z0-9._\/-]{2,40}$/', $lot)) {
            $v->addError('lot_no', 'Enter the lot number printed on the pack.');
        }
        $expires = $v->date('expires_on', 'Expiry date', $this->app->clock->now(), null, false);
        $qty = $this->int($v, $input, 'quantity', 'Quantity', 1, 1000000);
        if ($v->fails()) {
            throw new ValidationFailed($v->errors());
        }
        $this->app->db->transaction(function (Database $db) use ($itemId, $lot, $expires, $qty, $actorId): void {
            if ($db->one('SELECT id FROM lab_inventory_items WHERE id = :id FOR UPDATE', ['id' => $itemId]) === null) {
                throw new WorkflowException('Item not found.');
            }
            $existing = $db->one('SELECT * FROM lab_inventory_lots WHERE item_id = :i AND lot_no = :l FOR UPDATE', ['i' => $itemId, 'l' => $lot]);
            if ($existing !== null) {
                if ((string) $existing['expires_on'] !== (string) $expires) {
                    throw new ValidationFailed(['expires_on' => 'This lot is already recorded with expiry ' . ($existing['expires_on'] ?? 'none') . '.']);
                }
                $db->execute('UPDATE lab_inventory_lots SET quantity = quantity + :q WHERE id = :id', ['q' => $qty, 'id' => $existing['id']]);
                $lotId = (int) $existing['id'];
            } else {
                $lotId = $db->insert('INSERT INTO lab_inventory_lots (item_id, lot_no, expires_on, quantity, received_at) VALUES (:i, :l, :e, :q, :t)',
                    ['i' => $itemId, 'l' => $lot, 'e' => $expires, 'q' => $qty, 't' => $this->app->clock->nowSql()]);
            }
            $this->movement($db, $itemId, $lotId, $qty, 'receipt', null, $actorId);
        });
    }

    /**
     * Take stock out, earliest expiry first (FEFO). Expired lots are never used.
     *
     * @param array<string,mixed> $input quantity, reason, note
     */
    public function consume(int $itemId, array $input, int $actorId): void
    {
        $v = new Validator($input);
        $qty = $this->int($v, $input, 'quantity', 'Quantity', 1, 1000000);
        $reason = $v->choice('reason', ['consumption' => '', 'wastage' => '', 'adjustment' => ''], 'Reason') ?? 'consumption';
        $note = $v->text('note', 'Note', 200, $reason !== 'consumption');
        if ($v->fails()) {
            throw new ValidationFailed($v->errors());
        }
        $this->app->db->transaction(function (Database $db) use ($itemId, $qty, $reason, $note, $actorId): void {
            $today = $this->app->clock->now()->format('Y-m-d');
            $lots = $db->all(
                'SELECT * FROM lab_inventory_lots WHERE item_id = :i AND quantity > 0 AND (expires_on IS NULL OR expires_on >= :t)
                  ORDER BY expires_on IS NULL, expires_on, id FOR UPDATE',
                ['i' => $itemId, 't' => $today]
            );
            $available = array_sum(array_map(static fn ($l) => (int) $l['quantity'], $lots));
            if ($available < $qty) {
                throw new ValidationFailed(['quantity' => "Only {$available} usable in stock."]);
            }
            $left = $qty;
            foreach ($lots as $lot) {
                if ($left === 0) {
                    break;
                }
                $take = min($left, (int) $lot['quantity']);
                $db->execute('UPDATE lab_inventory_lots SET quantity = quantity - :q WHERE id = :id', ['q' => $take, 'id' => $lot['id']]);
                $this->movement($db, $itemId, (int) $lot['id'], -$take, $reason, $note, $actorId);
                $left -= $take;
            }
            $this->checkReorder($db, $itemId);
        });
    }

    /** Write off every expired lot. Returns units written off. */
    public function writeOffExpired(int $actorId): int
    {
        return $this->app->db->transaction(function (Database $db) use ($actorId): int {
            $lots = $db->all('SELECT * FROM lab_inventory_lots WHERE quantity > 0 AND expires_on < :t FOR UPDATE', ['t' => $this->app->clock->now()->format('Y-m-d')]);
            $total = 0;
            foreach ($lots as $lot) {
                $db->execute('UPDATE lab_inventory_lots SET quantity = 0 WHERE id = :id', ['id' => $lot['id']]);
                $this->movement($db, (int) $lot['item_id'], (int) $lot['id'], -(int) $lot['quantity'], 'expired', 'Expired ' . $lot['expires_on'], $actorId);
                $total += (int) $lot['quantity'];
            }
            return $total;
        });
    }

    private function checkReorder(Database $db, int $itemId): void
    {
        $row = $db->one(
            'SELECT i.name, i.reorder_level, COALESCE(SUM(l.quantity), 0) AS qty FROM lab_inventory_items i
               LEFT JOIN lab_inventory_lots l ON l.item_id = i.id AND (l.expires_on IS NULL OR l.expires_on >= :t)
              WHERE i.id = :id GROUP BY i.id',
            ['id' => $itemId, 't' => $this->app->clock->now()->format('Y-m-d')]
        );
        if ($row !== null && (int) $row['qty'] <= (int) $row['reorder_level']) {
            $this->app->service(NotificationService::class)->notifyRole('lab', 'stock_low', 'Low stock: ' . $row['name'],
                (int) $row['qty'] . ' left; reorder level is ' . (int) $row['reorder_level'] . '.', '/lab/inventory/' . $itemId);
        }
    }

    private function movement(Database $db, int $itemId, ?int $lotId, int $change, string $reason, ?string $note, int $actorId): void
    {
        $db->insert(
            'INSERT INTO lab_inventory_movements (item_id, lot_id, quantity_change, reason, note, actor_user_id, created_at) VALUES (:i, :l, :q, :r, :n, :a, :t)',
            ['i' => $itemId, 'l' => $lotId, 'q' => $change, 'r' => $reason, 'n' => $note ?: null, 'a' => $actorId, 't' => $this->app->clock->nowSql()]
        );
    }

    /* ----------------------------------------------------------- equipment */

    /** @return list<array<string,mixed>> */
    public function equipment(): array
    {
        $rows = $this->app->db->all("SELECT e.*, f.name AS facility_name FROM lab_equipment e LEFT JOIN facilities f ON f.id = e.facility_id WHERE e.status <> 'retired' ORDER BY e.name");
        $today = $this->app->clock->now()->format('Y-m-d');
        $soon = $this->app->clock->now()->modify('+14 days')->format('Y-m-d');
        foreach ($rows as &$r) {
            $due = $r['calibration_due_on'];
            $r['calibration_state'] = $due === null ? 'unknown' : ($due < $today ? 'overdue' : ($due <= $soon ? 'due' : 'ok'));
        }
        return $rows;
    }

    /** @return array<string,mixed>|null */
    public function equipmentItem(int $id): ?array
    {
        $e = $this->app->db->one('SELECT e.*, f.name AS facility_name FROM lab_equipment e LEFT JOIN facilities f ON f.id = e.facility_id WHERE e.id = :id', ['id' => $id]);
        if ($e !== null) {
            $e['logs'] = $this->app->db->all('SELECT l.*, u.full_name FROM lab_equipment_logs l LEFT JOIN users u ON u.id = l.actor_user_id WHERE l.equipment_id = :id ORDER BY l.performed_on DESC, l.id DESC', ['id' => $id]);
        }
        return $e;
    }

    /** @param array<string,mixed> $input */
    public function createEquipment(array $input, int $actorId): int
    {
        $v = new Validator($input);
        $tag = strtoupper(trim((string) ($input['asset_tag'] ?? '')));
        if (!preg_match('/^[A-Z0-9-]{3,20}$/', $tag)) {
            $v->addError('asset_tag', 'Use 3–20 letters, digits or hyphens for the asset tag.');
        } elseif ($this->app->db->value('SELECT id FROM lab_equipment WHERE asset_tag = :t', ['t' => $tag]) !== null) {
            $v->addError('asset_tag', 'That asset tag already exists.');
        }
        $name = $v->text('name', 'Name', 120, true, 2);
        $manufacturer = $v->text('manufacturer', 'Manufacturer', 80, false);
        $model = $v->text('model', 'Model', 80, false);
        $serial = $v->text('serial_no', 'Serial number', 60, false);
        $interval = $this->int($v, $input, 'calibration_interval_days', 'Calibration interval', 1, 1825);
        $last = $v->date('last_calibrated_on', 'Last calibrated', null, $this->app->clock->now(), false);
        if ($v->fails()) {
            throw new ValidationFailed($v->errors());
        }
        $due = $last !== null ? (new DateTimeImmutable($last))->modify("+{$interval} days")->format('Y-m-d') : null;
        $id = $this->app->db->insert(
            'INSERT INTO lab_equipment (asset_tag, name, manufacturer, model, serial_no, calibration_interval_days, last_calibrated_on, calibration_due_on, created_at)
             VALUES (:a, :n, :m, :mo, :s, :i, :l, :d, :t)',
            ['a' => $tag, 'n' => $name, 'm' => $manufacturer ?: null, 'mo' => $model ?: null, 's' => $serial ?: null, 'i' => $interval,
             'l' => $last, 'd' => $due, 't' => $this->app->clock->nowSql()]
        );
        $this->app->service(AuditLog::class)->record('equipment.created', 'equipment', $id, ['tag' => $tag], $actorId);
        return $id;
    }

    /**
     * Record calibration, maintenance, a repair, a QC failure or a status change.
     *
     * @param array<string,mixed> $input kind, performed_on, notes, status
     */
    public function log(int $equipmentId, array $input, int $actorId): void
    {
        $v = new Validator($input);
        $kind = $v->choice('kind', self::LOG_KINDS, 'Entry type');
        $on = $v->date('performed_on', 'Date', null, $this->app->clock->now());
        $notes = $v->text('notes', 'Notes', 500, in_array($kind, ['repair', 'qc_failure', 'note'], true));
        $status = $kind === 'status_change' ? $v->choice('status', self::EQUIPMENT_STATUSES, 'New status') : null;
        if ($v->fails()) {
            throw new ValidationFailed($v->errors());
        }
        $this->app->db->transaction(function (Database $db) use ($equipmentId, $kind, $on, $notes, $status, $actorId): void {
            $e = $db->one('SELECT * FROM lab_equipment WHERE id = :id FOR UPDATE', ['id' => $equipmentId]);
            if ($e === null) {
                throw new WorkflowException('Equipment not found.');
            }
            $text = $notes ?: null;
            if ($kind === 'calibration') {
                $due = (new DateTimeImmutable((string) $on))->modify('+' . (int) $e['calibration_interval_days'] . ' days')->format('Y-m-d');
                $db->execute("UPDATE lab_equipment SET last_calibrated_on = :l, calibration_due_on = :d,
                                status = IF(status = 'maintenance', 'operational', status) WHERE id = :id", ['l' => $on, 'd' => $due, 'id' => $equipmentId]);
            } elseif ($kind === 'qc_failure') {
                // A failed quality control takes the analyser out of use until it is checked.
                $db->execute("UPDATE lab_equipment SET status = 'out_of_service' WHERE id = :id AND status = 'operational'", ['id' => $equipmentId]);
                $this->app->service(NotificationService::class)->notifyRole('lab', 'equipment', 'QC failure on ' . $e['name'], (string) $notes, '/lab/equipment/' . $equipmentId);
            } elseif ($kind === 'status_change' && $status !== null) {
                $db->execute('UPDATE lab_equipment SET status = :s WHERE id = :id', ['s' => $status, 'id' => $equipmentId]);
                $text = trim(self::EQUIPMENT_STATUSES[$e['status']] . ' → ' . self::EQUIPMENT_STATUSES[$status] . '. ' . ($notes ?? ''));
            }
            $db->insert('INSERT INTO lab_equipment_logs (equipment_id, kind, performed_on, notes, actor_user_id, created_at) VALUES (:e, :k, :p, :n, :a, :t)',
                ['e' => $equipmentId, 'k' => $kind, 'p' => $on, 'n' => $text, 'a' => $actorId, 't' => $this->app->clock->nowSql()]);
        });
    }

    /** @param array<string,mixed> $input */
    private function int(Validator $v, array $input, string $field, string $label, int $min, int $max): int
    {
        $raw = trim((string) ($input[$field] ?? ''));
        if (!preg_match('/^\d{1,7}$/', $raw) || (int) $raw < $min || (int) $raw > $max) {
            $v->addError($field, "{$label} must be a whole number from {$min} to " . number_format($max) . '.');
            return 0;
        }
        return (int) $raw;
    }
}
