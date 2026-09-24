@props(['name', 'label', 'value' => null, 'help' => null, 'required' => false, 'rows' => 4])
@php($id = $attributes->get('id', $name))
<div class="mb-3">
    <label for="{{ $id }}" class="form-label">{{ $label }} @if ($required)<span class="text-danger" aria-hidden="true">*</span>@endif</label>
    <textarea id="{{ $id }}" name="{{ $name }}" rows="{{ $rows }}" @required($required)
        {{ $attributes->except('id')->class(['form-control', 'is-invalid' => $errors->has($name)]) }}
        @if ($help || $errors->has($name)) aria-describedby="{{ $id }}-help {{ $id }}-error" @endif>{{ old($name, $value) }}</textarea>
    @if ($help)<div id="{{ $id }}-help" class="form-text">{{ $help }}</div>@endif
    @error($name)<div id="{{ $id }}-error" class="invalid-feedback">{{ $message }}</div>@enderror
</div>
