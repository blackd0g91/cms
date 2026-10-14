<?php

namespace App\Cms\Concerns;

use Closure;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * For the home page cards fed by another site once a day (see
 * App\Cms\HomeWidgets): what it answered is kept until the day ends (in
 * UTC), so visitors never wait on it, and a slow or failing site is only
 * asked again after a while.
 */
trait FetchesDaily
{
    /**
     * After a failure, how long to wait before asking again, in seconds.
     */
    protected int $retryAfter = 1800;

    /**
     * Today's value under $key, fetched when not kept yet. Null when there is
     * none, or the other site could not be reached.
     *
     * @template T of array
     *
     * @param  Closure(): (T|null)  $fetch
     * @return T|null
     */
    protected function daily(string $key, Closure $fetch): ?array
    {
        $today = now('UTC');

        // False for "none, try again later": a null would not be kept.
        $value = Cache::get($key = "{$key}.{$today->toDateString()}");

        if ($value === null) {
            try {
                $value = $fetch() ?? false;
            } catch (Throwable $e) {
                Log::warning("Could not get {$key}: {$e->getMessage()}");
                $value = false;
            }

            Cache::put($key, $value, $value === false ? $this->retryAfter : $today->copy()->endOfDay());
        }

        return $value ?: null;
    }

    /**
     * How long to wait for an answer, in seconds.
     */
    protected function timeout(): int
    {
        return 4;
    }

    /**
     * A request that gives up quickly, saying who is asking, as Wikimedia
     * asks.
     */
    protected function http(): PendingRequest
    {
        return Http::timeout($this->timeout())
            ->withUserAgent(config('app.name').' ('.config('app.url').')')
            ->acceptJson();
    }
}
