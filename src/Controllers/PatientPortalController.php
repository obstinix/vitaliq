<?php

declare(strict_types=1);

namespace Vitaliq\Controllers;

use Vitaliq\Core\Request;
use Vitaliq\Core\Response;
use Vitaliq\Services\AppointmentService;
use Vitaliq\Services\DocumentVaultService;
use Vitaliq\Services\HealthIdService;
use Vitaliq\Services\LaboratoryService;
use Vitaliq\Services\PatientPlatformService;
use Vitaliq\Services\PatientService;
use Vitaliq\Services\TestingService;
use Vitaliq\Services\ValidationFailed;
use Vitaliq\Services\WorkflowException;

/**
 * Patient platform pages (features 2-10). The profile being worked on comes
 * from ?for= (or the posted profile_id) and is always resolved through
 * PatientPlatformService::profile(), which 404s on a profile the signed-in
 * holder neither owns nor manages.
 */
final class PatientPortalController extends Controller
{
    private function platform(): PatientPlatformService
    {
        return $this->app->service(PatientPlatformService::class);
    }

    /** @return array<string,mixed> */
    private function current(Request $request): array
    {
        $for = (int) ($request->body['profile_id'] ?? $request->query('for', '0'));
        return $this->platform()->profile($this->userId(), $for > 0 ? $for : null);
    }

    /** @param array<string,mixed> $profile */
    private function toProfile(string $path, array $profile = []): Response
    {
        $query = isset($profile['rel']) && $profile['rel'] !== 'self' ? ['for' => (int) $profile['id']] : [];
        return Response::redirect(url($path, $query));
    }

    /**
     * @param array<string,mixed> $profile
     * @param array<string,mixed> $data
     */
    private function screen(string $template, string $title, array $profile, array $data = [], int $status = 200): Response
    {
        return $this->view($template, $data + [
            'title' => $title,
            'profile' => $profile,
            'profiles' => $this->platform()->profiles($this->userId()),
        ], $status, 'app');
    }

    /* -------------------------------------------------------------- family */

    public function family(Request $request): Response
    {
        return $this->screen('patient/family', 'Family profiles', $this->platform()->profile($this->userId()), [
            'relationships' => PatientPlatformService::RELATIONSHIPS,
            'today' => $this->app->clock->now()->format('Y-m-d'),
        ]);
    }

    public function addFamily(Request $request): Response
    {
        try {
            $id = $this->platform()->addFamilyMember($this->userId(), $request->body);
        } catch (ValidationFailed $e) {
            return $this->screen('patient/family', 'Family profiles', $this->platform()->profile($this->userId()), [
                'relationships' => PatientPlatformService::RELATIONSHIPS, 'errors' => $e->errors, 'old' => $request->body,
                'today' => $this->app->clock->now()->format('Y-m-d'),
            ], 422);
        }
        $this->flash('success', 'Family profile added. You can now book appointments and keep records for them.');
        return Response::redirect(url('/patient/passport', ['for' => $id]));
    }

    public function removeFamily(Request $request, string $id): Response
    {
        try {
            $this->platform()->removeFamilyMember($this->userId(), (int) $id);
            $this->flash('success', 'The profile was removed from your account. Its test history is kept.');
        } catch (WorkflowException $e) {
            $this->flash('error', $e->getMessage());
        }
        return $this->redirect('/patient/family');
    }

    /* ------------------------------------------------------------ passport */

    /**
     * @param array<string,string> $errors
     * @param array<string,mixed> $old
     */
    public function passport(Request $request, array $errors = [], array $old = [], int $status = 200): Response
    {
        $profile = $this->current($request);
        return $this->screen('patient/passport', 'Health passport', $profile, [
            'passport' => $this->platform()->passport((int) $profile['id']),
            'age' => PatientService::age((string) $profile['date_of_birth'], $this->app->clock->now()),
            'errors' => $errors, 'old' => $old,
            'today' => $this->app->clock->now()->format('Y-m-d'),
        ], $status);
    }

    public function savePassport(Request $request): Response
    {
        $profile = $this->current($request);
        try {
            $this->platform()->savePassport((int) $profile['id'], $request->body);
        } catch (ValidationFailed $e) {
            return $this->passport($request, $e->errors, $request->body, 422);
        }
        $this->flash('success', 'Health passport saved.');
        return $this->toProfile('/patient/passport', $profile);
    }

