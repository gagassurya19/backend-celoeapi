-- SQL Script to add missing columns to CP tables
-- Run this before running migration 007

USE celoeapi;

-- Add course_id column to cp_student_quiz_detail if it doesn't exist
ALTER TABLE `cp_student_quiz_detail` 
ADD COLUMN `course_id` bigint NOT NULL AFTER `quiz_id`;

-- Add course_id column to cp_student_assignment_detail if it doesn't exist
ALTER TABLE `cp_student_assignment_detail` 
ADD COLUMN `course_id` bigint NOT NULL AFTER `assignment_id`;

-- Add course_id column to cp_student_resource_access if it doesn't exist
ALTER TABLE `cp_student_resource_access` 
ADD COLUMN `course_id` bigint NOT NULL AFTER `resource_id`;

-- Add indexes for the new course_id columns
ALTER TABLE `cp_student_quiz_detail` ADD INDEX `idx_course_id` (`course_id`);
ALTER TABLE `cp_student_assignment_detail` ADD INDEX `idx_course_id` (`course_id`);
ALTER TABLE `cp_student_resource_access` ADD INDEX `idx_course_id` (`course_id`);

-- Show the updated table structures
DESCRIBE cp_student_quiz_detail;
DESCRIBE cp_student_assignment_detail;
DESCRIBE cp_student_resource_access;
