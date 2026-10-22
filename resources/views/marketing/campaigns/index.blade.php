@extends('layouts.admin')

@section('page-title')
    {{ __('Marketing Campaigns') }}
@endsection

@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">{{ __('Dashboard') }}</a></li>
    <li class="breadcrumb-item"><a href="{{ route('marketing.index') }}">{{ __('Marketing') }}</a></li>
    <li class="breadcrumb-item active">{{ __('Campaigns') }}</li>
@endsection

@section('action-btn')
    <a href="{{ route('marketing.campaigns.create') }}" class="btn btn-sm btn-primary">
        <i class="ti ti-plus"></i> {{ __('New Campaign') }}
    </a>
@endsection

@section('content')
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>{{ __('Campaign') }}</th>
                                    <th>{{ __('Account') }}</th>
                                    <th>{{ __('Objective') }}</th>
                                    <th>{{ __('Budget') }}</th>
                                    <th>{{ __('Duration') }}</th>
                                    <th>{{ __('Status') }}</th>
                                    <th>{{ __('Performance') }}</th>
                                    <th class="text-end">{{ __('Actions') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($campaigns as $campaign)
                                    <tr>
                                        <td>
                                            <a href="{{ route('marketing.campaigns.show', $campaign->id) }}" class="text-dark">
                                                <strong>{{ $campaign->campaign_name }}</strong>
                                            </a>
                                            @if($campaign->description)
                                                <br><small class="text-muted">{{ Str::limit($campaign->description, 50) }}</small>
                                            @endif
                                        </td>
                                        <td>
                                            <i class="{{ $campaign->account->platform_icon }}" style="color: {{ $campaign->account->platform_color }};"></i>
                                            {{ $campaign->account->account_name }}
                                        </td>
                                        <td>
                                            <span class="badge bg-info">{{ ucfirst($campaign->objective) }}</span>
                                        </td>
                                        <td>
                                            <strong>${{ number_format($campaign->budget, 2) }}</strong>
                                            <br><small class="text-muted">{{ $campaign->currency }}</small>
                                        </td>
                                        <td>
                                            @if($campaign->start_date && $campaign->end_date)
                                                {{ $campaign->start_date->format('M d') }} - {{ $campaign->end_date->format('M d, Y') }}
                                            @elseif($campaign->start_date)
                                                {{ __('From') }} {{ $campaign->start_date->format('M d, Y') }}
                                            @else
                                                <span class="text-muted">{{ __('Not set') }}</span>
                                            @endif
                                        </td>
                                        <td>
                                            @php
                                                $statusColors = [
                                                    'active' => 'success',
                                                    'paused' => 'warning',
                                                    'draft' => 'secondary',
                                                    'completed' => 'info',
                                                    'cancelled' => 'danger'
                                                ];
                                            @endphp
                                            <span class="badge bg-{{ $statusColors[$campaign->status] ?? 'secondary' }}">
                                                {{ ucfirst($campaign->status) }}
                                            </span>
                                        </td>
                                        <td>
                                            <div class="d-flex flex-column">
                                                <small><strong>{{ number_format($campaign->total_impressions) }}</strong> {{ __('impressions') }}</small>
                                                <small><strong>{{ number_format($campaign->total_clicks) }}</strong> {{ __('clicks') }}</small>
                                                <small><strong>${{ number_format($campaign->total_spent, 2) }}</strong> {{ __('spent') }}</small>
                                            </div>
                                        </td>
                                        <td class="text-end">
                                            <div class="btn-group" role="group">
                                                <a href="{{ route('marketing.campaigns.show', $campaign->id) }}" class="btn btn-sm btn-info" data-bs-toggle="tooltip" title="{{ __('View') }}">
                                                    <i class="ti ti-eye"></i>
                                                </a>
                                                <a href="{{ route('marketing.campaigns.edit', $campaign->id) }}" class="btn btn-sm btn-primary" data-bs-toggle="tooltip" title="{{ __('Edit') }}">
                                                    <i class="ti ti-edit"></i>
                                                </a>
                                                <form action="{{ route('marketing.campaigns.delete', $campaign->id) }}" method="POST" class="d-inline" onsubmit="return confirm('{{ __('Are you sure?') }}');">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="submit" class="btn btn-sm btn-danger" data-bs-toggle="tooltip" title="{{ __('Delete') }}">
                                                        <i class="ti ti-trash"></i>
                                                    </button>
                                                </form>
                                            </div>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="8" class="text-center py-5">
                                            <i class="ti ti-speakerphone text-muted" style="font-size: 5rem;"></i>
                                            <h4 class="mt-3">{{ __('No Campaigns Yet') }}</h4>
                                            <p class="text-muted">{{ __('Create your first marketing campaign to get started') }}</p>
                                            <a href="{{ route('marketing.campaigns.create') }}" class="btn btn-primary mt-3">
                                                <i class="ti ti-plus"></i> {{ __('Create Campaign') }}
                                            </a>
                                        </td>
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
