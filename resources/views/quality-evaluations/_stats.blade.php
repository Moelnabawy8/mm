<div class="row">
    <div class="col-md-3 col-sm-6 mb-3">
        <div class="card shadow-sm border-0 h-100">
            <div class="card-body d-flex flex-column">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <h6 class="mb-0 text-muted">{{ __('Total Evaluations') }}</h6>
                    <i class="ti ti-clipboard-list fs-4 text-primary"></i>
                </div>
                <h3 class="mb-0 mt-auto">{{ $stats['total'] }}</h3>
            </div>
        </div>
    </div>
    <div class="col-md-3 col-sm-6 mb-3">
        <div class="card shadow-sm border-0 h-100 bg-light-success">
            <div class="card-body d-flex flex-column">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <h6 class="mb-0 text-muted">{{ __('Potential Leads') }}</h6>
                    <i class="ti ti-user-check fs-4 text-success"></i>
                </div>
                <h3 class="mb-0 mt-auto text-success">{{ $stats['potential'] }}</h3>
                @if ($stats['total'] > 0)
                    <small class="text-muted">{{ round(($stats['potential'] / $stats['total']) * 100) }}%</small>
                @endif
            </div>
        </div>
    </div>
    <div class="col-md-3 col-sm-6 mb-3">
        <div class="card shadow-sm border-0 h-100 bg-light-danger">
            <div class="card-body d-flex flex-column">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <h6 class="mb-0 text-muted">{{ __('Not Potential') }}</h6>
                    <i class="ti ti-user-x fs-4 text-danger"></i>
                </div>
                <h3 class="mb-0 mt-auto text-danger">{{ $stats['not_potential'] }}</h3>
                @if ($stats['total'] > 0)
                    <small class="text-muted">{{ round(($stats['not_potential'] / $stats['total']) * 100) }}%</small>
                @endif
            </div>
        </div>
    </div>
    <div class="col-md-3 col-sm-6 mb-3">
        <div class="card shadow-sm border-0 h-100 bg-light-secondary">
            <div class="card-body d-flex flex-column">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <h6 class="mb-0 text-muted">{{ __('Unknown') }}</h6>
                    <i class="ti ti-help fs-4 text-secondary"></i>
                </div>
                <h3 class="mb-0 mt-auto text-secondary">{{ $stats['unknown'] ?? 0 }}</h3>
                @if ($stats['total'] > 0)
                    <small class="text-muted">{{ round((($stats['unknown'] ?? 0) / $stats['total']) * 100) }}%</small>
                @endif
            </div>
        </div>
    </div>
    <div class="col-md-3 col-sm-6 mb-3">
        <div class="card shadow-sm border-0 h-100 bg-light-info">
            <div class="card-body d-flex flex-column">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <h6 class="mb-0 text-muted">{{ __('Highly Engaged') }}</h6>
                    <i class="ti ti-flame fs-4 text-info"></i>
                </div>
                <h3 class="mb-0 mt-auto text-info">{{ $stats['highly_engaged'] }}</h3>
            </div>
        </div>
    </div>
    <div class="col-md-3 col-sm-6 mb-3">
        <div class="card shadow-sm border-0 h-100 bg-light-primary">
            <div class="card-body d-flex flex-column">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <h6 class="mb-0 text-muted">{{ __('Perfect Fit') }}</h6>
                    <i class="ti ti-target fs-4 text-primary"></i>
                </div>
                <h3 class="mb-0 mt-auto text-primary">{{ $stats['perfect_fit'] }}</h3>
            </div>
        </div>
    </div>
    <div class="col-md-6 col-sm-6 mb-3">
        <div class="card shadow-sm border-0 h-100 bg-light-warning">
            <div class="card-body d-flex flex-column">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <h6 class="mb-0 text-muted">{{ __('Avg Rating') }}</h6>
                    <i class="ti ti-star-filled fs-4 text-warning"></i>
                </div>
                <h3 class="mb-0 mt-auto text-warning">{{ number_format($stats['avg_rating'], 1) }}</h3>
                <div class="d-flex align-items-center mt-1">
                    @for ($i = 1; $i <= 5; $i++)
                        @if ($i <= floor($stats['avg_rating']))
                            <i class="fas fa-star text-warning" style="font-size: 0.8rem;"></i>
                        @elseif ($i - $stats['avg_rating'] < 1)
                            <i class="fas fa-star-half-alt text-warning" style="font-size: 0.8rem;"></i>
                        @else
                            <i class="far fa-star text-muted" style="font-size: 0.8rem;"></i>
                        @endif
                    @endfor
                </div>
            </div>
        </div>
    </div>
</div>
