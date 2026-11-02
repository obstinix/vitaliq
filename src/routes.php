<?php

declare(strict_types=1);

use Vitaliq\Controllers\AccountController;
use Vitaliq\Controllers\Admin\AdminBroadcastController;
use Vitaliq\Controllers\Admin\AdminCareController;
use Vitaliq\Controllers\Admin\AdminCommentController;
use Vitaliq\Controllers\Admin\AdminContentController;
use Vitaliq\Controllers\Admin\AdminDashboardController;
use Vitaliq\Controllers\Admin\AdminGovernanceController;
use Vitaliq\Controllers\Admin\AdminPhlebotomistController;
use Vitaliq\Controllers\Admin\AdminReportController;
use Vitaliq\Controllers\Admin\AdminRequestController;
use Vitaliq\Controllers\Admin\AdminUserController;
use Vitaliq\Controllers\AuthController;
use Vitaliq\Controllers\GuestTestController;
use Vitaliq\Controllers\HealthCardController;
use Vitaliq\Controllers\LabController;
use Vitaliq\Controllers\NotificationController;
use Vitaliq\Controllers\PatientController;
use Vitaliq\Controllers\PatientPortalController;
use Vitaliq\Controllers\PublicController;
use Vitaliq\Controllers\QaController;
use Vitaliq\Controllers\SecurityController;
use Vitaliq\Controllers\StaffController;
use Vitaliq\Controllers\SurveillanceController;
use Vitaliq\Controllers\WorkspaceController;
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
    $r->post('/locale', [PublicController::class, 'setLocale']);
    $r->get('/api/health', [AdminGovernanceController::class, 'probe']);

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
    $r->get('/login/verify', [AuthController::class, 'mfaForm'], ['guest']);
    $r->post('/login/verify', [AuthController::class, 'mfa'], ['guest']);
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
    $r->get('/account/security', [SecurityController::class, 'index'], ['auth']);
    $r->post('/account/security/two-step', [SecurityController::class, 'startTwoFactor'], ['auth']);
    $r->post('/account/security/two-step/confirm', [SecurityController::class, 'confirmTwoFactor'], ['auth']);
    $r->post('/account/security/two-step/disable', [SecurityController::class, 'disableTwoFactor'], ['auth']);
    $r->post('/account/security/sessions/others', [SecurityController::class, 'revokeOthers'], ['auth']);
    $r->post('/account/security/sessions/{id}', [SecurityController::class, 'revokeSession'], ['auth']);
    $r->post('/account/security/delete', [SecurityController::class, 'requestDeletion'], ['auth']);
    $r->get('/account/preferences', [AccountController::class, 'preferences'], ['auth']);
    $r->post('/account/preferences', [AccountController::class, 'savePreferences'], ['auth']);
    $r->get('/notifications', [NotificationController::class, 'index'], ['auth']);
    $r->get('/notifications/{id}', [NotificationController::class, 'open'], ['auth']);
    $r->post('/notifications/{id}/read', [NotificationController::class, 'markRead'], ['auth']);
    $r->post('/notifications/read-all', [NotificationController::class, 'markAllRead'], ['auth']);
    $r->get('/api/notifications', [NotificationController::class, 'poll'], ['auth']);
    $r->get('/api/notifications/stream', [NotificationController::class, 'stream'], ['auth']);
    $r->get('/search', [WorkspaceController::class, 'search'], ['auth']);

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
    $r->get('/patient/lab-results/{id}', [$pp, 'labResult'], $p);
    $r->get('/patient/feedback', [$pp, 'feedback'], $p);
    $r->post('/patient/feedback', [$pp, 'sendFeedback'], $p);

    // Phlebotomist portal
    $r->get('/staff', [StaffController::class, 'tasks'], ['role:phlebotomist']);
    $r->get('/staff/tasks/{id}', [StaffController::class, 'task'], ['role:phlebotomist']);
    $r->post('/staff/tasks/{id}/collect', [StaffController::class, 'collect'], ['role:phlebotomist']);
    $r->get('/staff/schedule', [WorkspaceController::class, 'schedule'], ['role:phlebotomist,lab,officer,qa']);

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
    $r->get('/admin/broadcasts', [AdminBroadcastController::class, 'index'], ['role:admin', 'perm:admin.broadcast']);
    $r->post('/admin/broadcasts', [AdminBroadcastController::class, 'send'], ['role:admin', 'perm:admin.broadcast']);
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
    $g = AdminGovernanceController::class;
    $r->get('/admin/audit/analytics', [$g, 'auditAnalytics'], ['role:admin']);
    $r->get('/admin/roles', [$g, 'roles'], ['role:admin', 'perm:admin.rbac']);
    $r->post('/admin/roles', [$g, 'saveRoles'], ['role:admin', 'perm:admin.rbac']);
    $r->get('/admin/users/{id}/permissions', [$g, 'userPermissions'], ['role:admin', 'perm:admin.rbac']);
    $r->post('/admin/users/{id}/permissions', [$g, 'saveUserPermissions'], ['role:admin', 'perm:admin.rbac']);
    $r->get('/admin/facilities', [$g, 'facilities'], ['role:admin', 'perm:admin.facilities']);
    $r->post('/admin/facilities', [$g, 'createFacility'], ['role:admin', 'perm:admin.facilities']);
    $r->get('/admin/facilities/{id}', [$g, 'facility'], ['role:admin', 'perm:admin.facilities']);
    $r->post('/admin/facilities/{id}', [$g, 'updateFacility'], ['role:admin', 'perm:admin.facilities']);
    $r->post('/admin/facilities/{id}/toggle', [$g, 'toggleFacility'], ['role:admin', 'perm:admin.facilities']);
    $r->post('/admin/facilities/{id}/staff', [$g, 'facilityStaff'], ['role:admin', 'perm:admin.facilities']);
    $r->get('/admin/system', [$g, 'system'], ['role:admin', 'perm:admin.system']);
    $r->get('/admin/backups', [$g, 'backups'], ['role:admin', 'perm:admin.backups']);
    $r->post('/admin/backups', [$g, 'createBackup'], ['role:admin', 'perm:admin.backups']);
    $r->get('/admin/backups/{id}', [$g, 'downloadBackup'], ['role:admin', 'perm:admin.backups']);
    $r->get('/admin/retention', [SecurityController::class, 'retention'], ['role:admin', 'perm:admin.retention']);
    $r->post('/admin/retention', [SecurityController::class, 'savePolicies'], ['role:admin', 'perm:admin.retention']);
    $r->post('/admin/retention/purge', [SecurityController::class, 'purge'], ['role:admin', 'perm:admin.retention']);
    $r->post('/admin/retention/requests/{id}', [SecurityController::class, 'decide'], ['role:admin', 'perm:admin.retention']);
    $r->get('/admin/schedule', [WorkspaceController::class, 'schedule'], ['role:admin', 'perm:admin.schedule']);
    $r->post('/admin/schedule', [WorkspaceController::class, 'addShift'], ['role:admin', 'perm:admin.schedule']);
    $r->post('/admin/schedule/{id}/remove', [WorkspaceController::class, 'removeShift'], ['role:admin', 'perm:admin.schedule']);
    $r->get('/admin/announcements', [AdminContentController::class, 'index'], ['role:admin']);
    $r->post('/admin/announcements', [AdminContentController::class, 'create'], ['role:admin']);
    $r->post('/admin/announcements/{id}/toggle', [AdminContentController::class, 'toggle'], ['role:admin']);

    // Laboratory (features 11-20)
    $lc = LabController::class;
    $lab = static fn (string $perm): array => ['role:lab,admin', 'perm:' . $perm];
    $r->get('/lab', [$lc, 'overview'], ['role:lab,admin']);
    $r->get('/lab/samples', [$lc, 'samples'], $lab('lab.samples'));
    $r->get('/lab/scan', [$lc, 'scanForm'], $lab('lab.samples'));
    $r->post('/lab/scan', [$lc, 'scan'], $lab('lab.samples'));
    $r->get('/lab/samples/{id}', [$lc, 'sample'], $lab('lab.samples'));
    $r->post('/lab/samples/{id}/move', [$lc, 'moveSample'], $lab('lab.samples'));
    $r->post('/lab/samples/{id}/recollect', [$lc, 'recollect'], $lab('lab.samples'));
    $r->get('/lab/samples/{id}/label', [$lc, 'label'], $lab('lab.samples'));
    $r->get('/lab/orders', [$lc, 'orders'], $lab('lab.orders'));
    $r->get('/lab/orders/new', [$lc, 'newOrder'], $lab('lab.orders'));
    $r->post('/lab/orders/new', [$lc, 'createOrder'], $lab('lab.orders'));
    $r->get('/lab/orders/{id}', [$lc, 'order'], $lab('lab.orders'));
    $r->post('/lab/orders/{id}/release', [$lc, 'release'], $lab('lab.verify'));
    $r->post('/lab/orders/{id}/cancel', [$lc, 'cancel'], $lab('lab.orders'));
    $r->post('/lab/items/{id}/result', [$lc, 'enterResult'], $lab('lab.orders'));
    $r->post('/lab/items/{id}/verify', [$lc, 'verifyItem'], $lab('lab.verify'));
    $r->post('/lab/items/{id}/return', [$lc, 'returnItem'], $lab('lab.verify'));
    $r->post('/lab/items/{id}/reject', [$lc, 'rejectItem'], $lab('lab.orders'));
    $r->get('/lab/verify', [$lc, 'verifyQueue'], $lab('lab.verify'));
    $r->get('/lab/catalogue', [$lc, 'catalogue'], $lab('lab.orders'));
    $r->get('/lab/catalogue/{id}', [$lc, 'catalogueTest'], $lab('lab.orders'));
    $r->post('/lab/catalogue/{id}/ranges', [$lc, 'saveRange'], $lab('lab.verify'));
    $r->get('/lab/inventory', [$lc, 'inventory'], $lab('lab.inventory'));
    $r->post('/lab/inventory', [$lc, 'createItem'], $lab('lab.inventory'));
    $r->post('/lab/inventory/write-off', [$lc, 'writeOff'], $lab('lab.inventory'));
    $r->get('/lab/inventory/{id}', [$lc, 'item'], $lab('lab.inventory'));
    $r->post('/lab/inventory/{id}/receive', [$lc, 'receive'], $lab('lab.inventory'));
    $r->post('/lab/inventory/{id}/use', [$lc, 'consume'], $lab('lab.inventory'));
    $r->get('/lab/equipment', [$lc, 'equipment'], $lab('lab.equipment'));
    $r->post('/lab/equipment', [$lc, 'createEquipment'], $lab('lab.equipment'));
    $r->get('/lab/equipment/{id}', [$lc, 'equipmentItem'], $lab('lab.equipment'));
    $r->post('/lab/equipment/{id}/log', [$lc, 'logEquipment'], $lab('lab.equipment'));
    $r->get('/lab/performance', [$lc, 'performance'], $lab('lab.analytics'));

    // Public-health surveillance (features 21-30)
    $sc = SurveillanceController::class;
    $sv = static fn (string $perm): array => ['role:officer,admin', 'perm:' . $perm];
    $r->get('/surveillance', [$sc, 'overview'], $sv('surveillance.view'));
    $r->get('/surveillance/map', [$sc, 'map'], $sv('surveillance.view'));
    $r->get('/surveillance/trends', [$sc, 'trends'], $sv('surveillance.view'));
    $r->get('/surveillance/demographics', [$sc, 'demographics'], $sv('surveillance.view'));
    $r->get('/surveillance/facilities', [$sc, 'facilities'], $sv('surveillance.view'));
    $r->get('/surveillance/quality', [$sc, 'quality'], $sv('surveillance.view'));
    $r->get('/surveillance/reports', [$sc, 'reports'], $sv('surveillance.view'));
    $r->get('/surveillance/reports/export', [$sc, 'export'], $sv('surveillance.view'));
    $r->get('/surveillance/alerts', [$sc, 'alerts'], $sv('surveillance.alerts'));
    $r->post('/surveillance/alerts/rules', [$sc, 'createRule'], $sv('surveillance.alerts'));
    $r->post('/surveillance/alerts/rules/{id}/toggle', [$sc, 'toggleRule'], $sv('surveillance.alerts'));
    $r->post('/surveillance/alerts/check', [$sc, 'runCheck'], $sv('surveillance.alerts'));
    $r->post('/surveillance/alerts/{id}', [$sc, 'alertStatus'], $sv('surveillance.alerts'));
    $r->get('/surveillance/imports', [$sc, 'imports'], $sv('surveillance.import'));
    $r->post('/surveillance/imports', [$sc, 'upload'], $sv('surveillance.import'));
    $r->get('/surveillance/imports/template.csv', [$sc, 'template'], $sv('surveillance.import'));
    $r->get('/surveillance/imports/{id}', [$sc, 'import'], $sv('surveillance.import'));
    $r->post('/surveillance/imports/{id}', [$sc, 'importAction'], $sv('surveillance.import'));

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
