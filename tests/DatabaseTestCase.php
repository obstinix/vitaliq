<?php

declare(strict_types=1);

namespace Vitaliq\Tests;

use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use Vitaliq\Auth\Passwords;
use Vitaliq\Core\App;
use Vitaliq\Core\Clock;
use Vitaliq\Core\Config;
use Vitaliq\Core\Database;
use Vitaliq\Core\Session;
use Vitaliq\Services\PatientService;
use Vitaliq\Services\PhlebotomistService;

/**
 * Base class for tests that need the database. Every test starts from empty
 * tables (reference data such as states is kept) and a frozen clock.
 */
abstract class DatabaseTestCase extends TestCase
{
    public const NOW = '2026-10-10 10:00:00';

    protected App $app;
    protected Database $db;
    protected Clock $clock;
    private static ?Database $shared = null;
    private int $mobileSeq = 0;

    protected function setUp(): void
    {
        if (!VQ_TEST_DB_AVAILABLE) {
            $this->markTestSkipped('Test database is not available.');
        }
        self::$shared ??= new Database(Config::fromEnv()['db']);
        $this->db = self::$shared;
        $this->clock = new Clock();
        $this->clock->freeze(new DateTimeImmutable(self::NOW));
        $this->app = new App(Config::fromEnv(), $this->db, $this->clock);
        $this->truncate();
    }

    private function truncate(): void
    {
        $keep = ['states', 'schema_migrations'];
        $tables = $this->db->all(
            'SELECT table_name AS t FROM information_schema.tables WHERE table_schema = :s',
            ['s' => $this->db->databaseName()]
        );
        $pdo = $this->db->pdo();
        $pdo->exec('SET FOREIGN_KEY_CHECKS = 0');
        foreach ($tables as $row) {
            if (!in_array($row['t'], $keep, true)) {
                $pdo->exec('DELETE FROM `' . $row['t'] . '`');
            }
        }
        $pdo->exec('SET FOREIGN_KEY_CHECKS = 1');
    }

    protected function mobile(): string
    {
        return sprintf('98%08d', 10000000 + (++$this->mobileSeq));
    }

    protected function createUser(string $role, string $email, string $password = 'correct horse 42', bool $active = true): int
    {
        return $this->db->insert(
            'INSERT INTO users (role, full_name, email, mobile, password_hash, is_active) VALUES (:r, :n, :e, :m, :h, :a)',
            ['r' => $role, 'n' => ucfirst($role) . ' User', 'e' => $email, 'm' => $role === 'patient' ? $this->mobile() : null,
             'h' => Passwords::hash($password), 'a' => $active ? 1 : 0]
        );
    }

    protected function createAdmin(string $email = 'admin@vitaliq.test'): int
    {
        return $this->createUser('admin', $email);
    }

    /** Registered patient via the real service. Returns the user id. */
    protected function createPatient(string $email = 'patient@vitaliq.test', array $overrides = []): int
    {
        return $this->app->service(PatientService::class)->register($overrides + [
            'full_name' => 'Kavya Menon',
            'email' => $email,
            'mobile' => $this->mobile(),
            'date_of_birth' => '1994-07-21',
            'sex' => 'female',
            'password' => 'correct horse 42',
            'password_confirmation' => 'correct horse 42',
            'consent' => '1',
        ]);
    }

    /** Phlebotomist record, optionally with a portal login. Returns [phlebotomist id, user id|null]. */
    protected function createPhlebotomist(string $name = 'Ramesh Kumar', ?string $loginEmail = null): array
    {
        $id = $this->app->service(PhlebotomistService::class)->create([
            'full_name' => $name, 'mobile' => $this->mobile(), 'base_city' => 'Bengaluru', 'state_code' => 'KA',
        ]);
        $userId = null;
        if ($loginEmail !== null) {
            $userId = $this->createUser('phlebotomist', $loginEmail);
            $this->db->execute('UPDATE phlebotomists SET user_id = :u WHERE id = :id', ['u' => $userId, 'id' => $id]);
        }
        return [$id, $userId];
    }

    /** @return array<string,mixed> valid test-details input */
    protected function testInput(array $overrides = []): array
    {
        return $overrides + [
            'test_type' => 'rt_pcr',
            'reason' => 'symptoms',
            'symptoms' => ['fever', 'cough'],
            'symptom_onset' => '2026-10-08',
            'exposure' => 'unsure',
            'collection_mode' => 'home',
            'collection_address' => '14 Temple Road, Indiranagar',
            'city' => 'Bengaluru',
            'state_code' => 'KA',
            'pincode' => '560038',
            'preferred_date' => '2026-10-11',
            'preferred_slot' => 'morning',
            'notes' => '',
            'consent' => '1',
        ];
    }

    /** @return array<string,mixed> valid guest (new patient) input */
    protected function guestInput(array $overrides = []): array
    {
        return $overrides + [
            'full_name' => 'Arjun Rao',
            'date_of_birth' => '1988-03-14',
            'sex' => 'male',
            'mobile' => '+91 98765 43210',
            'email' => 'arjun@example.com',
        ] + $this->testInput();
    }

    /** Sign a user in on the application's current session. */
    protected function actingAs(int $userId): void
    {
        $version = (int) $this->db->value('SELECT session_version FROM users WHERE id = :id', ['id' => $userId]);
        $storage = [];
        $session = new Session($storage);
        $session->set('user_id', $userId);
        $session->set('session_version', $version);
        $session->set('last_activity', $this->clock->now()->getTimestamp());
        $this->setSession($session);
    }

    protected function setSession(Session $session): void
    {
        $ref = new \ReflectionProperty(App::class, 'session');
        $ref->setValue($this->app, $session);
        $auth = new \ReflectionProperty(App::class, 'auth');
        $auth->setValue($this->app, null);
        $csrf = new \ReflectionProperty(App::class, 'csrf');
        $csrf->setValue($this->app, null);
    }

    /** @return array{id:int,role:string} */
    protected function adminActor(?int $id = null): array
    {
        return ['id' => $id ?? $this->createAdmin(), 'role' => 'admin'];
    }
}
