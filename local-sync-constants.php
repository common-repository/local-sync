<?php

error_reporting(error_reporting() & ~E_DEPRECATED);
class Local_Sync_Constants{
	public function __construct(){
	}

	public function init_live_plugin(){
		$this->path();
		$this->set_env();
		$this->general();
		$this->versions();
		$this->debug();
		$this->set_mode();
	}

	public function init_staging_plugin(){
		$this->path();
		$this->set_env();
		$this->general();
		$this->versions();
		$this->debug();
		$this->set_mode();
	}

	public function init_restore(){
		$this->path();
		$this->set_env();
		$this->general();
		$this->versions();
		$this->debug();
		$this->set_mode();
	}

	public function bridge_restore(){
		$this->set_env($type = 'bridge');
		$this->general();
		$this->versions();
		$this->debug();
		$this->set_mode();
	}

	private function define( $name, $value ) {
		if ( ! defined( $name ) ) {
			define( $name, $value );
		}
	}

	public function set_env($type = false){
		$path = ($type === 'bridge') ? '' : LOCAL_SYNC_PLUGIN_DIR ;

		if (file_exists($path . 'local-sync-env-parameters.php')){
			include_once ($path . 'local-sync-env-parameters.php');
		}

		$this->define( 'LOCAL_SYNC_ENV', 'production' );
	}

	public function set_mode(){
		switch (LOCAL_SYNC_ENV) {
			case 'production':
				$this->production_mode();
				break;
			case 'staging':
				$this->staging_mode();
				break;
			case 'local':
			default:
				$this->development_mode();
		}
	}

	public function debug(){
		$this->define( 'LOCAL_SYNC_DEBUG', false );
	}

	public function versions(){
		$this->define( 'LOCAL_SYNC_VERSION', '1.1.6' );
		$this->define( 'LOCAL_SYNC_DATABASE_VERSION', '1.0' );
	}

	public function general(){

		$this->define( 'LOCAL_SYNC_CHUNKED_UPLOAD_THREASHOLD', 5242880); //5 MB
		$this->define( 'LOCAL_SYNC_MIN_REQUIRED_STORAGE_SPACE', 5242880); //5 MB
		$this->define( 'LOCAL_SYNC_MINUMUM_PHP_VERSION', '5.2.16' );
		$this->define( 'LOCAL_SYNC_NO_ACTIVITY_WAIT_TIME', 60); //5 mins to allow for socket timeouts and long uploads
		$this->define( 'LOCAL_SYNC_PLUGIN_PREFIX', 'local_sync' );
		$this->define( 'LOCAL_SYNC_PLUGIN_NAME', 'local-sync' );
		$this->define( 'LOCAL_SYNC_TIMEOUT', 23 );
		$this->define( 'LOCAL_SYNC_HASH_FILE_LIMIT', 1024 * 1024 * 15); //15 MB
		$this->define( 'LOCAL_SYNC_STAGING_COPY_SIZE', 1024 * 1024 * 2); //2 MB
		$this->define( 'LOCAL_SYNC_HASH_CHUNK_LIMIT', 1024 * 128); // 128  KB
		$this->define( 'LOCAL_SYNC_STAGING_PLUGIN_DIR_NAME', 'local-sync-staging' );
		$this->define( 'LOCAL_SYNC_RESTORE_FILES_NOT_WRITABLE_COUNT', 15 );
		$this->define( 'LOCAL_SYNC_NOTIFY_ERRORS_THRESHOLD', 10 );
		$this->define( 'LOCAL_SYNC_RESTORE_ADDING_FILES_LIMIT', 30);
		$this->define( 'LOCAL_SYNC_STAGING_DEFAULT_DEEP_LINK_REPLACE_LIMIT', 5000);
		$this->define( 'LOCAL_SYNC_CHECK_CURRENT_STATE_FILE_LIMIT', 500);
		$this->define( 'LOCAL_SYNC_STAGING_DEFAULT_FILE_COPY_LIMIT', 200);
		$this->define( 'LOCAL_SYNC_STAGING_DEFAULT_COPY_DB_ROWS_LIMIT', 1000);
		$this->define( 'LOCAL_SYNC_DEFAULT_DB_ROWS_BACKUP_LIMIT', 300); // 10 mins (60 * 10)
		$this->define( 'LOCAL_SYNC_DEFAULT_CURL_CONTENT_TYPE','Content-Type: application/x-www-form-urlencoded'); // some servers outbound requests are got blocked due to without content type
		$this->define( 'LOCAL_SYNC_MAX_REQUEST_PROGRESS_WAIT_TIME', 180); // 3 mins (3 * 60)
		$this->define( 'LOCAL_SYNC_CRYPT_BUFFER_SIZE', 2097152);

		$this->define( 'LOCAL_SYNC_DOWNLOAD_CHUNK_SIZE', 1024 * 1024);
		$this->define( 'LOCAL_SYNC_UPLOAD_CHUNK_SIZE', 1024 * 1024);

		//below PHP 5.4
		$this->define( 'JSON_UNESCAPED_SLASHES', 64);
		$this->define( 'JSON_UNESCAPED_UNICODE', 256);
		$this->define('LOCAL_SYNC_SERVICE_URL', 'https://localsync.io/applogin/');
	}

