<?php

declare(strict_types=1);

namespace Vitaliq\Tests\Functional;

use PHPUnit\Framework\Attributes\DataProvider;
use Vitaliq\Services\CommentService;
use Vitaliq\Tests\HttpTestCase;

final class PublicSiteTest extends HttpTestCase
{
    /** @return iterable<string,array{string}> */
    public static function publicPages(): iterable
    {
        foreach (['/', '/covid-19', '/symptoms', '/prevention', '/testing', '/faq', '/announcements', '/statistics',
            '/contact', '/privacy', '/community', '/test/new', '/track', '/login', '/register', '/forgot-password'] as $p) {
            yield $p => [$p];
        }
    }

    #[DataProvider('publicPages')]
    public function testPublicPageRendersWithSecurityHeaders(string $path): void
    {
        $response = $this->get($path);
        $this->assertSame(200, $response->status);
        $this->assertStringContainsString('<main id="main"', $response->body);
        $csp = (string) $response->header('Content-Security-Policy');
        $this->assertStringContainsString("script-src 'self'", $csp);
        $this->assertStringContainsString("frame-ancestors 'none'", $csp);
        $this->assertSame('nosniff', $response->header('X-Content-Type-Options'));
        $this->assertSame('DENY', $response->header('X-Frame-Options'));
        $this->assertNotNull($response->header('Referrer-Policy'));
        $this->assertStringNotContainsString(' style="', $response->body, 'Inline style attributes would be blocked by the CSP');
        // Every <script> must load a file or be an inert JSON data block.
        $this->assertSame(0, preg_match('/<script(?![^>]*(?:\bsrc=|type="application\/json"))[^>]*>/i', $response->body), 'No inline scripts');
    }

    public function testInformationPagesCiteSources(): void
    {
        foreach (['/covid-19', '/symptoms', '/prevention', '/testing'] as $path) {
            $body = $this->get($path)->body;
            $this->assertStringContainsString('Sources', $body, $path);
            $this->assertMatchesRegularExpression('#https://www\.(who\.int|cdc\.gov)/#', $body, $path);
        }
    }

    public function testStatisticsAreLabelledAndComputedFromData(): void
    {
        $body = $this->get('/statistics')->body;
        $this->assertStringContainsString('Not official statistics', $body);
        $this->assertStringContainsString('CC BY 4.0', $body);
        $this->assertStringContainsString('No requests yet', $body, 'Empty database shows an empty state, not invented numbers');
    }

    public function testUnknownPathIs404AndDotfilesAreNotRoutable(): void
    {
        $this->assertSame(404, $this->get('/does-not-exist')->status);
        $this->assertSame(404, $this->get('/.env')->status);
        $this->assertSame(404, $this->get('/../.env')->status);
        $this->assertSame(405, $this->get('/logout')->status);
    }

    public function testCommentHtmlIsEscapedWhenPublished(): void
    {
        $this->actingAs($this->createAdmin());
        $comments = $this->app->service(CommentService::class);
        $stamp = $comments->formStamp();
        $this->clock->advance('+5 seconds');
        $comments->submit([
            'author_name' => 'Ravi Kumar', 'topic' => 'general', 'stamp' => $stamp,
            'body' => '<script>alert("xss")</script><img src=x onerror=alert(1)> is this safe?',
        ], null);
        $comments->moderate((int) $this->db->value('SELECT id FROM comments'), 'approve', null);

        $this->newBrowser();
        $body = $this->get('/community')->body;
        $this->assertStringContainsString('&lt;script&gt;alert(&quot;xss&quot;)&lt;/script&gt;', $body);
        $this->assertStringNotContainsString('<script>alert("xss")', $body);
        $this->assertStringNotContainsString('<img src=x', $body);
    }

    public function testCommentFormEndToEnd(): void
    {
        $page = $this->get('/community');
        $stamp = $this->hiddenField($page, 'stamp');
        $this->clock->advance('+8 seconds');
        $response = $this->post('/community', [
            'author_name' => 'Divya Nair', 'email' => '', 'topic' => 'website', 'website' => '',
            'body' => 'Please add a Malayalam version of the guidance.', 'stamp' => $stamp,
        ]);
        $this->assertRedirectsTo('/community', $response);
        $this->assertSame('pending', $this->db->value('SELECT status FROM comments'));
        $this->assertStringContainsString('will appear once a moderator approves it', $this->get('/community')->body);
    }
}
