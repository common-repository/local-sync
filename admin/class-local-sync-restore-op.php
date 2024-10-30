<?php

/**
 * 
 */
#[AllowDynamicProperties]
class Local_Sync_Restore_Op
{

	const SECRET_HEAD = '<LOCAL_SYNC_START>';
	const SECRET_TAIL = '<LOCAL_SYNC_END>';

	private $is_multisite;
	private $multisite_config;
	
	public function __construct() {
		global $wpdb;

		$this->wpdb = $wpdb;
		$this->local_sync_options = new Local_Sync_Options();
		// $this->exclude_class_obj = new Local_Sync_Exclude_Option();

		$this->tempQueryCount=0;
	    $this->tempQuerySize=0;
	    $this->old_table_name='';

		$this->is_migration          = true;

		$this->is_multisite          = $this->local_sync_options->get_option('is_away_site_multisite') ? true : false;
		$this->away_site_id_current_site          = $this->local_sync_options->get_option('away_site_id_current_site') ? true : false;
		$this->away_blog_id_current_site          = $this->local_sync_options->get_option('away_blog_id_current_site') ? true : false;

		if ($this->is_multisite) {
			$this->multisite_config['base_prefix']    = $this->local_sync_options->get_option('away_site_db_prefix');
			$this->multisite_config['current_prefix'] = $this->local_sync_options->get_option('away_site_db_prefix');
			$this->multisite_config['upload_dir']     = $this->local_sync_options->get_option('restore_multisite_upload_dir');
		}

		local_sync_log($this->is_multisite,'-----------$this->is_multisite sql----------------');
		local_sync_log($this->multisite_config,'-----------$this->multisite_config sql----------------');

		$this->away_site_db_prefix = $this->local_sync_options->get_option('away_site_db_prefix');
		$this->live_db_prefix = $this->away_site_db_prefix;

		if(!defined('DB_PREFIX_LOCAL_SYNC')){
			define('DB_PREFIX_LOCAL_SYNC', $wpdb->base_prefix);
		}
		$this->site_db_prefix = DB_PREFIX_LOCAL_SYNC;

		local_sync_log($this->site_db_prefix,'-----------$this->site_db_prefix----------------');
	}

	public function prepare()	{
		$prod_key_random_id = $this->local_sync_options->get_option('prod_key_random_id');
		$this->local_sync_options->set_option('current_bridge_file_name', 'local_sync_bridge_' . $prod_key_random_id);
		$this->copy_bridge_files();
	}

	public function set_fs(){
		global $wp_filesystem;

		if (!$wp_filesystem) {
			initiate_filesystem_local_sync();
			if (!$wp_filesystem) {
				die_with_ls_signature( array('error' => 'Cannot initiate WordPress file system.') );
			}
		}

		$this->fs = $wp_filesystem;
	}

	public function define( $name, $value ) {
		if ( ! defined( $name ) ) {
			define( $name, $value );
		}
	}

	public function copy_bridge_files() {

		$this->set_fs();

		local_sync_set_time_limit(0);

		$this->local_sync_options->remove_old_bridge_folder();
		// $this->local_sync_options->remove_tmp_dir();

		$config_like_file = $this->create_config_file();

		$bridge_dir = $this->get_bridge_dir();

		if (!empty($bridge_dir['error'])) {
			local_sync_log('Failed to Copy Bridge files');

			return $bridge_dir;
		}

		//copy bridge folder
		$plugin_path_tc = $this->get_local_sync_plugin_dir();

		local_sync_log($plugin_path_tc, "--------plugin_path_tc----copy_bridge_files----");

		$plugin_bridge_file_path = trailingslashit($plugin_path_tc . 'local-sync-bridge');
		$copy_res = $this->local_sync_options->tc_file_system_copy_dir($plugin_bridge_file_path, $bridge_dir, array('multicall_exit' => true));

		if (!$copy_res) {
			local_sync_log('Failed to Copy Bridge files');
			return array('error' => 'Cannot copy Bridge Directory.');
		}

		$plugin_folders_to_copy = array('Classes', 'includes', 'admin');
		foreach ($plugin_folders_to_copy as $v) {
			$plugin_folder = trailingslashit($plugin_path_tc . $v);
			$bridge_dir_sub = trailingslashit($bridge_dir . $v);

			if (!$this->fs->is_dir($bridge_dir_sub)) {
				if (!$this->fs->mkdir($bridge_dir_sub, FS_CHMOD_DIR)) {
					local_sync_log('Failed to create bridge directory while restoring . Check your folder permissions');
					return array('error' => 'Cannot create Plugin Directory in bridge.');
				}
			}

			$copy_res = $this->local_sync_options->tc_file_system_copy_dir($plugin_folder, $bridge_dir_sub, array('multicall_exit' => true));
			if (!$copy_res) {
				local_sync_log('Failed to Copy Bridge files');
				return array('error' => 'Cannot copy Plugin Directory(' . $plugin_folder . ').');
			}
		}

		$files_other_than_bridge                              = array();
		$files_other_than_bridge['wp-tc-config.php']          = $config_like_file; //config-like-file which was prepared already
		$files_other_than_bridge['local-sync-debug.php']      = $plugin_path_tc . '/local-sync-debug.php';
		$files_other_than_bridge['local-sync-generic-functions.php']      = $plugin_path_tc . '/local-sync-generic-functions.php';
		$files_other_than_bridge['local-sync-constants.php']        = $plugin_path_tc . '/local-sync-constants.php';
		// $files_other_than_bridge['restore-progress-ajax.php'] = $plugin_path_tc . '/restore-progress-ajax.php';
		// $files_other_than_bridge['local-sync-monitor.js']           = $plugin_path_tc . '/Views/local-sync-monitor.js';
		$files_other_than_bridge['ls-bridge-jq.js']           = ABSPATH . '/wp-includes/js/jquery/jquery.js';

		if(LOCAL_SYNC_ENV != 'production'){
			$files_other_than_bridge['local-sync-env-parameters.php'] = $plugin_path_tc . '/local-sync-env-parameters.php';
		}

		foreach ($files_other_than_bridge as $key => $value) {
			$copy_result = $this->local_sync_options->tc_file_system_copy($value, $bridge_dir . $key, true);
			if (!$copy_result) {
				return array('error' => 'Cannot copy Bridge files(' . $value . ').');
			}
		}

		if ($this->fs->exists($config_like_file)) {
			$this->fs->delete($config_like_file);
		}

		local_sync_log('Bridge Files are prepared successfully');
		return true;
	}

	private function get_local_sync_plugin_dir(){
		$plugin_path_tc = $this->fs->wp_plugins_dir() . LOCAL_SYNC_PLUGIN_NAME;
		return trailingslashit($plugin_path_tc);
	}

	private function create_config_file(){
		$config_like_file = $this->create_config_like_file();
		if ($config_like_file) {
			return $config_like_file;
		}

		local_sync_log('Error Creating config like file.');
		return array('error' => 'Error Creating config like file.');
	}

	private function get_bridge_dir(){

		$bridge_dir = $this->fs->abspath() . $this->local_sync_options->get_option('current_bridge_file_name');

		local_sync_log($bridge_dir,'-----------$bridge_dir----------------');

		$bridge_dir = trailingslashit($bridge_dir);

		if ($this->fs->is_dir($bridge_dir)) {
			return $bridge_dir;
		}

		if ($this->fs->mkdir($bridge_dir, FS_CHMOD_DIR)) {
			return $bridge_dir;
		}

		local_sync_log('', 'Failed to create bridge directory while restoring . Check your folder permissions');

		return array('error' => 'Cannot create Bridge Directory in root.');
	}

