<?php

namespace App\Mail;

use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/** Email notifikasi sederhana: judul, isi teks, dan satu tombol tautan. */
class SimpleMail extends Mailable
{
    public function __construct(
        public string $mailSubject,
        public string $body,
        public ?string $url = null,
        public ?string $urlLabel = null,
        public string $brand = '',
        public ?string $replyTo = null,
    ) {}

    public function envelope(): Envelope
    {
        // Nama pengirim = nama villa (alamat tetap dari MAIL_FROM_ADDRESS agar lolos SMTP); balasan tamu ke owner.
        return new Envelope(
            from: $this->brand !== '' ? new Address((string) config('mail.from.address'), $this->brand) : null,
            replyTo: filled($this->replyTo) ? [new Address($this->replyTo)] : [],
            subject: $this->mailSubject,
        );
    }

    public function content(): Content
    {
        return new Content(view: 'emails.simple');
    }
}
