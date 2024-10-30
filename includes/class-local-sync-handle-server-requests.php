<?php

class Local_Sync_Handle_Server_Requests {

	public function __construct() {
		$this->local_sync_options = new Local_Sync_Options();
		$this->app_functions = new Local_Sync_App_Functions();
		$this->request_data = array();
	}

	public function init() {
		$this->request_data = $this->decode_server_request();
		if(empty($this->request_data)){

			return false;
		}

		$site_type = $this->local_sync_options->get_option('site_type');
		if(empty($site_type)){
			local_sync_die_with_json_encode(array(
				'error' => true,
				'msg' => 'Local Sync not set up yet'
			));
		}

		global $local_sync_ajax_start_time;
		$local_sync_ajax_start_time = time();
		global $local_sync_profiling_start;
		$local_sync_profiling_start = time();
		global $iterator_files_count_this_call_losy;
		$iterator_files_count_this_call_losy = 0;

		if( !empty($this->request_data) 
			&& !empty($this->request_data['action']) ){

			$prod_key_random_id = $this->local_sync_options->get_option('prod_key_random_id');
			if( empty($prod_key_random_id) 
				|| empty($this->request_data['prod_key_random_id']) 
				|| $this->request_data['prod_key_random_id'] != $prod_key_random_id ){

				local_sync_log($this->request_data['action'], "--------no_prod_id_error_response-----$prod_key_random_id---");

				local_sync_die_with_json_encode(array(
					'error' =>  'Unauthorized live site request, prod id is not valid, reset the site and try again.',
					'msg' =>  'Unauthorized live site request, prod id is not valid, reset the site and try again.',
					'sync_sub_action' => $this->local_sync_options->get_option('sync_sub_action'),
					'sync_current_action' => $this->local_sync_options->get_option('sync_current_action'),
					'requires_next_call' => false
				));
			}

			if( !empty($this->request_data['current_sync_unique_id']) ){
				$this->local_sync_options->set_option('current_sync_unique_id', $this->request_data['current_sync_unique_id']);
			}

			if( !empty($this->request_data['away_sync_type_db_or_files']) ){
				$this->local_sync_options->set_option('sync_type_db_or_files', $this->request_data['away_sync_type_db_or_files']);
			}

			if( !empty($this->request_data['load_images_from_live_site_settings']) ){
				$this->local_sync_options->set_option('load_images_from_live_site_settings', $this->request_data['load_images_from_live_site_settings']);
			}
			
			switch ($this->request_data['action']) {
				case 'check_validity':
					$this->check_validity();
					
					break;
				case 'run_sync_to_local':
					$this->run_sync_to_local_server_request();
					
					break;
				case 'run_sync_from_local':
					$this->run_sync_from_local_server_request();
					
					break;
				case 'handle_upload_file':
					$this->handle_upload_file();
					
					break;
				case 'handle_batch_upload_files':
					$this->handle_batch_upload_files();
					
					break;
				case 'handle_upload_single_big_file':
					$this->handle_upload_single_big_file();
					
					break;
				case 'handle_upload_zip_file':
					$this->handle_upload_zip_file();
					
					break;
				case 'handle_batch_download_files':
					$this->handle_batch_download_files();
					
					break;
				case 'clear_temp_files_local_sync':
					if(defined('LOCAL_SYNC_DELETE_TEMP') && LOCAL_SYNC_DELETE_TEMP){
						$this->local_sync_options->remove_backup_dir_files();
						die(json_encode(array(
							'success' => 'clear_temp_files_local_sync_success'
						)));
					}
					
					break;
				case 'modified_files_selection':
					$this->process_modified_files_selection();
					
					break;
				case 'get_file_size':
					$this->get_file_size();
					
					break;
				case 'get_file_data':
					$this->get_file_data();
					
					break;
				
				default:
					
					break;
			}
		}

		die(json_encode(array(
			'success' =>  true,
			'message' => 'Post call ends, probably handled nothing'
		)));
	}