	public function path(){

		$this->define( 'LOCAL_SYNC_ABSPATH', wp_normalize_path( ABSPATH ) );
		$this->define( 'LOCAL_SYNC_RELATIVE_ABSPATH', '/' );
		$this->define( 'LOCAL_SYNC_WP_CONTENT_DIR', wp_normalize_path( WP_CONTENT_DIR ) );
		$this->define( 'LOCAL_SYNC_WP_CONTENT_BASENAME', basename( LOCAL_SYNC_WP_CONTENT_DIR ) );
		$this->define( 'LOCAL_SYNC_RELATIVE_WP_CONTENT_DIR', '/' . LOCAL_SYNC_WP_CONTENT_BASENAME );

		//Before modifying these, think about existing users
		$this->define( 'LOCAL_SYNC_TEMP_DIR_BASENAME', 'local_sync' );

		if (defined('LOCAL_SYNC_BRIDGE')) {
			$this->define( 'LOCAL_SYNC_EXTENSIONS_DIR', wp_normalize_path(BRIDGE_NAME_LOCAL_SYNC . '/Classes/Extension/') );
			$this->define( 'LOCAL_SYNC_PLUGIN_DIR', '' );
			$this->define( 'LOCAL_SYNC_RELATIVE_PLUGIN_DIR', '' );
			return ;
		}

		$this->define( 'LOCAL_SYNC_EXTENSIONS_DIR', wp_normalize_path(plugin_dir_path(__FILE__) . 'Classes/Extension/' ));
		$this->define( 'LOCAL_SYNC_CLASSES_DIR', wp_normalize_path(plugin_dir_path(__FILE__) . 'Classes/') );
		$this->define( 'LOCAL_SYNC_PRO_DIR', wp_normalize_path(plugin_dir_path(__FILE__) . 'Pro/') );

		$plugin_dir_path = wp_normalize_path( plugin_dir_path( __FILE__ ) );
		$this->define( 'LOCAL_SYNC_RELATIVE_PLUGIN_DIR', str_replace(LOCAL_SYNC_ABSPATH, LOCAL_SYNC_RELATIVE_ABSPATH, $plugin_dir_path ) );
		$this->define( 'LOCAL_SYNC_PLUGIN_DIR', $plugin_dir_path );

		$uploads_meta = wp_upload_dir();
		$basedir_path = wp_normalize_path( $uploads_meta['basedir'] );
		$this->define( 'LOCAL_SYNC_RELATIVE_UPLOADS_DIR', str_replace(LOCAL_SYNC_WP_CONTENT_DIR . '/', LOCAL_SYNC_RELATIVE_ABSPATH, $basedir_path ) );
		$this->define( 'LOCAL_SYNC_UPLOADS_DIR', $basedir_path);
		$this->define( 'LOCAL_SYNC_RELATIVE_EXCLUDE_MEDIA_FILE_LISTS_PATH', LOCAL_SYNC_TEMP_DIR_BASENAME . '/backups/' . 'local-sync-excluded-media.txt');

	}

	public function production_mode(){
		$this->define( 'LOCAL_SYNC_CURL_TIMEOUT', 20 );
		$this->define('LOCAL_SYNC_SHELL_DB', true);
		$this->define('LOCAL_SYNC_DELETE_TEMP', true);
	}

	public function staging_mode(){
		$this->define( 'LOCAL_SYNC_CURL_TIMEOUT', 20 );
		$this->define('LOCAL_SYNC_SHELL_DB', true);
		$this->define('LOCAL_SYNC_DELETE_TEMP', true);
	}

	public function development_mode(){
		$this->define( 'LOCAL_SYNC_CURL_TIMEOUT', 20 );
		$this->define('LOCAL_SYNC_SHELL_DB', true);
		$this->define('LOCAL_SYNC_DELETE_TEMP', false);
	}
}
