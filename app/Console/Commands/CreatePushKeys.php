<?php

namespace App\Console\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Minishlink\WebPush\VAPID;

#[Signature('app:push-keys')]
#[Description('Make the pair of keys push notifications are signed with, to put in .env')]
class CreatePushKeys extends Command
{
    public function handle(): int
    {
        $keys = VAPID::createVapidKeys();

        $this->components->info('Add these lines to .env, once: new keys stop the browsers already signed up from getting pushes.');
        $this->line("WEBPUSH_PUBLIC_KEY={$keys['publicKey']}");
        $this->line("WEBPUSH_PRIVATE_KEY={$keys['privateKey']}");
        $this->newLine();
        $this->components->info('Then run php artisan config:cache on the server.');

        return self::SUCCESS;
    }
}
