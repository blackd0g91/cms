<?php

namespace App\Console\Commands;

use App\Cms\PushNotifications;
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
 * Sends the reminders that are due: as push notifications to the browsers
 * members turned them on in, and as emails for the reminders they asked
 * that for. Runs every minute (see routes/console.php). Emails stay within
 * the daily limits in config/reminders.php: past them, a reminder is not
 * emailed, but still shows on the site. Reminders it comes to too late,
 * like after the server was down, are not sent at all.
 */
#[Signature('app:send-reminders')]
#[Description('Send the reminders that are due, as push notifications and emails')]
class SendReminders extends Command
{
    public function handle(PushNotifications $push): int
    {
        $pushed = $push->enabled() ? $this->push($push) : 0;
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

        if ($pushed > 0) {
            $this->components->info("Pushed {$pushed} ".($pushed === 1 ? 'reminder' : 'reminders').'.');
        }

        if ($sent > 0) {
            $this->components->info("Emailed {$sent} ".($sent === 1 ? 'reminder' : 'reminders').'.');
        }

        return self::SUCCESS;
    }

    /**
     * Push the reminders that are due to the members' browsers. How many
     * reached at least one.
     */
    private function push(PushNotifications $push): int
    {
        $pushed = 0;

        Reminder::query()->toPush()->with('user')->orderBy('due_at')->limit(200)->get()
            ->each(function (Reminder $reminder) use ($push, &$pushed) {
                $reminder->update(['pushed_at' => now()]);

                if (! $this->inTime($reminder)) {
                    return;
                }

                $reached = $push->send($reminder->user, [
                    'title' => 'Reminder',
                    'body' => $reminder->text,
                    'tag' => "reminder-{$reminder->id}",
                    'url' => route('reminders.index', absolute: false),
                ]);

                $pushed += $reached > 0 ? 1 : 0;
            });

        return $pushed;
    }

    /**
     * For a confirmed account, and not too late: like after the server was down.
     */
    private function inTime(Reminder $reminder): bool
    {
        return $reminder->user->hasVerifiedEmail()
            && $reminder->due_at->gte(now()->subMinutes(config('reminders.too_late_after_minutes')));
    }

    private function shouldSend(Reminder $reminder): bool
    {
        if (! $this->inTime($reminder)) {
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
