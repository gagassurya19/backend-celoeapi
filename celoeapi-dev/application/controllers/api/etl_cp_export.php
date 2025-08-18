<?php
use Restserver\Libraries\REST_Controller;
defined('BASEPATH') OR exit('No direct script access allowed');

require APPPATH . 'libraries/REST_Controller.php';
require APPPATH . 'libraries/Format.php';

/**
 * Course Performance export API
 *
 * @property CI_DB_query_builder $db
 * @property CI_Input $input
 */
class etl_cp_export extends REST_Controller {

    public function __construct()
    {
        parent::__construct();
        $this->load->database();
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-cache, must-revalidate');
    }

    // GET /api/etl_cp/export
    public function export_get()
    {
        try {
            $limit = intval($this->get('limit')) ?: 100;
            $offset = intval($this->get('offset')) ?: 0;

            if ($limit < 1) { $limit = 100; }
            if ($offset < 0) { $offset = 0; }

            // Optional: include specific tables via comma-separated list
            $tablesParam = $this->get('tables');
            $singleTableParam = $this->get('table');
            $debug = $this->get('debug');

            // Per request: export all data without date filters
            $filters = [];
            $allTables = [
                'cp_student_profile',
                'cp_course_summary',
                'cp_activity_summary',
                'cp_student_quiz_detail',
                'cp_student_assignment_detail',
                'cp_student_resource_access',
            ];

            $requestedTables = $allTables;
            if (!empty($singleTableParam)) {
                $requestedTables = array_values(array_intersect($allTables, [trim($singleTableParam)]));
            }
            if (!empty($tablesParam)) {
                $requested = array_map('trim', explode(',', $tablesParam));
                // Filter only known tables to prevent SQL injection on identifiers
                $requestedTables = array_values(array_intersect($allTables, $requested));
                if (empty($requestedTables)) {
                    $requestedTables = $allTables;
                }
            }

            $tablesResult = [];
            $overallHasNext = false;

            foreach ($requestedTables as $table) {
                $tableResult = $this->fetch_table_page($table, $limit, $offset, $filters);
                if ($debug) { $tableResult['debug'] = $this->table_debug_counts($table, $filters); }
                $tablesResult[$table] = $tableResult;
                if (!empty($tableResult['hasNext'])) {
                    $overallHasNext = true;
                }
            }

            $payload = [
                'success' => true,
                'limit' => $limit,
                'offset' => $offset,
                'hasNext' => $overallHasNext,
                'tables' => $tablesResult,
            ];
            if ($debug) { $payload['debug'] = ['database' => $this->db->database]; }
            return $this->response($payload, 200);
        } catch (Exception $e) {
            return $this->response([
                'success' => false,
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    private function fetch_table_page($table, $limit, $offset, $filters)
    {
        // Ensure identifier safety: this method is only called with whitelisted table names
        $limitPlusOne = $limit + 1;

        $dateColumn = $this->get_table_date_column($table);
        $whereSql = '';
        $params = [];

        if ($dateColumn !== null && !empty($filters)) {
            // Use half-open interval [start, end)
            $whereSql = " WHERE `{$dateColumn}` >= ? AND `{$dateColumn}` < ?";
            $params[] = $filters['start'];
            $params[] = $filters['end'];
        }

        $sql = "SELECT * FROM `{$table}`" . $whereSql . " ORDER BY `id` ASC LIMIT {$limitPlusOne} OFFSET {$offset}";
        $query = $this->db->query($sql, $params);
        $rows = $query->result_array();
        $hasNext = false;
        if (count($rows) > $limit) {
            $hasNext = true;
            // Trim to requested limit
            $rows = array_slice($rows, 0, $limit);
        }
        return [
            'count' => count($rows),
            'hasNext' => $hasNext,
            'nextOffset' => $hasNext ? ($offset + $limit) : null,
            'rows' => $rows,
        ];
    }

    private function table_debug_counts($table, $filters)
    {
        $dateColumn = $this->get_table_date_column($table);
        $total = 0;
        $filtered = null;
        try {
            $q = $this->db->query("SELECT COUNT(*) AS c FROM `{$table}`");
            $row = $q->row_array();
            if ($row && isset($row['c'])) { $total = intval($row['c']); }
        } catch (Exception $e) {
            // ignore
        }
        if ($dateColumn !== null && !empty($filters)) {
            try {
                $q2 = $this->db->query(
                    "SELECT COUNT(*) AS c FROM `{$table}` WHERE `{$dateColumn}` >= ? AND `{$dateColumn}` < ?",
                    [$filters['start'], $filters['end']]
                );
                $row2 = $q2->row_array();
                if ($row2 && isset($row2['c'])) { $filtered = intval($row2['c']); }
            } catch (Exception $e) {
                // ignore
            }
        }
        return [
            'totalCount' => $total,
            'filteredCount' => $filtered,
        ];
    }

    private function get_table_date_column($table)
    {
        // Datetime columns for filtering by date range
        switch ($table) {
            case 'cp_student_quiz_detail':
                return 'waktu_mulai';
            case 'cp_student_assignment_detail':
                return 'waktu_submit';
            case 'cp_student_resource_access':
                return 'waktu_akses';
            default:
                return null; // No date filter for summary/static tables
        }
    }

    private function normalize_date_filters($date, $startDate, $endDate)
    {
        // Returns ['start' => 'YYYY-mm-dd 00:00:00', 'end' => 'YYYY-mm-dd 00:00:00'] or empty array if no filter
        $normalize = function($d) {
            return preg_match('/^\d{4}-\d{2}-\d{2}$/', $d) ? $d : null;
        };

        $date = $date ? $normalize($date) : null;
        $startDate = $startDate ? $normalize($startDate) : null;
        $endDate = $endDate ? $normalize($endDate) : null;

        if ($date) {
            $start = $date . ' 00:00:00';
            $end = date('Y-m-d', strtotime($date . ' +1 day')) . ' 00:00:00';
            return ['start' => $start, 'end' => $end];
        }

        if ($startDate && $endDate) {
            $start = $startDate . ' 00:00:00';
            // Make end exclusive by adding one day to endDate
            $end = date('Y-m-d', strtotime($endDate . ' +1 day')) . ' 00:00:00';
            return ['start' => $start, 'end' => $end];
        }

        return [];
    }
}


