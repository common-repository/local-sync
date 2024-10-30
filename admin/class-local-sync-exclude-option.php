<?php

class Local_Sync_Exclude_Option {
	protected $config;
	protected $logger;
	private $cron_server_curl;
	private $default_wp_folders;
	private $default_wp_files;
	private $db;
	private $default_exclude_files;
	private $processed_files;
	private $bulk_limit;
	private $default_wp_files_n_folders;
	private $excluded_files;
	private $included_files;
	private $excluded_tables;
	private $included_tables;
	private $max_table_size_allowed = 104857600; //100 MB
	private $max_file_size_allowed  = 52428800; //50 MB
	private $key_recursive_seek;
	private $file_ext;
	private $app_functions;
	private $category;
	private $analyze_files_response = array();
	private $skip_tables = array(
						'blc_instances',
						'bwps_log',
						'Counterize',
						'Counterize_Referers',
						'Counterize_UserAgents',
						'et_bloom_stats',
						'itsec_log',
						'lbakut_activity_log',
						'redirection_404',
						'redirection_logs',
						'relevanssi_log',
						'simple_feed_stats',
						'slim_stats',
						'statpress',
						'svisitor_stat',
						'tts_referrer_stats',
						'tts_trafficstats',
						'wbz404_logs',
						'wbz404_redirects',
						'woocommerce_sessions',
						'wponlinebackup_generations',
						'wysija_email_user_stat',
						'wfknownfilelist',
						'wfhits',
						'wffilemods',
						'wffilechanges'
					);

	public function __construct($category = 'backup') {
		global $wpdb;

		$this->category = $category;
		$this->db = $wpdb;
		$this->bulk_limit = 500;
		$this->local_sync_options = new Local_Sync_Options();
		$this->default_exclude_files = $this->get_dirs_to_exculde();

		$this->default_wp_folders = array(
						LOCAL_SYNC_RELATIVE_ABSPATH . 'wp-admin',
						LOCAL_SYNC_RELATIVE_ABSPATH . 'wp-includes',
						LOCAL_SYNC_RELATIVE_WP_CONTENT_DIR,
					);
		$this->default_wp_files = array(
						LOCAL_SYNC_RELATIVE_ABSPATH . 'favicon.ico',
						LOCAL_SYNC_RELATIVE_ABSPATH . 'index.php',
						LOCAL_SYNC_RELATIVE_ABSPATH . 'license.txt',
						LOCAL_SYNC_RELATIVE_ABSPATH . 'readme.html',
						LOCAL_SYNC_RELATIVE_ABSPATH . 'robots.txt',
						LOCAL_SYNC_RELATIVE_ABSPATH . 'sitemap.xml',
						LOCAL_SYNC_RELATIVE_ABSPATH . 'wp-activate.php',
						LOCAL_SYNC_RELATIVE_ABSPATH . 'wp-blog-header.php',
						LOCAL_SYNC_RELATIVE_ABSPATH . 'wp-comments-post.php',
						LOCAL_SYNC_RELATIVE_ABSPATH . 'wp-config-sample.php',
						LOCAL_SYNC_RELATIVE_ABSPATH . 'wp-config.php',
						LOCAL_SYNC_RELATIVE_ABSPATH . 'wp-cron.php',
						LOCAL_SYNC_RELATIVE_ABSPATH . 'wp-links-opml.php',
						LOCAL_SYNC_RELATIVE_ABSPATH . 'wp-load.php',
						LOCAL_SYNC_RELATIVE_ABSPATH . 'wp-login.php',
						LOCAL_SYNC_RELATIVE_ABSPATH . 'wp-mail.php',
						LOCAL_SYNC_RELATIVE_ABSPATH . 'wp-settings.php',
						LOCAL_SYNC_RELATIVE_ABSPATH . 'wp-signup.php',
						LOCAL_SYNC_RELATIVE_ABSPATH . 'wp-trackback.php',
						LOCAL_SYNC_RELATIVE_ABSPATH . 'wp-salt.php',//some people added this file in wp-config.php
						LOCAL_SYNC_RELATIVE_ABSPATH . 'xmlrpc.php',
						LOCAL_SYNC_RELATIVE_ABSPATH . '.htaccess',
						LOCAL_SYNC_RELATIVE_ABSPATH . 'google',//google analytics files
						LOCAL_SYNC_RELATIVE_ABSPATH . 'gd-config.php',//go daddy configuration file
						LOCAL_SYNC_RELATIVE_ABSPATH . 'wp',//including all wp files on root
						LOCAL_SYNC_RELATIVE_ABSPATH . '.user.ini',//User custom settings / WordFence Files
						LOCAL_SYNC_RELATIVE_ABSPATH . 'wordfence-waf.php',//WordFence Files
					);
		$this->force_exclude_folders = array(
						LOCAL_SYNC_RELATIVE_ABSPATH . 'wp-tcapsule-bridge',
		);

		$extra_files = $this->get_extra_files_to_exclude_for_staging();
		$this->force_exclude_folders = array_merge($this->force_exclude_folders, $extra_files);
		
		$this->default_wp_files_n_folders = array_merge($this->default_wp_folders, $this->default_wp_files);
		// $this->app_functions = new Local_Sync_App_Functions();
		$this->file_ext = new Local_Sync_File_Ext();
		$this->load_saved_keys($category);
	}

