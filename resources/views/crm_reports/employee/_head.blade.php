{{-- Title, what the numbers mean, and the export/print actions for one report tab. --}}
@php
    $exportQuery = collect(request()->query())->except(['page'])->all();
@endphp
<div class="crm-section-head">
    <div>
        <h4>{{ $title }}</h4>
        @if (!empty($definition))
            <div class="crm-definition"><i class="ti ti-info-circle"></i> {{ $definition }}</div>
        @endif
    </div>
    <div class="d-flex gap-2 crm-no-print">
        <a href="{{ route('crm-reports.employee.export', $section) . ($exportQuery ? '?' . http_build_query($exportQuery) : '') }}"
           class="btn btn-sm btn-outline-success"><i class="ti ti-file-spreadsheet"></i> {{ __('Excel') }}</a>
        <button type="button" class="btn btn-sm btn-outline-secondary" data-crm-print><i class="ti ti-printer"></i> {{ __('Print / PDF') }}</button>
    </div>
</div>
