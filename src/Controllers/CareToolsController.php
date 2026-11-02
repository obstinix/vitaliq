<?php

declare(strict_types=1);

namespace Vitaliq\Controllers;

use Vitaliq\Core\Request;
use Vitaliq\Core\Response;
use Vitaliq\Services\AssistantService;
use Vitaliq\Services\ClinicalReferenceService;
use Vitaliq\Services\ValidationFailed;

/** Patient tools: assistant (51), medicine checker (52) and symptom questionnaire (54). */
final class CareToolsController extends Controller
{
    /** @param array<string,string> $errors */
    public function assistant(Request $request, array $errors = [], int $status = 200): Response
    {
        $svc = $this->app->service(AssistantService::class);
        $provider = $svc->provider();
        return $this->view('patient/assistant', [
            'title' => t('Ask the assistant'),
            'history' => $svc->history($this->userId()),
            'providerName' => $provider?->name(),
            'errors' => $errors,
        ], $status, 'app');
    }

    public function ask(Request $request): Response
    {
        try {
            $this->app->service(AssistantService::class)->ask($this->userId(), $request->input('question'));
        } catch (ValidationFailed $e) {
            return $this->assistant($request, $e->errors, 422);
        }
        return $this->redirect('/patient/assistant#latest');
    }

    public function clearAssistant(Request $request): Response
    {
        $this->app->service(AssistantService::class)->clear($this->userId());
        $this->flash('success', 'Conversation deleted.');
        return $this->redirect('/patient/assistant');
    }

    public function medicines(Request $request): Response
    {
        $result = null;
        $errors = [];
        $names = $request->query('m') === '' ? [] : array_slice(explode("\n", str_replace(',', "\n", $request->query('m'))), 0, 10);
        if ($names !== []) {
            try {
                $result = $this->app->service(ClinicalReferenceService::class)->interactions($names);
            } catch (ValidationFailed $e) {
                $errors = $e->errors;
            }
        }
        return $this->view('patient/medicines', ['title' => t('Medicine checker'), 'm' => $request->query('m'), 'result' => $result, 'errors' => $errors], $errors === [] ? 200 : 422, 'app');
    }

    public function triageForm(Request $request): Response
    {
        return $this->view('patient/triage', [
            'title' => t('Check symptoms'), 'result' => null,
            'flags' => ClinicalReferenceService::RED_FLAGS, 'symptoms' => ClinicalReferenceService::SYMPTOMS, 'risks' => ClinicalReferenceService::RISKS, 'old' => [],
        ], 200, 'app');
    }

    public function triage(Request $request): Response
    {
        $result = ClinicalReferenceService::triage($request->body);
        $this->app->service(ClinicalReferenceService::class)->saveTriage($this->userId(), $request->body, $result['outcome']);
        return $this->view('patient/triage', [
            'title' => t('Check symptoms'), 'result' => $result,
            'flags' => ClinicalReferenceService::RED_FLAGS, 'symptoms' => ClinicalReferenceService::SYMPTOMS, 'risks' => ClinicalReferenceService::RISKS, 'old' => $request->body,
        ], 200, 'app');
    }
}