	private function decode_server_request(){
		global $HTTP_RAW_POST_DATA;
		$HTTP_RAW_POST_DATA_LOCAL = NULL;
		$HTTP_RAW_POST_DATA_LOCAL = file_get_contents('php://input');

		if(empty($HTTP_RAW_POST_DATA_LOCAL)){
			if (isset($HTTP_RAW_POST_DATA)) {
				$HTTP_RAW_POST_DATA_LOCAL = $HTTP_RAW_POST_DATA;
			}
		}

		// local_sync_log($HTTP_RAW_POST_DATA_LOCAL, "--------HTTP_RAW_POST_DATA_LOCAL----decode_server_request_ls-3333---");

		if(empty($HTTP_RAW_POST_DATA_LOCAL)){

			return false;
		}

		ob_start();
		$data = base64_decode($HTTP_RAW_POST_DATA_LOCAL);
		if ($data){
			$post_data_encoded = $data;
			$post_data = json_decode($post_data_encoded, true);

			$is_validated = false;
			$is_validated = $this->is_valid_local_sync_request($post_data);
			if(empty($is_validated)){

				// local_sync_log($post_data, "--------is_valid_ls_request--failed----for--");

				return false;
			}

			// local_sync_log($post_data, "--------HTTP_RAW_POST_DATA_LOCAL----decode_server_request_ls----");

			return $post_data;
		} else {
			$HTTP_RAW_POST_DATA =  $HTTP_RAW_POST_DATA_LOCAL;
		}
		ob_end_clean();
	}

	public function is_valid_local_sync_request($post_data)	{
		
		if( empty($post_data) || empty($post_data['is_local_sync']) ){

			return false;
		}

		return true;
	}

	public function start_zip_creation()	{

		$this->local_sync_options->set_option('sync_sub_action', 'zipping');

		$response = $this->app_functions->create_zip();

		local_sync_log($response, "-----start_zip_creation---response--------");
	}

	public function check_validity()	{
		$prod_key_from_local = $this->request_data['prod_key'];
		$prod_key = $this->local_sync_options->get_option('prod_key');

		if($prod_key_from_local == $prod_key){
			$this->app_functions->service_login(false);
		} else {
			local_sync_die_with_json_encode_simple(array(
				'error' =>  "Prod key doesn't match"
			));
		}
	}

	public function run_sync_to_local_server_request($first_call = false)	{
		if( !empty($this->request_data['first_call']) ){
			$this->local_sync_options->set_option('sync_current_action', 'start_db_dump');

			$this->local_sync_options->set_option('sync_file_list_current_action', false);
			$this->local_sync_options->set_option('got_files_list', 0);
			$this->local_sync_options->set_option('restore_db_index', 0);

			$this->local_sync_options->set_option('local_sync_db_backup_1_completed', false);
			$this->local_sync_options->set_option('local_sync_db_gz_1_completed', false);
			$this->local_sync_options->set_option('sql_gz_compression_offset_1', 0);
			$this->local_sync_options->set_option('local_sync_db_backup_2_completed', false);
			$this->local_sync_options->set_option('local_sync_db_gz_2_completed', false);
			$this->local_sync_options->set_option('sql_gz_compression_offset_2', 0);
			$this->local_sync_options->set_option('local_sync_db_un_gz_1_completed', false);
			$this->local_sync_options->set_option('local_sync_db_un_gz_2_completed', false);
			$this->local_sync_options->set_option('restore_full_db_process_completed', false);

			$this->local_sync_options->set_option('shell_db_dump_status', '');
			$this->local_sync_options->set_option('collected_tables_for_backups', false);
			$this->local_sync_options->set_option('collected_tables_for_backups_offset', 0);
			$this->local_sync_options->set_option('last_action_running', false);

			
			$this->local_sync_files_op = new Local_Sync_Files_Op();
			$this->local_sync_files_op->truncate_processed_iterator_table();
			$this->local_sync_options->truncate_current_process_table();
			$this->local_sync_files_op->truncate_local_site_file_list_table();
			$this->local_sync_options->truncate_delete_list_table();

			$this->local_sync_options->create_local_sync_files();

			//zip flags
			$this->local_sync_options->remove_backup_dir_files(false);
		}

		if( !empty($this->request_data['away_site_db_prefix']) ){
			$this->local_sync_options->set_option('away_site_db_prefix', $this->request_data['away_site_db_prefix']);
		}

		$this->local_sync_options->check_last_action_logic();

		$sync_current_action = $this->local_sync_options->get_option('sync_current_action');

		local_sync_log($sync_current_action, "--------sync_current_action----run_sync_to_local_server_request----");

		switch ($sync_current_action) {
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
			case 'zip_creation':
				$this->start_zip_creation();
				break;
			
			default:
				
				break;
		}
	}

