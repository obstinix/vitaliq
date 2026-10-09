<?php

declare(strict_types=1);

namespace Vitaliq\Support;

use DateTimeImmutable;
use RuntimeException;
use Vitaliq\Auth\Passwords;
use Vitaliq\Core\App;
use Vitaliq\Services\PatientService;
use Vitaliq\Services\PhlebotomistService;
use Vitaliq\Services\QaService;
use Vitaliq\Services\TestingService;
use Vitaliq\Services\ValidationFailed;

/**
 * Synthetic demonstration data. Every person, phone number and request is
 * invented. Records are created through the real services with the clock
 * frozen at each step, so history, notifications and audit entries are
 * produced by the same code paths the application uses.
 *
 * Passwords are random for every install and printed once by bin/seed.php.
 */
final class DemoSeeder
{
    private const FIRST = ['Aarav', 'Ananya', 'Vivaan', 'Diya', 'Ishaan', 'Meera', 'Kabir', 'Saanvi', 'Arjun', 'Kavya',
        'Rohan', 'Priya', 'Aditya', 'Nisha', 'Siddharth', 'Lakshmi', 'Farhan', 'Zoya', 'Harpreet', 'Anjali',
        'Joseph', 'Mary', 'Karthik', 'Divya', 'Rahul', 'Sneha', 'Imran', 'Fatima', 'Vikram', 'Pooja', 'Tenzin', 'Lalremruati'];
    private const LAST = ['Sharma', 'Iyer', 'Reddy', 'Khan', 'Singh', 'Nair', 'Das', 'Patel', 'Menon', 'Gupta',
        'Fernandes', 'Banerjee', 'Kulkarni', 'Rao', 'Chatterjee', 'Joshi', 'Pillai', 'Mehta', 'Ali', 'Bhat'];
    /** City, state code, PIN prefix */
    private const PLACES = [
        ['Bengaluru', 'KA', '5600'], ['Mumbai', 'MH', '4000'], ['Delhi', 'DL', '1100'], ['Chennai', 'TN', '6000'],
        ['Hyderabad', 'TG', '5000'], ['Kolkata', 'WB', '7000'], ['Pune', 'MH', '4110'], ['Jaipur', 'RJ', '3020'],
        ['Lucknow', 'UP', '2260'], ['Kochi', 'KL', '6820'], ['Ahmedabad', 'GJ', '3800'], ['Guwahati', 'AS', '7810'],
        ['Chandigarh', 'CH', '1600'], ['Bhubaneswar', 'OD', '7510'],
    ];

    /** @var list<array{role:string,name:string,email:string,password:string}> */
    public array $credentials = [];
    private int $mobileCounter = 0;

    public function __construct(private readonly App $app)
    {
    }

    public function run(): void
    {
        if ($this->app->db->value("SELECT value FROM app_settings WHERE name = 'demo_data_seeded_at'") !== null) {
            throw new RuntimeException('Demo data has already been seeded. Use php bin/migrate.php --fresh first to start again.');
        }
        if ((int) $this->app->db->value('SELECT COUNT(*) FROM test_requests') > 0) {
            throw new RuntimeException('This database already contains testing requests; refusing to add demo data.');
        }
        mt_srand(20261010);
        $now = new DateTimeImmutable('now');
        $clock = $this->app->clock;

        $clock->freeze($now->modify('-60 days'));
        $staff = $this->createStaff();
        $phlebIds = $this->createPhlebotomists($staff);
        [$accountUsers, $demoPatient] = $this->createPatients();

        $this->createRequests($now, $phlebIds, $staff['admin'], $accountUsers, $demoPatient);
        $clock->freeze($now);
        $this->createAnnouncements($now, $staff['admin']);
        $this->createComments($now);
        $this->createDefects($now, $staff['qa']);
        $this->app->service(QaService::class)->syncCatalogue();

        // Older notifications are marked read so the bell reflects recent activity.
        $this->app->db->execute('UPDATE notifications SET read_at = created_at WHERE created_at < :t', [
            't' => $now->modify('-2 days')->format('Y-m-d H:i:s'),
        ]);
        $this->app->db->execute(
            "INSERT INTO app_settings (name, value) VALUES ('demo_data_seeded_at', :v)",
            ['v' => $now->format('Y-m-d H:i:s')]
        );
    }

    private function password(): string
    {
        return 'Vq-' . bin2hex(random_bytes(5)) . '-' . random_int(10, 99);
    }

    private function mobile(): string
    {
        // Synthetic numbers in a single block, so demo data is easy to recognise.
        return sprintf('9000%06d', 100000 + (++$this->mobileCounter) * 37);
    }

