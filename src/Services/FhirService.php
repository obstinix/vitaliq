<?php

declare(strict_types=1);

namespace Vitaliq\Services;

use Vitaliq\Core\App;

/**
 * Read-only FHIR R4 resources (feature 57): Patient, Observation (verified
 * lab results and released COVID-19 results) and DiagnosticReport
 * (released lab orders and released COVID-19 test reports). LOINC codes
 * are given only where the mapping is certain; every observation also
 * carries the Vitaliq test code. This is a conformance subset, not a
 * certified FHIR server.
 */
final class FhirService
{
    public const SYSTEM_LOCAL = 'https://vitaliq.example/fhir/CodeSystem/lab-test';
    public const LOINC = [
        'HB' => ['718-7', 'Hemoglobin [Mass/volume] in Blood'],
        'WBC' => ['6690-2', 'Leukocytes [#/volume] in Blood by Automated count'],
        'PLT' => ['777-3', 'Platelets [#/volume] in Blood by Automated count'],
        'GLUF' => ['1558-6', 'Fasting glucose [Mass/volume] in Serum or Plasma'],
        'CREA' => ['2160-0', 'Creatinine [Mass/volume] in Serum or Plasma'],
        'CRP' => ['1988-5', 'C reactive protein [Mass/volume] in Serum or Plasma'],
        'HBA1C' => ['4548-4', 'Hemoglobin A1c/Hemoglobin.total in Blood'],
        'COVPCR' => ['94500-6', 'SARS-CoV-2 (COVID-19) RNA [Presence] in Respiratory system specimen by NAA with probe detection'],
        'COVRAT' => ['94558-4', 'SARS-CoV-2 (COVID-19) Ag [Presence] in Respiratory system specimen by Rapid immunoassay'],
    ];
    private const INTERP = ['normal' => 'N', 'low' => 'L', 'high' => 'H', 'critical_low' => 'LL', 'critical_high' => 'HH', 'abnormal' => 'A'];

    public function __construct(private readonly App $app)
    {
    }

    private function base(): string
    {
        return $this->app->config['url'] . '/fhir/r4';
    }

    /** @return array<string,mixed> */
    public function capability(): array
    {
        return [
            'resourceType' => 'CapabilityStatement', 'status' => 'active', 'date' => $this->app->clock->now()->format('Y-m-d'),
            'kind' => 'instance', 'fhirVersion' => '4.0.1', 'format' => ['application/fhir+json'],
            'software' => ['name' => 'Vitaliq'],
            'rest' => [['mode' => 'server', 'security' => ['description' => 'Bearer API key with the fhir.read scope'], 'resource' => [
                ['type' => 'Patient', 'interaction' => [['code' => 'read']]],
                ['type' => 'Observation', 'interaction' => [['code' => 'read'], ['code' => 'search-type']], 'searchParam' => [['name' => 'patient', 'type' => 'reference']]],
                ['type' => 'DiagnosticReport', 'interaction' => [['code' => 'read'], ['code' => 'search-type']], 'searchParam' => [['name' => 'patient', 'type' => 'reference']]],
            ]]],
        ];
    }

    /** @return array<string,mixed>|null */
    public function patient(int $id): ?array
    {
        $p = $this->app->db->one('SELECT * FROM patients WHERE id = :id', ['id' => $id]);
        if ($p === null) {
            return null;
        }
        $parts = preg_split('/\s+/', trim((string) $p['full_name'])) ?: [];
        $family = count($parts) > 1 ? (string) array_pop($parts) : null;
        $out = ['resourceType' => 'Patient', 'id' => (string) $p['id'], 'meta' => ['lastUpdated' => date('c', (int) strtotime((string) $p['updated_at']))],
            'name' => [array_filter(['text' => $p['full_name'], 'family' => $family, 'given' => $parts])],
            'gender' => ['female' => 'female', 'male' => 'male', 'other' => 'other'][$p['sex']] ?? 'unknown',
            'birthDate' => $p['date_of_birth'],
            'telecom' => [['system' => 'phone', 'value' => '+91' . $p['mobile'], 'use' => 'mobile']]];
        if ($p['city'] !== null) {
            $out['address'] = [array_filter(['city' => $p['city'], 'state' => $p['state_code'], 'postalCode' => $p['pincode'], 'country' => 'IN'])];
        }
        return $out;
    }

