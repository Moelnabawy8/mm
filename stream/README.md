# SecureStream — Enterprise Secure Video Hosting Platform

A production-ready, Vimeo-like private video hosting platform built with Laravel 11. Designed for hosting online course videos with full anti-piracy protection, HLS encrypted streaming, Google Drive storage, and secure embedding.

---

## Features

- **HLS AES-128 Encrypted Streaming** — Video segments encrypted with unique keys per video
- **Google Drive API Storage** — Private storage with auto-fallback to local on quota exceeded
- **5-Layer Security Architecture** — UUID hashing, signed temp URLs, streaming proxy, HLS encryption, domain restriction
- **Chunked Video Upload** — Supports large files (up to 10GB) with background FFmpeg processing
- **Dynamic Watermark** — User email + timestamp overlay that repositions periodically
- **Secure Embedding** — Domain-whitelisted iframe embeds with auto-refreshing tokens
- **Admin Dashboard** — Full CRUD for courses, videos, users, enrollments, domains, analytics
- **Anti-Piracy** — No direct file access, disabled right-click/download, inspection blocking
- **Analytics** — Watch duration, completion %, device detection, suspicious activity flagging

---

## Tech Stack

| Component | Technology |
|-----------|-----------|
| Backend | Laravel 11, PHP 8.2+ |
| Database | MySQL (phpMyAdmin compatible) |
| Auth | Laravel Sanctum (session-based) |
| Queue | Database driver (Redis optional) |
| Storage | Google Drive API / Local |
| Video Processing | FFmpeg |
| Frontend | Blade + Tailwind CSS + Alpine.js |
| Video Player | Video.js 8 with HLS support |

---

## Requirements

- PHP 8.2+
- Composer 2.x
- MySQL 5.7+ or 8.x
- FFmpeg (for video processing)
- Node.js (optional, only for Vite assets)

---

## Installation

### 1. Clone & Install Dependencies

```bash
git clone <repo-url> securestream
cd securestream
composer install
```

### 2. Environment Setup

```bash
cp .env.example .env
php artisan key:generate
```

Edit `.env` with your database credentials:

```
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=securestream
DB_USERNAME=root
DB_PASSWORD=your_password
```

### 3. Database Setup

```bash
php artisan migrate
php artisan db:seed
```

This creates the default admin user:
- **Email:** `admin@securestream.com`
- **Password:** `password`

### 4. Storage Link

```bash
php artisan storage:link
```

### 5. Start the Application

```bash
php artisan serve
```

Visit `http://localhost:8000/login`

### 6. Start Queue Worker (for video processing)

```bash
php artisan queue:work --timeout=3600
```

---

## FFmpeg Installation

### macOS
```bash
brew install ffmpeg
```

### Ubuntu/Debian
```bash
sudo apt update && sudo apt install ffmpeg -y
```

### CentOS/RHEL
```bash
sudo yum install epel-release -y
sudo yum install ffmpeg ffmpeg-devel -y
```

### Windows
Download from https://ffmpeg.org/download.html and add to system PATH.

Set the path in `.env`:
```
FFMPEG_PATH=/usr/bin/ffmpeg
```

---

## Google Drive Setup

### 1. Create Google Cloud Project

