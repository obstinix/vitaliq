<?php

declare(strict_types=1);

namespace Vitaliq\Tests\Integration;

use Vitaliq\Auth\Auth;
use Vitaliq\Services\PatientService;
use Vitaliq\Services\ValidationFailed;
use Vitaliq\Tests\DatabaseTestCase;

final class RegistrationAndAuthTest extends DatabaseTestCase
{
    public function testRegistrationPersistsUserAndPatientWithHashedPassword(): void
    {
        $id = $this->createPatient('Kavya@Example.com');
        $user = $this->db->one('SELECT * FROM users WHERE id = :id', ['id' => $id]);
        $this->assertSame('patient', $user['role']);
        $this->assertSame('kavya@example.com', $user['email'], 'Email stored in lower case');
        $this->assertStringStartsWith('$argon2id$', $user['password_hash']);
        $this->assertStringNotContainsString('correct horse', $user['password_hash']);
        $patient = $this->db->one('SELECT * FROM patients WHERE user_id = :id', ['id' => $id]);
        $this->assertSame('Kavya Menon', $patient['full_name']);
        $this->assertSame($user['mobile'], $patient['mobile']);
        $this->assertSame(1, (int) $this->db->value("SELECT COUNT(*) FROM audit_events WHERE action = 'patient.registered'"));
    }

    public function testDuplicateEmailIsRejectedCaseInsensitively(): void
    {
        $this->createPatient('kavya@example.com');
        try {
            $this->createPatient('KAVYA@example.com');
            $this->fail('Duplicate email accepted');
        } catch (ValidationFailed $e) {
            $this->assertArrayHasKey('email', $e->errors);
        }
        $this->assertSame(1, (int) $this->db->value('SELECT COUNT(*) FROM users'));
        $this->assertSame(1, (int) $this->db->value('SELECT COUNT(*) FROM patients'));
    }

    public function testDuplicateMobileIsRejected(): void
    {
        $this->createPatient('a@example.com', ['mobile' => '9876500001']);
        try {
            $this->createPatient('b@example.com', ['mobile' => '+91 98765 00001']);
            $this->fail('Duplicate mobile accepted');
        } catch (ValidationFailed $e) {
            $this->assertArrayHasKey('mobile', $e->errors);
        }
    }

    public function testInvalidRegistrationSavesNothingAndReportsEachField(): void
    {
        try {
            $this->app->service(PatientService::class)->register([
                'full_name' => 'R2D2', 'email' => 'nope', 'mobile' => '12345', 'date_of_birth' => '2030-01-01',
                'password' => 'short', 'password_confirmation' => 'short',
            ]);
            $this->fail('Invalid registration accepted');
        } catch (ValidationFailed $e) {
            foreach (['full_name', 'email', 'mobile', 'date_of_birth', 'password', 'consent'] as $field) {
                $this->assertArrayHasKey($field, $e->errors, $field);
            }
        }
        $this->assertSame(0, (int) $this->db->value('SELECT COUNT(*) FROM users'));
    }

    public function testPasswordConfirmationMustMatch(): void
    {
        $this->expectException(ValidationFailed::class);
        $this->createPatient('x@example.com', ['password_confirmation' => 'different horse 42']);
    }

    public function testValidLoginRegeneratesSessionAndRecordsLogin(): void
    {
        $id = $this->createPatient();
        $result = $this->app->auth()->attempt('patient@vitaliq.test', 'correct horse 42');
        $this->assertTrue($result['ok']);
        $this->assertSame($id, $this->app->session()->get('user_id'));
        $this->assertSame(1, $this->app->session()->regenerations);
        $this->assertNotNull($this->db->value('SELECT last_login_at FROM users WHERE id = :id', ['id' => $id]));
        $this->assertSame('/patient', $this->app->auth()->homePath());
    }

    public function testWrongPasswordAndUnknownEmailGiveTheSameMessage(): void
    {
        $this->createPatient();
        $wrong = $this->app->auth()->attempt('patient@vitaliq.test', 'wrong horse 99');
        $unknown = $this->app->auth()->attempt('nobody@vitaliq.test', 'wrong horse 99');
        $this->assertFalse($wrong['ok']);
        $this->assertSame($wrong['error'], $unknown['error']);
        $this->assertNull($this->app->session()->get('user_id'));
    }

