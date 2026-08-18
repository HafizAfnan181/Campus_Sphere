-- phpMyAdmin SQL Dump
-- Database: social_site

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";

CREATE DATABASE IF NOT EXISTS `social_site` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
USE `social_site`;

-- Table: users
CREATE TABLE `users` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `username` varchar(50) NOT NULL,
  `email` varchar(100) NOT NULL,
  `password` varchar(255) NOT NULL,
  `full_name` varchar(100) DEFAULT NULL,
  `bio` text DEFAULT NULL,
  `profile_pic` varchar(255) DEFAULT 'default.jpg',
  `cover_photo` varchar(255) DEFAULT NULL,
  `email_verified` tinyint(1) DEFAULT 0,
  `verification_code` varchar(6) DEFAULT NULL,
  `reset_token` varchar(255) DEFAULT NULL,
  `reset_token_expiry` datetime DEFAULT NULL,
  `online_status` tinyint(1) DEFAULT 0,
  `last_seen` datetime DEFAULT current_timestamp(),
  `created_at` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `username` (`username`),
  UNIQUE KEY `email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Table: posts
CREATE TABLE `posts` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `content` text DEFAULT NULL,
  `image` varchar(255) DEFAULT NULL,
  `pinned` tinyint(1) DEFAULT 0,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Table: likes
CREATE TABLE `likes` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `post_id` int(11) NOT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_like` (`user_id`,`post_id`),
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`post_id`) REFERENCES `posts`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Table: comments
CREATE TABLE `comments` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `post_id` int(11) NOT NULL,
  `comment` text NOT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`post_id`) REFERENCES `posts`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Table: friends
CREATE TABLE `friends` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `sender_id` int(11) NOT NULL,
  `receiver_id` int(11) NOT NULL,
  `status` enum('pending','accepted','rejected') DEFAULT 'pending',
  `created_at` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  FOREIGN KEY (`sender_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`receiver_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Table: messages
CREATE TABLE `messages` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `sender_id` int(11) NOT NULL,
  `receiver_id` int(11) NOT NULL,
  `message` text NOT NULL,
  `seen` tinyint(1) DEFAULT 0,
  `created_at` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  FOREIGN KEY (`sender_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`receiver_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Table: notifications
CREATE TABLE `notifications` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `from_user_id` int(11) NOT NULL,
  `type` enum('like','comment','friend_request','friend_accepted','follow') NOT NULL,
  `post_id` int(11) DEFAULT NULL,
  `related_id` int(11) DEFAULT NULL,
  `message` text DEFAULT NULL,
  `seen` tinyint(1) DEFAULT 0,
  `created_at` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`from_user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Table: follows (one-directional — separate from the mutual "friends" system)
CREATE TABLE `follows` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `follower_id` int(11) NOT NULL,
  `following_id` int(11) NOT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_follow` (`follower_id`,`following_id`),
  FOREIGN KEY (`follower_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`following_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Table: saved_posts (per-account bookmarks — replaces the old localStorage-only version)
CREATE TABLE `saved_posts` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `post_id` int(11) NOT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_saved` (`user_id`,`post_id`),
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`post_id`) REFERENCES `posts`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Seed: CampusSphere Community system account.
-- This is the ONLY account the welcome post is ever attributed to, so it
-- never shows up under a real user's name. Password is a random unusable
-- hash — nobody is meant to log into this account.
INSERT INTO `users` (`username`, `email`, `password`, `full_name`, `bio`, `profile_pic`, `email_verified`, `created_at`)
VALUES ('campussphere', 'hafizafnan181@gmail.com', '$2y$10$P4ZUu0S1yQ8N2sQvT6zK6ObC1Q0nGZ8m2Vw2q8h6qk9dG6P6cO6Fu', 'CampusSphere Community', 'Official CampusSphere community account 🎓', 'default.jpg', 1, '2026-07-20 00:00:00');

-- Seed: the single shared welcome post, owned by the system account above,
-- dated 20/07/2026 12:00:00 AM as requested.
INSERT INTO `posts` (`user_id`, `content`, `pinned`, `created_at`)
VALUES (
  (SELECT id FROM (SELECT id FROM `users` WHERE `email` = 'hafizafnan181@gmail.com') AS tmp),
  '🎓 Welcome to CampusSphere!\nWhere Students Connect, Learn, and Grow Together.\n\nWelcome to CampusSphere — a community built exclusively for students to connect, collaborate, and create meaningful opportunities.\n\nHere, you can:\n🚀 Share your ideas, achievements, and experiences.\n📚 Discover educational content and campus updates.\n🤝 Connect with classmates and expand your academic network.\n💬 Engage in discussions, exchange knowledge, and support one another.\n🌟 Build your digital student profile and showcase your journey.\n\nCampusSphere isn''t just another social platform—it''s a place where friendships begin, ideas come to life, and future leaders grow together.\n\nStart exploring, create your first post, connect with fellow students, and become an active part of the CampusSphere community.\n\nTogether, let''s build a smarter, stronger, and more connected campus. 💙',
  1,
  '2026-07-20 00:00:00'
);

COMMIT;