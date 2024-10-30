<?php

class Local_Sync_App_Functions {
	private $local_sync_options,
			$wpdb,
			$logger,
			$utils_base,
			$current_iterator_table,
			$exclude_option,
			$wp_version,
			$allowed_free_disk_space;

	const RESET_CHUNK_UPLOAD_ON_FAILURE_LIMIT = 4;

	public function __construct(){
		$this->local_sync_options = new Local_Sync_Options();
		// $this->exclude_option = new Local_Sync_Exclude_Option();
		$this->allowed_free_disk_space = 1024 * 1024 * 10; //10 MB
		$this->retry_allowed_http_status_codes = array(5, 6, 7);
		$this->utils_base = new Local_Sync_Utils();
		$this->init_db();
	}

	public function init_db(){
		global $wpdb;
		$this->wpdb = $wpdb;
	}

	public function set_fresh_install_flags(){
		$this->local_sync_options->set_option('database_version', LOCAL_SYNC_DATABASE_VERSION);
		$this->local_sync_options->set_option('local_sync_version', LOCAL_SYNC_VERSION);
		// $this->local_sync_options->set_option('activity_log_lazy_load_limit', LOCAL_SYNC_ACTIVITY_LOG_LAZY_LOAD_LIMIT);
		$this->exclude_option = new Local_Sync_Exclude_Option();
		$this->exclude_option->insert_default_excluded_files();
		
		// $this->set_user_to_access();

		$this->local_sync_options->set_option('internal_staging_db_rows_copy_limit', LOCAL_SYNC_STAGING_DEFAULT_COPY_DB_ROWS_LIMIT);
		$this->local_sync_options->set_option('internal_staging_file_copy_limit', LOCAL_SYNC_STAGING_DEFAULT_FILE_COPY_LIMIT);
		$this->local_sync_options->set_option('internal_staging_enable_admin_login', 'yes');
		$this->local_sync_options->set_option('user_excluded_extenstions', strtolower(
			'.zip, .mp4, .mp3, .avi, .mov, .mpg, .pdf, .log, .DS_Store, .git, .gitignore, .gitmodules, .svn, .dropbox, .sass-cache, .wpress, .db, .tmp'
			)
		);
		$this->local_sync_options->set_option('user_excluded_extenstions_staging', strtolower(
			'.zip, .mp4, .mp3, .avi, .mov, .mpg, .pdf, .log, .DS_Store, .git, .gitignore, .gitmodules, .svn, .dropbox, .sass-cache, .wpress, .db, .tmp'
			)
		);
		$this->local_sync_options->set_option('user_excluded_files_more_than_size_settings', serialize(array('status' => true, 'size' => 52428800) )); //50MB
		$this->local_sync_options->set_option('backup_db_query_limit', LOCAL_SYNC_DEFAULT_DB_ROWS_BACKUP_LIMIT);

		// $this->register_Must_Use();
	}

	public function register_Must_Use(){

		return;
	}

	private function registerMustUse($loaderName, $loaderContent) {

		return;

		$mustUsePluginDir = rtrim(WPMU_PLUGIN_DIR, '/');
		$loaderPath       = $mustUsePluginDir.'/'.$loaderName;

		if (file_exists($loaderPath) && md5($loaderContent) === md5_file($loaderPath)) {
			return;
		}

		if (!is_dir($mustUsePluginDir)) {
			$dirMade = @mkdir($mustUsePluginDir, 0755);

			if (!$dirMade) {
				$error = error_get_last();
				return array('');
				throw new Exception(sprintf('Unable to create loader directory: %s', $error['message']));
			}
		}

		if (!is_writable($mustUsePluginDir)) {
			throw new Exception('MU-plugin directory is not writable.');
		}

		$loaderWritten = @file_put_contents($loaderPath, $loaderContent);

		if (!$loaderWritten) {
			$error = error_get_last();
			throw new Exception(sprintf('Unable to write loader: %s', $error['message']));
		}
	}

	public function set_user_to_access(){

		return;

		if ( ! function_exists( 'wp_get_current_user' ) ){
			include_once ABSPATH.'wp-includes/pluggable.php';
		}

		$username = $this->get_current_user_meta('user_login');

		if (empty($username)) {
			return false;
		}
	}

	public function get_current_user_meta($key){
		if ( ! function_exists( 'wp_get_current_user' ) )
			include_once ABSPATH.'wp-includes/pluggable.php';

		$user = wp_get_current_user();

		if (empty($user) || empty($user->data) || empty($user->data->$key)) {
			return false;
		}

		return $user->data->$key;
	}

	public function verify_ajax_requests($admin_check = true){

		//verify its ajax request
		if (empty($_POST['action'])) {
			return false;
		}


		//Verifies the Ajax request to prevent processing requests external of the site
		$result = check_ajax_referer( 'ls_revmakx', 'security', false );

		// local_sync_log($result, "-----check_ajax_referer---result--------");

		if(empty($result) || $result == -1){
			local_sync_die_with_json_encode(array(
				'error' => true,
				'msg' => 'Ajax nonce verify failed'
			));
		}

		if (!$admin_check) {
			return true;
		}

		//Check request made by admin
		if (!is_admin()) {
			local_sync_die_with_json_encode(array(
				'error' => true,
				'msg' => 'Ajax nonce verify failed, not an admin'
			));
		}
	}

	public function wp_remote_post_local_sync($url, $post_body = array(), $remove_local_sync_signature = true) {

		$post_body['is_local_sync'] = true;
		$post_body['prod_key_random_id'] = $this->local_sync_options->get_option('prod_key_random_id');

		$timeout = 30;
		if(!empty($post_body) && !empty($post_body['action']) && $post_body['action'] == 'check_validity'){
			$timeout = 60;
		}
		$response = wp_remote_post( $url, array(
			'method' => 'POST',
			'timeout' => $timeout,
			'redirection' => 5,
			'sslverify' => false,
			'blocking' => true,
			'headers' => array(),
			'body' => base64_encode(json_encode($post_body)),
			'cookies' => array()
		    )
		);

		$raw_response = wp_remote_retrieve_body( $response );

		$response = parse_local_sync_response_from_raw_data_php($raw_response);

		local_sync_log($response, "-----wp_remote_post_local_sync---response-----$url---");
		
		if(empty($response)){
			local_sync_log($raw_response, "-----wp_remote_post_local_sync---raw_response-----$url---");
		}

		return json_decode($response, true);
	}

