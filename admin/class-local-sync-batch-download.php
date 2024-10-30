<?php

class Local_Sync_Batch_Download {
	private $files_this_batch = [];
	private $files_data_this_batch = [];
	private $files_compressed_data_size_so_far = 0;
	private $queued_files_count = 0;

    public function __construct(){
		$this->local_sync_options = new Local_Sync_Options();
		// $this->exclude_option = new Local_Sync_Exclude_Option();
		$this->allowed_free_disk_space = 1024 * 1024 * 10; //10 MB
		$this->retry_allowed_http_status_codes = array(5, 6, 7);
		// $this->utils_base = new Local_Sync_Utils();
		$this->init_db();
    }

	public function init_db(){
		global $wpdb;
		$this->wpdb = $wpdb;
	}

    public function pull_and_save_current_batch_of_files(){
		$current_sync_unique_id = $this->local_sync_options->get_option('current_sync_unique_id');
		$URL = rtrim($this->local_sync_options->get_option('prod_site_url'), '/') . '/index.php';

		$bridge_fs_obj = new LocalSyncFileSystem();
		$download_result = $bridge_fs_obj->download_files_using_curl($URL);

		if(empty($download_result)){

			local_sync_log($bridge_fs_obj->last_error, "--------pull_and_save_current_batch_of_file---error-----");

			$this->local_sync_options->set_this_current_action_step('error');

			local_sync_die_with_json_encode(array(
				'error' =>  $bridge_fs_obj->last_error,
				'sync_sub_action' => $this->local_sync_options->get_option('sync_sub_action'),
				'sync_current_action' => $this->local_sync_options->get_option('sync_current_action'),
				'requires_next_call' => false
			));
		}

        // local_sync_log($download_result, "--------download_result-----pull_and_save_current_batch_of_file---");

		$this->local_sync_files_op = new Local_Sync_Files_Op();
		if(!empty($download_result['is_a_big_file'])){
			local_sync_log($download_result['start_range'], "--------saving a big file----start_range----");
			$this->local_sync_files_op->save_files_batch($download_result['files_data'], $download_result['start_range']);
			
		} else {
			$this->local_sync_files_op->save_files_batch($download_result['files_data']);
		}

		return $download_result;
	}

    public function send_batch_download_requests() {
		try{
			local_sync_log('', "--------send_batch_download_requests---start-----");

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

			$this_start_time = time();

			local_sync_manual_debug('', 'before_downloading_files_request');

			$download_result = $this->pull_and_save_current_batch_of_files();

			local_sync_manual_debug('', 'after_downloading_files_request');

			$this_time_diff = time() - $this_start_time;
			$site_type = $this->local_sync_options->get_option('site_type');

			if(!empty($download_result['is_completed'])){

				$this->local_sync_options->set_this_current_action_step('done');

				$this->local_sync_options->set_option('sync_sub_action', 'initiate_bridge_files');
				$this->local_sync_options->set_option('sync_current_action', 'initiate_bridge_files');
			}
	
			local_sync_die_with_json_encode(array(
				'success' =>  true,
				'msg' =>  'save_files_batch_success',
				'sync_sub_action' => $this->local_sync_options->get_option('sync_sub_action'),
				'sync_current_action' => $this->local_sync_options->get_option('sync_current_action'),
				'queued_files_count' => $download_result['queued_files_count'] ?? 0,
				'requires_next_call' => true
			));

		} catch(Exception $e){
			local_sync_log($e->getMessage(), "--------send_batch_download_requests----exception----");
		}
	}

	public function add_file_data_to_current_batch($vv){
		if(empty($this->files_data_this_batch)){
			$this->files_data_this_batch = [];
		}

		$base64_filename = base64_encode($vv->add_as);
		$this->files_data_this_batch[$base64_filename] = $this->get_single_batch_file_data($vv->file_full_path);
	}

	public function get_current_batch_of_small_files(){
		if(empty($this->files_this_batch)){

			return false;
		}

		$files_data = [];
		foreach($this->files_this_batch as $vv){
			$base64_filename = base64_encode($vv->add_as);
			$files_data[$base64_filename] = $this->get_single_batch_file_data($vv->file_full_path);
		}

		return $files_data;
	}

