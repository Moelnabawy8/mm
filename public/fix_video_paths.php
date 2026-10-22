<?php

/**
 * Video Path Fixer Script
 * Fixes video paths in database and creates proper directory structure
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);
header('Content-Type: text/html; charset=utf-8');

// Get action from URL
$action = $_GET['action'] ?? 'check';
$confirmed = $_GET['confirmed'] ?? 'no';

?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>إصلاح مسارات الفيديو</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

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
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
        }

        h1 {
            color: #667eea;
            margin-bottom: 30px;
            text-align: center;
            font-size: 2.5em;
        }

        h2 {
            color: #333;
            margin: 20px 0 15px;
            font-size: 1.5em;
        }

        .section {
            background: #f8f9fa;
            padding: 20px;
            margin-bottom: 20px;
            border-radius: 10px;
            border-left: 5px solid #667eea;
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

        th,
        td {
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

        .btn {
            display: inline-block;
            padding: 12px 24px;
            margin: 10px 5px;
            border-radius: 8px;
            text-decoration: none;
            font-weight: bold;
            transition: all 0.3s;
            border: none;
            cursor: pointer;
            font-size: 1em;
        }

        .btn-primary {
            background: #667eea;
            color: white;
        }

        .btn-primary:hover {
            background: #5568d3;
            transform: translateY(-2px);
        }

        .btn-success {
            background: #28a745;
            color: white;
        }

        .btn-success:hover {
            background: #218838;
            transform: translateY(-2px);
        }

        .btn-danger {
            background: #dc3545;
            color: white;
        }

        .btn-danger:hover {
            background: #c82333;
            transform: translateY(-2px);
        }

        .btn-warning {
            background: #ffc107;
            color: #333;
        }

        .btn-warning:hover {
            background: #e0a800;
            transform: translateY(-2px);
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

        .action-box {
            background: white;
            padding: 20px;
            border-radius: 10px;
            margin: 15px 0;
            border: 2px solid #667eea;
        }

        .form-group {
            margin: 15px 0;
        }

        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
            color: #333;
        }

        .form-group input,
        .form-group select {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 1em;
        }
    </style>
</head>

<body>
    <div class="container">
        <h1>🔧 إصلاح مسارات الفيديو</h1>

        <?php

        $rootPath = dirname(__DIR__);

        // ============================================
        // Database Connection Form
        // ============================================
        if ($action === 'check' || !isset($_POST['db_host'])) {
            echo '<div class="section">';
            echo '<h2>🗄️ الاتصال بقاعدة البيانات</h2>';
            echo '<div class="warning">⚠️ يجب إدخال بيانات قاعدة البيانات للمتابعة</div>';

            echo '<form method="POST" action="?action=connect">';
            echo '<div class="action-box">';

            echo '<div class="form-group">';
            echo '<label>عنوان السيرفر (Host):</label>';
            echo '<input type="text" name="db_host" value="localhost" required>';
            echo '</div>';

            echo '<div class="form-group">';
            echo '<label>اسم قاعدة البيانات (Database):</label>';
            echo '<input type="text" name="db_name" value="" required>';
            echo '</div>';

            echo '<div class="form-group">';
            echo '<label>اسم المستخدم (Username):</label>';
            echo '<input type="text" name="db_user" value="" required>';
            echo '</div>';

            echo '<div class="form-group">';
            echo '<label>كلمة المرور (Password):</label>';
            echo '<input type="password" name="db_pass" value="">';
            echo '</div>';

            echo '<button type="submit" class="btn btn-primary">🔗 اتصال</button>';
            echo '</div>';
            echo '</form>';
            echo '</div>';
        }

        // ============================================
        // Connect and Analyze
        // ============================================
        if ($action === 'connect' && isset($_POST['db_host'])) {
            $host = $_POST['db_host'];
            $database = $_POST['db_name'];
            $username = $_POST['db_user'];
            $password = $_POST['db_pass'];

            try {
                $pdo = new PDO("mysql:host={$host};dbname={$database};charset=utf8mb4", $username, $password);
                $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

                echo '<div class="section">';
                echo '<div class="success">✅ تم الاتصال بقاعدة البيانات بنجاح!</div>';
                echo '</div>';

                // Get all videos from database
                $stmt = $pdo->query("SELECT id, title, video_path FROM guide_videos ORDER BY id");
                $videos = $stmt->fetchAll(PDO::FETCH_ASSOC);

                echo '<div class="section">';
                echo '<h2>📊 تحليل الفيديوهات</h2>';
                echo '<div class="info">📹 عدد الفيديوهات في قاعدة البيانات: <strong>' . count($videos) . '</strong></div>';

                // Find actual video files in multiple locations
                $searchPaths = [
                    '/public/uploads',
                    '/public_html/public/uploads',
                    '../public/uploads',
                ];

                $actualVideos = [];
                $foundPath = '';

                foreach ($searchPaths as $searchPath) {
                    $fullSearchPath = $rootPath . $searchPath;
                    if (is_dir($fullSearchPath)) {
                        $files = glob($fullSearchPath . '/*.{mp4,webm,ogg,avi,mov,MP4,WEBM,OGG,AVI,MOV}', GLOB_BRACE);
                        if (count($files) > 0) {
                            foreach ($files as $file) {
                                $actualVideos[] = basename($file);
                            }
                            $foundPath = $fullSearchPath;
                            break;
                        }
                    }
                }

                echo '<div class="info">📁 عدد ملفات الفيديو الموجودة: <strong>' . count($actualVideos) . '</strong></div>';
                if ($foundPath) {
                    echo '<div class="info">📂 تم العثور عليها في: <strong>' . htmlspecialchars($foundPath) . '</strong></div>';
                }

                if (count($actualVideos) > 0) {
                    echo '<div class="warning">';
                    echo '<h3>📹 الملفات الموجودة فعلياً:</h3>';
                    echo '<ul style="margin-right: 20px;">';
                    foreach ($actualVideos as $video) {
                        echo '<li>' . htmlspecialchars($video) . '</li>';
                    }
                    echo '</ul>';
                    echo '</div>';
                }

                // Compare and show issues
                echo '<h3>🔍 مقارنة المسارات:</h3>';
                echo '<table>';
                echo '<tr><th>ID</th><th>العنوان</th><th>المسار الحالي في DB</th><th>الملف موجود؟</th><th>المسار المقترح</th></tr>';

                $needsFix = [];

                foreach ($videos as $video) {
                    $currentPath = $video['video_path'];
                    $fullPath = $rootPath . '/public/' . ltrim($currentPath, '/');
                    $exists = file_exists($fullPath);

                    // Try to find the file in uploads
                    $fileName = basename($currentPath);
                    $uploadsFilePath = $uploadsPath . '/' . $fileName;
                    $existsInUploads = file_exists($uploadsFilePath);

                    $suggestedPath = '';
                    $needsFixFlag = false;

                    if (!$exists && $existsInUploads) {
                        $suggestedPath = 'uploads/' . $fileName;
                        $needsFixFlag = true;
                        $needsFix[] = [
                            'id' => $video['id'],
                            'old_path' => $currentPath,
                            'new_path' => $suggestedPath,
                        ];
                    } elseif (!$exists && !$existsInUploads) {
                        // Try to find by similar name
                        foreach ($actualVideos as $actualVideo) {
                            if (stripos($actualVideo, pathinfo($fileName, PATHINFO_FILENAME)) !== false) {
                                $suggestedPath = 'uploads/' . $actualVideo;
                                $needsFixFlag = true;
                                $needsFix[] = [
                                    'id' => $video['id'],
                                    'old_path' => $currentPath,
                                    'new_path' => $suggestedPath,
                                ];
                                break;
                            }
                        }
                        if (!$suggestedPath) {
                            $suggestedPath = '❌ لم يتم العثور على الملف';
                        }
                    } else {
                        $suggestedPath = '✅ المسار صحيح';
                    }

                    $statusBadge = $exists ? '<span style="color: #28a745; font-weight: bold;">✅ نعم</span>' : '<span style="color: #dc3545; font-weight: bold;">❌ لا</span>';
                    $rowStyle = $needsFixFlag ? 'background: #fff3cd;' : '';

                    echo '<tr style="' . $rowStyle . '">';
                    echo '<td>' . $video['id'] . '</td>';
                    echo '<td>' . htmlspecialchars(substr($video['title'], 0, 40)) . '</td>';
                    echo '<td style="font-family: monospace; font-size: 0.85em;">' . htmlspecialchars($currentPath) . '</td>';
                    echo '<td>' . $statusBadge . '</td>';
                    echo '<td style="font-family: monospace; font-size: 0.85em;">' . htmlspecialchars($suggestedPath) . '</td>';
                    echo '</tr>';
                }

                echo '</table>';

                // Show fix options
                if (count($needsFix) > 0) {
                    echo '<div class="warning">';
                    echo '<h3>⚠️ تم العثور على ' . count($needsFix) . ' فيديو يحتاج إلى إصلاح المسار</h3>';
                    echo '</div>';

                    echo '<div class="action-box">';
                    echo '<h3>🔧 خيارات الإصلاح:</h3>';

                    echo '<form method="POST" action="?action=fix_paths">';
                    echo '<input type="hidden" name="db_host" value="' . htmlspecialchars($host) . '">';
                    echo '<input type="hidden" name="db_name" value="' . htmlspecialchars($database) . '">';
                    echo '<input type="hidden" name="db_user" value="' . htmlspecialchars($username) . '">';
                    echo '<input type="hidden" name="db_pass" value="' . htmlspecialchars($password) . '">';
                    echo '<input type="hidden" name="fixes" value="' . htmlspecialchars(json_encode($needsFix)) . '">';

                    echo '<p><strong>سيتم تحديث المسارات التالية:</strong></p>';
                    echo '<div class="code">';
                    foreach ($needsFix as $fix) {
                        echo 'ID ' . $fix['id'] . ': ' . htmlspecialchars($fix['old_path']) . ' → ' . htmlspecialchars($fix['new_path']) . '<br>';
                    }
                    echo '</div>';

                    echo '<button type="submit" class="btn btn-success">✅ تحديث المسارات في قاعدة البيانات</button>';
                    echo '</form>';
                    echo '</div>';
                } else {
                    echo '<div class="success">';
                    echo '<h3>✅ جميع المسارات صحيحة!</h3>';
                    echo '<p>لا توجد مسارات تحتاج إلى إصلاح.</p>';
                    echo '</div>';
                }

                echo '</div>';
            } catch (PDOException $e) {
                echo '<div class="section">';
                echo '<div class="error">❌ خطأ في الاتصال بقاعدة البيانات: ' . htmlspecialchars($e->getMessage()) . '</div>';
                echo '<a href="?action=check" class="btn btn-warning">🔙 العودة</a>';
                echo '</div>';
            }
        }

        // ============================================
        // Fix Paths
        // ============================================
        if ($action === 'fix_paths' && isset($_POST['fixes'])) {
            $host = $_POST['db_host'];
            $database = $_POST['db_name'];
            $username = $_POST['db_user'];
            $password = $_POST['db_pass'];
            $fixes = json_decode($_POST['fixes'], true);

            try {
                $pdo = new PDO("mysql:host={$host};dbname={$database};charset=utf8mb4", $username, $password);
                $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

                echo '<div class="section">';
                echo '<h2>🔧 تحديث المسارات</h2>';

                $updated = 0;
                $failed = 0;

                foreach ($fixes as $fix) {
                    try {
                        $stmt = $pdo->prepare("UPDATE guide_videos SET video_path = ? WHERE id = ?");
                        $stmt->execute([$fix['new_path'], $fix['id']]);

                        echo '<div class="success">✅ تم تحديث ID ' . $fix['id'] . ': ' . htmlspecialchars($fix['new_path']) . '</div>';
                        $updated++;
                    } catch (PDOException $e) {
                        echo '<div class="error">❌ فشل تحديث ID ' . $fix['id'] . ': ' . htmlspecialchars($e->getMessage()) . '</div>';
                        $failed++;
                    }
                }

                echo '<div class="info">';
                echo '<h3>📊 النتيجة:</h3>';
                echo '<p>✅ تم التحديث بنجاح: <strong>' . $updated . '</strong></p>';
                if ($failed > 0) {
                    echo '<p>❌ فشل التحديث: <strong>' . $failed . '</strong></p>';
                }
                echo '</div>';

                echo '<a href="?action=check" class="btn btn-primary">🔄 فحص مرة أخرى</a>';
                echo '</div>';
            } catch (PDOException $e) {
                echo '<div class="section">';
                echo '<div class="error">❌ خطأ: ' . htmlspecialchars($e->getMessage()) . '</div>';
                echo '</div>';
            }
        }

        // ============================================
        // Additional Tools
        // ============================================
        echo '<div class="section">';
        echo '<h2>🛠️ أدوات إضافية</h2>';

        echo '<div class="action-box">';
        echo '<h3>📂 إنشاء هيكل المجلدات الصحيح</h3>';
        echo '<p>قم بتشغيل هذه الأوامر على السيرفر عبر SSH:</p>';
        echo '<div class="code">';
        echo '# إنشاء المجلدات المطلوبة<br>';
        echo 'mkdir -p storage/app/public/guide_videos<br>';
        echo 'mkdir -p public/storage<br><br>';
        echo '# إنشاء الرابط الرمزي<br>';
        echo 'php artisan storage:link<br><br>';
        echo '# نقل الفيديوهات من uploads إلى storage (اختياري)<br>';
        echo 'cp public/uploads/*.mp4 storage/app/public/guide_videos/<br><br>';
        echo '# تعيين الصلاحيات<br>';
        echo 'chmod -R 755 storage/<br>';
        echo 'chmod -R 755 public/storage/<br>';
        echo 'find storage/app/public/guide_videos -type f -exec chmod 644 {} \;<br>';
        echo '</div>';
        echo '</div>';

        echo '<div class="action-box">';
        echo '<h3>🎬 اختبار الفيديو</h3>';
        echo '<p>الفيديوهات الموجودة في /public/uploads:</p>';

        $uploadsPath = $rootPath . '/public/uploads';
        if (is_dir($uploadsPath)) {
            $files = glob($uploadsPath . '/*.{mp4,webm,ogg,MP4,WEBM,OGG}', GLOB_BRACE);
            if (count($files) > 0) {
                echo '<ul style="margin-right: 20px;">';
                foreach ($files as $file) {
                    $fileName = basename($file);
                    $webPath = '/uploads/' . rawurlencode($fileName);
                    echo '<li style="margin: 10px 0;">';
                    echo '<strong>' . htmlspecialchars($fileName) . '</strong><br>';
                    echo '<a href="' . htmlspecialchars($webPath) . '" target="_blank" class="btn btn-primary" style="margin-top: 5px;">▶️ تشغيل</a>';
                    echo '</li>';
                }
                echo '</ul>';
            }
        }

        echo '</div>';

        echo '</div>';

        ?>

        <div class="section" style="text-align: center; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white;">
            <h2>✅ انتهى</h2>
            <p>تم إنشاء هذا التقرير في: <?php echo date('Y-m-d H:i:s'); ?></p>
        </div>
    </div>
</body>

</html>