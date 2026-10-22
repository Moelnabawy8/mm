{{-- Shared by create and edit. On edit the owner and cadence are fixed: moving a
     commitment from a person to a department, or from weekly to one-off, would rewrite
     what was agreed rather than edit it. --}}
@php
    $editing = isset($commitment);
    $c       = $commitment ?? null;
@endphp

<div class="mb-3">
    <label class="form-label">{{ __('What is being committed to') }} <span class="text-danger">*</span></label>
    <input type="text" name="title" class="form-control" maxlength="191" required
           value="{{ old('title', $c->title ?? '') }}"
           placeholder="{{ __('One clear sentence — what will be delivered') }}">
</div>

<div class="mb-3">
    <label class="form-label">{{ __('Detail') }}</label>
    <textarea name="description" class="form-control" rows="3"
              placeholder="{{ __('What counts as done, and anything the supervisor should know.') }}">{{ old('description', $c->description ?? '') }}</textarea>
</div>

@unless ($editing)
    <div class="mb-3">
        <label class="form-label">{{ __('Who owes it') }} <span class="text-danger">*</span></label>
        <div class="d-flex gap-3 mb-2">
            <div class="form-check">
                <input class="form-check-input" type="radio" name="owner_type" value="user"
                       id="owner-user" checked onchange="qcOwnerToggle()">
                <label class="form-check-label" for="owner-user">{{ __('An employee') }}</label>
            </div>
            <div class="form-check">
                <input class="form-check-input" type="radio" name="owner_type" value="department"
                       id="owner-dept" onchange="qcOwnerToggle()">
                <label class="form-check-label" for="owner-dept">{{ __('A whole department') }}</label>
            </div>
        </div>

        <select name="user_id" id="owner-user-select" class="form-control">
            <option value="">{{ __('Choose an employee') }}</option>
            @foreach ($people as $p)
                <option value="{{ $p->id }}" {{ old('user_id') == $p->id ? 'selected' : '' }}>{{ $p->name }}</option>
            @endforeach
        </select>

        <select name="department_id" id="owner-dept-select" class="form-control" style="display:none">
            <option value="">{{ __('Choose a department') }}</option>
            @foreach ($departments as $d)
                <option value="{{ $d->id }}" {{ old('department_id') == $d->id ? 'selected' : '' }}>{{ $d->name }}</option>
            @endforeach
        </select>
    </div>
@else
    <div class="mb-3">
        <label class="form-label">{{ __('Who owes it') }}</label>
        <input type="text" class="form-control" value="{{ $c->owner_label }}" disabled>
    </div>
@endunless

<div class="mb-3">
    <label class="form-label">{{ __('Owed to') }} <span class="text-danger">*</span></label>
    <select name="owed_to_id" class="form-control" required>
        <option value="">{{ __('Choose the supervisor this is owed to') }}</option>
        @foreach ($people as $p)
            <option value="{{ $p->id }}"
                {{ old('owed_to_id', $c->owed_to_id ?? '') == $p->id ? 'selected' : '' }}>{{ $p->name }}</option>
        @endforeach
    </select>
    <small class="text-muted">{{ __('They will see it under "Owed to me".') }}</small>
</div>

@unless ($editing)
    <div class="mb-3">
        <label class="form-label">{{ __('Cadence') }}</label>
        <div class="d-flex gap-3">
            <div class="form-check">
                <input class="form-check-input" type="radio" name="cadence" value="once"
                       id="cad-once" checked onchange="qcCadenceToggle()">
                <label class="form-check-label" for="cad-once">{{ __('One-off, with a due date') }}</label>
            </div>
            <div class="form-check">
                <input class="form-check-input" type="radio" name="cadence" value="weekly"
                       id="cad-weekly" onchange="qcCadenceToggle()">
                <label class="form-check-label" for="cad-weekly">{{ __('For a week') }}</label>
            </div>
        </div>
    </div>
@endunless

<div class="mb-3" id="due-wrap" style="{{ $editing && $c->cadence === 'weekly' ? 'display:none' : '' }}">
    <label class="form-label">{{ __('Due date') }}</label>
    <input type="date" name="due_date" class="form-control" dir="ltr"
           value="{{ old('due_date', isset($c) && $c->due_date ? $c->due_date->toDateString() : '') }}">
</div>

<div class="mb-3" id="week-wrap" style="{{ $editing && $c->cadence === 'weekly' ? '' : 'display:none' }}">
    <label class="form-label">{{ __('Week starting (Monday)') }}</label>
    <input type="date" name="week_start" class="form-control" dir="ltr"
           value="{{ old('week_start', isset($c) && $c->week_start ? $c->week_start->toDateString() : ($week ?? '')) }}"
           {{ $editing ? 'disabled' : '' }}>

    <div class="form-check mt-2">
        <input class="form-check-input" type="checkbox" name="recurs" value="1" id="recurs"
               {{ old('recurs', $c->recurs ?? false) ? 'checked' : '' }}>
        <label class="form-check-label" for="recurs">
            {{ __('Repeat this every week') }}
            <small class="text-muted d-block">
                {{ __('A copy is created each Monday until you turn this off.') }}
            </small>
        </label>
    </div>
</div>

@isset($meeting)
    @if ($meeting)
        <input type="hidden" name="meeting_id" value="{{ $meeting->id }}">
        <div class="alert alert-light small">
            <i class="ti ti-users me-1"></i>
            {{ __('This commitment comes from the meeting') }}: <strong>{{ $meeting->title }}</strong>
        </div>
    @endif
@endisset

@push('script-page')
<script>
    function qcOwnerToggle() {
        var isDept = document.getElementById('owner-dept') && document.getElementById('owner-dept').checked;
        var u = document.getElementById('owner-user-select');
        var d = document.getElementById('owner-dept-select');
        if (!u || !d) return;
        u.style.display = isDept ? 'none' : '';
        d.style.display = isDept ? '' : 'none';
    }

    function qcCadenceToggle() {
        var weekly = document.getElementById('cad-weekly') && document.getElementById('cad-weekly').checked;
        var dw = document.getElementById('due-wrap');
        var ww = document.getElementById('week-wrap');
        if (dw) dw.style.display = weekly ? 'none' : '';
        if (ww) ww.style.display = weekly ? '' : 'none';
    }

    qcOwnerToggle();
    qcCadenceToggle();
</script>
@endpush
