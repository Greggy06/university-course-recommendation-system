CREATE DATABASE IF NOT EXISTS university_course_recommendation
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

USE university_course_recommendation;

CREATE TABLE IF NOT EXISTS students (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    full_name VARCHAR(150) NOT NULL,
    email VARCHAR(254) NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    school_stream VARCHAR(32) NULL,
    math_level VARCHAR(16) NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY students_email_unique (email)
) ENGINE=InnoDB
  DEFAULT CHARACTER SET=utf8mb4
  COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS student_results (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    student_id BIGINT UNSIGNED NOT NULL,
    subject VARCHAR(100) NOT NULL,
    result VARCHAR(30) NOT NULL,
    PRIMARY KEY (id),
    KEY student_results_student_id_index (student_id),
    CONSTRAINT student_results_student_id_foreign
        FOREIGN KEY (student_id) REFERENCES students (id)
        ON DELETE CASCADE
) ENGINE=InnoDB
  DEFAULT CHARACTER SET=utf8mb4
  COLLATE=utf8mb4_unicode_ci;