	public function get_dirs_to_exculde() {
		$upload_dir_path = local_sync_get_upload_dir();
		$path = array(
				LOCAL_SYNC_RELATIVE_WP_CONTENT_DIR . "/managewp/backups",
				LOCAL_SYNC_RELATIVE_WP_CONTENT_DIR   . "/" . md5('iwp_mmb-client') . "/iwp_backups",
				LOCAL_SYNC_RELATIVE_WP_CONTENT_DIR . "/infinitewp",
				LOCAL_SYNC_RELATIVE_WP_CONTENT_DIR . "/".md5('mmb-worker')."/mwp_backups",
				LOCAL_SYNC_RELATIVE_WP_CONTENT_DIR . "/backupwordpress",
				LOCAL_SYNC_RELATIVE_WP_CONTENT_DIR . "/contents/cache",
				LOCAL_SYNC_RELATIVE_WP_CONTENT_DIR . "/content/cache",
				LOCAL_SYNC_RELATIVE_WP_CONTENT_DIR . "/cache",
				LOCAL_SYNC_RELATIVE_WP_CONTENT_DIR . "/logs",
				LOCAL_SYNC_RELATIVE_WP_CONTENT_DIR . "/old-cache",
				LOCAL_SYNC_RELATIVE_WP_CONTENT_DIR . "/w3tc",
				LOCAL_SYNC_RELATIVE_WP_CONTENT_DIR . "/cmscommander/backups",
				LOCAL_SYNC_RELATIVE_WP_CONTENT_DIR . "/gt-cache",
				LOCAL_SYNC_RELATIVE_WP_CONTENT_DIR . "/wfcache",
				LOCAL_SYNC_RELATIVE_WP_CONTENT_DIR . "/widget_cache",
				LOCAL_SYNC_RELATIVE_WP_CONTENT_DIR . "/bps-backup",
				LOCAL_SYNC_RELATIVE_WP_CONTENT_DIR . "/old-cache",
				LOCAL_SYNC_RELATIVE_WP_CONTENT_DIR . "/updraft",
				LOCAL_SYNC_RELATIVE_WP_CONTENT_DIR . "/nfwlog",
				LOCAL_SYNC_RELATIVE_WP_CONTENT_DIR . "/upgrade",
				LOCAL_SYNC_RELATIVE_WP_CONTENT_DIR . "/wflogs",
				LOCAL_SYNC_RELATIVE_WP_CONTENT_DIR . "/tmp",
				LOCAL_SYNC_RELATIVE_WP_CONTENT_DIR . "/backups",
				LOCAL_SYNC_RELATIVE_WP_CONTENT_DIR . "/updraftplus",
				LOCAL_SYNC_RELATIVE_WP_CONTENT_DIR . "/wishlist-backup",
				LOCAL_SYNC_RELATIVE_WP_CONTENT_DIR . "/wptouch-data/infinity-cache/",
				LOCAL_SYNC_RELATIVE_WP_CONTENT_DIR . "/mysql.sql",
				LOCAL_SYNC_RELATIVE_WP_CONTENT_DIR . "/DE_clTimeTaken.php",
				LOCAL_SYNC_RELATIVE_WP_CONTENT_DIR . "/DE_cl.php",
				LOCAL_SYNC_RELATIVE_WP_CONTENT_DIR . "/DE_clMemoryPeak.php",
				LOCAL_SYNC_RELATIVE_WP_CONTENT_DIR . "/DE_clMemoryUsage.php",
				LOCAL_SYNC_RELATIVE_WP_CONTENT_DIR . "/DE_clCalledTime.php",
				LOCAL_SYNC_RELATIVE_WP_CONTENT_DIR . "/DE_cl_func_mem.php",
				LOCAL_SYNC_RELATIVE_WP_CONTENT_DIR . "/DE_cl_func.php",
				LOCAL_SYNC_RELATIVE_WP_CONTENT_DIR . "/DE_cl_server_call_log_wptc.php",
				LOCAL_SYNC_RELATIVE_WP_CONTENT_DIR . "/DE_cl_dev_log_auto_update.php",
				LOCAL_SYNC_RELATIVE_WP_CONTENT_DIR . "/DE_cl_dev_log_auto_update.txt",
				LOCAL_SYNC_RELATIVE_WP_CONTENT_DIR . "/local-sync-server-request-logs.txt",
				LOCAL_SYNC_RELATIVE_WP_CONTENT_DIR . "/local-sync-logs.txt",
				LOCAL_SYNC_RELATIVE_WP_CONTENT_DIR . "/local-sync-memory-peak.txt",
				LOCAL_SYNC_RELATIVE_WP_CONTENT_DIR . "/local-sync-memory-usage.txt",
				LOCAL_SYNC_RELATIVE_WP_CONTENT_DIR . "/local-sync-time-taken.txt",
				LOCAL_SYNC_RELATIVE_WP_CONTENT_DIR . "/local-sync-cpu-usage.txt",
				LOCAL_SYNC_RELATIVE_WP_CONTENT_DIR . "/debug.log",
				LOCAL_SYNC_RELATIVE_WP_CONTENT_DIR . "/Dropbox_Backup",
				LOCAL_SYNC_RELATIVE_WP_CONTENT_DIR . "/backup-db",
				LOCAL_SYNC_RELATIVE_WP_CONTENT_DIR . "/updraft",
				LOCAL_SYNC_RELATIVE_WP_CONTENT_DIR . "/w3tc-config",
				LOCAL_SYNC_RELATIVE_WP_CONTENT_DIR . "/aiowps_backups",
				rtrim ( trim ( LOCAL_SYNC_RELATIVE_PLUGIN_DIR ) , '/' ), //LS plugin's file path
				$upload_dir_path . "/wp-clone",
				$upload_dir_path . "/db-backup",
				$upload_dir_path . "/ithemes-security",
				$upload_dir_path . "/mainwp/backup",
				$upload_dir_path . "/backupbuddy_backups",
				$upload_dir_path . "/vcf",
				$upload_dir_path . "/pb_backupbuddy",
				$upload_dir_path . "/sucuri",
				$upload_dir_path . "/aiowps_backups",
				$upload_dir_path . "/gravity_forms",
				$upload_dir_path . "/mainwp",
				$upload_dir_path . "/snapshots",
				$upload_dir_path . "/wp-clone",
				$upload_dir_path . "/wp_system",
				$upload_dir_path . "/wpcf7_captcha",
				$upload_dir_path . "/wc-logs",
				$upload_dir_path . "/siteorigin-widgets",
				$upload_dir_path . "/wp-hummingbird-cache",
				$upload_dir_path . "/wp-security-audit-log",
				$upload_dir_path . "/freshizer",
				$upload_dir_path . "/report-cache",
				$upload_dir_path . "/cache",
				$upload_dir_path . "/et_temp",
				$upload_dir_path . "/local_sync_restore_logs",
				$upload_dir_path . "/bb-plugin",
				LOCAL_SYNC_RELATIVE_ABSPATH . "wp-admin/error_log",
				LOCAL_SYNC_RELATIVE_ABSPATH . "wp-admin/php_errorlog",
				LOCAL_SYNC_RELATIVE_ABSPATH . "error_log",
				LOCAL_SYNC_RELATIVE_ABSPATH . "error.log",
				LOCAL_SYNC_RELATIVE_ABSPATH . "debug.log",
				LOCAL_SYNC_RELATIVE_ABSPATH . "WS_FTP.LOG",
				LOCAL_SYNC_RELATIVE_ABSPATH . "security.log",
				LOCAL_SYNC_RELATIVE_ABSPATH . "wp-tcapsule-bridge.zip",
				LOCAL_SYNC_RELATIVE_ABSPATH . "dbcache",
				LOCAL_SYNC_RELATIVE_ABSPATH . "pgcache",
				LOCAL_SYNC_RELATIVE_ABSPATH . "objectcache",
			);
		return $path;
	}

