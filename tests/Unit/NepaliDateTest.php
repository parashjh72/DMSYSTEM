<?php

namespace Tests\Unit;

use App\Support\NepaliDate;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class NepaliDateTest extends TestCase
{
    /** @return array<string, array{string, string}> AD => BS, from the official calendar */
    public static function knownDates(): array
    {
        return [
            'first supported day' => ['1943-04-14', '2000-01-01'],
            'new year 2081' => ['2024-04-13', '2081-01-01'],
            'new year 2082' => ['2025-04-14', '2082-01-01'],
            'last day of 2081' => ['2025-04-13', '2081-12-31'],
            'mid Asoj 2082' => ['2025-09-25', '2082-06-09'],
            'last supported day' => ['2034-04-13', '2090-12-30'],
        ];
    }

    #[DataProvider('knownDates')]
    public function test_converts_ad_to_bs(string $ad, string $bs): void
    {
        $this->assertSame($bs, NepaliDate::format($ad));
    }

    #[DataProvider('knownDates')]
    public function test_converts_bs_back_to_ad(string $ad, string $bs): void
    {
        [$year, $month, $day] = array_map('intval', explode('-', $bs));

        $this->assertSame($ad, NepaliDate::toAd($year, $month, $day)?->toDateString());
    }

    public function test_dates_outside_the_table_return_null(): void
    {
        $this->assertNull(NepaliDate::format('1943-04-13'));
        $this->assertNull(NepaliDate::format('2034-04-14'));
        $this->assertNull(NepaliDate::toAd(2082, 13, 1));
        $this->assertNull(NepaliDate::toAd(2082, 1, 32));
    }

    public function test_long_format_and_month_label(): void
    {
        $this->assertSame('9 Aswin 2082', NepaliDate::formatLong('2025-09-25'));
        $this->assertSame('Aswin 2082', NepaliDate::monthLabel('2025-09-25'));
    }
}
