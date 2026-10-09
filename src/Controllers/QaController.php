<?php

declare(strict_types=1);

namespace Vitaliq\Controllers;

use Vitaliq\Core\Request;
use Vitaliq\Core\Response;
use Vitaliq\Services\DefectService;
use Vitaliq\Services\QaService;
use Vitaliq\Services\ValidationFailed;
use Vitaliq\Services\WorkflowException;
use Vitaliq\Validation\Rules;

/**
 * STQA laboratory (QA analysts and administrators).
 */
final class QaController extends Controller
{
    public function overview(Request $request): Response
    {
        return $this->view('qa/overview', [
            'title' => 'STQA laboratory',
            'summary' => $this->app->service(QaService::class)->summary(),
            'scripts' => ['charts'],
        ], 200, 'app');
    }

    /** @param array<string,mixed>|null $result */
    private function renderValidator(string $rule, string $value, ?array $result = null, array $errors = [], int $status = 200): Response
    {
        $catalogue = $this->app->service(QaService::class)->catalogue(['kind' => 'regex'], 1, 500)['rows'];
        $byRule = [];
        foreach ($catalogue as $case) {
            $byRule[$case['rule_key']][] = $case;
        }
        return $this->view('qa/validator', [
            'title' => 'Validation rule tester',
            'rules' => Rules::all(),
            'rule' => Rules::exists($rule) ? $rule : 'full_name',
            'value' => $value,
            'result' => $result,
            'casesByRule' => $byRule,
            'errors' => $errors,
            'scripts' => ['qa-lab'],
        ], $status, 'app');
    }

    public function validator(Request $request): Response
    {
        return $this->renderValidator($request->query('rule', 'full_name'), '');
    }

    /** No-JavaScript fallback: evaluates on the server and re-renders. */
    public function validate(Request $request): Response
    {
        $rule = $request->input('rule');
        $value = (string) ($request->body['value'] ?? '');
        try {
            $result = $this->app->service(QaService::class)->evaluate($rule, $value);
        } catch (ValidationFailed $e) {
            return $this->renderValidator($rule, '', null, $e->errors, 422);
        } catch (WorkflowException) {
            return $this->renderValidator('full_name', '', null, ['rule' => 'Choose a rule from the list.'], 422);
        }
        return $this->renderValidator($rule, $value, $result);
    }

    public function evaluateJson(Request $request): Response
    {
        try {
            $result = $this->app->service(QaService::class)->evaluate(
                (string) ($request->body['rule'] ?? ''),
                is_string($request->body['value'] ?? null) ? $request->body['value'] : ''
            );
        } catch (ValidationFailed $e) {
            return Response::json(['error' => $e->errors['value'] ?? 'Invalid input.'], 422);
        } catch (WorkflowException $e) {
            return Response::json(['error' => $e->getMessage()], 422);
        }
        return Response::json($result);
    }

    public function cases(Request $request): Response
    {
        $filters = [
            'module' => mb_substr($request->query('module'), 0, 40),
            'kind' => $request->query('kind'),
            'type' => $request->query('type'),
            'status' => $request->query('status'),
            'q' => mb_substr($request->query('q'), 0, 80),
        ];
        $qa = $this->app->service(QaService::class);
        return $this->view('qa/cases', [
            'title' => 'Test case catalogue',
            'filters' => $filters,
            'list' => $qa->catalogue($filters, $this->page($request)),
            'modules' => $qa->modules(),
            'page' => $this->page($request),
        ], 200, 'app');
    }

    /**
     * @param array<string,string> $errors
     * @param array<string,mixed> $old
     */
    private function caseFormView(array $errors = [], array $old = [], int $status = 200): Response
    {
        return $this->view('qa/case-form', [
            'title' => 'New test case',
            'rules' => Rules::all(),
            'modules' => $this->app->service(QaService::class)->modules(),
            'errors' => $errors,
            'old' => $old + ['kind' => 'regex'],
        ], $status, 'app');
    }

    public function caseForm(Request $request): Response
    {
        return $this->caseFormView();
    }

    public function createCase(Request $request): Response
    {
        try {
            $id = $this->app->service(QaService::class)->createCase($request->body, $this->userId());
        } catch (ValidationFailed $e) {
            return $this->caseFormView($e->errors, $request->body, 422);
        }
        $this->flash('success', 'Test case added to the catalogue. It has not been executed yet.');
        return $this->redirect('/qa/cases/' . $id);
    }

