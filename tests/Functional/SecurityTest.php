<?php

declare(strict_types=1);

namespace Vitaliq\Tests\Functional;

use Vitaliq\Services\TestingService;
use Vitaliq\Tests\HttpTestCase;

final class SecurityTest extends HttpTestCase
{
    public function testPostWithoutOrWithWrongCsrfTokenIs419(): void
    {
        $this->createPatient();
        $this->get('/login');
        $this->assertSame(419, $this->post('/login', ['email' => 'patient@vitaliq.test', 'password' => 'correct horse 42'], false)->status);
        $this->assertSame(419, $this->post('/login', ['email' => 'patient@vitaliq.test', 'password' => 'correct horse 42', '_token' => str_repeat('a', 64)])->status);
        $this->assertNull($this->jar['user_id'] ?? null, 'Not signed in');
    }

    public function testCsrfTokenFromAnotherSessionIsRejected(): void
    {
        $this->get('/login');
        $stolen = $this->jar['_csrf'];
        $this->newBrowser();
        $this->get('/login');
        $this->assertSame(419, $this->post('/register', ['_token' => $stolen])->status);
    }

    public function testGuestsAreSentToSignIn(): void
    {
        foreach (['/admin', '/patient', '/staff', '/qa', '/account', '/notifications'] as $path) {
            $this->assertRedirectsTo('/login', $this->get($path));
        }
        $api = $this->get('/api/notifications');
        $this->assertSame(401, $api->status);
        $this->assertStringStartsWith('application/json', (string) $api->header('Content-Type'));
    }

    public function testRolesAreEnforcedOnTheServer(): void
    {
        $this->createPatient();
        $this->signIn('patient@vitaliq.test');
        foreach (['/admin', '/admin/reports', '/admin/reports/export', '/qa', '/staff', '/api/admin/series'] as $path) {
            $this->assertSame(403, $this->get($path)->status, $path);
        }
        $this->assertSame(403, $this->post('/admin/requests/1/release')->status, 'Hidden buttons are not the protection');

        $this->newBrowser();
        $this->createUser('qa', 'qa@vitaliq.test');
        $this->signIn('qa@vitaliq.test');
        $this->assertSame(200, $this->get('/qa')->status);
        $this->assertSame(403, $this->get('/admin')->status);
    }

    public function testPatientCannotSeeOrCancelAnotherPatientsRequest(): void
    {
        $owner = $this->createPatient('owner@example.com');
        $this->createPatient('intruder@example.com');
        $id = $this->app->service(TestingService::class)->submitForUser($owner, $this->testInput(), 'own')['id'];

        $this->signIn('intruder@example.com');
        $this->assertSame(404, $this->get('/patient/requests/' . $id)->status);
        $this->assertSame(404, $this->get('/patient/requests/' . $id . '/report')->status);
        $this->assertSame(404, $this->post('/patient/requests/' . $id . '/cancel', ['reason' => 'mine now'])->status);
        $this->assertSame('submitted', $this->db->value('SELECT status FROM test_requests WHERE id = :id', ['id' => $id]));
    }

    public function testPhlebotomistCannotActOnAnotherPhlebotomistsCollection(): void
    {
        [$x] = $this->createPhlebotomist('Phleb X', 'x@vitaliq.test');
        $this->createPhlebotomist('Phleb Y', 'y@vitaliq.test');
        $id = $this->app->service(TestingService::class)->submitGuest($this->guestInput(), 'pp')['id'];
        $this->app->service(TestingService::class)->assign($id, $x, $this->adminActor());

        $this->signIn('y@vitaliq.test');
        $this->assertSame(404, $this->get('/staff/tasks/' . $id)->status);
        $this->assertSame(404, $this->post('/staff/tasks/' . $id . '/collect', ['specimen' => 'nasal', 'barcode' => 'ABCDEFGH'])->status);
        $this->assertSame('assigned', $this->db->value('SELECT status FROM test_requests WHERE id = :id', ['id' => $id]));
        $this->assertStringNotContainsString('Arjun Rao', $this->get('/staff')->body, 'Other phlebotomists\' patients are not listed');
    }

    public function testLoginRegeneratesSessionAndLogoutClearsIt(): void
    {
        $this->createPatient();
        $this->get('/login');
        $tokenBefore = $this->jar['_csrf'];
        $this->signIn('patient@vitaliq.test');
        $this->assertNotSame($tokenBefore, $this->jar['_csrf'], 'CSRF token rotated at login');
        $this->assertSame(200, $this->get('/patient')->status);

        $this->assertRedirectsTo('/login', $this->post('/logout'));
        $this->assertArrayNotHasKey('user_id', $this->jar);
        $this->assertRedirectsTo('/login', $this->get('/patient'));
    }

    public function testOpenRedirectIsNotPossibleAfterLogin(): void
    {
        $this->createPatient();
        $this->jar['intended'] = '//evil.example/phish';
        $this->get('/login');
        $this->jar['intended'] = '//evil.example/phish';
        $response = $this->post('/login', ['email' => 'patient@vitaliq.test', 'password' => 'correct horse 42']);
        $this->assertSame('/patient', parse_url((string) $response->header('Location'), PHP_URL_PATH));
        $this->assertNull(parse_url((string) $response->header('Location'), PHP_URL_HOST) === 'evil.example' ? 'bad' : null);
    }

    public function testReportSearchReflectsInputSafely(): void
    {
        $this->createAdmin();
        $this->signIn('admin@vitaliq.test');
        $body = $this->get('/admin/reports', ['name' => '"><script>alert(1)</script>'])->body;
        $this->assertStringNotContainsString('<script>alert(1)</script>', $body);
        $this->assertStringContainsString('&quot;&gt;&lt;script&gt;', $body);
        $sqli = $this->get('/admin/reports', ['name' => "' OR 1=1 --", 'sort' => 'id DESC; DROP TABLE users']);
        $this->assertSame(200, $sqli->status);
        $this->assertStringContainsString('0 matching requests', $sqli->body);
    }

    public function testAuthenticatedPagesAreNotCached(): void
    {
        $this->createPatient();
        $this->signIn('patient@vitaliq.test');
        $this->assertStringContainsString('no-store', (string) $this->get('/patient')->header('Cache-Control'));
    }

    public function testCsvExportIsAdminOnlyAndAttachment(): void
    {
        $this->createAdmin();
        $this->signIn('admin@vitaliq.test');
        $response = $this->get('/admin/reports/export');
        $this->assertSame(200, $response->status);
        $this->assertStringStartsWith('text/csv', (string) $response->header('Content-Type'));
        $this->assertStringContainsString('attachment;', (string) $response->header('Content-Disposition'));
    }
}