	public function get_extra_files_to_exclude_for_staging(){
		$upload_dir_path = local_sync_get_upload_dir();
		$path = array(
			LOCAL_SYNC_RELATIVE_WP_CONTENT_DIR . "/wp-rocket-config",
			LOCAL_SYNC_RELATIVE_WP_CONTENT_DIR . "/mu-plugins/swift-performance-loader.php",
			LOCAL_SYNC_RELATIVE_WP_CONTENT_DIR . "/plugins/swift-performance",
			LOCAL_SYNC_RELATIVE_WP_CONTENT_DIR . "/plugins/wp-redis",
			LOCAL_SYNC_RELATIVE_WP_CONTENT_DIR . "/object-cache.php",
			LOCAL_SYNC_RELATIVE_ABSPATH . "user.ini",
		);
		
		return $path;
	}

	private function load_saved_keys($category){
		if (!$this->table_exist($this->db->base_prefix . 'local_sync_inc_exc_contents')) {
			return ;
		}

		$this->load_exc_inc_files($category);
		$this->load_exc_inc_tables($category);
	}

	public function table_exist($table){
		$small_letters_table = strtolower($table);

		if( $this->db->get_var("SHOW TABLES LIKE '$small_letters_table'") == $small_letters_table ){
			return true;
		}

		if( $this->db->get_var("SHOW TABLES LIKE '$table'") == $table ){
			return true;
		}

		local_sync_log("SHOW TABLES LIKE '$table'", "--------table_exist_failed_for--------");

		return false;
	}