    /** @return array{admin:int,qa:int,phleb1:int,phleb2:int} */
    private function createStaff(): array
    {
        $make = function (string $role, string $name, string $email): int {
            $password = $this->password();
            $id = $this->app->db->insert(
                'INSERT INTO users (role, full_name, email, mobile, password_hash) VALUES (:r, :n, :e, NULL, :h)',
                ['r' => $role, 'n' => $name, 'e' => $email, 'h' => Passwords::hash($password)]
            );
            $this->credentials[] = ['role' => $role, 'name' => $name, 'email' => $email, 'password' => $password];
            return $id;
        };
        return [
            'admin' => $make('admin', 'Asha Varghese', 'admin@vitaliq.example'),
            'qa' => $make('qa', 'Nikhil Deshpande', 'qa@vitaliq.example'),
            'phleb1' => $make('phlebotomist', 'Ramesh Kumar', 'ramesh.kumar@vitaliq.example'),
            'phleb2' => $make('phlebotomist', 'Shalini Mathew', 'shalini.mathew@vitaliq.example'),
        ];
    }

    /**
     * @param array{admin:int,qa:int,phleb1:int,phleb2:int} $staff
     * @return list<int>
     */
    private function createPhlebotomists(array $staff): array
    {
        $service = $this->app->service(PhlebotomistService::class);
        $people = [
            ['Ramesh Kumar', 'Bengaluru', 'KA', $staff['phleb1']],
            ['Shalini Mathew', 'Kochi', 'KL', $staff['phleb2']],
            ['Deepak Yadav', 'Delhi', 'DL', null],
            ['Sunita Pawar', 'Mumbai', 'MH', null],
            ['Abdul Rahman', 'Hyderabad', 'TG', null],
            ['Moumita Ghosh', 'Kolkata', 'WB', null],
            ['Gurpreet Kaur', 'Chandigarh', 'CH', null],
        ];
        $ids = [];
        foreach ($people as [$name, $city, $state, $userId]) {
            $id = $service->create(['full_name' => $name, 'mobile' => $this->mobile(), 'base_city' => $city, 'state_code' => $state]);
            if ($userId !== null) {
                $this->app->db->execute('UPDATE phlebotomists SET user_id = :u WHERE id = :id', ['u' => $userId, 'id' => $id]);
            }
            $ids[] = $id;
        }
        return $ids;
    }

    /** @return array{0:list<int>,1:int} account user ids, and the documented demo patient id */
    private function createPatients(): array
    {
        $service = $this->app->service(PatientService::class);
        $users = [];
        $demoPassword = $this->password();
        $demo = $service->register([
            'full_name' => 'Kavya Menon', 'email' => 'patient@vitaliq.example', 'mobile' => $this->mobile(),
            'date_of_birth' => '1994-07-21', 'sex' => 'female', 'password' => $demoPassword,
            'password_confirmation' => $demoPassword, 'consent' => '1',
        ]);
        $this->app->db->execute(
            "UPDATE patients SET address_line = '14 Temple Road, Indiranagar', city = 'Bengaluru', state_code = 'KA', pincode = '560038' WHERE user_id = :u",
            ['u' => $demo]
        );
        $this->credentials[] = ['role' => 'patient', 'name' => 'Kavya Menon', 'email' => 'patient@vitaliq.example', 'password' => $demoPassword];

        for ($i = 0; $i < 24; $i++) {
            $first = self::FIRST[$i % count(self::FIRST)];
            $last = self::LAST[mt_rand(0, count(self::LAST) - 1)];
            $password = $this->password();
            try {
                $users[] = $service->register([
                    'full_name' => "{$first} {$last}",
                    'email' => strtolower("{$first}.{$last}.{$i}@example.com"),
                    'mobile' => $this->mobile(),
                    'date_of_birth' => sprintf('%d-%02d-%02d', mt_rand(1950, 2006), mt_rand(1, 12), mt_rand(1, 28)),
                    'sex' => ['female', 'male', 'undisclosed'][mt_rand(0, 2)],
                    'password' => $password, 'password_confirmation' => $password, 'consent' => '1',
                ]);
            } catch (ValidationFailed) {
                continue;
            }
        }
        return [$users, $demo];
    }

