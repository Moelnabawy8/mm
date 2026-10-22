-- ================================================================
-- Integration Hub Database Schema
-- n8n API Integration Module for Laravel ERP
-- ================================================================
-- Run this SQL file to create the required tables
-- Or use: php artisan migrate
-- ================================================================

SET FOREIGN_KEY_CHECKS=0;

-- ================================================================
-- Table: api_clients
-- Stores API client tokens and permissions for n8n integration
-- ================================================================

DROP TABLE IF EXISTS `api_clients`;

CREATE TABLE `api_clients` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `name` VARCHAR(255) NOT NULL COMMENT 'Client name (e.g., n8n Production)',
    `description` TEXT NULL COMMENT 'Optional description',
    `token_hash` VARCHAR(64) NOT NULL COMMENT 'SHA-256 hash of the API token',
    `token_prefix` VARCHAR(20) NOT NULL COMMENT 'First 10 chars for identification',
    `abilities` JSON NOT NULL COMMENT 'Array of granted scopes/permissions',
    `allowed_ips` JSON NULL COMMENT 'IP whitelist (null = allow all)',
    `rate_limit` INT UNSIGNED NOT NULL DEFAULT 60 COMMENT 'Requests per minute',
    `is_active` TINYINT(1) NOT NULL DEFAULT 1 COMMENT '1 = active, 0 = revoked',
    `last_used_at` TIMESTAMP NULL COMMENT 'Last API request timestamp',
    `total_requests` BIGINT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'Total API requests count',
    `created_by` BIGINT UNSIGNED NOT NULL COMMENT 'User ID who created this client',
    `expires_at` TIMESTAMP NULL COMMENT 'Token expiration date (null = never)',
    `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `api_clients_token_hash_unique` (`token_hash`),
    INDEX `api_clients_token_prefix_index` (`token_prefix`),
    INDEX `api_clients_is_active_index` (`is_active`),
    INDEX `api_clients_created_by_index` (`created_by`),
    INDEX `api_clients_expires_at_index` (`expires_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ================================================================
-- Table: api_logs
-- Black box logging for all API requests and responses
-- ================================================================

DROP TABLE IF EXISTS `api_logs`;

CREATE TABLE `api_logs` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `api_client_id` BIGINT UNSIGNED NULL COMMENT 'FK to api_clients (null if auth failed)',
    `method` VARCHAR(10) NOT NULL COMMENT 'HTTP method (GET, POST, PUT, DELETE)',
    `endpoint` VARCHAR(500) NOT NULL COMMENT 'API endpoint path',
    `full_url` VARCHAR(2000) NULL COMMENT 'Complete request URL',
    `headers` JSON NULL COMMENT 'Request headers (sanitized)',
    `payload` JSON NULL COMMENT 'Request body/payload',
    `query_params` JSON NULL COMMENT 'Query string parameters',
    `response_code` INT UNSIGNED NULL COMMENT 'HTTP response status code',
    `response_body` JSON NULL COMMENT 'Response data (truncated if large)',
    `response_time` DECIMAL(10,2) NULL COMMENT 'Response time in milliseconds',
    `ip_address` VARCHAR(45) NOT NULL COMMENT 'Client IP address (IPv4/IPv6)',
    `user_agent` VARCHAR(500) NULL COMMENT 'Client user agent string',
    `error_message` TEXT NULL COMMENT 'Error message if request failed',
    `exception_trace` TEXT NULL COMMENT 'Stack trace for debugging',
    `ability_used` VARCHAR(100) NULL COMMENT 'Scope/ability checked',
    `ability_granted` TINYINT(1) NULL COMMENT 'Whether ability was granted',
    `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    INDEX `api_logs_api_client_id_index` (`api_client_id`),
    INDEX `api_logs_method_index` (`method`),
    INDEX `api_logs_response_code_index` (`response_code`),
    INDEX `api_logs_ip_address_index` (`ip_address`),
    INDEX `api_logs_created_at_index` (`created_at`),
    INDEX `api_logs_endpoint_index` (`endpoint`(191)),
    CONSTRAINT `api_logs_api_client_id_foreign` 
        FOREIGN KEY (`api_client_id`) 
        REFERENCES `api_clients` (`id`) 
        ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS=1;

-- ================================================================
-- Sample Data (Optional - Remove in production)
-- ================================================================

-- Insert a sample API client for testing
-- Token: n8n_test_token_12345 (hash this in production!)
-- INSERT INTO `api_clients` (`name`, `description`, `token_hash`, `token_prefix`, `abilities`, `rate_limit`, `is_active`, `created_by`) VALUES
-- ('Test Client', 'For development testing', SHA2('n8n_test_token_12345', 256), 'n8n_test_t', '["*"]', 60, 1, 1);

-- ================================================================
-- Useful Queries
-- ================================================================

-- Get all active clients
-- SELECT * FROM api_clients WHERE is_active = 1 AND (expires_at IS NULL OR expires_at > NOW());

-- Get today's request count per client
-- SELECT c.name, COUNT(l.id) as requests 
-- FROM api_clients c 
-- LEFT JOIN api_logs l ON c.id = l.api_client_id AND DATE(l.created_at) = CURDATE()
-- GROUP BY c.id;

-- Get failed requests (4xx, 5xx)
-- SELECT * FROM api_logs WHERE response_code >= 400 ORDER BY created_at DESC LIMIT 100;

-- Get requests per hour (last 24h)
-- SELECT HOUR(created_at) as hour, COUNT(*) as count 
-- FROM api_logs 
-- WHERE created_at >= NOW() - INTERVAL 24 HOUR 
-- GROUP BY HOUR(created_at);

-- Get top endpoints
-- SELECT endpoint, COUNT(*) as hits 
-- FROM api_logs 
-- GROUP BY endpoint 
-- ORDER BY hits DESC 
-- LIMIT 10;

-- Get average response time per endpoint
-- SELECT endpoint, AVG(response_time) as avg_time, COUNT(*) as requests
-- FROM api_logs 
-- WHERE response_time IS NOT NULL
-- GROUP BY endpoint 
-- ORDER BY avg_time DESC;

-- Clean old logs (keep last 30 days)
-- DELETE FROM api_logs WHERE created_at < NOW() - INTERVAL 30 DAY;

-- ================================================================
-- Indexes Explanation
-- ================================================================
-- token_hash: Unique index for fast token lookup during authentication
-- token_prefix: Index for identifying tokens in logs without exposing full hash
-- is_active: Filter active/revoked clients quickly
-- created_by: Find all clients created by a specific admin
-- expires_at: Filter expired tokens efficiently
-- api_client_id: Join logs with clients
-- method: Filter by HTTP method (GET, POST, etc.)
-- response_code: Filter success/error responses
-- ip_address: Security analysis by IP
-- created_at: Time-based queries and cleanup
-- endpoint: Filter by API endpoint (limited to 191 chars for utf8mb4)

-- ================================================================
-- End of Schema
-- ================================================================