	private function load_saved_keys_manually(){
		$this->load_exc_inc_files('backup');
		$this->load_exc_inc_tables('backup');
	}

	private function load_exc_inc_files($category){
		$this->excluded_files = $this->get_keys($type = 'file' , $action = 'exclude', $category);
		$this->included_files = $this->get_keys($type = 'file' , $action = 'include', $category);
	}

	private function load_exc_inc_tables($category){
		$this->excluded_tables = $this->get_keys($type = 'table' , $action = 'exclude', $category);
		$this->included_tables = $this->get_keys($type = 'table' , $action = 'include', $category);
	}

	private function get_keys($type = 'file' , $action = '', $category = 'backup'){

		$sql = "SELECT * FROM {$this->db->base_prefix}local_sync_inc_exc_contents WHERE `type` = '$type' AND `action` = '$action' AND `category` = '$category'";
		$raw_data = $this->db->get_results($sql);

		if (empty($raw_data)) {
			return array();
		}

		$result = array();

		foreach ($raw_data as $value) {
			if ($type === 'file') {
				$value->key = local_sync_add_fullpath($value->key);
			}

			$result[] = $value;
		}

		return empty($result) ? array() : $result;
	}

	public function insert_default_excluded_files(){
		$status = $this->local_sync_options->get_option('insert_default_excluded_files');

		if ($status) {
			return false;
		}

		$files = $this->format_excluded_files($this->default_exclude_files);

		foreach ($files as $file) {
			$file['category'] = 'backup';
			$this->exclude_file_list($file, true);

			// $file['category'] = 'staging';
			// $this->exclude_file_list($file, true);
		}

		$this->local_sync_options->set_option('insert_default_excluded_files', true);
	}

	public function is_excluded_table($table){
		if (empty($table)) {
			return 'table_excluded';
		}

		if(is_wp_merge_table($table)){
			return 'table_excluded';
		}
		if (local_sync_is_meta_data_backup()) {
			return $this->is_meta_table_excluded($table);
		}

		$is_wp_table = false;

		if($this->is_wp_table($table) ){
			if($this->exclude_table_check_deep($table)){
				return 'table_excluded';
			}

			$is_wp_table = true;
		}

		return $this->is_included_table($table, $is_wp_table);
	}

	private function is_wp_table($table){
		$case_i_prefix = strtolower($this->db->base_prefix);
		if (preg_match('#^' . $case_i_prefix . '#i', $table) === 1) {

			return true;
		}

		return false;
	}

	private function exclude_table_check_deep($table){
		foreach ($this->excluded_tables as $key_meta) {
			if (preg_match('#^' . $key_meta->key . '#i', $table) === 1 ) {
				return true;
			}
		}

		return false;
	}

	private function is_included_table($table, $is_wp_table){
		if(is_array($this->included_tables)){
			foreach ($this->included_tables as $key_meta) {
				if (preg_match('#^' . $key_meta->key . '#i', $table) === 1) {
					return $key_meta->table_structure_only == 1 ? 'content_excluded' : 'table_included';
				}
			}
		}

		return $is_wp_table === true ? 'table_included' : 'table_excluded';
	}

	public function is_meta_table_excluded($table){

		$structure_tables = $this->get_meta_backup_tables($filer = 'structure');

		if (in_array( $table, $structure_tables) ) {
			return 'content_excluded';
		}

		$full_tables = $this->get_meta_backup_tables($filer = 'full');

		if (in_array( $table , $full_tables ) ) {
			return 'table_included';
		}

		return 'table_excluded';
	}

	public function get_meta_backup_tables($filter = false){

		$structure_tables = array(
			$this->db->base_prefix . 'local_sync_activity_log',
			$this->db->base_prefix . 'local_sync_current_process',
			$this->db->base_prefix . 'local_sync_processed_iterator',
			$this->db->base_prefix . 'local_sync_processed_restored_files',
		);

		$full_tables = array(
			$this->db->base_prefix . 'local_sync_backups',
			$this->db->base_prefix . 'local_sync_inc_exc_contents',
			$this->db->base_prefix . 'local_sync_options',
			$this->db->base_prefix . 'local_sync_processed_files',
		);

		switch ($filter) {
			case 'structure':
				return $structure_tables;
			case 'full':
				return $full_tables;
			default:
				return array_merge($structure_tables, $full_tables);
		}

	}