	public function create_config_like_file() {

		global $wpdb;

		$base_prefix          = $wpdb->base_prefix;
		$uploads_dir          = LOCAL_SYNC_UPLOADS_DIR;
		$content_dir          = LOCAL_SYNC_WP_CONTENT_DIR;
		$plugin_dir           = LOCAL_SYNC_PLUGIN_DIR;
		$lang_dir             = WP_LANG_DIR;

		if(defined('FTP_CONTENT_DIR'))
			$ftp_content_dir      = FTP_CONTENT_DIR;

		if(defined('FTP_PLUGIN_DIR'))
			$ftp_plugin_dir       = FTP_PLUGIN_DIR;


		$contents_to_be_written = "
		<?php
		/** The name of the database for WordPress */
		if(!defined('DB_NAME'))
		define('DB_NAME', '" . DB_NAME . "');

		/** MySQL database username */
		if(!defined('DB_USER'))
		define('DB_USER', '" . DB_USER . "');

		/** MySQL database password */
		if(!defined('DB_PASSWORD'))
		define('DB_PASSWORD', '" . DB_PASSWORD . "');

		/** MySQL hostname */
		if(!defined('DB_HOST'))
		define('DB_HOST', '" . DB_HOST . "');

		/** Database Charset to use in creating database tables. */
		if(!defined('DB_CHARSET'))
		define('DB_CHARSET', '" . DB_CHARSET . "');

		/** The Database Collate type. Don't change this if in doubt. */
		if(!defined('DB_COLLATE'))
		define('DB_COLLATE', '" . DB_COLLATE . "');

		if(!defined('DB_PREFIX_LOCAL_SYNC'))
		define('DB_PREFIX_LOCAL_SYNC', '" . $base_prefix . "');

		if(!defined('LOCAL_SYNC_UPLOADS_DIR'))
		define('LOCAL_SYNC_UPLOADS_DIR', '" .  wp_normalize_path($uploads_dir) . "');

		if(!defined('LOCAL_SYNC_RELATIVE_UPLOADS_DIR'))
		define('LOCAL_SYNC_RELATIVE_UPLOADS_DIR', '" .  wp_normalize_path(LOCAL_SYNC_RELATIVE_UPLOADS_DIR) . "');

		if(!defined('BRIDGE_NAME_LOCAL_SYNC'))
		define('BRIDGE_NAME_LOCAL_SYNC', '" . $this->local_sync_options->get_option('current_bridge_file_name') . "');

		if (!defined('WP_MAX_MEMORY_LIMIT')) {
			define('WP_MAX_MEMORY_LIMIT', '256M');
		}

		if(!defined('WP_DEBUG'))
		define('WP_DEBUG', false);

		if(!defined('WP_DEBUG_DISPLAY'))
		define('WP_DEBUG_DISPLAY', false);

		if ( !defined('MINUTE_IN_SECONDS') )
		define('MINUTE_IN_SECONDS', 60);
		if ( !defined('HOUR_IN_SECONDS') )
		define('HOUR_IN_SECONDS', 60 * MINUTE_IN_SECONDS);
		if ( !defined('DAY_IN_SECONDS') )
		define('DAY_IN_SECONDS', 24 * HOUR_IN_SECONDS);
		if ( !defined('WEEK_IN_SECONDS') )
		define('WEEK_IN_SECONDS', 7 * DAY_IN_SECONDS);
		if ( !defined('YEAR_IN_SECONDS') )
		define('YEAR_IN_SECONDS', 365 * DAY_IN_SECONDS);



		/** Absolute path to the WordPress directory. */
		if ( !defined('ABSPATH') )
		define('ABSPATH',  wp_normalize_path(dirname(dirname(__FILE__)) . '/'));

		if ( !defined('WP_CONTENT_DIR') )
		define('WP_CONTENT_DIR',  wp_normalize_path('" . $content_dir . "'));

		if ( !defined('WP_LANG_DIR') )
		define('WP_LANG_DIR',  wp_normalize_path('" . $lang_dir . "'));

		if(!defined('WP_PLUGIN_DIR'))
		define('WP_PLUGIN_DIR', '" .  $plugin_dir . "');

			  ";

		if ($this->is_multisite) {
			$contents_to_be_written .= "
		define('MULTISITE', " . $this->is_multisite . ");
			";
		}

		if (defined('FS_METHOD')) {
			$contents_to_be_written .= "
		define('FS_METHOD', '" . FS_METHOD . "');
			";
		}
		if (defined('FTP_BASE')) {
			$contents_to_be_written .= "
		define('FTP_BASE', '" . FTP_BASE . "');
			";
		}
		if (defined('FTP_USER')) {
			$contents_to_be_written .= "
		define('FTP_USER', '" . FTP_USER . "');
			";
		}
		if (defined('FTP_PASS')) {
			$contents_to_be_written .= "
		define('FTP_PASS', '" . FTP_PASS . "');
			";
		}
		if (defined('FTP_HOST')) {
			$contents_to_be_written .= "
		define('FTP_HOST', '" . FTP_HOST . "');
			";
		}
		if (defined('FTP_SSL') && FTP_SSL) {
			$contents_to_be_written .= "
		define('FTP_SSL', '" . FTP_SSL . "');
			";
		}
		if (defined('FTP_CONTENT_DIR')) {
			$contents_to_be_written .= "
		define('FTP_CONTENT_DIR', '" . $ftp_content_dir . "');
			";
		}
		if (defined('FTP_PLUGIN_DIR')) {
			$contents_to_be_written .= "
		define('FTP_PLUGIN_DIR', '" . FTP_PLUGIN_DIR . "');
			";
		}
		if (defined('FTP_PUBKEY') && FTP_PUBKEY) {
			$contents_to_be_written .= "
		define('FTP_PUBKEY', '" . FTP_PUBKEY . "');
			";
		}
		if (defined('FTP_PRIKEY') && FTP_PRIKEY) {
			$contents_to_be_written .= "
		define('FTP_PRIKEY', '" . FTP_PRIKEY . "');
			";
		}

		$dump_dir = $this->local_sync_options->get_backup_dir();

		$dump_dir = $this->local_sync_options->wp_filesystem_safe_abspath_replace($dump_dir);

		$dump_dir_parent = trailingslashit(dirname($dump_dir));

		$config_like_file = $dump_dir_parent . 'config-like-file.php';

		$result = $this->fs->put_contents($config_like_file, $contents_to_be_written, 0644);

		if (!$result) {

			local_sync_log($config_like_file, "--------create_config_like_file---failed-----");

			return false;
		}

		return $config_like_file;
	}

	public function die_with_msg($msg, $option = false){
		if (!$option) {
			$json_encoded_msg = json_encode($msg);
		} else if($option === 'unescape_slashes'){
			$json_encoded_msg = json_encode($msg, JSON_UNESCAPED_SLASHES);
		}

		local_sync_log($msg, "--------die_with_msg--------");

		$msg_with_secret = self::SECRET_HEAD . $json_encoded_msg . self::SECRET_TAIL;
		die($msg_with_secret);
	}

	public function uncompress($file){
		if(!file_exists($file)){
			return $this->remove_gz_ext_from_file($file);			
		}

		//Return original sql file for normal sql file or compression completed file.
		if( strpos($file, '.gz') === false 
			|| $this->local_sync_options->get_option('local_sync_db_un_gz_1_completed') ){

			local_sync_log(array(), '--------Either compression done or file is not compressed--------');

			return $this->remove_gz_ext_from_file($file);
		}

		local_sync_log(array(), '---------------Uncompressing file-----------------');

		if ( !$this->is_gzip_available() ) {
			$this->local_sync_options->set_option('local_sync_db_un_gz_1_completed', true);

			die_with_ls_signature(array('error' => 'gzip not installed on this server so could not uncompress the sql file'));
		}

		local_sync_manual_debug('', 'start_uncompress_db');

		$this->gz_uncompress_file($file, $offset = 0);

		$this->local_sync_options->set_option('local_sync_db_un_gz_1_completed', true);

		return $this->remove_gz_ext_from_file($file);
	}

