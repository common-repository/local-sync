<?php

	// if ( ! defined('BRIDGE_NAME_LOCAL_SYNC') ){
	// 	define('BRIDGE_NAME_LOCAL_SYNC', dirname(__FILE__) );
	// }

	if ( ! defined('LOCAL_SYNC_BRIDGE') ){
		define('LOCAL_SYNC_BRIDGE', true );
	}

	if ( ! defined('FS_CHMOD_DIR') )
		define('FS_CHMOD_DIR', 0755 );
	if ( ! defined('FS_CHMOD_FILE') )
		define('FS_CHMOD_FILE', 0644 );

	$GLOBALS['needFileSystem'] = false;
	$GLOBALS['is_new_backup'] = false;

	global $local_sync_ajax_start_time;
	$local_sync_ajax_start_time = time();
	global $local_sync_profiling_start;
	$local_sync_profiling_start = time();

	error_reporting(error_reporting() & ~E_DEPRECATED);
	include_once(dirname(__FILE__) . '/wp-modified-functions.php');
	include_once(dirname(__FILE__) . '/wp-tc-config.php');
	if(file_exists(dirname(__FILE__) . '/local-sync-env-parameters.php')){
		include_once(dirname(__FILE__) . '/local-sync-env-parameters.php');
	}
	include_once(dirname(__FILE__) . '/local-sync-constants.php');
	include_once(dirname(__FILE__) . '/local-sync-generic-functions.php');
	include_once(dirname(__FILE__) . '/local-sync-debug.php');

	// include_once(dirname(__FILE__) . '/admin/class-pclzip.php');
	include_once(dirname(__FILE__) . '/iwp-pclzip.php');
	include_once(dirname(__FILE__) . '/admin/class-local-sync-zip-facade.php');
	include_once(dirname(__FILE__) . '/admin/class-local-sync-restore-op.php');
	include_once(dirname(__FILE__) . '/admin/class-local-sync-replace-db-links.php');
	include_once(dirname(__FILE__) . '/admin/class-local-sync-exclude-option.php');
	include_once(dirname(__FILE__) . '/admin/class-local-sync-file-ext.php');
	include_once(dirname(__FILE__) . '/admin/class-local-sync-file-iterator.php');

	include_once(dirname(__FILE__) . '/includes/class-local-sync-options.php');

	include_once( dirname(__FILE__) . '/wp-files/wp-db-custom.php');
	include_once( dirname(__FILE__) . '/wp-files/class-wp-error.php');
	include_once( dirname(__FILE__) . '/wp-files/file.php');
	include_once( dirname(__FILE__) . '/wp-files/class-wp-filesystem-base.php');
	include_once( dirname(__FILE__) . '/wp-files/class-wp-filesystem-direct.php');
	include_once( dirname(__FILE__) . '/wp-files/class-wp-filesystem-ftpext.php');
	include_once( dirname(__FILE__) . '/wp-files/class-wp-filesystem-ssh2.php');
	include_once( dirname(__FILE__) . '/wp-files/class-wp-filesystem-ftpsockets.php');

	// if (function_exists('date_default_timezone_set')) {
	// 	date_default_timezone_set('UTC');
	// }

	error_reporting(E_ERROR | E_PARSE);
	ini_set('display_errors', 'On');

	$constants = new Local_Sync_Constants();
	$constants->init_restore();

	register_shutdown_function('local_sync_fatal_error_hadler_bridge');
	function local_sync_fatal_error_hadler_bridge($return = null) {
		$last_error = error_get_last();

		if(!empty($last_error) && !empty($last_error['type']) && $last_error['type'] != 8192){
			local_sync_log($last_error, "--------last_error--------");
		}

	}

	class Local_Sync_Bridge_Index
	{
		public $wpdb;
		public $restore_app_functions;

		public function __construct() {
			$this->dir_path = dirname(__FILE__). '/';

			$this->init_db_connection();
			$this->initiate_filesystem();

			$this->local_sync_options = new Local_Sync_Options();
			$this->restore_app_functions = new Local_Sync_Restore_Op();
		}

		public function init_db_connection(){
			//initialize wpdb since we are using it independently
			global $wpdb;
			$wpdb = new wpdb(DB_USER, DB_PASSWORD, DB_NAME, DB_HOST);

			//setting the prefix from post value;
			$wpdb->prefix = $wpdb->base_prefix = DB_PREFIX_LOCAL_SYNC;

			$this->wpdb = $wpdb;

			return $wpdb;
		}

		public function choose_action() {
			$sync_current_action = $this->local_sync_options->get_option('sync_current_action');
			$is_bridge_process = $this->local_sync_options->get_option('is_bridge_process');

			$this->local_sync_options->check_last_action_logic();

			local_sync_log($sync_current_action, "--------choosing action--------");

			if(empty($is_bridge_process)){

				local_sync_log('', "--------no_bridge_process_is_registered--------");

				die_with_ls_signature(array(
					'error' => 'No Bridge process is registered'
				));
			}

			$this->local_sync_options->set_this_current_action_step('processing');

			switch ($sync_current_action) {
				case 'continue_extract_from_bridge':

					$this->continue_extract_from_bridge();

					break;

				case 'continue_extract_from_live_bridge':

					$this->local_sync_options->set_this_step_by_name('start_db_dump', 'done'); //hack
					$this->local_sync_options->set_this_step_by_name('download_local_file_list_dump', 'done'); //hack

					$this->continue_extract_from_bridge();

					break;

				case 'copy_files_from_live_bridge':

					$this->local_sync_options->set_this_step_by_name('start_db_dump', 'done'); //hack
					$this->local_sync_options->set_this_step_by_name('download_local_file_list_dump', 'done'); //hack
					$this->local_sync_options->set_this_step_by_name('batch_upload_files', 'done'); //hack

					$this->copy_files_from_live_bridge();

					break;

				case 'copy_files_from_bridge':

					$this->copy_files_from_live_bridge();

					break;
				case 'prepare_delete_list_table':

					$this->restore_app_functions->process_prepare_delete_list_table();

					break;


				case 'delete_files_during_restore':
					$this->restore_app_functions->process_delete_files_during_restore();

					break;

				case 'db_dump_restore':

					$current_sync_type = $this->local_sync_options->get_option('sync_type_db_or_files');

					local_sync_log($current_sync_type, "--------sync_type_db_or_files--------");
						
					$do_db_restore = true;
					if(!empty($current_sync_type) && $current_sync_type == 'files_alone'){
						$do_db_restore = false;
					}

					if($do_db_restore){
						$this->restore_full_db_file();

						$this->restore_app_functions->migration_replace_links();
						$this->restore_app_functions->reset_bridge_constants();

						$this->disable_maintenance_mode();
					}

					$this->restore_app_functions->truncate_new_attachments_table();

					local_sync_manual_debug('', 'start_delete_empty_folders');

					if(defined('LOCAL_SYNC_DELETE_TEMP') && LOCAL_SYNC_DELETE_TEMP){
						$this->local_sync_options->remove_backup_dir_files();
					}

					$this->restore_app_functions->delete_empty_folders(WP_CONTENT_DIR . '/plugins'); //Remove invalid plugins
					$this->restore_app_functions->delete_empty_folders(WP_CONTENT_DIR . '/themes'); //Remove invalid themes

					local_sync_manual_debug('', 'end_delete_empty_folders');

					$this->local_sync_options->set_this_current_action_step('done');

					die_with_ls_signature(array(
						'success' =>  true,
						'sync_sub_action' => 'db_dump_and_replace_over',
						'sync_current_action' => 'redirect_to_local_site',
						'local_site_url' => $this->local_sync_options->get_option('local_site_url'),
						'requires_next_call' => false
					));

					break;
				
				default:
					# code...
					break;
			}

			$this->local_sync_options->set_this_current_action_step('error');

			die_with_ls_signature(array(
				'error' => 'Bridge process ends with no action'
			));
		}

		public function continue_extract_from_bridge()	{

			$unzip_dir = ABSPATH;
			// $unzip_dir = dirname(__FILE__) . '/restore_result';

			$current_sync_unique_id = $this->local_sync_options->get_option('current_sync_unique_id');

			$zipfile = WP_CONTENT_DIR . "/uploads/local_sync/backups/local_sync_files-$current_sync_unique_id.zip";

			local_sync_log($zipfile, "--------zipfile--------");

			global $extract_multi_call_flags_losy;
			$extract_multi_call_flags_losy = $this->local_sync_options->get_json_decoded_option('extract_multi_call_flags_losy');

			local_sync_log($extract_multi_call_flags_losy, "--------old extractions flags--------");

			global $backup_core_losy;

			$backup_core_losy = new Local_Sync_Zip_Facade();
			$archive   = new LOCAL_SYNC_PclZip();

			if (file_exists($zipfile)) {



				$opencode = $archive->open($zipfile);

				local_sync_log($opencode, "--------opencode--------");
				local_sync_log($archive->last_error, "--------archive error--------");

				$original_size = filesize($zipfile);
				clearstatcache();
			} else {

				local_sync_log('', "-----zip---file not exists--------");

				$create_code = (version_compare(PHP_VERSION, '5.2.12', '>') && defined('ZIPARCHIVE::CREATE')) ? ZIPARCHIVE::CREATE : 1;
				$opencode = $archive->open($zipfile, $create_code);
				$original_size = 0;
			}

			$extracted = $archive->extract($unzip_dir, $zipfile);

			local_sync_log($extracted, "--------extracted--------");

			if ( !empty($extracted) && !empty($extracted['break']) ) {

				local_sync_log('', "--------extraction in multicall--------");

				$this->local_sync_options->set_option('extract_multi_call_flags_losy', json_encode($extracted));

				die_with_ls_signature(array(
					'success' =>  true,
					'sync_sub_action' => $this->local_sync_options->get_option('sync_sub_action'),
					'sync_current_action' => $this->local_sync_options->get_option('sync_current_action'),
					'requires_next_call' => true
				));
			}

			$this->local_sync_options->set_option('extract_multi_call_flags_losy', json_encode(array()));

			if (!$extracted || $archive->error_code) {

				$this->local_sync_options->set_this_current_action_step('error');

				die_with_ls_signature(array(
					'error' => 'Error: Failed to extract backup file (' . $archive->error_string . ', ' . $archive->error_code . ').'
				));
			}

			// $this->local_sync_options->remove_tmp_dir();

			// unlink($single_backup_file);
			$this->local_sync_options->set_this_current_action_step('done');

			$this->local_sync_options->set_option('sync_sub_action', 'prepare_delete_list_table');
			$this->local_sync_options->set_option('sync_current_action', 'prepare_delete_list_table');

			$this->local_sync_options->set_this_current_action_step('processing');

			die_with_ls_signature(array(
				'success' =>  true,
				'sync_sub_action' => $this->local_sync_options->get_option('sync_sub_action'),
				'sync_current_action' => $this->local_sync_options->get_option('sync_current_action'),
				'requires_next_call' => true
			));
		}

		public function copy_files_from_live_bridge()	{

			$unzip_dir = ABSPATH;
			// $unzip_dir = dirname(__FILE__) . '/restore_result';

			$current_sync_unique_id = $this->local_sync_options->get_option('current_sync_unique_id');
			
			$backup_dir = WP_CONTENT_DIR . "/uploads/local_sync/backups/";
			
			local_sync_log($backup_dir, "--------backup_dir--------");
			
			$move_result = $this->local_sync_options->tc_file_system_move_dir($backup_dir, $unzip_dir);

			local_sync_log($move_result, "--------move_result--------");

			// if ( !empty($extracted) && !empty($extracted['break']) ) {

			// 	local_sync_log('', "--------extraction in multicall--------");

			// 	$this->local_sync_options->set_option('extract_multi_call_flags_losy', json_encode($extracted));

			// 	die_with_ls_signature(array(
			// 		'success' =>  true,
			// 		'sync_sub_action' => $this->local_sync_options->get_option('sync_sub_action'),
			// 		'sync_current_action' => $this->local_sync_options->get_option('sync_current_action'),
			// 		'requires_next_call' => true
			// 	));
			// }

			// $this->local_sync_options->remove_tmp_dir();

			// unlink($single_backup_file);

			// local_sync_log($move_result, "--------exciting by me----after files copy----");
			// local_sync_log($move_result, "--------exciting by me--------");

			// exit;

			$this->local_sync_options->set_this_current_action_step('done');

			$this->local_sync_options->set_option('sync_sub_action', 'prepare_delete_list_table');
			$this->local_sync_options->set_option('sync_current_action', 'prepare_delete_list_table');

			$this->local_sync_options->set_this_current_action_step('processing');

			die_with_ls_signature(array(
				'success' =>  true,
				'sync_sub_action' => $this->local_sync_options->get_option('sync_sub_action'),
				'sync_current_action' => $this->local_sync_options->get_option('sync_current_action'),
				'requires_next_call' => true
			));
		}

		public function restore_full_db_file(){

			local_sync_log('', "--------restore_full_db_file--------");

			$current_sync_unique_id = $this->local_sync_options->get_option('current_sync_unique_id');

			$full_db_file = WP_CONTENT_DIR . "/uploads/local_sync/backups/local_sync_full_db-backup-$current_sync_unique_id.sql.gz";

			if (!file_exists($full_db_file)) {
				$full_db_file = WP_CONTENT_DIR . "/uploads/local_sync/backups/local_sync_full_db-backup-$current_sync_unique_id.sql";
			}

			local_sync_log($full_db_file,'-----------$full_db_file----------------');

			$restore_full_db_process_completed = $this->local_sync_options->get_option('restore_full_db_process_completed');

			if (!empty($restore_full_db_process_completed)) {

				local_sync_log('', "--------restore_full_db_process-completed----so returning--and deleting the file-");

				local_sync_wait_for_sometime();
				if ($this->fs->exists($full_db_file)) {
					// $this->fs->delete($full_db_file);  //revisit
				}
				return ;
			}

			if (!$this->fs->exists($full_db_file)) {

				local_sync_log(array(), '-----------Full Sql db file not found in this restore-------------');

				$this->local_sync_options->set_option('restore_full_db_process_completed', true);
				$this->local_sync_options->set_option('restore_db_index', 0);

				die_with_ls_signature(array(
					'error' => 'DB file does not exists'
				));
			}

			$this->restore_sql_file_common($full_db_file, $type = 'full');

			die_with_ls_signature(array(
				'success' =>  true,
				'sync_sub_action' => $this->local_sync_options->get_option('sync_sub_action'),
				'sync_current_action' => $this->local_sync_options->get_option('sync_current_action'),
				'requires_next_call' => true
			));
		}

		private function restore_sql_file_common($restore_db_dump_file, $type ){
			local_sync_log(func_get_args(), "--------" . __FUNCTION__ . "--------");

			$this->enable_maintenance_mode();

			local_sync_manual_debug('', "start_" . basename($restore_db_dump_file) . "_db_restore");

			$db_restore_result = $this->database_restore($restore_db_dump_file);

			local_sync_log($db_restore_result,'-----------$db_restore_result----------------');

			local_sync_manual_debug('', "end_" . basename($restore_db_dump_file) . "_db_restore");

			if (!$db_restore_result) {
				$this->handle_restore_error_local_sync($this->local_sync_options);
				$err_obj = array();
				$err_obj['restore_db_dump_file'] = $restore_db_dump_file;
				$err_obj['mysql_error'] = $this->wpdb->last_error;
				$err = array('error' => $err_obj);

				$this->restore_app_functions->disable_maintenance_mode();

				// $this->restore_app_functions->send_report_data($this->restore_id, 'FAILED');

				die_with_ls_signature($err);
			}

			$this->local_sync_options->set_option('restore_full_db_process_completed', true);
			$this->local_sync_options->set_option('restore_db_index', 0);
			$this->local_sync_options->set_option('sql_gz_uncompression', false);
			$this->local_sync_options->set_option('restore_database_decrypted', false);

			$restore_db_dump_file = $this->restore_app_functions->remove_gz_ext_from_file($restore_db_dump_file);
			local_sync_wait_for_sometime();
			//delete the sql file then carryout the copying files process
			if ($this->fs->exists($restore_db_dump_file)) {
				@unlink($restore_db_dump_file);
				if ($this->fs->exists($restore_db_dump_file)) {
					// $this->fs->delete($restore_db_dump_file); //revisit
				}
			}
		}

		private function handle_restore_error_local_sync() {
			$this->local_sync_options->remove_garbage_files(array('is_restore' => true));
			$this->local_sync_options->set_option('restore_full_db_process_completed', true);
			$this->local_sync_options->set_option('restore_db_index', 0);
			$this->restore_complete('Restoring DB error.');
		}

		private	function database_restore($file_name) {
			local_sync_log(func_get_args(), "--------" . __FUNCTION__ . "--------");

			// $file_name = $this->decrypt($file_name);

			local_sync_log($file_name,'-----------$file_name after decrypt----------------');

			$file_name = $this->restore_app_functions->uncompress($file_name);

			$prev_index = $this->local_sync_options->get_option('restore_db_index');

			$this->restore_app_functions->set_local_sync_sql_mode_variables();
			$response = $this->restore_app_functions->import_sql_file($file_name, $prev_index);
			$this->restore_app_functions->reset_local_sync_sql_mode_variables();

			local_sync_log($response, '--------database_restore response--------');

			if (empty( $response ) || empty($response['status']) || $response['status'] === 'error') {
				$this->disable_maintenance_mode();
				// $this->restore_app_functions->send_report_data($this->restore_id, 'FAILED');
				$err = $response['status'] === 'error' ? $response['msg'] : 'Unknown error during database import';
				die_with_ls_signature(array('error' => $err));
			}

			if ($response['status'] === 'continue') {
				$this->local_sync_options->set_option('restore_db_index', $response['offset']); //updating the status in db for each 10 lines
				die_with_ls_signature(array(
					'success' =>  true,
					'offset' => $response['offset'],
					'sync_sub_action' => $this->local_sync_options->get_option('sync_sub_action'),
					'sync_current_action' => $this->local_sync_options->get_option('sync_current_action'),
					'requires_next_call' => true
				));
			}

			if ($response['status'] === 'completed') {
				return true;
			}

		}

		public function enable_maintenance_mode() {

			$path = $this->local_sync_options->wp_filesystem_safe_abspath_replace(ABSPATH);

			$path = local_sync_add_trailing_slash($path);

			$file = $path . '.maintenance';

			$content = '<?php global $upgrading; $upgrading = time();';

			if (file_exists($file)) {

				return ;
			}

			@file_put_contents($file, $content);
		}

		public function disable_maintenance_mode() {

			$path = $this->local_sync_options->wp_filesystem_safe_abspath_replace(ABSPATH);

			$path = local_sync_add_trailing_slash($path);

			$file = $path . '.maintenance';

			local_sync_wait_for_sometime();

			if (!is_file($file) && !file_exists($file)) {
				return ;
			}

			unlink($file);
		}

		public function include_file($file){
			if(!file_exists($file)){
				return false;
			}

			require_once $file;
		}

		public function initiate_filesystem() {
			$creds = request_filesystem_credentials("", "", false, false, null);
			if (false === $creds) {

				local_sync_log('', "--------initiate_filesystem--failed bridge------");

				return false;
			}

			if (!WP_Filesystem($creds)) {

				local_sync_log('', "--------initiate_filesystem--failed bridge--2----");

				return false;
			}

			$this->set_fs();
		}

		public function set_fs(){
			global $wp_filesystem;
			$this->fs = $wp_filesystem;

			return $this->fs;
		}

		public function fetch_files_to_be_deleted()	{
			$this->restore_app_functions->process_fetch_files_to_be_deleted();
		}

		public function update_the_file_list_to_be_deleted_and_delete() {
			$selected_files = $_POST['user_selected_files'] ?? array();

			$this->restore_app_functions->process_delete_files_list_update_then_delete($selected_files);
		}

		public function delete_all_files_in_delete_list() {
			$this->restore_app_functions->process_delete_all_files_in_delete_list();
		}

		public function process_get_steps_for_steps_parent_echo() {
			$steps_parent = $_POST['data']['steps_parent'];

			local_sync_log($_POST, "--------get_steps_for_steps_parent_echo----bridge----");

			$this->local_sync_options->get_steps_for_steps_parent_echo($steps_parent);
		}

	}

	local_sync_log($_REQUEST, "----bridge----_REQUEST--------");

	$obj = new Local_Sync_Bridge_Index();

	$prod_key_random_id_from_options_table = $obj->local_sync_options->get_option('prod_key_random_id');
	if( empty($_REQUEST) 
		|| empty($_REQUEST['prod_key_random_id']) 
		|| $_REQUEST['prod_key_random_id'] != $prod_key_random_id_from_options_table ){

		die_with_ls_signature(array(
			'error' => 'Not a valid prod key id.'
		));
	}

	if(!empty($_REQUEST) && $_REQUEST['action'] == 'sync_from_live_site'){
		$obj->choose_action();
	}

	if(!empty($_REQUEST) && $_REQUEST['action'] == 'fetch_files_to_be_deleted'){
		$obj->fetch_files_to_be_deleted();
	}

	if(!empty($_REQUEST) && $_REQUEST['action'] == 'delete_files_modal_ok'){
		$obj->update_the_file_list_to_be_deleted_and_delete();
	}

	if(!empty($_REQUEST) && $_REQUEST['action'] == 'delete_all_files_modal_ok'){
		$obj->delete_all_files_in_delete_list();
	}

	if(!empty($_REQUEST) && $_REQUEST['action'] == 'process_get_steps_for_steps_parent_echo'){
		$obj->process_get_steps_for_steps_parent_echo();
	}

	$site_type = $obj->local_sync_options->get_option('site_type');

