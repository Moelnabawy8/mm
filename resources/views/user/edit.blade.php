{{Form::model($user,array('route' => array('users.update', $user->id), 'method' => 'PUT', 'class'=>'needs-validation','novalidate')) }}
<div class="modal-body">
    <div class="row">
        <div class="col-md-6">
            <div class="form-group ">
                {{Form::label('name',__('Name'),['class'=>'form-label']) }}<x-required></x-required>
                {{Form::text('name',null,array('class'=>'form-control font-style','placeholder'=>__('Enter User Name'), 'required' => 'required'))}}
                @error('name')
                <small class="invalid-name" role="alert">
                    <strong class="text-danger">{{ $message }}</strong>
                </small>
                @enderror
            </div>
        </div>
        <div class="col-md-6">
            <div class="form-group">
                {{Form::label('email',__('Email'),['class'=>'form-label'])}}<x-required></x-required>
                {{Form::text('email',null,array('class'=>'form-control','placeholder'=>__('Enter User Email'), 'required' => 'required'))}}
                @error('email')
                <small class="invalid-email" role="alert">
                    <strong class="text-danger">{{ $message }}</strong>
                </small>
                @enderror
            </div>
        </div>
        @can('manage whatsapp recipient')
            <div class="col-md-6">
                <div class="form-group">
                    {{ Form::label('whatsapp', __('WhatsApp number'), ['class' => 'form-label']) }}
                    {{ Form::text('whatsapp', $user->whatsapp, ['class' => 'form-control', 'dir' => 'ltr', 'placeholder' => '+962791234567']) }}
                    <small class="text-muted">{{ __('International format. Task notifications are sent here.') }}</small>
                </div>
            </div>
        @endcan
        @if(\Auth::user()->type != 'super admin')
            <div class="form-group col-md-6">
                {{ Form::label('role', __('User Role'),['class'=>'form-label']) }}<x-required></x-required>
                {!! Form::select('role', $roles, $user->roles,array('class' => 'form-control select','required'=>'required', 'id' => 'edit_user_role')) !!}
                <div class="text-xs mt-1">
                    {{ __('Create role here.') }} <a href="{{ route('roles.index') }}"><b>{{ __('Create role') }}</b></a>
                </div>
                @error('role')
                <small class="invalid-role" role="alert">
                    <strong class="text-danger">{{ $message }}</strong>
                </small>
                @enderror
            </div>
            <div class="form-group col-md-6 edit_sales_group_div d-none">
                {{ Form::label('sales_group', __('Sales Group'), ['class' => 'form-label']) }}
                <select name="sales_group" id="edit_sales_group" class="form-control select">
                    <option value="">{{ __('Select Group') }}</option>
                    <option value="A" {{ $user->sales_group == 'A' ? 'selected' : '' }}>A</option>
                    <option value="B" {{ $user->sales_group == 'B' ? 'selected' : '' }}>B</option>
                    <option value="JOR" {{ $user->sales_group == 'JOR' ? 'selected' : '' }}>JOR</option>
                </select>
                @error('sales_group')
                <small class="invalid-sales_group" role="alert">
                    <strong class="text-danger">{{ $message }}</strong>
                </small>
                @enderror
            </div>
        @endif
        @if(!$customFields->isEmpty())
                    @include('customFields.formBuilder')
        @endif
    </div>

</div>

<div class="modal-footer">
    <input type="button" value="{{__('Cancel')}}" class="btn  btn-secondary"data-bs-dismiss="modal">
    <input type="submit" value="{{__('Update')}}" class="btn  btn-primary">
</div>

{{Form::close()}}

<script>
    $(document).ready(function() {
        var roleNames = @json($roles);

        function toggleEditSalesGroup() {
            var selectedRoleId = $('#edit_user_role').val();
            var roleName = roleNames[selectedRoleId] || '';
            var isSalesRole = roleName.toLowerCase().indexOf('sales') !== -1;

            if (isSalesRole) {
                $('.edit_sales_group_div').removeClass('d-none');
            } else {
                $('.edit_sales_group_div').addClass('d-none');
                $('#edit_sales_group').val('');
            }
        }

        $('#edit_user_role').on('change', toggleEditSalesGroup);
        toggleEditSalesGroup(); // check on load
    });
</script>
