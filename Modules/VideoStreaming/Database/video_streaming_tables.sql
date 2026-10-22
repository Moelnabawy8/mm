-- =====================================================
-- Video Streaming Module - Database Tables
-- Run this SQL manually in phpMyAdmin or MySQL console
-- =====================================================

-- 1. Videos Table (الجدول الرئيسي للفيديوهات)
CREATE TABLE IF NOT EXISTS `vs_videos` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `uuid` CHAR(36) NOT NULL,
    `user_id` BIGINT UNSIGNED NOT NULL,
    `company_id` BIGINT UNSIGNED NULL,
    `title` VARCHAR(255) NOT NULL,
    `description` TEXT NULL,
    `original_filename` VARCHAR(255) NULL,
    `original_extension` VARCHAR(10) NULL,
    `original_size` BIGINT UNSIGNED NULL DEFAULT 0,
    `duration` INT UNSIGNED NULL DEFAULT 0,
    `width` INT UNSIGNED NULL,
    `height` INT UNSIGNED NULL,
    `thumbnail_path` VARCHAR(500) NULL,
    `google_drive_file_id` VARCHAR(255) NULL,
    `google_drive_folder_id` VARCHAR(255) NULL,
    `status` ENUM('uploading', 'uploaded', 'processing', 'ready', 'failed') NOT NULL DEFAULT 'uploading',
    `processing_error` TEXT NULL,
    `processed_at` TIMESTAMP NULL,
    `views_count` INT UNSIGNED NOT NULL DEFAULT 0,
    `unique_views_count` INT UNSIGNED NOT NULL DEFAULT 0,
    `is_public` TINYINT(1) NOT NULL DEFAULT 1,
    `password_protected` TINYINT(1) NOT NULL DEFAULT 0,
    `password` VARCHAR(255) NULL,
    `watermark_enabled` TINYINT(1) NOT NULL DEFAULT 1,
    `watermark_text` VARCHAR(100) NULL,
    `watermark_position` ENUM('top-left', 'top-right', 'bottom-left', 'bottom-right', 'center') DEFAULT 'top-right',
    `domain_lock_enabled` TINYINT(1) NOT NULL DEFAULT 1,
    `allowed_domains` JSON NULL,
    `download_enabled` TINYINT(1) NOT NULL DEFAULT 0,
    `share_enabled` TINYINT(1) NOT NULL DEFAULT 1,
    `comments_enabled` TINYINT(1) NOT NULL DEFAULT 0,
    `auto_play` TINYINT(1) NOT NULL DEFAULT 0,
    `loop_video` TINYINT(1) NOT NULL DEFAULT 0,
    `show_controls` TINYINT(1) NOT NULL DEFAULT 1,
    `max_views` INT UNSIGNED NULL,
    `expires_at` TIMESTAMP NULL,
    `metadata` JSON NULL,
    `created_at` TIMESTAMP NULL,
    `updated_at` TIMESTAMP NULL,
    `deleted_at` TIMESTAMP NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `vs_videos_uuid_unique` (`uuid`),
    KEY `vs_videos_user_id_index` (`user_id`),
    KEY `vs_videos_company_id_index` (`company_id`),
    KEY `vs_videos_status_index` (`status`),
    KEY `vs_videos_created_at_index` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. Video Qualities Table (جودات الفيديو المختلفة)
CREATE TABLE IF NOT EXISTS `vs_video_qualities` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `video_id` BIGINT UNSIGNED NOT NULL,
    `quality` VARCHAR(20) NOT NULL,
    `width` INT UNSIGNED NOT NULL,
    `height` INT UNSIGNED NOT NULL,
    `bitrate` VARCHAR(20) NULL,
    `playlist_filename` VARCHAR(255) NULL,
    `google_drive_folder_id` VARCHAR(255) NULL,
    `segments_count` INT UNSIGNED NOT NULL DEFAULT 0,
    `file_size` BIGINT UNSIGNED NULL DEFAULT 0,
    `status` ENUM('pending', 'processing', 'ready', 'failed') NOT NULL DEFAULT 'pending',
    `created_at` TIMESTAMP NULL,
    `updated_at` TIMESTAMP NULL,
    PRIMARY KEY (`id`),
    KEY `vs_video_qualities_video_id_index` (`video_id`),
    KEY `vs_video_qualities_quality_index` (`quality`),
    CONSTRAINT `vs_video_qualities_video_id_foreign` FOREIGN KEY (`video_id`) REFERENCES `vs_videos` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3. Video Encryption Keys Table (مفاتيح التشفير)
CREATE TABLE IF NOT EXISTS `vs_video_encryption_keys` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `video_id` BIGINT UNSIGNED NOT NULL,
    `key_id` VARCHAR(64) NOT NULL,
    `encryption_key` VARBINARY(32) NOT NULL,
    `iv` VARBINARY(16) NOT NULL,
    `segment_start` INT UNSIGNED NOT NULL DEFAULT 0,
    `segment_end` INT UNSIGNED NULL,
    `is_active` TINYINT(1) NOT NULL DEFAULT 1,
    `created_at` TIMESTAMP NULL,
    `updated_at` TIMESTAMP NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `vs_video_encryption_keys_key_id_unique` (`key_id`),
    KEY `vs_video_encryption_keys_video_id_index` (`video_id`),
    KEY `vs_video_encryption_keys_is_active_index` (`is_active`),
    CONSTRAINT `vs_video_encryption_keys_video_id_foreign` FOREIGN KEY (`video_id`) REFERENCES `vs_videos` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 4. Video Access Tokens Table (توكنات الوصول للبث)
