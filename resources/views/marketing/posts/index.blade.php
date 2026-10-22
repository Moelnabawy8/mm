@extends('layouts.admin')

@section('page-title')
    {{ __('Social Media Posts') }}
@endsection

@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">{{ __('Dashboard') }}</a></li>
    <li class="breadcrumb-item"><a href="{{ route('marketing.index') }}">{{ __('Marketing') }}</a></li>
    <li class="breadcrumb-item active">{{ __('Posts') }}</li>
@endsection

@section('action-btn')
    <a href="{{ route('marketing.posts.create') }}" class="btn btn-sm btn-primary">
        <i class="ti ti-plus"></i> {{ __('Create Post') }}
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
                                    <th>{{ __('Content') }}</th>
                                    <th>{{ __('Account') }}</th>
                                    <th>{{ __('Campaign') }}</th>
                                    <th>{{ __('Status') }}</th>
                                    <th>{{ __('Engagement') }}</th>
                                    <th>{{ __('Scheduled/Published') }}</th>
                                    <th class="text-end">{{ __('Actions') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($posts as $post)
                                    <tr>
                                        <td style="max-width: 300px;">
                                            <div class="text-truncate">{{ $post->content }}</div>
                                            @if($post->media_url)
                                                <small class="text-muted">
                                                    <i class="ti ti-photo"></i> {{ __('Has media') }}
                                                </small>
                                            @endif
                                        </td>
                                        <td>
                                            <i class="{{ $post->account->platform_icon }}" style="color: {{ $post->account->platform_color }};"></i>
                                            {{ $post->account->account_name }}
                                        </td>
                                        <td>
                                            @if($post->campaign)
                                                <a href="{{ route('marketing.campaigns.show', $post->campaign_id) }}">
                                                    {{ $post->campaign->campaign_name }}
                                                </a>
                                            @else
                                                <span class="text-muted">-</span>
                                            @endif
                                        </td>
                                        <td>
                                            @php
                                                $statusColors = [
                                                    'draft' => 'secondary',
                                                    'scheduled' => 'warning',
                                                    'published' => 'success',
                                                    'failed' => 'danger'
                                                ];
                                            @endphp
                                            <span class="badge bg-{{ $statusColors[$post->status] ?? 'secondary' }}">
                                                {{ ucfirst($post->status) }}
                                            </span>
                                        </td>
                                        <td>
                                            @if($post->status == 'published')
                                                <small>
                                                    <i class="ti ti-heart"></i> {{ number_format($post->likes) }}<br>
                                                    <i class="ti ti-message"></i> {{ number_format($post->comments) }}<br>
                                                    <i class="ti ti-share"></i> {{ number_format($post->shares) }}
                                                </small>
                                            @else
                                                <span class="text-muted">-</span>
                                            @endif
                                        </td>
                                        <td>
                                            @if($post->published_at)
                                                {{ $post->published_at->format('M d, Y H:i') }}
                                            @elseif($post->scheduled_at)
                                                {{ $post->scheduled_at->format('M d, Y H:i') }}
                                            @else
                                                <span class="text-muted">-</span>
                                            @endif
                                        </td>
                                        <td class="text-end">
                                            @if($post->status == 'published')
                                                <form action="{{ route('marketing.posts.sync', $post->id) }}" method="POST" class="d-inline">
                                                    @csrf
                                                    <button type="submit" class="btn btn-sm btn-info" data-bs-toggle="tooltip" title="{{ __('Sync Stats') }}">
                                                        <i class="ti ti-refresh"></i>
                                                    </button>
                                                </form>
                                            @endif
                                            
                                            @if($post->status == 'draft' || $post->status == 'scheduled')
                                                <form action="{{ route('marketing.posts.publish', $post->id) }}" method="POST" class="d-inline">
                                                    @csrf
                                                    <button type="submit" class="btn btn-sm btn-success" data-bs-toggle="tooltip" title="{{ __('Publish Now') }}">
                                                        <i class="ti ti-send"></i>
                                                    </button>
                                                </form>
                                            @endif

                                            <a href="{{ route('marketing.posts.edit', $post->id) }}" class="btn btn-sm btn-warning" data-bs-toggle="tooltip" title="{{ __('Edit') }}">
                                                <i class="ti ti-pencil"></i>
                                            </a>
                                            
                                            <form action="{{ route('marketing.posts.delete', $post->id) }}" method="POST" class="d-inline" onsubmit="return confirm('{{ __('Are you sure you want to delete this post? This cannot be undone!') }}');">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="btn btn-sm btn-danger" data-bs-toggle="tooltip" title="{{ __('Delete') }}">
                                                    <i class="ti ti-trash"></i>
                                                </button>
                                            </form>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="7" class="text-center py-5">
                                            <i class="ti ti-message-circle text-muted" style="font-size: 5rem;"></i>
                                            <h4 class="mt-3">{{ __('No Posts Yet') }}</h4>
                                            <p class="text-muted">{{ __('Create your first social media post') }}</p>
                                            <a href="{{ route('marketing.posts.create') }}" class="btn btn-primary mt-3">
                                                <i class="ti ti-plus"></i> {{ __('Create Post') }}
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
