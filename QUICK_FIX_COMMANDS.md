# 🚀 حل سريع لمشكلة "Employee record not found"

## ⚡ الحل السريع - استخدم أحد الخيارات التالية:

---

## 🔧 الخيار 1: تشغيل Script تلقائي (الأسهل)

```bash
php create_employee_records.php
```

**هذا الـ Script سيقوم بـ:**
- ✅ البحث عن جميع Users بدون Employee records
- ✅ إنشاء Branch/Department/Designation افتراضية إذا لم تكن موجودة
- ✅ إنشاء employee records تلقائياً لجميع المستخدمين
- ✅ تعيين employee_id تلقائياً

---

## 🎯 الخيار 2: Tinker - لمستخدم واحد محدد

```bash
php artisan tinker
```

### **نسخ والصق هذا الكود:**

```php
// 1. ابحث عن المستخدم بالإيميل
$user = App\Models\User::where('email', 'your-email@example.com')->first();

// 2. تحقق من وجود User
if (!$user) {
    echo "User not found!\n";
    exit;
}

// 3. تحقق إذا كان له employee record
$existingEmployee = App\Models\Employee::where('user_id', $user->id)->first();
if ($existingEmployee) {
    echo "Employee already exists! Employee ID: {$existingEmployee->employee_id}\n";
    exit;
}

// 4. احصل على أو أنشئ البيانات المطلوبة
$branch = App\Models\Branch::first() ?? App\Models\Branch::create(['name' => 'Main Branch', 'created_by' => 1]);
$department = App\Models\Department::first() ?? App\Models\Department::create(['name' => 'General', 'branch_id' => $branch->id, 'created_by' => 1]);
$designation = App\Models\Designation::first() ?? App\Models\Designation::create(['name' => 'Employee', 'branch_id' => $branch->id, 'department_id' => $department->id, 'created_by' => 1]);

// 5. احصل على أعلى employee_id
$maxId = App\Models\Employee::max('employee_id');
$nextId = $maxId ? (is_numeric($maxId) ? $maxId + 1 : 1) : 1;

// 6. أنشئ employee record
$employee = App\Models\Employee::create([
    'user_id' => $user->id,
    'name' => $user->name,
    'email' => $user->email,
    'phone' => $user->phone ?? '',
    'address' => $user->address ?? '',
    'employee_id' => $nextId,
    'branch_id' => $branch->id,
    'department_id' => $department->id,
    'designation_id' => $designation->id,
    'company_doj' => now()->format('Y-m-d'),
    'created_by' => $user->created_by ?? 1,
]);

echo "✅ Employee created successfully!\n";
echo "Employee ID: {$employee->employee_id}\n";
echo "Name: {$employee->name}\n";
echo "Email: {$employee->email}\n";
```

**غير فقط:** `your-email@example.com` بالإيميل الصحيح

---

## 🔍 الخيار 3: التحقق من المستخدمين بدون Employee Records

```bash
php artisan tinker
```

```php
// عرض جميع Users بدون Employee records
$users = App\Models\User::leftJoin('employees', 'users.id', '=', 'employees.user_id')
    ->whereNull('employees.id')
    ->where('users.type', 'employee')
    ->select('users.*')
    ->get();

echo "Users without employee records:\n";
foreach ($users as $user) {
    echo "- ID: {$user->id}, Name: {$user->name}, Email: {$user->email}\n";
}
echo "\nTotal: " . $users->count() . " users\n";
```

---

## 📋 الخيار 4: إنشاء Employee عبر API

```bash
POST {{base_url}}/api/v1/employees
Authorization: Bearer {{admin_token}}
Content-Type: application/json

{
    "name": "Ahmed Mohamed",
    "email": "ahmed@example.com",
    "password": "password123",
    "phone": "01234567890",
    "address": "Cairo, Egypt",
    "dob": "1990-01-01",
    "gender": "male",
    "branch_id": 1,
    "department_id": 1,
    "designation_id": 1,
    "company_doj": "2024-01-15"
}
```

**ملاحظة:** يجب أن يكون الـ email مطابق للـ user الموجود

---

## ✅ التحقق من النجاح

بعد تنفيذ أي من الحلول أعلاه، جرب:

```bash
# 1. Login
POST {{base_url}}/api/v1/login
{
    "email": "your-email@example.com",
    "password": "your-password"
}

# 2. Get Profile (يجب أن يعرض employee_id)
GET {{base_url}}/api/v1/profile
Authorization: Bearer {{token}}

# 3. Check-in (يجب أن يعمل)
POST {{base_url}}/api/v1/attendance/check-in
Authorization: Bearer {{token}}
```

---

## 🎯 أسرع طريقة (خطوة واحدة)

```bash
# في Terminal
php create_employee_records.php
```

**هذا كل شيء! سيتم إنشاء employee records لجميع المستخدمين تلقائياً** ✅

---

## 📝 ملاحظات مهمة

1. **Branch/Department/Designation:** إذا لم تكن موجودة، سيتم إنشاؤها تلقائياً
2. **Employee ID:** سيتم تعيينه تلقائياً (1, 2, 3, ...)
3. **Company DOJ:** سيتم تعيينه لتاريخ اليوم
4. **Created By:** سيتم استخدام created_by من User أو 1 كقيمة افتراضية

---

## 🆘 إذا واجهت مشاكل

### **خطأ: "Class 'App\Models\Branch' not found"**
```bash
composer dump-autoload
php artisan config:clear
php artisan cache:clear
```

### **خطأ: "SQLSTATE[23000]: Integrity constraint violation"**
تأكد من:
- Branch ID موجود في جدول branches
- Department ID موجود في جدول departments
- Designation ID موجود في جدول designations

### **خطأ: "employee_id must be unique"**
الـ Script يتعامل مع هذا تلقائياً، لكن إذا حدث:
```php
// في Tinker
$maxId = App\Models\Employee::max('employee_id');
echo "Next available ID: " . ($maxId + 1) . "\n";
```

---

## 🎉 النتيجة المتوقعة

```json
{
    "is_success": true,
    "message": "Profile retrieved successfully.",
    "data": {
        "profile": {
            "id": 1,
            "name": "Ahmed Mohamed",
            "email": "ahmed@example.com",
            "employee_id": 1,
            "department": {
                "name": "General"
            },
            "designation": {
                "name": "Employee"
            },
            "branch": {
                "name": "Main Branch"
            },
            "join_date": "2024-01-31",
            "salary": null
        }
    }
}
```

**الآن جميع APIs ستعمل بشكل صحيح! 🚀**
