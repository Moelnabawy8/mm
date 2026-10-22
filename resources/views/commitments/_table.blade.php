{{-- Shared by the week board, "what I owe" and "what is owed to me". One table so the
     three screens can never disagree about what a commitment looks like. --}}
<div class="table-responsive">
    <table class="table table-hover mb-0">
        <thead>
            <tr>
                <th>{{ __('Commitment') }}</th>
                @if (($show ?? 'owner') === 'owner')
                    <th>{{ __('Owed by') }}</th>
                @endif
                <th>{{ __('Owed to') }}</th>
                <th>{{ __('Due') }}</th>
                <th style="min-width:110px">{{ __('Progress') }}</th>
                <th class="text-center">{{ __('Status') }}</th>
                <th></th>
            </tr>
        </thead>
        <tbody>
            @forelse ($items as $c)
                <tr>
                    <td>
                        <div>{{ $c->title }}</div>
                        @if ($c->cadence === \App\Models\Commitment::CADENCE_WEEKLY)
                            <span class="badge bg-light text-muted">{{ __('weekly') }}</span>
                        @endif
                        @if ($c->recurs)
                            <span class="badge bg-light text-muted">{{ __('recurring') }}</span>
                        @endif
                        @if ($c->meeting_id)
                            <span class="badge bg-light text-muted">
                                <i class="ti ti-users me-1"></i>{{ __('from a meeting') }}
                            </span>
                        @endif
                    </td>
                    @if (($show ?? 'owner') === 'owner')
                        <td class="small">{{ $c->owner_label }}</td>
                    @endif
                    <td class="small">{{ $c->owedTo->name ?? '—' }}</td>
                    <td class="small" dir="ltr">
                        {{ $c->deadline ? $c->deadline->format('Y-m-d') : '—' }}
                        @if ($c->is_overdue)
                            {{-- الأيام المتأخرة تُعرض صراحةً: "متأخر" وحدها لا تقول كم --}}
                            <span class="text-danger d-block">
                                {{ abs($c->days_left) }} {{ __('days late') }}
                            </span>
                        @elseif ($c->status === \App\Models\Commitment::STATUS_OPEN && $c->days_left !== null && $c->days_left <= 2)
                            <span class="text-warning d-block">
                                {{ $c->days_left === 0 ? __('due today') : $c->days_left . ' ' . __('days left') }}
                            </span>
                        @endif
                    </td>
                    <td>
                        <div class="d-flex align-items-center">
                            <div class="progress flex-grow-1" style="height:6px" dir="ltr">
                                <div class="progress-bar bg-{{ $c->status_badge }}"
                                     style="width: {{ min(max((int) $c->progress, 0), 100) }}%"></div>
                            </div>
                            <span class="ms-2 small" dir="ltr">{{ (int) $c->progress }}%</span>
                        </div>
                    </td>
                    <td class="text-center">
                        <span class="badge bg-{{ $c->status_badge }}">
                            @switch($c->status)
                                @case(\App\Models\Commitment::STATUS_DONE) {{ __('Kept') }} @break
                                @case(\App\Models\Commitment::STATUS_MISSED) {{ __('Missed') }} @break
                                @case(\App\Models\Commitment::STATUS_CANCELLED) {{ __('Withdrawn') }} @break
                                @default {{ $c->is_overdue ? __('Overdue') : __('Open') }}
                            @endswitch
                        </span>
                    </td>
                    <td class="text-end">
                        <a href="{{ route('commitments.show', $c->id) }}"
                           class="btn btn-sm btn-outline-primary">{{ __('Open') }}</a>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="{{ ($show ?? 'owner') === 'owner' ? 7 : 6 }}" class="text-center text-muted py-4">
                        {{ $emptyText ?? __('Nothing here.') }}
                    </td>
                </tr>
            @endforelse
        </tbody>
    </table>
</div>
