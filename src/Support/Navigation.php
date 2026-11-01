<?php

declare(strict_types=1);

namespace Vitaliq\Support;

use Vitaliq\Core\Router;

/**
 * Role-aware sidebar. Each role has groups of links; a link is shown only
 * when a GET route exists for it and the user holds its permission, so the
 * navigation never points at a page the user cannot open.
 */
final class Navigation
{
    /** Outline icons, 24-unit viewBox, drawn at 16px with a 1.5 stroke. */
    public const ICONS = [
        'home' => '<path d="M3 10.5 12 3l9 7.5"/><path d="M5 9v11h14V9"/>',
        'list' => '<path d="M8 6h13M8 12h13M8 18h13"/><path d="M3 6h.01M3 12h.01M3 18h.01"/>',
        'plus' => '<path d="M12 5v14M5 12h14"/>',
        'calendar' => '<rect x="3" y="5" width="18" height="16" rx="2"/><path d="M3 10h18M8 3v4M16 3v4"/>',
        'file' => '<path d="M14 3H6v18h12V7z"/><path d="M14 3v4h4"/>',
        'id' => '<rect x="3" y="5" width="18" height="14" rx="2"/><circle cx="9" cy="11" r="2"/><path d="M6 16c.6-1.4 1.7-2 3-2s2.4.6 3 2M15 10h3M15 13h3"/>',
        'users' => '<circle cx="9" cy="8" r="3.5"/><path d="M2.5 20c.8-3.4 3.4-5 6.5-5s5.7 1.6 6.5 5"/><path d="M16 4.5a3.5 3.5 0 0 1 0 7M18.5 15c1.6.6 2.6 2.2 3 5"/>',
        'beaker' => '<path d="M9 3h6M10 3v6L4.5 19a1.5 1.5 0 0 0 1.3 2h12.4a1.5 1.5 0 0 0 1.3-2L14 9V3"/><path d="M7 15h10"/>',
        'box' => '<path d="M3 7.5 12 3l9 4.5v9L12 21l-9-4.5z"/><path d="M3 7.5 12 12l9-4.5M12 12v9"/>',
        'chart' => '<path d="M4 20V4M4 20h16"/><path d="m7 15 4-4 3 3 5-6"/>',
        'map' => '<path d="m3 6 6-3 6 3 6-3v15l-6 3-6-3-6 3z"/><path d="M9 3v15M15 6v15"/>',
        'bell' => '<path d="M6 8a6 6 0 1 1 12 0c0 7 3 9 3 9H3s3-2 3-9"/><path d="M10.3 21a1.94 1.94 0 0 0 3.4 0"/>',
        'settings' => '<circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.6 1.6 0 0 0 .3 1.8l.1.1a2 2 0 1 1-2.8 2.8l-.1-.1a1.6 1.6 0 0 0-2.7 1.1V21a2 2 0 1 1-4 0v-.1a1.6 1.6 0 0 0-2.7-1.1l-.1.1a2 2 0 1 1-2.8-2.8l.1-.1A1.6 1.6 0 0 0 3.6 15H3a2 2 0 1 1 0-4h.1a1.6 1.6 0 0 0 1.1-2.7l-.1-.1a2 2 0 1 1 2.8-2.8l.1.1A1.6 1.6 0 0 0 9.7 4.4V3a2 2 0 1 1 4 0v.1a1.6 1.6 0 0 0 2.7 1.1l.1-.1a2 2 0 1 1 2.8 2.8l-.1.1a1.6 1.6 0 0 0 1.1 2.7H21a2 2 0 1 1 0 4h-.1a1.6 1.6 0 0 0-1.5 1.3z"/>',
        'activity' => '<path d="M3 12h4l3-8 4 16 3-8h4"/>',
        'heart' => '<path d="M12 20s-8-4.6-8-10.2A4.3 4.3 0 0 1 12 7a4.3 4.3 0 0 1 8 2.8C20 15.4 12 20 12 20z"/>',
        'lock' => '<rect x="4" y="10" width="16" height="11" rx="2"/><path d="M8 10V7a4 4 0 0 1 8 0v3"/>',
        'shield' => '<path d="M12 3 4 6v6c0 5 3.5 8 8 9 4.5-1 8-4 8-9V6z"/>',
        'alert' => '<path d="M12 3 2 20h20z"/><path d="M12 10v4M12 17h.01"/>',
        'upload' => '<path d="M12 16V4M7 9l5-5 5 5"/><path d="M4 16v4h16v-4"/>',
        'message' => '<path d="M4 5h16v11H8l-4 4z"/>',
        'clock' => '<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/>',
        'search' => '<circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/>',
        'globe' => '<circle cx="12" cy="12" r="9"/><path d="M3 12h18M12 3c2.5 2.5 3.5 5.5 3.5 9S14.5 18.5 12 21c-2.5-2.5-3.5-5.5-3.5-9S9.5 5.5 12 3z"/>',
        'scan' => '<path d="M4 8V4h4M16 4h4v4M20 16v4h-4M8 20H4v-4"/><path d="M7 12h10"/>',
        'database' => '<ellipse cx="12" cy="5" rx="8" ry="3"/><path d="M4 5v14c0 1.7 3.6 3 8 3s8-1.3 8-3V5"/><path d="M4 12c0 1.7 3.6 3 8 3s8-1.3 8-3"/>',
        'code' => '<path d="m8 8-5 4 5 4M16 8l5 4-5 4M14 4l-4 16"/>',
        'pill' => '<rect x="3" y="8" width="18" height="8" rx="4" transform="rotate(-45 12 12)"/><path d="m8.5 8.5 7 7"/>',
        'video' => '<rect x="3" y="6" width="13" height="12" rx="2"/><path d="m16 10 5-3v10l-5-3"/>',
        'sparkle' => '<path d="M12 3v4M12 17v4M3 12h4M17 12h4M6 6l2.5 2.5M15.5 15.5 18 18M6 18l2.5-2.5M15.5 8.5 18 6"/>',
    ];

