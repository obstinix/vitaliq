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

    /** Runs each part that has not run yet; each part records its own marker. */
    public function run(): void
    {
        $db = $this->app->db;
        if ($db->value("SELECT value FROM app_settings WHERE name = 'demo_data_seeded_at'") === null) {
            throw new RuntimeException('Run php bin/seed.php first: the platform demo builds on the base demo data.');
        }
        $parts = ['platform_lab_seeded_at' => 'seedLab', 'platform_surveillance_seeded_at' => 'seedSurveillance'];
        $ran = 0;
        foreach ($parts as $marker => $method) {
            if ($db->value('SELECT value FROM app_settings WHERE name = :n', ['n' => $marker]) !== null) {
                continue;
            }
            $this->{$method}();
            $db->execute('INSERT INTO app_settings (name, value) VALUES (:n, :v)', ['n' => $marker, 'v' => date('Y-m-d H:i:s')]);
            $ran++;
        }
        $this->app->clock->freeze(new DateTimeImmutable('now'));
        if ($ran === 0) {
            throw new RuntimeException('Platform demo data has already been seeded.');
        }
    }

    private function seedLab(): void
    {
        $db = $this->app->db;
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
    }

    /**
     * Two years of synthetic daily returns for five states, loaded through
     * the real importer (dengue with a monsoon peak, COVID-19 with small
     * waves), an officer account and three outbreak rules.
     */
    private function seedSurveillance(): void
    {
        mt_srand(20261102);
        $officer = $this->staff('officer', 'Dr. Farah Siddiqui', 'farah.siddiqui@vitaliq.example');
        $now = new DateTimeImmutable('now');
        $this->app->clock->freeze($now);
        $states = ['KA' => 1.0, 'KL' => 1.4, 'MH' => 1.2, 'TN' => 0.9, 'DL' => 0.7];
        $csv = "disease,date,state,tests,positives\n";
        for ($d = $now->modify('-730 days'); $d < $now; $d = $d->modify('+1 day')) {
            $doy = (int) $d->format('z');
            foreach ($states as $code => $scale) {
                // Dengue: monsoon season peaking in late September, with a recent rise in Kerala.
                $season = exp(-(($doy - 265) ** 2) / (2 * 35 ** 2));
                $recent = $code === 'KL' && $d > $now->modify('-12 days') ? 2.2 : 1.0;
                $tests = (int) round((40 + 60 * $season) * $scale + mt_rand(0, 12));
                $pos = min($tests, $this->poisson((2 + 22 * $season) * $scale * $recent));
                $csv .= sprintf("dengue,%s,%s,%d,%d\n", $d->format('Y-m-d'), $code, $tests, $pos);
                // COVID-19: low background with two small waves.
                $wave = 1 + 3 * exp(-(($d->getTimestamp() - $now->modify('-400 days')->getTimestamp()) / 86400) ** 2 / (2 * 20 ** 2))
                          + 2 * exp(-(($d->getTimestamp() - $now->modify('-120 days')->getTimestamp()) / 86400) ** 2 / (2 * 15 ** 2));
                $ct = (int) round(30 * $scale + mt_rand(0, 10));
                $csv .= sprintf("covid19,%s,%s,%d,%d\n", $d->format('Y-m-d'), $code, $ct, min($ct, $this->poisson(0.6 * $scale * $wave)));
            }
        }
        $tmp = (string) tempnam(sys_get_temp_dir(), 'vqseed');
        file_put_contents($tmp, $csv);
        $imports = $this->app->service(\Vitaliq\Services\SurveillanceImportService::class);
        $id = $imports->preview(['name' => 'synthetic-returns-2y.csv', 'tmp_name' => $tmp, 'size' => strlen($csv), 'error' => UPLOAD_ERR_OK], 'Synthetic demonstration returns', $officer);
        $imports->commit($id, $officer);
        @unlink($tmp);
        $outbreak = $this->app->service(\Vitaliq\Services\OutbreakService::class);
        $outbreak->createThreshold(['disease' => 'dengue', 'method' => 'baseline', 'threshold' => '2', 'window_days' => '7'], $officer);
        $outbreak->createThreshold(['disease' => 'dengue', 'method' => 'positivity', 'threshold' => '25', 'window_days' => '7', 'min_tests' => '100'], $officer);
        $outbreak->createThreshold(['disease' => 'covid19', 'method' => 'count', 'threshold' => '40', 'window_days' => '7'], $officer);
        $outbreak->evaluate();
    }

    /** Knuth's Poisson sampler; fine for the small means used here. */
    private function poisson(float $lambda): int
    {
        $l = exp(-$lambda);
        $k = 0;
        $p = 1.0;
        do {
            $k++;
            $p *= mt_rand() / mt_getrandmax();
        } while ($p > $l && $k < 1000);
        return $k - 1;
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
