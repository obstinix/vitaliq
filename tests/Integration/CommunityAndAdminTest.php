<?php

declare(strict_types=1);

namespace Vitaliq\Tests\Integration;

use Vitaliq\Auth\PasswordTokens;
use Vitaliq\Services\AnnouncementService;
use Vitaliq\Services\CommentService;
use Vitaliq\Services\PhlebotomistService;
use Vitaliq\Services\TestingService;
use Vitaliq\Services\UserAdminService;
use Vitaliq\Services\ValidationFailed;
use Vitaliq\Services\WorkflowException;
use Vitaliq\Tests\DatabaseTestCase;

final class CommunityAndAdminTest extends DatabaseTestCase
{
    /** @return array<string,string> */
    private function comment(array $overrides = []): array
    {
        $comments = $this->app->service(CommentService::class);
        $stamp = $comments->formStamp();
        $this->clock->advance('+10 seconds');
        return $overrides + [
            'author_name' => 'Meera Pillai', 'email' => '', 'topic' => 'testing',
            'body' => 'The status timeline was very clear and helpful.', 'website' => '', 'stamp' => $stamp,
        ];
    }

    public function testCommentIsStoredAsPendingAndNotPublicUntilApproved(): void
    {
        $this->createAdmin();
        $service = $this->app->service(CommentService::class);
        $this->assertSame('pending', $service->submit($this->comment(['body' => '<b>hi</b> there, friend <script>x()</script>']), null));
        $row = $this->db->one('SELECT * FROM comments');
        $this->assertSame('<b>hi</b> there, friend <script>x()</script>', $row['body'], 'Stored verbatim; escaping happens on output');
        $this->assertSame(0, $service->approved(1)['total']);
        $this->assertSame(1, (int) $this->db->value("SELECT COUNT(*) FROM notifications WHERE type = 'comment.pending'"));

        $this->actingAs($this->createAdmin('mod@vitaliq.test'));
        $service->moderate((int) $row['id'], 'approve', 'Looks fine');
        $this->assertSame(1, $service->approved(1)['total']);
        $this->assertNotNull($this->db->value('SELECT moderated_by_user_id FROM comments'));
    }

    public function testHoneypotSubmissionIsDiscardedSilently(): void
    {
        $result = $this->app->service(CommentService::class)->submit($this->comment(['website' => 'http://spam.example']), null);
        $this->assertSame('discarded', $result);
        $this->assertSame(0, (int) $this->db->value('SELECT COUNT(*) FROM comments'));
    }

    public function testTooFastForgedAndStaleStampsAreRefused(): void
    {
        $service = $this->app->service(CommentService::class);
        $fast = $this->comment();
        $fast['stamp'] = $service->formStamp(); // issued "now", so 0 seconds old
        try {
            $service->submit($fast, null);
            $this->fail('Too-fast comment accepted');
        } catch (ValidationFailed $e) {
            $this->assertStringContainsString('quick', $e->errors['body']);
        }
        // (Each comment() call advances the clock, so build these after the fast case.)
        foreach ([$this->comment(['stamp' => '1791500000.' . str_repeat('a', 64)]), $this->comment(['stamp' => 'garbage'])] as $input) {
            try {
                $service->submit($input, null);
                $this->fail('Comment accepted');
            } catch (ValidationFailed $e) {
                $this->assertArrayHasKey('body', $e->errors);
            }
        }
        $stale = $this->comment();
        $this->clock->advance('+3 hours');
        $this->expectException(ValidationFailed::class);
        $service->submit($stale, null);
    }

    public function testCommentRateLimitAndLinkLimit(): void
    {
        $service = $this->app->service(CommentService::class);
        for ($i = 0; $i < CommentService::MAX_PER_WINDOW; $i++) {
            $service->submit($this->comment(), null);
        }
        try {
            $service->submit($this->comment(), null);
            $this->fail('Rate limit not applied');
        } catch (ValidationFailed $e) {
            $this->assertStringContainsString('several comments', $e->errors['body']);
        }
        $this->clock->advance('+11 minutes');
        try {
            $service->submit($this->comment(['body' => 'see http://a.example http://b.example http://c.example now']), null);
            $this->fail('Link limit not applied');
        } catch (ValidationFailed $e) {
            $this->assertStringContainsString('two links', $e->errors['body']);
        }
    }

