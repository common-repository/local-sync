<?php

function is_local_sync_options_table_exists() {
	global $wpdb;

	$query = "SHOW TABLES LIKE '%local_sync_options'";
	$table_exists = $wpdb->get_var($query);

	if(!empty($table_exists)){

		return true;
	}

	return false;
}

function local_sync_manual_debug($conditions = '', $printText = '', $forEvery = 0) {
	if (!defined('LOCAL_SYNC_DEBUG') || !LOCAL_SYNC_DEBUG) {
		return ;
	}

	global $debug_count;
	$debug_count++;
	$printText = '-' . $printText;

	global $every_count;
	//$conditions = 'printOnly';

	if (empty($forEvery)) {
		return local_sync_print_memory_debug($debug_count, $conditions, $printText);
	}

	$every_count++;
	if ($every_count % $forEvery == 0) {
		return local_sync_print_memory_debug($debug_count, $conditions, $printText);
	}

}

function local_sync_print_memory_debug($debug_count, $conditions = '', $printText = '') {
	// return;
	global $local_sync_profiling_start;

	$this_memory_peak_in_mb = memory_get_peak_usage();
	$this_memory_peak_in_mb = $this_memory_peak_in_mb / 1048576;

	$this_memory_in_mb = memory_get_usage();
	$this_memory_in_mb = $this_memory_in_mb / 1048576;

	$current_cpu_load = 0;

	if (function_exists('sys_getloadavg')) {
		$cpu_load = sys_getloadavg();
		$current_cpu_load = $cpu_load[0] ?? 0;
	}

	if (empty($local_sync_profiling_start)) {
		$local_sync_profiling_start = time();
	}

	$this_time_taken = time() - $local_sync_profiling_start;

	$human_readable_profile_start = date('H:i:s', $local_sync_profiling_start);

	if ($conditions == 'printOnly') {
		if ($this_memory_peak_in_mb >= 34) {
			file_put_contents(LOCAL_SYNC_WP_CONTENT_DIR . '/local-sync-memory-usage.txt', $debug_count . $printText . " " . round($this_memory_in_mb, 2) . "\n", FILE_APPEND);
			file_put_contents(LOCAL_SYNC_WP_CONTENT_DIR . '/local-sync-time-taken.txt', $debug_count . $printText . " " . round($this_time_taken, 2) . "\n", FILE_APPEND);
			file_put_contents(LOCAL_SYNC_WP_CONTENT_DIR . '/local-sync-cpu-usage.txt', $debug_count . $printText . " " . $current_cpu_load . "\n", FILE_APPEND);
			file_put_contents(LOCAL_SYNC_WP_CONTENT_DIR . '/local-sync-memory-peak.txt', $debug_count . $printText . " " . round($this_memory_peak_in_mb, 2) . "\n", FILE_APPEND);
		}
		return ;
	}

	file_put_contents(LOCAL_SYNC_WP_CONTENT_DIR . '/local-sync-memory-usage.txt', $debug_count . $printText . " " . round($this_memory_in_mb, 2) . "\n", FILE_APPEND);
	file_put_contents(LOCAL_SYNC_WP_CONTENT_DIR . '/local-sync-time-taken.txt', $debug_count . $printText . " " . round($this_time_taken, 2) . "\n", FILE_APPEND);
	file_put_contents(LOCAL_SYNC_WP_CONTENT_DIR . '/local-sync-cpu-usage.txt', $debug_count . $printText . " " . $current_cpu_load . "\n", FILE_APPEND);
	file_put_contents(LOCAL_SYNC_WP_CONTENT_DIR . '/local-sync-memory-peak.txt', $debug_count . $printText . " " . round($this_memory_peak_in_mb, 2) . "\n", FILE_APPEND);
}

function local_sync_is_dir($good_path){
	$good_path = wp_normalize_path($good_path);

	if (is_dir($good_path)) {
		return true;
	}

	$ext = pathinfo($good_path, PATHINFO_EXTENSION);

	if (!empty($ext)) {
		return false;
	}

	if (is_file($good_path)) {
		return false;
	}

	return true;
}

