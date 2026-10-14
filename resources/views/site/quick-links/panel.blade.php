{{--
    A member's quick links on their own page (see QuickLinkController): a
    row of tiles, opening in a new tab so the page stays open, and a dialog
    to add, change, move and delete them, which opens again after each
    change. Expects $quickLinks.
--}}
@php
    $inputClass = 'block w-full min-w-0 rounded-xl border border-line bg-paper px-3 py-2 text-sm placeholder:text-muted focus:border-accent focus:ring-4 focus:ring-accent-soft focus:outline-none';
    $iconButton = 'grid size-8 shrink-0 place-items-center rounded-full text-muted transition hover:bg-paper hover:text-ink disabled:pointer-events-none disabled:opacity-30';
@endphp
<section aria-labelledby="quick-links-title">
    <div class="mb-3 flex items-center gap-4">
        <h2 id="quick-links-title" class="font-mono text-xs tracking-wider text-muted uppercase">Quick links</h2>
        <span class="h-px flex-1 bg-line" aria-hidden="true"></span>
        @if ($quickLinks->isNotEmpty())
            <button type="button" data-dialog-open="quick-links" aria-haspopup="dialog" class="text-xs text-muted underline-offset-4 hover:text-ink hover:underline">Edit</button>
        @endif
    </div>

    <ul class="grid grid-cols-3 gap-2 sm:grid-cols-4 lg:grid-cols-6 xl:grid-cols-8">
        @foreach ($quickLinks as $link)
            <li>
                <a
                    href="{{ $link->url }}"
                    target="_blank"
                    rel="noopener noreferrer"
                    title="{{ $link->url }}"
                    class="flex h-full flex-col items-center justify-center gap-1 rounded-xl border border-line bg-card px-1.5 py-2.5 text-center shadow-sm transition hover:-translate-y-0.5 hover:border-accent"
                >
                    @if ($link->emoji)
                        <span class="text-xl leading-none" aria-hidden="true">{{ $link->emoji }}</span>
                    @else
                        <span class="font-display text-xl leading-none font-semibold" aria-hidden="true">{{ mb_strtoupper(mb_substr($link->label, 0, 1)) }}</span>
                    @endif
                    <span class="w-full truncate text-xs">{{ $link->label }}</span>
                </a>
            </li>
        @endforeach
        <li>
            <button
                type="button"
                data-dialog-open="quick-links"
                aria-haspopup="dialog"
                class="flex h-full min-h-16 w-full flex-col items-center justify-center gap-1 rounded-xl border border-dashed border-line px-1.5 py-2.5 text-center text-muted transition hover:border-accent hover:text-ink"
            >
                <svg class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" aria-hidden="true"><path d="M12 5v14M5 12h14"/></svg>
                <span class="text-xs">{{ $quickLinks->isEmpty() ? 'Add a link' : 'Add' }}</span>
            </button>
        </li>
    </ul>
</section>

{{-- Opened again after each change, and when one comes back with an error. --}}
<dialog
    id="quick-links"
    aria-labelledby="quick-links-dialog-title"
    @if (session('dialog') === 'quick-links' || $errors->quickLink->any()) data-dialog-open-now @endif
    class="m-auto w-[calc(100%-2rem)] max-w-2xl rounded-3xl border border-line bg-card p-0 text-ink shadow-2xl backdrop:bg-black/40 backdrop:backdrop-blur-sm"
