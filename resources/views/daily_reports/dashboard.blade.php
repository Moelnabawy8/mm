@extends('layouts.admin')
@section('page-title')
    {{ __('Daily Reports Dashboard') }}
@endsection
@section('title')
    {{ __('Reports Dashboard') }}
@endsection
@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">{{ __('Home') }}</a></li>
    <li class="breadcrumb-item"><a href="{{ route('quality-evaluations.index') }}">{{ __('Quality Evaluations') }}</a></li>
    <li class="breadcrumb-item">{{ __('Dashboard') }}</li>
@endsection

@section('content')
    <div class="row" id="dashboard-stats">
        <div class="col-lg-3 col-md-6">
            <div class="card">
                <div class="card-body">
                    <div class="row align-items-center justify-content-between">
                        <div class="col-auto mb-3 mb-sm-0">
                            <div class="d-flex align-items-center">
                                <div class="theme-avtar bg-primary">
                                    <i class="ti ti-target"></i>
                                </div>
                                <div class="ms-3">
                                    <small class="text-muted">{{ __('Achievement Rate') }}</small>
                                    <h6 class="m-0" id="stat-achievement">0%</h6>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-lg-3 col-md-6">
            <div class="card">
                <div class="card-body">
                    <div class="row align-items-center justify-content-between">
                        <div class="col-auto mb-3 mb-sm-0">
                            <div class="d-flex align-items-center">
                                <div class="theme-avtar bg-info">
                                    <i class="ti ti-chart-pie"></i>
                                </div>
                                <div class="ms-3">
                                    <small class="text-muted">{{ __('Conversion Rate') }}</small>
                                    <h6 class="m-0" id="stat-conversion">0%</h6>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-lg-3 col-md-6">
            <div class="card">
                <div class="card-body">
                    <div class="row align-items-center justify-content-between">
                        <div class="col-auto mb-3 mb-sm-0">
                            <div class="d-flex align-items-center">
                                <div class="theme-avtar bg-warning">
                                    <i class="ti ti-clock"></i>
                                </div>
                                <div class="ms-3">
                                    <small class="text-muted">{{ __('Efficiency Index') }}</small>
                                    <h6 class="m-0" id="stat-efficiency">0</h6>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-lg-3 col-md-6">
            <div class="card">
                <div class="card-body">
                    <div class="row align-items-center justify-content-between">
                        <div class="col-auto mb-3 mb-sm-0">
                            <div class="d-flex align-items-center">
                                <div class="theme-avtar bg-danger">
                                    <i class="ti ti-alert-triangle"></i>
                                </div>
                                <div class="ms-3">
                                    <small class="text-muted">{{ __('Missing EODs') }}</small>
                                    <h6 class="m-0" id="stat-missing-eod">0</h6>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row mt-4">
        <div class="col-md-12">
            <div class="card shadow-sm border-0">
                <div class="card-header bg-white d-flex justify-content-between align-items-center">
                    <h5 class="mb-0 text-primary"><i class="ti ti-list me-2"></i>{{ __('Detailed Reports View') }}</h5>
                    <button class="btn btn-sm btn-outline-success" id="btn-export">
                        <i class="ti ti-file-spreadsheet"></i> {{ __('Export CSV') }}
                    </button>
                </div>
                <div class="card-body table-border-style">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle" id="reports-table">
                            <thead class="table-light">
                                <tr>
                                    <th>{{ __('Date') }}</th>
                                    <th>{{ __('Employee') }}</th>
                                    <th>{{ __('Status') }}</th>
                                    <th class="text-center">{{ __('Actions (View Data)') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td colspan="4" class="text-center py-4 text-muted"><i class="ti ti-loader ti-spin me-2"></i>{{ __('Loading reports data...') }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Data Viewer Modal -->
    <div class="modal fade" id="dataViewerModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header bg-light">
                    <h5 class="modal-title text-primary" id="dataViewerTitle">Report Details</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body p-4">
                    <div id="dataViewerContent" class="row">
                        <!-- Dynamic content injected here -->
                    </div>
                </div>
                <div class="modal-footer border-0">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('script-page')
    <script>
        // Global object to store reports for modal
        let reportsData = {};

        // Helper to format keys like "total_clients_received" into "Total Clients Received"
        function formatKeyName(key) {
            let translations = {
                "total_clients_received": "إجمالي العملاء المستلمين",
                "total_sales_achieved": "إجمالي المبيعات",
                "total_collections_value": "قيمة التحصيلات",
                "total_clients_in_progress": "عملاء قيد التواصل",
                "total_clients_upsell": "عملاء Upsell",
                "total_lost_clients": "العملاء المفقودين",
                "current_month_target": "تارجت الشهر الحالي",
                "weekly_target": "التارجت الأسبوعي",
                "daily_target": "التارجت اليومي",
                "achieved_till_today_month": "المحقق حتى اليوم",
                "clients_to_close_this_month": "عملاء للإغلاق هذا الشهر",
                "top_three_clients_today": "أهم 3 عملاء لليوم",
                "new_data_received_today": "داتا جديدة مستلمة اليوم",
                "target_collection_today": "التحصيل المستهدف لليوم",
                "clients_to_contact_today": "العملاء المستهدف التواصل معهم",
                "presentations_done_this_month": "برزنتيشن منذ بداية الشهر",
                "remaining_debts_value": "المديونيات المتبقية",
                "debts_to_collect_this_month": "مديونيات للتحصيل هذا الشهر",
                "expected_work_hours_today": "ساعات العمل المتوقعة",
                "subscribed_clients_to_followup_today": "متابعة العملاء المشتركين",
                "ai_usage_plan_today": "خطة استخدام الذكاء الصناعي",
                "vip_client_recommendation": "ترشيح عميل VIP",
                "ideas_to_work_on_today": "أفكار للعمل اليوم",
                
                "data_received_today": "داتا تم استلامها اليوم",
                "sales_achieved_today": "مبيعات اليوم",
                "achieved_till_now_month_target": "المحقق من تارجت الشهر",
                "achieved_till_now_weekly_target": "المحقق من تارجت الأسبوع",
                "did_achieve_daily_target": "هل حققت التارجت اليومي؟",
                "collection_achieved_today": "التحصيل المحقق اليوم",
                "clients_worked_to_close_today": "عملاء تم العمل لإغلاقهم",
                "did_achieve_collection_target": "هل حققت هدف التحصيل؟",
                "clients_contacted_today": "عملاء تم التواصل معهم",
                "presentations_done_today": "برزنتيشن تمت اليوم",
                "did_upload_presentations": "تم رفع البرزنتيشن؟",
                "debts_collected_today": "المديونيات المحصلة اليوم",
                "actual_work_hours_today": "ساعات العمل الفعلية",
                "proof_of_work_hours": "دليل ساعات العمل",
                "actual_ai_usage_today": "استخدام الذكاء الصناعي الفعلي",
                "top_three_clients_results": "نتائج أهم 3 عملاء",
                "new_vip_client_recommendation": "ترشيح عميل VIP إضافي",
                "ideas_for_tomorrow": "أفكار للغد"
            };
            return translations[key] || key.replace(/_/g, ' ').replace(/\b\w/g, l => l.toUpperCase());
        }

        window.viewData = function(reportId, type) {
            let report = reportsData[reportId];
            if(!report || !report.logic_fields) return;

            let dataObj = type === 'SOD' ? report.logic_fields.sod_data : report.logic_fields.eod_data;
            let title = type === 'SOD' ? 'تفاصيل تقرير بداية اليوم (SOD)' : 'تفاصيل تقرير نهاية اليوم (EOD)';
            
            $('#dataViewerTitle').text(title);
            let contentHtml = '';

            if(!dataObj || Object.keys(dataObj).length === 0) {
                contentHtml = '<div class="col-12 text-center text-muted"><i class="ti ti-alert-circle me-1"></i> No data found for this report.</div>';
            } else {
                Object.keys(dataObj).forEach(function(key) {
                    let val = dataObj[key] !== null && dataObj[key] !== '' ? dataObj[key] : '—';
                    contentHtml += `
                        <div class="col-md-6 mb-3">
                            <div class="p-3 border rounded bg-white shadow-sm h-100">
                                <small class="text-muted d-block mb-1 fw-bold">${formatKeyName(key)}</small>
                                <span class="text-dark" style="white-space: pre-wrap;">${val}</span>
                            </div>
                        </div>
                    `;
                });
            }

            $('#dataViewerContent').html(contentHtml);
            var myModal = new bootstrap.Modal(document.getElementById('dataViewerModal'));
            myModal.show();
        };

        $(document).ready(function () {
            // Fetch Snapshot
            $.ajax({
                url: "{{ route('reports.snapshot') }}",
                type: 'GET',
                success: function (res) {
                    if(res.success) {
                        $('#stat-achievement').text((res.data.overall_target_achievement || 0).toFixed(1) + '%');
                        $('#stat-efficiency').text((res.data.overall_efficiency || 0).toFixed(1));
                        $('#stat-missing-eod').text(res.data.missing_eod || 0);
                    }
                }
            });

            // Fetch Summary
            $.ajax({
                url: "{{ route('reports.summary') }}",
                type: 'GET',
                success: function (res) {
                    if(res.success) {
                        $('#stat-conversion').text((res.data.conversion_rate || 0).toFixed(1) + '%');
                    }
                }
            });

            // Fetch Detailed Table
            $.ajax({
                url: "{{ route('reports.detailed') }}",
                type: 'GET',
                success: function (res) {
                    let tbody = $('#reports-table tbody');
                    tbody.empty();
                    if(res.success && res.data && res.data.reports && res.data.reports.length > 0) {
                        res.data.reports.forEach(function(r) {
                            reportsData[r.id] = r; // Store for modal

                            let statusBadge = r.status === 'submitted' ? '<span class="badge bg-success"><i class="ti ti-check me-1"></i>Complete</span>' : '<span class="badge bg-warning text-dark"><i class="ti ti-clock me-1"></i>Draft (Waiting EOD)</span>';
                            
                            let sodBtn = `<button onclick="viewData(${r.id}, 'SOD')" class="btn btn-sm btn-light-primary me-2 shadow-sm" title="View SOD"><i class="ti ti-sun"></i> View SOD</button>`;
                            let eodBtn = r.status === 'submitted' 
                                ? `<button onclick="viewData(${r.id}, 'EOD')" class="btn btn-sm btn-dark shadow-sm" title="View EOD"><i class="ti ti-moon"></i> View EOD</button>`
                                : `<button disabled class="btn btn-sm btn-light-secondary shadow-sm" title="EOD Not Submitted"><i class="ti ti-moon"></i> Pending</button>`;

                            let employeeName = r.user && r.user.name ? r.user.name : 'Unknown';
                            tbody.append(`
                                <tr>
                                    <td class="fw-bold">${r.report_date}</td>
                                    <td>
                                        <div class="d-flex align-items-center">
                                            <div class="theme-avtar bg-light-primary text-primary me-2" style="width: 35px; height: 35px; font-size: 14px;">
                                                #${r.user_id}
                                            </div>
                                            <span class="fw-bold">${employeeName}</span>
                                        </div>
                                    </td>
                                    <td>${statusBadge}</td>
                                    <td class="text-center">${sodBtn} ${eodBtn}</td>
                                </tr>
                            `);
                        });
                    } else {
                        tbody.append('<tr><td colspan="4" class="text-center py-4 text-muted"><i class="ti ti-info-circle me-2"></i>No reports found.</td></tr>');
                    }
                }
            });

            $('#btn-export').on('click', function() {
                window.location.href = "{{ route('reports.export') }}?format=csv";
            });
        });
    </script>
@endpush
