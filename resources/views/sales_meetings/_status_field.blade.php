{{-- Meeting status, read by CrmReportsServiceProvider (field name meeting_status) and reported in CRM Reports. --}}
@php $currentStatus = isset($meeting) ? ($meeting->status ?? '') : 'scheduled'; @endphp
<div class="form-group col-6">
    <label class="form-label" for="meeting_status">{{ __('Meeting Status') }}</label>
    <select name="meeting_status" id="meeting_status" class="form-control">
        <option value="" @selected($currentStatus === '')>{{ __('Not set') }}</option>
        @foreach (['scheduled' => __('Scheduled'), 'done' => __('Done'), 'no_show' => __('No show'), 'cancelled' => __('Cancelled')] as $value => $label)
            <option value="{{ $value }}" @selected($currentStatus === $value)>{{ $label }}</option>
        @endforeach
    </select>
</div>
