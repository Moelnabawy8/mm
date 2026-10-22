@extends('layouts.admin')

@section('page-title')
    {{ __('Start-of-Day (SOD) Strategy') }}
@endsection

@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">{{ __('Dashboard') }}</a></li>
    <li class="breadcrumb-item"><a href="{{ route('quality-evaluations.index') }}">{{ __('Quality Evaluations') }}</a></li>
    <li class="breadcrumb-item">{{ __('Start-of-Day (SOD)') }}</li>
@endsection

@section('content')
<div class="row">
    <div class="col-12">
        <div class="card shadow-sm border-0">
            <div class="card-header bg-primary text-white">
                <h5 class="mb-0 text-white"><i class="ti ti-sun me-2"></i>{{ __('Submit Your SOD Report') }}</h5>
            </div>
            <div class="card-body">
                <form id="sod-form">
                    @csrf
                    <div class="row">
                        <!-- User ID is inferred from auth() -->
                        <div class="col-md-4 mb-3">
                            <label class="form-label" for="report_date">{{ __('Report Date') }}</label>
                            <input type="date" class="form-control" id="report_date" name="report_date" value="{{ date('Y-m-d') }}" required readonly>
                        </div>
                    </div>

                    <h5 class="text-primary mt-4 border-bottom pb-2">البيانات المتراكمة (التاريخية)</h5>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label text-dark fw-bold">عدد العملاء الكلي الذي تم استلامه من اول يوم عمل رسمي وحتى اليوم *</label>
                            <input type="number" class="form-control" name="sod_data[total_clients_received]" required min="0">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label text-dark fw-bold">عدد المبيعات التي حققتها من بداية عملي حتى اليوم *</label>
                            <input type="number" class="form-control" name="sod_data[total_sales_achieved]" required min="0">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label text-dark fw-bold">قيمة التحصيلات التي حققتها من بداية عملي حتى اليوم *</label>
                            <input type="number" class="form-control" name="sod_data[total_collections_value]" required min="0" step="0.01">
                        </div>
                    </div>

                    <h5 class="text-primary mt-4 border-bottom pb-2">موقف العملاء الحاليين</h5>
                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label class="form-label text-dark fw-bold">العملاء الذين ما زلت على تواصل معهم للمبيعة الأولى *</label>
                            <input type="number" class="form-control" name="sod_data[total_clients_in_progress]" required min="0">
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label text-dark fw-bold">العملاء المستهدفين لمبيعات إضافية (Upsell) *</label>
                            <input type="number" class="form-control" name="sod_data[total_clients_upsell]" required min="0">
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label text-dark fw-bold">عدد العملاء المقطوع منهم الأمل *</label>
                            <input type="number" class="form-control" name="sod_data[total_lost_clients]" required min="0">
                        </div>
                    </div>

                    <h5 class="text-primary mt-4 border-bottom pb-2">التارجت (الأهداف)</h5>
                    <div class="row">
                        <div class="col-md-3 mb-3">
                            <label class="form-label text-dark fw-bold">تارجت الشهر الحالي *</label>
                            <input type="number" class="form-control" name="sod_data[current_month_target]" required min="0" step="0.01">
                        </div>
                        <div class="col-md-3 mb-3">
                            <label class="form-label text-dark fw-bold">التارجت الأسبوعي *</label>
                            <input type="number" class="form-control" name="sod_data[weekly_target]" required min="0" step="0.01">
                        </div>
                        <div class="col-md-3 mb-3">
                            <label class="form-label text-dark fw-bold">التارجت اليومي *</label>
                            <input type="number" class="form-control" name="sod_data[daily_target]" required min="0" step="0.01">
                        </div>
                        <div class="col-md-3 mb-3">
                            <label class="form-label text-dark fw-bold">المحقق حتى اليوم (من بداية الشهر) *</label>
                            <input type="number" class="form-control" name="sod_data[achieved_till_today_month]" required min="0" step="0.01">
                        </div>
                    </div>

                    <h5 class="text-primary mt-4 border-bottom pb-2">خطة العمل اليومية / الشهرية</h5>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label text-dark fw-bold">عدد الداتا الجديدة التي استلمتها اليوم *</label>
                            <input type="number" class="form-control" name="sod_data[new_data_received_today]" required min="0">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label text-dark fw-bold">عدد العملاء الذين أعمل على إغلاق الصفقات معهم هذا الشهر *</label>
                            <input type="number" class="form-control" name="sod_data[clients_to_close_this_month]" required min="0">
                        </div>
                        <div class="col-md-12 mb-3">
                            <label class="form-label text-dark fw-bold">أسماء أهم ثلاث عملاء لليوم *</label>
                            <textarea class="form-control" name="sod_data[top_three_clients_today]" rows="2" required placeholder="1. ... 2. ... 3. ..."></textarea>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label text-dark fw-bold">المبلغ المالي الذي سأسعى لتحصيله اليوم *</label>
                            <input type="number" class="form-control" name="sod_data[target_collection_today]" required min="0" step="0.01">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label text-dark fw-bold">عدد العملاء الذين سأتواصل معاهم اليوم *</label>
                            <input type="number" class="form-control" name="sod_data[clients_to_contact_today]" required min="0">
                        </div>
                    </div>

                    <h5 class="text-primary mt-4 border-bottom pb-2">المديونيات والاجتماعات</h5>
                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label class="form-label text-dark fw-bold">البرنتيشن التي نفذتها منذ بداية الشهر *</label>
                            <input type="number" class="form-control" name="sod_data[presentations_done_this_month]" required min="0">
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label text-dark fw-bold">قيمة المديونيات المتبقية على عملائي *</label>
                            <input type="number" class="form-control" name="sod_data[remaining_debts_value]" required min="0" step="0.01">
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label text-dark fw-bold">المديونيات التي سأحصلها هذا الشهر *</label>
                            <input type="number" class="form-control" name="sod_data[debts_to_collect_this_month]" required min="0" step="0.01">
                        </div>
                    </div>

                    <h5 class="text-primary mt-4 border-bottom pb-2">الإنتاجية والأفكار</h5>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label text-dark fw-bold">ساعات العمل الفعلي المتوقعة لليوم *</label>
                            <input type="number" class="form-control" name="sod_data[expected_work_hours_today]" required min="0" max="24" step="0.5">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label text-dark fw-bold">العملاء المشتركين المطلوب متابعتهم اليوم *</label>
                            <input type="number" class="form-control" name="sod_data[subscribed_clients_to_followup_today]" required min="0">
                        </div>
                        <div class="col-md-12 mb-3">
                            <label class="form-label text-dark fw-bold">بماذا ستستخدم الذكاء الصناعي اليوم؟ *</label>
                            <textarea class="form-control" name="sod_data[ai_usage_plan_today]" rows="2" required></textarea>
                        </div>
                        <div class="col-md-12 mb-3">
                            <label class="form-label text-dark fw-bold">هل لديك عميل مهم ترشحه للاجتماع مع المهندس ابراهيم؟ ما اسمه ورقمة وما المهم فيه؟ *</label>
                            <textarea class="form-control" name="sod_data[vip_client_recommendation]" rows="2" required></textarea>
                        </div>
                        <div class="col-md-12 mb-3">
                            <label class="form-label text-dark fw-bold">فكرة / أفكار سأعمل عليها اليوم *</label>
                            <textarea class="form-control" name="sod_data[ideas_to_work_on_today]" rows="2" required></textarea>
                        </div>
                    </div>

                    <div class="text-end mt-4">
                        <button type="submit" class="btn btn-primary btn-lg px-5 shadow-sm">
                            <i class="ti ti-send me-1"></i> {{ __('Submit SOD Report') }}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
    $(document).ready(function() {
        $('#sod-form').on('submit', function(e) {
            e.preventDefault();
            
            let btn = $(this).find('button[type="submit"]');
            btn.prop('disabled', true).html('<i class="ti ti-loader ti-spin me-1"></i> Submitting...');

            $.ajax({
                url: "{{ route('reports.sod.submit') }}",
                type: 'POST',
                data: $(this).serialize(),
                success: function(response) {
                    Swal.fire({
                        icon: 'success',
                        title: 'Success!',
                        text: response.message,
                        timer: 2000,
                        showConfirmButton: false
                    }).then(() => {
                        window.location.href = "{{ route('reports.dashboard') }}";
                    });
                },
                error: function(xhr) {
                    btn.prop('disabled', false).html('<i class="ti ti-send me-1"></i> Submit SOD Report');
                    let errorMsg = 'An error occurred';
                    if(xhr.responseJSON && xhr.responseJSON.message) {
                        errorMsg = xhr.responseJSON.message;
                    }
                    Swal.fire({
                        icon: 'error',
                        title: 'Oops...',
                        text: errorMsg,
                    });
                }
            });
        });
    });
</script>
@endpush
