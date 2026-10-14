{{--
    The member's labels above a list, to show only what has one, and a
    button to manage them (see site/labels/dialog.blade.php). Expects $labels
    (the member's), $label (the one shown, or null) and $route (the list's
    route name).
--}}
@php
    $labels ??= collect();
@endphp
<div class="flex flex-wrap items-center gap-1.5">
    @if ($labels->isNotEmpty())
        <a href="{{ route($route) }}" @class([
            'rounded-full border px-2.5 py-0.5 text-xs transition',
            'border-ink bg-ink text-paper' => ! $label,
            'border-line text-muted hover:border-accent hover:text-ink' => $label,
        ])>All</a>
        @foreach ($labels as $each)
            <a
                href="{{ route($route, ['label' => $each->id]) }}"
                @class(['label-chip transition', 'label-chip-on' => $label?->is($each)])
                style="--label: {{ $each->color }}"
                @if ($label?->is($each)) aria-current="page" @endif
            >{{ $each->name }}</a>
        @endforeach
    @endif
    <button type="button" data-dialog-open="labels" aria-haspopup="dialog" class="inline-flex items-center gap-1 rounded-full border border-dashed border-line px-2.5 py-0.5 text-xs text-muted transition hover:border-accent hover:text-ink">
        {{ $labels->isEmpty() ? '+ Make labels' : 'Edit labels' }}
    </button>
</div>
