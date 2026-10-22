<div class="row">
    <div class="col-md-12">
        @php
            $dbDuplicateErrors = collect($errors)->where('row', 0);
            $rowErrors = collect($errors)->where('row', '!=', 0);
        @endphp

        @if($dbDuplicateErrors->count() > 0)
            <div class="alert alert-danger" role="alert">
                <h6 class="alert-heading mb-2"><i class="ti ti-alert-triangle me-1"></i>{{ __('File Rejected — Duplicate Records Found') }}</h6>
                @foreach($dbDuplicateErrors as $error)
                    <p class="mb-1">{{ $error['error'] }}</p>
                @endforeach
                <hr class="my-2">
                <p class="mb-0 small">{{ __('Please remove the duplicate phone numbers/emails from your file and try again. The entire file was rejected — no records were imported.') }}</p>
            </div>
        @endif

        @if($rowErrors->count() > 0)
            <div class="alert alert-warning" role="alert">
                <span class="text-danger">
                    {{ __('The following rows have errors and will be ignored if you continue. Please review them or cancel to fix your file.') }}
                </span>
            </div>

            <div class="table-responsive" style="max-height: 400px; overflow-y: auto;">
                <table class="table table-bordered table-striped table-sm">
                    <thead>
                        <tr>
                            <th>{{ __('Row') }}</th>
                            <th>{{ __('Name') }}</th>
                            <th>{{ __('Email') }}</th>
                            <th>{{ __('Phone') }}</th>
                            <th>{{ __('Error') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($rowErrors as $error)
                            <tr>
                                <td>{{ $error['row'] }}</td>
                                <td>{{ $error['data']['name'] ?? '-' }}</td>
                                <td>{{ $error['data']['email'] ?? '-' }}</td>
                                <td>{{ $error['data']['phone'] ?? '-' }}</td>
                                <td class="text-danger">{{ $error['error'] }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif

        <div class="mt-3">
            <p>{{ __('Total Errors') }}: <strong>{{ count($errors) }}</strong></p>
        </div>
    </div>
</div>
