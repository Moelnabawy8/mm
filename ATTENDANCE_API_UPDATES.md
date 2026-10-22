# 📋 Attendance API Updates Documentation

## Overview
تم تحديث `AttendanceApiController` لدعم:
1. **Multiple Check-ins/Check-outs** - إمكانية عمل check-in و check-out عدة مرات في نفس اليوم
2. **Department Projects Endpoint** - جلب المشاريع الخاصة بقسم معين

---

## 🔄 Changes Made

### 1. Multiple Check-ins/Check-outs Support

#### Modified `checkIn()` Method
**التغييرات:**
- تم تعديل الشرط للسماح بعمل check-in متعدد في نفس اليوم
- الآن يتحقق فقط من وجود check-in نشط (بدون check-out)
- إذا كان هناك check-in نشط، يطلب من الموظف عمل check-out أولاً

**الكود القديم:**
```php
$existingAttendance = AttendanceEmployee::where('employee_id', $employee->id)
    ->where('date', $date)
    ->first();

if ($existingAttendance) {
    return $this->error('You have already checked in today.', 400);
}
```

**الكود الجديد:**
```php
$existingAttendance = AttendanceEmployee::where('employee_id', $employee->id)
    ->where('date', $date)
    ->where('clock_out', '00:00:00')
    ->first();

if ($existingAttendance) {
    return $this->error('You have an active check-in. Please check out first.', 400);
}
```

#### Modified `checkOut()` Method
**التغييرات:**
- يبحث عن آخر check-in نشط (بدون check-out)
- يستخدم `orderBy('id', 'desc')` لجلب أحدث سجل

**الكود الجديد:**
```php
$attendance = AttendanceEmployee::where('employee_id', $employee->id)
    ->where('date', $date)
    ->where('clock_out', '00:00:00')
    ->orderBy('id', 'desc')
    ->first();
```

---

### 2. New Endpoints Added

#### A. `getDepartmentProjects()` - جلب مشاريع القسم

**Endpoint:** `GET /api/attendance/get-department-projects`

**Request Parameters:**
```json
{
  "department_id": "integer (required)"
}
```

**Response Success (200):**
```json
{
  "success": true,
  "data": {
    "department": {
      "id": 1,
      "name": "IT Department"
    },
    "projects": [
      {
        "id": 1,
        "project_name": "ERP System",
        "start_date": "2024-01-01",
        "end_date": "2024-12-31",
        "budget": 50000,
        "status": "in_progress",
        "description": "Enterprise Resource Planning System",
        "created_at": "2024-01-01 10:00:00"
      }
    ],
    "total_projects": 1
  },
  "message": "Department projects retrieved successfully."
}
```

**Response Error (404):**
```json
{
  "success": false,
  "error": "Department not found or access denied."
}
```

**كيف يعمل:**
1. يتحقق من وجود القسم وصلاحية الوصول
2. يجلب جميع الموظفين في هذا القسم
3. يحصل على user_ids الخاصة بهم
4. يجلب المشاريع المرتبطة بهؤلاء المستخدمين من جدول `project_users`
5. يعيد قائمة المشاريع مع معلومات القسم

---

#### B. `getMultipleAttendanceRecords()` - جلب سجلات الحضور المتعددة

**Endpoint:** `GET /api/attendance/multiple-records`

**Request Parameters:**
```json
{
  "date": "2024-01-15" // optional, defaults to today
}
```

**Response Success (200):**
```json
{
  "success": true,
  "data": {
    "date": "2024-01-15",
    "attendances": [
      {
        "id": 1,
        "employee_id": 5,
        "date": "2024-01-15",
        "clock_in": "09:00:00",
        "clock_out": "12:00:00",
        "late": "00:00:00",
        "early_leaving": "00:00:00",
        "overtime": "00:00:00",
        "status": "Present"
      },
      {
        "id": 2,
        "employee_id": 5,
        "date": "2024-01-15",
        "clock_in": "13:00:00",
        "clock_out": "18:00:00",
        "late": "00:00:00",
        "early_leaving": "00:00:00",
        "overtime": "00:00:00",
        "status": "Present"
      }
    ],
    "total_records": 2,
    "total_hours_worked": "08:00",
    "active_checkin": null
  },
  "message": "Multiple attendance records retrieved successfully."
}
```

**كيف يعمل:**
1. يجلب جميع سجلات الحضور للموظف في التاريخ المحدد
2. يحسب إجمالي ساعات العمل
3. يحدد إذا كان هناك check-in نشط (بدون check-out)
4. يرتب السجلات حسب وقت الدخول

---

## 🔌 API Routes to Add

أضف هذه الـ routes في ملف `routes/api.php`:

```php
// Multiple Check-ins/Check-outs
Route::middleware('auth:sanctum')->group(function () {
    // Existing routes...
    Route::post('/attendance/check-in', [AttendanceApiController::class, 'checkIn']);
    Route::post('/attendance/check-out', [AttendanceApiController::class, 'checkOut']);
    
    // New routes
    Route::get('/attendance/get-department-projects', [AttendanceApiController::class, 'getDepartmentProjects']);
    Route::get('/attendance/multiple-records', [AttendanceApiController::class, 'getMultipleAttendanceRecords']);
});
```

---

## 📊 Use Cases

