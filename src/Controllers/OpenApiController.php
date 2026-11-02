<?php

declare(strict_types=1);

namespace Vitaliq\Controllers;

use Vitaliq\Core\Request;
use Vitaliq\Core\Response;
use Vitaliq\Services\ApiKeyService;
use Vitaliq\Services\FhirService;
use Vitaliq\Services\ReportVerificationService;
use Vitaliq\Services\SurveillanceService;
use Vitaliq\Support\Stats;

/**
 * Public versioned API (feature 60) and FHIR R4 read endpoints (feature 57).
 * Every call needs a bearer API key with the right scope, except the FHIR
 * capability statement and the API index. Errors are JSON with a status.
 */
final class OpenApiController extends Controller
{
    private function guard(Request $request, string $scope, bool $fhir = false): ?Response
    {
        $auth = $this->app->service(ApiKeyService::class)->authorise($request->header('authorization'), $scope);
        if ($auth['ok']) {
            return null;
        }
        $body = $fhir ? FhirService::outcome($auth['status'] === 403 ? 'forbidden' : ($auth['status'] === 429 ? 'throttled' : 'security'), (string) $auth['error'])
            : ['error' => ['status' => $auth['status'], 'message' => $auth['error']]];
        $r = $fhir ? $this->fhirJson($body, $auth['status']) : Response::json($body, $auth['status']);
        return $auth['status'] === 401 ? $r->withHeader('WWW-Authenticate', 'Bearer') : $r;
    }