	public function uncompress_local_file_list_dump($file){
		if(!file_exists($file)){

			local_sync_log($file, "--------not exists during uncompress--------");

			return $this->remove_gz_ext_from_file($file);			
		}

		//Return original sql file for normal sql file or compression completed file.
		if( strpos($file, '.gz') === false 
			|| $this->local_sync_options->get_option('local_sync_db_un_gz_2_completed') ){

			local_sync_log(array(), '--------Either compression done or file is not compressed--------');

			return $this->remove_gz_ext_from_file($file);
		}

		local_sync_log(array(), '---------------Uncompressing file-----------------');

		$restore_app_functions = new Local_Sync_Restore_Op();
		if ( !$restore_app_functions->is_gzip_available() ) {
			$this->local_sync_options->set_option('local_sync_db_un_gz_2_completed', true);

			die_with_ls_signature(array('error' => 'gzip not installed on this server so could not uncompress the sql file'));
		}

		local_sync_manual_debug('', 'start_uncompress_db');

		$restore_app_functions->gz_uncompress_file($file, $offset = 0);

		$this->local_sync_options->set_option('local_sync_db_un_gz_2_completed', true);

		return $this->remove_gz_ext_from_file($file);
	}

	public function remove_gz_ext_from_file($file){

		local_sync_log($file, "--------remove_gz_ext_from_file--------");

		if (strstr($file, '.gz.crypt') !== false){
			return str_replace('.gz.crypt', '', $file);
		}

		if (strstr($file, '.gz') !== false){
			return str_replace('.gz', '', $file);
		}

		return $file;
	}

	public function gz_uncompress_file($source, $offset = 0){

		local_sync_log($source, "--------gz_uncompress_file--------");

		$dest =  str_replace('.gz', '', $source);

		$fp_in = gzopen($source, 'rb');

		if (empty($fp_in)) {

			local_sync_log(error_get_last(),'-----------error_get_last()---gz_uncompress_file-------------');

			chmod($source, 0644);

			$fp_in = gzopen($source, 'rb');

			if(empty($fp_in)){
				die_with_ls_signature(array('error' => "Cannot open gzfile to uncompress sql. Give 644 permission to the file $source and resume again."));
			}
			
		}

		$fp_out = ($offset === 0) ? fopen($dest, 'wb') : fopen($dest, 'ab');

		if (empty($fp_out)) {
			fclose($fp_out);
			die_with_ls_signature(array('error' => 'Cannot open temp file to uncompress sql'));
		}

		gzseek($fp_in, $offset);

		$emptimes = 0;

		while (!gzeof($fp_in)){

			$chunk_data = gzread($fp_in, 1024 * 1024 * 5); //read 5MB per chunk

			local_sync_log(strlen($chunk_data), '----------gz_uncompress_file-----strlen($chunk_data)-----------------');

			if (empty($chunk_data)) {

				$emptimes++;

				local_sync_log(array(), "---------gz_uncompress_file------Got empty gzread ($emptimes times)---------------");

				if ($emptimes > 3){
					die_with_ls_signature(array('error' => "Got empty gzread ($emptimes times). Give 644 permission to the file $source and resume again."));
				}

			} else {
				@fwrite($fp_out, $chunk_data);
			}

			local_sync_manual_debug('', 'during_uncompress_db', 2);

			$current_offset = gztell($fp_in);

			local_sync_log($current_offset, '---------------$current_offset-----------------');

			//Clearning to save memory
			unset($chunk_data);
		}

		fclose($fp_out);
		gzclose($fp_in);

		local_sync_log(array(), '--------Un compression done--------');

		@unlink($source);  //revisit

		local_sync_manual_debug('', 'end_uncompress_db');

		return $dest;
	}

	private function get_collation_replacement_status(){
		return $this->local_sync_options->get_option('replace_collation_for_this_restore');
	}

	public function set_local_sync_sql_mode_variables() {
		try{
			local_sync_log('', "--------altering foreign key mode--------");
			// $this->wpdb->query("SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0");
			$this->wpdb->query("SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=OFF;");
			local_sync_log($this->wpdb->last_error,'-----------$last_error----set_local_sync_sql_mode_variables------------');
			$this->wpdb->query("SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO';");
		} catch(Exception $e){
			local_sync_log($e->getMessage(), "--------set_local_sync_sql_mode_variables----exception----");
		}

	}

	public function reset_local_sync_sql_mode_variables() {
		try{
			local_sync_log('', "--------resetting foreign key mode--------");
			// $this->wpdb->query("SET UNIQUE_CHECKS=@@OLD_UNIQUE_CHECKS");
			$this->wpdb->query("SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS");
			$this->wpdb->query("SET SQL_MODE=@OLD_SQL_MODE");
		} catch(Exception $e){
			local_sync_log($e->getMessage(), "--------set_local_sync_sql_mode_variables----exception----");
		}
	}

	public function import_sql_file($file_name, $prev_index, $replace_collation = false, $is_local_file_list_dump = false){
		local_sync_log(func_get_args(), "--------------" . __FUNCTION__ . "------------------");

		if (!$replace_collation) {
			$replace_collation = $this->get_collation_replacement_status();
		}

		local_sync_log($replace_collation,'-----------$replace_collation----------------');

		$handle = fopen($file_name, "rb");

		if (empty($handle)) {
			return array('status' => 'error', 'msg' => 'Cannot open database file');
		}

		$this->init_necessary_things_migration();

		$prev_index = empty($prev_index) ? 0 : $prev_index;

		$current_query = '';
		$tempQuery = '';

		$this_lines_count = $loop_iteration = 0;

		while ( ( $line = fgets( $handle ) ) !== false ) {

			$loop_iteration++;

			if ($loop_iteration <= $prev_index ) {
				continue; //check index; if it is previously written ; then continue;
			}

			$this_lines_count++;

			if (substr($line, 0, 2) == '--' || $line == '' || substr($line, 0, 3) == '/*!') {
				continue; // Skip it if it's a comment
			}

			if (strpos($line, "'itsec_online_files_hashes',") > 0) {
				continue; // Skip it if it's itsec_online_files_hashes value
			}

			if (strpos($line, "'_transient_dirsize_cache',") > 0) {
				continue; // Skip it if it's _transient_dirsize_cache value
			}

			if (strpos($line, "'limit_login_logged',") > 0) {
				continue; // Skip it if it's limit_login_logged value
			}
			$current_query .= $line;

			// If it does not have a semicolon at the end, then it's not the end of the query
			if (substr(trim($line), -1, 1) != ';') {
				continue;
			}

			if (is_multisite()) {
				if($this->skip_this_query_for_multisite($current_query)){
					// local_sync_log(array(),'-----------Query skipped----------------');
					$current_query = '';
					continue;
				}
			}

			if ( $replace_collation ) {
				// local_sync_log(array(),'-----------Collation replaced----------------');
				$current_query = $this->replace_collation($current_query);
			}

			if(!empty($is_local_file_list_dump)){
				$current_query = $this->search_and_replace_local_file_list_table($current_query);
			}

			if ( $this->is_migration ) {
				$current_query = $this->search_and_replace_db_name($current_query);
				// $current_query = $this->search_and_replace_prefix($current_query);
				$replaceQuery = $this->search_and_replace_urls_new($current_query, $this->old_url, $this->new_url, $this->live_db_prefix);
			}

			local_sync_manual_debug('', 'during_db_restore', 1000);

			// local_sync_log($replaceQuery, "--------replaceQuery------");

			// local_sync_log($tempQuery, "--------tempQuery--cat------");

			if(!empty($replaceQuery['prevExec']) && !empty($tempQuery)) {

				// local_sync_log('', "--------prevExec---coming-----");

				// local_sync_log(strlen($tempQuery), "--------tempQuery--length---exec---");
				// local_sync_log($tempQuery, "--------tempQuerying---q-----");

				// local_sync_log($tempQuery, "--------running query--------");

				$result = $this->wpdb->query($tempQuery);
				if($result===false) {
					local_sync_log($this->wpdb->last_error,'-----------$last_error----------------');

					// local_sync_log($tempQuery, "--------tempQuery--------");

					$string = substr($tempQuery,0,2500).'...';
					$this->log_data('queries', $string);

					$tempQuery ='';

					if( !$replace_collation && $this->is_collation_issue($this->wpdb->last_error) ){

						local_sync_log(array(),'-----------Collation issue----------------');

						$this->wpdb->query('UNLOCK TABLES;');
						fclose($handle);

						return array('status' => 'continue', 'offset' => $loop_iteration, 'replace_collation' => true);
					}
				}
				$tempQuery ='';
            }

            $tempQuery .= $replaceQuery['q'];

			if(!empty($replaceQuery['exec']) && !empty($tempQuery)) {

				// local_sync_log(strlen($tempQuery), "--------tempQuery--length---exec---");
				// local_sync_log($tempQuery, "--------tempQuerying---q-----");

				// local_sync_log($tempQuery, "--------running query--------");

				$result = $this->wpdb->query($tempQuery);
				if($result===false) {
					local_sync_log($this->wpdb->last_error,'-----------$last_error----------------');

					// local_sync_log($tempQuery, "--------tempQuery--------");

					$string = substr($tempQuery,0,2500).'...';
					$this->log_data('queries', $string);

					$tempQuery ='';

					if( !$replace_collation && $this->is_collation_issue($this->wpdb->last_error) ){

						local_sync_log(array(),'-----------Collation issue----------------');

						$this->wpdb->query('UNLOCK TABLES;');
						fclose($handle);

						return array('status' => 'continue', 'offset' => $loop_iteration, 'replace_collation' => true);
					}
				}
				$tempQuery ='';
            }

			$current_query = $line = '';

			//check timeout after every 10 queries executed
			if ($this_lines_count <= 10) {
				continue;
			}

			$this_lines_count = 0;

			if(!$this->maybe_call_again_tc($return = true)){
				continue;
			}

			$this->wpdb->query('UNLOCK TABLES;');
			fclose($handle);
			return array('status' => 'continue', 'offset' => $loop_iteration, 'replace_collation' => false);
		}

		$this->wpdb->query('UNLOCK TABLES;');

		return array('status' => 'completed');
	}

