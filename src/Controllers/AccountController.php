<?php

declare(strict_types=1);

namespace Vitaliq\Controllers;

use Vitaliq\Core\Request;
use Vitaliq\Core\Response;
use Vitaliq\Services\PatientService;
use Vitaliq\Services\ValidationFailed;

final class AccountController extends Controller
{
    public function profile(Request $request): Response
    {
        return $this->renderProfile();
    }

    /**
     * @param array<string,string> $errors
     * @param array<string,mixed> $old
     */
    private function renderProfile(array $errors = [], array $old = [], int $status = 200): Response
    {
        $user = $this->app->auth()->user();
        $patients = $this->app->service(PatientService::class);
        $patient = $user['role'] === 'patient' ? $patients->forUser((int) $user['id']) : null;
        if ($old === []) {
            $old = $patient ?? ['full_name' => $user['full_name'], 'mobile' => $user['mobile']];
        }
        return $this->view('account/profile', [
            'title' => 'Your profile',
            'patient' => $patient,
            'states' => $patients->states(),
            'errors' => $errors,
            'old' => $old,
        ], $status, 'app');
    }

    public function updateProfile(Request $request): Response
    {
        $user = $this->app->auth()->user();
        $patients = $this->app->service(PatientService::class);
        try {
            if ($user['role'] === 'patient') {
                $patients->updateProfile((int) $user['id'], $request->body);
            } else {
                $patients->updateStaffProfile((int) $user['id'], $request->body);
            }
        } catch (ValidationFailed $e) {
            return $this->renderProfile($e->errors, $request->body, 422);
        }
        $this->flash('success', 'Your profile has been updated.');
        return $this->redirect('/account');
    }

    public function passwordForm(Request $request): Response
    {
        return $this->view('account/password', ['title' => 'Change password'], 200, 'app');
    }

    public function changePassword(Request $request): Response
    {
        try {
            $this->app->service(PatientService::class)->changePassword($this->userId(), $request->body);
        } catch (ValidationFailed $e) {
            return $this->view('account/password', ['title' => 'Change password', 'errors' => $e->errors], 422, 'app');
        }
        $this->flash('success', 'Password changed. Any other signed-in sessions have been signed out.');
        return $this->redirect('/account/password');
    }
}
