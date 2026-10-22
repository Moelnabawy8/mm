@extends('layouts.admin')

@section('page-title')
    {{ __('End-of-Day (EOD) Review') }}
@endsection

@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">{{ __('Dashboard') }}</a></li>
    <li class="breadcrumb-item"><a href="{{ route('quality-evaluations.index') }}">{{ __('Quality Evaluations') }}</a></li>
    <li class="breadcrumb-item">{{ __('End-of-Day (EOD)') }}</li>
@endsection

@section('content')
<div class="row">
    <div class="col-12">
        <div class="card shadow-sm border-0">
            <div class="card-header bg-dark text-white">
                <h5 class="mb-0 text-white"><i class="ti ti-moon me-2"></i>{{ __('Submit Your EOD Report') }}</h5>
            </div>
            <div class="card-body">
                <form id="eod-form">
                    @csrf
                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label class="form-label" for="report_date">{{ __('Report Date') }}</label>
                            <input type="date" class="form-control" id="report_date" name="report_date" value="{{ date('Y-m-d') }}" required readonly>
                        </div>
                    </div>

                    <h5 class="text-dark mt-4 border-bottom pb-2">النتائج اليومية</h5>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label text-dark fw-bold">عدد الداتا التي استلمتها اليوم *</label>
                            <input type="number" class="form-control" name="eod_data[data_received_today]" required min="0">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label text-dark fw-bold">عدد المبيعات التي حققتها اليوم *</label>
                            <input type="number" class="form-control" name="eod_data[sales_achieved_today]" required min="0">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label text-dark fw-bold">التحصيل المحقق اليوم *</label>
                            <input type="number" class="form-control" name="eod_data[collection_achieved_today]" required min="0" step="0.01">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label text-dark fw-bold">المديونيات التي حصلتها اليوم من عملائي *</label>
                            <input type="number" class="form-control" name="eod_data[debts_collected_today]" required min="0" step="0.01">
                        </div>
                    </div>

                    <h5 class="text-dark mt-4 border-bottom pb-2">مراجعة التارجت</h5>
                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label class="form-label text-dark fw-bold">كم حققت حتى اللحظة من تارجت الشهر الحالي؟ *</label>
                            <input type="number" class="form-control" name="eod_data[achieved_till_now_month_target]" required min="0" step="0.01">
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label text-dark fw-bold">كم حققت حتى اللحظة من التارجت الأسبوعي؟ *</label>
                            <input type="number" class="form-control" name="eod_data[achieved_till_now_weekly_target]" required min="0" step="0.01">
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label text-dark fw-bold d-block">هل حققت التارجت اليومي؟ *</label>
                            <div class="form-check form-check-inline">
                                <input class="form-check-input" type="radio" name="eod_data[did_achieve_daily_target]" value="نعم" required>
                                <label class="form-check-label">نعم</label>
                            </div>
                            <div class="form-check form-check-inline">
                                <input class="form-check-input" type="radio" name="eod_data[did_achieve_daily_target]" value="لا" required>
                                <label class="form-check-label">لا</label>
                            </div>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label text-dark fw-bold d-block">هل حققت المبلغ المالي الذي سعيت لتحصيله اليوم؟ *</label>
                            <div class="form-check form-check-inline">
                                <input class="form-check-input" type="radio" name="eod_data[did_achieve_collection_target]" value="نعم" required>
                                <label class="form-check-label">نعم</label>
                            </div>
                            <div class="form-check form-check-inline">
                                <input class="form-check-input" type="radio" name="eod_data[did_achieve_collection_target]" value="لا" required>
                                <label class="form-check-label">لا</label>
                            </div>
                        </div>
                    </div>

                    <h5 class="text-dark mt-4 border-bottom pb-2">الأنشطة والعملاء</h5>
                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label class="form-label text-dark fw-bold">العملاء الذين تواصلت معهم اليوم *</label>
                            <input type="number" class="form-control" name="eod_data[clients_contacted_today]" required min="0">
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label text-dark fw-bold">العملاء الذين عملت على إغلاق الصفقات معهم اليوم *</label>
                            <input type="number" class="form-control" name="eod_data[clients_worked_to_close_today]" required min="0">
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label text-dark fw-bold">البرنتيشن التي قمت بتنفيذها اليوم *</label>
                            <input type="number" class="form-control" name="eod_data[presentations_done_today]" required min="0">
                        </div>
                        <div class="col-md-12 mb-3">
                            <label class="form-label text-dark fw-bold d-block">هل رفعت رابط تسجيل البرزنتيشن او سكرين البرزنتيشن على الدرايف المخصص لك؟ *</label>
                            <div class="form-check form-check-inline">
                                <input class="form-check-input" type="radio" name="eod_data[did_upload_presentations]" value="نعم" required>
                                <label class="form-check-label">نعم</label>
                            </div>
                            <div class="form-check form-check-inline">
                                <input class="form-check-input" type="radio" name="eod_data[did_upload_presentations]" value="لا" required>
                                <label class="form-check-label">لا</label>
                            </div>
                        </div>
                        <div class="col-md-12 mb-3">
                            <label class="form-label text-dark fw-bold">ما هي نتيجة أهم ثلاث عملاء لليوم *</label>
                            <textarea class="form-control" name="eod_data[top_three_clients_results]" rows="2" required></textarea>
                        </div>
                    </div>

                    <h5 class="text-dark mt-4 border-bottom pb-2">تقييم الأداء والأفكار</h5>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label text-dark fw-bold">عدد ساعات العمل الفعلي التي حققتها اليوم *</label>
                            <input type="number" class="form-control" name="eod_data[actual_work_hours_today]" required min="0" max="24" step="0.5">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label text-dark fw-bold">ما دليلك على صحة عدد ساعات العمل هذا؟ *</label>
                            <input type="text" class="form-control" name="eod_data[proof_of_work_hours]" required>
                        </div>
                        <div class="col-md-12 mb-3">
                            <label class="form-label text-dark fw-bold">بماذا استخدمت الذكاء الصناعي اليوم؟ *</label>
                            <textarea class="form-control" name="eod_data[actual_ai_usage_today]" rows="2" required></textarea>
                        </div>
                        <div class="col-md-12 mb-3">
                            <label class="form-label text-dark fw-bold">هل ظهر عميل آخر مهم لترشيحه للاجتماع مع المهندس ابراهيم؟ ما اسمه ورقمة وما المهم فيه؟ *</label>
                            <textarea class="form-control" name="eod_data[new_vip_client_recommendation]" rows="2" required></textarea>
                        </div>
                        <div class="col-md-12 mb-3">
                            <label class="form-label text-dark fw-bold">فكرة / أفكار سأعمل عليها غداً *</label>
                            <textarea class="form-control" name="eod_data[ideas_for_tomorrow]" rows="2" required></textarea>
                        </div>
                    </div>

                    <div class="text-end mt-4">
                        <button type="submit" class="btn btn-dark btn-lg px-5 shadow-sm">
                            <i class="ti ti-send me-1"></i> {{ __('Submit EOD Report') }}
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
        $('#eod-form').on('submit', function(e) {
            e.preventDefault();
            
            let btn = $(this).find('button[type="submit"]');
            btn.prop('disabled', true).html('<i class="ti ti-loader ti-spin me-1"></i> Submitting...');

            $.ajax({
                url: "{{ route('reports.eod.submit') }}",
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
                    btn.prop('disabled', false).html('<i class="ti ti-send me-1"></i> Submit EOD Report');
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
