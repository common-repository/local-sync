<?php

class Local_Sync_Files_Op {

	const IMAGE_EXTENSIONS = array(
		'.jpg', '.png', '.jpeg', '.bmp', '.svg', '.ico', '.gif', '.mov', '.mp4', '.mpg', '.mpeg', '.mp3', '.pdf', '.avi', '.mov', '.qt', '.wav', '.rm', '.ram', '.webm'
	);

	public function __construct() {
		global $wpdb;

		$this->wpdb = $wpdb;
		$this->local_sync_options = new Local_Sync_Options();
		$this->exclude_class_obj = new Local_Sync_Exclude_Option();
	}

	public function init() {

	}

	public function init_file_iterator(){
		$this->file_iterator = new Local_Sync_File_Iterator();
		$this->seek_file_iterator = new Local_Sync_Seek_Iterator($this, $type = 'BACKUP', 100);
	}

	public function iterate_files(){

		$this->init_file_iterator();

		// $this->set_is_auto_backup();

		$current_action = $this->local_sync_options->get_option('sync_file_list_current_action');
		$current_action = empty($current_action) ? false : $current_action ;

		switch ($current_action) {
			case false:
				$this->get_folders();
				break;
			case 'get_hash_by_folders':
				$this->get_hash_by_folders();
				break;
			default:
				break;
		}

		//Iterator is completed
		$this->local_sync_options->set_option('got_files_list', 1);
		$this->truncate_processed_iterator_table();
	}

	private function get_folders(){
		$this->file_iterator->get_folders();
		$this->local_sync_options->set_option('sync_file_list_current_action', 'get_hash_by_folders');
		$this->get_hash_by_folders();
	}

	private function get_hash_by_folders(){
		$break = false;
		$loop = $temp_counter = 0;

		while(!$break){
			$dir_meta = $this->get_unfnished_folder();
			$deep_dirs = false;

			if (empty($dir_meta) || $dir_meta->offset === -1) {
				$break = true;
				continue;
			}

			// local_sync_manual_debug('', 'after_get_unfnished_folder_hash');

			$relative_path = wp_normalize_path($dir_meta->name);

			$path = local_sync_add_fullpath($relative_path);

			if( array_search($relative_path, $this->file_iterator->get_deep_dirs()) !== false ){
				$deep_dirs = true;
			}

			if ($deep_dirs === false && $this->is_skip($path, true)) {
				$this->update_iterator($relative_path, -1);
				continue;
			}

			if(local_sync_is_dir($path)){
				$this->get_hash_dir($relative_path, $dir_meta->offset, $temp_counter, $deep_dirs);
			}
		}

		// $this->local_sync_options->set_option('sync_current_action', 'zip_creation');
		return;
	}

	public function insert_into_current_process($qry){
		$sql = "insert into " . $this->wpdb->base_prefix . "local_sync_current_process (file_path, status, file_hash) values $qry";
		$result = $this->wpdb->query($sql);

		if($result === false){
			local_sync_log($sql, "--------insert_into_current_process--error------");
		}
	}

	public function truncate_local_site_file_list_table()	{
		$sql = "TRUNCATE TABLE {$this->wpdb->base_prefix}local_sync_local_site_files";
		$response = $this->wpdb->query($sql);
	}

	public function truncate_processed_iterator_table()	{
		$sql = "TRUNCATE TABLE {$this->wpdb->base_prefix}local_sync_processed_iterator";
		$response = $this->wpdb->query($sql);
	}

	public function get_unfnished_folder() {
		$sql = "SELECT * FROM {$this->wpdb->base_prefix}local_sync_processed_iterator WHERE `offset` != -1 ORDER BY id LIMIT 1";
		$response = $this->wpdb->get_results($sql);

		// local_sync_log($response, '-------get_unfnished_folder---response----');

		return empty($response) ? false : $response[0];
	}

