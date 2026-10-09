<?php

declare(strict_types=1);

namespace Vitaliq\Tests\Integration;

use Vitaliq\Services\TestingService;
use Vitaliq\Services\ValidationFailed;
use Vitaliq\Services\WorkflowException;
use Vitaliq\Tests\DatabaseTestCase;
use Vitaliq\Validation\Rules;

final class TestingWorkflowTest extends DatabaseTestCase
{
    private function testing(): TestingService
    {
        return $this->app->service(TestingService::class);
    }

    private function requestStatus(int $id): string
    {
        return (string) $this->db->value('SELECT status FROM test_requests WHERE id = :id', ['id' => $id]);
    }

    public function testGuestRequestCreatesAllRecordsInOneTransaction(): void
    {
        $adminId = $this->createAdmin();
        $result = $this->testing()->submitGuest($this->guestInput(), 'form-token-1');

        $this->assertNull(Rules::check('reference', $result['reference']), 'Reference matches the published format');
        $this->assertStringStartsWith('VQ-261010-', $result['reference']);
        $req = $this->db->one('SELECT * FROM test_requests WHERE id = :id', ['id' => $result['id']]);
        $this->assertSame('submitted', $req['status']);
        $this->assertSame('guest', $req['channel']);
        $this->assertSame(hash('sha256', 'form-token-1'), $req['idempotency_key']);
        $patient = $this->db->one('SELECT * FROM patients WHERE id = :id', ['id' => $req['patient_id']]);
        $this->assertNull($patient['user_id']);
        $this->assertSame('9876543210', $patient['mobile'], 'Mobile stored in canonical form');
        // (ORDER BY on an ENUM sorts by declaration order, so sort here instead.)
        $symptoms = array_column($this->db->all('SELECT symptom FROM test_request_symptoms WHERE request_id = :id', ['id' => $result['id']]), 'symptom');
        sort($symptoms);
        $this->assertSame(['cough', 'fever'], $symptoms);
        $this->assertSame(1, (int) $this->db->value("SELECT COUNT(*) FROM test_status_history WHERE request_id = :id AND to_status = 'submitted' AND actor_role = 'guest'", ['id' => $result['id']]));
        $this->assertSame(1, (int) $this->db->value("SELECT COUNT(*) FROM notifications WHERE user_id = :u AND type = 'request.new'", ['u' => $adminId]));
    }

    public function testSameIdempotencyKeyFindsTheExistingRequest(): void
    {
        $first = $this->testing()->submitGuest($this->guestInput(), 'same-token');
        $this->assertSame($first['reference'], $this->testing()->findByIdempotencyKey('same-token')['reference']);
        $this->assertNull($this->testing()->findByIdempotencyKey('other-token'));
    }

    public function testSecondActiveRequestOfSameTypeIsRefusedWithoutLeakingReference(): void
    {
        $first = $this->testing()->submitGuest($this->guestInput(), 't1');
        try {
            $this->testing()->submitGuest($this->guestInput(), 't2');
            $this->fail('Duplicate active request accepted');
        } catch (ValidationFailed $e) {
            $this->assertStringNotContainsString($first['reference'], $e->errors['test_type'], 'Guests must not learn existing references');
        }
        $this->assertSame(1, (int) $this->db->value('SELECT COUNT(*) FROM test_requests'));
        // A different test type is allowed, and reuses the same guest patient record.
        $this->testing()->submitGuest($this->guestInput(['test_type' => 'rapid_antigen']), 't3');
        $this->assertSame(1, (int) $this->db->value('SELECT COUNT(*) FROM patients'));
    }

    public function testAccountRequestUsesProfileAndShowsOwnReferenceOnDuplicate(): void
    {
        $userId = $this->createPatient();
        $first = $this->testing()->submitForUser($userId, $this->testInput(), 'a1');
        $patientId = (int) $this->db->value('SELECT id FROM patients WHERE user_id = :u', ['u' => $userId]);
        $this->assertSame($patientId, (int) $this->db->value('SELECT patient_id FROM test_requests WHERE id = :id', ['id' => $first['id']]));
        try {
            $this->testing()->submitForUser($userId, $this->testInput(), 'a2');
            $this->fail('Duplicate accepted');
        } catch (ValidationFailed $e) {
            $this->assertStringContainsString($first['reference'], $e->errors['test_type']);
        }
    }

