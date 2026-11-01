<?php

declare(strict_types=1);

/*
 * Notification worker (features 31-36). Sends appointment reminders for the
 * next 24 hours, then works the outside-delivery queue (email, SMS,
 * WhatsApp) with retries. Run every few minutes, for example:
 *
 *   * /5 * * * *  php /path/to/vitaliq/bin/notify-worker.php
 */

require dirname(__DIR__) . '/src/bootstrap.php';

use Vitaliq\Core\App;
use Vitaliq\Core\Config;
use Vitaliq\Core\Database;
use Vitaliq\Services\AppointmentService;
use Vitaliq\Services\DeliveryService;

if (PHP_SAPI !== 'cli') {
    exit(1);
}
$config = Config::fromEnv();
$app = new App($config, new Database($config['db']));
$reminders = $app->service(AppointmentService::class)->sendReminders(24);
$stats = $app->service(DeliveryService::class)->process(200);
fwrite(STDOUT, sprintf(
    "Reminders: %d. Deliveries: %d sent, %d written locally, %d to retry, %d failed.\n",
    $reminders, $stats['sent'], $stats['simulated'], $stats['retry'], $stats['failed']
));
