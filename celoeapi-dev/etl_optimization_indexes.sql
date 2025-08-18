-- ETL Performance Optimization Indexes
-- This file contains indexes and database optimizations for handling millions of records

-- ==================================================================
-- SOURCE DATABASE OPTIMIZATIONS (moodle401)
-- ==================================================================

-- Primary log table optimizations
CREATE INDEX idx_mdl_log_timecreated_userid 
    ON moodle401.mdl_logstore_standard_log(timecreated, userid);

CREATE INDEX idx_mdl_log_contextlevel_action 
    ON moodle401.mdl_logstore_standard_log(contextlevel, action, contextinstanceid);

CREATE INDEX idx_mdl_log_courseid_userid 
    ON moodle401.mdl_logstore_standard_log(courseid, userid, timecreated);

CREATE INDEX idx_mdl_log_component_target 
    ON moodle401.mdl_logstore_standard_log(component, target, action);

-- Course and module optimizations
CREATE INDEX idx_mdl_course_visible_id 
    ON moodle401.mdl_course(visible, id);

CREATE INDEX idx_mdl_course_modules_course_instance 
    ON moodle401.mdl_course_modules(course, instance, module);

CREATE INDEX idx_mdl_course_modules_section 
    ON moodle401.mdl_course_modules(section, course);

-- User and role optimizations
CREATE INDEX idx_mdl_role_assignments_roleid_userid 
    ON moodle401.mdl_role_assignments(roleid, userid, contextid);

CREATE INDEX idx_mdl_context_level_instance 
    ON moodle401.mdl_context(contextlevel, instanceid);

CREATE INDEX idx_mdl_user_info_data_userid_fieldid 
    ON moodle401.mdl_user_info_data(userid, fieldid);

-- Quiz and assignment optimizations
CREATE INDEX idx_mdl_quiz_attempts_quiz_userid 
    ON moodle401.mdl_quiz_attempts(quiz, userid, state);

CREATE INDEX idx_mdl_assign_submission_assignment_userid 
    ON moodle401.mdl_assign_submission(assignment, userid, status);

CREATE INDEX idx_mdl_grade_items_iteminstance_itemmodule 
    ON moodle401.mdl_grade_items(iteminstance, itemmodule);

CREATE INDEX idx_mdl_grade_grades_itemid_userid 
    ON moodle401.mdl_grade_grades(itemid, userid);

-- Question attempt optimizations
CREATE INDEX idx_mdl_question_attempts_questionusageid 
    ON moodle401.mdl_question_attempts(questionusageid);

CREATE INDEX idx_mdl_question_attempt_steps_questionattemptid 
    ON moodle401.mdl_question_attempt_steps(questionattemptid, state);

-- ==================================================================
-- ETL DATABASE OPTIMIZATIONS (celoeapi)
-- ==================================================================

-- Raw log table composite indexes
CREATE INDEX idx_raw_log_courseid_userid_time 
    ON celoeapi.raw_log(courseid, userid, timecreated);

CREATE INDEX idx_raw_log_contextlevel_action 
    ON celoeapi.raw_log(contextlevel, action, contextinstanceid);

CREATE INDEX idx_raw_log_component_target 
    ON celoeapi.raw_log(component, target, objectid);

CREATE INDEX idx_raw_log_timecreated 
    ON celoeapi.raw_log(timecreated DESC);

-- Course activity summary optimizations
CREATE INDEX idx_course_activity_course_type 
    ON celoeapi.course_activity_summary(course_id, activity_type);

CREATE INDEX idx_course_activity_section 
    ON celoeapi.course_activity_summary(course_id, section);

CREATE INDEX idx_course_activity_created 
    ON celoeapi.course_activity_summary(created_at DESC);

-- Student profile optimizations
CREATE INDEX idx_student_profile_userid 
    ON celoeapi.student_profile(user_id);

CREATE INDEX idx_student_profile_idnumber 
    ON celoeapi.student_profile(idnumber);

CREATE INDEX idx_student_profile_program 
    ON celoeapi.student_profile(program_studi);

-- Quiz detail optimizations
CREATE INDEX idx_student_quiz_quiz_user 
    ON celoeapi.student_quiz_detail(quiz_id, user_id);

CREATE INDEX idx_student_quiz_nim 
    ON celoeapi.student_quiz_detail(nim);

CREATE INDEX idx_student_quiz_waktu_mulai 
    ON celoeapi.student_quiz_detail(waktu_mulai DESC);

