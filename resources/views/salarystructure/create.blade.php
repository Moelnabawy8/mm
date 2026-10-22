@extends('layouts.admin')

@section('page-title'){{ __('Create Salary Structure') }}@endsection

@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">{{ __('Dashboard') }}</a></li>
    <li class="breadcrumb-item"><a href="{{ route('salary-structure.index') }}">{{ __('Salary Structures') }}</a></li>
    <li class="breadcrumb-item active">{{ __('Create') }}</li>
@endsection

@section('content')
{{ Form::open(['url' => route('salary-structure.store'), 'method' => 'POST', 'class' => 'needs-validation', 'novalidate' => true]) }}
<div class="row">
    {{-- Left column --}}
    <div class="col-md-8">

        {{-- Basic Info --}}
        <div class="card mb-4">
            <div class="card-header"><h5 class="mb-0">{{ __('Basic Information') }}</h5></div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-8">
                        <div class="form-group">
                            {{ Form::label('name', __('Structure Name'), ['class' => 'form-label']) }}<x-required></x-required>
                            {{ Form::text('name', null, ['class' => 'form-control', 'placeholder' => __('e.g. Senior Engineer Package'), 'required' => 'required']) }}
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="form-group">
                            {{ Form::label('status', __('Status'), ['class' => 'form-label']) }}
                            {{ Form::select('status', ['active' => __('Active'), 'inactive' => __('Inactive')], 'active', ['class' => 'form-control']) }}
                        </div>
                    </div>
                    <div class="col-md-12">
                        <div class="form-group">
                            {{ Form::label('description', __('Description'), ['class' => 'form-label']) }}
                            {{ Form::textarea('description', null, ['class' => 'form-control', 'rows' => 2]) }}
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-group">
                            {{ Form::label('basic_salary', __('Basic Salary'), ['class' => 'form-label']) }}<x-required></x-required>
                            <div class="input-group">
                                <span class="input-group-text">{{ \Auth::user()->currencySymbol() ?? '$' }}</span>
                                {{ Form::number('basic_salary', null, ['class' => 'form-control', 'min' => 0, 'step' => '0.01', 'required' => 'required', 'id' => 'basic_salary']) }}
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- Allowances --}}
        <div class="card mb-4">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0">{{ __('Allowances') }}</h5>
                <button type="button" class="btn btn-sm btn-outline-success" id="add-allowance">
                    <i class="ti ti-plus me-1"></i>{{ __('Add') }}
                </button>
            </div>
            <div class="card-body" id="allowances-container">
                <p class="text-muted text-center py-2" id="no-allowance-msg">{{ __('No allowances added yet.') }}</p>
            </div>
        </div>

        {{-- Deductions --}}
        <div class="card mb-4">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0">{{ __('Deductions') }}</h5>
                <button type="button" class="btn btn-sm btn-outline-danger" id="add-deduction">
                    <i class="ti ti-plus me-1"></i>{{ __('Add') }}
                </button>
            </div>
            <div class="card-body" id="deductions-container">
                <p class="text-muted text-center py-2" id="no-deduction-msg">{{ __('No deductions added yet.') }}</p>
            </div>
        </div>

    </div>

    {{-- Right summary --}}
    <div class="col-md-4">
        <div class="card sticky-top" style="top:80px">
            <div class="card-header"><h5 class="mb-0">{{ __('Salary Summary') }}</h5></div>
            <div class="card-body">
                <table class="table table-sm mb-0">
                    <tr><td class="text-muted">{{ __('Basic Salary') }}</td><td class="text-end fw-bold" id="sum-basic">0.00</td></tr>
                    <tr class="text-success"><td>{{ __('+ Allowances') }}</td><td class="text-end" id="sum-allowances">0.00</td></tr>
                    <tr class="text-danger"><td>{{ __('- Deductions') }}</td><td class="text-end" id="sum-deductions">0.00</td></tr>
                    <tr class="border-top"><td class="fw-bold">{{ __('Net Salary') }}</td><td class="text-end fw-bold text-primary" id="sum-net">0.00</td></tr>
                </table>
            </div>
            <div class="card-footer">
                <button type="submit" class="btn btn-primary w-100">
                    <i class="ti ti-device-floppy me-1"></i>{{ __('Save Structure') }}
                </button>
                <a href="{{ route('salary-structure.index') }}" class="btn btn-secondary w-100 mt-2">
                    {{ __('Cancel') }}
                </a>
            </div>
        </div>
    </div>
