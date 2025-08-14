<?php
/**
 * ETL Real-time Logging Helper
 * 
 * This helper class provides easy methods for ETL processes to add real-time logs
 * that can be monitored via the streaming API endpoints.
 */
class ETL_Realtime_Logger
{
    private $base_url;
    private $auth_token;
    private $log_id;
    private $curl_timeout = 5;

    /**
     * Constructor
     * 
     * @param string $base_url Base URL of the API (e.g., 'http://localhost:8081/index.php')
     * @param string $auth_token Authentication token for API access
     * @param int|null $log_id ETL process log ID (if null, will be auto-detected)
     */
    public function __construct($base_url, $auth_token, $log_id = null)
    {
        $this->base_url = rtrim($base_url, '/');
        $this->auth_token = $auth_token;
        $this->log_id = $log_id;
    }

    /**
     * Set the ETL log ID
     * 
     * @param int $log_id
     */
    public function setLogId($log_id)
    {
        $this->log_id = $log_id;
    }

    /**
     * Add an info log entry
     * 
     * @param string $message
     * @param float|null $progress
     * @return bool Success status
     */
    public function info($message, $progress = null)
    {
        return $this->addLog('info', $message, $progress);
    }

    /**
     * Add a warning log entry
     * 
     * @param string $message
     * @param float|null $progress
     * @return bool Success status
     */
    public function warning($message, $progress = null)
    {
        return $this->addLog('warning', $message, $progress);
    }

    /**
     * Add an error log entry
     * 
     * @param string $message
     * @param float|null $progress
     * @return bool Success status
     */
    public function error($message, $progress = null)
    {
        return $this->addLog('error', $message, $progress);
    }

    /**
     * Add a debug log entry
     * 
     * @param string $message
     * @param float|null $progress
     * @return bool Success status
     */
    public function debug($message, $progress = null)
    {
        return $this->addLog('debug', $message, $progress);
    }

    /**
     * Add a progress update
     * 
     * @param float $progress Progress percentage (0-100)
     * @param string $message Optional message
     * @return bool Success status
     */
    public function progress($progress, $message = null)
    {
        $default_message = $message ?: "Progress: {$progress}%";
        return $this->addLog('info', $default_message, $progress);
    }

    /**
     * Add a log entry with specified level
     * 
     * @param string $level Log level (info, warning, error, debug)
     * @param string $message Log message
     * @param float|null $progress Progress percentage
     * @return bool Success status
     */
    public function addLog($level, $message, $progress = null)
    {
        if (!$this->log_id) {
            // If no log_id set, cannot add logs
            error_log("ETL Realtime Logger: No log_id set. Cannot add log: {$message}");
            return false;
        }

        $data = [
            'log_id' => $this->log_id,
            'level' => $level,
            'message' => $message
        ];

        if ($progress !== null) {
            $data['progress'] = $progress;
        }

        return $this->sendApiRequest('/api/etl/chart/log', 'POST', $data);
    }

    /**
     * Send API request
     * 
     * @param string $endpoint
     * @param string $method
     * @param array $data
     * @return bool Success status
     */
    private function sendApiRequest($endpoint, $method = 'GET', $data = null)
    {
        $url = $this->base_url . $endpoint;
        
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => $this->curl_timeout,
            CURLOPT_HTTPHEADER => [
                "Authorization: Bearer {$this->auth_token}",
                "Content-Type: application/json"
            ],
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false
        ]);

        if ($method === 'POST' && $data) {
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        }

        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($error) {
            error_log("ETL Realtime Logger cURL Error: {$error}");
            return false;
        }

        if ($http_code !== 200 && $http_code !== 201) {
            error_log("ETL Realtime Logger HTTP Error: {$http_code} - Response: {$response}");
            return false;
        }

        $result = json_decode($response, true);
        
        if (!$result || !isset($result['status']) || !$result['status']) {
            error_log("ETL Realtime Logger API Error: " . ($result['message'] ?? 'Unknown error'));
            return false;
        }

        return true;
    }

    /**
     * Create a logger instance with auto-detected log_id
     * This will use the most recent running ETL process
     * 
     * @param string $base_url
     * @param string $auth_token
     * @return ETL_Realtime_Logger|null
     */
    public static function createWithAutoLogId($base_url, $auth_token)
    {
        // For auto log_id, we'll just create the logger without log_id
        // The backend will handle auto-detection when logs are added
        return new self($base_url, $auth_token);
    }

    /**
     * Set cURL timeout
     * 
     * @param int $timeout Timeout in seconds
     */
    public function setTimeout($timeout)
    {
        $this->curl_timeout = $timeout;
    }
}

/**
 * Example usage in ETL process:
 * 
 * // Initialize logger
 * $logger = new ETL_Realtime_Logger(
 *     'http://localhost:8081/index.php',
 *     'default-webhook-token-change-this',
 *     123 // ETL log ID
 * );
 * 
 * // Log various events during ETL process
 * $logger->info('Starting ETL process...');
 * $logger->progress(10, 'Fetching categories from API...');
 * 
 * try {
 *     // Your ETL logic here
 *     $categories = fetch_categories();
 *     $logger->info("Fetched " . count($categories) . " categories");
 *     $logger->progress(50, 'Processing categories...');
 *     
 *     process_categories($categories);
 *     $logger->progress(80, 'Fetching subjects...');
 *     
 *     $subjects = fetch_subjects();
 *     $logger->info("Fetched " . count($subjects) . " subjects");
 *     $logger->progress(90, 'Processing subjects...');
 *     
 *     process_subjects($subjects);
 *     $logger->progress(100, 'ETL process completed successfully');
 *     
 * } catch (Exception $e) {
 *     $logger->error('ETL process failed: ' . $e->getMessage());
 *     throw $e;
 * }
 */ 