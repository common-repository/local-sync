<?php

class Local_Sync_Options {

	public $all_configs;
	public $files_to_be_skipped_during_full_copy;
	public $options_table_name;
	public $wpdb;
	public $fs;
	public $backup_dir;

	public function __construct() {
		global $wpdb;

		$this->wpdb = $wpdb;
		$this->all_configs = array();

		$this->files_to_be_skipped_during_full_copy = [
			'local_sync_deleted_files.txt',
			'local_sync_file_list_dump',
			'test.php',
		];
		$options_table_name = $this->wpdb->base_prefix . 'local_sync_options';
		$this->options_table_name = $options_table_name;

		if(is_local_sync_options_table_exists()){
			$query = "SELECT * FROM `$options_table_name`";

			$temp_all_configs = $this->wpdb->get_results($query, ARRAY_A);
			$all_configs = array();
			foreach ($temp_all_configs as $key => $value) {
				$all_configs[$value['name']] = $value['value'];
			}
			$this->all_configs = $all_configs;
		}
	}

	public function set_option($name, $value) {
		$result = $this->wpdb->replace($this->options_table_name, array(
			'name' => $name,
			'value' => $value
		));

		if($result === false){
			local_sync_log($name, "--------update_option-failed-------");
			local_sync_log($this->wpdb->last_error, "--------update_option-failed----last_error---");
			local_sync_log($this->wpdb->last_query, "--------update_option-failed----last_query---");
		} else {
			$this->all_configs[$name] = $value;
		}
	}

	public function get_option($name, $value = null) {
		$query = $this->wpdb->prepare("SELECT value FROM " . $this->options_table_name . " WHERE name=%s", $name);
		$value = $this->wpdb->get_var($query);

		return $value;
	}

	public function get_json_decoded_option($name, $value = null) {
		$query = $this->wpdb->prepare("SELECT value FROM " . $this->options_table_name . " WHERE name=%s", $name);
		$value = $this->wpdb->get_var($query);

		try{
			if(!empty($value)){
				return json_decode($value, true);
			} else {
				return array();
			}
		} catch(Exception $e){
			return array();
		}

		return $value;
	}

	private function init_fs(){

		if (!empty($this->fs)) {
			return ;
		}

		global $wp_filesystem;

		if ($wp_filesystem) {
			$this->fs = $wp_filesystem;
			return ;
		}

		if (!$wp_filesystem) {
			initiate_filesystem_local_sync();
			if (empty($wp_filesystem)) {
				send_response_local_sync('FS_INIT_FAILED-CONFIG');
			}
		}

		$this->fs = $wp_filesystem;
	}

	public function get_backup_dir($only_path = false) {
		$backup_db_path = $this->local_sync_get_tmp_dir();

		if (empty($backup_db_path)) {
			$this->choose_db_backup_path();
		}

		$backup_db_path = $this->local_sync_get_tmp_dir();

		if (empty($backup_db_path)) {
			return false;
		}

		if (!$only_path) {
			$path = wp_normalize_path($backup_db_path . '/'. LOCAL_SYNC_TEMP_DIR_BASENAME .'/backups');
		} else {
			$path = wp_normalize_path($backup_db_path);
		}

		return  local_sync_replace_abspath($path);
	}

	public static function get_default_backup_dir() {
		return wp_normalize_path(LOCAL_SYNC_WP_CONTENT_DIR . '/uploads');
	}

	private function set_paths_flags($path){
		$prev_path = $this->local_sync_get_tmp_dir($create = false);

		local_sync_log($prev_path, "--------set_paths_flags-res-------");

		if (!empty($prev_path) && $prev_path == $path) {
			return true;
		}

		$path = local_sync_remove_fullpath($path);

		$this->set_option('backup_db_path', $path);
		$this->set_option('site_abspath', LOCAL_SYNC_ABSPATH);
		$this->set_option('site_db_name', DB_NAME);
		$this->set_option('wp_content_dir', LOCAL_SYNC_WP_CONTENT_DIR);
		$this->set_option('is_wp_content_dir_moved_outside_root', $this->is_outside_content_dir());

		global $wpdb;
		$this->set_option('site_db_prefix', $wpdb->base_prefix);
	}

