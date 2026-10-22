@extends('layouts.admin')
@section('page-title')
    {{ __('Quality Evaluation Details') }}
@endsection

@section('title')
    {{ __('Quality Evaluation Details') }}
@endsection

@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">{{ __('Home') }}</a></li>
    <li class="breadcrumb-item"><a href="{{ route('quality-evaluations.index') }}">{{ __('Quality Evaluations') }}</a></li>
    <li class="breadcrumb-item">{{ __('Details') }}</li>
@endsection

@section('action-btn')
    @can('edit deal')
        @if ($evaluation->evaluator_id == Auth::user()->id)
            <div class="col-12 text-end btn btn-sm">
                <a href="{{ route('quality-evaluations.edit', $evaluation->id) }}" class="btn btn-sm btn-primary"
                    data-bs-toggle="tooltip" title="{{ __('Edit') }}">
                    <i class="ti ti-pencil"></i>
                </a>
            </div>
        @endif
    @endcan
@endsection

@section('content')
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h5>{{ __('Evaluation Information') }}</h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label fw-bold">{{ __('Lead') }}</label>
                            <p class="form-control-plaintext">
                                @if ($evaluation->lead)
                                    <a href="{{ route('leads.show', $evaluation->lead_id) }}" class="text-primary">
                                        {{ $evaluation->lead->name }}
                                    </a>
                                @else
                                    <span class="text-muted">{{ __('N/A') }}</span>
                                @endif
                            </p>
                        </div>

                        <div class="col-md-6 mb-3">
                            <label class="form-label fw-bold">{{ __('Evaluator') }}</label>
                            <p class="form-control-plaintext">
                                {{ $evaluation->evaluator->name ?? __('N/A') }}
                            </p>
                        </div>

                        <div class="col-md-6 mb-3">
                            <label class="form-label fw-bold">{{ __('University Major') }}</label>
                            <p class="form-control-plaintext">{{ $evaluation->university_major ?? __('N/A') }}</p>
                        </div>

                        <div class="col-md-6 mb-3">
                            <label class="form-label fw-bold">{{ __('Career Stage') }}</label>
                            <p class="form-control-plaintext">
                                @if ($evaluation->career_stage)
                                    @switch($evaluation->career_stage)
                                        @case('student')
                                            <span class="badge bg-info">{{ __('Student') }}</span>
                                        @break

                                        @case('graduate_employed_same_field')
                                            <span class="badge bg-success">{{ __('Graduate - Employed in Same Field') }}</span>
                                        @break

                                        @case('graduate_employed_different_field')
                                            <span
                                                class="badge bg-warning">{{ __('Graduate - Employed in Different Field') }}</span>
                                        @break

                                        @case('graduate_unemployed')
                                            <span class="badge bg-danger">{{ __('Graduate - Unemployed') }}</span>
                                        @break
                                    @endswitch
                                @else
                                    <span class="text-muted">{{ __('N/A') }}</span>
                                @endif
                            </p>
                        </div>

                        <div class="col-md-6 mb-3">
                            <label class="form-label fw-bold">{{ __('Engagement Level') }}</label>
                            <p class="form-control-plaintext">
                                @if ($evaluation->engagement_level)
                                    <span class="badge bg-{{ $evaluation->engagement_badge }}">
                                        @switch($evaluation->engagement_level)
                                            @case('highly_engaged')
                                                {{ __('Highly Engaged') }}
                                            @break

                                            @case('low_engagement')
                                                {{ __('Low Engagement') }}
                                            @break

                                            @case('not_engaged')
                                                {{ __('Not Engaged') }}
                                            @break
                                        @endswitch
                                    </span>
                                @else
                                    <span class="text-muted">{{ __('N/A') }}</span>
                                @endif
                            </p>
                        </div>

                        <div class="col-md-6 mb-3">
                            <label class="form-label fw-bold">{{ __('Course Fit Level') }}</label>
                            <p class="form-control-plaintext">
                                @if ($evaluation->course_fit_level)
                                    <span class="badge bg-{{ $evaluation->course_fit_badge }}">
                                        @switch($evaluation->course_fit_level)
                                            @case('perfect_fit')
                                                {{ __('Perfect Fit') }}
                                            @break

                                            @case('good_fit')
                                                {{ __('Good Fit') }}
                                            @break

                                            @case('partial_fit')
                                                {{ __('Partial Fit') }}
                                            @break

                                            @case('not_a_fit')
                                                {{ __('Not a Fit') }}
                                            @break
                                        @endswitch
                                    </span>
                                @else
                                    <span class="text-muted">{{ __('N/A') }}</span>
                                @endif
                            </p>
                        </div>

                        <div class="col-md-12 mb-3">
                            <label class="form-label fw-bold">{{ __('Interest Indicators') }}</label>
                            <p class="form-control-plaintext">
                                @if ($evaluation->interest_indicators && count($evaluation->interest_indicators) > 0)
                                    @foreach ($evaluation->interest_indicators as $indicator)
                                        <span class="badge bg-primary me-1">
                                            @switch($indicator)
                                                @case('asked_about_price')
                                                    {{ __('Asked about price') }}
                                                @break

                                                @case('asked_about_content')
                                                    {{ __('Asked about course content') }}
                                                @break

                                                @case('asked_about_duration')
                                                    {{ __('Asked about course duration') }}
                                                @break

                                                @case('asked_about_certification')
                                                    {{ __('Asked about certification / employment') }}
                                                @break

                                                @case('no_questions')
                                                    {{ __('No questions / Just browsing') }}
                                                @break

                                                @case('other')
                                                    {{ __('Other') }}
                                                @break

                                                @default
                                                    {{ $indicator }}
                                            @endswitch
                                        </span>
                                    @endforeach
                                @else
                                    <span class="text-muted">{{ __('N/A') }}</span>
                                @endif
                            </p>
                        </div>

                        <div class="col-md-6 mb-3">
                            <label class="form-label fw-bold">{{ __('Is Potential Lead?') }}</label>
                            <p class="form-control-plaintext">
                                <span class="badge bg-{{ $evaluation->potential_badge }}">
                                    {{ __($evaluation->potential_label) }}
                                </span>
                            </p>
                        </div>

                        @if ($evaluation->is_potential_lead === 'no' && $evaluation->rejection_reasons)
                            <div class="col-md-12 mb-3">
                                <label class="form-label fw-bold">{{ __('Rejection / Hesitation Reasons') }}</label>
                                <p class="form-control-plaintext">
                                    @if (count($evaluation->rejection_reasons) > 0)
                                        @foreach ($evaluation->rejection_reasons as $reason)
                                            <span class="badge bg-warning text-dark me-1">
                                                @switch($reason)
                                                    @case('price')
                                                        {{ __('Price') }}
                                                    @break

                                                    @case('timing_not_suitable')
                                                        {{ __('Timing not suitable') }}
                                                    @break

                                                    @case('not_interested_in_field')
                                                        {{ __('Not interested in the field') }}
                                                    @break

                                                    @case('needs_approval')
                                                        {{ __('Needs approval') }}
                                                    @break

                                                    @case('other')
                                                        {{ __('Other reasons') }}
                                                    @break

                                                    @default
                                                        {{ $reason }}
                                                @endswitch
                                            </span>
                                        @endforeach
                                    @else
                                        <span class="text-muted">{{ __('N/A') }}</span>
                                    @endif
                                </p>
                            </div>
                        @endif

                        <div class="col-md-6 mb-3">
                            <label class="form-label fw-bold">{{ __('City') }}</label>
                            <p class="form-control-plaintext">{{ $evaluation->city ?? __('N/A') }}</p>
                        </div>

                        <div class="col-md-6 mb-3">
                            <label class="form-label fw-bold">{{ __('Rating') }}</label>
                            <p class="form-control-plaintext">
                                @if ($evaluation->rating)
                                    <span class="text-warning">
                                        @for ($i = 1; $i <= 5; $i++)
                                            @if ($i <= $evaluation->rating)
                                                <i class="fas fa-star"></i>
                                            @else
                                                <i class="far fa-star text-muted"></i>
                                            @endif
                                        @endfor
                                        <span class="text-dark ms-2">({{ $evaluation->rating }}/5)</span>
                                    </span>
                                @else
                                    <span class="text-muted">{{ __('N/A') }}</span>
                                @endif
                            </p>
                        </div>

                        <div class="col-md-12 mb-3">
                            <label class="form-label fw-bold">{{ __('Notes') }}</label>
                            <p class="form-control-plaintext">{{ $evaluation->notes ?? __('N/A') }}</p>
                        </div>

                        <div class="col-md-6 mb-3">
                            <label class="form-label fw-bold">{{ __('Created At') }}</label>
                            <p class="form-control-plaintext">{{ $evaluation->created_at->format('Y-m-d H:i:s') }}</p>
                        </div>

                        <div class="col-md-6 mb-3">
                            <label class="form-label fw-bold">{{ __('Updated At') }}</label>
                            <p class="form-control-plaintext">{{ $evaluation->updated_at->format('Y-m-d H:i:s') }}</p>
                        </div>
                    </div>

                    <div class="row mt-3">
                        <div class="col-12 text-end">
                            <a href="{{ route('quality-evaluations.index') }}"
                                class="btn btn-secondary">{{ __('Back to List') }}</a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