	private function insert($data){
		local_sync_log(func_get_args(), "--------" . __FUNCTION__ . "--------");

		$result = $this->db->insert("{$this->db->base_prefix}local_sync_inc_exc_contents", $data);

		if ($result === false) {

			local_sync_log($this->db->last_error,'-----------$this->db->last_error----------------');

		}

		return $result;
	}

	private function delete($key, $category = 'backup', $force = false, $is_table = false){

		local_sync_log(func_get_args(), "--------" . __FUNCTION__ . "--------");

		if (empty($key)) {
			return false;
		}

		if(!$is_table){
			$key = local_sync_remove_fullpath($key);
		}

		if ($force) {
			$re_sql = $this->db->prepare(" DELETE FROM {$this->db->base_prefix}local_sync_inc_exc_contents WHERE `key` LIKE  '%%%s%%' AND `category` = '%s' ", $key, $category);
		} else {
			$re_sql = $this->db->prepare(" DELETE FROM {$this->db->base_prefix}local_sync_inc_exc_contents WHERE `key` = '%s' AND `category` = '%s' ", $key, $category);
		}

		$result = $this->db->query($re_sql);

		if ($result === false) {
			local_sync_log($this->db->last_error,'-----------$this->db->last_error----------------');
		}
	}

	private function format_excluded_files($files){

		if (empty($files)) {
			return false;
		}

		$selected_files = array();

		foreach ($files as $file) {
				$selected_files[] = array(
							"id"    => NULL,
							"file"  => $file,
							"isdir" => local_sync_is_dir($file) ? 1 : 0 ,
						);
		}
		return $selected_files;
	}

	public function update_default_excluded_files_list(){
		$upload_dir_path = local_sync_get_upload_dir();

		$files_index = array(
			'1.1.0'  => 'ls_1_1_0',
			);

		$ls_1_1_0 = array(
			$upload_dir_path . "/bb-plugin",
		);

		$prev_plugin_version =  $this->local_sync_options->get_option('prev_installed_local_sync_version');

		if (empty($prev_plugin_version)) {
			return false;
		}

		$required_files = array();
		foreach ($files_index as $key => $value) {
			if (version_compare($prev_plugin_version, $key, '<') && version_compare(LOCAL_SYNC_VERSION, $key, '>=')) {
				$required_files = array_merge($required_files, ${$files_index[$key]});
			}
		}
		return $required_files;
	}

	public function update_default_excluded_files(){
		$status = $this->local_sync_options->get_option('update_default_excluded_files');

		if ($status) {
			return false;
		}

		$new_default_exclude_files = $this->update_default_excluded_files_list();

		if (empty($new_default_exclude_files)) {
			$this->local_sync_options->set_option('update_default_excluded_files', true);
			return false;
		}

		$files = $this->format_excluded_files($new_default_exclude_files);

		foreach ($files as $file) {
			$file['category'] = 'backup';
			$this->exclude_file_list($file, true);

			// $file['category'] = 'staging';
			// $this->exclude_file_list($file, true);
		}

		$this->local_sync_options->set_option('update_default_excluded_files', true);
	}

	public function is_included_file($file, $is_dir = false){
		$found = false;
		$file = wp_normalize_path($file);

		foreach ($this->included_files as $key_meta) {
			$value = str_replace('(', '-', $key_meta->key);
			$value = str_replace(')', '-', $value);
			$file = str_replace('(', '-', $file);
			$file = str_replace(')', '-', $file);
			if(stripos($file.'/', $value.'/') === 0){
				$found = true;
				break;
			}
		}
		return $found;
	}

	public function is_excluded_file($file, $is_dir = false){

		if (empty($file)) {
			return true;
		}

		if( !$is_dir 
			&& $this->file_ext->in_ignore_list( $file, $this->category ) 
			&& !$this->is_included_file( $file ) ) {

			// local_sync_log($file, '---------------skip, file in ignore list-----------------');
			
			return true;
		}

		$file = wp_normalize_path($file);

		if ($this->froce_exclude_files($file)) {
			return true;
		}

		$found = false;
		if ($this->is_wp_file($file)) {
			return $this->exclude_file_check_deep($file);
		}
		if (!$this->is_included_file($file)) {
			return true;
		} else {
			return $this->exclude_file_check_deep($file);
		}
	}