	public function update_iterator($table, $offset) {
		$table_name = "{$this->wpdb->base_prefix}local_sync_processed_iterator";

		$sql = "INSERT INTO `$table_name` (`name`, `offset` ) VALUES (%s,%s) ON DUPLICATE KEY UPDATE `offset` = %s";
		$sql = $this->wpdb->prepare($sql,$table,$offset,$offset);

		$result = $this->wpdb->query($sql);

		if($result === false){
			local_sync_log($sql, "--------update_iterator--query false------");
		}

		// $this->wpdb->replace($table_name, array(
		// 	'name' => $table,
		// 	'offset' => $offset,
		// ));
	}

	private function get_hash_dir($path, $offset, &$temp_counter, $deep_dirs){

		$is_recursive = empty($deep_dirs) ? true : false;

		//local_sync_log($offset, "--------dir_meta_offset----get_hash_dir----");
		//local_sync_log($path, "--------path----get_hash_dir----");

		// if($offset > 20){
		// 	exit;
		// }

		try{
			$this->seek_file_iterator->process_iterator($path, $offset, $is_recursive);
		} catch(Exception $e){

			$exception_msg = $e->getMessage();
			local_sync_log($exception_msg, '---------------Exception-----------------');

			if (local_sync_is_file_iterator_allowed_exception($exception_msg)) {
				//revisit
				// $this->update_iterator($path, -1);
				// $this->exclude_class_obj->exclude_file_list(array('file' => $path, 'isdir' => true, 'category' => 'backup'), true);

				local_sync_die_with_json_encode(array(
					'error' =>  true,
					'msg' => 'Seeking Error :' . $exception_msg . 'So exlcuded this dir automatically',
					'sync_sub_action' => $this->local_sync_options->get_option('sync_sub_action'),
					'sync_current_action' => $this->local_sync_options->get_option('sync_current_action'),
					'requires_next_call' => false
				));
			}

			if (!local_sync_is_seeking_exception($exception_msg)) {
				local_sync_die_with_json_encode(array(
					'error' =>  true,
					'msg' => 'Iterator error',
					'sync_sub_action' => $this->local_sync_options->get_option('sync_sub_action'),
					'sync_current_action' => $this->local_sync_options->get_option('sync_current_action'),
					'requires_next_call' => false
				));	
			}

			local_sync_log($path, '---------------Retry Seeking-----------------');

			$this->update_iterator($path, 0);

			local_sync_die_with_json_encode(array(
				'error' =>  true,
				'msg' => 'Seeking Error :' . $exception_msg,
				'sync_sub_action' => $this->local_sync_options->get_option('sync_sub_action'),
				'sync_current_action' => $this->local_sync_options->get_option('sync_current_action'),
				'requires_next_call' => false
			));
		}

		$this->update_iterator($path, -1);
	}

	public function process_file($iterator, $path, &$counter, $iterator_loop_limit, &$query, $key){

		local_sync_manual_debug('', 'during_backup_file_iterator', 1000);

		global $iterator_files_count_this_call_losy;
		$iterator_files_count_this_call_losy++;

		$this->set_iterator_file_size();

		$file = $iterator->getPathname();

		$file = wp_normalize_path($file);

		if (!$iterator->isReadable()) {

			local_sync_log($file, "--------not readable file--------");

			return ;
		}

		$size = $iterator->getSize();

		$file = wp_normalize_path( $file );

		$relative_file = local_sync_remove_fullpath( $file );

		if($this->is_skip($file)){
			return $this->check_timeout_iter_file($path, $counter, $iterator_loop_limit, $query, $key, $is_skip = true, $relative_file);
		}

		//local_sync_manual_debug('', 'before_hash_check_iterator');

		$file_hash = local_sync_get_hash($file);

		//local_sync_manual_debug('', 'after_hash_get_iterator');

		// if(!$this->processed_files->is_file_modified_from_before_backup($relative_file, $size, $file_hash)){

		// 	return $this->check_timeout_iter_file($path, $counter, $iterator_loop_limit, $query, $key);
		// }


		$query .= empty($query) ? "(" : ",(" ;

		$query .= $this->wpdb->prepare("%s, 'Q', %s)", $relative_file, $file_hash);

		$this->add_iterator_file_size($size);

		$this->check_timeout_iter_file($path, $counter, $iterator_loop_limit, $query, $key, false, $relative_file);
	}

