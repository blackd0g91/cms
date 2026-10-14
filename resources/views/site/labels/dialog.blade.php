{{--
    Managing the member's labels (see LabelController): each with its name
    and color, to change or delete, and a form for a new one. The color is
    picked from a few swatches or any other (see labels.ts). Opens again
    after each change, and when one comes back with an error. Expects
    $labels (the member's).
--}}
@php
    $labels ??= collect();
    $inputClass = 'block w-full min-w-0 rounded-xl border border-line bg-paper px-3 py-2 text-sm placeholder:text-muted focus:border-accent focus:ring-4 focus:ring-accent-soft focus:outline-none';
@endphp
<dialog
    id="labels"
    aria-labelledby="labels-title"
    @if (session('dialog') === 'labels' || $errors->label->any()) data-dialog-open-now @endif
    class="m-auto w-[calc(100%-2rem)] max-w-lg rounded-3xl border border-line bg-card p-0 text-ink shadow-2xl backdrop:bg-black/40 backdrop:backdrop-blur-sm"
>
    <div class="p-5 sm:p-6">
        <div class="flex items-start justify-between gap-4">
            <div>
                <h2 id="labels-title" class="font-display text-2xl font-semibold tracking-tight">Labels</h2>
                <p class="mt-1 text-sm text-muted">For your notes and reminders. Only you can see them.</p>
            </div>
            <button type="button" data-dialog-close aria-label="Close" class="-m-1.5 grid size-8 shrink-0 place-items-center rounded-full text-muted transition hover:bg-paper hover:text-ink">
                <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M18 6l-12 12M6 6l12 12"/></svg>
            </button>
        </div>

        @if ($errors->label->any())
            <p class="mt-3 text-sm text-red-600 dark:text-red-400">{{ $errors->label->first() }}</p>
        @endif

        @if ($labels->isNotEmpty())
            <ul class="mt-4 space-y-2">
                @foreach ($labels as $label)
                    <li class="flex items-center gap-1 rounded-2xl border border-line bg-paper/60 p-2">
                        <form method="post" action="{{ route('labels.update', $label) }}" class="flex min-w-0 flex-1 items-center gap-2">
                            @csrf
                            @method('put')
                            <label class="sr-only" for="label-{{ $label->id }}-color">Color</label>
                            <input id="label-{{ $label->id }}-color" type="color" name="color" value="{{ $label->color }}" class="label-color">
                            <label class="sr-only" for="label-{{ $label->id }}-name">Name</label>
                            <input id="label-{{ $label->id }}-name" name="name" value="{{ $label->name }}" maxlength="30" required class="{{ $inputClass }}">
                            <button type="submit" class="shrink-0 rounded-full border border-line px-4 py-2 text-sm transition hover:border-accent">Save</button>
                        </form>
                        <form method="post" action="{{ route('labels.destroy', $label) }}">
                            @csrf
                            @method('delete')
                            <button type="submit" title="Delete" class="grid size-8 shrink-0 place-items-center rounded-full text-muted transition hover:bg-paper hover:text-red-600">
                                <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M4 7h16M10 11v6M14 11v6M5.5 7l1 12a2 2 0 0 0 2 1.8h7a2 2 0 0 0 2-1.8l1-12M9 7V4.5a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1V7"/></svg>
                                <span class="sr-only">Delete: {{ $label->name }}</span>
                            </button>
                        </form>
                    </li>
                @endforeach
            </ul>
        @endif

        <form method="post" action="{{ route('labels.store') }}" class="mt-5 space-y-3 border-t border-line pt-4">
            @csrf
            <h3 class="text-sm font-medium">New label</h3>
            <div class="flex items-center gap-2">
                <label class="sr-only" for="label-new-color">Color</label>
                <input id="label-new-color" type="color" name="color" value="{{ old('color', \App\Models\Label::COLORS[$labels->count() % count(\App\Models\Label::COLORS)]) }}" class="label-color" data-label-color>
                <label class="sr-only" for="label-new-name">Name</label>
                <input id="label-new-name" name="name" value="{{ old('name') }}" maxlength="30" required placeholder="Work, Home, Ideas…" class="{{ $inputClass }}">
                <button type="submit" class="shrink-0 rounded-full bg-ink px-4 py-2 text-sm font-medium text-paper transition hover:bg-accent">Add</button>
            </div>
            {{-- Quick colors; the square above picks any other. --}}
            <div class="flex flex-wrap gap-1.5" data-label-swatches="label-new-color">
                @foreach (\App\Models\Label::COLORS as $color)
                    <button type="button" data-color="{{ $color }}" title="{{ $color }}" class="size-6 rounded-full border-2 border-card ring-1 ring-line transition hover:scale-110" style="background: {{ $color }}">
                        <span class="sr-only">Use {{ $color }}</span>
                    </button>
                @endforeach
            </div>
        </form>
    </div>
</dialog>
