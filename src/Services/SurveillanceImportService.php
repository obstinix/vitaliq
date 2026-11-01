<?php

declare(strict_types=1);

namespace Vitaliq\Services;

use DateTimeImmutable;
use finfo;
use RuntimeException;
use Vitaliq\Core\App;

/**
 * CSV and JSON importer for aggregate surveillance counts (feature 28).
 * Two steps: preview (parse, validate every row, stage the valid rows and
 * keep the first 200 errors) and commit (upsert the staged rows in one
 * transaction). A committed import can be rolled back; rows another import
 * has since replaced are left alone.
 *
 * Columns: disease, date (YYYY-MM-DD), state (code or name), tests,
 * positives; optional sex (female, male), age_band (0-17, 18-44, 45-64,
 * 65+) and facility (facility code).
 */
final class SurveillanceImportService
{
    public const MAX_BYTES = 5 * 1024 * 1024;
    public const MAX_ROWS = 50000;
    private const REQUIRED = ['disease', 'date', 'state', 'tests', 'positives'];

    private string $dir;

    public function __construct(private readonly App $app)
    {
        $this->dir = VQ_ROOT . '/storage/imports';
    }

    /**
     * @param array{name?:string,tmp_name?:string,size?:int,error?:int}|null $file
     * @return int import id
     */
    public function preview(?array $file, string $sourceName, int $actorId): int
    {
        $sourceName = trim($sourceName);
        if (mb_strlen($sourceName) < 3 || mb_strlen($sourceName) > 120) {
            throw new ValidationFailed(['source_name' => 'Name the source of the data (3 to 120 characters), for example "State IDSP weekly return".']);
        }
        $code = $file === null ? UPLOAD_ERR_NO_FILE : (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE);
        if ($code !== UPLOAD_ERR_OK || !is_file((string) ($file['tmp_name'] ?? ''))) {
            throw new ValidationFailed(['file' => $code === UPLOAD_ERR_INI_SIZE || $code === UPLOAD_ERR_FORM_SIZE ? 'The file is larger than 5 MB.' : 'Choose a CSV or JSON file.']);
        }
        $bytes = (string) file_get_contents((string) $file['tmp_name']);
        if (strlen($bytes) > self::MAX_BYTES) {
            throw new ValidationFailed(['file' => 'The file is larger than 5 MB.']);
        }
        $name = mb_substr(basename(str_replace('\\', '/', (string) ($file['name'] ?? 'upload'))), 0, 160);
        $mime = (new finfo(FILEINFO_MIME_TYPE))->buffer($bytes) ?: '';
        $trimmed = ltrim($bytes, "\xEF\xBB\xBF \t\r\n");
        $format = str_starts_with($trimmed, '[') || str_starts_with($trimmed, '{') ? 'json' : 'csv';
        if (!str_starts_with($mime, 'text/') && $mime !== 'application/json' && $mime !== 'application/csv') {
            throw new ValidationFailed(['file' => 'Only CSV or JSON text files can be imported.']);
        }
        $rawRows = $format === 'json' ? $this->parseJson($trimmed) : $this->parseCsv($bytes);
        if (count($rawRows) > self::MAX_ROWS) {
            throw new ValidationFailed(['file' => 'A file can hold up to ' . number_format(self::MAX_ROWS) . ' rows.']);
        }

        $valid = [];
        $errors = [];
        $seen = [];
        $states = $this->stateLookup();
        $facilities = array_column($this->app->db->all('SELECT id, code, state_code FROM facilities'), null, 'code');
        foreach ($rawRows as $i => $row) {
            $line = $i + ($format === 'csv' ? 2 : 1);
            [$clean, $problem] = $this->validateRow($row, $states, $facilities);
            if ($problem !== null) {
                if (count($errors) < 200) {
                    $errors[] = ['row' => $line, 'error' => $problem];
                }
                continue;
            }
            $key = implode('|', [$clean['disease'], $clean['period_start'], $clean['state_code'], $clean['facility_key'], $clean['sex'], $clean['age_band']]);
            if (isset($seen[$key])) {
                if (count($errors) < 200) {
                    $errors[] = ['row' => $line, 'error' => 'Repeats row ' . $seen[$key] . ' (same disease, date, state, facility, sex and age band).'];
                }
                continue;
            }
            $seen[$key] = $line;
            $valid[] = $clean;
        }
        $failed = count($rawRows) - count($valid);
        $id = $this->app->db->insert(
            'INSERT INTO surveillance_imports (filename, format, sha256, source_name, rows_total, rows_ok, rows_failed, errors_json, uploaded_by_user_id, created_at)
             VALUES (:f, :fmt, :h, :s, :t, :ok, :bad, :e, :u, :at)',
            ['f' => $name, 'fmt' => $format, 'h' => hash('sha256', $bytes), 's' => $sourceName, 't' => count($rawRows), 'ok' => count($valid),
             'bad' => $failed, 'e' => $errors === [] ? null : json_encode($errors, JSON_UNESCAPED_UNICODE), 'u' => $actorId, 'at' => $this->app->clock->nowSql()]
        );
        if (!is_dir($this->dir) && !mkdir($this->dir, 0700, true) && !is_dir($this->dir)) {
            throw new RuntimeException('Import staging directory cannot be created.');
        }
        file_put_contents($this->dir . '/import-' . $id . '.json', json_encode($valid, JSON_THROW_ON_ERROR), LOCK_EX);
        $this->app->service(AuditLog::class)->record('surveillance.import_previewed', 'surveillance_import', $id, ['rows' => count($rawRows), 'failed' => $failed], $actorId);
        return $id;
    }

