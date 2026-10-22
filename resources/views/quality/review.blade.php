@extends('layouts.admin')

@section('page-title')
    {{ __('Review quality') }}
@endsection

@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">{{ __('Dashboard') }}</a></li>
    <li class="breadcrumb-item"><a href="{{ route('quality.index') }}">{{ __('Quality by Department') }}</a></li>
    <li class="breadcrumb-item">
        <a href="{{ route('quality.department', $department->id) }}">{{ $department->name }}</a>
    </li>
    <li class="breadcrumb-item">{{ __('Review quality') }}</li>
@endsection

@section('content')
    <div class="row justify-content-center">
        <div class="col-lg-8">

            <div class="card">
                <div class="card-header">
                    <h5 class="mb-1">{{ $task->name }}</h5>
                    <div class="text-muted small">
                        {{ $task->project->project_name ?? '—' }} · {{ $department->name }}
                    </div>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-4 mb-2">
                            <div class="text-muted small">{{ __('Assigned to') }}</div>
                            <div>
                                {{ $assignees->count() ? $assignees->pluck('name')->implode('، ') : __('Unassigned') }}
                            </div>
                        </div>
                        <div class="col-md-4 mb-2">
                            <div class="text-muted small">{{ __('Due') }}</div>
                            <div dir="ltr">{{ $task->end_date ?: '—' }}</div>
                        </div>
                        <div class="col-md-4 mb-2">
                            <div class="text-muted small">{{ __('Completed on') }}</div>
                            <div dir="ltr">
                                {{ $task->marked_at ?: '—' }}
                                {{-- تُعرض كمعلومة للمراجع، ولا تُحتسب درجة تلقائيًا --}}
                                @if ($onTime === true)
                                    <span class="badge bg-success ms-1">{{ __('On time') }}</span>
                                @elseif ($onTime === false)
                                    <span class="badge bg-danger ms-1">{{ __('Late') }}</span>
                                @endif
                            </div>
                        </div>
                    </div>

                    @if ($task->description)
                        <div class="mt-2">
                            <div class="text-muted small">{{ __('Description') }}</div>
                            <div class="small">{!! nl2br(e(strip_tags($task->description))) !!}</div>
                        </div>
                    @endif
                </div>
            </div>

            <form method="POST" action="{{ route('quality.review.store', $task->id) }}">
                @csrf

                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">{{ __('Score each criterion') }}</h5>
                        <small class="text-muted">
                            {{ __('The total is weighted, so a heavier criterion moves the score further.') }}
                        </small>
                    </div>
                    <div class="card-body">
                        @foreach ($criteria as $c)
                            @php
                                $current = $existing && isset($existing->scores[$c->id]) ? $existing->scores[$c->id] : null;
                            @endphp
                            <div class="mb-3 pb-3 border-bottom">
                                <div class="d-flex justify-content-between align-items-start mb-2">
                                    <div>
                                        <strong>{{ $c->name }}</strong>
                                        <span class="text-muted small" dir="ltr">(×{{ $c->weight }})</span>
                                        @if ($c->description)
                                            <div class="text-muted small">{{ $c->description }}</div>
                                        @endif
                                    </div>
                                </div>
                                <div class="btn-group flex-wrap" role="group" dir="ltr">
                                    @for ($i = 0; $i <= $c->max_score; $i++)
                                        <input type="radio" class="btn-check"
                                               name="scores[{{ $c->id }}]"
                                               id="c{{ $c->id }}s{{ $i }}"
                                               value="{{ $i }}"
                                               {{ (string) $current === (string) $i ? 'checked' : '' }} required>
                                        <label class="btn btn-sm btn-outline-primary" for="c{{ $c->id }}s{{ $i }}">
                                            {{ $i }}
                                        </label>
                                    @endfor
                                </div>
                            </div>
                        @endforeach

                        <div class="mb-3">
                            <label class="form-label">{{ __('Notes') }}</label>
                            <textarea name="notes" class="form-control" rows="3"
                                      placeholder="{{ __('What was good, what needs to change next time.') }}">{{ old('notes', $existing->notes ?? '') }}</textarea>
                        </div>
                    </div>
                    <div class="card-footer d-flex justify-content-between align-items-center">
                        <a href="{{ route('quality.department', $department->id) }}" class="btn btn-light">
                            {{ __('Cancel') }}
                        </a>
                        <div>
                            @if ($existing)
                                <span class="text-muted small me-2" dir="ltr">
                                    {{ __('Current') }}: {{ $existing->total_score }}%
                                </span>
                            @endif
                            <button type="submit" class="btn btn-primary">
                                {{ $existing ? __('Update review') : __('Save review') }}
                            </button>
                        </div>
                    </div>
                </div>
            </form>

            @if ($existing)
                <form method="POST" action="{{ route('quality.review.destroy', $existing->id) }}"
                      onsubmit="return confirm('{{ __('Remove this review and put the task back in the queue?') }}');">
                    @csrf
                    <button type="submit" class="btn btn-sm btn-outline-danger">
                        <i class="ti ti-trash me-1"></i>{{ __('Remove review') }}
                    </button>
                </form>
            @endif

        </div>
    </div>
@endsection
