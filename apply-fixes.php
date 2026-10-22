<?php
/**
 * apply-fixes.php
 * ------------------------------------------------------------------
 * بيطبّق تعديلات محددة بالسطر على ملفات البرودكشن، بدل ما ترفع ملفات
 * كاملة. ليه؟ لأن الملفات المحلية فيها شغل تاني لسه مترفعش، ورفعها
 * كاملة هيشحن تعديلات مش مقصودة.
 *
 * لكل تعديل:
 *   - بيتأكد إن النص المطلوب موجود بالعدد المتوقع بالظبط
 *   - لو مش موجود -> بيتخطاه ويقولك، ومش بيلمس الملف
 *   - بعض التعديلات ليها شرط (وجود isHrOrCompany في User.php)
 *   - بياخد نسخة احتياطية قبل أي كتابة
 * idempotent: لو اتشغّل تاني هيقول "متطبّق قبل كده" ومش هيكرر.
 *
 *   php apply-fixes.php          <- فحص فقط، مش بيكتب حاجة
 *   php apply-fixes.php --fix    <- التطبيق
 * ------------------------------------------------------------------
 */

$doFix = in_array('--fix', $argv, true);
$stamp = date('Ymd_His');

function line($s = '') { echo $s . PHP_EOL; }
function hr() { line(str_repeat('-', 74)); }

$root = __DIR__;
if (!is_dir("$root/app") || !is_dir("$root/resources")) {
    line("  [FAIL] شغّله من جوه مجلد اللارافيل."); exit(1);
}

// شرط: الميثود دي محلية لسه، لو مش موجودة على السيرفر يبقى أي تعديل
// بيعتمد عليها هيكسر الصفحة بـ Call to undefined method.
$userSrc     = @file_get_contents("$root/app/Models/User.php") ?: '';
$hasHrHelper = str_contains($userSrc, 'function isHrOrCompany');

hr();
line("  تطبيق الإصلاحات   |   وضع: " . ($doFix ? "كتابة" : "فحص فقط"));
line("  isHrOrCompany في User.php: " . ($hasHrHelper ? "موجودة" : "غير موجودة (التعديلات المعتمدة عليها هتتوقف)"));
hr();

// زرار Edit في صفحة الرواتب. مكتوب بـ nowdoc عشان الماركب فيه أقواس
// مفردة ومزدوجة و $employee، فالهروب اليدوي بيبوّظه.
$salaryBtnFrom = <<<'HTML'
                                            <i class="ti ti-eye text-white"></i>
                                        </a>
                                    </div>
HTML;

$salaryBtnTo = <<<'HTML'
                                            <i class="ti ti-eye text-white"></i>
                                        </a>
                                    </div>
                                    @can('edit set salary')
                                        <div class="action-btn me-2">
                                            <a href="{{route('setsalary.edit',$employee->id)}}" class="mx-3 btn btn-sm align-items-center bg-info" data-bs-toggle="tooltip" title="{{__('Edit')}}" data-original-title="{{__('Edit')}}">
                                                <i class="ti ti-pencil text-white"></i>
                                            </a>
                                        </div>
                                    @endcan
HTML;