	public function is_skip_wp_config_file($file, $is_dir = false){
		$wp_config_file = ABSPATH . 'wp-config.php';
		if(!$is_dir && $file == $wp_config_file){

			return true;
		}

		return false;
	}

	public function is_skip($file, $is_dir = false){

		$basename = basename($file);

		if ($basename == '.' || $basename == '..') {
			return true;
		}

		if (!is_readable($file)) {
			return true;
		}

		if($this->is_skip_wp_config_file($file, $is_dir)){

			return true;
		}

		if($is_dir === false && local_sync_is_dir($file)){
			return true;
		}

		if (is_local_sync_file($file)) {
			return true;
		}

		$always_backup = local_sync_is_always_include_file($file);
		$is_excluded = $this->exclude_class_obj->is_excluded_file($file);

		if ( $is_excluded && $always_backup === false ) {

			return true;
		}

		if (!$is_excluded) {
			$file_path = local_sync_add_fullpath($file);
			$is_excluded = $this->exclude_class_obj->is_bigger_than_allowed_file_size($file_path) ? true : false;
		}

		if ( $is_excluded && $always_backup === false ) {

			return true;
		}

		if($this->local_sync_options->is_feature_valid('images')){

			$is_image_file = $this->is_upload_dir_media_file_on_prod_site($file);

			if($is_image_file){

				return true;
			}
		}

		// if (strstr($file, 'local_sync_saved_queries.sql') !== false) {
			// if (!apply_filters('is_realtime_valid_query_file_wptc', $file)) {
			// 	return true;
			// }
		// }

		return false;
	}

	public function is_image_ext_present($file='')	{
		$is_present = false;
		foreach (self::IMAGE_EXTENSIONS as $value) {
			if(stripos($file, $value) !== false){

				$is_present = true;
				break;
			}
		}

		return $is_present;
	}

	public function is_upload_dir_media_file_on_prod_site($file = '') {

		$load_images_from_live_site_settings = $this->local_sync_options->get_option('load_images_from_live_site_settings');

		if(empty($load_images_from_live_site_settings) || $load_images_from_live_site_settings == 'no'){
			return false;
		}

		$site_type = $this->local_sync_options->get_option('site_type');
		if( $site_type != 'local' 
			&& stripos($file, LOCAL_SYNC_UPLOADS_DIR . '/') !== false 
			&& $this->is_image_ext_present($file) ){
			// $content_to_write = str_replace(LOCAL_SYNC_UPLOADS_DIR, '', $file) . "\n";
			// file_put_contents(LOCAL_SYNC_UPLOADS_DIR . '/' . LOCAL_SYNC_RELATIVE_EXCLUDE_MEDIA_FILE_LISTS_PATH, $content_to_write, FILE_APPEND);

			$relative_file = local_sync_remove_fullpath( $file );

			$this->prepare_deleted_files_list(array(
				$relative_file
			));

			return true;
		}

		return false;
	}

	private function set_iterator_file_size(){
		global $local_sync_iterator_file_size;

		if (!empty($local_sync_iterator_file_size)) {
			return ;
		}

		$iterator_file_size = $this->local_sync_options->get_option('iterator_file_size');

		$local_sync_iterator_file_size = empty($iterator_file_size) ? 0 : $iterator_file_size;
	}

	private function add_iterator_file_size($size){
		global $local_sync_iterator_file_size;
		$local_sync_iterator_file_size += $size;
	}