	private function exclude_file_check_deep($file){

		if (empty($this->excluded_files)) {
			return false;
		}

		foreach ($this->excluded_files as $key_meta) {
			$value = str_replace('(', '-', $key_meta->key);
			$value = str_replace(')', '-', $value);
			$file = str_replace('(', '-', $file);
			$file = str_replace(')', '-', $file);
			if(stripos($file.'/', $value.'/') === 0){

				return true;
			}
		}

		return false;
	}

	private function froce_exclude_files($file){
		if (empty($file)) {
			return false;
		}

		$file = wp_normalize_path($file);

		foreach ($this->force_exclude_folders as $path) {

			$path = local_sync_add_fullpath($path);

			if(stripos($file, $path) !== false){
				return true;
			}
		}

		return false;
	}

	private function is_wp_file($file){
		if (empty($file)) {
			return false;
		}
		$file = wp_normalize_path($file);
		foreach ($this->default_wp_files_n_folders as $path) {

			$path = local_sync_add_fullpath($path);
			$path = local_sync_remove_trailing_slash($path);
			if(stripos($file, $path) !== false){
				return true;
			}
		}

		return false;
	}

	public function get_all_included_tables($structure_only = false){

		if (local_sync_is_meta_data_backup()) {
			$filter = $structure_only ? 'structure' : 'full';
			return $this->get_meta_backup_tables($filter);
		}

		$all_tables = $this->get_all_tables();

		$tables = array();

		foreach ($all_tables as $key => $table) {
			if ($structure_only) {
				if ($this->is_excluded_table($table) === 'content_excluded') {
					$tables[] = $table;
				}
			} else {
				if ($this->is_excluded_table($table) === 'table_included') {
					$tables[] = $table;
				}
			}
		}

		return $tables;
	}

	public function get_all_tables($override_meta = false){
		$sql = "SELECT TABLE_NAME FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_NAME NOT LIKE '%local_sync_%' AND TABLE_SCHEMA = '".DB_NAME."'";
		$result_obj = $this->db->get_results($sql, ARRAY_N);

		foreach ($result_obj as $table) {
			$tables[] = $table[0];
		}

		return $tables;
	}

	public function get_root_files($exc_wp_files = false) {

		$this->load_saved_keys_manually();

		$root_files    = $this->get_wp_content_files();
		$root_files    = $this->get_abspath_files($exc_wp_files, array($root_files));

		die(json_encode($root_files));
	}

	private function get_abspath_files($exc_wp_files, $root_files){
		$this->file_iterator = new Local_Sync_File_Iterator();
		$files_object = $this->file_iterator->get_files_obj_by_path(LOCAL_SYNC_ABSPATH);

		if ($exc_wp_files && !$this->local_sync_options->get_option('non_wp_files_excluded')) {
			$this->exclude_non_wp_files($files_object);
			$this->local_sync_options->set_option('non_wp_files_excluded', true);
		}

		return $this->format_result_data($files_object, $root_files, $skip_wp_content = true);
	}

	private function get_wp_content_files(){

		$is_excluded = $this->is_excluded_file(LOCAL_SYNC_WP_CONTENT_DIR, true);

		return array(
			'folder'        => true,
			'lazy'          => true,
			'size'          => '',
			'title'         => basename(LOCAL_SYNC_WP_CONTENT_DIR),
			'key'           => LOCAL_SYNC_WP_CONTENT_DIR,
			'size_in_bytes' => '0',
			'partial'       => $is_excluded ? false : true,
			'preselected'   => $is_excluded ? false : true,
		);
	}

	private function format_result_data($file_obj, $files_arr = array(), $skip_wp_content = false){

		if (empty($file_obj)) {
			return false;
		}

		foreach ($file_obj as $Ofiles) {

			$file_path = $Ofiles->getPathname();

			$file_path = wp_normalize_path($file_path);

			$file_name = basename($file_path);

			if ($file_name == '.' || $file_name == '..') {
				continue;
			}

			if (!$Ofiles->isReadable()) {
				continue;
			}

			$file_size = $Ofiles->getSize();

			$temp = array(
					'title' => basename($file_name),
					'key'   => $file_path,
					'size'  => convert_bytes_to_hr_format_local_sync($file_size),
				);

			$is_dir = local_sync_is_dir($file_path);

			if ($is_dir) {
				if ($skip_wp_content) {
					if ($file_path === LOCAL_SYNC_WP_CONTENT_DIR) {
						continue;
					}
				}
				$is_excluded    = $this->is_excluded_file($file_path, true);
				$temp['folder'] = true;
				$temp['lazy']   = true;
				$temp['size']   = '';
			} else {
				$is_excluded = $this->is_excluded_file($file_path, false);

				if (!$is_excluded) {
					$is_excluded = ( $this->file_ext->in_ignore_list($file_path) && !$this->is_included_file($file_path) ) ? true : false;
				}

				if (!$is_excluded) {
					$is_excluded = $this->is_bigger_than_allowed_file_size($file_path) ? true : false;
				}

				$temp['folder']        = false;
				$temp['size_in_bytes'] = $Ofiles->getSize();
			}

			if($is_excluded){
				$temp['partial']     = false;
				$temp['preselected'] = false;
			} else {
				$temp['preselected'] = true;
			}

			$files_arr[] = $temp;
		}

		$this->sort_by_folders($files_arr);

		return $files_arr;
	}