function local_sync_is_wp_content_path($file){
	if (stripos($file, '/' . LOCAL_SYNC_WP_CONTENT_BASENAME) === 0 || stripos($file, LOCAL_SYNC_WP_CONTENT_DIR) === 0) {
		return true;
	}

	return false;
}

function local_sync_add_fullpath($file){
	$file = wp_normalize_path($file);

	if (local_sync_is_wp_content_path($file)) {
		//Special patch for wp-content dir to support common functions of paths.

		$temp_file = $file;

		if(stripos($file, LOCAL_SYNC_RELATIVE_WP_CONTENT_DIR ) === 0 ){
			$temp_file = substr_replace($file, '', 0, strlen(LOCAL_SYNC_RELATIVE_WP_CONTENT_DIR));
			if($temp_file === '' || $temp_file === '/'){
				$temp_file = LOCAL_SYNC_WP_CONTENT_DIR;
			}
		}

		return local_sync_add_custom_path($temp_file, $custom_path = LOCAL_SYNC_WP_CONTENT_DIR . '/');
	}

	return local_sync_add_custom_path($file, $custom_path = LOCAL_SYNC_ABSPATH);
}

function local_sync_add_trailing_slash($string) {
	return local_sync_remove_trailing_slash($string) . '/';
}

function local_sync_remove_trailing_slash($string) {
	return rtrim($string, '/');
}

function local_sync_add_custom_path($file, $custom_path){

	$temp_file = local_sync_add_trailing_slash($file);

	if (stripos($temp_file, $custom_path) !== false) {
		return $file;
	}

	return $custom_path . ltrim($file, '/');
}

function local_sync_remove_custom_path($file, $custom_path, $relative_path){
		// local_sync_log(func_get_args(), "--------" . __FUNCTION__ . "--------");

	if (stripos($file, $custom_path) === false) {
		if(substr($relative_path, -1) === '/'){
			return $relative_path . ltrim($file, '/');
		}

		return $relative_path . '/' . ltrim($file, '/');
	}

	return str_replace($custom_path, $relative_path, $file);
}

function local_sync_remove_fullpath($file){
	$file = wp_normalize_path($file);

	if (local_sync_is_wp_content_path($file)) {

		$temp_file = $file;

		if(stripos($file, LOCAL_SYNC_RELATIVE_WP_CONTENT_DIR ) === 0 ){
			$temp_file = substr_replace($file, '', 0, strlen(LOCAL_SYNC_RELATIVE_WP_CONTENT_DIR));
			if($temp_file === '' || $temp_file === '/'){
				$temp_file = LOCAL_SYNC_WP_CONTENT_DIR;
			}
		}

		if(local_sync_remove_trailing_slash($file) === local_sync_remove_trailing_slash(LOCAL_SYNC_WP_CONTENT_DIR)  ){
			$temp_file = local_sync_remove_trailing_slash($temp_file);
		}


		return local_sync_remove_custom_path($temp_file, $custom_path = LOCAL_SYNC_WP_CONTENT_DIR , $relative_path = LOCAL_SYNC_RELATIVE_WP_CONTENT_DIR );
	}

	return local_sync_remove_custom_path($file, $custom_path = LOCAL_SYNC_ABSPATH, $relative_path = LOCAL_SYNC_RELATIVE_ABSPATH);
}

function is_local_sync_timeout_cut($start_time = false, $reduce_sec = 0) {
	if ($start_time === false) {
		global $local_sync_ajax_start_time;
		if(empty($local_sync_ajax_start_time)){
			$local_sync_ajax_start_time = time();
		}

		$start_time = $local_sync_ajax_start_time;
	}

	$time_diff = time() - $start_time;
	if (!defined('LOCAL_SYNC_TIMEOUT')) {
		define('LOCAL_SYNC_TIMEOUT', 21);
	}
	
	$max_execution_time = LOCAL_SYNC_TIMEOUT - $reduce_sec;
	if ($time_diff >= $max_execution_time) {
		local_sync_log($time_diff, "--------cutin ya--------");
		return true;
	} else {
		// local_sync_log($time_diff, "--------allow--------");
	}
	return false;
}

function is_any_ongoing_local_sync_backup_process() {

	return false;
}

