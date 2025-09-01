<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Cp_etl_model extends CI_Model
{
    const DEFAULT_MEMORY_LIMIT_MB = 1024;
    const DEFAULT_MAX_EXECUTION_SECONDS = 7200;
    private $moodleDbName = 'moodle';
    private $memoryLimitMb;
    private $maxExecutionSeconds;

    public function __construct()
    {
        parent::__construct();
        $this->load->database();
        $this->memoryLimitMb = getenv('CP_ETL_MEMORY_LIMIT_MB') ? intval(getenv('CP_ETL_MEMORY_LIMIT_MB')) : self::DEFAULT_MEMORY_LIMIT_MB;
        $this->maxExecutionSeconds = getenv('CP_ETL_MAX_EXECUTION_SECONDS') ? intval(getenv('CP_ETL_MAX_EXECUTION_SECONDS')) : self::DEFAULT_MAX_EXECUTION_SECONDS;
        $this->optimize_php_limits();
        // Detect Moodle DB name from CI connection
        try {
            $moodle = $this->load->database('moodle', true);
            if (!empty($moodle->database)) {
                $this->moodleDbName = $moodle->database;
            }
        } catch (Exception $e) {
            // fallback to default 'moodle'
        }
    }

    private function optimize_php_limits()
    {
        ini_set('memory_limit', $this->memoryLimitMb . 'M');
        ini_set('max_execution_time', $this->maxExecutionSeconds);
        set_time_limit($this->maxExecutionSeconds);
    }

    private function ensureDbConnection()
    {
        try {
            $this->db->simple_query('SELECT 1');
        } catch (Throwable $e) {
            // Reconnect
            $this->db->close();
            $this->load->database();
            // Set session options for long-running jobs
            $this->db->simple_query("SET SESSION wait_timeout = 28800");
            $this->db->simple_query("SET SESSION interactive_timeout = 28800");
        }
    }

    // === Watermark helpers (CP) ===
    public function get_watermark_date($process_name = 'cp_etl')
    {
        $exists = $this->db->query("SHOW TABLES LIKE 'cp_etl_watermarks'")->num_rows() > 0;
        if (!$exists) {
            return null;
        }
        $sql = "SELECT * FROM cp_etl_watermarks WHERE process_name = ?";
        $row = $this->db->query($sql, [$process_name])->row_array();
        return $row && isset($row['last_date']) ? $row['last_date'] : null;
    }

    public function update_watermark_date($date, $timestamp = null, $process_name = 'cp_etl')
    {
        $exists = $this->db->query("SHOW TABLES LIKE 'cp_etl_watermarks'")->num_rows() > 0;
        if (!$exists) {
            return false;
        }
        $sql = "INSERT INTO cp_etl_watermarks(process_name,last_date,last_timecreated,updated_at) VALUES(?,?,?,NOW())
                ON DUPLICATE KEY UPDATE last_date=VALUES(last_date), last_timecreated=VALUES(last_timecreated), updated_at=NOW()";
        return $this->db->query($sql, [$process_name, $date, $timestamp]);
    }

    private function create_log($status = 2, $type = 'run_etl', $requestedStartDate = null, $existingLogId = null)
    {
        $data = [
            'offset' => 0,
            'numrow' => 0,
            'status' => $status, // 2=inprogress
            'type' => $type,
            'message' => null,
            'requested_start_date' => $requestedStartDate,
            'extracted_start_date' => null,
            'extracted_end_date' => null,
            'start_date' => date('Y-m-d H:i:s'),
            'end_date' => null,
            'duration_seconds' => null,
            'created_at' => date('Y-m-d H:i:s'),
        ];
        
        if ($existingLogId) {
            // Update existing log
            unset($data['message']); // Don't overwrite existing message
            $sql = "UPDATE cp_etl_logs SET offset = ?, numrow = ?, status = ?, type = ?, requested_start_date = ?, extracted_start_date = ?, extracted_end_date = ?, start_date = ?, end_date = ?, duration_seconds = ?, created_at = ? WHERE id = ?";
            $this->db->query($sql, [
                $data['offset'], $data['numrow'], $data['status'], $data['type'], 
                $data['requested_start_date'], $data['extracted_start_date'], 
                $data['extracted_end_date'], $data['start_date'], $data['end_date'], 
                $data['duration_seconds'], $data['created_at'], $existingLogId
            ]);
            return $existingLogId;
        } else {
            // Insert new log
            $sql = "INSERT INTO cp_etl_logs (offset, numrow, status, type, message, requested_start_date, extracted_start_date, extracted_end_date, start_date, end_date, duration_seconds, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
            $this->db->query($sql, [
                $data['offset'], $data['numrow'], $data['status'], $data['type'], 
                $data['message'], $data['requested_start_date'], $data['extracted_start_date'], 
                $data['extracted_end_date'], $data['start_date'], $data['end_date'], 
                $data['duration_seconds'], $data['created_at']
            ]);
            return $this->db->insert_id();
        }
    }

    private function mark_inprogress($logId)
    {
        $sql = "UPDATE cp_etl_logs SET status = ?, start_date = ? WHERE id = ?";
        $this->db->query($sql, [2, date('Y-m-d H:i:s'), $logId]);
    }

    private function complete_log($logId, $numrow = 0, $extractedStart = null, $extractedEnd = null)
    {
        // Compute duration if possible
        $sql = "SELECT * FROM cp_etl_logs WHERE id = ?";
        $log = $this->db->query($sql, [$logId])->row_array();
        $duration = null;
        if ($log && !empty($log['start_date'])) {
            $duration = time() - strtotime($log['start_date']);
        }
        $sql = "UPDATE cp_etl_logs SET status = ?, numrow = ?, extracted_start_date = ?, extracted_end_date = ?, end_date = ?, duration_seconds = ? WHERE id = ?";
        $this->db->query($sql, [1, $numrow, $extractedStart, $extractedEnd, date('Y-m-d H:i:s'), $duration, $logId]);
    }

    private function fail_log($logId, $message)
    {
        // Compute duration if possible
        $sql = "SELECT * FROM cp_etl_logs WHERE id = ?";
        $log = $this->db->query($sql, [$logId])->row_array();
        $duration = null;
        if ($log && !empty($log['start_date'])) {
            $duration = time() - strtotime($log['start_date']);
        }
        $sql = "UPDATE cp_etl_logs SET status = ?, message = ?, end_date = ?, duration_seconds = ? WHERE id = ?";
        $this->db->query($sql, [3, $message, date('Y-m-d H:i:s'), $duration, $logId]);
        log_message('error', 'CP ETL failed: ' . $message);
    }

    public function run_etl($existingLogId = null)
    {
        $logId = $existingLogId ?: $this->create_log(2, 'run_etl');
        if ($existingLogId) {
            $this->mark_inprogress($logId);
        }
        $totalInserted = 0;
        try {
            // Full refresh for deterministic output
            $this->truncate_cp_tables();

            $totalInserted += $this->etl_student_profile();
            $totalInserted += $this->etl_course_summary();
            $totalInserted += $this->etl_activity_summary();
            $totalInserted += $this->etl_student_quiz_detail();
            $totalInserted += $this->etl_student_assignment_detail();
            $totalInserted += $this->etl_student_resource_access();

            $this->complete_log($logId, $totalInserted, null, null);

            return [
                'success' => true,
                'total_inserted' => $totalInserted,
                'log_id' => $logId,
            ];
        } catch (Exception $e) {
            $this->fail_log($logId, $e->getMessage());
            throw $e;
        }
    }

    /**
     * Backfill ETL from a start date up to today (inclusive of start, exclusive of next day end)
     * - Processes per-day chunks for efficiency on large datasets
     * - Optionally runs concurrently if pcntl is available
     */
    public function run_backfill_from_date($startDate, $maxConcurrency = 1, $existingLogId = null, $endDate = null)
    {
        etl_log('info', 'CP backfill start', [
            'startDate' => $startDate,
            'endDate' => $endDate,
            'maxConcurrency' => $maxConcurrency,
            'existingLogId' => $existingLogId
        ]);
        if (!$startDate || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $startDate)) {
            throw new Exception('Invalid start_date. Use YYYY-MM-DD');
        }

        // Shift startDate forward based on watermark if available
        $wmDate = $this->get_watermark_date('cp_etl');
        if ($wmDate && strtotime($wmDate) >= strtotime($startDate)) {
            $startDate = date('Y-m-d', strtotime($wmDate));
        }

        $this->create_log(2, 'run_cp_backfill', $startDate, $existingLogId);

        
        if ($existingLogId) {
            $this->mark_inprogress($existingLogId);
        }

        $startTs = strtotime($startDate);
        $computedEnd = $endDate && preg_match('/^\d{4}-\d{2}-\d{2}$/', $endDate) ? $endDate : date('Y-m-d');
        $endTs = strtotime($computedEnd); // inclusive window end marker used for logging
        if ($startTs === false || $startTs > $endTs) {
            throw new Exception('Invalid date range');
        }

        // Ensure student profiles seeded once (idempotent via unique user_id)
        etl_log('info', 'Seeding student profiles');
        $this->seed_student_profiles();
        etl_log('info', 'Building quiz detail full refresh');
        $this->etl_student_quiz_detail();
        $dates = [];
        // Build processing dates. If endDate provided, limit to it; otherwise go until today.
        for ($ts = $startTs; $ts <= $endTs; $ts = strtotime('+1 day', $ts)) {
            $dates[] = date('Y-m-d', $ts);
        }

        $usedConcurrency = max(1, intval($maxConcurrency));
        $insertedTotal = 0;

        etl_log('info', 'Prepared processing window', ['dates' => $dates, 'usedConcurrency' => $usedConcurrency]);
        if (extension_loaded('pcntl') && $usedConcurrency > 1) {
            $activeChildren = [];
            foreach ($dates as $date) {
                while (count($activeChildren) >= $usedConcurrency) {
                    $pid = pcntl_wait($status);
                    if ($pid > 0) {
                        unset($activeChildren[$pid]);
                    }
                }
                etl_log('debug', 'Forking child for date', ['date' => $date]);
                $pid = pcntl_fork();
                if ($pid == -1) {
                    // Fork failed, fallback to sequential
                    etl_log('warning', 'Fork failed, processing sequentially', ['date' => $date]);
                    $insertedTotal += $this->process_single_date($date);
                    // Update watermark after sequential fallback success
                    $this->update_watermark_date($date, strtotime($date . ' 23:59:59'), 'cp_etl');
                } elseif ($pid) {
                    // Parent
                    $activeChildren[$pid] = true;
                    etl_log('debug', 'Child forked', ['pid' => $pid, 'date' => $date]);
                } else {
                    // Child
                    try {
                        // Reconnect DB in child process
                        $this->ensureDbConnection();
                        etl_log('info', 'Child processing date', ['date' => $date]);
                        $this->process_single_date($date);
                        etl_log('info', 'Child finished date', ['date' => $date]);
                        exit(0);
                    } catch (Exception $e) {
                        etl_log('error', 'Child failed date', ['date' => $date, 'error' => $e->getMessage()]);
                        exit(1);
                    }
                }
            }
            // Wait remaining children
            while (count($activeChildren) > 0) {
                $pid = pcntl_wait($status);
                if ($pid > 0) unset($activeChildren[$pid]);
            }
            // Best-effort watermark: set to last scheduled date
            if (!empty($dates)) {
                $last = end($dates);
                $this->update_watermark_date($last, strtotime($last . ' 23:59:59'), 'cp_etl');
            }
        } else {
            // Sequential
            foreach ($dates as $date) {
                $this->ensureDbConnection();
                etl_log('info', 'Processing date sequential', ['date' => $date]);
                $insertedTotal += $this->process_single_date($date);
                etl_log('info', 'Processed date sequential', ['date' => $date, 'insertedTotalSoFar' => $insertedTotal]);
                // Update watermark after each successful day
                $this->update_watermark_date($date, strtotime($date . ' 23:59:59'), 'cp_etl');
            }
        }

        // Rebuild summaries using cp detail tables for the processed window
        etl_log('info', 'Rebuilding course summary');
        $this->rebuild_course_summary();
        etl_log('info', 'Rebuilding activity summary from details');
        $this->rebuild_activity_summary_from_cp_details();

        log_message('info', 'CP ETL Backfill - Completed: ' . $existingLogId . ' with ' . $insertedTotal . ' inserted' . $startDate);

        // Update log at the end
        $this->complete_log($existingLogId, $insertedTotal, $startDate, $computedEnd);
        etl_log('info', 'CP backfill completed', [
            'logId' => $existingLogId,
            'insertedTotal' => $insertedTotal,
            'processedDays' => count($dates)
        ]);

        return [
            'success' => true,
            'processed_days' => count($dates),
            'inserted_total' => $insertedTotal,
            'concurrency' => $usedConcurrency,
            'log_id' => $existingLogId,
        ];
    }

    /** Seed student profile table once, idempotent via unique user_id */
    private function seed_student_profiles()
    {
        $sql = "
            INSERT INTO cp_student_profile (user_id, idnumber, full_name, email, program_studi)
SELECT 
    u.id AS user_id,
    u.idnumber AS idnumber,
    TRIM(CONCAT(COALESCE(u.firstname,''), ' ', COALESCE(u.lastname,''))) AS full_name,
    u.email,
    (
        SELECT c.fullname
        FROM {$this->moodleDbName}.mdl_user_enrolments ue
        JOIN {$this->moodleDbName}.mdl_enrol e ON ue.enrolid = e.id
        JOIN {$this->moodleDbName}.mdl_course c ON e.courseid = c.id
        WHERE ue.userid = u.id
        LIMIT 1
    ) AS program_studi
FROM {$this->moodleDbName}.mdl_user u
LEFT JOIN (
    SELECT d.userid, d.data AS idnumber
    FROM {$this->moodleDbName}.mdl_user_info_data d
    JOIN {$this->moodleDbName}.mdl_user_info_field f ON f.id = d.fieldid
    WHERE LOWER(f.shortname) IN ('idnumber','nim','npm')
) uid ON uid.userid = u.id
WHERE u.deleted = 0
ON DUPLICATE KEY UPDATE
    idnumber = VALUES(idnumber),
    full_name = VALUES(full_name),
    email = VALUES(email),
    program_studi = VALUES(program_studi),
    updated_at = CURRENT_TIMESTAMP;

        ";
        $this->db->query($sql);
    }

    /** Process a single date (YYYY-MM-DD) and return inserted rows count */
    public function process_single_date($date)
    {
        $inserted = 0;
        // $inserted += $this->etl_student_quiz_detail_for_date($date);
        $inserted += $this->etl_student_assignment_detail_for_date($date);
        $inserted += $this->etl_student_resource_access_for_date($date);
        return $inserted;
    }

    private function etl_student_quiz_detail_for_date($date)
    {
        // Idempotent: remove existing rows for this date window
        $this->db->query(
            "DELETE FROM cp_student_quiz_detail WHERE waktu_mulai >= ? AND waktu_mulai < DATE_ADD(?, INTERVAL 1 DAY)",
            [$date, $date]
        );
        $sql = "
            INSERT INTO cp_student_quiz_detail (quiz_id, user_id, nim, full_name, waktu_mulai, waktu_selesai, durasi_waktu, jumlah_soal, jumlah_dikerjakan, nilai)
            SELECT
                l.contextinstanceid AS quiz_id,
                l.userid AS user_id,
                u.idnumber AS nim,
                TRIM(CONCAT(COALESCE(u.firstname,''),' ',COALESCE(u.lastname,''))) AS full_name,
                FROM_UNIXTIME(qa.timestart) AS waktu_mulai,
                FROM_UNIXTIME(qa.timefinish) AS waktu_selesai,
                CASE WHEN qa.timefinish > 0 AND qa.timestart > 0 THEN SEC_TO_TIME(qa.timefinish - qa.timestart) ELSE NULL END AS durasi_waktu,
                qs.num_questions AS jumlah_soal,
                NULL AS jumlah_dikerjakan,
                qasum.grade AS nilai
            FROM
                {$this->moodleDbName}.mdl_logstore_standard_log l
            JOIN (
                SELECT
                    MIN(id) AS min_id
                FROM
                    {$this->moodleDbName}.mdl_logstore_standard_log
                WHERE
                    component = 'mod_quiz'
                GROUP BY
                    userid, contextinstanceid, action
            ) l_min ON l.id = l_min.min_id
            JOIN
                {$this->moodleDbName}.mdl_course_modules cm ON cm.id = l.contextinstanceid
            LEFT JOIN
                {$this->moodleDbName}.mdl_quiz_attempts qa ON qa.quiz = cm.instance AND qa.userid = l.userid
            JOIN
                {$this->moodleDbName}.mdl_user u ON u.id = l.userid
            JOIN
                {$this->moodleDbName}.mdl_modules m ON m.id = cm.module AND m.name = 'quiz'
            LEFT JOIN (
                SELECT
                    d.userid,
                    d.data AS idnumber
                FROM
                    {$this->moodleDbName}.mdl_user_info_data d
                JOIN
                    {$this->moodleDbName}.mdl_user_info_field f ON f.id = d.fieldid
                WHERE
                    LOWER(f.shortname) IN ('idnumber','nim','npm')
            ) uid ON uid.userid = u.id
            LEFT JOIN (
                SELECT
                    quizid AS quiz,
                    COUNT(*) AS num_questions
                FROM
                    {$this->moodleDbName}.mdl_quiz_slots
                GROUP BY
                    quizid
            ) qs ON qs.quiz = cm.instance
            LEFT JOIN (
                SELECT
                    gi.iteminstance AS quizid,
                    g.finalgrade AS grade,
                    g.userid
                FROM
                    {$this->moodleDbName}.mdl_grade_items gi
                JOIN
                    {$this->moodleDbName}.mdl_grade_grades g ON g.itemid = gi.id
                WHERE
                    gi.itemtype = 'mod' AND gi.itemmodule = 'quiz'
            ) qasum ON qasum.quizid = cm.instance AND qasum.userid = l.userid
            WHERE
                l.action = 'viewed' 
        ";
        $this->db->query($sql, [$date, $date]);
        return $this->db->affected_rows();
    }

    private function etl_student_assignment_detail_for_date($date)
    {
        // Idempotent: remove existing rows for this date window
        $this->db->query(
            "DELETE FROM cp_student_assignment_detail WHERE waktu_submit >= ? AND waktu_submit < DATE_ADD(?, INTERVAL 1 DAY)",
            [$date, $date]
        );
        $sql = "
            INSERT INTO cp_student_assignment_detail (assignment_id, user_id, nim, full_name, waktu_submit, waktu_pengerjaan, nilai)
            SELECT s.assignment AS assignment_id,
                   s.userid AS user_id,
                   u.idnumber AS nim,
                   TRIM(CONCAT(COALESCE(u.firstname,''),' ',COALESCE(u.lastname,''))) AS full_name,
                   FROM_UNIXTIME(s.timemodified) AS waktu_submit,
                   NULL AS waktu_pengerjaan,
                   g.finalgrade AS nilai
            FROM {$this->moodleDbName}.mdl_assign_submission s
            JOIN {$this->moodleDbName}.mdl_user u ON u.id = s.userid
            LEFT JOIN (
                SELECT d.userid, d.data AS idnumber
                FROM {$this->moodleDbName}.mdl_user_info_data d
                JOIN {$this->moodleDbName}.mdl_user_info_field f ON f.id = d.fieldid
                WHERE LOWER(f.shortname) IN ('idnumber','nim','npm')
            ) uid ON uid.userid = u.id
            LEFT JOIN {$this->moodleDbName}.mdl_assign a ON a.id = s.assignment
            LEFT JOIN (
                SELECT gi.iteminstance AS assignid, g.userid, g.finalgrade
                FROM {$this->moodleDbName}.mdl_grade_items gi
                JOIN {$this->moodleDbName}.mdl_grade_grades g ON g.itemid = gi.id
                WHERE gi.itemtype = 'mod' AND gi.itemmodule = 'assign'
            ) g ON g.assignid = s.assignment AND g.userid = s.userid
            WHERE s.status = 'submitted'
              AND s.timemodified >= UNIX_TIMESTAMP(?)
              AND s.timemodified < UNIX_TIMESTAMP(DATE_ADD(?, INTERVAL 1 DAY))
        ";
        $this->db->query($sql, [$date, $date]);
        return $this->db->affected_rows();
    }

    private function etl_student_resource_access_for_date($date)
    {
        // Idempotent: remove existing rows for this date window
        $this->db->query(
            "DELETE FROM cp_student_resource_access WHERE waktu_akses >= ? AND waktu_akses < DATE_ADD(?, INTERVAL 1 DAY)",
            [$date, $date]
        );
        $sql = "
            INSERT INTO cp_student_resource_access (resource_id, user_id, nim, full_name, waktu_akses)
            SELECT l.contextinstanceid AS resource_id,
                   l.userid AS user_id,
                   u.idnumber AS nim,
                   TRIM(CONCAT(COALESCE(u.firstname,''),' ',COALESCE(u.lastname,''))) AS full_name,
                   FROM_UNIXTIME(l.timecreated) AS waktu_akses
            FROM {$this->moodleDbName}.mdl_logstore_standard_log l
            JOIN {$this->moodleDbName}.mdl_user u ON u.id = l.userid
            LEFT JOIN (
                SELECT d.userid, d.data AS idnumber
                FROM {$this->moodleDbName}.mdl_user_info_data d
                JOIN {$this->moodleDbName}.mdl_user_info_field f ON f.id = d.fieldid
                WHERE LOWER(f.shortname) IN ('idnumber','nim','npm')
            ) uid ON uid.userid = u.id
            WHERE l.component = 'mod_resource'
              AND l.action IN ('viewed','downloaded')
              AND l.timecreated >= UNIX_TIMESTAMP(?)
              AND l.timecreated < UNIX_TIMESTAMP(DATE_ADD(?, INTERVAL 1 DAY))
        ";
        $this->db->query($sql, [$date, $date]);
        return $this->db->affected_rows();
    }

    /** Build course summary from Moodle static tables (no date filter, static metadata) */
    private function rebuild_course_summary()
    {
        $this->db->query('TRUNCATE TABLE cp_course_summary');
        return $this->etl_course_summary();
    }

    /** Build activity summary using cp_* detail tables for the processed window */
    private function rebuild_activity_summary_from_cp_details()
    {
        $this->db->query('TRUNCATE TABLE cp_activity_summary');

        // Resources
        $sqlResource = "
            INSERT INTO cp_activity_summary (course_id, section, activity_id, activity_type, activity_name, accessed_count, submission_count, graded_count, attempted_count)
            SELECT c.id AS course_id,
                   cm.section,
                   cra.resource_id AS activity_id,
                   'resource' AS activity_type,
                   r.name AS activity_name,
                   COUNT(cra.id) AS accessed_count,
                   NULL AS submission_count,
                   NULL AS graded_count,
                   NULL AS attempted_count
            FROM {$this->moodleDbName}.mdl_course_modules cm
            JOIN {$this->moodleDbName}.mdl_course c ON c.id = cm.course
            JOIN {$this->moodleDbName}.mdl_modules m ON m.id = cm.module AND m.name = 'resource' and cm.module in (1,18,19)
            JOIN {$this->moodleDbName}.mdl_resource r ON r.id = cm.instance
            LEFT JOIN cp_student_resource_access cra ON cra.resource_id = cm.id
            GROUP BY c.id, cm.section, cm.id, r.name
        ";
        $this->db->query($sqlResource);

        // Quizzes
        $sqlQuiz = "
            INSERT INTO cp_activity_summary (course_id, section, activity_id, activity_type, activity_name, accessed_count, submission_count, graded_count, attempted_count)
            SELECT c.id AS course_id,
                   cm.section,
                   csqd.quiz_id AS activity_id,
                   'quiz' AS activity_type,
                   q.name AS activity_name,
                   COUNT(csqd.id) AS accessed_count,
                   NULL AS submission_count,
                   SUM(CASE WHEN csqd.nilai IS NOT NULL THEN 1 ELSE 0 END) AS graded_count,
                   COUNT(csqd.id) AS attempted_count
            FROM {$this->moodleDbName}.mdl_course_modules cm
            JOIN {$this->moodleDbName}.mdl_course c ON c.id = cm.course
            JOIN {$this->moodleDbName}.mdl_modules m ON m.id = cm.module AND m.name = 'quiz'
            JOIN {$this->moodleDbName}.mdl_quiz q ON q.id = cm.instance
            LEFT JOIN cp_student_quiz_detail csqd ON csqd.quiz_id = cm.id
            WHERE csqd.id IS NOT NULL
            GROUP BY c.id, cm.section, cm.id, q.name
        ";
        $this->db->query($sqlQuiz);

        // Assignments
        $sqlAssign = "
            INSERT INTO cp_activity_summary (course_id, section, activity_id, activity_type, activity_name, accessed_count, submission_count, graded_count, attempted_count)
            SELECT c.id AS course_id,
                   cm.section,
                   csad.assignment_id AS activity_id,
                   'assign' AS activity_type,
                   a.name AS activity_name,
                   COUNT(csad.id) AS accessed_count,
                   COUNT(csad.id) AS submission_count,
                   SUM(CASE WHEN csad.nilai IS NOT NULL THEN 1 ELSE 0 END) AS graded_count,
                   NULL AS attempted_count
            FROM {$this->moodleDbName}.mdl_course_modules cm
            JOIN {$this->moodleDbName}.mdl_course c ON c.id = cm.course
            JOIN {$this->moodleDbName}.mdl_modules m ON m.id = cm.module AND m.name = 'assign' and cm.module in (1,18,19)
            JOIN {$this->moodleDbName}.mdl_assign a ON a.id = cm.instance
            LEFT JOIN cp_student_assignment_detail csad ON csad.assignment_id = a.id
            GROUP BY c.id, cm.section, cm.id, a.name
        ";
        $this->db->query($sqlAssign);
    }

    private function truncate_cp_tables()
    {
        $tables = [
            'cp_activity_summary',
            'cp_course_summary',
            'cp_student_assignment_detail',
            'cp_student_profile',
            'cp_student_quiz_detail',
            'cp_student_resource_access',
        ];
        foreach ($tables as $table) {
            $this->db->query("TRUNCATE TABLE `{$table}`");
        }
    }

    /**
     * Clear all CP data tables. Optionally include logs table.
     */
    public function clear_all($includeLogs = false)
    {
        $tables = [
            'cp_activity_summary',
            'cp_course_summary',
            'cp_student_assignment_detail',
            'cp_student_profile',
            'cp_student_quiz_detail',
            'cp_student_resource_access',
        ];

        $details = [];
        $totalCleared = 0;

        foreach ($tables as $table) {
            $cnt = 0;
            $q = $this->db->query("SELECT COUNT(*) AS c FROM `{$table}`");
            $row = $q->row();
            if ($row) {
                $cnt = intval($row->c);
            }
            $details[$table] = $cnt;
            $totalCleared += $cnt;
        }

        // Perform truncates
        foreach ($tables as $table) {
            $this->db->query("TRUNCATE TABLE `{$table}`");
        }

        $logsCleared = 0;
        if ($includeLogs) {
            $q = $this->db->query("SELECT COUNT(*) AS c FROM `cp_etl_logs`");
            $row = $q->row();
            if ($row) {
                $logsCleared = intval($row->c);
            }
            $this->db->query("TRUNCATE TABLE `cp_etl_logs`");
        }

        return [
            'total_cleared' => $totalCleared + ($includeLogs ? $logsCleared : 0),
            'data_cleared' => $totalCleared,
            'logs_cleared' => $includeLogs ? $logsCleared : 0,
            'details' => $details,
        ];
    }

    private function etl_student_profile()
    {
        $sql = "
            INSERT INTO cp_student_profile (user_id, idnumber, full_name, email, program_studi)
            SELECT u.id AS user_id,
                   u.idnumber AS idnumber,
                   TRIM(CONCAT(COALESCE(u.firstname,''), ' ', COALESCE(u.lastname,''))) AS full_name,
                   u.email,
                   NULL AS program_studi
            FROM {$this->moodleDbName}.mdl_user u
            LEFT JOIN (
                SELECT d.userid, d.data AS idnumber
                FROM {$this->moodleDbName}.mdl_user_info_data d
                JOIN {$this->moodleDbName}.mdl_user_info_field f ON f.id = d.fieldid
                WHERE LOWER(f.shortname) IN ('idnumber','nim','npm')
            ) uid ON uid.userid = u.id
            WHERE u.deleted = 0
        ";
        $result = $this->db->query($sql);
        return $this->db->affected_rows();
    }

    private function etl_course_summary()
    {
        // jumlah_aktivitas via mdl_course_modules, jumlah_mahasiswa via role student enrolments, dosen_pengampu via teacher roles
        $sql = "
            INSERT INTO cp_course_summary (course_id, course_name, kelas, jumlah_aktivitas, jumlah_mahasiswa, dosen_pengampu)
            SELECT c.id AS course_id,
                   c.fullname AS course_name,
                   c.idnumber AS kelas,
                   COALESCE(cm.ct, 0) AS jumlah_aktivitas,
                   COALESCE(s.ct, 0) AS jumlah_mahasiswa,
                   t.teachers AS dosen_pengampu
            FROM {$this->moodleDbName}.mdl_course c
            LEFT JOIN (
                SELECT course, COUNT(*) ct
                FROM {$this->moodleDbName}.mdl_course_modules cmt
                WHERE cmt.module in (1,18,19)
                GROUP BY course
            ) cm ON cm.course = c.id
            LEFT JOIN (
                SELECT e.courseid AS course, COUNT(DISTINCT ue.userid) ct
                FROM {$this->moodleDbName}.mdl_enrol e
                JOIN {$this->moodleDbName}.mdl_user_enrolments ue ON ue.enrolid = e.id
                JOIN {$this->moodleDbName}.mdl_course_modules cmt ON e.courseid = cmt.course 
                WHERE cmt.module in (1,18,19)
                GROUP BY e.courseid
            ) s ON s.course = c.id
            LEFT JOIN (
                SELECT ctx.instanceid AS course,
                       GROUP_CONCAT(DISTINCT TRIM(CONCAT(COALESCE(u.firstname,''),' ',COALESCE(u.lastname,''))) SEPARATOR ', ') AS teachers
                FROM {$this->moodleDbName}.mdl_context ctx
                JOIN {$this->moodleDbName}.mdl_role_assignments ra ON ra.contextid = ctx.id
                JOIN {$this->moodleDbName}.mdl_user u ON u.id = ra.userid
                JOIN {$this->moodleDbName}.mdl_role r ON r.id = ra.roleid
                WHERE ctx.contextlevel = 50 AND r.shortname IN ('editingteacher','teacher')
                GROUP BY ctx.instanceid
            ) t ON t.course = c.id
        ";
        $this->db->query($sql);
        return $this->db->affected_rows();
    }

    private function etl_activity_summary()
    {
        // access counts from logstore, attempt/submission/graded counts for quizzes and assignments
        $sql = "
            INSERT INTO cp_activity_summary (course_id, section, activity_id, activity_type, activity_name, accessed_count, submission_count, graded_count, attempted_count)
            SELECT c.id AS course_id,
                   cm.section,
                   cm.id AS activity_id,
                   m.name AS activity_type,
                   COALESCE(a.name, q.name, r.name, l.name, 'activity') AS activity_name,
                   CASE 
                       WHEN m.name = 'assign' THEN COALESCE(asub.submission_count, 0)
                       WHEN m.name = 'quiz' THEN COALESCE(qa.attempted_count, 0)
                       WHEN m.name = 'resource' THEN COALESCE(lg.accessed_count, 0)
                       ELSE COALESCE(lg.accessed_count, 0)
                   END AS accessed_count,
                   CASE WHEN m.name = 'assign' THEN COALESCE(asub.submission_count, 0) ELSE NULL END AS submission_count,
                   CASE WHEN m.name IN ('assign','quiz') THEN COALESCE(gg.graded_count, 0) ELSE NULL END AS graded_count,
                   CASE WHEN m.name = 'quiz' THEN COALESCE(qa.attempted_count, 0) ELSE NULL END AS attempted_count
            FROM {$this->moodleDbName}.mdl_course_modules cm
            JOIN {$this->moodleDbName}.mdl_course c ON c.id = cm.course
            JOIN {$this->moodleDbName}.mdl_modules m ON m.id = cm.module
            LEFT JOIN {$this->moodleDbName}.mdl_assign a ON (m.name = 'assign' AND a.id = cm.instance)
            LEFT JOIN {$this->moodleDbName}.mdl_quiz q ON (m.name = 'quiz' AND q.id = cm.instance)
            LEFT JOIN {$this->moodleDbName}.mdl_resource r ON (m.name = 'resource' AND r.id = cm.instance)
            LEFT JOIN {$this->moodleDbName}.mdl_lesson l ON (m.name = 'lesson' AND l.id = cm.instance)
            LEFT JOIN (
                SELECT contextinstanceid AS cmid, COUNT(*) AS accessed_count
                FROM {$this->moodleDbName}.mdl_logstore_standard_log
                WHERE action IN ('viewed','viewed all','launched')
                  AND target IN ('course_module','resource','assign','quiz','lesson')
                GROUP BY contextinstanceid
            ) lg ON lg.cmid = cm.id
            LEFT JOIN (
                SELECT a.id AS assignid, COUNT(*) AS submission_count
                FROM {$this->moodleDbName}.mdl_assign a
                JOIN {$this->moodleDbName}.mdl_assign_submission s ON s.assignment = a.id AND s.status = 'submitted'
                GROUP BY a.id
            ) asub ON (m.name = 'assign' AND asub.assignid = a.id)
            LEFT JOIN (
                SELECT item.itemmodule AS module, item.iteminstance AS instance, COUNT(*) AS graded_count
                FROM {$this->moodleDbName}.mdl_grade_items item
                JOIN {$this->moodleDbName}.mdl_grade_grades g ON g.itemid = item.id AND g.finalgrade IS NOT NULL
                WHERE item.itemtype = 'mod'
                GROUP BY item.itemmodule, item.iteminstance
            ) gg ON ((m.name = 'assign' AND gg.module = 'assign' AND gg.instance = a.id)
                OR (m.name = 'quiz' AND gg.module = 'quiz' AND gg.instance = q.id))
            LEFT JOIN (
                SELECT quiz AS quizid, COUNT(*) AS attempted_count
                FROM {$this->moodleDbName}.mdl_quiz_attempts
                WHERE state IN ('finished','abandoned','inprogress')
                GROUP BY quiz
            ) qa ON (m.name = 'quiz' AND qa.quizid = q.id)
        ";
        $this->db->query($sql);
        return $this->db->affected_rows();
    }

    private function etl_student_quiz_detail()
    {
        $this->db->query('TRUNCATE TABLE cp_student_quiz_detail');
        $sql = "
            INSERT INTO cp_student_quiz_detail (quiz_id, user_id, nim, full_name, waktu_mulai, waktu_selesai, durasi_waktu, jumlah_soal, jumlah_dikerjakan, nilai)
            SELECT
                l.contextinstanceid AS quiz_id,
                l.userid AS user_id,
                u.idnumber AS nim,
                TRIM(CONCAT(COALESCE(u.firstname,''),' ',COALESCE(u.lastname,''))) AS full_name,
                FROM_UNIXTIME(qa.timestart) AS waktu_mulai,
                FROM_UNIXTIME(qa.timefinish) AS waktu_selesai,
                CASE WHEN qa.timefinish > 0 AND qa.timestart > 0 THEN SEC_TO_TIME(qa.timefinish - qa.timestart) ELSE NULL END AS durasi_waktu,
                qs.num_questions AS jumlah_soal,
                NULL AS jumlah_dikerjakan,
                qasum.grade AS nilai
            FROM
                {$this->moodleDbName}.mdl_logstore_standard_log l
            JOIN (
                SELECT
                    MIN(id) AS min_id
                FROM
                    {$this->moodleDbName}.mdl_logstore_standard_log
                WHERE
                    component = 'mod_quiz'
                GROUP BY
                    userid, contextinstanceid, action
            ) l_min ON l.id = l_min.min_id
            JOIN
                {$this->moodleDbName}.mdl_course_modules cm ON cm.id = l.contextinstanceid
            LEFT JOIN
                {$this->moodleDbName}.mdl_quiz_attempts qa ON qa.quiz = cm.instance AND qa.userid = l.userid
            JOIN
                {$this->moodleDbName}.mdl_user u ON u.id = l.userid
            JOIN
                {$this->moodleDbName}.mdl_modules m ON m.id = cm.module AND m.name = 'quiz'
            LEFT JOIN (
                SELECT
                    d.userid,
                    d.data AS idnumber
                FROM
                    {$this->moodleDbName}.mdl_user_info_data d
                JOIN
                    {$this->moodleDbName}.mdl_user_info_field f ON f.id = d.fieldid
                WHERE
                    LOWER(f.shortname) IN ('idnumber','nim','npm')
            ) uid ON uid.userid = u.id
            LEFT JOIN (
                SELECT
                    quizid AS quiz,
                    COUNT(*) AS num_questions
                FROM
                    {$this->moodleDbName}.mdl_quiz_slots
                GROUP BY
                    quizid
            ) qs ON qs.quiz = cm.instance
            LEFT JOIN (
                SELECT
                    gi.iteminstance AS quizid,
                    g.finalgrade AS grade,
                    g.userid
                FROM
                    {$this->moodleDbName}.mdl_grade_items gi
                JOIN
                    {$this->moodleDbName}.mdl_grade_grades g ON g.itemid = gi.id
                WHERE
                    gi.itemtype = 'mod' AND gi.itemmodule = 'quiz'
            ) qasum ON qasum.quizid = cm.instance AND qasum.userid = l.userid
            WHERE
                l.action = 'viewed'
        ";
        $this->db->query($sql);
        return $this->db->affected_rows();
    }

    private function etl_student_assignment_detail()
    {
        $sql = "
            INSERT INTO cp_student_assignment_detail (assignment_id, user_id, nim, full_name, waktu_submit, waktu_pengerjaan, nilai)
            SELECT s.assignment AS assignment_id,
                   s.userid AS user_id,
                   u.idnumber AS nim,
                   TRIM(CONCAT(COALESCE(u.firstname,''),' ',COALESCE(u.lastname,''))) AS full_name,
                   FROM_UNIXTIME(s.timemodified) AS waktu_submit,
                   NULL AS waktu_pengerjaan,
                   g.finalgrade AS nilai
            FROM {$this->moodleDbName}.mdl_assign_submission s
            JOIN {$this->moodleDbName}.mdl_user u ON u.id = s.userid
            JOIN {$this->moodleDbName}.mdl_course_modules cm ON cm.instance = s.assignment AND cm.module IN (1,18,19)
            JOIN {$this->moodleDbName}.mdl_modules m ON m.id = cm.module AND m.name = 'assign'
            JOIN {$this->moodleDbName}.mdl_logstore_standard_log l ON l.contextinstanceid = cm.id AND l.userid = s.userid AND l.component = 'mod_assign'
            LEFT JOIN (
                SELECT d.userid, d.data AS idnumber
                FROM {$this->moodleDbName}.mdl_user_info_data d
                JOIN {$this->moodleDbName}.mdl_user_info_field f ON f.id = d.fieldid
                WHERE LOWER(f.shortname) IN ('idnumber','nim','npm')
            ) uid ON uid.userid = u.id
            LEFT JOIN {$this->moodleDbName}.mdl_assign a ON a.id = s.assignment
            LEFT JOIN (
                SELECT gi.iteminstance AS assignid, g.userid, g.finalgrade
                FROM {$this->moodleDbName}.mdl_grade_items gi
                JOIN {$this->moodleDbName}.mdl_grade_grades g ON g.itemid = gi.id
                WHERE gi.itemtype = 'mod' AND gi.itemmodule = 'assign'
            ) g ON g.assignid = s.assignment AND g.userid = s.userid
            WHERE s.status = 'submitted'
        ";
        $this->db->query($sql);
        return $this->db->affected_rows();
    }

    private function etl_student_resource_access()
    {
        $sql = "
            INSERT INTO cp_student_resource_access (resource_id, user_id, nim, full_name, waktu_akses)
            SELECT l.contextinstanceid AS resource_id,
                   l.userid AS user_id,
                   u.idnumber AS nim,
                   TRIM(CONCAT(COALESCE(u.firstname,''),' ',COALESCE(u.lastname,''))) AS full_name,
                   FROM_UNIXTIME(l.timecreated) AS waktu_akses
            FROM {$this->moodleDbName}.mdl_logstore_standard_log l
            JOIN {$this->moodleDbName}.mdl_user u ON u.id = l.userid
            JOIN {$this->moodleDbName}.mdl_course_modules cm ON cm.id = l.contextinstanceid AND cm.module IN (1,18,19)
            JOIN {$this->moodleDbName}.mdl_modules m ON m.id = cm.module AND m.name = 'resource'
            LEFT JOIN (
                SELECT d.userid, d.data AS idnumber
                FROM {$this->moodleDbName}.mdl_user_info_data d
                JOIN {$this->moodleDbName}.mdl_user_info_field f ON f.id = d.fieldid
                WHERE LOWER(f.shortname) IN ('idnumber','nim','npm')
            ) uid ON uid.userid = u.id
            WHERE l.component = 'mod_resource' AND l.action IN ('viewed','downloaded')
        ";
        $this->db->query($sql);
        return $this->db->affected_rows();
    }
}


