{{ Form::open(['url' => 'sales-meetings', 'enctype' => 'multipart/form-data']) }}
<div class="modal-body">
    <div class="row">
        <div class="form-group col-6">
            {{ Form::label('title', __('Title'), ['class' => 'form-label']) }}
            {{ Form::text('title', '', ['class' => 'form-control', 'required' => 'required']) }}
        </div>
        <div class="form-group col-6">
            {{ Form::label('meeting_type_id', __('Meeting Type'), ['class' => 'form-label']) }}
            {{ Form::select('meeting_type_id', $types, null, ['class' => 'form-control select2', 'required' => 'required']) }}
        </div>
        @if ($lead_id > 0)
            {{-- When coming from lead page, hide the lead selector and use hidden field --}}
            {{ Form::hidden('lead_id', $lead_id) }}
        @else
            <div class="form-group col-6">
                {{ Form::label('lead_id', __('Lead'), ['class' => 'form-label']) }}
                {{ Form::select('lead_id', $leads, $lead_id, ['class' => 'form-control select2', 'required' => 'required', 'id' => 'lead_select']) }}
            </div>
        @endif
        <div class="form-group col-6">
            {{ Form::label('deal_id', __('Deal'), ['class' => 'form-label']) }}
            <div id="deal_div">
                {{ Form::select('deal_id', $deals, null, ['class' => 'form-control select2', 'id' => 'deal_select']) }}
            </div>
        </div>
        <div class="form-group col-6">
            {{ Form::label('datetime', __('Date'), ['class' => 'form-label']) }}
            {{ Form::date('datetime', '', ['class' => 'form-control', 'required' => 'required']) }}
        </div>
        <div class="form-group col-6">
            {{ Form::label('duration', __('Duration (minutes)'), ['class' => 'form-label']) }}
            {{ Form::number('duration', '', ['class' => 'form-control', 'min' => '1', 'placeholder' => __('e.g. 30, 60, 90')]) }}
        </div>
        <div class="form-group col-12">
            {{ Form::label('products', __('Products'), ['class' => 'form-label']) }}
            {{ Form::select('products[]', $products, null, ['class' => 'form-control select2', 'id' => 'products_select', 'multiple' => 'multiple']) }}
        </div>
        <div class="form-group col-12">
            {{ Form::label('link', __('Link (Optional)'), ['class' => 'form-label']) }}
            {{ Form::text('link', '', ['class' => 'form-control']) }}
        </div>
        <div class="form-group col-12">
            {{ Form::label('attachment', __('Attachment'), ['class' => 'form-label']) }}
            {{ Form::file('attachment', ['class' => 'form-control']) }}
        </div>
        <div class="form-group col-12">
            {{ Form::label('notes', __('Notes'), ['class' => 'form-label']) }}
            {{ Form::textarea('notes', '', ['class' => 'form-control summernote-simple']) }}
        </div>
    </div>
</div>
<div class="modal-footer">
    <input type="button" value="{{ __('Cancel') }}" class="btn  btn-light" data-bs-dismiss="modal">
    <input type="submit" value="{{ __('Create') }}" class="btn  btn-primary">
</div>
{{ Form::close() }}

<script>
    $(document).ready(function() {
        // Function to update Choices.js instance
        function updateChoices(elementId, data, placeholder) {
            var element = document.getElementById(elementId);
            if (element && element.choices) {
                var choicesInstance = element.choices;
                choicesInstance.clearStore();
                choicesInstance.clearInput();

                var choicesData = [{
                    value: '',
                    label: placeholder,
                    selected: true,
                    disabled: true
                }];

                $.each(data, function(key, value) {
                    choicesData.push({
                        value: key,
                        label: value,
                        selected: false,
                        disabled: false
                    });
                });

                choicesInstance.setChoices(choicesData, 'value', 'label', true);
            } else {
                // Fallback for standard select
                var $element = $('#' + elementId);
                $element.empty();
                $element.append('<option value="">' + placeholder + '</option>');
                $.each(data, function(key, value) {
                    $element.append('<option value="' + key + '">' + value + '</option>');
                });
            }
        }

        $(document).on('change', '#lead_select', function() {
            var lead_id = $(this).val();

            if (!lead_id) {
                updateChoices('deal_select', {}, '{{ __('Select Deal') }}');
                return;
            }

            $.ajax({
                url: '{{ route('sales-meetings.get-deals') }}',
                type: 'POST',
                data: {
                    "lead_id": lead_id,
                    "_token": "{{ csrf_token() }}",
                },
                success: function(data) {
                    updateChoices('deal_select', data, '{{ __('Select Deal') }}');
                },
                error: function(xhr, status, error) {
                    // console.error("Error loading deals:", error);
                }
            });
        });
    });
</script>
