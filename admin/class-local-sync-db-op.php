<?php

class Local_Sync_DB_Op {

	const WAIT_TIMEOUT = 600; //10 minutes
	const NOT_STARTED = 0;
	const COMPLETE = 1;
	const TABLE_COMPLETE = -1;
	const IN_PROGRESS = 2;
	const GROUP_INSERT_VALUES_COUNT = 29;

	private $temp,
			$db,
			$config,
			$exclude_class_obj,
			$app_functions,
			$select_query_limit,
			$processed_files,
			$total_tables_size = 0,
			$bulk_table_insert;

	public function __construct() {
		global $wpdb;

		$this->wpdb = $wpdb;
		$this->local_sync_options = new Local_Sync_Options();
		$this->exclude_class_obj = new Local_Sync_Exclude_Option();
		$this->db = $wpdb;

		$this->set_wait_timeout();
		$this->init_query_limit();
	}

	public function init() {

	}

	private function set_wait_timeout() {
		$this->wpdb->query("SET SESSION wait_timeout=" . self::WAIT_TIMEOUT);
	}

	private function init_query_limit(){
		if (!empty($this->select_query_limit)) {
			return $select_query_limit;
		}

		// $this->select_query_limit = $this->app_functions->get_backup_db_query_limit(); //revist

		$this->select_query_limit = 1000;

		return $this->select_query_limit;
	}

	public function backup_database(){

		$bef_time = time();

		$this->collect_tables_for_backup();

		$collect_tables_time_taken = time() - $bef_time;

		local_sync_log($collect_tables_time_taken, "--------collect_tables_time_taken--------");

		$dbStatus = $this->get_status();

		if (($dbStatus != self::NOT_STARTED) && ($dbStatus != self::IN_PROGRESS)) {

			local_sync_log('', "--------return by db status--------");

			return ;
		}

		if(defined('LOCAL_SYNC_SHELL_DB') && !LOCAL_SYNC_SHELL_DB){
			$status = 'failed';
		} else {
			$shell_obj = new Local_Sync_Shell_Dump();
			$status = $shell_obj->shell_db_dump();
		}

		local_sync_log($status, '---------------$status------backup_database_shell_status-----------');

		if ($status === 'failed') {

			if ($dbStatus == self::IN_PROGRESS) {
				// local_sync_log('', "--------Resuming SQL backu--------");
			} else {
				// local_sync_log('', "--------Starting SQL backup.--------");
			}

			try{
				$this->execute();
			}catch(Exception $e){
				local_sync_die_with_json_encode(array(
					'error' =>  true,
					'msg' => 'Caught error on PHP DB execute' . $e->getMessage(),
					'sync_sub_action' => $this->local_sync_options->get_option('sync_sub_action'),
					'sync_current_action' => $this->local_sync_options->get_option('sync_current_action'),
					'requires_next_call' => false
				), 0, true);
			}
			// $this->logger->log(__('SQL backup complete. Starting file backup.', 'ls'), 'backups', $backup_id);
			$this->local_sync_options->set_option('local_sync_db_backup_1_completed', true);

		}  else if ($status === 'running') {

			local_sync_log('', "--------Shell DB dump is running, wait for next request--------");

			local_sync_die_with_json_encode(array(
				'success' =>  true,
				'sync_sub_action' => $this->local_sync_options->get_option('sync_sub_action'),
				'sync_current_action' => $this->local_sync_options->get_option('sync_current_action'),
				'requires_next_call' => true
			), 0, true);

		} else if($status === 'do_not_continue'){

			// $this->logger->log(__('SQL backup complete. Starting file backup.', 'local_sync'), 'backups', $backup_id);
			
			$this->local_sync_options->set_option('local_sync_db_backup_1_completed', true);
			$this->complete_all_tables();

			local_sync_log(array(), '---------------database dump completed but wait for next call-----------------');

		}
	}

	public function get_table($name) {
		$single_table_result = $this->wpdb->get_results("SELECT * FROM {$this->wpdb->base_prefix}local_sync_processed_iterator WHERE name = '$name'");

		if (!empty($single_table_result)) {
			return $single_table_result[0];
		}
	}