    /**
     * Groups per role: heading => list of [path, label, icon, permission|null].
     *
     * @var array<string,array<string,list<array{0:string,1:string,2:string,3?:string}>>>
     */
    private const MENUS = [
        'patient' => [
            'Your care' => [
                ['/patient', 'Overview', 'home'],
                ['/patient/appointments', 'Appointments', 'calendar'],
                ['/patient/requests', 'Tests and results', 'list'],
                ['/patient/timeline', 'Health timeline', 'activity'],
                ['/patient/triage', 'Check symptoms', 'heart'],
                ['/patient/assistant', 'Ask the assistant', 'sparkle'],
            ],
            'Your records' => [
                ['/patient/passport', 'Health passport', 'id'],
                ['/patient/family', 'Family profiles', 'users'],
                ['/patient/documents', 'Document vault', 'lock'],
                ['/patient/contacts', 'Emergency contacts', 'shield'],
                ['/patient/medicines', 'Medicine checker', 'pill'],
                ['/patient/link', 'Link a request', 'plus'],
                ['/patient/feedback', 'Feedback', 'message'],
            ],
        ],
        'phlebotomist' => [
            'Field work' => [
                ['/staff', 'Your collections', 'list'],
                ['/staff/schedule', 'Your shifts', 'clock'],
            ],
        ],
        'lab' => [
            'Bench' => [
                ['/lab', 'Overview', 'home'],
                ['/lab/samples', 'Samples', 'beaker', 'lab.samples'],
                ['/lab/scan', 'Scan a barcode', 'scan', 'lab.samples'],
                ['/lab/orders', 'Test orders', 'list', 'lab.orders'],
                ['/lab/verify', 'Awaiting verification', 'shield', 'lab.verify'],
            ],
            'Lab resources' => [
                ['/lab/inventory', 'Inventory', 'box', 'lab.inventory'],
                ['/lab/equipment', 'Equipment', 'settings', 'lab.equipment'],
                ['/lab/catalogue', 'Test catalogue', 'file', 'lab.orders'],
                ['/lab/performance', 'Workload and turnaround', 'chart', 'lab.analytics'],
                ['/staff/schedule', 'Your shifts', 'clock'],
            ],
        ],
        'officer' => [
            'Surveillance' => [
                ['/surveillance', 'Overview', 'home', 'surveillance.view'],
                ['/surveillance/map', 'Map by state', 'map', 'surveillance.view'],
                ['/surveillance/trends', 'Trends and seasons', 'chart', 'surveillance.view'],
                ['/surveillance/demographics', 'Demographics', 'users', 'surveillance.view'],
                ['/surveillance/facilities', 'Facility comparison', 'activity', 'surveillance.view'],
                ['/surveillance/alerts', 'Outbreak alerts', 'alert', 'surveillance.alerts'],
            ],
            'Data' => [
                ['/surveillance/imports', 'Imports', 'upload', 'surveillance.import'],
                ['/surveillance/quality', 'Data quality', 'shield', 'surveillance.view'],
                ['/surveillance/reports', 'Reports', 'file', 'surveillance.view'],
            ],
        ],
        'admin' => [
            'Operations' => [
                ['/admin', 'Overview', 'home'],
                ['/admin/requests', 'Requests', 'list'],
                ['/admin/appointments', 'Appointments', 'calendar'],
                ['/admin/reports', 'Reports', 'file'],
                ['/admin/phlebotomists', 'Phlebotomists', 'users'],
                ['/admin/schedule', 'Staff schedule', 'clock', 'admin.schedule'],
                ['/lab', 'Laboratory', 'beaker'],
                ['/surveillance', 'Surveillance', 'map'],
            ],
            'Community' => [
                ['/admin/comments', 'Comments', 'message'],
                ['/admin/announcements', 'Announcements', 'globe'],
                ['/admin/broadcasts', 'Broadcasts', 'bell', 'admin.broadcast'],
                ['/admin/feedback', 'Patient feedback', 'heart'],
            ],
            'Governance' => [
                ['/admin/users', 'Users', 'users'],
                ['/admin/roles', 'Roles and permissions', 'lock', 'admin.rbac'],
                ['/admin/facilities', 'Facilities', 'map', 'admin.facilities'],
                ['/admin/audit', 'Audit log', 'activity'],
                ['/admin/audit/analytics', 'Audit analytics', 'chart'],
                ['/admin/alert-rules', 'Alert rules', 'alert', 'admin.alerts'],
                ['/admin/api-keys', 'API keys', 'code', 'admin.api'],
                ['/admin/system', 'System health', 'activity', 'admin.system'],
                ['/admin/backups', 'Backups', 'database', 'admin.backups'],
                ['/admin/retention', 'Data retention', 'clock', 'admin.retention'],
                ['/qa', 'STQA lab', 'shield'],
            ],
        ],
        'qa' => [
            'STQA lab' => [
                ['/qa', 'Overview', 'home'],
                ['/qa/validator', 'Rule tester', 'scan'],
                ['/qa/cases', 'Test cases', 'list'],
                ['/qa/runs', 'Executions', 'activity'],
                ['/qa/defects', 'Defects', 'alert'],
            ],
        ],
    ];