	public function choose_db_backup_path() {
		$dump_location = self::get_default_backup_dir();
		$dump_location_tmp = $dump_location . '/' . LOCAL_SYNC_TEMP_DIR_BASENAME . '/backups';

		if (file_exists($dump_location_tmp)) {
			$this->set_paths_flags($dump_location);

			return true;
		}

		$this->create_local_sync_files(); //revisit

		if (!file_exists($dump_location_tmp) || !is_writable($dump_location_tmp)) {

			local_sync_die_with_json_encode(array(
				'error' => true,
				'msg' => 'Not able to create backup folders',
			));

			return false;
		}

		$this->set_paths_flags($dump_location);
		return true;
	}

	public function create_local_sync_files(){

		$file_name = LOCAL_SYNC_UPLOADS_DIR . '/' . LOCAL_SYNC_TEMP_DIR_BASENAME . '/backups/test.php';

		// local_sync_log($file_name, "--------create_local_sync_files--------");

		if(file_exists($file_name)){

			return true;
		}

		if(!is_dir(LOCAL_SYNC_UPLOADS_DIR . '/' . LOCAL_SYNC_TEMP_DIR_BASENAME)){
			mkdir(LOCAL_SYNC_UPLOADS_DIR . '/' . LOCAL_SYNC_TEMP_DIR_BASENAME, 0755);
		}

		if(!is_dir(LOCAL_SYNC_UPLOADS_DIR . '/' . LOCAL_SYNC_TEMP_DIR_BASENAME . '/backups')){
			mkdir(LOCAL_SYNC_UPLOADS_DIR . '/' . LOCAL_SYNC_TEMP_DIR_BASENAME . '/backups', 0755);
		}

		file_put_contents($file_name, 'test');
		
		if (false === ($handle = fopen($file_name, 'w+'))) return false;

		fclose($handle);

		return true;
	}

	public function createRecursiveFileSystemFolder($this_temp_folder, $this_absbath_length = null, $override_abspath_check = true) {

		return true;
	}

	private function is_outside_content_dir(){
		return dirname(LOCAL_SYNC_ABSPATH) === dirname(LOCAL_SYNC_WP_CONTENT_DIR) ? true : false;
	}

	public function local_sync_get_tmp_dir($create = true){
		$backup_dir_path = $this->get_option('backup_db_path');

		if ( empty($backup_dir_path) ) {
			if ($create) {
				$this->choose_db_backup_path();
			} else {
				return false;
			}
		}

		$backup_dir_path = $this->get_option('backup_db_path');

		if (empty($backup_dir_path)) {
			return false;
		}

		$path = local_sync_add_fullpath($backup_dir_path);

		return $path;
	}

	public function wp_filesystem_safe_abspath_replace($file_path) {
		$file_path = trailingslashit($file_path);
		$this->init_fs();
		$safe_path = str_replace(LOCAL_SYNC_ABSPATH, wp_normalize_path($this->fs->abspath()), wp_normalize_path($file_path));

		return wp_normalize_path($safe_path);
	}

	public function remove_backup_dir_files($delete_local_file_list_dump = true){
		$this_temp_backup_folder = $this->get_backup_dir();
		$this->delete_files_of_this_folder($this_temp_backup_folder, array('is_restore' => true), false, $delete_local_file_list_dump);
	}

	public function remove_tmp_dir(){
		if(!defined('LOCAL_SYNC_TEMP_DIR_BASENAME')){
			define(LOCAL_SYNC_TEMP_DIR_BASENAME, 'local_sync');
		}
		$this_temp_backup_folder = $this->local_sync_get_tmp_dir() . '/' . LOCAL_SYNC_TEMP_DIR_BASENAME;
		$this_temp_backup_folder = $this->wp_filesystem_safe_abspath_replace($this_temp_backup_folder);
		$this->delete_files_of_this_folder($this_temp_backup_folder, array('is_restore' => true), false);
	}