	public function check_timeout_iter_file($path, &$temp_counter, &$timeout_limit, &$qry, &$offset, $is_skip = false, $last_file = null){

		$break = is_local_sync_timeout_cut();

		if(!$is_skip){
			$files_count_check = 3000;
			if(defined('ITERATOR_FILES_COUNT_CHECK')){
				$files_count_check = ITERATOR_FILES_COUNT_CHECK;
			}

			global $iterator_files_count_this_call_losy;
			if($iterator_files_count_this_call_losy > $files_count_check){

				local_sync_log($iterator_files_count_this_call_losy, "--------cutting_by_iterator_files_count--------");

				$break = true;
			}
		}

		if (!$break) {
			return ;
		}

		local_sync_log('', "--------normal_cutting--------");

		if (!empty($qry)) {
			$this->insert_into_current_process($qry);
			$qry = '';
		}

		$this->save_iterator_file_size();

		$this->update_iterator($path, $offset);

		if( !empty($last_file) ){
			$this->delete_this_file_from_current_process($last_file);
		}

		local_sync_log($offset, "--------iterator break offset----$last_file----");

		// $this->local_sync_die_with_json_encode(array("status" => "continue", 'msg' => 'Processing files ' . $path, "path" => $path, "offset" => $offset, 'percentage' => 75), 1);

		local_sync_die_with_json_encode(array(
			'success' =>  true,
			'sync_sub_action' => $this->local_sync_options->get_option('sync_sub_action'),
			'sync_current_action' => $this->local_sync_options->get_option('sync_current_action'),
			'requires_next_call' => true
		));

	}

	private function save_iterator_file_size(){
		global $local_sync_iterator_file_size;

		if (empty($local_sync_iterator_file_size)) {
			return ;
		}

		$this->local_sync_options->set_option('iterator_file_size', $local_sync_iterator_file_size);
	}

	public function delete_zip_file() {
		
	}

	public function get_total_no_of_files_zipped() {
		$sql = "SELECT COUNT(file_path) FROM `{$this->wpdb->base_prefix}local_sync_current_process` WHERE STATUS='P'";
		$response = $this->wpdb->get_var($sql);

		if($response === false){
			local_sync_log($sql, "--------get_limited_files_to_zip---error-----");
		}

		return $response;
	}

	public function get_total_no_of_files_to_be_zipped() {
		$sql = "SELECT COUNT(file_path) FROM `{$this->wpdb->base_prefix}local_sync_current_process` WHERE STATUS='Q'";
		$response = $this->wpdb->get_var($sql);

		if($response === false){
			local_sync_log($sql, "--------get_total_no_of_files_to_be_zipped---error-----");
		}

		return $response;
	}

	public function get_prev_uploaded_size($file) {
		$sql = "SELECT prev_uploaded_size FROM `{$this->wpdb->base_prefix}local_sync_current_process` WHERE file_path='$file'";
		$response = $this->wpdb->get_var($sql);

		if($response === false){
			local_sync_log($sql, "--------get_limited_files_to_zip---error-----");

			return 0;
		}

		return $response;
	}

	public function update_current_process_prev_uploaded_size($file_path, $file_size) {
		
		$sql = "UPDATE `{$this->wpdb->base_prefix}local_sync_current_process` SET prev_uploaded_size=$file_size WHERE file_path='$file_path'";
		$response = $this->wpdb->query($sql);

		if($response === false){
			local_sync_log($sql, "--------update_current_process_prev_uploaded_size---error-----");
		}

		return $response;
	}

	public function update_current_file_status($file_path, $status) {
		
		$sql = "UPDATE `{$this->wpdb->base_prefix}local_sync_current_process` SET status='$status' WHERE file_path='$file_path'";
		$response = $this->wpdb->query($sql);

		if($response === false){
			local_sync_log($sql, "--------update_current_file_status---error-----");
		}

		return $response;
	}

	public function get_limited_files_to_zip($limit = 50) {
		$sql = "SELECT file_path FROM `{$this->wpdb->base_prefix}local_sync_current_process` WHERE status='Q' LIMIT $limit";
		$response = $this->wpdb->get_results($sql);

		if($response === false){
			local_sync_log($sql, "--------get_limited_files_to_zip---error-----");
		}

		return $response;
	}

	public function get_limited_files_to_zip_array($limit = 10) {
		$sql = "SELECT file_path FROM `{$this->wpdb->base_prefix}local_sync_current_process` WHERE status='Q' LIMIT $limit";
		$response = $this->wpdb->get_results($sql, ARRAY_A);

		if($response === false){
			local_sync_log($sql, "--------get_limited_files_to_zip---error-----");
		}

		return $response;
	}

	public function get_processed_files_count() {
		$sql = "SELECT COUNT(id) FROM `{$this->wpdb->base_prefix}local_sync_current_process` WHERE status='P'";
		$response = $this->wpdb->get_var($sql);

		if($response === false){
			local_sync_log($sql, "--------get_processed_files_count---error-----");
		}

		return $response;
	}

