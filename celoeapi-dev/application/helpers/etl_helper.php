<?php
defined('BASEPATH') OR exit('No direct script access allowed');

if (!function_exists('etl_debug_enabled')) {
	function etl_debug_enabled()
	{
		$val = getenv('ETL_DEBUG');
		if ($val === false && isset($_ENV['ETL_DEBUG'])) {
			$val = $_ENV['ETL_DEBUG'];
		}
		if ($val === null || $val === false) { return false; }
		$val = strtolower(trim((string)$val));
		return in_array($val, ['1','true','on','yes','y'], true);
	}
}

if (!function_exists('etl_global_logging_enabled')) {
	function etl_global_logging_enabled()
	{
		$val = getenv('ETL_LOG_GLOBAL');
		if ($val === false && isset($_ENV['ETL_LOG_GLOBAL'])) {
			$val = $_ENV['ETL_LOG_GLOBAL'];
		}
		if ($val === null || $val === false) { return false; }
		$val = strtolower(trim((string)$val));
		return in_array($val, ['1','true','on','yes','y'], true);
	}
}

if (!function_exists('etl_log')) {
	function etl_log($level, $message, $context = null)
	{
		if (!etl_debug_enabled()) { return; }
		$allowed = ['error','debug','info','warning','notice'];
		$level = in_array($level, $allowed, true) ? $level : 'debug';
		$prefix = '[ETL] ';
		if ($context !== null) {
			if (is_array($context) || is_object($context)) {
				$message .= ' | context=' . json_encode($context);
			} else {
				$message .= ' | context=' . strval($context);
			}
		}
		$full = $prefix . $message;
		if (etl_global_logging_enabled()) {
			log_message($level, $full);
		}
		if (php_sapi_name() === 'cli') {
			echo date('Y-m-d H:i:s') . ' [' . strtoupper($level) . '] ' . $full . PHP_EOL;
			flush();
		}
	}
}


