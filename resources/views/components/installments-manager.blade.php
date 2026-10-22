{{-- Inline Installments Manager Component --}}
<div id="installments-manager-wrapper" class="installments-manager" style="display: none;">
    <div class="card mt-3">
        <div class="card-header">
            <div class="d-flex justify-content-between align-items-center">
                <h6 class="mb-0">
                    <i class="ti ti-calendar"></i>
                    {{ __('Installments Management') }}
                </h6>
                <button type="button" class="btn btn-sm btn-primary" id="add-installment-btn">
                    <i class="ti ti-plus"></i> {{ __('Add Installment') }}
                </button>
            </div>
        </div>
        <div class="card-body">
            {{-- Summary Info --}}
            <div class="row mb-3">
                <div class="col-md-3">
                    <div class="card card-stats mb-0">
                        <div class="card-body p-3">
                            <div class="row align-items-center">
                                <div class="col">
                                    <h6 class="text-muted mb-1">{{ __('Total Price') }}</h6>
                                    <span class="h5 font-weight-bold mb-0" id="summary-total-price">0.00</span>
                                </div>
                                <div class="col-auto">
                                    <i class="ti ti-currency-dollar text-primary h2 mb-0"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card card-stats mb-0">
                        <div class="card-body p-3">
                            <div class="row align-items-center">
                                <div class="col">
                                    <h6 class="text-muted mb-1">{{ __('Total Installments') }}</h6>
                                    <span class="h5 font-weight-bold mb-0 text-success"
                                        id="summary-installments-total">0.00</span>
                                </div>
                                <div class="col-auto">
                                    <i class="ti ti-check text-success h2 mb-0"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card card-stats mb-0">
                        <div class="card-body p-3">
                            <div class="row align-items-center">
                                <div class="col">
                                    <h6 class="text-muted mb-1">{{ __('Exemption') }}</h6>
                                    <span class="h5 font-weight-bold mb-0 text-info"
                                        id="summary-exemption-total">0.00</span>
                                </div>
                                <div class="col-auto">
                                    <i class="ti ti-discount-2 text-info h2 mb-0"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card card-stats mb-0">
                        <div class="card-body p-3">
                            <div class="row align-items-center">
                                <div class="col">
                                    <h6 class="text-muted mb-1">{{ __('Remaining Balance') }}</h6>
                                    <span class="h5 font-weight-bold mb-0 text-warning"
                                        id="summary-remaining-balance">0.00</span>
                                </div>
                                <div class="col-auto">
                                    <i class="ti ti-alert-circle text-warning h2 mb-0"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Installments Table --}}
            <div id="installments-list" class="table-responsive mt-3">
                <table class="table align-items-center">
                    <thead>
                        <tr>
                            <th width="4%">#</th>
                            <th width="15%">{{ __('Date') }}</th>
                            <th width="12%">{{ __('Amount') }}</th>
                            <th width="10%">{{ __('Exemption') }}</th>
                            <th width="10%">{{ __('Effective') }}</th>
                            <th width="8%">{{ __('Status') }}</th>
                            <th width="8%">{{ __('Hopeless') }}</th>
                            <th width="20%">{{ __('Description') }}</th>
                            <th width="13%" class="text-end">{{ __('Actions') }}</th>
                        </tr>
                    </thead>
                    <tbody id="installments-tbody">
                        <tr id="no-installments-row">
                            <td colspan="9" class="text-center text-muted py-5">
                                <i class="ti ti-folder-off"
                                    style="font-size: 3rem; display: block; margin-bottom: 1rem;"></i>
                                <p class="mb-0">
                                    {{ __('No installments added yet. Click "Add Installment" to get started.') }}</p>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            {{-- Add/Edit Form (Hidden by default) --}}
            <div id="installment-form-container" style="display: none;" class="mt-3">
                <div class="card">
                    <div class="card-header">
                        <h6 class="mb-0" id="form-title">
                            <i class="ti ti-edit"></i> {{ __('Add New Installment') }}
                        </h6>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <input type="hidden" id="installment-id" value="">
                            <input type="hidden" id="installment-index" value="">

                            <div class="form-group col-md-4">
                                <label for="installment-date" class="form-label">
                                    {{ __('Date') }} <span class="text-danger">*</span>
                                </label>
                                <input type="date" class="form-control" id="installment-date">
                                <div class="invalid-feedback">{{ __('Please select a date') }}</div>
                            </div>

                            <div class="form-group col-md-4">
                                <label for="installment-amount" class="form-label">
                                    {{ __('Amount') }} <span class="text-danger">*</span>
                                </label>
                                <div class="input-group">
                                    <input type="number" class="form-control" id="installment-amount"
                                        step="0.01" min="0.01">
                                    <button type="button" class="btn btn-outline-primary" id="set-max-amount-btn"
                                        title="{{ __('Set to remaining balance') }}">
                                        <i class="ti ti-arrow-up"></i> {{ __('Max') }}
                                    </button>
                                </div>
                                <small class="form-text text-muted" id="amount-helper">
                                    {{ __('Remaining balance: ') }}<span id="remaining-for-input">0.00</span>
                                </small>
                                <div class="invalid-feedback">{{ __('Please enter a valid amount') }}</div>
                            </div>

                            <div class="form-group col-md-4">
                                <label for="installment-exemption" class="form-label">
                                    {{ __('Exemption') }}
                                    <i class="ti ti-info-circle" data-bs-toggle="tooltip"
                                        title="{{ __('Amount to forgive/exempt from this installment') }}"></i>
                                </label>
                                <input type="number" class="form-control" id="installment-exemption" step="0.01"
                                    min="0" value="0">
                                <small class="form-text text-muted">
                                    {{ __('Effective amount: ') }}<span id="effective-amount-display">0.00</span>
                                </small>
                            </div>

                            <div class="form-group col-md-4">
                                <label for="installment-status" class="form-label">
                                    {{ __('Status') }} <span class="text-danger">*</span>
                                </label>
                                <select class="form-control" id="installment-status">
                                    <option value="unpaid">{{ __('Unpaid') }}</option>
                                    <option value="paid">{{ __('Paid') }}</option>
                                </select>
                            </div>

                            <div class="form-group col-md-6">
                                <label for="installment-description"
                                    class="form-label">{{ __('Description') }}</label>
                                <input type="text" class="form-control" id="installment-description"
                                    placeholder="{{ __('Optional notes...') }}">
                            </div>

                            <div class="form-group col-md-6">
                                <label class="form-label d-block">
                                    {{ __('Hopeless Status') }}
                                    <i class="ti ti-info-circle" data-bs-toggle="tooltip"
                                        title="{{ __('Mark as hopeless if customer cannot pay this installment') }}"></i>
                                </label>
                                <div class="form-check form-switch" style="padding-top: 8px;">
                                    <input class="form-check-input" type="checkbox" id="installment-hopeless"
                                        style="width: 3rem; height: 1.5rem; cursor: pointer;">
                                    <label class="form-check-label ms-2" for="installment-hopeless"
                                        style="cursor: pointer; font-weight: 500;">
                                        <span id="hopeless-label">{{ __('OFF') }}</span>
                                    </label>
                                </div>
                                <small class="form-text text-danger" id="hopeless-warning" style="display: none;">
                                    <i class="ti ti-alert-triangle"></i>
                                    {{ __('This installment is marked as uncollectable') }}
                                </small>
                            </div>
                        </div>

                        <div class="text-end mt-3">
                            <button type="button" class="btn btn-primary" id="save-installment-btn">
                                <i class="ti ti-check"></i> {{ __('Save Installment') }}
                            </button>
                            <button type="button" class="btn btn-secondary" id="cancel-installment-btn">
                                <i class="ti ti-x"></i> {{ __('Cancel') }}
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
    /* Smooth animations */
    .installments-manager {
        animation: slideInUp 0.3s ease-out;
    }

    @keyframes slideInUp {
        from {
            opacity: 0;
            transform: translateY(15px);
        }

        to {
            opacity: 1;
            transform: translateY(0);
        }
    }

    .installment-row-enter {
        animation: fadeInRow 0.3s ease-out;
    }

    @keyframes fadeInRow {
        from {
            opacity: 0;
            transform: translateX(-10px);
        }

        to {
            opacity: 1;
            transform: translateX(0);
        }
    }

    #installment-form-container {
        animation: expandForm 0.3s ease-out;
    }

    @keyframes expandForm {
        from {
            opacity: 0;
            transform: scaleY(0.95);
        }

        to {
            opacity: 1;
            transform: scaleY(1);
        }
    }

    /* Table styling enhancements */
    #installments-list .table tbody tr {
        transition: background-color 0.2s ease;
    }

    #installments-list .table tbody tr:hover {
        background-color: rgba(0, 0, 0, 0.02);
    }

    /* Summary cards styling */
    .card-stats {
        transition: transform 0.2s ease, box-shadow 0.2s ease;
    }

    .card-stats:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
    }
</style>
