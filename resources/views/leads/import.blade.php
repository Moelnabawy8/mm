{{ Form::open(['route' => ['leads.file.import'], 'method' => 'post', 'enctype' => 'multipart/form-data', 'id' => 'import-leads-form']) }}
@csrf
<div class="modal-body">
    <div class="row">
        <div class="col-md-12">
            {{ Form::label('file', __('Select CSV/XLSX File'), ['class' => 'form-label']) }}
            <div class="choose-file form-group">
                <label for="file" class="form-label">
                    <input type="file" class="form-control" name="file" id="file" data-filename="upload_file"
                        accept=".csv, .xlsx, .xls" required>
                </label>
                <p class="upload_file"></p>
            </div>
        </div>
    </div>
</div>

<div class="modal-footer">
    <input type="button" value="{{ __('Cancel') }}" class="btn btn-secondary" data-bs-dismiss="modal">
    <button type="submit" value="{{ __('Upload') }}" class="btn btn-primary" id="upload-leads-btn">
        {{ __('Upload') }}
    </button>
</div>

{{ Form::close() }}

<div id="import-error-modal-content" style="display:none;"></div>

<script>
    $(document).ready(function() {
        $('#import-leads-form').on('submit', function(e) {
            e.preventDefault();

            // Check if file is selected
            var fileInput = document.getElementById('file');
            if (!fileInput.files || fileInput.files.length === 0) {
                show_toastr('Error', '{{ __('Please select a file') }}', 'error');
                return false;
            }

            // Create FormData and explicitly add the file
            var formData = new FormData();
            formData.append('file', fileInput.files[0]);
            formData.append('_token', '{{ csrf_token() }}');

            var $btn = $('#upload-leads-btn');
            var originalBtnText = $btn.text();

            // Debug: Check if file is in FormData
            console.log('File selected:', fileInput.files[0].name);
            console.log('File size:', fileInput.files[0].size, 'bytes');
            console.log('File type:', fileInput.files[0].type);
            for (var pair of formData.entries()) {
                console.log(pair[0] + ': ' + (pair[1] instanceof File ? pair[1].name : pair[1]));
            }

            $btn.prop('disabled', true).text('{{ __('Processing...') }}');

            $.ajax({
                url: $(this).attr('action'),
                type: 'POST',
                data: formData,
                contentType: false,
                processData: false,
                success: function(response) {
                    $btn.prop('disabled', false).text(originalBtnText);

                    if (response.status == 'success') {
                        show_toastr('success', response.message);
                        $('#commonModal').modal('hide');
                        window.location.reload();
                    } else if (response.status == 'validation_errors') {
                        // Close the current modal or switch content
                        // Changing content of commonModal is cleaner
                        var $modalBody = $('#commonModal .modal-body');
                        var $modalFooter = $('#commonModal .modal-footer');
                        var $modalHeader = $('#commonModal .modal-title');

                        $modalHeader.text('{{ __('Import Errors') }}');
                        $modalBody.html(response.html);

                        // Increase modal size
                        $('#commonModal .modal-dialog').addClass('modal-xl');

                        // Replace footer buttons
                        var footerHtml =
                            '<button type="button" class="btn btn-secondary" data-bs-dismiss="modal">{{ __('Cancel') }}</button>';
                        if (response.can_continue !== false) {
                            footerHtml +=
                                ' <button type="button" class="btn btn-primary" id="continue-import-btn">{{ __('Continue Import') }}</button>';
                        }
                        $modalFooter.html(footerHtml);

                        // Bind new event handler to Continue button
                        $('#continue-import-btn').on('click', function() {
                            var $continueBtn = $(this);
                            $continueBtn.prop('disabled', true).text(
                                '{{ __('Processing...') }}');

                            // Append continue flag
                            formData.append('continue', 'true');

                            $.ajax({
                                url: '{{ route('leads.file.import') }}',
                                type: 'POST',
                                data: formData,
                                contentType: false,
                                processData: false,
                                success: function(res) {
                                    if (res.status == 'success') {
                                        show_toastr('success', res
                                            .message);
                                        $('#commonModal').modal('hide');
                                        window.location.reload();
                                    } else {
                                        show_toastr('Error', res
                                            .message, 'error');
                                        $continueBtn.prop('disabled',
                                            false).text(
                                            '{{ __('Continue Import') }}'
                                        );
                                    }
                                },
                                error: function(err) {
                                    show_toastr('Error',
                                        '{{ __('An error occurred.') }}',
                                        'error');
                                    $continueBtn.prop('disabled', false)
                                        .text(
                                            '{{ __('Continue Import') }}'
                                        );
                                }
                            });
                        });

                    } else {
                        show_toastr('Error', response.message, 'error');
                    }
                },
                error: function(xhr, status, error) {
                    $btn.prop('disabled', false).text(originalBtnText);
                    console.error('Upload error:', xhr.responseText);

                    var errorMessage = '{{ __('An error occurred.') }}';
                    if (xhr.responseJSON && xhr.responseJSON.message) {
                        errorMessage = xhr.responseJSON.message;
                    } else if (xhr.responseJSON && xhr.responseJSON.errors) {
                        errorMessage = Object.values(xhr.responseJSON.errors).flat().join(
                            ', ');
                    } else if (xhr.status === 419) {
                        errorMessage =
                            '{{ __('Session expired. Please refresh the page and try again.') }}';
                    } else if (xhr.status === 413) {
                        errorMessage =
                            '{{ __('File size is too large. Please upload a smaller file.') }}';
                    }

                    show_toastr('Error', errorMessage, 'error');
                }
            });
        });
    });
</script>