    /** @return array{replaced:int,added:int} */
    public function commit(int $importId, int $actorId): array
    {
        return $this->app->db->transaction(function ($db) use ($importId, $actorId): array {
            $imp = $db->one('SELECT * FROM surveillance_imports WHERE id = :id FOR UPDATE', ['id' => $importId]);
            if ($imp === null || $imp['status'] !== 'previewed') {
                throw new WorkflowException('Only a previewed import can be committed.');
            }
            if ((int) $imp['rows_ok'] === 0) {
                throw new WorkflowException('There are no valid rows to import.');
            }
            $path = $this->dir . '/import-' . $importId . '.json';
            $rows = is_file($path) ? json_decode((string) file_get_contents($path), true, 512, JSON_THROW_ON_ERROR) : null;
            if (!is_array($rows)) {
                throw new WorkflowException('The staged rows are missing; upload the file again.');
            }
            $added = $replaced = 0;
            foreach ($rows as $r) {
                $n = $db->execute(
                    'INSERT INTO surveillance_records (import_id, disease, period_start, state_code, facility_key, facility_id, sex, age_band, tests, positives)
                     VALUES (:i, :d, :p, :s, :fk, :f, :sex, :age, :t, :pos)
                     ON DUPLICATE KEY UPDATE import_id = VALUES(import_id), tests = VALUES(tests), positives = VALUES(positives)',
                    ['i' => $importId, 'd' => $r['disease'], 'p' => $r['period_start'], 's' => $r['state_code'], 'fk' => $r['facility_key'],
                     'f' => $r['facility_key'] > 0 ? $r['facility_key'] : null, 'sex' => $r['sex'], 'age' => $r['age_band'], 't' => $r['tests'], 'pos' => $r['positives']]
                );
                // MariaDB reports 1 for an insert and 2 for an update that changed the row.
                $n === 1 ? $added++ : $replaced++;
            }
            $db->execute("UPDATE surveillance_imports SET status = 'committed', committed_at = :t WHERE id = :id", ['t' => $this->app->clock->nowSql(), 'id' => $importId]);
            @unlink($path);
            $this->app->service(AuditLog::class)->record('surveillance.import_committed', 'surveillance_import', $importId, ['added' => $added, 'replaced' => $replaced], $actorId);
            return ['replaced' => $replaced, 'added' => $added];
        });
    }

