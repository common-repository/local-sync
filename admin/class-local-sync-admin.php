<?php

/**
 * The admin-specific functionality of the plugin.
 *
 * @link       https://revmakx.com
 * @since      1.0.0
 *
 * @package    Local_Sync
 * @subpackage Local_Sync/admin
 */

/**
 * The admin-specific functionality of the plugin.
 *
 * Defines the plugin name, version, and two examples hooks for how to
 * enqueue the admin-specific stylesheet and JavaScript.
 *
 * @package    Local_Sync
 * @subpackage Local_Sync/admin
 * @author     Local Sync <mohamed@revmakx.com>
 */
class Local_Sync_Admin {

	/**
	 * The ID of this plugin.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      string    $plugin_name    The ID of this plugin.
	 */
	private $plugin_name;

	/**
	 * The version of this plugin.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      string    $version    The current version of this plugin.
	 */
	private $version;


	private $local_sync_files_op;

	/**
	 * Initialize the class and set its properties.
	 *
	 * @since    1.0.0
	 * @param      string    $plugin_name       The name of this plugin.
	 * @param      string    $version    The version of this plugin.
	 */
	public function __construct( $plugin_name, $version ) {
		global $wpdb;

		$this->plugin_name = $plugin_name;
		$this->version = $version;
		$this->wpdb = $wpdb;
		$this->local_sync_options = new Local_Sync_Options();
		$this->app_functions = new Local_Sync_App_Functions();
		$this->request_data = array();
	}

	public function set_local_sync_globals()	{
		global $LOCAL_SYNC_SITE_TYPE;
		global $LOCAL_SYNC_PROD_UPLOADS_URL;
		global $LOCAL_SYNC_PROD_URL;
		global $LOCAL_SYNC_LOCAL_URL;
		global $LOCAL_SYNC_LOCAL_UPLOADS_URL;
		global $LOCAL_SYNC_LOAD_IMAGES_FROM_LIVE;

		$LOCAL_SYNC_SITE_TYPE = $this->local_sync_options->get_option('site_type');
		$LOCAL_SYNC_LOAD_IMAGES_FROM_LIVE = $this->local_sync_options->get_option('load_images_from_live_site_settings');

		if(empty($LOCAL_SYNC_SITE_TYPE) || $LOCAL_SYNC_SITE_TYPE == 'local'){
			$LOCAL_SYNC_PROD_URL = $this->local_sync_options->get_option('prod_site_url');
			$LOCAL_SYNC_PROD_URL = trim($LOCAL_SYNC_PROD_URL, '/');

			$upload_dir_meta = wp_upload_dir();
			$LOCAL_SYNC_LOCAL_UPLOADS_URL = $upload_dir_meta['baseurl'];

			$local_site_url = get_home_url();
			$LOCAL_SYNC_LOCAL_URL = $local_site_url;

			$LOCAL_SYNC_PROD_UPLOADS_URL = str_replace($local_site_url, $LOCAL_SYNC_PROD_URL, $LOCAL_SYNC_LOCAL_UPLOADS_URL);
		}

	}

	public function check_db_upgrade() {
		// if(is_local_sync_options_table_exists()){

		// 	return true;
		// }
		$is_table_exist = $this->wpdb->query('SHOW TABLES LIKE "'.$this->wpdb->base_prefix.'local_sync_options%"');

		if(!$is_table_exist){

			include_once ( ABSPATH . 'wp-admin/includes/upgrade.php' );

			$this->create_fresh_tables();
		}

		$this->local_sync_options->set_option('local_sync_version', LOCAL_SYNC_VERSION);
		$this->local_sync_options->set_option('local_sync_db_version', LOCAL_SYNC_DATABASE_VERSION);

		$this->set_local_sync_globals();
		$this->save_urls();
	}

	public function set_initial_flags() {
		$initial_flags_set = $this->local_sync_options->get_option('initial_flags_set');
		if(!$initial_flags_set){

			$res = $this->local_sync_options->create_local_sync_files();
			if(!$res){
				local_sync_log('', "--------create_local_sync_files---failed-----");
			}
		
			$this->app_functions->set_fresh_install_flags();

			$this->exclude_option = new Local_Sync_Exclude_Option();
			$this->exclude_option->exclude_default_tables();

			// $this->app_functions->prepare_and_set_prod_key();

			$this->local_sync_options->set_option('initial_flags_set', 1);
		}
	}

	public function update_1_0_1_handle() {

		$is_table_exist = $this->wpdb->query('SHOW TABLES LIKE "'.$this->wpdb->base_prefix.'local_sync_options%"');

		if(!$is_table_exist){

			return;
		}

		$site_type = $this->local_sync_options->get_option('site_type');

		if(empty($site_type) || $site_type == 'local'){

			return;
		}


		$local_sync_version_in_options_table = $this->local_sync_options->get_option('local_sync_version');

		if( empty($local_sync_version_in_options_table) 
			|| $local_sync_version_in_options_table == '1.0.0' ){

			local_sync_log($local_sync_version_in_options_table, "-----running---update_1_0_1_handle--------");
	
			$this->app_functions->prepare_and_set_prod_key();
		}
	}

	public function update_1_0_2_handle() {

		$is_table_exist = $this->wpdb->query('SHOW TABLES LIKE "'.$this->wpdb->base_prefix.'local_sync_options%"');

		if(!$is_table_exist){

			return;
		}

		$site_type = $this->local_sync_options->get_option('site_type');

		if(empty($site_type)){

			return;
		}


		$local_sync_version_in_options_table = $this->local_sync_options->get_option('local_sync_version');

		if( $local_sync_version_in_options_table == '1.0.1' 
			|| $local_sync_version_in_options_table == '1.0.0' ){

			local_sync_log($local_sync_version_in_options_table, "-----running---update_1_0_2_handle--------");
	
			$this->exclude_option = new Local_Sync_Exclude_Option();
			$this->exclude_option->exclude_default_tables();
		}
	}

