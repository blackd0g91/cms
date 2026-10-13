<?php

namespace App\Console\Commands;

use App\Cms\PushNotifications;
use App\Models\User;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Throwable;

#[Signature('app:test-push {email : The account to push to}')]
#[Description('Push a test notification to every browser an account turned notifications on in, and show what each answered')]
class SendTestPush extends Command
{
    public function handle(PushNotifications $push): int
    {
        if (! $push->enabled()) {
            $this->components->error('Push notifications are off: set WEBPUSH_PUBLIC_KEY and WEBPUSH_PRIVATE_KEY in .env (see php artisan app:push-keys), then run php artisan config:cache.');

            return self::FAILURE;
        }

        $user = User::query()->where('email', $this->argument('email'))->first();

        if (! $user) {
            $this->components->error("There is no account for [{$this->argument('email')}].");

            return self::FAILURE;
        }

        try {
            $results = $push->deliver($user, [
                'title' => 'Test notification',
                'body' => 'If you see this, push notifications reach this browser.',
                'tag' => 'test',
                'url' => '/',
            ]);
        } catch (Throwable $e) {
            $this->components->error("The push could not be sent: {$e->getMessage()}");

            return self::FAILURE;
        }

        if ($results === []) {
            $this->components->warn('This account has not turned notifications on in any browser yet.');

            return self::SUCCESS;
        }

        foreach ($results as $result) {
            $subscription = $result['subscription'];
            $browser = PushNotifications::service($subscription).' · since '.$subscription->created_at?->format('Y-m-d H:i');

            $this->components->twoColumnDetail($browser, match (true) {
                $result['delivered'] => '<fg=green>taken</>',
                $result['gone'] => '<fg=yellow>gone, forgotten</>',
                default => "<fg=red>refused: {$result['reason']}</>",
            });
        }

        $this->newLine();
        $this->line('  <fg=gray>"Taken" means the push service has it: if a browser still shows nothing, look at its notification settings.</>');

        return self::SUCCESS;
    }
}
