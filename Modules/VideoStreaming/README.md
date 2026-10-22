# Video Streaming Module

نظام بث فيديو احترافي مع تخزين Google Drive وتشفير HLS.

## المميزات

### 🔐 الحماية والأمان
- **تشفير AES-128 HLS**: كل قطعة فيديو مشفرة بمفتاح فريد
- **قفل النطاق**: الفيديو يعمل فقط على النطاقات المسموحة
- **روابط مؤقتة موقعة**: تنتهي صلاحية الروابط تلقائياً
- **حماية من برامج التحميل**: IDM وغيرها لا يمكنها تحميل الفيديو
- **منع DevTools**: إيقاف التشغيل عند فتح أدوات المطور

### 📹 معالجة الفيديو
- **تقطيع HLS**: تحويل الفيديو إلى آلاف القطع الصغيرة
- **جودات متعددة**: 360p, 480p, 720p, 1080p
- **تحويل تلقائي**: FFmpeg لمعالجة الفيديو
- **Thumbnails**: إنشاء صور مصغرة تلقائياً

### ☁️ التخزين السحابي
- **Google Drive**: تخزين مجاني وآمن
- **حفظ خاص**: الملفات غير قابلة للوصول العام
- **تنظيف تلقائي**: حذف الملفات المؤقتة

### 🎬 مشغل احترافي
- **Plyr + HLS.js**: مشغل حديث ومتوافق
- **جودة تكيفية**: يختار الجودة المناسبة للاتصال
- **علامة مائية**: حماية إضافية للمحتوى
- **تتبع المشاهدات**: إحصائيات مفصلة

## التثبيت

### 1. متطلبات النظام
```bash
# FFmpeg (مطلوب لمعالجة الفيديو)
# Windows: تحميل من https://ffmpeg.org/download.html
# Linux:
sudo apt install ffmpeg

# التحقق من التثبيت
ffmpeg -version
```

### 2. إعداد Google Drive API

1. اذهب إلى [Google Cloud Console](https://console.cloud.google.com/)
2. أنشئ مشروع جديد
3. فعّل Google Drive API
4. أنشئ OAuth 2.0 credentials
5. حدد Authorized redirect URIs

### 3. إضافة المتغيرات البيئية

أضف التالي إلى ملف `.env`:

```env
# Google Drive
GOOGLE_DRIVE_CLIENT_ID=your_client_id
GOOGLE_DRIVE_CLIENT_SECRET=your_client_secret
GOOGLE_DRIVE_REFRESH_TOKEN=your_refresh_token
GOOGLE_DRIVE_FOLDER_ID=your_folder_id

# HLS Settings
HLS_SEGMENT_DURATION=10
HLS_ENCRYPTION_ENABLED=true

# FFmpeg
FFMPEG_PATH=/usr/bin/ffmpeg
FFPROBE_PATH=/usr/bin/ffprobe

# Security
VIDEO_URL_EXPIRY=3600
VIDEO_DOMAIN_LOCK=true
VIDEO_ALLOWED_DOMAINS=yourdomain.com,subdomain.yourdomain.com

# Player
VIDEO_WATERMARK=true
VIDEO_WATERMARK_TEXT="Your Brand"
```

### 4. تشغيل Migrations

```bash
php artisan migrate
```

### 5. إعداد Queue (مهم للمعالجة)

```bash
# في ملف .env
QUEUE_CONNECTION=database

# تشغيل Queue Worker
php artisan queue:work --queue=video-processing
```

## الاستخدام

### رفع فيديو
1. اذهب إلى Video Streaming > Upload Video
2. اسحب وأفلت الفيديو أو اختره من جهازك
3. أضف العنوان والوصف
4. اختر إعدادات الحماية
5. انقر "Upload Video"

### تضمين الفيديو
```html
<iframe 
    src="https://yourdomain.com/embed/VIDEO_UUID" 
    width="100%" 
    height="100%" 
    frameborder="0" 
    allowfullscreen>
</iframe>
```

### API استخدام

```php
// إنشاء token للوصول
POST /api/video/token/{uuid}
Headers: X-API-Key: your_api_key
Body: { "user_id": 123, "domain": "allowed-domain.com" }

// الحصول على معلومات الفيديو
GET /api/video/info/{uuid}

// قائمة فيديوهات المستخدم
GET /api/video/videos
Headers: Authorization: Bearer {sanctum_token}
```

## هيكل الملفات

```
Modules/VideoStreaming/
├── Config/
│   └── config.php              # إعدادات الموديول
├── Database/
│   └── Migrations/             # جداول قاعدة البيانات
├── Entities/                   # Models
│   ├── Video.php
│   ├── VideoQuality.php
│   ├── VideoEncryptionKey.php
│   ├── VideoAccessToken.php
│   ├── VideoAnalytics.php
│   └── VideoEmbedSettings.php
├── Http/
│   ├── Controllers/
│   │   ├── VideoStreamingController.php
│   │   ├── VideoStreamController.php
│   │   └── VideoSettingsController.php
│   └── Middleware/
│       ├── VideoAccessMiddleware.php
│       └── AntiDownloadMiddleware.php
├── Jobs/
│   └── ProcessVideoJob.php     # معالجة الفيديو في الخلفية
├── Providers/
│   ├── VideoStreamingServiceProvider.php
│   ├── RouteServiceProvider.php
│   └── AddMenuProvider.php
├── Resources/
│   ├── lang/                   # ملفات الترجمة
│   └── views/                  # واجهات المستخدم
├── Routes/
│   ├── web.php
│   └── api.php
└── Services/
    ├── GoogleDriveService.php
    ├── VideoProcessingService.php
    ├── HLSEncryptionService.php
    └── VideoStreamingService.php
```

## كيف يعمل النظام

### 1. مرحلة الرفع
```
المستخدم → رفع الفيديو → السيرفر → Google Drive (خاص)
                              ↓
                    حذف النسخة المحلية
```

### 2. مرحلة المعالجة
```
Google Drive → تحميل → FFmpeg → تقطيع HLS + تشفير AES-128
                                      ↓
                         رفع القطع المشفرة إلى Drive
```

### 3. مرحلة البث
```
طلب المشاهدة → التحقق من Token → التحقق من النطاق
                                      ↓
                    إرسال playlist m3u8 مع روابط موقعة
                                      ↓
                    طلب المفتاح → التحقق مجدداً → إرسال المفتاح
                                      ↓
                    تشغيل الفيديو في المشغل المحمي
```

## الأمان

### طبقات الحماية:
1. **تشفير AES-128**: كل قطعة مشفرة
2. **روابط موقعة**: تنتهي بعد وقت محدد
3. **قفل النطاق**: يعمل فقط على نطاقاتك
4. **Token Validation**: كل طلب يحتاج token صالح
5. **Anti-Download**: حظر برامج التحميل
6. **DevTools Detection**: إيقاف عند فتح المطور

## الدعم

للمساعدة أو الإبلاغ عن مشاكل، تواصل عبر Issues في المشروع.

## الترخيص

هذا الموديول جزء من مشروع خاص.
