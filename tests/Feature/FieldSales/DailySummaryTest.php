<?php

namespace Tests\Feature\FieldSales;

use App\Mail\FieldSales\DailyAttendanceSummaryMail;
use App\Models\User;
use App\Support\FieldSalesConfig;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class DailySummaryTest extends TestCase
{
    use InteractsWithFieldStaff, RefreshDatabase;

    private User $asm;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpFieldStaff();
        Mail::fake();
        $this->asm = $this->makeUser('ASM', ['RD001']);
        $tso = $this->makeUser('TSO', ['RD001'], $this->asm);
        $this->attendance($tso, '2026-09-25', '09:50');
        $this->makeUser('ASM', ['RD009']); // no team: gets nothing
    }

    public function test_nothing_is_sent_while_disabled(): void
    {
        $this->artisan('fs:send-daily-summary')->assertSuccessful();

        Mail::assertNothingSent();
    }

    public function test_enabled_summary_waits_for_the_send_time_and_sends_once_a_day(): void
    {
        FieldSalesConfig::save(['daily_summary_enabled' => true, 'daily_summary_time' => '19:00']);

        $this->artisan('fs:send-daily-summary');
        Mail::assertNothingSent();

        $this->travelTo(Carbon::parse('2026-09-25 19:02', self::TZ));
        $this->artisan('fs:send-daily-summary');
        $this->artisan('fs:send-daily-summary');

        Mail::assertSent(DailyAttendanceSummaryMail::class, 1);
        Mail::assertSent(DailyAttendanceSummaryMail::class, function (DailyAttendanceSummaryMail $mail) {
            return $mail->hasTo($this->asm->email)
                && $mail->rows[0]['status'] === 'Late'
                && $mail->rows[0]['check_in'] === '09:50';
        });
    }
}
