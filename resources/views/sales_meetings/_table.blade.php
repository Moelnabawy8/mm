{{-- Statistics Summary --}}
@if (isset($stats))
    <div class="row mb-3">
        <div class="col-md-2">
            <div class="card bg-primary text-white">
                <div class="card-body p-3">
                    <h6 class="mb-0">{{ __('Total Meetings') }}</h6>
                    <h3 class="mb-0">{{ $stats['total_meetings'] }}</h3>
                </div>
            </div>
        </div>
        <div class="col-md-2">
            <div class="card bg-info text-white">
                <div class="card-body p-3">
                    <h6 class="mb-0">{{ __('Calls') }}</h6>
                    <h3 class="mb-0">{{ $stats['total_calls'] }}</h3>
                </div>
            </div>
        </div>
        <div class="col-md-2">
            <div class="card bg-success text-white">
                <div class="card-body p-3">
                    <h6 class="mb-0">{{ __('Presentations') }}</h6>
                    <h3 class="mb-0">{{ $stats['total_presentations'] }}</h3>
                </div>
            </div>
        </div>
        <div class="col-md-2">
            <div class="card bg-warning text-white">
                <div class="card-body p-3">
                    <h6 class="mb-0">{{ __('Clients') }}</h6>
                    <h3 class="mb-0">{{ $stats['total_clients'] }}</h3>
                </div>
            </div>
        </div>
        <div class="col-md-2">
            <div class="card bg-danger text-white">
                <div class="card-body p-3">
                    <h6 class="mb-0">{{ __('Deals') }}</h6>
                    <h3 class="mb-0">{{ $stats['total_deals'] }}</h3>
                </div>
            </div>
        </div>
    </div>
@endif

<div class="table-responsive">
    <table class="table table-striped">
        <thead>
            <tr>
                <th>{{ __('Title') }}</th>
                <th>{{ __('Lead') }}</th>
                <th>{{ __('Deal') }}</th>
                <th>{{ __('Assigned Staff') }}</th>
                <th>{{ __('Date & Time') }}</th>
                <th>{{ __('Duration') }}</th>
                <th>{{ __('Type') }}</th>
                <th>{{ __('Link') }}</th>
                <th width="250px">{{ __('Action') }}</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($meetings as $meeting)
                <tr>
                    <td>{{ $meeting->title }}</td>
                    <td>
                        @if ($meeting->lead)
                            <a href="{{ route('leads.show', $meeting->lead->id) }}">{{ $meeting->lead->name }}</a>
                        @else
                            -
                        @endif
                    </td>
                    <td>
                        @if ($meeting->deal)
                            <a href="{{ route('deals.show', $meeting->deal->id) }}">{{ $meeting->deal->name }}</a>
                        @else
                            -
                        @endif
                    </td>
                    <td>
                        {{-- Read from the user_leads pivot, the same source the lead page and
                             the leads list use. This column used to read leads.user_id, a
                             separate column that can point at a different person, so the two
                             screens named different staff for the same lead. --}}
                        @forelse ($meeting->lead ? $meeting->lead->users : [] as $user)
                            <span class="d-inline-flex align-items-center me-2">
                                <img alt="{{ $user->name }}"
                                    @if ($user->avatar) src="{{ asset('/storage/uploads/avatar/' . $user->avatar) }}" @else src="{{ asset('/storage/uploads/avatar/avatar.png') }}" @endif
                                    class="rounded-circle me-1" width="25" height="25">
                                <span dir="auto">{{ $user->name }}</span>
                            </span>
                        @empty
                            <span class="text-muted">-</span>
                        @endforelse
                    </td>
                    <td>
                        @if ($meeting->datetime)
                            {{ \Auth::user()->dateFormat($meeting->datetime) }}
                            {{-- date-only meetings are stored at midnight; do not invent a 12:00 AM --}}
                            @if ($meeting->datetime->format('H:i') !== '00:00')
                                {{ $meeting->datetime->format('h:i A') }}
                            @endif
                        @elseif ($meeting->date)
                            {{ \Auth::user()->dateFormat($meeting->date) }} {{ $meeting->time }}
                        @else
                            -
                        @endif
                    </td>
                    <td>
                        @if ($meeting->duration)
                            {{ $meeting->duration }} {{ __('min') }}
                        @else
                            -
                        @endif
                    </td>
                    <td>{{ $meeting->meetingType ? $meeting->meetingType->name : '-' }}</td>
                    <td>{{ $meeting->link }}</td>
                    <td class="Action">
                        <span>
                            @can('view lead')
                                <div class="action-btn me-2">
                                    <a href="{{ route('sales-meetings.show', $meeting->id) }}"
                                        class="mx-3 btn btn-sm align-items-center bg-warning" data-bs-toggle="tooltip"
                                        title="{{ __('View') }}">
                                        <i class="ti ti-eye text-white"></i>
                                    </a>
                                </div>
                            @endcan
                            @can('edit lead')
                                <div class="action-btn me-2">
                                    <a href="#" class="mx-3 btn btn-sm align-items-center bg-info"
                                        data-url="{{ route('sales-meetings.edit', $meeting->id) }}" data-ajax-popup="true"
                                        data-size="lg" data-title="{{ __('Edit Meeting') }}" data-bs-toggle="tooltip"
                                        title="{{ __('Edit') }}">
                                        <i class="ti ti-pencil text-white"></i>
                                    </a>
                                </div>
                            @endcan
                            @can('delete lead')
                                @if (!Auth::user()->hasRole('Sales Employee'))
                                    <div class="action-btn me-2">
                                        {!! Form::open(['method' => 'DELETE', 'route' => ['sales-meetings.destroy', $meeting->id]]) !!}
                                        <a href="#" class="mx-3 btn btn-sm align-items-center bs-pass-para bg-danger"
                                            data-bs-toggle="tooltip" title="{{ __('Delete') }}"><i
                                                class="ti ti-trash text-white"></i></a>
                                        {!! Form::close() !!}
                                    </div>
                                @endif
                            @endcan
                        </span>
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>
</div>
@if($meetings->hasPages())
<div class="d-flex justify-content-center mt-3">
    {{ $meetings->links('pagination::bootstrap-5') }}
</div>
@endif
