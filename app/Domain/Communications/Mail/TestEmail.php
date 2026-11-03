<?php

declare(strict_types=1);

namespace App\Domain\Communications\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class TestEmail extends Mailable
{
    use Queueable, SerializesModels;

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Test Email from '.config('app.name'),
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'communications::emails.test',
        );
    }
}