    public function testInvalidTestDetailsAreRejected(): void
    {
        try {
            $this->testing()->submitGuest($this->guestInput([
                'preferred_date' => '2026-10-09', 'test_type' => 'antibody', 'collection_mode' => 'home',
                'collection_address' => '', 'pincode' => '056001', 'reason' => 'symptoms', 'symptoms' => [],
            ]), 'bad');
            $this->fail('Invalid request accepted');
        } catch (ValidationFailed $e) {
            foreach (['preferred_date', 'test_type', 'collection_address', 'pincode', 'symptoms'] as $field) {
                $this->assertArrayHasKey($field, $e->errors, $field);
            }
        }
        $this->assertSame(0, (int) $this->db->value('SELECT COUNT(*) FROM test_requests'));
        $this->assertSame(0, (int) $this->db->value('SELECT COUNT(*) FROM patients'), 'No orphan patient rows');
    }

    public function testFullWorkflowWithHistoryAndPatientNotifications(): void
    {
        $patientUser = $this->createPatient();
        $admin = $this->adminActor();
        [$phlebId, $phlebUser] = $this->createPhlebotomist('Ramesh Kumar', 'ramesh@vitaliq.test');
        $req = $this->testing()->submitForUser($patientUser, $this->testInput(), 'wf');
        $id = $req['id'];

        $this->testing()->assign($id, $phlebId, $admin);
        $this->assertSame('assigned', $this->requestStatus($id));
        $this->assertSame(1, (int) $this->db->value("SELECT COUNT(*) FROM notifications WHERE user_id = :u AND type = 'assignment.new'", ['u' => $phlebUser]));

        $this->testing()->recordCollection($id, ['specimen' => 'nasopharyngeal', 'barcode' => 'vqs20261010a'], ['id' => $phlebUser, 'role' => 'phlebotomist']);
        $this->assertSame('sample_collected', $this->requestStatus($id));
        $this->assertSame('VQS20261010A', $this->db->value('SELECT barcode FROM sample_collections WHERE request_id = :id', ['id' => $id]));

        $this->testing()->startProcessing($id, $admin);
        $this->testing()->recordResult($id, ['outcome' => 'negative', 'ct_value' => '', 'remarks' => 'No viral RNA detected.'], $admin);
        $this->assertSame('completed', $this->requestStatus($id));
        $detail = $this->testing()->detailForPatient($patientUser, $id);
        $this->assertNull($detail['result']['released_at'], 'Not released yet');

        $this->testing()->releaseResult($id, $admin);
        $this->assertSame('result_available', $this->requestStatus($id));

        $history = array_column($this->db->all('SELECT to_status FROM test_status_history WHERE request_id = :id ORDER BY id', ['id' => $id]), 'to_status');
        $this->assertSame(['submitted', 'assigned', 'sample_collected', 'in_progress', 'completed', 'result_available'], $history);
        $this->assertSame(0, (int) $this->db->value('SELECT COUNT(*) FROM test_status_history WHERE request_id = :id AND actor_user_id IS NULL', ['id' => $id]), 'Every step records its actor');

        $types = array_column($this->db->all('SELECT type FROM notifications WHERE user_id = :u ORDER BY id', ['u' => $patientUser]), 'type');
        $this->assertSame(['request.received', 'request.assigned', 'request.sample_collected', 'request.result_available'], $types);
    }

    public function testInvalidTransitionsAreRefusedAndStatusIsUnchanged(): void
    {
        $admin = $this->adminActor();
        $id = $this->testing()->submitGuest($this->guestInput(), 'x')['id'];

        foreach ([
            fn () => $this->testing()->releaseResult($id, $admin),
            fn () => $this->testing()->startProcessing($id, $admin),
            fn () => $this->testing()->recordResult($id, ['outcome' => 'positive'], $admin),
            fn () => $this->testing()->recordCollection($id, ['specimen' => 'nasal', 'barcode' => 'ABCDEFGH'], $admin),
        ] as $attempt) {
            try {
                $attempt();
                $this->fail('Invalid transition was accepted');
            } catch (WorkflowException) {
                $this->assertSame('submitted', $this->requestStatus($id));
            }
        }
        $this->assertSame(0, (int) $this->db->value('SELECT COUNT(*) FROM test_results'));
        $this->assertSame(0, (int) $this->db->value('SELECT COUNT(*) FROM sample_collections'));
    }