	public function get_queued_files_count() {
		$sql = "SELECT COUNT(id) FROM `{$this->wpdb->base_prefix}local_sync_current_process` WHERE status='Q'";
		$response = $this->wpdb->get_var($sql);

		if($response === false){
			local_sync_log($sql, "--------get_processed_files_count---error-----");
		}

		return $response;
	}

	public function get_limited_files_to_zip_by_file_paths($file_paths_str = '') {
		if(empty($file_paths_str)){
			return array();
		}
		
		$sql = "SELECT id, file_path, file_hash FROM `{$this->wpdb->base_prefix}local_sync_current_process` WHERE file_path IN ($file_paths_str)";
		$response = $this->wpdb->get_results($sql, ARRAY_A);

		local_sync_log('', "--------got_limited_files_to_zip_by_file_paths--------");

		if($response === false){
			local_sync_log($sql, "--------get_limited_files_to_zip_by_file_paths---error-----");
		}

		return $response;
	}

	public function get_limited_local_site_files_list_info() {
		$sql = "SELECT id, file_path, file_hash FROM `{$this->wpdb->base_prefix}local_sync_local_site_files` WHERE STATUS='Q' LIMIT 50";
		$response = $this->wpdb->get_results($sql, ARRAY_A);

		if($response === false){
			local_sync_log($sql, "--------get_limited_local_site_files_list_info---error-----");
		}

		return $response;
	}

	public function update_current_process_by_file_paths_str($file_paths_str = '') {
		if(empty($file_paths_str)){
			return true;
		}
		
		$sql = "UPDATE `{$this->wpdb->base_prefix}local_sync_current_process` SET status = 'P' WHERE file_path IN ($file_paths_str)";
		$response = $this->wpdb->query($sql);

		if($response === false){
			local_sync_log($sql, "--------update_current_process_by_file_paths_str---error-----");
		}

		return $response;
	}

	public function update_all_current_process_except_ls_backup_files() {
		
		$sql = "UPDATE `{$this->wpdb->base_prefix}local_sync_current_process` SET status = 'P' WHERE file_path NOT LIKE '%wp-content/uploads/local_sync%'";
		$response = $this->wpdb->query($sql);

		if($response === false){
			local_sync_log($sql, "--------update_all_current_process_except_ls_backup_files---error-----");
		}

		return $response;
	}

	public function update_local_site_files_list_by_file_paths_str($file_paths_str = '') {
		if(empty($file_paths_str)){
			return true;
		}
		
		$sql = "UPDATE `{$this->wpdb->base_prefix}local_sync_local_site_files` SET status = 'P' WHERE file_path IN ($file_paths_str)";
		$response = $this->wpdb->query($sql);

		local_sync_log('', "--------updated_local_site_files_list_by_file_paths_str--------");

		if($response === false){
			local_sync_log($sql, "--------update_local_site_files_list_by_file_paths_str---error-----");
		}

		return $response;
	}

	public function delete_this_file_from_current_process($file_name = '') {
		if(empty($file_name)){
			return true;
		}
		
		$sql = "DELETE FROM `{$this->wpdb->base_prefix}local_sync_current_process` WHERE file_path = '$file_name' ";
		$response = $this->wpdb->query($sql);

		if($response === false){
			local_sync_log($sql, "--------delete_this_file_from_current_process---error-----");
		}

		return $response;
	}