	public function get_single_batch_file_data($file_name, $startRange = 0, $add_as = ''){

		// local_sync_log($file_name, "--------get_single_batch_file_data--file_name------");

		$total_file_size = filesize($file_name);
		$endRange = $total_file_size;

		if(empty($total_file_size)){

			return '';
		}

		$fp = fopen($file_name, 'rb');

		$currentOffest = $startRange;
		@fseek($fp, $currentOffest, SEEK_SET);
		$file_data = @fread($fp, LOCAL_SYNC_DOWNLOAD_CHUNK_SIZE);

		if(empty($file_data)){
			local_sync_log('', "--------empty file data for get_single_batch_file_data------");

			return '';
		}

		if(!empty($fp)){
			fclose($fp);
		}
		
		$endRange = $startRange + LOCAL_SYNC_DOWNLOAD_CHUNK_SIZE;

		$newEndRange = ($endRange > $total_file_size) ? $total_file_size : $endRange;

		$gz_encoded_file_data = gzcompress($file_data, 9);
		$file_data_enc = bin2hex($gz_encoded_file_data);

		$this->files_compressed_data_size_so_far += strlen($file_data_enc);

		if(empty($file_data_enc)){
			local_sync_log('', "--------empty file bin data for get_single_batch_file_data------");
		}

		if(!empty($add_as) && $total_file_size > LOCAL_SYNC_DOWNLOAD_CHUNK_SIZE){
			$this->local_sync_files_op->update_current_process_prev_uploaded_size($add_as, $newEndRange);
			if($total_file_size == $newEndRange){
				$this->local_sync_files_op->update_current_file_status($add_as, 'P');
			}
		}

		return $file_data_enc;
	}

	public function get_single_big_file($file_obj) {
		$current_sync_unique_id = $this->local_sync_options->get_option('current_sync_unique_id');

		$URL = rtrim($this->local_sync_options->get_option('prod_site_url'), '/') . '/index.php';

		$prev_uploaded_size = $this->local_sync_files_op->get_prev_uploaded_size($file_obj->file_path);

		$files_data = [];
		$base64_filename = base64_encode($file_obj->add_as);
		$files_data[$base64_filename] = $this->get_single_batch_file_data($file_obj->file_full_path, $prev_uploaded_size, $file_obj->file_path);

		if(empty($files_data[$base64_filename])){
			$this->local_sync_files_op->update_current_file_status($file_obj->file_path, 'P');
		}

		$this->queued_files_count = $this->local_sync_files_op->get_queued_files_count();

		local_sync_manual_debug('', 'after_handle_batch_download_big_file');

		local_sync_die_with_json_encode(array(
			'success' =>  true,
			'is_a_big_file' =>  true,
			'start_range' =>  $prev_uploaded_size ?? 0,
			'sync_sub_action' => $this->local_sync_options->get_option('sync_sub_action'),
			'sync_current_action' => $this->local_sync_options->get_option('sync_current_action'),
			'files_data' => $files_data,
			'queued_files_count' => $this->queued_files_count,
			'requires_next_call' => true
		));

		return $files_data;
	}

