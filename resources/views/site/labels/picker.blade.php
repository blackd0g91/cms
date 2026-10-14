{{--
    Picking labels for a note or reminder: the member's labels as chips to
    tick. Nothing without labels yet; they are made from the notes and
    reminders pages (see site/labels/dialog.blade.php). Expects $labels (the
    member's), $selected (label ids) and $idPrefix.
--}}
@php
    $labels ??= collect();
@endphp
@if ($labels->isNotEmpty())
    <fieldset>
        <legend class="sr-only">Labels</legend>
        {{-- So that unticking every label is saved too (see SyncsLabels). --}}
        <input type="hidden" name="with_labels" value="1">
        <div class="flex flex-wrap gap-1.5">
            @foreach ($labels as $label)
                <label class="cursor-pointer">
                    <input type="checkbox" name="labels[]" value="{{ $label->id }}" id="{{ $idPrefix }}-label-{{ $label->id }}" @checked(in_array($label->id, $selected, true)) class="peer sr-only">
                    <span class="label-chip label-pick" style="--label: {{ $label->color }}">{{ $label->name }}</span>
                </label>
            @endforeach
        </div>
    </fieldset>
@endif
