{{--
    WhatsApp settings panel, included from resources/views/settings/company.blade.php.

    These are stacked cards under a Bootstrap ScrollSpy (#useradd-sidenav), NOT tab-panes,
    so the markup mirrors the Twilio card exactly.

    The panel body lives here rather than inline because company.blade.php is already 5456
    lines long.
--}}
<div id="whatsapp-settings" class="card">
    <div class="card-header p-3">
        <h5>{{ __('WhatsApp Task Notifications') }}</h5>
        <small class="text-muted">{{ __('Send project tasks to the assignee on WhatsApp via Meta Cloud API') }}</small>
    </div>

    {{ Form::open(['route' => 'whatsapp.settings', 'method' => 'post']) }}
    <div class="card-body p-3">

        {{-- Master switches ------------------------------------------------ --}}
        <div class="row row-gap-1 mb-3">
            <div class="col-md-6">
                <div class="list-group">
                    <div class="list-group-item shadow-sm">
                        <div class="form-switch form-switch-right">
                            <span><strong>{{ __('Enable WhatsApp notifications') }}</strong></span>
                            {{ Form::checkbox('whatsapp_enabled', '1', isset($comSetting['whatsapp_enabled']) && $comSetting['whatsapp_enabled'] == '1' ? 'checked' : '', ['class' => 'form-check-input', 'id' => 'whatsapp_enabled']) }}
                            <label class="form-check-label" for="whatsapp_enabled"></label>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="list-group">
                    <div class="list-group-item shadow-sm">
                        <div class="form-switch form-switch-right">
                            <span><strong>{{ __('Dry run (test mode)') }}</strong></span>
                            {{ Form::checkbox('whatsapp_dry_run', '1', !isset($comSetting['whatsapp_dry_run']) || $comSetting['whatsapp_dry_run'] == '1' ? 'checked' : '', ['class' => 'form-check-input', 'id' => 'whatsapp_dry_run']) }}
                            <label class="form-check-label" for="whatsapp_dry_run"></label>
                        </div>
                    </div>
                </div>
                <small class="text-muted d-block mt-1">
                    {{ __('Everything is logged but nothing is actually sent. Leave this on for the first day to verify the triggers against real traffic.') }}
                </small>
            </div>
        </div>

        {{-- Credentials ----------------------------------------------------- --}}
        <div class="col-md-12"><h5 class="small-title mb-3">{{ __('Meta Cloud API credentials') }}</h5></div>
        <div class="row row-gap-1">
            <div class="col-md-6">
                <div class="form-group mb-0">
                    {{ Form::label('whatsapp_token', __('Access Token'), ['class' => 'form-label']) }}
                    {{ Form::password('whatsapp_token', ['class' => 'form-control w-100', 'placeholder' => (isset($comSetting['whatsapp_token']) && $comSetting['whatsapp_token'] !== '') ? __('Saved — leave blank to keep it') : __('Paste the Meta access token')]) }}
                    <small class="text-muted">
                        {{ __('A System User token never expires. A personal user token stops working after its data-access window.') }}
                    </small>
                </div>
            </div>
            <div class="col-md-3">
                <div class="form-group mb-0">
                    {{ Form::label('whatsapp_phone_number_id', __('Phone Number ID'), ['class' => 'form-label']) }}
                    {{ Form::text('whatsapp_phone_number_id', $comSetting['whatsapp_phone_number_id'] ?? '', ['class' => 'form-control w-100', 'placeholder' => '1074702182387689']) }}
                </div>
            </div>
            <div class="col-md-3">
                <div class="form-group mb-0">
                    {{ Form::label('whatsapp_waba_id', __('WhatsApp Business Account ID'), ['class' => 'form-label']) }}
                    {{ Form::text('whatsapp_waba_id', $comSetting['whatsapp_waba_id'] ?? '', ['class' => 'form-control w-100', 'placeholder' => '3988204118102182']) }}
                </div>
            </div>
        </div>

        {{-- Webhook (delivery receipts) --------------------------------------- --}}
        <div class="col-md-12 mt-3"><h5 class="small-title mb-3">{{ __('Webhook (delivery & read receipts)') }}</h5></div>
        <div class="row row-gap-1">
            <div class="col-md-6">
                <div class="form-group mb-0">
                    <label class="form-label">{{ __('Callback URL') }}</label>
                    <input type="text" class="form-control w-100" dir="ltr" readonly
                        value="{{ url('/whatsapp/webhook') }}" onclick="this.select()">
                    <small class="text-muted d-block mt-1">
                        {{ __('Paste this into Meta App Dashboard → WhatsApp → Configuration → Callback URL.') }}
                        <strong>{{ __('This WABA currently calls maya-os.com instead — nothing arrives here until that is repointed there.') }}</strong>
                    </small>
                </div>
            </div>
            <div class="col-md-6">
                <div class="form-group mb-0">
                    {{ Form::label('whatsapp_webhook_verify_token', __('Verify token'), ['class' => 'form-label']) }}
                    {{ Form::text('whatsapp_webhook_verify_token', $comSetting['whatsapp_webhook_verify_token'] ?? '', ['class' => 'form-control w-100', 'dir' => 'ltr', 'placeholder' => __('any string you choose')]) }}
                    <small class="text-muted">
                        {{ __('Must match exactly what you enter as the Verify Token in Meta App Dashboard.') }}
                    </small>
                </div>
            </div>
        </div>

        {{-- Template -------------------------------------------------------- --}}
        <div class="col-md-12 mt-3"><h5 class="small-title mb-3">{{ __('Message template') }}</h5></div>
        <div class="row row-gap-1">
            <div class="col-md-3">
                <div class="form-group mb-0">
                    {{ Form::label('whatsapp_template_name', __('Template name'), ['class' => 'form-label']) }}
                    {{ Form::text('whatsapp_template_name', $comSetting['whatsapp_template_name'] ?? 'task_alert_ar', ['class' => 'form-control w-100']) }}
                </div>
            </div>
            <div class="col-md-2">
                <div class="form-group mb-0">
                    {{ Form::label('whatsapp_language', __('Language code'), ['class' => 'form-label']) }}
                    {{ Form::text('whatsapp_language', $comSetting['whatsapp_language'] ?? 'ar', ['class' => 'form-control w-100', 'placeholder' => 'ar']) }}
                </div>
            </div>
            <div class="col-md-2">
                <div class="form-group mb-0">
                    {{ Form::label('whatsapp_default_country', __('Default country code'), ['class' => 'form-label']) }}
                    {{ Form::text('whatsapp_default_country', $comSetting['whatsapp_default_country'] ?? '962', ['class' => 'form-control w-100', 'placeholder' => '962']) }}
                    <small class="text-muted">{{ __('Used for numbers stored without one') }}</small>
                </div>
            </div>
            <div class="col-md-2">
                <div class="form-group mb-0">
                    {{ Form::label('whatsapp_daily_cap', __('Daily cap'), ['class' => 'form-label']) }}
                    {{ Form::number('whatsapp_daily_cap', $comSetting['whatsapp_daily_cap'] ?? '300', ['class' => 'form-control w-100', 'min' => 1]) }}
                </div>
            </div>
            <div class="col-md-3">
                <div class="form-group mb-0">
                    {{ Form::label('whatsapp_timezone', __('Timezone for reminders'), ['class' => 'form-label']) }}
                    {{ Form::text('whatsapp_timezone', $comSetting['whatsapp_timezone'] ?? 'Asia/Amman', ['class' => 'form-control w-100']) }}
                    <small class="text-muted">{{ __('The server clock runs on UTC') }}</small>
                </div>
            </div>
        </div>

        {{-- Events ---------------------------------------------------------- --}}
        <div class="col-md-12 mt-3"><h5 class="small-title mb-3">{{ __('Notify on') }}</h5></div>
        <div class="row row-gap-1">
            @php
                $waEvents = [
                    'whatsapp_notify_assign'  => [__('Task assigned'), __('The core feature: every assignee is messaged'), '1'],
                    'whatsapp_notify_stage'   => [__('Task stage changed'), __('Noisier — one message per kanban move'), '0'],
                    'whatsapp_notify_comment' => [__('New comment'), __('Noisier — one message per comment'), '0'],
                    'whatsapp_notify_due'     => [__('Due-date reminder'), __('Runs once daily. Never covers overdue tasks.'), '0'],
                ];
            @endphp
            @foreach ($waEvents as $key => $meta)
                <div class="col-md-3">
                    <div class="list-group">
                        <div class="list-group-item shadow-sm">
                            <div class="form-switch form-switch-right">
                                <span>{{ $meta[0] }}</span>
                                {{ Form::checkbox($key, '1', (isset($comSetting[$key]) ? $comSetting[$key] : $meta[2]) == '1' ? 'checked' : '', ['class' => 'form-check-input', 'id' => $key]) }}
                                <label class="form-check-label" for="{{ $key }}"></label>
                            </div>
                        </div>
                    </div>
                    <small class="text-muted d-block mt-1">{{ $meta[1] }}</small>
                </div>
            @endforeach
        </div>

        @php $waOffsets = explode(',', $comSetting['whatsapp_due_offsets'] ?? 'd1'); @endphp
        <div class="row row-gap-1 mt-2">
            <div class="col-md-6">
                {{ Form::label('whatsapp_due_offsets', __('Send the reminder'), ['class' => 'form-label']) }}
                <div class="d-flex gap-3">
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" name="whatsapp_due_offsets[]" value="d1"
                            id="wa_due_d1" {{ in_array('d1', $waOffsets) ? 'checked' : '' }}>
                        <label class="form-check-label" for="wa_due_d1">{{ __('One day before') }}</label>
                    </div>
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" name="whatsapp_due_offsets[]" value="d0"
                            id="wa_due_d0" {{ in_array('d0', $waOffsets) ? 'checked' : '' }}>
                        <label class="form-check-label" for="wa_due_d0">{{ __('On the due date') }}</label>
                    </div>
                </div>
            </div>
        </div>

        {{-- Connection check ------------------------------------------------ --}}
        <div class="col-md-12 mt-3"><h5 class="small-title mb-3">{{ __('Connection with Meta') }}</h5></div>

        <div class="border rounded p-3">
            <div class="d-flex align-items-center flex-wrap gap-2">
                <button type="button" id="wa-link-btn" class="btn btn-success">
                    <i class="ti ti-brand-whatsapp me-1"></i>{{ __('Link the number with Meta') }}
                </button>
                <button type="button" id="wa-connect-btn" class="btn btn-primary">
                    <i class="ti ti-plug-connected me-1"></i>{{ __('Test connection') }}
                </button>
                <span id="wa-connect-state" class="badge bg-secondary">{{ __('Not checked yet') }}</span>
            </div>
            <small class="text-muted d-block mt-2">
                {{ __('Linking shows every WhatsApp Business account and number this token can send from, so you can pick the sender. Testing verifies the token, the number, the quality rating and the template approval.') }}
            </small>
            <div id="wa-connect-result" class="mt-3 d-none">
                <div id="wa-connect-headline" class="mb-2 fw-bold"></div>
                <div class="table-responsive">
                    <table class="table table-sm mb-0">
                        <tbody id="wa-connect-rows"></tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="alert alert-light border mt-3 mb-0">
            <i class="ti ti-info-circle me-1"></i>
            {{ __('The same checks are available on the server with') }} <code>php artisan whatsapp:doctor</code>.
        </div>
    </div>

    {{-- Link modal: pick the sending number ------------------------------- --}}
    <div class="modal fade" id="wa-link-modal" tabindex="-1">
        <div class="modal-dialog modal-lg modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="ti ti-brand-whatsapp text-success me-1"></i>{{ __('Link the number with Meta') }}
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p class="text-muted small">
                        {{ __('These are all the WhatsApp Business accounts your Meta token can send from. Pick the number that should deliver task notifications.') }}
                    </p>
                    <div id="wa-link-body">
                        <div class="text-center text-muted py-4">{{ __('Loading...') }}</div>
                    </div>
                </div>
                <div class="modal-footer">
                    <div id="wa-link-msg" class="me-auto small"></div>
                    <button class="btn btn-light" data-bs-dismiss="modal">{{ __('Close') }}</button>
                </div>
            </div>
        </div>
    </div>

    <div class="card-footer text-end">
        @can('manage whatsapp recipient')
            <a href="{{ route('whatsapp.recipients') }}" class="btn btn-light me-1">
                <i class="ti ti-address-book me-1"></i>{{ __('WhatsApp numbers') }}
            </a>
        @endcan
        @can('view whatsapp log')
            <a href="{{ route('whatsapp.log') }}" class="btn btn-light me-1">
                <i class="ti ti-history me-1"></i>{{ __('Delivery log') }}
            </a>
        @endcan
        {{ Form::submit(__('Save Changes'), ['class' => 'btn btn-primary']) }}
    </div>
    {{ Form::close() }}
</div>

@push('script-page')
<script>
(function () {
    var btn = document.getElementById('wa-connect-btn');
    if (!btn) return;

    var state    = document.getElementById('wa-connect-state');
    var wrap     = document.getElementById('wa-connect-result');
    var headline = document.getElementById('wa-connect-headline');
    var rows     = document.getElementById('wa-connect-rows');

    var ICON = {
        pass: '<i class="ti ti-circle-check text-success"></i>',
        warn: '<i class="ti ti-alert-triangle text-warning"></i>',
        fail: '<i class="ti ti-circle-x text-danger"></i>',
        info: '<i class="ti ti-info-circle text-primary"></i>'
    };

    btn.addEventListener('click', function () {
        btn.disabled = true;
        state.className = 'badge bg-info';
        state.textContent = '{{ __('Checking...') }}';
        rows.innerHTML = '';
        wrap.classList.remove('d-none');
        headline.textContent = '';

        // Each Graph call has its own timeout server-side; six of them can take a few
        // seconds, so the button stays disabled until the whole run comes back.
        fetch('{{ route('whatsapp.connection') }}', {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': '{{ csrf_token() }}',
                'Accept': 'application/json'
            }
        })
        .then(function (r) { return r.json().then(function (d) { return { ok: r.ok, d: d }; }); })
        .then(function (res) {
            btn.disabled = false;

            if (!res.ok) {
                state.className = 'badge bg-danger';
                state.textContent = '{{ __('Error') }}';
                headline.textContent = res.d.error || 'Error';
                return;
            }

            var d = res.d;
            state.className = 'badge bg-' + (d.connected ? 'success' : 'danger');
            state.textContent = d.connected ? '{{ __('Connected') }}' : '{{ __('Not connected') }}';

            headline.className = 'mb-2 fw-bold text-' + (d.connected ? 'success' : 'danger');
            headline.textContent = d.headline;

            rows.innerHTML = d.checks.map(function (c) {
                return '<tr>' +
                    '<td style="width:34px">' + (ICON[c.level] || '') + '</td>' +
                    '<td style="width:32%"><strong>' + c.name + '</strong></td>' +
                    '<td class="text-muted" dir="auto">' + c.detail + '</td>' +
                    '</tr>';
            }).join('');
        })
        .catch(function () {
            btn.disabled = false;
            state.className = 'badge bg-danger';
            state.textContent = '{{ __('Connection error') }}';
        });
    });

    /* ---------------- link flow: discover accounts, pick a number ---------------- */

    var linkBtn = document.getElementById('wa-link-btn');
    if (!linkBtn) return;

    var linkBody = document.getElementById('wa-link-body');
    var linkMsg  = document.getElementById('wa-link-msg');
    var CSRF     = '{{ csrf_token() }}';

    function post(url, body) {
        return fetch(url, {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': CSRF,
                'Accept': 'application/json',
                'Content-Type': 'application/json'
            },
            body: body ? JSON.stringify(body) : null
        }).then(function (r) { return r.json().then(function (d) { return { ok: r.ok, d: d }; }); });
    }

    function esc(s) {
        return String(s == null ? '' : s).replace(/[&<>"]/g, function (c) {
            return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c];
        });
    }

    linkBtn.addEventListener('click', function () {
        linkMsg.textContent = '';
        linkBody.innerHTML = '<div class="text-center text-muted py-4">{{ __('Loading...') }}</div>';
        new bootstrap.Modal(document.getElementById('wa-link-modal')).show();

        post('{{ route('whatsapp.link.discover') }}').then(function (res) {
            if (!res.ok) {
                linkBody.innerHTML = '<div class="alert alert-danger mb-0">' + esc(res.d.error) + '</div>';
                return;
            }

            var html = '';
            res.d.accounts.forEach(function (a) {
                html += '<div class="mb-3"><div class="fw-bold mb-1">' + esc(a.name) +
                        ' <span class="text-muted small" dir="ltr">' + esc(a.waba_id) + '</span></div>' +
                        '<table class="table table-sm mb-0"><tbody>';

                a.numbers.forEach(function (n) {
                    html += '<tr>' +
                        '<td dir="ltr" style="width:38%"><strong>' + esc(n.display) + '</strong>' +
                            '<div class="text-muted small">' + esc(n.verified_name) + '</div></td>' +
                        '<td>' + (n.usable
                            ? '<span class="badge bg-success">' + esc(n.status) + '</span>' +
                              (n.quality ? ' <span class="badge bg-light text-dark">' + esc(n.quality) + '</span>' : '')
                            : '<span class="badge bg-secondary">' + esc(n.status) + '</span>' +
                              '<div class="text-muted small">' + esc(n.reason) + '</div>') +
                        '</td>' +
                        '<td class="text-end" style="width:130px">' +
                            (n.current
                                ? '<span class="badge bg-primary">{{ __('Current') }}</span>'
                                : (n.usable
                                    ? '<button type="button" class="btn btn-sm btn-success wa-pick"' +
                                      ' data-waba="' + esc(a.waba_id) + '"' +
                                      ' data-phone="' + esc(n.phone_number_id) + '">{{ __('Use this') }}</button>'
                                    : '')) +
                        '</td></tr>';
                });

                html += '</tbody></table></div>';
            });

            linkBody.innerHTML = html || '<div class="alert alert-warning mb-0">{{ __('No accounts found.') }}</div>';

            linkBody.querySelectorAll('.wa-pick').forEach(function (b) {
                b.addEventListener('click', function () {
                    b.disabled = true;
                    b.textContent = '...';
                    linkMsg.textContent = '';

                    post('{{ route('whatsapp.link.select') }}', {
                        waba_id: b.dataset.waba,
                        phone_number_id: b.dataset.phone
                    }).then(function (r2) {
                        b.disabled = false;
                        b.textContent = '{{ __('Use this') }}';

                        if (!r2.ok) {
                            linkMsg.innerHTML = '<span class="text-danger">' + esc(r2.d.error) + '</span>';
                            return;
                        }

                        if (r2.d.needs_template) {
                            // A template lives on one WABA. Moving the sender to another
                            // account leaves it behind, so offer to create it there.
                            linkMsg.innerHTML =
                                '<span class="text-warning">' +
                                '{{ __('Linked, but the template') }} <code>' + esc(r2.d.template_name) + '</code> ' +
                                '{{ __('is not approved on this account') }} (' + esc(r2.d.template_state) + '). ' +
                                '</span> <button type="button" id="wa-mk-tpl" class="btn btn-sm btn-warning ms-1">' +
                                '{{ __('Create it now') }}</button>';

                            document.getElementById('wa-mk-tpl').addEventListener('click', function (e) {
                                e.target.disabled = true;
                                post('{{ route('whatsapp.link.template') }}').then(function (r3) {
                                    linkMsg.innerHTML = r3.ok
                                        ? '<span class="text-success">' + esc(r3.d.message) + '</span>'
                                        : '<span class="text-danger">' + esc(r3.d.error) + '</span>';
                                });
                            });
                        } else {
                            linkMsg.innerHTML = '<span class="text-success">' + esc(r2.d.message) +
                                ' {{ __('Template approved.') }}</span>';
                        }

                        // Reflect the new binding in the status badge behind the modal.
                        state.className = 'badge bg-' + (r2.d.summary.connected ? 'success' : 'warning');
                        state.textContent = r2.d.summary.connected
                            ? '{{ __('Connected') }}' : '{{ __('Needs attention') }}';
                    });
                });
            });
        }).catch(function () {
            linkBody.innerHTML = '<div class="alert alert-danger mb-0">{{ __('Failed to load.') }}</div>';
        });
    });
})();
</script>
@endpush