	public function remove_old_bridge_folder(){
		$prod_key_random_id = $this->get_option('prod_key_random_id');
		$folder = ABSPATH . 'local_sync_bridge_' . $prod_key_random_id;
		$folder = $this->wp_filesystem_safe_abspath_replace($folder);
		$this->delete_files_of_this_folder($folder, array('is_restore' => true));
		$this->fs->delete($folder);
	}

	public function delete_files_of_this_folder($folder_name, $options = array('is_restore' => false), $delete_folder_also = true, $delete_local_file_list_dump = true) {
		$folder_name = trailingslashit($folder_name);

		// local_sync_log($folder_name, "----deleting----folder_name--------");

		$normalized_abspath = wp_normalize_path(ABSPATH);

		if(strlen($folder_name) < 10 || strlen($normalized_abspath) < 10){

			return;
		}

		if(stripos($folder_name, $normalized_abspath) === false){

			return;
		}

		if(stripos($folder_name, $normalized_abspath . 'wp-config.php') !== false){

			return;
		}

		$this->init_fs();

		if (!$this->fs->is_dir($folder_name)) {
			return;
		}

		$dirlist = $this->fs->dirlist($folder_name);
		$folder_name = trailingslashit($folder_name);

		if ($delete_folder_also && empty($dirlist)) {
			$this->fs->delete($folder_name);
			return;
		}

		$current_sync_unique_id = $this->get_option('current_sync_unique_id');

		foreach ($dirlist as $filename => $fileinfo) {
			if ('f' == $fileinfo['type']) {
				// $chmod_result = chmod($folder_name . $filename, 0644);

				// local_sync_log($chmod_result, "--------chmod_result--------");

				if(empty($delete_local_file_list_dump) && stripos($filename, "local_sync_file_list_dump-$current_sync_unique_id") !== false){
					continue;
				}

				// local_sync_log($folder_name . $filename, "------deleting----------");

				$this->fs->delete($folder_name . $filename);
				if (!empty($options['is_restore'])) {
					if(is_local_sync_timeout_cut()){

						local_sync_log($folder_name, "--------timeout during delete_files_of_this_folder--------");
						//revisit -> timeout cut
						die_with_ls_signature(array(
							'success' =>  true,
							'sync_sub_action' => 'delete_files_of_this_folder',
							'sync_current_action' => $this->get_option('sync_current_action'),
							'requires_next_call' => true
						));
					}
				} else if (!empty($options['is_backup'])) {
					if(is_local_sync_timeout_cut()){

						local_sync_log($folder_name, "--------timeout during delete_files_of_this_folder--------");

						local_sync_die_with_json_encode(array(
							'success' =>  true,
							'sync_sub_action' => 'delete_files_of_this_folder',
							'sync_current_action' => $this->get_option('sync_current_action'),
							'requires_next_call' => true
						));
					}
				}
			} elseif ('d' == $fileinfo['type']) {
				$this->delete_files_of_this_folder($folder_name . $filename);
				$this->delete_files_of_this_folder($folder_name . $filename); //second time to delete empty folders
			}
		}
	}

