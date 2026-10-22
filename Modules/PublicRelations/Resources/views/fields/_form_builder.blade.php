{{-- Renders `custom_fields[...]` inputs from a Collection<PrField> $prFields.
     Optional $customFieldValues: Collection keyed by field->key => ['value' => ...]. --}}
@if (isset($prFields) && $prFields->isNotEmpty())
    <div class="row">
        @foreach ($prFields as $prField)
            @php
                $fieldName = 'custom_fields.' . $prField->key;
                $stored = ($customFieldValues ?? collect())->get($prField->key)['value'] ?? null;
                $isCheckbox = $prField->type->value === 'checkbox';
                $current = old($fieldName, $stored ?? ($isCheckbox ? [] : null));
                $inputId = 'custom_fields-' . $prField->key;
            @endphp
            <div class="col-md-6 col-12 form-group">
                <label for="{{ $inputId }}" class="form-label">
                    {{ $prField->label }}
                    @if ($prField->is_required)<x-required></x-required>@endif
                </label>

                @if ($prField->type->value === 'textarea')
                    <textarea name="custom_fields[{{ $prField->key }}]" id="{{ $inputId }}" class="form-control"
                        {{ $prField->is_required ? 'required' : '' }}>{{ $current }}</textarea>
                @elseif ($prField->type->value === 'select')
                    <select name="custom_fields[{{ $prField->key }}]" id="{{ $inputId }}"
                        class="form-control select2" {{ $prField->is_required ? 'required' : '' }}>
                        <option value="">{{ __('Select') }} {{ $prField->label }}</option>
                        @foreach ($prField->options ?? [] as $option)
                            <option value="{{ $option }}" {{ (string) $current === (string) $option ? 'selected' : '' }}>
                                {{ $option }}
                            </option>
                        @endforeach
                    </select>
                @elseif ($prField->type->value === 'checkbox')
                    <div>
                        @foreach ($prField->options ?? [] as $option)
                            <div class="form-check form-check-inline">
                                <input type="checkbox" class="form-check-input"
                                    name="custom_fields[{{ $prField->key }}][]" id="{{ $inputId }}-{{ $loop->index }}"
                                    value="{{ $option }}" {{ is_array($current) && in_array($option, $current) ? 'checked' : '' }}>
                                <label class="form-check-label" for="{{ $inputId }}-{{ $loop->index }}">{{ $option }}</label>
                            </div>
                        @endforeach
                    </div>
                @elseif ($prField->type->value === 'number')
                    <input type="number" name="custom_fields[{{ $prField->key }}]" id="{{ $inputId }}"
                        class="form-control" value="{{ $current }}" {{ $prField->is_required ? 'required' : '' }}>
                @elseif ($prField->type->value === 'date')
                    <input type="date" name="custom_fields[{{ $prField->key }}]" id="{{ $inputId }}"
                        class="form-control" value="{{ $current }}" {{ $prField->is_required ? 'required' : '' }}>
                @else
                    <input type="text" name="custom_fields[{{ $prField->key }}]" id="{{ $inputId }}"
                        class="form-control" value="{{ $current }}" {{ $prField->is_required ? 'required' : '' }}>
                @endif

                @error($fieldName)
                    <small class="text-danger">{{ $message }}</small>
                @enderror
            </div>
        @endforeach
    </div>
@endif
