@extends('layouts.admin')

@section('page-title')
    {{ __('PR Custom Fields') }}
@endsection

@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">{{ __('Dashboard') }}</a></li>
    <li class="breadcrumb-item"><a href="{{ route('pr.index') }}">{{ __('Public Relationships') }}</a></li>
    <li class="breadcrumb-item">{{ __('Custom Fields') }}</li>
@endsection

@section('action-btn')
    <div class="float-end">
        @can('manage pr custom field')
            <a href="#" data-size="md" data-url="{{ route('pr.field.create') }}?module={{ $module->value }}"
                data-ajax-popup="true" data-title="{{ __('Add Custom Field') }}" data-bs-toggle="tooltip"
                title="{{ __('Add Custom Field') }}" class="btn btn-sm btn-primary">
                <i class="ti ti-plus"></i>
            </a>
        @endcan
    </div>
@endsection

@section('content')
    <div class="row">
        <div class="col-xl-12">
            <div class="card">
                <div class="card-body">
                    <ul class="nav nav-tabs mb-3">
                        @foreach (\Modules\PublicRelations\Enums\PrFieldModule::cases() as $tab)
                            <li class="nav-item">
                                <a class="nav-link {{ $module === $tab ? 'active' : '' }}"
                                    href="{{ route('pr.field.index', ['module' => $tab->value]) }}">{{ $tab->label() }}</a>
                            </li>
                        @endforeach
                    </ul>

                    <div class="table-responsive">
                        <table class="table table-striped">
                            <thead>
                                <tr>
                                    <th>{{ __('Label') }}</th>
                                    <th>{{ __('Key') }}</th>
                                    <th>{{ __('Type') }}</th>
                                    <th>{{ __('Required') }}</th>
                                    <th>{{ __('Options') }}</th>
                                    <th width="120px">{{ __('Action') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($prFields as $prField)
                                    <tr>
                                        <td>{{ $prField->label }}</td>
                                        <td><code>{{ $prField->key }}</code></td>
                                        <td>{{ $prField->type->label() }}</td>
                                        <td>{{ $prField->is_required ? __('Yes') : __('No') }}</td>
                                        <td>{{ $prField->options ? implode(', ', $prField->options) : '-' }}</td>
                                        <td>
                                            @can('manage pr custom field')
                                                <a href="#" data-url="{{ route('pr.field.edit', $prField->id) }}"
                                                    data-ajax-popup="true" data-size="md"
                                                    data-title="{{ __('Edit Custom Field') }}" class="btn btn-sm btn-light">
                                                    <i class="ti ti-pencil"></i>
                                                </a>
                                                {!! Form::open(['method' => 'DELETE', 'route' => ['pr.field.destroy', $prField->id], 'class' => 'd-inline']) !!}
                                                <button type="submit" class="btn btn-sm btn-light bs-pass-para">
                                                    <i class="ti ti-trash text-danger"></i>
                                                </button>
                                                {!! Form::close() !!}
                                            @endcan
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="6" class="text-center">{{ __('No custom fields defined for this sheet yet') }}</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
