<?php

/**
 * The file that defines the core plugin class
 *
 * A class definition that includes attributes and functions used across both the
 * public-facing side of the site and the admin area.
 *
 * @link       https://revmakx.com
 * @since      1.0.0
 *
 * @package    Local_Sync
 * @subpackage Local_Sync/includes
 */

/**
 * The core plugin class.
 *
 * This is used to define internationalization, admin-specific hooks, and
 * public-facing site hooks.
 *
 * Also maintains the unique identifier of this plugin as well as the current
 * version of the plugin.
 *
 * @since      1.0.0
 * @package    Local_Sync
 * @subpackage Local_Sync/includes
 * @author     Local Sync <mohamed@revmakx.com>
 */
class Local_Sync {

	/**
	 * The loader that's responsible for maintaining and registering all hooks that power
	 * the plugin.
	 *
	 * @since    1.0.0
	 * @access   protected
	 * @var      Local_Sync_Loader    $loader    Maintains and registers all hooks for the plugin.
	 */
	protected $loader;

	/**
	 * The unique identifier of this plugin.
	 *
	 * @since    1.0.0
	 * @access   protected
	 * @var      string    $plugin_name    The string used to uniquely identify this plugin.
	 */
	protected $plugin_name;

	/**
	 * The current version of the plugin.
	 *
	 * @since    1.0.0
	 * @access   protected
	 * @var      string    $version    The current version of the plugin.
	 */
	protected $version;

	/**
	 * Define the core functionality of the plugin.
	 *
	 * Set the plugin name and the plugin version that can be used throughout the plugin.
	 * Load the dependencies, define the locale, and set the hooks for the admin area and
	 * the public-facing side of the site.
	 *
	 * @since    1.0.0
	 */
	public function __construct() {
		if ( defined( 'LOCAL_SYNC_VERSION' ) ) {
			$this->version = LOCAL_SYNC_VERSION;
		} else {
			$this->version = '1.0.0';
		}
		$this->plugin_name = 'local-sync';

		$this->load_dependencies();
		$this->set_locale();
		$this->define_admin_hooks();
		$this->define_public_hooks();

	}

	/**
	 * Load the required dependencies for this plugin.
	 *
	 * Include the following files that make up the plugin:
	 *
	 * - Local_Sync_Loader. Orchestrates the hooks of the plugin.
	 * - Local_Sync_i18n. Defines internationalization functionality.
	 * - Local_Sync_Admin. Defines all hooks for the admin area.
	 * - Local_Sync_Public. Defines all hooks for the public side of the site.
	 *
	 * Create an instance of the loader which will be used to register the hooks
	 * with WordPress.
	 *
	 * @since    1.0.0
	 * @access   private
	 */
	private function load_dependencies() {

		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/class-local-sync-options.php';
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'local-sync-generic-functions.php';
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'admin/class-local-sync-app-functions.php';
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/class-local-sync-handle-server-requests.php';
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/class-local-sync-utils.php';
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'admin/class-local-sync-file-ext.php';
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'admin/class-local-sync-file-iterator.php';
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'admin/class-local-sync-exclude-option.php';
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'admin/class-local-sync-shell-dump.php';
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'admin/class-local-sync-db-op.php';
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'admin/class-local-sync-files-op.php';
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'admin/class-local-sync-zip-facade.php';
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'admin/class-local-sync-restore-op.php';
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'admin/class-local-sync-replace-db-links.php';
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'admin/class-local-sync-batch-upload.php';
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'admin/class-local-sync-batch-download.php';
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'local-sync-bridge/wp-modified-functions.php';
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'local-sync-bridge/class-local-sync-file-system.php';

		/**
		 * The class responsible for orchestrating the actions and filters of the
		 * core plugin.
		 */
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/class-local-sync-loader.php';

		/**
		 * The class responsible for defining internationalization functionality
		 * of the plugin.
		 */
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/class-local-sync-i18n.php';

		/**
		 * The class responsible for defining all actions that occur in the admin area.
		 */
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'admin/class-local-sync-admin.php';

		/**
		 * The class responsible for defining all actions that occur in the public-facing
		 * side of the site.
		 */
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'public/class-local-sync-public.php';

		$this->loader = new Local_Sync_Loader();

	}

	/**
	 * Define the locale for this plugin for internationalization.
	 *
	 * Uses the Local_Sync_i18n class in order to set the domain and to register the hook
	 * with WordPress.
	 *
	 * @since    1.0.0
	 * @access   private
	 */
	private function set_locale() {

		$plugin_i18n = new Local_Sync_i18n();

		$this->loader->add_action( 'plugins_loaded', $plugin_i18n, 'load_plugin_textdomain' );

	}

