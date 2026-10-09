<?php

declare(strict_types=1);

namespace Vitaliq\Controllers;

use Vitaliq\Core\Request;
use Vitaliq\Core\Response;
use Vitaliq\Services\TestingService;
use Vitaliq\Services\ValidationFailed;
use Vitaliq\Services\WorkflowException;

/**
 * Phlebotomist portal: only collections currently assigned to the
 * signed-in phlebotomist are visible or actionable.
 */
final class StaffController extends Controller
{
    public function tasks(Request $request): Response
    {
        $testing = $this->app->service(TestingService::class);
        return $this->view('staff/tasks', [
            'title' => 'Your collections',
            'open' => $testing->tasksForPhlebotomistUser($this->userId(), true),
            'done' => array_slice($testing->tasksForPhlebotomistUser($this->userId(), false), 0, 20),
        ], 200, 'app');
    }

    /** @return array<string,mixed> */
    private function ownedTask(int $requestId): array
    {
        $detail = $this->app->service(TestingService::class)->detail($requestId);
        $owner = $detail !== null && $detail['assignment'] !== null
            ? $this->app->db->value('SELECT user_id FROM phlebotomists WHERE id = :id', ['id' => (int) $detail['assignment']['phlebotomist_id']])
            : null;
        if ($detail === null || $owner === null || (int) $owner !== $this->userId()) {
            $this->notFound();
        }
        return $detail;
    }

    public function task(Request $request, string $id): Response
    {
        return $this->view('staff/task', ['title' => 'Collection', 'request' => $this->ownedTask((int) $id)], 200, 'app');
    }

    public function collect(Request $request, string $id): Response
    {
        $detail = $this->ownedTask((int) $id);
        try {
            $this->app->service(TestingService::class)->recordCollection((int) $id, $request->body, $this->actor());
        } catch (ValidationFailed $e) {
            return $this->view('staff/task', [
                'title' => 'Collection', 'request' => $detail, 'errors' => $e->errors, 'old' => $request->body,
            ], 422, 'app');
        } catch (WorkflowException $e) {
            $this->flash('error', $e->getMessage());
            return $this->redirect('/staff/tasks/' . (int) $id);
        }
        $this->flash('success', 'Sample collection recorded for ' . $detail['reference'] . '.');
        return $this->redirect('/staff');
    }
}
