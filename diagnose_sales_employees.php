<?php
/**
 * =====================================================================
 * SALES EMPLOYEE DIAGNOSTIC & FIX SCRIPT  (v2 - Updated after analysis)
 * =====================================================================
 * المشكلة المكتشفة:
 *   الـ 8 موظفين لديهم department_id = NULL في جدول employees.
 *   LeadController يستخدم INNER JOIN مع departments ← يحذفهم.
 *
 * تم إصلاح LeadController.php تلقائياً.
 * هذا السكريبت يوفر فقط خيار ضبط الـ department للموظفين.
 * =====================================================================
 */

define('LARAVEL_START', microtime(true));
require __DIR__ . '/vendor/autoload.php';
$app  = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);
$kernel->handle(Illuminate\Http\Request::capture());

use Illuminate\Support\Facades\DB;

$targetNames = [
    'Asmaa Jaber', 'Feras Qatrawi', 'Bushra Kadour', 'Jihad Alhaj',
    'Farah Muthanna', 'Naseem Ahmed', 'Hadeel Ramez', 'Yamen Nasser',
];

$fixDept       = isset($_POST['fix_dept'])   && $_POST['fix_dept']   === '1';
$selectedDept  = isset($_POST['dept_id'])    ? (int)$_POST['dept_id'] : 0;

