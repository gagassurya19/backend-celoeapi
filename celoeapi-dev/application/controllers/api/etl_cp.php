<?php
use Restserver\Libraries\REST_Controller;
defined('BASEPATH') OR exit('No direct script access allowed');

require APPPATH . 'libraries/REST_Controller.php';
require APPPATH . 'libraries/Format.php';

class etl_cp extends REST_Controller {

    public function __construct()
    {
        parent::__construct();
        $this->load->database();
        $this->load->model('cp_etl_model', 'm_cp');
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-cache, must-revalidate');
    }

    // POST /api/etl_cp/run - trigger CP ETL (full or backfill if start_date is provided) in background
    public function run_post()
    {
        try {
            $php = PHP_BINARY ?: 'php';
            $start_date = $this->post('start_date');
            $concurrency = $this->post('concurrency');

            // Prepare a cp_etl_logs entry so status is visible immediately
            $this->load->database();
            $log_id = null;
            $this->db->insert('cp_etl_logs', [
                'offset' => 0,
                'numrow' => 0,
                'status' => 2,
                'type' => !empty($start_date) ? 'backfill' : 'run_etl',
                'message' => null,
                'requested_start_date' => !empty($start_date) ? $start_date : null,
                'extracted_start_date' => null,
                'extracted_end_date' => null,
                'start_date' => date('Y-m-d H:i:s'),
                'end_date' => null,
                'duration_seconds' => null,
                'created_at' => date('Y-m-d H:i:s')
            ]);
            $log_id = $this->db->insert_id();

            if (!empty($start_date)) {
                $conc = intval($concurrency ?: 1);
                $cmd = escapeshellcmd($php) . ' index.php cli run_cp_backfill ' . escapeshellarg($start_date) . ' ' . $conc . ' ' . intval($log_id) . ' > /dev/null 2>&1 &';
                $message = 'CP backfill has been started in the background';
                $extra = ['start_date' => $start_date, 'concurrency' => $conc, 'log_id' => intval($log_id)];
            } else {
                $cmd = escapeshellcmd($php) . ' index.php cli run_cp_etl ' . intval($log_id) . ' > /dev/null 2>&1 &';
                $message = 'CP ETL has been started in the background';
                $extra = ['log_id' => intval($log_id)];
            }

            chdir(FCPATH);
            exec($cmd);
            return $this->response(array_merge([
                'success' => true,
                'message' => $message,
            ], $extra), 202);
        } catch (Exception $e) {
            return $this->response([
                'success' => false,
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    // GET /api/etl_cp/status - last log
    public function status_get()
    {
        $q = $this->db->query('SELECT * FROM cp_etl_logs ORDER BY id DESC LIMIT 1');
        $row = $q->row_array();
        return $this->response([
            'success' => true,
            'last_log' => $row,
        ], 200);
    }

    // POST /api/etl_cp/clear - clear all cp_* data; body: { include_logs?: bool }
    public function clear_post()
    {
        $log_id = null;
        try {
            $include_logs = filter_var($this->post('include_logs'), FILTER_VALIDATE_BOOLEAN);
            // Log start
            $this->db->insert('cp_etl_logs', [
                'offset' => 0,
                'numrow' => 0,
                'status' => 2,
                'type' => 'clear',
                'message' => null,
                'requested_start_date' => null,
                'extracted_start_date' => null,
                'extracted_end_date' => null,
                'start_date' => date('Y-m-d H:i:s'),
                'end_date' => null,
                'duration_seconds' => null,
                'created_at' => date('Y-m-d H:i:s')
            ]);
            $log_id = $this->db->insert_id();

            $t0 = time();
            $result = $this->m_cp->clear_all($include_logs);
            $duration = time() - $t0;
            // Mark success
            $this->db->where('id', $log_id)->update('cp_etl_logs', [
                'status' => 1,
                'numrow' => isset($result['total_cleared']) ? intval($result['total_cleared']) : 0,
                'end_date' => date('Y-m-d H:i:s'),
                'duration_seconds' => $duration,
                'message' => $include_logs ? 'Cleared data and logs' : 'Cleared data'
            ]);

            return $this->response([
                'success' => true,
                'message' => 'CP tables cleared' . ($include_logs ? ' including logs' : ''),
                'log_id' => intval($log_id),
                'cleared' => $result
            ], 200);
        } catch (Exception $e) {
            if ($log_id) {
                $this->db->where('id', $log_id)->update('cp_etl_logs', [
                    'status' => 3,
                    'end_date' => date('Y-m-d H:i:s'),
                    'message' => $e->getMessage()
                ]);
            }
            return $this->response([
                'success' => false,
                'error' => $e->getMessage(),
                'log_id' => $log_id ? intval($log_id) : null
            ], 500);
        }
    }
}


