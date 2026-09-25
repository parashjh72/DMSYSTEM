<?php

namespace App\Mail\FieldSales;

use App\Models\User;
use App\Support\NepaliDate;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Carbon;

class DailyAttendanceSummaryMail extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * @param  list<array{name: string, status: string, check_in: ?string, check_out: ?string, km: float, geofence: ?string}>  $rows
     * @param  array<string, int>  $totals
     */
    public function __construct(public User $manager, public Carbon $date, public array $rows, public array $totals) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Team attendance — '.$this->date->format('d M Y').' ('.NepaliDate::format($this->date).' BS)');
    }

    public function content(): Content
    {
        return new Content(markdown: 'emails.field-sales.daily-attendance-summary');
    }
}
