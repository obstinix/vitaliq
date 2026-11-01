<?php

declare(strict_types=1);

namespace Vitaliq\Controllers;

use Vitaliq\Core\Request;
use Vitaliq\Core\Response;
use Vitaliq\Services\AppointmentService;
use Vitaliq\Services\LaboratoryService;
use Vitaliq\Services\NotificationService;
use Vitaliq\Services\PatientPlatformService;
use Vitaliq\Services\PatientService;
use Vitaliq\Services\TestingService;
use Vitaliq\Services\ValidationFailed;
use Vitaliq\Services\WorkflowException;
use Vitaliq\Validation\Rules;

/**
 * Signed-in patient area. Every query is scoped by the session user id;
 * request ids from the URL are only ever used together with it.
 */
final class PatientController extends Controller
{
    public function dashboard(Request $request): Response
    {
        $userId = $this->userId();
        $testing = $this->app->service(TestingService::class);
        $platform = $this->app->service(PatientPlatformService::class);
        $profiles = $platform->profiles($userId);
        $self = $platform->profile($userId);
        $now = $this->app->clock->nowSql();
        $appointments = array_values(array_filter(
            $this->app->service(AppointmentService::class)->forPatients(array_map(static fn ($p) => (int) $p['id'], $profiles)),
            static fn ($a) => $a['starts_at'] >= $now && in_array($a['status'], ['scheduled', 'confirmed'], true)
        ));
        $passport = $platform->passport((int) $self['id']);
        $hp = $passport['profile'];
        $filled = count(array_filter([$hp['blood_group'] !== 'unknown', $hp['height_cm'] !== null, $hp['weight_kg'] !== null,
            $hp['allergies'] !== null || $hp['conditions'] !== null, $passport['contacts'] !== []]));
        return $this->view('patient/dashboard', [
            'title' => 'Your dashboard',
            'patient' => $this->app->service(PatientService::class)->forUser($userId),
            'active' => $testing->forPatientUser($userId, true),
            'past' => array_slice($testing->forPatientUser($userId, false), 0, 5),
            'notifications' => $this->app->service(NotificationService::class)->latest($userId, 5),
            'appointments' => array_slice($appointments, 0, 4),
            'family' => count($profiles) - 1,
            'passportFilled' => $filled,
            'passportOf' => 5,
        ], 200, 'app');
    }

    public function history(Request $request): Response
    {
        $profiles = $this->app->service(PatientPlatformService::class)->profiles($this->userId());
        return $this->view('patient/history', [
            'title' => 'Tests and results',
            'requests' => $this->app->service(TestingService::class)->forPatientUser($this->userId()),
            'labOrders' => $this->app->service(LaboratoryService::class)->releasedForPatients(array_map(static fn ($p) => (int) $p['id'], $profiles)),
        ], 200, 'app');
    }

    public function newForm(Request $request): Response
    {
        return $this->renderNew();
    }

    /**
     * @param array<string,string> $errors
     * @param array<string,mixed> $old
     */
    private function renderNew(array $errors = [], array $old = [], int $status = 200): Response
    {
        $patient = $this->app->service(PatientService::class)->forUser($this->userId());
        if ($old === [] && $patient !== null) {
            $old = [
                'city' => $patient['city'], 'state_code' => $patient['state_code'], 'pincode' => $patient['pincode'],
                'collection_address' => $patient['address_line'],
            ];
        }
        return $this->view('patient/request-new', [
            'title' => 'Request a test',
            'patient' => $patient,
            'states' => $this->app->service(PatientService::class)->states(),
            'formToken' => $this->formToken('patient-test'),
            'errors' => $errors,
            'old' => $old,
            'today' => $this->app->clock->now()->format('Y-m-d'),
            'maxDate' => $this->app->clock->now()->modify('+14 days')->format('Y-m-d'),
        ], $status, 'app');
    }

    public function create(Request $request): Response
    {
        $testing = $this->app->service(TestingService::class);
        $token = $request->input('form_token');
        if ($token !== '' && ($existing = $testing->findByIdempotencyKey($token)) !== null) {
            $owned = $testing->detailForPatient($this->userId(), (int) $existing['id']);
            if ($owned !== null) {
                return $this->redirect('/patient/requests/' . (int) $existing['id']);
            }
        }
        if (!$this->consumeFormToken('patient-test', $token)) {
            $this->flash('error', 'This form expired or was already submitted. Please review and submit again.');
            return $this->renderNew([], $request->body, 409);
        }
        try {
            $result = $testing->submitForUser($this->userId(), $request->body, $token);
        } catch (ValidationFailed $e) {
            return $this->renderNew($e->errors, $request->body, 422);
        }
        $this->flash('success', 'Request ' . $result['reference'] . ' submitted. Keep this reference for your records.');
        return $this->redirect('/patient/requests/' . $result['id']);
    }

    public function show(Request $request, string $id): Response
    {
        $detail = $this->app->service(TestingService::class)->detailForPatient($this->userId(), (int) $id);
        if ($detail === null) {
            $this->notFound();
        }
        return $this->view('patient/request-show', ['title' => 'Request ' . $detail['reference'], 'request' => $detail], 200, 'app');
    }

    public function report(Request $request, string $id): Response
    {
        $detail = $this->app->service(TestingService::class)->detailForPatient($this->userId(), (int) $id);
        if ($detail === null || $detail['status'] !== 'result_available' || $detail['result'] === null) {
            $this->notFound();
        }
        return $this->view('patient/report', [
            'title' => 'Test report ' . $detail['reference'],
            'request' => $detail,
            'generatedAt' => $this->app->clock->nowSql(),
        ], 200, 'print');
    }

    public function cancel(Request $request, string $id): Response
    {
        $userId = $this->userId();
        if ($this->app->service(TestingService::class)->detailForPatient($userId, (int) $id) === null) {
            $this->notFound();
        }
        try {
            $this->app->service(TestingService::class)->cancel((int) $id, ['id' => $userId, 'role' => 'patient'], $request->input('reason'));
            $this->flash('success', 'Your request has been cancelled.');
        } catch (WorkflowException $e) {
            $this->flash('error', $e->getMessage());
        }
        return $this->redirect('/patient/requests/' . (int) $id);
    }

    public function linkForm(Request $request): Response
    {
        return $this->view('patient/link', ['title' => 'Link an earlier request'], 200, 'app');
    }

    public function link(Request $request): Response
    {
        $reference = Rules::normalise('reference', $request->input('reference'));
        $dob = trim($request->input('date_of_birth'));
        $errors = [];
        if (Rules::check('reference', $reference) !== null) {
            $errors['reference'] = 'Enter a reference such as VQ-261010-7K3MP.';
        }
        if (Rules::check('iso_date', $dob) !== null) {
            $errors['date_of_birth'] = 'Enter the date of birth used on that request.';
        }
        if ($errors === [] && !$this->app->rateLimiter()->hit('link:' . $this->userId(), 10, 3600)) {
            $errors['form'] = 'Too many attempts. Please try again later.';
        }
        if ($errors === []) {
            $linked = $this->app->service(TestingService::class)->linkToAccount($this->userId(), $reference, $dob);
            if ($linked !== null) {
                $this->flash('success', "Request {$linked} is now part of your history.");
                return $this->redirect('/patient/requests');
            }
            $errors['form'] = 'We could not find a request made without an account that matches your mobile number, this reference and this date of birth.';
        }
        return $this->view('patient/link', ['title' => 'Link an earlier request', 'errors' => $errors, 'old' => $request->body], 422, 'app');
    }
}
