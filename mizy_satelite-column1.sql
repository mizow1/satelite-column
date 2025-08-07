-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- ホスト: mysql3102.db.sakura.ne.jp
-- 生成日時: 2025 年 8 月 07 日 16:25
-- サーバのバージョン： 8.0.39
-- PHP のバージョン: 8.2.16

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- データベース: `mizy_satelite-column1`
--

-- --------------------------------------------------------

--
-- テーブルの構造 `ai_generation_logs`
--

CREATE TABLE `ai_generation_logs` (
  `id` int NOT NULL,
  `article_id` int DEFAULT NULL,
  `ai_model` varchar(50) DEFAULT NULL,
  `prompt` text,
  `response` text,
  `generation_time` decimal(10,2) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `user_id` int DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- テーブルの構造 `ai_usage_logs`
--

CREATE TABLE `ai_usage_logs` (
  `id` int NOT NULL,
  `site_id` int DEFAULT NULL,
  `article_id` int DEFAULT NULL,
  `ai_model` varchar(50) NOT NULL,
  `usage_type` enum('site_analysis','article_outline','article_generation','additional_outline') NOT NULL,
  `prompt_text` text,
  `response_text` text,
  `tokens_used` int DEFAULT '0',
  `processing_time` decimal(10,2) DEFAULT '0.00',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `user_id` int DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- テーブルの構造 `articles`
--

CREATE TABLE `articles` (
  `id` int NOT NULL,
  `site_id` int DEFAULT NULL,
  `title` varchar(500) NOT NULL,
  `seo_keywords` text,
  `summary` text,
  `content` text,
  `ai_model` varchar(50) DEFAULT NULL,
  `status` enum('draft','generated','published') DEFAULT 'draft',
  `publish_date` datetime DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `user_id` int DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- テーブルの構造 `multilingual_articles`
--

CREATE TABLE `multilingual_articles` (
  `id` int NOT NULL,
  `original_article_id` int NOT NULL,
  `language_code` varchar(10) NOT NULL,
  `title` varchar(500) NOT NULL,
  `seo_keywords` text,
  `summary` text,
  `content` longtext,
  `ai_model` varchar(50) DEFAULT NULL,
  `status` enum('draft','generated','published') DEFAULT 'draft',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `user_id` int DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- テーブルの構造 `multilingual_settings`
--

CREATE TABLE `multilingual_settings` (
  `id` int NOT NULL,
  `site_id` int NOT NULL,
  `language_code` varchar(10) NOT NULL,
  `language_name` varchar(50) NOT NULL,
  `is_enabled` tinyint(1) DEFAULT '1',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `user_id` int DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- テーブルの構造 `reference_urls`
--

CREATE TABLE `reference_urls` (
  `id` int NOT NULL,
  `site_id` int DEFAULT NULL,
  `url` varchar(1000) NOT NULL,
  `is_selected` tinyint(1) DEFAULT '1',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `user_id` int DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- テーブルの構造 `sites`
--

CREATE TABLE `sites` (
  `id` int NOT NULL,
  `name` varchar(255) NOT NULL,
  `url` varchar(500) NOT NULL,
  `features` text,
  `keywords` text,
  `analysis_result` text,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `ai_model` varchar(50) DEFAULT NULL,
  `user_id` int DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- テーブルの構造 `site_analysis_history`
--

CREATE TABLE `site_analysis_history` (
  `id` int NOT NULL,
  `site_id` int DEFAULT NULL,
  `analysis_date` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `analysis_result` text,
  `ai_model` varchar(50) DEFAULT NULL,
  `user_id` int DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- テーブルの構造 `users`
--

CREATE TABLE `users` (
  `id` int NOT NULL,
  `email` varchar(255) NOT NULL,
  `password` varchar(255) NOT NULL,
  `name` varchar(100) DEFAULT '',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `last_login` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- ダンプしたテーブルのインデックス
--

--
-- テーブルのインデックス `ai_generation_logs`
--
ALTER TABLE `ai_generation_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_ai_logs_article_id` (`article_id`),
  ADD KEY `idx_ai_generation_logs_user_id` (`user_id`);

--
-- テーブルのインデックス `ai_usage_logs`
--
ALTER TABLE `ai_usage_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_ai_usage_logs_site_id` (`site_id`),
  ADD KEY `idx_ai_usage_logs_article_id` (`article_id`),
  ADD KEY `idx_ai_usage_logs_type` (`usage_type`),
  ADD KEY `idx_ai_usage_logs_model` (`ai_model`),
  ADD KEY `idx_ai_usage_logs_user_id` (`user_id`);

--
-- テーブルのインデックス `articles`
--
ALTER TABLE `articles`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_articles_site_id` (`site_id`),
  ADD KEY `idx_articles_status` (`status`),
  ADD KEY `idx_articles_user_id` (`user_id`);

--
-- テーブルのインデックス `multilingual_articles`
--
ALTER TABLE `multilingual_articles`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_article_language` (`original_article_id`,`language_code`),
  ADD KEY `idx_multilingual_articles_original_id` (`original_article_id`),
  ADD KEY `idx_multilingual_articles_language` (`language_code`),
  ADD KEY `idx_multilingual_articles_status` (`status`),
  ADD KEY `idx_multilingual_articles_user_id` (`user_id`);

--
-- テーブルのインデックス `multilingual_settings`
--
ALTER TABLE `multilingual_settings`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_site_language` (`site_id`,`language_code`),
  ADD KEY `idx_multilingual_settings_site_id` (`site_id`),
  ADD KEY `idx_multilingual_settings_enabled` (`is_enabled`),
  ADD KEY `idx_multilingual_settings_user_id` (`user_id`);

--
-- テーブルのインデックス `reference_urls`
--
ALTER TABLE `reference_urls`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_reference_urls_site_id` (`site_id`),
  ADD KEY `idx_reference_urls_user_id` (`user_id`);

--
-- テーブルのインデックス `sites`
--
ALTER TABLE `sites`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_sites_user_id` (`user_id`);

--
-- テーブルのインデックス `site_analysis_history`
--
ALTER TABLE `site_analysis_history`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_site_analysis_site_id` (`site_id`),
  ADD KEY `idx_site_analysis_history_user_id` (`user_id`);

--
-- テーブルのインデックス `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`);

--
-- ダンプしたテーブルの AUTO_INCREMENT
--

--
-- テーブルの AUTO_INCREMENT `ai_generation_logs`
--
ALTER TABLE `ai_generation_logs`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- テーブルの AUTO_INCREMENT `ai_usage_logs`
--
ALTER TABLE `ai_usage_logs`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- テーブルの AUTO_INCREMENT `articles`
--
ALTER TABLE `articles`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- テーブルの AUTO_INCREMENT `multilingual_articles`
--
ALTER TABLE `multilingual_articles`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- テーブルの AUTO_INCREMENT `multilingual_settings`
--
ALTER TABLE `multilingual_settings`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- テーブルの AUTO_INCREMENT `reference_urls`
--
ALTER TABLE `reference_urls`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- テーブルの AUTO_INCREMENT `sites`
--
ALTER TABLE `sites`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- テーブルの AUTO_INCREMENT `site_analysis_history`
--
ALTER TABLE `site_analysis_history`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- テーブルの AUTO_INCREMENT `users`
--
ALTER TABLE `users`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- ダンプしたテーブルの制約
--

--
-- テーブルの制約 `ai_generation_logs`
--
ALTER TABLE `ai_generation_logs`
  ADD CONSTRAINT `ai_generation_logs_ibfk_1` FOREIGN KEY (`article_id`) REFERENCES `articles` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_ai_generation_logs_user_id` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- テーブルの制約 `ai_usage_logs`
--
ALTER TABLE `ai_usage_logs`
  ADD CONSTRAINT `ai_usage_logs_ibfk_1` FOREIGN KEY (`site_id`) REFERENCES `sites` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `ai_usage_logs_ibfk_2` FOREIGN KEY (`article_id`) REFERENCES `articles` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_ai_usage_logs_user_id` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- テーブルの制約 `articles`
--
ALTER TABLE `articles`
  ADD CONSTRAINT `articles_ibfk_1` FOREIGN KEY (`site_id`) REFERENCES `sites` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_articles_user_id` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- テーブルの制約 `multilingual_articles`
--
ALTER TABLE `multilingual_articles`
  ADD CONSTRAINT `fk_multilingual_articles_user_id` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `multilingual_articles_ibfk_1` FOREIGN KEY (`original_article_id`) REFERENCES `articles` (`id`) ON DELETE CASCADE;

--
-- テーブルの制約 `multilingual_settings`
--
ALTER TABLE `multilingual_settings`
  ADD CONSTRAINT `fk_multilingual_settings_user_id` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `multilingual_settings_ibfk_1` FOREIGN KEY (`site_id`) REFERENCES `sites` (`id`) ON DELETE CASCADE;

--
-- テーブルの制約 `reference_urls`
--
ALTER TABLE `reference_urls`
  ADD CONSTRAINT `fk_reference_urls_user_id` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `reference_urls_ibfk_1` FOREIGN KEY (`site_id`) REFERENCES `sites` (`id`) ON DELETE CASCADE;

--
-- テーブルの制約 `sites`
--
ALTER TABLE `sites`
  ADD CONSTRAINT `fk_sites_user_id` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- テーブルの制約 `site_analysis_history`
--
ALTER TABLE `site_analysis_history`
  ADD CONSTRAINT `fk_site_analysis_history_user_id` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `site_analysis_history_ibfk_1` FOREIGN KEY (`site_id`) REFERENCES `sites` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
