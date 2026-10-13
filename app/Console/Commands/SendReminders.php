<?php

namespace App\Console\Commands;

use App\Mail\ReminderEmail;
use App\Models\Reminder;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * Emails the reminders that are due, for those who asked for it. Runs every
 * minute (see routes/console.php). Within the daily limits in
 * config/reminders.php: past them, a reminder is not emailed, but still shows
 * on the site. So are reminders it comes to too late, like after the server
 * was down.
 */
#[Signature('app:send-reminders')]
#[Description('Email the reminders that are due')]
class SendReminders extends Command
{
    public function handle(): int
    {
        $sent = 0;

        Reminder::query()->toEmail()->with('user')->orderBy('due_at')->limit(200)->get()
            ->each(function (Reminder $reminder) use (&$sent) {
                // Each time it is due is handled once, sent or not.
                $reminder->update(['emailed_at' => now()]);

                if (! $this->shouldSend($reminder)) {
                    return;
                }

                try {
                    Mail::to($reminder->user)->send(new ReminderEmail($reminder));
                    $sent++;
                } catch (Throwable $e) {
                    Log::warning("Could not email reminder {$reminder->id}: {$e->getMessage()}");
                }
            });

        if ($sent > 0) {
            $this->components->info("Emailed {$sent} ".($sent === 1 ? 'reminder' : 'reminders').'.');
        }

        return self::SUCCESS;
    }

    private function shouldSend(Reminder $reminder): bool
    {
        if (! $reminder->user->hasVerifiedEmail()) {
            return false;
        }

        if ($reminder->due_at->lt(now()->subMinutes(config('reminders.too_late_after_minutes')))) {
            return false;
        }

        $today = now()->toDateString();
        $limits = [
            "reminder-emails.site.{$today}" => config('reminders.emails_per_day'),
            "reminder-emails.member.{$reminder->user_id}.{$today}" => config('reminders.emails_per_member'),
        ];

        foreach ($limits as $key => $max) {
            if ((int) Cache::get($key, 0) >= $max) {
                Log::info("Reminder {$reminder->id} was not emailed: the daily limit for {$key} is reached.");

                return false;
            }
        }

        // Counted for the day, the site's and the member's.
        foreach (array_keys($limits) as $key) {
            Cache::add($key, 0, now()->addDays(2));
            Cache::increment($key);
        }

        return true;
    }
}