    public function addImmunisation(Request $request): Response
    {
        $profile = $this->current($request);
        try {
            $this->platform()->addImmunisation((int) $profile['id'], $request->body);
        } catch (ValidationFailed $e) {
            return $this->passport($request, $e->errors, $request->body, 422);
        }
        $this->flash('success', 'Immunisation added.');
        return $this->toProfile('/patient/passport', $profile);
    }

    public function deleteImmunisation(Request $request, string $id): Response
    {
        $profile = $this->current($request);
        if (!$this->platform()->deleteImmunisation((int) $profile['id'], (int) $id)) {
            $this->notFound();
        }
        $this->flash('success', 'Immunisation removed.');
        return $this->toProfile('/patient/passport', $profile);
    }

    /* ------------------------------------------------------------ contacts */

    /**
     * @param array<string,string> $errors
     * @param array<string,mixed> $old
     */
    public function contacts(Request $request, array $errors = [], array $old = [], int $status = 200): Response
    {
        $profile = $this->current($request);
        return $this->screen('patient/contacts', 'Emergency contacts', $profile, [
            'contacts' => $this->platform()->contacts((int) $profile['id']), 'errors' => $errors, 'old' => $old,
        ], $status);
    }

    public function addContact(Request $request): Response
    {
        $profile = $this->current($request);
        try {
            $this->platform()->addContact((int) $profile['id'], $request->body);
        } catch (ValidationFailed $e) {
            return $this->contacts($request, $e->errors, $request->body, 422);
        }
        $this->flash('success', 'Emergency contact added.');
        return $this->toProfile('/patient/contacts', $profile);
    }

    public function primaryContact(Request $request, string $id): Response
    {
        $profile = $this->current($request);
        if (!$this->platform()->makePrimaryContact((int) $profile['id'], (int) $id)) {
            $this->notFound();
        }
        return $this->toProfile('/patient/contacts', $profile);
    }

    public function deleteContact(Request $request, string $id): Response
    {
        $profile = $this->current($request);
        if (!$this->platform()->deleteContact((int) $profile['id'], (int) $id)) {
            $this->notFound();
        }
        $this->flash('success', 'Emergency contact removed.');
        return $this->toProfile('/patient/contacts', $profile);
    }

    /* -------------------------------------------------------- appointments */

