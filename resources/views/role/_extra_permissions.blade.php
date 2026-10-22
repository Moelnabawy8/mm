{{--
    Every permission the six hardcoded tabs cannot draw.

    The tabs render a checkbox only where a permission is named "<verb> <module>" and
    both the verb and the module appear in lists written into the blade by hand. A
    permission whose name fits neither list gets no checkbox — and RoleController@update
    revokes every permission before re-granting the ones the form posted, so saving any
    role silently strips it. That is why "review task quality", "record meeting minutes"
    and "use ai assistant" could be granted by migration and then vanish the first time
    somebody pressed Update on the roles screen.

    This pane lists whatever the tabs missed, so it can be granted and, more importantly,
    so it survives a save. It needs no maintenance: a permission added later shows up
    here on its own until someone gives it a tab.

    Expects: $permissions  [id => name]
             $checkedState  third argument for Form::checkbox (model binding on edit)
--}}
@php
    $coveredModules = [
        // staff
        'user', 'role', 'client', 'product & service', 'constant unit', 'constant tax',
        'constant category', 'zoom meeting', 'company settings', 'language', 'permission',
        // crm
        'crm dashboard', 'lead', 'convert', 'pipeline', 'lead stage', 'source', 'label',
        'lead email', 'lead call', 'deal', 'stage', 'task', 'form builder', 'form response',
        'form field', 'contract', 'contract type', 'support request', 'quality evaluation',
        'reports dashboard', 'sod', 'eod',
        // project
        'project dashboard', 'project', 'milestone', 'grant chart', 'project stage',
        'timesheet', 'project expense', 'project task', 'activity', 'CRM activity',
        'project task stage', 'bug report', 'bug status',
        // hrm
        'hrm dashboard', 'employee', 'employee profile', 'department', 'designation',
        'branch', 'document type', 'document', 'payslip type', 'allowance', 'commission',
        'allowance option', 'loan option', 'deduction option', 'loan',
        'saturation deduction', 'other payment', 'overtime', 'set salary', 'pay slip',
        'company policy', 'appraisal', 'goal tracking', 'goal type', 'indicator', 'event',
        'meeting', 'training', 'trainer', 'training type', 'award', 'award type',
        'resignation', 'travel', 'promotion', 'complaint', 'warning', 'termination',
        'termination type', 'job application', 'job application note', 'job onBoard',
        'job category', 'job', 'job stage', 'custom question', 'interview schedule',
        'career', 'estimation', 'holiday', 'transfer', 'announcement', 'leave',
        'leave type', 'attendance',
        // account
        'account dashboard', 'proposal', 'invoice', 'bill', 'revenue', 'payment',
        'proposal product', 'invoice product', 'bill product', 'goal', 'credit note',
        'debit note', 'bank account', 'bank transfer', 'transaction', 'customer', 'vender',
        'constant custom field', 'assets', 'chart of account', 'journal entry', 'report',
        // pos
        'pos dashboard', 'warehouse', 'quotation', 'purchase', 'pos', 'barcode',
    ];

    // The verbs the tabs actually put a checkbox behind, taken together.
    $coveredVerbs = [
        'view', 'add', 'move', 'manage', 'create', 'edit', 'delete', 'show', 'send',
        'copy', 'duplicate', 'reply', 'convert', 'create payment', 'delete payment',
        'invoice', 'bill', 'expense', 'income', 'tax', 'ledger', 'balance sheet',
        'trial balance', 'loss & profit', 'income vs expense',
    ];

    $covered = [];

    foreach ($coveredModules as $m) {
        foreach ($coveredVerbs as $v) {
            $covered[$v . ' ' . $m] = true;
        }
    }

    // The CRM "Convert" row is the one place the blade writes the module FIRST,
    // which is how "convert lead to deal" gets drawn.
    foreach ($coveredModules as $m) {
        $covered[$m . ' lead to deal'] = true;
    }

    $extra = [];

    foreach ((array) $permissions as $id => $name) {
        if (!isset($covered[$name])) {
            $extra[$id] = $name;
        }
    }

    asort($extra);
@endphp

<div class="col-md-12">
    <div class="form-group">
        @if (count($extra))
            <h6 class="my-3">{{ __('Assign Other Permission to Roles') }}</h6>
            <table class="table table-striped mb-0">
                <thead>
                <tr>
                    <th>
                        <input type="checkbox" class="form-check-input align-middle custom_align_middle" name="other_checkall" id="other_checkall">
                    </th>
                    <th>{{ __('Permission') }}</th>
                </tr>
                </thead>
                <tbody>
                @foreach ($extra as $id => $name)
                    <tr>
                        <td></td>
                        <td class="custom-control custom-checkbox">
                            {{ Form::checkbox('permissions[]', $id, $checkedState, ['class' => 'form-check-input other_checkall', 'id' => 'permission' . $id]) }}
                            {{ Form::label('permission' . $id, ucfirst($name), ['class' => 'custom-control-label']) }}
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        @else
            <p class="my-3 text-muted">{{ __('Every permission is shown under one of the other tabs.') }}</p>
        @endif
    </div>
</div>
