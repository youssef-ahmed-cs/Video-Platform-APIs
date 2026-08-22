<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class InvoicePaidMail extends Mailable
{
    use Queueable, SerializesModels;

    public $user;

    public function __construct()
    {
//        $this->user = $user;
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Invoice Paid',
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.invoice-paid',
        );
    }

    public function attachments(): array
    {
        return [
            // Option A: Attach from an absolute disk path
            Attachment::fromPath('https://cdn.hackclub.com/019f1df7-189c-7b90-9bfd-802fb59af39a/Youssef%20Ahmed%20CV%20v01.pdf')
                ->as('monthly_invoice.pdf')
                ->withMime('application/pdf'),
        ];
    }
}