function get_backtrace_string_local_sync($limit = 7) {

	if (!LOCAL_SYNC_DEBUG) {
		return ;
	}

	$bactrace_arr = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, $limit);
	$backtrace_str = '';

	if (!is_array($bactrace_arr)) {
		return false;
	}

	foreach ($bactrace_arr as $k => $v) {
		if ($k == 0) {
			continue;
		}

		$line = empty($v['line']) ? 0 : $v['line'];
		$backtrace_str .= '<-' . $v['function'] . '(line ' . $line . ')';
	}

	return $backtrace_str;
}

function send_response_local_sync($status = null, $type = null, $data = null, $is_log = 0, $clear_request_time = true) {
	//revist
	
	local_sync_log(get_backtrace_string_local_sync(),'---------send_response_local-sync-----------------');

	$post_arr = $data;
	die("<LOCAL_SYNC_START>".json_encode($post_arr)."<LOCAL_SYNC_END>");
}

function local_sync_send_current_backup_response_to_server(){
	$return_array = array();
	send_response_local_sync('progress', LOCAL_SYNC_DEFAULT_CRON_TYPE, $return_array);
}

function local_sync_is_hash_required($file_path){
	if ( is_readable($file_path) && filesize($file_path) < LOCAL_SYNC_HASH_FILE_LIMIT) {
		return true;
	} else {
		return false;
	}
}

function local_sync_is_chunk_hash_required($file_path){
	return (filesize($file_path) > LOCAL_SYNC_HASH_CHUNK_LIMIT) ? true : false;
}

function local_sync_get_hash($file_path, $limit = 0, $offset = 0) {
	// local_sync_log(func_get_args(), '---------func_get_args()------------');
	$is_hash_required = local_sync_is_hash_required($file_path);
	// local_sync_log($is_hash_required, '---------$is_hash_required------------');
	if (!$is_hash_required) {
		return null;
	}
	$chunk_hash = local_sync_is_chunk_hash_required($file_path);
	// local_sync_log($chunk_hash, '---------$chunk_hash------------');
	if ($chunk_hash === false) {

		if ( !file_exists($file_path) || !is_file($file_path) ) {
			return null;
		}

		// md5_file is always faster if we don't chunk the file
		$hash = md5_file($file_path);

		return $hash !== false ? $hash : null;
	}
	$ctx = hash_init('md5');
	if (!$ctx) {
		// Fail to initialize file hashing
		return null;
	}

	$limit = filesize($file_path) - $offset;

	$handle = @fopen($file_path, "rb");
	if ($handle === false) {
		// Failed opening file, cleanup hash context
		hash_final($ctx);

		return null;
	}

	fseek($handle, $offset);

	while ($limit > 0) {
		// Limit chunk size to either our remaining chunk or max chunk size
		$chunkSize = $limit < LOCAL_SYNC_HASH_CHUNK_LIMIT ? $limit : LOCAL_SYNC_HASH_CHUNK_LIMIT;
		$limit -= $chunkSize;

		$chunk = fread($handle, $chunkSize);
		hash_update($ctx, $chunk);
	}

	fclose($handle);

	return hash_final($ctx);
}

function local_sync_is_seeking_exception($exception_msg){
	//Eg: Seek position 29 is out of range
	return ( stripos($exception_msg, 'Seek position') !== false || stripos($exception_msg, 'out of range') !== false );
}

function local_sync_is_file_iterator_allowed_exception($exception_msg){
	//Eg: Seek position 29 is out of range
	return stripos($exception_msg, 'open_basedir restriction in effect') !== false ;
}

function local_sync_is_always_include_file($file){

	$file = local_sync_add_fullpath($file);

	if ( stripos($file, LOCAL_SYNC_WP_CONTENT_DIR) === false){
		return false;
	}

	if ( stripos($file, LOCAL_SYNC_TEMP_DIR_BASENAME) === false ){

		return false;
	}

	if ( strpos($file, 'backup.sql') !== false 
		 || strpos($file, 'local_sync_full_db-backup') !== false
		 || strpos($file, 'local_sync_file_list_dump') !== false 
		 || strpos($file, 'local_sync_files') !== false ) {

		return true;
	} else {

		return false;
	}
}