    public function batch_download_files() {
		try{
			$current_sync_unique_id = $this->local_sync_options->get_option('current_sync_unique_id');

			local_sync_log('', "--------batch_download_files---start-----");

			$this->local_sync_files_op = new Local_Sync_Files_Op();
			$files_to_zip = $this->local_sync_files_op->get_limited_files_to_zip();

			local_sync_log(count($files_to_zip), "--------files_to_download--------");
			// local_sync_log($files_to_zip, "--------files_to_download--------");

			$can_continue = true;
			$is_completed = false;

			if(empty($files_to_zip)){
				local_sync_log('', "--------no files to be downloaded this time----empty----");

				$files_to_zip = array();
				$can_continue = false;
				$is_completed = true;
			}

			$files_size_so_far = 0;
			$this->files_compressed_data_size_so_far = 0;
			$files_to_zip_this_call = 0;
			$files_to_zip_this_time = 0;

			$this_start_time = time();

			local_sync_manual_debug('', 'before_handle_batch_download');

			do{

				local_sync_manual_debug('', 'during_batch_download_files', 1000);

				$files_status_completed = array();
				foreach ($files_to_zip as $kk => $file_obj) {
					$file_path = trim($file_obj->file_path, '/');
					$file_full_path = ABSPATH . $file_path;
					$add_as = $file_path;

					$file_obj->file_full_path = $file_full_path;
					$file_obj->add_as = $add_as;

					$this_file_exists = file_exists($file_full_path);
					$this_file_size = 0;
					if($this_file_exists){
						$this_file_size = filesize($file_full_path);
					}

					if( $this_file_exists 
						&& $this_file_size > LOCAL_SYNC_DOWNLOAD_CHUNK_SIZE 
						&& $files_to_zip_this_call == 0 ){

						local_sync_log($file_path, "--------spotted big file during batch upload first call so downloading it--------");

						$this->get_single_big_file($file_obj);

						$can_continue = false;
						break;
					}

					if( $this_file_exists
						&& $this_file_size > LOCAL_SYNC_DOWNLOAD_CHUNK_SIZE
						&& $files_to_zip_this_call != 0 ){

						local_sync_log($file_path, "--------spotted big file during batch upload so skipping it--------");

						// $can_continue = false;
						continue;
					}

					if($this_file_exists){
						$this->files_this_batch[] = $file_obj;
						$files_size_so_far = $files_size_so_far + $this_file_size;

						$this->add_file_data_to_current_batch($file_obj);
					}

					$files_to_zip_this_call++;
					$files_to_zip_this_time++;

					$files_status_completed[] = '"'.$file_obj->file_path.'"';

					// local_sync_log($files_size_so_far, "--------files_size_so_far--------");

					if( $this->files_compressed_data_size_so_far > LOCAL_SYNC_DOWNLOAD_CHUNK_SIZE ){
						$this_time_diff = time() - $this_start_time;
						local_sync_log($this_time_diff, "--------this_time_diff--before close------");

						local_sync_log($files_size_so_far, "--------files_size_so_far--reached---$this_time_diff---");
						local_sync_log($this->files_compressed_data_size_so_far, "--------this->files_compressed_data_size_so_far--reached---$this_time_diff---");
						local_sync_log($files_to_zip_this_time, "--------files_to_zip_this_time--reached------");

						$files_size_so_far = 0;
						$this->files_compressed_data_size_so_far = 0;
						$files_to_zip_this_time = 0;

						$can_continue = false;

						break;
					}

					if(is_local_sync_timeout_cut(false, 13)){
						$can_continue = false;

						$this_time_diff = time() - $this_start_time;

						local_sync_log('', "--------breaking batch download loop----$this_time_diff----");

						break;
					}
					$can_continue = false;
				}

				$this_time_diff = time() - $this_start_time;

				// local_sync_log($this_time_diff, "--------after batch files download--------");

				local_sync_manual_debug('', 'after_handle_batch_download');

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

					local_sync_log('', "--------empty files to batch download--------");

					$can_continue = false;
					$is_completed = true;
				}

			} while ($can_continue);

			// $files_data = $this->get_current_batch_of_small_files();
			$files_data = $this->files_data_this_batch;

			$this_time_diff = time() - $this_start_time;
			$site_type = $this->local_sync_options->get_option('site_type');

			if($is_completed){

				local_sync_log('', "--------downloading the files completed--------");

				$this->local_sync_options->set_this_current_action_step('done');

				if(empty($site_type) || $site_type == 'local'){
					$this->local_sync_options->set_option('sync_current_action', 'initiate_bridge_files');
					$this->local_sync_options->set_option('sync_sub_action', 'initiate_bridge_files');
					$this->local_sync_options->set_this_current_action_step('processing');
				} elseif($site_type == 'production'){
					$this->local_sync_options->set_option('sync_current_action', 'initiate_bridge_files');
					$this->local_sync_options->set_option('sync_sub_action', 'initiate_bridge_files');
					$this->local_sync_options->set_this_current_action_step('processing');
				}
			}

			local_sync_log('', "--------batch_upload_files sending response--------");

			$this->queued_files_count = $this->local_sync_files_op->get_queued_files_count();

			local_sync_die_with_json_encode(array(
				'success' =>  true,
				'sync_sub_action' => $this->local_sync_options->get_option('sync_sub_action'),
				'sync_current_action' => $this->local_sync_options->get_option('sync_current_action'),
				'files_data' => $files_data,
				'is_completed' => $is_completed,
				'queued_files_count' => $this->queued_files_count,
				'requires_next_call' => true
			));

		} catch(Exception $e){
			local_sync_log($e->getMessage(), "--------batch_download----exception----");
		}
	}

}

