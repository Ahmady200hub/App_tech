-- ============================================================
--  Aptech Student Attendance Portal — Database Schema
--
--  RECOMMENDED: Use setup.php to create the database and
--  seed all data with correct password hashes automatically.
--  Visit: http://localhost/aptech_portal/setup.php
--
--  This file creates the schema only (no seed data).
-- ============================================================

CREATE DATABASE IF NOT EXISTS aptech_portal
  CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

USE aptech_portal;

CREATE TABLE IF NOT EXISTS users (
  id           INT AUTO_INCREMENT PRIMARY KEY,
  full_name    VARCHAR(120)  NOT NULL,
  email        VARCHAR(150)  NOT NULL UNIQUE,
  password     VARCHAR(255)  NOT NULL,
  role         ENUM('admin','lecturer','student') NOT NULL DEFAULT 'student',
  student_id   VARCHAR(30)   DEFAULT NULL,
  avatar_color TINYINT       DEFAULT 0,
  is_active    TINYINT(1)    NOT NULL DEFAULT 1,
  created_at   DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  last_login   DATETIME      DEFAULT NULL
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS modules (
  id           INT AUTO_INCREMENT PRIMARY KEY,
  code         VARCHAR(20)   NOT NULL UNIQUE,
  name         VARCHAR(120)  NOT NULL,
  lecturer_id  INT           DEFAULT NULL,
  year_level   TINYINT       DEFAULT 1,
  schedule     VARCHAR(120)  DEFAULT NULL,
  is_active    TINYINT(1)    NOT NULL DEFAULT 1,
  created_at   DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (lecturer_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS enrollments (
  id          INT AUTO_INCREMENT PRIMARY KEY,
  student_id  INT NOT NULL,
  module_id   INT NOT NULL,
  enrolled_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_enroll (student_id, module_id),
  FOREIGN KEY (student_id) REFERENCES users(id) ON DELETE CASCADE,
  FOREIGN KEY (module_id)  REFERENCES modules(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS sessions (
  id           INT AUTO_INCREMENT PRIMARY KEY,
  module_id    INT  NOT NULL,
  session_date DATE NOT NULL,
  session_time ENUM('morning','afternoon','evening') NOT NULL DEFAULT 'morning',
  marked_by    INT  NOT NULL,
  created_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_session (module_id, session_date, session_time),
  FOREIGN KEY (module_id) REFERENCES modules(id) ON DELETE CASCADE,
  FOREIGN KEY (marked_by) REFERENCES users(id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS attendance (
  id          INT AUTO_INCREMENT PRIMARY KEY,
  session_id  INT  NOT NULL,
  student_id  INT  NOT NULL,
  status      ENUM('present','absent','late') NOT NULL DEFAULT 'present',
  marked_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_att (session_id, student_id),
  FOREIGN KEY (session_id) REFERENCES sessions(id) ON DELETE CASCADE,
  FOREIGN KEY (student_id) REFERENCES users(id)    ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS notifications (
  id          INT AUTO_INCREMENT PRIMARY KEY,
  user_id     INT NOT NULL,
  message     TEXT NOT NULL,
  is_read     TINYINT(1) NOT NULL DEFAULT 0,
  created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;
