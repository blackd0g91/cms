{{--
    One note on the notes page: its title, the markdown as it reads, when it
    was last changed, and buttons to pin, change and delete it. Expects
    $note and $user.
--}}
<article id="note-{{ $note->id }}" class="flex scroll-mt-24 flex-col rounded-3xl border border-line bg-card p-5 shadow-sm">
    <div class="flex items-start justify-between gap-3">
        <div class="min-w-0">
            @if ($note->title)
                <h2 class="font-display text-xl leading-tight font-semibold tracking-tight break-words">{{ $note->title }}</h2>
            @endif
            <p class="mt-0.5 font-mono text-xs text-muted">
                @if ($note->pinned_at)
                    <span class="text-accent">Pinned ·</span>
                @endif
                {{ $note->updated_at->setTimezone($user->localTimezone())->isoFormat($note->updated_at->year === now()->year ? 'MMM D, HH:mm' : 'MMM D YYYY, HH:mm') }}
            </p>
            @if ($note->labels->isNotEmpty())
                <p class="mt-2 flex flex-wrap gap-1">
                    @foreach ($note->labels as $label)
                        @include('site.labels.chip', ['label' => $label, 'href' => route('notes.index', ['label' => $label->id])])
                    @endforeach
                </p>
            @endif
        </div>

        <div class="-mt-1 -mr-1 flex shrink-0 items-center">
            <form method="post" action="{{ route('notes.pin', $note) }}">
                @csrf
                <button type="submit" title="{{ $note->pinned_at ? 'Unpin' : 'Pin to the top' }}" @class([
                    'grid size-8 place-items-center rounded-full transition hover:bg-paper',
                    'text-accent' => $note->pinned_at,
                    'text-muted hover:text-ink' => ! $note->pinned_at,
                ])>
                    <svg class="size-4" viewBox="0 0 24 24" fill="{{ $note->pinned_at ? 'currentColor' : 'none' }}" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 17v5M9 3h6l-1 6 4 4v2H6v-2l4-4z"/></svg>
                    <span class="sr-only">{{ $note->pinned_at ? 'Unpin' : 'Pin' }}: {{ $note->title ?? $note->preview(40) }}</span>
                </button>
            </form>
            <form method="post" action="{{ route('notes.destroy', $note) }}">
                @csrf
                @method('delete')
                <button type="submit" title="Delete" class="grid size-8 place-items-center rounded-full text-muted transition hover:bg-paper hover:text-red-600">
                    <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M4 7h16M10 11v6M14 11v6M5.5 7l1 12a2 2 0 0 0 2 1.8h7a2 2 0 0 0 2-1.8l1-12M9 7V4.5a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1V7"/></svg>
                    <span class="sr-only">Delete: {{ $note->title ?? $note->preview(40) }}</span>
                </button>
            </form>
        </div>
    </div>

    <div class="prose prose-sm mt-3 max-w-none break-words">
        {{ $note->html() }}
    </div>

    <details class="mt-4 border-t border-line pt-3">
        <summary class="inline-flex cursor-pointer list-none text-xs text-muted underline-offset-4 hover:text-ink hover:underline [&::-webkit-details-marker]:hidden">Change</summary>
        <div class="mt-3">
            @include('site.notes.form', ['note' => $note, 'idPrefix' => 'note-'.$note->id])
        </div>
    </details>
</article>