	public function start_db_dump() {

		$this->local_sync_options->set_option('sync_sub_action', 'db_dump_preparation');

		$current_sync_type = $this->local_sync_options->get_option('sync_type_db_or_files');

		local_sync_log($current_sync_type, "--------sync_type_db_or_files--------");
			
		$do_db_backup = true;
		if(!empty($current_sync_type) && $current_sync_type == 'files_alone'){
			$do_db_backup = false;
		}

		if($do_db_backup){
			$is_gz_completed = $this->local_sync_options->get_option('local_sync_db_gz_1_completed');
			$is_bk_completed = $this->local_sync_options->get_option('local_sync_db_backup_1_completed');

			if(!$is_bk_completed){
				$this->db_obj = new Local_Sync_DB_Op();
				$this->db_obj->backup_database();
			}

			$is_bk_completed = $this->local_sync_options->get_option('local_sync_db_backup_1_completed');

			if($is_bk_completed && !$is_gz_completed){
				$this->db_obj = new Local_Sync_DB_Op();
				$this->db_obj->gz_compress();
			}
		}

		$this->local_sync_options->set_this_current_action_step('done');
		$this->local_sync_options->set_option('sync_current_action', 'start_file_list_preparation');
		$this->local_sync_options->set_this_current_action_step('processing');

		local_sync_die_with_json_encode(array(
			'success' =>  true,
			'sync_sub_action' => $this->local_sync_options->get_option('sync_sub_action'),
			'sync_current_action' => $this->local_sync_options->get_option('sync_current_action'),
			'prod_site_url_enc' => $this->local_sync_options->get_option('local_site_url_enc'),
			'prod_admin_url_enc' => $this->local_sync_options->get_option('local_admin_url_enc'),
			'prod_uploads_url_enc' => $this->local_sync_options->get_option('local_uploads_url_enc'),
			'load_images_from_live_site_settings' => $this->local_sync_options->get_option('load_images_from_live_site_settings'),
			'away_site_abspath' => wp_normalize_path(ABSPATH),
			'is_away_site_multisite' => defined('MULTISITE') ? MULTISITE : false,
			'away_site_id_current_site' => defined('SITE_ID_CURRENT_SITE') ? SITE_ID_CURRENT_SITE : false,
			'away_blog_id_current_site' => defined('BLOG_ID_CURRENT_SITE') ? BLOG_ID_CURRENT_SITE : false,
			'away_site_db_prefix' => $this->wpdb->base_prefix,
			'requires_next_call' => true
		), 1);
	}

	public function start_db_dump_local_file_list() {

		local_sync_log('', "--------start_db_dump_local_file_list--------");

		$this->local_sync_options->set_option('sync_sub_action', 'start_db_dump_local_file_list');
		$this->local_sync_options->set_option('sync_current_action', 'start_db_dump_local_file_list');

		if($this->local_sync_options->is_feature_valid('filesDiff')){
			$is_gz_completed = $this->local_sync_options->get_option('local_sync_db_gz_2_completed');
			$is_bk_completed = $this->local_sync_options->get_option('local_sync_db_backup_2_completed');

			if(!$is_bk_completed){
				$this->db_obj = new Local_Sync_DB_Op();
				$this->db_obj->backup_database_only_local_file_list();
			}

			$is_bk_completed = $this->local_sync_options->get_option('local_sync_db_backup_2_completed');

			if($is_bk_completed && !$is_gz_completed){
				$this->db_obj = new Local_Sync_DB_Op();
				$this->db_obj->gz_compress('local_files_list_table');
			}
		}

		$site_type = $this->local_sync_options->get_option('site_type');
		
		$this->local_sync_options->set_this_current_action_step('done');
		if(empty($site_type) || $site_type == 'local'){
			$this->local_sync_options->set_option('sync_current_action', 'upload_local_file_list_dump');
			$this->local_sync_options->set_option('sync_sub_action', 'upload_local_file_list_dump');
			$this->local_sync_options->set_this_current_action_step('processing');
		} elseif($site_type == 'production'){
			$this->local_sync_options->set_option('sync_current_action', 'download_local_file_list_dump');
			$this->local_sync_options->set_option('sync_sub_action', 'download_local_file_list_dump');
			$this->local_sync_options->set_this_current_action_step('processing');
		}

		local_sync_die_with_json_encode(array(
			'success' =>  true,
			'sync_sub_action' => $this->local_sync_options->get_option('sync_sub_action'),
			'sync_current_action' => $this->local_sync_options->get_option('sync_current_action'),
			'requires_next_call' => true
		));
	}

	public function process_prod_key_and_set_prod_url($prod_key = '') {
		if(empty($prod_key)){
			local_sync_die_with_json_encode_simple(array(
				'error' =>  'Invalid Prod Key',
				'sync_sub_action' => $this->local_sync_options->get_option('sync_sub_action'),
				'sync_current_action' => $this->local_sync_options->get_option('sync_current_action'),
				'requires_next_call' => false
			));
		}

		$decoded_prod_key = json_decode(base64_decode($prod_key), true);
		$url = $decoded_prod_key['url'];

		local_sync_log($decoded_prod_key, "--------decoded_prod_key--------");

		if(empty($url)){
			local_sync_die_with_json_encode_simple(array(
				'error' =>  'Invalid Prod Key URL',
				'sync_sub_action' => $this->local_sync_options->get_option('sync_sub_action'),
				'sync_current_action' => $this->local_sync_options->get_option('sync_current_action'),
				'requires_next_call' => false
			));
		}

		$prod_key_random_id = $decoded_prod_key['prod_key_random_id'] ?? 0;

		if(!empty($url)){
			$url = rtrim($url, '/');
			$this->local_sync_options->set_option('prod_site_url', $url);
			$this->local_sync_options->set_option('prod_site_url_enc', base64_encode($url) );
			$this->local_sync_options->set_option('prod_key', $prod_key);
			$this->local_sync_options->set_option('prod_key_random_id', $prod_key_random_id);
		}

		return $url;
	}

	public function process_upload_local_file_list_dump()	{

		if($this->local_sync_options->is_feature_valid('filesDiff')){

			$current_sync_unique_id = $this->local_sync_options->get_option('current_sync_unique_id');
			$URL = rtrim($this->local_sync_options->get_option('prod_site_url'), '/') . '/index.php';
			$file = rtrim($this->local_sync_options->get_backup_dir(), '/') . '/' . "local_sync_file_list_dump-$current_sync_unique_id.sql.gz";

			if(!file_exists($file)){
				$file = rtrim($this->local_sync_options->get_backup_dir(), '/') . '/' . "local_sync_file_list_dump-$current_sync_unique_id.sql";
			}

			$prev_result = $this->local_sync_options->get_option('upload_current_result');
			$prev_result = json_decode($prev_result, true);

			$bridge_fs_obj = new LocalSyncFileSystem();
			$upload_result = $bridge_fs_obj->multi_call_upload_using_curl($URL, $file, $uploadResponseHeaders, $prev_result, false, 'local_file_list');

			if(empty($upload_result)){

				local_sync_log($bridge_fs_obj->last_error, "--------process_upload_local_file_list_dump---error-----");

				$this->local_sync_options->set_this_current_action_step('error');

				local_sync_die_with_json_encode(array(
					'error' =>  $bridge_fs_obj->last_error,
					'sync_sub_action' => $this->local_sync_options->get_option('sync_sub_action'),
					'sync_current_action' => $this->local_sync_options->get_option('sync_current_action'),
					'requires_next_call' => false
				));
			}

			local_sync_log($upload_result, "--------upload_result-----process_upload_local_file_list_dump---");
			local_sync_log($uploadResponseHeaders, "--------uploadResponseHeaders-----process_upload_local_file_list_dump---");

			$this->local_sync_options->set_option('upload_current_result', json_encode($upload_result));

		}

		if( !$this->local_sync_options->is_feature_valid('filesDiff') || 
			empty($upload_result['is_upload_multi_call']) ){
			$this->local_sync_options->set_this_current_action_step('done');
			$this->local_sync_options->set_option('sync_current_action', 'start_db_dump');
			$this->local_sync_options->set_option('sync_sub_action', 'start_db_dump');
			$this->local_sync_options->set_this_current_action_step('processing');
		}

		local_sync_die_with_json_encode(array(
			'success' =>  true,
			'sync_sub_action' => $this->local_sync_options->get_option('sync_sub_action'),
			'sync_current_action' => $this->local_sync_options->get_option('sync_current_action'),
			'requires_next_call' => true
		));
	}