	public function mark_modified_files_from_local_file_list_multi_call() {
		$local_list_info = $this->get_limited_local_site_files_list_info();
		$local_list_info_proper_arr = array();

		$is_break = false;
		$is_completed = false;
		do{
			// local_sync_log($local_list_info, "--------local_list_info--------");

			local_sync_manual_debug('', 'mark_modified_files_from_local_file_list_multi_call');

			$file_paths_to_be_deleted = array();
			$file_paths_str = array();

			foreach ($local_list_info as $key => $value) {
				if(stripos($value['file_path'], 'wp-content/uploads/local_sync') === false){
					$file_paths_to_be_deleted[$value['file_path']] = $value['file_path'];
				}

				$file_paths_str[] = '"' . $value['file_path'] . '"';
				$local_list_info_proper_arr[$value['file_path']] = $value;
			}
			$file_paths_str = implode(',', $file_paths_str);

			// local_sync_log($file_paths_str, "--------file_paths_str--------");

			$current_process_files = $this->get_limited_files_to_zip_by_file_paths($file_paths_str);

			local_sync_log(count($current_process_files), "----count----current_process_files--------");

			$file_path_strs_unmodifed = array();
			foreach ($current_process_files as $key => $value) {
				
				//calculating delete files list logic
				unset($file_paths_to_be_deleted[$value['file_path']]);

				if(!empty($value['file_hash']) && $local_list_info_proper_arr[$value['file_path']]['file_hash'] == $value['file_hash']){
					$file_path_strs_unmodifed[] = '"' . $value['file_path'] . '"';
				}
			}

			$file_path_strs_unmodifed = implode(',', $file_path_strs_unmodifed);

			// local_sync_log($file_path_strs_unmodifed, "--------file_path_strs_unmodifed--------");

			// local_sync_log($file_paths_to_be_deleted, "--------file_paths_to_be_deleted--------");

			$this->update_current_process_by_file_paths_str($file_path_strs_unmodifed);

			$this->update_local_site_files_list_by_file_paths_str($file_paths_str);

			local_sync_manual_debug('', 'after_update_local_site_files_list_by_file_paths_str');

			$this->prepare_deleted_files_list($file_paths_to_be_deleted);

			local_sync_manual_debug('', 'after_prepare_deleted_files_list');

			$local_list_info = $this->get_limited_local_site_files_list_info();
			$local_list_info_proper_arr = array();

			if(empty($local_list_info)){
				$is_break = true;
				$is_completed = true;

				$this->add_deleted_state_file_to_current_process();
			}

			if(is_local_sync_timeout_cut(false, 10)){

				local_sync_log('', "--------breaking---mark_modified_files_from_local_file_list_multi_call-----");

				$is_break = true;
			}

		} while($is_break == false);

		return array(
			'is_completed' => $is_completed
		);

	}

	public function add_full_db_file_to_current_process() {
		$full_db_file = $this->local_sync_options->get_backup_dir() . '/local_sync_deleted_files.txt';

		$current_sync_unique_id = $this->local_sync_options->get_option('current_sync_unique_id');
		$full_db_file = rtrim($this->local_sync_options->get_backup_dir(), '/') . '/' . "local_sync_full_db-backup-$current_sync_unique_id.sql.gz";

		if(!file_exists($full_db_file)){
			$full_db_file = rtrim($this->local_sync_options->get_backup_dir(), '/') . '/' . "local_sync_full_db-backup-$current_sync_unique_id.sql";
		}

		if(!file_exists($full_db_file)){
			local_sync_log($full_db_file, "--------full_db_file_doesnt_exist----add_full_db_file_to_current_process----");
		}

		$full_db_file = wp_normalize_path( $full_db_file );
		$relative_file = local_sync_remove_fullpath( $full_db_file );

		$file_hash = uniqid();

		$query = '';
		$query .= empty($query) ? "(" : ",(" ;
		$query .= $this->wpdb->prepare("%s, 'Q', %s)", $relative_file, $file_hash);

		$this->insert_into_current_process($query);
	}

	public function add_deleted_state_file_to_current_process() {
		$deleted_files_list_file = $this->local_sync_options->get_backup_dir() . '/local_sync_deleted_files.txt';
		$deleted_files_list_file = wp_normalize_path( $deleted_files_list_file );
		$relative_file = local_sync_remove_fullpath( $deleted_files_list_file );

		$file_hash = uniqid();

		$query = '';
		$query .= empty($query) ? "(" : ",(" ;
		$query .= $this->wpdb->prepare("%s, 'Q', %s)", $relative_file, $file_hash);

		$this->insert_into_current_process($query);
	}