    public function testOnlyTheAssignedPhlebotomistCanRecordCollection(): void
    {
        $admin = $this->adminActor();
        [$x] = $this->createPhlebotomist('Phleb X', 'x@vitaliq.test');
        [, $yUser] = $this->createPhlebotomist('Phleb Y', 'y@vitaliq.test');
        $id = $this->testing()->submitGuest($this->guestInput(), 'p')['id'];
        $this->testing()->assign($id, $x, $admin);

        $this->expectException(WorkflowException::class);
        try {
            $this->testing()->recordCollection($id, ['specimen' => 'nasal', 'barcode' => 'ABCDEFGH'], ['id' => $yUser, 'role' => 'phlebotomist']);
        } finally {
            $this->assertSame('assigned', $this->requestStatus($id));
        }
    }

    public function testPatientsAndGuestsCannotPerformStaffActions(): void
    {
        $patientUser = $this->createPatient();
        [$phleb] = $this->createPhlebotomist();
        $id = $this->testing()->submitForUser($patientUser, $this->testInput(), 'z')['id'];
        foreach ([
            fn () => $this->testing()->assign($id, $phleb, ['id' => $patientUser, 'role' => 'patient']),
            fn () => $this->testing()->releaseResult($id, ['id' => $patientUser, 'role' => 'patient']),
            fn () => $this->testing()->reject($id, ['id' => null, 'role' => 'guest'], 'because I said so'),
        ] as $attempt) {
            try {
                $attempt();
                $this->fail('Unauthorised action accepted');
            } catch (WorkflowException) {
                $this->assertSame('submitted', $this->requestStatus($id));
            }
        }
    }

    public function testReassignmentKeepsExactlyOneCurrentAssignment(): void
    {
        $admin = $this->adminActor();
        [$a] = $this->createPhlebotomist('Phleb A');
        [$b] = $this->createPhlebotomist('Phleb B');
        $id = $this->testing()->submitGuest($this->guestInput(), 'r')['id'];
        $this->testing()->assign($id, $a, $admin);
        $this->testing()->assign($id, $b, $admin);
        $this->assertSame(1, (int) $this->db->value('SELECT COUNT(*) FROM test_assignments WHERE request_id = :id AND is_current = 1', ['id' => $id]));
        $this->assertSame($b, (int) $this->db->value('SELECT phlebotomist_id FROM test_assignments WHERE request_id = :id AND is_current = 1', ['id' => $id]));
        $this->assertNotNull($this->db->value('SELECT ended_at FROM test_assignments WHERE request_id = :id AND phlebotomist_id = :p', ['id' => $id, 'p' => $a]));
        $this->assertSame('assigned', $this->requestStatus($id));

        $this->expectException(WorkflowException::class);
        $this->testing()->assign($id, $b, $admin); // already assigned to B
    }

    public function testInactivePhlebotomistCannotBeAssigned(): void
    {
        $admin = $this->adminActor();
        [$a] = $this->createPhlebotomist('Phleb A');
        $this->db->execute('UPDATE phlebotomists SET is_active = 0 WHERE id = :id', ['id' => $a]);
        $id = $this->testing()->submitGuest($this->guestInput(), 'i')['id'];
        $this->expectException(WorkflowException::class);
        $this->testing()->assign($id, $a, $admin);
    }

    public function testPatientCanCancelOnlyOwnRequestBeforeCollection(): void
    {
        $owner = $this->createPatient('owner@example.com');
        $other = $this->createPatient('other@example.com');
        $admin = $this->adminActor();
        [$phleb] = $this->createPhlebotomist();
        $id = $this->testing()->submitForUser($owner, $this->testInput(), 'c')['id'];

        try {
            $this->testing()->cancel($id, ['id' => $other, 'role' => 'patient']);
            $this->fail('Another patient cancelled the request');
        } catch (WorkflowException) {
            $this->assertSame('submitted', $this->requestStatus($id));
        }

        $this->testing()->assign($id, $phleb, $admin);
        $this->testing()->recordCollection($id, ['specimen' => 'nasal', 'barcode' => 'ABCDEFGH1'], $admin);
        try {
            $this->testing()->cancel($id, ['id' => $owner, 'role' => 'patient']);
            $this->fail('Cancelled after collection');
        } catch (WorkflowException) {
            $this->assertSame('sample_collected', $this->requestStatus($id));
        }
    }

    public function testCancellationEndsTheCurrentAssignment(): void
    {
        $owner = $this->createPatient();
        $admin = $this->adminActor();
        [$phleb] = $this->createPhlebotomist();
        $id = $this->testing()->submitForUser($owner, $this->testInput(), 'c2')['id'];
        $this->testing()->assign($id, $phleb, $admin);
        $this->testing()->cancel($id, ['id' => $owner, 'role' => 'patient'], 'Feeling better');
        $this->assertSame('cancelled', $this->requestStatus($id));
        $this->assertSame('Feeling better', $this->db->value('SELECT closed_reason FROM test_requests WHERE id = :id', ['id' => $id]));
        $this->assertSame(0, (int) $this->db->value('SELECT COUNT(*) FROM test_assignments WHERE is_current = 1'));
    }