    public function discard(int $importId, int $actorId): void
    {
        $n = $this->app->db->execute("UPDATE surveillance_imports SET status = 'discarded' WHERE id = :id AND status = 'previewed'", ['id' => $importId]);
        if ($n !== 1) {
            throw new WorkflowException('Only a previewed import can be discarded.');
        }
        @unlink($this->dir . '/import-' . $importId . '.json');
        $this->app->service(AuditLog::class)->record('surveillance.import_discarded', 'surveillance_import', $importId, [], $actorId);
    }

    public function rollback(int $importId, int $actorId): int
    {
        return $this->app->db->transaction(function ($db) use ($importId, $actorId): int {
            $imp = $db->one('SELECT status FROM surveillance_imports WHERE id = :id FOR UPDATE', ['id' => $importId]);
            if ($imp === null || $imp['status'] !== 'committed') {
                throw new WorkflowException('Only a committed import can be rolled back.');
            }
            $n = $db->execute('DELETE FROM surveillance_records WHERE import_id = :id', ['id' => $importId]);
            $db->execute("UPDATE surveillance_imports SET status = 'rolled_back' WHERE id = :id", ['id' => $importId]);
            $this->app->service(AuditLog::class)->record('surveillance.import_rolled_back', 'surveillance_import', $importId, ['rows' => $n], $actorId);
            return $n;
        });
    }

    /** @return list<array<string,mixed>> */
    public function imports(): array
    {
        return $this->app->db->all(
            'SELECT i.*, u.full_name, (SELECT COUNT(*) FROM surveillance_records r WHERE r.import_id = i.id) AS live_rows
               FROM surveillance_imports i LEFT JOIN users u ON u.id = i.uploaded_by_user_id ORDER BY i.id DESC LIMIT 100'
        );
    }

    /** @return array<string,mixed>|null */
    public function find(int $id): ?array
    {
        $imp = $this->app->db->one('SELECT i.*, u.full_name FROM surveillance_imports i LEFT JOIN users u ON u.id = i.uploaded_by_user_id WHERE i.id = :id', ['id' => $id]);
        if ($imp !== null) {
            $imp['errors'] = $imp['errors_json'] ? json_decode((string) $imp['errors_json'], true) : [];
            $path = $this->dir . '/import-' . $id . '.json';
            $imp['sample'] = $imp['status'] === 'previewed' && is_file($path) ? array_slice((array) json_decode((string) file_get_contents($path), true), 0, 10) : [];
        }
        return $imp;
    }

    /** @return list<array<string,string>> */
    private function parseCsv(string $bytes): array
    {
        $bytes = preg_replace('/^\xEF\xBB\xBF/', '', $bytes) ?? $bytes;
        $handle = fopen('php://temp', 'r+');
        if ($handle === false) {
            throw new RuntimeException('Cannot read upload.');
        }
        fwrite($handle, $bytes);
        rewind($handle);
        $header = fgetcsv($handle, 0, ',', '"', '');
        if ($header === false || $header === [null]) {
            throw new ValidationFailed(['file' => 'The CSV file is empty.']);
        }
        $header = array_map(static fn ($h) => strtolower(trim((string) $h)), $header);
        $missing = array_diff(self::REQUIRED, $header);
        if ($missing !== []) {
            throw new ValidationFailed(['file' => 'The CSV header is missing: ' . implode(', ', $missing) . '.']);
        }
        $rows = [];
        while (($line = fgetcsv($handle, 0, ',', '"', '')) !== false) {
            if ($line === [null]) {
                continue;
            }
            $rows[] = array_combine($header, array_pad(array_slice($line, 0, count($header)), count($header), ''));
            if (count($rows) > self::MAX_ROWS) {
                break;
            }
        }
        fclose($handle);
        return $rows;
    }

