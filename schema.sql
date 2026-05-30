-- ================================
-- 1. Creation of Database
-- ================================
CREATE DATABASE IF NOT EXISTS nmims_quiz_app;
USE nmims_quiz_app;

-- ================================
-- 2. Lookup Tables
-- ================================
CREATE TABLE `roles` (
  `id`   INT AUTO_INCREMENT PRIMARY KEY,
  `name` VARCHAR(30) NOT NULL UNIQUE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `schools` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(50) NOT NULL UNIQUE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `courses` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `school_id` INT NOT NULL,
    `name` VARCHAR(100) NOT NULL,
    `code` VARCHAR(20) UNIQUE,
    FOREIGN KEY (`school_id`) REFERENCES `schools`(`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `question_types` (
  `id`   INT AUTO_INCREMENT PRIMARY KEY,
  `name` VARCHAR(30) NOT NULL UNIQUE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `question_difficulties` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `level` VARCHAR(20) NOT NULL UNIQUE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `exam_statuses` (
  `id`   INT AUTO_INCREMENT PRIMARY KEY,
  `name` VARCHAR(30) NOT NULL UNIQUE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ================================
-- 3. Core User & Profile Tables
-- ================================
CREATE TABLE `users` (
  `id`                INT AUTO_INCREMENT PRIMARY KEY,
  `email`             VARCHAR(100) NOT NULL UNIQUE,
  `password_hash`     VARCHAR(255) NOT NULL,
  `active_session_id` VARCHAR(255) DEFAULT NULL,
  `role_id`           INT          NOT NULL,
  `is_active`         TINYINT(1)   NOT NULL DEFAULT 1,
  `created_at`        TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`        TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (`role_id`) REFERENCES `roles`(`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `students` (
  `user_id`         INT           PRIMARY KEY,
  `name`            VARCHAR(100)  NOT NULL,
  `sap_id`          VARCHAR(20)   NOT NULL UNIQUE,
  `roll_no`         VARCHAR(20)   NOT NULL UNIQUE,
  `course_id`       INT           NOT NULL,
  `batch`           VARCHAR(50)   NOT NULL,
  `graduation_year` YEAR          NOT NULL,
  FOREIGN KEY (`user_id`)   REFERENCES `users`(`id`),
  FOREIGN KEY (`course_id`) REFERENCES `courses`(`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `faculties` (
  `user_id`    INT          PRIMARY KEY,
  `name`       VARCHAR(100) NOT NULL,
  `sap_id`     VARCHAR(20)  NOT NULL UNIQUE,
  `school_id`  INT          DEFAULT NULL,
  `is_visiting` TINYINT(1)  NOT NULL DEFAULT 0,
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`),
  FOREIGN KEY (`school_id`) REFERENCES `schools`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `placement_officers` (
  `user_id`    INT          PRIMARY KEY,
  `name`       VARCHAR(100) NOT NULL,
  `department` VARCHAR(100),
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `admins` (
  `user_id`    INT          PRIMARY KEY,
  `name`       VARCHAR(100) NOT NULL,
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `heads` (
  `id`      INT AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT NOT NULL UNIQUE,
  `name`    VARCHAR(100) NOT NULL,
  `school_id` INT DEFAULT NULL,
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`school_id`) REFERENCES `schools`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


-- ================================
-- 3.5. Specializations
-- ================================
-- MODIFIED: Added school_id and foreign key constraint
CREATE TABLE `specializations` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(255) NOT NULL,
    `description` TEXT,
    `school_id` INT NOT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (`school_id`) REFERENCES `schools`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `student_specializations` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `student_id` INT NOT NULL,
    `specialization_id` INT NOT NULL,
    FOREIGN KEY (`student_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`specialization_id`) REFERENCES `specializations`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


-- ================================
-- 4. Exams & Questions
-- ================================
CREATE TABLE `quizzes` (
  `id`                         INT AUTO_INCREMENT PRIMARY KEY,
  `title`                      VARCHAR(150) NOT NULL,
  `faculty_id`                 INT          NOT NULL,
  `course_id`                  INT          NOT NULL,
  `graduation_year`            YEAR         DEFAULT NULL,
  `specialization_id`          INT          DEFAULT NULL,
  `sap_id_range_start`         BIGINT       NULL DEFAULT NULL,
  `sap_id_range_end`           BIGINT       NULL DEFAULT NULL,
  `show_results_immediately`   BOOLEAN      NOT NULL DEFAULT TRUE,
  `start_time`                 DATETIME     NOT NULL,
  `end_time`                   DATETIME     NOT NULL,
  `duration_minutes`           INT          NOT NULL,
  `status_id`                  INT          NOT NULL,
  `config_easy_count`          INT          NOT NULL DEFAULT 0,
  `config_medium_count`        INT          NOT NULL DEFAULT 0,
  `config_hard_count`          INT          NOT NULL DEFAULT 0,
  `created_at`                 TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`                 TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (`faculty_id`) REFERENCES `faculties`(`user_id`),
  FOREIGN KEY (`course_id`)  REFERENCES `courses`(`id`),
  FOREIGN KEY (`status_id`)  REFERENCES `exam_statuses`(`id`),
  FOREIGN KEY (`specialization_id`) REFERENCES `specializations`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `questions` (
  `id`                  INT AUTO_INCREMENT PRIMARY KEY,
  `quiz_id`             INT            NOT NULL,
  `question_type_id`    INT            NOT NULL,
  `difficulty_id`       INT            NOT NULL,
  `question_text`       TEXT           NOT NULL,
  `points`              DECIMAL(5,2)   NOT NULL DEFAULT 1.00,
  `created_at`          TIMESTAMP      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`quiz_id`)          REFERENCES `quizzes`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`question_type_id`) REFERENCES `question_types`(`id`),
  FOREIGN KEY (`difficulty_id`)    REFERENCES `question_difficulties`(`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `options` (
  `id`           INT AUTO_INCREMENT PRIMARY KEY,
  `question_id`  INT           NOT NULL,
  `option_text`  TEXT          NOT NULL,
  `is_correct`   TINYINT(1)    NOT NULL DEFAULT 0,
  `created_at`   TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`question_id`) REFERENCES `questions`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


-- ================================
-- 5. Student Attempts & Answers
-- ================================
CREATE TABLE `quiz_lobby` (
  `id`         INT AUTO_INCREMENT PRIMARY KEY,
  `quiz_id`    INT NOT NULL,
  `student_id` INT NOT NULL,
  `joined_at`  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`quiz_id`) REFERENCES `quizzes`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`student_id`) REFERENCES `users`(`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `student_attempts` (
  `id`                   INT AUTO_INCREMENT PRIMARY KEY,
  `quiz_id`              INT          NOT NULL,
  `student_id`           INT          NOT NULL,
  `started_at`           TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `submitted_at`         TIMESTAMP    NULL,
  `attempt_end_time`     DATETIME     DEFAULT NULL,
  `questions_json`       TEXT         DEFAULT NULL,
  `total_score`          DECIMAL(5,2),
  `is_disqualified`      TINYINT(1)   NOT NULL DEFAULT 0,
  `is_manually_locked`   TINYINT(1)   NOT NULL DEFAULT 0,
  `can_resume`           TINYINT(1)   NOT NULL DEFAULT 1,
  `is_initialized`       TINYINT(1)   NOT NULL DEFAULT 0,
  UNIQUE(`quiz_id`, `student_id`),
  FOREIGN KEY (`quiz_id`)    REFERENCES `quizzes`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`student_id`) REFERENCES `students`(`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `student_answers` (
  `id`                  INT AUTO_INCREMENT PRIMARY KEY,
  `attempt_id`          INT NOT NULL,
  `question_id`         INT NOT NULL,
  `answer_text`         TEXT,
  `selected_option_ids` VARCHAR(255),
  `is_correct`          TINYINT(1),
  `score_awarded`       DECIMAL(5,2),
  `time_spent_seconds`  INT,
  FOREIGN KEY (`attempt_id`)  REFERENCES `student_attempts`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`question_id`) REFERENCES `questions`(`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


-- ================================
-- 6. Logging and Monitoring
-- ================================
CREATE TABLE `event_logs` (
  `id`          INT AUTO_INCREMENT PRIMARY KEY,
  `attempt_id`  INT,
  `user_id`     INT,
  `event_type`  VARCHAR(100) NOT NULL,
  `description` TEXT,
  `ip_address`  VARCHAR(45),
  `timestamp`   TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`attempt_id`) REFERENCES `student_attempts`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ================================
-- 7. Populating Lookup Tables
-- ================================

-- Populating the 'exam_statuses' table
INSERT INTO `exam_statuses` (`id`, `name`) VALUES
(1, 'Not Started'),
(2, 'Lobby Open'),
(3, 'In Progress'),
(4, 'Completed'),
(5, 'Cancelled');

-- Populating the 'question_types' table
INSERT INTO `question_types` (`id`, `name`) VALUES
(1, 'MCQ'),
(2, 'Multiple Answer'),
(3, 'Descriptive');

-- Populating the 'question_difficulties' table
INSERT INTO `question_difficulties` (`id`, `level`) VALUES
(1, 'Easy'),
(2, 'Medium'),
(3, 'Hard');

-- Populating the 'roles' table
INSERT INTO `roles` (`id`, `name`) VALUES
(1, 'admin'),
(2, 'faculty'),
(3, 'placement'),
(4, 'student'),
(5, 'school head'),
(6, 'director');

-- Populating the 'schools' table
INSERT INTO `schools` (`id`, `name`) VALUES
(1, 'STME'),
(2, 'SPTM'),
(3, 'SOL'),
(4, 'SOC'),
(5, 'SBM');