    /**
     * @param array<string,mixed> $i
     * @return array<string,mixed>
     */
    private function observationFromItem(array $i): array
    {
        $coding = [['system' => self::SYSTEM_LOCAL, 'code' => $i['code'], 'display' => $i['name']]];
        if (isset(self::LOINC[$i['code']])) {
            array_unshift($coding, ['system' => 'http://loinc.org', 'code' => self::LOINC[$i['code']][0], 'display' => self::LOINC[$i['code']][1]]);
        }
        $o = ['resourceType' => 'Observation', 'id' => 'lab-' . $i['id'], 'status' => 'final',
            'category' => [['coding' => [['system' => 'http://terminology.hl7.org/CodeSystem/observation-category', 'code' => 'laboratory']]]],
            'code' => ['coding' => $coding, 'text' => $i['name']], 'subject' => ['reference' => 'Patient/' . $i['patient_id']],
            'effectiveDateTime' => date('c', (int) strtotime((string) $i['entered_at'])), 'issued' => date('c', (int) strtotime((string) $i['verified_at']))];
        if ($i['value_numeric'] !== null) {
            $o['valueQuantity'] = ['value' => (float) $i['value_numeric'], 'unit' => $i['unit']];
            if ($i['ref_low'] !== null || $i['ref_high'] !== null) {
                $o['referenceRange'] = [array_filter(['low' => $i['ref_low'] !== null ? ['value' => (float) $i['ref_low'], 'unit' => $i['unit']] : null,
                                                      'high' => $i['ref_high'] !== null ? ['value' => (float) $i['ref_high'], 'unit' => $i['unit']] : null])];
            }
        } else {
            $o['valueString'] = $i['value_text'];
        }
        if (isset(self::INTERP[$i['flag'] ?? ''])) {
            $o['interpretation'] = [['coding' => [['system' => 'http://terminology.hl7.org/CodeSystem/v3-ObservationInterpretation', 'code' => self::INTERP[$i['flag']]]]]];
        }
        return $o;
    }