	/**
	 * Register all of the hooks related to the admin area functionality
	 * of the plugin.
	 *
	 * @since    1.0.0
	 * @access   private
	 */
	private function define_admin_hooks() {

		$plugin_admin = new Local_Sync_Admin( $this->get_plugin_name(), $this->get_version() );

		$plugin_admin->update_1_0_1_handle();
		$plugin_admin->update_1_0_2_handle();
		$plugin_admin->update_1_1_0_handle();

		$plugin_admin->check_db_upgrade();
		$plugin_admin->set_initial_flags();

		$this->loader->add_action( 'admin_enqueue_scripts', $plugin_admin, 'enqueue_styles' );
		$this->loader->add_action( 'admin_enqueue_scripts', $plugin_admin, 'enqueue_scripts' );

		if(defined('MULTISITE') && MULTISITE){
			$this->loader->add_action( 'network_admin_menu', $plugin_admin, 'local_sync_setttings_page' );
		} else {
			$this->loader->add_action( 'admin_menu', $plugin_admin, 'local_sync_setttings_page' );
		}
		
		$this->loader->add_action( 'wp_ajax_set_as_local_site_losy', $plugin_admin, 'set_as_local_site_losy' );
		$this->loader->add_action( 'wp_ajax_set_as_prod_site_losy', $plugin_admin, 'set_as_prod_site_losy' );
		$this->loader->add_action( 'wp_ajax_start_file_list_preparation', $plugin_admin, 'start_file_list_preparation' );
		$this->loader->add_action( 'wp_ajax_start_db_dump', $plugin_admin, 'start_db_dump' );
		$this->loader->add_action( 'wp_ajax_sync_from_live_site', $plugin_admin, 'sync_from_live_site' );
		$this->loader->add_action( 'wp_ajax_push_to_live_site', $plugin_admin, 'push_to_live_site' );
		$this->loader->add_action( 'wp_ajax_start_zip_download', $plugin_admin, 'start_zip_download_dev' );
		$this->loader->add_action( 'wp_ajax_start_zip_creation', $plugin_admin, 'start_zip_creation_dev' );
		$this->loader->add_action( 'wp_ajax_zip_extract_dev', $plugin_admin, 'zip_extract_dev' );
		$this->loader->add_action( 'wp_ajax_test_button', $plugin_admin, 'test_button' );
		$this->loader->add_action( 'wp_ajax_test_button', $plugin_admin, 'test_button' );
		$this->loader->add_action( 'wp_ajax_local_sync_get_root_files', $plugin_admin, 'local_sync_get_root_files' );
		$this->loader->add_action( 'wp_ajax_local_sync_get_tables', $plugin_admin, 'local_sync_get_tables' );
		$this->loader->add_action( 'wp_ajax_local_sync_get_init_root_files', $plugin_admin, 'local_sync_get_init_root_files' );
		$this->loader->add_action( 'wp_ajax_local_sync_get_files_by_key', $plugin_admin, 'local_sync_get_files_by_key' );
		$this->loader->add_action( 'wp_ajax_exclude_file_list_local_sync', $plugin_admin, 'exclude_file_list_local_sync' );
		$this->loader->add_action( 'wp_ajax_include_file_list_local_sync', $plugin_admin, 'include_file_list_local_sync' );
		$this->loader->add_action( 'wp_ajax_exclude_table_list_local_sync', $plugin_admin, 'exclude_table_list_local_sync' );
		$this->loader->add_action( 'wp_ajax_include_table_list_local_sync', $plugin_admin, 'include_table_list_local_sync' );
		$this->loader->add_action( 'wp_ajax_include_table_structure_only_local_sync', $plugin_admin, 'include_table_structure_only_local_sync' );
		$this->loader->add_action( 'wp_ajax_save_settings_local_sync', $plugin_admin, 'save_settings_local_sync' );
		$this->loader->add_action( 'wp_ajax_process_get_steps_for_steps_parent_echo', $plugin_admin, 'process_get_steps_for_steps_parent_echo' );
		$this->loader->add_action( 'wp_ajax_process_service_login', $plugin_admin, 'process_service_login' );
		$this->loader->add_action( 'wp_ajax_process_service_logout', $plugin_admin, 'process_service_logout' );
		$this->loader->add_action( 'wp_ajax_process_add_site', $plugin_admin, 'process_add_site' );
		$this->loader->add_action( 'wp_ajax_process_remove_site', $plugin_admin, 'process_remove_site' );
		$this->loader->add_action( 'wp_ajax_modified_files_modal_ok', $plugin_admin, 'modified_files_modal_ok' );
		$this->loader->add_action( 'wp_ajax_modify_all_files_modal_cancel', $plugin_admin, 'modify_all_files_modal_cancel' );

		$this->loader->add_action( 'setup_theme', $plugin_admin, 'handle_requests' );
		$this->loader->add_action( 'the_content', $plugin_admin, 'modify_posts_content' );
		$this->loader->add_action( 'wp_get_attachment_url', $plugin_admin, 'modify_image_site_url' );
		$this->loader->add_action( 'admin_print_footer_scripts', $plugin_admin, 'admin_print_footer_scripts' );
		$this->loader->add_filter( 'wp_calculate_image_srcset', $plugin_admin, 'modify_image_src_set' );
		$this->loader->add_filter( 'wp_insert_attachment_data', $plugin_admin, 'wp_insert_attachment_data' );
	}

	/**
	 * Register all of the hooks related to the public-facing functionality
	 * of the plugin.
	 *
	 * @since    1.0.0
	 * @access   private
	 */
	private function define_public_hooks() {

		$plugin_public = new Local_Sync_Public( $this->get_plugin_name(), $this->get_version() );

		$this->loader->add_action( 'wp_enqueue_scripts', $plugin_public, 'enqueue_styles' );
		$this->loader->add_action( 'wp_enqueue_scripts', $plugin_public, 'enqueue_scripts' );

	}

	/**
	 * Run the loader to execute all of the hooks with WordPress.
	 *
	 * @since    1.0.0
	 */
	public function run() {
		$this->loader->run();
	}

	/**
	 * The name of the plugin used to uniquely identify it within the context of
	 * WordPress and to define internationalization functionality.
	 *
	 * @since     1.0.0
	 * @return    string    The name of the plugin.
	 */
	public function get_plugin_name() {
		return $this->plugin_name;
	}

	/**
	 * The reference to the class that orchestrates the hooks with the plugin.
	 *
	 * @since     1.0.0
	 * @return    Local_Sync_Loader    Orchestrates the hooks of the plugin.
	 */
	public function get_loader() {
		return $this->loader;
	}

	/**
	 * Retrieve the version number of the plugin.
	 *
	 * @since     1.0.0
	 * @return    string    The version number of the plugin.
	 */
	public function get_version() {
		return $this->version;
	}
}