    /**
     * @param list<int> $phlebIds
     * @param list<int> $accountUsers
     */
    private function createRequests(DateTimeImmutable $now, array $phlebIds, int $adminId, array $accountUsers, int $demoPatient): void
    {
        $testing = $this->app->service(TestingService::class);
        $clock = $this->app->clock;
        $admin = ['id' => $adminId, 'role' => 'admin'];
        $symptomKeys = array_keys(Labels::SYMPTOMS);

        $plans = [];
        // The documented demo patient gets a released result and one request in progress.
        $plans[] = ['user' => $demoPatient, 'days' => 20, 'type' => 'rt_pcr', 'target' => 'result_available', 'place' => 0];
        $plans[] = ['user' => $demoPatient, 'days' => 1.2, 'type' => 'rapid_antigen', 'target' => 'sample_collected', 'place' => 0];
        for ($i = 0; $i < 150; $i++) {
            $days = round(mt_rand(0, 4400) / 100, 2);
            $roll = mt_rand(1, 100);
            $target = match (true) {
                $roll <= 5 => 'cancelled',
                $roll <= 8 => 'rejected',
                $days > 5 => 'result_available',
                $days > 3 => ['completed', 'result_available', 'result_available'][mt_rand(0, 2)],
                $days > 1.5 => ['sample_collected', 'in_progress', 'completed'][mt_rand(0, 2)],
                $days > 0.5 => ['assigned', 'sample_collected'][mt_rand(0, 1)],
                default => ['submitted', 'submitted', 'assigned'][mt_rand(0, 2)],
            };
            $useAccount = $accountUsers !== [] && mt_rand(1, 100) <= 45;
            $plans[] = [
                'user' => $useAccount ? $accountUsers[mt_rand(0, count($accountUsers) - 1)] : null,
                'days' => $days,
                'type' => mt_rand(1, 100) <= 62 ? 'rt_pcr' : 'rapid_antigen',
                'target' => $target,
                'place' => mt_rand(0, count(self::PLACES) - 1),
            ];
        }
        usort($plans, static fn ($a, $b) => $b['days'] <=> $a['days']);

        $guestIndex = 0;
        foreach ($plans as $n => $plan) {
            [$city, $state, $pinPrefix] = self::PLACES[$plan['place']];
            $at = $now->modify('-' . (int) round($plan['days'] * 86400) . ' seconds');
            $clock->freeze($at);
            $symptoms = mt_rand(1, 100) <= 55 ? array_values(array_unique([$symptomKeys[mt_rand(0, 3)], $symptomKeys[mt_rand(0, 11)]])) : [];
            $input = [
                'test_type' => $plan['type'],
                'reason' => $symptoms !== [] ? 'symptoms' : ['exposure', 'travel', 'pre_procedure', 'screening'][mt_rand(0, 3)],
                'symptoms' => $symptoms,
                'symptom_onset' => $symptoms !== [] ? $at->modify('-' . mt_rand(0, 4) . ' days')->format('Y-m-d') : '',
                'exposure' => ['yes', 'no', 'unsure'][mt_rand(0, 2)],
                'collection_mode' => mt_rand(1, 100) <= 60 ? 'home' : 'centre',
                'collection_address' => (mt_rand(1, 400)) . ', ' . ['MG Road', 'Station Road', 'Park Street', 'Lake View Colony', 'Gandhi Nagar'][mt_rand(0, 4)],
                'city' => $city,
                'state_code' => $state,
                'pincode' => $pinPrefix . sprintf('%02d', mt_rand(1, 99)),
                'preferred_date' => $at->modify('+' . mt_rand(0, 1) . ' days')->format('Y-m-d'),
                'preferred_slot' => ['morning', 'afternoon', 'evening'][mt_rand(0, 2)],
                'notes' => '',
                'consent' => '1',
            ];
            try {
                if ($plan['user'] !== null) {
                    $created = $testing->submitForUser($plan['user'], $input, bin2hex(random_bytes(16)));
                } else {
                    $guestIndex++;
                    $first = self::FIRST[mt_rand(0, count(self::FIRST) - 1)];
                    $last = self::LAST[mt_rand(0, count(self::LAST) - 1)];
                    $created = $testing->submitGuest($input + [
                        'full_name' => "{$first} {$last}",
                        'date_of_birth' => sprintf('%d-%02d-%02d', mt_rand(1945, 2018), mt_rand(1, 12), mt_rand(1, 28)),
                        'sex' => ['female', 'male', 'undisclosed'][mt_rand(0, 2)],
                        'mobile' => $this->mobile(),
                        'email' => mt_rand(0, 1) ? strtolower("{$first}.{$guestIndex}@example.com") : '',
                    ], bin2hex(random_bytes(16)));
                }
            } catch (ValidationFailed) {
                continue; // e.g. an account already has an active request of this type
            }
            $this->advance($testing, $created['id'], $plan['target'], $at, $now, $admin, $phlebIds, $plan['type'], $n);
        }
        $clock->freeze($now);
    }

