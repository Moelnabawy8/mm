@extends('layouts.admin')

@section('page-title')
    {{ __('Quality') }} — {{ $department->name }}
@endsection

@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">{{ __('Dashboard') }}</a></li>
    <li class="breadcrumb-item"><a href="{{ route('quality.index') }}">{{ __('Quality by Department') }}</a></li>
    <li class="breadcrumb-item">{{ $department->name }}</li>
@endsection

@section('content')
    <div class="row">

        {{-- الأرقام أولًا: التغطية هي الرقم الذي يكشف إن كانت المراجعة تحدث فعلًا أم لا --}}
        <div class="col-12">
            <div class="row">
                <div class="col-md-3 col-6 mb-3">
                    <div class="card h-100 mb-0">
                        <div class="card-body py-3">
                            <h6 class="text-muted mb-1 small">{{ __('Awaiting review') }}</h6>
                            <h4 class="mb-0 text-warning" dir="ltr">{{ number_format($summary['pending']) }}</h4>
                            <small class="text-muted">{{ __('of') }} <span dir="ltr">{{ number_format($summary['completed']) }}</span> {{ __('completed') }}</small>
                        </div>
                    </div>
                </div>
                <div class="col-md-3 col-6 mb-3">
                    <div class="card h-100 mb-0">
                        <div class="card-body py-3">
                            <h6 class="text-muted mb-1 small">{{ __('Average score') }}</h6>
                            <h4 class="mb-0" dir="ltr">
                                {{ $summary['avg_score'] === null ? '—' : $summary['avg_score'] . '%' }}
                            </h4>
                            @if ($summary['avg_last_30'] !== null && $summary['avg_prev_30'] !== null)
                                @php $delta = round($summary['avg_last_30'] - $summary['avg_prev_30'], 1); @endphp
                                <small class="text-{{ $delta >= 0 ? 'success' : 'danger' }}" dir="ltr">
                                    {{ $delta >= 0 ? '+' : '' }}{{ $delta }} {{ __('vs previous 30 days') }}
                                </small>
                            @endif
                        </div>
                    </div>
                </div>
                <div class="col-md-3 col-6 mb-3">
                    <div class="card h-100 mb-0">
                        <div class="card-body py-3">
                            <h6 class="text-muted mb-1 small">{{ __('Review coverage') }}</h6>
                            <h4 class="mb-0" dir="ltr">
                                {{ $summary['coverage'] === null ? '—' : $summary['coverage'] . '%' }}
                            </h4>
                            <small class="text-muted">{{ __('completed tasks that were reviewed') }}</small>
                        </div>
                    </div>
                </div>
                <div class="col-md-3 col-6 mb-3">
                    <div class="card h-100 mb-0">
                        <div class="card-body py-3">
                            <h6 class="text-muted mb-1 small">{{ __('Delivered on time') }}</h6>
                            <h4 class="mb-0" dir="ltr">
                                {{ $summary['on_time_rate'] === null ? '—' : $summary['on_time_rate'] . '%' }}
                            </h4>
                            <small class="text-muted">{{ __('of reviewed tasks') }}</small>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- لا معايير = لا يمكن المراجعة إطلاقًا، فهذا التحذير يسبق كل شيء --}}
        @if ($criteria->where('is_active', true)->isEmpty())
            <div class="col-12">
                <div class="alert alert-warning d-flex align-items-center justify-content-between flex-wrap">
                    <div class="d-flex align-items-center">
                        <i class="ti ti-alert-triangle me-2 fs-5"></i>
                        <div>
                            <strong>{{ __('This department has no active quality criteria.') }}</strong>
                            <div class="small">{{ __('Nothing can be reviewed until at least one is defined.') }}</div>
                        </div>
                    </div>
                    @if ($canConfig && $criteria->isEmpty())
                        <form method="POST" action="{{ route('quality.criteria.seed', $department->id) }}" class="mt-2 mt-md-0">
                            @csrf
                            <button type="submit" class="btn btn-sm btn-primary">
                                <i class="ti ti-wand me-1"></i>{{ __('Add the standard criteria') }}
                            </button>
                        </form>
                    @endif
                </div>
            </div>
        @endif

        {{-- قائمة الانتظار: العمل الفعلي لهذه الشاشة --}}
        <div class="col-lg-8">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-start">
                    <div>
                        <h5 class="mb-0">{{ __('Awaiting review') }}</h5>
                        {{-- يُقال صراحةً: المهام بلا تاريخ إكمال مستبعدة، وإلا بدا الرقم ناقصًا بلا سبب --}}
                        <small class="text-muted">
                            {{ __('Completed tasks that carry a completion date, from the last 90 days.') }}
                        </small>
                    </div>
                    <span class="badge bg-warning">{{ number_format($summary['pending']) }}</span>
                </div>
                <div class="card-body table-border-style">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead>
                                <tr>
                                    <th>{{ __('Task') }}</th>
                                    <th>{{ __('Project') }}</th>
                                    <th>{{ __('Assigned to') }}</th>
                                    <th>{{ __('Completed on') }}</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($pending as $t)
                                    <tr>
                                        <td>{{ $t->name }}</td>
                                        <td class="text-muted small">{{ $t->project_name }}</td>
                                        <td class="small">
                                            @php
                                                $ids = array_filter(array_map('intval', explode(',', (string) $t->assign_to)));
                                                $names = $ids ? \App\Models\User::whereIn('id', $ids)->pluck('name')->implode('، ') : '';
                                            @endphp
                                            {{ $names !== '' ? $names : __('Unassigned') }}
                                        </td>
                                        <td dir="ltr" class="small">{{ $t->marked_at ?: '—' }}</td>
                                        <td class="text-end">
                                            @if ($canReview)
                                                <a href="{{ route('quality.review.form', $t->id) }}"
                                                   class="btn btn-sm btn-primary">{{ __('Review') }}</a>
                                            @endif
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="5" class="text-center text-muted py-4">
                                            @if ($summary['completed'] === 0)
                                                {{ __('This department has not completed any project tasks in the last 180 days.') }}
                                            @else
                                                {{ __('Everything completed has been reviewed.') }}
                                            @endif
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">{{ __('Recent reviews') }}</h5>
                </div>
                <div class="card-body table-border-style">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead>
                                <tr>
                                    <th>{{ __('Task') }}</th>
                                    <th>{{ __('Reviewer') }}</th>
                                    <th class="text-center">{{ __('Score') }}</th>
                                    <th>{{ __('Reviewed on') }}</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($reviews as $r)
                                    <tr>
                                        <td>{{ $r->task->name ?? __('(task deleted)') }}</td>
                                        <td class="small text-muted">{{ $r->reviewer->name ?? '—' }}</td>
                                        <td class="text-center" dir="ltr">
                                            <span class="badge bg-{{ $r->score_band }}">{{ $r->total_score }}%</span>
                                        </td>
                                        <td dir="ltr" class="small">
                                            {{ $r->reviewed_at ? $r->reviewed_at->format('Y-m-d') : '—' }}
                                        </td>
                                        <td class="text-end">
                                            @if ($canReview)
                                                <a href="{{ route('quality.review.form', $r->project_task_id) }}"
                                                   class="btn btn-sm btn-outline-secondary">{{ __('Edit') }}</a>
                                            @endif
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="5" class="text-center text-muted py-4">
                                            {{ __('No reviews yet.') }}
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-4">

            {{-- المعايير --}}
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">{{ __('Quality criteria') }}</h5>
                    <small class="text-muted">{{ __('This department judges its own work by these.') }}</small>
                </div>
                <div class="card-body">
                    @forelse ($criteria as $c)
                        <div class="d-flex justify-content-between align-items-start mb-2 pb-2 border-bottom {{ $c->is_active ? '' : 'opacity-50' }}">
                            <div>
                                <strong>{{ $c->name }}</strong>
                                @unless ($c->is_active)
                                    <span class="badge bg-light text-muted">{{ __('Retired') }}</span>
                                @endunless
                                @if ($c->description)
                                    <div class="text-muted small">{{ $c->description }}</div>
                                @endif
                                <div class="text-muted small" dir="ltr">
                                    {{ __('weight') }} ×{{ $c->weight }} · 0–{{ $c->max_score }}
                                </div>
                            </div>
                            @if ($canConfig)
                                <form method="POST" action="{{ route('quality.criteria.toggle', $c->id) }}">
                                    @csrf
                                    <button type="submit" class="btn btn-sm btn-link p-0 text-muted"
                                            title="{{ $c->is_active ? __('Retire') : __('Re-activate') }}">
                                        <i class="ti {{ $c->is_active ? 'ti-eye-off' : 'ti-eye' }}"></i>
                                    </button>
                                </form>
                            @endif
                        </div>
                    @empty
                        <p class="text-muted mb-0">{{ __('None defined.') }}</p>
                    @endforelse

                    @if ($canConfig)
                        <form method="POST" action="{{ route('quality.criteria.store', $department->id) }}" class="mt-3">
                            @csrf
                            <div class="mb-2">
                                <input type="text" name="name" class="form-control form-control-sm"
                                       placeholder="{{ __('Criterion name') }}" required maxlength="191">
                            </div>
                            <div class="mb-2">
                                <input type="text" name="description" class="form-control form-control-sm"
                                       placeholder="{{ __('Short description (optional)') }}" maxlength="191">
                            </div>
                            <div class="row g-2 mb-2">
                                <div class="col-6">
                                    <label class="form-label small mb-0">{{ __('Weight') }}</label>
                                    <input type="number" name="weight" class="form-control form-control-sm"
                                           dir="ltr" min="1" max="10" value="1" required>
                                </div>
                                <div class="col-6">
                                    <label class="form-label small mb-0">{{ __('Top of scale') }}</label>
                                    <input type="number" name="max_score" class="form-control form-control-sm"
                                           dir="ltr" min="1" max="10" value="5" required>
                                </div>
                            </div>
                            <button type="submit" class="btn btn-sm btn-primary w-100">
                                <i class="ti ti-plus me-1"></i>{{ __('Add criterion') }}
                            </button>
                        </form>
                    @endif
                </div>
            </div>

            {{-- المراجعون بالاسم، لا بالدور — انظر الـmigration --}}
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">{{ __('Reviewers') }}</h5>
                    <small class="text-muted">{{ __('Named people, not roles.') }}</small>
                </div>
                <div class="card-body">
                    @if ($canConfig)
                        <form method="POST" action="{{ route('quality.reviewers.save', $department->id) }}">
                            @csrf
                            <select name="reviewers[]" class="form-control select2" multiple id="quality-reviewers">
                                @foreach ($staff as $u)
                                    <option value="{{ $u->id }}"
                                        {{ $reviewers->contains('id', $u->id) ? 'selected' : '' }}>
                                        {{ $u->name }}
                                    </option>
                                @endforeach
                            </select>
                            <button type="submit" class="btn btn-sm btn-primary w-100 mt-2">
                                {{ __('Save reviewers') }}
                            </button>
                        </form>
                    @else
                        @forelse ($reviewers as $u)
                            <div class="mb-1">{{ $u->name }}</div>
                        @empty
                            <p class="text-muted mb-0">{{ __('Nobody is named yet.') }}</p>
                        @endforelse
                    @endif
                </div>
            </div>

            {{-- من وقعت عليه المراجعات فعليًا --}}
            @if (!empty($byEmployee))
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">{{ __('By employee') }}</h5>
                    </div>
                    <div class="card-body table-border-style">
                        <div class="table-responsive">
                            <table class="table table-sm mb-0">
                                <thead>
                                    <tr>
                                        <th>{{ __('Name') }}</th>
                                        <th class="text-center">{{ __('Reviews') }}</th>
                                        <th class="text-center">{{ __('Average') }}</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($byEmployee as $e)
                                        <tr>
                                            <td class="small">{{ $e['name'] }}</td>
                                            <td class="text-center small" dir="ltr">{{ $e['reviews'] }}</td>
                                            <td class="text-center" dir="ltr">
                                                @php
                                                    $band = $e['avg_score'] >= 85 ? 'success'
                                                        : ($e['avg_score'] >= 70 ? 'info'
                                                        : ($e['avg_score'] >= 50 ? 'warning' : 'danger'));
                                                @endphp
                                                <span class="badge bg-{{ $band }}">{{ $e['avg_score'] }}%</span>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            @endif

        </div>
    </div>
@endsection

@push('script-page')
    <script>
        if (typeof $ !== 'undefined' && $.fn.select2) {
            $('#quality-reviewers').select2({ width: '100%' });
        }
    </script>
@endpush
