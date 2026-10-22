@extends('layouts.admin')

@section('page-title'){{ __('Minutes') }} — {{ $meeting->title }}@endsection

@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">{{ __('Dashboard') }}</a></li>
    <li class="breadcrumb-item"><a href="{{ route('meetings.board') }}">{{ __('Meeting Minutes') }}</a></li>
    <li class="breadcrumb-item">{{ $meeting->title }}</li>
@endsection

@section('content')
    <div class="row">
        <div class="col-lg-7">

            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-start">
                    <div>
                        <h5 class="mb-1">{{ $meeting->title }}</h5>
                        <div class="text-muted small" dir="ltr">
                            {{ optional($meeting->dateOn())->format('Y-m-d') ?: '—' }} {{ $meeting->time }}
                        </div>
                    </div>
                    <span class="badge bg-{{ $meeting->status_badge }}">
                        @if ($meeting->status === \App\Models\Meeting::STATUS_CANCELLED)
                            {{ __('Cancelled') }}
                        @elseif ($meeting->hasMinutes())
                            {{ __('Written up') }}
                        @elseif ($meeting->isAwaitingMinutes())
                            {{ __('Awaiting minutes') }}
                        @else
                            {{ __('Scheduled') }}
                        @endif
                    </span>
                </div>

                @if ($meeting->note)
                    <div class="card-body border-bottom">
                        <div class="text-muted small">{{ __('Agenda note from the organiser') }}</div>
                        <div>{!! nl2br(e($meeting->note)) !!}</div>
                    </div>
                @endif

                <form method="POST" action="{{ route('meetings.minutes.save', $meeting->id) }}">
                    @csrf
                    <div class="card-body">
                        <div class="mb-3">
                            <label class="form-label">{{ __('Minutes') }}</label>
                            <textarea name="minutes" class="form-control" rows="10"
                                      {{ $canRecord ? '' : 'disabled' }}
                                      placeholder="{{ __('What was discussed and what was agreed. Decisions with an owner go below, not here.') }}">{{ old('minutes', $meeting->minutes) }}</textarea>
                            @if ($meeting->minutes_at)
                                <small class="text-muted">
                                    {{ __('Last written by') }} {{ $meeting->minutesAuthor->name ?? '—' }}
                                    <span dir="ltr">{{ $meeting->minutes_at->format('Y-m-d H:i') }}</span>
                                </small>
                            @endif
                        </div>

                        @if (count($employees))
                            <div class="mb-3">
                                <label class="form-label">{{ __('Attendance') }}</label>
                                <div class="row">
                                    @foreach ($employees as $e)
                                        <div class="col-md-6">
                                            <div class="form-check">
                                                <input class="form-check-input" type="checkbox"
                                                       name="attended[{{ $e->id }}]" value="1"
                                                       id="att-{{ $e->id }}"
                                                       {{ ($attendance[$e->id] ?? null) ? 'checked' : '' }}
                                                       {{ $canRecord ? '' : 'disabled' }}>
                                                <label class="form-check-label" for="att-{{ $e->id }}">
                                                    {{ $e->name }}
                                                </label>
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                                <small class="text-muted">
                                    {{ __('Unticked means absent once you save; before the first save nobody is marked either way.') }}
                                </small>
                            </div>
                        @endif

                        @if ($canRecord)
                            <div class="mb-0">
                                <label class="form-label">{{ __('Meeting status') }}</label>
                                <select name="status" class="form-control">
                                    <option value="">{{ __('Leave as it is') }}</option>
                                    <option value="held" {{ $meeting->status === 'held' ? 'selected' : '' }}>{{ __('Held') }}</option>
                                    <option value="scheduled" {{ $meeting->status === 'scheduled' ? 'selected' : '' }}>{{ __('Scheduled') }}</option>
                                    <option value="cancelled" {{ $meeting->status === 'cancelled' ? 'selected' : '' }}>{{ __('Cancelled') }}</option>
                                </select>
                                <small class="text-muted">
                                    {{ __('Writing minutes marks the meeting as held automatically.') }}
                                </small>
                            </div>
                        @endif
                    </div>

                    @if ($canRecord)
                        <div class="card-footer text-end">
                            <button type="submit" class="btn btn-primary">{{ __('Save minutes') }}</button>
                        </div>
                    @endif
                </form>
            </div>

        </div>

        <div class="col-lg-5">

            {{-- القرارات هي الرابط بين الاجتماع والالتزامات: قرار بلا صاحب ولا موعد لا يُنفَّذ --}}
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">{{ __('Decisions') }}</h5>
                    <small class="text-muted">
                        {{ __('Each one becomes a tracked commitment with an owner and a date.') }}
                    </small>
                </div>
                <div class="card-body">
                    @forelse ($commitments as $c)
                        <div class="d-flex justify-content-between align-items-start mb-2 pb-2 border-bottom">
                            <div>
                                <a href="{{ route('commitments.show', $c->id) }}">{{ $c->title }}</a>
                                <div class="text-muted small">
                                    {{ $c->owner_label }} &rarr; {{ $c->owedTo->name ?? '—' }}
                                    · <span dir="ltr">{{ $c->deadline ? $c->deadline->format('Y-m-d') : '—' }}</span>
                                </div>
                            </div>
                            <span class="badge bg-{{ $c->status_badge }}">
                                @switch($c->status)
                                    @case(\App\Models\Commitment::STATUS_DONE) {{ __('Kept') }} @break
                                    @case(\App\Models\Commitment::STATUS_MISSED) {{ __('Missed') }} @break
                                    @case(\App\Models\Commitment::STATUS_CANCELLED) {{ __('Withdrawn') }} @break
                                    @default {{ $c->is_overdue ? __('Overdue') : __('Open') }}
                                @endswitch
                            </span>
                        </div>
                    @empty
                        <p class="text-muted">{{ __('No decisions recorded from this meeting yet.') }}</p>
                    @endforelse

                    @if ($canRecord)
                        <form method="POST" action="{{ route('meetings.decision', $meeting->id) }}" class="mt-3">
                            @csrf
                            <div class="mb-2">
                                <input type="text" name="title" class="form-control form-control-sm" required
                                       maxlength="191" placeholder="{{ __('What was decided') }}">
                            </div>
                            <div class="mb-2">
                                <input type="text" name="description" class="form-control form-control-sm"
                                       placeholder="{{ __('Detail (optional)') }}">
                            </div>
                            <div class="mb-2">
                                <label class="form-label small mb-0">{{ __('Who will do it') }}</label>
                                <select name="user_id" class="form-control form-control-sm" required>
                                    <option value="">{{ __('Choose') }}</option>
                                    @foreach ($people as $p)
                                        <option value="{{ $p->id }}">{{ $p->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="mb-2">
                                <label class="form-label small mb-0">{{ __('Owed to') }}</label>
                                <select name="owed_to_id" class="form-control form-control-sm" required>
                                    <option value="">{{ __('Choose') }}</option>
                                    @foreach ($people as $p)
                                        <option value="{{ $p->id }}">{{ $p->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="mb-2">
                                <label class="form-label small mb-0">{{ __('By when') }}</label>
                                <input type="date" name="due_date" class="form-control form-control-sm" dir="ltr" required>
                            </div>
                            <button type="submit" class="btn btn-sm btn-primary w-100">
                                <i class="ti ti-plus me-1"></i>{{ __('Add decision') }}
                            </button>
                        </form>
                    @endif
                </div>
            </div>

        </div>
    </div>
@endsection
