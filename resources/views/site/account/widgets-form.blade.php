{{--
    Choosing the cards at the top of the member's own page, and their order
    (see sortable.ts): on the account page, and in a dialog on the page
    itself. Expects $user. Saving comes back to where it was sent from.
--}}
@php
    // The chosen ones first, in their order, then the rest.
    $chosen = $user->homeWidgets();
    $widgets = collect($chosen)->merge(\App\Enums\HomeWidget::cases())->unique()->values();
@endphp

<form method="post" action="{{ route('account.widgets') }}">
    @csrf
    @method('put')

    <ol data-sortable class="space-y-2">
        @foreach ($widgets as $widget)
            <li data-sortable-item class="flex items-center gap-3 rounded-2xl border border-line bg-paper p-3">
                <label class="flex min-w-0 flex-1 cursor-pointer items-center gap-3">
                    <input type="checkbox" name="widgets[]" value="{{ $widget->value }}" @checked(in_array($widget, $chosen, true)) class="size-4 shrink-0 accent-(--accent)">
                    <span class="text-2xl leading-none" aria-hidden="true">{{ $widget->emoji() }}</span>
                    <span class="min-w-0 leading-tight">
                        <span class="block font-medium">{{ $widget->label() }}</span>
                        <span class="block text-sm text-muted">{{ $widget->description() }}</span>
                    </span>
                </label>
                {{-- Shown by the script (see sortable.ts), which moves the card. --}}
                <span data-sortable-buttons hidden class="flex shrink-0 gap-1">
                    <button type="button" data-move="up" aria-label="Move {{ $widget->label() }} up" title="Move up" class="grid size-8 place-items-center rounded-full border border-line text-muted transition hover:border-accent hover:text-ink disabled:pointer-events-none disabled:opacity-30">
                        <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M6 15l6-6 6 6"/></svg>
                    </button>
                    <button type="button" data-move="down" aria-label="Move {{ $widget->label() }} down" title="Move down" class="grid size-8 place-items-center rounded-full border border-line text-muted transition hover:border-accent hover:text-ink disabled:pointer-events-none disabled:opacity-30">
                        <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M6 9l6 6 6-6"/></svg>
                    </button>
                </span>
            </li>
        @endforeach
    </ol>

    @error('widgets.*', 'widgets')
        <p class="mt-3 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
    @enderror

    <div class="mt-5 flex flex-wrap items-center gap-2">
        <button type="submit" class="rounded-full bg-ink px-5 py-2.5 text-sm font-medium text-paper transition hover:bg-accent">Save</button>
        @if ($user->home_widgets !== null)
            <button type="submit" name="reset" value="1" class="rounded-full border border-line px-5 py-2.5 text-sm font-medium transition hover:border-accent">Back to the usual</button>
        @endif
    </div>
</form>