	public function tc_file_system_copy_dir($from, $to = '', $action = array('multicall_exit' => false)) {

		$from = trailingslashit($from);
		$to = trailingslashit($to);

		$this->init_fs();
		$dirlist = $this->fs->dirlist($from);

		foreach ((array) $dirlist as $filename => $fileinfo) {
			if (!empty($fileinfo) && 'f' == $fileinfo['type'] && $filename != '.htaccess') {
				if (!$this->tc_file_system_copy($from . $filename, $to . $filename, false, FS_CHMOD_FILE)) {
					$this->fs->chmod($to . $filename, 0644);
					if (!$this->tc_file_system_copy($from . $filename, $to . $filename, false, FS_CHMOD_FILE)) {
						return false;
					}
				}
				if ($action['multicall_exit'] == true) {
					if(is_local_sync_timeout_cut()){
						die(json_encode([
							'success' =>  true,
							'sync_sub_action' => 'tc_file_system_copy_dir',
							'sync_current_action' => $this->get_option('sync_current_action'),
							'requires_next_call' => true
						]));
					}
				}
			} elseif (!empty($fileinfo) && 'd' == $fileinfo['type']) {
				if (!$this->fs->is_dir($to . $filename)) {
					if (!$this->fs->mkdir($to . $filename, FS_CHMOD_DIR)) {
						return false;
					}
				}
				$result = $this->tc_file_system_copy_dir($from . $filename, $to . $filename, $action);
				if (!$result) {
					return false;
				}
			}
		}
		return true;
	}

	public function tc_file_system_move_dir($from, $to = '', $action = array('multicall_exit' => false)) {

		$from = trailingslashit($from);
		$to = trailingslashit($to);

		$this->init_fs();
		$dirlist = $this->fs->dirlist($from);

		foreach ((array) $dirlist as $filename => $fileinfo) {
			if ('f' == $fileinfo['type'] && $filename != '.htaccess') {

				// local_sync_log($filename, "----moving----filename--------");

				if (!$this->tc_file_system_move($from . $filename, $to . $filename, true, FS_CHMOD_FILE)) {

					local_sync_log($filename, "----moving----false--------");

					$this->fs->chmod($to . $filename, 0644);
					if (!$this->tc_file_system_move($from . $filename, $to . $filename, true, FS_CHMOD_FILE)) {
						return true;
					}
				}
				if ($action['multicall_exit'] == true) {
					if(is_local_sync_timeout_cut()){
						die(json_encode([
							'success' =>  true,
							'sync_sub_action' => 'tc_file_system_move_dir',
							'sync_current_action' => $this->get_option('sync_current_action'),
							'requires_next_call' => true
						]));
					}
				}
			} elseif ('d' == $fileinfo['type']) {
				// local_sync_log($filename, "----moving----dirname--------");

				if (!$this->fs->is_dir($to . $filename)) {
					if (!$this->fs->mkdir($to . $filename, FS_CHMOD_DIR)) {

						local_sync_log($filename, "----moving----false----mkdir----");

						return true;
					}
				}
				$result = $this->tc_file_system_move_dir($from . $filename, $to . $filename, $action);
				if (!$result) {

					local_sync_log($filename, "----moving----false----recdir----");

					return true;
				}
			}
		}
		return true;
	}

	public function tc_file_system_copy($source, $destination, $overwrite = false, $mode = 0644) {
		$this->init_fs();

		$copy_result = $this->fs->copy($source, $destination, $overwrite, $mode);

		if (!$copy_result && !$overwrite) {
			return true;
		}
		return $copy_result;
	}