    /**
     * @param array{id:int,role:string} $admin
     * @param list<int> $phlebIds
     */
    private function advance(TestingService $testing, int $id, string $target, DateTimeImmutable $start, DateTimeImmutable $now, array $admin, array $phlebIds, string $type, int $n): void
    {
        $clock = $this->app->clock;
        $t = $start;
        $step = function (int $minHours, int $maxHours) use (&$t, $now): bool {
            $next = $t->modify('+' . mt_rand($minHours * 60, $maxHours * 60) . ' minutes');
            if ($next >= $now) {
                return false;
            }
            $t = $next;
            return true;
        };

        if ($target === 'cancelled') {
            if ($step(1, 8)) {
                $clock->freeze($t);
                $testing->cancel($id, $admin, 'Patient called the helpdesk to cancel (demo record)');
            }
            return;
        }
        $order = ['assigned', 'sample_collected', 'in_progress', 'completed', 'result_available'];
        $stopAt = $target === 'rejected' ? 1 : array_search($target, $order, true);
        if ($target === 'submitted' || $stopAt === false) {
            return;
        }
        for ($i = 0; $i <= $stopAt; $i++) {
            $stage = $order[$i];
            $ok = match ($stage) {
                'assigned' => $step(1, 6),
                'sample_collected' => $step(5, 26),
                'in_progress' => $step(1, 5),
                'completed' => $step($type === 'rt_pcr' ? 8 : 1, $type === 'rt_pcr' ? 30 : 3),
                'result_available' => $step(1, 6),
            };
            if (!$ok) {
                return;
            }
            $clock->freeze($t);
            switch ($stage) {
                case 'assigned':
                    $testing->assign($id, $phlebIds[$n % count($phlebIds)], $admin);
                    break;
                case 'sample_collected':
                    if ($target === 'rejected') {
                        $testing->reject($id, $admin, 'Collection address could not be reached (demo record)');
                        return;
                    }
                    $testing->recordCollection($id, [
                        'specimen' => $type === 'rt_pcr' ? 'nasopharyngeal' : 'nasal',
                        'barcode' => sprintf('VQS%09d', 100000000 + $id * 7919),
                    ], $admin);
                    break;
                case 'in_progress':
                    $testing->startProcessing($id, $admin);
                    break;
                case 'completed':
                    $roll = mt_rand(1, 100);
                    $outcome = $roll <= 14 ? 'positive' : ($roll <= 97 ? 'negative' : 'inconclusive');
                    $testing->recordResult($id, [
                        'outcome' => $outcome,
                        'ct_value' => $type === 'rt_pcr' && $outcome === 'positive' ? (string) (mt_rand(180, 340) / 10) : '',
                        'remarks' => '',
                    ], $admin);
                    break;
                case 'result_available':
                    $testing->releaseResult($id, $admin);
                    break;
            }
        }
    }

    private function createAnnouncements(DateTimeImmutable $now, int $adminId): void
    {
        $rows = [
            ['public_health', 'WHO: COVID-19 is no longer a public health emergency of international concern',
                'WHO’s Director-General accepted the Emergency Committee’s advice that COVID-19 is now an established and ongoing health issue rather than a PHEIC.',
                'World Health Organization', 'https://www.who.int/news/item/05-05-2023-statement-on-the-fifteenth-meeting-of-the-international-health-regulations-(2005)-emergency-committee-regarding-the-coronavirus-disease-(covid-19)-pandemic',
                '2023-05-05', 0],
            ['public_health', 'WHO characterises COVID-19 as a pandemic',
                'At a media briefing, WHO’s Director-General said COVID-19 could be characterised as a pandemic, citing its spread and severity.',
                'World Health Organization', 'https://www.who.int/news-room/speeches/item/who-director-general-s-opening-remarks-at-the-media-briefing-on-covid-19---11-march-2020',
                '2020-03-11', 0],
            ['public_health', 'WHO declares a public health emergency of international concern',
                'Following the second meeting of the IHR Emergency Committee, WHO declared the novel coronavirus outbreak a PHEIC.',
                'World Health Organization', 'https://www.who.int/news/item/30-01-2020-statement-on-the-second-meeting-of-the-international-health-regulations-(2005)-emergency-committee-regarding-the-outbreak-of-novel-coronavirus-(2019-ncov)',
                '2020-01-30', 0],
            ['service', 'Evening home-collection slots added',
                'Requests can now ask for a 4–7 pm home collection. This is an example service notice included with the demonstration data.',
                null, null, $now->modify('-4 days')->format('Y-m-d'), 1],
            ['service', 'Track any request with three details',
                'Your reference, mobile number and date of birth are enough to see each step of a request without an account. Example notice for the demonstration.',
                null, null, $now->modify('-12 days')->format('Y-m-d'), 1],
        ];
        foreach ($rows as [$cat, $title, $summary, $sourceName, $sourceUrl, $date, $demo]) {
            $this->app->db->insert(
                'INSERT INTO announcements (category, title, summary, source_name, source_url, published_on, is_demo, is_published, created_by_user_id)
                 VALUES (:c, :t, :s, :sn, :su, :d, :demo, 1, :u)',
                ['c' => $cat, 't' => $title, 's' => $summary, 'sn' => $sourceName, 'su' => $sourceUrl, 'd' => $date, 'demo' => $demo, 'u' => $adminId]
            );
        }
    }

