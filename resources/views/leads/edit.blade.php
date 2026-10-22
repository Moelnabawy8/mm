{{ Form::model($lead, ['route' => ['leads.update', $lead->id], 'method' => 'PUT', 'class' => 'needs-validation', 'novalidate']) }}
<div class="modal-body">
    {{-- start for ai module --}}
    @php
        $plan = \App\Models\Utility::getChatGPTSettings();
    @endphp
    @if ($plan->chatgpt == 1)
        <div class="text-end">
            <a href="#" data-size="md" class="btn  btn-primary btn-icon btn-sm" data-ajax-popup-over="true"
                data-url="{{ route('generate', ['lead']) }}" data-bs-placement="top"
                data-title="{{ __('Generate content with AI') }}">
                <i class="fas fa-robot"></i> <span>{{ __('Generate with AI') }}</span>
            </a>
        </div>
    @endif
    {{-- end for ai module --}}
    <div class="row">
        <div class="col-6 form-group">
            {{ Form::label('name', __('Name'), ['class' => 'form-label']) }}<x-required></x-required>
            {{ Form::text('name', null, ['class' => 'form-control', 'required' => 'required', 'placeholder' => __('Enter Name')]) }}
        </div>
        <div class="col-6 form-group">
            {{ Form::label('email', __('Email'), ['class' => 'form-label']) }}
            {{ Form::email('email', null, ['class' => 'form-control check-unique', 'placeholder' => __('Enter Email'), 'data-check-url' => route('leads.check.uniqueness'), 'data-id' => $lead->id]) }}
        </div>
        <div class="col-6 form-group">
            <x-mobile label="{{ __('Phone') }}" name="phone" value="{{ $lead->phone }}" placeholder="Enter Phone"
                class="form-control check-unique" data-check-url="{{ route('leads.check.uniqueness') }}"
                data-id="{{ $lead->id }}"></x-mobile>
        </div>
        <div class="col-6 form-group">
            {{ Form::label('telegram', __('Telegram'), ['class' => 'form-label']) }}
            {{ Form::text('telegram', null, ['class' => 'form-control', 'placeholder' => __('Enter Telegram username or phone')]) }}
        </div>
        <div class="col-6 form-group">
            {{ Form::label('gender', __('Gender'), ['class' => 'form-label']) }}
            {{ Form::select('gender', ['Male' => 'Male', 'Female' => 'Female'], null, ['class' => 'form-control select', 'placeholder' => __('Select Gender')]) }}
        </div>
        <div class="col-6 form-group">
            {{ Form::label('country', __('Country'), ['class' => 'form-label']) }}
            <select name="country" id="country_select" class="form-control select2">
                <option value="">{{ __('Select Country') }}</option>
                @foreach([
                    'Afghanistan','Albania','Algeria','Andorra','Angola','Antigua and Barbuda','Argentina','Armenia','Australia','Austria',
                    'Azerbaijan','Bahamas','Bahrain','Bangladesh','Barbados','Belarus','Belgium','Belize','Benin','Bhutan','Bolivia',
                    'Bosnia and Herzegovina','Botswana','Brazil','Brunei','Bulgaria','Burkina Faso','Burundi','Cabo Verde','Cambodia',
                    'Cameroon','Canada','Central African Republic','Chad','Chile','China','Colombia','Comoros','Congo','Costa Rica',
                    'Croatia','Cuba','Cyprus','Czech Republic','Denmark','Djibouti','Dominica','Dominican Republic','Ecuador','Egypt',
                    'El Salvador','Equatorial Guinea','Eritrea','Estonia','Eswatini','Ethiopia','Fiji','Finland','France','Gabon',
                    'Gambia','Georgia','Germany','Ghana','Greece','Grenada','Guatemala','Guinea','Guinea-Bissau','Guyana','Haiti',
                    'Honduras','Hungary','Iceland','India','Indonesia','Iran','Iraq','Ireland','Israel','Italy','Jamaica','Japan',
                    'Jordan','Kazakhstan','Kenya','Kiribati','Kuwait','Kyrgyzstan','Laos','Latvia','Lebanon','Lesotho','Liberia',
                    'Libya','Liechtenstein','Lithuania','Luxembourg','Madagascar','Malawi','Malaysia','Maldives','Mali','Malta',
                    'Marshall Islands','Mauritania','Mauritius','Mexico','Micronesia','Moldova','Monaco','Mongolia','Montenegro',
                    'Morocco','Mozambique','Myanmar','Namibia','Nauru','Nepal','Netherlands','New Zealand','Nicaragua','Niger',
                    'Nigeria','North Korea','North Macedonia','Norway','Oman','Pakistan','Palau','Palestine','Panama','Papua New Guinea',
                    'Paraguay','Peru','Philippines','Poland','Portugal','Qatar','Romania','Russia','Rwanda','Saint Kitts and Nevis',
                    'Saint Lucia','Saint Vincent and the Grenadines','Samoa','San Marino','Sao Tome and Principe','Saudi Arabia',
                    'Senegal','Serbia','Seychelles','Sierra Leone','Singapore','Slovakia','Slovenia','Solomon Islands','Somalia',
                    'South Africa','South Korea','South Sudan','Spain','Sri Lanka','Sudan','Suriname','Sweden','Switzerland','Syria',
                    'Taiwan','Tajikistan','Tanzania','Thailand','Timor-Leste','Togo','Tonga','Trinidad and Tobago','Tunisia',
                    'Turkey','Turkmenistan','Tuvalu','Uganda','Ukraine','United Arab Emirates','United Kingdom','United States',
                    'Uruguay','Uzbekistan','Vanuatu','Vatican City','Venezuela','Vietnam','Yemen','Zambia','Zimbabwe', 'Other'
                ] as $country)
                    <option value="{{ $country }}" {{ $lead->country == $country ? 'selected' : '' }}>{{ $country }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-6 form-group">
            {{ Form::label('city', __('City'), ['class' => 'form-label']) }}
            {{ Form::text('city', null, ['class' => 'form-control', 'placeholder' => __('Enter City')]) }}
        </div>
        <div class="col-6 form-group">
            {{ Form::label('costing_type', __('Costing Type'), ['class' => 'form-label']) }}
            {{ Form::select('costing_type', ['organic' => __('Organic'), 'paid' => __('Paid')], $lead->costing_type, ['class' => 'form-control select', 'placeholder' => __('Select Costing Type')]) }}
        </div>
        <div class="col-6 form-group">
            {{ Form::label('campaign_name', __('Campaign Name'), ['class' => 'form-label']) }}
            {{ Form::text('campaign_name', null, ['class' => 'form-control', 'placeholder' => __('Enter Campaign Name')]) }}
        </div>
        <div class="col-6 form-group">
            {{ Form::label('cost', __('Cost'), ['class' => 'form-label']) }}
            {{ Form::number('cost', null, ['class' => 'form-control', 'placeholder' => __('Enter Cost'), 'step' => '0.01']) }}
        </div>
        <div class="col-6 form-group">
            {{ Form::label('pipeline_id', __('Pipeline'), ['class' => 'form-label']) }}
            {{ Form::select('pipeline_id', $pipelines, null, ['class' => 'form-control select']) }}
            <div class="text-xs mt-1">
                {{ __('Create pipeline here.') }} <a
                    href="{{ route('pipelines.index') }}"><b>{{ __('Create pipeline') }}</b></a>
            </div>
        </div>
        <div class="col-6 form-group">
            {{ Form::label('stage_id', __('Stage'), ['class' => 'form-label']) }}
            {{ Form::select('stage_id', ['' => __('Select Stage')], null, ['class' => 'form-control select']) }}
            <div class="text-xs mt-1">
                {{ __('Create stage here.') }} <a
                    href="{{ route('lead_stages.index') }}"><b>{{ __('Create stage') }}</b></a>
            </div>
        </div>
        <div class="col-12 form-group">
            {{ Form::label('sources', __('Sources'), ['class' => 'form-label']) }}
            {{ Form::select('sources[]', $sources, null, ['class' => 'form-control select2', 'id' => 'choices-multiple2', 'multiple' => '', 'placeholder' => __('Select Source')]) }}
            <div class="text-xs mt-1">
                {{ __('Create source here.') }} <a
                    href="{{ route('sources.index') }}"><b>{{ __('Create source') }}</b></a>
            </div>
        </div>
        <div class="col-12 form-group">
            {{ Form::label('labels', __('Tags'), ['class' => 'form-label']) }}
            {{ Form::select('labels[]', $labels, null, ['class' => 'form-control select2', 'id' => 'choices-multiple', 'multiple' => '']) }}
        </div>
        <div class="col-12 form-group">
            {{ Form::label('interests', __('Interests'), ['class' => 'form-label']) }}
            {{ Form::select('interests[]', \App\Models\Interest::where('created_by', \Auth::user()->creatorId())->pluck('name', 'id'), explode(',', $lead->interests), ['class' => 'form-control select2', 'id' => 'choices-multiple-interests', 'multiple' => '']) }}
        </div>
        <div class="col-12 form-group">
            {{ Form::label('products', __('Products'), ['class' => 'form-label']) }}
            {{ Form::select('products[]', $products, null, ['class' => 'form-control select2', 'id' => 'choices-multiple1', 'multiple' => '', 'placeholder' => __('Select Product')]) }}
            <div class="text-xs mt-1">
                {{ __('Create product here.') }} <a
                    href="{{ route('productservice.index') }}"><b>{{ __('Create product') }}</b></a>
            </div>
        </div>
        <div class="col-12 form-group">
            {{ Form::label('notes', __('Notes'), ['class' => 'form-label']) }}
            {{ Form::textarea('notes', null, ['class' => 'summernote-simple']) }}
        </div>
    </div>
</div>

<div class="modal-footer">
    <input type="button" value="{{ __('Cancel') }}" class="btn  btn-secondary" data-bs-dismiss="modal">
    <input type="submit" value="{{ __('Update') }}" class="btn  btn-primary">
</div>

{{ Form::close() }}



<script>
    var stage_id = '{{ $lead->stage_id }}';

    var pipeline_id = $('[name=pipeline_id]').val();
    getStages(pipeline_id);

    $(document).on("change", "select[name=pipeline_id]", function() {
        var currVal = $(this).val();
        getStages(currVal);
    });

    var stageChoices;
    function getStages(id) {
        $.ajax({
            url: '{{ route('leads.json') }}',
            data: {
                pipeline_id: id,
                _token: $('meta[name="csrf-token"]').attr('content')
            },
            type: 'POST',
            success: function(data) {
                var stage_cnt = Object.keys(data).length;
                if (stageChoices) {
                    stageChoices.destroy();
                }
                $("#stage_id").empty();
                if (stage_cnt > 0) {
                    $.each(data, function(key, data1) {
                        var select = '';
                        if (key == '{{ $lead->stage_id }}') {
                            select = 'selected';
                        }
                        $("#stage_id").append('<option value="' + key + '" ' + select + '>' +
                            data1 + '</option>');
                    });
                }
                $("#stage_id").val(stage_id);
                var element = document.getElementById('stage_id');
                if (element) {
                    stageChoices = new Choices(element, {
                        removeItemButton: true,
                        placeholder: true,
                        placeholderValue: "{{ __('Select Stage') }}",
                        searchEnabled: true
                    });
                }
            }
        })
    }

    // Auto-fill Telegram from Phone
    $(document).ready(function() {
        $('input[name="phone"]').on('change keyup', function() {
            var phone = $(this).val();
            var telegramInput = $('input[name="telegram"]');

            if (telegramInput.val() === '' || telegramInput.val() === phone.slice(0, -1)) {
                telegramInput.val(phone);
            }
        });
    });
</script>