?>

<!DOCTYPE html>
<html>
<head>
	<title>Local Sync Bridge</title>
	<link rel="stylesheet" href="./ls-boots.min.css">
	<link rel="stylesheet" href="./ls-dialog.min.css">
	<link rel="stylesheet" href="./admin/css/local-sync-admin.css">
	<style type="text/css">
		.user_del_file_single_losy{
		  margin-bottom: 10px;
		  cursor: pointer;
		}
	</style>
</head>
<body>
	<div id="choose_delete_files_modal_losy" style="display: none;">
		<div class="losy-parent-cont" style="height: auto; overflow-y: hidden; overflow-x: hidden; background: #fff;">
		    <div class="main-cols-losy">
		        <div class="m-box-losy">
		            <h2 class="hd"> Keep / Delete Extra files? </h2>
		            <div class="pad">
				        <em class="subtitle_modal_losy for_pulling"> We found few extra files in the local site, after pulling from live site. Do you want to keep them or delete them?</em>
				        <em class="subtitle_modal_losy for_pushing" style="display: none;"> We found few extra files in the live site, after pushing to live site. Do you want to keep them or delete them?</em>
					    <div class="modal_top_losy pad"></div>
		            </div>
		            <div class="pad modal_bottom_losy modal_bottoms_losy" style="border-top: 1px solid #e5e5e5;">
		            	<input  type="submit" class="delete_all_files_modal_cancel modal_button_losy" value="Cancel">
		            	<input type="submit" class="delete_files_modal_ok modal_button_losy delete_files_button_losy" value="Delete Selected Extra Files">
		            </div>
		            <div class="pad modal_bottom_losy_all modal_bottoms_losy" style="border-top: 1px solid #e5e5e5; display: none;">
				        <input  type="submit" class="delete_all_files_modal_cancel modal_button_losy" value="Keep All Extra Files">
		            	<input type="submit" class="delete_all_files_modal_ok modal_button_losy delete_files_button_losy"  value="Delete All Extra Files">
		            </div>
		        </div>
		    </div>
		</div>
	</div>

	<div style="margin-top: 20px; background: #f1f1f1; color: #444; font-family: 'Helvetica Neue',sans-serif; font-size: 13px; line-height: 1.4em;">
	    <div class="losy-main-cols-cont cf">
	        <div class="main-cols-losy">
	            <div class="process-steps-progress-losy steps-result-losy" style="padding: 10px 0 0 30px;">
	            </div>
	        </div>
			<div style="display: none;">
				<label>Result</label>
				<div class="sync_from_live_site_result"></div>
			</div>
	    </div>
    </div>

</body>
</html>
<script type="text/javascript">
	var site_type_losy_global = '<?php echo $site_type; ?>';
	var PROD_RANDOM_KEY_ID = '<?php echo $_REQUEST['prod_key_random_id']; ?>';
	var ajaxurl = 'index.php?prod_key_random_id='+PROD_RANDOM_KEY_ID;
</script>
<script type="text/javascript" src="./ls-bridge-jq.js"></script>
<script type="text/javascript" src="./ls-dialog.js"></script>
<script type="text/javascript" src="./index.js"></script>
