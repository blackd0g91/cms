{{--
    Wiktionary's word of the day (see App\Cms\WordOfTheDay): the word on the
    card, and what it means in the dialog. Expects $word.
--}}
<x-site.card dialog="word-of-the-day" :href="$word['url']" emoji="📖" label="Word of the day">
    {{ $word['word'] }}
</x-site.card>

<x-site.dialog id="word-of-the-day" title="Word of the day">
    <p class="font-display text-4xl leading-tight font-semibold tracking-tight break-words">{{ $word['word'] }}</p>
    @if ($word['kind'])
        <p class="mt-1 text-sm text-muted italic">{{ $word['kind'] }}</p>
    @endif

    @if ($word['meanings'] !== [])
        <ol class="mt-4 list-decimal space-y-2 pl-5 text-sm leading-relaxed marker:text-muted">
            @foreach ($word['meanings'] as $meaning)
                <li>{{ $meaning }}</li>
            @endforeach
        </ol>
    @endif

    <div class="mt-5 flex items-center justify-between gap-3 border-t border-line pt-3 text-xs text-muted">
        <span>From Wiktionary</span>
        <a href="{{ $word['url'] }}" target="_blank" rel="noopener" class="rounded-full border border-line px-3 py-1.5 text-ink transition hover:border-accent">More on Wiktionary ↗&#xFE0E;</a>
    </div>
</x-site.dialog>
