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

    /** Language, accessibility mode and outside notification channels (features 35, 38, 59). */
    public function preferences(Request $request): Response
    {
        $delivery = $this->app->service(\Vitaliq\Services\DeliveryService::class);
        $channels = $delivery->channels();
        $status = [];
        foreach (array_keys(\Vitaliq\Services\DeliveryService::CHANNELS) as $ch) {
            $status[$ch] = ['enabled' => $channels->enabled($ch), 'live' => $channels->isLive($ch)];
        }
        return $this->view('account/preferences', [
            'title' => t('Preferences'),
            'prefs' => $delivery->preferences($this->userId()),
            'recipients' => $delivery->recipients($this->userId()),
            'channelStatus' => $status,
            'recent' => $delivery->recentFor($this->userId(), 10),
            'locales' => \Vitaliq\Support\Lang::LOCALES,
        ], 200, 'app');
    }

    public function savePreferences(Request $request): Response
    {
        $locale = $request->input('locale');
        $userId = $this->userId();
        $this->app->db->execute('UPDATE users SET locale = :l, accessibility_mode = :a WHERE id = :id', [
            'l' => isset(\Vitaliq\Support\Lang::LOCALES[$locale]) ? $locale : 'en',
            'a' => $request->input('accessibility_mode') === '1' ? 1 : 0,
            'id' => $userId,
        ]);
        $this->app->session()->set('locale', isset(\Vitaliq\Support\Lang::LOCALES[$locale]) ? $locale : 'en');
        $this->app->service(\Vitaliq\Services\DeliveryService::class)->savePreferences($userId, $request->body);
        $this->app->auth()->refresh();
        \Vitaliq\Support\Lang::set(isset(\Vitaliq\Support\Lang::LOCALES[$locale]) ? $locale : 'en');
        $this->flash('success', t('Preferences saved.'));
        return $this->redirect('/account/preferences');
    }
}
