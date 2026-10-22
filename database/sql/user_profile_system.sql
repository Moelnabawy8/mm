-- ============================================
-- User Profile System - Database Schema
-- Created: 2026-01-31
-- Author: Moelnabawy8
-- ============================================

-- Table: user_profiles
-- Description: Stores comprehensive user profile information including job details, responsibilities, and tasks
CREATE TABLE IF NOT EXISTS `user_profiles` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint(20) unsigned NOT NULL,
  `job_title` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `primary_role` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `official_responsibilities` text COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'JSON array of responsibilities',
  `daily_tasks` text COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'JSON array of daily tasks',
  `periodic_tasks` text COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'JSON array of periodic tasks',
  `work_details` text COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `task_execution_method` text COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `department_connections` text COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'JSON array of department IDs',
  `collaboration_details` text COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_by` int(11) NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `user_profiles_user_id_foreign` (`user_id`),
  CONSTRAINT `user_profiles_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: user_requests
-- Description: Stores user requests for tools, resources, and requirements
CREATE TABLE IF NOT EXISTS `user_requests` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint(20) unsigned NOT NULL,
  `title` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `type` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'requirement' COMMENT 'requirement, request, tool, resource',
  `priority` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'medium' COMMENT 'low, medium, high, urgent',
  `status` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending' COMMENT 'pending, approved, rejected, fulfilled',
  `approved_at` timestamp NULL DEFAULT NULL,
  `approved_by` bigint(20) unsigned DEFAULT NULL,
  `admin_notes` text COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_by` int(11) NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `user_requests_user_id_foreign` (`user_id`),
  KEY `user_requests_approved_by_index` (`approved_by`),
  CONSTRAINT `user_requests_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: user_subscriptions
-- Description: Stores custom user subscriptions that can be managed by users and admins
CREATE TABLE IF NOT EXISTS `user_subscriptions` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint(20) unsigned NOT NULL,
  `subscription_name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `plan_type` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `price` decimal(10,2) NOT NULL DEFAULT 0.00,
  `status` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'active' COMMENT 'active, expired, cancelled, pending',
  `start_date` date DEFAULT NULL,
  `end_date` date DEFAULT NULL,
  `notes` text COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_by` int(11) NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `user_subscriptions_user_id_foreign` (`user_id`),
  KEY `user_subscriptions_status_index` (`status`),
  CONSTRAINT `user_subscriptions_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- Sample Data (Optional)
-- ============================================

-- You can add sample data here if needed
-- Example:
-- INSERT INTO `user_profiles` (`user_id`, `job_title`, `primary_role`, `created_by`, `created_at`, `updated_at`) 
-- VALUES (1, 'System Administrator', 'IT Management', 1, NOW(), NOW());

-- ============================================
-- Indexes for Performance
-- ============================================

-- Add additional indexes if needed
-- CREATE INDEX idx_user_profiles_job_title ON user_profiles(job_title);
-- CREATE INDEX idx_user_requests_status ON user_requests(status);
-- CREATE INDEX idx_user_requests_priority ON user_requests(priority);

-- ============================================
-- End of SQL File
-- ============================================
