<?php

declare(strict_types=1);

/*
 * Print, as JSON, the client rule export plus every catalogue input with the
 * PHP format-level verdict (Rules::check). Used by tests/js/rules.test.mjs to
 * prove the browser and server engines agree.
 *
 *   php bin/export-rules.php
 */

require dirname(__DIR__) . '/src/bootstrap.php';

use Vitaliq\Validation\Rules;

if (PHP_SAPI !== 'cli') {
    exit(1);
}

$cases = json_decode((string) file_get_contents(VQ_ROOT . '/database/qa/regex-cases.json'), true, 512, JSON_THROW_ON_ERROR);
$inputs = [];
foreach ($cases as $c) {
    $inputs[] = ['code' => $c['code'], 'rule' => $c['rule'], 'input' => $c['input']];
}
foreach (Rules::all() as $key => $rule) {
    foreach (array_merge($rule['valid'], $rule['invalid']) as $i => $value) {
        $inputs[] = ['code' => "{$key}-example-{$i}", 'rule' => $key, 'input' => $value];
    }
}
foreach ($inputs as &$item) {
    $normalised = Rules::normalise($item['rule'], $item['input']);
    $item['php_accepts'] = $item['rule'] === 'required'
        ? Rules::matches('required', $normalised)
        : Rules::check($item['rule'], $normalised) === null;
    // filter_var() is an extra server-only check for email; record it so the
    // test can tell a regex disagreement from that deliberate extra step.
    $length = mb_strlen($normalised);
    $item['server_only_extra'] = $item['rule'] === 'email'
        && $length >= Rules::get('email')['min'] && $length <= Rules::get('email')['max']
        && Rules::matches('email', $normalised)
        && filter_var($normalised, FILTER_VALIDATE_EMAIL) === false;
}
unset($item);

echo json_encode(['rules' => Rules::forClient(), 'inputs' => $inputs], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
