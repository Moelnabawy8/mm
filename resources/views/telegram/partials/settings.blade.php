{{-- Telegram task notifications (Bot API, long-polling — no webhook, no templates). --}}
<div id="telegram-task-settings" class="card">
    <div class="col-md-12">
        <div class="card-header p-3">
            <h5>{{ __('Telegram Task Notifications') }}</h5>
            <small class="text-muted">
                {{ __('Plain HTML messages via your bot. Employees link their own chat from the Telegram page — no phone numbers to type.') }}
            </small>
        </div>
        <div class="card-body">
            {{ Form::open(['route' => 'telegram.task.settings', 'method' => 'post']) }}
            <div class="row">
                <div class="col-md-6">
                    <div class="form-check form-switch mb-3">
                        {{ Form::checkbox('telegram_enabled', '1', isset($comSetting['telegram_enabled']) && $comSetting['telegram_enabled'] == '1' ? 'checked' : '', ['class' => 'form-check-input', 'id' => 'telegram_enabled']) }}
                        <label class="form-check-label" for="telegram_enabled">{{ __('Enable Telegram notifications') }}</label>
                    </div>
                    <div class="form-check form-switch mb-3">
                        {{ Form::checkbox('telegram_dry_run', '1', !isset($comSetting['telegram_dry_run']) || $comSetting['telegram_dry_run'] == '1' ? 'checked' : '', ['class' => 'form-check-input', 'id' => 'telegram_dry_run']) }}
                        <label class="form-check-label" for="telegram_dry_run">{{ __('Dry run (log everything, send nothing)') }}</label>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="mb-3">
                        {{ Form::label('telegram_bot_token', __('Bot Token'), ['class' => 'form-label']) }}
                        {{ Form::password('telegram_bot_token', ['class' => 'form-control w-100', 'dir' => 'ltr', 'placeholder' => (isset($comSetting['telegram_bot_token']) && $comSetting['telegram_bot_token'] !== '') ? __('Saved — leave blank to keep it') : __('123456:ABC-DEF... (from @BotFather)')]) }}
                        <small class="text-muted">{{ __('Create one with @BotFather, then paste it here.') }}</small>
                    </div>
                    <div class="mb-3">
                        {{ Form::label('telegram_bot_username', __('Bot Username'), ['class' => 'form-label']) }}
                        {{ Form::text('telegram_bot_username', $comSetting['telegram_bot_username'] ?? '', ['class' => 'form-control w-100', 'dir' => 'ltr', 'placeholder' => 'mycompany_tasks_bot']) }}
                        <small class="text-muted">{{ __('Without @. Used for the t.me deep-link on the employee linking page.') }}</small>
                    </div>
                </div>
            </div>
            <div class="row">
                <div class="col-md-4">
                    <div class="mb-3">
                        {{ Form::label('telegram_daily_cap', __('Daily cap'), ['class' => 'form-label']) }}
                        {{ Form::number('telegram_daily_cap', $comSetting['telegram_daily_cap'] ?? '300', ['class' => 'form-control w-100', 'min' => 1]) }}
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="mb-3">
                        {{ Form::label('telegram_timezone', __('Timezone for reminders'), ['class' => 'form-label']) }}
                        {{ Form::text('telegram_timezone', $comSetting['telegram_timezone'] ?? 'Asia/Amman', ['class' => 'form-control w-100']) }}
                    </div>
                </div>
                <div class="col-md-4">
                    @php $tgOffsets = explode(',', $comSetting['telegram_due_offsets'] ?? 'd1'); @endphp
                    <div class="mb-3">
                        {{ Form::label('telegram_due_offsets', __('Send the reminder'), ['class' => 'form-label']) }}
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="telegram_due_offsets[]" value="d1"
                                {{ in_array('d1', $tgOffsets) ? 'checked' : '' }} id="tg_d1">
                            <label class="form-check-label" for="tg_d1">{{ __('A day before (due tomorrow)') }}</label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="telegram_due_offsets[]" value="d0"
                                {{ in_array('d0', $tgOffsets) ? 'checked' : '' }} id="tg_d0">
                            <label class="form-check-label" for="tg_d0">{{ __('On the day (due today)') }}</label>
                        </div>
                    </div>
                </div>
            </div>
            <div class="row">
                <div class="col-12">
                    <label class="form-label">{{ __('Notify on') }}</label>
                    @php
                        $tgEvents = [
                            'telegram_notify_assign'  => [__('Task assigned'), __('The core feature: every assignee is messaged'), '1'],
                            'telegram_notify_stage'   => [__('Task stage changed'), __('Noisier — one message per kanban move'), '0'],
                            'telegram_notify_comment' => [__('New comment'), __('Noisier — one message per comment'), '0'],
                            'telegram_notify_due'     => [__('Due-date reminder'), __('Runs once daily. Never covers overdue tasks.'), '0'],
                        ];
                    @endphp
                    @foreach ($tgEvents as $key => [$label, $hint, $default])
                        <div class="form-check form-switch mb-1">
                            {{ Form::checkbox($key, '1', isset($comSetting[$key]) ? $comSetting[$key] == '1' : $default == '1', ['class' => 'form-check-input', 'id' => $key]) }}
                            <label class="form-check-label" for="{{ $key }}">{{ $label }}
                                <small class="text-muted">— {{ $hint }}</small></label>
                        </div>
                    @endforeach
                </div>
            </div>
            <div class="mt-3 d-flex gap-2 flex-wrap">
                <button class="btn btn-primary">{{ __('Save Telegram settings') }}</button>
                <button type="button" class="btn btn-light" id="tg-test-connection">
                    <i class="ti ti-brand-telegram me-1"></i>{{ __('Test connection') }}
                </button>
                <span id="tg-connection-result" class="align-self-center small"></span>
            </div>
            {{ Form::close() }}

            <hr>
            <div class="d-flex gap-2 flex-wrap">
                @can('manage telegram recipient')
                    <a href="{{ route('telegram.recipients') }}" class="btn btn-light">
                        <i class="ti ti-users me-1"></i>{{ __('Linked accounts') }}
                    </a>
                @endcan
                @can('view telegram log')
                    <a href="{{ route('telegram.log') }}" class="btn btn-light">
                        <i class="ti ti-history me-1"></i>{{ __('Delivery log') }}
                    </a>
                @endcan
                <a href="{{ route('telegram.link') }}" class="btn btn-light">
                    <i class="ti ti-link me-1"></i>{{ __('My linking page') }}
                </a>
            </div>
            <small class="text-muted d-block mt-2">
                {{ __('Linking and delivery run every minute via') }} <code>php artisan telegram:dispatch</code>.
            </small>
        </div>
    </div>
</div>

<script>
    (function () {
        const btn = document.getElementById('tg-test-connection');
        if (!btn) return;
        btn.addEventListener('click', function () {
            const out = document.getElementById('tg-connection-result');
            out.textContent = '{{ __('Checking...') }}';
            fetch('{{ route('telegram.connection') }}', {
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}', 'Accept': 'application/json' }
            })
            .then(r => r.json())
            .then(function (d) {
                out.textContent = d.headline
                    + (d.bot && d.bot.username ? ' (@' + d.bot.username + ')' : '')
                    + (d.error ? ' — ' + d.error : '');
                out.className = 'align-self-center small ' + (d.connected ? 'text-success' : 'text-danger');
            })
            .catch(() => { out.textContent = 'Network error'; out.className = 'align-self-center small text-danger'; });
        });
    })();
</script>
