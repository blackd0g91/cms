<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Enums\HomeWidget;
use App\Enums\UserRole;
use Database\Factories\UserFactory;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $name
 * @property string $email
 * @property UserRole $role
 * @property array{latitude: float, longitude: float, name: string|null}|null $location Where they last asked for the weather
 * @property list<string>|null $home_widgets The cards they chose for the home page, in order, or null for the defaults
 * @property string|null $timezone Their own, from their browser, for their reminders
 * @property Carbon|null $email_verified_at
 * @property string|null $password
 * @property string|null $remember_token
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['name', 'email', 'role', 'password', 'location', 'home_widgets', 'timezone'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable implements MustVerifyEmail
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * Someone created without a role is a member, who can not reach the
     * control panel: staff are always given their role on purpose.
     *
     * @var array<string, string>
     */
    protected $attributes = [
        'role' => 'member',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'role' => UserRole::class,
            'location' => 'array',
            'home_widgets' => 'array',
        ];
    }

    public function isAdmin(): bool
    {
        return $this->role === UserRole::Admin;
    }

    /**
     * Whether they use the control panel: an admin or an editor.
     */
    public function isStaff(): bool
    {
        return $this->role->isStaff();
    }

    /**
     * Admins and editors, not members.
     *
     * @param  Builder<User>  $query
     */
    public function scopeStaff(Builder $query): void
    {
        $query->whereIn('role', UserRole::staff());
    }

    /**
     * Added from the users page, and has not chosen a password yet.
     */
    public function isInvited(): bool
    {
        return $this->password === null;
    }

    /**
     * Their time zone, or the site's until their browser has told it.
     */
    public function localTimezone(): string
    {
        return $this->timezone ?? config('app.timezone');
    }

    /**
     * The cards at the top of their home page, in their order: the ones
     * they chose, or the defaults until they do.
     *
     * @return list<HomeWidget>
     */
    public function homeWidgets(): array
    {
        if ($this->home_widgets === null) {
            return HomeWidget::defaults();
        }

        // Any the site no longer has are left out, and each shows once.
        $widgets = [];

        foreach ($this->home_widgets as $value) {
            $widget = HomeWidget::tryFrom($value);

            if ($widget !== null && ! in_array($widget, $widgets, true)) {
                $widgets[] = $widget;
            }
        }

        return $widgets;
    }

    /**
     * @return HasMany<Reminder, $this>
     */
    public function reminders(): HasMany
    {
        return $this->hasMany(Reminder::class);
    }

    /**
     * The browsers they turned push notifications on in.
     *
     * @return HasMany<PushSubscription, $this>
     */
    public function pushSubscriptions(): HasMany
    {
        return $this->hasMany(PushSubscription::class);
    }

    /**
     * @return HasMany<Post, $this>
     */
    public function posts(): HasMany
    {
        return $this->hasMany(Post::class, 'author_id');
    }
}
