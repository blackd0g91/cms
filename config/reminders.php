<?php

/*
 * Emailing reminders when they are due (see App\Console\Commands\SendReminders).
 * The mail service's free plan sends only so many emails a day, shared with
 * the account emails (confirming addresses, new passwords), so reminders get
 * a part of it.
 */
return [

    // Reminder emails one member can get in a day.
    'emails_per_member' => (int) env('REMINDER_EMAILS_PER_MEMBER', 10),

    // Reminder emails the whole site sends in a day, leaving the rest of the
    // mail service's daily allowance for account emails.
    'emails_per_day' => (int) env('REMINDER_EMAILS_PER_DAY', 80),

    // A reminder due longer ago than this when it comes to be sent (like
    // after the server was down) is not emailed any more.
    'too_late_after_minutes' => 360,

];
