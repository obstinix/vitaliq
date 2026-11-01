<?php

declare(strict_types=1);

namespace Vitaliq\Controllers;

use Vitaliq\Core\Request;
use Vitaliq\Core\Response;
use Vitaliq\Services\HealthIdService;

/**
 * Public emergency card behind a QR health ID (feature 6). Rate-limited per
 * connection so tokens cannot be guessed at speed; never indexed or cached.
 */
final class HealthCardController extends Controller
{
    public function show(Request $request, string $token): Response
    {
        if (!$this->app->rateLimiter()->hit('healthcard:' . $this->app->ipHash(), 30, 600)) {
            return $this->view('public/health-card', ['title' => 'Health ID', 'card' => null, 'limited' => true], 429)
                ->withHeader('X-Robots-Tag', 'noindex, nofollow');
        }
        $card = $this->app->service(HealthIdService::class)->resolve($token);
        return $this->view('public/health-card', ['title' => 'Health ID', 'card' => $card, 'limited' => false], $card === null ? 404 : 200)
            ->withHeader('X-Robots-Tag', 'noindex, nofollow')
            ->withHeader('Referrer-Policy', 'no-referrer');
    }
}