    /** @return list<array<string,mixed>> */
    private function parseJson(string $text): array
    {
        try {
            $data = json_decode($text, true, 8, JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            throw new ValidationFailed(['file' => 'The JSON file is not valid: ' . $e->getMessage() . '.']);
        }
        $records = is_array($data) && array_is_list($data) ? $data : ($data['records'] ?? null);
        if (!is_array($records) || !array_is_list($records)) {
            throw new ValidationFailed(['file' => 'The JSON must be a list of records, or an object with a "records" list.']);
        }
        return array_map(static fn ($r) => is_array($r) ? array_change_key_case($r, CASE_LOWER) : [], $records);
    }

    /** @return array<string,string> lower-case code or name => code */
    private function stateLookup(): array
    {
        $map = [];
        foreach ($this->app->db->all('SELECT code, name FROM states') as $s) {
            $map[strtolower((string) $s['code'])] = (string) $s['code'];
            $map[strtolower((string) $s['name'])] = (string) $s['code'];
        }
        return $map;
    }

    /**
     * @param array<string,mixed> $row
     * @param array<string,string> $states
     * @param array<string,array<string,mixed>> $facilities
     * @return array{0:array<string,mixed>,1:?string}
     */
    private function validateRow(array $row, array $states, array $facilities): array
    {
        $get = static fn (string $k): string => is_scalar($row[$k] ?? null) ? trim((string) $row[$k]) : '';
        $disease = strtolower($get('disease'));
        if (!isset(SurveillanceService::DISEASES[$disease])) {
            return [[], 'Unknown disease "' . mb_substr($get('disease'), 0, 30) . '". Use one of: ' . implode(', ', array_keys(SurveillanceService::DISEASES)) . '.'];
        }
        $date = $get('date');
        $d = DateTimeImmutable::createFromFormat('!Y-m-d', $date);
        if ($d === false || $d->format('Y-m-d') !== $date) {
            return [[], 'Date must be YYYY-MM-DD.'];
        }
        if ($date < '2000-01-01' || $date > $this->app->clock->now()->format('Y-m-d')) {
            return [[], 'Date must be between 2000-01-01 and today.'];
        }
        $state = $states[strtolower($get('state'))] ?? null;
        if ($state === null) {
            return [[], 'Unknown state "' . mb_substr($get('state'), 0, 40) . '". Use the two-letter code or the full name.'];
        }
        foreach (['tests', 'positives'] as $k) {
            if (!preg_match('/^\d{1,7}$/', $get($k))) {
                return [[], ucfirst($k) . ' must be a whole number from 0 to 9,999,999.'];
            }
        }
        $tests = (int) $get('tests');
        $positives = (int) $get('positives');
        if ($positives > $tests) {
            return [[], 'Positives (' . $positives . ') cannot exceed tests (' . $tests . ').'];
        }
        $sex = strtolower($get('sex')) ?: 'any';
        if (!in_array($sex, ['any', 'female', 'male'], true)) {
            return [[], 'Sex must be female, male or blank.'];
        }
        $age = $get('age_band') ?: 'all';
        if (!in_array($age, ['all', '0-17', '18-44', '45-64', '65+'], true)) {
            return [[], 'Age band must be 0-17, 18-44, 45-64, 65+ or blank.'];
        }
        $facilityKey = 0;
        if ($get('facility') !== '') {
            $f = $facilities[strtoupper($get('facility'))] ?? null;
            if ($f === null) {
                return [[], 'Unknown facility code "' . mb_substr($get('facility'), 0, 20) . '".'];
            }
            if ($f['state_code'] !== $state) {
                return [[], 'Facility ' . $f['code'] . ' is not in that state.'];
            }
            $facilityKey = (int) $f['id'];
        }
        return [['disease' => $disease, 'period_start' => $date, 'state_code' => $state, 'facility_key' => $facilityKey,
                 'sex' => $sex, 'age_band' => $age, 'tests' => $tests, 'positives' => $positives], null];
    }
}