    private function createComments(DateTimeImmutable $now): void
    {
        $comments = [
            ['Meera Pillai', 'testing', 'The step-by-step status made waiting for my father’s result much less stressful. We could see when the sample reached the lab.', 'approved'],
            ['Arjun Rao', 'website', 'Clear and calm website. The symptoms page linked to the WHO and CDC pages, which I appreciated.', 'approved'],
            ['Fatima Ali', 'testing', 'The phlebotomist arrived in the evening slot I chose and checked my reference before taking the swab.', 'approved'],
            ['Tenzin Norbu', 'prevention', 'Opening windows at home was a simple change. Good to see ventilation explained plainly.', 'approved'],
            ['Divya Nair', 'general', 'Would be helpful to get an SMS when the result is released, not only an in-app notification.', 'approved'],
            ['Rohan Gupta', 'testing', 'My antigen test was negative but I still had symptoms. The note about testing again after 48 hours was useful.', 'approved'],
            ['Lakshmi Iyer', 'website', 'Linking my earlier guest request to my new account worked with my reference and date of birth.', 'approved'],
            ['Harpreet Singh', 'general', 'Please add a Punjabi translation of the guidance pages.', 'pending'],
            ['Anjali Das', 'testing', 'Can I choose a centre near my office instead of home collection? I could not find a list of centres.', 'pending'],
            ['Buy Followers', 'general', 'Cheap followers and likes!!! visit http://spam.example http://spam.example/2 http://spam.example/3', 'spam'],
        ];
        foreach ($comments as $i => [$name, $topic, $body, $status]) {
            $created = $now->modify('-' . (20 - $i * 2) . ' days')->modify('+' . (9 + $i) . ' hours');
            $this->app->db->insert(
                'INSERT INTO comments (author_name, topic, body, status, is_demo, created_at, moderated_at)
                 VALUES (:n, :t, :b, :s, 1, :c, :m)',
                ['n' => $name, 't' => $topic, 'b' => $body, 's' => $status, 'c' => $created->format('Y-m-d H:i:s'),
                 'm' => $status === 'pending' ? null : $created->modify('+5 hours')->format('Y-m-d H:i:s')]
            );
        }
    }

    private function createDefects(DateTimeImmutable $now, int $qaUser): void
    {
        $file = VQ_ROOT . '/database/seeds/defects.json';
        $defects = json_decode((string) file_get_contents($file), true, 512, JSON_THROW_ON_ERROR);
        foreach ($defects as $i => $d) {
            $this->app->db->insert(
                'INSERT INTO defects (code, title, description, severity, priority, module, steps_to_reproduce, expected_behaviour,
                    actual_behaviour, status, origin, reported_by_user_id, resolution_notes, resolved_at, created_at)
                 VALUES (:code, :t, :d, :s, :p, :m, :st, :e, :a, :status, :o, :u, :rn, :ra, :c)',
                [
                    'code' => sprintf('DEF-%03d', $i + 1), 't' => $d['title'], 'd' => $d['description'], 's' => $d['severity'],
                    'p' => $d['priority'], 'm' => $d['module'], 'st' => $d['steps'], 'e' => $d['expected'], 'a' => $d['actual'],
                    'status' => $d['status'], 'o' => $d['origin'], 'u' => $d['origin'] === 'demo' ? $qaUser : null,
                    'rn' => $d['resolution'] ?? null, 'ra' => isset($d['resolved_on']) ? $d['resolved_on'] . ' 12:00:00' : null,
                    'c' => ($d['found_on'] ?? $now->format('Y-m-d')) . ' 10:00:00',
                ]
            );
        }
    }
}
