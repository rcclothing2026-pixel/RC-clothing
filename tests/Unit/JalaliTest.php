<?php

namespace Tests\Unit;

use App\Support\Jalali;
use Tests\TestCase;

class JalaliTest extends TestCase
{
    public function test_gregorian_to_jalali_known_dates(): void
    {
        $this->assertSame('۱۴۰۵/۰۳/۲۵', Jalali::format('2026-06-15'));
        $this->assertSame('۱۴۰۳/۰۱/۰۱', Jalali::format('2024-03-20')); // Nowruz
    }

    public function test_parse_round_trip(): void
    {
        $this->assertSame('2026-06-15', Jalali::parse(Jalali::format('2026-06-15')));
        $this->assertSame('2024-03-20', Jalali::parse('1403/01/01'));
    }

    public function test_parse_invalid_returns_null(): void
    {
        $this->assertNull(Jalali::parse(''));
        $this->assertNull(Jalali::parse('not-a-date'));
    }
}
