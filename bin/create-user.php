<?php

declare(strict_types=1);

/*
 * Create a staff account and print a one-time set-password link.
 * Use this to create the first administrator on a fresh install.
 *
 *   php bin/create-user.php --role=admin --name="Asha Varghese" --email=asha@example.org
 *
 * Roles: admin, qa. (Phlebotomist logins are created from Admin → Users so
 * they can be linked to a phlebotomist record.) No password is printed or
 * stored; the link expires in 48 hours and works once.
 */

require dirname(__DIR__) . '/src/bootstrap.php';

use Vitaliq\Auth\PasswordTokens;
use Vitaliq\Core\App;
use Vitaliq\Core\Config;
use Vitaliq\Core\Database;
use Vitaliq\Services\PatientService;
use Vitaliq\Validation\Validator;

if (PHP_SAPI !== 'cli') {
    exit(1);
}

$opts = getopt('', ['role:', 'name:', 'email:']);
$v = new Validator([
    'role' => $opts['role'] ?? '',
    'full_name' => $opts['name'] ?? '',
    'email' => $opts['email'] ?? '',
]);
$role = $v->choice('role', ['admin' => 'Administrator', 'qa' => 'QA analyst'], 'Role');
$name = $v->rule('full_name', 'full_name');
$email = $v->rule('email', 'email');
if ($v->fails()) {
    foreach ($v->errors() as $field => $message) {
        fwrite(STDERR, "{$field}: {$message}\n");
    }
    fwrite(STDERR, "Usage: php bin/create-user.php --role=admin|qa --name=\"Full Name\" --email=you@example.org\n");
    exit(1);
}

$config = Config::fromEnv();
$app = new App($config, new Database($config['db']));
if ($app->service(PatientService::class)->emailTaken((string) $email)) {
    fwrite(STDERR, "An account with that email already exists.\n");
    exit(1);
}
$id = $app->db->insert(
    'INSERT INTO users (role, full_name, email, password_hash) VALUES (:r, :n, :e, NULL)',
    ['r' => $role, 'n' => $name, 'e' => $email]
);
$token = $app->service(PasswordTokens::class)->issue($id, 'invite');
fwrite(STDOUT, "Created {$role} account #{$id} for {$email}.\n");
fwrite(STDOUT, "Set-password link (valid 48 hours, single use):\n  " . rtrim((string) $config['url'], '/') . '/reset-password/' . $token . "\n");