	public function update_1_1_0_handle() {

		$is_table_exist = $this->wpdb->query('SHOW TABLES LIKE "'.$this->wpdb->base_prefix.'local_sync_options%"');

		if(!$is_table_exist){

			return;
		}

		$site_type = $this->local_sync_options->get_option('site_type');

		if(empty($site_type)){

			return;
		}


		$local_sync_version_in_options_table = $this->local_sync_options->get_option('local_sync_version');

		if(empty($local_sync_version_in_options_table)){

			return;
		}

		if (version_compare($local_sync_version_in_options_table, '1.1.0', '<')) {
			$table_name = $this->wpdb->base_prefix . 'local_sync_current_process';
			$this->wpdb->query("ALTER TABLE `$table_name` ADD `prev_uploaded_size` int(30) NULL;");

			local_sync_log($table_name,'----running-----update_1_1_0_handle-----------------');
		}
	}
	public function create_fresh_tables() {
		$cachecollation = $this->local_sync_get_collation();

		$table_name = $this->wpdb->base_prefix . 'local_sync_options';
		dbDelta("CREATE TABLE IF NOT EXISTS `$table_name` (
			name varchar(50) NOT NULL,
			value text NOT NULL,
			UNIQUE KEY name (name)
		) " . $cachecollation . " ;");

		$table_name = $this->wpdb->base_prefix . 'local_sync_processed_iterator';
		dbDelta("CREATE TABLE IF NOT EXISTS $table_name (
			`id` int(11) NOT NULL AUTO_INCREMENT,
			`name` longtext NOT NULL,
			`offset` text DEFAULT NULL,
			PRIMARY KEY (`id`),
			UNIQUE `name` (`name`(191))
		) " . $cachecollation . " ;");

		$table_name = $this->wpdb->base_prefix . 'local_sync_current_process';
		dbDelta("CREATE TABLE IF NOT EXISTS $table_name (
			`id` bigint(20) NOT NULL AUTO_INCREMENT,
			`file_path` text NOT NULL,
			`status` char(1) NOT NULL DEFAULT 'Q' COMMENT 'P=Processed, Q= In Queue, S- Skipped',
			`processed_time` varchar(30) NOT NULL,
			`file_hash` varchar(128) DEFAULT NULL,
			`prev_uploaded_size` int(30) NULL,
			PRIMARY KEY (`id`),
			INDEX `file_path` (`file_path`(191))
			) ENGINE=InnoDB " . $cachecollation . ";"
		);

