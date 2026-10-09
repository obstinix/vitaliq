<?php

declare(strict_types=1);

namespace Vitaliq\Tests\Unit;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Vitaliq\Core\App;
use Vitaliq\Core\Config;
use Vitaliq\Core\Database;
use Vitaliq\Services\QaService;
use Vitaliq\Validation\Rules;

final class RulesTest extends TestCase
{
    /** @return iterable<string,array{string,string,bool}> */
    public static function catalogueCases(): iterable
    {
        $cases = json_decode((string) file_get_contents(VQ_ROOT . '/database/qa/regex-cases.json'), true, 512, JSON_THROW_ON_ERROR);
        foreach ($cases as $c) {
            yield $c['code'] . ' ' . $c['title'] => [$c['rule'], $c['input'], (bool) $c['expected']];
        }
    }

    /** Every regex case in the STQA catalogue, evaluated exactly as the lab does. */
    #[DataProvider('catalogueCases')]
    public function testCatalogueCase(string $rule, string $input, bool $expected): void
    {
        $app = new App(Config::fromEnv(), new Database(Config::fromEnv()['db']));
        $result = $app->service(QaService::class)->evaluate($rule, $input);
        $this->assertSame($expected, $result['accepted'], $result['message'] ?? 'accepted');
    }

    public function testEveryPatternCompilesInPcre(): void
    {
        foreach (Rules::all() as $key => $rule) {
            $this->assertNotFalse(@preg_match('/' . $rule['pattern'] . '/u', ''), "Pattern for {$key} does not compile");
        }
    }

    public function testDocumentedExamplesBehaveAsDocumented(): void
    {
        foreach (Rules::all() as $key => $rule) {
            foreach ($rule['valid'] as $v) {
                $n = Rules::normalise($key, $v);
                $ok = $key === 'required' ? Rules::matches($key, $n) : Rules::check($key, $n) === null;
                $this->assertTrue($ok, "{$key}: documented valid example rejected: {$v}");
            }
            foreach ($rule['invalid'] as $v) {
                $n = Rules::normalise($key, $v);
                $ok = $key === 'required' ? Rules::matches($key, $n) : Rules::check($key, $n) === null;
                $this->assertFalse($ok, "{$key}: documented invalid example accepted: {$v}");
            }
        }
    }

    public function testMobileNumbersAreStoredAsTenDigits(): void
    {
        foreach (['+91 98765 43210', '09876543210', '919876543210', '0091 9876543210', '98765-43210'] as $input) {
            $this->assertSame('9876543210', Rules::canonical('indian_mobile', Rules::normalise('indian_mobile', $input)));
        }
    }

    public function testCtValueUpperBoundMatchesDatabaseConstraint(): void
    {
        $this->assertNull(Rules::check('ct_value', '45'));
        $this->assertNull(Rules::check('ct_value', '45.0'));
        $this->assertNull(Rules::check('ct_value', '44.9'));
        $this->assertNotNull(Rules::check('ct_value', '45.1'), 'DEF-004 regression');
        $this->assertNotNull(Rules::check('ct_value', '45.9'), 'DEF-004 regression');
    }

    public function testErrorMessagesDoNotEchoTheInput(): void
    {
        $message = (string) Rules::check('full_name', '<script>alert(1)</script>');
        $this->assertStringNotContainsString('<script>', $message);
    }

    public function testClientExportContainsNoServerOnlyFields(): void
    {
        $client = Rules::forClient();
        $this->assertSame(array_keys(Rules::all()), array_keys($client));
        foreach ($client as $rule) {
            $this->assertSame(['label', 'pattern', 'flags', 'min', 'max', 'normalise', 'format'], array_keys($rule));
        }
    }

    public function testEvaluateRefusesOversizedInput(): void
    {
        $app = new App(Config::fromEnv(), new Database(Config::fromEnv()['db']));
        $this->expectException(\Vitaliq\Services\ValidationFailed::class);
        $app->service(QaService::class)->evaluate('full_name', str_repeat('a', QaService::MAX_INPUT + 1));
    }
}
