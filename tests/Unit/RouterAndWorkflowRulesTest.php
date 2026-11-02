<?php

declare(strict_types=1);

namespace Vitaliq\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Vitaliq\Core\HttpException;
use Vitaliq\Core\Router;
use Vitaliq\Services\TestingService;
use Vitaliq\Support\Labels;

final class RouterAndWorkflowRulesTest extends TestCase
{
    private function router(): Router
    {
        $r = new Router();
        (require VQ_ROOT . '/src/routes.php')($r);
        return $r;
    }

    public function testParameterConstraints(): void
    {
        $r = $this->router();
        $this->assertSame(['id' => '12'], $r->match('GET', '/patient/requests/12')['params']);
        foreach (['/patient/requests/0', '/patient/requests/012', '/patient/requests/1;DROP', '/patient/requests/-1'] as $bad) {
            try {
                $r->match('GET', $bad);
                $this->fail("{$bad} should not match");
            } catch (HttpException $e) {
                $this->assertSame(404, $e->status);
            }
        }
    }

    public function testMethodNotAllowed(): void
    {
        try {
            $this->router()->match('GET', '/logout');
            $this->fail('GET /logout must not be routable');
        } catch (HttpException $e) {
            $this->assertSame(405, $e->status);
        }
    }

    public function testStateChangingRoutesAreNeverGet(): void
    {
        $verbs = ['assign', 'collect', 'process', 'result', 'release', 'reject', 'cancel', 'delete', 'toggle', 'logout', 'read', 'execute', 'reinvite'];
        foreach ($this->router()->list() as $route) {
            foreach ($verbs as $verb) {
                if (str_ends_with($route['pattern'], '/' . $verb) || str_ends_with($route['pattern'], '-' . $verb)) {
                    $this->assertSame('POST', $route['method'], $route['pattern'] . ' changes state and must be POST');
                }
            }
        }
    }

    public function testEveryAdminAndQaRouteRequiresARole(): void
    {
        foreach ($this->router()->list() as $route) {
            $p = $route['pattern'];
            if (str_starts_with($p, '/admin') || str_starts_with($p, '/api/admin')) {
                $this->assertContains('role:admin', $route['middleware'], $p);
            }
            if (str_starts_with($p, '/qa') || str_starts_with($p, '/api/qa')) {
                $this->assertContains('role:qa,admin', $route['middleware'], $p);
            }
            if (str_starts_with($p, '/patient')) {
                $this->assertContains('role:patient', $route['middleware'], $p);
            }
            if (str_starts_with($p, '/staff')) {
                $roles = array_values(array_filter($route['middleware'], static fn ($m) => str_starts_with($m, 'role:')));
                $this->assertCount(1, $roles, $p);
                $this->assertContains('phlebotomist', explode(',', substr($roles[0], 5)), $p);
                $this->assertNotContains('patient', explode(',', substr($roles[0], 5)), $p);
            }
            if (str_starts_with($p, '/lab')) {
                $this->assertContains('role:lab,admin', $route['middleware'], $p);
            }
            if (str_starts_with($p, '/surveillance')) {
                $this->assertContains('role:officer,admin', $route['middleware'], $p);
            }
        }
    }

    public function testTransitionTableCoversEveryStatus(): void
    {
        $this->assertSame(array_keys(Labels::STATUSES), array_keys(TestingService::TRANSITIONS));
        foreach (TestingService::TRANSITIONS as $targets) {
            foreach ($targets as $to) {
                $this->assertArrayHasKey($to, Labels::STATUSES);
            }
        }
    }

    public function testAllowedAndForbiddenTransitions(): void
    {
        $this->assertTrue(TestingService::canTransition('submitted', 'assigned'));
        $this->assertTrue(TestingService::canTransition('assigned', 'sample_collected'));
        $this->assertTrue(TestingService::canTransition('completed', 'result_available'));
        $this->assertFalse(TestingService::canTransition('submitted', 'result_available'));
        $this->assertFalse(TestingService::canTransition('submitted', 'completed'));
        $this->assertFalse(TestingService::canTransition('sample_collected', 'cancelled'), 'Cannot cancel after collection');
        $this->assertFalse(TestingService::canTransition('in_progress', 'rejected'));
        $this->assertFalse(TestingService::canTransition('unknown', 'assigned'));
        foreach (TestingService::TERMINAL as $terminal) {
            $this->assertSame([], TestingService::TRANSITIONS[$terminal], "{$terminal} must be terminal");
        }
    }
}