    /** @return list<array<string,mixed>> */
    public function observations(int $patientId): array
    {
        $items = $this->app->db->all(
            "SELECT i.*, t.code, t.name, o.patient_id FROM lab_order_items i JOIN lab_tests t ON t.id = i.test_id JOIN lab_orders o ON o.id = i.order_id
              WHERE o.patient_id = :p AND o.status = 'released' AND i.status = 'verified' ORDER BY i.verified_at DESC LIMIT 200",
            ['p' => $patientId]
        );
        $out = array_map(fn ($i) => $this->observationFromItem($i), $items);
        foreach ($this->app->db->all(
            "SELECT r.id, r.test_type, res.outcome, res.released_at, res.recorded_at FROM test_requests r JOIN test_results res ON res.request_id = r.id
              WHERE r.patient_id = :p AND r.status = 'result_available' ORDER BY res.released_at DESC LIMIT 100", ['p' => $patientId]) as $r) {
            $code = $r['test_type'] === 'rt_pcr' ? 'COVPCR' : 'COVRAT';
            $out[] = ['resourceType' => 'Observation', 'id' => 'covid-' . $r['id'], 'status' => 'final',
                'category' => [['coding' => [['system' => 'http://terminology.hl7.org/CodeSystem/observation-category', 'code' => 'laboratory']]]],
                'code' => ['coding' => [['system' => 'http://loinc.org', 'code' => self::LOINC[$code][0], 'display' => self::LOINC[$code][1]]]],
                'subject' => ['reference' => 'Patient/' . $patientId], 'effectiveDateTime' => date('c', (int) strtotime((string) $r['recorded_at'])),
                'issued' => date('c', (int) strtotime((string) $r['released_at'])), 'valueString' => ucfirst((string) $r['outcome'])];
        }
        return $out;
    }

    /** @return array<string,mixed>|null */
    public function observation(string $id): ?array
    {
        if (preg_match('/^lab-(\d+)$/', $id, $m)) {
            $i = $this->app->db->one(
                "SELECT i.*, t.code, t.name, o.patient_id FROM lab_order_items i JOIN lab_tests t ON t.id = i.test_id JOIN lab_orders o ON o.id = i.order_id
                  WHERE i.id = :id AND o.status = 'released' AND i.status = 'verified'", ['id' => (int) $m[1]]);
            return $i === null ? null : $this->observationFromItem($i);
        }
        if (preg_match('/^covid-(\d+)$/', $id, $m)) {
            $pid = $this->app->db->value("SELECT patient_id FROM test_requests WHERE id = :id AND status = 'result_available'", ['id' => (int) $m[1]]);
            foreach ($pid === null ? [] : $this->observations((int) $pid) as $o) {
                if ($o['id'] === $id) {
                    return $o;
                }
            }
        }
        return null;
    }

    /** @return list<array<string,mixed>> */
    public function reports(int $patientId): array
    {
        $out = [];
        foreach ($this->app->db->all("SELECT id FROM lab_orders WHERE patient_id = :p AND status = 'released' ORDER BY released_at DESC LIMIT 100", ['p' => $patientId]) as $o) {
            $out[] = $this->report('lab-' . $o['id']);
        }
        return array_values(array_filter($out));
    }

    /** @return array<string,mixed>|null */
    public function report(string $id): ?array
    {
        if (!preg_match('/^lab-(\d+)$/', $id, $m)) {
            return null;
        }
        $o = $this->app->db->one("SELECT * FROM lab_orders WHERE id = :id AND status = 'released'", ['id' => (int) $m[1]]);
        if ($o === null) {
            return null;
        }
        $items = $this->app->db->all("SELECT id FROM lab_order_items WHERE order_id = :o AND status = 'verified' ORDER BY id", ['o' => $o['id']]);
        return ['resourceType' => 'DiagnosticReport', 'id' => $id, 'status' => 'final', 'identifier' => [['system' => 'https://vitaliq.example/order-no', 'value' => $o['order_no']]],
            'category' => [['coding' => [['system' => 'http://terminology.hl7.org/CodeSystem/v2-0074', 'code' => 'LAB']]]],
            'code' => ['text' => 'Laboratory report'], 'subject' => ['reference' => 'Patient/' . $o['patient_id']],
            'effectiveDateTime' => date('c', (int) strtotime((string) $o['created_at'])), 'issued' => date('c', (int) strtotime((string) $o['released_at'])),
            'result' => array_map(static fn ($i) => ['reference' => 'Observation/lab-' . $i['id']], $items)];
    }

    /**
     * @param list<array<string,mixed>> $resources
     * @return array<string,mixed>
     */
    public function bundle(array $resources, string $self): array
    {
        return ['resourceType' => 'Bundle', 'type' => 'searchset', 'total' => count($resources),
            'link' => [['relation' => 'self', 'url' => $this->base() . $self]],
            'entry' => array_map(fn ($r) => ['fullUrl' => $this->base() . '/' . $r['resourceType'] . '/' . $r['id'], 'resource' => $r], $resources)];
    }

    /** @return array<string,mixed> */
    public static function outcome(string $code, string $text): array
    {
        return ['resourceType' => 'OperationOutcome', 'issue' => [['severity' => 'error', 'code' => $code, 'diagnostics' => $text]]];
    }
}
