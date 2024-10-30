<?php

class Local_Sync_Shell_Dump {

	const WAIT_TIMEOUT = 600; //10 minutes
	const NOT_STARTED = 0;
	const COMPLETE = 1;
	const TABLE_COMPLETE = -1;
	const IN_PROGRESS = 2;

	public function __construct() {
		global $wpdb;

		$this->wpdb = $wpdb;
		$this->local_sync_options = new Local_Sync_Options();
		$this->exclude_class_obj = new Local_Sync_Exclude_Option();
		$this->db = $wpdb;
	}

	public function shell_db_dump(){

		if(!$this->is_shell_exec_available()){
			return 'failed';
		}

		$status = $this->local_sync_options->get_option('shell_db_dump_status');

		if ($status === 'failed' || $status === 'error') {
			return 'failed';
		}

		if ($status === 'completed') {
			return 'completed';
		}

		if ($status === 'running') {
			return $this->check_is_shell_db_dump_running();
		}

		local_sync_set_time_limit(0);
		$this->local_sync_options->set_option('shell_db_dump_status', 'running');
		return $this->backup_db_dump();
	}

	public function shell_db_dump_local_file_list(){

		if(!$this->is_shell_exec_available()){
			return 'failed';
		}

		$status = $this->local_sync_options->get_option('shell_db_dump_status');

		if ($status === 'failed' || $status === 'error') {
			return 'failed';
		}

		if ($status === 'completed') {
			return 'completed';
		}

		if ($status === 'running') {
			return $this->check_is_shell_db_dump_running();
		}

		local_sync_set_time_limit(0);
		$this->local_sync_options->set_option('shell_db_dump_status', 'running');
		return $this->backup_db_dump_local_file_list();
	}

	public function is_shell_exec_available() {
		if (in_array(strtolower(ini_get('safe_mode')), array('on', '1'), true) || (!function_exists('exec'))) {
			return false;
		}
		$disabled_functions = explode(',', ini_get('disable_functions'));
		$exec_enabled = !in_array('exec', $disabled_functions);

		return ($exec_enabled) ? true : false;
	}

	private function check_is_shell_db_dump_running($local_file_list_dump = false){
		if(empty($local_file_list_dump)){
			$file = $this->get_file();
		} else {
			$file = $this->get_file_local_file_list();
		}

		if ( !file_exists($file) || !is_file($file) ) {
			$this->local_sync_options->set_option('shell_db_dump_status', 'failed');

			return 'failed';
		}

		$filesize = @filesize($file);

		if ($filesize === false) {
			$this->local_sync_options->set_option('shell_db_dump_status', 'failed');
			return 'failed';
		}

		local_sync_log($filesize, '---------------$filesize-----------------');
		local_sync_log($this->local_sync_options->get_option('shell_db_dump_prev_size'), '---------------$prev-----------------');

		if ( $this->local_sync_options->get_option('shell_db_dump_prev_size') === false 
			 || $this->local_sync_options->get_option('shell_db_dump_prev_size') === null ) {
			$this->local_sync_options->set_option('shell_db_dump_prev_size', $filesize );

			return 'running';
		} else if($this->local_sync_options->get_option('shell_db_dump_prev_size') < $filesize){
			$this->local_sync_options->set_option('shell_db_dump_prev_size', $filesize );

			return 'running';
		} else {

			return 'failed';
		}

		$this->local_sync_options->set_option('shell_db_dump_status');
	}

	public function get_file() {
		$current_sync_unique_id = $this->local_sync_options->get_option('current_sync_unique_id');
		$file = rtrim($this->local_sync_options->get_backup_dir(), '/') . '/' . "local_sync_full_db-backup-".$current_sync_unique_id.".sql";

		local_sync_log($file, "--------get_file----file----");

		$files = glob($file . '*');

		if (isset($files[0])) {
			return $files[0];
		}

		// $prepared_file_name = $file . '.' . $this->secret(DB_NAME);
		$prepared_file_name = $file;

		return $prepared_file_name;
	}

	public function get_file_local_file_list() {
		$current_sync_unique_id = $this->local_sync_options->get_option('current_sync_unique_id');
		$file = rtrim($this->local_sync_options->get_backup_dir(), '/') . '/' . "local_sync_file_list_dump-$current_sync_unique_id.sql";

		local_sync_log($file, "--------get_file_local_file_list----file----");

		// $prepared_file_name = $file . '.' . $this->secret(DB_NAME);
		$prepared_file_name = $file;

		return $prepared_file_name;
	}

	public static function secret($data) {
		return hash_hmac('sha1', $data, uniqid(mt_rand(), true)) . '-ls-secret';
	}

