<?php

declare(strict_types=1);

namespace Vitaliq\Tests\Functional;

use Vitaliq\Auth\PasswordTokens;
use Vitaliq\Services\TestingService;
use Vitaliq\Tests\HttpTestCase;

final class UserJourneysTest extends HttpTestCase
{
    public function testNewPatientRequestsTracksAndCannotDoubleSubmit(): void
    {
        $this->createAdmin();
        $form = $this->get('/test/new');
        $this->assertSame(200, $form->status);
        $formToken = $this->hiddenField($form, 'form_token');

        $input = $this->guestInput() + ['form_token' => $formToken];
        $first = $this->post('/test/new', $input);
        $this->assertSame(303, $first->status);
        $location = (string) $first->header('Location');
        $this->assertMatchesRegularExpression('#/test/confirmation/VQ-261010-[0-9A-Z]{5}$#', $location);
        $reference = basename($location);

        $confirmation = $this->get(parse_url($location, PHP_URL_PATH));
        $this->assertSame(200, $confirmation->status);
        $this->assertStringContainsString($reference, $confirmation->body);

        // Double click / refresh: the same form token is submitted again.
        $again = $this->post('/test/new', $input);
        $this->assertSame($location, $again->header('Location'));
        $this->assertSame(1, (int) $this->db->value('SELECT COUNT(*) FROM test_requests'));

        // Another browser cannot open the confirmation page...
        $this->newBrowser();
        $this->assertSame(404, $this->get('/test/confirmation/' . $reference)->status);
        // ...and needs all three details to track it.
        $this->assertRedirectsTo('/track', $this->get('/track/' . $reference));
        $this->get('/track');
        $wrong = $this->post('/track', ['reference' => strtolower($reference), 'mobile' => '9876543210', 'date_of_birth' => '1990-01-01']);
        $this->assertSame(404, $wrong->status);
        $right = $this->post('/track', ['reference' => strtolower($reference), 'mobile' => '+91 98765 43210', 'date_of_birth' => '1988-03-14']);
        $this->assertRedirectsTo('/track/' . $reference, $right);
        $status = $this->get('/track/' . $reference);
        $this->assertSame(200, $status->status);
        $this->assertStringContainsString('Submitted', $status->body);
    }

    public function testTrackingIsRateLimited(): void
    {
        $this->get('/track');
        for ($i = 0; $i < 10; $i++) {
            $this->post('/track', ['reference' => 'VQ-261010-AAAAA', 'mobile' => '9876543210', 'date_of_birth' => '1990-01-01']);
        }
        $this->assertSame(429, $this->post('/track', ['reference' => 'VQ-261010-AAAAA', 'mobile' => '9876543210', 'date_of_birth' => '1990-01-01'])->status);
    }

    public function testInvalidGuestSubmissionReDisplaysErrorsWithoutSaving(): void
    {
        $form = $this->get('/test/new');
        $response = $this->post('/test/new', ['form_token' => $this->hiddenField($form, 'form_token'), 'full_name' => '<script>', 'mobile' => 'abc']);
        $this->assertSame(422, $response->status);
        $this->assertStringContainsString('Please check', $response->body);
        $this->assertStringContainsString('aria-invalid="true"', $response->body);
        $this->assertStringNotContainsString('value="<script>"', $response->body);
        $this->assertSame(0, (int) $this->db->value('SELECT COUNT(*) FROM test_requests'));
    }

    public function testRegistrationSignsInAndExistingPatientRequestsWithSavedDetails(): void
    {
        $this->get('/register');
        $response = $this->post('/register', [
            'full_name' => 'Kavya Menon', 'date_of_birth' => '1994-07-21', 'sex' => 'female', 'email' => 'kavya@example.com',
            'mobile' => '9845012345', 'password' => 'correct horse 42', 'password_confirmation' => 'correct horse 42', 'consent' => '1',
        ]);
        $this->assertRedirectsTo('/patient', $response);
        $dashboard = $this->get('/patient');
        $this->assertSame(200, $dashboard->status);
        $this->assertStringContainsString('Kavya Menon', $dashboard->body);

        $form = $this->get('/patient/requests/new');
        $submit = $this->post('/patient/requests/new', $this->testInput() + ['form_token' => $this->hiddenField($form, 'form_token')]);
        $this->assertSame(303, $submit->status);
        $id = (int) basename((string) $submit->header('Location'));
        $show = $this->get('/patient/requests/' . $id);
        $this->assertSame(200, $show->status);
        $this->assertStringContainsString('Kavya Menon', $show->body);
        $this->assertStringContainsString('Cancel this request', $show->body);
        $this->assertSame(0, (int) $this->db->value('SELECT COUNT(*) FROM patients WHERE user_id IS NULL'), 'No duplicate patient record');
    }

    public function testResultIsVisibleToPatientOnlyAfterRelease(): void
    {
        $patient = $this->createPatient();
        $admin = $this->adminActor();
        [$phleb] = $this->createPhlebotomist();
        $testing = $this->app->service(TestingService::class);
        $id = $testing->submitForUser($patient, $this->testInput(), 'rel')['id'];
        $testing->assign($id, $phleb, $admin);
        $testing->recordCollection($id, ['specimen' => 'nasal', 'barcode' => 'RELEASE01'], $admin);
        $testing->startProcessing($id, $admin);
        $testing->recordResult($id, ['outcome' => 'positive', 'ct_value' => '24.0', 'remarks' => ''], $admin);

        $this->signIn('patient@vitaliq.test');
        $before = $this->get('/patient/requests/' . $id);
        $this->assertStringNotContainsString('Detected (positive)', $before->body, 'Unreleased result must not leak');
        $this->assertSame(404, $this->get('/patient/requests/' . $id . '/report')->status);

        $testing->releaseResult($id, $admin);
        $after = $this->get('/patient/requests/' . $id);
        $this->assertStringContainsString('Detected (positive)', $after->body);
        $report = $this->get('/patient/requests/' . $id . '/report');
        $this->assertSame(200, $report->status);
        $this->assertStringContainsString('Demonstration document', $report->body);
    }

