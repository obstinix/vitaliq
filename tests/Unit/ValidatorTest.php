<?php

declare(strict_types=1);

namespace Vitaliq\Tests\Unit;

use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use Vitaliq\Validation\Validator;

final class ValidatorTest extends TestCase
{
    public function testRequiredRuleReportsMissingValue(): void
    {
        $v = new Validator(['full_name' => "  \u{200B} "]);
        $this->assertNull($v->rule('full_name', 'full_name'));
        $this->assertSame('Full name is required.', $v->errors()['full_name']);
    }

    public function testOptionalRuleAcceptsBlank(): void
    {
        $v = new Validator(['email' => '   ']);
        $this->assertNull($v->rule('email', 'email', false));
        $this->assertFalse($v->fails());
    }

    public function testCustomLabelReplacesRuleLabel(): void
    {
        $v = new Validator(['mobile' => '123']);
        $v->rule('mobile', 'indian_mobile', true, 'Mobile number');
        $this->assertStringStartsWith('Mobile number', $v->errors()['mobile']);
    }

    public function testChoiceRejectsValuesOutsideWhitelist(): void
    {
        $v = new Validator(['slot' => "morning' OR '1'='1"]);
        $this->assertNull($v->choice('slot', ['morning' => 'Morning'], 'Slot'));
        $this->assertTrue($v->hasError('slot'));
    }

    public function testChoicesRejectsArrayWithUnknownValueAndNonArrays(): void
    {
        $v = new Validator(['symptoms' => ['fever', 'zombie'], 'other' => 'fever']);
        $this->assertSame([], $v->choices('symptoms', ['fever' => 'Fever'], 'Symptoms'));
        $this->assertSame([], $v->choices('other', ['fever' => 'Fever'], 'Other'));
        $this->assertTrue($v->hasError('symptoms'));
        $this->assertTrue($v->hasError('other'));
    }

    public function testChoicesDeduplicates(): void
    {
        $v = new Validator(['symptoms' => ['fever', 'fever']]);
        $this->assertSame(['fever'], $v->choices('symptoms', ['fever' => 'Fever'], 'Symptoms'));
    }

    public function testDateRangeIsEnforced(): void
    {
        $today = new DateTimeImmutable('2026-10-10');
        $v = new Validator(['a' => '2026-10-09', 'b' => '2026-10-25', 'c' => '2026-10-12', 'd' => '2026-02-30']);
        $this->assertNull($v->date('a', 'A', $today, $today->modify('+14 days')));
        $this->assertNull($v->date('b', 'B', $today, $today->modify('+14 days')));
        $this->assertSame('2026-10-12', $v->date('c', 'C', $today, $today->modify('+14 days')));
        $this->assertNull($v->date('d', 'D'));
        $this->assertSame('That date does not exist.', $v->errors()['d']);
    }

    public function testTextStripsControlCharactersButKeepsJoiners(): void
    {
        // ZWJ (U+200D) is needed by Indic scripts and must survive; a NUL and a bell must not.
        $v = new Validator(['notes' => "Line one\r\nक्\u{200D}ष\x07\x00"]);
        $value = $v->text('notes', 'Notes', 100);
        $this->assertSame("Line one\nक्\u{200D}ष", $value);
    }

    public function testTextLengthLimits(): void
    {
        $v = new Validator(['a' => 'abc', 'b' => str_repeat('x', 11)]);
        $this->assertNull($v->text('a', 'A', 10, true, 5));
        $this->assertNull($v->text('b', 'B', 10));
        $this->assertCount(2, $v->errors());
    }

    public function testNonScalarInputIsTreatedAsEmpty(): void
    {
        $v = new Validator(['full_name' => ['array', 'injection']]);
        $this->assertNull($v->rule('full_name', 'full_name'));
        $this->assertSame('Full name is required.', $v->errors()['full_name']);
    }

    public function testAccepted(): void
    {
        $v = new Validator(['consent' => '1', 'no' => '0']);
        $this->assertTrue($v->accepted('consent', 'x'));
        $this->assertFalse($v->accepted('no', 'Please confirm.'));
        $this->assertSame('Please confirm.', $v->errors()['no']);
    }
}