	public function tc_file_system_move($source, $destination, $overwrite = false, $mode = 0644) {
		if(empty($this->backup_dir)){
			$this->backup_dir = rtrim($this->get_backup_dir(), '/');
		}

		$is_file_to_be_skipped = false;
		foreach($this->files_to_be_skipped_during_full_copy as $vv){
			if(stripos($source, $this->backup_dir . '/' . $vv) !== false){
				$is_file_to_be_skipped = true;
				break;
			}
		}

		if($is_file_to_be_skipped){
			return true;
		}

		if(stripos($source, $this->backup_dir . '/' . 'wp-config.php' !== false)){
			$current_sync_type = $this->get_option('sync_type_db_or_files');
			if(!empty($current_sync_type) && $current_sync_type == 'files_alone'){
				return true;
			}
		}
		
		local_sync_manual_debug('', 'during_moving_files', 500);

		$this->init_fs();

		$copy_result = $this->fs->move($source, $destination, $overwrite);

		if (!$copy_result && !$overwrite) {
			return true;
		}

		$copy_result = true;

		return $copy_result;
	}
	public function remove_garbage_files($options = array('is_restore' => false), $hard_reset = false) {
		try {
			return; //revisit
			local_sync_log(get_backtrace_string_local_sync(), "--------removing garbage files--------");

			$this->init_fs();

			$this_config_like_file = $this->wp_filesystem_safe_abspath_replace(LOCAL_SYNC_ABSPATH);
			$this_config_like_file = $this_config_like_file . 'config-like-file.php';

			if ($this->fs->exists($this_config_like_file)) {
				$this->fs->delete($this_config_like_file);
			}

			$this->remove_tmp_dir();

			if(!$this->get_option('is_staging_running')){
				$current_bridge_file_name = $this->get_option('current_bridge_file_name');
				if (!empty($current_bridge_file_name)) {
					$root_bridge_file_path = LOCAL_SYNC_ABSPATH . '/' . $current_bridge_file_name;
					$root_bridge_file_path = $this->wp_filesystem_safe_abspath_replace($root_bridge_file_path);
					$this->delete_files_of_this_folder($root_bridge_file_path, $options);
					$this->fs->delete($root_bridge_file_path);
				}
			}

			$this_backups = $this->wp_filesystem_safe_abspath_replace(LOCAL_SYNC_ABSPATH . '/backups');
			$this->delete_files_of_this_folder($this_backups, $options);
			$this->fs->delete($this_backups);

			$this->set_option('garbage_deleted', true);
			if (!$hard_reset) {
				// $this->send_restore_complete_status();
			}
		} catch (Exception $e) {
			local_sync_log(array(), "--------error --------");
		}
	}

	public function convert_bytes_to_mb($size){

		if (empty($size)) {
			return 0;
		}

		$size = trim($size);
		return ( ($size / 1024 ) / 1024 );
	}

	public function convert_mb_to_bytes($size){
		$size = trim($size);
		return $size * pow( 1024, 2 );
	}

	public function get_user_excluded_files_more_than_size(){
		$raw_settings = $this->get_option('user_excluded_files_more_than_size_settings');

		if (empty($raw_settings)) {
			return array(
				'status' => true,
				'size' => 50 * 1024 * 1024,
				'hr' => 50,
			);
		}

		$settings       = unserialize($raw_settings);
		$settings['hr'] = $this->convert_bytes_to_mb($settings['size']);
		return $settings;
	}

	public function get_load_images_from_live_site_echo(){
		if(!$this->is_feature_valid('images')){
			$this->set_option('load_images_from_live_site_settings', 'no');

			return  '
				<label class="disabled"><input name="unavailable_losy"  type="radio" class="unavailable_losy" disabled value="yes" />
					<span style="font-size:14px; opacity: 0.4;">Yes, load images directly from the Prod site.</span><br>
					<div style="background-color:#f5f1a6; opacity: 1; padding:10px; text-align:center; margin-top: 5px;"><a href="http://localsync.io" style="color:#0085ba;">Upgrade to PRO</a> to use this feature</div>
	                <em style="margin-left: 24px; display: block; padding-top: 2px; opacity: 0.4;"><strong>Recommended</strong>: Pulling will be faster since images won’t be downloaded and will be loaded directly from the Prod site URLs. Since image URLs don’t have to be replaced, pushing to prod will also be faster.
	                </em><br>
				</label>
				<label><input name="load_images_from_live_settings_radio_losy" type="radio" class="load_images_from_live_settings_radio_losy" checked value="no" />
					<span style="font-size:14px;">No, do not load images from Prod site.</span>
				</label><br>
	            <em style="margin-left: 24px; display: block; padding-top: 2px;">Pulling will be slower since images will be downloaded and linked from the local site. Pushing to Prod will also be slower since image URLs have to be replaced.</em>
			';
		}

		$settings = $this->get_option('load_images_from_live_site_settings');

		$yes = $settings === 'yes' ? 'checked' : '';
		$no  = $settings === 'no' ? 'checked' : '';

		if(empty($yes) && empty($no)){
			$no = 'checked';
		}

		return  '
			<label><input name="load_images_from_live_settings_radio_losy"  type="radio" class="load_images_from_live_settings_radio_losy" ' . $yes . ' value="yes" />
				<span style="font-size:14px;">Yes, load images directly from the Prod site.</span><br>
                <em style="margin-left: 24px; display: block; padding-top: 2px;"><strong>Recommended</strong>: Pulling will be faster since images won’t be downloaded and will be loaded directly from the Prod site URLs. Since image URLs don’t have to be replaced, pushing to prod will also be faster.
                </em><br>
			</label>
			<label><input name="load_images_from_live_settings_radio_losy" type="radio" class="load_images_from_live_settings_radio_losy" '. $no . ' value="no" />
				<span style="font-size:14px;">No, do not load images from Prod site.</span>
			</label><br>
            <em style="margin-left: 24px; display: block; padding-top: 2px;">Pulling will be slower since images will be downloaded and linked from the local site. Pushing to Prod will also be slower since image URLs have to be replaced.</em>
		';
	}

