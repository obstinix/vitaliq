<?php

declare(strict_types=1);

namespace Vitaliq\Controllers;

use Vitaliq\Core\Request;
use Vitaliq\Core\Response;
use Vitaliq\Services\PatientService;
use Vitaliq\Services\TestingService;
use Vitaliq\Services\ValidationFailed;
use Vitaliq\Validation\Rules;

/**
 * New patients request a test without an account, then track it with
 * reference + mobile number + date of birth.
 */
final class GuestTestController extends Controller
{
    public const TRACK_MAX_PER_IP = 10;
    public const TRACK_WINDOW = 900;
    public const SUBMIT_MAX_PER_IP = 10;

    public function form(Request $request): Response
    {
        if ($this->app->auth()->is('patient')) {
            return $this->redirect('/patient/requests/new');
        }
        return $this->renderForm();
    }

    /**
     * @param array<string,string> $errors
     * @param array<string,mixed> $old
     */
    private function renderForm(array $errors = [], array $old = [], int $status = 200): Response
    {
        return $this->view('public/test-new', [
            'title' => 'Request a COVID-19 test',
            'states' => $this->app->service(PatientService::class)->states(),
            'formToken' => $this->formToken('guest-test'),
            'errors' => $errors,
            'old' => $old,
            'today' => $this->app->clock->now()->format('Y-m-d'),
            'maxDate' => $this->app->clock->now()->modify('+14 days')->format('Y-m-d'),
        ], $status);
    }

    public function submit(Request $request): Response
    {
        $testing = $this->app->service(TestingService::class);
        $token = $request->input('form_token');

        // A repeated submission of the same form (double click, refresh after
        // a slow response) returns the request that was already created.
        if ($token !== '' && ($existing = $testing->findByIdempotencyKey($token)) !== null
            && in_array($existing['reference'], (array) $this->app->session()->get('guest_refs', []), true)) {
            return $this->redirect('/test/confirmation/' . $existing['reference']);
        }
        // Anonymous submissions are limited per connection to stop scripted
        // request spam (a resubmission of the same form is handled above).
        if (!$this->app->rateLimiter()->hit('guest-test-ip:' . $this->app->ipHash(), self::SUBMIT_MAX_PER_IP, 3600)) {
            $this->flash('error', 'Too many requests have been submitted from this connection. Please try again in an hour, or sign in.');
            return $this->renderForm([], $request->body, 429);
        }
        if (!$this->consumeFormToken('guest-test', $token)) {
            $this->flash('error', 'This form expired or was already submitted. Please check the details and submit again.');
            return $this->renderForm([], $request->body, 409);
        }
        try {
            $result = $testing->submitGuest($request->body, $token);
        } catch (ValidationFailed $e) {
            return $this->renderForm($e->errors, $request->body, 422);
        }
        $refs = (array) $this->app->session()->get('guest_refs', []);
        $refs[] = $result['reference'];
        $this->app->session()->set('guest_refs', array_slice($refs, -10));
        return $this->redirect('/test/confirmation/' . $result['reference']);
    }

    public function confirmation(Request $request, string $ref): Response
    {
        $ref = Rules::normalise('reference', $ref);
        // Only the browser session that submitted the request can view this page.
        if (!in_array($ref, (array) $this->app->session()->get('guest_refs', []), true)) {
            $this->notFound();
        }
        $id = (int) $this->app->db->value('SELECT id FROM test_requests WHERE reference = :r', ['r' => $ref]);
        $detail = $this->app->service(TestingService::class)->detail($id);
        if ($detail === null) {
            $this->notFound();
        }
        return $this->view('public/test-confirmation', ['title' => 'Request received', 'request' => $detail]);
    }

    public function trackForm(Request $request): Response
    {
        return $this->view('public/track', ['title' => 'Track a test', 'old' => ['reference' => $request->query('reference')]]);
    }

    public function track(Request $request): Response
    {
        $errors = [];
        $reference = Rules::normalise('reference', $request->input('reference'));
        if (Rules::check('reference', $reference) !== null) {
            $errors['reference'] = 'Enter the reference exactly as shown on your confirmation, for example VQ-261010-7K3MP.';
        }
        $mobileRaw = Rules::normalise('indian_mobile', $request->input('mobile'));
        if (Rules::check('indian_mobile', $mobileRaw) !== null) {
            $errors['mobile'] = 'Enter the mobile number used for the request.';
        }
        $dob = trim($request->input('date_of_birth'));
        if (Rules::check('iso_date', $dob) !== null) {
            $errors['date_of_birth'] = 'Enter the patient’s date of birth.';
        }
        if ($errors !== []) {
            return $this->view('public/track', ['title' => 'Track a test', 'errors' => $errors, 'old' => $request->body], 422);
        }
        if (!$this->app->rateLimiter()->hit('track:' . $this->app->ipHash(), self::TRACK_MAX_PER_IP, self::TRACK_WINDOW)) {
            return $this->view('public/track', [
                'title' => 'Track a test',
                'errors' => ['form' => 'Too many lookups from this connection. Please wait 15 minutes and try again.'],
                'old' => $request->body,
            ], 429);
        }
        $id = $this->app->service(TestingService::class)->trackGuest($reference, Rules::canonical('indian_mobile', $mobileRaw), $dob);
        if ($id === null) {
            return $this->view('public/track', [
                'title' => 'Track a test',
                'errors' => ['form' => 'We could not find a request matching all three details. Check them and try again.'],
                'old' => $request->body,
            ], 404);
        }
        $tracked = (array) $this->app->session()->get('tracked_refs', []);
        $tracked[] = $reference;
        $this->app->session()->set('tracked_refs', array_slice(array_unique($tracked), -10));
        return $this->redirect('/track/' . $reference);
    }

    public function trackResult(Request $request, string $ref): Response
    {
        $ref = Rules::normalise('reference', $ref);
        $allowed = array_merge((array) $this->app->session()->get('tracked_refs', []), (array) $this->app->session()->get('guest_refs', []));
        if (!in_array($ref, $allowed, true)) {
            return $this->redirect('/track?reference=' . rawurlencode($ref));
        }
        $id = (int) $this->app->db->value('SELECT id FROM test_requests WHERE reference = :r', ['r' => $ref]);
        $detail = $this->app->service(TestingService::class)->detail($id);
        if ($detail === null) {
            $this->notFound();
        }
        return $this->view('public/track-result', ['title' => 'Request ' . $ref, 'request' => $detail]);
    }
}
