{{-- Posts as a grid of cards. Expects $posts; $featureFirst makes the first one wide. --}}
@if ($posts->isEmpty())
    <div class="rounded-2xl border border-dashed border-line p-10 text-center">
        <p class="font-display text-2xl italic">Blank pages, for now.</p>
        <p class="mt-1 text-sm text-muted">{{ $empty ?? 'Nothing has been published here yet.' }}</p>
    </div>
@else
    <div class="grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
        @foreach ($posts as $post)
            @include('site.partials.post-card', ['post' => $post, 'featured' => ($featureFirst ?? false) && $loop->first])
        @endforeach
    </div>
@endif
