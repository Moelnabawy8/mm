@extends('layouts.admin')

@section('page-title'){{ __('Edit Salary Structure') }}@endsection

@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">{{ __('Dashboard') }}</a></li>
    <li class="breadcrumb-item"><a href="{{ route('salary-structure.index') }}">{{ __('Salary Structures') }}</a></li>
    <li class="breadcrumb-item active">{{ __('Edit') }}</li>
@endsection

@section('content')
{{ Form::model($salaryStructure, ['route' => ['salary-structure.update', $salaryStructure->id], 'method' => 'PUT', 'class' => 'needs-validation', 'novalidate' => true]) }}
<div class="row">
    <div class="col-md-8">
        <div class="card mb-4">
            <div class="card-header"><h5 class="mb-0">{{ __('Basic Information') }}</h5></div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-8">
                        <div class="form-group">
                            {{ Form::label('name', __('Structure Name'), ['class' => 'form-label']) }}<x-required></x-required>
                            {{ Form::text('name', null, ['class' => 'form-control', 'required' => 'required']) }}
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="form-group">
                            {{ Form::label('status', __('Status'), ['class' => 'form-label']) }}
                            {{ Form::select('status', ['active' => __('Active'), 'inactive' => __('Inactive')], null, ['class' => 'form-control']) }}
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

        <div class="card mb-4">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0">{{ __('Allowances') }}</h5>
                <button type="button" class="btn btn-sm btn-outline-success" id="add-allowance">
                    <i class="ti ti-plus me-1"></i>{{ __('Add') }}
                </button>
            </div>
            <div class="card-body" id="allowances-container">
                <p class="text-muted text-center py-2 d-none" id="no-allowance-msg">{{ __('No allowances.') }}</p>
                @foreach((array)$salaryStructure->allowances as $a)
                <div class="row g-2 mb-2 component-row">
                    <div class="col-md-5">
                        <select name="allowance_option[]" class="form-control form-control-sm">
                            @foreach($allowanceOptions as $opt)
                            <option value="{{ $opt->id }}" @selected($opt->id == $a['option_id'])>{{ $opt->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-4">
                        <div class="input-group input-group-sm">
                            <input type="number" name="allowance_amount[]" class="form-control comp-amount" value="{{ $a['amount'] }}" min="0" step="0.01">
                            <span class="input-group-text" style="padding:2px 6px">
                                <input type="checkbox" name="allowance_is_percentage[]" value="1" class="form-check-input mt-0 is-pct" @checked(!empty($a['is_percentage']))>
                                <small class="ms-1">%</small>
                            </span>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <button type="button" class="btn btn-sm btn-outline-danger w-100 remove-row"><i class="ti ti-x"></i></button>
                    </div>
                </div>
                @endforeach
            </div>
        </div>

        <div class="card mb-4">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0">{{ __('Deductions') }}</h5>
                <button type="button" class="btn btn-sm btn-outline-danger" id="add-deduction">
                    <i class="ti ti-plus me-1"></i>{{ __('Add') }}
                </button>
            </div>
            <div class="card-body" id="deductions-container">
                <p class="text-muted text-center py-2 d-none" id="no-deduction-msg">{{ __('No deductions.') }}</p>
                @foreach((array)$salaryStructure->deductions as $d)
                <div class="row g-2 mb-2 component-row">
                    <div class="col-md-5">
                        <select name="deduction_option[]" class="form-control form-control-sm">
                            @foreach($deductionOptions as $opt)
                            <option value="{{ $opt->id }}" @selected($opt->id == $d['option_id'])>{{ $opt->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-4">
                        <div class="input-group input-group-sm">
                            <input type="number" name="deduction_amount[]" class="form-control comp-amount" value="{{ $d['amount'] }}" min="0" step="0.01">
                            <span class="input-group-text" style="padding:2px 6px">
                                <input type="checkbox" name="deduction_is_percentage[]" value="1" class="form-check-input mt-0 is-pct" @checked(!empty($d['is_percentage']))>
                                <small class="ms-1">%</small>
                            </span>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <button type="button" class="btn btn-sm btn-outline-danger w-100 remove-row"><i class="ti ti-x"></i></button>
                    </div>
                </div>
                @endforeach
            </div>
        </div>
    </div>

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
                    <i class="ti ti-device-floppy me-1"></i>{{ __('Update Structure') }}
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
    const row = document.createElement('div');
    row.className = 'row g-2 mb-2 component-row';
    row.innerHTML = `
        <div class="col-md-5">
            <select name="${prefix}_option[]" class="form-control form-control-sm">
                <option value="">-- ${type} --</option>${buildOptionHtml(opts)}
            </select>
        </div>
        <div class="col-md-4">
            <div class="input-group input-group-sm">
                <input type="number" name="${prefix}_amount[]" class="form-control comp-amount" min="0" step="0.01" placeholder="0.00">
                <span class="input-group-text" style="padding:2px 6px">
                    <input type="checkbox" name="${prefix}_is_percentage[]" value="1" class="form-check-input mt-0 is-pct"><small class="ms-1">%</small>
                </span>
            </div>
        </div>
        <div class="col-md-3">
            <button type="button" class="btn btn-sm btn-outline-danger w-100 remove-row"><i class="ti ti-x"></i></button>
        </div>`;
    container.appendChild(row);
    recalc();
}

document.getElementById('add-allowance').addEventListener('click', () => addRow('allowance'));
document.getElementById('add-deduction').addEventListener('click', () => addRow('deduction'));
document.addEventListener('click', e => { if (e.target.closest('.remove-row')) { e.target.closest('.component-row').remove(); recalc(); }});
document.addEventListener('input', e => { if (e.target.matches('#basic_salary, .comp-amount')) recalc(); });
document.addEventListener('change', e => { if (e.target.matches('.is-pct')) recalc(); });

function recalc() {
    const basic = parseFloat(document.getElementById('basic_salary').value) || 0;
    let a = 0, d = 0;
    document.querySelectorAll('#allowances-container .component-row').forEach(row => {
        const amt = parseFloat(row.querySelector('.comp-amount').value) || 0;
        a += row.querySelector('.is-pct').checked ? (basic * amt / 100) : amt;
    });
    document.querySelectorAll('#deductions-container .component-row').forEach(row => {
        const amt = parseFloat(row.querySelector('.comp-amount').value) || 0;
        d += row.querySelector('.is-pct').checked ? (basic * amt / 100) : amt;
    });
    document.getElementById('sum-basic').textContent = basic.toFixed(2);
    document.getElementById('sum-allowances').textContent = a.toFixed(2);
    document.getElementById('sum-deductions').textContent = d.toFixed(2);
    document.getElementById('sum-net').textContent = (basic + a - d).toFixed(2);
}
recalc();
</script>
@endpush
