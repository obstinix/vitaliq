<?php

declare(strict_types=1);

namespace Vitaliq\Controllers\Admin;

use DateTimeImmutable;
use Vitaliq\Controllers\Controller;
use Vitaliq\Core\Request;
use Vitaliq\Core\Response;
use Vitaliq\Services\AppointmentService;
use Vitaliq\Services\PatientPlatformService;
use Vitaliq\Services\ValidationFailed;
use Vitaliq\Services\WorkflowException;

/** Administrator views of appointments and patient feedback (features 4, 5, 10). */
final class AdminCareController extends Controller
{
    public function appointments(Request $request): Response
    {
        $svc = $this->app->service(AppointmentService::class);
        $day = preg_match('/^\d{4}-\d{2}-\d{2}$/', $request->query('day')) ? $request->query('day')
            : (preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $request->query('month')) ? $request->query('month') . '-01' : $this->app->clock->now()->format('Y-m-d'));
        $facility = max(0, (int) $request->query('facility'));
        $start = new DateTimeImmutable($day);
        $month = $start->format('Y-m');
        $monthStart = new DateTimeImmutable($month . '-01');
        $events = $svc->forRange($monthStart->modify('-7 days')->format('Y-m-d H:i:s'), $monthStart->modify('+1 month +7 days')->format('Y-m-d H:i:s'), $facility);
        return $this->view('admin/appointments', [
            'title' => 'Appointments',
            'day' => $day,
            'facility' => $facility,
            'facilities' => $svc->facilities(),
            'rows' => $svc->forRange($start->format('Y-m-d 00:00:00'), $start->modify('+1 day')->format('Y-m-d 00:00:00'), $facility),
            'grid' => $svc->monthGrid($month, $events),
        ], 200, 'app');
    }

    public function setAppointmentStatus(Request $request, string $id): Response
    {
        try {
            $this->app->service(AppointmentService::class)->setStatus((int) $id, $request->input('status'), $this->userId());
            $this->flash('success', 'Appointment updated.');
        } catch (WorkflowException $e) {
            $this->flash('error', $e->getMessage());
        }
        return $this->redirect('/admin/appointments' . ($request->input('day') !== '' ? '?day=' . rawurlencode($request->input('day')) : ''));
    }

    public function feedback(Request $request): Response
    {
        $status = in_array($request->query('status'), ['new', 'reviewed', 'actioned'], true) ? $request->query('status') : '';
        return $this->view('admin/feedback', [
            'title' => 'Patient feedback',
            'status' => $status,
            'data' => $this->app->service(PatientPlatformService::class)->feedbackForAdmin($status),
            'categories' => PatientPlatformService::FEEDBACK_CATEGORIES,
        ], 200, 'app');
    }

    public function reviewFeedback(Request $request, string $id): Response
    {
        try {
            if (!$this->app->service(PatientPlatformService::class)->reviewFeedback((int) $id, $request->input('status'), $request->input('note'), $this->userId())) {
                $this->notFound();
            }
            $this->flash('success', 'Feedback updated.');
        } catch (ValidationFailed $e) {
            $this->flash('error', implode(' ', $e->errors));
        }
        return $this->redirect('/admin/feedback');
    }
}