    public function testAccountIsLockedAfterFiveFailures(): void
    {
        $this->createPatient();
        for ($i = 0; $i < Auth::LOGIN_MAX_PER_ACCOUNT; $i++) {
            $this->assertFalse($this->app->auth()->attempt('patient@vitaliq.test', 'wrong horse 99')['ok']);
        }
        $locked = $this->app->auth()->attempt('patient@vitaliq.test', 'correct horse 42');
        $this->assertFalse($locked['ok'], 'Correct password must be refused while locked');
        $this->assertStringContainsString('Too many', (string) $locked['error']);

        $this->clock->advance('+16 minutes');
        $this->assertTrue($this->app->auth()->attempt('patient@vitaliq.test', 'correct horse 42')['ok'], 'Lock expires after the window');
    }

    public function testInactiveAccountCannotSignIn(): void
    {
        $this->createUser('admin', 'old@vitaliq.test', 'correct horse 42', false);
        $this->assertFalse($this->app->auth()->attempt('old@vitaliq.test', 'correct horse 42')['ok']);
    }

    public function testInvitedUserWithoutPasswordCannotSignIn(): void
    {
        $this->db->insert("INSERT INTO users (role, full_name, email, password_hash) VALUES ('qa', 'New Person', 'new@vitaliq.test', NULL)");
        $this->assertFalse($this->app->auth()->attempt('new@vitaliq.test', '')['ok']);
    }

    public function testSessionEndsWhenSessionVersionChangesOrUserDeactivated(): void
    {
        $id = $this->createPatient();
        $this->actingAs($id);
        $this->assertTrue($this->app->auth()->check());

        $this->db->execute('UPDATE users SET session_version = session_version + 1 WHERE id = :id', ['id' => $id]);
        $this->app->auth()->refresh();
        $this->assertFalse($this->app->auth()->check(), 'Old session must be rejected after a password change elsewhere');

        $this->actingAs($id);
        $this->db->execute('UPDATE users SET is_active = 0 WHERE id = :id', ['id' => $id]);
        $this->app->auth()->refresh();
        $this->assertFalse($this->app->auth()->check(), 'Deactivated users are signed out');
    }

    public function testIdleTimeoutSignsOut(): void
    {
        $id = $this->createPatient();
        $this->actingAs($id);
        $this->clock->advance('+31 minutes');
        $this->app->auth()->enforceIdleTimeout(30);
        $this->assertFalse($this->app->auth()->check());
        $this->assertNotNull($this->app->session()->getFlash('notice'));
    }

    public function testChangePasswordRequiresCurrentPasswordAndKeepsThisSession(): void
    {
        $id = $this->createPatient();
        $this->actingAs($id);
        $service = $this->app->service(PatientService::class);
        try {
            $service->changePassword($id, ['current_password' => 'wrong', 'password' => 'brand new pass 7', 'password_confirmation' => 'brand new pass 7']);
            $this->fail('Wrong current password accepted');
        } catch (ValidationFailed $e) {
            $this->assertArrayHasKey('current_password', $e->errors);
        }
        $service->changePassword($id, ['current_password' => 'correct horse 42', 'password' => 'brand new pass 7', 'password_confirmation' => 'brand new pass 7']);
        $this->assertTrue($this->app->auth()->check(), 'Current session stays signed in');
        $this->assertSame(2, (int) $this->db->value('SELECT session_version FROM users WHERE id = :id', ['id' => $id]));
        $this->assertTrue($this->app->auth()->attempt('patient@vitaliq.test', 'brand new pass 7')['ok']);
    }

    public function testProfileUpdateKeepsUserAndPatientInSync(): void
    {
        $id = $this->createPatient();
        $this->app->service(PatientService::class)->updateProfile($id, [
            'full_name' => 'Kavya  R  Menon', 'mobile' => '9123456780', 'date_of_birth' => '1994-07-21', 'sex' => 'female',
            'city' => 'Kochi', 'state_code' => 'KL', 'pincode' => '682001', 'address_line' => '1 Beach Road',
        ]);
        $this->assertSame('Kavya R Menon', $this->db->value('SELECT full_name FROM users WHERE id = :id', ['id' => $id]));
        $this->assertSame('9123456780', $this->db->value('SELECT mobile FROM patients WHERE user_id = :id', ['id' => $id]));
        $this->assertSame('KL', $this->db->value('SELECT state_code FROM patients WHERE user_id = :id', ['id' => $id]));
    }
}