    private const ACCOUNT = [
        ['/account', 'Profile', 'id'],
        ['/account/security', 'Security', 'lock'],
        ['/account/preferences', 'Preferences', 'settings'],
        ['/notifications', 'Notifications', 'bell'],
    ];

    /**
     * @param callable(string):bool|null $can permission check; null allows every link
     * @return array<string,list<array{href:string,label:string,icon:string}>>
     */
    public static function for(string $role, Router $router, ?callable $can = null, string $path = '/'): array
    {
        $menus = self::MENUS[$role] ?? [];
        if ($role === 'admin' && str_starts_with($path, '/qa')) {
            $menus = self::MENUS['qa'] + ['Operations' => [['/admin', 'Back to operations', 'home']]];
        }
        $menus['Account'] = self::ACCOUNT;

        $routes = [];
        foreach ($router->list() as $r) {
            if ($r['method'] === 'GET') {
                $routes[$r['pattern']] = true;
            }
        }

        $out = [];
        foreach ($menus as $heading => $links) {
            foreach ($links as $link) {
                if (!isset($routes[$link[0]])) {
                    continue;
                }
                if (isset($link[3]) && $can !== null && !$can($link[3])) {
                    continue;
                }
                $out[$heading][] = ['href' => $link[0], 'label' => $link[1], 'icon' => $link[2]];
            }
        }
        return $out;
    }

    /**
     * The longest matching prefix wins, so /patient/requests/12 highlights
     * "Tests and results".
     *
     * @param array<string,list<array{href:string,label:string,icon:string}>> $sections
     */
    public static function active(array $sections, string $path): string
    {
        $active = '';
        foreach ($sections as $links) {
            foreach ($links as $link) {
                $href = $link['href'];
                if (($path === $href || str_starts_with($path, $href . '/')) && strlen($href) > strlen($active)) {
                    $active = $href;
                }
            }
        }
        return $active;
    }

    public static function icon(string $name): string
    {
        $paths = self::ICONS[$name] ?? self::ICONS['list'];
        return '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' . $paths . '</svg>';
    }
}