CREATE INDEX idx_student_quiz_nilai 
    ON celoeapi.student_quiz_detail(nilai DESC);

-- Assignment detail optimizations
CREATE INDEX idx_student_assignment_assignment_user 
    ON celoeapi.student_assignment_detail(assignment_id, user_id);

CREATE INDEX idx_student_assignment_nim 
    ON celoeapi.student_assignment_detail(nim);

CREATE INDEX idx_student_assignment_waktu_submit 
    ON celoeapi.student_assignment_detail(waktu_submit DESC);

-- Resource access optimizations
CREATE INDEX IF NOT EXISTS idx_student_resource_resource_user 
    ON celoeapi.student_resource_access(resource_id, user_id);

CREATE INDEX IF NOT EXISTS idx_student_resource_waktu_akses 
    ON celoeapi.student_resource_access(waktu_akses DESC);

CREATE INDEX IF NOT EXISTS idx_student_resource_nim 
    ON celoeapi.student_resource_access(nim);

-- Course summary optimizations
CREATE INDEX IF NOT EXISTS idx_course_summary_course_id 
    ON celoeapi.course_summary(course_id);

CREATE INDEX IF NOT EXISTS idx_course_summary_jumlah_mahasiswa 
    ON celoeapi.course_summary(jumlah_mahasiswa DESC);

-- Log scheduler optimizations
CREATE INDEX IF NOT EXISTS idx_log_scheduler_status_start 
    ON celoeapi.sas_log_scheduler(status, start_date DESC);

CREATE INDEX IF NOT EXISTS idx_log_scheduler_end_date 
    ON celoeapi.sas_log_scheduler(end_date DESC);

-- ==================================================================
-- PARTITIONING FOR LARGE TABLES (Optional - for very large datasets)
-- ==================================================================

-- Raw log partitioning by month (example for MySQL 5.7+)
-- Note: Implement only if you have millions of log entries per month
/*
ALTER TABLE celoeapi.raw_log 
PARTITION BY RANGE (YEAR(FROM_UNIXTIME(timecreated)) * 100 + MONTH(FROM_UNIXTIME(timecreated))) (
    PARTITION p202301 VALUES LESS THAN (202302),
    PARTITION p202302 VALUES LESS THAN (202303),
    PARTITION p202303 VALUES LESS THAN (202304),
    -- Add more partitions as needed
    PARTITION p_future VALUES LESS THAN MAXVALUE
);
*/

-- ==================================================================
-- DATABASE CONFIGURATION OPTIMIZATIONS
-- ==================================================================

-- These should be applied at session level or in database configuration
-- See the ETL_Model.php optimize_database_connection() method

-- For MySQL/MariaDB my.cnf optimizations:
/*
[mysqld]
# Buffer pool size (set to 70-80% of available RAM on dedicated server)
innodb_buffer_pool_size = 2G

# Log file size for better write performance
innodb_log_file_size = 256M
innodb_log_buffer_size = 64M

# Bulk insert optimizations
bulk_insert_buffer_size = 64M
myisam_sort_buffer_size = 128M

# Query cache (if using MySQL < 8.0)
query_cache_size = 256M
query_cache_type = 1

# Connection settings
max_connections = 200
thread_cache_size = 50

# Sort and read buffer optimizations
sort_buffer_size = 16M
read_buffer_size = 8M
read_rnd_buffer_size = 16M

# InnoDB optimizations
innodb_flush_log_at_trx_commit = 2
innodb_flush_method = O_DIRECT
innodb_file_per_table = 1
*/

-- ==================================================================
-- ANALYZE TABLES FOR OPTIMAL QUERY PLANS
-- ==================================================================

-- Run these periodically after ETL operations
ANALYZE TABLE moodle401.mdl_logstore_standard_log;
ANALYZE TABLE moodle401.mdl_course;
ANALYZE TABLE moodle401.mdl_user;
ANALYZE TABLE moodle401.mdl_role_assignments;
ANALYZE TABLE moodle401.mdl_course_modules;
ANALYZE TABLE moodle401.mdl_quiz_attempts;
ANALYZE TABLE moodle401.mdl_assign_submission;

ANALYZE TABLE celoeapi.raw_log;
ANALYZE TABLE celoeapi.course_activity_summary;
ANALYZE TABLE celoeapi.student_profile;
ANALYZE TABLE celoeapi.student_quiz_detail;
ANALYZE TABLE celoeapi.student_assignment_detail;
ANALYZE TABLE celoeapi.student_resource_access;
ANALYZE TABLE celoeapi.course_summary; 