echo '<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
<meta charset="UTF-8">
<title>Sales Employee Fix - v2</title>
<style>
  *{box-sizing:border-box;margin:0;padding:0}
  body{font-family:"Segoe UI",sans-serif;background:#0f172a;color:#e2e8f0;padding:28px;direction:rtl}
  h1{color:#38bdf8;font-size:1.5rem;margin-bottom:20px;border-bottom:2px solid #334155;padding-bottom:10px}
  h2{color:#94a3b8;font-size:1rem;margin:18px 0 10px}
  .card{background:#1e293b;border-radius:10px;padding:20px;margin-bottom:18px;border:1px solid #334155}
  .badge{display:inline-block;padding:3px 10px;border-radius:20px;font-size:.78rem;font-weight:600}
  .ok{background:#166534;color:#bbf7d0}.warn{background:#854d0e;color:#fef08a}
  .err{background:#991b1b;color:#fecaca}.info{background:#1e3a5f;color:#bae6fd}
  table{width:100%;border-collapse:collapse;font-size:.85rem}
  th{background:#334155;padding:8px 12px;text-align:right;color:#94a3b8}
  td{padding:8px 12px;border-bottom:1px solid #0f172a}
  tr:hover td{background:#273449}
  .log{background:#0f172a;border-radius:6px;padding:12px;font-family:monospace;font-size:.82rem;
       margin-top:8px;border:1px solid #334155;line-height:1.8}
  .log .ok2{color:#4ade80}.log .err2{color:#f87171}.log .inf{color:#60a5fa}
  .btn{display:inline-block;background:#2563eb;color:#fff;border:none;padding:10px 24px;
       border-radius:8px;cursor:pointer;font-size:.92rem;margin-top:10px}
  .btn:hover{background:#1d4ed8}
  .alert-fix{background:#14532d;border:1px solid #166534;border-radius:8px;padding:14px;margin-top:12px}
  .alert-warn{background:#451a03;border:1px solid #92400e;border-radius:8px;padding:14px;margin-top:12px}
  select,input{background:#0f172a;border:1px solid #334155;color:#e2e8f0;border-radius:6px;
               padding:8px 12px;font-size:.9rem;margin-top:4px}
  label{color:#94a3b8;font-size:.88rem}
  code{background:#0f172a;color:#f472b6;padding:2px 6px;border-radius:4px}
</style>
</head>
<body>
<h1>🔧 Sales Employee Fix Tool — v2</h1>';

// ── Load users ──────────────────────────────────────────────────────
$foundUsers = [];
foreach ($targetNames as $name) {
    $user = DB::table('users')->where('name', 'like', '%' . $name . '%')->first();
    if ($user) $foundUsers[] = $user;
}

// ══ SECTION 1: WHAT WAS FIXED ════════════════════════════════════════
echo '<div class="card">
<h2>✅ ما تم إصلاحه تلقائياً في الكود</h2>
<div class="alert-fix" style="margin-top:8px">
  <strong>🛠 LeadController.php — تم التعديل:</strong>
  <div class="log" style="margin-top:10px">
    <span class="inf">// السطر 104 — قائمة المستخدمين في صفحة الـ Leads:</span><br>
    <span class="err2">- ->join("departments", "employees.department_id", "=", "departments.id")</span><br>
    <span class="ok2">+ ->leftJoin("departments", "employees.department_id", "=", "departments.id")</span><br><br>
    <span class="inf">// السطر 890 — قائمة الـ Sales في تعديل الـ Lead:</span><br>
    <span class="err2">- ->join("departments", ...) ->where("departments.name", "LIKE", "%Sales%")</span><br>
    <span class="ok2">+ ->leftJoin("departments", ...) ->where(dept LIKE Sales <strong>OR</strong> user.type LIKE Sales <strong>OR</strong> role LIKE Sales)</span>
  </div>
</div>
<p style="color:#94a3b8;margin-top:12px;font-size:.88rem">
  <strong>السبب الجذري:</strong> الـ 8 موظفين لديهم <code>department_id = NULL</code> في جدول employees.
  الـ <strong>INNER JOIN</strong> مع departments كان يُخرجهم من النتائج. تغيّر إلى <strong>LEFT JOIN</strong>
  فأصبح يُبقيهم حتى لو لم يكن لديهم department.
</p>
</div>';

// ══ SECTION 2: Current State ══════════════════════════════════════════
echo '<div class="card">
<h2>📊 الحالة الحالية للموظفين</h2>
<table>
<thead><tr>
<th>الاسم</th><th>User ID</th><th>Employee Record ID</th>
<th>department_id</th><th>اسم الـ Dept</th>
<th>Sales Role؟</th><th>ستظهر الآن؟</th>
</tr></thead><tbody>';

$noDeptUsers = [];
foreach ($foundUsers as $user) {
    $emp = DB::table('employees')->where('user_id', $user->id)->first();
    if (!$emp) { continue; }

    $deptName = '—';
    if ($emp->department_id) {
        $dept = DB::table('departments')->find($emp->department_id);
        $deptName = $dept ? $dept->name : '<span style="color:#f87171">ID موجود لكن record محذوف!</span>';
    }

    $roles = DB::table('model_has_roles')
        ->join('roles', 'model_has_roles.role_id', '=', 'roles.id')
        ->where('model_has_roles.model_id', $user->id)
        ->pluck('roles.name')->toArray();
    $hasSalesRole = !empty(array_filter($roles, fn($r) => stripos($r, 'sales') !== false));

    // After fix: will show if has employee record + (has dept OR type LIKE sales OR role LIKE sales)
    $willShow = $emp && ($emp->department_id || stripos($user->type, 'sales') !== false || $hasSalesRole);

    $deptBadge = $emp->department_id
        ? '<span class="badge ok">' . $emp->department_id . '</span>'
        : '<span class="badge warn">NULL</span>';
    $roleBadge = $hasSalesRole
        ? '<span class="badge ok">نعم ✓</span>'
        : '<span class="badge err">لا ✗</span>';
    $showBadge = $willShow
        ? '<span class="badge ok">✅ نعم</span>'
        : '<span class="badge err">❌ لا</span>';

    if (!$emp->department_id) $noDeptUsers[] = $user;

    echo "<tr>
<td>{$user->name}</td>
<td>{$user->id}</td>
<td>{$emp->id}</td>
<td>{$deptBadge}</td>
<td>{$deptName}</td>
<td>{$roleBadge}</td>
<td>{$showBadge}</td>
</tr>";
}
echo '</tbody></table></div>';

// ══ SECTION 3: Assign Department (Optional) ════════════════════════
$departments = DB::table('departments')
    ->where('created_by', 2) // CompanyID=2 from diagnostic
    ->get();

echo '<div class="card">
<h2>🗂 تعيين Department للموظفين (اختياري لكن مُستحسن)</h2>
<p style="color:#94a3b8;margin-bottom:14px;font-size:.88rem">
  بعد تعديل الكود، الموظفون <strong>سيظهرون</strong> حتى بدون department.
  لكن يُستحسن تعيين department صحيح لهم لحسن التنظيم.
</p>';

if (!empty($noDeptUsers)) {
    if (!$fixDept) {
        echo '<p style="color:#fbbf24;margin-bottom:12px">الموظفون التالون ليس لديهم department — اختر department وعيّنه لهم:</p>';
        echo '<form method="POST">
        <input type="hidden" name="fix_dept" value="1">
        <label>اختر الـ Department:</label><br>
        <select name="dept_id" style="margin:8px 0 14px">';
        foreach ($departments as $d) {
            echo "<option value=\"{$d->id}\">{$d->name}</option>";
        }
        echo '</select><br>
        <p style="color:#94a3b8;font-size:.82rem;margin-bottom:8px">سيتم تعيين هذا الـ department لـ:</p>
        <ul style="color:#cbd5e1;margin:0 20px 14px;line-height:2">';
        foreach ($noDeptUsers as $u) {
            echo "<li>{$u->name}</li>";
        }
        echo '</ul>
        <button type="submit" class="btn">تعيين الـ Department</button>
        </form>';
    } else {
        // Do the fix
        echo '<div class="log">';
        foreach ($noDeptUsers as $user) {
            $emp = DB::table('employees')->where('user_id', $user->id)->first();
            if ($emp && $selectedDept > 0) {
                DB::table('employees')
                    ->where('user_id', $user->id)
                    ->update(['department_id' => $selectedDept, 'updated_at' => now()]);
                $deptName = DB::table('departments')->find($selectedDept)->name ?? '?';
                echo "<span class='ok2'>✅ {$user->name} → department: {$deptName} (ID: {$selectedDept})</span><br>";
            }
        }
        echo '</div>';
        echo '<div class="alert-fix" style="margin-top:12px">
        <strong>✅ تم تعيين الـ Department بنجاح!</strong>
        </div>';
    }
} else {
    echo '<div class="alert-fix">
    <strong>✅ جميع الموظفين لديهم department مُعيّن مسبقاً.</strong>
    </div>';
}

echo '</div>';

// ══ SECTION 4: Summary ════════════════════════════════════════════════
echo '<div class="card">
<h2>📋 ملخص خطوات الإصلاح الكاملة</h2>
<ol style="color:#cbd5e1;line-height:2.4;margin:0 20px">
  <li>
    <strong style="color:#4ade80">✅ تم (تلقائياً)</strong>
    تعديل <code>LeadController.php</code>:
    تغيير الـ INNER JOIN إلى LEFT JOIN ← الموظفون بدون department يظهرون الآن
  </li>
  <li>
    <strong style="color:#4ade80">✅ تم (تلقائياً)</strong>
    تعديل <code>userEdit()</code> في LeadController:
    الفلتر أصبح يشمل الـ role والـ type وليس الـ department فقط
  </li>
  <li>
    <strong style="color:#fbbf24">⚠️ اختياري</strong>
    تعيين department للموظفين (القسم أعلاه)
  </li>
  <li>
    <strong style="color:#fbbf24">⚠️ ضروري إذا كان الـ Cache مُفعّل</strong>
    تشغيل: <code>php artisan cache:clear</code> و <code>php artisan config:clear</code>
  </li>
</ol>
<div class="alert-warn" style="margin-top:16px">
  <strong>⚠️ بعد الانتهاء: احذف هذا الملف من السيرفر لأسباب أمنية</strong><br>
  <code>diagnose_sales_employees.php</code>
</div>
</div>
</body></html>';
