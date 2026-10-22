{{-- الخمس أرقام التي تلخص أي مجموعة التزامات. "نسبة الالتزام" تحسب المحسوم فقط. --}}
<div class="row">
    <div class="col-md col-6 mb-3">
        <div class="card h-100 mb-0"><div class="card-body py-3">
            <h6 class="text-muted mb-1 small">{{ __('Open') }}</h6>
            <h4 class="mb-0" dir="ltr">{{ number_format($tally['open']) }}</h4>
        </div></div>
    </div>
    <div class="col-md col-6 mb-3">
        <div class="card h-100 mb-0"><div class="card-body py-3">
            <h6 class="text-muted mb-1 small">{{ __('Overdue') }}</h6>
            <h4 class="mb-0 {{ $tally['overdue'] ? 'text-danger' : '' }}" dir="ltr">{{ number_format($tally['overdue']) }}</h4>
        </div></div>
    </div>
    <div class="col-md col-6 mb-3">
        <div class="card h-100 mb-0"><div class="card-body py-3">
            <h6 class="text-muted mb-1 small">{{ __('Kept') }}</h6>
            <h4 class="mb-0 text-success" dir="ltr">{{ number_format($tally['done']) }}</h4>
        </div></div>
    </div>
    <div class="col-md col-6 mb-3">
        <div class="card h-100 mb-0"><div class="card-body py-3">
            <h6 class="text-muted mb-1 small">{{ __('Missed') }}</h6>
            <h4 class="mb-0 {{ $tally['missed'] ? 'text-danger' : '' }}" dir="ltr">{{ number_format($tally['missed']) }}</h4>
        </div></div>
    </div>
    <div class="col-md col-12 mb-3">
        <div class="card h-100 mb-0"><div class="card-body py-3">
            <h6 class="text-muted mb-1 small">{{ __('Kept rate') }}</h6>
            <h4 class="mb-0" dir="ltr">{{ $tally['kept_rate'] === null ? '—' : $tally['kept_rate'] . '%' }}</h4>
            <small class="text-muted">{{ __('of commitments that reached a verdict') }}</small>
        </div></div>
    </div>
</div>
