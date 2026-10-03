@props(['name', 'label', 'type' => 'text', 'value' => null, 'help' => null, 'required' => false])
@php($id = $attributes->get('id', str_replace(['[', ']'], ['_', ''], $name)))
@php($isPassword = $type === 'password')
<div class="mb-3">
    <label for="{{ $id }}" class="form-label">{{ $label }} @if ($required)<span class="text-danger" aria-hidden="true">*</span>@endif</label>
    @if ($isPassword)<div class="input-group has-validation">@endif
    <input id="{{ $id }}" name="{{ $name }}" type="{{ $type }}" @unless ($isPassword) value="{{ old($name, $value) }}" @endunless @required($required)
        {{ $attributes->except('id')->class(['form-control', 'is-invalid' => $errors->has($name)]) }}
        @if ($help || $errors->has($name)) aria-describedby="{{ $id }}-help {{ $id }}-error" @endif>
    @if ($isPassword)
        <button type="button" class="btn btn-outline-secondary" data-password-toggle="{{ $id }}" aria-controls="{{ $id }}" aria-pressed="false" aria-label="Show password">
            <i class="bi bi-eye" aria-hidden="true"></i>
        </button>
    @endif
    @error($name)<div id="{{ $id }}-error" class="invalid-feedback">{{ $message }}</div>@enderror
    @if ($isPassword)</div>@endif
    @if ($help)<div id="{{ $id }}-help" class="form-text">{{ $help }}</div>@endif
</div>