	public function start_file_list_preparation() {
		$this->local_sync_options->set_option('sync_sub_action', 'file_list_preparation');

		// $this->local_sync_options->set_option('sync_sub_action', 'file_list_preparation');
		// $this->local_sync_options->set_option('sync_file_list_current_action', false);
		// $this->local_sync_options->set_option('got_files_list', 0);
		// $this->local_sync_files_op->truncate_processed_iterator_table();
		// $this->local_sync_files_op->truncate_current_process_table();

		$this->local_sync_files_op = new Local_Sync_Files_Op();

		$current_sync_type = $this->local_sync_options->get_option('sync_type_db_or_files');

		local_sync_log($current_sync_type, "--------sync_type_db_or_files--------");
		
		if(!empty($current_sync_type) && $current_sync_type == 'db_alone'){

			$this->local_sync_options->set_this_current_action_step('done');

			$this->local_sync_files_op->add_full_db_file_to_current_process();
			$next_action = 'batch_upload_files';
			$site_type = $this->local_sync_options->get_option('site_type');
			if($site_type == 'production'){
				$next_action = 'send_batch_download_requests';
			}
			$this->local_sync_options->set_option('sync_current_action', $next_action);

			$this->local_sync_options->set_this_current_action_step('processing');

			local_sync_die_with_json_encode(array(
				'success' =>  true,
				'sync_sub_action' => $this->local_sync_options->get_option('sync_sub_action'),
				'sync_current_action' => $this->local_sync_options->get_option('sync_current_action'),
				'requires_next_call' => true
			));

			return;
		}

		$this->local_sync_files_op->iterate_files();

		$this->local_sync_options->set_this_current_action_step('done');
		$this->local_sync_options->set_option('sync_current_action', 'import_local_file_list_dump');
		$this->local_sync_options->set_this_current_action_step('processing');

		local_sync_die_with_json_encode(array(
			'success' =>  true,
			'sync_sub_action' => $this->local_sync_options->get_option('sync_sub_action'),
			'sync_current_action' => $this->local_sync_options->get_option('sync_current_action'),
			'requires_next_call' => true
		));
	}

	public function start_file_list_preparation_for_local_dump() {
		$this->local_sync_options->set_option('sync_sub_action', 'file_list_preparation_for_local_dump');
		$this->local_sync_options->set_option('sync_current_action', 'file_list_preparation_for_local_dump');

		$this->local_sync_files_op = new Local_Sync_Files_Op();

		$current_sync_type = $this->local_sync_options->get_option('sync_type_db_or_files');
		
		if(!empty($current_sync_type) && $current_sync_type == 'db_alone'){
			$this->local_sync_options->set_this_current_action_step('done');
			$this->local_sync_options->set_option('sync_current_action', 'start_db_dump');
			$this->local_sync_options->set_this_current_action_step('processing');

			local_sync_die_with_json_encode(array(
				'success' =>  true,
				'sync_sub_action' => $this->local_sync_options->get_option('sync_sub_action'),
				'sync_current_action' => $this->local_sync_options->get_option('sync_current_action'),
				'requires_next_call' => true
			));

			return;
		}

		if(!$this->local_sync_options->is_feature_valid('filesDiff')){
			$this->local_sync_options->set_this_current_action_step('done');
			$this->local_sync_options->set_option('sync_current_action', 'start_db_dump_local_file_list');
			$this->local_sync_options->set_this_current_action_step('processing');

			local_sync_die_with_json_encode(array(
				'success' =>  true,
				'sync_sub_action' => $this->local_sync_options->get_option('sync_sub_action'),
				'sync_current_action' => $this->local_sync_options->get_option('sync_current_action'),
				'requires_next_call' => true
			));

			return;
		}

		$this->local_sync_files_op->iterate_files();

		$this->local_sync_options->set_this_current_action_step('done');
		$this->local_sync_options->set_option('sync_current_action', 'start_db_dump_local_file_list');
		$this->local_sync_options->set_this_current_action_step('processing');

		// $this->start_db_dump_local_file_list();

		local_sync_die_with_json_encode(array(
			'success' =>  true,
			'sync_sub_action' => $this->local_sync_options->get_option('sync_sub_action'),
			'sync_current_action' => $this->local_sync_options->get_option('sync_current_action'),
			'requires_next_call' => true
		));
	}

