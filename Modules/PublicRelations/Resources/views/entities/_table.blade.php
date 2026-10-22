<div class="table-responsive">
    <table class="table table-striped">
        <thead>
            <tr>
                <th>{{ __('Name') }}</th>
                <th>{{ __('Type') }}</th>
                <th>{{ __('Region') }}</th>
                <th>{{ __('Contact Person') }}</th>
                <th>{{ __('Phone / Email') }}</th>
                <th>{{ __('Status') }}</th>
                <th>{{ __('Latest Interaction') }}</th>
                <th width="150px">{{ __('Action') }}</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($entities as $entity)
                <tr>
                    <td>
                        <a href="{{ route('pr.show', $entity->id) }}">{{ $entity->name }}</a>
                    </td>
                    <td>{{ $entity->type->label() }}</td>
                    <td>{{ $entity->region?->label() ?? '-' }}</td>
                    <td>{{ $entity->contact_person ?? '-' }}</td>
                    <td>
                        {{ $entity->phone ?? '-' }}
                        @if ($entity->email)
                            <br><small class="text-muted">{{ $entity->email }}</small>
                        @endif
                    </td>
                    <td>
                        <span class="badge bg-{{ match($entity->status->value) {
                            'active' => 'primary',
                            'partner' => 'success',
                            'on_hold' => 'warning',
                            'inactive' => 'danger',
                            default => 'secondary',
                        } }}">{{ $entity->status->label() }}</span>
                    </td>
                    <td>
                        @if ($entity->latestInteractionLog)
                            {{ $entity->latestInteractionLog->current_action }}
                            <br><small class="text-muted">{{ \Auth::user()->dateFormat($entity->latestInteractionLog->interaction_date) }}</small>
                        @else
                            <span class="text-muted">-</span>
                        @endif
                    </td>
                    <td class="Action">
                        <span>
                            @can('manage pr entity')
                                <div class="action-btn me-2">
                                    <a href="{{ route('pr.show', $entity->id) }}"
                                        class="mx-3 btn btn-sm align-items-center bg-warning" data-bs-toggle="tooltip"
                                        title="{{ __('View') }}">
                                        <i class="ti ti-eye text-white"></i>
                                    </a>
                                </div>
                            @endcan
                            @can('edit pr entity')
                                <div class="action-btn me-2">
                                    <a href="#" class="mx-3 btn btn-sm align-items-center bg-info"
                                        data-url="{{ route('pr.edit', $entity->id) }}" data-ajax-popup="true"
                                        data-size="lg" data-title="{{ __('Edit Public Relationships Entity') }}"
                                        data-bs-toggle="tooltip" title="{{ __('Edit') }}">
                                        <i class="ti ti-pencil text-white"></i>
                                    </a>
                                </div>
                            @endcan
                            @can('delete pr entity')
                                <div class="action-btn me-2">
                                    {!! Form::open(['method' => 'DELETE', 'route' => ['pr.destroy', $entity->id], 'id' => 'delete-form-' . $entity->id]) !!}
                                    <a href="#" class="mx-3 btn btn-sm align-items-center bs-pass-para bg-danger"
                                        data-bs-toggle="tooltip" title="{{ __('Delete') }}">
                                        <i class="ti ti-trash text-white"></i>
                                    </a>
                                    {!! Form::close() !!}
                                </div>
                            @endcan
                        </span>
                    </td>
                </tr>
            @empty
                <tr class="font-style">
                    <td colspan="8" class="text-center">{{ __('No data available in table') }}</td>
                </tr>
            @endforelse
        </tbody>
    </table>
</div>
@if ($entities->hasPages())
    <div class="d-flex justify-content-center mt-3">
        {{ $entities->links('pagination::bootstrap-5') }}
    </div>
@endif
