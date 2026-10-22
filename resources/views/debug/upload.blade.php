<!DOCTYPE html>
<html dir="rtl" lang="ar">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>تشخيص رفع الملفات - Laravel</title>
    <style>
        body { font-family: Arial, sans-serif; padding: 20px; background: #f5f5f5; }
        .container { max-width: 900px; margin: 0 auto; background: white; padding: 30px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        h1 { color: #333; border-bottom: 3px solid #007bff; padding-bottom: 15px; margin-bottom: 30px; }
        h2 { color: #555; margin-top: 30px; border-bottom: 2px solid #ddd; padding-bottom: 10px; }
        .info-box { background: #e7f3ff; border-right: 4px solid #007bff; padding: 15px; margin: 15px 0; border-radius: 4px; }
        .error-box { background: #ffe7e7; border-right: 4px solid #dc3545; padding: 15px; margin: 15px 0; border-radius: 4px; }
        .success-box { background: #e7ffe7; border-right: 4px solid #28a745; padding: 15px; margin: 15px 0; border-radius: 4px; }
        .warning-box { background: #fff3cd; border-right: 4px solid #ffc107; padding: 15px; margin: 15px 0; border-radius: 4px; }
        table { width: 100%; border-collapse: collapse; margin: 15px 0; }
        th, td { padding: 12px; text-align: right; border: 1px solid #ddd; }
        th { background: #007bff; color: white; font-weight: bold; }
        tr:nth-child(even) { background: #f9f9f9; }
        .code { background: #f4f4f4; padding: 15px; border-radius: 4px; font-family: 'Courier New', monospace; overflow-x: auto; margin: 10px 0; border: 1px solid #ddd; }
        pre { margin: 0; white-space: pre-wrap; word-wrap: break-word; }
        form { margin: 20px 0; padding: 20px; background: #f9f9f9; border-radius: 4px; }
        input[type="file"] { padding: 10px; border: 2px solid #ddd; border-radius: 4px; width: 100%; margin: 10px 0; background: white; }
        button { background: #007bff; color: white; padding: 12px 30px; border: none; border-radius: 4px; cursor: pointer; font-size: 16px; font-weight: bold; }
        button:hover { background: #0056b3; }
        .status-ok { color: #28a745; font-weight: bold; }
        .status-warning { color: #ffc107; font-weight: bold; }
        .status-error { color: #dc3545; font-weight: bold; }
    </style>
</head>
<body>
    <div class="container">
        <h1>🔍 تشخيص مشكلة رفع الملفات - Laravel Debug</h1>

        <h2>1️⃣ إعدادات PHP</h2>
        <table>
            <tr>
                <th>الإعداد</th>
                <th>القيمة</th>
                <th>الحالة</th>
            </tr>
            <tr>
                <td>file_uploads</td>
                <td>{{ $phpInfo['file_uploads'] ? 'مفعل' : 'معطل' }}</td>
                <td class="{{ $phpInfo['file_uploads'] ? 'status-ok' : 'status-error' }}">
                    {{ $phpInfo['file_uploads'] ? '✅' : '❌' }}
                </td>
            </tr>
            <tr>
                <td>upload_max_filesize</td>
                <td>{{ $phpInfo['upload_max_filesize'] }}</td>
                <td class="status-ok">✅</td>
            </tr>
            <tr>
                <td>post_max_size</td>
                <td>{{ $phpInfo['post_max_size'] }}</td>
                <td class="status-ok">✅</td>
            </tr>
            <tr>
                <td>memory_limit</td>
                <td>{{ $phpInfo['memory_limit'] }}</td>
                <td class="status-ok">✅</td>
            </tr>
            <tr>
                <td>max_execution_time</td>
                <td>{{ $phpInfo['max_execution_time'] }} ثانية</td>
                <td class="status-ok">✅</td>
            </tr>
            <tr>
                <td>PHP Version</td>
                <td>{{ $phpInfo['php_version'] }}</td>
                <td class="status-ok">✅</td>
            </tr>
        </table>

        @if(isset($result))
            <h2>2️⃣ نتائج اختبار رفع الملف</h2>
            
            <div class="info-box">
                <strong>معلومات الطلب:</strong><br>
                Request Method: {{ $result['request_method'] }}<br>
                Content-Type: {{ $result['content_type'] ?? 'N/A' }}<br>
                Content-Length: {{ $result['content_length'] ?? 'N/A' }}<br>
                Has File: {{ $result['has_file'] ? 'نعم ✅' : 'لا ❌' }}
            </div>

            @if($result['has_file'])
                <div class="success-box">
                    <h3>✅ تم استلام الملف بنجاح!</h3>
                    <strong>تفاصيل الملف:</strong><br>
                    الاسم: {{ $result['file_details']['original_name'] }}<br>
                    الحجم: {{ number_format($result['file_details']['size']) }} bytes<br>
                    النوع: {{ $result['file_details']['mime_type'] }}<br>
                    الامتداد: {{ $result['file_details']['extension'] }}<br>
                    صالح: {{ $result['file_details']['is_valid'] ? 'نعم ✅' : 'لا ❌' }}<br>
                    رمز الخطأ: {{ $result['file_details']['error'] }}<br>
                    رسالة الخطأ: {{ $result['file_details']['error_message'] }}
                </div>

                <div class="code">
                    <strong>Laravel Validation:</strong><br>
                    {{ $result['validation'] }}
                </div>
            @else
                <div class="error-box">
                    <h3>❌ لم يتم استلام الملف!</h3>
                    <strong>الخطأ:</strong> {{ $result['error'] ?? 'غير معروف' }}
                </div>
            @endif

            <h3>📋 تفاصيل كاملة للطلب</h3>
            <div class="code">
                <strong>All Files:</strong>
                <pre>{{ print_r($result['all_files'], true) }}</pre>
            </div>

            <div class="code">
                <strong>All Input:</strong>
                <pre>{{ print_r($result['all_input'], true) }}</pre>
            </div>
        @endif

        <h2>3️⃣ اختبار رفع ملف</h2>
        <form method="POST" action="{{ route('debug.upload.test') }}" enctype="multipart/form-data">
            @csrf
            <label for="file"><strong>اختر ملف CSV أو Excel:</strong></label>
            <input type="file" name="file" id="file" accept=".csv,.xlsx,.xls" required>
            <button type="submit">رفع واختبار الآن</button>
        </form>

        <h2>4️⃣ اختبار مباشر لـ Route الأصلي</h2>
        <div class="warning-box">
            <strong>لاختبار الـ route الأصلي مباشرة، استخدم هذا الكود في Console:</strong>
            <div class="code">
<pre>
var fileInput = document.getElementById('file');
var formData = new FormData();
formData.append('file', fileInput.files[0]);
formData.append('_token', '{{ csrf_token() }}');

fetch('{{ route('leads.file.import') }}', {
    method: 'POST',
    body: formData
})
.then(response => response.json())
.then(data => {
    console.log('Success:', data);
    alert(JSON.stringify(data, null, 2));
})
.catch(error => {
    console.error('Error:', error);
    alert('Error: ' + error);
});
</pre>
            </div>
        </div>

        <h2>5️⃣ معلومات Laravel</h2>
        <div class="code">
            <strong>Laravel Version:</strong> {{ app()->version() }}<br>
            <strong>Environment:</strong> {{ app()->environment() }}<br>
            <strong>Debug Mode:</strong> {{ config('app.debug') ? 'Enabled' : 'Disabled' }}<br>
            <strong>Base Path:</strong> {{ base_path() }}<br>
            <strong>Storage Path:</strong> {{ storage_path() }}
        </div>
    </div>
</body>
</html>
