<?php

namespace App\Mail;

use App\Cms\Settings;
use App\Models\Reminder;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;

/**
 * A reminder that is due, with links to mark it done, snooze it or stop its
 * emails. The links work without logging in, for a week, and each asks to
 * confirm first, as email apps open links on their own to check them.
 */
class ReminderEmail extends Mailable
{
    public function __construct(public Reminder $reminder) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Reminder: '.Str::limit($this->reminder->text, 80));
    }

    public function content(): Content
    {
        $timezone = $this->reminder->user->localTimezone();
        $due = $this->reminder->due_at->setTimezone($timezone);
        $link = fn (string $action) => URL::temporarySignedRoute('reminders.email', now()->addWeek(), ['reminder' => $this->reminder->id, 'action' => $action]);

        return new Content(
            view: 'mail.action',
            text: 'mail.action-text',
            with: [
                'siteName' => rescue(fn () => app(Settings::class)->siteName(), config('app.name'), report: false),
                'heading' => $this->reminder->text,
                'lines' => [
                    'Due '.$due->format('l, F j \a\t H:i').($this->reminder->repeat ? ', '.Str::lower($this->reminder->repeat->label()) : '').'.',
                ],
                'actionText' => 'Mark as done',
                'actionUrl' => $link('done'),
                'links' => [
                    'Snooze for an hour' => $link('snooze'),
                    'See all your reminders' => route('reminders.index'),
                    'Stop emails for this reminder' => $link('stop'),
                ],
                'outro' => 'You asked to be emailed about this reminder. The links work for a week.',
            ],
        );
    }
}
