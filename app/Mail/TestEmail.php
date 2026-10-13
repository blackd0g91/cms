<?php

namespace App\Mail;

use App\Cms\Settings;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * A short email to check that the site can send email (see the
 * app:test-email command).
 */
class TestEmail extends Mailable
{
    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Test email from '.$this->siteName());
    }

    public function content(): Content
    {
        return new Content(
            view: 'mail.test',
            text: 'mail.test-text',
            with: [
                'siteName' => $this->siteName(),
                'mailer' => config('mail.default'),
                'sentAt' => now()->toDayDateTimeString(),
            ],
        );
    }

    private function siteName(): string
    {
        return rescue(fn () => app(Settings::class)->siteName(), config('app.name'), report: false);
    }
}