	public function prepare_deleted_files_list($file_paths_to_be_deleted = array()) {
		$deleted_files_list_file = $this->local_sync_options->get_backup_dir() . '/local_sync_deleted_files.txt';
		if(!file_exists($deleted_files_list_file)){
			file_put_contents($deleted_files_list_file, '');	//revisit file permission
		}

		if(!file_exists($deleted_files_list_file)){
			local_sync_log($deleted_files_list_file, "--------not able to create deleted files list file--------");

			return;
		}

		$value_to_print = '';

		foreach ($file_paths_to_be_deleted as $value) {
			$value_to_print .= $value . "\n";
		}

		file_put_contents($deleted_files_list_file, $value_to_print, FILE_APPEND);

	}

	public function save_files_batch($files_post_obj, $start_range = 0) {
		try {
			
			$current_sync_unique_id = $this->local_sync_options->get_option('current_sync_unique_id');

			foreach($files_post_obj as $kk => $vv){
				$this->save_single_batch_file([
					'file_name' => base64_decode($kk),
					'file_data' => $vv,
				], $start_range);
			}
		} catch (Exception $e) {
			local_sync_die_with_json_encode(array(
				'success' =>  false,
				'msg' =>  'save_files_batch_error' . $e->getMessage(),
				'sync_sub_action' => $this->local_sync_options->get_option('sync_sub_action'),
				'sync_current_action' => $this->local_sync_options->get_option('sync_current_action'),
				'requires_next_call' => false
			));
		}
	}

	public function save_single_batch_file($file_obj = null, $start_range = 0) {
		$full_file_name = rtrim($this->local_sync_options->get_backup_dir(), '/') . '/' . $file_obj['file_name'];

		// local_sync_log($full_file_name, "--------save_single_batch_file--------");

		$this->recursive_mk_directory_of_this_file($full_file_name);

		if( $start_range == 0 ){
			file_put_contents($full_file_name, '');
		}

		$file_data = $file_obj['file_data'];
		if(!empty($file_data)){
			$file_data = gzuncompress(hex2bin($file_data));
		}

		$fp = fopen($full_file_name, 'rb+');
		fseek($fp, $start_range);
		fwrite( $fp, $file_data);

		if(!empty($fp)){
			fclose($fp);
		} else {
			local_sync_log($full_file_name, "--------save_full_file_name---failed-----");
		}

		return true;
	}

	public function recursive_mk_directory_of_this_file($full_file_name) {
		$this_dir_name = dirname($full_file_name);

		if(!is_dir($this_dir_name)){
			$this->recursive_mk_directory_of_this_file($this_dir_name);
		} else {
			return;
		}

		mkdir($this_dir_name, 0755);
	}

	public function save_single_big_file($file_name, $file_data = null, $start_range = 0, $end_range = 0) {
		$current_sync_unique_id = $this->local_sync_options->get_option('current_sync_unique_id');

		$full_file_name = rtrim($this->local_sync_options->get_backup_dir(), '/') . '/' . $file_name;

		$this->recursive_mk_directory_of_this_file($full_file_name);

		if( $start_range == 0 ){
			file_put_contents($full_file_name, '');
		}

		$fp = fopen($full_file_name, 'rb+');
		fseek($fp, $start_range);
		fwrite( $fp, gzuncompress(hex2bin($file_data)) );

		fclose($fp);

		if(!file_exists($full_file_name)){
			local_sync_log($full_file_name, "--------save_single_big_file---failed-----");

			local_sync_die_with_json_encode(array(
				'error' =>  'Cannot create full zip file',
				'msg' =>  'save_single_big_file_failed',
				'sync_sub_action' => $this->local_sync_options->get_option('sync_sub_action'),
				'sync_current_action' => $this->local_sync_options->get_option('sync_current_action'),
				'requires_next_call' => false
			));
		}

		// local_sync_log(filesize($full_file_name), "--------file zise---save_single_big_file-----");

		local_sync_die_with_json_encode(array(
			'success' =>  true,
			'msg' =>  'save_single_big_file_success',
			'file_size' => filesize($full_file_name),
			'sync_sub_action' => $this->local_sync_options->get_option('sync_sub_action'),
			'sync_current_action' => $this->local_sync_options->get_option('sync_current_action'),
			'requires_next_call' => true
		));

		// return filesize($file_list_dump_file);
	}
}