	public function search_and_replace_urls_new($haystack, $from, $to, $table_prefix = NULL) {
		// local_sync_log('', "--------search_and_replace_urls_new--------");
		// local_sync_log($from, "-----search_and_replace_urls_new---fromURL--------");
		// local_sync_log($to, "--------toURL--------");

		$fromURL = parse_url($from);
		$toURL = parse_url($to);
		$retArray = array();

		$this->old_table_name = local_sync_get_table_from_query($haystack);

		if(!empty($table_prefix)){
			if ( stripos($haystack, $table_prefix . 'user_roles') === false
				 && stripos($haystack, $table_prefix . 'usermeta') === false ) {
				$queryArray = explode(" (", $haystack);
				$queryArray[0] = str_ireplace($table_prefix, DB_PREFIX_LOCAL_SYNC, $queryArray[0]);
				$haystack = implode(" (", $queryArray);
			} else {
				$haystack = str_ireplace($table_prefix, DB_PREFIX_LOCAL_SYNC, $haystack);
			}
		}

		if( stripos($haystack, "insert into") !== false 
			&& ( !empty($from) && !empty($fromURL) && !empty($fromURL['host']) )
			&& stripos($haystack, $fromURL['host']) !== false ){
			$match = explode(",'", $haystack);

			// local_sync_log($match, "--------exploded match--------");

			$incrementor = 0;
			foreach($match as $matchDat => $val) {
				$val = str_replace("\',", "**||**||-lcsync,", $val);
				$val = explode("',", $val);
				$val = $val[0];
				$replaceEndQuote = 0;
				$replaceStartQuote = 0;
				$replaceEndBraces = 0;
				$val = str_replace("**||**||-lcsync,", "\',", $val);
				$val = trim($val, ");\n");
				$val = trim($val, "'");
				$oldval = $val;
				$val = $this->stripallslashes($val);

				// local_sync_log($val, "--------to replace val--------");

				if ($this->is_multisite) { //revisit
					$replace = $this->findAndReplace($fromURL['host'], $toURL['host'], $val);
					$replace = $this->findAndReplace($fromURL['path'], $toURL['path'], $replace);
				} else {
					$urlPort = '';
					$urlPath = '';
					if (isset($fromURL['port']) && $fromURL['port'] != ''){
						$urlPort = ":".$fromURL['port'];
					}
					if (isset($fromURL['path']) && $fromURL['path'] != ''){
						$urlPath = $fromURL['path'];
					}

					// if (isset($toURL['port']) && $toURL['port'] != ''){
					// 	$url2Port = ":".$toURL['port'];
					// }
					// if (isset($toURL['path']) && $toURL['path'] != ''){
					// 	$url2Path = $toURL['path'];
					// }

					$fromHTTPS = "https://".$fromURL['host'].$urlPort.$urlPath;
					$fromHTTP = "http://".$fromURL['host'].$urlPort.$urlPath;

					// $withoutProtocolFrom = "//".$fromURL['host'].$urlPort.$urlPath;

					if(empty($urlPath)){
						$urlPathEsc = $urlPath;
					} else {
						$urlPathEsc = "\\" . $urlPath;
					}
					$withoutProtocolFrom = "https:\\/\\/".$fromURL['host'].$urlPort.$urlPathEsc;
					$withoutProtocolFrom2 = "http:\\/\\/".$fromURL['host'].$urlPort.$urlPathEsc;

					// $new_from = $fromURL['host'].$urlPort.$urlPath;
					// $new_to = $toURL['host'].$url2Port.$url2Path;

					// $replace = $this->findAndReplace($new_from, $new_to, $val);

					$replace = $this->findAndReplace(array($fromHTTPS, $fromHTTP, $withoutProtocolFrom, $withoutProtocolFrom2), $to, $val);
				}

				if ($incrementor == 0 && stripos($replace, "'") !== false) {

					$replace = str_replace("'", "**||**||-lcsync", $replace);
					$escapedSQL = $this->wpdb->_real_escape($replace);
					$escapedSQL = str_replace("**||**||-lcsync", "'", $escapedSQL);
				} else {
					$escapedSQL = $this->wpdb->_real_escape($replace);
				}

				$haystack = str_replace($oldval, $escapedSQL, $haystack);
				$incrementor++;
			}
		}

		// local_sync_log($haystack, "--------haystack--------");

		if (stripos($haystack, "insert into") !== false) {
			$sub_str_haystack = substr($haystack, 0, 500);
			$this->old_table_name = local_sync_get_table_from_query($sub_str_haystack);
			if ($this->tempQueryCount > 0) {
				if ($this->tempQueryCount > 1000 || $this->tempQuerySize > 100000) {
					if(empty($this->old_table_name)){
						$sql = '';
					} else {
						$sql = ",".$this->replaceInsertQuery($haystack, $table_prefix). ";\n";
					}
					$retArray['q'] = $sql;
					$retArray['exec'] = 1;
					$this->resetTempQuery(-1);
				} else {
					if(empty($this->old_table_name)){
						$sql = '';
					} else {
						$sql = ",".$this->replaceInsertQuery($haystack, $table_prefix);
					}
					$retArray['q'] = $sql;
				}
			} else {
				if(empty($this->old_table_name)){
					$sql = '';
				} else {
					$sql = substr($haystack, 0, -2);
				}
				
				$retArray['q'] = $sql;
			}

			$this->tempQueryCount = $this->tempQueryCount + 1;
			$this->tempQuerySize = $this->tempQuerySize + strlen($sql);
		} else {

			// local_sync_log('', "--------else haystack--------");

			// if($this->tempQueryCount > 0){
			// 	$haystack = "; \n " . $haystack;
			// }

			// local_sync_log($from, "-----search_and_replace_urls_new---fromURL--------");
			// local_sync_log($to, "--------toURL--------");

			$retArray['q'] = $haystack;
			$retArray['exec'] = 1;
			$retArray['prevExec'] = 1;
			$this->resetTempQuery();
		}

		// local_sync_log($retArray['q'], "-----modified---haystack--------");

		return $retArray;
	}

	public function log_sql_error_queries($queries = '') {
		local_sync_log($this->wpdb->last_error,'-----------$last_error----------------');
		$string = substr($current_query,0,250).'...';
		local_sync_log($string, "--------current_query--------");

		if( !$replace_collation && $this->is_collation_issue($this->wpdb->last_error) ){

			local_sync_log(array(),'-----------Collation issue----------------');

			$this->wpdb->query('UNLOCK TABLES;');
			fclose($handle);

			//restart the processes
			return array('status' => 'continue', 'offset' => 0, 'replace_collation' => true);
		}
		//log failed queries
		$this->log_data('queries', $string);
	}