### Use Case 1: موظف يعمل نوبتين في نفس اليوم

**السيناريو:**
موظف يعمل من 9 صباحاً - 12 ظهراً، ثم يعود من 2 ظهراً - 6 مساءً

**الخطوات:**
1. **First Check-in (9:00 AM)**
   ```bash
   POST /api/attendance/check-in
   Response: Success - Check-in recorded
   ```

2. **First Check-out (12:00 PM)**
   ```bash
   POST /api/attendance/check-out
   Response: Success - Check-out recorded
   ```

3. **Second Check-in (2:00 PM)**
   ```bash
   POST /api/attendance/check-in
   Response: Success - New check-in recorded
   ```

4. **Second Check-out (6:00 PM)**
   ```bash
   POST /api/attendance/check-out
   Response: Success - Check-out recorded
   ```

5. **View All Records**
   ```bash
   GET /api/attendance/multiple-records?date=2024-01-15
   Response: Shows 2 attendance records, total hours: 08:00
   ```

---

### Use Case 2: مدير يريد رؤية مشاريع قسم معين

**السيناريو:**
مدير يريد معرفة جميع المشاريع التي يعمل عليها قسم IT

**الخطوات:**
```bash
GET /api/attendance/get-department-projects?department_id=1

Response:
{
  "success": true,
  "data": {
    "department": {
      "id": 1,
      "name": "IT Department"
    },
    "projects": [
      {
        "id": 1,
        "project_name": "ERP System",
        "status": "in_progress",
        ...
      },
      {
        "id": 2,
        "project_name": "Mobile App",
        "status": "on_hold",
        ...
      }
    ],
    "total_projects": 2
  }
}
```

---

## ⚠️ Important Notes

### Multiple Check-ins/Check-outs
1. **لا يمكن عمل check-in جديد إذا كان هناك check-in نشط**
   - يجب عمل check-out أولاً
   
2. **يتم حساب Late و Overtime لكل سجل بشكل منفصل**
   - كل check-in يحسب التأخير بناءً على وقت بداية العمل
   - كل check-out يحسب الوقت الإضافي

3. **إجمالي ساعات العمل**
   - يتم حسابه من مجموع جميع الفترات المسجلة في اليوم

### Department Projects
1. **الصلاحيات**
   - يجب أن يكون القسم تابع لنفس الـ creator
   - يتم جلب المشاريع المرتبطة بموظفي القسم فقط

2. **الأداء**
   - يستخدم JOIN للحصول على البيانات بكفاءة
   - يستخدم `distinct()` لتجنب التكرار

---

## 🧪 Testing

### Test Multiple Check-ins

```bash
# Test 1: First check-in
curl -X POST http://localhost/api/attendance/check-in \
  -H "Authorization: Bearer YOUR_TOKEN"

# Test 2: Try check-in again (should fail)
curl -X POST http://localhost/api/attendance/check-in \
  -H "Authorization: Bearer YOUR_TOKEN"
# Expected: Error - "You have an active check-in"

# Test 3: Check-out
curl -X POST http://localhost/api/attendance/check-out \
  -H "Authorization: Bearer YOUR_TOKEN"

# Test 4: Second check-in (should succeed)
curl -X POST http://localhost/api/attendance/check-in \
  -H "Authorization: Bearer YOUR_TOKEN"
```

### Test Department Projects

```bash
# Test: Get department projects
curl -X GET "http://localhost/api/attendance/get-department-projects?department_id=1" \
  -H "Authorization: Bearer YOUR_TOKEN"
```

---

## 📈 Database Impact

### No Schema Changes Required
التعديلات لا تحتاج إلى تغيير في قاعدة البيانات، فقط تستخدم الجداول الموجودة:
- `attendance_employees`
- `employees`
- `departments`
- `projects`
- `project_users`

### Indexes Recommendation
للأداء الأفضل، تأكد من وجود indexes على:
```sql
-- For faster attendance queries
CREATE INDEX idx_attendance_employee_date ON attendance_employees(employee_id, date, clock_out);

-- For department projects
CREATE INDEX idx_employee_department ON employees(department_id);
CREATE INDEX idx_project_users ON project_users(user_id, project_id);
```

---

## 🔐 Security Considerations

1. **Authentication Required**
   - جميع الـ endpoints تحتاج authentication
   
2. **Authorization Checks**
   - التحقق من أن المستخدم يملك صلاحية الوصول للبيانات
   - استخدام `creatorId()` للتأكد من عدم الوصول لبيانات شركات أخرى

3. **Input Validation**
   - استخدام Laravel Validator لجميع المدخلات
   - التحقق من صحة التواريخ والـ IDs

---

## 📝 Summary

### ✅ What's New
- ✅ دعم multiple check-ins/check-outs في نفس اليوم
- ✅ endpoint لجلب مشاريع القسم
- ✅ endpoint لجلب جميع سجلات الحضور في يوم معين
- ✅ حساب إجمالي ساعات العمل

### 🎯 Benefits
- مرونة أكبر في تسجيل الحضور
- تتبع أفضل للموظفين الذين يعملون نوبات متعددة
- سهولة ربط الأقسام بالمشاريع
- تقارير أكثر دقة

---

**Version:** 1.0.0  
**Last Updated:** February 2024  
**Author:** Development Team
