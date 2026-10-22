<div class="{{ $divClass }}">
    <div class="form-group">
        {{ Form::label($name, $label, ['class' => 'form-label']) }}@if ($required)
            <x-required></x-required>
        @endif
        {{ Form::text($name, $value, array_merge(['class' => $class, 'placeholder' => $placeholder, 'id' => $id, 'required' => $required], $attributes->getIterator()->getArrayCopy())) }}
        <div class=" text-xs text-muted mt-1">
            {{ __('Use country code (ex. +971). For multiple numbers, separate with comma (,)') }}
        </div>
    </div>
</div>