	public function is_complete($name) {
		$table = $this->get_table($name);

		if ($table) {
			return $table->offset == -1;
		}

		return false;
	}

	public function update_iterator($table, $offset) {
		$table_name = "{$this->wpdb->base_prefix}local_sync_processed_iterator";
		$this->wpdb->replace($table_name, array(
			'name' => $table,
			'offset' => $offset,
		));
	}

	private function write_db_dump_header($is_local_file_list_table_alone = false) {

		if($this->local_sync_options->choose_db_backup_path() === false){
			$get_default_backup_dir = $this->local_sync_options->get_default_backup_dir();
			$msg = "A database backup cannot be created because WordPress does not have write access to $get_default_backup_dir, please ensure this directory has write access.";

			local_sync_log($msg, '--------write_db_dump_header------');

			return false;
		}

		$current_sync_unique_id = $this->local_sync_options->get_option('current_sync_unique_id');

		//clearing the db file for the first time by simple logic to clear all the contents of the file if it already exists;
		$db_file = rtrim($this->local_sync_options->get_backup_dir(), '/') . '/' . "local_sync_full_db-backup-$current_sync_unique_id.sql";
		if(!empty($is_local_file_list_table_alone)){
			$db_file = rtrim($this->local_sync_options->get_backup_dir(), '/') . '/' . "local_sync_file_list_dump-$current_sync_unique_id.sql";
		}

		local_sync_log($db_file,'-----------$db_file----------------');

		$fp = fopen($db_file, 'w');
		fwrite($fp, '');
		fclose($fp);

		$blog_time = strtotime(current_time('mysql'));

		$this->write_to_temp("-- Local Sync SQL Dump\n");
		$this->write_to_temp("-- Version " . LOCAL_SYNC_VERSION . "\n");
		$this->write_to_temp("-- https://localsync.com\n");
		$this->write_to_temp("-- Generation Time: " . date("F j, Y", $blog_time) . " at " . date("H:i", $blog_time) . "\n\n");
		$this->write_to_temp("
/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;\n\n");
		$this->write_to_temp("CREATE DATABASE IF NOT EXISTS `" . DB_NAME . "`;\n");
		$this->write_to_temp("USE `" . DB_NAME . "`;\n\n");

		$this->persist($is_local_file_list_table_alone);

		$this->update_iterator('header', -1);
	}

	private function write_to_temp($out) {
		if (!$this->temp) {
			$this->temp = fopen('php://memory', 'rw');
		}

		if (fwrite($this->temp, $out) === false) {
			throw new Exception('Sql Backup : Error writing to php://memory.');
		}
	}

	private function persist($is_local_file_list_table_alone = false) {

		$current_sync_unique_id = $this->local_sync_options->get_option('current_sync_unique_id');
		$file = rtrim($this->local_sync_options->get_backup_dir(), '/') . '/' . "local_sync_full_db-backup-$current_sync_unique_id.sql";
		if(!empty($is_local_file_list_table_alone)){
			$file = rtrim($this->local_sync_options->get_backup_dir(), '/') . '/' . "local_sync_file_list_dump-$current_sync_unique_id.sql";
		}

		if (file_exists($file)) {
			$fh = fopen($file, 'a');
		} else {
			$fh = fopen($file, 'w');
		}

		if (!$fh) {
			throw new Exception('Sql Backup : Error creating sql dump file.');
		}

		fseek($this->temp, 0);

		fwrite($fh, stream_get_contents($this->temp));

		if (!fclose($fh)) {
			throw new Exception(' Sql Backup : Error closing sql dump file.');
		}

		if (!fclose($this->temp)) {
			throw new Exception(' Sql Backup : Error closing php://memory.');
		}

		$this->temp = null;
	}

	public function execute($is_local_file_list_table_alone = false) {

		if (!$this->is_complete('header')) {
			$this->write_db_dump_header($is_local_file_list_table_alone);
		}

		$table_meta = $this->get_unfinished_table();

		while ( $table_meta ) {

			if ($table_meta->offset > 1) {

				local_sync_log($table_meta->offset, "--------Resuming table--------" . $table_meta->name);

			}

			$table_skip_status = $this->exclude_class_obj->is_excluded_table($table_meta->name);
			$this->backup_database_table($table_meta->name, $table_meta->offset, $table_skip_status, $is_local_file_list_table_alone);

			// local_sync_log($table_meta->name, "--------Processed table--------");

			$table_meta = $this->get_unfinished_table();
		}

		$this->write_to_temp("
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;
/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;\n\n");
		$blog_time = strtotime(current_time('mysql'));
		$this->write_to_temp("-- Dump completed on ". date("F j, Y", $blog_time) . " at " . date("H:i", $blog_time) );
		$this->persist($is_local_file_list_table_alone);
	}

	public function backup_database_table($table, $offset, $table_skip_status, $is_local_file_list_table_alone = false) {

		local_sync_manual_debug('', 'start_backup_' . $table);

		$db_error = 'Error while accessing database.';

		if( $is_local_file_list_table_alone && stripos($table, 'local_sync_current_process') === false ){
			$table_skip_status = 'table_excluded';
		} else {
			$table_skip_status = 'table_included';
		}

		if ($table_skip_status == 'table_excluded') {
			$this->update_iterator($table, -1); //Done

			return true;
		}

		if ($offset == 0) {
			$this->write_to_temp("\n--\n-- Table structure for table `$table`\n--\n\n");

			$table_creation_query = '';
			$table_creation_query .= "DROP TABLE IF EXISTS `$table`;";
			$table_creation_query .= "
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;\n";

			$table_create = $this->wpdb->get_row("SHOW CREATE TABLE `$table`", ARRAY_N);
			if ($table_create === false) {
				throw new Exception($db_error . ' (ERROR_3)');
			}

			$table_creation_query .= $table_create[1].";";
			$table_creation_query .= "\n/*!40101 SET character_set_client = @saved_cs_client */;\n\n";

			if ($table_skip_status !== 'content_excluded') {
				$table_creation_query .= "--\n-- Dumping data for table `$table`\n--\n";
				$table_creation_query .= "\nLOCK TABLES `$table` WRITE;\n";
				$table_creation_query .= "/*!40000 ALTER TABLE `$table` DISABLE KEYS */;";

			}

			$this->write_to_temp($table_creation_query . "\n");
		}

		if ( $table_skip_status === 'content_excluded' ) {
			$this->update_iterator($table, -1); //Done

			return true;
		}

		$row_count = $offset;
		$table_count = $this->wpdb->get_var("SELECT COUNT(*) FROM $table");
		$columns = $this->wpdb->get_results("SHOW COLUMNS IN `$table`", OBJECT_K);

		// local_sync_log($this->select_query_limit, "--------select_query_limit--------");

		if ($table_count != 0) {
			for ($i = $offset; $i < $table_count; $i = $i + $this->select_query_limit) {

				local_sync_manual_debug('', 'during_db_backup', 1000);

				$table_data = $this->wpdb->get_results("SELECT * FROM $table LIMIT " . $this->select_query_limit . " OFFSET $i", ARRAY_A);
				if ($table_data === false || !is_array($table_data[0])) {
					throw new Exception($db_error . ' (ERROR_4)');
				}

				// if($table == 'wp_posts'){
				// 	local_sync_log($table_data, "--------table_data--------");
				// }

				$table_data_count = count($table_data);

				local_sync_log($table_data_count, "--------table_data_count--------");

				$out = '';
				$inserted_count_local = 0;
				$last_record_modulus = false;
				foreach ($table_data as $key => $row) {
					$last_record_modulus = false;
					$data_out = $this->create_row_insert_statement($table, $row, $columns);
					$out .= $data_out;
					$row_count++;
					$inserted_count_local++;

					if($inserted_count_local == 1){
						$out = "INSERT INTO `$table` VALUES " . $out;
					}

					$current_rows_size = self::GROUP_INSERT_VALUES_COUNT;
					// if( strlen($out) >= (1024 * 900) ){
					if( true ){
						$last_record_modulus = true;

						$out = rtrim($out, ",") . ";\n" . "INSERT INTO `$table` VALUES ";
					}
				}

				$out = rtrim($out, "INSERT INTO `$table` VALUES ");
				$out = rtrim($out, ",");

				if(!$last_record_modulus){
					$out .= ";\n";
				}

				$this->write_to_temp($out);
				$this->persist($is_local_file_list_table_alone);

				// exit;

				local_sync_log($table . ' - ' . $row_count, '---------Table backing up-----------------------');

				if($this->is_backup_request_timeout($return = true, true)){
					$this->update_iterator($table, $row_count);
					$msg = 'Backing up table' . $table . ' (Offset : ' .$row_count . ')';

					local_sync_die_with_json_encode(array(
						'success' =>  true,
						'msg' => $msg,
						'sync_sub_action' => $this->local_sync_options->get_option('sync_sub_action'),
						'sync_current_action' => $this->local_sync_options->get_option('sync_current_action'),
						'requires_next_call' => true
					));
				}

				if ($row_count >= $table_count) {
					$this->update_iterator($table, -1); //Done
				} else {
					$this->update_iterator($table, $row_count);
				}
			}
		}
		$this->update_iterator($table, -1); //Done
		$this->write_to_temp("/*!40000 ALTER TABLE `$table` ENABLE KEYS */;\n");
		$this->write_to_temp("UNLOCK TABLES;\n");
		$this->persist($is_local_file_list_table_alone);
		return true;
	}

	protected function create_row_insert_statement( $table, array $row, array $columns = array()) {
		$values = $this->create_row_insert_values($row, $columns);
		$joined = join(',', $values);

		$sql    = "($joined),";

		// local_sync_log($sql, "--------joined sql--------");

		return $sql;
	}

	protected function create_row_insert_values($row, $columns) {
		$values = array();

		foreach ($row as $columnName => $value) {
			$type = $columns[$columnName]->Type;
			// If it should not be enclosed
			if ($value === null) {
				$values[] = 'null';
			} elseif (strpos($type, 'int') !== false
				|| strpos($type, 'float') !== false
				|| strpos($type, 'double') !== false
				|| strpos($type, 'decimal') !== false
				|| strpos($type, 'bool') !== false
			) {
				$values[] = $value;
			} else {
				$values[] = $this->quote_and_esc_sql($value);
			}
		}

		return $values;
	}

	/*
		there is a behavioural change in esc_sql() after WP-v4.8.3
		https://make.wordpress.org/core/2017/10/31/changed-behaviour-of-esc_sql-in-wordpress-4-8-3/
	*/
	private function quote_and_esc_sql($value){
		if ( $this->is_wp_version_greater_than_4_8_3() || method_exists($this->wpdb, 'remove_placeholder_escape') ) {
			return  "'" . $this->wpdb->remove_placeholder_escape( esc_sql( $value ) ) . "'";
		}

		return  "'" . esc_sql( $value ) . "'";
	}

	public function is_wp_version_greater_than_4_8_3(){
		//revisit
		return true;
		return version_compare($this->app_functions->get_wp_core_version(), '4.8.3', '>=');
	}

	public function get_unfinished_table(){

		$sql = "SELECT * FROM {$this->db->base_prefix}local_sync_processed_iterator WHERE `offset` != '-1' AND `name` NOT LIKE '%/%' ORDER BY `id` LIMIT 1";

		// local_sync_log($sql,'-----------$sql----------------');

		$result_obj = $this->db->get_results($sql);

		// local_sync_log($result_obj,'-----------$result_obj----get_unfinished_table------------');

		if (empty($result_obj[0])) {
			return false;
		}

		$result_obj[0]->offset = empty($result_obj[0]->offset) ? 0 : $result_obj[0]->offset ;

		return $result_obj[0];
	}

	public function backup_database_only_local_file_list(){

		$this->collect_tables_for_backup_local_file_list();

		$dbStatus = $this->get_status();

		if (($dbStatus != self::NOT_STARTED) && ($dbStatus != self::IN_PROGRESS)) {

			local_sync_log('', "--------return by db status----backup_database_only_local_file_list----");

			return ;
		}

		if(defined('LOCAL_SYNC_SHELL_DB') && !LOCAL_SYNC_SHELL_DB){
			$status = 'failed';
		} else {
			$shell_obj = new Local_Sync_Shell_Dump();
			$status = $shell_obj->shell_db_dump_local_file_list();
		}

		local_sync_log($status, '---------------$status------backup_database_only_local_file_list_shell_status-----------');

		if ($status === 'failed') {
			try{
				$this->execute(true);
			}catch(Exception $e){
				local_sync_die_with_json_encode(array(
					'error' =>  true,
					'msg' => 'Caught error on PHP DB execute' . $e->getMessage(),
					'sync_sub_action' => $this->local_sync_options->get_option('sync_sub_action'),
					'sync_current_action' => $this->local_sync_options->get_option('sync_current_action'),
					'requires_next_call' => false
				), 0, true);
			}

			// $this->logger->log(__('SQL backup complete. Starting file backup.', 'ls'), 'backups', $backup_id);

			$this->local_sync_options->set_option('local_sync_db_backup_2_completed', true);

		}  else if ($status === 'running') {

			local_sync_log(array(), '---------------database dump is running---needs next call--------------');
			
			send_response_local_sync('Shell DB dump is running, wait for next request');

		} else if($status === 'do_not_continue'){

			// $this->logger->log(__('SQL backup complete. Starting file backup.', 'local_sync'), 'backups', $backup_id);
			
			$this->local_sync_options->set_option('local_sync_db_backup_2_completed', true);
			$this->complete_all_tables();

			local_sync_log(array(), '---------------database dump completed but wait for next call-----------------');

			// send_response_local_sync('Shell DB dump is completed, continue from next request');
		}
	}

	public function complete_all_tables(){
		$update_all_tables = "UPDATE " . $this->db->base_prefix . "local_sync_processed_iterator SET `offset` = '-1' WHERE `name` NOT LIKE '%/%'";

		local_sync_log($update_all_tables, '---------------$update_all_tables-----------------');

		$result = $this->db->query($update_all_tables);

		local_sync_log($result, '---------------$result-----------------');
	}

	public function collect_tables_for_backup(){

		if($this->local_sync_options->get_option('collected_tables_for_backups')){

			local_sync_log('', "--------skipping collect_tables_for_backup--------");

			return ;
		}

		$tables = $this->exclude_class_obj->get_all_tables();

		local_sync_log($tables, "--------all_tabnles--------");

		if (empty($tables)) {
			return true;
		}

		$offset = $this->get_colllected_tables_offset();

		$counter = 0;

		foreach ($tables as $table) {

			if ($offset > $counter++) {

				local_sync_log('', "--------offser_greater_than_counter--------");

				continue;
			}

			$exclude_status = $this->exclude_class_obj->is_excluded_table($table);

			// local_sync_log($table, '---------------$table-----------------');
			// local_sync_log($exclude_status, '---------------$exclude_status-----------------');

			if ($exclude_status === 'table_excluded') {
				continue;
			}

			// local_sync_log($table , '---------------Table not completed-----------------');

			if (is_local_sync_table($table)) {

				local_sync_log('', "--------is_local_sync_table--so excluded------");

				$this->prepare_table_bulk_insert($table, -1);

				continue;
			}

			$this->prepare_table_bulk_insert($table, 0);

			if($this->is_backup_request_timeout($return = true, true)){
				$this->save_colllected_tables();
				$this->save_colllected_tables_size();
				$this->local_sync_options->set_option('collected_tables_for_backups_offset', $counter);

				local_sync_die_with_json_encode(array(
					'success' =>  true,
					'sync_sub_action' => $this->local_sync_options->get_option('sync_sub_action'),
					'sync_current_action' => $this->local_sync_options->get_option('sync_current_action'),
					'requires_next_call' => true
				));
			}
		}

		$this->local_sync_options->set_option('collected_tables_for_backups_offset', $counter);
		$this->save_colllected_tables();
		$this->save_colllected_tables_size();
		$this->local_sync_options->set_option('collected_tables_for_backups', true);
	}

	public function collect_tables_for_backup_local_file_list(){

		if($this->local_sync_options->get_option('collected_tables_for_backups')){

			local_sync_log('', "--------skipping collect_tables_for_backup_local_file_list--------");

			return ;
		}

		$this->save_colllected_table_local_file_list();

		$this->local_sync_options->set_option('collected_tables_for_backups', true);
	}

	private function is_already_inserted($table){
		$qry = "SELECT name FROM " . $this->db->base_prefix . "local_sync_processed_iterator WHERE name = '" . $table . "'";
		$is_already_inserted = $this->db->get_var($qry);

		// local_sync_log($is_already_inserted ,'-----------$is_already_inserted----------------');
		
		return $is_already_inserted;
	}

	private function save_colllected_tables_size(){
		$current_size = $this->local_sync_options->get_option('collected_tables_for_backups_size');
		$current_size = empty($current_size) ? 0 : $current_size;
		$current_size += $this->total_tables_size;

		$this->local_sync_options->set_option('collected_tables_for_backups_size', $current_size);
	}

	private function save_colllected_tables(){

		$sql = "INSERT IGNORE INTO " . $this->db->base_prefix . "local_sync_processed_iterator (id, `name`, `offset`) values " . $this->bulk_table_insert;

		$result = $this->db->query($sql);

		if($result === false){
			local_sync_log($sql, "--------save_colllected_tables--error------");
			local_sync_log($this->db->last_error, "--------save_colllected_tables--error------");
		}
	}

	private function save_colllected_table_local_file_list(){
		$table_name = $this->wpdb->base_prefix . 'local_sync_current_process';
		$insert_str = "(NULL, '$table_name', 0)";
		$sql = "insert into `" . $this->db->base_prefix . "local_sync_processed_iterator` (id, `name`, `offset`) values $insert_str";

		// local_sync_log($sql,'-----------$sql save_colllected_table_local_file_list----------------');

		$result = $this->db->query($sql);

		if($result === false){
			local_sync_log($sql, "--------save_colllected_table_local_file_list--error------");
		}

	}

	private function prepare_table_bulk_insert($table, $offset){

		// local_sync_log($table, "--------prepare_table_bulk_insert--------");

		if ($this->is_already_inserted($table)) {

			return ;
		}

		// local_sync_log($table, "--------prepare_table_bulk_insert--------");

		$this->bulk_table_insert .= empty($this->bulk_table_insert) ? "(" : ",(" ;
		$this->bulk_table_insert .= $this->db->prepare("NULL, %s, %d)", $table, $offset);

		$this->total_tables_size += $this->exclude_class_obj->get_table_size($table, $return = false);
	}

	private function get_colllected_tables_offset(){
		$offset = $this->local_sync_options->get_option('collected_tables_for_backups_offset');

		return empty($offset) ? 0 : $offset;
	}

	public function get_status() {

		if (local_sync_is_meta_data_backup()) {
			return self::IN_PROGRESS;
		}

		if ($this->count_complete() == 0) {
			return self::NOT_STARTED;
		}

		$count = $this->get_overall_tables();

		if ($this->count_complete() <= $count) {
			return self::IN_PROGRESS;
		}

		return self::COMPLETE;
	}

	public function get_overall_tables(){

		$tables = $this->exclude_class_obj->get_all_tables($override_meta = true);

		if (local_sync_is_meta_data_backup()) {
			$meta_tables = $this->get_meta_backup_tables();
			$tables = array_merge($tables, $meta_tables);
		}

		$count = 0;

		foreach ($tables as $table) {
			if ($this->exclude_class_obj->is_excluded_table($table) !== 'table_excluded') {
				$count ++;
			}
		}

		return $count;
	}

	public function count_complete() {
		$i = 0;

		$process_table_values = $this->db->get_results("SELECT * FROM {$this->db->base_prefix}local_sync_processed_iterator");

		foreach ($process_table_values as $table) {
			if ($table->offset == self::TABLE_COMPLETE) {
				$i++;
			}
		}

		return $i;
	}

	public function is_backup_request_timeout($return = false, $print_time = false) {
		global $local_sync_ajax_start_time;

		if ((time() - $local_sync_ajax_start_time) >= LOCAL_SYNC_TIMEOUT) {

			if ($return) return true;
		}

		if ($print_time) {
			// local_sync_log(time() - $local_sync_ajax_start_time, '------------I still have time--------------------');
		}

		return false;
	}

	public function gz_compress($type = 'full'){
		local_sync_log(func_get_args(), "--------" . __FUNCTION__ . "--------");

		if( !local_sync_function_exist('gzwrite') 
			|| !local_sync_function_exist('gzopen') 
			|| !local_sync_function_exist('gzclose') ){

			local_sync_log(array(), '--------ZGIP not available--------');

			if($type == 'full'){
				$this->local_sync_options->set_option('local_sync_db_gz_1_completed', true);
			} else {
				$this->local_sync_options->set_option('local_sync_db_gz_2_completed', true);
			}

			return ;
		}

		if($type == 'full'){
			$offset = $this->local_sync_options->get_option('sql_gz_compression_offset_1');
		} else {
			$offset = $this->local_sync_options->get_option('sql_gz_compression_offset_2');
		}
		$offset = empty($offset) ? 0 : $offset;

		local_sync_log($offset, '-----gz---$offset--------');

		$current_sync_unique_id = $this->local_sync_options->get_option('current_sync_unique_id');

		if($type == 'full'){
			$file = rtrim($this->local_sync_options->get_backup_dir(), '/') . '/' . "local_sync_full_db-backup-$current_sync_unique_id.sql";
		} else {
			$file = rtrim($this->local_sync_options->get_backup_dir(), '/') . '/' . "local_sync_file_list_dump-$current_sync_unique_id.sql";
		}

		local_sync_log($file, '-----gz---$file--------');

		if (!file_exists($file)) {
			if($type == 'full'){
				$this->local_sync_options->set_option('local_sync_db_gz_1_completed', true);
			} else {
				$this->local_sync_options->set_option('local_sync_db_gz_2_completed', true);
			}

			return ;
		}

		$this->gz_compress_file($file, $offset, 9, $type);
	}

	private function gz_compress_file($source, $offset, $level = 9, $type = 'full'){

		if (filesize($source) < 5 ) {
			local_sync_log(array(),'-----------FILE contains nothing so delete it and skip compression----------------');
			// @unlink($source); //revisit unlink

			return;
		}

		local_sync_log(func_get_args(), "--------" . __FUNCTION__ . "--------");
		local_sync_log(filesize($source),'-----------filesize($source)----------------');

		$dest = $source . '.gz';
		$mode = 'ab' . $level;

		$break = false;

		$fp_out = gzopen($dest, $mode);

		if (empty($fp_out)) {
			return false;
		}

		$fp_in = fopen($source,'rb');

		if (empty($fp_in)) {
			return false;
		}

		fseek($fp_in, $offset);

		while (!feof($fp_in)){

			gzwrite($fp_out, fread($fp_in, 1024 * 1024 * 5)); //read 5MB chunk

			local_sync_manual_debug('', 'during_compress_db', 10);

			if($this->is_backup_request_timeout($return = true)){
				$break = true;
				$offset = ftell($fp_in);
				break;
			}
		}

		fclose($fp_in);
		gzclose($fp_out);

		if ($break) {

			if($type == 'full'){
				$this->local_sync_options->set_option('sql_gz_compression_offset_1', $offset);
			} else {
				$this->local_sync_options->set_option('sql_gz_compression_offset_2', $offset);
			}

			local_sync_die_with_json_encode(array(
				'success' =>  true,
				'gz_offset' => $offset,
				'sync_sub_action' => $this->local_sync_options->get_option('sync_sub_action'),
				'sync_current_action' => $this->local_sync_options->get_option('sync_current_action'),
				'requires_next_call' => true
			), 0, true);
		}

		local_sync_log(array(), '---gz-----Done--------');

		if($type == 'full'){
			$this->local_sync_options->set_option('local_sync_db_gz_1_completed', true);
		} else {
			$this->local_sync_options->set_option('local_sync_db_gz_2_completed', true);
		}

		@unlink($source); //revisit unlink

		return ;
	}
}
