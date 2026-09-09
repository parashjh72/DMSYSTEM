<?php

namespace App\Mail;

use App\Models\Pjp;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class PjpWorkflowMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public Pjp $pjp, public string $body) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: "PJP {$this->pjp->monthLabel()} — ".$this->pjp->statusLabel());
    }

    public function content(): Content
    {
        return new Content(markdown: 'emails.pjp-workflow');
    }
}
