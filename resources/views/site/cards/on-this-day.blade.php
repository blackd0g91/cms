{{--
    What happened on this day in years past (see App\Cms\OnThisDay): the
    latest event on the card, and all of them in the dialog, each leading to
    its article on Wikipedia. Expects $onThisDay.
--}}
@php
    $first = $onThisDay['events'][0];
@endphp
<x-site.card dialog="on-this-day" :href="$first['url'] ?? 'https://en.wikipedia.org/wiki/'.str_replace(' ', '_', $onThisDay['date'])" emoji="📜" :label="'On this day · '.$first['year']">
    {{ $first['text'] }}
</x-site.card>

<x-site.dialog id="on-this-day" :title="'On '.$onThisDay['date']" width="max-w-xl">
    <ol class="space-y-4">
        @foreach ($onThisDay['events'] as $event)
            <li class="flex gap-3">
                <span class="w-12 shrink-0 pt-0.5 font-mono text-sm font-medium text-accent">{{ $event['year'] }}</span>
                <div class="min-w-0 flex-1">
                    <p class="text-sm leading-relaxed">{{ $event['text'] }}</p>
                    @if ($event['url'])
                        <a href="{{ $event['url'] }}" target="_blank" rel="noopener" class="mt-1 inline-block text-xs text-muted underline-offset-4 hover:text-ink hover:underline">{{ $event['title'] ?? 'Read more' }} on Wikipedia ↗&#xFE0E;</a>
                    @endif
                </div>
                @if ($event['image'])
                    <img src="{{ $event['image'] }}" alt="" loading="lazy" class="size-14 shrink-0 rounded-xl border border-line bg-paper object-cover">
                @endif
            </li>
        @endforeach
    </ol>
    <p class="mt-5 border-t border-line pt-3 text-xs text-muted">Selected by Wikipedia's editors.</p>
</x-site.dialog>
