@props(['name', 'label', 'type' => 'text', 'value' => null, 'help' => null, 'required' => false])
@php($id = $attributes->get('id', str_replace(['[', ']'], ['_', ''], $name)))
<div class="mb-3">
    <label for="{{ $id }}" class="form-label">{{ $label }} @if ($required)<span class="text-danger" aria-hidden="true">*</span>@endif</label>
    <input id="{{ $id }}" name="{{ $name }}" type="{{ $type }}" value="{{ old($name, $value) }}" @required($required)
        {{ $attributes->except('id')->class(['form-control', 'is-invalid' => $errors->has($name)]) }}
        @if ($help || $errors->has($name)) aria-describedby="{{ $id }}-help {{ $id }}-error" @endif>
    @if ($help)<div id="{{ $id }}-help" class="form-text">{{ $help }}</div>@endif
    @error($name)<div id="{{ $id }}-error" class="invalid-feedback">{{ $message }}</div>@enderror
</div>
