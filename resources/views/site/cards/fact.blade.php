{{--
    A useless fact, a new one each day (see App\Cms\UselessFact): the start
    of it on the card, all of it in the dialog. Expects $fact.
--}}
<x-site.card dialog="useless-fact" :href="$fact['url'] ?? 'https://uselessfacts.jsph.pl/'" emoji="💡" label="Useless fact">
    {{ $fact['text'] }}
</x-site.card>

<x-site.dialog id="useless-fact" title="Useless fact">
    <p class="font-display text-2xl leading-snug">{{ $fact['text'] }}</p>
    <p class="mt-5 border-t border-line pt-3 text-xs text-muted">
        @if ($fact['source'])
            Source:
            @if ($fact['url'])
                <a href="{{ $fact['url'] }}" target="_blank" rel="noopener" class="underline underline-offset-2 hover:text-ink">{{ $fact['source'] }}</a>
            @else
                {{ $fact['source'] }}
            @endif
            ·
        @endif
        From uselessfacts.jsph.pl, a new one tomorrow.
    </p>
</x-site.dialog>