CREATE TABLE IF NOT EXISTS `vs_video_access_tokens` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `video_id` BIGINT UNSIGNED NOT NULL,
    `user_id` BIGINT UNSIGNED NULL,
    `token` VARCHAR(64) NOT NULL,
    `ip_address` VARCHAR(45) NULL,
    `user_agent` TEXT NULL,
    `referer_domain` VARCHAR(255) NULL,
    `expires_at` TIMESTAMP NOT NULL,
    `last_accessed_at` TIMESTAMP NULL,
    `requests_count` INT UNSIGNED NOT NULL DEFAULT 0,
    `is_revoked` TINYINT(1) NOT NULL DEFAULT 0,
    `created_at` TIMESTAMP NULL,
    `updated_at` TIMESTAMP NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `vs_video_access_tokens_token_unique` (`token`),
    KEY `vs_video_access_tokens_video_id_index` (`video_id`),
    KEY `vs_video_access_tokens_user_id_index` (`user_id`),
    KEY `vs_video_access_tokens_expires_at_index` (`expires_at`),
    CONSTRAINT `vs_video_access_tokens_video_id_foreign` FOREIGN KEY (`video_id`) REFERENCES `vs_videos` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 5. Video Analytics Table (إحصائيات المشاهدة)
CREATE TABLE IF NOT EXISTS `vs_video_analytics` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `video_id` BIGINT UNSIGNED NOT NULL,
    `user_id` BIGINT UNSIGNED NULL,
    `session_id` VARCHAR(64) NOT NULL,
    `ip_address` VARCHAR(45) NULL,
    `country` VARCHAR(2) NULL,
    `city` VARCHAR(100) NULL,
    `device_type` VARCHAR(20) NULL,
    `browser` VARCHAR(50) NULL,
    `os` VARCHAR(50) NULL,
    `referer_url` TEXT NULL,
    `referer_domain` VARCHAR(255) NULL,
    `watch_duration` INT UNSIGNED NOT NULL DEFAULT 0,
    `total_duration` INT UNSIGNED NOT NULL DEFAULT 0,
    `completion_rate` DECIMAL(5,2) NOT NULL DEFAULT 0.00,
    `quality_watched` VARCHAR(20) NULL,
    `pauses_count` INT UNSIGNED NOT NULL DEFAULT 0,
    `seeks_count` INT UNSIGNED NOT NULL DEFAULT 0,
    `quality_changes_count` INT UNSIGNED NOT NULL DEFAULT 0,
    `started_at` TIMESTAMP NULL,
    `ended_at` TIMESTAMP NULL,
    `created_at` TIMESTAMP NULL,
    `updated_at` TIMESTAMP NULL,
    PRIMARY KEY (`id`),
    KEY `vs_video_analytics_video_id_index` (`video_id`),
    KEY `vs_video_analytics_user_id_index` (`user_id`),
    KEY `vs_video_analytics_session_id_index` (`session_id`),
    KEY `vs_video_analytics_created_at_index` (`created_at`),
    KEY `vs_video_analytics_referer_domain_index` (`referer_domain`),
    CONSTRAINT `vs_video_analytics_video_id_foreign` FOREIGN KEY (`video_id`) REFERENCES `vs_videos` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 6. Video Embed Settings Table (إعدادات التضمين)
CREATE TABLE IF NOT EXISTS `vs_video_embed_settings` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `video_id` BIGINT UNSIGNED NOT NULL,
    `autoplay` TINYINT(1) NOT NULL DEFAULT 0,
    `loop` TINYINT(1) NOT NULL DEFAULT 0,
    `muted` TINYINT(1) NOT NULL DEFAULT 0,
    `controls` TINYINT(1) NOT NULL DEFAULT 1,
    `fullscreen` TINYINT(1) NOT NULL DEFAULT 1,
    `color` VARCHAR(7) NULL DEFAULT '#2A1274',
    `logo_url` VARCHAR(500) NULL,
    `logo_position` VARCHAR(20) NULL DEFAULT 'top-right',
    `start_time` INT UNSIGNED NULL DEFAULT 0,
    `end_time` INT UNSIGNED NULL,
    `quality_selector` TINYINT(1) NOT NULL DEFAULT 1,
    `speed_control` TINYINT(1) NOT NULL DEFAULT 1,
    `keyboard_controls` TINYINT(1) NOT NULL DEFAULT 1,
    `responsive` TINYINT(1) NOT NULL DEFAULT 1,
    `custom_css` TEXT NULL,
    `created_at` TIMESTAMP NULL,
    `updated_at` TIMESTAMP NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `vs_video_embed_settings_video_id_unique` (`video_id`),
    CONSTRAINT `vs_video_embed_settings_video_id_foreign` FOREIGN KEY (`video_id`) REFERENCES `vs_videos` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- Add to migrations table (to prevent Laravel from running migrations again)
-- =====================================================
INSERT INTO `migrations` (`migration`, `batch`) VALUES
('2024_01_01_000001_create_videos_table', 99),
('2024_01_01_000002_create_video_qualities_table', 99),
('2024_01_01_000003_create_video_encryption_keys_table', 99),
('2024_01_01_000004_create_video_access_tokens_table', 99),
('2024_01_01_000005_create_video_analytics_table', 99),
('2024_01_01_000006_create_video_embed_settings_table', 99);

-- =====================================================
-- Success! Tables created successfully.
-- =====================================================
