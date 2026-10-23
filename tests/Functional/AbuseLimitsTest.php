<?php

declare(strict_types=1);

namespace Vitaliq\Tests\Functional;

use Vitaliq\Controllers\AuthController;
use Vitaliq\Controllers\GuestTestController;
use Vitaliq\Tests\HttpTestCase;

/**
 * Regression tests for the audit of 23 Oct 2026: registration and anonymous
 * test requests previously had no rate limit, so they could be scripted to
 * create spam or to probe which emails and mobile numbers are registered.
 */
final class AbuseLimitsTest extends HttpTestCase
{
    /** @return array<string,string> */
    private function registration(int $i): array
    {
        return [
            'full_name' => 'Spam Account', 'date_of_birth' => '1990-01-01', 'sex' => 'undisclosed',
            'email' => "probe{$i}@example.com", 'mobile' => sprintf('97%08d', $i),
            'password' => 'correct horse 42', 'password_confirmation' => 'correct horse 42', 'consent' => '1',
        ];
    }

    public function testRegistrationIsRateLimitedPerConnection(): void
    {
        for ($i = 1; $i <= AuthController::REGISTER_MAX_PER_IP; $i++) {
            $this->newBrowser();
            $this->get('/register');
            $this->assertSame(303, $this->post('/register', $this->registration($i))->status, "Attempt {$i} should be allowed");
        }
        $this->newBrowser();
        $this->get('/register');
        $blocked = $this->post('/register', $this->registration(99));
        $this->assertSame(429, $blocked->status);
        $this->assertStringContainsString('Too many registration attempts', $blocked->body);
        $this->assertSame(AuthController::REGISTER_MAX_PER_IP, (int) $this->db->value('SELECT COUNT(*) FROM users'));

        $this->clock->advance('+61 minutes');
        $this->get('/register');
        $this->assertSame(303, $this->post('/register', $this->registration(100))->status, 'Limit resets after the window');
    }

    public function testFailedRegistrationsCountTowardsTheLimit(): void
    {
        $this->createPatient('taken@example.com');
        $this->get('/register');
        for ($i = 0; $i < AuthController::REGISTER_MAX_PER_IP; $i++) {
            // Probing whether an email is registered.
            $this->assertSame(422, $this->post('/register', ['email' => 'taken@example.com'] + $this->registration($i))->status);
        }
        $this->assertSame(429, $this->post('/register', $this->registration(50))->status);
    }

    public function testGuestRequestsAreRateLimitedButReplaysStillWork(): void
    {
        $first = $this->get('/test/new');
        $firstToken = $this->hiddenField($first, 'form_token');
        $created = $this->post('/test/new', $this->guestInput() + ['form_token' => $firstToken]);
        $this->assertSame(303, $created->status);

        for ($i = 1; $i < GuestTestController::SUBMIT_MAX_PER_IP; $i++) {
            $form = $this->get('/test/new');
            // Invalid submissions still count.
            $this->assertSame(422, $this->post('/test/new', ['form_token' => $this->hiddenField($form, 'form_token')])->status);
        }
        $form = $this->get('/test/new');
        $blocked = $this->post('/test/new', $this->guestInput(['full_name' => 'Another Person', 'mobile' => '9123456789']) + ['form_token' => $this->hiddenField($form, 'form_token')]);
        $this->assertSame(429, $blocked->status);
        $this->assertSame(1, (int) $this->db->value('SELECT COUNT(*) FROM test_requests'));

        // A double-click replay of an already-accepted form is not blocked.
        $replay = $this->post('/test/new', $this->guestInput() + ['form_token' => $firstToken]);
        $this->assertSame($created->header('Location'), $replay->header('Location'));
    }
}