	public function create_zip() {
		try{
			$current_sync_unique_id = $this->local_sync_options->get_option('current_sync_unique_id');
			$file = rtrim($this->local_sync_options->get_backup_dir(), '/') . '/' . "local_sync_files-$current_sync_unique_id.zip";

			local_sync_log('', "--------create_zip---start-----");

			$backup_dir = $this->local_sync_options->get_backup_dir();
			if (!is_dir($backup_dir) && !mkdir($backup_dir, 0755)) {

				$this->local_sync_options->set_this_current_action_step('error');

				$err_msg = "Could not create backup directory ($backup_dir)";
				local_sync_die_with_json_encode(array(
					'error' =>  $err_msg,
					'sync_sub_action' => $this->local_sync_options->get_option('sync_sub_action'),
					'sync_current_action' => $this->local_sync_options->get_option('sync_current_action'),
					'requires_next_call' => false
				));
			}

			global $backup_core_losy;

			$backup_core_losy = new Local_Sync_Zip_Facade();

			local_sync_log($backup_core_losy->use_zip_object, "--------selected zip method is--------");

			$zipfile = $file;
			$zip = new $backup_core_losy->use_zip_object;

			if (file_exists($zipfile)) {
				$opencode = $zip->open($zipfile);
				$original_size = filesize($zipfile);
				clearstatcache();

				local_sync_log($opencode, "--------opencode--------");
				local_sync_log($zip->last_error, "--------archive error--------");
			} else {

				$fp = fopen($zipfile, 'w');
				fwrite($fp, '');
				fclose($fp);

				// $create_code = (version_compare(PHP_VERSION, '5.2.12', '>') && defined('ZIPARCHIVE::CREATE')) ? ZIPARCHIVE::CREATE : 1;
				$opencode = $zip->open($zipfile);

				local_sync_log($opencode, "--------opencode----2----");
				local_sync_log($zip->last_error, "--------archive error------2--");

				$original_size = 0;
			}

			if(empty($opencode)){

				$this->local_sync_options->set_this_current_action_step('error');

				local_sync_die_with_json_encode(array(
					'error' =>  $zip->last_error,
					'sync_sub_action' => $this->local_sync_options->get_option('sync_sub_action'),
					'sync_current_action' => $this->local_sync_options->get_option('sync_current_action'),
					'requires_next_call' => false
				));
			}

			$this->local_sync_files_op = new Local_Sync_Files_Op();
			$files_to_zip = $this->local_sync_files_op->get_limited_files_to_zip();

			local_sync_log(count($files_to_zip), "--------files_to_zips--------");

			$can_continue = true;
			$is_completed = false;

			if(empty($files_to_zip)){
				local_sync_log('', "--------no files to be zipped this time----empty----");

				$files_to_zip = array();
				$can_continue = false;
				$is_completed = true;
			}

			$zip_closed = false;
			$zip_open_needed = false;
			$files_size_so_far = 0;
			$files_to_zip_this_call = 0;
			$files_to_zip_this_time = 0;

			$this_start_time = time();

			do{

				local_sync_manual_debug('', 'during_zipping', 1000);

				$files_status_completed = array();
				foreach ($files_to_zip as $kk => $file_obj) {
					if($zip_open_needed){
						$zip->open($zipfile);
					}

					$zip_closed = false;

					$file_path = trim($file_obj->file_path, '/');
					$file_full_path = ABSPATH . $file_path;
					$add_as = $file_path;

					if( file_exists($file_full_path) 
						&& filesize($file_full_path) > 50*1024*1024 
						&& $files_to_zip_this_call != 0 ){

						local_sync_log($file_path, "--------spotted big file during zip so breaking it--------");

						$can_continue = false;
						break;
					}

					if(file_exists($file_full_path)){
						$zip->addFile($file_full_path, $add_as);
						$files_size_so_far = $files_size_so_far + filesize($file_full_path);
					}

					$files_to_zip_this_call++;
					$files_to_zip_this_time++;

					$files_status_completed[] = '"'.$file_obj->file_path.'"';


					// local_sync_log($files_size_so_far, "--------files_size_so_far--------");

					if( $files_size_so_far > 2*1024*1024 ){
						$this_time_diff = time() - $this_start_time;

						local_sync_log($this_time_diff, "--------this_time_diff--before close------");

						local_sync_log($files_size_so_far, "--------files_size_so_far--reached---$this_time_diff---");
						local_sync_log($files_to_zip_this_time, "--------files_to_zip_this_time--reached------");

						$files_size_so_far = 0;
						$zip_open_needed = true;
						$zip_closed = true;
						$files_to_zip_this_time = 0;

						$zip_close_result = $zip->close();

						$this_time_diff = time() - $this_start_time;

						local_sync_log($this_time_diff, "--------after zip close 1--------");

						if (!$zip_close_result) {

							local_sync_log($zip->last_error, "--------create_zip_failure---1-----");

							$this->local_sync_options->set_this_current_action_step('error');

							local_sync_die_with_json_encode(array(
								'error' =>  true,
								'msg' => 'zipping failed',
								'sync_sub_action' => $this->local_sync_options->get_option('sync_sub_action'),
								'sync_current_action' => $this->local_sync_options->get_option('sync_current_action'),
								'requires_next_call' => false
							));
						} else {
							$can_continue = false;
							
							break;
						}
					}

					if(is_local_sync_timeout_cut(false, 13)){
						$can_continue = false;

						$this_time_diff = time() - $this_start_time;

						local_sync_log('', "--------breaking zip creation----$this_time_diff----");

						break;
					}
				}

				$files_status_completed_str = implode(',', $files_status_completed);

				if(!empty($files_status_completed_str)){
					$sql = "UPDATE `{$this->wpdb->base_prefix}local_sync_current_process` SET status='P' WHERE file_path IN ({$files_status_completed_str})";
					$db_result = $this->wpdb->query($sql);

					// local_sync_log($sql, "--------sql--files_status_completed_str------");

					if($db_result === false){
						local_sync_log($sql, "--------db_result_error---files_status_completed_str-----");
					}
				}

				$files_to_zip = $this->local_sync_files_op->get_limited_files_to_zip();
				if( empty($files_to_zip) ){

					local_sync_log('', "--------empty files to zip 2--------");

					$can_continue = false;
					$is_completed = true;
				}

			} while ($can_continue);

			$this_time_diff = time() - $this_start_time;

			local_sync_log($zip->last_error, "--------below while loop---$this_time_diff-----");

			if(!$zip_closed){
				$zip_close_result = $zip->close();

				local_sync_log($this_time_diff, "--------after zip close 1--------");

				if (!$zip_close_result) {

					local_sync_log($zip->last_error, "--------create_zip_failure----2----");

					$this->local_sync_options->set_this_current_action_step('error');

					local_sync_die_with_json_encode(array(
						'error' =>  true,
						'msg' => 'zipping failed' . $zip->last_error,
						'sync_sub_action' => $this->local_sync_options->get_option('sync_sub_action'),
						'sync_current_action' => $this->local_sync_options->get_option('sync_current_action'),
						'requires_next_call' => false
					));
				}
			}

			$site_type = $this->local_sync_options->get_option('site_type');

			if($is_completed){
				$this->local_sync_options->set_this_current_action_step('done');

				if(empty($site_type) || $site_type == 'local'){
					$this->local_sync_options->set_option('sync_current_action', 'zip_upload');
					$this->local_sync_options->set_option('sync_sub_action', 'zip_upload');
					$this->local_sync_options->set_this_current_action_step('processing');
				} elseif($site_type == 'production'){
					$this->local_sync_options->set_option('sync_current_action', 'zip_download');
					$this->local_sync_options->set_option('sync_sub_action', 'zip_download');
					$this->local_sync_options->set_this_current_action_step('processing');
				}
			}

			local_sync_log('', "--------zip creation sending response--------");

			local_sync_die_with_json_encode(array(
				'success' =>  true,
				'sync_sub_action' => $this->local_sync_options->get_option('sync_sub_action'),
				'sync_current_action' => $this->local_sync_options->get_option('sync_current_action'),
				'requires_next_call' => true
			));

		} catch(Exception $e){
			local_sync_log('', "--------catching zip exception--------");
			local_sync_log($e->getMessage(), "--------create_zip----exception----");
		}
	}

	public function away_post_call_get_file_size($file_name = '', $sync_current_action='')	{
		$post_body = array(
			'action' => 'get_file_size',
			'file_name' => $file_name
		);

		$url = $this->local_sync_options->get_option('prod_site_url');
		$response = $this->wp_remote_post_local_sync($url, $post_body);

		if( !empty($response) && !empty($response['success']) ){
			local_sync_log($response, "--------away_post_call_get_file_size--success------");

			return $response['file_size'];
		}

		if( empty($response) || 
			( !empty($response) && !empty($response['error']) ) ){
			local_sync_log($response, "--------away_post_call_get_file_size--error------");

			local_sync_die_with_json_encode(array(
				'error' => 'Not able to get file size for downloading the file',
				'sync_sub_action' => $this->local_sync_options->get_option('sync_sub_action'),
				'sync_current_action' => $this->local_sync_options->get_option('sync_current_action'),
				'requires_next_call' => false
			));

			return false;
		}
	}

