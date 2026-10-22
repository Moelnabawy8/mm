{{ Form::model($meeting, ['route' => ['sales-meetings.update', $meeting->id], 'method' => 'PUT', 'enctype' => 'multipart/form-data']) }}
<div class="modal-body">
    <div class="row">
        <div class="form-group col-6">
            {{ Form::label('title', __('Title'), ['class' => 'form-label']) }}
            {{ Form::text('title', null, ['class' => 'form-control', 'required' => 'required']) }}
        </div>
        <div class="form-group col-6">
            {{ Form::label('meeting_type_id', __('Meeting Type'), ['class' => 'form-label']) }}
            {{ Form::select('meeting_type_id', $types, null, ['class' => 'form-control select2', 'required' => 'required']) }}
        </div>
        <div class="form-group col-6">
            {{ Form::label('lead_select_search', __('Lead'), ['class' => 'form-label']) }}
            {{-- Server-searched: rendering every lead here froze the browser --}}
            @include('sales_meetings._search_picker', [
                'pickerId' => 'lead_select',
                'fieldName' => 'lead_id',
                'url' => route('sales-meetings.leads.search'),
                'selectedId' => $meeting->lead_id,
                'selectedName' => $meeting->lead->name ?? '',
                'placeholder' => __('Search lead by name, phone or email'),
                'required' => true,
            ])
        </div>
        <div class="form-group col-6">
            {{ Form::label('deal_id', __('Deal'), ['class' => 'form-label']) }}
            <div id="deal_div">
                {{ Form::select('deal_id', ['' => __('Select Deal')] + collect($deals)->all(), null, ['class' => 'form-control select2', 'id' => 'deal_select']) }}
            </div>
        </div>
        <div class="form-group col-6">
            {{ Form::label('datetime', __('Date'), ['class' => 'form-label']) }}
            {{ Form::input('datetime-local', 'datetime', $meeting->datetime ? $meeting->datetime->format('Y-m-d\TH:i') : null, ['class' => 'form-control', 'required' => 'required']) }}
        </div>
        <div class="form-group col-6">
            {{ Form::label('duration', __('Duration (minutes)'), ['class' => 'form-label']) }}
            {{ Form::number('duration', null, ['class' => 'form-control', 'min' => '1', 'placeholder' => __('e.g. 30, 60, 90')]) }}
        </div>
        @include('sales_meetings._status_field')
        <div class="form-group col-12">
            {{ Form::label('products', __('Products'), ['class' => 'form-label']) }}
            {{ Form::select('products[]', $products, $meeting->products->pluck('id')->toArray(), ['class' => 'form-control select2', 'id' => 'products_select', 'multiple' => 'multiple']) }}
        </div>
        <div class="form-group col-12">
            {{ Form::label('link', __('Link (Optional)'), ['class' => 'form-label']) }}
            {{ Form::text('link', null, ['class' => 'form-control']) }}
        </div>
        <div class="form-group col-12">
            {{ Form::label('attachment', __('Attachment'), ['class' => 'form-label']) }}
            @if ($meeting->attachment)
                <p><a href="{{ route('sales-meetings.attachment', $meeting->id) }}"
                        target="_blank">{{ $meeting->attachment }}</a></p>
            @endif
            {{ Form::file('attachment', ['class' => 'form-control']) }}
        </div>
        <div class="form-group col-12">
            {{ Form::label('notes', __('Notes'), ['class' => 'form-label']) }}
            {{ Form::textarea('notes', null, ['class' => 'form-control summernote-simple']) }}
        </div>
    </div>
</div>
<div class="modal-footer">
    <input type="button" value="{{ __('Cancel') }}" class="btn  btn-light" data-bs-dismiss="modal">
    <input type="submit" value="{{ __('Update') }}" class="btn  btn-primary">
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
                    disabled: false
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
                $element.append($('<option value=""></option>').text(placeholder));
                $.each(data, function(key, value) {
                    $element.append($('<option></option>').attr('value', key).text(value));
                });
            }
        }

        // Namespaced + off(): this modal is loaded again on every open, and a plain
        // on() stacked one more deals request per open.
        $(document).off('change.smLead').on('change.smLead', '#lead_select', function() {
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
                }
            });
        });
    });
</script>
