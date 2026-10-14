{{--
    Writing a note, or changing one. Expects optionally $note (to change it),
    $quick (the text alone, for the member's own page) and $idPrefix (for
    more than one form on a page). A new note keeps what was typed when it
    comes back with an error.
--}}
@php
    $note ??= null;
    $quick ??= false;
    $idPrefix ??= 'note';
    $inputClass = 'block w-full min-w-0 rounded-xl border border-line bg-paper px-3 py-2 text-sm placeholder:text-muted focus:border-accent focus:ring-4 focus:ring-accent-soft focus:outline-none';
@endphp
<form method="post" action="{{ $note ? route('notes.update', $note) : route('notes.store') }}" class="space-y-3">
    @csrf
    @if ($note)
        @method('put')
    @endif

    @unless ($quick)
        <div>
            <label for="{{ $idPrefix }}-title" class="sr-only">Title</label>
            <input
                id="{{ $idPrefix }}-title"
                type="text"
                name="title"
                value="{{ $note ? $note->title : old('title') }}"
                maxlength="120"
                placeholder="Title (optional)"
                class="{{ $inputClass }} font-medium"
            >
        </div>
    @endunless

    <div>
        <label for="{{ $idPrefix }}-body" class="sr-only">Note</label>
        <textarea
            id="{{ $idPrefix }}-body"
            name="body"
            rows="{{ $quick ? 3 : ($note ? 10 : 5) }}"
            maxlength="{{ \App\Models\Note::MAX_LENGTH }}"
            required
            placeholder="{{ $quick ? 'Jot something down…' : 'Write in markdown: **bold**, - lists, [links](https://…)' }}"
            class="{{ $inputClass }} resize-y font-mono leading-relaxed"
        >{{ $note ? $note->body : old('body') }}</textarea>
    </div>

    <div class="flex items-center justify-between gap-3">
        <p class="text-xs text-muted">Markdown works. Only you can see your notes.</p>
        <button type="submit" class="shrink-0 rounded-full bg-ink px-5 py-2 text-sm font-medium text-paper transition hover:bg-accent">
            {{ $note ? 'Save' : 'Add note' }}
        </button>
    </div>
</form>
