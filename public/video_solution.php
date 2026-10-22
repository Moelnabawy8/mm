<?php
/**
 * Simple Video Problem Identifier & Solution
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
    <title>حل مشكلة الفيديوهات</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            padding: 20px;
            direction: rtl;
        }
        .container {
            max-width: 1000px;
            margin: 0 auto;
            background: white;
            border-radius: 15px;
            padding: 30px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
        }
        h1 { color: #667eea; margin-bottom: 30px; text-align: center; font-size: 2.5em; }
        h2 { color: #333; margin: 20px 0 15px; font-size: 1.5em; border-bottom: 3px solid #667eea; padding-bottom: 10px; }
        .problem { background: #f8d7da; color: #721c24; padding: 20px; border-radius: 10px; margin: 20px 0; border-left: 5px solid #dc3545; }
        .solution { background: #d4edda; color: #155724; padding: 20px; border-radius: 10px; margin: 20px 0; border-left: 5px solid #28a745; }
        .warning { background: #fff3cd; color: #856404; padding: 20px; border-radius: 10px; margin: 20px 0; border-left: 5px solid #ffc107; }
        .info { background: #d1ecf1; color: #0c5460; padding: 20px; border-radius: 10px; margin: 20px 0; border-left: 5px solid #17a2b8; }
        .code { background: #2d2d2d; color: #f8f8f2; padding: 20px; border-radius: 10px; font-family: 'Courier New', monospace; margin: 15px 0; line-height: 1.8; }
        .comparison { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin: 20px 0; }
        .comparison-box { background: #f8f9fa; padding: 15px; border-radius: 8px; }
        .comparison-box h3 { color: #667eea; margin-bottom: 10px; font-size: 1.1em; }
        .comparison-box ul { margin-right: 20px; }
        .comparison-box li { margin: 8px 0; font-family: monospace; font-size: 0.9em; }
        .step { background: white; border: 2px solid #667eea; padding: 20px; margin: 15px 0; border-radius: 10px; }
        .step-number { background: #667eea; color: white; width: 40px; height: 40px; border-radius: 50%; display: inline-flex; align-items: center; justify-content: center; font-weight: bold; font-size: 1.2em; margin-left: 15px; }
        strong { color: #667eea; }
    </style>
</head>
<body>
    <div class="container">
        <h1>🎯 حل مشكلة الفيديوهات - التقرير النهائي</h1>

        <div class="problem">
            <h2 style="color: #721c24; border: none;">❌ المشكلة المكتشفة</h2>
            <p style="font-size: 1.2em; margin: 15px 0;">
                <strong style="color: #721c24;">أسماء الملفات في قاعدة البيانات لا تطابق الملفات الموجودة على السيرفر!</strong>
            </p>
        </div>

        <h2>🔍 التفاصيل</h2>

        <div class="comparison">
            <div class="comparison-box" style="border: 3px solid #dc3545;">
                <h3>📋 في قاعدة البيانات (7 فيديوهات):</h3>
                <ul>
                    <li>❌ CRM system.mp4</li>
                    <li>❌ Dashboard-HRM system.mp4</li>
                    <li>❌ Departments system.mp4</li>
                    <li>❌ Payroll.mp4</li>
                    <li>❌ leave mangement.mp4</li>
                    <li>❌ Ateendance.mp4</li>
                    <li>❌ ERP overview.mp4</li>
                </ul>
            </div>

            <div class="comparison-box" style="border: 3px solid #28a745;">
                <h3>📁 على السيرفر (3 فيديوهات فقط):</h3>
                <ul>
                    <li>✅ ERP overview.mp4</li>
                    <li>✅ Support -Messenger-Google.mp4</li>
                    <li>✅ شرح كامل لكيفية البدء في العمل على نظام ERP.mp4</li>
                </ul>
            </div>
        </div>

        <div class="warning">
            <h3 style="color: #856404;">⚠️ الملاحظات:</h3>
            <ul style="margin-right: 20px; margin-top: 10px;">
                <li>فيديو واحد فقط موجود في الطرفين: <strong style="color: #856404;">ERP overview.mp4</strong></li>
                <li>6 فيديوهات مسجلة في قاعدة البيانات لكن <strong style="color: #856404;">ملفاتها مفقودة</strong></li>
                <li>2 فيديوهات موجودة على السيرفر لكن <strong style="color: #856404;">غير مسجلة في قاعدة البيانات</strong></li>
            </ul>
        </div>

        <h2>💡 الحلول المتاحة</h2>

        <div class="step">
            <span class="step-number">1</span>
            <strong style="font-size: 1.2em;">رفع الملفات المفقودة</strong>
            <p style="margin: 15px 0 15px 55px;">
                ارفع الملفات التالية إلى مجلد <code>/public/uploads/</code> على السيرفر:
            </p>
            <ul style="margin-right: 75px;">
                <li>CRM system.mp4</li>
                <li>Dashboard-HRM system.mp4</li>
                <li>Departments system.mp4</li>
                <li>Payroll.mp4</li>
                <li>leave mangement.mp4</li>
                <li>Ateendance.mp4</li>
            </ul>
        </div>

        <div class="step">
            <span class="step-number">2</span>
            <strong style="font-size: 1.2em;">أو: تحديث قاعدة البيانات</strong>
            <p style="margin: 15px 0 15px 55px;">
                إذا كانت الملفات الموجودة هي الصحيحة، قم بتحديث قاعدة البيانات لتطابقها.
            </p>
        </div>

        <h2>🔧 أوامر الإصلاح</h2>

        <div class="info">
            <h3 style="color: #0c5460;">📝 الخيار الأول: رفع الملفات عبر FTP/cPanel</h3>
            <ol style="margin-right: 20px; margin-top: 10px;">
                <li>افتح FTP أو File Manager في cPanel</li>
                <li>اذهب إلى: <code>/public_html/public/uploads/</code></li>
                <li>ارفع جميع ملفات الفيديو المفقودة</li>
                <li>تأكد من أن أسماء الملفات تطابق تماماً ما في قاعدة البيانات</li>
            </ol>
        </div>

        <div class="solution">
            <h3 style="color: #155724;">✅ الخيار الثاني: تحديث قاعدة البيانات (إذا كانت الملفات الموجودة صحيحة)</h3>
            <p style="margin: 10px 0;">قم بتشغيل هذه الاستعلامات SQL:</p>
            <div class="code">
-- تحديث الفيديو رقم 2 ليشير إلى الملف الموجود<br>
UPDATE guide_videos <br>
SET video_path = 'uploads/Support -Messenger-Google.mp4',<br>
&nbsp;&nbsp;&nbsp;&nbsp;title = 'دعم عبر Messenger و Google'<br>
WHERE id = 2;<br><br>

-- تحديث الفيديو رقم 3<br>
UPDATE guide_videos <br>
SET video_path = 'uploads/شرح كامل لكيفية البدء في العمل على نظام ERP.mp4',<br>
&nbsp;&nbsp;&nbsp;&nbsp;title = 'شرح كامل لكيفية البدء في العمل على نظام ERP'<br>
WHERE id = 3;<br><br>

-- حذف الفيديوهات المفقودة (اختياري)<br>
DELETE FROM guide_videos WHERE id IN (4, 5, 6, 7);
            </div>
        </div>

        <h2>🎬 اختبار الفيديوهات الموجودة</h2>

        <div class="info">
            <h3 style="color: #0c5460;">▶️ الفيديوهات القابلة للتشغيل حالياً:</h3>
            <?php
            $rootPath = dirname(__DIR__);
            $searchPaths = [
                '/public/uploads',
                '/public_html/public/uploads',
            ];
            
            $found = false;
            foreach ($searchPaths as $searchPath) {
                $fullPath = $rootPath . $searchPath;
                if (is_dir($fullPath)) {
                    $files = glob($fullPath . '/*.{mp4,MP4}', GLOB_BRACE);
                    if (count($files) > 0) {
                        echo '<ul style="margin: 15px 0 15px 20px;">';
                        foreach ($files as $file) {
                            $fileName = basename($file);
                            $webPath = '/uploads/' . rawurlencode($fileName);
                            $fileSize = round(filesize($file) / 1048576, 2);
                            echo '<li style="margin: 10px 0;">';
                            echo '<strong>' . htmlspecialchars($fileName) . '</strong> (' . $fileSize . ' MB)<br>';
                            echo '<a href="' . htmlspecialchars($webPath) . '" target="_blank" style="color: #667eea; text-decoration: none; font-weight: bold;">🎥 تشغيل الفيديو</a>';
                            echo '</li>';
                        }
                        echo '</ul>';
                        $found = true;
                        break;
                    }
                }
            }
            
            if (!$found) {
                echo '<p style="color: #dc3545; margin: 15px 0;">❌ لم يتم العثور على ملفات فيديو</p>';
            }
            ?>
        </div>

        <h2>📋 الخلاصة والتوصيات</h2>

        <div class="solution">
            <h3 style="color: #155724;">✅ الحل الموصى به:</h3>
            <ol style="margin: 15px 0 15px 20px; line-height: 2;">
                <li><strong style="color: #155724;">ارفع جميع ملفات الفيديو المفقودة</strong> إلى <code>/public/uploads/</code></li>
                <li><strong style="color: #155724;">تأكد من أسماء الملفات</strong> تطابق تماماً ما في قاعدة البيانات</li>
                <li><strong style="color: #155724;">اختبر كل فيديو</strong> بعد الرفع</li>
                <li><strong style="color: #155724;">تأكد من الصلاحيات:</strong> <code>chmod 644</code> للملفات</li>
            </ol>
        </div>

        <div class="code">
# إذا كنت تستخدم SSH، قم بتشغيل:<br><br>
cd /home/erp.my-communication.uk/public_html/public/uploads<br>
ls -lah *.mp4<br><br>
# تعيين الصلاحيات الصحيحة<br>
chmod 644 *.mp4<br>
chmod 755 .<br>
        </div>

        <div style="text-align: center; margin-top: 40px; padding: 20px; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); border-radius: 10px; color: white;">
            <h2 style="color: white; border: none;">✅ بعد تطبيق الحل</h2>
            <p style="font-size: 1.1em; margin-top: 10px;">
                ستعمل جميع الفيديوهات بشكل صحيح على الموقع
            </p>
            <p style="margin-top: 15px; font-size: 0.9em; opacity: 0.9;">
                تم إنشاء هذا التقرير في: <?php echo date('Y-m-d H:i:s'); ?>
            </p>
        </div>
    </div>
</body>
</html>
