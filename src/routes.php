<?php

declare(strict_types=1);

use Vitaliq\Controllers\AccountController;
use Vitaliq\Controllers\Admin\AdminCareController;
use Vitaliq\Controllers\Admin\AdminCommentController;
use Vitaliq\Controllers\Admin\AdminContentController;
use Vitaliq\Controllers\Admin\AdminDashboardController;
use Vitaliq\Controllers\Admin\AdminPhlebotomistController;
use Vitaliq\Controllers\Admin\AdminReportController;
use Vitaliq\Controllers\Admin\AdminRequestController;
use Vitaliq\Controllers\Admin\AdminUserController;
use Vitaliq\Controllers\AuthController;
use Vitaliq\Controllers\GuestTestController;
use Vitaliq\Controllers\HealthCardController;
use Vitaliq\Controllers\NotificationController;
use Vitaliq\Controllers\PatientController;
use Vitaliq\Controllers\PatientPortalController;
use Vitaliq\Controllers\PublicController;
use Vitaliq\Controllers\QaController;
use Vitaliq\Controllers\StaffController;
use Vitaliq\Core\Router;

/*
 * Route table. Every POST route is CSRF-checked by the kernel.
 * Middleware: 'guest', 'auth', 'role:<role>[,<role>]'.
 */
return static function (Router $r): void {
    // Public information
    $r->get('/', [PublicController::class, 'home']);
    $r->get('/covid-19', [PublicController::class, 'overview']);
    $r->get('/symptoms', [PublicController::class, 'symptoms']);
    $r->get('/prevention', [PublicController::class, 'prevention']);
    $r->get('/testing', [PublicController::class, 'testing']);
    $r->get('/faq', [PublicController::class, 'faq']);
    $r->get('/announcements', [PublicController::class, 'announcements']);
    $r->get('/statistics', [PublicController::class, 'statistics']);
    $r->get('/contact', [PublicController::class, 'contact']);
    $r->get('/privacy', [PublicController::class, 'privacy']);
    $r->get('/community', [PublicController::class, 'community']);
    $r->post('/community', [PublicController::class, 'postComment']);
    $r->get('/api/statistics', [PublicController::class, 'statisticsJson']);

    // QR health ID emergency card (public, token-gated)
    $r->get('/h/{token}', [HealthCardController::class, 'show']);

    // New-patient (guest) testing and tracking
    $r->get('/test/new', [GuestTestController::class, 'form']);
    $r->post('/test/new', [GuestTestController::class, 'submit']);
    $r->get('/test/confirmation/{ref}', [GuestTestController::class, 'confirmation']);
    $r->get('/track', [GuestTestController::class, 'trackForm']);
    $r->post('/track', [GuestTestController::class, 'track']);
    $r->get('/track/{ref}', [GuestTestController::class, 'trackResult']);

    // Authentication
    $r->get('/register', [AuthController::class, 'registerForm'], ['guest']);
    $r->post('/register', [AuthController::class, 'register'], ['guest']);
    $r->get('/login', [AuthController::class, 'loginForm'], ['guest']);
    $r->post('/login', [AuthController::class, 'login'], ['guest']);
    $r->post('/logout', [AuthController::class, 'logout'], ['auth']);
    $r->get('/forgot-password', [AuthController::class, 'forgotForm'], ['guest']);
    $r->post('/forgot-password', [AuthController::class, 'forgot'], ['guest']);
    $r->get('/reset-password/{token}', [AuthController::class, 'resetForm']);
    $r->post('/reset-password/{token}', [AuthController::class, 'reset']);

    // Any signed-in user
    $r->get('/account', [AccountController::class, 'profile'], ['auth']);
    $r->post('/account', [AccountController::class, 'updateProfile'], ['auth']);
    $r->get('/account/password', [AccountController::class, 'passwordForm'], ['auth']);
    $r->post('/account/password', [AccountController::class, 'changePassword'], ['auth']);
    $r->get('/notifications', [NotificationController::class, 'index'], ['auth']);
    $r->get('/notifications/{id}', [NotificationController::class, 'open'], ['auth']);
    $r->post('/notifications/{id}/read', [NotificationController::class, 'markRead'], ['auth']);
    $r->post('/notifications/read-all', [NotificationController::class, 'markAllRead'], ['auth']);
    $r->get('/api/notifications', [NotificationController::class, 'poll'], ['auth']);

    // Patients
    $r->get('/patient', [PatientController::class, 'dashboard'], ['role:patient']);
    $r->get('/patient/requests', [PatientController::class, 'history'], ['role:patient']);
    $r->get('/patient/requests/new', [PatientController::class, 'newForm'], ['role:patient']);
    $r->post('/patient/requests/new', [PatientController::class, 'create'], ['role:patient']);
    $r->get('/patient/requests/{id}', [PatientController::class, 'show'], ['role:patient']);
    $r->get('/patient/requests/{id}/report', [PatientController::class, 'report'], ['role:patient']);
    $r->post('/patient/requests/{id}/cancel', [PatientController::class, 'cancel'], ['role:patient']);
    $r->get('/patient/link', [PatientController::class, 'linkForm'], ['role:patient']);
    $r->post('/patient/link', [PatientController::class, 'link'], ['role:patient']);

    // Patient platform (features 2-10)
    $pp = PatientPortalController::class;
    $p = ['role:patient'];
    $r->get('/patient/family', [$pp, 'family'], $p);
    $r->post('/patient/family', [$pp, 'addFamily'], $p);
    $r->post('/patient/family/{id}/remove', [$pp, 'removeFamily'], $p);
    $r->get('/patient/passport', [$pp, 'passport'], $p);
    $r->post('/patient/passport', [$pp, 'savePassport'], $p);
    $r->post('/patient/passport/immunisations', [$pp, 'addImmunisation'], $p);
    $r->post('/patient/passport/immunisations/{id}/delete', [$pp, 'deleteImmunisation'], $p);
    $r->get('/patient/contacts', [$pp, 'contacts'], $p);
    $r->post('/patient/contacts', [$pp, 'addContact'], $p);
    $r->post('/patient/contacts/{id}/primary', [$pp, 'primaryContact'], $p);
    $r->post('/patient/contacts/{id}/delete', [$pp, 'deleteContact'], $p);
    $r->get('/patient/appointments', [$pp, 'appointments'], $p);
    $r->get('/patient/appointments/new', [$pp, 'newAppointment'], $p);
    $r->post('/patient/appointments/new', [$pp, 'bookAppointment'], $p);
    $r->get('/patient/appointments/{id}', [$pp, 'showAppointment'], $p);
    $r->post('/patient/appointments/{id}/cancel', [$pp, 'cancelAppointment'], $p);
    $r->get('/patient/health-id', [$pp, 'healthId'], $p);
    $r->post('/patient/health-id', [$pp, 'issueHealthId'], $p);
    $r->post('/patient/health-id/{id}/revoke', [$pp, 'revokeHealthId'], $p);
    $r->get('/patient/documents', [$pp, 'documents'], $p);
    $r->post('/patient/documents', [$pp, 'uploadDocument'], $p);
    $r->get('/patient/documents/{id}', [$pp, 'downloadDocument'], $p);
    $r->post('/patient/documents/{id}/delete', [$pp, 'deleteDocument'], $p);
    $r->get('/patient/timeline', [$pp, 'timeline'], $p);
    $r->get('/patient/feedback', [$pp, 'feedback'], $p);
    $r->post('/patient/feedback', [$pp, 'sendFeedback'], $p);

    // Phlebotomist portal
    $r->get('/staff', [StaffController::class, 'tasks'], ['role:phlebotomist']);
    $r->get('/staff/tasks/{id}', [StaffController::class, 'task'], ['role:phlebotomist']);
    $r->post('/staff/tasks/{id}/collect', [StaffController::class, 'collect'], ['role:phlebotomist']);

    // Administration
    $r->get('/admin', [AdminDashboardController::class, 'index'], ['role:admin']);
    $r->get('/api/admin/series', [AdminDashboardController::class, 'series'], ['role:admin']);
    $r->get('/admin/requests', [AdminRequestController::class, 'index'], ['role:admin']);
    $r->get('/admin/requests/{id}', [AdminRequestController::class, 'show'], ['role:admin']);
    $r->post('/admin/requests/{id}/assign', [AdminRequestController::class, 'assign'], ['role:admin']);
    $r->post('/admin/requests/{id}/collect', [AdminRequestController::class, 'collect'], ['role:admin']);
    $r->post('/admin/requests/{id}/process', [AdminRequestController::class, 'process'], ['role:admin']);
    $r->post('/admin/requests/{id}/result', [AdminRequestController::class, 'result'], ['role:admin']);
    $r->post('/admin/requests/{id}/release', [AdminRequestController::class, 'release'], ['role:admin']);
    $r->post('/admin/requests/{id}/reject', [AdminRequestController::class, 'reject'], ['role:admin']);
    $r->post('/admin/requests/{id}/cancel', [AdminRequestController::class, 'cancel'], ['role:admin']);
    $r->get('/admin/appointments', [AdminCareController::class, 'appointments'], ['role:admin']);
    $r->post('/admin/appointments/{id}/status', [AdminCareController::class, 'setAppointmentStatus'], ['role:admin']);
    $r->get('/admin/feedback', [AdminCareController::class, 'feedback'], ['role:admin']);
    $r->post('/admin/feedback/{id}', [AdminCareController::class, 'reviewFeedback'], ['role:admin']);
    $r->get('/admin/reports', [AdminReportController::class, 'index'], ['role:admin']);
    $r->get('/admin/reports/export', [AdminReportController::class, 'export'], ['role:admin']);
    $r->get('/admin/phlebotomists', [AdminPhlebotomistController::class, 'index'], ['role:admin']);
    $r->get('/admin/phlebotomists/new', [AdminPhlebotomistController::class, 'createForm'], ['role:admin']);
    $r->post('/admin/phlebotomists/new', [AdminPhlebotomistController::class, 'create'], ['role:admin']);
    $r->get('/admin/phlebotomists/{id}', [AdminPhlebotomistController::class, 'edit'], ['role:admin']);
    $r->post('/admin/phlebotomists/{id}', [AdminPhlebotomistController::class, 'update'], ['role:admin']);
    $r->post('/admin/phlebotomists/{id}/toggle', [AdminPhlebotomistController::class, 'toggle'], ['role:admin']);
    $r->post('/admin/phlebotomists/{id}/delete', [AdminPhlebotomistController::class, 'delete'], ['role:admin']);
    $r->get('/admin/comments', [AdminCommentController::class, 'index'], ['role:admin']);
    $r->post('/admin/comments/{id}', [AdminCommentController::class, 'moderate'], ['role:admin']);
    $r->get('/admin/users', [AdminUserController::class, 'index'], ['role:admin']);
    $r->get('/admin/users/new', [AdminUserController::class, 'inviteForm'], ['role:admin']);
    $r->post('/admin/users/new', [AdminUserController::class, 'invite'], ['role:admin']);
    $r->post('/admin/users/{id}/toggle', [AdminUserController::class, 'toggle'], ['role:admin']);
    $r->post('/admin/users/{id}/reinvite', [AdminUserController::class, 'reinvite'], ['role:admin']);
    $r->get('/admin/audit', [AdminUserController::class, 'audit'], ['role:admin']);
    $r->get('/admin/announcements', [AdminContentController::class, 'index'], ['role:admin']);
    $r->post('/admin/announcements', [AdminContentController::class, 'create'], ['role:admin']);
    $r->post('/admin/announcements/{id}/toggle', [AdminContentController::class, 'toggle'], ['role:admin']);

    // STQA laboratory
    $r->get('/qa', [QaController::class, 'overview'], ['role:qa,admin']);
    $r->get('/qa/validator', [QaController::class, 'validator'], ['role:qa,admin']);
    $r->post('/qa/validator', [QaController::class, 'validate'], ['role:qa,admin']);
    $r->post('/api/qa/evaluate', [QaController::class, 'evaluateJson'], ['role:qa,admin']);
    $r->get('/qa/cases', [QaController::class, 'cases'], ['role:qa,admin']);
    $r->get('/qa/cases/new', [QaController::class, 'caseForm'], ['role:qa,admin']);
    $r->post('/qa/cases/new', [QaController::class, 'createCase'], ['role:qa,admin']);
    $r->get('/qa/cases/{id}', [QaController::class, 'showCase'], ['role:qa,admin']);
    $r->post('/qa/cases/{id}/execute', [QaController::class, 'recordManual'], ['role:qa,admin']);
    $r->post('/qa/cases/{id}/defect', [QaController::class, 'linkDefect'], ['role:qa,admin']);
    $r->get('/qa/runs', [QaController::class, 'runs'], ['role:qa,admin']);
    $r->post('/qa/runs', [QaController::class, 'startRun'], ['role:qa,admin']);
    $r->get('/qa/runs/{id}', [QaController::class, 'showRun'], ['role:qa,admin']);
    $r->get('/qa/defects', [QaController::class, 'defects'], ['role:qa,admin']);
    $r->get('/qa/defects/new', [QaController::class, 'defectForm'], ['role:qa,admin']);
    $r->post('/qa/defects/new', [QaController::class, 'createDefect'], ['role:qa,admin']);
    $r->get('/qa/defects/{id}', [QaController::class, 'showDefect'], ['role:qa,admin']);
    $r->post('/qa/defects/{id}', [QaController::class, 'updateDefect'], ['role:qa,admin']);
};