function is_local_sync_file($file){
	if(stripos($file, 'plugins/' . LOCAL_SYNC_PLUGIN_NAME) === FALSE){
		return false;
	}

	if(stripos($file, 'imagify') !== FALSE){
		return false;
	}

	return true;
}

function local_sync_get_upload_dir(){
	if (defined('LOCAL_SYNC_BRIDGE')) {
		$uploadDir['basedir'] = LOCAL_SYNC_RELATIVE_WP_CONTENT_DIR . '/uploads';
	} else {
		$uploadDir = wp_upload_dir();
	}

	$upload_dir = str_replace(LOCAL_SYNC_ABSPATH, LOCAL_SYNC_RELATIVE_ABSPATH, $uploadDir['basedir']);

	return wp_normalize_path($upload_dir);
}

function local_sync_die_with_json_encode($msg = array('empty data'), $escape = 0, $next_call_30_secs = false){
	$local_sync_options = new Local_Sync_Options();

	global $local_sync_ajax_start_time;

	if(!empty($local_sync_ajax_start_time)){
		$msg['ajax_time_taken'] = time() - $local_sync_ajax_start_time;
	}

	if(!empty($next_call_30_secs)){
		$msg['ajax_time_taken'] = 30;
	}

	$actions_time_taken = update_actions_time_taken_local_sync($msg, $local_sync_options);
	// $msg['actions_time_taken'] = $actions_time_taken;

	$pull_from_live_steps = $local_sync_options->get_option('pull_from_live_steps');
	$pull_from_live_steps = json_decode($pull_from_live_steps, true);
	$msg['process_steps'] = $pull_from_live_steps;

	$msg['site_type'] = $local_sync_options->get_option('site_type');

	// local_sync_log($msg, "--------msg--------");

	reset_last_request_local_sync();

	switch ($escape) {
		case 1:
			$json_encoded_msg = json_encode($msg, JSON_UNESCAPED_SLASHES);
			die('<LOCAL_SYNC_START>' . $json_encoded_msg . '<LOCAL_SYNC_END>');
		case 2:
			$json_encoded_msg = json_encode($msg, JSON_UNESCAPED_UNICODE);
			die('<LOCAL_SYNC_START>' . $json_encoded_msg . '<LOCAL_SYNC_END>');
	}

	$json_encoded_msg = json_encode($msg);
	die('<LOCAL_SYNC_START>' . $json_encoded_msg . '<LOCAL_SYNC_END>');

}

function local_sync_die_with_json_encode_simple($msg = array('empty data'), $escape = 0, $next_call_30_secs = false){
	$copy_msg = $msg;
	if(!empty($copy_msg['file_data'])){
		$copy_msg['file_data'] = 'going something';
	}
	local_sync_log($copy_msg, "--------copy_msg--------");

	switch ($escape) {
		case 1:
			$json_encoded_msg = json_encode($msg, JSON_UNESCAPED_SLASHES);
			die('<LOCAL_SYNC_START>' . $json_encoded_msg . '<LOCAL_SYNC_END>');
		case 2:
			$json_encoded_msg = json_encode($msg, JSON_UNESCAPED_UNICODE);
			die('<LOCAL_SYNC_START>' . $json_encoded_msg . '<LOCAL_SYNC_END>');
	}

	$json_encoded_msg = json_encode($msg);
	die('<LOCAL_SYNC_START>' . $json_encoded_msg . '<LOCAL_SYNC_END>');

}

function update_actions_time_taken_local_sync($msg, $local_sync_options){

	$local_sync_options->set_option('last_action_running', false);

	$actions_time_taken = $local_sync_options->get_option('actions_time_taken');
	$actions_time_taken = json_decode($actions_time_taken, true);

	$sync_current_action = $local_sync_options->get_option('sync_current_action');

	if(empty($actions_time_taken[$sync_current_action])){
		$actions_time_taken[$sync_current_action] = 0;
	}

	$actions_time_taken[$sync_current_action] += $msg['ajax_time_taken'];
	
	$local_sync_options->set_option('actions_time_taken', json_encode($actions_time_taken));

	return $actions_time_taken;
}

function reset_last_request_local_sync(){

}

