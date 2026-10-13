<?php

namespace App\Providers;

use App\Cms\Settings;
use App\Enums\PostStatus;
use App\Models\Link;
use App\Models\Template;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();
        $this->configureAccountEmails();

        // The control panel, and in it templates, links, settings and users (see routes/cp.php).
        Gate::define('staff', fn (User $user) => $user->isStaff());
        Gate::define('admin', fn (User $user) => $user->isAdmin());

        View::composer('site.layout', function ($view) {
            $view->with([
                'navTemplates' => Template::query()
                    ->withCount(['posts' => fn ($query) => $query->where('status', PostStatus::Published)])
                    ->orderBy('name')
                    ->get(['id', 'name', 'handle', 'color']),
                'settings' => app(Settings::class),
                // Links to drafts wait until they are published.
                'navLinks' => Link::query()
                    ->with('post.template')
                    ->orderBy('position')
                    ->get()
                    ->filter(fn (Link $link) => $link->href() !== null)
                    ->values(),
            ]);
        });
    }

    /**
     * The emails to confirm an address and to choose a new password, in the
     * site's look (see resources/views/mail/action.blade.php).
     */
    protected function configureAccountEmails(): void
    {
        $email = function (string $subject, string $heading, array $lines, string $actionText, string $actionUrl, string $outro): MailMessage {
            $siteName = rescue(fn () => app(Settings::class)->siteName(), config('app.name'), report: false);

            return (new MailMessage)
                ->subject($subject)
                ->view(['mail.action', 'mail.action-text'], compact('siteName', 'heading', 'lines', 'actionText', 'actionUrl', 'outro'));
        };

        VerifyEmail::toMailUsing(fn (User $user, string $url) => $email(
            'Confirm your email address',
            'Confirm your email address',
            ['Thanks for signing up. Confirm that this address is yours, so it can be used to reach you, like when you forget your password.'],
            'Confirm my address',
            $url,
            'The link works for an hour. If you did not sign up, you can ignore this email.',
        ));

        ResetPassword::toMailUsing(fn (User $user, string $token) => $email(
            'Choose a new password',
            'Choose a new password',
            ['Someone, hopefully you, asked to choose a new password for the account with this address.'],
            'Choose a new password',
            route('password.reset', ['token' => $token, 'email' => $user->getEmailForPasswordReset()]),
            'The link works for an hour. If you did not ask for it, you can ignore this email: your password stays as it is.',
        ));
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        Password::defaults(fn (): ?Password => app()->isProduction()
            ? Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
            : null,
        );
    }
}
