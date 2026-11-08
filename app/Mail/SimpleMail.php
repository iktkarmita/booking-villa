<?php

namespace App\Mail;

use Illuminate\Mail\Mailable;
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
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: $this->mailSubject);
    }

    public function content(): Content
    {
        return new Content(view: 'emails.simple');
    }
}