	public function is_feature_valid($feature_name=''){
		$prod_site_features = $this->get_option('prod_site_features');
		if(empty($prod_site_features)){
			return false;
		}

		if(!empty($prod_site_features)){
	        $prod_site_features = json_decode($prod_site_features);
	    }

	    if(!is_array($prod_site_features)){

	    	return false;
	    }

	    return in_array($feature_name, $prod_site_features);
	}

	public function get_sync_type_db_or_files_echo(){

		$settings = $this->get_option('sync_type_db_or_files');

		$db_alone = $settings === 'db_alone' ? 'checked' : '';
		$files_alone  = $settings === 'files_alone' ? 'checked' : '';
		$both  = $settings === 'both' ? 'checked' : '';

		if(empty($db_alone) && empty($files_alone)){
			$both = 'checked';
		}

		return '
			<label><input name="sync_type_db_or_files_losy"  type="radio" '.$db_alone.' class="sync_type_db_or_files_losy" value="db_alone" /><span>DB only</span></label>
			<label><input name="sync_type_db_or_files_losy" type="radio" '.$files_alone.' class="sync_type_db_or_files_losy" value="files_alone" /><span>Changed Files only</span></label>
			<label><input name="sync_type_db_or_files_losy" type="radio" '.$both.' class="sync_type_db_or_files_losy" value="both" /><span>Both DB+Changed Files</span></label>
		';
	}

	public function get_user_excluded_files_more_than_size_for_echo(){

		$settings = $this->get_user_excluded_files_more_than_size();


		$yes = !empty($settings['status']) ? 'checked' : '';

		return  '<input style="float: left; margin-top: 5px;" name="user_excluded_files_more_than_size_status"  type="checkbox" class="user_excluded_files_more_than_size_status" ' . $yes . ' />
		<span style="float: left; margin-right: 5px; margin-top: 5px;">Exclude files more than </span>
		<input class="losy-split-column" type="text" style="width: 70px; height: 30px; float: left;" name="user_excluded_files_more_than_size" id="user_excluded_files_more_than_size" placeholder="50" value='.  $settings['hr']  . '>
		<span style="margin-top: 5px; float: left;"> MB in size.</span> <div style="clear: both;"></div> ';
	}

	public function truncate_delete_list_table()	{
		$sql = "TRUNCATE TABLE {$this->wpdb->base_prefix}local_sync_delete_list";
		$response = $this->wpdb->query($sql);
	}

	public function truncate_current_process_table()	{
		$sql = "TRUNCATE TABLE {$this->wpdb->base_prefix}local_sync_current_process";
		$response = $this->wpdb->query($sql);
	}

