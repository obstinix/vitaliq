<?php

declare(strict_types=1);

namespace Vitaliq\Support;

use DateTimeImmutable;
use RuntimeException;
use Vitaliq\Auth\Passwords;
use Vitaliq\Core\App;
use Vitaliq\Services\AppointmentService;
use Vitaliq\Services\LaboratoryService;
use Vitaliq\Services\LabResourcesService;

/**
 * Synthetic demonstration data for the platform features added after the
 * first release: facilities, laboratory staff, the test catalogue, lab
 * stock, equipment, lab orders driven through the real workflow, and
 * appointments. Runs once per database (app_settings marker), after the
 * base demo data, and never in production.
 */
final class PlatformSeeder
{
    /** @var list<array{role:string,name:string,email:string,password:string}> */
    public array $credentials = [];

    public function __construct(private readonly App $app)
    {
    }

    public function run(): void
    {
        $db = $this->app->db;
        if ($db->value("SELECT value FROM app_settings WHERE name = 'platform_lab_seeded_at'") !== null) {
            throw new RuntimeException('Platform demo data has already been seeded.');
        }
        if ($db->value("SELECT value FROM app_settings WHERE name = 'demo_data_seeded_at'") === null) {
            throw new RuntimeException('Run php bin/seed.php first: the platform demo builds on the base demo data.');
        }
        mt_srand(20261101);
        $clock = $this->app->clock;
        $now = new DateTimeImmutable('now');
        $lab = $this->app->service(LaboratoryService::class);
        $res = $this->app->service(LabResourcesService::class);

        $facilities = [];
        foreach ([
            ['BLR-LAB', 'Vitaliq Central Laboratory', 'laboratory', 'Bengaluru', 'KA'],
            ['BLR-IND', 'Indiranagar Collection Centre', 'collection_centre', 'Bengaluru', 'KA'],
            ['COK-EDP', 'Edappally Collection Centre', 'collection_centre', 'Kochi', 'KL'],
            ['PNQ-KOT', 'Kothrud Community Clinic', 'clinic', 'Pune', 'MH'],
        ] as [$code, $name, $kind, $city, $state]) {
            $facilities[$code] = $db->insert(
                'INSERT INTO facilities (code, name, kind, city, state_code, created_at) VALUES (:c, :n, :k, :ci, :s, :t)',
                ['c' => $code, 'n' => $name, 'k' => $kind, 'ci' => $city, 's' => $state, 't' => $now->modify('-90 days')->format('Y-m-d H:i:s')]
            );
        }
        $techA = $this->staff('lab', 'Meera Iyer', 'meera.iyer@vitaliq.example');
        $techB = $this->staff('lab', 'Arvind Rao', 'arvind.rao@vitaliq.example');
        foreach ([$techA, $techB] as $u) {
            $db->execute('INSERT INTO user_facilities (user_id, facility_id) VALUES (:u, :f)', ['u' => $u, 'f' => $facilities['BLR-LAB']]);
        }
        $a = ['id' => $techA, 'role' => 'lab'];
        $b = ['id' => $techB, 'role' => 'lab'];

        $clock->freeze($now->modify('-21 days'));
        $lab->syncCatalogue();
        foreach ([
            ['RAT-KIT', 'SARS-CoV-2 rapid antigen kit', 'kit', 'tests', 40, [['A2609', '+120 days', 150], ['A2611', '+15 days', 30]]],
            ['EDTA-3ML', 'EDTA tube, 3 mL', 'consumable', 'tubes', 200, [['E8841', '+300 days', 600]]],
            ['SST-5ML', 'Serum separator tube, 5 mL', 'consumable', 'tubes', 150, [['S1203', '+280 days', 180]]],
            ['GLU-REAG', 'Glucose reagent cartridge', 'reagent', 'cartridges', 4, [['G0457', '+60 days', 6]]],
            ['QC-HAEM-L2', 'Haematology control, level 2', 'control', 'vials', 2, [['Q7731', '+5 days', 3]]],
        ] as [$sku, $name, $cat, $unit, $reorder, $lots]) {
            $item = $res->createItem(['sku' => $sku, 'name' => $name, 'category' => $cat, 'unit' => $unit, 'reorder_level' => (string) $reorder], $techA);
            foreach ($lots as [$lot, $exp, $qty]) {
                $res->receive($item, ['lot_no' => $lot, 'expires_on' => $now->modify($exp)->format('Y-m-d'), 'quantity' => (string) $qty], $techA);
            }
        }
        foreach ([
            ['HAEM-01', 'Haematology analyser', 'Sysmex', 'XN-550', 180, '-150 days'],
            ['CHEM-01', 'Clinical chemistry analyser', 'Beckman Coulter', 'AU480', 90, '-100 days'],
            ['PCR-01', 'Real-time PCR system', 'Bio-Rad', 'CFX96', 365, '-40 days'],
        ] as [$tag, $name, $make, $model, $interval, $last]) {
            $res->createEquipment(['asset_tag' => $tag, 'name' => $name, 'manufacturer' => $make, 'model' => $model,
                'calibration_interval_days' => (string) $interval, 'last_calibrated_on' => $now->modify($last)->format('Y-m-d')], $techA);
        }

        // Lab orders for existing demo patients, moved through the real workflow.
        $patients = $db->all('SELECT id FROM patients ORDER BY id LIMIT 40');
        $panels = [['HB', 'WBC', 'PLT'], ['GLUF', 'HBA1C'], ['CREA', 'CRP'], ['DENNS1', 'PLT'], ['COVPCR'], ['HB', 'GLUF', 'CREA']];
        $codes = array_column($db->all('SELECT id, code FROM lab_tests'), 'id', 'code');
        for ($i = 0; $i < 18; $i++) {
            $start = $now->modify('-' . (20 - $i) . ' days')->setTime(8 + mt_rand(0, 6), mt_rand(0, 59));
            $clock->freeze($start);
            $panel = $panels[$i % count($panels)];
            $orderId = $lab->createOrder([
                'patient_id' => (string) $patients[mt_rand(0, count($patients) - 1)]['id'],
                'tests' => array_map(static fn ($c) => (string) $codes[$c], $panel),
                'priority' => $i % 7 === 0 ? 'urgent' : 'routine',
            ], $a);
            $samples = $db->all('SELECT id FROM lab_samples WHERE order_id = :o', ['o' => $orderId]);
            $last = $i >= 16;
            foreach ($samples as $k => $s) {
                $clock->freeze($start->modify('+20 minutes'));
                $lab->moveSample((int) $s['id'], 'collected', $a);
                if ($last && $k === 0) {
                    continue; // still on its way
                }
                $clock->freeze($start->modify('+' . (60 + mt_rand(0, 90)) . ' minutes'));
                if ($i === 5 || $i === 11) {
                    $lab->moveSample((int) $s['id'], 'rejected', $a, '', ['rejection_reason' => $i === 5 ? 'haemolysed' : 'insufficient_volume']);
                    continue;
                }
                $lab->moveSample((int) $s['id'], 'received', $a);
            }
            $items = $db->all(
                "SELECT i.id, t.code FROM lab_order_items i JOIN lab_tests t ON t.id = i.test_id JOIN lab_samples s ON s.id = i.sample_id
                  WHERE i.order_id = :o AND s.status IN ('received','in_analysis')",
                ['o' => $orderId]
            );
            foreach ($items as $it) {
                $clock->freeze($start->modify('+' . (2 + mt_rand(0, 5)) . ' hours'));
                $lab->enterResult((int) $it['id'], $this->value((string) $it['code']), '', $a);
                if ($i < 15) {
                    $clock->freeze($start->modify('+' . (6 + mt_rand(0, 12)) . ' hours'));
                    $lab->verifyItem((int) $it['id'], $b);
                }
            }
            if ($i < 14 && $db->value('SELECT status FROM lab_orders WHERE id = :id', ['id' => $orderId]) === 'verified') {
                $clock->freeze($start->modify('+20 hours'));
                $lab->releaseOrder($orderId, $b);
            }
        }

        // Appointments for the demo patient account.
        $clock->freeze($now);
        $demo = $db->one("SELECT u.id AS user_id, p.id AS patient_id FROM users u JOIN patients p ON p.user_id = u.id WHERE u.email = 'patient@vitaliq.example'");
        if ($demo !== null) {
            $appointments = $this->app->service(AppointmentService::class);
            $day = static fn (int $d): string => $now->modify("+{$d} days")->format('Y-m-d');
            $appointments->book((int) $demo['user_id'], (int) $demo['patient_id'], ['kind' => 'consultation', 'facility_id' => (string) $facilities['BLR-IND'], 'date' => $day(3), 'slot' => '10:20', 'reason' => 'Review of lab results']);
            $appointments->book((int) $demo['user_id'], (int) $demo['patient_id'], ['kind' => 'teleconsultation', 'date' => $day(9), 'slot' => '18:40', 'reason' => 'Follow-up']);
        }
        $db->execute("INSERT INTO app_settings (name, value) VALUES ('platform_lab_seeded_at', :v)", ['v' => $now->format('Y-m-d H:i:s')]);
    }

