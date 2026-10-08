{{-- Posts as a compact list with excerpts. Expects $posts (template and thumbnail loaded) and $excerpt. --}}
<ul class="space-y-3">
    @forelse ($posts as $post)
        <li class="hue" style="{{ $post->template->accentStyle() }}">
            <a href="{{ $post->url() }}" class="group flex gap-4 rounded-2xl border border-(--tint-line) bg-(--tint) p-4 transition hover:border-accent">
                @include('site.partials.thumbnail', ['post' => $post, 'class' => 'size-16 shrink-0 rounded-xl', 'sizes' => '64px'])
                <div class="min-w-0">
                    <p class="flex items-center gap-2 font-mono text-[11px] tracking-wider text-muted uppercase">
                        <span class="size-2 rounded-full bg-accent"></span>
                        {{ $post->template->name }} &middot; {{ $post->published_at?->format('M j, Y') }}
                    </p>
                    <p class="mt-1 font-display text-lg font-semibold decoration-accent decoration-2 underline-offset-4 group-hover:underline">{{ $post->title }}</p>
                    @php($snippet = $excerpt($post))
                    @if ($snippet->toHtml() !== '')
                        <p class="mt-1 text-sm text-muted [&_mark]:rounded-sm [&_mark]:bg-accent-soft [&_mark]:px-0.5 [&_mark]:text-ink">
                            {{ $snippet }}
                        </p>
                    @endif
                </div>
            </a>
        </li>
    @empty
        <li class="rounded-2xl border border-dashed border-line p-10 text-center">
            <p class="font-display text-2xl italic">Nothing turned up.</p>
            <p class="mt-1 text-sm text-muted">{{ $empty ?? 'Try different or fewer words.' }}</p>
        </li>
    @endforelse
</ul>