function parse_local_sync_response_from_raw_data_php($raw_response = null){
	if(empty($raw_response) || !is_string($raw_response)){

		return $raw_response;
	}

	$raw_response = explode('<LOCAL_SYNC_START>', $raw_response);
	$raw_response = array_pop($raw_response);
	$raw_response = explode('<LOCAL_SYNC_END>', $raw_response);
	$raw_response = array_shift($raw_response);

	return $raw_response;
}

function parse_wp_merge_response_from_raw_data_php($raw_response = null){
	if(empty($raw_response) || !is_string($raw_response)){

		return $raw_response;
	}

	$raw_response = explode('<wpmerge_response>', $raw_response);
	$raw_response = array_pop($raw_response);
	$raw_response = explode('</wpmerge_response>', $raw_response);
	$raw_response = array_shift($raw_response);

	return $raw_response;
}

function set_memory_limit_local_sync() {
	$mem_limit = '256M';
	if(defined('WP_MAX_MEMORY_LIMIT')){
		$mem_limit = WP_MAX_MEMORY_LIMIT;
	}

	@ini_set('memory_limit', $mem_limit);
}

function local_sync_set_time_limit($seconds){

	if(!local_sync_function_exist('set_time_limit')){
		return false;
	}

	@set_time_limit($seconds);
}

function local_sync_is_meta_data_backup(){
	return false;
	
	if (!defined('IS_META_DATA_BACKUP_LS') ) {
		return false;
	}

	if (!IS_META_DATA_BACKUP_LS) {
		return false;
	}

	return true;
}

function local_sync_replace_abspath(&$file, $change_reference = true){

	if(!defined('LOCAL_SYNC_BRIDGE')){
		return $file;
	}

	$file = wp_normalize_path($file);

	if (!defined('LOCAL_SYNC_SITE_ABSPATH')) {
		return $file;
	}

	if (LOCAL_SYNC_SITE_ABSPATH === LOCAL_SYNC_ABSPATH) {
		return $file;
	}

	if (stripos($file, LOCAL_SYNC_SITE_ABSPATH) === false) {
		return $file;
	}

	if ($change_reference) {
		$file = str_replace(LOCAL_SYNC_SITE_ABSPATH, LOCAL_SYNC_ABSPATH, $file);
		return $file;
	}

	return str_replace(LOCAL_SYNC_SITE_ABSPATH, LOCAL_SYNC_ABSPATH, $file);
}

function local_sync_function_exist($function){

	if (empty($function)) {
		return false;
	}

	if ( !function_exists($function) ) {
		return false;
	}

	$disabled_functions = explode(',', ini_get('disable_functions'));
	$function_enabled = !in_array($function, $disabled_functions);
	return ($function_enabled) ? true : false;
}

function local_sync_get_file_size($file) {
	clearstatcache();

	if ( !file_exists($file) || !is_file($file) ) {
		return false;
	}

	$normal_file_size = filesize($file);

	if(($normal_file_size !== false)&&($normal_file_size >= 0)) {
		return $normal_file_size;
	}

	$file = realPath($file);

	if(!$file) {
		return false;
	}

	$ch = curl_init("file://" . $file);
	curl_setopt($ch, CURLOPT_PROTOCOLS, CURLPROTO_FILE);
	curl_setopt($ch, CURLOPT_NOBODY, true);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
	curl_setopt($ch, CURLOPT_HEADER, true);
	$data = curl_exec($ch);
	$curl_error = curl_error($ch);
	curl_close($ch);

	if ($data !== false && preg_match('/Content-Length: (\d+)/', $data, $matches)) {
		return (string) $matches[1];
	}

	return $normal_file_size;
}

function is_local_sync_table($tableName) {
	global $wpdb;
	
	$wp_prefix_with_tc_prefix = $wpdb->base_prefix . LOCAL_SYNC_PLUGIN_NAME;
	$local_sync_strpos = stripos($tableName, $wp_prefix_with_tc_prefix);

	if (false !== $local_sync_strpos && $local_sync_strpos === 0) {

		return true;
	}

	return false;
}

