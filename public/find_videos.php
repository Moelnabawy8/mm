<?php
/**
 * Advanced Video Finder Script
 * Searches entire server for video files and database records
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
    <title>البحث عن الفيديوهات</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            padding: 20px;
            direction: rtl;
        }
        .container {
            max-width: 1400px;
            margin: 0 auto;
            background: white;
            border-radius: 15px;
            padding: 30px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
        }
        h1 { color: #667eea; margin-bottom: 30px; text-align: center; font-size: 2.5em; }
        h2 { color: #333; margin: 20px 0 15px; font-size: 1.5em; }
        .section {
            background: #f8f9fa;
            padding: 20px;
            margin-bottom: 20px;
            border-radius: 10px;
            border-left: 5px solid #667eea;
        }
        .success { background: #d4edda; color: #155724; padding: 15px; border-radius: 8px; margin: 10px 0; border-left: 5px solid #28a745; }
        .error { background: #f8d7da; color: #721c24; padding: 15px; border-radius: 8px; margin: 10px 0; border-left: 5px solid #dc3545; }
        .warning { background: #fff3cd; color: #856404; padding: 15px; border-radius: 8px; margin: 10px 0; border-left: 5px solid #ffc107; }
        .info { background: #d1ecf1; color: #0c5460; padding: 15px; border-radius: 8px; margin: 10px 0; border-left: 5px solid #17a2b8; }
        table { width: 100%; border-collapse: collapse; margin: 15px 0; background: white; border-radius: 8px; overflow: hidden; }
        th, td { padding: 10px; text-align: right; border-bottom: 1px solid #ddd; font-size: 0.9em; }
        th { background: #667eea; color: white; font-weight: bold; }
        tr:hover { background: #f5f5f5; }
        .code { background: #2d2d2d; color: #f8f8f2; padding: 15px; border-radius: 8px; font-family: 'Courier New', monospace; overflow-x: auto; margin: 10px 0; font-size: 0.9em; }
        .badge { display: inline-block; padding: 5px 10px; border-radius: 5px; font-size: 0.85em; font-weight: bold; }
        .badge-success { background: #28a745; color: white; }
        .badge-danger { background: #dc3545; color: white; }
        .badge-warning { background: #ffc107; color: #333; }
        .path-box { background: white; padding: 10px; border-radius: 5px; margin: 5px 0; font-family: monospace; font-size: 0.85em; word-break: break-all; }
    </style>
</head>
<body>
    <div class="container">
        <h1>🔍 البحث المتقدم عن الفيديوهات</h1>

<?php

$rootPath = dirname(__DIR__);
echo '<div class="section">';
echo '<h2>📍 المسار الجذري</h2>';
echo '<div class="path-box">' . $rootPath . '</div>';
echo '</div>';

// ============================================
// 1. Search for ALL video files
// ============================================
echo '<div class="section">';
echo '<h2>🎬 البحث عن جميع ملفات الفيديو</h2>';

function findVideos($dir, $maxDepth = 5, $currentDepth = 0) {
    $videos = [];
    if ($currentDepth >= $maxDepth || !is_dir($dir) || !is_readable($dir)) {
        return $videos;
    }
    
    try {
        $items = @scandir($dir);
        if ($items === false) return $videos;
        
        foreach ($items as $item) {
            if ($item === '.' || $item === '..') continue;
            
            $path = $dir . DIRECTORY_SEPARATOR . $item;
            
            if (is_file($path)) {
                $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
                if (in_array($ext, ['mp4', 'webm', 'ogg', 'avi', 'mov', 'mkv', 'flv'])) {
                    $videos[] = [
                        'path' => $path,
                        'name' => $item,
                        'size' => @filesize($path),
                        'readable' => is_readable($path),
                        'perms' => substr(sprintf('%o', @fileperms($path)), -4),
                    ];
                }
            } elseif (is_dir($path)) {
                // Skip common directories to avoid
                $skipDirs = ['node_modules', 'vendor', '.git', 'cache'];
                $skip = false;
                foreach ($skipDirs as $skipDir) {
                    if (strpos($path, DIRECTORY_SEPARATOR . $skipDir) !== false) {
                        $skip = true;
                        break;
                    }
                }
                if (!$skip) {
                    $videos = array_merge($videos, findVideos($path, $maxDepth, $currentDepth + 1));
                }
            }
        }
    } catch (Exception $e) {
        // Skip directories with errors
    }
    
    return $videos;
}

echo '<div class="info">⏳ جاري البحث في المجلدات... قد يستغرق بعض الوقت</div>';

$allVideos = findVideos($rootPath, 4);

if (count($allVideos) > 0) {
    echo '<div class="success">✅ تم العثور على ' . count($allVideos) . ' ملف فيديو</div>';
    
    echo '<table>';
    echo '<tr><th>اسم الملف</th><th>المسار النسبي</th><th>الحجم</th><th>الصلاحيات</th><th>قابل للقراءة</th></tr>';
    
    foreach ($allVideos as $video) {
        $relativePath = str_replace($rootPath, '', $video['path']);
        $sizeFormatted = $video['size'] > 1048576 ? round($video['size'] / 1048576, 2) . ' MB' : round($video['size'] / 1024, 2) . ' KB';
        $readable = $video['readable'] ? '<span class="badge badge-success">نعم</span>' : '<span class="badge badge-danger">لا</span>';
        
        echo '<tr>';
        echo '<td>' . htmlspecialchars($video['name']) . '</td>';
        echo '<td style="font-family: monospace; font-size: 0.8em;">' . htmlspecialchars($relativePath) . '</td>';
        echo '<td>' . $sizeFormatted . '</td>';
        echo '<td>' . $video['perms'] . '</td>';
        echo '<td>' . $readable . '</td>';
        echo '</tr>';
    }
    echo '</table>';
} else {
    echo '<div class="error">❌ لم يتم العثور على أي ملفات فيديو</div>';
}

echo '</div>';

// ============================================
// 2. Try to connect to database with multiple methods
// ============================================
echo '<div class="section">';
echo '<h2>🗄️ محاولة الاتصال بقاعدة البيانات</h2>';

$dbConnected = false;
$pdo = null;

// Method 1: Try .env file
$envPaths = [
    $rootPath . '/.env',
    dirname($rootPath) . '/.env',
    $rootPath . '/../.env',
];

foreach ($envPaths as $envPath) {
    if (file_exists($envPath)) {
        echo '<div class="success">✅ تم العثور على ملف .env في: ' . $envPath . '</div>';
        
        $envContent = file_get_contents($envPath);
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
            echo '<div class="success">✅ تم الاتصال بقاعدة البيانات بنجاح</div>';
            $dbConnected = true;
            break;
        } catch (PDOException $e) {
            echo '<div class="error">❌ فشل الاتصال: ' . $e->getMessage() . '</div>';
        }
    }
}

if (!$dbConnected) {
    echo '<div class="warning">⚠️ لم يتم العثور على ملف .env أو فشل الاتصال بقاعدة البيانات</div>';
    echo '<div class="info">💡 جرب إدخال بيانات الاتصال يدوياً:</div>';
    
    // Try to connect with common credentials
    $commonConfigs = [
        ['localhost', 'root', '', 'erp'],
        ['localhost', 'root', 'root', 'erp'],
        ['127.0.0.1', 'root', '', 'erp'],
    ];
    
    foreach ($commonConfigs as $config) {
        try {
            $pdo = new PDO("mysql:host={$config[0]};dbname={$config[3]};charset=utf8mb4", $config[1], $config[2]);
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            echo '<div class="success">✅ تم الاتصال باستخدام: host=' . $config[0] . ', user=' . $config[1] . ', db=' . $config[3] . '</div>';
            $dbConnected = true;
            break;
        } catch (PDOException $e) {
            // Continue trying
        }
    }
}

// If connected, get video records
if ($dbConnected && $pdo) {
    try {
        $stmt = $pdo->query("SELECT COUNT(*) as total FROM guide_videos");
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        echo '<div class="info">📊 عدد الفيديوهات في قاعدة البيانات: <strong>' . $result['total'] . '</strong></div>';
        
        $stmt = $pdo->query("SELECT id, title, video_path, is_active FROM guide_videos ORDER BY id DESC LIMIT 20");
        $dbVideos = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        if (count($dbVideos) > 0) {
            echo '<h3>سجلات الفيديو في قاعدة البيانات:</h3>';
            echo '<table>';
            echo '<tr><th>ID</th><th>العنوان</th><th>المسار في DB</th><th>الملف موجود؟</th><th>نشط</th></tr>';
            
            foreach ($dbVideos as $dbVideo) {
                $fullPath = $rootPath . '/public/' . ltrim($dbVideo['video_path'], '/');
                $exists = file_exists($fullPath) ? '<span class="badge badge-success">نعم</span>' : '<span class="badge badge-danger">لا</span>';
                $active = $dbVideo['is_active'] ? '<span class="badge badge-success">نعم</span>' : '<span class="badge badge-danger">لا</span>';
                
                echo '<tr>';
                echo '<td>' . $dbVideo['id'] . '</td>';
                echo '<td>' . htmlspecialchars(substr($dbVideo['title'], 0, 50)) . '</td>';
                echo '<td style="font-family: monospace; font-size: 0.8em;">' . htmlspecialchars($dbVideo['video_path']) . '</td>';
                echo '<td>' . $exists . '</td>';
                echo '<td>' . $active . '</td>';
                echo '</tr>';
            }
            echo '</table>';
        }
    } catch (PDOException $e) {
        echo '<div class="error">❌ خطأ في قراءة البيانات: ' . $e->getMessage() . '</div>';
    }
}

echo '</div>';

// ============================================
// 3. Check storage directories
// ============================================
echo '<div class="section">';
echo '<h2>📂 فحص مجلدات التخزين</h2>';

$storagePaths = [
    'storage/app/public/guide_videos',
    'public/storage/guide_videos',
    'storage/guide_videos',
    'public/guide_videos',
    'uploads/guide_videos',
    'public/uploads/guide_videos',
    'storage/app/guide_videos',
    'public/videos',
    'videos',
];

echo '<table>';
echo '<tr><th>المسار</th><th>موجود؟</th><th>قابل للقراءة؟</th><th>قابل للكتابة؟</th><th>الصلاحيات</th></tr>';

foreach ($storagePaths as $path) {
    $fullPath = $rootPath . '/' . $path;
    $exists = is_dir($fullPath);
    $readable = $exists ? is_readable($fullPath) : false;
    $writable = $exists ? is_writable($fullPath) : false;
    $perms = $exists ? substr(sprintf('%o', fileperms($fullPath)), -4) : 'N/A';
    
    echo '<tr>';
    echo '<td style="font-family: monospace;">' . $path . '</td>';
    echo '<td>' . ($exists ? '<span class="badge badge-success">نعم</span>' : '<span class="badge badge-danger">لا</span>') . '</td>';
    echo '<td>' . ($readable ? '<span class="badge badge-success">نعم</span>' : '<span class="badge badge-danger">لا</span>') . '</td>';
    echo '<td>' . ($writable ? '<span class="badge badge-success">نعم</span>' : '<span class="badge badge-danger">لا</span>') . '</td>';
    echo '<td>' . $perms . '</td>';
    echo '</tr>';
}

echo '</table>';
echo '</div>';

// ============================================
// 4. Solutions
// ============================================
echo '<div class="section">';
echo '<h2>💡 الحلول المقترحة</h2>';

if (count($allVideos) > 0) {
    echo '<div class="success">';
    echo '<h3>✅ تم العثور على ملفات فيديو!</h3>';
    echo '<p><strong>الخطوة التالية:</strong></p>';
    echo '<ol style="margin-right: 20px;">';
    echo '<li>تأكد من أن المسارات في قاعدة البيانات تطابق مواقع الملفات الفعلية</li>';
    echo '<li>قم بتشغيل الأمر: <code>php artisan storage:link</code></li>';
    echo '<li>تأكد من الصلاحيات الصحيحة للملفات والمجلدات</li>';
    echo '</ol>';
    echo '</div>';
} else {
    echo '<div class="error">';
    echo '<h3>❌ لم يتم العثور على ملفات فيديو</h3>';
    echo '<p><strong>يجب عليك:</strong></p>';
    echo '<ol style="margin-right: 20px;">';
    echo '<li>رفع ملفات الفيديو إلى السيرفر</li>';
    echo '<li>وضعها في المجلد: <code>storage/app/public/guide_videos</code></li>';
    echo '<li>تشغيل الأمر: <code>php artisan storage:link</code></li>';
    echo '</ol>';
    echo '</div>';
}

echo '<div class="code">';
echo '# الأوامر المطلوبة:<br><br>';
echo '# 1. إنشاء المجلد إذا لم يكن موجوداً<br>';
echo 'mkdir -p storage/app/public/guide_videos<br><br>';
echo '# 2. إنشاء الرابط الرمزي<br>';
echo 'php artisan storage:link<br><br>';
echo '# 3. تعيين الصلاحيات<br>';
echo 'chmod -R 755 storage/<br>';
echo 'chmod -R 755 public/storage/<br><br>';
echo '# 4. إذا كانت الملفات موجودة في مكان آخر، انقلها:<br>';
echo 'mv /path/to/videos/* storage/app/public/guide_videos/<br>';
echo '</div>';

echo '</div>';

?>

        <div class="section" style="text-align: center; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white;">
            <h2>✅ انتهى البحث</h2>
            <p>تم إنشاء هذا التقرير في: <?php echo date('Y-m-d H:i:s'); ?></p>
        </div>
    </div>
</body>
</html>
