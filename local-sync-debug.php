<?php

register_shutdown_function('local_sync_fatal_error_hadler');
function local_sync_fatal_error_hadler($return = null) {

	//reference http://php.net/manual/en/errorfunc.constants.php
	$log_error_types = array(
		1 => 'PHP Fatal error',
		2 => 'PHP Warning',
		4 => 'PHP Parse',
		8 => 'PHP Notice error',
		16 => 'PHP Core error',
		32 => 'PHP Core Warning',
		64 => 'PHP Core compile error',
		128 => 'PHP Core compile error',
		256 => 'PHP User error',
		512 => 'PHP User warning',
		1024 => 'PHP User notice',
		2048 => 'PHP Strict',
		4096 => 'PHP Recoverable error',
		8192 => 'PHP Deprecated error',
		16384 => 'PHP User deprecated',
		32767 => 'PHP All',
	);

	$last_error = error_get_last();

	if (empty($last_error) && empty($return)) {
		return ;
	}

	// if ($return) {
	// 	$config = Local_Sync_Factory::get('config');
	// 	$recent_error = $config->get_option('plugin_recent_error');
	// 	if (empty($recent_error)) {
	// 		$recent_error = "Something went wrong ";
	// 	}
	// 	return $recent_error. ". \n Please contact us help@wptimecapsule.com";
	// }

	if (LOCAL_SYNC_ENV === 'local') {
		if (strstr($last_error['file'], 'local-sync') === false ) {
			return ;
		}
	}

	if (strpos($last_error['message'], 'use the CURLFile class') !== false || strpos($last_error['message'], 'Automatically populating') !== false) {
		return ;
	}

	if (strpos($last_error['file'], 'iwp-client') !== false || !defined('LOCAL_SYNC_DEBUG') || !LOCAL_SYNC_DEBUG) {
		return ;
	}

	if(!empty($last_error['type']) && $last_error['type'] == 8192){
		return;
	}
	file_put_contents(LOCAL_SYNC_WP_CONTENT_DIR . '/local-sync-logs.txt', $log_error_types[$last_error['type']] . ": " . $last_error['message'] . " in " . $last_error['file'] . " on " . " line " . $last_error['line'] . "\n", FILE_APPEND);
}

function local_sync_log($value = null, $key = null, $is_print_all_time = true, $forEvery = 0) {
	if (!defined('LOCAL_SYNC_DEBUG') || !LOCAL_SYNC_DEBUG || !$is_print_all_time) {
		return ;
	}

	if (!defined('LOCAL_SYNC_WP_CONTENT_DIR')) {
		define('LOCAL_SYNC_WP_CONTENT_DIR', ABSPATH);
	}

	try {
		global $every_count;
		//$conditions = 'printOnly';

		$usr_time = time();

		if (function_exists('user_formatted_time_local_sync') && class_exists('LOCAL_SYNC_Base_Factory')) {
			$usr_time = user_formatted_time_local_sync(time());
		}

		if (empty($forEvery)) {
			return @file_put_contents(LOCAL_SYNC_WP_CONTENT_DIR . '/local-sync-logs.txt', "\n -----$key------------$usr_time --- " . microtime(true) . "  ----- " . var_export($value, true) . "\n", FILE_APPEND);
		}

		$every_count++;
		if ($every_count % $forEvery == 0) {
			return @file_put_contents(LOCAL_SYNC_WP_CONTENT_DIR . '/local-sync-logs.txt', "\n -----$key------- " . var_export($value, true) . "\n", FILE_APPEND);
		}

	} catch (Exception $e) {
		@file_put_contents(LOCAL_SYNC_WP_CONTENT_DIR . '/local-sync-logs.txt', "\n -----$key----------$usr_time --- " . microtime(true) . "  ------ " . var_export(serialize($value), true) . "\n", FILE_APPEND);
	}
}