    /**
     * @param array<string,string> $errors
     * @param array<string,mixed> $old
     */
    private function caseView(int $id, array $errors = [], array $old = [], int $status = 200): Response
    {
        $case = $this->app->service(QaService::class)->findCase($id);
        if ($case === null) {
            $this->notFound();
        }
        return $this->view('qa/case-show', [
            'title' => $case['case_code'],
            'case' => $case,
            'defects' => $this->app->service(DefectService::class)->options(),
            'errors' => $errors,
            'old' => $old,
        ], $status, 'app');
    }

    public function showCase(Request $request, string $id): Response
    {
        return $this->caseView((int) $id);
    }

    public function recordManual(Request $request, string $id): Response
    {
        try {
            $this->app->service(QaService::class)->recordManual((int) $id, $request->body, $this->userId());
        } catch (ValidationFailed $e) {
            return $this->caseView((int) $id, $e->errors, $request->body, 422);
        } catch (WorkflowException) {
            $this->notFound();
        }
        $this->flash('success', 'Execution recorded.');
        return $this->redirect('/qa/cases/' . (int) $id);
    }

    public function linkDefect(Request $request, string $id): Response
    {
        $defect = (int) $request->input('defect_id');
        try {
            $this->app->service(QaService::class)->linkDefect((int) $id, $defect > 0 ? $defect : null);
            $this->flash('success', $defect > 0 ? 'Defect linked.' : 'Defect link removed.');
        } catch (WorkflowException $e) {
            $this->flash('error', $e->getMessage());
        }
        return $this->redirect('/qa/cases/' . (int) $id);
    }

    public function runs(Request $request): Response
    {
        return $this->view('qa/runs', [
            'title' => 'Execution history',
            'list' => $this->app->service(QaService::class)->runs($this->page($request)),
            'page' => $this->page($request),
        ], 200, 'app');
    }

    public function startRun(Request $request): Response
    {
        if (!$this->app->rateLimiter()->hit('qa-run:' . $this->userId(), 10, 60)) {
            $this->flash('error', 'Please wait a moment before starting another run.');
            return $this->redirect('/qa/runs');
        }
        $runId = $this->app->service(QaService::class)->runRegexSuite($this->userId(), 'web');
        $this->flash('success', 'Regex suite executed.');
        return $this->redirect('/qa/runs/' . $runId);
    }

    public function showRun(Request $request, string $id): Response
    {
        $run = $this->app->service(QaService::class)->run((int) $id, $request->query('status'));
        if ($run === null) {
            $this->notFound();
        }
        return $this->view('qa/run-show', [
            'title' => 'Run #' . (int) $id,
            'run' => $run,
            'status' => $request->query('status'),
        ], 200, 'app');
    }

    public function defects(Request $request): Response
    {
        $filters = ['status' => $request->query('status'), 'severity' => $request->query('severity'), 'origin' => $request->query('origin')];
        return $this->view('qa/defects', [
            'title' => 'Defect register',
            'filters' => $filters,
            'list' => $this->app->service(DefectService::class)->list($filters),
        ], 200, 'app');
    }

    public function defectForm(Request $request): Response
    {
        return $this->view('qa/defect-form', ['title' => 'Report a defect'], 200, 'app');
    }

    public function createDefect(Request $request): Response
    {
        try {
            $id = $this->app->service(DefectService::class)->create($request->body, $this->userId());
        } catch (ValidationFailed $e) {
            return $this->view('qa/defect-form', ['title' => 'Report a defect', 'errors' => $e->errors, 'old' => $request->body], 422, 'app');
        }
        $this->flash('success', 'Defect recorded.');
        return $this->redirect('/qa/defects/' . $id);
    }

    /**
     * @param array<string,string> $errors
     */
    private function defectView(int $id, array $errors = [], int $status = 200): Response
    {
        $defect = $this->app->service(DefectService::class)->find($id);
        if ($defect === null) {
            $this->notFound();
        }
        return $this->view('qa/defect-show', ['title' => $defect['code'], 'defect' => $defect, 'errors' => $errors], $status, 'app');
    }

    public function showDefect(Request $request, string $id): Response
    {
        return $this->defectView((int) $id);
    }

    public function updateDefect(Request $request, string $id): Response
    {
        try {
            $this->app->service(DefectService::class)->updateStatus((int) $id, $request->body);
        } catch (ValidationFailed $e) {
            return $this->defectView((int) $id, $e->errors, 422);
        } catch (WorkflowException) {
            $this->notFound();
        }
        $this->flash('success', 'Defect updated.');
        return $this->redirect('/qa/defects/' . (int) $id);
    }
}