>
    <div class="p-5 sm:p-6">
        <div class="flex items-start justify-between gap-4">
            <div>
                <h2 id="quick-links-dialog-title" class="font-display text-2xl font-semibold tracking-tight">Quick links</h2>
                <p class="mt-1 text-sm text-muted">Sites you open often, as tiles on your page. Only you can see them.</p>
            </div>
            <button type="button" data-dialog-close aria-label="Close" class="-m-1.5 grid size-8 shrink-0 place-items-center rounded-full text-muted transition hover:bg-paper hover:text-ink">
                <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M18 6l-12 12M6 6l12 12"/></svg>
            </button>
        </div>

        @if ($errors->quickLink->any())
            <p class="mt-3 text-sm text-red-600 dark:text-red-400">{{ $errors->quickLink->first() }}</p>
        @endif

        @if ($quickLinks->isNotEmpty())
            <ol class="mt-4 space-y-2">
                @foreach ($quickLinks as $link)
                    <li class="flex items-center gap-1 rounded-2xl border border-line bg-paper/60 p-2">
                        <form method="post" action="{{ route('quick-links.update', $link) }}" class="grid min-w-0 flex-1 grid-cols-[3rem_minmax(0,1fr)] gap-2 sm:grid-cols-[3rem_8rem_minmax(0,1fr)_auto]">
                            @csrf
                            @method('put')
                            <label class="sr-only" for="quick-link-{{ $link->id }}-emoji">Emoji</label>
                            <input id="quick-link-{{ $link->id }}-emoji" name="emoji" value="{{ $link->emoji }}" maxlength="32" placeholder="🔗" class="{{ $inputClass }} text-center">
                            <label class="sr-only" for="quick-link-{{ $link->id }}-label">Name</label>
                            <input id="quick-link-{{ $link->id }}-label" name="label" value="{{ $link->label }}" maxlength="40" required class="{{ $inputClass }}">
                            <label class="sr-only" for="quick-link-{{ $link->id }}-url">Address</label>
                            <input id="quick-link-{{ $link->id }}-url" name="url" value="{{ $link->url }}" inputmode="url" required class="{{ $inputClass }} col-span-2 sm:col-span-1">
                            <button type="submit" class="col-span-2 rounded-full border border-line px-4 py-2 text-sm transition hover:border-accent sm:col-span-1">Save</button>
                        </form>
                        <div class="flex shrink-0 flex-col sm:flex-row">
                            @foreach (['up' => ['M6 15l6-6 6 6', 'Move up'], 'down' => ['M6 9l6 6 6-6', 'Move down']] as $direction => [$path, $label])
                                <form method="post" action="{{ route('quick-links.move', $link) }}">
                                    @csrf
                                    <input type="hidden" name="direction" value="{{ $direction }}">
                                    <button type="submit" title="{{ $label }}" class="{{ $iconButton }}" @disabled($direction === 'up' ? $loop->parent->first : $loop->parent->last)>
                                        <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="{{ $path }}"/></svg>
                                        <span class="sr-only">{{ $label }}: {{ $link->label }}</span>
                                    </button>
                                </form>
                            @endforeach
                            <form method="post" action="{{ route('quick-links.destroy', $link) }}">
                                @csrf
                                @method('delete')
                                <button type="submit" title="Delete" class="{{ $iconButton }} hover:text-red-600">
                                    <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M4 7h16M10 11v6M14 11v6M5.5 7l1 12a2 2 0 0 0 2 1.8h7a2 2 0 0 0 2-1.8l1-12M9 7V4.5a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1V7"/></svg>
                                    <span class="sr-only">Delete: {{ $link->label }}</span>
                                </button>
                            </form>
                        </div>
                    </li>
                @endforeach
            </ol>
        @endif

        <form method="post" action="{{ route('quick-links.store') }}" class="mt-5 border-t border-line pt-4">
            @csrf
            <h3 class="mb-2 text-sm font-medium">Add a link</h3>
            <div class="grid grid-cols-[3rem_minmax(0,1fr)] gap-2 sm:grid-cols-[3rem_8rem_minmax(0,1fr)_auto]">
                <label class="sr-only" for="quick-link-new-emoji">Emoji</label>
                <input id="quick-link-new-emoji" name="emoji" value="{{ old('emoji') }}" maxlength="32" placeholder="🔗" class="{{ $inputClass }} text-center">
                <label class="sr-only" for="quick-link-new-label">Name</label>
                <input id="quick-link-new-label" name="label" value="{{ old('label') }}" maxlength="40" required placeholder="Name" class="{{ $inputClass }}">
                <label class="sr-only" for="quick-link-new-url">Address</label>
                <input id="quick-link-new-url" name="url" value="{{ old('url') }}" inputmode="url" required placeholder="github.com" class="{{ $inputClass }} col-span-2 sm:col-span-1">
                <button type="submit" class="col-span-2 rounded-full bg-ink px-4 py-2 text-sm font-medium text-paper transition hover:bg-accent sm:col-span-1">Add</button>
            </div>
        </form>
    </div>
</dialog>
