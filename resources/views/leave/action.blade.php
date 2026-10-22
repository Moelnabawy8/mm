{{ Form::open(['url' => 'leave/changeaction', 'method' => 'post']) }}
<div class="modal-body">
    <div class="row">
        <div class="col-12">
            <table class="table modal-table">
                <tr role="row">
                    <th>{{ __('Employee') }}</th>
                    <td>{{ !empty($employee->name) ? $employee->name : '' }}</td>
                </tr>
                <tr>
                    <th>{{ __('Leave Type ') }}</th>
                    <td>{{ !empty($leavetype->title) ? $leavetype->title : '' }}</td>
                </tr>
                <tr>
                    <th>{{ __('Appplied On') }}</th>
                    <td>{{ \Auth::user()->dateFormat($leave->applied_on) }}</td>
                </tr>
                <tr>
                    <th>{{ __('Start Date') }}</th>
                    <td>{{ \Auth::user()->dateFormat($leave->start_date) }}</td>
                </tr>
                <tr>
                    <th>{{ __('End Date') }}</th>
                    <td>{{ \Auth::user()->dateFormat($leave->end_date) }}</td>
                </tr>
                <tr>
                    <th>{{ __('Leave Reason') }}</th>
                    <td>{{ !empty($leave->leave_reason) ? $leave->leave_reason : '' }}</td>
                </tr>
                <tr>
                    <th>{{ __('Status') }}</th>
                    <td>{{ !empty($leave->status) ? $leave->status : '' }}</td>
                </tr>
                @if ($leave->attachments && $leave->attachments->count() > 0)
                    <tr>
                        <th>{{ __('Attachments') }}</th>
                        <td>
                            <div class="d-flex flex-wrap gap-2">
                                @foreach ($leave->attachments as $attachment)
                                    <a href="{{ $attachment->file_url }}" target="_blank"
                                        class="d-flex align-items-center text-decoration-none border rounded p-1 px-2">
                                        <i class="{{ $attachment->file_icon }} me-1"></i>
                                        <span class="small">{{ $attachment->original_name }}</span>
                                    </a>
                                @endforeach
                            </div>
                        </td>
                    </tr>
                @endif
                <input type="hidden" value="{{ $leave->id }}" name="leave_id">
            </table>
        </div>
    </div>
</div>
@if (\Auth::user()->type == 'company' || \Auth::user()->type == 'HR')
    <div class="modal-footer">
        <input type="submit" value="{{ __('Approval') }}" class="btn btn-success" data-bs-dismiss="modal"
            name="status">
        <input type="submit" value="{{ __('Reject') }}" class="btn btn-danger" name="status">
    </div>
@endif
{{ Form::close() }}