// [ملف, نص قديم, نص جديد, العدد المتوقع, وصف, شرط, (اختياري) علامة "متطبّق"]
// العلامة بتتستخدم لما النص الجديد مش بيتطابق حرفيًا بعد التطبيق
// (مثلاً لو فيه كومنت اتضاف جواه) عشان ما يتكررش التعديل.
$patches = [

 // ---- (1) الـ 500 في صفحة الموظف: __() ملفوفة حوالين اسم عمود ----
 ['resources/views/employee/edit.blade.php',
  "__('document_id')", "'document_id'", 2,
  'إصلاح الـ 500 - اسم عمود متُرجَم', true],

 ['resources/views/employee/show.blade.php',
  "__('document_id')", "'document_id'", 1,
  'إصلاح الـ 500 - اسم عمود متُرجَم', true],

 // ---- (2) أسماء صلاحيات بحالة أحرف غلط (can بترجع false للكل) ----
 ['app/Http/Controllers/DashboardController.php',
  "can('View Task')", "can('view task')", 1,
  'حالة أحرف: view task', true],

 ['resources/views/clients/show.blade.php',
  "@can('View Estimation')", "@can('view estimation')", 2,
  'حالة أحرف: view estimation', true],
 ['resources/views/clients/show.blade.php',
  "@can('Edit Estimation')", "@can('edit estimation')", 1,
  'حالة أحرف: edit estimation', true],
 ['resources/views/clients/show.blade.php',
  "@can('Delete Estimation')", "@can('delete estimation')", 1,
  'حالة أحرف: delete estimation', true],

 ['resources/views/employee/profile.blade.php',
  "@can('Show Employee Profile')", "@can('show employee profile')", 1,
  'حالة أحرف: show employee profile', true],

 ['resources/views/estimations/index.blade.php',
  "@can('View Estimation')", "@can('view estimation')", 1,
  'حالة أحرف: view estimation', true],
 ['resources/views/estimations/show.blade.php',
  "@can('Edit Estimation')", "@can('edit estimation')", 1,
  'حالة أحرف: edit estimation', true],
 ['resources/views/estimations/show.blade.php',
  "@can('View Estimation')", "@can('view estimation')", 1,
  'حالة أحرف: view estimation', true],

 // القايمة بتطلب الاسم بحروف صغيرة والموجود فعلاً 'Manage Competencies'
 ['resources/views/partials/admin/menu.blade.php',
  "Gate::check('manage competencies')", "Gate::check('Manage Competencies')", 1,
  'حالة أحرف: Manage Competencies', true],

 // ---- (3) اسم الموظف بيتمسح عند تعديل الإجازة ----
 // ده آمن ومالوش شروط: بيعرض الموظف الحالي في الـ dropdown
 ['resources/views/leave/edit.blade.php',
  "{{ Form::select('employee_id', \$employees, null, ['class' => 'form-control select', 'placeholder' => __('Select Employee'), 'required' => 'required']) }}",
  "{{ Form::select('employee_id', \$employees, \$leave->employee_id, ['class' => 'form-control select', 'placeholder' => __('Select Employee'), 'required' => 'required']) }}",
  1, 'إظهار الموظف الحالي في dropdown الإجازة', true],

 // دول محتاجين isHrOrCompany موجودة على السيرفر
 ['resources/views/leave/edit.blade.php',
  "@if (\\Auth::user()->type == 'company' || \\Auth::user()->type == 'HR')",
  "@if (\\Auth::user()->isHrOrCompany())", 2,
  'توحيد شرط عرض حقول الإجازة مع الكونترولر', $hasHrHelper],

 ['resources/views/leave/edit.blade.php',
  "@if (\\Auth::user()->type != 'company' && \\Auth::user()->type != 'HR' && isset(\$employee_id))",
  "@if (!\\Auth::user()->isHrOrCompany() && isset(\$employee_id))", 1,
  'توحيد شرط الـ JS', $hasHrHelper],

 ['app/Http/Controllers/LeaveController.php',
  "                    if (\\Auth::user()->isHrOrCompany()) {\n                        \$leave->employee_id = \$request->employee_id;\n                    }",
  "                    if (\\Auth::user()->isHrOrCompany() && !empty(\$request->employee_id)) {\n                        \$leave->employee_id = \$request->employee_id;\n                    }",
  1, 'منع مسح employee_id لما الحقل مش مبعوت', $hasHrHelper],

 // ---- (6) قفل الوصول: كل واحد فيهم كان بياخد id من اللينك ويدوّر
 //      من غير ما يتأكد إن الموظف تابع لنفس الشركة. index() بيفلتر صح
 //      من الأصل، دول بقوا زيه. أخطرهم employeeUpdateSalary لأنه بيحفظ.
 // القفل والحماية في edit() بيتعملوا في تعديل واحد: لو اتقسموا،
 // كل واحد بيغيّر السطر اللي التاني بيدوّر عليه فالتاني بيتخطى.
 ['app/Http/Controllers/SetSalaryController.php',
  "                \$employee             = Employee::find(\$id);\n\n                return view('setsalary.edit',",
  "                // Scope to the caller's own company - an unscoped find() exposed\n                // other tenants' payroll to anyone holding 'edit set salary'.\n                \$employee             = Employee::where('created_by', \\Auth::user()->creatorId())->find(\$id);\n\n                if (empty(\$employee)) {\n                    return redirect()->route('setsalary.index')->with('error', __('Employee not found.'));\n                }\n\n                return view('setsalary.edit',",
  1, 'قفل وصول + حماية: setsalary edit()', true,
  "// Scope to the caller's own company"],

 ['app/Http/Controllers/SetSalaryController.php',
  "            \$employee             = Employee::with('salaryType')->find(\$id);",
  "            \$employee             = Employee::where('created_by', \\Auth::user()->creatorId())->with('salaryType')->find(\$id);\n\n            if (empty(\$employee)) {\n                return redirect()->route('setsalary.index')->with('error', __('Employee not found.'));\n            }",
  1, 'قفل وصول: setsalary show()', true],

 ['app/Http/Controllers/SetSalaryController.php',
  "        \$employee = Employee::findOrFail(\$id);\n        \$input    = \$request->all();",
  "        \$employee = Employee::where('created_by', \\Auth::user()->creatorId())->find(\$id);\n\n        if (empty(\$employee)) {\n            return redirect()->route('setsalary.index')->with('error', __('Employee not found.'));\n        }\n\n        \$input    = \$request->all();",
  1, 'قفل وصول: حفظ الراتب (الأخطر)', true],

 ['app/Http/Controllers/SetSalaryController.php',
  "        \$employee     = Employee::find(\$id);\n        return view('setsalary.basic_salary',",
  "        \$employee     = Employee::where('created_by', \\Auth::user()->creatorId())->find(\$id);\n\n        if (empty(\$employee)) {\n            return redirect()->route('setsalary.index')->with('error', __('Employee not found.'));\n        }\n\n        return view('setsalary.basic_salary',",
  1, 'قفل وصول: الراتب الأساسي', true],

 // ---- (5) زرار Edit في صفحة الرواتب ----
 ['resources/views/setsalary/index.blade.php',
  $salaryBtnFrom, $salaryBtnTo, 1,
  'إضافة زرار Edit في قائمة الرواتب', true,
  "route('setsalary.edit',\$employee->id)"],

 // ---- (4) حماية إضافية: موظف غير موجود كان بيدي 500 ----
 // (حماية setsalary edit() اتدمجت مع تعديل القفل فوق)

 ['app/Http/Controllers/EmployeeController.php',
  "            \$departmentData = Department::where('created_by', \\Auth::user()->creatorId())->where('branch_id', \$employee->branch_id)",
  "            if (empty(\$employee)) {\n                return redirect()->route('employee.index')->with('error', __('Employee Not Found.'));\n            }\n\n            \$departmentData = Department::where('created_by', \\Auth::user()->creatorId())->where('branch_id', \$employee->branch_id)",
  1, 'حماية من موظف غير موجود في edit()', true, "if (empty(\$employee)) {"],
];