	public function run_sync_from_local_server_request($first_call = false)	{
		if( !empty($this->request_data['sync_current_action']) ){
			$this->local_sync_options->set_option('sync_current_action', $this->request_data['sync_current_action']);
			$this->local_sync_options->set_option('sync_sub_action', $this->request_data['sync_current_action']);
		}

		if( !empty($this->request_data['first_call']) ){
			$this->local_sync_options->set_option('sync_current_action', 'file_list_preparation_for_local_dump');
			$this->local_sync_options->set_option('sync_sub_action', 'file_list_preparation_for_local_dump');

			$this->local_sync_options->set_option('sync_file_list_current_action', false);
			$this->local_sync_options->set_option('got_files_list', 0);
			$this->local_sync_options->set_option('restore_db_index', 0);
			
			$this->local_sync_options->set_option('delete_state_files_from_download_list_offset', 0);
			$this->local_sync_options->set_option('prepare_delete_list_table_offset', 0);
			$this->local_sync_options->set_option('download_current_result', json_encode(array()));
			$this->local_sync_options->set_option('upload_current_result', json_encode(array()));
			$this->local_sync_options->set_option('actions_time_taken', json_encode(array()));
			$this->local_sync_options->set_option('extract_multi_call_flags_losy', json_encode(array()));

			$this->local_sync_options->set_option('local_sync_db_backup_1_completed', false);
			$this->local_sync_options->set_option('local_sync_db_gz_1_completed', false);
			$this->local_sync_options->set_option('sql_gz_compression_offset_1', 0);
			$this->local_sync_options->set_option('local_sync_db_backup_2_completed', false);
			$this->local_sync_options->set_option('local_sync_db_gz_2_completed', false);
			$this->local_sync_options->set_option('sql_gz_compression_offset_2', 0);
			$this->local_sync_options->set_option('local_sync_db_un_gz_1_completed', false);
			$this->local_sync_options->set_option('local_sync_db_un_gz_2_completed', false);
			$this->local_sync_options->set_option('restore_full_db_process_completed', false);

			$this->local_sync_options->set_option('shell_db_dump_status', '');
			$this->local_sync_options->set_option('collected_tables_for_backups', false);
			$this->local_sync_options->set_option('collected_tables_for_backups_offset', 0);
			$this->local_sync_options->set_option('last_action_running', false);

			$this->local_sync_files_op = new Local_Sync_Files_Op();
			$this->local_sync_files_op->truncate_processed_iterator_table();
			$this->local_sync_options->truncate_current_process_table();
			$this->local_sync_files_op->truncate_local_site_file_list_table();
			$this->local_sync_options->truncate_delete_list_table();

			$this->local_sync_options->create_local_sync_files();

			$this->local_sync_options->remove_backup_dir_files();
		}

		if( !empty($this->request_data['is_away_site_multisite']) ){
			$this->local_sync_options->set_option('is_away_site_multisite', $this->request_data['is_away_site_multisite']);
		}
		if( !empty($this->request_data['away_site_id_current_site']) ){
			$this->local_sync_options->set_option('away_site_id_current_site', $this->request_data['away_site_id_current_site']);
		}
		if( !empty($this->request_data['away_blog_id_current_site']) ){
			$this->local_sync_options->set_option('away_blog_id_current_site', $this->request_data['away_blog_id_current_site']);
		}

		$this->local_sync_options->check_last_action_logic();

		if( !empty($this->request_data['prod_site_url']) ){
			$this->local_sync_options->set_option('prod_site_url', $this->request_data['prod_site_url']);
		}

		if( !empty($this->request_data['away_site_abspath']) ){
			$this->local_sync_options->set_option('away_site_abspath', $this->request_data['away_site_abspath']);
		}

		if( !empty($this->request_data['away_site_db_prefix']) ){
			$this->local_sync_options->set_option('away_site_db_prefix', $this->request_data['away_site_db_prefix']);
		}

		$sync_current_action = $this->local_sync_options->get_option('sync_current_action');

		local_sync_log($sync_current_action, "--------sync_current_action----run_sync_from_local_server_request----");

		switch ($sync_current_action) {
			case 'file_list_preparation_for_local_dump':
				$this->app_functions->start_file_list_preparation_for_local_dump();
				break;
			case 'start_db_dump_local_file_list':
				$this->app_functions->start_db_dump_local_file_list();
				break;
			case 'initiate_zip_extract':
				$this->app_functions->initiate_zip_extract();
				break;
			case 'initiate_bridge_files':
				$this->app_functions->initiate_bridge_files();
				break;
			
			default:
				
				break;
		}
	}