		$table_name = $this->wpdb->base_prefix . 'local_sync_local_site_files';
		dbDelta("CREATE TABLE IF NOT EXISTS $table_name (
			`id` bigint(20) NOT NULL AUTO_INCREMENT,
			`file_path` text NOT NULL,
			`status` char(1) NOT NULL DEFAULT 'Q' COMMENT 'P=Processed, Q= In Queue, S- Skipped',
			`processed_time` varchar(30) NOT NULL,
			`file_hash` varchar(128) DEFAULT NULL,
			PRIMARY KEY (`id`),
			INDEX `file_path` (`file_path`(191))
			) ENGINE=InnoDB " . $cachecollation . ";"
		);

		$table_name = $this->wpdb->base_prefix . 'local_sync_inc_exc_contents';
		dbDelta("CREATE TABLE IF NOT EXISTS $table_name (
			`id` int NOT NULL AUTO_INCREMENT,
			`key` text NOT NULL,
			`type` varchar(20) NOT NULL,
			`category` varchar(30) NOT NULL,
			`action` varchar(30) NOT NULL,
			`table_structure_only` int(1) NULL,
			`is_dir` int(1) NULL,
			PRIMARY KEY (`id`),
			INDEX `key` (`key`(191))
		) ENGINE=InnoDB " . $cachecollation . ";");

		$table_name = $this->wpdb->base_prefix . 'local_sync_local_site_new_attachments';
		dbDelta("CREATE TABLE IF NOT EXISTS $table_name (
			`id` int NOT NULL AUTO_INCREMENT,
			`url` text NOT NULL,
			`name` text NOT NULL,
			`relative_file_path` text NOT NULL,
			PRIMARY KEY (`id`),
			UNIQUE `url` (`url`(191))
		) ENGINE=InnoDB " . $cachecollation . ";");

		$table_name = $this->wpdb->base_prefix . 'local_sync_delete_list';
		dbDelta("CREATE TABLE IF NOT EXISTS $table_name (
			`id` int NOT NULL AUTO_INCREMENT,
			`file` text NOT NULL,
			`status` char(1) NOT NULL DEFAULT 'Q' COMMENT 'P=Processed, Q= In Queue, S- Skipped',
			PRIMARY KEY (`id`),
			INDEX `file` (`file`(191))
		) ENGINE=InnoDB " . $cachecollation . ";");
	}

	public function save_urls()	{
		global $LOCAL_SYNC_SITE_TYPE;

		$upload_dir_meta = wp_upload_dir();

		$enc_site_url = base64_encode(get_home_url());
		$enc_admin_url = base64_encode(network_admin_url());
		$enc_uploads_url = base64_encode($upload_dir_meta['baseurl']);

		$this->local_sync_options->set_option('local_site_url', get_home_url());
		$this->local_sync_options->set_option('local_site_url_enc', $enc_site_url);
		$this->local_sync_options->set_option('local_admin_url_enc', $enc_admin_url);
		$this->local_sync_options->set_option('local_uploads_url_enc', $enc_uploads_url);
	}

	public function local_sync_get_collation(){
		if (method_exists( $this->wpdb, 'get_charset_collate')) {
			$charset_collate =  $this->wpdb->get_charset_collate();
		}

		return !empty($charset_collate) ?  $charset_collate : ' DEFAULT CHARACTER SET utf8 DEFAULT COLLATE utf8_general_ci ' ;
	}

	/**
	 * Register the stylesheets for the admin area.
	 *
	 * @since    1.0.0
	 */
	public function enqueue_styles() {

		/**
		 * This function is provided for demonstration purposes only.
		 *
		 * An instance of this class should be passed to the run() function
		 * defined in Local_Sync_Loader as all of the hooks are defined
		 * in that particular class.
		 *
		 * The Local_Sync_Loader will then create the relationship
		 * between the defined hooks and the functions defined in this
		 * class.
		 */

		wp_enqueue_style( 'wp-jquery-ui-dialog' );
		wp_enqueue_style( $this->plugin_name, plugin_dir_url( __FILE__ ) . 'css/local-sync-admin.css', array(), $this->version, 'all' );

	}

	/**
	 * Register the JavaScript for the admin area.
	 *
	 * @since    1.0.0
	 */
	public function enqueue_scripts() {

		/**
		 * This function is provided for demonstration purposes only.
		 *
		 * An instance of this class should be passed to the run() function
		 * defined in Local_Sync_Loader as all of the hooks are defined
		 * in that particular class.
		 *
		 * The Local_Sync_Loader will then create the relationship
		 * between the defined hooks and the functions defined in this
		 * class.
		 */

		wp_enqueue_script( 'jquery-ui-dialog' ); 

		wp_enqueue_script( $this->plugin_name, plugin_dir_url( __FILE__ ) . 'js/local-sync-admin.js', array( 'jquery' ), LOCAL_SYNC_VERSION, false );

		wp_enqueue_script( 'ls-clipboard', plugin_dir_url( __FILE__ ) . '/js/ls-clipboard.min.js', array(), LOCAL_SYNC_VERSION );
	}

	public function local_sync_setttings_page($value='') {
		add_menu_page(
			'Local Sync',
			'WP Duplicate',
			'activate_plugins',
			plugin_dir_path(__FILE__) . 'views/local-sync-settings-display.php',
			null,
			null,
			20
		);
	}

	public function set_as_local_site_losy() {

		$this->app_functions->verify_ajax_requests();

		$this->local_sync_options->set_option('site_type', 'local');
		$this->local_sync_options->set_option('load_images_from_live_site_settings', 'no');

		die(json_encode([
			'success' =>  true
		]));

	}

	public function set_as_prod_site_losy() {

		$this->app_functions->verify_ajax_requests();

		$this->local_sync_options->set_option('site_type', 'production');
		$this->local_sync_options->set_option('load_images_from_live_site_settings', 'no');

		$this->app_functions->prepare_and_set_prod_key();

		die(json_encode([
			'success' =>  true
		]));

	}

	public function process_service_logout() {
		$this->app_functions->verify_ajax_requests();

		$this->local_sync_options->set_option('site_added_once', 0);
		$this->local_sync_options->set_option('is_logged_in', 0);
		$this->local_sync_options->set_option('password', 0);

		die(json_encode(array(
			'success' =>  true
		)));
	}

	public function process_add_site()	{
		$prod_key = sanitize_text_field( $_POST['data']['prod_key'] );

		$url = $this->app_functions->process_prod_key_and_set_prod_url($prod_key);

		$this->away_post_call_check_validity($prod_key);

		local_sync_die_with_json_encode_simple(array(
			'success' =>  true,
			'prod_site_url' => $url
		));
	}

	public function process_remove_site()	{
		$this->local_sync_options->set_option('prod_key', '');

		local_sync_die_with_json_encode_simple(array(
			'success' =>  true,
		));
	}

	public function process_service_login($from_ajax = true) {
		$this->app_functions->service_login(true);
	}

	public function start_sync_to_local_process() {
		$got_files_list = $this->local_sync_options->get_option('got_files_list');
		if(!$got_files_list){
			
		}
		
	}

	public function modified_files_modal_ok() {

		local_sync_log($_POST, "--------modified_files_modal_ok---POST-----");

		global $local_sync_ajax_start_time;
		$local_sync_ajax_start_time = time();
		global $local_sync_profiling_start;
		$local_sync_profiling_start = time();

		$this->app_functions->verify_ajax_requests();

		$user_selected_files = $_POST['user_selected_files'] ?? array();

		$un_selected_file_paths_str = array();

		foreach ($user_selected_files as $key => $value) {
			$wp_content_dir_1 = 'wp-content';
			if(stripos($key, $wp_content_dir_1 . '/uploads/local_sync') === false && (!$value || $value == 'false') ){
				$un_selected_file_paths_str[] = '"' . $key . '"';
			}
		}
		$un_selected_file_paths_str = implode(',', $un_selected_file_paths_str);

		$un_selected_file_paths_str = sanitize_text_field($un_selected_file_paths_str);

		$chosen_action_local_sync = sanitize_text_field($_POST['chosen_action_local_sync']) ?? 'sync_from_live_site';

		if($chosen_action_local_sync == 'push_to_live_site'){
			$this->local_sync_files_op = new Local_Sync_Files_Op();
			$this->local_sync_files_op->update_current_process_by_file_paths_str($un_selected_file_paths_str);

			local_sync_die_with_json_encode(array(
				'success' =>  true,
			));

			return;
		}


		$response = $this->away_post_modified_files_selection($un_selected_file_paths_str);

		if( !empty($response) && !empty($response['success'])){
			local_sync_log($response, "--------modified_files_modal_ok--success------");

			local_sync_die_with_json_encode(array(
				'success' =>  true,
			));
		}

		if( !empty($response) && !empty($response['error']) ){
			local_sync_log($response, "--------modified_files_modal_ok--error------");

			local_sync_die_with_json_encode(array(
				'error' =>  true,
			));
		}
	}

	public function modify_all_files_modal_cancel()	{

		local_sync_log($_POST, "--------modify_all_files_modal_cancel---POST-----");

		global $local_sync_ajax_start_time;
		$local_sync_ajax_start_time = time();
		global $local_sync_profiling_start;
		$local_sync_profiling_start = time();

		$this->app_functions->verify_ajax_requests();

		$is_pull_or_push = $this->local_sync_options->get_option('is_pull_or_push');

		if($is_pull_or_push == 'push'){
			$this->local_sync_files_op = new Local_Sync_Files_Op();
			$this->local_sync_files_op->update_all_current_process_except_ls_backup_files();

			local_sync_die_with_json_encode(array(
				'success' =>  true,
			));			
		}

		$response = $this->away_post_modified_files_selection('dont_replace_any_file');

		local_sync_die_with_json_encode(array(
			'success' =>  true,
		));
	}

	public function first_call_flags_reset() {
		$this->local_sync_options->remove_backup_dir_files();
		
		$this->local_sync_options->set_option('sync_action_id', time());
		$this->local_sync_options->set_option('current_sync_unique_id', uniqid());
		$this->local_sync_options->set_option('sync_current_action', 'file_list_preparation_for_local_dump');
		$this->local_sync_options->set_option('sync_sub_action', 'file_list_preparation_for_local_dump');
		$this->local_sync_options->set_option('sync_file_list_current_action', false);
		$this->local_sync_options->set_option('got_files_list', 0);
		$this->local_sync_options->set_option('restore_db_index', 0);
		$this->local_sync_options->set_option('away_action_first_call', 1);
		$this->local_sync_options->set_option('delete_state_files_from_download_list_offset', 0);
		$this->local_sync_options->set_option('prepare_delete_list_table_offset', 0);
		$this->local_sync_options->set_option('download_current_result', json_encode(array()));
		$this->local_sync_options->set_option('upload_current_result', json_encode(array()));
		$this->local_sync_options->set_option('actions_time_taken', json_encode(array()));
		$this->local_sync_options->set_option('extract_multi_call_flags_losy', json_encode(array()));
		$this->local_sync_options->set_option('pull_from_live_steps', json_encode(array()));
		$this->local_sync_options->set_option('push_to_live_steps', json_encode(array()));
		$this->local_sync_options->set_option('ls_plugin_url', WP_PLUGIN_URL . '/' . $this->plugin_name);

		$wp_content_dir_1 = 'wp-content';
		$URL = rtrim($this->local_sync_options->get_option('prod_site_url'), '/') . "/$wp_content_dir_1/uploads/local_sync/backups";
		$this->local_sync_options->set_option('prod_site_backup_location_url', $URL);

		//DB dump flags
		$this->local_sync_options->set_option('shell_db_dump_status', '');
		$this->local_sync_options->set_option('collected_tables_for_backups', false);
		$this->local_sync_options->set_option('collected_tables_for_backups_offset', 0);
		$this->local_sync_options->set_option('restore_deep_links_completed', 0);
		$this->local_sync_options->set_option('same_server_replace_url_multicall_status', 0);
		$this->local_sync_options->set_option('local_sync_db_backup_1_completed', false);
		$this->local_sync_options->set_option('local_sync_db_gz_1_completed', false);
		$this->local_sync_options->set_option('sql_gz_compression_offset_1', 0);
		$this->local_sync_options->set_option('local_sync_db_backup_2_completed', false);
		$this->local_sync_options->set_option('local_sync_db_gz_2_completed', false);
		$this->local_sync_options->set_option('sql_gz_compression_offset_2', 0);
		$this->local_sync_options->set_option('local_sync_db_un_gz_1_completed', false);
		$this->local_sync_options->set_option('local_sync_db_un_gz_2_completed', false);
		$this->local_sync_options->set_option('restore_full_db_process_completed', false);

		$this->local_sync_files_op = new Local_Sync_Files_Op();
		$this->local_sync_files_op->truncate_processed_iterator_table();
		$this->local_sync_options->truncate_current_process_table();
		$this->local_sync_files_op->truncate_local_site_file_list_table();
		$this->local_sync_options->truncate_delete_list_table();

		$this->local_sync_options->create_local_sync_files();
	}

	public function sync_from_live_site()	{
		local_sync_log($_POST, "--------sync_from_live_site---POST-----");

		global $local_sync_ajax_start_time;
		$local_sync_ajax_start_time = time();
		global $local_sync_profiling_start;
		$local_sync_profiling_start = time();
		global $iterator_files_count_this_call_losy;
		$iterator_files_count_this_call_losy = 0;

		$this->app_functions->verify_ajax_requests();

		$is_first_call = empty($_POST['data']['first_call']) ? false : true;

		if(!$is_first_call){
			$this->local_sync_options->check_last_action_logic();
		}

		$prod_key = $this->local_sync_options->get_option('prod_key');

		$url = $this->local_sync_options->get_option('prod_site_url');

		$this->local_sync_options->set_option('local_site_url', get_home_url());
		$this->local_sync_options->set_option('site_url_local_sync', get_home_url());
		$this->local_sync_options->set_option('child_site_specific_admin_url', admin_url());
		$this->local_sync_options->set_option('is_pull_or_push', 'pull');

		if (is_multisite()) {
			$this->local_sync_options->set_option('network_admin_url', network_admin_url());
		} else {
			$this->local_sync_options->set_option('network_admin_url', admin_url());
		}

		if($is_first_call){
			$this->first_call_flags_reset();

			$this->away_post_call_check_validity($prod_key);

			$pull_from_live_steps = array(
				'file_list_preparation_for_local_dump' => 'waiting',
				'start_db_dump_local_file_list' => 'waiting',
				'upload_local_file_list_dump' => 'waiting',
				'start_db_dump' => 'waiting',
				'start_file_list_preparation' => 'waiting',
				'import_local_file_list_dump' => 'waiting',
				'process_file_list_difference' => 'waiting',
				'send_batch_download_requests' => 'waiting',
				'copy_files_from_bridge' => 'waiting',
				'prepare_delete_list_table' => 'waiting',
				'db_dump_restore' => 'waiting',
				'delete_files_during_restore' => 'waiting',
			);
			$this->local_sync_options->set_option('pull_from_live_steps', json_encode($pull_from_live_steps));
		}

		$sync_current_action = $this->local_sync_options->get_option('sync_current_action');

		local_sync_log($sync_current_action, "--------sync_current_action---to be processed-----");

		$this->local_sync_options->set_this_current_action_step('processing');

		$is_away_action = false;
		if( $sync_current_action == 'start_db_dump' 
			|| $sync_current_action == 'start_file_list_preparation' 
			|| $sync_current_action == 'import_local_file_list_dump' 
			|| $sync_current_action == 'process_file_list_difference' 
			|| $sync_current_action == 'zip_creation' ){

			$is_away_action = true;
		}

		if( $is_away_action ){
			$this->away_post_call('run_sync_to_local', $url, $sync_current_action);
		} else {
			switch ($sync_current_action) {
				case 'file_list_preparation_for_local_dump':
					$this->app_functions->start_file_list_preparation_for_local_dump();
					break;
				case 'start_db_dump_local_file_list':
					$this->app_functions->start_db_dump_local_file_list();
					break;
				case 'upload_local_file_list_dump':
					$this->app_functions->process_upload_local_file_list_dump();
					break;
				case 'zip_download':
					$this->app_functions->do_zip_download();
					break;
				case 'send_batch_download_requests':
					$this->batch_download_obj = new Local_Sync_Batch_Download();
					$response = $this->batch_download_obj->send_batch_download_requests();
					break;
				case 'initiate_bridge_files':
					$this->app_functions->initiate_bridge_files();
					break;
				case 'initiate_zip_extract':
					$this->away_post_call_dont_handle_response('clear_temp_files_local_sync', $url);
					$this->app_functions->initiate_zip_extract();
					break;
				
				default:
					
					break;
			}
		}

	}

	public function push_to_live_site()	{
		local_sync_log($_POST, "--------push_to_live_site---POST-----");

		global $local_sync_ajax_start_time;
		$local_sync_ajax_start_time = time();
		global $local_sync_profiling_start;
		$local_sync_profiling_start = time();
		global $iterator_files_count_this_call_losy;
		$iterator_files_count_this_call_losy = 0;

		$this->app_functions->verify_ajax_requests();

		$prod_key = $this->local_sync_options->get_option('prod_key');

		$url = $this->local_sync_options->get_option('prod_site_url');

		$this->local_sync_options->set_option('local_site_url', get_home_url());
		$this->local_sync_options->set_option('site_url_local_sync', get_home_url());
		$this->local_sync_options->set_option('child_site_specific_admin_url', admin_url());
		$this->local_sync_options->set_option('is_pull_or_push', 'push');

		if (is_multisite()) {
			$this->local_sync_options->set_option('network_admin_url', network_admin_url());
		} else {
			$this->local_sync_options->set_option('network_admin_url', admin_url());
		}

		$is_first_call = empty($_POST['data']['first_call']) ? false : true;

		if($is_first_call){
			$this->first_call_flags_reset();

			$this->away_post_call_check_validity($prod_key);
		}

		$sync_current_action = $this->local_sync_options->get_option('sync_current_action');

		local_sync_log($sync_current_action, "--------sync_current_action---to be processed-----");

		$this->local_sync_options->set_this_current_action_step('processing');

		$is_away_action = false;
		if( $sync_current_action == 'file_list_preparation_for_local_dump' 
			|| $sync_current_action == 'start_db_dump_local_file_list' 
			|| $sync_current_action == 'initiate_zip_extract'
			|| $sync_current_action == 'initiate_bridge_files' ){

			$is_away_action = true;
		}

		if( $is_away_action ){
			$this->away_post_call('run_sync_from_local', $url, $sync_current_action);
		} else {
			switch ($sync_current_action) {
				case 'download_local_file_list_dump':
					$this->app_functions->process_download_local_file_list_dump();
					break;
				case 'start_db_dump':
					$this->app_functions->start_db_dump();
					break;
				case 'start_file_list_preparation':
					$this->app_functions->start_file_list_preparation();
					break;
				case 'import_local_file_list_dump':
					$this->app_functions->import_local_file_list_dump();
					break;
				case 'process_file_list_difference':
					$this->app_functions->process_file_list_difference();
					break;
				case 'batch_upload_files':
					$this->batch_upload_obj = new Local_Sync_Batch_Upload();
					$response = $this->batch_upload_obj->batch_upload_files();
					break;
				case 'zip_creation':
					$response = $this->app_functions->create_zip();
					break;
				case 'zip_upload':
					$this->app_functions->do_zip_upload();
					break;
				
				default:
					
					break;
			}
		}

	}

	public function process_get_steps_for_steps_parent_echo() {
		$steps_parent = $_POST['data']['steps_parent'];

		local_sync_log($_POST, "--------get_steps_for_steps_parent_echo--------");

		$this->local_sync_options->get_steps_for_steps_parent_echo($steps_parent);
	}

	public function away_post_call($main_action, $url='', $sync_current_action='')	{
		$away_action_first_call = $this->local_sync_options->get_option('away_action_first_call');
		$post_body = array(
			'action' => $main_action,
			'first_call' => $away_action_first_call,
			'sync_current_action' => $sync_current_action,
		);

		$post_body['load_images_from_live_site_settings'] = $this->local_sync_options->get_option('load_images_from_live_site_settings');
		if($away_action_first_call){
			$post_body['current_sync_unique_id'] = $this->local_sync_options->get_option('current_sync_unique_id');
			$post_body['prod_site_url'] = $this->local_sync_options->get_option('local_site_url');
			$post_body['away_sync_type_db_or_files'] = $this->local_sync_options->get_option('sync_type_db_or_files');

			$post_body['away_site_abspath'] = wp_normalize_path(ABSPATH);
			$post_body['away_site_db_prefix'] = $this->wpdb->base_prefix;
			$post_body['is_away_site_multisite'] = defined('MULTISITE') ? MULTISITE : false;
			$post_body['away_site_id_current_site'] = defined('SITE_ID_CURRENT_SITE') ? SITE_ID_CURRENT_SITE : false;
			$post_body['away_blog_id_current_site'] = defined('BLOG_ID_CURRENT_SITE') ? BLOG_ID_CURRENT_SITE : false;
		}

		$this->local_sync_options->set_option('away_action_first_call', 0);

		$response = $this->app_functions->wp_remote_post_local_sync($url, $post_body);

		local_sync_log($response, "-----away_site_response---response--------");

		if( !empty($response) && !empty($response['success'])){

			if( !empty($response['sync_current_action']) 
				&& $sync_current_action != $response['sync_current_action'] ){
				$this->local_sync_options->set_this_current_action_step('done');
			}

			if( !empty($response['sync_current_action']) ){
				$this->local_sync_options->set_option('sync_current_action', $response['sync_current_action']);
			}
			
			$this->local_sync_options->set_this_current_action_step('processing');

			if(!empty($response['prod_site_url_enc'])){
				$this->local_sync_options->set_option('prod_site_url_enc', $response['prod_site_url_enc']);
				$this->local_sync_options->set_option('prod_admin_url_enc', $response['prod_admin_url_enc']);
				$this->local_sync_options->set_option('prod_uploads_url_enc', $response['prod_uploads_url_enc']);
				$this->local_sync_options->set_option('away_site_abspath', $response['away_site_abspath']);
				$this->local_sync_options->set_option('away_site_db_prefix', $response['away_site_db_prefix']);
				$this->local_sync_options->set_option('is_away_site_multisite', $response['is_away_site_multisite']);
				$this->local_sync_options->set_option('away_site_id_current_site', $response['away_site_id_current_site']);
				$this->local_sync_options->set_option('away_blog_id_current_site', $response['away_blog_id_current_site']);
			}

			local_sync_die_with_json_encode($response);

			return;
		}

		if( !empty($response) && !empty($response['error']) ){
			$this->local_sync_options->set_this_current_action_step('error');
			local_sync_die_with_json_encode($response);
		}

		if( !empty($response) && is_string($response) ){
			$this->local_sync_options->set_this_current_action_step('error');
			die($response);
		}

		$this->local_sync_options->set_this_current_action_step('error');

		local_sync_die_with_json_encode(array(
			'error' =>  'Error No proper response from away site.'
		));
	}

	public function away_post_call_dont_handle_response($main_action, $url='', $sync_current_action='')	{
		$post_body = array(
			'action' => $main_action
		);

		$response = $this->app_functions->wp_remote_post_local_sync($url, $post_body);

		if( !empty($response) && !empty($response['success'])){
			local_sync_log($response, "--------away_post_call_dont_handle_response--success------");
		}

		if( !empty($response) && !empty($response['error']) ){
			local_sync_log($response, "--------away_post_call_dont_handle_response--error------");
		}
	}

	public function away_post_call_check_validity($prod_key='') {
		$post_body = array(
			'action' => 'check_validity',
			'prod_key' => $prod_key
		);

		$url = $this->local_sync_options->get_option('prod_site_url');

		$response = $this->app_functions->wp_remote_post_local_sync($url, $post_body);

		local_sync_log($response, "--------response-----away_post_call_check_validity---");

		$this->app_functions->process_features_from_response($response);
		if(!empty($response['prod_site_url_enc'])){
			$this->local_sync_options->set_option('prod_site_url_enc', $response['prod_site_url_enc']);
			$this->local_sync_options->set_option('prod_admin_url_enc', $response['prod_admin_url_enc']);
			$this->local_sync_options->set_option('prod_uploads_url_enc', $response['prod_uploads_url_enc']);
			$this->local_sync_options->set_option('away_site_abspath', $response['away_site_abspath']);
			$this->local_sync_options->set_option('away_site_db_prefix', $response['away_site_db_prefix']);
			$this->local_sync_options->set_option('is_away_site_multisite', $response['is_away_site_multisite']);
			$this->local_sync_options->set_option('away_site_id_current_site', $response['away_site_id_current_site']);
			$this->local_sync_options->set_option('away_blog_id_current_site', $response['away_blog_id_current_site']);
		}

		if( !empty($response) && !empty($response['success'])){
			local_sync_log($response, "--------away_post_call_check_validity--success------");
		}

		if( !empty($response) && !empty($response['error']) ){
			local_sync_log($response, "--------away_post_call_check_validity--error------");

			local_sync_die_with_json_encode(array(
				'error' =>  $response['error'],
				'is_validity_check_request' => true,
				'sync_sub_action' => $this->local_sync_options->get_option('sync_sub_action'),
				'sync_current_action' => $this->local_sync_options->get_option('sync_current_action'),
				'requires_next_call' => false
			));
		}
	}

	public function away_post_modified_files_selection($un_selected_file_paths_str='') {
		$post_body = array(
			'action' => 'modified_files_selection',
			'un_selected_file_paths_str' => $un_selected_file_paths_str
		);

		if(empty($un_selected_file_paths_str)){
			return array(
				'success' => true
			);
		}

		$url = $this->local_sync_options->get_option('prod_site_url');

		$response = $this->app_functions->wp_remote_post_local_sync($url, $post_body);

		return $response;
	}

	public function start_zip_creation_dev()	{
		global $local_sync_ajax_start_time;
		$local_sync_ajax_start_time = time();
		global $local_sync_profiling_start;
		$local_sync_profiling_start = time();

		local_sync_log($_POST, "--------start_zip_creation---POST-----");

		$url = sanitize_text_field( $_POST['data']['prod_site_url'] );

		if(!empty($url)){
			$this->local_sync_options->set_option('prod_site_url', $url);
		}

		$current_sync_unique_id = $this->local_sync_options->get_option('current_sync_unique_id');

		@unlink(WP_CONTENT_DIR . "/uploads/local_sync/backups/local_sync_files-$current_sync_unique_id.zip");

		$response = $this->app_functions->create_zip();

		local_sync_log($response, "-----start_zip_creation---response--------");
	}

	public function start_zip_download_dev()	{
		global $local_sync_ajax_start_time;
		$local_sync_ajax_start_time = time();
		global $local_sync_profiling_start;
		$local_sync_profiling_start = time();

		local_sync_log($_POST, "--------start_zip_download---POST-----");

		$url = sanitize_text_field( $_POST['data']['prod_site_url'] );

		$this->local_sync_options->set_option('prod_site_url', $url);

		$response = $this->app_functions->do_zip_download();

		local_sync_log($response, "-----start_zip_download---response--------");
	}

	public function zip_extract_dev()	{
		global $local_sync_ajax_start_time;
		$local_sync_ajax_start_time = time();
		global $local_sync_profiling_start;
		$local_sync_profiling_start = time();

		local_sync_log($_POST, "--------zip_extract---POST-----");

		$url = $_POST['data']['prod_site_url'];

		$url = sanitize_text_field($url);

		$this->local_sync_options->set_option('sync_current_action', 'initiate_zip_extract');

		$this->local_sync_options->set_option('prod_site_url', $url);

		$response = $this->app_functions->initiate_zip_extract();

		local_sync_log($response, "-----zip_extract---response--------");
	}

	public function start_zip_download()	{

		$response = $this->app_functions->do_zip_download();

	}

	public function test_button()	{
		global $local_sync_ajax_start_time;
		$local_sync_ajax_start_time = time();
		global $local_sync_profiling_start;
		$local_sync_profiling_start = time();

		local_sync_log($_POST, "--------test_button---POST-----");

		return;

		$this->restore_app_functions = new Local_Sync_Restore_Op();

		local_sync_log($this->wpdb->base_prefix, "--------base_prefix--------");
		local_sync_log($this->wpdb->prefix, "--------prefix--------");
	}

	public function handle_requests() {
		global $local_sync_ajax_start_time;
		$local_sync_ajax_start_time = time();

		$requests_handler = new Local_Sync_Handle_Server_Requests();
		$requests_handler->init();
	}

	public function modify_posts_content($content) {
		// local_sync_log($content, "--------modify_posts_content--------");

		global $LOCAL_SYNC_SITE_TYPE;
		global $LOCAL_SYNC_PROD_UPLOADS_URL;
		global $LOCAL_SYNC_LOCAL_UPLOADS_URL;
		global $LOCAL_SYNC_LOAD_IMAGES_FROM_LIVE;

		if(!empty($LOCAL_SYNC_SITE_TYPE) && $LOCAL_SYNC_SITE_TYPE != 'production' ){
			$content = $this->replace_relative_url($content);
		}
		if( $LOCAL_SYNC_LOAD_IMAGES_FROM_LIVE != 'yes'
			|| empty($LOCAL_SYNC_SITE_TYPE) 
			|| $LOCAL_SYNC_SITE_TYPE != 'local' 
			|| empty($LOCAL_SYNC_PROD_UPLOADS_URL) ){

			return $content;
		}

		// return $content;
		
		// local_sync_log($content, "----before----modify_posts_content--------");

		$all_new_attachment_urls_arr = $this->get_local_site_new_attachments_url();
		foreach ($all_new_attachment_urls_arr as $key => $value) {
			$content = str_replace($value, $key, $content);
		}

		$content = str_replace($LOCAL_SYNC_LOCAL_UPLOADS_URL, $LOCAL_SYNC_PROD_UPLOADS_URL, $content);

		foreach ($all_new_attachment_urls_arr as $key => $value) {
			$content = str_replace($key, $value, $content);
		}

		// local_sync_log($content, "--------modified_posts_content--------");
		
		return $content;
	}

	public function replace_relative_url($content){
		if(empty($content)){
			return $content;
		}

		global $LOCAL_SYNC_PROD_URL;
		global $LOCAL_SYNC_LOCAL_URL;
		
		$prod_site_base_paths = explode("/", $LOCAL_SYNC_PROD_URL);
		$prod_site_origin_url = $prod_site_base_paths[0] . '//' . $prod_site_base_paths[2];

		$prod_site_base_path = str_replace($prod_site_origin_url, '', $LOCAL_SYNC_PROD_URL);

		if(empty($prod_site_base_path)){
			$prod_site_base_path = '';
		}

		$prod_site_base_path = ltrim($prod_site_base_path, '/');
      
		$content = str_replace('href="/' . $prod_site_base_path, 'href="' . $LOCAL_SYNC_LOCAL_URL . '/', $content);
		return $content;
	}

	public function modify_image_site_url( $url, $post_id = null ) {
		// local_sync_log($url, "--------modify_image_site_url--------");

		global $LOCAL_SYNC_SITE_TYPE;
		global $LOCAL_SYNC_PROD_UPLOADS_URL;
		global $LOCAL_SYNC_LOCAL_UPLOADS_URL;
		global $LOCAL_SYNC_LOAD_IMAGES_FROM_LIVE;

		if( $LOCAL_SYNC_LOAD_IMAGES_FROM_LIVE != 'yes'
			|| empty($LOCAL_SYNC_SITE_TYPE) 
			|| $LOCAL_SYNC_SITE_TYPE != 'local' 
			|| empty($LOCAL_SYNC_PROD_UPLOADS_URL) ){

			return $url;
		}

		$all_new_attachment_urls_arr = $this->get_local_site_new_attachments_url();

		if(in_array($url, $all_new_attachment_urls_arr)){

			return $url;
		}

		$url = str_replace($LOCAL_SYNC_LOCAL_UPLOADS_URL, $LOCAL_SYNC_PROD_UPLOADS_URL, $url);

		local_sync_log($url, "--------modified_image_site_url--------");
		
		return $url;
	}

	public function wp_prepare_attachment_for_js($response, $attachment = null, $meta = null)	{

		global $LOCAL_SYNC_SITE_TYPE;
		global $LOCAL_SYNC_PROD_UPLOADS_URL;
		global $LOCAL_SYNC_LOCAL_UPLOADS_URL;
		global $LOCAL_SYNC_LOAD_IMAGES_FROM_LIVE;

		if( $LOCAL_SYNC_LOAD_IMAGES_FROM_LIVE != 'yes'
			|| empty($LOCAL_SYNC_SITE_TYPE) 
			|| $LOCAL_SYNC_SITE_TYPE != 'local' 
			|| empty($LOCAL_SYNC_PROD_UPLOADS_URL) ){

			local_sync_log($LOCAL_SYNC_SITE_TYPE, "--------LOCAL_SYNC_SITE_TYPE--------");
			local_sync_log($LOCAL_SYNC_PROD_UPLOADS_URL, "--------LOCAL_SYNC_PROD_UPLOADS_URL--------");
			local_sync_log($LOCAL_SYNC_LOCAL_UPLOADS_URL, "--------LOCAL_SYNC_LOCAL_UPLOADS_URL--------");

			return $response;
		}


		if(empty($response) || $response['type'] != 'image' || empty($response['sizes']) ){

			return $response;
		}

		$all_new_attachment_urls_arr = $this->get_local_site_new_attachments_url();

		// local_sync_log($all_new_attachment_urls_arr, "--------all_new_attachment_urls_arr--------");

		$needs_change = true;
		foreach ($response['sizes'] as $key => $value) {
			if(in_array($value['url'], $all_new_attachment_urls_arr)){

				local_sync_log($value['url'], "--------value['url']---kusumban-----");

				$needs_change = false;

				break;
			}
		}

		if(!$needs_change){

			return $response;
		}

		foreach ($response['sizes'] as $key => $value) {
			$response['sizes'][$key]['url'] = str_replace($LOCAL_SYNC_LOCAL_UPLOADS_URL, $LOCAL_SYNC_PROD_UPLOADS_URL, $value['url']);
		}
		
		return $response;
	}

	public function modify_image_src_set( $sources, $size_array = null, $image_src = null, $image_meta = null, $attachment_id = null ) {
		global $LOCAL_SYNC_SITE_TYPE;
		global $LOCAL_SYNC_PROD_UPLOADS_URL;
		global $LOCAL_SYNC_LOCAL_UPLOADS_URL;
		global $LOCAL_SYNC_LOAD_IMAGES_FROM_LIVE;

		if( $LOCAL_SYNC_LOAD_IMAGES_FROM_LIVE != 'yes'
			|| empty($LOCAL_SYNC_SITE_TYPE) 
			|| $LOCAL_SYNC_SITE_TYPE != 'local' 
			|| empty($LOCAL_SYNC_PROD_UPLOADS_URL) ){

			return $sources;
		}

		// local_sync_log($sources, "--------modify_image_src_set--------");
		// local_sync_log($image_src, "--------image_src--------");
		// local_sync_log($image_meta, "--------image_meta--------");
		// local_sync_log($attachment_id, "--------attachment_id--------");

		$all_new_attachment_urls_arr = $this->get_local_site_new_attachments_url();

		// local_sync_log($all_new_attachment_urls_arr, "--------all_new_attachment_urls_arr--------");

		$needs_change = true;
		foreach ($sources as $key => $value) {
			if(in_array($value['url'], $all_new_attachment_urls_arr)){

				local_sync_log($value['url'], "--------value['url']---kusumban-----");

				$needs_change = false;

				break;
			}
		}

		if(!$needs_change){

			return $sources;
		}

		foreach ($sources as $key => $value) {
			$value['url'] = str_replace($LOCAL_SYNC_LOCAL_UPLOADS_URL, $LOCAL_SYNC_PROD_UPLOADS_URL, $value['url']);
			$sources[$key] = $value;
		}

		local_sync_log($sources, "---sources-----modified_image_src_set--------");
		
		return $sources;
	}

	public function insert_into_local_site_new_attachments_table($data = null)	{
		global $wpdb;

		$uploads_url_with_slash = content_url() . '/uploads/';
		$relative_file_path = str_replace($uploads_url_with_slash, '', $data['guid']);

		$table_name = $this->wpdb->base_prefix . 'local_sync_local_site_new_attachments';
		$insert_res = $wpdb->replace($table_name, array(
			'url' => $data['guid'],
			'name' =>$data['post_name'], 
			'relative_file_path' => $relative_file_path
		));

		if(false === $insert_res){
			local_sync_log($wpdb->last_error, "--------insert_res--error--insert_into_local_site_new_attachments_table----");
		}
	}

	public function get_local_site_new_attachments_url()	{
		global $wpdb;

		$table_name = $this->wpdb->base_prefix . 'local_sync_local_site_new_attachments';

		$sql = "SELECT url FROM `$table_name` WHERE 1 ORDER BY url";
		$all_new_attachment_urls = $wpdb->get_results($sql, ARRAY_A);

		if($all_new_attachment_urls === false){
			local_sync_log($sql, "--------error----get_local_site_new_attachments_url----");
		}

		$all_new_attachment_urls_arr = array();

		foreach ($all_new_attachment_urls as $key => $value) {
			$md5 = md5($value['url']);
			$all_new_attachment_urls_arr[$md5] = $value['url'];
		}

		local_sync_log($all_new_attachment_urls_arr, "--------all_new_attachment_urls_arr--------");

		return $all_new_attachment_urls_arr;
	}

	public function get_local_site_new_attachments_file_path()	{
		global $wpdb;

		$table_name = $this->wpdb->base_prefix . 'local_sync_local_site_new_attachments';

		$sql = "SELECT relative_file_path FROM `$table_name` WHERE 1 ORDER BY relative_file_path";
		$all_new_attachment_files = $wpdb->get_results($sql, ARRAY_A);

		if($all_new_attachment_files === false){
			local_sync_log($sql, "--------error----get_local_site_new_attachments_url----");
		}

		$all_new_attachment_files_arr = array();

		foreach ($all_new_attachment_files as $key => $value) {
			$md5 = md5($value['relative_file_path']);
			$all_new_attachment_files_arr[$md5] = $value['relative_file_path'];
		}

		// local_sync_log($all_new_attachment_files_arr, "--------all_new_attachment_files_arr--------");

		return $all_new_attachment_files_arr;
	}

	public function wp_insert_attachment_data($data = null, $arg2 = null) {
		// local_sync_log($data, "--------arg1-wp_insert_attachment_data-------");
		// local_sync_log($arg2, "--------arg2-wp_insert_attachment_data-------");

		if(empty($data)){
			return $data;
		}

		if($data['post_type'] == 'attachment'){
			$this->insert_into_local_site_new_attachments_table($data);
		}

		return $data;
	}

	public function admin_print_footer_scripts() {
		global $LOCAL_SYNC_PROD_URL;
		global $LOCAL_SYNC_LOCAL_URL;
		global $LOCAL_SYNC_LOAD_IMAGES_FROM_LIVE;

		if($LOCAL_SYNC_LOAD_IMAGES_FROM_LIVE != 'yes'){
			echo '';

			return;
		}

		local_sync_log("", "--------admin_print_footer_scripts--------");

		echo '<script type="text/javascript">
			var LOCAL_SYNC_PROD_URL = "'.$LOCAL_SYNC_PROD_URL.'";
			var LOCAL_SYNC_LOCAL_URL = "'.$LOCAL_SYNC_LOCAL_URL.'";
			setTimeout(function(){ jQuery(".editor-writing-flow img").each(function(){
				var srcAttr = jQuery(this).attr("src");
				console.log(srcAttr);
				if(typeof srcAttr == "undefined" || srcAttr == "" || !srcAttr){
					return;
				}

				if(srcAttr.indexOf(LOCAL_SYNC_LOCAL_URL) < 0){
					return;
				}

				srcAttr = srcAttr.replace(LOCAL_SYNC_LOCAL_URL, LOCAL_SYNC_PROD_URL);
				jQuery(this).attr("src", srcAttr);
			}); }, 3000);

		</script>';
	}

	public function local_sync_get_root_files($args) {
		$this->exclude_option = new Local_Sync_Exclude_Option();
		$this->exclude_option->get_root_files();
	}

	public function local_sync_get_init_root_files($args) {
		$this->exclude_option = new Local_Sync_Exclude_Option();
		$this->exclude_option->get_root_files($exc_wp_files = true);
	}

	public function local_sync_get_files_by_key($args) {
		$key = sanitize_text_field($_REQUEST['key']);

		$this->exclude_option = new Local_Sync_Exclude_Option();
		$this->exclude_option->get_files_by_key($key);
	}

	public function include_file_list_local_sync() {

		$this->app_functions->verify_ajax_requests();

		$this->exclude_option = new Local_Sync_Exclude_Option();

		if (!isset($_POST['data'])) {
			local_sync_die_with_json_encode( array('status' => 'no data found') );
		}
		$this->exclude_option->include_file_list($_POST['data']);
	}

	public function exclude_file_list_local_sync() {

		$this->app_functions->verify_ajax_requests();

		$this->exclude_option = new Local_Sync_Exclude_Option();

		if (!isset($_POST['data'])) {
			local_sync_die_with_json_encode( array('status' => 'no data found') );
		}
		$this->exclude_option->exclude_file_list($_POST['data']);
	}

	public function include_table_list_local_sync() {

		$this->app_functions->verify_ajax_requests();

		$this->exclude_option = new Local_Sync_Exclude_Option();

		if (!isset($_POST['data'])) {
			local_sync_die_with_json_encode( array('status' => 'no data found') );
		}
		$this->exclude_option->include_table_list($_POST['data']);
	}

	public function include_table_structure_only_local_sync() {

		$this->app_functions->verify_ajax_requests();

		$this->exclude_option = new Local_Sync_Exclude_Option();

		if (!isset($_POST['data'])) {
			local_sync_die_with_json_encode( array('status' => 'no data found') );
		}
		$this->exclude_option->include_table_structure_only($_POST['data']);
	}

	public function exclude_table_list_local_sync() {

		$this->app_functions->verify_ajax_requests();

		$this->exclude_option = new Local_Sync_Exclude_Option();

		if (!isset($_POST['data'])) {
			local_sync_die_with_json_encode( array('status' => 'no data found') );
		}
		$this->exclude_option->exclude_table_list($_POST['data']);
	}

	public function local_sync_get_tables() {

		$this->exclude_option = new Local_Sync_Exclude_Option();

		// if (!isset($_POST['data'])) {
		// 	local_sync_die_with_json_encode( array('status' => 'no data found') );
		// }

		$this->exclude_option->get_tables();
	}

	public function save_settings_local_sync() {

		local_sync_log($_POST, "--------save_settings_local_sync--------");

		$this->app_functions->verify_ajax_requests();

		$this->exclude_option = new Local_Sync_Exclude_Option();

		if (!isset($_POST['data'])) {
			local_sync_die_with_json_encode( array('status' => 'no settings data') );
		}

		if(empty($_POST['data']['settings'])){

			local_sync_log('', "--------save_settings_local_sync---empty-----");

			return;
		}

		if(!empty($_POST['data']['settings']['prod_key'])){
			// $this->local_sync_options->set_option('prod_key', $_POST['data']['settings']['prod_key']);
			// $url = $this->app_functions->process_prod_key_and_set_prod_url($_POST['data']['settings']['prod_key']);
		}

		if(!empty($_POST['data']['settings']['prod_url'])){
			$url = rtrim($_POST['data']['settings']['prod_url'], '/');
			$url = sanitize_text_field( $_POST['data']['prod_site_url'] );
			$this->local_sync_options->set_option('prod_site_url', $url);
			$this->local_sync_options->set_option('prod_site_url_enc', base64_encode($url) );
		}

		if(!empty($_POST['data']['settings']['load_images_from_live_site_settings'])){
			$load_images_from_live_site_settings = sanitize_text_field($_POST['data']['settings']['load_images_from_live_site_settings']);

			if( !empty($load_images_from_live_site_settings) ){

				$this->local_sync_options->set_option('load_images_from_live_site_settings', $load_images_from_live_site_settings);
			}
		}

		if(!empty($_POST['data']['settings']['user_excluded_extenstions'])){
			$user_excluded_extenstions = sanitize_text_field($_POST['data']['settings']['user_excluded_extenstions']);

			if(!empty($user_excluded_extenstions) || $user_excluded_extenstions == ''){
				$this->local_sync_options->set_option('user_excluded_extenstions', $user_excluded_extenstions);
			}
		}

		if(!empty($_POST['data']['settings']['user_excluded_files_more_than_size_settings'])){
			$exclude_by_size_settings = $_POST['data']['settings']['user_excluded_files_more_than_size_settings'];

			if( empty($exclude_by_size_settings['size']) 
				|| $exclude_by_size_settings['size'] < 10 ){

				local_sync_log('', "--------exclude_by_size_settings--<--10------");
			} else{
				$exclude_by_size_settings['size'] = sanitize_text_field($exclude_by_size_settings['size']);
				$bytes_val = $this->local_sync_options->convert_mb_to_bytes($exclude_by_size_settings['size']);

				$this->local_sync_options->set_option('user_excluded_files_more_than_size_settings', serialize(array(
					'status' => (empty($exclude_by_size_settings['status']) || $exclude_by_size_settings['status'] == 'false') ? false : true, 
					'size' => $bytes_val
				)));
			}

		}

		if(!empty($_POST['data']['settings']['sync_type_db_or_files'])){
			$sync_type_db_or_files = sanitize_text_field($_POST['data']['settings']['sync_type_db_or_files']);
			$this->local_sync_options->set_option('sync_type_db_or_files', $sync_type_db_or_files);
		}

		local_sync_die_with_json_encode_simple(array(
			'prod_site_url' => $this->local_sync_options->get_option('prod_site_url')
		));
	}
}
