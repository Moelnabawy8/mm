<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-body table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>{{ __('Lead') }}</th>
                            <th>{{ __('Users') }}</th>
                            <th>{{ __('University Major') }}</th>
                            <th>{{ __('Career Stage') }}</th>
                            <th>{{ __('Engagement') }}</th>
                            <th>{{ __('Course Fit') }}</th>
                            <th>{{ __('Potential') }}</th>
                            <th>{{ __('Rating') }}</th>
                            <th>{{ __('City') }}</th>
                            <th>{{ __('Date') }}</th>
                            <th class="text-end">{{ __('Actions') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($evaluations as $evaluation)
                            <tr>
                                <td>
                                    @if ($evaluation->lead)
                                        <a href="{{ route('leads.show', $evaluation->lead_id) }}" class="text-primary">
                                            {{ $evaluation->lead->name }}
                                        </a>
                                        @php
                                            // see the note in _grid.blade.php
                                            $qeNumbers = array_values(
                                                array_filter(array_map('trim', preg_split('/[,\n]+/', (string) $evaluation->lead->phone))),
                                            );
                                            $qeWhatsapp = $qeNumbers ? preg_replace('/[^0-9]/', '', $qeNumbers[0]) : '';
                                        @endphp
                                        @if ($qeWhatsapp !== '')
                                            <a href="https://wa.me/{{ $qeWhatsapp }}" target="_blank" rel="noopener"
                                                class="text-success ms-2" data-bs-toggle="tooltip"
                                                title="{{ __('WhatsApp') }}: {{ $qeNumbers[0] }}">
                                                <i class="ti ti-brand-whatsapp"></i>
                                            </a>
                                        @endif
                                    @else
                                        <span class="text-muted">{{ __('N/A') }}</span>
                                    @endif
                                </td>
                                <td>
                                    @if ($evaluation->lead && $evaluation->lead->users->count())
                                        <div class="d-flex flex-wrap gap-1">
                                            @foreach ($evaluation->lead->users as $assignedUser)
                                                <span class="badge bg-light-primary text-primary border border-primary">
                                                    <i class="ti ti-user me-1"></i>{{ $assignedUser->name }}
                                                </span>
                                            @endforeach
                                        </div>
                                    @else
                                        <span class="text-muted">{{ __('Unassigned') }}</span>
                                    @endif
                                </td>
                                <td>
                                    {{ $evaluation->university_major == 'Other' ? $evaluation->university_major_other : $evaluation->university_major ?? __('N/A') }}
                                </td>
                                <td>
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
                                </td>
                                <td>
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

                                                @case('moderate')
                                                    {{ __('Moderate') }}
                                                @break
                                            @endswitch
                                        </span>
                                    @else
                                        <span class="text-muted">{{ __('N/A') }}</span>
                                    @endif
                                </td>
                                <td>
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
                                </td>
                                <td>
                                    <span class="badge bg-{{ $evaluation->potential_badge }}">
                                        {{ __($evaluation->potential_label) }}
                                    </span>
                                </td>
                                <td>
                                    <div class="d-flex align-items-center gap-1">
                                        @php
                                            $ratingValue = (int) ($evaluation->rating ?? 0);
                                        @endphp
                                        @for ($i = 1; $i <= 5; $i++)
                                            @if ($i <= $ratingValue)
                                                <i class="fas fa-star text-warning"></i>
                                            @else
                                                <i class="far fa-star text-muted"></i>
                                            @endif
                                        @endfor
                                        @if ($ratingValue > 0)
                                            <span class="ms-1 text-muted small">({{ $ratingValue }})</span>
                                        @endif
                                    </div>
                                </td>
                                <td>{{ $evaluation->city ?? __('N/A') }}</td>
                                <td>{{ $evaluation->created_at->diffForHumans() }}</td>
                                <td class="text-end">
                                    @can('manage deal')
                                        <a href="{{ route('quality-evaluations.show', $evaluation->id) }}"
                                            class="btn btn-sm btn-info" data-bs-toggle="tooltip"
                                            title="{{ __('View') }}">
                                            <i class="ti ti-eye"></i>
                                        </a>
                                    @endcan
                                    @can('edit deal')
                                        @if ($evaluation->evaluator_id == Auth::user()->id)
                                            <a href="{{ route('quality-evaluations.edit', $evaluation->id) }}"
                                                class="btn btn-sm btn-primary" data-bs-toggle="tooltip"
                                                title="{{ __('Edit') }}">
                                                <i class="ti ti-pencil"></i>
                                            </a>
                                        @endif
                                    @endcan
                                    @if (in_array(Auth::user()->type, ['Alaa,Saba,aber', 'fadi']) && $evaluation->evaluator_id != Auth::user()->id)
                                        <a href="{{ route('quality-evaluations.edit', $evaluation->id) }}"
                                            class="btn btn-sm btn-primary" data-bs-toggle="tooltip"
                                            title="{{ __('Edit') }}">
                                            <i class="ti ti-pencil"></i>
                                        </a>
                                    @endif
                                </td>
                            </tr>
                            @empty
                                <tr>
                                    <td colspan="11" class="text-center">{{ __('No evaluations found') }}</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                    @if ($evaluations->hasPages())
                        <div class="d-flex justify-content-center">
                            {{ $evaluations->appends(request()->query())->links('pagination::bootstrap-5') }}
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
