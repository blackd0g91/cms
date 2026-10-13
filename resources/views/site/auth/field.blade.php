{{--
    A labelled text field with its error. Expects $name, $label and $type,
    and optionally $value, $autocomplete, $hint and $autofocus.
--}}
@php($id = 'account-'.$name)
<div>
    <label for="{{ $id }}" class="block text-sm font-medium">{{ $label }}</label>
    <input
        id="{{ $id }}"
        name="{{ $name }}"
        type="{{ $type }}"
        value="{{ $value ?? '' }}"
        @isset($autocomplete) autocomplete="{{ $autocomplete }}" @endisset
        @if ($autofocus ?? false) autofocus @endif
        required
        @error($name) aria-invalid="true" aria-describedby="{{ $id }}-error" @enderror
        class="mt-1.5 block w-full rounded-xl border border-line bg-paper px-4 py-2.5 text-sm placeholder:text-muted focus:border-accent focus:ring-4 focus:ring-accent-soft focus:outline-none aria-invalid:border-red-500"
    >
    @error($name)
        <p id="{{ $id }}-error" class="mt-1.5 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
    @elseif (isset($hint))
        <p class="mt-1.5 text-xs text-muted">{{ $hint }}</p>
    @enderror
</div>
