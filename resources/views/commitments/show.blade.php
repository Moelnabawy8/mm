@extends('layouts.admin')

@section('page-title'){{ __('Commitment') }}@endsection

@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">{{ __('Dashboard') }}</a></li>
    <li class="breadcrumb-item"><a href="{{ route('commitments.mine') }}">{{ __('My Commitments') }}</a></li>
    <li class="breadcrumb-item">{{ __('Commitment') }}</li>
@endsection

@section('content')
    <div class="row">
        <div class="col-lg-7">

            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-start">
                    <div>
                        <h5 class="mb-1">{{ $commitment->title }}</h5>
                        <div class="text-muted small">
                            {{ $commitment->owner_label }} &rarr; {{ $commitment->owedTo->name ?? '—' }}
                        </div>
                    </div>
                    <span class="badge bg-{{ $commitment->status_badge }}">
                        @switch($commitment->status)
                            @case(\App\Models\Commitment::STATUS_DONE) {{ __('Kept') }} @break
                            @case(\App\Models\Commitment::STATUS_MISSED) {{ __('Missed') }} @break
                            @case(\App\Models\Commitment::STATUS_CANCELLED) {{ __('Withdrawn') }} @break
                            @default {{ $commitment->is_overdue ? __('Overdue') : __('Open') }}
                        @endswitch
                    </span>
                </div>
                <div class="card-body">
                    @if ($commitment->description)
                        <p>{!! nl2br(e($commitment->description)) !!}</p>
                    @endif

                    <div class="row">
                        <div class="col-md-4 mb-2">
                            <div class="text-muted small">{{ __('Due') }}</div>
                            <div dir="ltr">
                                {{ $commitment->deadline ? $commitment->deadline->format('Y-m-d') : '—' }}
                                @if ($commitment->is_overdue)
                                    <span class="text-danger d-block small">
                                        {{ abs($commitment->days_left) }} {{ __('days late') }}
                                    </span>
                                @endif
                            </div>
                        </div>
                        <div class="col-md-4 mb-2">
                            <div class="text-muted small">{{ __('Cadence') }}</div>
                            <div>
                                {{ $commitment->cadence === \App\Models\Commitment::CADENCE_WEEKLY ? __('Weekly') : __('One-off') }}
                                @if ($commitment->recurs)
                                    <span class="badge bg-light text-muted">{{ __('recurring') }}</span>
                                @endif
                            </div>
                        </div>
                        <div class="col-md-4 mb-2">
                            <div class="text-muted small">{{ __('Progress') }}</div>
                            <div class="d-flex align-items-center">
                                <div class="progress flex-grow-1" style="height:6px" dir="ltr">
                                    <div class="progress-bar bg-{{ $commitment->status_badge }}"
                                         style="width: {{ min(max((int) $commitment->progress, 0), 100) }}%"></div>
                                </div>
                                <span class="ms-2 small" dir="ltr">{{ (int) $commitment->progress }}%</span>
                            </div>
                        </div>
                    </div>

                    @if ($commitment->meeting)
                        <div class="alert alert-light small mb-0 mt-2">
                            <i class="ti ti-users me-1"></i>
                            {{ __('Came out of the meeting') }}:
                            <a href="{{ route('meetings.minutes', $commitment->meeting->id) }}">
                                {{ $commitment->meeting->title }}
                            </a>
                        </div>
                    @endif
                </div>

                @if ($canUpdate)
                    <div class="card-footer d-flex justify-content-between">
                        <a href="{{ route('commitments.edit', $commitment->id) }}" class="btn btn-sm btn-light">
                            {{ __('Edit') }}
                        </a>
                        @if ($canManage)
                            <form method="POST" action="{{ route('commitments.destroy', $commitment->id) }}"
                                  onsubmit="return confirm('{{ __('Delete this commitment and its history?') }}');">
                                @csrf
                                <button type="submit" class="btn btn-sm btn-outline-danger">{{ __('Delete') }}</button>
                            </form>
                        @endif
                    </div>
                @endif
            </div>

            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">{{ __('History') }}</h5>
                </div>
                <div class="card-body">
                    @forelse ($commitment->updates as $u)
                        <div class="d-flex mb-3 pb-3 border-bottom">
                            <div class="flex-grow-1">
                                <div class="small text-muted">
                                    {{ $u->author->name ?? '—' }} ·
                                    <span dir="ltr">{{ $u->created_at->format('Y-m-d H:i') }}</span>
                                    @if ($u->status_after)
                                        · <span class="badge bg-light text-muted">{{ __($u->status_after) }}</span>
                                    @endif
                                    @if ($u->progress !== null)
                                        · <span dir="ltr">{{ $u->progress }}%</span>
                                    @endif
                                </div>
                                @if ($u->note)
                                    <div>{!! nl2br(e($u->note)) !!}</div>
                                @endif
                            </div>
                        </div>
                    @empty
                        <p class="text-muted mb-0">{{ __('No updates yet.') }}</p>
                    @endforelse
                </div>
            </div>

        </div>

        <div class="col-lg-5">
            @if ($canUpdate)
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">{{ __('Add an update') }}</h5>
                        <small class="text-muted">
                            {{ __('A commitment that only changes on its last day tells a supervisor nothing in time to help.') }}
                        </small>
                    </div>
                    <form method="POST" action="{{ route('commitments.update.add', $commitment->id) }}">
                        @csrf
                        <div class="card-body">
                            <div class="mb-3">
                                <label class="form-label">{{ __('Note') }}</label>
                                <textarea name="note" class="form-control" rows="3" maxlength="2000"
                                          placeholder="{{ __('Where it stands, and anything blocking it.') }}"></textarea>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">{{ __('Progress') }} (%)</label>
                                <input type="number" name="progress" class="form-control" dir="ltr"
                                       min="0" max="100" value="{{ (int) $commitment->progress }}">
                            </div>
                            <div class="mb-3">
                                <label class="form-label">{{ __('Change status') }}</label>
                                <select name="status" class="form-control">
                                    <option value="">{{ __('Leave as it is') }}</option>
                                    <option value="open">{{ __('Open') }}</option>
                                    <option value="done">{{ __('Kept — it is done') }}</option>
                                    <option value="missed">{{ __('Missed') }}</option>
                                    <option value="cancelled">{{ __('Withdrawn') }}</option>
                                </select>
                                <small class="text-muted">
                                    {{ __('Marking it kept sets progress to 100%.') }}
                                </small>
                            </div>
                        </div>
                        <div class="card-footer">
                            <button type="submit" class="btn btn-primary w-100">{{ __('Record update') }}</button>
                        </div>
                    </form>
                </div>
            @endif
        </div>
    </div>
@endsection