	public function get_files_by_key($path) {
		$this->load_saved_keys_manually();
		$this->file_iterator = new Local_Sync_File_Iterator();
		$result_obj = $this->file_iterator->get_files_obj_by_path($path);
		$result = $this->format_result_data($result_obj);
		
		die(json_encode($result));
	}

	private function sort_by_folders(&$files_arr) {
		if (empty($files_arr) || !is_array($files_arr)) {
			return false;
		}
		foreach ($files_arr as $key => $row) {
			$volume[$key]  = $row['folder'];
		}
		array_multisort($volume, SORT_DESC, $files_arr);
	}

	public function is_bigger_than_allowed_file_size($file){

		$settings = $this->local_sync_options->get_user_excluded_files_more_than_size();

		if (empty($settings['status'])) {
			return false;
		}

		if ( $this->is_included_file($file) ) {
			return false;
		}

		if (filesize($file) > $settings['size']) {
			return true;
		}

		return false;
	}

	public function exclude_file_list($data, $do_not_die = false){

		$data = stripslashes_deep($data);

		if (empty($data['file']) || LOCAL_SYNC_ABSPATH ===  local_sync_add_trailing_slash($data['file'])) {

			local_sync_log(array(), '--------Matches abspath--------');

			return false;
		}

		$data['file'] = wp_normalize_path($data['file']);

		if ($data['isdir']) {
			$this->delete($data['file'], $data['category'], $force = true);
		} else {
			$this->delete($data['file'], $data['category'], $force = false );
		}

		$data['file'] = local_sync_remove_fullpath($data['file']);

		$result = $this->insert( array(
					'key'      => $data['file'],
					'type'     => 'file',
					'category' => $data['category'],
					'action'   => 'exclude',
					'is_dir'   => $data['isdir'],
				));

		if($do_not_die){
			return true;
		}

		if ($result) {
			local_sync_die_with_json_encode( array('status' => 'success') );
		}
		local_sync_die_with_json_encode( array('status' => 'error') );
	}

	public function include_file_list($data, $force_insert = false){

		$data = stripslashes_deep($data);

		if (empty($data['file'])) {
			return false;
		}

		$data['file'] = wp_normalize_path($data['file']);

		if ($data['isdir']) {
			$this->delete($data['file'], $data['category'], $force = true );
		} else {
			$this->delete($data['file'], $data['category'], $force = false );
		}

		if ( $this->is_wp_file($data['file'] ) && !$this->file_ext->in_ignore_list( $data['file'] ) && !$this->is_bigger_than_allowed_file_size( $data['file'] ) ) {

			local_sync_log(array(), '---------------wordpress folder so no need to inserted ----------------');

			local_sync_die_with_json_encode( array('status' => 'success') );
			return false;
		}

		$data['file'] = local_sync_remove_fullpath($data['file']);

		$result = $this->insert( array(
					'key'      => sanitize_text_field($data['file']),
					'type'     => 'file',
					'category' => sanitize_text_field($data['category']),
					'action'   => 'include',
					'is_dir'   => sanitize_text_field($data['isdir']),
				));

		if ($result) {
			local_sync_die_with_json_encode( array('status' => 'success') );
		}
		local_sync_die_with_json_encode( array('status' => 'error') );
	}

	public function exclude_table_list($data, $do_not_die = false){
		if (empty($data['file'])) {
			return false;
		}

		$this->delete($data['file'], $data['category'], $force = false, $is_table = true);

		$result = $this->insert( array(
					'key'      => sanitize_text_field($data['file']),
					'type'     => 'table',
					'category' => sanitize_text_field($data['category']),
					'action'   => 'exclude',
				));

		if ($do_not_die) {
			return false;
		}
		if ($result) {
			local_sync_die_with_json_encode( array('status' => 'success') );
		}
		local_sync_die_with_json_encode( array('status' => 'error') );
	}

