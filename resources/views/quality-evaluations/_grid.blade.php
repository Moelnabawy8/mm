<div class="row">
    @forelse($evaluations as $evaluation)
        <div class="col-xl-3 col-lg-4 col-md-6 col-sm-12 mb-4">
            <div class="card h-100 shadow-sm hover-shadow-lg transition-all">
                <div class="card-header bg-gradient-primary text-white">
                    <div class="d-flex justify-content-between align-items-center">
                        <h6 class="mb-0 text-truncate">
                            @if ($evaluation->lead)
                                <i class="ti ti-user me-1"></i>{{ $evaluation->lead->name }}
                            @else
                                <span class="text-muted">{{ __('N/A') }}</span>
                            @endif
                        </h6>
                        <span class="badge bg-{{ $evaluation->potential_badge }}">
                            @switch($evaluation->is_potential_lead)
                                @case('yes') {{ __('Potential') }} @break
                                @case('no') {{ __('Not Potential') }} @break
                                @default {{ __('Unknown') }}
                            @endswitch
                        </span>
                    </div>
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <div class="d-flex align-items-center justify-content-center mb-2">
                            @php
                                $ratingValue = (int) ($evaluation->rating ?? 0);
                            @endphp
                            @for ($i = 1; $i <= 5; $i++)
                                @if ($i <= $ratingValue)
                                    <i class="fas fa-star text-warning" style="font-size: 1.2rem;"></i>
                                @else
                                    <i class="far fa-star text-muted" style="font-size: 1.2rem;"></i>
                                @endif
                            @endfor
                        </div>
                        @if ($ratingValue > 0)
                            <p class="text-center text-muted mb-0 small">{{ __('Rating') }}: {{ $ratingValue }}/5</p>
                        @endif
                    </div>

                    <div class="mb-2">
                        <small class="text-muted d-block mb-1"><i class="ti ti-school me-1"></i>{{ __('Major') }}</small>
                        <p class="mb-0 fw-medium">{{ $evaluation->university_major ?? __('N/A') }}</p>
                    </div>

                    <div class="mb-2">
                        <small class="text-muted d-block mb-1"><i class="ti ti-briefcase me-1"></i>{{ __('Career Stage') }}</small>
                        @if ($evaluation->career_stage)
                            @switch($evaluation->career_stage)
                                @case('student')
                                    <span class="badge bg-info">{{ __('Student') }}</span>
                                @break

                                @case('graduate_employed_same_field')
                                    <span class="badge bg-success">{{ __('Graduate - Same Field') }}</span>
                                @break

                                @case('graduate_employed_different_field')
                                    <span class="badge bg-warning">{{ __('Graduate - Different Field') }}</span>
                                @break

                                @case('graduate_unemployed')
                                    <span class="badge bg-danger">{{ __('Graduate - Unemployed') }}</span>
                                @break
                            @endswitch
                        @else
                            <span class="text-muted">{{ __('N/A') }}</span>
                        @endif
                    </div>

                    <div class="mb-2">
                        <small class="text-muted d-block mb-1"><i class="ti ti-flame me-1"></i>{{ __('Engagement') }}</small>
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
                    </div>

                    <div class="mb-2">
                        <small class="text-muted d-block mb-1"><i class="ti ti-target me-1"></i>{{ __('Course Fit') }}</small>
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
                    </div>

                    <div class="mb-2">
                        <small class="text-muted d-block mb-1"><i class="ti ti-map-pin me-1"></i>{{ __('City') }}</small>
                        <p class="mb-0">{{ $evaluation->city ?? __('N/A') }}</p>
                    </div>

                    <div class="mb-0">
                        <small class="text-muted"><i class="ti ti-clock me-1"></i>{{ $evaluation->created_at->diffForHumans() }}</small>
                    </div>
                </div>
                <div class="card-footer bg-light">
                    <div class="d-flex justify-content-between align-items-center">
                        @can('manage deal')
                            <a href="{{ route('quality-evaluations.show', $evaluation->id) }}"
                                class="btn btn-sm btn-info" data-bs-toggle="tooltip"
                                title="{{ __('View') }}">
                                <i class="ti ti-eye"></i> {{ __('View') }}
                            </a>
                        @endcan
                        @can('edit deal')
                            @if ($evaluation->evaluator_id == Auth::user()->id)
                                <a href="{{ route('quality-evaluations.edit', $evaluation->id) }}"
                                    class="btn btn-sm btn-primary" data-bs-toggle="tooltip"
                                    title="{{ __('Edit') }}">
                                    <i class="ti ti-pencil"></i> {{ __('Edit') }}
                                </a>
                            @endif
                        @endcan
                        @if (in_array(Auth::user()->type, ['Alaa,Saba,aber', 'fadi']) && $evaluation->evaluator_id != Auth::user()->id)
                            <a href="{{ route('quality-evaluations.edit', $evaluation->id) }}"
                                class="btn btn-sm btn-primary" data-bs-toggle="tooltip"
                                title="{{ __('Edit') }}">
                                <i class="ti ti-pencil"></i> {{ __('Edit') }}
                            </a>
                        @endif
                        @if ($evaluation->lead)
                            @php
                                // leads.phone can hold several numbers separated by
                                // commas or newlines; wa.me takes digits only. Same
                                // handling as the lead page.
                                $qeNumbers = array_values(
                                    array_filter(array_map('trim', preg_split('/[,\n]+/', (string) $evaluation->lead->phone))),
                                );
                                $qeWhatsapp = $qeNumbers ? preg_replace('/[^0-9]/', '', $qeNumbers[0]) : '';
                            @endphp
                            @if ($qeWhatsapp !== '')
                                <a href="https://wa.me/{{ $qeWhatsapp }}" target="_blank" rel="noopener"
                                    class="btn btn-sm btn-success" data-bs-toggle="tooltip"
                                    title="{{ __('WhatsApp') }}: {{ $qeNumbers[0] }}">
                                    <i class="ti ti-brand-whatsapp"></i>
                                </a>
                            @endif
                            <a href="{{ route('leads.show', $evaluation->lead_id) }}"
                                class="btn btn-sm btn-secondary" data-bs-toggle="tooltip"
                                title="{{ __('View Lead') }}">
                                <i class="ti ti-user"></i>
                            </a>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    @empty
        <div class="col-12">
            <div class="card">
                <div class="card-body text-center py-5">
                    <i class="ti ti-inbox" style="font-size: 48px; color: #ccc;"></i>
                    <p class="mt-3 text-muted">{{ __('No evaluations found') }}</p>
                </div>
            </div>
        </div>
    @endforelse
</div>

@if ($evaluations->hasPages())
    <div class="d-flex justify-content-center mt-4">
        {{ $evaluations->appends(request()->query())->links('pagination::bootstrap-5') }}
    </div>
@endif

<style>
.hover-shadow-lg {
    transition: all 0.3s ease;
}
.hover-shadow-lg:hover {
    box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.15) !important;
    transform: translateY(-2px);
}
.bg-gradient-primary {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
}
</style>
