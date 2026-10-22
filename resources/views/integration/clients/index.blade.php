@extends('layouts.admin')

@section('page-title')
    {{ __('API Clients') }}
@endsection

@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">{{ __('Dashboard') }}</a></li>
    <li class="breadcrumb-item"><a href="{{ route('integration.index') }}">{{ __('Integration Hub') }}</a></li>
    <li class="breadcrumb-item">{{ __('API Clients') }}</li>
@endsection

@section('action-btn')
    <a href="{{ route('integration.clients.create') }}" class="btn btn-primary btn-sm">
        <i class="ti ti-plus me-1"></i>{{ __('Create New API Key') }}
    </a>
@endsection

@section('content')
<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover" id="clientsTable">
                        <thead>
                            <tr>
                                <th>{{ __('Name') }}</th>
                                <th>{{ __('Token Prefix') }}</th>
                                <th>{{ __('Abilities') }}</th>
                                <th>{{ __('Requests') }}</th>
                                <th>{{ __('Rate Limit') }}</th>
                                <th>{{ __('Status') }}</th>
                                <th>{{ __('Last Used') }}</th>
                                <th class="text-end">{{ __('Actions') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($clients as $client)
                            <tr>
                                <td>
                                    <strong>{{ $client->name }}</strong>
                                    @if($client->description)
                                        <br><small class="text-muted">{{ Str::limit($client->description, 50) }}</small>
                                    @endif
                                </td>
                                <td>
                                    <code>{{ $client->token_prefix }}...</code>
                                </td>
                                <td>
                                    @php $abilities = $client->abilities ?? []; @endphp
                                    @if(in_array('*', $abilities))
                                        <span class="badge bg-danger">{{ __('Full Access') }}</span>
                                    @else
                                        <span class="badge bg-info">{{ count($abilities) }} {{ __('scopes') }}</span>
                                    @endif
                                </td>
                                <td>
                                    {{ number_format($client->total_requests) }}
                                    @if($client->failed_requests > 0)
                                        <br><small class="text-danger">{{ $client->failed_requests }} {{ __('failed') }}</small>
                                    @endif
                                </td>
                                <td>{{ $client->rate_limit }}/min</td>
                                <td>
                                    @if($client->is_active)
                                        @if($client->isExpired())
                                            <span class="badge bg-warning">{{ __('Expired') }}</span>
                                        @else
                                            <span class="badge bg-success">{{ __('Active') }}</span>
                                        @endif
                                    @else
                                        <span class="badge bg-danger">{{ __('Revoked') }}</span>
                                    @endif
                                </td>
                                <td>
                                    @if($client->last_used_at)
                                        {{ $client->last_used_at->diffForHumans() }}
                                    @else
                                        <span class="text-muted">{{ __('Never') }}</span>
                                    @endif
                                </td>
                                <td class="text-end">
                                    <div class="btn-group">
                                        <a href="{{ route('integration.clients.show', $client->id) }}" 
                                           class="btn btn-sm btn-outline-primary" 
                                           data-bs-toggle="tooltip" 
                                           title="{{ __('View') }}">
                                            <i class="ti ti-eye"></i>
                                        </a>
                                        <a href="{{ route('integration.clients.edit', $client->id) }}" 
                                           class="btn btn-sm btn-outline-info"
                                           data-bs-toggle="tooltip" 
                                           title="{{ __('Edit') }}">
                                            <i class="ti ti-pencil"></i>
                                        </a>
                                        @if($client->is_active)
                                            <form action="{{ route('integration.clients.revoke', $client->id) }}" 
                                                  method="POST" 
                                                  class="d-inline"
                                                  onsubmit="return confirm('{{ __('Are you sure you want to revoke this API key?') }}')">
                                                @csrf
                                                <button type="submit" 
                                                        class="btn btn-sm btn-outline-warning"
                                                        data-bs-toggle="tooltip" 
                                                        title="{{ __('Revoke') }}">
                                                    <i class="ti ti-ban"></i>
                                                </button>
                                            </form>
                                        @else
                                            <form action="{{ route('integration.clients.activate', $client->id) }}" 
                                                  method="POST" 
                                                  class="d-inline">
                                                @csrf
                                                <button type="submit" 
                                                        class="btn btn-sm btn-outline-success"
                                                        data-bs-toggle="tooltip" 
                                                        title="{{ __('Activate') }}">
                                                    <i class="ti ti-check"></i>
                                                </button>
                                            </form>
                                        @endif
                                        <form action="{{ route('integration.clients.destroy', $client->id) }}" 
                                              method="POST" 
                                              class="d-inline"
                                              onsubmit="return confirm('{{ __('Are you sure you want to permanently delete this API client?') }}')">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" 
                                                    class="btn btn-sm btn-outline-danger"
                                                    data-bs-toggle="tooltip" 
                                                    title="{{ __('Delete') }}">
                                                <i class="ti ti-trash"></i>
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                            @empty
                            <tr>
                                <td colspan="8" class="text-center py-4">
                                    <div class="text-muted">
                                        <i class="ti ti-plug-connected fs-1 d-block mb-2"></i>
                                        {{ __('No API clients yet.') }}
                                        <br>
                                        <a href="{{ route('integration.clients.create') }}" class="btn btn-primary btn-sm mt-2">
                                            {{ __('Create your first API key') }}
                                        </a>
                                    </div>
                                </td>
                            </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                
                {{ $clients->links() }}
            </div>
        </div>
    </div>
</div>
@endsection
