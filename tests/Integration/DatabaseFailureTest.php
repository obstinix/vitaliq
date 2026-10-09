<?php

declare(strict_types=1);

namespace Vitaliq\Tests\Integration;

use PHPUnit\Framework\TestCase;
use Vitaliq\Core\App;
use Vitaliq\Core\Config;
use Vitaliq\Core\Database;
use Vitaliq\Core\DatabaseUnavailable;
use Vitaliq\Core\Request;
use Vitaliq\Core\Session;

/**
 * Simulates a database outage with an unreachable port and wrong password.
 */
final class DatabaseFailureTest extends TestCase
{
    private function brokenApp(): App
    {
        $config = Config::fromEnv();
        $config['db']['port'] = 1;
        $config['db']['pass'] = 'definitely-wrong-password';
        return new App($config, new Database($config['db']));
    }

    public function testConnectionFailureRaisesDatabaseUnavailable(): void
    {
        $config = Config::fromEnv();
        $config['db']['pass'] = 'definitely-wrong-password';
        $this->expectException(DatabaseUnavailable::class);
        (new Database($config['db']))->pdo();
    }

    public function testPagesNeedingTheDatabaseReturnAFriendly503(): void
    {
        $storage = [];
        $response = $this->brokenApp()->handle(new Request('GET', '/statistics'), new Session($storage));
        $this->assertSame(503, $response->status);
        $this->assertStringContainsString('temporarily unavailable', $response->body);
        $this->assertStringNotContainsString('SQLSTATE', $response->body);
        $this->assertStringNotContainsString('definitely-wrong-password', $response->body);
        $this->assertStringNotContainsString('PDOException', $response->body);
    }

    public function testJsonEndpointsReturnJson503(): void
    {
        $storage = [];
        $response = $this->brokenApp()->handle(new Request('GET', '/api/statistics'), new Session($storage));
        $this->assertSame(503, $response->status);
        $this->assertSame(['error' => 'Vitaliq is temporarily unavailable. Please try again shortly.'], json_decode($response->body, true));
    }

    public function testStaticInformationPagesStillWork(): void
    {
        $storage = [];
        $response = $this->brokenApp()->handle(new Request('GET', '/symptoms'), new Session($storage));
        $this->assertSame(200, $response->status, 'Health guidance stays available during a database outage');
    }
}
