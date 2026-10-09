<?php

declare(strict_types=1);

namespace Vitaliq\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Vitaliq\Auth\Passwords;
use Vitaliq\Core\Csrf;
use Vitaliq\Core\Request;
use Vitaliq\Core\Session;
use Vitaliq\Services\ReportService;

final class SecurityPrimitivesTest extends TestCase
{
    public function testPasswordsUseArgon2idAndVerify(): void
    {
        $hash = Passwords::hash('correct horse 42');
        $this->assertStringStartsWith('$argon2id$', $hash);
        $this->assertTrue(Passwords::verify('correct horse 42', $hash));
        $this->assertFalse(Passwords::verify('correct horse 43', $hash));
        $this->assertNotSame($hash, Passwords::hash('correct horse 42'), 'Hashes must be salted');
    }

    public function testPasswordPolicy(): void
    {
        $this->assertNull(Passwords::policyError('correct horse 42', 'kavya@example.com'));
        $this->assertNotNull(Passwords::policyError('short1'));
        $this->assertNotNull(Passwords::policyError('Password123'), 'Blocklist is case-insensitive');
        $this->assertNotNull(Passwords::policyError('kavya-secure-2026', 'kavya@example.com'), 'Must not contain email name');
        $this->assertNotNull(Passwords::policyError('          '));
    }

    public function testCsrfTokenVerification(): void
    {
        $storage = [];
        $session = new Session($storage);
        $csrf = new Csrf($session);
        $token = $csrf->token();
        $this->assertSame(64, strlen($token));
        $this->assertSame($token, $csrf->token(), 'Token is stable within a session');

        $this->assertTrue($csrf->verify(new Request('POST', '/x', [], ['_token' => $token])));
        $this->assertTrue($csrf->verify(new Request('POST', '/x', [], [], [], ['x-csrf-token' => $token])));
        $this->assertFalse($csrf->verify(new Request('POST', '/x', [], ['_token' => 'wrong'])));
        $this->assertFalse($csrf->verify(new Request('POST', '/x', [], [])));
        $this->assertFalse($csrf->verify(new Request('POST', '/x', [], ['_token' => ['array']])));

        $csrf->rotate();
        $this->assertFalse($csrf->verify(new Request('POST', '/x', [], ['_token' => $token])), 'Old token invalid after rotation');
    }

    public function testCsrfFailsWhenSessionHasNoToken(): void
    {
        $storage = [];
        $csrf = new Csrf(new Session($storage));
        $this->assertFalse($csrf->verify(new Request('POST', '/x', [], ['_token' => ''])));
    }

    public function testCsvCellsAreNeutralised(): void
    {
        $this->assertSame("'=HYPERLINK(\"http://evil\")", ReportService::csvCell('=HYPERLINK("http://evil")'));
        $this->assertSame("'+91 98765", ReportService::csvCell('+91 98765'));
        $this->assertSame("'-2+3", ReportService::csvCell('-2+3'));
        $this->assertSame("'@SUM(A1)", ReportService::csvCell('@SUM(A1)'));
        $this->assertSame("'\tx", ReportService::csvCell("\tx"));
        $this->assertSame('Kavya Menon', ReportService::csvCell('Kavya Menon'));
        $this->assertSame('', ReportService::csvCell(null));
    }

    public function testHtmlEscaping(): void
    {
        $this->assertSame('&lt;script&gt;alert(&quot;x&quot;)&lt;/script&gt;', e('<script>alert("x")</script>'));
        $this->assertSame('O&apos;Brien', e("O'Brien"));
        $this->assertSame('', e(null));
        // Invalid UTF-8 is replaced rather than producing an empty string.
        $this->assertNotSame('', e("bad \xC3\x28 byte"));
    }

    public function testJsonScriptCannotBreakOutOfItsElement(): void
    {
        $html = json_script('data', ['x' => '</script><script>alert(1)</script>']);
        $this->assertSame(1, substr_count(strtolower($html), '</script>'));
        $this->assertStringContainsString('</script>', $html);
    }

    public function testShowInputMakesInvisibleCharactersVisible(): void
    {
        $this->assertSame('"a\u{200B}b"', show_input("a\u{200B}b"));
        $this->assertSame('"\t\n"', show_input("\t\n"));
        $this->assertSame('"\u{00A0}"', show_input("\u{00A0}"));
        $this->assertSame('"say \"hi\""', show_input('say "hi"'));
    }
}
