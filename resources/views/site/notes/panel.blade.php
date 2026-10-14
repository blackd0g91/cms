{{--
    The notes on a member's own page (see site/me.blade.php): a quick form
    for a new one, and the latest few, pinned first, each leading to it on
    the notes page. Expects $notes and $noteCount.
--}}
<section aria-labelledby="your-notes" class="rounded-3xl border border-line bg-card p-5 shadow-sm sm:p-6">
    <h2 id="your-notes" class="font-display text-2xl font-semibold tracking-tight">
        <a href="{{ route('notes.index') }}" class="decoration-2 underline-offset-4 hover:underline">Notes</a>
    </h2>

    <div class="mt-3">
        @include('site.notes.form', ['quick' => true, 'idPrefix' => 'quick-note'])
        @error('body', 'note')
            <p class="mt-2 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
        @enderror
    </div>

    @if ($notes->isNotEmpty())
        <ul class="mt-4 divide-y divide-line border-t border-line">
            @foreach ($notes as $note)
                <li>
                    <a href="{{ route('notes.index') }}#note-{{ $note->id }}" class="group block py-2.5">
                        <span class="flex items-center gap-1.5 text-sm font-medium group-hover:text-accent">
                            @if ($note->pinned_at)
                                <svg class="size-3.5 shrink-0 text-accent" viewBox="0 0 24 24" fill="currentColor" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round" aria-label="Pinned"><path d="M12 17v5M9 3h6l-1 6 4 4v2H6v-2l4-4z"/></svg>
                            @endif
                            <span class="truncate">{{ $note->title ?? $note->preview(60) }}</span>
                        </span>
                        @if ($note->title)
                            <span class="mt-0.5 line-clamp-2 block text-sm text-muted">{{ $note->preview() }}</span>
                        @endif
                    </a>
                </li>
            @endforeach
        </ul>
    @endif

    @if ($noteCount > 0)
        <a href="{{ route('notes.index') }}" class="mt-3 inline-flex items-center gap-1 text-sm text-muted underline-offset-4 hover:text-ink hover:underline">
            All notes{{ $noteCount > $notes->count() ? " ({$noteCount})" : '' }} <span aria-hidden="true">→</span>
        </a>
    @endif
</section>
