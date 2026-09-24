{{-- $options: [value => label] or a list of ['value' => , 'label' => , 'group' => ] for dependent selects (data-sub-county). --}}
@props(['name', 'label', 'options' => [], 'value' => null, 'help' => null, 'required' => false, 'placeholder' => 'Select...', 'groupAttribute' => null])
@php($id = $attributes->get('id', $name))
@php($selected = (string) old($name, $value))
<div class="mb-3">
    <label for="{{ $id }}" class="form-label">{{ $label }} @if ($required)<span class="text-danger" aria-hidden="true">*</span>@endif</label>
    <select id="{{ $id }}" name="{{ $name }}" @required($required)
        {{ $attributes->except('id')->class(['form-select', 'is-invalid' => $errors->has($name)]) }}
        @if ($help || $errors->has($name)) aria-describedby="{{ $id }}-help {{ $id }}-error" @endif>
        <option value="">{{ $placeholder }}</option>
        @foreach ($options as $key => $option)
            @if (is_array($option))
                <option value="{{ $option['value'] }}" @if ($groupAttribute) data-{{ $groupAttribute }}="{{ $option['group'] }}" @endif @selected($selected === (string) $option['value'])>{{ $option['label'] }}</option>
            @else
                <option value="{{ $key }}" @selected($selected === (string) $key)>{{ $option }}</option>
            @endif
        @endforeach
    </select>
    @if ($help)<div id="{{ $id }}-help" class="form-text">{{ $help }}</div>@endif
    @error($name)<div id="{{ $id }}-error" class="invalid-feedback">{{ $message }}</div>@enderror
</div>