	public function do_zip_download() {
		$current_sync_unique_id = $this->local_sync_options->get_option('current_sync_unique_id');
		$URL = $this->local_sync_options->get_option('prod_site_backup_location_url') . "/local_sync_files-$current_sync_unique_id.zip";
		$file = rtrim($this->local_sync_options->get_backup_dir(), '/') . '/' . "local_sync_files-$current_sync_unique_id.zip";

		$this->local_sync_options->set_option('sync_sub_action', 'starting_zip_download');

		local_sync_log($URL, "-----do_zip_download---URL--------");
		local_sync_log($file, "-----do_zip_download---file--------");

		$prev_result = $this->local_sync_options->get_option('download_current_result');
		$prev_result = json_decode($prev_result, true);

		$file_end_name = "local_sync_files-$current_sync_unique_id.zip";
		$total_file_size = $this->away_post_call_get_file_size($file_end_name);

		$bridge_fs_obj = new LocalSyncFileSystem();
		$download_result = $bridge_fs_obj->multi_call_download_using_curl($URL, $file, $downloadResponseHeaders, $total_file_size, $prev_result);

		if(empty($download_result)){

			local_sync_log($bridge_fs_obj->last_error, "--------do_zip_download---error-----");

			$this->local_sync_options->set_this_current_action_step('error');

			local_sync_die_with_json_encode(array(
				'error' =>  $bridge_fs_obj->last_error,
				'sync_sub_action' => $this->local_sync_options->get_option('sync_sub_action'),
				'sync_current_action' => $this->local_sync_options->get_option('sync_current_action'),
				'requires_next_call' => false
			));
		}

		local_sync_log($download_result, "--------download_result--do_zip_download------");

		$this->local_sync_options->set_option('download_current_result', json_encode($download_result));

		$this->check_download_response_headers($downloadResponseHeaders);

		if(empty($download_result['is_download_multi_call'])){
			$this->local_sync_options->set_this_current_action_step('done');
			$this->local_sync_options->set_option('sync_sub_action', 'initiate_zip_extract');
			$this->local_sync_options->set_option('sync_current_action', 'initiate_zip_extract');
			$this->local_sync_options->set_this_current_action_step('processing');
		}

		local_sync_die_with_json_encode(array(
			'success' =>  true,
			'sync_sub_action' => $this->local_sync_options->get_option('sync_sub_action'),
			'sync_current_action' => $this->local_sync_options->get_option('sync_current_action'),
			'requires_next_call' => true
		));
	}

	public function do_zip_upload()	{
		$current_sync_unique_id = $this->local_sync_options->get_option('current_sync_unique_id');
		$URL = rtrim($this->local_sync_options->get_option('prod_site_url'), '/') . '/index.php';
		$file = rtrim($this->local_sync_options->get_backup_dir(), '/') . '/' . "local_sync_files-$current_sync_unique_id.zip";

		$prev_result = $this->local_sync_options->get_option('upload_current_result');
		$prev_result = json_decode($prev_result, true);

		$bridge_fs_obj = new LocalSyncFileSystem();
		$upload_result = $bridge_fs_obj->multi_call_upload_using_curl($URL, $file, $uploadResponseHeaders, $prev_result, false, 'full');

		if(empty($upload_result)){

			local_sync_log($bridge_fs_obj->last_error, "--------do_zip_upload---error-----");

			$this->local_sync_options->set_this_current_action_step('error');

			local_sync_die_with_json_encode(array(
				'error' =>  $bridge_fs_obj->last_error,
				'sync_sub_action' => $this->local_sync_options->get_option('sync_sub_action'),
				'sync_current_action' => $this->local_sync_options->get_option('sync_current_action'),
				'requires_next_call' => false
			));
		}

		local_sync_log($upload_result, "--------upload_result-----process_upload_local_full_dump---");
		local_sync_log($uploadResponseHeaders, "--------uploadResponseHeaders-----process_upload_local_full_dump---");

		$this->local_sync_options->set_option('upload_current_result', json_encode($upload_result));

		if(empty($upload_result['is_upload_multi_call'])){
			$this->local_sync_options->set_this_current_action_step('done');

			$this->local_sync_options->set_option('sync_current_action', 'initiate_zip_extract');
			$this->local_sync_options->set_option('sync_sub_action', 'initiate_zip_extract');

			$this->local_sync_options->set_this_current_action_step('processing');

			if(defined('LOCAL_SYNC_DELETE_TEMP') && LOCAL_SYNC_DELETE_TEMP){
				$this->local_sync_options->remove_backup_dir_files();
				$this->local_sync_options->truncate_delete_list_table();
				$this->local_sync_options->truncate_current_process_table();
			}
		}

		local_sync_die_with_json_encode(array(
			'success' =>  true,
			'sync_sub_action' => $this->local_sync_options->get_option('sync_sub_action'),
			'sync_current_action' => $this->local_sync_options->get_option('sync_current_action'),
			'requires_next_call' => true
		));
	}

	public function process_download_local_file_list_dump()	{
		// $this->local_sync_options->set_option('sync_sub_action', 'process_download_local_file_list_dump');
		
		if($this->local_sync_options->is_feature_valid('filesDiff')){

			$current_sync_unique_id = $this->local_sync_options->get_option('current_sync_unique_id');
			$URL = $this->local_sync_options->get_option('prod_site_backup_location_url') . "/local_sync_file_list_dump-$current_sync_unique_id.sql.gz";
			$file = rtrim($this->local_sync_options->get_backup_dir(), '/') . '/' . "local_sync_file_list_dump-$current_sync_unique_id.sql.gz";

			local_sync_log($URL, "-----process_download_local_file_list_dump---URL--------");
			local_sync_log($file, "-----process_download_local_file_list_dump---file--------");

			$prev_result = $this->local_sync_options->get_option('download_current_result');
			$prev_result = json_decode($prev_result, true);

			$file_end_name = "local_sync_file_list_dump-$current_sync_unique_id.sql.gz";
			$total_file_size = $this->away_post_call_get_file_size($file_end_name);

			$bridge_fs_obj = new LocalSyncFileSystem();
			$download_result = $bridge_fs_obj->multi_call_download_using_curl($URL, $file, $downloadResponseHeaders, $total_file_size, $prev_result);

			if(empty($download_result)){

				local_sync_log($bridge_fs_obj->last_error, "--------process_download_local_file_list_dump---error-----");

				$this->local_sync_options->set_this_current_action_step('error');

				local_sync_die_with_json_encode(array(
					'error' =>  $bridge_fs_obj->last_error,
					'sync_sub_action' => $this->local_sync_options->get_option('sync_sub_action'),
					'sync_current_action' => $this->local_sync_options->get_option('sync_current_action'),
					'requires_next_call' => false
				));
			}

			local_sync_log($download_result, "--------download_result--process_download_local_file_list_dump------");

			$this->local_sync_options->set_option('download_current_result', json_encode($download_result));

			$this->check_download_response_headers($downloadResponseHeaders);
		}

		if( !$this->local_sync_options->is_feature_valid('filesDiff')
			|| empty($download_result['is_download_multi_call']) ){
			$this->local_sync_options->set_this_current_action_step('done');
			$this->local_sync_options->set_option('sync_sub_action', 'start_db_dump');
			$this->local_sync_options->set_option('sync_current_action', 'start_db_dump');
			$this->local_sync_options->set_this_current_action_step('processing');
		} else {

		}

		local_sync_die_with_json_encode(array(
			'success' =>  true,
			'sync_sub_action' => $this->local_sync_options->get_option('sync_sub_action'),
			'sync_current_action' => $this->local_sync_options->get_option('sync_current_action'),
			'requires_next_call' => true
		));
	}