	public function stripallslashes($string) {
        $string = str_ireplace( array('\"', "\'", '\\\\n', '\\\\r', '\r', '\n', '\\\\', '##nn', '##rr'), 
        						array('"',  "'",   '##nn',  '##rr', "\r", "\n",   "\\",  '\\n',  '\\r'), 
        						$string );

		return $string;
    }

    public function findAndReplace( $from = '', $to = '', $data = '', $serialised = false) {

    	// local_sync_log(func_get_args(), "--------findAndReplace--------");

        try {
            if ( is_string( $data ) && ( $unserialized = @unserialize( $data ) ) !== false ) {

                $data = $this->findAndReplace( $from, $to, $unserialized, true );
            }

            elseif ( is_array( $data ) ) {
                $_tmp = array( );
                foreach ( $data as $key => $value ) {
                    $_tmp[ $key ] = $this->findAndReplace( $from, $to, $value, false );
                }

                $data = $_tmp;
                unset( $_tmp );
            }

            elseif ( is_object( $data ) ) {
				if( get_class($data) != '__PHP_Incomplete_Class' ){
					$_tmp = $data;
					$props = get_object_vars( $data );
					foreach ( $props as $key => $value ) {

						//If some objects has \0 in the key it creates the fatal error so skip such contents
						if (is_string($key) && strstr($key, "\0") !== false ) {
							continue;
						}
						if (is_string($value) && strstr($value, "\0") !== false ) {
							continue;
						}

						$_tmp->$key = $this->findAndReplace( $from, $to, $value, false );
					}

					$data = $_tmp;
					unset( $_tmp );
				}
            }

            else {
                if ( is_string( $data ) ) {
                    $data = str_replace( $from, $to, $data );
                }
            }
            //file_put_contents(dirname(__FILE__)."/__debugger1.php",$tableName.'-'.var_export($data,1)."\n<br><br>\n",FILE_APPEND );
            if ( $serialised )
                return serialize( $data );

        } catch( Exception $error ) {}

        return $data;
    }

	public function resetTempQuery($val=0) {
        $this->tempQueryCount=$val;
	    $this->tempQuerySize=0;
    }

	public function replaceInsertQuery($query, $table_prefix) {
        if(stripos($query,"INSERT INTO")!==false) {
	        $newTable = str_ireplace($table_prefix, DB_PREFIX_LOCAL_SYNC, $this->old_table_name);
	        $query = str_ireplace("INSERT INTO `".$newTable."` VALUES ", '', $query);
	        $query = substr($query, 0, -2);
        }

        return $query;
    }

	public function maybe_call_again_tc($return = false) {
		global $local_sync_ajax_start_time;
		if(empty($local_sync_ajax_start_time)){

			local_sync_log('', "--------local_sync_ajax_start_time--not set------");
			
		}

		$this->define('LOCAL_SYNC_TIMEOUT', 21);

		if ((time() - $local_sync_ajax_start_time) >= LOCAL_SYNC_TIMEOUT) {

			if ($return) return true;

			die_with_ls_signature(array(
				'success' =>  true,
				'sync_sub_action' => $this->local_sync_options->get_option('sync_sub_action'),
				'sync_current_action' => $this->local_sync_options->get_option('sync_current_action'),
				'requires_next_call' => true
			));

		}

		return false;
	}

	public function log_data($type, $data){
		if (empty($type) || empty($data)) {
			return false;
		}

		if ($type === 'files') {
			$file_path = $this->local_sync_options->get_option('restore_failed_downloads_file_path');
		} else if($type === 'queries') {
			$file_path = $this->local_sync_options->get_option('restore_failed_queries_file_path');
		}

		if (empty($file_path) || !file_exists($file_path)) {
			local_sync_log($file_path, '--------$file_path not exist so cannot log--------');

			$string = substr($data,0,50).'...';

			local_sync_log($string, "-----log_data---data--------");
			
			return false;
		}

		if ($type === 'files') {
			foreach ($data as $key => $value) {
				file_put_contents($file_path, $key . " : " . $value . "\n", FILE_APPEND);
			}
			file_put_contents($file_path, "\n", FILE_APPEND);
		} else if($type === 'queries') {
			file_put_contents($file_path, $data . "\n", FILE_APPEND);
		}

	}

	private function is_collation_issue($error){
		// local_sync_log(func_get_args(), "--------" . __FUNCTION__ . "--------");

		if (!$error) {
			return false;
		}

		if (strstr($error, 'Unknown collation') === false) {
			return false;
		}

		$this->local_sync_options->set_option('replace_collation_for_this_restore', true);

		return true;

	}

	private function replace_collation($current_query){
		if (strstr($current_query,'utf8mb4_unicode_520_ci') === false) {
			return $current_query;
		}

		return str_replace('utf8mb4_unicode_520_ci','utf8mb4_unicode_ci', $current_query);
	}

	public function search_and_replace_db_name($query){
		if(!$this->is_migration){

			return $query;
		}

		$stripos1 = stripos($query, 'CREATE DATABASE IF NOT EXISTS');
		$stripos2 = stripos($query, 'USE ');
		if($stripos1 !== false){

			local_sync_log('', "--------search_and_replace_db_name--1--success----");

			$this_query = 'CREATE DATABASE IF NOT EXISTS `' . DB_NAME . '`';

			return '';
		}

		if($stripos2 !== false && $stripos2 == 0){

			local_sync_log('', "--------search_and_replace_db_name--2--success----");

			$this_query =  'USE `' . DB_NAME . '`';

			return '';
		}

		return $query;
	}

	private function init_necessary_things_migration(){
		$this->get_replace_db_link_obj();

		$local_site_url_enc = $this->local_sync_options->get_option('local_site_url_enc');
		$local_site_url = base64_decode($local_site_url_enc);

		$this->old_url = rtrim($this->local_sync_options->get_option('prod_site_url'), '/');
		$this->new_url = rtrim($this->local_sync_options->get_option('local_site_url'), '/');
		$this->old_dir = $this->local_sync_options->get_option('away_site_abspath');
		$this->new_dir = ABSPATH;

		$this->local_sync_options->set_option('setup_fresh_site_coz_migration', true);

		local_sync_log($this->old_url, '---------------$this->old_url-----------------');
		local_sync_log($this->new_url, '---------------$this->new_url-----------------');
		local_sync_log($this->old_dir, '---------------$this->old_dir-----------------');
		local_sync_log($this->new_dir, '---------------$this->new_dir-----------------');
	}

	private function get_replace_db_link_obj(){
		$this->replace_links_obj = new Local_Sync_Replace_DB_Links($this->is_multisite, $this->away_site_id_current_site, $this->away_blog_id_current_site);
	}

	public function replace_db_links($is_migration = false){
		if (!$is_migration) {

			return ;
		}

		$restore_deep_links_completed = $this->local_sync_options->get_option('restore_deep_links_completed');

		local_sync_log($restore_deep_links_completed,'-----------$restore_deep_links_completed----------------');

		if ($restore_deep_links_completed) {

			local_sync_log($restore_deep_links_completed,'-----------$restore_deep_links_completed--so not runnnig--------------');

			return ;
		}

		$raw_result = $this->local_sync_options->get_option('same_server_replace_old_url_data');

		local_sync_log($raw_result,'-----------$raw_result----------------');
		
		$tables = false;
		if (!empty($raw_result)) {
			$tables = @unserialize($raw_result);
		}

		$new_site_url = $this->local_sync_options->get_option('prod_site_url');

		$this->replace_links_obj->replace_uri($this->old_url, $this->new_url, $this->old_dir, $this->new_dir, DB_PREFIX_LOCAL_SYNC, $tables, $new_site_url, 'restore_in_staging');

		$this->local_sync_options->set_option('restore_deep_links_completed', true);
	}