    public function testAdminWorkflowThroughHttp(): void
    {
        $this->createAdmin();
        [$phleb] = $this->createPhlebotomist();
        $id = $this->app->service(TestingService::class)->submitGuest($this->guestInput(), 'http')['id'];
        $this->signIn('admin@vitaliq.test');

        $this->assertStringContainsString('Assign a phlebotomist', $this->get('/admin/requests/' . $id)->body);
        $this->assertRedirectsTo('/admin/requests/' . $id, $this->post('/admin/requests/' . $id . '/assign', ['phlebotomist_id' => (string) $phleb]));
        $bad = $this->post('/admin/requests/' . $id . '/collect', ['specimen' => 'nasal', 'barcode' => 'no']);
        $this->assertSame(422, $bad->status, 'Server-side validation applies even without JavaScript');
        $this->post('/admin/requests/' . $id . '/collect', ['specimen' => 'nasal', 'barcode' => 'HTTPFLOW1']);
        $skip = $this->post('/admin/requests/' . $id . '/release');
        $this->assertRedirectsTo('/admin/requests/' . $id, $skip);
        $this->assertStringContainsString('cannot move', $this->get('/admin/requests/' . $id)->body, 'Skipping steps is refused with a message');
        $this->assertSame('sample_collected', $this->db->value('SELECT status FROM test_requests WHERE id = :id', ['id' => $id]));
    }

    public function testNotificationPollingAndMarkRead(): void
    {
        $adminId = $this->createAdmin();
        $this->app->service(TestingService::class)->submitGuest($this->guestInput(), 'n');
        $this->signIn('admin@vitaliq.test');
        $poll = json_decode($this->get('/api/notifications')->body, true);
        $this->assertSame(1, $poll['unread']);
        $this->assertStringContainsString('New testing request', $poll['items'][0]['title']);

        $noteId = $poll['items'][0]['id'];
        $read = $this->post('/notifications/' . $noteId . '/read', [], true, ['accept' => 'application/json']);
        $this->assertSame(['ok' => true, 'unread' => 0], json_decode($read->body, true));
        $this->assertSame(0, (int) $this->db->value('SELECT COUNT(*) FROM notifications WHERE user_id = :u AND read_at IS NULL', ['u' => $adminId]));
    }

    public function testForgotPasswordGivesTheSameAnswerForUnknownEmails(): void
    {
        $this->createPatient();
        $this->get('/forgot-password');
        $known = $this->post('/forgot-password', ['email' => 'patient@vitaliq.test']);
        $knownPage = $this->get('/forgot-password')->body;
        $this->newBrowser();
        $this->get('/forgot-password');
        $unknown = $this->post('/forgot-password', ['email' => 'nobody@vitaliq.test']);
        $unknownPage = $this->get('/forgot-password')->body;

        $this->assertSame($known->status, $unknown->status);
        $flash = static fn (string $html): string => preg_match('#<div class="flash flash--success".*?<p>(.*?)</p>#s', $html, $m) ? $m[1] : '';
        $this->assertNotSame('', $flash($knownPage));
        $this->assertSame($flash($knownPage), $flash($unknownPage));
        $this->assertSame(1, (int) $this->db->value('SELECT COUNT(*) FROM password_tokens'), 'Token only for the real account');
    }

    public function testResetLinkWorksOnceThroughHttp(): void
    {
        $id = $this->createPatient();
        $token = $this->app->service(PasswordTokens::class)->issue($id, 'reset');
        $this->assertSame(200, $this->get('/reset-password/' . $token)->status);
        $this->assertSame('no-referrer', $this->get('/reset-password/' . $token)->header('Referrer-Policy'));
        $this->assertRedirectsTo('/login', $this->post('/reset-password/' . $token, ['password' => 'fresh start 2026', 'password_confirmation' => 'fresh start 2026']));
        $this->assertSame(410, $this->get('/reset-password/' . $token)->status);
        $this->signIn('patient@vitaliq.test', 'fresh start 2026');
    }

    public function testQaLabEvaluatesRulesAndRunsTheSuite(): void
    {
        $this->createUser('qa', 'qa@vitaliq.test');
        $this->app->service(\Vitaliq\Services\QaService::class)->syncCatalogue();
        $this->signIn('qa@vitaliq.test');
        $json = $this->post('/api/qa/evaluate', ['rule' => 'indian_mobile', 'value' => '+91 98765 43210'], true, ['accept' => 'application/json']);
        $data = json_decode($json->body, true);
        $this->assertTrue($data['accepted']);
        $this->assertSame('+91 98765 43210', $data['normalised']);
        $this->assertSame(422, $this->post('/api/qa/evaluate', ['rule' => 'no_such_rule', 'value' => 'x'], true, ['accept' => 'application/json'])->status);
        $run = $this->post('/qa/runs');
        $this->assertSame(303, $run->status);
        $runPage = $this->get(parse_url((string) $run->header('Location'), PHP_URL_PATH));
        $this->assertStringContainsString('Regex suite', $runPage->body);
        $this->assertSame(1, (int) $this->db->value('SELECT COUNT(*) FROM qa_runs'));
    }
}