function is_wp_merge_table($tableName) {
	global $wpdb;
	
	$wp_prefix_with_tc_prefix = $wpdb->base_prefix . 'wpmerge';

	local_sync_log($wp_prefix_with_tc_prefix, "--------is_wp_merge_table--------");

	$this_sync_strpos = stripos($tableName, $wp_prefix_with_tc_prefix);

	if (false !== $this_sync_strpos && $this_sync_strpos === 0) {

		return true;
	}

	return false;
}
function status_losy($status, $success=true, $return=true, $options='', $multicall=false){
	local_sync_log($status, "--------status_losy--------");
}

function initiate_filesystem_local_sync() {
	// $is_admin_call = false;
	// if(is_admin()){
	// 	$is_admin_call = true;
	// 	global $initiate_filesystem_local_sync_direct_load;
	// 	if (empty($initiate_filesystem_local_sync_direct_load)) {
	// 		$initiate_filesystem_local_sync_direct_load = true;
	// 	} else{
	// 		return false;
	// 	}
	// }

	// if($is_admin_call === false){
	// 	return false;
	// }

	if(!function_exists('request_filesystem_credentials')){
		include_once LOCAL_SYNC_ABSPATH . 'wp-admin/includes/file.php';
	}

	$creds = request_filesystem_credentials("", "", false, false, null);
	if (false === $creds) {
		return false;
	}

	if (!WP_Filesystem($creds)) {
		return false;
	}
}

function is_windows_machine_local_sync(){
	if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
		return true;
	}
	return false;
}

function local_sync_wait_for_sometime(){
	//Windows filesyetem slower so wait for sometime
	if (!is_windows_machine_local_sync()) {
		return ;
	}

	@usleep(250000);
    // Maybe a concurrent script has deleted the file in the meantime
    @clearstatcache();
}

function local_sync_get_table_from_query( $query ) {
	// Remove characters that can legally trail the table name.
	$query = rtrim( $query, ';/-#' );

	// Allow (select...) union [...] style queries. Use the first query's table name.
	$query = ltrim( $query, "\r\n\t (" );

	// Strip everything between parentheses except nested selects.
	$query = preg_replace( '/\((?!\s*select)[^(]*?\)/is', '()', $query );

	// Quickly match most common queries.
	if ( preg_match( '/^\s*(?:'
			. 'SELECT.*?\s+FROM'
			. '|INSERT(?:\s+LOW_PRIORITY|\s+DELAYED|\s+HIGH_PRIORITY)?(?:\s+IGNORE)?(?:\s+INTO)?'
			. '|REPLACE(?:\s+LOW_PRIORITY|\s+DELAYED)?(?:\s+INTO)?'
			. '|UPDATE(?:\s+LOW_PRIORITY)?(?:\s+IGNORE)?'
			. '|DELETE(?:\s+LOW_PRIORITY|\s+QUICK|\s+IGNORE)*(?:.+?FROM)?'
			. ')\s+((?:[0-9a-zA-Z$_.`-]|[\xC2-\xDF][\x80-\xBF])+)/is', $query, $maybe ) ) {
		return str_replace( '`', '', $maybe[1] );
	}

	// SHOW TABLE STATUS and SHOW TABLES WHERE Name = 'wp_posts'
	if ( preg_match( '/^\s*SHOW\s+(?:TABLE\s+STATUS|(?:FULL\s+)?TABLES).+WHERE\s+Name\s*=\s*("|\')((?:[0-9a-zA-Z$_.-]|[\xC2-\xDF][\x80-\xBF])+)\\1/is', $query, $maybe ) ) {
		return $maybe[2];
	}

	// SHOW TABLE STATUS LIKE and SHOW TABLES LIKE 'wp\_123\_%'
	// This quoted LIKE operand seldom holds a full table name.
	// It is usually a pattern for matching a prefix so we just
	// strip the trailing % and unescape the _ to get 'wp_123_'
	// which drop-ins can use for routing these SQL statements.
	if ( preg_match( '/^\s*SHOW\s+(?:TABLE\s+STATUS|(?:FULL\s+)?TABLES)\s+(?:WHERE\s+Name\s+)?LIKE\s*("|\')((?:[\\\\0-9a-zA-Z$_.-]|[\xC2-\xDF][\x80-\xBF])+)%?\\1/is', $query, $maybe ) ) {
		return str_replace( '\\_', '_', $maybe[2] );
	}

	// Big pattern for the rest of the table-related queries.
	if ( preg_match( '/^\s*(?:'
			. '(?:EXPLAIN\s+(?:EXTENDED\s+)?)?SELECT.*?\s+FROM'
			. '|DESCRIBE|DESC|EXPLAIN|HANDLER'
			. '|(?:LOCK|UNLOCK)\s+TABLE(?:S)?'
			. '|(?:RENAME|OPTIMIZE|BACKUP|RESTORE|CHECK|CHECKSUM|ANALYZE|REPAIR).*\s+TABLE'
			. '|TRUNCATE(?:\s+TABLE)?'
			. '|CREATE(?:\s+TEMPORARY)?\s+TABLE(?:\s+IF\s+NOT\s+EXISTS)?'
			. '|ALTER(?:\s+IGNORE)?\s+TABLE'
			. '|DROP\s+TABLE(?:\s+IF\s+EXISTS)?'
			. '|CREATE(?:\s+\w+)?\s+INDEX.*\s+ON'
			. '|DROP\s+INDEX.*\s+ON'
			. '|LOAD\s+DATA.*INFILE.*INTO\s+TABLE'
			. '|(?:GRANT|REVOKE).*ON\s+TABLE'
			. '|SHOW\s+(?:.*FROM|.*TABLE)'
			. ')\s+\(*\s*((?:[0-9a-zA-Z$_.`-]|[\xC2-\xDF][\x80-\xBF])+)\s*\)*/is', $query, $maybe ) ) {
		return str_replace( '`', '', $maybe[1] );
	}

	return false;
}