	public function initiate_zip_extract() {
		$this->local_sync_options->set_option('sync_sub_action', 'creating_bridge_files');

		$restore_app_functions = new Local_Sync_Restore_Op();
		$download_result = $restore_app_functions->prepare();

		$restore_app_functions->reset_bridge_constants();

		$site_type = $this->local_sync_options->get_option('site_type');

		$this->local_sync_options->set_this_current_action_step('done');

		if(empty($site_type) || $site_type == 'local'){
			$this->local_sync_options->set_option('sync_current_action', 'continue_extract_from_bridge');
			$this->local_sync_options->set_option('sync_sub_action', 'continue_extract_from_bridge');
			$this->local_sync_options->set_this_current_action_step( 'processing');
		} elseif($site_type == 'production'){
			$this->local_sync_options->set_option('sync_current_action', 'continue_extract_from_live_bridge');
			$this->local_sync_options->set_option('sync_sub_action', 'continue_extract_from_live_bridge');
			$this->local_sync_options->set_this_current_action_step( 'processing');
		}

		$this->local_sync_options->set_option('is_bridge_process', true);

		local_sync_die_with_json_encode(array(
			'success' =>  true,
			'sync_sub_action' => $this->local_sync_options->get_option('sync_sub_action'),
			'sync_current_action' => $this->local_sync_options->get_option('sync_current_action'),
			'requires_next_call' => false
		));
	}

	public function initiate_bridge_files() {
		$this->local_sync_options->set_option('sync_sub_action', 'creating_bridge_files');

		$restore_app_functions = new Local_Sync_Restore_Op();
		$download_result = $restore_app_functions->prepare();

		$restore_app_functions->reset_bridge_constants();

		$site_type = $this->local_sync_options->get_option('site_type');

		$this->local_sync_options->set_this_current_action_step('done');

		if(empty($site_type) || $site_type == 'local'){
			$this->local_sync_options->set_option('sync_current_action', 'copy_files_from_bridge');
			$this->local_sync_options->set_option('sync_sub_action', 'copy_files_from_bridge');
			$this->local_sync_options->set_this_current_action_step( 'processing');
		} elseif($site_type == 'production'){
			$this->local_sync_options->set_option('sync_current_action', 'copy_files_from_live_bridge');
			$this->local_sync_options->set_option('sync_sub_action', 'copy_files_from_live_bridge');
			$this->local_sync_options->set_this_current_action_step( 'processing');
		}

		$this->local_sync_options->set_option('is_bridge_process', true);

		local_sync_die_with_json_encode(array(
			'success' =>  true,
			'sync_sub_action' => $this->local_sync_options->get_option('sync_sub_action'),
			'sync_current_action' => $this->local_sync_options->get_option('sync_current_action'),
			'requires_next_call' => false
		));
	}

	public function check_download_response_headers($headers){
		$httpCodeChecked = false;

		local_sync_log($headers, "--------headers---check_download_response_headers-----");

		foreach($headers as $line){
		  if(!$httpCodeChecked && stripos($line, 'HTTP/') !== false){
			  $matches = array();
			  preg_match('#HTTP/\d+\.\d+ (\d+)#', $line, $matches);
			  $httpCode = (int)$matches[1];
			  if($httpCode != 200 && $httpCode != 206){
				status_losy("Error while downloading the zip file HTTP error: ".$httpCode.".", false ,true);
				local_sync_die_with_json_encode(array(
					'error' =>  "Error while downloading the zip file HTTP error: ".$httpCode.".",
					'sync_sub_action' => $this->local_sync_options->get_option('sync_sub_action'),
					'sync_current_action' => $this->local_sync_options->get_option('sync_current_action'),
					'requires_next_call' => false
				));
			  }
			  $httpCodeChecked = true;
		  }

		  if(stripos($line, 'Content-Type') !== false){
			   //$contentType = trim(str_ireplace('Content-Type:', '', $line));
			   //if(strtolower($contentType) != 'application/zip')
			   if(stripos($line, 'application/zip') === false){
				  //die(status_losy("Invalid zip type, please check file is downloadable.", false ,true));
				  $GLOBALS['downloadPossibleError'] = " Please check file is downloadable.";
			  }
		  }
		}
		return true;
	}

	public function save_local_site_file_list_dump_file($file_data = null, $start_range = 0, $end_range = 0) {
		$current_sync_unique_id = $this->local_sync_options->get_option('current_sync_unique_id');
		$file_list_dump_file = rtrim($this->local_sync_options->get_backup_dir(), '/') . '/' . "local_sync_file_list_dump-$current_sync_unique_id.sql.gz";

		if( $start_range == 0 ){
			file_put_contents($file_list_dump_file, '');
		}

		$fp = fopen($file_list_dump_file, 'rb+');
		fseek($fp, $start_range);
		fwrite($fp, hex2bin($file_data));

		

		if(!file_exists($file_list_dump_file)){
			local_sync_log($file_list_dump_file, "--------save_local_site_file_list_dump_file---failed-----");

			local_sync_die_with_json_encode(array(
				'error' =>  'Cannot create dump file',
				'msg' =>  'save_local_site_file_list_dump_file_success',
				'sync_sub_action' => $this->local_sync_options->get_option('sync_sub_action'),
				'sync_current_action' => $this->local_sync_options->get_option('sync_current_action'),
				'requires_next_call' => false
			));
		}

		local_sync_log(filesize($file_list_dump_file), "--------file zise---save_local_site_file_list_dump_file-----");

		local_sync_die_with_json_encode(array(
			'success' =>  true,
			'msg' =>  'save_local_site_file_list_dump_file_success',
			'file_size' => filesize($file_list_dump_file),
			'sync_sub_action' => $this->local_sync_options->get_option('sync_sub_action'),
			'sync_current_action' => $this->local_sync_options->get_option('sync_current_action'),
			'requires_next_call' => false
		));

		// return filesize($file_list_dump_file);
	}

	public function save_full_zip_file($file_data = null, $start_range = 0, $end_range = 0) {
		$current_sync_unique_id = $this->local_sync_options->get_option('current_sync_unique_id');
		$full_zip_file = rtrim($this->local_sync_options->get_backup_dir(), '/') . '/' . "local_sync_files-$current_sync_unique_id.zip";

		if( $start_range == 0 ){
			file_put_contents($full_zip_file, '');
		}

		$fp = fopen($full_zip_file, 'rb+');
		fseek($fp, $start_range);
		fwrite( $fp, gzuncompress(hex2bin($file_data)) );


		if(!file_exists($full_zip_file)){
			local_sync_log($full_zip_file, "--------save_full_zip_file---failed-----");

			local_sync_die_with_json_encode(array(
				'error' =>  'Cannot create full zip file',
				'msg' =>  'save_full_zip_file_failed',
				'sync_sub_action' => $this->local_sync_options->get_option('sync_sub_action'),
				'sync_current_action' => $this->local_sync_options->get_option('sync_current_action'),
				'requires_next_call' => false
			));
		}

		local_sync_log(filesize($full_zip_file), "--------file zise---save_full_zip_file-----");

		local_sync_die_with_json_encode(array(
			'success' =>  true,
			'msg' =>  'save_full_zip_file_success',
			'file_size' => filesize($full_zip_file),
			'sync_sub_action' => $this->local_sync_options->get_option('sync_sub_action'),
			'sync_current_action' => $this->local_sync_options->get_option('sync_current_action'),
			'requires_next_call' => false
		));

		// return filesize($file_list_dump_file);
	}

