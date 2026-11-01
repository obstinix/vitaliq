<?php

declare(strict_types=1);

namespace Vitaliq\Tests\Integration;

use Vitaliq\Auth\Permissions;
use Vitaliq\Support\Navigation;
use Vitaliq\Tests\HttpTestCase;

final class NavigationAndPermissionsTest extends HttpTestCase
{
    public function testNavigationShowsOnlyRegisteredRoutesForTheRole(): void
    {
        $patient = Navigation::for('patient', $this->app->router);
        $hrefs = array_merge(...array_map(static fn ($l) => array_column($l, 'href'), array_values($patient)));
        $this->assertContains('/patient', $hrefs);
        $this->assertContains('/account', $hrefs);
        $this->assertNotContains('/admin', $hrefs);
        $gets = array_column(array_filter($this->app->router->list(), static fn ($r) => $r['method'] === 'GET'), 'pattern');
        foreach ($hrefs as $href) {
            $this->assertContains($href, $gets, "Navigation links to {$href}, which has no GET route");
        }
    }

    public function testPermissionFilterHidesLinks(): void
    {
        $all = Navigation::for('lab', $this->app->router, static fn () => true);
        $none = Navigation::for('lab', $this->app->router, static fn () => false);
        $count = static fn (array $s) => array_sum(array_map('count', $s));
        $this->assertGreaterThanOrEqual($count($none), $count($all));
        foreach ($none as $links) {
            foreach ($links as $link) {
                $this->assertNotSame('/lab/inventory', $link['href']);
            }
        }
    }

    public function testActiveLinkIsLongestPrefix(): void
    {
        $sections = ['A' => [['href' => '/patient', 'label' => 'x', 'icon' => 'home'], ['href' => '/patient/requests', 'label' => 'y', 'icon' => 'list']]];
        $this->assertSame('/patient/requests', Navigation::active($sections, '/patient/requests/12'));
        $this->assertSame('/patient', Navigation::active($sections, '/patient'));
        $this->assertSame('', Navigation::active($sections, '/patients'));
    }

    public function testRoleDefaultsAndOverrides(): void
    {
        $labId = $this->createUser('lab', 'lab@vitaliq.test');
        $perm = new Permissions($this->app);
        $lab = ['id' => $labId, 'role' => 'lab'];
        $this->assertTrue($perm->allows($lab, 'lab.verify'));
        $this->assertFalse($perm->allows($lab, 'surveillance.view'));
        $this->assertTrue($perm->allows(['id' => 1, 'role' => 'admin'], 'admin.backups'));
        $this->assertFalse($perm->allows(null, 'lab.verify'));

        $this->db->execute("INSERT INTO role_permissions (role, permission, granted, updated_at) VALUES ('lab','lab.verify',0,NOW())");
        $this->db->execute("INSERT INTO user_permissions (user_id, permission, granted, updated_at) VALUES (:u,'surveillance.view',1,NOW())", ['u' => $labId]);
        $perm->forget();
        $this->assertFalse($perm->allows($lab, 'lab.verify'), 'role override revokes the default');
        $this->assertTrue($perm->allows($lab, 'surveillance.view'), 'user override grants');

        $this->db->execute("INSERT INTO user_permissions (user_id, permission, granted, updated_at) VALUES (:u,'lab.verify',1,NOW())", ['u' => $labId]);
        $perm->forget();
        $this->assertTrue($perm->allows($lab, 'lab.verify'), 'user override beats role override');
    }

    public function testAppShellRendersRoleNavigation(): void
    {
        $this->createAdmin();
        $this->signIn('admin@vitaliq.test');
        $page = $this->get('/admin');
        $this->assertSame(200, $page->status);
        $this->assertStringContainsString('class="layout-app', $page->body);
        $this->assertStringContainsString('css/app.css', $page->body);
        $this->assertMatchesRegularExpression('#href="/admin" aria-current="page"#', $page->body);
        $this->assertStringNotContainsString(' style="', $page->body, 'inline styles are blocked by the CSP');
    }
}