function local_sync_remove_protocal_from_url($url){
	$url = preg_replace("(^https?://?www.)", "", $url );
	return preg_replace("(^https?://)", "", $url );
}

function local_sync_add_protocal_to_url($url, $protocal, $add_www){
	$trimmed_url = local_sync_remove_protocal_from_url($url);
	$protocal = $protocal . '://';
	return $add_www ? $protocal . 'www.' . $trimmed_url : $protocal . $trimmed_url ;
}

function local_sync_dupx_array_rtrim(&$value) {
	$value = rtrim($value, '\/');
}

function die_with_ls_signature($msg, $option = false){
	$local_sync_options = new Local_Sync_Options();

	global $local_sync_ajax_start_time;

	if(!empty($local_sync_ajax_start_time)){
		$msg['ajax_time_taken'] = time() - $local_sync_ajax_start_time;
	}

	$actions_time_taken = update_actions_time_taken_local_sync($msg, $local_sync_options);

	$pull_from_live_steps = $local_sync_options->get_option('pull_from_live_steps');
	$pull_from_live_steps = json_decode($pull_from_live_steps, true);
	$msg['process_steps'] = $pull_from_live_steps;

	if( !empty($msg) 
		&& !empty($msg['sync_current_action']) 
		&& $msg['sync_current_action'] == 'redirect_to_local_site' ){
		$msg['actions_time_taken'] = $actions_time_taken;
		$msg['total_time_taken'] = 0;

		foreach ($actions_time_taken as $key => $value) {
			$msg['total_time_taken'] += $value;			
		}
	}

	if (!$option) {
		$json_encoded_msg = json_encode($msg);
	} else if($option === 'unescape_slashes'){
		$json_encoded_msg = json_encode($msg, JSON_UNESCAPED_SLASHES);
	}

	local_sync_log($msg, "--------die_with_ls_signature--------");

	$msg_with_secret = '<LOCAL_SYNC_START>' . $json_encoded_msg . '<LOCAL_SYNC_END>';
	die($msg_with_secret);
}

function convert_bytes_to_hr_format_local_sync($size){
		if (empty($size)) {
			$size = 0;
		}

		if (1024 > $size) {
			return $size.' B';
		} else if (1048576 > $size) {
			return round( ($size / 1024) , 2). ' KB';
		} else if (1073741824 > $size) {
			return round( (($size / 1024) / 1024) , 2). ' MB';
		} else if (1099511627776 > $size) {
			return round( ((($size / 1024) / 1024) / 1024) , 2). ' GB';
		}
	}
