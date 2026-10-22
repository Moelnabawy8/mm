@extends('layouts.admin')

@section('page-title')
    {{ __('Edit Category') }}
@endsection

@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">{{ __('Home') }}</a></li>
    <li class="breadcrumb-item"><a href="{{ route('ticket-management.index') }}">{{ __('Public Tickets') }}</a></li>
    <li class="breadcrumb-item"><a href="{{ route('ticket-management.categories') }}">{{ __('Categories') }}</a></li>
    <li class="breadcrumb-item">{{ __('Edit') }}</li>
@endsection

@section('content')
<div class="row">
    <div class="col-lg-8">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">{{ __('Edit Category') }}</h5>
            </div>
            <div class="card-body">
                <form action="{{ route('ticket-management.categories.update', $category->id) }}" method="POST">
                    @csrf
                    @method('PUT')
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="name" class="form-label">{{ __('Name (English)') }} <span class="text-danger">*</span></label>
                            <input type="text" class="form-control @error('name') is-invalid @enderror" 
                                id="name" name="name" value="{{ old('name', $category->name) }}" required>
                            @error('name')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="name_ar" class="form-label">{{ __('Name (Arabic)') }}</label>
                            <input type="text" class="form-control" id="name_ar" name="name_ar" 
                                value="{{ old('name_ar', $category->name_ar) }}" dir="rtl">
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="description" class="form-label">{{ __('Description') }}</label>
                        <textarea class="form-control" id="description" name="description" rows="3">{{ old('description', $category->description) }}</textarea>
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="color" class="form-label">{{ __('Color') }} <span class="text-danger">*</span></label>
                            <input type="color" class="form-control form-control-color w-100 @error('color') is-invalid @enderror" 
                                id="color" name="color" value="{{ old('color', $category->color) }}" required>
                            @error('color')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="icon" class="form-label">{{ __('Icon Class') }}</label>
                            <input type="text" class="form-control" id="icon" name="icon" 
                                value="{{ old('icon', $category->icon) }}" placeholder="e.g., ti ti-help-circle">
                            <small class="text-muted">{{ __('Use Tabler Icons class name') }}</small>
                        </div>
                    </div>

                    <div class="mb-3">
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" id="is_active" name="is_active" 
                                {{ $category->is_active ? 'checked' : '' }}>
                            <label class="form-check-label" for="is_active">{{ __('Active') }}</label>
                        </div>
                    </div>

                    <div class="text-end">
                        <a href="{{ route('ticket-management.categories') }}" class="btn btn-secondary">{{ __('Cancel') }}</a>
                        <button type="submit" class="btn btn-primary">
                            <i class="ti ti-device-floppy me-2"></i>{{ __('Update Category') }}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
