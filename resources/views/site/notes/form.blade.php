{{--
    Writing a note, or changing one. Expects optionally $note (to change it),
    $quick (the text alone, for the member's own page, titled with when it
    was written unless given one) and $idPrefix (for more than one form on
    a page). The
    form that comes back with an error keeps what was typed in it;
    "editing" tells which one it was. Away from the
    member's page, the note shows as it will read while it is written, with
    a switch for the text alone, both side by side, or the preview alone
    (see notes.ts).
--}}
@php
    $note ??= null;
    $quick ??= false;
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
        <div class="flex flex-wrap items-center gap-2">
            <label for="{{ $idPrefix }}-title" class="sr-only">Title</label>
            <input
                id="{{ $idPrefix }}-title"
                type="text"
                name="title"
                value="{{ $value('title', $note?->title) }}"
                maxlength="120"
                placeholder="Title (optional)"
                class="{{ $inputClass }} min-w-48 flex-1 font-medium"
            >

            {{-- Shown by the script, which draws the preview. --}}
            <div data-note-views hidden role="group" aria-label="Show" class="inline-flex shrink-0 gap-0.5 rounded-full border border-line bg-paper p-0.5 text-xs">
                @foreach (['write' => 'Write', 'split' => 'Both', 'preview' => 'Preview'] as $view => $label)
                    <button type="button" data-note-view="{{ $view }}" aria-pressed="false" class="rounded-full px-3 py-1.5 text-muted transition hover:text-ink aria-pressed:bg-card aria-pressed:text-ink aria-pressed:shadow-sm">{{ $label }}</button>
                @endforeach
            </div>
        </div>
    @endunless

    <div class="grid gap-3" @unless ($quick) data-note-preview="{{ route('notes.preview') }}" @endunless>
        <div data-note-write>
            <label for="{{ $idPrefix }}-body" class="sr-only">Note</label>
            <textarea
                id="{{ $idPrefix }}-body"
                name="body"
                rows="{{ $quick ? 3 : 12 }}"
                maxlength="{{ \App\Models\Note::MAX_LENGTH }}"
                required
                placeholder="{{ $quick ? 'Jot something down…' : 'Write in markdown: **bold**, - lists, [links](https://…)' }}"
                class="{{ $inputClass }} resize-y font-mono leading-relaxed"
            >{{ $value('body', $note?->body) }}</textarea>
        </div>

        @unless ($quick)
            {{-- Filled in by the script; hidden without it. --}}
            <div data-note-preview-pane hidden class="min-h-24 overflow-auto rounded-xl border border-dashed border-line px-4 py-3 lg:max-h-[70vh]">
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
