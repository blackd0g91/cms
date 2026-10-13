<?php

namespace App\Console\Commands;

use App\Mail\TestEmail;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Validator;
use Throwable;

#[Signature('app:test-email {to : The address to send it to}')]
#[Description('Send a test email, to check the mail settings in .env')]
class SendTestEmail extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $to = (string) $this->argument('to');

        if (Validator::make(['to' => $to], ['to' => ['email']])->fails()) {
            $this->components->error("[{$to}] is not an email address.");

            return self::FAILURE;
        }

        $mailer = (string) config('mail.default');

        $this->components->twoColumnDetail('Mailer', $mailer);

        if (config("mail.mailers.{$mailer}.transport") === 'smtp') {
            $this->components->twoColumnDetail('Server', config("mail.mailers.{$mailer}.host").':'.config("mail.mailers.{$mailer}.port"));
        }

        $this->components->twoColumnDetail('From', config('mail.from.name').' <'.config('mail.from.address').'>');
        $this->newLine();

        try {
            Mail::to($to)->send(new TestEmail);
        } catch (Throwable $e) {
            $this->components->error("The email could not be sent: {$e->getMessage()}");
            $this->components->bulletList([
                'Check MAIL_HOST, MAIL_PORT, MAIL_USERNAME and MAIL_PASSWORD in .env.',
                'The from address must be on a domain the mail service has verified.',
                'After changing .env on the server, run php artisan config:cache.',
            ]);

            return self::FAILURE;
        }

        if (in_array($mailer, ['log', 'array'], true)) {
            $this->components->warn("MAIL_MAILER is {$mailer}, so nothing left this machine: the email went to the log instead.");

            return self::SUCCESS;
        }

        $this->components->info("Sent to [{$to}]. Check that it arrived, and not in spam.");

        return self::SUCCESS;
    }
}