	public function check_last_action_logic() {
		$last_action_running = $this->get_option('last_action_running');
		$last_action_start_time = $this->get_option('last_action_start_time');
		if(empty($last_action_running)){
			$this->set_option('last_action_running', true);
			$this->set_option('last_action_start_time', time());
		} else {
			if( (time() - $last_action_start_time) >= 180 ){
				$this->set_option('last_action_running', true);
				$this->set_option('last_action_start_time', time());
			} else {

				local_sync_log('', "--------last action not completed yet so waiting for 3 secs--------");

				sleep(21);
				die_with_ls_signature(array(
					'success' =>  true,
					'msg' => 'Last action not completed yet, so waited for 20 secs',
					'sync_sub_action' => $this->get_option('sync_sub_action'),
					'sync_current_action' => $this->get_option('sync_current_action'),
					'requires_next_call' => true
				));
			}
		}
	}

	public function set_this_pull_from_live_step($option = null, $value = '')	{
		$sync_current_action = $this->get_option('sync_current_action');

		if(empty($sync_current_action)){
			return;
		}

		$pull_from_live_steps = $this->get_option('pull_from_live_steps');
		$pull_from_live_steps = json_decode($pull_from_live_steps, true);

		$pull_from_live_steps[$sync_current_action] = $value;
		$this->set_option('pull_from_live_steps', json_encode($pull_from_live_steps));
	}

	public function set_this_current_action_step($value = '') {
		$sync_current_action = $this->get_option('sync_current_action');

		if(empty($sync_current_action)){
			return;
		}

		$pull_from_live_steps = $this->get_option('pull_from_live_steps');
		$pull_from_live_steps = json_decode($pull_from_live_steps, true);

		$pull_from_live_steps[$sync_current_action] = $value;
		$this->set_option('pull_from_live_steps', json_encode($pull_from_live_steps));
	}

	public function set_this_step_by_name($step_name = '', $value = '') {
		$pull_from_live_steps = $this->get_option('pull_from_live_steps');
		$pull_from_live_steps = json_decode($pull_from_live_steps, true);

		$pull_from_live_steps[$step_name] = $value;
		$this->set_option('pull_from_live_steps', json_encode($pull_from_live_steps));
	}

