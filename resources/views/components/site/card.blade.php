{{--
    A small card in the row at the top of the home and member's pages (see
    site/partials/cards.blade.php), opening its dialog: an emoji, a small
    label and a line of text. Without the script it follows $href instead.
--}}
@props(['dialog', 'href', 'emoji', 'label'])
<a
    href="{{ $href }}"
    data-dialog-open="{{ $dialog }}"
    aria-haspopup="dialog"
    class="group flex min-w-0 items-center gap-3 rounded-2xl border border-line bg-card p-3 shadow-sm transition hover:border-accent"
>
    <span class="text-2xl leading-none" aria-hidden="true">{{ $emoji }}</span>
    <span class="min-w-0 flex-1 leading-tight">
        <span class="block font-mono text-[10px] tracking-wider text-muted uppercase">{{ $label }}</span>
        <span class="block truncate text-sm font-medium">{{ $slot }}</span>
    </span>
    <svg class="size-4 shrink-0 text-muted transition group-hover:translate-x-0.5 group-hover:text-ink" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M9 6l6 6-6 6"/></svg>
</a>