    public function appointments(Request $request): Response
    {
        $profiles = $this->platform()->profiles($this->userId());
        $ids = array_map(static fn ($p) => (int) $p['id'], $profiles);
        $svc = $this->app->service(AppointmentService::class);
        $mode = $request->query('view') === 'calendar' ? 'calendar' : 'list';
        $data = ['mode' => $mode];
        if ($mode === 'calendar') {
            $month = preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $request->query('month')) ? $request->query('month') : $this->app->clock->now()->format('Y-m');
            $from = $month . '-01 00:00:00';
            $to = (new \DateTimeImmutable($from))->modify('+1 month')->modify('+7 days')->format('Y-m-d H:i:s');
            $grid = $svc->monthGrid($month, $svc->forPatients($ids, (new \DateTimeImmutable($from))->modify('-7 days')->format('Y-m-d H:i:s'), $to));
            $data['grid'] = $grid;
        } else {
            $all = $svc->forPatients($ids);
            $now = $this->app->clock->nowSql();
            $data['upcoming'] = array_values(array_filter($all, static fn ($a) => $a['starts_at'] >= $now && in_array($a['status'], ['scheduled', 'confirmed'], true)));
            $data['past'] = array_reverse(array_values(array_filter($all, static fn ($a) => !($a['starts_at'] >= $now && in_array($a['status'], ['scheduled', 'confirmed'], true)))));
        }
        return $this->screen('patient/appointments', 'Appointments', $this->platform()->profile($this->userId()), $data);
    }

    /**
     * @param array<string,string> $errors
     * @param array<string,mixed> $old
     */
    public function newAppointment(Request $request, array $errors = [], array $old = [], int $status = 200): Response
    {
        $profile = $this->current($request);
        $now = $this->app->clock->now();
        return $this->screen('patient/appointment-new', 'Book an appointment', $profile, [
            'kinds' => AppointmentService::KINDS,
            'slots' => AppointmentService::slots(),
            'facilities' => $this->app->service(AppointmentService::class)->facilities(),
            'today' => $now->format('Y-m-d'),
            'maxDate' => $now->modify('+' . AppointmentService::MAX_DAYS_AHEAD . ' days')->format('Y-m-d'),
            'errors' => $errors, 'old' => $old + ['kind' => $request->query('kind')],
        ], $status);
    }

    public function bookAppointment(Request $request): Response
    {
        $profile = $this->current($request);
        try {
            $id = $this->app->service(AppointmentService::class)->book($this->userId(), (int) $profile['id'], $request->body);
        } catch (ValidationFailed $e) {
            return $this->newAppointment($request, $e->errors, $request->body, 422);
        }
        $this->flash('success', 'Appointment booked.');
        return $this->redirect('/patient/appointments/' . $id);
    }

    /** @return array<string,mixed> */
    private function ownedAppointment(int $id): array
    {
        $a = $this->app->service(AppointmentService::class)->find($id);
        $ids = array_map(static fn ($p) => (int) $p['id'], $this->platform()->profiles($this->userId()));
        if ($a === null || !in_array((int) $a['patient_id'], $ids, true)) {
            $this->notFound();
        }
        return $a;
    }

    public function showAppointment(Request $request, string $id): Response
    {
        $a = $this->ownedAppointment((int) $id);
        return $this->screen('patient/appointment-show', AppointmentService::KINDS[$a['kind']], $this->platform()->profile($this->userId()), [
            'appointment' => $a,
            'canCancel' => in_array($a['status'], ['scheduled', 'confirmed'], true)
                && strtotime((string) $a['starts_at']) - $this->app->clock->now()->getTimestamp() >= AppointmentService::CANCEL_NOTICE_MINUTES * 60,
        ]);
    }

    public function cancelAppointment(Request $request, string $id): Response
    {
        $a = $this->ownedAppointment((int) $id);
        try {
            $this->app->service(AppointmentService::class)->cancelByPatient((int) $a['patient_id'], (int) $id, $request->input('reason'));
            $this->flash('success', 'Appointment cancelled.');
        } catch (WorkflowException $e) {
            $this->flash('error', $e->getMessage());
        }
        return $this->redirect('/patient/appointments/' . (int) $id);
    }

    /* ----------------------------------------------------------- health ID */

    /**
     * @param array<string,string> $errors
     * @param array{id:int,token:string,url:string}|null $issued
     */
    public function healthId(Request $request, array $errors = [], ?array $issued = null, int $status = 200): Response
    {
        $profile = $this->current($request);
        return $this->screen('patient/health-id', 'QR health ID', $profile, [
            'tokens' => $this->app->service(HealthIdService::class)->tokens((int) $profile['id']),
            'issued' => $issued,
            'qr' => $issued !== null ? HealthIdService::qrSvg($issued['url']) : null,
            'errors' => $errors,
        ], $status);
    }

    public function issueHealthId(Request $request): Response
    {
        $profile = $this->current($request);
        $days = $request->input('days');
        try {
            $issued = $this->app->service(HealthIdService::class)->issue((int) $profile['id'], $this->userId(), $request->input('label'), $days === '' ? null : (int) $days);
        } catch (ValidationFailed $e) {
            return $this->healthId($request, $e->errors, null, 422);
        }
        // Shown once: the plain token is not stored.
        return $this->healthId($request, [], $issued);
    }

    public function revokeHealthId(Request $request, string $id): Response
    {
        $profile = $this->current($request);
        if (!$this->app->service(HealthIdService::class)->revoke((int) $profile['id'], (int) $id)) {
            $this->notFound();
        }
        $this->flash('success', 'Health ID revoked. Its QR code no longer opens anything.');
        return $this->toProfile('/patient/health-id', $profile);
    }

    /* ----------------------------------------------------------- documents */

    /**
     * @param array<string,string> $errors
     * @param array<string,mixed> $old
     */
    public function documents(Request $request, array $errors = [], array $old = [], int $status = 200): Response
    {
        $profile = $this->current($request);
        return $this->screen('patient/documents', 'Document vault', $profile, [
            'documents' => $this->app->service(DocumentVaultService::class)->list((int) $profile['id']),
            'categories' => DocumentVaultService::CATEGORIES, 'errors' => $errors, 'old' => $old,
        ], $status);
    }

    public function uploadDocument(Request $request): Response
    {
        $profile = $this->current($request);
        try {
            $this->app->service(DocumentVaultService::class)->store((int) $profile['id'], $this->userId(), $request->file('document'), $request->body);
        } catch (ValidationFailed $e) {
            return $this->documents($request, $e->errors, $request->body, 422);
        }
        $this->flash('success', 'Document encrypted and stored.');
        return $this->toProfile('/patient/documents', $profile);
    }

    public function downloadDocument(Request $request, string $id): Response
    {
        $profile = $this->current($request);
        $doc = $this->app->service(DocumentVaultService::class)->read((int) $profile['id'], (int) $id);
        if ($doc === null) {
            $this->notFound();
        }
        $ext = DocumentVaultService::TYPES[$doc['meta']['mime_type']] ?? 'bin';
        $name = pathinfo((string) $doc['meta']['original_name'], PATHINFO_FILENAME) . '.' . $ext;
        return Response::download($doc['bytes'], (string) $doc['meta']['mime_type'], $name, $request->query('inline') === '1');
    }

    public function deleteDocument(Request $request, string $id): Response
    {
        $profile = $this->current($request);
        if (!$this->app->service(DocumentVaultService::class)->delete((int) $profile['id'], (int) $id)) {
            $this->notFound();
        }
        $this->flash('success', 'Document deleted. The encrypted file was destroyed.');
        return $this->toProfile('/patient/documents', $profile);
    }

    /* ------------------------------------------------------------ timeline */

    public function timeline(Request $request): Response
    {
        $profile = $this->current($request);
        return $this->screen('patient/timeline', 'Health timeline', $profile, [
            'events' => $this->platform()->timeline((int) $profile['id']),
        ]);
    }

    /* --------------------------------------------------------- lab results */

    public function labResult(Request $request, string $id): Response
    {
        $ids = array_map(static fn ($p) => (int) $p['id'], $this->platform()->profiles($this->userId()));
        $order = $this->app->service(LaboratoryService::class)->order((int) $id);
        if ($order === null || $order['status'] !== 'released' || !in_array((int) $order['patient_id'], $ids, true)) {
            $this->notFound();
        }
        $order['items'] = array_values(array_filter($order['items'], static fn ($i) => $i['status'] === 'verified'));
        return $this->screen('patient/lab-result', 'Lab results ' . $order['order_no'], $this->platform()->profile($this->userId()), ['order' => $order, 'verify' => $this->verification('lab_order', (int) $order['id'])]);
    }

    /* ------------------------------------------------------------ feedback */

    /**
     * @param array<string,string> $errors
     * @param array<string,mixed> $old
     */
    public function feedback(Request $request, array $errors = [], array $old = [], int $status = 200): Response
    {
        return $this->screen('patient/feedback', 'Feedback', $this->platform()->profile($this->userId()), [
            'mine' => $this->platform()->feedbackForUser($this->userId()),
            'requests' => $this->app->service(TestingService::class)->forPatientUser($this->userId()),
            'categories' => PatientPlatformService::FEEDBACK_CATEGORIES,
            'errors' => $errors, 'old' => $old + ['request_id' => $request->query('request')],
        ], $status);
    }

    public function sendFeedback(Request $request): Response
    {
        try {
            $this->platform()->submitFeedback($this->userId(), $request->body);
        } catch (ValidationFailed $e) {
            return $this->feedback($request, $e->errors, $request->body, 422);
        }
        $this->flash('success', 'Thank you. Your feedback was sent to the Vitaliq team.');
        return $this->redirect('/patient/feedback');
    }

    /** @return array{code:string,qr:string} */
    private function verification(string $kind, int $id): array
    {
        $svc = $this->app->service(\Vitaliq\Services\ReportVerificationService::class);
        $code = $svc->codeFor($kind, $id);
        return ['code' => $code, 'qr' => \Vitaliq\Services\HealthIdService::qrSvg($svc->url($code))];
    }
}
