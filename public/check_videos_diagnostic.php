<?php
/**
 * Video Diagnostic Script
 * Upload this file to your server's public directory and access it via browser
 * URL: http://yourdomain.com/check_videos_diagnostic.php
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>تشخيص مشاكل الفيديوهات</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            padding: 20px;
            direction: rtl;
        }
        .container {
            max-width: 1200px;
            margin: 0 auto;
            background: white;
            border-radius: 15px;
            padding: 30px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
        }
        h1 {
            color: #667eea;
            margin-bottom: 30px;
            text-align: center;
            font-size: 2.5em;
        }
        .section {
            background: #f8f9fa;
            padding: 20px;
            margin-bottom: 20px;
            border-radius: 10px;
            border-left: 5px solid #667eea;
        }
        .section h2 {
            color: #333;
            margin-bottom: 15px;
            font-size: 1.5em;
        }
        .success {
            background: #d4edda;
            color: #155724;
            padding: 15px;
            border-radius: 8px;
            margin: 10px 0;
            border-left: 5px solid #28a745;
        }
        .error {
            background: #f8d7da;
            color: #721c24;
            padding: 15px;
            border-radius: 8px;
            margin: 10px 0;
            border-left: 5px solid #dc3545;
        }
        .warning {
            background: #fff3cd;
            color: #856404;
            padding: 15px;
            border-radius: 8px;
            margin: 10px 0;
            border-left: 5px solid #ffc107;
        }
        .info {
            background: #d1ecf1;
            color: #0c5460;
            padding: 15px;
            border-radius: 8px;
            margin: 10px 0;
            border-left: 5px solid #17a2b8;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin: 15px 0;
            background: white;
            border-radius: 8px;
            overflow: hidden;
        }
        th, td {
            padding: 12px;
            text-align: right;
            border-bottom: 1px solid #ddd;
        }
        th {
            background: #667eea;
            color: white;
            font-weight: bold;
        }
        tr:hover {
            background: #f5f5f5;
        }
        .video-test {
            margin: 20px 0;
            padding: 15px;
            background: white;
            border-radius: 8px;
        }
        video {
            width: 100%;
            max-width: 600px;
            border-radius: 8px;
            margin: 10px 0;
        }
        .code {
            background: #2d2d2d;
            color: #f8f8f2;
            padding: 15px;
            border-radius: 8px;
            font-family: 'Courier New', monospace;
            overflow-x: auto;
            margin: 10px 0;
        }
        .badge {
            display: inline-block;
            padding: 5px 10px;
            border-radius: 5px;
            font-size: 0.9em;
            font-weight: bold;
        }
        .badge-success { background: #28a745; color: white; }
        .badge-danger { background: #dc3545; color: white; }
        .badge-warning { background: #ffc107; color: #333; }
    </style>
</head>
<body>
    <div class="container">
        <h1>🎬 تشخيص شامل لمشاكل الفيديوهات</h1>

<?php

// ============================================
// 1. Server Environment Check
// ============================================
echo '<div class="section">';
echo '<h2>📋 معلومات السيرفر</h2>';

$serverInfo = [
    'PHP Version' => phpversion(),
    'Server Software' => $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown',
    'Document Root' => $_SERVER['DOCUMENT_ROOT'] ?? 'Unknown',
    'Server Name' => $_SERVER['SERVER_NAME'] ?? 'Unknown',
    'Server IP' => $_SERVER['SERVER_ADDR'] ?? 'Unknown',
    'Max Upload Size' => ini_get('upload_max_filesize'),
    'Max Post Size' => ini_get('post_max_size'),
    'Memory Limit' => ini_get('memory_limit'),
    'Max Execution Time' => ini_get('max_execution_time') . ' seconds',
];

echo '<table>';
foreach ($serverInfo as $key => $value) {
    echo "<tr><th>{$key}</th><td>{$value}</td></tr>";
}
echo '</table>';
echo '</div>';

// ============================================
// 2. Database Connection Check
// ============================================
echo '<div class="section">';
echo '<h2>🗄️ فحص الاتصال بقاعدة البيانات</h2>';

$envFile = dirname(__DIR__) . '/.env';
if (file_exists($envFile)) {
    $envContent = file_get_contents($envFile);
    preg_match('/DB_HOST=(.*)/', $envContent, $dbHost);
    preg_match('/DB_DATABASE=(.*)/', $envContent, $dbName);
    preg_match('/DB_USERNAME=(.*)/', $envContent, $dbUser);
    preg_match('/DB_PASSWORD=(.*)/', $envContent, $dbPass);

    $host = trim($dbHost[1] ?? 'localhost');
    $database = trim($dbName[1] ?? '');
    $username = trim($dbUser[1] ?? '');
    $password = trim($dbPass[1] ?? '');

    try {
        $pdo = new PDO("mysql:host={$host};dbname={$database};charset=utf8mb4", $username, $password);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        echo '<div class="success">✅ الاتصال بقاعدة البيانات ناجح</div>';
        
        // Check guide_videos table
        $stmt = $pdo->query("SELECT COUNT(*) as total FROM guide_videos");
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        echo '<div class="info">📊 عدد الفيديوهات في قاعدة البيانات: <strong>' . $result['total'] . '</strong></div>';
        
        // Get sample videos
        $stmt = $pdo->query("SELECT id, title, video_path, is_active, created_at FROM guide_videos ORDER BY created_at DESC LIMIT 10");
        $videos = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        if (count($videos) > 0) {
            echo '<h3>آخر 10 فيديوهات:</h3>';
            echo '<table>';
            echo '<tr><th>ID</th><th>العنوان</th><th>المسار</th><th>نشط</th><th>التاريخ</th></tr>';
            foreach ($videos as $video) {
                $activeStatus = $video['is_active'] ? '<span class="badge badge-success">نشط</span>' : '<span class="badge badge-danger">غير نشط</span>';
                echo "<tr>";
                echo "<td>{$video['id']}</td>";
                echo "<td>{$video['title']}</td>";
                echo "<td>{$video['video_path']}</td>";
                echo "<td>{$activeStatus}</td>";
                echo "<td>{$video['created_at']}</td>";
                echo "</tr>";
            }
            echo '</table>';
        }
        
    } catch (PDOException $e) {
        echo '<div class="error">❌ خطأ في الاتصال بقاعدة البيانات: ' . $e->getMessage() . '</div>';
        $pdo = null;
    }
} else {
    echo '<div class="error">❌ ملف .env غير موجود</div>';
    $pdo = null;
}
echo '</div>';

// ============================================
// 3. File System Check
// ============================================
echo '<div class="section">';
echo '<h2>📁 فحص نظام الملفات</h2>';

$videoPaths = [
    'storage/app/public/guide_videos',
    'public/storage/guide_videos',
    'storage/guide_videos',
    'public/guide_videos',
    'uploads/guide_videos',
    'public/uploads/guide_videos',
];

$foundPath = null;
$videoFiles = [];

foreach ($videoPaths as $path) {
    $fullPath = dirname(__DIR__) . '/' . $path;
    if (is_dir($fullPath)) {
        echo '<div class="success">✅ المجلد موجود: ' . $path . '</div>';
        echo '<div class="info">المسار الكامل: ' . $fullPath . '</div>';
        
        // Check permissions
        $perms = fileperms($fullPath);
        $permString = substr(sprintf('%o', $perms), -4);
        echo '<div class="info">صلاحيات المجلد: ' . $permString . '</div>';
        
        if (is_readable($fullPath)) {
            echo '<div class="success">✅ المجلد قابل للقراءة</div>';
        } else {
            echo '<div class="error">❌ المجلد غير قابل للقراءة</div>';
        }
        
        if (is_writable($fullPath)) {
            echo '<div class="success">✅ المجلد قابل للكتابة</div>';
        } else {
            echo '<div class="warning">⚠️ المجلد غير قابل للكتابة</div>';
        }
        
        // List video files
        $files = glob($fullPath . '/*.{mp4,webm,ogg,avi,mov,MP4,WEBM,OGG,AVI,MOV}', GLOB_BRACE);
        if (count($files) > 0) {
            $foundPath = $path;
            $videoFiles = array_merge($videoFiles, $files);
            echo '<div class="success">✅ تم العثور على ' . count($files) . ' ملف فيديو</div>';
        } else {
            echo '<div class="warning">⚠️ لا توجد ملفات فيديو في هذا المجلد</div>';
        }
        echo '<hr style="margin: 15px 0;">';
    }
}

if (empty($videoFiles)) {
    echo '<div class="error">❌ لم يتم العثور على أي ملفات فيديو في المسارات المتوقعة</div>';
} else {
    echo '<h3>ملفات الفيديو الموجودة:</h3>';
    echo '<table>';
    echo '<tr><th>اسم الملف</th><th>الحجم</th><th>الصلاحيات</th><th>قابل للقراءة</th></tr>';
    foreach (array_slice($videoFiles, 0, 20) as $file) {
        $fileName = basename($file);
        $fileSize = filesize($file);
        $fileSizeFormatted = $fileSize > 1048576 ? round($fileSize / 1048576, 2) . ' MB' : round($fileSize / 1024, 2) . ' KB';
        $perms = fileperms($file);
        $permString = substr(sprintf('%o', $perms), -4);
        $readable = is_readable($file) ? '<span class="badge badge-success">نعم</span>' : '<span class="badge badge-danger">لا</span>';
        
        echo "<tr>";
        echo "<td>{$fileName}</td>";
        echo "<td>{$fileSizeFormatted}</td>";
        echo "<td>{$permString}</td>";
        echo "<td>{$readable}</td>";
        echo "</tr>";
    }
    echo '</table>';
}

echo '</div>';

// ============================================
// 4. MIME Types Check
// ============================================
echo '<div class="section">';
echo '<h2>🎥 فحص أنواع MIME</h2>';

$mimeTypes = [
    'mp4' => 'video/mp4',
    'webm' => 'video/webm',
    'ogg' => 'video/ogg',
];

echo '<table>';
echo '<tr><th>الامتداد</th><th>MIME Type</th><th>الحالة</th></tr>';
foreach ($mimeTypes as $ext => $mime) {
    $supported = function_exists('mime_content_type') ? '<span class="badge badge-success">مدعوم</span>' : '<span class="badge badge-warning">غير متأكد</span>';
    echo "<tr><td>.{$ext}</td><td>{$mime}</td><td>{$supported}</td></tr>";
}
echo '</table>';

echo '</div>';

// ============================================
// 5. .htaccess Check
// ============================================
echo '<div class="section">';
echo '<h2>⚙️ فحص ملف .htaccess</h2>';

$htaccessPath = dirname(__DIR__) . '/public/.htaccess';
if (file_exists($htaccessPath)) {
    echo '<div class="success">✅ ملف .htaccess موجود</div>';
    $htaccessContent = file_get_contents($htaccessPath);
    
    // Check for video MIME types
    if (strpos($htaccessContent, 'video/mp4') !== false || strpos($htaccessContent, 'AddType') !== false) {
        echo '<div class="success">✅ يحتوي على إعدادات MIME types</div>';
    } else {
        echo '<div class="warning">⚠️ لا يحتوي على إعدادات MIME types للفيديو</div>';
        echo '<div class="info"><strong>أضف هذه الأسطر إلى ملف .htaccess:</strong></div>';
        echo '<div class="code">';
        echo 'AddType video/mp4 .mp4 .m4v<br>';
        echo 'AddType video/webm .webm<br>';
        echo 'AddType video/ogg .ogv .ogg<br>';
        echo '</div>';
    }
} else {
    echo '<div class="warning">⚠️ ملف .htaccess غير موجود</div>';
}

echo '</div>';

// ============================================
// 6. Video Test
// ============================================
if (!empty($videoFiles) && isset($pdo)) {
    echo '<div class="section">';
    echo '<h2>🎬 اختبار تشغيل الفيديو</h2>';
    
    // Get first video from database
    $stmt = $pdo->query("SELECT * FROM guide_videos WHERE is_active = 1 LIMIT 1");
    $testVideo = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($testVideo) {
        echo '<div class="video-test">';
        echo '<h3>فيديو الاختبار: ' . htmlspecialchars($testVideo['title']) . '</h3>';
        
        $videoPath = $testVideo['video_path'];
        $fullVideoPath = dirname(__DIR__) . '/public/' . ltrim($videoPath, '/');
        
        echo '<div class="info"><strong>مسار الفيديو في قاعدة البيانات:</strong> ' . $videoPath . '</div>';
        echo '<div class="info"><strong>المسار الكامل:</strong> ' . $fullVideoPath . '</div>';
        
        if (file_exists($fullVideoPath)) {
            echo '<div class="success">✅ الملف موجود على السيرفر</div>';
            $fileSize = filesize($fullVideoPath);
            echo '<div class="info"><strong>حجم الملف:</strong> ' . round($fileSize / 1048576, 2) . ' MB</div>';
            
            if (is_readable($fullVideoPath)) {
                echo '<div class="success">✅ الملف قابل للقراءة</div>';
                
                // Try to play video
                $webPath = '/' . ltrim($videoPath, '/');
                echo '<h4>محاولة تشغيل الفيديو:</h4>';
                echo '<video controls style="max-width: 100%;">';
                echo '<source src="' . htmlspecialchars($webPath) . '" type="video/mp4">';
                echo 'متصفحك لا يدعم تشغيل الفيديو';
                echo '</video>';
                
                echo '<div class="code">';
                echo '&lt;video controls&gt;<br>';
                echo '&nbsp;&nbsp;&lt;source src="' . htmlspecialchars($webPath) . '" type="video/mp4"&gt;<br>';
                echo '&lt;/video&gt;';
                echo '</div>';
                
            } else {
                echo '<div class="error">❌ الملف غير قابل للقراءة - تحقق من الصلاحيات</div>';
            }
        } else {
            echo '<div class="error">❌ الملف غير موجود على السيرفر</div>';
            echo '<div class="warning">⚠️ المسار في قاعدة البيانات لا يطابق موقع الملف الفعلي</div>';
        }
        echo '</div>';
    }
    
    echo '</div>';
}

// ============================================
// 7. Recommendations
// ============================================
echo '<div class="section">';
echo '<h2>💡 التوصيات والحلول</h2>';

$recommendations = [];

if (empty($videoFiles)) {
    $recommendations[] = [
        'type' => 'error',
        'title' => 'لا توجد ملفات فيديو',
        'solution' => 'تأكد من رفع ملفات الفيديو إلى المجلد الصحيح (storage/app/public/guide_videos أو public/storage/guide_videos)'
    ];
}

if (!isset($pdo)) {
    $recommendations[] = [
        'type' => 'error',
        'title' => 'مشكلة في الاتصال بقاعدة البيانات',
        'solution' => 'تحقق من إعدادات قاعدة البيانات في ملف .env'
    ];
}

if (!file_exists($htaccessPath)) {
    $recommendations[] = [
        'type' => 'warning',
        'title' => 'ملف .htaccess مفقود',
        'solution' => 'أنشئ ملف .htaccess في مجلد public وأضف إعدادات MIME types للفيديو'
    ];
}

$recommendations[] = [
    'type' => 'info',
    'title' => 'تأكد من الصلاحيات',
    'solution' => 'يجب أن تكون صلاحيات مجلدات الفيديو 755 وملفات الفيديو 644'
];

$recommendations[] = [
    'type' => 'info',
    'title' => 'تأكد من المسارات',
    'solution' => 'تأكد من أن المسارات في قاعدة البيانات تطابق موقع الملفات الفعلي'
];

$recommendations[] = [
    'type' => 'info',
    'title' => 'تأكد من حجم الملفات',
    'solution' => 'تأكد من أن حجم ملفات الفيديو لا يتجاوز الحد المسموح به في إعدادات PHP'
];

foreach ($recommendations as $rec) {
    echo '<div class="' . $rec['type'] . '">';
    echo '<strong>📌 ' . $rec['title'] . '</strong><br>';
    echo '💡 الحل: ' . $rec['solution'];
    echo '</div>';
}

echo '</div>';

// ============================================
// 8. Quick Fix Commands
// ============================================
echo '<div class="section">';
echo '<h2>🔧 أوامر الإصلاح السريع</h2>';

echo '<p>قم بتشغيل هذه الأوامر على السيرفر عبر SSH:</p>';

echo '<div class="code">';
echo '# إنشاء رابط تخزين رمزي<br>';
echo 'php artisan storage:link<br><br>';

echo '# تعيين الصلاحيات الصحيحة<br>';
echo 'chmod -R 755 storage/<br>';
echo 'chmod -R 755 public/storage/<br><br>';

echo '# تعيين الصلاحيات لملفات الفيديو<br>';
echo 'find storage/app/public/guide_videos -type f -exec chmod 644 {} \;<br>';
echo 'find storage/app/public/guide_videos -type d -exec chmod 755 {} \;<br><br>';

echo '# مسح الكاش<br>';
echo 'php artisan cache:clear<br>';
echo 'php artisan config:clear<br>';
echo 'php artisan view:clear<br>';
echo '</div>';

echo '</div>';

?>

        <div class="section" style="text-align: center; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white;">
            <h2>✅ انتهى الفحص</h2>
            <p>تم إنشاء هذا التقرير في: <?php echo date('Y-m-d H:i:s'); ?></p>
            <p style="margin-top: 10px; font-size: 0.9em;">إذا استمرت المشكلة، شارك هذا التقرير مع فريق الدعم الفني</p>
        </div>
    </div>
</body>
</html>
