<?php

declare(strict_types=1);

namespace Vitaliq\Support;

/**
 * Minimum patched PHP release per supported branch.
 *
 * The floors are the October 2026 releases that fix CVE-2026-109618
 * (GHSA-w8hw-w9fx-j79w, RCE via OOM corruption) and CVE-2026-109619
 * (GHSA-6wmf-4rv5-p3rp, RCE via stale global state). Branches older than
 * 8.2 receive no fix and are refused outright.
 */
final class RuntimeCheck
{
    public const PATCHED = [
        '8.2' => '8.2.35',
        '8.3' => '8.3.36',
        '8.4' => '8.4.27',
        '8.5' => '8.5.12',
    ];

    /** @return array{ok:bool,message:string} */
    public static function php(string $version = PHP_VERSION): array
    {
        if (!preg_match('/^(\d+)\.(\d+)\.(\d+)/', $version, $m)) {
            return ['ok' => false, 'message' => "Unrecognised PHP version '{$version}'."];
        }
        $branch = $m[1] . '.' . $m[2];
        $plain = $m[1] . '.' . $m[2] . '.' . $m[3];
        if (!isset(self::PATCHED[$branch])) {
            return version_compare($plain, '8.2.0', '<')
                ? ['ok' => false, 'message' => "PHP {$plain} is unsupported and unpatched for CVE-2026-109618/109619. Upgrade to 8.4.27 or another patched release."]
                : ['ok' => true, 'message' => "PHP {$plain} is newer than the branches tracked here; confirm it includes the October 2026 security fixes."];
        }
        $floor = self::PATCHED[$branch];
        if (version_compare($plain, $floor, '<')) {
            return ['ok' => false, 'message' => "PHP {$plain} is vulnerable to CVE-2026-109618/109619. Upgrade to {$floor} or later on the {$branch} branch."];
        }
        return ['ok' => true, 'message' => "PHP {$plain} includes the fixes for CVE-2026-109618/109619 (requires >= {$floor})."];
    }
}