    /** @param array<string,mixed> $data */
    private function fhirJson(array $data, int $status = 200): Response
    {
        return new Response((string) json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), $status, ['Content-Type' => 'application/fhir+json; charset=UTF-8', 'Cache-Control' => 'no-store']);
    }

    public function index(Request $request): Response
    {
        return Response::json(['name' => 'Vitaliq API', 'version' => 'v1', 'documentation' => $this->app->config['url'] . '/developers', 'endpoints' => [
            ['path' => '/api/v1/surveillance/summary', 'scope' => 'surveillance.read'],
            ['path' => '/api/v1/facilities', 'scope' => 'facilities.read'],
            ['path' => '/api/v1/reports/verify/{code}', 'scope' => 'reports.verify'],
            ['path' => '/fhir/r4/metadata', 'scope' => null],
            ['path' => '/fhir/r4/Patient/{id}', 'scope' => 'fhir.read'],
            ['path' => '/fhir/r4/Observation?patient={id}', 'scope' => 'fhir.read'],
            ['path' => '/fhir/r4/DiagnosticReport?patient={id}', 'scope' => 'fhir.read'],
        ]]);
    }

    public function surveillance(Request $request): Response
    {
        if (($deny = $this->guard($request, 'surveillance.read')) !== null) {
            return $deny;
        }
        $svc = $this->app->service(SurveillanceService::class);
        $disease = $request->query('disease', 'covid19');
        $days = (int) $request->query('days', '28');
        $state = strtoupper($request->query('state'));
        if (!isset(SurveillanceService::DISEASES[$disease]) || !in_array($days, [7, 14, 28, 90, 365], true) || ($state !== '' && !isset($svc->stateNames()[$state]))) {
            return Response::json(['error' => ['status' => 400, 'message' => 'Use disease=' . implode('|', array_keys(SurveillanceService::DISEASES)) . ', days=7|14|28|90|365 and an optional two-letter state.']], 400);
        }
        $w = $svc->window($days);
        $row = $svc->counts($disease, $w['from'], $w['to'], [], $state)[0] ?? ['tests' => 0, 'positives' => 0];
        $ci = Stats::wilson($row['positives'], $row['tests']);
        $byState = array_map(static fn ($r) => ['state' => $r['state_code'], 'tests' => $r['tests'], 'positives' => $r['positives']], $svc->counts($disease, $w['from'], $w['to'], ['state_code'], $state));
        return Response::json(['disease' => $disease, 'from' => $w['from'], 'to' => $w['to'], 'state' => $state ?: null,
            'tests' => $row['tests'], 'positives' => $row['positives'],
            'positivity' => $ci['p'] === null ? null : ['estimate' => round($ci['p'], 4), 'ci95' => [round((float) $ci['low'], 4), round((float) $ci['high'], 4)]],
            'by_state' => $byState, 'daily' => $svc->daily($disease, $w['from'], $w['to'], $state)]);
    }

    public function facilities(Request $request): Response
    {
        if (($deny = $this->guard($request, 'facilities.read')) !== null) {
            return $deny;
        }
        return Response::json(['data' => $this->app->db->all('SELECT code, name, kind, city, state_code AS state FROM facilities WHERE is_active = 1 ORDER BY name')]);
    }

    public function verify(Request $request, string $code): Response
    {
        if (($deny = $this->guard($request, 'reports.verify')) !== null) {
            return $deny;
        }
        $r = $this->app->service(ReportVerificationService::class)->check($code);
        return $r === null ? Response::json(['valid' => false, 'reason' => 'Unknown code.'], 404) : Response::json($r);
    }

    /* ---------------------------------------------------------------- FHIR */

    public function metadata(Request $request): Response
    {
        return $this->fhirJson($this->app->service(FhirService::class)->capability());
    }

    public function patient(Request $request, string $id): Response
    {
        if (($deny = $this->guard($request, 'fhir.read', true)) !== null) {
            return $deny;
        }
        $p = $this->app->service(FhirService::class)->patient((int) $id);
        $this->app->service(\Vitaliq\Services\AuditLog::class)->record('fhir.read', 'patient', (int) $id, ['resource' => 'Patient'], null);
        return $p === null ? $this->fhirJson(FhirService::outcome('not-found', 'Patient not found.'), 404) : $this->fhirJson($p);
    }

    public function observations(Request $request): Response
    {
        if (($deny = $this->guard($request, 'fhir.read', true)) !== null) {
            return $deny;
        }
        $pid = (int) preg_replace('/^Patient\//', '', $request->query('patient'));
        if ($pid <= 0) {
            return $this->fhirJson(FhirService::outcome('required', 'Search by patient, e.g. ?patient=12.'), 400);
        }
        $svc = $this->app->service(FhirService::class);
        $this->app->service(\Vitaliq\Services\AuditLog::class)->record('fhir.read', 'patient', $pid, ['resource' => 'Observation'], null);
        return $this->fhirJson($svc->bundle($svc->observations($pid), '/Observation?patient=' . $pid));
    }

    public function observation(Request $request, string $fid): Response
    {
        if (($deny = $this->guard($request, 'fhir.read', true)) !== null) {
            return $deny;
        }
        $o = $this->app->service(FhirService::class)->observation($fid);
        return $o === null ? $this->fhirJson(FhirService::outcome('not-found', 'Observation not found.'), 404) : $this->fhirJson($o);
    }

    public function reports(Request $request): Response
    {
        if (($deny = $this->guard($request, 'fhir.read', true)) !== null) {
            return $deny;
        }
        $pid = (int) preg_replace('/^Patient\//', '', $request->query('patient'));
        if ($pid <= 0) {
            return $this->fhirJson(FhirService::outcome('required', 'Search by patient, e.g. ?patient=12.'), 400);
        }
        $svc = $this->app->service(FhirService::class);
        $this->app->service(\Vitaliq\Services\AuditLog::class)->record('fhir.read', 'patient', $pid, ['resource' => 'DiagnosticReport'], null);
        return $this->fhirJson($svc->bundle($svc->reports($pid), '/DiagnosticReport?patient=' . $pid));
    }

    public function report(Request $request, string $fid): Response
    {
        if (($deny = $this->guard($request, 'fhir.read', true)) !== null) {
            return $deny;
        }
        $r = $this->app->service(FhirService::class)->report($fid);
        return $r === null ? $this->fhirJson(FhirService::outcome('not-found', 'DiagnosticReport not found.'), 404) : $this->fhirJson($r);
    }
}