	public function include_table_list($data){
		if (empty($data['file'])) {
			return false;
		}

		$this->delete($data['file'], $data['category'], $force = false, $is_table = true );

		if ($this->is_wp_table($data['file'])) {

			local_sync_log($data['file'], '---------------Wordpress table so no need to insert-----------------');

			local_sync_die_with_json_encode( array('status' => 'success') );
		}

		$result = $this->insert( array(
				'key'                  => $data['file'],
				'type'                 => 'table',
				'category'             => $data['category'],
				'action'               => 'include',
				'table_structure_only' => 0,
			));

		if ($result) {
			local_sync_die_with_json_encode( array('status' => 'success') );
		}

		local_sync_die_with_json_encode( array('status' => 'error') );
	}

	public function include_table_structure_only($data, $do_not_die = false){

		if (empty($data['file'])) {
			return false;
		}

		$this->delete($data['file'], $data['category'], $force = false );

		$result = $this->insert( array(
				'key'                  => $data['file'],
				'type'                 => 'table',
				'category'             => $data['category'],
				'action'               => 'include',
				'table_structure_only' => 1,
			));

		if ($do_not_die) {
			return ;
		}

		if ($result) {
			local_sync_die_with_json_encode( array('status' => 'success') );
		}

		local_sync_die_with_json_encode( array('status' => 'error') );
	}

	private function exclude_non_wp_tabes($tables){
		foreach ($tables as $table) {
			if (!$this->is_wp_table($table)) {
				$this->exclude_table_list(array('file' => $table, 'category' => 'backup'), true);
				// $this->exclude_table_list(array('file' => $table, 'category' => 'staging'), true);
			}
		}
	}

	private function is_log_table($table){
		foreach ($this->skip_tables as $skip_table) {
			if (stripos($table, $skip_table) !== false) {
				return true;
			}
		}

		return false;
	}

	public function exclude_content_for_default_log_tables($tables = false){

		if($this->local_sync_options->get_option('exclude_content_for_default_log_tables')){
			return ;
		}

		if (empty($tables)) {
			$tables = $this->get_all_tables();
		}

		if (empty($tables)) {
			return $this->local_sync_options->set_option('exclude_content_for_default_log_tables', true);
		}

		foreach ($tables as $table) {
			if(!$this->is_log_table($table)){
				continue;
			}

			$this->include_table_structure_only(array('file' => $table, 'category' => 'backup'), $do_not_die = true);
		}

		$this->local_sync_options->set_option('exclude_content_for_default_log_tables', true);
	}

	public function exclude_default_tables()	{
		$this->load_saved_keys_manually();

		if (!$this->local_sync_options->get_option('non_wp_tables_excluded')) {
			$tables = $this->get_all_tables();
			$this->exclude_non_wp_tabes($tables);
			$this->exclude_content_for_default_log_tables($tables);
			$this->local_sync_options->set_option('non_wp_tables_excluded', true);
		}
	}

	public function get_tables($exc_wp_tables = false) {
		$this->load_saved_keys_manually();

		$tables = $this->get_all_tables();

		if ($exc_wp_tables && !$this->local_sync_options->get_option('non_wp_tables_excluded')) {
			$this->exclude_non_wp_tabes($tables);
			$this->exclude_content_for_default_log_tables($tables);
			$this->local_sync_options->set_option('non_wp_tables_excluded', true);
			$this->load_exc_inc_tables('backup');
		}

		$tables_arr = array();

		foreach ($tables as $table) {

			//revisit
			// if (!$this->show_this_tables_in_staging_site($table)) {
			// 	continue;
			// }

			$table_status = $this->is_excluded_table($table);

			if ($table_status === 'table_included') {
				$temp = array(
					'title'            => $table,
					'key'              => $table,
					'content_excluded' => 0,
					'size'             => $this->get_table_size($table),
					'preselected'      => true,
				);
			} else if ($table_status === 'content_excluded') {
				$temp = array(
					'title'            => $table,
					'key'              => $table,
					'content_excluded' => 1,
					'size'             => $this->get_table_size($table),
					'preselected'      => true,
				);
			} else  {
				$temp = array(
					'title'       => $table,
					'key'         => $table,
					'size'        => $this->get_table_size($table),
					'preselected' => false,
				);
			}
			$temp['size_in_bytes'] = $this->get_table_size($table, 0);
			$tables_arr[] = $temp;
		}
		die(json_encode($tables_arr));
	}

	public function get_table_size($table_name, $return = 1){
		$sql = "SHOW TABLE STATUS LIKE '".$table_name."'";
		$result = $this->db->get_results($sql);
		if (isset($result[0]->Data_length) && isset($result[0]->Index_length) && $return) {
			return convert_bytes_to_hr_format_local_sync(($result[0]->Data_length) + ($result[0]->Index_length));
		} else {
			return $result[0]->Data_length + $result[0]->Index_length;
		}
		return '0 B';
	}
}
