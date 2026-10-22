@extends('layouts.admin')
@section('page-title')
    {{ __('Create Quality Evaluation') }}
@endsection

@section('title')
    {{ __('Create Quality Evaluation') }}
@endsection

@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">{{ __('Home') }}</a></li>
    <li class="breadcrumb-item"><a href="{{ route('quality-evaluations.index') }}">{{ __('Quality Evaluations') }}</a></li>
    <li class="breadcrumb-item">{{ __('Create') }}</li>
@endsection

@section('content')
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-body">
                    <form method="POST" action="{{ route('quality-evaluations.store') }}">
                        @csrf
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="lead_id" class="form-label">{{ __('Lead') }} <span
                                        class="text-danger">*</span></label>
                                {{-- Server-searched picker. This was a <select> holding one
                                     <option> per lead in the company; on a large tenant the
                                     page ran out of memory and rendered blank. --}}
                                @include('sales_meetings._search_picker', [
                                    'pickerId' => 'lead_id',
                                    'fieldName' => 'lead_id',
                                    'url' => route('quality-evaluations.leads.search'),
                                    'selectedId' => old('lead_id', $leadId),
                                    'selectedName' => $selectedLead->name ?? '',
                                    'placeholder' => __('Search lead by name, phone or email'),
                                    'required' => true,
                                ])
                            </div>

                            <div class="col-md-6 mb-3">
                                <label for="university_major" class="form-label">{{ __('University Major') }}</label>
                                <input type="text" class="form-control" id="university_major" name="university_major"
                                    value="{{ old('university_major') }}"
                                    placeholder="{{ __('Enter university major') }}">
                            </div>

                            <div class="col-md-6 mb-3">
                                <label for="career_stage" class="form-label">{{ __('Career Stage') }}</label>
                                <select class="form-control" id="career_stage" name="career_stage">
                                    <option value="">{{ __('Select Career Stage') }}</option>
                                    <option value="student" {{ old('career_stage') == 'student' ? 'selected' : '' }}>
                                        {{ __('Student') }}</option>
                                    <option value="graduate_employed_same_field"
                                        {{ old('career_stage') == 'graduate_employed_same_field' ? 'selected' : '' }}>
                                        {{ __('Graduate - Employed in Same Field') }}</option>
                                    <option value="graduate_employed_different_field"
                                        {{ old('career_stage') == 'graduate_employed_different_field' ? 'selected' : '' }}>
                                        {{ __('Graduate - Employed in Different Field') }}</option>
                                    <option value="graduate_unemployed"
                                        {{ old('career_stage') == 'graduate_unemployed' ? 'selected' : '' }}>
                                        {{ __('Graduate - Unemployed') }}</option>
                                </select>
                            </div>

                            <div class="col-md-6 mb-3">
                                <label for="engagement_level" class="form-label">{{ __('Engagement Level') }} <span class="text-danger">*</span></label>
                                <select class="form-control" id="engagement_level" name="engagement_level" required>
                                    <option value="">{{ __('Select Engagement Level') }}</option>
                                    <option value="highly_engaged"
                                        {{ old('engagement_level') == 'highly_engaged' ? 'selected' : '' }}>
                                        {{ __('Highly Engaged') }}</option>
                                    <option value="low_engagement"
                                        {{ old('engagement_level') == 'low_engagement' ? 'selected' : '' }}>
                                        {{ __('Low Engagement') }}</option>
                                    <option value="not_engaged"
                                        {{ old('engagement_level') == 'not_engaged' ? 'selected' : '' }}>
                                        {{ __('Not Engaged') }}</option>
                                </select>
                            </div>

                            <div class="col-md-12 mb-3">
                                <label class="form-label">{{ __('Interest Indicators') }}</label>
                                <div class="row">
                                    <div class="col-md-4">
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" name="interest_indicators[]"
                                                value="asked_about_price" id="interest_price"
                                                {{ in_array('asked_about_price', old('interest_indicators', [])) ? 'checked' : '' }}>
                                            <label class="form-check-label"
                                                for="interest_price">{{ __('Asked about price') }}</label>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" name="interest_indicators[]"
                                                value="asked_about_content" id="interest_content"
                                                {{ in_array('asked_about_content', old('interest_indicators', [])) ? 'checked' : '' }}>
                                            <label class="form-check-label"
                                                for="interest_content">{{ __('Asked about course content') }}</label>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" name="interest_indicators[]"
                                                value="asked_about_duration" id="interest_duration"
                                                {{ in_array('asked_about_duration', old('interest_indicators', [])) ? 'checked' : '' }}>
                                            <label class="form-check-label"
                                                for="interest_duration">{{ __('Asked about course duration') }}</label>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" name="interest_indicators[]"
                                                value="asked_about_certification" id="interest_cert"
                                                {{ in_array('asked_about_certification', old('interest_indicators', [])) ? 'checked' : '' }}>
                                            <label class="form-check-label"
                                                for="interest_cert">{{ __('Asked about certification / employment') }}</label>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" name="interest_indicators[]"
                                                value="no_questions" id="interest_none"
                                                {{ in_array('no_questions', old('interest_indicators', [])) ? 'checked' : '' }}>
                                            <label class="form-check-label"
                                                for="interest_none">{{ __('No questions / Just browsing') }}</label>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" name="interest_indicators[]"
                                                value="other" id="interest_other"
                                                {{ in_array('other', old('interest_indicators', [])) ? 'checked' : '' }}>
                                            <label class="form-check-label"
                                                for="interest_other">{{ __('Other') }}</label>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="col-md-6 mb-3">
                                <label for="course_fit_level" class="form-label">{{ __('Course Fit Level') }}</label>
                                <select class="form-control" id="course_fit_level" name="course_fit_level">
                                    <option value="">{{ __('Select Course Fit Level') }}</option>
                                    <option value="perfect_fit"
                                        {{ old('course_fit_level') == 'perfect_fit' ? 'selected' : '' }}>
                                        {{ __('Perfect Fit') }}</option>
                                    <option value="good_fit"
                                        {{ old('course_fit_level') == 'good_fit' ? 'selected' : '' }}>{{ __('Good Fit') }}
                                    </option>
                                    <option value="partial_fit"
                                        {{ old('course_fit_level') == 'partial_fit' ? 'selected' : '' }}>
                                        {{ __('Partial Fit') }}</option>
                                    <option value="not_a_fit"
                                        {{ old('course_fit_level') == 'not_a_fit' ? 'selected' : '' }}>
                                        {{ __('Not a Fit') }}</option>
                                </select>
                            </div>

                            <div class="col-md-6 mb-3">
                                <label for="is_potential_lead" class="form-label">{{ __('Is the Lead Potential?') }}
                                    <span class="text-danger">*</span></label>
                                <select class="form-control" id="is_potential_lead" name="is_potential_lead" required>
                                    <option value="yes" {{ old('is_potential_lead') == 'yes' ? 'selected' : '' }}>
                                        {{ __('Yes') }}</option>
                                    <option value="no" {{ old('is_potential_lead') == 'no' ? 'selected' : '' }}>
                                        {{ __('No') }}</option>
                                    <option value="unknown" {{ old('is_potential_lead', 'unknown') == 'unknown' ? 'selected' : '' }}>
                                        {{ __('Unknown') }}</option>
                                </select>
                            </div>

                            <div class="col-md-12 mb-3" id="rejection_reasons_section" style="display: none;">
                                <label class="form-label">{{ __('Reason for Rejection / Hesitation') }}</label>
                                <div class="row">
                                    <div class="col-md-3">
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" name="rejection_reasons[]"
                                                value="price" id="rejection_price"
                                                {{ in_array('price', old('rejection_reasons', [])) ? 'checked' : '' }}>
                                            <label class="form-check-label"
                                                for="rejection_price">{{ __('Price') }}</label>
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" name="rejection_reasons[]"
                                                value="timing_not_suitable" id="rejection_timing"
                                                {{ in_array('timing_not_suitable', old('rejection_reasons', [])) ? 'checked' : '' }}>
                                            <label class="form-check-label"
                                                for="rejection_timing">{{ __('Timing not suitable') }}</label>
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" name="rejection_reasons[]"
                                                value="not_interested_in_field" id="rejection_interest"
                                                {{ in_array('not_interested_in_field', old('rejection_reasons', [])) ? 'checked' : '' }}>
                                            <label class="form-check-label"
                                                for="rejection_interest">{{ __('Not interested in the field') }}</label>
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" name="rejection_reasons[]"
                                                value="needs_approval" id="rejection_approval"
                                                {{ in_array('needs_approval', old('rejection_reasons', [])) ? 'checked' : '' }}>
                                            <label class="form-check-label"
                                                for="rejection_approval">{{ __('Needs approval') }}</label>
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" name="rejection_reasons[]"
                                                value="other" id="rejection_other"
                                                {{ in_array('other', old('rejection_reasons', [])) ? 'checked' : '' }}>
                                            <label class="form-check-label"
                                                for="rejection_other">{{ __('Other reasons') }}</label>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="col-md-6 mb-3">
                                <label for="city" class="form-label">{{ __('City') }}</label>
                                <input type="text" class="form-control" id="city" name="city"
                                    value="{{ old('city') }}" placeholder="{{ __('Enter city') }}">
                            </div>

                            <div class="col-md-6 mb-3">
                                <label for="rating" class="form-label">
                                    {{ __('Rating (1-5)') }}
                                    <button type="button" class="btn btn-sm btn-link p-0 ms-1" data-bs-toggle="modal"
                                        data-bs-target="#ratingReferenceModal" title="{{ __('View Rating Reference') }}">
                                        <i class="ti ti-info-circle text-info"></i>
                                    </button>
                                </label>
                                <select class="form-control" id="rating" name="rating">
                                    <option value="">{{ __('Select Rating') }}</option>
                                    <option value="5" {{ old('rating') == '5' ? 'selected' : '' }}>5 -
                                        {{ __('Sales-Ready Lead') }}</option>
                                    <option value="4" {{ old('rating') == '4' ? 'selected' : '' }}>4 -
                                        {{ __('Good Fit / Engaged, with Objections') }}</option>
                                    <option value="3" {{ old('rating') == '3' ? 'selected' : '' }}>3 -
                                        {{ __('Good Fit / Low Engagement') }}</option>
                                    <option value="2" {{ old('rating') == '2' ? 'selected' : '' }}>2 -
                                        {{ __('Low Fit / Low Engagement') }}</option>
                                    <option value="1" {{ old('rating') == '1' ? 'selected' : '' }}>1 -
                                        {{ __('Unqualified') }}</option>
                                </select>
                            </div>

                            <div class="col-md-12 mb-3">
                                <label for="notes" class="form-label">{{ __('Notes') }}</label>
                                <textarea class="form-control" id="notes" name="notes" rows="4"
                                    placeholder="{{ __('Enter any additional notes') }}">{{ old('notes') }}</textarea>
                            </div>

                            <div class="col-12 text-end">
                                <a href="{{ route('quality-evaluations.index') }}"
                                    class="btn btn-secondary">{{ __('Cancel') }}</a>
                                <button type="submit" class="btn btn-primary">{{ __('Create Evaluation') }}</button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="ratingReferenceModal" tabindex="-1" aria-labelledby="ratingReferenceModalLabel"
        aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="ratingReferenceModalLabel">{{ __('Rating Reference Guide') }}</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="table-responsive">
                        <table class="table table-bordered table-hover">
                            <thead class="table-light">
                                <tr>
                                    <th width="20%">{{ __('Rating') }}</th>
                                    <th width="30%">{{ __('Definition') }}</th>
                                    <th width="50%">{{ __('Description') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr class="table-success">
                                    <td class="fw-bold">
                                        <div class="d-flex align-items-center">
                                            <span class="me-2">5</span>
                                            <div>
                                                @for ($i = 1; $i <= 5; $i++)
                                                    <i class="ti ti-star-filled text-warning"></i>
                                                @endfor
                                            </div>
                                        </div>
                                        <small class="text-muted d-block mt-1">{{ __('Sales-Ready Lead') }}</small>
                                    </td>
                                    <td>{{ __('High potential to close or already closed +') }}</td>
                                    <td>{{ __('Has budget + decision-making power + a clear need + active engagement + ready to enroll/purchase within a set timeline') }}
                                    </td>
                                </tr>
                                <tr class="table-info">
                                    <td class="fw-bold">
                                        <div class="d-flex align-items-center">
                                            <span class="me-2">4</span>
                                            <div>
                                                @for ($i = 1; $i <= 5; $i++)
                                                    @if ($i <= 4)
                                                        <i class="ti ti-star-filled text-warning"></i>
                                                    @else
                                                        <i class="ti ti-star text-muted"></i>
                                                    @endif
                                                @endfor
                                            </div>
                                        </div>
                                        <small
                                            class="text-muted d-block mt-1">{{ __('Good Fit / Engaged, with Objections') }}</small>
                                    </td>
                                    <td>{{ __('Shows demand & interaction') }}</td>
                                    <td>{{ __('Interested in a specific course/topic, needs are known, but has concerns or specific objections') }}
                                    </td>
                                </tr>
                                <tr class="table-warning">
                                    <td class="fw-bold">
                                        <div class="d-flex align-items-center">
                                            <span class="me-2">3</span>
                                            <div>
                                                @for ($i = 1; $i <= 5; $i++)
                                                    @if ($i <= 3)
                                                        <i class="ti ti-star-filled text-warning"></i>
                                                    @else
                                                        <i class="ti ti-star text-muted"></i>
                                                    @endif
                                                @endfor
                                            </div>
                                        </div>
                                        <small
                                            class="text-muted d-block mt-1">{{ __('Good Fit / Low Engagement') }}</small>
                                    </td>
                                    <td>{{ __('Right profile, but not motivated') }}</td>
                                    <td>{{ __('Correct specialization/need, but weak activity or unclear interest') }}
                                    </td>
                                </tr>
                                <tr class="table-danger">
                                    <td class="fw-bold">
                                        <div class="d-flex align-items-center">
                                            <span class="me-2">2</span>
                                            <div>
                                                @for ($i = 1; $i <= 5; $i++)
                                                    @if ($i <= 2)
                                                        <i class="ti ti-star-filled text-warning"></i>
                                                    @else
                                                        <i class="ti ti-star text-muted"></i>
                                                    @endif
                                                @endfor
                                            </div>
                                        </div>
                                        <small
                                            class="text-muted d-block mt-1">{{ __('Low Fit / Low Engagement') }}</small>
                                    </td>
                                    <td>{{ __('Not currently relevant') }}</td>
                                    <td>{{ __('Shows minimal interaction, unclear eligibility') }}</td>
                                </tr>
                                <tr class="table-secondary">
                                    <td class="fw-bold">
                                        <div class="d-flex align-items-center">
                                            <span class="me-2">1</span>
                                            <div>
                                                @for ($i = 1; $i <= 5; $i++)
                                                    @if ($i <= 1)
                                                        <i class="ti ti-star-filled text-warning"></i>
                                                    @else
                                                        <i class="ti ti-star text-muted"></i>
                                                    @endif
                                                @endfor
                                            </div>
                                        </div>
                                        <small class="text-muted d-block mt-1">{{ __('Unqualified') }}</small>
                                    </td>
                                    <td>{{ __('Not a viable lead') }}</td>
                                    <td>{{ __('No interest, no fit, no buying potential') }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary"
                        data-bs-dismiss="modal">{{ __('Close') }}</button>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('script-page')
    <script>
        $(document).ready(function() {
            $('#is_potential_lead').on('change', function() {
                if ($(this).val() == 'no') {
                    $('#rejection_reasons_section').show();
                } else {
                    $('#rejection_reasons_section').hide();
                }
            });

            if ($('#is_potential_lead').val() == 'no') {
                $('#rejection_reasons_section').show();
            }
        });
    </script>
@endpush
