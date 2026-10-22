@extends('layouts.admin')

@section('page-title')
    {{ __('Create Custom Field') }}
@endsection

@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">{{ __('Home') }}</a></li>
    <li class="breadcrumb-item"><a href="{{ route('ticket-management.index') }}">{{ __('Public Tickets') }}</a></li>
    <li class="breadcrumb-item"><a href="{{ route('ticket-management.custom-fields') }}">{{ __('Custom Fields') }}</a></li>
    <li class="breadcrumb-item">{{ __('Create') }}</li>
@endsection

@section('content')
<div class="row">
    <div class="col-lg-8">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">{{ __('Create New Custom Field') }}</h5>
            </div>
            <div class="card-body">
                <form action="{{ route('ticket-management.custom-fields.store') }}" method="POST">
                    @csrf
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="name" class="form-label">{{ __('Field Name (English)') }} <span class="text-danger">*</span></label>
                            <input type="text" class="form-control @error('name') is-invalid @enderror" 
                                id="name" name="name" value="{{ old('name') }}" required>
                            @error('name')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="name_ar" class="form-label">{{ __('Field Name (Arabic)') }}</label>
                            <input type="text" class="form-control" id="name_ar" name="name_ar" 
                                value="{{ old('name_ar') }}" dir="rtl">
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="type" class="form-label">{{ __('Field Type') }} <span class="text-danger">*</span></label>
                        <select class="form-select @error('type') is-invalid @enderror" id="type" name="type" required>
                            <option value="">{{ __('Select Type') }}</option>
                            @foreach($fieldTypes as $key => $label)
                                <option value="{{ $key }}" {{ old('type') == $key ? 'selected' : '' }}>{{ $label }}</option>
                            @endforeach
                        </select>
                        @error('type')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="mb-3" id="optionsContainer" style="display: none;">
                        <label for="options" class="form-label">{{ __('Options') }}</label>
                        <textarea class="form-control" id="options" name="options" rows="4" 
                            placeholder="{{ __('Enter each option on a new line') }}">{{ old('options') }}</textarea>
                        <small class="text-muted">{{ __('Enter each option on a new line. Required for Select, Radio, and Checkbox types.') }}</small>
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="placeholder" class="form-label">{{ __('Placeholder (English)') }}</label>
                            <input type="text" class="form-control" id="placeholder" name="placeholder" 
                                value="{{ old('placeholder') }}">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="placeholder_ar" class="form-label">{{ __('Placeholder (Arabic)') }}</label>
                            <input type="text" class="form-control" id="placeholder_ar" name="placeholder_ar" 
                                value="{{ old('placeholder_ar') }}" dir="rtl">
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" id="is_required" name="is_required">
                                <label class="form-check-label" for="is_required">{{ __('Required Field') }}</label>
                            </div>
                        </div>
                        <div class="col-md-6 mb-3">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" id="is_active" name="is_active" checked>
                                <label class="form-check-label" for="is_active">{{ __('Active') }}</label>
                            </div>
                        </div>
                    </div>

                    <div class="text-end">
                        <a href="{{ route('ticket-management.custom-fields') }}" class="btn btn-secondary">{{ __('Cancel') }}</a>
                        <button type="submit" class="btn btn-primary">
                            <i class="ti ti-device-floppy me-2"></i>{{ __('Create Field') }}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection

@section('script')
<script>
document.addEventListener('DOMContentLoaded', function() {
    const typeSelect = document.getElementById('type');
    const optionsContainer = document.getElementById('optionsContainer');
    const optionTypes = ['select', 'radio', 'checkbox'];
    
    typeSelect.addEventListener('change', function() {
        if (optionTypes.includes(this.value)) {
            optionsContainer.style.display = 'block';
        } else {
            optionsContainer.style.display = 'none';
        }
    });
    
    // Trigger on page load if type is already selected
    if (optionTypes.includes(typeSelect.value)) {
        optionsContainer.style.display = 'block';
    }
});
</script>
@endsection
