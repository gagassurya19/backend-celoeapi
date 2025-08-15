<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Cli extends CI_Controller {

    public function __construct()
    {
        parent::__construct();
        
        // Only allow CLI access
        if (!$this->input->is_cli_request()) {
            show_error('This script can only be run from the command line.');
        }
        
        $this->load->database();
    }

    /**
     * Run ETL process via CLI
     * Usage: php index.php cli run_etl
     */
    public function run_etl()
    {
        try {
            echo "Starting ETL process...\n";
            log_message('info', 'CLI ETL process started');
            
            $this->load->model('cp_course_performance_etl_model', 'm_ETL');
            $result = $this->m_ETL->run_etl();
            
            echo "ETL process completed successfully!\n";
            echo "Total records processed: " . $result['total_records'] . "\n";
            echo "Duration: " . $result['duration'] . " seconds\n";
            echo "Peak memory usage: " . $result['peak_memory'] . "\n";
            
            log_message('info', 'CLI ETL process completed successfully');
            
        } catch (Exception $e) {
            echo "ETL process failed: " . $e->getMessage() . "\n";
            log_message('error', 'CLI ETL process failed: ' . $e->getMessage());
            exit(1);
        }
    }

    /**
     * Run incremental ETL process via CLI
     * Usage: php index.php cli run_incremental_etl
     */
    public function run_incremental_etl()
    {
        try {
            echo "Starting incremental ETL process...\n";
            log_message('info', 'CLI incremental ETL process started');
            
            $this->load->model('cp_course_performance_etl_model', 'm_ETL');
            $result = $this->m_ETL->run_etl(true); // true for incremental
            
            echo "Incremental ETL process completed successfully!\n";
            echo "Total records processed: " . $result['total_records'] . "\n";
            echo "Duration: " . $result['duration'] . " seconds\n";
            echo "Peak memory usage: " . $result['peak_memory'] . "\n";
            
            log_message('info', 'CLI incremental ETL process completed successfully');
            
        } catch (Exception $e) {
            echo "Incremental ETL process failed: " . $e->getMessage() . "\n";
            log_message('error', 'CLI incremental ETL process failed: ' . $e->getMessage());
            exit(1);
        }
    }

    /**
     * Run Student Activity Summary ETL process via CLI
     * Usage: php index.php cli run_student_activity_etl [date]
     * @param string $date Optional date parameter (YYYY-MM-DD format)
     */
    public function run_student_activity_etl($date = null)
    {
        try {
            // If no date provided, default to yesterday
            if (!$date) {
                $date = date('Y-m-d', strtotime('-1 day'));
            }
            
            echo "Starting Student Activity Summary ETL process for date: $date...\n";
            log_message('info', 'CLI Student Activity Summary ETL process started for date: ' . $date);
            
            // Load the specific models needed for student activity ETL
            $this->load->model('sas_user_activity_etl_model', 'm_user_activity');
            $this->load->model('sas_actvity_counts_model', 'm_activity_counts');
            $this->load->model('sas_user_counts_model', 'm_user_counts');
            
            $start_time = microtime(true);
            $start_memory = memory_get_usage();
            
            // Update scheduler status to inprogress (2)
            $this->m_user_activity->update_scheduler_status_inprogress($date);
            
            $current_date = date('Y-m-d', strtotime('+1 day'));
            $total_records = 0;
            
            // Step 1: Activity Counts ETL with pagination
            echo "📊 Step 1: Processing Activity Counts ETL...\n";
            try {
                $activity_total = $this->m_activity_counts->get_activity_counts_total_by_date_range($date, $current_date);
                echo "  📈 Total activity records found: $activity_total\n";
                
                if ($activity_total > 0) {
                    $activity_all_data = [];
                    $activity_limit = 1000;
                    $activity_offset = 0;
                    while ($activity_offset < $activity_total) {
                        $activity_batch = $this->m_activity_counts->get_activity_counts_by_date_range($date, $current_date, $activity_limit, $activity_offset);
                        echo "    📦 Batch processed: " . count($activity_batch) . " records (offset: $activity_offset)\n";
                        $activity_all_data = array_merge($activity_all_data, $activity_batch);
                        $activity_offset += $activity_limit;
                    }
                    
                    if (!empty($activity_all_data)) {
                        echo "  💾 Inserting " . count($activity_all_data) . " activity records to database...\n";
                        $insert_result = $this->m_activity_counts->insert_activity_counts_etl($activity_all_data, $date);
                        echo "  ✅ Activity counts inserted successfully. Result: " . json_encode($insert_result) . "\n";
                        $total_records += count($activity_all_data);
                    } else {
                        echo "  ⚠️  No activity data to insert\n";
                    }
                } else {
                    echo "  ⚠️  No activity records found for date range: $date to $current_date\n";
                }
            } catch (Exception $e) {
                echo "  ❌ Error in Activity Counts ETL: " . $e->getMessage() . "\n";
                log_message('error', "Activity Counts ETL error for date $date: " . $e->getMessage());
            }
            
            // Step 2: User Counts ETL with pagination
            echo "👥 Step 2: Processing User Counts ETL...\n";
            try {
                $user_total = $this->m_user_counts->get_user_counts_total_by_date_range($date, $current_date);
                echo "  📈 Total user records found: $user_total\n";
                
                if ($user_total > 0) {
                    $user_all_data = [];
                    $user_limit = 1000;
                    $user_offset = 0;
                    while ($user_offset < $user_total) {
                        $user_batch = $this->m_user_counts->get_user_counts_by_date_range($date, $current_date, $user_limit, $user_offset);
                        echo "    📦 Batch processed: " . count($user_batch) . " records (offset: $user_offset)\n";
                        $user_all_data = array_merge($user_all_data, $user_batch);
                        $user_offset += $user_limit;
                    }
                    
                    if (!empty($user_all_data)) {
                        echo "  💾 Inserting " . count($user_all_data) . " user records to database...\n";
                        $insert_result = $this->m_user_counts->insert_user_counts_etl($user_all_data, $date);
                        echo "  ✅ User counts inserted successfully. Result: " . json_encode($insert_result) . "\n";
                        $total_records += count($user_all_data);
                    } else {
                        echo "  ⚠️  No user data to insert\n";
                    }
                } else {
                    echo "  ⚠️  No user records found for date range: $date to $current_date\n";
                }
            } catch (Exception $e) {
                echo "  ❌ Error in User Counts ETL: " . $e->getMessage() . "\n";
                log_message('error', "User Counts ETL error for date $date: " . $e->getMessage());
            }
            
            // Step 3: Main ETL - join data into user_activity_etl
            echo "🔗 Step 3: Processing Main ETL...\n";
            try {
                $user_activity_data = $this->m_user_activity->get_user_activity_data_paginated(null, $date);
                echo "  📈 User activity data found: " . count($user_activity_data) . " records\n";
                
                if (!empty($user_activity_data)) {
                    echo "  💾 Inserting user activity data to database...\n";
                    $insert_result = $this->m_user_activity->insert_user_activity_etl($user_activity_data, $date);
                    echo "  ✅ User activity inserted successfully. Result: " . json_encode($insert_result) . "\n";
                    $total_records += count($user_activity_data);
                } else {
                    echo "  ⚠️  No user activity data to insert\n";
                }
            } catch (Exception $e) {
                echo "  ❌ Error in Main ETL: " . $e->getMessage() . "\n";
                log_message('error', "Main ETL error for date $date: " . $e->getMessage());
            }
            
            // Update scheduler status to finished (1)
            $this->m_user_activity->update_scheduler_status_finished($date);
            
            $end_time = microtime(true);
            $duration = round($end_time - $start_time, 2);
            $peak_memory = memory_get_peak_usage(true);
            
            $result = [
                'total_records' => $total_records,
                'duration' => $duration,
                'peak_memory' => $this->format_bytes($peak_memory)
            ];
            
            echo "Student Activity Summary ETL process completed successfully for date: $date!\n";
            echo "Total records processed: " . $result['total_records'] . "\n";
            echo "Duration: " . $result['duration'] . " seconds\n";
            echo "Peak memory usage: " . $result['peak_memory'] . "\n";
            
            log_message('info', 'CLI Student Activity Summary ETL process completed successfully for date: ' . $date);
            
        } catch (Exception $e) {
            // Update scheduler status to failed (3)
            if (isset($date)) {
                $this->m_user_activity->update_scheduler_status_failed($date, $e->getMessage());
            }
            echo "Student Activity Summary ETL process failed for date $date: " . $e->getMessage() . "\n";
            log_message('error', 'CLI Student Activity Summary ETL process failed for date ' . $date . ': ' . $e->getMessage());
            exit(1);
        }
    }

    /**
     * Run Student Activity Summary ETL process automatically (for cron jobs)
     * This method automatically finds the last processed date and processes all new data
     * Usage: php index.php cli run_student_activity_etl_auto
     */
    public function run_student_activity_etl_auto()
    {
        try {
            echo "Starting Automatic Student Activity Summary ETL process...\n";
            log_message('info', 'CLI Automatic Student Activity Summary ETL process started');
            
            // Load the specific models needed for student activity ETL
            $this->load->model('sas_user_activity_etl_model', 'm_user_activity');
            $this->load->model('sas_actvity_counts_model', 'm_activity_counts');
            $this->load->model('sas_user_counts_model', 'm_user_counts');
            
            // Get the last processed date directly from database
            $query = $this->db->query("SELECT MAX(extraction_date) as last_processed FROM user_activity_etl");
            $result = $query->row();
            $last_processed_date = $result ? $result->last_processed : null;
            
            if (!$last_processed_date) {
                // If no data exists, start from 7 days ago
                $start_date = date('Y-m-d', strtotime('-7 days'));
                echo "No previous data found. Starting from: $start_date\n";
            } else {
                // Start from the day after the last processed date
                $start_date = date('Y-m-d', strtotime($last_processed_date . ' +1 day'));
                echo "Last processed date: $last_processed_date\n";
                echo "Starting from: $start_date\n";
            }
            
            $end_date = date('Y-m-d', strtotime('-1 day')); // Process until yesterday
            
            // Check if we have dates to process - allow processing even if start_date > end_date
            // This happens when we want to process today's data
            if (strtotime($start_date) > strtotime($end_date) && strtotime($start_date) > strtotime(date('Y-m-d'))) {
                echo "No new data to process. All data is up to date.\n";
                log_message('info', 'No new data to process. All data is up to date.');
                return;
            }
            
            echo "Processing date range: $start_date to $end_date\n";
            
            $current_date = $start_date;
            $total_records_processed = 0;
            $total_dates_processed = 0;
            
            // Process each date in the range
            do {
                echo "Processing date: $current_date\n";
                
                try {
                    // Check if there's data available for this date by checking moodle database
                    $moodle_db = $this->load->database('moodle', TRUE);
                    $moodle_query = $moodle_db->query("SELECT COUNT(*) as count FROM mdl_logstore_standard_log WHERE DATE(FROM_UNIXTIME(timecreated)) = ?", [$current_date]);
                    $moodle_result = $moodle_query->row();
                    $data_count = $moodle_result ? $moodle_result->count : 0;
                    
                    if ($data_count > 0) {
                        echo "  Found $data_count records to process\n";
                        // Process this date
                        $result = $this->process_single_date($current_date);
                        $total_records_processed += $result['total_records'];
                        $total_dates_processed++;
                        echo "✅ Date $current_date processed successfully. Records: {$result['total_records']}\n";
                    } else {
                        echo "⏭️  No data available for date: $current_date\n";
                    }
                    
                } catch (Exception $e) {
                    echo "❌ Error processing date $current_date: " . $e->getMessage() . "\n";
                    log_message('error', "Error processing date $current_date: " . $e->getMessage());
                }
                
                // Move to next date
                $current_date = date('Y-m-d', strtotime($current_date . ' +1 day'));
                
            } while (strtotime($current_date) <= strtotime($end_date));
            
            echo "\n🎉 Automatic ETL process completed!\n";
            echo "Total dates processed: $total_dates_processed\n";
            echo "Total records processed: $total_records_processed\n";
            echo "Date range: $start_date to $end_date\n";
            
            log_message('info', "Automatic ETL process completed. Dates: $total_dates_processed, Records: $total_records_processed");
            
        } catch (Exception $e) {
            echo "❌ Automatic ETL process failed: " . $e->getMessage() . "\n";
            log_message('error', 'Automatic ETL process failed: ' . $e->getMessage());
            exit(1);
        }
    }
    
    /**
     * Run Student Activity Summary ETL process for specific date range
     * Usage: php index.php cli run_student_activity_etl_range [start_date] [end_date]
     * @param string $start_date Start date parameter (YYYY-MM-DD format)
     * @param string $end_date End date parameter (YYYY-MM-DD format)
     */
    public function run_student_activity_etl_range($start_date = null, $end_date = null)
    {
        try {
            // If no dates provided, default to last 7 days
            if (!$start_date) {
                $start_date = date('Y-m-d', strtotime('-7 days'));
            }
            if (!$end_date) {
                $end_date = date('Y-m-d', strtotime('-1 day'));
            }
            
            // Validate date format
            if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $start_date) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $end_date)) {
                throw new Exception('Invalid date format. Use YYYY-MM-DD format.');
            }
            
            // Validate date range
            if (strtotime($start_date) > strtotime($end_date)) {
                throw new Exception('Start date cannot be after end date.');
            }
            
            echo "Starting Student Activity Summary ETL process for date range: $start_date to $end_date...\n";
            log_message('info', 'CLI Student Activity Summary ETL process started for date range: ' . $start_date . ' to ' . $end_date);
            
            // Load the specific models needed for student activity ETL
            $this->load->model('sas_user_activity_etl_model', 'm_user_activity');
            $this->load->model('sas_actvity_counts_model', 'm_activity_counts');
            $this->load->model('sas_user_counts_model', 'm_user_counts');
            
            echo "Processing date range: $start_date to $end_date\n";
            
            $current_date = $start_date;
            $total_records_processed = 0;
            $total_dates_processed = 0;
            
            // Process each date in the range
            do {
                echo "Processing date: $current_date\n";
                
                try {
                    // Check if there's data available for this date by checking moodle database
                    $moodle_db = $this->load->database('moodle', TRUE);
                    $moodle_query = $moodle_db->query("SELECT COUNT(*) as count FROM mdl_logstore_standard_log WHERE DATE(FROM_UNIXTIME(timecreated)) = ?", [$current_date]);
                    $moodle_result = $moodle_query->row();
                    $data_count = $moodle_result ? $moodle_result->count : 0;
                    
                    if ($data_count > 0) {
                        echo "  Found $data_count records to process\n";
                        // Process this date
                        $result = $this->process_single_date($current_date);
                        $total_records_processed += $result['total_records'];
                        $total_dates_processed++;
                        echo "✅ Date $current_date processed successfully. Records: {$result['total_records']}\n";
                    } else {
                        echo "⏭️  No data available for date: $current_date\n";
                    }
                    
                } catch (Exception $e) {
                    echo "❌ Error processing date $current_date: " . $e->getMessage() . "\n";
                    log_message('error', "Error processing date $current_date: " . $e->getMessage());
                }
                
                // Move to next date
                $current_date = date('Y-m-d', strtotime($current_date . ' +1 day'));
                
            } while (strtotime($current_date) <= strtotime($end_date));
            
            echo "\n🎉 ETL process completed for date range!\n";
            echo "Total dates processed: $total_dates_processed\n";
            echo "Total records processed: $total_records_processed\n";
            echo "Date range: $start_date to $end_date\n";
            
            log_message('info', "ETL process completed for date range. Dates: $total_dates_processed, Records: $total_records_processed");
            
        } catch (Exception $e) {
            echo "❌ ETL process failed: " . $e->getMessage() . "\n";
            log_message('error', 'ETL process failed: ' . $e->getMessage());
            exit(1);
        }
    }
    
    /**
     * Process a single date for ETL
     * @param string $date Date to process (YYYY-MM-DD format)
     * @return array Result information
     */
    private function process_single_date($date)
    {
        $start_time = microtime(true);
        $start_memory = memory_get_usage();
        
        echo "  🔍 Starting ETL process for date: $date\n";
        
        // Update scheduler status to inprogress (2)
        $this->m_user_activity->update_scheduler_status_inprogress($date);
        
        $current_date = date('Y-m-d', strtotime($date . ' +1 day'));
        $total_records = 0;
        
        // Step 1: Activity Counts ETL with pagination
        echo "  📊 Step 1: Processing Activity Counts ETL...\n";
        try {
            $activity_total = $this->m_activity_counts->get_activity_counts_total_by_date_range($date, $current_date);
            echo "    📈 Total activity records found: $activity_total\n";
            
            if ($activity_total > 0) {
                $activity_all_data = [];
                $activity_limit = 1000;
                $activity_offset = 0;
                while ($activity_offset < $activity_total) {
                    $activity_batch = $this->m_activity_counts->get_activity_counts_by_date_range($date, $current_date, $activity_limit, $activity_offset);
                    echo "      📦 Batch processed: " . count($activity_batch) . " records (offset: $activity_offset)\n";
                    $activity_all_data = array_merge($activity_all_data, $activity_batch);
                    $activity_offset += $activity_limit;
                }
                
                if (!empty($activity_all_data)) {
                    echo "    💾 Inserting " . count($activity_all_data) . " activity records to database...\n";
                    $insert_result = $this->m_activity_counts->insert_activity_counts_etl($activity_all_data, $date);
                    echo "    ✅ Activity counts inserted successfully. Result: " . json_encode($insert_result) . "\n";
                    $total_records += count($activity_all_data);
                } else {
                    echo "    ⚠️  No activity data to insert\n";
                }
            } else {
                echo "    ⚠️  No activity records found for date range: $date to $current_date\n";
            }
        } catch (Exception $e) {
            echo "    ❌ Error in Activity Counts ETL: " . $e->getMessage() . "\n";
            log_message('error', "Activity Counts ETL error for date $date: " . $e->getMessage());
        }
        
        // Step 2: User Counts ETL with pagination
        echo "  👥 Step 2: Processing User Counts ETL...\n";
        try {
            $user_total = $this->m_user_counts->get_user_counts_total_by_date_range($date, $current_date);
            echo "    📈 Total user records found: $user_total\n";
            
            if ($user_total > 0) {
                $user_all_data = [];
                $user_limit = 1000;
                $user_offset = 0;
                while ($user_offset < $user_total) {
                    $user_batch = $this->m_user_counts->get_user_counts_by_date_range($date, $current_date, $user_limit, $user_offset);
                    echo "      📦 Batch processed: " . count($user_batch) . " records (offset: $user_offset)\n";
                    $user_all_data = array_merge($user_all_data, $user_batch);
                    $user_offset += $user_limit;
                }
                
                if (!empty($user_all_data)) {
                    echo "    💾 Inserting " . count($user_all_data) . " user records to database...\n";
                    $insert_result = $this->m_user_counts->insert_user_counts_etl($user_all_data, $date);
                    echo "    ✅ User counts inserted successfully. Result: " . json_encode($insert_result) . "\n";
                    $total_records += count($user_all_data);
                } else {
                    echo "    ⚠️  No user data to insert\n";
                }
            } else {
                echo "    ⚠️  No user records found for date range: $date to $current_date\n";
            }
        } catch (Exception $e) {
            echo "    ❌ Error in User Counts ETL: " . $e->getMessage() . "\n";
            log_message('error', "User Counts ETL error for date $date: " . $e->getMessage());
        }
        
        // Step 3: Main ETL - join data into user_activity_etl
        echo "  🔗 Step 3: Processing Main ETL...\n";
        try {
            $user_activity_data = $this->m_user_activity->get_user_activity_data_paginated(null, $date);
            echo "    📈 User activity data found: " . count($user_activity_data) . " records\n";
            
            if (!empty($user_activity_data)) {
                echo "    💾 Inserting user activity data to database...\n";
                $insert_result = $this->m_user_activity->insert_user_activity_etl($user_activity_data, $date);
                echo "    ✅ User activity inserted successfully. Result: " . json_encode($insert_result) . "\n";
                $total_records += count($user_activity_data);
            } else {
                echo "    ⚠️  No user activity data to insert\n";
            }
        } catch (Exception $e) {
            echo "    ❌ Error in Main ETL: " . $e->getMessage() . "\n";
            log_message('error', "Main ETL error for date $date: " . $e->getMessage());
        }
        
        // Update scheduler status to finished (1)
        $this->m_user_activity->update_scheduler_status_finished($date);
        
        $end_time = microtime(true);
        $duration = round($end_time - $start_time, 2);
        
        echo "  🎯 ETL process completed for date $date in {$duration}s\n";
        echo "  📊 Total records processed: $total_records\n";
        
        return [
            'total_records' => $total_records,
            'duration' => $duration
        ];
    }

    /**
     * Format bytes to human readable format
     * @param int $bytes
     * @return string
     */
    private function format_bytes($bytes)
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        $bytes /= pow(1024, $pow);
        return round($bytes, 2) . ' ' . $units[$pow];
    }

} 