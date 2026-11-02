<?php

declare(strict_types=1);

namespace Vitaliq\Services;

use Throwable;
use Vitaliq\Core\App;
use Vitaliq\Support\RuntimeCheck;

/**
 * System health checks (feature 44). Each check returns ok, warn or fail
 * with a measured value and a one-line explanation.
 */
final class SystemHealthService
{
    public function __construct(private readonly App $app)
    {
    }

    /** @return list<array{name:string,state:string,value:string,detail:string}> */
    public function checks(): array
    {
        $out = [];
        $t = microtime(true);
        try {
            $this->app->db->value('SELECT 1');
            $ms = (microtime(true) - $t) * 1000;
            $version = (string) $this->app->db->value('SELECT VERSION()');
            $out[] = ['name' => 'Database', 'state' => $ms < 100 ? 'ok' : 'warn', 'value' => number_format($ms, 1) . ' ms', 'detail' => $version];
        } catch (Throwable $e) {
            return [['name' => 'Database', 'state' => 'fail', 'value' => 'unreachable', 'detail' => 'The database did not answer.']];
        }

        $files = array_map('basename', glob(VQ_ROOT . '/database/migrations/*.sql') ?: []);
        $applied = array_column($this->app->db->all('SELECT filename FROM schema_migrations'), 'filename');
        $pending = array_values(array_diff($files, $applied));
        $out[] = ['name' => 'Migrations', 'state' => $pending === [] ? 'ok' : 'fail', 'value' => count($applied) . ' of ' . count($files) . ' applied',
                  'detail' => $pending === [] ? 'Schema is current.' : 'Pending: ' . implode(', ', $pending)];

        $runtime = RuntimeCheck::php(PHP_VERSION);
        $out[] = ['name' => 'PHP', 'state' => $runtime['ok'] ? 'ok' : 'fail', 'value' => PHP_VERSION, 'detail' => $runtime['message']];

        $missing = array_values(array_filter(['pdo_mysql', 'mbstring', 'openssl', 'sodium', 'fileinfo', 'zlib', 'curl'], static fn ($x) => !extension_loaded($x)));
        $out[] = ['name' => 'Extensions', 'state' => $missing === [] ? 'ok' : (in_array('curl', $missing, true) && count($missing) === 1 ? 'warn' : 'fail'),
                  'value' => $missing === [] ? 'All present' : 'Missing ' . implode(', ', $missing), 'detail' => 'pdo_mysql, mbstring, openssl, sodium, fileinfo, zlib; curl for outside providers'];

        $free = @disk_free_space(VQ_ROOT . '/storage');
        $total = @disk_total_space(VQ_ROOT . '/storage');
        $pct = $free !== false && $total ? $free / $total * 100 : null;
        $out[] = ['name' => 'Disk', 'state' => $pct === null ? 'warn' : ($pct < 5 ? 'fail' : ($pct < 15 ? 'warn' : 'ok')),
                  'value' => $free === false ? 'unknown' : number_format($free / 1073741824, 1) . ' GB free', 'detail' => $pct === null ? '' : number_format($pct, 0) . '% of the volume is free'];

        $unwritable = array_values(array_filter(['logs', 'mail', 'vault', 'backups', 'imports', 'messages', 'cache'], static fn ($d) => !is_writable(VQ_ROOT . '/storage/' . $d)));
        $out[] = ['name' => 'Storage folders', 'state' => $unwritable === [] ? 'ok' : 'fail', 'value' => $unwritable === [] ? 'Writable' : 'Not writable: ' . implode(', ', $unwritable), 'detail' => 'storage/'];

        $q = $this->app->service(DeliveryService::class)->queueSummary();
        $stale = (int) $this->app->db->value("SELECT COUNT(*) FROM notification_deliveries WHERE status = 'queued' AND next_attempt_at < :t", ['t' => $this->app->clock->now()->modify('-15 minutes')->format('Y-m-d H:i:s')]);
        $out[] = ['name' => 'Notification queue', 'state' => $stale > 0 ? 'warn' : ($q['failed'] > 0 ? 'warn' : 'ok'), 'value' => $q['queued'] . ' queued, ' . $q['failed'] . ' failed',
                  'detail' => $stale > 0 ? $stale . ' deliveries have waited over 15 minutes: is bin/notify-worker.php scheduled?' : 'Worked by bin/notify-worker.php'];

        $last = $this->app->service(BackupService::class)->lastBackupAt();
        $age = $last === null ? null : ($this->app->clock->now()->getTimestamp() - (int) strtotime($last)) / 3600;
        $out[] = ['name' => 'Backups', 'state' => $age === null ? 'warn' : ($age > 48 ? 'warn' : 'ok'), 'value' => $last === null ? 'None yet' : number_format($age, 0) . ' h ago', 'detail' => 'Daily backups recommended (bin/backup.php)'];

        $logFile = VQ_ROOT . '/storage/logs/app-' . $this->app->clock->now()->format('Y-m-d') . '.log';
        $errors = is_file($logFile) ? substr_count((string) file_get_contents($logFile, false, null, 0, 5_000_000), '] ERROR: ') : 0;
        $out[] = ['name' => 'Errors today', 'state' => $errors > 20 ? 'warn' : 'ok', 'value' => (string) $errors, 'detail' => 'Logged exceptions in storage/logs'];

        $key = (string) $this->app->config['key'];
        $out[] = ['name' => 'Configuration', 'state' => $this->app->isProduction() && $this->app->config['debug'] ? 'fail' : 'ok',
                  'value' => (string) $this->app->config['env'], 'detail' => ($this->app->config['debug'] ? 'Debug on' : 'Debug off') . '; APP_KEY ' . (strlen($key) >= 32 ? 'set' : 'missing')];
        return $out;
    }

    public function overall(): string
    {
        $states = array_column($this->checks(), 'state');
        return in_array('fail', $states, true) ? 'fail' : (in_array('warn', $states, true) ? 'warn' : 'ok');
    }
}
