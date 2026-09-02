<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class RestoreAccountOtpMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(protected string $otp)
    {
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Restore Account OTP',
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.restore-account-otp',
            with: [
                'otp' => $this->otp,
            ]
        );
    }

    public function attachments(): array
    {
        return [];
    }
}
