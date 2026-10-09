<?php

declare(strict_types=1);

namespace Vitaliq\Tests\Integration;

use Vitaliq\Services\NotificationService;
use Vitaliq\Services\ReportService;
use Vitaliq\Services\StatsService;
use Vitaliq\Services\TestingService;
use Vitaliq\Tests\DatabaseTestCase;

final class ReportsAndNotificationsTest extends DatabaseTestCase
{
    /** Three guest requests on 1, 5 and 9 October. */
    private function seedRequests(): void
    {
        $testing = $this->app->service(TestingService::class);
        foreach ([
            ['2026-10-01 09:00:00', 'Asha Varghese', '9811100001'],
            ['2026-10-05 12:00:00', 'Rohan Gupta', '9811100002'],
            ['2026-10-09 23:30:00', "Sean O'Brien", '9922200003'],
        ] as [$at, $name, $mobile]) {
            $this->clock->freeze(new \DateTimeImmutable($at));
            $testing->submitGuest($this->guestInput([
                'full_name' => $name, 'mobile' => $mobile,
                'preferred_date' => substr($at, 0, 10), 'symptom_onset' => substr($at, 0, 10),
            ]), bin2hex(random_bytes(8)));
        }
        $this->clock->freeze(new \DateTimeImmutable(self::NOW));
    }

    private function search(array $query): array
    {
        $reports = $this->app->service(ReportService::class);
        return $reports->search($reports->filters($query));
    }

    public function testFiltersByNameMobileAndDateRange(): void
    {
        $this->seedRequests();
        $this->assertSame(1, $this->search(['name' => 'rohan'])['total']);
        $this->assertSame(2, $this->search(['mobile' => '98111'])['total'], 'Partial mobile match');
        $this->assertSame(1, $this->search(['mobile' => '+91 99222 00003'])['total'], 'Formatted mobile is normalised');
        $this->assertSame(2, $this->search(['from' => '2026-10-02', 'to' => '2026-10-09'])['total'], 'End date is inclusive of the whole day');
        $this->assertSame(1, $this->search(['from' => '2026-10-01', 'to' => '2026-10-01'])['total']);
        $this->assertSame(3, $this->search(['status' => 'submitted'])['total']);
        $this->assertSame(0, $this->search(['status' => 'assigned'])['total']);
    }

    public function testReferencePrefixSearch(): void
    {
        $this->seedRequests();
        $this->assertSame(1, $this->search(['reference' => 'VQ-261005'])['total']);
        $this->assertSame(3, $this->search(['reference' => 'vq-2610'])['total']);
    }

    public function testInjectionAttemptsAreInertAndWildcardsAreLiteral(): void
    {
        $this->seedRequests();
        $this->assertSame(0, $this->search(['name' => "' OR 1=1 --"])['total']);
        $this->assertSame(0, $this->search(['name' => '%'])['total'], '% must match literally, not everything');
        $this->assertSame(0, $this->search(['name' => '_'])['total']);
        $this->assertSame(1, $this->search(['name' => "O'Brien"])['total'], 'Apostrophes are handled as data');
        $filters = $this->app->service(ReportService::class)->filters(['reference' => "VQ' UNION SELECT", 'sort' => 'id; DROP TABLE users', 'status' => 'x', 'from' => '2026-13-45']);
        $this->assertSame('', $filters['reference']);
        $this->assertSame('newest', $filters['sort'], 'Unknown sort keys fall back to the whitelist');
        $this->assertSame('', $filters['status']);
        $this->assertArrayHasKey('from', $filters['errors']);
        $this->assertNotNull($this->db->value('SELECT COUNT(*) FROM users'), 'users table still exists');
    }

    public function testPhlebotomistFilterAndPaginationBounds(): void
    {
        $this->seedRequests();
        [$phleb] = $this->createPhlebotomist();
        $first = (int) $this->db->value('SELECT MIN(id) FROM test_requests');
        $this->app->service(TestingService::class)->assign($first, $phleb, $this->adminActor());
        $this->assertSame(1, $this->search(['phlebotomist' => (string) $phleb])['total']);

        $reports = $this->app->service(ReportService::class);
        $page = $reports->search($reports->filters([]), 99, 2);
        $this->assertSame(5, $page['per_page'], 'Page size is clamped to the minimum of 5');
        $this->assertSame(1, $page['pages']);
        $this->assertSame(1, $page['page'], 'Page number is clamped to the last page');
        $this->assertCount(3, $page['rows']);
        $this->assertSame(ReportService::MAX_PER_PAGE, $reports->search($reports->filters([]), 1, 5000)['per_page']);
    }

    public function testCsvExportHasHeaderAndNeutralisedCells(): void
    {
        $this->seedRequests();
        $this->db->execute("UPDATE patients SET full_name = '=HYPERLINK(\"http://evil.example\")' WHERE mobile = '9811100001'");
        $reports = $this->app->service(ReportService::class);
        $csv = $reports->csv($reports->filters([]));
        $lines = preg_split('/\r?\n/', trim(substr($csv, 3)));
        $this->assertStringStartsWith('Reference,Submitted,Patient,Mobile', $lines[0]);
        $this->assertCount(4, $lines);
        $this->assertStringContainsString("\"'=HYPERLINK", $csv);
        $this->assertSame(1, (int) $this->db->value("SELECT COUNT(*) FROM audit_events WHERE action = 'report.exported'"));
    }

    public function testNotificationsReadStateAndOwnership(): void
    {
        $a = $this->createAdmin('a@vitaliq.test');
        $b = $this->createAdmin('b@vitaliq.test');
        $notes = $this->app->service(NotificationService::class);
        $this->assertSame(2, $notes->notifyRole('admin', 'x', 'Hello', 'Body', '/admin'));
        $this->assertSame(1, $notes->unreadCount($a));
        $noteA = (int) $this->db->value('SELECT id FROM notifications WHERE user_id = :u', ['u' => $a]);

        $this->assertFalse($notes->markRead($b, $noteA), 'Cannot mark another user\'s notification');
        $this->assertSame(1, $notes->unreadCount($a));
        $this->assertTrue($notes->markRead($a, $noteA));
        $this->assertSame(0, $notes->unreadCount($a));
        $this->assertSame(self::NOW, $this->db->value('SELECT read_at FROM notifications WHERE id = :id', ['id' => $noteA]));
        $this->assertSame(1, $notes->markAllRead($b));
        $this->assertSame(0, $notes->unreadCount($b));
    }

    public function testDashboardMetricsComeFromTheDatabase(): void
    {
        $stats = $this->app->service(StatsService::class);
        $empty = $stats->dashboard();
        $this->assertSame(0, $empty['total_requests']);
        $this->assertNull($empty['avg_turnaround_hours']);
        $this->assertCount(30, $stats->dailySeries(30));
        $this->assertSame(0, array_sum(array_column($stats->dailySeries(30), 'submitted')));

        $this->seedRequests();
        $now = $stats->dashboard();
        $this->assertSame(3, $now['total_requests']);
        $this->assertSame(3, $now['counts']['submitted']);
        $this->assertSame(1, $now['new_24h'], 'Only the 9 October 23:30 request is within 24 hours of 10 October 10:00');
        $this->assertSame(3, $now['patients']);
        $series = $stats->dailySeries(30);
        $this->assertSame('2026-10-10', end($series)['date']);
        $this->assertSame(3, array_sum(array_column($series, 'submitted')));
        $this->assertSame([['code' => 'KA', 'name' => 'Karnataka', 'requests' => 3, 'released' => 0, 'positive' => 0]], $stats->stateSummary());
    }
}