	public function handle_upload_file()	{
		$this->local_sync_options->set_option('sync_current_action', 'handle_upload_file');
		$this->local_sync_options->set_option('sync_sub_action', 'handle_upload_file');

		$this->local_sync_options->create_local_sync_files();

		$this->app_functions->save_local_site_file_list_dump_file($this->request_data['file_data'], $this->request_data['start_range'], $this->request_data['end_range']);

	}

	public function handle_upload_zip_file()	{
		$this->local_sync_options->set_option('sync_current_action', 'handle_upload_zip_file');
		$this->local_sync_options->set_option('sync_sub_action', 'handle_upload_zip_file');

		$this->local_sync_options->create_local_sync_files();

		$this->app_functions->save_full_zip_file($this->request_data['file_data'], $this->request_data['start_range'], $this->request_data['end_range']);
	}

	public function handle_upload_single_big_file()	{
		$this->local_sync_options->set_option('sync_current_action', 'handle_upload_single_big_file');
		$this->local_sync_options->set_option('sync_sub_action', 'handle_upload_single_big_file');

		$this->local_sync_options->create_local_sync_files();

		$this->local_sync_files_op = new Local_Sync_Files_Op();
		$this->local_sync_files_op->save_single_big_file($this->request_data['file_name'], $this->request_data['file_data'], $this->request_data['start_range'], $this->request_data['end_range']);

	}

	public function handle_batch_upload_files()	{
		$this->local_sync_options->set_option('sync_current_action', 'handle_batch_upload_files');
		$this->local_sync_options->set_option('sync_sub_action', 'handle_batch_upload_files');

		$this->local_sync_options->create_local_sync_files();

		$this->local_sync_files_op = new Local_Sync_Files_Op();
		$this->local_sync_files_op->save_files_batch($this->request_data['files_data']);

	}

	public function handle_batch_download_files()	{
		$this->local_sync_options->set_option('sync_current_action', 'handle_batch_download_files');
		$this->local_sync_options->set_option('sync_sub_action', 'handle_batch_download_files');

		$this->local_sync_options->create_local_sync_files();

		$this->batch_download_obj = new Local_Sync_Batch_Download();
		$response = $this->batch_download_obj->batch_download_files();

	}

	public function process_modified_files_selection()	{
		$un_selected_file_paths_str = $this->request_data['un_selected_file_paths_str'];

		if($un_selected_file_paths_str == 'dont_replace_any_file'){
			$this->local_sync_files_op = new Local_Sync_Files_Op();
			$this->local_sync_files_op->update_all_current_process_except_ls_backup_files();
		} else {
			$this->local_sync_files_op = new Local_Sync_Files_Op();
			$this->local_sync_files_op->update_current_process_by_file_paths_str($un_selected_file_paths_str);
		}

		local_sync_log($un_selected_file_paths_str, "--------un_selected_file_paths_str--------");

		local_sync_die_with_json_encode_simple(array(
			'success' =>  true
		));
	}

	public function get_file_size()	{
		$file_name = $this->request_data['file_name'];

		$file_name = rtrim($this->local_sync_options->get_backup_dir(), '/') . '/' . $file_name;

		local_sync_log($file_name, "--------get_file_size--file_name------");

		$file_size = filesize($file_name);

		local_sync_die_with_json_encode_simple(array(
			'success' =>  true,
			'file_size' => $file_size,
		));
	}

	public function get_file_data()	{
		$UPLOAD_CHUNK_SIZE = LOCAL_SYNC_UPLOAD_CHUNK_SIZE;

		$file_name = ABSPATH . $this->request_data['file_name'];
		$startRange = $this->request_data['startRange'];

		// $file_name = rtrim($this->local_sync_options->get_backup_dir(), '/') . '/' . $file_name;

		local_sync_log($file_name, "--------get_file_data--file_name------");

		$total_file_size = filesize($file_name);

		$fp = fopen($file_name, 'rb');

		$currentOffest = (empty($startRange)) ? 0 : $startRange;
		@fseek($fp, $currentOffest, SEEK_SET);
		$file_data = @fread($fp, $UPLOAD_CHUNK_SIZE);

		if(empty($file_data)){
			local_sync_log('', "--------empty file data for get_file_data------");
		}

		$file_data_enc = bin2hex($file_data);

		if(empty($file_data_enc)){
			local_sync_log('', "--------empty file bin data for get_file_data------");
		}

		local_sync_die_with_json_encode_simple(array(
			'success' =>  true,
			'total_file_size' => $total_file_size,
			'file_data' => $file_data_enc
		));
	}

}