    public function testRejectRequiresAReason(): void
    {
        $id = $this->testing()->submitGuest($this->guestInput(), 'rr')['id'];
        $this->expectException(ValidationFailed::class);
        $this->testing()->reject($id, $this->adminActor(), ' no ');
    }

    public function testDuplicateBarcodeIsAFieldErrorNotACrash(): void
    {
        $admin = $this->adminActor();
        [$phleb] = $this->createPhlebotomist();
        $one = $this->testing()->submitGuest($this->guestInput(), 'b1')['id'];
        $two = $this->testing()->submitGuest($this->guestInput(['full_name' => 'Other Person', 'mobile' => '9123456789']), 'b2')['id'];
        foreach ([$one, $two] as $id) {
            $this->testing()->assign($id, $phleb, $admin);
        }
        $this->testing()->recordCollection($one, ['specimen' => 'nasal', 'barcode' => 'SAMEBARCODE1'], $admin);
        try {
            $this->testing()->recordCollection($two, ['specimen' => 'nasal', 'barcode' => 'samebarcode1'], $admin);
            $this->fail('Duplicate barcode accepted');
        } catch (ValidationFailed $e) {
            $this->assertArrayHasKey('barcode', $e->errors);
            $this->assertSame('assigned', $this->requestStatus($two), 'Transaction rolled back');
        }
    }

    public function testCtValueOnlyForRtPcr(): void
    {
        $admin = $this->adminActor();
        [$phleb] = $this->createPhlebotomist();
        $id = $this->testing()->submitGuest($this->guestInput(['test_type' => 'rapid_antigen']), 'ct')['id'];
        $this->testing()->assign($id, $phleb, $admin);
        $this->testing()->recordCollection($id, ['specimen' => 'nasal', 'barcode' => 'CTCTCTCT'], $admin);
        $this->testing()->startProcessing($id, $admin);
        $this->expectException(ValidationFailed::class);
        $this->testing()->recordResult($id, ['outcome' => 'positive', 'ct_value' => '22.5'], $admin);
    }

    public function testGuestTrackingNeedsAllThreeDetails(): void
    {
        $req = $this->testing()->submitGuest($this->guestInput(), 'tr');
        $this->assertSame($req['id'], $this->testing()->trackGuest($req['reference'], '9876543210', '1988-03-14'));
        $this->assertNull($this->testing()->trackGuest($req['reference'], '9876543210', '1988-03-15'));
        $this->assertNull($this->testing()->trackGuest($req['reference'], '9876543211', '1988-03-14'));
        $this->assertNull($this->testing()->trackGuest('VQ-261010-AAAAA', '9876543210', '1988-03-14'));
    }

    public function testDetailForPatientIsScopedToTheOwner(): void
    {
        $a = $this->createPatient('a@example.com');
        $b = $this->createPatient('b@example.com');
        $id = $this->testing()->submitForUser($a, $this->testInput(), 'own')['id'];
        $this->assertNotNull($this->testing()->detailForPatient($a, $id));
        $this->assertNull($this->testing()->detailForPatient($b, $id));
        $this->assertSame([], $this->testing()->forPatientUser($b));
    }

    public function testLinkingAGuestRequestRequiresMatchingMobileAndBirthDate(): void
    {
        $guest = $this->testing()->submitGuest($this->guestInput(['mobile' => '9988776655']), 'g');
        $userId = $this->createPatient('me@example.com', ['mobile' => '9988776655', 'date_of_birth' => '1988-03-14']);
        $stranger = $this->createPatient('stranger@example.com', ['date_of_birth' => '1988-03-14']);

        $this->assertNull($this->testing()->linkToAccount($stranger, $guest['reference'], '1988-03-14'), 'Different mobile cannot claim it');
        $this->assertNull($this->testing()->linkToAccount($userId, $guest['reference'], '1990-01-01'), 'Wrong birth date cannot claim it');
        $this->assertSame($guest['reference'], $this->testing()->linkToAccount($userId, $guest['reference'], '1988-03-14'));
        $this->assertNotNull($this->testing()->detailForPatient($userId, $guest['id']));
        $this->assertNull($this->testing()->linkToAccount($userId, $guest['reference'], '1988-03-14'), 'Cannot be linked twice');
    }
}
