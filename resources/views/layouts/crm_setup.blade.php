<div class="col-lg-3">
    <div class="card sticky-top" style="top:30px">
        <div class="list-group list-group-flush" id="useradd-sidenav">
            <a href="{{ route('pipelines.index') }}"
                class="list-group-item list-group-item-action border-0 {{ Request::route()->getName() == 'pipelines.index' ? ' active' : '' }}">{{ __('Pipeline') }}
                <div class="float-end"><i class="ti ti-chevron-right"></i></div>
            </a>

            <a href="{{ route('lead_stages.index') }}"
                class="list-group-item list-group-item-action border-0 {{ Request::route()->getName() == 'lead_stages.index' ? ' active' : '' }}">{{ __('Lead Stage') }}
                <div class="float-end"><i class="ti ti-chevron-right"></i></div>
            </a>

            <a href="{{ route('deal-statuses.index') }}"
                class="list-group-item list-group-item-action border-0 {{ Request::route()->getName() == 'deal-statuses.index' ? ' active' : '' }}">{{ __('Deal Status') }}
                <div class="float-end"><i class="ti ti-chevron-right"></i></div>
            </a>

            <a href="{{ route('sales-meeting-types.index') }}"
                class="list-group-item list-group-item-action border-0 {{ Request::route()->getName() == 'sales-meeting-types.index' ? ' active' : '' }}">{{ __('Sales Meeting Types') }}
                <div class="float-end"><i class="ti ti-chevron-right"></i></div>
            </a>

            <a href="{{ route('sales-meeting-payment-methods.index') }}"
                class="list-group-item list-group-item-action border-0 {{ Request::route()->getName() == 'sales-meeting-payment-methods.index' ? ' active' : '' }}">{{ __('Payment Method') }}
                <div class="float-end"><i class="ti ti-chevron-right"></i></div>
            </a>

            <a href="{{ route('sources.index') }}"
                class="list-group-item list-group-item-action border-0 {{ Request::route()->getName() == 'sources.index' ? 'active' : '' }}   ">{{ __('Sources') }}
                <div class="float-end"><i class="ti ti-chevron-right"></i></div>
            </a>

            <a href="{{ route('labels.index') }}"
                class="list-group-item list-group-item-action border-0 {{ Request::route()->getName() == 'labels.index' ? 'active' : '' }}   ">{{ __('Labels') }}
                <div class="float-end"><i class="ti ti-chevron-right"></i></div>
            </a>

            <a href="{{ route('contractType.index') }}"
                class="list-group-item list-group-item-action border-0 {{ Request::route()->getName() == 'contractType.index' ? 'active' : '' }}   ">{{ __('Contract Type') }}
                <div class="float-end"><i class="ti ti-chevron-right"></i></div>
            </a>

        </div>
    </div>
</div>
