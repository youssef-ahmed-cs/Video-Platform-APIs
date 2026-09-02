<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class AdminRestoreAccountRequestMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public array $details)
    {
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Account Activation Request (Forgot Account Number)',
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.admin-restore-account-request',
            with: [
                'details' => $this->details,
            ]
        );
    }

    public function attachments(): array
    {
        return [];
    }
}
