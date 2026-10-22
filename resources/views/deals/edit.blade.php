{{ Form::model($deal, ['route' => ['deals.update', $deal->id], 'method' => 'PUT', 'class' => 'needs-validation', 'novalidate']) }}
<div class="modal-body">
    <div class="row">
        <div class="col-6 form-group">
            {{ Form::label('name', __('Deal Name'), ['class' => 'form-label']) }}<x-required></x-required>
            {{ Form::text('name', null, ['class' => 'form-control', 'required' => 'required', 'placeholder' => __('Enter Deal Name')]) }}
        </div>
        @if (request('lead_id'))
            {{ Form::hidden('lead_id', request('lead_id')) }}
        @else
            <div class="col-6 form-group">
                {{ Form::label('lead_id', __('Lead'), ['class' => 'form-label']) }}<x-required></x-required>
                @include('deals._lead_picker', [
                    'pickerId' => 'edit_deal_lead_' . $deal->id,
                    'fieldName' => 'lead_id',
                    'selectedId' => $deal->lead_id,
                    'selectedName' => $deal->lead->name ?? '',
                    'required' => true,
                ])
            </div>
        @endif
        <div class="col-12 form-group">
            {{ Form::label('products', __('Products'), ['class' => 'form-label']) }}
            {{ Form::select('products[]', $products, $deal->products, ['class' => 'form-control select2', 'multiple' => '', 'id' => 'choices-multiple4', 'placeholder' => __('Select Products')]) }}
        </div>
        <div class="col-6 form-group">
            {{ Form::label('stage_id', __('Status'), ['class' => 'form-label']) }}<x-required></x-required>
            {{ Form::select('stage_id', $stages, null, ['class' => 'form-control select2', 'required' => 'required', 'id' => 'stage_id', 'placeholder' => __('Select Status')]) }}
        </div>
        <div class="col-6 form-group">
            {{ Form::label('datetime', __('Date'), ['class' => 'form-label']) }}
            {{ Form::date('datetime', $deal->datetime ? $deal->datetime->format('Y-m-d') : null, ['class' => 'form-control', 'placeholder' => __('Select Date')]) }}
        </div>
        <div class="col-12 form-group">
            {{ Form::label('notes', __('Notes'), ['class' => 'form-label']) }}
            {{ Form::textarea('notes', null, ['class' => 'summernote-simple', 'placeholder' => __('Enter Note')]) }}
        </div>

        {{-- Attachments Info --}}
        <div class="col-12">
            <div class="alert alert-info d-flex align-items-center" role="alert">
                <i class="ti ti-paperclip me-2" style="font-size: 1.5rem;"></i>
                <div>
                    <strong>{{ __('Attachments') }}:</strong>
                    @php($attachmentCount = $deal->attachments()->count())
                    {{ $attachmentCount }} {{ __('file(s)') }}
                    <a href="{{ route('deals.show', $deal->id) }}#attachments" class="alert-link ms-2">
                        {{ __('Manage Attachments') }} <i class="ti ti-external-link"></i>
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>
<div class="modal-footer">
    <input type="button" value="{{ __('Cancel') }}" class="btn btn-secondary" data-bs-dismiss="modal">
    <input type="submit" value="{{ __('Update') }}" class="btn btn-primary">
</div>
{{ Form::close() }}
