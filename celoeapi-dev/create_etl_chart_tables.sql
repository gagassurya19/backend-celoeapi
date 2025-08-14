-- ETL Chart Database Tables
-- Run this in celoeapi database

-- Create categories table
CREATE TABLE IF NOT EXISTS etl_chart_categories (
    category_id INT PRIMARY KEY,
    category_name VARCHAR(255) NOT NULL,
    category_site VARCHAR(50),
    category_type ENUM('FACULTY', 'STUDYPROGRAM', 'DEPARTMENT', 'OTHER') NOT NULL,
    category_parent_id INT DEFAULT NULL,
    INDEX idx_category_type (category_type),
    INDEX idx_category_parent (category_parent_id),
    FOREIGN KEY (category_parent_id) REFERENCES etl_chart_categories(category_id) ON DELETE SET NULL
);

-- Create subjects table
CREATE TABLE IF NOT EXISTS etl_chart_subjects (
    subject_id INT PRIMARY KEY,
    subject_code VARCHAR(20) NOT NULL UNIQUE,
    subject_name VARCHAR(255) NOT NULL,
    curriculum_year YEAR NOT NULL,
    category_id INT NOT NULL,
    INDEX idx_subject_code (subject_code),
    INDEX idx_curriculum_year (curriculum_year),
    INDEX idx_category_id (category_id),
    FOREIGN KEY (category_id) REFERENCES etl_chart_categories(category_id) ON DELETE CASCADE
);

-- Create ETL Chart logs table
CREATE TABLE IF NOT EXISTS etl_chart_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    start_date DATETIME,
    end_date DATETIME,
    duration VARCHAR(20),
    status VARCHAR(20),
    total_records INT,
    offset INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_status (status),
    INDEX idx_start_date (start_date)
); 