1. Go to [Google Cloud Console](https://console.cloud.google.com/)
2. Create a new project
3. Enable the **Google Drive API**

### 2. Create OAuth Credentials

1. Go to **APIs & Services > Credentials**
2. Click **Create Credentials > OAuth 2.0 Client ID**
3. Application type: **Web application**
4. Add `http://localhost:8000` to authorized redirect URIs
5. Download the credentials

### 3. Generate Refresh Token

Use the [OAuth 2.0 Playground](https://developers.google.com/oauthplayground/):

1. Click the gear icon, check "Use your own OAuth credentials"
2. Enter your Client ID and Client Secret
3. In Step 1, select `https://www.googleapis.com/auth/drive`
4. Authorize and exchange for tokens
5. Copy the **Refresh Token**

### 4. Create Private Folder

1. Go to Google Drive
2. Create a folder for SecureStream videos
3. Copy the folder ID from the URL

### 5. Configure `.env`

```
STORAGE_DRIVER=google
GOOGLE_DRIVE_CLIENT_ID=your_client_id
GOOGLE_DRIVE_CLIENT_SECRET=your_client_secret
GOOGLE_DRIVE_REFRESH_TOKEN=your_refresh_token
GOOGLE_DRIVE_FOLDER_ID=your_folder_id
```

### Quota Fallback

If Google Drive quota is exceeded, uploads automatically fall back to local storage. A warning is logged and the video's `storage_driver` field is set to `local`.

---

## Queue Setup

### Database Driver (Default)

Already configured. Just run:
```bash
php artisan queue:work --timeout=3600
```

### Redis Driver (Optional)

1. Install Redis on your server
2. Install PHP Redis extension: `pecl install redis`
3. Update `.env`:
```
QUEUE_CONNECTION=redis
CACHE_STORE=redis
```
4. Run queue worker:
```bash
php artisan queue:work redis --timeout=3600
```

### Supervisor (Production)

Create `/etc/supervisor/conf.d/securestream-worker.conf`:

```ini
[program:securestream-worker]
process_name=%(program_name)s_%(process_num)02d
command=php /var/www/securestream/artisan queue:work --sleep=3 --tries=3 --timeout=3600
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
user=www-data
numprocs=2
redirect_stderr=true
stdout_logfile=/var/www/securestream/storage/logs/worker.log
stopwaitsecs=3600
```

```bash
sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl start securestream-worker:*
```

---

## Deployment

### Shared Hosting / cPanel

1. Upload files to your hosting (public_html should point to `/public`)
2. Set PHP version to 8.2+
3. Create MySQL database via cPanel
4. Update `.env` with database credentials
5. SSH or use terminal to run:
   ```bash
   php artisan migrate --seed
   php artisan storage:link
   ```
6. Set up a cron job for the scheduler:
   ```
   * * * * * cd /home/user/securestream && php artisan schedule:run >> /dev/null 2>&1
   ```
7. Use cPanel's "Setup Node.js App" or background process to run queue worker

### VPS (Nginx)

1. Copy `nginx.conf.example` to your Nginx sites-available
2. Update `server_name` and `root` path
3. Enable the site and reload Nginx
4. Set up Supervisor for queue workers
5. Set up SSL with Let's Encrypt:
   ```bash
   sudo certbot --nginx -d yourdomain.com
   ```

### VPS (Apache)

1. The included `.htaccess` handles routing and security
2. Enable required modules:
   ```bash
   sudo a2enmod rewrite headers
   sudo systemctl restart apache2
   ```
3. Set `AllowOverride All` in your Apache vhost config

---

## Security Architecture

| Layer | Protection |
|-------|-----------|
| **Layer 1** | UUID primary keys + encrypted hash tokens — never expose numeric IDs |
| **Layer 2** | Signed temporary streaming URLs with 5-10 minute expiry |
| **Layer 3** | Streaming proxy — Laravel streams bytes, never exposes storage URLs |
| **Layer 4** | HLS AES-128 encryption — each video has unique encryption key |
| **Layer 5** | Domain restriction — embed whitelist enforced via HTTP Referer |

Additional hardening:
- CSRF protection on all forms
- API rate limiting (60 req/min)
- Hotlinking prevention
- Directory browsing disabled
- Sensitive file access blocked
- Security headers (X-Content-Type-Options, X-Frame-Options, etc.)
- Dynamic watermark with user email overlay

---

## Admin Credentials

After seeding:
- **URL:** `http://yourdomain.com/login`
- **Email:** `admin@securestream.com`
- **Password:** `password`

**Change the password immediately after first login.**

---

## Project Structure

```
app/
├── Exceptions/
│   └── StorageQuotaExceededException.php
├── Http/
│   ├── Controllers/
│   │   ├── Admin/
│   │   │   ├── AnalyticsController.php
│   │   │   ├── CourseController.php
│   │   │   ├── DashboardController.php
│   │   │   ├── DomainController.php
│   │   │   ├── EnrollmentController.php
│   │   │   ├── SettingsController.php
│   │   │   ├── UserController.php
│   │   │   └── VideoController.php
│   │   ├── Auth/
│   │   │   └── LoginController.php
│   │   ├── EmbedController.php
│   │   └── StreamController.php
│   └── Middleware/
│       ├── AdminMiddleware.php
│       ├── CheckDomainMiddleware.php
│       ├── CheckEnrollmentMiddleware.php
│       └── PreventHotlinking.php
├── Jobs/
│   └── ProcessVideoJob.php
├── Models/
│   ├── AllowedDomain.php
│   ├── Course.php
│   ├── Enrollment.php
│   ├── User.php
│   ├── Video.php
│   └── VideoView.php
├── Providers/
│   └── SecureStreamServiceProvider.php
└── Services/
    ├── HlsEncryptionService.php
    ├── StreamService.php
    ├── VideoProcessingService.php
    └── Storage/
        ├── GoogleDriveStorageService.php
        ├── LocalStorageService.php
        ├── StorageInterface.php
        └── StorageManager.php
```

---

## Environment Variables Reference

| Variable | Default | Description |
|----------|---------|-------------|
| `STORAGE_DRIVER` | `local` | Storage backend: `local` or `google` |
| `GOOGLE_DRIVE_CLIENT_ID` | — | Google OAuth Client ID |
| `GOOGLE_DRIVE_CLIENT_SECRET` | — | Google OAuth Client Secret |
| `GOOGLE_DRIVE_REFRESH_TOKEN` | — | Google OAuth Refresh Token |
| `GOOGLE_DRIVE_FOLDER_ID` | — | Google Drive folder ID for uploads |
| `FFMPEG_PATH` | `/usr/bin/ffmpeg` | Path to FFmpeg binary |
| `STREAM_TOKEN_EXPIRY` | `10` | Stream token expiry in minutes |
| `HLS_KEY_EXPIRY` | `5` | HLS encryption key token expiry in minutes |

---

## License

Proprietary. All rights reserved.