	public function get_steps_for_steps_parent_echo($steps_parent) {
		$sync_type_db_or_files = $this->get_option('sync_type_db_or_files');

		switch ($steps_parent) {
			case 'sync_from_live_site':

					$this_arr = array(
						'header' => 'Pulling from Live Site ',
						'file_list_preparation_for_local_dump' => 'Preparing this site files...',
						'start_db_dump_local_file_list' => 'Dumping this site files list...',
						'upload_local_file_list_dump' => 'Uploading the files list SQL dump...',
						'start_db_dump' => 'Preparing DB dump of the live site...',
						'start_file_list_preparation' => 'Preparing files list of the live site...',
						'import_local_file_list_dump' => 'Import this site\'s files list on the live site...',
						'process_file_list_difference' => 'Finding modified files...',
						'send_batch_download_requests' => 'Downloading modified files...',
						'copy_files_from_bridge' => 'Copying Modified Files...',
						'prepare_delete_list_table' => 'Preparing the list of files to be deleted...',
						'db_dump_restore' => 'Restoring the DB...',
						'delete_files_during_restore' => 'Deleting the selected files...',
					);

					if($sync_type_db_or_files == 'db_alone'){
						$this_arr = array(
							'header' => 'Pulling from Live Site ',
							'start_db_dump' => 'Preparing DB dump of the live site...',
							'send_batch_download_requests' => 'Downloading DB dump...',
							'copy_files_from_bridge' => 'Copying DB dump file...',
							'db_dump_restore' => 'Restoring the DB...',
						);
					} elseif($sync_type_db_or_files == 'files_alone'){
						$this_arr = array(
							'header' => 'Pulling from Live Site ',
							'file_list_preparation_for_local_dump' => 'Preparing this site files...',
							'start_db_dump_local_file_list' => 'Dumping this site files list...',
							'upload_local_file_list_dump' => 'Uploading the files list SQL dump...',
							'start_file_list_preparation' => 'Preparing files list of the live site...',
							'import_local_file_list_dump' => 'Import this site\'s files list on the live site...',
							'process_file_list_difference' => 'Finding modified files...',
							'send_batch_download_requests' => 'Downloading modified files...',
							'copy_files_from_bridge' => 'Copying Modified Files...',
							'prepare_delete_list_table' => 'Preparing the list of files to be deleted...',
							'delete_files_during_restore' => 'Deleting the selected files...',
						);
					}

					if(!$this->is_feature_valid('filesDiff')){
						unset($this_arr['file_list_preparation_for_local_dump']);
						unset($this_arr['start_db_dump_local_file_list']);
						unset($this_arr['upload_local_file_list_dump']);
						unset($this_arr['import_local_file_list_dump']);
						unset($this_arr['process_file_list_difference']);
						unset($this_arr['delete_files_during_restore']);
					}

					local_sync_die_with_json_encode(array(
						'steps' => $this_arr,
					));
				break;
			case 'push_to_live_site':
					$this_arr = array(
						'header' => 'Pushing to Live Site ',
						'file_list_preparation_for_local_dump' => 'Preparing live site files list...',
						'start_db_dump_local_file_list' => 'Dumping live site files list...',
						'download_local_file_list_dump' => 'Downloading the live site files list SQL dump...',
						'start_db_dump' => 'Preparing DB dump of this site...',
						'start_file_list_preparation' => 'Preparing files list of this site...',
						'import_local_file_list_dump' => 'Import live site\'s files list on this site...',
						'process_file_list_difference' => 'Finding modified files...',
						'batch_upload_files' => 'Uploading modified files...',
						'copy_files_from_live_bridge' => 'Copying modified files...',
						'prepare_delete_list_table' => 'Preparing the list of files to be deleted...',
						'db_dump_restore' => 'Restoring the DB...',
						'delete_files_during_restore' => 'Deleting the selected files...',
					);

					if($sync_type_db_or_files == 'db_alone'){
						$this_arr = array(
							'header' => 'Pushing to Live Site ',
							'start_db_dump' => 'Preparing DB dump of this site...',
							'batch_upload_files' => 'Uploading DB dump...',
							'copy_files_from_live_bridge' => 'Copying DB dump file...',
							'db_dump_restore' => 'Restoring the DB...',
						);
					} elseif($sync_type_db_or_files == 'files_alone'){
						$this_arr = array(
							'header' => 'Pushing to Live Site ',
							'file_list_preparation_for_local_dump' => 'Preparing live site files list...',
							'start_db_dump_local_file_list' => 'Dumping live site files list...',
							'download_local_file_list_dump' => 'Downloading the live site files list SQL dump...',
							'start_file_list_preparation' => 'Preparing files list of this site...',
							'import_local_file_list_dump' => 'Import live site\'s files list on this site...',
							'process_file_list_difference' => 'Finding modified files...',
							'batch_upload_files' => 'Uploading modified files...',
							'copy_files_from_live_bridge' => 'Copying modified files...',
							'prepare_delete_list_table' => 'Preparing the list of files to be deleted...',
							'delete_files_during_restore' => 'Deleting the selected files...',
						);
					}

					if(!$this->is_feature_valid('filesDiff')){
						unset($this_arr['file_list_preparation_for_local_dump']);
						unset($this_arr['start_db_dump_local_file_list']);
						unset($this_arr['download_local_file_list_dump']);
						unset($this_arr['import_local_file_list_dump']);
						unset($this_arr['process_file_list_difference']);
						unset($this_arr['delete_files_during_restore']);
					}

					local_sync_die_with_json_encode(array(
						'steps' => $this_arr,
					));
				break;
			
			default:
				local_sync_die_with_json_encode(array(
					'error' => 'No proper steps variable.'
				));
				break;
		}
	}

}
