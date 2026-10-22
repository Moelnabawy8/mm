-- =====================================================
-- Video Streaming Module - UPDATE Tables
-- Run this if you already created the tables and need to add new columns
-- =====================================================

-- Add new columns to vs_videos table
ALTER TABLE `vs_videos` 
ADD COLUMN IF NOT EXISTS `company_id` BIGINT UNSIGNED NULL AFTER `user_id`,
ADD COLUMN IF NOT EXISTS `password_protected` TINYINT(1) NOT NULL DEFAULT 0 AFTER `is_public`,
ADD COLUMN IF NOT EXISTS `password` VARCHAR(255) NULL AFTER `password_protected`,
ADD COLUMN IF NOT EXISTS `watermark_position` ENUM('top-left', 'top-right', 'bottom-left', 'bottom-right', 'center') DEFAULT 'top-right' AFTER `watermark_text`,
ADD COLUMN IF NOT EXISTS `download_enabled` TINYINT(1) NOT NULL DEFAULT 0 AFTER `allowed_domains`,
ADD COLUMN IF NOT EXISTS `share_enabled` TINYINT(1) NOT NULL DEFAULT 1 AFTER `download_enabled`,
ADD COLUMN IF NOT EXISTS `comments_enabled` TINYINT(1) NOT NULL DEFAULT 0 AFTER `share_enabled`,
ADD COLUMN IF NOT EXISTS `auto_play` TINYINT(1) NOT NULL DEFAULT 0 AFTER `comments_enabled`,
ADD COLUMN IF NOT EXISTS `loop_video` TINYINT(1) NOT NULL DEFAULT 0 AFTER `auto_play`,
ADD COLUMN IF NOT EXISTS `show_controls` TINYINT(1) NOT NULL DEFAULT 1 AFTER `loop_video`,
ADD COLUMN IF NOT EXISTS `max_views` INT UNSIGNED NULL AFTER `show_controls`,
ADD COLUMN IF NOT EXISTS `expires_at` TIMESTAMP NULL AFTER `max_views`;

-- Add index for company_id
ALTER TABLE `vs_videos` ADD INDEX IF NOT EXISTS `vs_videos_company_id_index` (`company_id`);

-- =====================================================
-- If the above fails (MySQL < 8.0 doesn't support IF NOT EXISTS for columns)
-- Use these individual statements instead:
-- =====================================================

-- Uncomment and run individually if needed:
-- ALTER TABLE `vs_videos` ADD COLUMN `company_id` BIGINT UNSIGNED NULL AFTER `user_id`;
-- ALTER TABLE `vs_videos` ADD COLUMN `password_protected` TINYINT(1) NOT NULL DEFAULT 0 AFTER `is_public`;
-- ALTER TABLE `vs_videos` ADD COLUMN `password` VARCHAR(255) NULL AFTER `password_protected`;
-- ALTER TABLE `vs_videos` ADD COLUMN `watermark_position` ENUM('top-left', 'top-right', 'bottom-left', 'bottom-right', 'center') DEFAULT 'top-right' AFTER `watermark_text`;
-- ALTER TABLE `vs_videos` ADD COLUMN `download_enabled` TINYINT(1) NOT NULL DEFAULT 0 AFTER `allowed_domains`;
-- ALTER TABLE `vs_videos` ADD COLUMN `share_enabled` TINYINT(1) NOT NULL DEFAULT 1 AFTER `download_enabled`;
-- ALTER TABLE `vs_videos` ADD COLUMN `comments_enabled` TINYINT(1) NOT NULL DEFAULT 0 AFTER `share_enabled`;
-- ALTER TABLE `vs_videos` ADD COLUMN `auto_play` TINYINT(1) NOT NULL DEFAULT 0 AFTER `comments_enabled`;
-- ALTER TABLE `vs_videos` ADD COLUMN `loop_video` TINYINT(1) NOT NULL DEFAULT 0 AFTER `auto_play`;
-- ALTER TABLE `vs_videos` ADD COLUMN `show_controls` TINYINT(1) NOT NULL DEFAULT 1 AFTER `loop_video`;
-- ALTER TABLE `vs_videos` ADD COLUMN `max_views` INT UNSIGNED NULL AFTER `show_controls`;
-- ALTER TABLE `vs_videos` ADD COLUMN `expires_at` TIMESTAMP NULL AFTER `max_views`;
