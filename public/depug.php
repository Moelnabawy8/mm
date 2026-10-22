<?php

/**
 * Debug Upload Script
 * Upload this file to your server and access it via browser
 * URL: https://erp.my-communication.uk/debug_upload.php
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

header('Content-Type: text/html; charset=utf-8');

// Helper functions
function convertToBytes($value)
{
    $value = trim($value);
    if (empty($value)) return 0;
    $last = strtolower($value[strlen($value) - 1]);
    $value = (int)$value;

    switch ($last) {
        case 'g':
            $value *= 1024;
        case 'm':
            $value *= 1024;
        case 'k':
            $value *= 1024;
    }

    return $value;
}

function getUploadErrorMessage($code)
{
    $errors = [
        UPLOAD_ERR_OK => 'لا يوجد خطأ، تم رفع الملف بنجاح',
        UPLOAD_ERR_INI_SIZE => 'الملف أكبر من upload_max_filesize في php.ini',
        UPLOAD_ERR_FORM_SIZE => 'الملف أكبر من MAX_FILE_SIZE في HTML form',
        UPLOAD_ERR_PARTIAL => 'تم رفع الملف جزئياً فقط',
        UPLOAD_ERR_NO_FILE => 'لم يتم رفع أي ملف',
        UPLOAD_ERR_NO_TMP_DIR => 'مجلد tmp مفقود',
        UPLOAD_ERR_CANT_WRITE => 'فشل كتابة الملف على القرص',
        UPLOAD_ERR_EXTENSION => 'امتداد PHP أوقف رفع الملف',
    ];

    return $errors[$code] ?? 'خطأ غير معروف: ' . $code;
}
?>
<!DOCTYPE html>
<html dir="rtl" lang="ar">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>تشخيص رفع الملفات</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            padding: 20px;
            background: #f5f5f5;
        }

        .container {
            max-width: 800px;
            margin: 0 auto;
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }

        h1 {
            color: #333;
            border-bottom: 2px solid #007bff;
            padding-bottom: 10px;
        }

        .info-box {
            background: #e7f3ff;
            border-left: 4px solid #007bff;
            padding: 15px;
            margin: 15px 0;
        }

        .error-box {
            background: #ffe7e7;
            border-left: 4px solid #dc3545;
            padding: 15px;
            margin: 15px 0;
        }

        .success-box {
            background: #e7ffe7;
            border-left: 4px solid #28a745;
            padding: 15px;
            margin: 15px 0;
        }

        .warning-box {
            background: #fff3cd;
            border-left: 4px solid #ffc107;
            padding: 15px;
            margin: 15px 0;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin: 15px 0;
        }

        th,
        td {
            padding: 10px;
            text-align: right;
            border: 1px solid #ddd;
        }

        th {
            background: #007bff;
            color: white;
        }

        .code {
            background: #f4f4f4;
            padding: 10px;
            border-radius: 4px;
            font-family: monospace;
            overflow-x: auto;
        }

        form {
            margin: 20px 0;
        }

        input[type="file"] {
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
            width: 100%;
            margin: 10px 0;
        }

        button {
            background: #007bff;
            color: white;
            padding: 10px 20px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
        }

        button:hover {
            background: #0056b3;
        }
    </style>
</head>

<body>
    <div class="container">
        <h1>🔍 تشخيص مشكلة رفع الملفات</h1>

        <?php
        // 1. Check PHP Configuration
        echo '<h2>1️⃣ إعدادات PHP</h2>';
        echo '<table>';
        echo '<tr><th>الإعداد</th><th>القيمة</th><th>الحالة</th></tr>';

        $upload_max = ini_get('upload_max_filesize');
        $post_max = ini_get('post_max_size');
        $memory_limit = ini_get('memory_limit');
        $max_execution = ini_get('max_execution_time');
        $file_uploads = ini_get('file_uploads');

        echo '<tr><td>file_uploads</td><td>' . ($file_uploads ? 'مفعل' : 'معطل') . '</td><td>' . ($file_uploads ? '✅' : '❌') . '</td></tr>';
        echo '<tr><td>upload_max_filesize</td><td>' . $upload_max . '</td><td>' . (convertToBytes($upload_max) >= 10485760 ? '✅' : '⚠️') . '</td></tr>';
        echo '<tr><td>post_max_size</td><td>' . $post_max . '</td><td>' . (convertToBytes($post_max) >= 10485760 ? '✅' : '⚠️') . '</td></tr>';
        echo '<tr><td>memory_limit</td><td>' . $memory_limit . '</td><td>' . (convertToBytes($memory_limit) >= 134217728 ? '✅' : '⚠️') . '</td></tr>';
        echo '<tr><td>max_execution_time</td><td>' . $max_execution . ' ثانية</td><td>' . ($max_execution >= 60 ? '✅' : '⚠️') . '</td></tr>';
        echo '</table>';

        // 2. Check if form was submitted
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            echo '<h2>2️⃣ نتائج الاختبار</h2>';

            echo '<div class="info-box">';
            echo '<strong>معلومات الطلب:</strong><br>';
            echo 'Content-Type: ' . ($_SERVER['CONTENT_TYPE'] ?? 'غير محدد') . '<br>';
            echo 'Content-Length: ' . ($_SERVER['CONTENT_LENGTH'] ?? 'غير محدد') . ' bytes<br>';
            echo '</div>';

            // Check $_FILES
            echo '<div class="code">';
            echo '<strong>$_FILES:</strong><pre>';
            print_r($_FILES);
            echo '</pre></div>';

            // Check $_POST
            echo '<div class="code">';
            echo '<strong>$_POST:</strong><pre>';
            print_r($_POST);
            echo '</pre></div>';

            // Check for file
            if (isset($_FILES['test_file'])) {
                $file = $_FILES['test_file'];

                if ($file['error'] === UPLOAD_ERR_OK) {
                    echo '<div class="success-box">';
                    echo '<strong>✅ تم رفع الملف بنجاح!</strong><br>';
                    echo 'اسم الملف: ' . htmlspecialchars($file['name']) . '<br>';
                    echo 'الحجم: ' . number_format($file['size']) . ' bytes<br>';
                    echo 'النوع: ' . htmlspecialchars($file['type']) . '<br>';
                    echo 'المسار المؤقت: ' . htmlspecialchars($file['tmp_name']) . '<br>';
                    echo '</div>';
                } else {
                    echo '<div class="error-box">';
                    echo '<strong>❌ خطأ في رفع الملف!</strong><br>';
                    echo 'رمز الخطأ: ' . $file['error'] . '<br>';
                    echo 'الرسالة: ' . getUploadErrorMessage($file['error']) . '<br>';
                    echo '</div>';
                }
            } else {
                echo '<div class="error-box">';
                echo '<strong>❌ لم يتم العثور على الملف في $_FILES</strong><br>';
                echo 'هذا يعني أن الملف لم يصل إلى السيرفر أو تم رفضه قبل معالجته.<br>';
                echo '</div>';
            }

            // Check Laravel validation
            echo '<h3>اختبار Laravel Validation</h3>';
            echo '<div class="code">';
            echo '<strong>محاكاة Laravel Validator:</strong><br>';
            if (isset($_FILES['test_file']) && $_FILES['test_file']['error'] === UPLOAD_ERR_OK) {
                $file = $_FILES['test_file'];
                $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
                $allowed = ['csv', 'txt', 'xlsx', 'xls'];

                if (in_array($ext, $allowed)) {
                    echo '✅ نوع الملف مقبول: ' . $ext . '<br>';
                } else {
                    echo '❌ نوع الملف غير مقبول: ' . $ext . '<br>';
                }
            } else {
                echo '❌ الملف غير موجود أو به خطأ<br>';
            }
            echo '</div>';
        } else {
            echo '<h2>2️⃣ اختبار رفع ملف</h2>';
            echo '<div class="info-box">';
            echo 'قم برفع ملف CSV أو Excel لاختبار عملية الرفع:';
            echo '</div>';
        }
        ?>

        <form method="POST" enctype="multipart/form-data">
            <label for="test_file"><strong>اختر ملف للاختبار:</strong></label>
            <input type="file" name="test_file" id="test_file" accept=".csv,.xlsx,.xls" required>
            <button type="submit">رفع واختبار</button>
        </form>

        <h2>3️⃣ اختبار Laravel Route</h2>
        <div class="info-box">
            <strong>اختبار Route باستخدام cURL:</strong>
            <div class="code">
                curl -X POST https://erp.my-communication.uk/leads/import \
                -H "X-CSRF-TOKEN: YOUR_TOKEN_HERE" \
                -F "file=@/path/to/your/file.csv" \
                -v
            </div>
        </div>

        <h2>4️⃣ التوصيات</h2>
        <div class="warning-box">
            <?php
            $recommendations = [];

            if (!$file_uploads) {
                $recommendations[] = '❌ تفعيل file_uploads في php.ini';
            }
            if (convertToBytes($upload_max) < 10485760) {
                $recommendations[] = '⚠️ زيادة upload_max_filesize إلى 10M على الأقل';
            }
            if (convertToBytes($post_max) < 10485760) {
                $recommendations[] = '⚠️ زيادة post_max_size إلى 10M على الأقل';
            }
            if (convertToBytes($memory_limit) < 134217728) {
                $recommendations[] = '⚠️ زيادة memory_limit إلى 128M على الأقل';
            }

            if (empty($recommendations)) {
                echo '✅ جميع الإعدادات جيدة!';
            } else {
                echo '<strong>يُنصح بالتالي:</strong><ul>';
                foreach ($recommendations as $rec) {
                    echo '<li>' . $rec . '</li>';
                }
                echo '</ul>';
            }
            ?>
        </div>

        <h2>5️⃣ معلومات إضافية</h2>
        <div class="code">
            <strong>PHP Version:</strong> <?php echo phpversion(); ?><br>
            <strong>Server Software:</strong> <?php echo $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown'; ?><br>
            <strong>Document Root:</strong> <?php echo $_SERVER['DOCUMENT_ROOT'] ?? 'Unknown'; ?><br>
            <strong>Script Filename:</strong> <?php echo __FILE__; ?><br>
        </div>
    </div>
</body>

</html>