    public function testPhlebotomistLifecycle(): void
    {
        $service = $this->app->service(PhlebotomistService::class);
        $a = $service->create(['full_name' => 'Deepak Yadav', 'mobile' => '9800000001', 'base_city' => 'Delhi', 'state_code' => 'DL']);
        $b = $service->create(['full_name' => 'Sunita Pawar', 'mobile' => '9800000002', 'base_city' => 'Mumbai', 'state_code' => 'MH']);
        $this->assertSame(['PHL-0001', 'PHL-0002'], array_column($this->db->all('SELECT employee_code FROM phlebotomists ORDER BY id'), 'employee_code'));

        try {
            $service->create(['full_name' => 'Copy Cat', 'mobile' => '9800000001', 'base_city' => 'Delhi', 'state_code' => 'DL']);
            $this->fail('Duplicate mobile accepted');
        } catch (ValidationFailed $e) {
            $this->assertArrayHasKey('mobile', $e->errors);
        }

        // Unused record: hard delete.
        $this->assertSame('deleted', $service->delete($b));
        $this->assertNull($this->db->value('SELECT id FROM phlebotomists WHERE id = :id', ['id' => $b]));

        // Record with an open assignment: cannot deactivate or delete.
        $id = $this->app->service(TestingService::class)->submitGuest($this->guestInput(), 'p1')['id'];
        $this->app->service(TestingService::class)->assign($id, $a, $this->adminActor());
        foreach ([fn () => $service->setActive($a, false), fn () => $service->delete($a)] as $attempt) {
            try {
                $attempt();
                $this->fail('Allowed with open collections');
            } catch (WorkflowException) {
                $this->assertSame(1, (int) $this->db->value('SELECT is_active FROM phlebotomists WHERE id = :id', ['id' => $a]));
            }
        }

        // Once the request is cancelled, the record is archived (it has history), not deleted.
        $this->app->service(TestingService::class)->cancel($id, $this->adminActor($this->createAdmin('x@vitaliq.test')), 'test');
        $this->assertSame('archived', $service->delete($a));
        $row = $this->db->one('SELECT is_active, deleted_at FROM phlebotomists WHERE id = :id', ['id' => $a]);
        $this->assertSame(0, (int) $row['is_active']);
        $this->assertNotNull($row['deleted_at']);
        $this->assertSame(1, (int) $this->db->value('SELECT COUNT(*) FROM test_assignments WHERE phlebotomist_id = :id', ['id' => $a]), 'History kept');
    }

    public function testUserAdministrationGuards(): void
    {
        $me = $this->createAdmin('me@vitaliq.test');
        $this->actingAs($me);
        $users = $this->app->service(UserAdminService::class);
        try {
            $users->setActive($me, false);
            $this->fail('Deactivated self');
        } catch (WorkflowException) {
            $this->assertTrue(true);
        }

        $patient = $this->createPatient();
        $users->setActive($patient, false);
        $this->assertSame(0, (int) $this->db->value('SELECT is_active FROM users WHERE id = :id', ['id' => $patient]));
        $this->assertSame(2, (int) $this->db->value('SELECT session_version FROM users WHERE id = :id', ['id' => $patient]), 'Sessions revoked');

        $list = $users->list('', '', 1);
        foreach ($list['rows'] as $row) {
            $this->assertArrayNotHasKey('password_hash', $row, 'Listings never expose hashes');
        }
    }

    public function testStaffInvitationCreatesAccountWithoutPassword(): void
    {
        $this->actingAs($this->createAdmin());
        [$phleb] = $this->createPhlebotomist();
        $result = $this->app->service(UserAdminService::class)->inviteStaff([
            'role' => 'phlebotomist', 'full_name' => 'Ramesh Kumar', 'email' => 'ramesh@vitaliq.test', 'phlebotomist_id' => (string) $phleb,
        ]);
        $this->assertNull($this->db->value('SELECT password_hash FROM users WHERE id = :id', ['id' => $result['id']]));
        $this->assertSame($result['id'], (int) $this->db->value('SELECT user_id FROM phlebotomists WHERE id = :id', ['id' => $phleb]));
        $tokens = $this->app->service(PasswordTokens::class);
        $this->assertSame('invite', $tokens->find($result['token'])['purpose']);
        $this->clock->advance('+47 hours');
        $this->assertTrue($tokens->consume($result['token'], 'collection day 2026'));
        $this->assertTrue($this->app->auth()->attempt('ramesh@vitaliq.test', 'collection day 2026')['ok']);
    }

    public function testPublicHealthAnnouncementsMustCiteASource(): void
    {
        $this->actingAs($this->createAdmin());
        $service = $this->app->service(AnnouncementService::class);
        try {
            $service->create(['category' => 'public_health', 'title' => 'New variant spreading', 'summary' => 'Unsourced public-health claim.', 'published_on' => '2026-10-10']);
            $this->fail('Unsourced public-health announcement accepted');
        } catch (ValidationFailed $e) {
            $this->assertArrayHasKey('source_url', $e->errors);
        }
        try {
            $service->create(['category' => 'public_health', 'title' => 'Item', 'summary' => 'Has a javascript link.', 'source_name' => 'X', 'source_url' => 'javascript:alert(1)', 'published_on' => '2026-10-10']);
            $this->fail('javascript: URL accepted');
        } catch (ValidationFailed $e) {
            $this->assertArrayHasKey('source_url', $e->errors);
        }
        $id = $service->create(['category' => 'service', 'title' => 'Evening slots', 'summary' => 'Evening collection is available.', 'published_on' => '2026-10-10']);
        $this->assertSame(1, count($service->published()));
        $service->togglePublished($id);
        $this->assertSame([], $service->published());
    }
}