	public function migration_replace_links(){

		local_sync_log(func_get_args(), "--------" . __FUNCTION__ . "--------");

		if($this->local_sync_options->get_option('migration_replaced_links')){
			local_sync_log(array(),'----------replace links done already----------------');
			return ;
		}

		if(!$this->local_sync_options->get_option('local_site_url')){
			local_sync_log(array(),'----------no local_site_url----------------');
			return ;
		}

		if(!$this->local_sync_options->get_option('away_site_abspath')){
			local_sync_log(array(),'----------no away_site_abspath----------------');
			return ;
		}

		$this->local_sync_options->set_option('is_migration_running', true);

		$this->init_necessary_things_migration();

		// $replace_db_links = $this->local_sync_options->get_option('restore_deep_links_completed');
		
		// if(empty($replace_db_links)){

		// 	local_sync_log($replace_db_links , '-------$replace_db_links ---not yet completed so running it----------------');

		// 	local_sync_manual_debug('', 'start_replace_old_url');

		// 	$this->replace_db_links($is_migration = true);

		// 	local_sync_manual_debug('', 'end_replace_old_url');
		// }

		// local_sync_log($replace_db_links , '--------completed so running site url replace alone----------------');

		$this->replace_links_obj->update_site_and_home_url(DB_PREFIX_LOCAL_SYNC, $this->new_url);

		$this->replace_links_obj->rewrite_rules(DB_PREFIX_LOCAL_SYNC);

		$this->replace_links_obj->update_user_roles(DB_PREFIX_LOCAL_SYNC, $this->live_db_prefix);

			//Replace new prefix
		$this->replace_links_obj->replace_prefix(DB_PREFIX_LOCAL_SYNC, $this->live_db_prefix);

		//multisite changes
		if ($this->is_multisite) {
			$this->replace_links_obj->multi_site_db_changes(DB_PREFIX_LOCAL_SYNC, $this->new_url, $this->old_url);
		}

		//replace $table_prefix in wp-config.php
		$this->replace_links_obj->modify_wp_config(
			array(
				'old_url'    => $this->old_url,
				'new_url'    => $this->new_url,
				'new_path'   => $this->new_dir,
				'old_path'   => $this->old_dir,
				'new_prefix' => DB_PREFIX_LOCAL_SYNC,
			), 'MIGRATION'
		);

		$this->replace_links_obj->replace_htaccess(
			array(
				'old_url'    => $this->old_url,
				'new_url'    => $this->new_url,
				'new_path'   => $this->new_dir,
				'old_path'   => $this->old_dir,
			)
		);

		$this->refresh_elementor_css();
		$this->local_sync_options->set_option('is_migration_running', false);
		$this->local_sync_options->set_option('migration_replaced_links', true);
	}

	public function refresh_elementor_css(){
		$sql_result = $this->wpdb->query("DELETE FROM `{$this->wpdb->base_prefix}postmeta` WHERE `meta_key`='_elementor_css'");
		if($sql_result === false){
			local_sync_log($this->wpdb->last_error, "--------refresh_elementor_css-query failed-----");
		}
	}

	public function search_and_replace_prefix($query){
		if(stripos($query, 'USE ') === 0){
			local_sync_log($query, "--------use query man------");

			return $query; //revisit
		}

		// return $query; //revisit

		$old_table_name = local_sync_get_table_from_query($query);

		// $this->get_table_prefix_local_sync_tables($old_table_name);

		if($this->is_migration) {
			$old_prefix = $this->live_db_prefix;
		}

		if (!empty($old_table_name)) {
			$new_table_name = preg_replace("/$old_prefix/i", DB_PREFIX_LOCAL_SYNC, $old_table_name, 1);
		} else {
			$old_table_name = $this->live_db_prefix;
			$new_table_name = DB_PREFIX_LOCAL_SYNC;
		}

		// if(stripos($query, 'local_sync') !== false){
		// 	local_sync_log($old_prefix, "--------old_prefix--------");
		// 	local_sync_log($old_table_name, "--------old_table_name--------");
		// 	local_sync_log($new_table_name, "--------new_table_name--------");
		// }

		return str_replace($old_table_name, $new_table_name, $query);
	}

	public function search_and_replace_local_file_list_table($query){

		// return $query; //revisit

		$old_table_name = local_sync_get_table_from_query($query);

		// $this->get_table_prefix_local_sync_tables($old_table_name);

		// if($this->is_migration) {
		// 	$old_prefix = $this->site_db_prefix;
		// }

		if (!empty($old_table_name)) {
			$new_table_name = preg_replace("/local_sync_current_process/i", 'local_sync_local_site_files', $old_table_name, 1);
		} else {
			$old_table_name = 'local_sync_current_process';
			$new_table_name = 'local_sync_local_site_files';
		}

		//local_sync_log($old_prefix, "--------old_prefix--------");
		// local_sync_log($old_table_name, "--------old_table_name---search_and_replace_local_file_list_table-----");
		// local_sync_log($new_table_name, "--------new_table_name----search_and_replace_local_file_list_table----");

		return str_replace($old_table_name, $new_table_name, $query);
	}

	public function get_table_prefix_local_sync_tables($table){


		if ($this->local_sync_db_prefix) {
			return ;
		}

		$local_sync_tables = array(
			'local_sync_current_process',
			'local_sync_inc_exc_contents',
			'local_sync_options',
			'local_sync_processed_files',
			'local_sync_processed_iterator',
		);

		foreach ($local_sync_tables as $local_sync_table) {
			if (stristr($table, $local_sync_table) !== false) {
				$this->local_sync_db_prefix = substr($table, 0, stripos($table, $local_sync_table));
				break;
			}
		}
	}

	private function skip_this_query_for_multisite($current_query){

		return false; //revisit

		$wildcards = array('UNLOCK TABLES', 'LOCK TABLES');

		foreach ($wildcards as $wildcard) {
			if (strstr($current_query, $wildcard) !== false) {
				return false;
			}
		}

		$table_name = local_sync_get_table_from_query($current_query);

		// local_sync_log($table_name,'-----------$table_name----------------');

		// local_sync_log($table_name,'-----------$table_name-----suring skip query-----------');

		preg_match("/^".$this->multisite_config['base_prefix']."._/", $table_name, $output_array);

		// local_sync_log($output_array, "--------skip this query output_array--------");

		if (empty($output_array)) {
			return true;
		}

		if($output_array[0] === $this->multisite_config['current_prefix']){
			return false;
		}

		return true;
	}

	public function is_gzip_available(){
		if(!local_sync_function_exist('gzwrite') || !local_sync_function_exist('gzopen') || !local_sync_function_exist('gzclose') ){
			local_sync_log(array(), '--------ZGIP not available--------');
			return false;
		}

		return true;
	}

	public function reset_bridge_constants() {
		$this->local_sync_options->set_option('setup_fresh_site_coz_migration', 0);
		$this->local_sync_options->set_option('restore_deep_links_completed', 0);
		$this->local_sync_options->set_option('restore_db_index', 0);
		$this->local_sync_options->set_option('restore_database_decrypted', 0);
		$this->local_sync_options->set_option('migration_replaced_links', 0);
		$this->local_sync_options->set_option('is_wp_content_dir_moved_outside_root', 0);
		$this->local_sync_options->set_option('is_migration_running', 0);
		$this->local_sync_options->set_option('restore_full_db_process_completed', 0);
		$this->local_sync_options->set_option('restore_db_index', 0);
		$this->local_sync_options->set_option('is_bridge_process', 0);
	}

	public function process_delete_files_during_restore() {

		$result = $this->delete_files_from_table_records();

		local_sync_log($result, "--------process_delete_files_during_restore--result------");

		if(!empty($result)){
			$requires_next_call = false;


			if( !empty($result['is_completed']) ){
				$this->local_sync_options->set_this_current_action_step('done');
				
				$this->local_sync_options->set_option('sync_sub_action', 'restore_completed');
				$this->local_sync_options->set_option('sync_current_action', 'restore_completed');

				$requires_next_call = false;
			}

			die_with_ls_signature(array(
				'success' =>  true,
				'deleted_files_count' =>  $result['deleted_files_count'],
				'sync_sub_action' => $this->local_sync_options->get_option('sync_sub_action'),
				'sync_current_action' => $this->local_sync_options->get_option('sync_current_action'),
				'requires_next_call' => $requires_next_call
			));
		}

		$this->local_sync_options->set_this_current_action_step('error');

		die_with_ls_signature(array(
			'error' =>  'Deleting files during restore failed.',
			'sync_sub_action' => $this->local_sync_options->get_option('sync_sub_action'),
			'sync_current_action' => $this->local_sync_options->get_option('sync_current_action'),
			'requires_next_call' => false
		));

	}