</div>
{{ Form::close() }}
@endsection

@push('scripts')
<script>
const allowanceOptions = @json($allowanceOptions->map(fn($o) => ['id' => $o->id, 'name' => $o->name]));
const deductionOptions = @json($deductionOptions->map(fn($o) => ['id' => $o->id, 'name' => $o->name]));

function buildOptionHtml(options) {
    return options.map(o => `<option value="${o.id}">${o.name}</option>`).join('');
}

function addRow(type) {
    const opts = type === 'allowance' ? allowanceOptions : deductionOptions;
    const prefix = type === 'allowance' ? 'allowance' : 'deduction';
    const container = document.getElementById(`${type}s-container`);
    const noMsg = document.getElementById(`no-${type}-msg`);
    if (noMsg) noMsg.style.display = 'none';

    const row = document.createElement('div');
    row.className = 'row g-2 mb-2 component-row';
    row.innerHTML = `
        <div class="col-md-5">
            <select name="${prefix}_option[]" class="form-control form-control-sm select2">
                <option value="">-- ${type} --</option>
                ${buildOptionHtml(opts)}
            </select>
        </div>
        <div class="col-md-4">
            <div class="input-group input-group-sm">
                <input type="number" name="${prefix}_amount[]" class="form-control comp-amount" min="0" step="0.01" placeholder="0.00">
                <span class="input-group-text" style="padding:2px 6px">
                    <input type="checkbox" name="${prefix}_is_percentage[]" value="1" class="form-check-input mt-0 is-pct" title="%">
                    <small class="ms-1">%</small>
                </span>
            </div>
        </div>
        <div class="col-md-3">
            <button type="button" class="btn btn-sm btn-outline-danger w-100 remove-row">
                <i class="ti ti-x"></i>
            </button>
        </div>`;
    container.appendChild(row);
    recalc();
    if (typeof select2 === 'function') select2();
}

document.getElementById('add-allowance').addEventListener('click', () => addRow('allowance'));
document.getElementById('add-deduction').addEventListener('click', () => addRow('deduction'));

document.addEventListener('click', function(e) {
    if (e.target.closest('.remove-row')) {
        e.target.closest('.component-row').remove();
        recalc();
    }
});

document.addEventListener('input', function(e) {
    if (e.target.matches('#basic_salary, .comp-amount, .is-pct')) recalc();
});
document.addEventListener('change', function(e) {
    if (e.target.matches('.is-pct')) recalc();
});

function recalc() {
    const basic = parseFloat(document.getElementById('basic_salary').value) || 0;
    let allowSum = 0, deductSum = 0;
    document.querySelectorAll('#allowances-container .component-row').forEach(row => {
        const amt  = parseFloat(row.querySelector('.comp-amount').value) || 0;
        const isPct = row.querySelector('.is-pct').checked;
        allowSum += isPct ? (basic * amt / 100) : amt;
    });
    document.querySelectorAll('#deductions-container .component-row').forEach(row => {
        const amt  = parseFloat(row.querySelector('.comp-amount').value) || 0;
        const isPct = row.querySelector('.is-pct').checked;
        deductSum += isPct ? (basic * amt / 100) : amt;
    });
    document.getElementById('sum-basic').textContent      = basic.toFixed(2);
    document.getElementById('sum-allowances').textContent = allowSum.toFixed(2);
    document.getElementById('sum-deductions').textContent = deductSum.toFixed(2);
    document.getElementById('sum-net').textContent        = (basic + allowSum - deductSum).toFixed(2);
}
</script>
@endpush
