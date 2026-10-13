<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A push notification an admin sent from the control panel (see
 * Cp\PushMessageController), with how it went.
 *
 * @property int $id
 * @property int|null $sender_id
 * @property int|null $recipient_id
 * @property bool $to_everyone
 * @property string $title
 * @property string $body
 * @property string $url
 * @property int $people Who it went to
 * @property int $reached Browsers whose push service took it
 * @property int $failed Browsers whose push service refused it
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read User|null $sender
 * @property-read User|null $recipient
 */
#[Fillable(['to_everyone', 'title', 'body', 'url', 'people', 'reached', 'failed'])]
class PushMessage extends Model
{
    /**
     * @return BelongsTo<User, $this>
     */
    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sender_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function recipient(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recipient_id');
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'to_everyone' => 'boolean',
            'people' => 'integer',
            'reached' => 'integer',
            'failed' => 'integer',
        ];
    }
}
