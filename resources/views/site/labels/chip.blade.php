{{--
    A label, as a small chip in its color (see .label-chip in site.css): a
    link to what has it when given $href. Expects $label.
--}}
@if (isset($href))
    <a href="{{ $href }}" class="label-chip transition hover:brightness-95" style="--label: {{ $label->color }}">{{ $label->name }}</a>
@else
    <span class="label-chip" style="--label: {{ $label->color }}">{{ $label->name }}</span>
@endif
