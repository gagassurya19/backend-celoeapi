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
        $this->load->model('ETL_Model', 'm_ETL');
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


} 