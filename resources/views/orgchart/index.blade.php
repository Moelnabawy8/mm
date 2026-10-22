@extends('layouts.admin')

@section('page-title'){{ __('Organizational Chart') }}@endsection

@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">{{ __('Dashboard') }}</a></li>
    <li class="breadcrumb-item"><a href="{{ url('finance/dashboard') }}">{{ __('Finance') }}</a></li>
    <li class="breadcrumb-item active">{{ __('Org Chart') }}</li>
@endsection

@section('content')
{{-- KPI row --}}
<div class="row mb-4">
    <div class="col-sm-6 col-md-3">
        <div class="card text-center border-0 shadow-sm">
            <div class="card-body py-3">
                <div class="text-muted small mb-1">{{ __('Total Departments') }}</div>
                <h3 class="fw-bold text-primary mb-0">{{ $totalDepartments }}</h3>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-md-3">
        <div class="card text-center border-0 shadow-sm">
            <div class="card-body py-3">
                <div class="text-muted small mb-1">{{ __('Total Employees') }}</div>
                <h3 class="fw-bold text-success mb-0">{{ $totalEmployees }}</h3>
            </div>
        </div>
    </div>
</div>

{{-- Org Chart Tree --}}
<div class="row">
    <div class="col-12">
        <div class="card border-0 shadow-sm">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0">{{ __('Company Structure') }}</h5>
                <div class="d-flex gap-2">
                    <button class="btn btn-sm btn-outline-secondary" id="expandAll">
                        <i class="ti ti-arrows-maximize me-1"></i>{{ __('Expand All') }}
                    </button>
                    <button class="btn btn-sm btn-outline-secondary" id="collapseAll">
                        <i class="ti ti-arrows-minimize me-1"></i>{{ __('Collapse') }}
                    </button>
                </div>
            </div>
            <div class="card-body" style="overflow-x: auto">
                @if($orgData->isEmpty())
                    <div class="text-center py-5">
                        <i class="ti ti-sitemap" style="font-size:56px;color:#ccc"></i>
                        <h5 class="mt-3">{{ __('No departments found.') }}</h5>
                        <p class="text-muted">{{ __('Add departments and employees to see the org chart.') }}</p>
                        <a href="{{ route('department.index') }}" class="btn btn-primary mt-2">{{ __('Manage Departments') }}</a>
                    </div>
                @else
                    {{-- Company root node --}}
                    <div class="org-tree">
                        <div class="org-node company-node text-center mx-auto mb-0" style="max-width:220px">
                            <div class="org-card bg-primary text-white p-3 rounded-3 shadow-sm">
                                <i class="ti ti-building" style="font-size:28px"></i>
                                <div class="fw-semibold mt-1">{{ config('app.name', 'Company') }}</div>
                                <div style="font-size:12px;opacity:.8">{{ $totalEmployees }} {{ __('Employees') }}</div>
                            </div>
                        </div>

                        {{-- Connector line down --}}
                        <div class="text-center"><div style="width:2px;height:32px;background:#dee2e6;margin:0 auto"></div></div>

                        {{-- Departments row --}}
                        <div class="d-flex flex-wrap justify-content-center gap-3 dept-row">
                            @foreach($orgData as $dept)
                            <div class="dept-branch text-center" style="min-width:200px;max-width:220px">
                                {{-- Dept card --}}
                                <div class="dept-card org-card bg-light border rounded-3 p-2 shadow-sm cursor-pointer dept-toggle"
                                     data-dept="{{ $dept['id'] }}">
                                    <div class="d-flex align-items-center gap-2">
                                        <span class="bg-primary text-white rounded p-1">
                                            <i class="ti ti-users" style="font-size:16px"></i>
                                        </span>
                                        <div class="text-start">
                                            <div class="fw-semibold" style="font-size:13px">{{ $dept['name'] }}</div>
                                            <div class="text-muted" style="font-size:11px">
                                                {{ count($dept['employees']) }} {{ __('members') }}
                                                <i class="ti ti-chevron-down ms-1 toggle-icon"></i>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                {{-- Connector --}}
                                @if(count($dept['employees']))
                                <div style="width:2px;height:20px;background:#dee2e6;margin:0 auto"></div>
                                @endif

                                {{-- Employees --}}
                                <div class="employees-list" id="emp-list-{{ $dept['id'] }}">
                                    @foreach($dept['employees'] as $emp)
                                    <div class="emp-card org-card border rounded-2 p-2 mb-1 shadow-sm text-start d-flex align-items-center gap-2"
                                         style="background:#fff;font-size:12px">
                                        <div class="avatar-sm bg-secondary text-white rounded-circle d-flex align-items-center justify-content-center"
                                             style="width:30px;height:30px;min-width:30px;font-size:13px;font-weight:600">
                                            {{ strtoupper(substr($emp['name'], 0, 1)) }}
                                        </div>
                                        <div>
                                            <div class="fw-semibold">{{ $emp['name'] }}</div>
                                            <div class="text-muted" style="font-size:10px">{{ $emp['designation'] }}</div>
                                        </div>
                                    </div>
                                    @endforeach
                                </div>
                            </div>
                            @endforeach
                        </div>
                    </div>
                @endif
            </div>
        </div>
    </div>
</div>

<style>
.org-tree { padding: 1.5rem 1rem; }
.dept-toggle { cursor: pointer; transition: .2s; }
.dept-toggle:hover { box-shadow: 0 4px 12px rgba(0,0,0,.12) !important; }
.employees-list { transition: all .3s ease; overflow: hidden; }
.employees-list.collapsed { display: none; }
</style>
@endsection

@push('scripts')
<script>
document.querySelectorAll('.dept-toggle').forEach(btn => {
    btn.addEventListener('click', function() {
        const id   = this.dataset.dept;
        const list = document.getElementById('emp-list-' + id);
        const icon = this.querySelector('.toggle-icon');
        if (!list) return;
        list.classList.toggle('collapsed');
        icon.className = list.classList.contains('collapsed')
            ? 'ti ti-chevron-right ms-1 toggle-icon'
            : 'ti ti-chevron-down ms-1 toggle-icon';
    });
});
document.getElementById('expandAll')?.addEventListener('click', function() {
    document.querySelectorAll('.employees-list').forEach(l => l.classList.remove('collapsed'));
    document.querySelectorAll('.toggle-icon').forEach(i => i.className = 'ti ti-chevron-down ms-1 toggle-icon');
});
document.getElementById('collapseAll')?.addEventListener('click', function() {
    document.querySelectorAll('.employees-list').forEach(l => l.classList.add('collapsed'));
    document.querySelectorAll('.toggle-icon').forEach(i => i.className = 'ti ti-chevron-right ms-1 toggle-icon');
});
</script>
@endpush
