@php($productsMap = $productsMap ?? collect())
<div class="table-responsive">
    <table class="table table-striped">
        <thead>
            <tr>
                <th>{{ __('Name') }}</th>
                <th>{{ __('Lead') }}</th>
                <th>{{ __('Assigned Staff') }}</th>
                <th>{{ __('Products') }}</th>
                <th>{{ __('Status') }}</th>
                <th>{{ __('Attachments') }}</th>
                <th>{{ __('Date & Time') }}</th>
                <th>{{ __('Created At') }}</th>
                <th width="300px">{{ __('Action') }}</th>
            </tr>
        </thead>
        <tbody>
            @if (count($deals) > 0)
                @foreach ($deals as $deal)
                    <tr>
                        <td>{{ $deal->name }}</td>
                        <td>
                            @if ($deal->lead)
                                <a href="{{ route('leads.show', $deal->lead->id) }}"
                                    class="btn btn-sm btn-outline-primary">{{ $deal->lead->name }}</a>
                            @else
                                -
                            @endif
                        </td>
                        <td>
                            {{-- Read from the user_leads pivot, the same source the lead page and
                                 the leads list use. This column used to read leads.user_id, which
                                 is a separate column that can point at a different person, so the
                                 two screens named different staff for the same lead. --}}
                            @forelse ($deal->lead ? $deal->lead->users : [] as $user)
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
                            @foreach (($deal->products ? explode(',', $deal->products) : []) as $pid)
                                @if(isset($productsMap[$pid]))
                                    <span class="badge bg-primary p-2 px-3 rounded">{{ $productsMap[$pid] }}</span>
                                @endif
                            @endforeach
                        </td>
                        <td>{{ $deal->stage ? $deal->stage->name : '-' }}</td>
                        <td>
                            @php($attachmentCount = $deal->attachments_count ?? $deal->attachments->count())
                            @if ($attachmentCount > 0)
                                <span class="badge bg-info rounded-pill">
                                    <i class="ti ti-paperclip"></i> {{ $attachmentCount }}
                                </span>
                            @else
                                <span class="text-muted">-</span>
                            @endif
                        </td>
                        <td>{{ $deal->datetime ? \Auth::user()->dateFormat($deal->datetime) . ($deal->datetime->format('H:i') !== '00:00' ? ' ' . $deal->datetime->format('h:i A') : '') : '-' }}
                        </td>
                        <td>{{ \Auth::user()->dateFormat($deal->created_at) }}</td>
                        <td class="Action">
                            <span>
                                @can('view deal')
                                    @if ($deal->is_active)
                                        <div class="action-btn me-2">
                                            <a href="{{ route('deals.show', $deal->id) }}"
                                                class="mx-3 btn btn-sm align-items-center bg-warning" data-size="xl"
                                                data-bs-toggle="tooltip" title="{{ __('View') }}"
                                                data-title="{{ __('Deal Detail') }}">
                                                <i class="ti ti-eye text-white"></i>
                                            </a>
                                        </div>
                                    @endif
                                @endcan
                                @can('edit deal')
                                    <div class="action-btn me-2">
                                        <a href="#" class="mx-3 btn btn-sm align-items-center bg-info"
                                            data-url="{{ URL::to('deals/' . $deal->id . '/edit') }}" data-ajax-popup="true"
                                            data-size="xl" data-bs-toggle="tooltip" title="{{ __('Edit') }}"
                                            data-title="{{ __('Deal Edit') }}">
                                            <i class="ti ti-pencil text-white"></i>
                                        </a>
                                    </div>
                                @endcan
                                @can('delete deal')
                                    @if (!Auth::user()->hasRole('Sales Employee'))
                                        <div class="action-btn me-2">
                                            {!! Form::open([
                                                'method' => 'DELETE',
                                                'route' => ['deals.destroy', $deal->id],
                                                'id' => 'delete-form-' . $deal->id,
                                            ]) !!}
                                            <a href="#" class="mx-3 btn btn-sm  align-items-center bs-pass-para bg-danger"
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
            @else
                <tr class="font-style">
                    <td colspan="8" class="text-center">{{ __('No data available in table') }}</td>
                </tr>
                @endif

            </tbody>
        </table>
    </div>
    @if($deals->hasPages())
    <div class="d-flex justify-content-center mt-3">
        {{ $deals->links('pagination::bootstrap-5') }}
    </div>
    @endif
