<?php

namespace App\Mail;

use App\Models\ScheduledReport;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ScheduledReportMail extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * @param  array{from: ?string, to: ?string}  $window
     */
    public function __construct(
        public ScheduledReport $report,
        public string $filePath,
        public string $fileName,
        public array $window,
        public int $rowCount,
    ) {}

    public function envelope(): Envelope
    {
        $date = now(config('reports.timezone'))->format('d M Y');

        return new Envelope(subject: "{$this->report->name} — {$date}");
    }

    public function content(): Content
    {
        return new Content(markdown: 'emails.scheduled-report');
    }

    /** @return array<int, Attachment> */
    public function attachments(): array
    {
        return [
            Attachment::fromPath($this->filePath)->as($this->fileName),
        ];
    }
}
