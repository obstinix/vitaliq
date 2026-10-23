<?php

declare(strict_types=1);

namespace Vitaliq\Tests\Unit;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Vitaliq\Support\RuntimeCheck;

/** Regression: refuse PHP releases affected by CVE-2026-109618 / CVE-2026-109619. */
final class RuntimeCheckTest extends TestCase
{
    /** @return iterable<string,array{string,bool}> */
    public static function versions(): iterable
    {
        yield 'previously installed 8.4.25' => ['8.4.25', false];
        yield '8.4.26' => ['8.4.26', false];
        yield '8.4.27 fixed' => ['8.4.27', true];
        yield '8.4.30 later' => ['8.4.30', true];
        yield '8.3.35' => ['8.3.35', false];
        yield '8.3.36 fixed' => ['8.3.36', true];
        yield '8.2.34' => ['8.2.34', false];
        yield '8.2.35 fixed' => ['8.2.35', true];
        yield '8.5.11' => ['8.5.11', false];
        yield '8.5.12 fixed' => ['8.5.12', true];
        yield '8.1 has no fix' => ['8.1.34', false];
        yield '7.4 has no fix' => ['7.4.33', false];
        yield 'dev suffix on fixed release' => ['8.4.27-dev', true];
        yield 'garbage' => ['not-a-version', false];
    }

    #[DataProvider('versions')]
    public function testPatchedFloors(string $version, bool $ok): void
    {
        $this->assertSame($ok, RuntimeCheck::php($version)['ok'], RuntimeCheck::php($version)['message']);
    }

    public function testTheRunningInterpreterIsPatched(): void
    {
        $result = RuntimeCheck::php();
        $this->assertTrue($result['ok'], $result['message']);
    }

    public function testComposerConstraintMatchesTheFloors(): void
    {
        $composer = json_decode((string) file_get_contents(VQ_ROOT . '/composer.json'), true, 512, JSON_THROW_ON_ERROR);
        foreach (RuntimeCheck::PATCHED as $floor) {
            $this->assertStringContainsString($floor, $composer['require']['php']);
        }
    }
}