	public function delete_files_to_be_deleted() {

		$this->set_fs();

		$state_file = $this->local_sync_options->get_backup_dir() . '/local_sync_deleted_files.txt';

		local_sync_log($state_file, '---------------$state_file-----------------');

		if (!file_exists($state_file)) {
			local_sync_log(array(), '----------------File not exists------initiate_delete_files----------');
			return true;
		}

		$handle = fopen($state_file, "rb");

		if (empty($handle)) {

			local_sync_log(array(), '----------------cannot open state file-----initiate_delete_files-----------');

			return false;
		}

		$prev_offset = $this->local_sync_options->get_option('delete_state_files_from_download_list_offset');
		$prev_offset = ($prev_offset) ? $prev_offset : 0 ;

		local_sync_log($prev_offset, '---------------$prev_offset-------initiate_delete_files----------');

		$current_offset = 0;

		$processsed_files_count_on_this_request = 0;

		$bulk_delete = '';

		while (($file = fgets($handle)) !== false) {

			$current_offset++;

			if ($current_offset <= $prev_offset ) {
				continue; //check offset; if it already processed ; then continue;
			}

			local_sync_manual_debug('', 'during_delete_state_files_from_download_list', 100);

			$file = str_replace("\n", '', $file);

			if (empty($file)) {

				local_sync_log('', "--------empty file-list---check_and_delete_state_files----");

				continue;
			}

			$file = local_sync_add_fullpath($file);

			if (!$this->fs->exists($file)) {
				local_sync_log($file, '----------------File not found----initiate_delete_files------------');
				continue;
			}

			local_sync_wait_for_sometime();

			local_sync_log($file, "--------deleting state file--initiate_delete_files------");

			$result = $this->fs->delete($file);

			if (!$result) {
				local_sync_log(error_get_last(), '-------deleting-state-------error_get_last()-----------------');
			}

			$processsed_files_count_on_this_request++;

			
			if(!is_local_sync_timeout_cut()){
				continue;
			}

			$this->local_sync_options->set_option('delete_state_files_from_download_list_offset', $current_offset);
			
			return array(
				'deleted_files_count' =>  $processsed_files_count_on_this_request,
				'is_completed' => false
			);
		}

		$this->local_sync_options->set_option('delete_state_files_from_download_list_offset', 0);

		return array(
			'deleted_files_count' =>  $processsed_files_count_on_this_request,
			'is_completed' => true
		);

	}

	public function delete_files_from_table_records() {
		$this->set_fs();

		$delete_files = $this->get_limited_files_to_delete_from_table();

		$processsed_files_count_on_this_request = 0;

		$updated_files_str = '';

		foreach ($delete_files as $key => $value) {
			$updated_files_str .= ',"'.$value['file'].'"';

			$file = local_sync_add_fullpath($value['file']);

			if (!$this->fs->exists($file)) {
				// local_sync_log($file, '----------------File not found----delete_files_from_table_records------------');
				
				continue;
			}

			local_sync_wait_for_sometime();

			// local_sync_log($file, "--------deleting state file--delete_files_from_table_records------");

			$result = $this->fs->delete($file);

			if (!$result) {
				local_sync_log(error_get_last(), "-------delete_files_from_table_records-------error_get_last()----for-------$file------");
			}

			$processsed_files_count_on_this_request++;

			if(!is_local_sync_timeout_cut()){
				continue;
			}


			return array(
				'deleted_files_count' =>  $processsed_files_count_on_this_request,
				'is_completed' => false
			);
		}

		local_sync_log($updated_files_str, "--------deleted files str----D----");

		$updated_files_str = ltrim($updated_files_str, ',');
		$this->update_delete_list_table_status($updated_files_str, 'D');

		return array(
			'deleted_files_count' =>  $processsed_files_count_on_this_request,
			'is_completed' => true
		);
	}

	public function process_prepare_delete_list_table()	{
		if($this->local_sync_options->is_feature_valid('filesDiff')){
			$result = $this->prepare_delete_list_table();
		} else {
			$result = array(
				'is_completed' => true
			);
		}

		local_sync_log($result, "--------process_prepare_delete_list_table--result------");

		if(!empty($result)){
			$requires_next_call = false;

			if( !empty($result['is_completed']) ){
				$this->local_sync_options->set_this_current_action_step('done');

				$this->local_sync_options->set_option('sync_sub_action', 'db_dump_restore');
				$this->local_sync_options->set_option('sync_current_action', 'db_dump_restore');

				$this->local_sync_options->set_this_current_action_step('processing');

				$requires_next_call = true;
			}

			die_with_ls_signature(array(
				'success' =>  true,
				'to_be_deleted_files_count' =>  $result['to_be_deleted_files_count'] ?? 0,
				'sync_sub_action' => $this->local_sync_options->get_option('sync_sub_action'),
				'sync_current_action' => $this->local_sync_options->get_option('sync_current_action'),
				'requires_next_call' => $requires_next_call
			));
		}

		$this->local_sync_options->set_this_current_action_step('error');

		die_with_ls_signature(array(
			'error' =>  'Deleting files during restore failed.',
			'sync_sub_action' => $this->local_sync_options->get_option('sync_sub_action'),
			'sync_current_action' => $this->local_sync_options->get_option('sync_current_action'),
			'requires_next_call' => false
		));
	}

	public function prepare_delete_list_table() {

		$state_file = $this->local_sync_options->get_backup_dir() . '/local_sync_deleted_files.txt';

		local_sync_log($state_file, '---------------$state_file-----------------');

		if (!file_exists($state_file)) {

			local_sync_log(array(), '----------------File not exists------prepare_delete_list_table----------');

			return array(
				'to_be_deleted_files_count' =>  0,
				'is_completed' => true
			);
		}

		$handle = fopen($state_file, "rb");

		if (empty($handle)) {

			local_sync_log(array(), '----------------cannot open state file-----prepare_delete_list_table-----------');

			return false;
		}

		$prev_offset = $this->local_sync_options->get_option('prepare_delete_list_table_offset');
		$prev_offset = ($prev_offset) ? $prev_offset : 0 ;

		local_sync_log($prev_offset, '---------------$prev_offset-------prepare_delete_list_table----------');

		$current_offset = 0;

		$processsed_files_count_on_this_request = 0;

		$bulk_delete = '';

		$query = '';

		while (($file = fgets($handle)) !== false) {

			$current_offset++;

			if ($current_offset <= $prev_offset ) {
				continue; //check offset; if it already processed ; then continue;
			}

			local_sync_manual_debug('', 'during_prepare_delete_list_table', 100);

			$file = str_replace("\n", '', $file);

			if (empty($file)) {

				local_sync_log('', "--------empty file-list---prepare_delete_list_table----");

				continue;
			}

			if ($file == '/wp-config.php') {

				local_sync_log('', "--------ignoring wp config file in delete list table----");

				continue;
			}

			// local_sync_log($file, "--------adding file--prepare_delete_list_table------");

			if(!empty($file)){
				$query .= ',("' . $file . '", "Q")';

			}

			$processsed_files_count_on_this_request++;

			if(!is_local_sync_timeout_cut()){
				continue;
			}

			$this->local_sync_options->set_option('prepare_delete_list_table_offset', $current_offset);
			
			return array(
				'to_be_deleted_files_count' =>  $processsed_files_count_on_this_request,
				'is_completed' => false
			);
		}

		$query = ltrim($query, ',');
		$result = $this->insert_into_delete_list_table($query);

		return array(
			'to_be_deleted_files_count' =>  $processsed_files_count_on_this_request,
			'is_completed' => true,
		);

	}

	public function insert_into_delete_list_table($query = ''){
		if(empty($query)){

			return;
		}

		$sql = "insert into " . $this->wpdb->base_prefix . "local_sync_delete_list (file, status) values $query";
		$result = $this->wpdb->query($sql, ARRAY_A);

		if ($result === false) {
			local_sync_log($this->wpdb->last_error, '-------deleting-state-------insert_into_delete_list_table---error--------------');
		}

		return $result;
	}

