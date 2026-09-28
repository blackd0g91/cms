{{-- The post's headings. Expects $headings; site.ts highlights the current one. --}}
<nav aria-label="On this page" data-toc>
    <p class="mb-3 font-mono text-[11px] tracking-widest text-muted uppercase">On this page</p>
    <ol class="space-y-1 border-l border-line text-sm">
        @foreach ($headings as $heading)
            <li>
                <a
                    href="#{{ $heading['id'] }}"
                    @class([
                        '-ml-px block border-l-2 border-transparent py-1 text-muted transition hover:text-ink data-active:border-accent data-active:text-ink',
                        'pl-4' => $heading['level'] === 2,
                        'pl-8 text-[13px]' => $heading['level'] === 3,
                    ])
                >
                    {{ $heading['text'] }}
                </a>
            </li>
        @endforeach
    </ol>
</nav>