	public function import_local_file_list_dump() {
		if($this->local_sync_options->is_feature_valid('filesDiff')){
			
			$current_sync_unique_id = $this->local_sync_options->get_option('current_sync_unique_id');
			$file_name = rtrim($this->local_sync_options->get_backup_dir(), '/') . '/' . "local_sync_file_list_dump-$current_sync_unique_id.sql.gz";

			$prev_index = $this->local_sync_options->get_option('restore_db_index');

			$restore_app_functions = new Local_Sync_Restore_Op();

			$file_name = $restore_app_functions->uncompress_local_file_list_dump($file_name);

			$response = $restore_app_functions->import_sql_file($file_name, $prev_index, false, true);

			local_sync_log($response, '--------import_local_file_list_dump response--------');

			if (empty( $response ) || empty($response['status']) || $response['status'] === 'error') {
				$this->local_sync_options->set_this_current_action_step('error');

				// $this->disable_maintenance_mode();
				// $this->restore_app_functions->send_report_data($this->restore_id, 'FAILED');
				$err = $response['status'] === 'error' ? $response['msg'] : 'Unknown error during database import';
				local_sync_die_with_json_encode(array(
					'prev_index' => $prev_index,
					'error' => $err,
					'requires_next_call' => false
				));
			}

			if ($response['status'] === 'continue') {
				$this->local_sync_options->set_option('restore_db_index', $response['offset']); //updating the status in db for each 10 lines
				local_sync_die_with_json_encode(array(
					'prev_index' => $prev_index,
					'success' =>  true,
					'sync_sub_action' => $this->local_sync_options->get_option('sync_sub_action'),
					'sync_current_action' => $this->local_sync_options->get_option('sync_current_action'),
					'requires_next_call' => true
				));
			}

		}

		if ( !$this->local_sync_options->is_feature_valid('filesDiff') 
			 || $response['status'] === 'completed' ) {
			$this->local_sync_options->set_this_current_action_step('done');

			$this->local_sync_options->set_option('restore_db_index', 0);
			$this->local_sync_options->set_option('sync_current_action', 'process_file_list_difference');
			$this->local_sync_options->set_option('sync_sub_action', 'process_file_list_difference');

			$this->local_sync_options->set_this_current_action_step('processing');

			local_sync_die_with_json_encode(array(
				'prev_index' => $prev_index,
				'success' =>  true,
				'sync_sub_action' => $this->local_sync_options->get_option('sync_sub_action'),
				'sync_current_action' => $this->local_sync_options->get_option('sync_current_action'),
				'requires_next_call' => true
			));
		}
	}

	public function process_file_list_difference()	{

		if($this->local_sync_options->is_feature_valid('filesDiff')){
			$this->local_sync_options->set_option('sync_current_action', 'process_file_list_difference');
			$this->local_sync_options->set_option('sync_sub_action', 'process_file_list_difference');

			$this->local_sync_files_op = new Local_Sync_Files_Op();
			$diff_result = $this->local_sync_files_op->mark_modified_files_from_local_file_list_multi_call();

			local_sync_log($diff_result, "--------diff_result--------");
		}

		if( !$this->local_sync_options->is_feature_valid('filesDiff') 
			|| $diff_result['is_completed'] ){
			$this->local_sync_options->set_this_current_action_step('done');

			$show_modified_files_dialog = 0;
			$modified_files = array();
			$modified_files_count = 0;
			$site_type = $this->local_sync_options->get_option('site_type');

			$next_action = 'batch_upload_files';
			if($site_type == 'production'){
				//hackish
				$next_action = 'send_batch_download_requests';
			}

			if( !$this->local_sync_options->is_feature_valid('filesDiff') ){
				$this->local_sync_options->set_option('sync_current_action', $next_action);
				$this->local_sync_options->set_option('sync_sub_action', $next_action);
			} elseif($diff_result['is_completed']){
				$this->local_sync_options->set_option('sync_current_action', $next_action);
				$this->local_sync_options->set_option('sync_sub_action', $next_action);

				$this->local_sync_files_op = new Local_Sync_Files_Op();

				$modified_files_count = $this->local_sync_files_op->get_total_no_of_files_to_be_zipped();
				$modified_files = $this->local_sync_files_op->get_limited_files_to_zip_array(100);

				$show_modified_files_dialog = 1;
			}

			$this->local_sync_options->set_this_current_action_step('processing');

			local_sync_die_with_json_encode(array(
				'success' =>  true,
				'sync_sub_action' => $this->local_sync_options->get_option('sync_sub_action'),
				'sync_current_action' => $this->local_sync_options->get_option('sync_current_action'),
				'requires_next_call' => true,
				'show_modified_files_dialog' => $show_modified_files_dialog,
				'modified_files' => $modified_files,
				'modified_files_count' => $modified_files_count,
			));
		} else {
			local_sync_die_with_json_encode(array(
				'success' =>  true,
				'sync_sub_action' => $this->local_sync_options->get_option('sync_sub_action'),
				'sync_current_action' => $this->local_sync_options->get_option('sync_current_action'),
				'requires_next_call' => true
			));
		}
	}

	public function prepare_and_set_prod_key()	{
		$email = $this->local_sync_options->get_option('email');

		$prod_key_random_id = uniqid();

		local_sync_log($prod_key_random_id, "--------prepare_and_set_prod_key--------");

		$this->local_sync_options->set_option('prod_key_random_id', $prod_key_random_id);

		$prepared_key = base64_encode(json_encode(array(
			'email' => $email,
			'url' => site_url(),
			'prod_key_random_id' => $prod_key_random_id
		)));

		$this->local_sync_options->set_option('prod_key', $prepared_key);
	}

	public function get_site_url_stripped_losy()	{
		$site_url = site_url();
		$parsed_url = parse_url($site_url);

		$formed_url = $parsed_url['host'] . $parsed_url['path'];
		$formed_url = trim($formed_url, '/' . '/');

		$www_string = 'www.';
		$formed_url = preg_replace("/$www_string/i", '', $formed_url, 1);

		return $formed_url;
	}

