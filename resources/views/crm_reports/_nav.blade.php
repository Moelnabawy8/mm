{{-- Category switcher shared by the three CRM Reports pages. --}}
@php
    $crmCategories = [
        'employee'  => ['route' => 'crm-reports.employee', 'icon' => 'ti-user-check', 'label' => __('Employee Reports')],
        'data'      => ['route' => 'crm-reports.data', 'icon' => 'ti-database', 'label' => __('Data Reports')],
        'financial' => ['route' => 'crm-reports.financial', 'icon' => 'ti-report-money', 'label' => __('Financial Reports')],
    ];
@endphp
<div class="crm-cat-nav d-print-none mb-3" role="navigation" aria-label="{{ __('CRM Reports') }}">
    @foreach ($crmCategories as $key => $cat)
        <a href="{{ route($cat['route']) }}"
           class="crm-cat-link {{ $current === $key ? 'active' : '' }}"
           @if ($current === $key) aria-current="page" @endif>
            <i class="ti {{ $cat['icon'] }}"></i><span>{{ $cat['label'] }}</span>
        </a>
    @endforeach
</div>
