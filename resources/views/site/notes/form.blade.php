{{--
    Writing a note, or changing one. Expects optionally $note (to change it),
    $quick (the text alone, for the member's own page, titled with when it
    was written unless given one), $wide (the preview beside the text rather
    than under it) and $idPrefix (for more than one form on a page). The
    form that comes back with an error keeps what was typed in it;
    "editing" tells which one it was. Away from the
    member's page, the note shows as it will read while it is written (see
    notes.ts).
--}}
@php
    $note ??= null;
    $quick ??= false;
    $wide ??= false;
    $idPrefix ??= 'note';
    $editing = $note ? 'note-'.$note->id : null;
    $sentBack = session()->hasOldInput() && old('editing') === $editing;
    $value = fn (string $key, mixed $current) => $sentBack ? old($key, $current) : $current;
    $inputClass = 'block w-full min-w-0 rounded-xl border border-line bg-paper px-3 py-2 text-sm placeholder:text-muted focus:border-accent focus:ring-4 focus:ring-accent-soft focus:outline-none';
@endphp
<form method="post" action="{{ $note ? route('notes.update', $note) : route('notes.store') }}" class="space-y-3">
    @csrf
    @if ($note)
        @method('put')
        <input type="hidden" name="editing" value="{{ $editing }}">
    @endif
    @if ($quick)
        <input type="hidden" name="quick" value="1">
    @endif

    @unless ($quick)
        <div>
            <label for="{{ $idPrefix }}-title" class="sr-only">Title</label>
            <input
                id="{{ $idPrefix }}-title"
                type="text"
                name="title"
                value="{{ $value('title', $note?->title) }}"
                maxlength="120"
                placeholder="Title (optional)"
                class="{{ $inputClass }} font-medium"
            >
        </div>
    @endunless

    <div @class(['grid gap-3', 'lg:grid-cols-2' => $wide]) @unless ($quick) data-note-preview="{{ route('notes.preview') }}" @endunless>
        <div>
            <label for="{{ $idPrefix }}-body" class="sr-only">Note</label>
            <textarea
                id="{{ $idPrefix }}-body"
                name="body"
                rows="{{ $quick ? 3 : ($note || $wide ? 10 : 5) }}"
                maxlength="{{ \App\Models\Note::MAX_LENGTH }}"
                required
                placeholder="{{ $quick ? 'Jot something down…' : 'Write in markdown: **bold**, - lists, [links](https://…)' }}"
                class="{{ $inputClass }} resize-y font-mono leading-relaxed"
            >{{ $value('body', $note?->body) }}</textarea>
        </div>

        @unless ($quick)
            {{-- Filled in by the script; hidden without it. --}}
            <div data-note-preview-pane hidden class="min-h-24 overflow-auto rounded-xl border border-dashed border-line px-4 py-3">
                <p class="mb-2 font-mono text-[10px] tracking-wider text-muted uppercase">Preview</p>
                <div data-note-preview-output class="prose prose-sm max-w-none break-words" aria-live="polite"></div>
            </div>
        @endunless
    </div>

    @unless ($quick)
        @include('site.labels.picker', [
            'selected' => array_map('intval', (array) $value('labels', $note ? $note->labels->modelKeys() : [])),
        ])
    @endunless

    <div class="flex items-center justify-between gap-3">
        <p class="text-xs text-muted">Markdown works. Only you can see your notes.</p>
        <button type="submit" class="shrink-0 rounded-full bg-ink px-5 py-2 text-sm font-medium text-paper transition hover:bg-accent">
            {{ $note ? 'Save' : 'Add note' }}
        </button>
    </div>
</form>