	public function service_login($from_ajax = true) {
		if($from_ajax){
			$this->verify_ajax_requests();

			$email = sanitize_text_field($_POST['data']['email']);
			$password = sanitize_text_field($_POST['data']['password']);
			$temp_password = sanitize_text_field($_POST['data']['password']);

			$this->local_sync_options->set_option('email', '');
			$this->local_sync_options->set_option('password', '');
		} else {
			$email = $this->local_sync_options->get_option('email');
			$password = $this->local_sync_options->get_option('password');
			$password = base64_decode($password);
		}

		$stripped_url = $this->get_site_url_stripped_losy();

		$post_body = array(
			'email' => $email,
			'password' => $password,
			'url' => $stripped_url,
		);

		$is_site_added_once = $this->local_sync_options->get_option('site_added_once');
		$is_site_connected_to_some_account = false;

		if(empty($is_site_added_once)){
			$response = $this->service_post_call('addSite', $post_body);

			$this->process_features_from_response($response);

			$this->local_sync_options->set_option('is_logged_in', 0);

			if(empty($response)){
				local_sync_die_with_json_encode_simple(array(
					'error' =>  'Invalid Response.'
				));				
			}

			if(!empty($response['error'])){
				if($response['message'] == 'login_error'){
					local_sync_die_with_json_encode_simple(array(
						'error' =>  'Login Failed, Check your email and password.'
					));
				}

				if($response['message'] == 'limit_reached'){
					local_sync_die_with_json_encode_simple(array(
						'error' =>  'Site limit is reached for this account'
					));
				}

				if($response['message'] == 'site_already_added'){
					$is_site_connected_to_some_account = true;

					$this->local_sync_options->set_option('site_added_once', 1);

					// local_sync_die_with_json_encode_simple(array(
					// 	'error' =>  'Site already added'
					// ));
				} else {
					local_sync_die_with_json_encode_simple(array(
						'error' =>  'Site adding failed.'
					));
				}
			}

			if(!empty($response['success']) && $response['message'] == 'added'){
				$this->local_sync_options->set_option('site_added_once', 1);
				$this->local_sync_options->set_option('is_logged_in', 1);
				$this->local_sync_options->set_option('email', $email);
				$this->local_sync_options->set_option('password', base64_encode($password));

				// local_sync_die_with_json_encode_simple(array(
				// 	'success' =>  true
				// ));
			}
		}

		$response = $this->service_post_call('checkValidity', $post_body);

		$this->process_features_from_response($response);

		$this->local_sync_options->set_option('is_logged_in', 0);

		if(empty($response)){
			local_sync_die_with_json_encode_simple(array(
				'error' =>  'Invalid Response.'
			));				
		}

		if(!empty($response['error'])){
			if($response['message'] == 'login_error'){
				local_sync_die_with_json_encode_simple(array(
					'error' =>  'Login Failed, Check your email and password.'
				));
			}

			if($response['message'] == 'upgrade'){
				local_sync_die_with_json_encode_simple(array(
					'error' =>  'Please upgrade the local sync plugin from WP Repo. Contact us at http://localsync.io'
				));
			}

			if($response['message'] == 'url_not_found'){
				$this->local_sync_options->set_option('site_added_once', 0);

				local_sync_die_with_json_encode_simple(array(
					'error' =>  'Site is not added yet, or connected to another Local Sync account.'
				));
			}

			if($response['message'] == 'expired'){
				local_sync_die_with_json_encode_simple(array(
					'error' =>  'Subscription expired.'
				));
			}

			if($is_site_connected_to_some_account){
				local_sync_die_with_json_encode_simple(array(
					'error' =>  'Site is connected to different account.'
				));					
			}

			local_sync_die_with_json_encode_simple(array(
				'error' =>  'Site is not valid.'
			));
		}

		if(!empty($response) && !empty($response['success']) && $response['message'] == 'valid'){
			$this->local_sync_options->set_option('is_logged_in', 1);
			$this->local_sync_options->set_option('email', $email);
			$this->local_sync_options->set_option('password', base64_encode($password));

			local_sync_die_with_json_encode_simple(array(
				'success' =>  true,
				'features' => $response['features'],
				'prod_site_url_enc' => $this->local_sync_options->get_option('local_site_url_enc'),
				'prod_admin_url_enc' => $this->local_sync_options->get_option('local_admin_url_enc'),
				'prod_uploads_url_enc' => $this->local_sync_options->get_option('local_uploads_url_enc'),
				'load_images_from_live_site_settings' => $this->local_sync_options->get_option('load_images_from_live_site_settings'),
				'away_site_abspath' => wp_normalize_path(ABSPATH),
				'is_away_site_multisite' => defined('MULTISITE') ? MULTISITE : false,
				'away_site_id_current_site' => defined('SITE_ID_CURRENT_SITE') ? SITE_ID_CURRENT_SITE : false,
				'away_blog_id_current_site' => defined('BLOG_ID_CURRENT_SITE') ? BLOG_ID_CURRENT_SITE : false,
				'away_site_db_prefix' => $this->wpdb->base_prefix,
			));
		}
		

		local_sync_die_with_json_encode_simple(array(
			'error' =>  'Authentication Failed.'
		));
	}

	public function service_post_call($main_action, $post_body = array()) {
		if(empty($post_body['email'])){
			$post_body['email'] = $this->local_sync_options->get_option('email');
		}

		if(empty($post_body['password'])){
			$post_body['password'] = $this->local_sync_options->get_option('password');
			$post_body['password'] = base64_decode($post_body['password']);
		}

		if(empty($post_body['url'])){
			$post_body['url'] = $this->local_sync_options->get_option('losy_url');
		}

		$post_body[$main_action] = true;
		$post_body['email'] = base64_encode($post_body['email']);
		$post_body['pass'] = base64_encode($post_body['password']);
		$post_body['URL'] = $post_body['url'];

		local_sync_log($post_body, "--------service_post_call---$main_action--post_body---");

		if(empty($post_body['email']) || empty($post_body['pass']) || empty($post_body['URL'])){
			die(json_encode(array(
				'error' =>  'Required fields are missing.'
			)));
		}

		$service_url = LOCAL_SYNC_SERVICE_URL;
		// $response = $this->wp_remote_post_local_sync($service_url, $post_body, false);

		$post_body['is_local_sync'] = true;
		$post_body['local_sync_version'] = LOCAL_SYNC_VERSION;

		$auth = base64_encode( 'localsyncnew' . ':' . 'localsyncnew' );

		$response = wp_remote_post( $service_url, array(
			'method' => 'POST',
			'timeout' => 60,
			'redirection' => 5,
			'sslverify' => false,
			'blocking' => true,
			'headers' => array(
				'Authorization' => "Basic $auth"
			),
			'body' => $post_body,
			'cookies' => array()
		    )
		);

		$response = wp_remote_retrieve_body( $response );

		$response = parse_wp_merge_response_from_raw_data_php($response);

		local_sync_log($response, "-----service_post_call-$main_action--response--------");

		$response = json_decode($response, true);

		return $response;
	}

	public function process_features_from_response($response=null) {
		if(empty($response)){

			return;
		}
		if( !empty($response) && !empty($response['success']) && !empty($response['features'])){
			$this->local_sync_options->set_option('prod_site_features', json_encode($response['features'], true));
			return;
		}

		//to handle temp live site timeout
		if(!empty($response) && !empty($response['error']) && $response['error'] == 'Invalid Response.'){

			return;
		}

		$this->local_sync_options->set_option('prod_site_features', array());
	}

}
