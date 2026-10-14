{{--
    A dialog on the site, opened by a button with data-dialog-open="{id}"
    (see dialogs.ts): a title, a close button and what is inside. $open
    opens it as the page does, like when its form comes back with an error.
--}}
@props(['id', 'title', 'open' => false, 'width' => 'max-w-md'])
<dialog
    id="{{ $id }}"
    aria-labelledby="{{ $id }}-title"
    @if ($open) data-dialog-open-now @endif
    class="m-auto w-[calc(100%-2rem)] {{ $width }} rounded-3xl border border-line bg-card p-0 text-left text-ink shadow-2xl backdrop:bg-black/40 backdrop:backdrop-blur-sm"
>
    <div class="p-5 sm:p-6">
        <div class="flex items-start justify-between gap-4">
            <h2 id="{{ $id }}-title" class="font-display text-2xl font-semibold tracking-tight">{{ $title }}</h2>
            <button type="button" data-dialog-close aria-label="Close" class="-m-1.5 grid size-8 shrink-0 place-items-center rounded-full text-muted transition hover:bg-paper hover:text-ink">
                <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M18 6l-12 12M6 6l12 12"/></svg>
            </button>
        </div>

        <div class="mt-4">
            {{ $slot }}
        </div>
    </div>
</dialog>