	public function get_limited_files_to_delete_user_selection() {
		$sql = "SELECT file FROM `{$this->wpdb->base_prefix}local_sync_delete_list` WHERE STATUS='Q' LIMIT 500";
		$response = $this->wpdb->get_results($sql);

		if($response === false){
			local_sync_log($sql, "--------get_limited_files_to_delete_user_selection---error-----");
		}

		return $response;
	}

	public function get_limited_files_to_delete_from_table() {
		$sql = "SELECT file FROM `{$this->wpdb->base_prefix}local_sync_delete_list` WHERE STATUS='P' LIMIT 5000";
		$response = $this->wpdb->get_results($sql, ARRAY_A);

		if($response === false){
			local_sync_log($sql, "--------get_limited_files_to_delete_user_selection---error-----");
		}

		return $response;
	}

	public function delete_empty_folders($source){
		local_sync_log(func_get_args(), __FUNCTION__);

		if (empty($source)) {
			return false;
		}

		$this->set_fs();

		$this->file_iterator = new Local_Sync_File_Iterator();

		$file_obj = $this->file_iterator->get_files_obj_by_path($source, false);

		foreach ($file_obj as $file_meta) {

			$path = $file_meta->getPathname();

			$path = wp_normalize_path($path);

			if (!local_sync_is_dir($path)) {
				continue;
			}

			if(!$this->file_iterator->is_empty_folder($path)){
				// local_sync_log($path, '---------------Not empty-----------------');
				continue;
			}

			local_sync_wait_for_sometime();

			local_sync_log($path, '---------------Deleted-----------------');

			$this->fs->delete($path, true);
		}

	}

	public function process_fetch_files_to_be_deleted() {
		// $this->local_sync_restore_op = new Local_Sync_Restore_Op();
		$user_files_to_delete = $this->get_limited_files_to_delete_user_selection();

		$show_all_delete_option = false;
		if(count($user_files_to_delete) > 100){
			$show_all_delete_option = true;
		}

		// local_sync_log($user_files_to_delete, "--------user_files_to_delete--------");

		die_with_ls_signature(array(
			'success' =>  true,
			'sync_sub_action' => $this->local_sync_options->get_option('sync_sub_action'),
			'sync_current_action' => $this->local_sync_options->get_option('sync_current_action'),
			'requires_next_call' => false,
			'user_selection_files' => $user_files_to_delete,
			'delete_files_allowed' => $this->local_sync_options->is_feature_valid('filesDiff'),
			'show_all_delete_option' => $show_all_delete_option
		), 'unescape_slashes');
	}

	public function update_delete_list_table_status($selected_files_str = '', $status = 'P') {
		$sql = "UPDATE `{$this->wpdb->base_prefix}local_sync_delete_list` SET STATUS='$status' WHERE file IN ($selected_files_str)";
		$response = $this->wpdb->query($sql);

		if($response === false){
			local_sync_log($sql, "--------update_delete_list_table_status---error-----");
		}

		return $response;
	}

	public function update_delete_list_table_status_by_all($status = 'P') {
		$sql = "UPDATE `{$this->wpdb->base_prefix}local_sync_delete_list` SET STATUS='$status' WHERE 1";
		$response = $this->wpdb->query($sql);

		if($response === false){
			local_sync_log($sql, "--------update_delete_list_table_status---error-----");
		}

		return $response;
	}

	public function truncate_new_attachments_table()	{
		$sql = "TRUNCATE TABLE {$this->wpdb->base_prefix}local_sync_local_site_new_attachments";
		$response = $this->wpdb->query($sql);
	}

	public function process_delete_files_list_update_then_delete($selected_files = null) {

		if(empty($selected_files)){

			if(defined('LOCAL_SYNC_DELETE_TEMP') && LOCAL_SYNC_DELETE_TEMP){
				$this->local_sync_options->remove_old_bridge_folder();
				$this->local_sync_options->truncate_delete_list_table();
				$this->local_sync_options->truncate_current_process_table();
			}

			die_with_ls_signature(array(
				'success' =>  true,
				'msg' => 'No Files to delete',
				'deleted_files_count' =>  0,
				// 'sync_sub_action' => $this->local_sync_options->get_option('sync_sub_action'),
				// 'sync_current_action' => $this->local_sync_options->get_option('sync_current_action'),
				'requires_next_call' => false,
			), 'unescape_slashes');
		}

		$selected_files_str = '';
		foreach ($selected_files as $key => $value) {
			if(!empty($value) && $value != 'false'){
				if($key == '/wp-config.php'){
					
					continue;
				}
				$selected_files_str .= ',"'.$key.'"';
			}
		}
		$selected_files_str = ltrim($selected_files_str, ',');

		$this->update_delete_list_table_status($selected_files_str);

		$result = $this->delete_files_from_table_records();

		local_sync_log($result, "--------process_delete_files_list_update_then_delete--------");

		if(!empty($result)){
			$requires_next_call = true;

			$this->local_sync_options->set_option('sync_sub_action', 'restore_completed');
			$this->local_sync_options->set_option('sync_current_action', 'restore_completed');

			if( !empty($result['is_completed']) ){

				$requires_next_call = false;
			}

			$this->delete_empty_folders(LOCAL_SYNC_WP_CONTENT_DIR . '/mu-plugins');
			$this->delete_empty_folders(LOCAL_SYNC_WP_CONTENT_DIR . '/plugins');
			$this->delete_empty_folders(LOCAL_SYNC_WP_CONTENT_DIR . '/themes');

			if(defined('LOCAL_SYNC_DELETE_TEMP') && LOCAL_SYNC_DELETE_TEMP){
				$this->local_sync_options->remove_old_bridge_folder();
			}

			die_with_ls_signature(array(
				'success' =>  true,
				'deleted_files_count' =>  $result['deleted_files_count'],
				// 'sync_sub_action' => $this->local_sync_options->get_option('sync_sub_action'),
				// 'sync_current_action' => $this->local_sync_options->get_option('sync_current_action'),
				'requires_next_call' => $requires_next_call
			));
		}

		die_with_ls_signature(array(
			'error' =>  'Deleting files during restore failed.',
			// 'sync_sub_action' => $this->local_sync_options->get_option('sync_sub_action'),
			// 'sync_current_action' => $this->local_sync_options->get_option('sync_current_action'),
			'requires_next_call' => false
		));
	}

	public function process_delete_all_files_in_delete_list() {

		$this->update_delete_list_table_status_by_all();

		$result = $this->delete_files_from_table_records();

		local_sync_log($result, "--------process_delete_all_files_in_delete_list--------");

		if(!empty($result)){
			$requires_next_call = true;

			$this->local_sync_options->set_option('sync_sub_action', 'restore_completed');
			$this->local_sync_options->set_option('sync_current_action', 'restore_completed');

			if( !empty($result['is_completed']) ){

				$requires_next_call = false;
			}

			$this->delete_empty_folders(LOCAL_SYNC_WP_CONTENT_DIR . '/mu-plugins');
			$this->delete_empty_folders(LOCAL_SYNC_WP_CONTENT_DIR . '/plugins');
			$this->delete_empty_folders(LOCAL_SYNC_WP_CONTENT_DIR . '/themes');

			if(defined('LOCAL_SYNC_DELETE_TEMP') && LOCAL_SYNC_DELETE_TEMP){
				$this->local_sync_options->remove_old_bridge_folder();
			}

			die_with_ls_signature(array(
				'success' =>  true,
				'deleted_files_count' =>  $result['deleted_files_count'],
				// 'sync_sub_action' => $this->local_sync_options->get_option('sync_sub_action'),
				// 'sync_current_action' => $this->local_sync_options->get_option('sync_current_action'),
				'requires_next_call' => $requires_next_call
			));
		}

		die_with_ls_signature(array(
			'error' =>  'Deleting files during restore failed.',
			// 'sync_sub_action' => $this->local_sync_options->get_option('sync_sub_action'),
			// 'sync_current_action' => $this->local_sync_options->get_option('sync_current_action'),
			'requires_next_call' => false
		));
	}

}
