{{--
    Read-only WhatsApp panel inside the task detail modal.

    Rendered directly from the parent view rather than through ProjectTask::activity_log():
    that method calls \Auth::user()->type without a null guard and would fatal outside a web
    session.
--}}
@if ($messages->count())
    <div class="col-md-12">
        <div class="card">
            <div class="card-header">
                <h6 class="mb-0">
                    <i class="ti ti-brand-whatsapp text-success me-1"></i>
                    {{ __('WhatsApp notifications') }}
                    <span class="badge bg-light text-dark">{{ $messages->count() }}</span>
                </h6>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-sm mb-0">
                        <thead>
                            <tr>
                                <th>{{ __('Recipient') }}</th>
                                <th>{{ __('Event') }}</th>
                                <th>{{ __('Status') }}</th>
                                <th>{{ __('Time') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($messages as $m)
                                <tr>
                                    <td>
                                        {{ $m->user->name ?? '—' }}
                                        <div class="text-muted small" dir="ltr">{{ $m->phone }}</div>
                                    </td>
                                    <td><span class="badge bg-light text-dark">{{ $m->eventLabel() }}</span></td>
                                    <td>
                                        <span class="badge bg-{{ $m->statusColor() }}">{{ __(ucfirst($m->status)) }}</span>
                                        @if ($m->error_code)
                                            <div class="text-danger small">{{ __('Error') }} {{ $m->error_code }}</div>
                                        @endif
                                    </td>
                                    <td class="text-muted small">
                                        {{ ($m->sent_at ?? $m->created_at)?->format('Y-m-d H:i') }}
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
@endif