    private function staff(string $role, string $name, string $email): int
    {
        $password = 'Vq-' . bin2hex(random_bytes(5)) . '-' . random_int(10, 99);
        $id = $this->app->db->insert(
            'INSERT INTO users (role, full_name, email, password_hash) VALUES (:r, :n, :e, :h)',
            ['r' => $role, 'n' => $name, 'e' => $email, 'h' => Passwords::hash($password)]
        );
        $this->credentials[] = ['role' => $role, 'name' => $name, 'email' => $email, 'password' => $password];
        return $id;
    }

    /** A plausible result for a demo order; about one in six falls outside the range. */
    private function value(string $code): string
    {
        $out = mt_rand(1, 6) === 1;
        return match ($code) {
            'HB' => number_format($out ? mt_rand(85, 115) / 10 : mt_rand(125, 160) / 10, 1, '.', ''),
            'WBC' => number_format($out ? mt_rand(115, 160) / 10 : mt_rand(45, 100) / 10, 1, '.', ''),
            'PLT' => (string) ($out ? mt_rand(80, 140) : mt_rand(170, 400)),
            'GLUF' => (string) ($out ? mt_rand(110, 180) : mt_rand(75, 98)),
            'HBA1C' => number_format($out ? mt_rand(60, 80) / 10 : mt_rand(45, 55) / 10, 1, '.', ''),
            'CREA' => number_format($out ? mt_rand(140, 220) / 100 : mt_rand(65, 100) / 100, 2, '.', ''),
            'CRP' => number_format($out ? mt_rand(150, 600) / 10 : mt_rand(5, 80) / 10, 1, '.', ''),
            'DENNS1' => $out ? 'Positive' : 'Negative',
            'COVPCR' => $out ? 'Detected' : 'Not detected',
            default => '1',
        };
    }
}