$counts = ['apply' => 0, 'done' => 0, 'skip' => 0, 'block' => 0];
$touched = [];

foreach ($patches as $p) {
    list($rel, $from, $to, $expected, $desc, $cond) = $p;
    $marker = $p[6] ?? $to;
    $path   = "$root/$rel";
    $tag    = str_pad(basename($rel), 26);

    if (!$cond) {
        line("  [BLOCK] $tag $desc");
        line("          الشرط غير متوفر (isHrOrCompany مش موجودة) - اتخطى.");
        $counts['block']++; continue;
    }
    if (!is_file($path)) {
        line("  [SKIP]  $tag الملف مش موجود على السيرفر");
        $counts['skip']++; continue;
    }

    $s = file_get_contents($path);

    if (str_contains($s, $marker) && ($marker !== $to || !str_contains($s, $from))) {
        line("  [DONE]  $tag $desc - متطبّق قبل كده");
        $counts['done']++; continue;
    }

    $n = substr_count($s, $from);
    if ($n !== $expected) {
        line("  [SKIP]  $tag $desc");
        line("          متوقع {$expected}، لقيت {$n} - نسخة السيرفر مختلفة، مش هلمسه.");
        $counts['skip']++; continue;
    }

    if ($doFix) {
        if (!isset($touched[$path])) {
            copy($path, "$path.bak_$stamp");
            $touched[$path] = true;
        }
        file_put_contents($path, str_replace($from, $to, $s));
        line("  [FIX]   $tag $desc  (x$n)");
    } else {
        line("  [READY] $tag $desc  (x$n)");
    }
    $counts['apply']++;
}

line();
hr();
line(sprintf("  جاهز للتطبيق: %d   |   متطبّق قبل كده: %d   |   متخطّى: %d   |   متوقّف: %d",
    $counts['apply'], $counts['done'], $counts['skip'], $counts['block']));
hr();

if (!$doFix) {
    line();
    line("  ده فحص بس - مكتبتش أي حاجة. للتطبيق:  php apply-fixes.php --fix");
} elseif ($counts['apply'] > 0) {
    line();
    line("  اتاخدت نسخ احتياطية بامتداد .bak_$stamp");
    line("  للتراجع عن أي ملف:  mv <file>.bak_$stamp <file>");
    line();
    line("  خطوة أخيرة مهمة (تعديلات blade محتاجة مسح الـ views المترجمة):");
    line("      php artisan view:clear");
}
line();