	private function backup_db_dump() {
		$this->mysqldump_structure_only_tables();

		$this->mysqldump_full_tables();

		$file = $this->get_file();

		$filesize = local_sync_get_file_size($file);

		if ( $filesize === false || $filesize == 0 || !is_file($file)) {

			$this->local_sync_options->set_option('shell_db_dump_status', 'failed');

			if (file_exists($file)) {
				@unlink($file);
			}

			return 'failed';
		}

		$this->local_sync_options->set_option('shell_db_dump_status', 'completed');
		
		return 'do_not_continue';
	}

	private function backup_db_dump_local_file_list() {
		$this->mysqldump_local_file_list_table();

		$file = $this->get_file_local_file_list();

		$filesize = local_sync_get_file_size($file);

		if ( $filesize === false || $filesize == 0 || !is_file($file)) {

			$this->local_sync_options->set_option('shell_db_dump_status', 'failed');

			if (file_exists($file)) {
				@unlink($file);
			}

			return 'failed';
		}

		$this->local_sync_options->set_option('shell_db_dump_status', 'completed');
		
		return 'do_not_continue';
	}

	private function mysqldump_structure_only_tables(){
		$tables = $this->exclude_class_obj->get_all_included_tables($structure_only = true);

		if (empty($tables)) {
			return true;
		}

		$tables =  implode("\" \"",$tables);

		$this->exec_mysqldump($tables, $structure_only = '--no-data');
	}

	private function mysqldump_full_tables(){
		local_sync_log(func_get_args(), "--------" . __FUNCTION__ . "--------");

		$tables = $this->exclude_class_obj->get_all_included_tables();

		local_sync_log($tables,'-----------$tables----------------');

		if (empty($tables)) {
			return true;
		}

		$tables =  implode("\" \"",$tables);

		$this->exec_mysqldump($tables);
	}

	private function mysqldump_local_file_list_table(){
		local_sync_log(func_get_args(), "--------" . __FUNCTION__ . "--------");

		$tables = array(
			$this->db->base_prefix . 'local_sync_current_process'
		);

		$tables =  implode("\" \"",$tables);

		$this->exec_mysqldump($tables, '', true);
	}

	private function exec_mysqldump($tables, $structure_only = '', $local_file_list_dump = false){
		if(empty($local_file_list_dump)){
			$file 	 = $this->get_file();
		} else {
			$file 	 = $this->get_file_local_file_list();
		}

		$paths   = $this->check_mysql_paths();
		$brace   = (substr(PHP_OS, 0, 3) == 'WIN') ? '"' : '';

		$this->reset_last_backup_request_local_sync();

		$comments = '';
		if (file_exists($file) && filesize($file) > 0) {
			$comments = '--skip-comments'; //assume already comments are dumped
		}

		$command = $brace . $paths['mysqldump'] . $brace . ' --force ' . $comments . ' ' . $structure_only . ' --host="' . DB_HOST . '" --user="' . DB_USER . '" --password="' . DB_PASSWORD . '" --add-drop-table --skip-lock-tables --extended-insert=FALSE "' . DB_NAME . '" "' . $tables . '" --triggers=false >> ' . $brace . $file . $brace;

		local_sync_log($command, '---------------$command-----------------');
		
		return $this->local_sync_exec($command);
	}

	private function check_mysql_paths() {
		global $wpdb;
		$paths = array(
			'mysql' => '',
			'mysqldump' => ''
		);
		if (substr(PHP_OS, 0, 3) == 'WIN') {
			$mysql_install = $wpdb->get_row("SHOW VARIABLES LIKE 'basedir'");
			if ($mysql_install) {
				$install_path       = str_replace('\\', '/', $mysql_install->Value);
				$paths['mysql']     = $install_path . 'bin/mysql.exe';
				$paths['mysqldump'] = $install_path . 'bin/mysqldump.exe';
			} else {
				$paths['mysql']     = 'mysql.exe';
				$paths['mysqldump'] = 'mysqldump.exe';
			}
		} else {
			$paths['mysql'] = $this->local_sync_exec('which mysql', true);
			if (empty($paths['mysql']))
				$paths['mysql'] = 'mysql'; // try anyway

			$paths['mysqldump'] = $this->local_sync_exec('which mysqldump', true);
			if (empty($paths['mysqldump']))
				$paths['mysqldump'] = 'mysqldump'; // try anyway

		}
		return $paths;
	}

	private function local_sync_exec($command, $string = false, $rawreturn = false) {
		if ($command == '') {

			return false;
		}

		$log = @exec($command, $output, $return);

		local_sync_log($log, '---------------$log-----------------');
		local_sync_log($output, '---------------$output-----------------');

		if ($string) {

			return $log;
		}

		if ($rawreturn) {

			return $return;
		}

		return $return ? false : true;
	}

	public function reset_last_backup_request_local_sync(){
		$this->local_sync_options->set_option('last_backup_request', false);
	}

}
