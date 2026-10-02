<?php

namespace App\Cms;

use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Passwords\PasswordBroker;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;

/**
 * Links for choosing a password, which an admin copies from the users page
 * and sends however they like: to invite someone, or for someone who forgot
 * theirs. A link works once, and a new one replaces the last.
 */
class PasswordLinks
{
    private const string BROKER = 'links';

    /**
     * @return array{url: string, expires_at: CarbonImmutable}
     */
    public function create(User $user): array
    {
        $token = $this->broker()->createToken($user);

        return [
            'url' => route('cp.password.edit', ['token' => $token, 'email' => $user->email]),
            'expires_at' => now()->addMinutes($this->expireMinutes()),
        ];
    }

    public function isValid(User $user, string $token): bool
    {
        return $this->broker()->tokenExists($user, $token);
    }

    /**
     * Set the password, if the link is still good, and use the link up.
     */
    public function use(string $email, string $token, string $password): ?User
    {
        $user = null;

        $this->broker()->reset(
            ['email' => $email, 'token' => $token, 'password' => $password],
            function (User $found, string $password) use (&$user) {
                // A new remember token logs out "remember me" sessions elsewhere.
                $found->forceFill(['password' => $password, 'remember_token' => Str::random(60)])->save();
                $user = $found;
            },
        );

        return $user;
    }

    /**
     * When each user's unused link stops working, by email.
     *
     * @param  array<string>  $emails
     * @return array<string, CarbonImmutable>
     */
    public function expiries(array $emails): array
    {
        $table = (string) config('auth.passwords.'.self::BROKER.'.table');

        return DB::table($table)
            ->whereIn('email', $emails)
            ->pluck('created_at', 'email')
            ->map(fn (string $createdAt) => CarbonImmutable::parse($createdAt)->addMinutes($this->expireMinutes()))
            ->filter(fn (CarbonImmutable $expires) => $expires->isFuture())
            ->all();
    }

    private function expireMinutes(): int
    {
        return (int) config('auth.passwords.'.self::BROKER.'.expire');
    }

    private function broker(): PasswordBroker
    {
        /** @var PasswordBroker */
        return Password::broker(self::BROKER);
    }
}
