<?php
/**
 * WordPress DB Class
 *
 * Original code from {@link http://php.justinvincent.com Justin Vincent (justin@visunet.ie)}
 *
 * @package WordPress
 * @subpackage Database
 * @since 0.71
 */

/**
 * @since 0.71
 */
define('EZSQL_VERSION', 'WP1.25');

/**
 * @since 0.71
 */
define('OBJECT', 'OBJECT');
define('object', 'OBJECT'); // Back compat.

/**
 * @since 2.5.0
 */
define('OBJECT_K', 'OBJECT_K');

/**
 * @since 0.71
 */
define('ARRAY_A', 'ARRAY_A');

/**
 * @since 0.71
 */
define('ARRAY_N', 'ARRAY_N');

//Fallback functions
if (!function_exists('wp_load_translations_early')) {
	function wp_load_translations_early() {
		return true;
	}
}

/**
 * WordPress Database Access Abstraction Object
 *
 * It is possible to replace this class with your own
 * by setting the $wpdb global variable in wp-content/db.php
 * file to your class. The wpdb class will still be included,
 * so you can extend it or simply use your own.
 *
 * @link http://codex.wordpress.org/Function_Reference/wpdb_Class
 *
 * @package WordPress
 * @subpackage Database
 * @since 0.71
 */
class wpdb {

	/**
	 * Whether to show SQL/DB errors.
	 *
	 * Default behavior is to show errors if both WP_DEBUG and WP_DEBUG_DISPLAY
	 * evaluated to true.
	 *
	 * @since 0.71
	 * @access private
	 * @var bool
	 */
	var $show_errors = false;

	/**
	 * Whether to suppress errors during the DB bootstrapping.
	 *
	 * @access private
	 * @since 2.5.0
	 * @var bool
	 */
	var $suppress_errors = false;

	/**
	 * The last error during query.
	 *
	 * @since 2.5.0
	 * @var string
	 */
	public $last_error = '';

	/**
	 * Amount of queries made
	 *
	 * @since 1.2.0
	 * @access private
	 * @var int
	 */
	var $num_queries = 0;

	/**
	 * Count of rows returned by previous query
	 *
	 * @since 0.71
	 * @access private
	 * @var int
	 */
	var $num_rows = 0;

	/**
	 * Count of affected rows by previous query
	 *
	 * @since 0.71
	 * @access private
	 * @var int
	 */
	var $rows_affected = 0;

	/**
	 * The ID generated for an AUTO_INCREMENT column by the previous query (usually INSERT).
	 *
	 * @since 0.71
	 * @access public
	 * @var int
	 */
	var $insert_id = 0;

	/**
	 * Last query made
	 *
	 * @since 0.71
	 * @access private
	 * @var array
	 */
	var $last_query;

	/**
	 * Results of the last query made
	 *
	 * @since 0.71
	 * @access private
	 * @var array|null
	 */
	var $last_result;

	/**
	 * MySQL result, which is either a resource or boolean.
	 *
	 * @since 0.71
	 * @access protected
	 * @var mixed
	 */
	protected $result;

	/**
	 * Saved info on the table column
	 *
	 * @since 0.71
	 * @access protected
	 * @var array
	 */
	protected $col_info;

	/**
	 * Saved queries that were executed
	 *
	 * @since 1.5.0
	 * @access private
	 * @var array
	 */
	var $queries;

	/**
	 * The number of times to retry reconnecting before dying.
	 *
	 * @since 3.9.0
	 * @access protected
	 * @see wpdb::check_connection()
	 * @var int
	 */
	protected $reconnect_retries = 5;

	/**
	 * WordPress table prefix
	 *
	 * You can set this to have multiple WordPress installations
	 * in a single database. The second reason is for possible
	 * security precautions.
	 *
	 * @since 2.5.0
	 * @access private
	 * @var string
	 */
	var $prefix = '';

	/**
	 * WordPress base table prefix.
	 *
	 * @since 3.0.0
	 * @access public
	 * @var string
	 */
	public $base_prefix;

	/**
	 * Whether the database queries are ready to start executing.
	 *
	 * @since 2.3.2
	 * @access private
	 * @var bool
	 */
	var $ready = false;

	/**
	 * {@internal Missing Description}}
	 *
	 * @since 3.0.0
	 * @access public
	 * @var int
	 */
	public $blogid = 0;

	/**
	 * {@internal Missing Description}}
	 *
	 * @since 3.0.0
	 * @access public
	 * @var int
	 */
	public $siteid = 0;

	/**
	 * List of WordPress per-blog tables
	 *
	 * @since 2.5.0
	 * @access private
	 * @see wpdb::tables()
	 * @var array
	 */
	var $tables = array('posts', 'comments', 'links', 'options', 'postmeta',
		'terms', 'term_taxonomy', 'term_relationships', 'commentmeta');

	/**
	 * List of deprecated WordPress tables
	 *
	 * categories, post2cat, and link2cat were deprecated in 2.3.0, db version 5539
	 *
	 * @since 2.9.0
	 * @access private
	 * @see wpdb::tables()
	 * @var array
	 */
	var $old_tables = array('categories', 'post2cat', 'link2cat');

	/**
	 * List of WordPress global tables
	 *
	 * @since 3.0.0
	 * @access private
	 * @see wpdb::tables()
	 * @var array
	 */
	var $global_tables = array('users', 'usermeta');

	/**
	 * List of Multisite global tables
	 *
	 * @since 3.0.0
	 * @access private
	 * @see wpdb::tables()
	 * @var array
	 */
	var $ms_global_tables = array('blogs', 'signups', 'site', 'sitemeta',
		'sitecategories', 'registration_log', 'blog_versions');

	/**
	 * WordPress Comments table
	 *
	 * @since 1.5.0
	 * @access public
	 * @var string
	 */
	public $comments;

	/**
	 * WordPress Comment Metadata table
	 *
	 * @since 2.9.0
	 * @access public
	 * @var string
	 */
	public $commentmeta;

	/**
	 * WordPress Links table
	 *
	 * @since 1.5.0
	 * @access public
	 * @var string
	 */
	public $links;

	/**
	 * WordPress Options table
	 *
	 * @since 1.5.0
	 * @access public
	 * @var string
	 */
	public $options;

	/**
	 * WordPress Post Metadata table
	 *
	 * @since 1.5.0
	 * @access public
	 * @var string
	 */
	public $postmeta;

	/**
	 * WordPress Posts table
	 *
	 * @since 1.5.0
	 * @access public
	 * @var string
	 */
	public $posts;

	/**
	 * WordPress Terms table
	 *
	 * @since 2.3.0
	 * @access public
	 * @var string
	 */
	public $terms;

	/**
	 * WordPress Term Relationships table
	 *
	 * @since 2.3.0
	 * @access public
	 * @var string
	 */
	public $term_relationships;

	/**
	 * WordPress Term Taxonomy table
	 *
	 * @since 2.3.0
	 * @access public
	 * @var string
	 */
	public $term_taxonomy;

	/*
		 * Global and Multisite tables
	*/

	/**
	 * WordPress User Metadata table
	 *
	 * @since 2.3.0
	 * @access public
	 * @var string
	 */
	public $usermeta;

	/**
	 * WordPress Users table
	 *
	 * @since 1.5.0
	 * @access public
	 * @var string
	 */
	public $users;

	/**
	 * Multisite Blogs table
	 *
	 * @since 3.0.0
	 * @access public
	 * @var string
	 */
	public $blogs;

	/**
	 * Multisite Blog Versions table
	 *
	 * @since 3.0.0
	 * @access public
	 * @var string
	 */
	public $blog_versions;

	/**
	 * Multisite Registration Log table
	 *
	 * @since 3.0.0
	 * @access public
	 * @var string
	 */
	public $registration_log;

	/**
	 * Multisite Signups table
	 *
	 * @since 3.0.0
	 * @access public
	 * @var string
	 */
	public $signups;

	/**
	 * Multisite Sites table
	 *
	 * @since 3.0.0
	 * @access public
	 * @var string
	 */
	public $site;

	/**
	 * Multisite Sitewide Terms table
	 *
	 * @since 3.0.0
	 * @access public
	 * @var string
	 */
	public $sitecategories;

	/**
	 * Multisite Site Metadata table
	 *
	 * @since 3.0.0
	 * @access public
	 * @var string
	 */
	public $sitemeta;

	/**
	 * Format specifiers for DB columns. Columns not listed here default to %s. Initialized during WP load.
	 *
	 * Keys are column names, values are format types: 'ID' => '%d'
	 *
	 * @since 2.8.0
	 * @see wpdb::prepare()
	 * @see wpdb::insert()
	 * @see wpdb::update()
	 * @see wpdb::delete()
	 * @see wp_set_wpdb_vars()
	 * @access public
	 * @var array
	 */
	public $field_types = array();

	/**
	 * Database table columns charset
	 *
	 * @since 2.2.0
	 * @access public
	 * @var string
	 */
	public $charset;

	/**
	 * Database table columns collate
	 *
	 * @since 2.2.0
	 * @access public
	 * @var string
	 */
	public $collate;

	/**
	 * Database Username
	 *
	 * @since 2.9.0
	 * @access protected
	 * @var string
	 */
	protected $dbuser;

	/**
	 * Database Password
	 *
	 * @since 3.1.0
	 * @access protected
	 * @var string
	 */
	protected $dbpassword;

	/**
	 * Database Name
	 *
	 * @since 3.1.0
	 * @access protected
	 * @var string
	 */
	protected $dbname;

	/**
	 * Database Host
	 *
	 * @since 3.1.0
	 * @access protected
	 * @var string
	 */
	protected $dbhost;

	/**
	 * Database Handle
	 *
	 * @since 0.71
	 * @access protected
	 * @var string
	 */
	protected $dbh;

	/**
	 * A textual description of the last query/get_row/get_var call
	 *
	 * @since 3.0.0
	 * @access public
	 * @var string
	 */
	public $func_call;

	/**
	 * Whether MySQL is used as the database engine.
	 *
	 * Set in WPDB::db_connect() to true, by default. This is used when checking
	 * against the required MySQL version for WordPress. Normally, a replacement
	 * database drop-in (db.php) will skip these checks, but setting this to true
	 * will force the checks to occur.
	 *
	 * @since 3.3.0
	 * @access public
	 * @var bool
	 */
	public $is_mysql = null;

	/**
	 * A list of incompatible SQL modes.
	 *
	 * @since 3.9.0
	 * @access protected
	 * @var array
	 */
	protected $incompatible_modes = array('NO_ZERO_DATE', 'ONLY_FULL_GROUP_BY',
		'STRICT_TRANS_TABLES', 'STRICT_ALL_TABLES', 'TRADITIONAL');

	/**
	 * Whether to use mysqli over mysql.
	 *
	 * @since 3.9.0
	 * @access private
	 * @var bool
	 */
	private $use_mysqli = false;

	/**
	 * Whether we've managed to successfully connect at some point
	 *
	 * @since 3.9.0
	 * @access private
	 * @var bool
	 */
	private $has_connected = false;

	/**
	 * Connects to the database server and selects a database
	 *
	 * PHP5 style constructor for compatibility with PHP5. Does
	 * the actual setting up of the class properties and connection
	 * to the database.
	 *
	 * @link https://core.trac.wordpress.org/ticket/3354
	 * @since 2.0.8
	 *
	 * @param string $dbuser MySQL database user
	 * @param string $dbpassword MySQL database password
	 * @param string $dbname MySQL database name
	 * @param string $dbhost MySQL database host
	 */
	public function __construct($dbuser, $dbpassword, $dbname, $dbhost) {
		register_shutdown_function(array($this, '__destruct'));

		if (WP_DEBUG && WP_DEBUG_DISPLAY) {
			$this->show_errors();
		}

		/* Use ext/mysqli if it exists and:
			 *  - WP_USE_EXT_MYSQL is defined as false, or
			 *  - We are a development version of WordPress, or
			 *  - We are running PHP 5.5 or greater, or
			 *  - ext/mysql is not loaded.
		*/
		if (function_exists('mysqli_connect')) {
			if (defined('WP_USE_EXT_MYSQL')) {
				$this->use_mysqli = !WP_USE_EXT_MYSQL;
			} elseif (version_compare(phpversion(), '5.5', '>=') || !function_exists('mysql_connect')) {
				$this->use_mysqli = true;
			} elseif (!empty($GLOBALS['wp_version']) && false !== strpos($GLOBALS['wp_version'], '-')) {
				$this->use_mysqli = true;
			}
		}

		$this->init_charset();

		$this->dbuser = $dbuser;
		$this->dbpassword = $dbpassword;
		$this->dbname = $dbname;
		$this->dbhost = $dbhost;

		// wp-config.php creation will manually connect when ready.
		if (defined('WP_SETUP_CONFIG')) {
			return;
		}

		$this->db_connect();
	}

	/**
	 * PHP5 style destructor and will run when database object is destroyed.
	 *
	 * @see wpdb::__construct()
	 * @since 2.0.8
	 * @return bool true
	 */
	public function __destruct() {
		return true;
	}

	/**
	 * PHP5 style magic getter, used to lazy-load expensive data.
	 *
	 * @since 3.5.0
	 *
	 * @param string $name The private member to get, and optionally process
	 * @return mixed The private member
	 */
	public function __get($name) {
		if ('col_info' === $name) {
			$this->load_col_info();
		}

		return $this->$name;
	}

	/**
	 * Magic function, for backwards compatibility.
	 *
	 * @since 3.5.0
	 *
	 * @param string $name  The private member to set
	 * @param mixed  $value The value to set
	 */
	public function __set($name, $value) {
		$this->$name = $value;
	}

	/**
	 * Magic function, for backwards compatibility.
	 *
	 * @since 3.5.0
	 *
	 * @param string $name  The private member to check
	 *
	 * @return bool If the member is set or not
	 */
	public function __isset($name) {
		return isset($this->$name);
	}

	/**
	 * Magic function, for backwards compatibility.
	 *
	 * @since 3.5.0
	 *
	 * @param string $name  The private member to unset
	 */
	public function __unset($name) {
		unset($this->$name);
	}

	/**
	 * Set $this->charset and $this->collate
	 *
	 * @since 3.1.0
	 */
	public function init_charset() {
		if (function_exists('is_multisite') && is_multisite()) {
			$this->charset = 'utf8';
			if (defined('DB_COLLATE') && DB_COLLATE) {
				$this->collate = DB_COLLATE;
			} else {
				$this->collate = 'utf8_general_ci';
			}

		} elseif (defined('DB_COLLATE')) {
			$this->collate = DB_COLLATE;
		}

		if (defined('DB_CHARSET')) {
			$this->charset = DB_CHARSET;
		}

	}

	/**
	 * Sets the connection's character set.
	 *
	 * @since 3.1.0
	 *
	 * @param resource $dbh     The resource given by mysql_connect
	 * @param string   $charset Optional. The character set. Default null.
	 * @param string   $collate Optional. The collation. Default null.
	 */
	public function set_charset($dbh, $charset = null, $collate = null) {
		if (!isset($charset)) {
			$charset = $this->charset;
		}

		if (!isset($collate)) {
			$collate = $this->collate;
		}

		if ($this->has_cap('collation') && !empty($charset)) {
			if ($this->use_mysqli) {
				if (function_exists('mysqli_set_charset') && $this->has_cap('set_charset')) {
					mysqli_set_charset($dbh, $charset);
				} else {
					$query = $this->prepare('SET NAMES %s', $charset);
					if (!empty($collate)) {
						$query .= $this->prepare(' COLLATE %s', $collate);
					}

					mysqli_query($query, $dbh);
				}
			} else {
				if (function_exists('mysql_set_charset') && $this->has_cap('set_charset')) {
					mysql_set_charset($charset, $dbh);
				} else {
					$query = $this->prepare('SET NAMES %s', $charset);
					if (!empty($collate)) {
						$query .= $this->prepare(' COLLATE %s', $collate);
					}

					mysql_query($query, $dbh);
				}
			}
		}
	}

	/**
	 * Change the current SQL mode, and ensure its WordPress compatibility.
	 *
	 * If no modes are passed, it will ensure the current MySQL server
	 * modes are compatible.
	 *
	 * @since 3.9.0
	 *
	 * @param array $modes Optional. A list of SQL modes to set.
	 */
	public function set_sql_mode($modes = array()) {
		if (empty($modes)) {
			if ($this->use_mysqli) {
				$res = mysqli_query($this->dbh, 'SELECT @@SESSION.sql_mode');
			} else {
				$res = mysql_query('SELECT @@SESSION.sql_mode', $this->dbh);
			}

			if (empty($res)) {
				return;
			}

			if ($this->use_mysqli) {
				$modes_array = mysqli_fetch_array($res);
				if (empty($modes_array[0])) {
					return;
				}
				$modes_str = $modes_array[0];
			} else {
				$modes_str = mysql_result($res, 0);
			}

			if (empty($modes_str)) {
				return;
			}

			$modes = explode(',', $modes_str);
		}

		$modes = array_change_key_case($modes, CASE_UPPER);

		/**
		 * Filter the list of incompatible SQL modes to exclude.
		 *
		 * @since 3.9.0
		 *
		 * @param array $incompatible_modes An array of incompatible modes.
		 */
		$incompatible_modes = (array) apply_filters('incompatible_sql_modes', $this->incompatible_modes);

		foreach ($modes as $i => $mode) {
			if (in_array($mode, $incompatible_modes)) {
				unset($modes[$i]);
			}
		}

		$modes_str = implode(',', $modes);

		if ( $this->use_mysqli ) {
			mysqli_query( $this->dbh, "SET SESSION sql_mode='$modes_str'" );
		} else {
			mysql_query( "SET SESSION sql_mode='$modes_str'", $this->dbh );
		}
	}

	/**
	 * Sets the table prefix for the WordPress tables.
	 *
	 * @since 2.5.0
	 *
	 * @param string $prefix Alphanumeric name for the new prefix.
	 * @param bool $set_table_names Optional. Whether the table names, e.g. wpdb::$posts, should be updated or not.
	 * @return string|WP_Error Old prefix or WP_Error on error
	 */
	public function set_prefix($prefix, $set_table_names = true) {

		if (preg_match('|[^a-z0-9_]|i', $prefix)) {
			return new WP_Error('invalid_db_prefix', 'Invalid database prefix');
		}

		$old_prefix = is_multisite() ? '' : $prefix;

		if (isset($this->base_prefix)) {
			$old_prefix = $this->base_prefix;
		}

		$this->base_prefix = $prefix;

		if ($set_table_names) {
			foreach ($this->tables('global') as $table => $prefixed_table) {
				$this->$table = $prefixed_table;
			}

			if (is_multisite() && empty($this->blogid)) {
				return $old_prefix;
			}

			$this->prefix = $this->get_blog_prefix();

			foreach ($this->tables('blog') as $table => $prefixed_table) {
				$this->$table = $prefixed_table;
			}

			foreach ($this->tables('old') as $table => $prefixed_table) {
				$this->$table = $prefixed_table;
			}

		}
		return $old_prefix;
	}

	/**
	 * Sets blog id.
	 *
	 * @since 3.0.0
	 * @access public
	 * @param int $blog_id
	 * @param int $site_id Optional.
	 * @return int previous blog id
	 */
	public function set_blog_id($blog_id, $site_id = 0) {
		if (!empty($site_id)) {
			$this->siteid = $site_id;
		}

		$old_blog_id = $this->blogid;
		$this->blogid = $blog_id;

		$this->prefix = $this->get_blog_prefix();

		foreach ($this->tables('blog') as $table => $prefixed_table) {
			$this->$table = $prefixed_table;
		}

		foreach ($this->tables('old') as $table => $prefixed_table) {
			$this->$table = $prefixed_table;
		}

		return $old_blog_id;
	}

	/**
	 * Gets blog prefix.
	 *
	 * @since 3.0.0
	 * @param int $blog_id Optional.
	 * @return string Blog prefix.
	 */
	public function get_blog_prefix($blog_id = null) {
		if (is_multisite()) {
			if (null === $blog_id) {
				$blog_id = $this->blogid;
			}

			$blog_id = (int) $blog_id;
			if (defined('MULTISITE') && (0 == $blog_id || 1 == $blog_id)) {
				return $this->base_prefix;
			} else {
				return $this->base_prefix . $blog_id . '_';
			}

		} else {
			return $this->base_prefix;
		}
	}

	/**
	 * Returns an array of WordPress tables.
	 *
	 * Also allows for the CUSTOM_USER_TABLE and CUSTOM_USER_META_TABLE to
	 * override the WordPress users and usermeta tables that would otherwise
	 * be determined by the prefix.
	 *
	 * The scope argument can take one of the following:
	 *
	 * 'all' - returns 'all' and 'global' tables. No old tables are returned.
	 * 'blog' - returns the blog-level tables for the queried blog.
	 * 'global' - returns the global tables for the installation, returning multisite tables only if running multisite.
	 * 'ms_global' - returns the multisite global tables, regardless if current installation is multisite.
	 * 'old' - returns tables which are deprecated.
	 *
	 * @since 3.0.0
	 * @uses wpdb::$tables
	 * @uses wpdb::$old_tables
	 * @uses wpdb::$global_tables
	 * @uses wpdb::$ms_global_tables
	 *
	 * @param string $scope Optional. Can be all, global, ms_global, blog, or old tables. Defaults to all.
	 * @param bool $prefix Optional. Whether to include table prefixes. Default true. If blog
	 * 	prefix is requested, then the custom users and usermeta tables will be mapped.
	 * @param int $blog_id Optional. The blog_id to prefix. Defaults to wpdb::$blogid. Used only when prefix is requested.
	 * @return array Table names. When a prefix is requested, the key is the unprefixed table name.
	 */
	public function tables($scope = 'all', $prefix = true, $blog_id = 0) {
		switch ($scope) {
		case 'all':
			$tables = array_merge($this->global_tables, $this->tables);
			if (is_multisite()) {
				$tables = array_merge($tables, $this->ms_global_tables);
			}

			break;
		case 'blog':
			$tables = $this->tables;
			break;
		case 'global':
			$tables = $this->global_tables;
			if (is_multisite()) {
				$tables = array_merge($tables, $this->ms_global_tables);
			}

			break;
		case 'ms_global':
			$tables = $this->ms_global_tables;
			break;
		case 'old':
			$tables = $this->old_tables;
			break;
		default:
			return array();
		}

		if ($prefix) {
			if (!$blog_id) {
				$blog_id = $this->blogid;
			}

			$blog_prefix = $this->get_blog_prefix($blog_id);
			$base_prefix = $this->base_prefix;
			$global_tables = array_merge($this->global_tables, $this->ms_global_tables);
			foreach ($tables as $k => $table) {
				if (in_array($table, $global_tables)) {
					$tables[$table] = $base_prefix . $table;
				} else {
					$tables[$table] = $blog_prefix . $table;
				}

				unset($tables[$k]);
			}

			if (isset($tables['users']) && defined('CUSTOM_USER_TABLE')) {
				$tables['users'] = CUSTOM_USER_TABLE;
			}

			if (isset($tables['usermeta']) && defined('CUSTOM_USER_META_TABLE')) {
				$tables['usermeta'] = CUSTOM_USER_META_TABLE;
			}

		}

		return $tables;
	}

	/**
	 * Selects a database using the current database connection.
	 *
	 * The database name will be changed based on the current database
	 * connection. On failure, the execution will bail and display an DB error.
	 *
	 * @since 0.71
	 *
	 * @param string $db MySQL database name
	 * @param resource $dbh Optional link identifier.
	 * @return null Always null.
	 */
	public function select($db, $dbh = null) {
		if (is_null($dbh)) {
			$dbh = $this->dbh;
		}

		if ($this->use_mysqli) {
			$success = @mysqli_select_db($dbh, $db);
		} else {
			$success = @mysql_select_db($db, $dbh);
		}
		if (!$success) {
			$this->ready = false;
			if (!did_action('template_redirect')) {
				wp_load_translations_early();
				die('We were not able to connect to the database server. Please recheck database name and try again');	//this lines has been modified
			}
			return;
		}
	}

	/**
	 * Do not use, deprecated.
	 *
	 * Use esc_sql() or wpdb::prepare() instead.
	 *
	 * @since 2.8.0
	 * @deprecated 3.6.0
	 * @see wpdb::prepare
	 * @see esc_sql()
	 * @access private
	 *
	 * @param string $string
	 * @return string
	 */
	function _weak_escape($string) {
		if (func_num_args() === 1 && function_exists('_deprecated_function')) {
			_deprecated_function(__METHOD__, '3.6', 'wpdb::prepare() or esc_sql()');
		}

		return addslashes($string);
	}

	/**
	 * Real escape, using mysqli_real_escape_string() or mysql_real_escape_string()
	 *
	 * @see mysqli_real_escape_string()
	 * @see mysql_real_escape_string()
	 * @since 2.8.0
	 * @access private
	 *
	 * @param  string $string to escape
	 * @return string escaped
	 */
	function _real_escape($string) {
		if ($this->dbh) {
			if ($this->use_mysqli) {
				return mysqli_real_escape_string($this->dbh, $string);
			} else {
				return mysql_real_escape_string($string, $this->dbh);
			}
		}

		$class = get_class($this);
		if (function_exists('__')) {
			_doing_it_wrong($class, sprintf(__('%s must set a database connection for use with escaping.'), $class), E_USER_NOTICE);
		} else {
			_doing_it_wrong($class, sprintf('%s must set a database connection for use with escaping.', $class), E_USER_NOTICE);
		}
		return addslashes($string);
	}

	/**
	 * Escape data. Works on arrays.
	 *
	 * @uses wpdb::_real_escape()
	 * @since  2.8.0
	 * @access private
	 *
	 * @param  string|array $data
	 * @return string|array escaped
	 */
	function _escape($data) {
		if (is_array($data)) {
			foreach ($data as $k => $v) {
				if (is_array($v)) {
					$data[$k] = $this->_escape($v);
				} else {
					$data[$k] = $this->_real_escape($v);
				}

			}
		} else {
			$data = $this->_real_escape($data);
		}

		return $data;
	}

	/**
	 * Do not use, deprecated.
	 *
	 * Use esc_sql() or wpdb::prepare() instead.
	 *
	 * @since 0.71
	 * @deprecated 3.6.0
	 * @see wpdb::prepare()
	 * @see esc_sql()
	 *
	 * @param mixed $data
	 * @return mixed
	 */
	public function escape($data) {
		if (func_num_args() === 1 && function_exists('_deprecated_function')) {
			_deprecated_function(__METHOD__, '3.6', 'wpdb::prepare() or esc_sql()');
		}

		if (is_array($data)) {
			foreach ($data as $k => $v) {
				if (is_array($v)) {
					$data[$k] = $this->escape($v, 'recursive');
				} else {
					$data[$k] = $this->_weak_escape($v, 'internal');
				}

			}
		} else {
			$data = $this->_weak_escape($data, 'internal');
		}

		return $data;
	}

	/**
	 * Escapes content by reference for insertion into the database, for security
	 *
	 * @uses wpdb::_real_escape()
	 * @since 2.3.0
	 * @param string $string to escape
	 * @return void
	 */
	public function escape_by_ref(&$string) {
		if (!is_float($string)) {
			$string = $this->_real_escape($string);
		}

	}

	/**
	 * Prepares a SQL query for safe execution. Uses sprintf()-like syntax.
	 *
	 * The following directives can be used in the query format string:
	 *   %d (integer)
	 *   %f (float)
	 *   %s (string)
	 *   %% (literal percentage sign - no argument needed)
	 *
	 * All of %d, %f, and %s are to be left unquoted in the query string and they need an argument passed for them.
	 * Literals (%) as parts of the query must be properly written as %%.
	 *
	 * This function only supports a small subset of the sprintf syntax; it only supports %d (integer), %f (float), and %s (string).
	 * Does not support sign, padding, alignment, width or precision specifiers.
	 * Does not support argument numbering/swapping.
	 *
	 * May be called like {@link http://php.net/sprintf sprintf()} or like {@link http://php.net/vsprintf vsprintf()}.
	 *
	 * Both %d and %s should be left unquoted in the query string.
	 *
	 *     wpdb::prepare( "SELECT * FROM `table` WHERE `column` = %s AND `field` = %d", 'foo', 1337 )
	 *     wpdb::prepare( "SELECT DATE_FORMAT(`field`, '%%c') FROM `table` WHERE `column` = %s", 'foo' );
	 *
	 * @link http://php.net/sprintf Description of syntax.
	 * @since 2.3.0
	 *
	 * @param string $query Query statement with sprintf()-like placeholders
	 * @param array|mixed $args The array of variables to substitute into the query's placeholders if being called like
	 * 	{@link http://php.net/vsprintf vsprintf()}, or the first variable to substitute into the query's placeholders if
	 * 	being called like {@link http://php.net/sprintf sprintf()}.
	 * @param mixed $args,... further variables to substitute into the query's placeholders if being called like
	 * 	{@link http://php.net/sprintf sprintf()}.
	 * @return null|false|string Sanitized query string, null if there is no query, false if there is an error and string
	 * 	if there was something to prepare
	 */
	public function prepare($query, $args) {
		if (is_null($query)) {
			return;
		}

		// This is not meant to be foolproof -- but it will catch obviously incorrect usage.
		if (strpos($query, '%') === false) {
			_doing_it_wrong('wpdb::prepare', sprintf(__('The query argument of %s must have a placeholder.'), 'wpdb::prepare()'), '3.9');
		}

		$args = func_get_args();
		array_shift($args);
		// If args were passed as an array (as in vsprintf), move them up
		if (isset($args[0]) && is_array($args[0])) {
			$args = $args[0];
		}

		$query = str_replace("'%s'", '%s', $query); // in case someone mistakenly already singlequoted it
		$query = str_replace('"%s"', '%s', $query); // doublequote unquoting
		$query = preg_replace('|(?<!%)%f|', '%F', $query); // Force floats to be locale unaware
		$query = preg_replace('|(?<!%)%s|', "'%s'", $query); // quote the strings, avoiding escaped strings like %%s
		array_walk($args, array($this, 'escape_by_ref'));
		return @vsprintf($query, $args);
	}

	/**
	 * First half of escaping for LIKE special characters % and _ before preparing for MySQL.
	 *
	 * Use this only before wpdb::prepare() or esc_sql().  Reversing the order is very bad for security.
	 *
	 * Example Prepared Statement:
	 *  $wild = '%';
	 *  $find = 'only 43% of planets';
	 *  $like = $wild . $wpdb->esc_like( $find ) . $wild;
	 *  $sql  = $wpdb->prepare( "SELECT * FROM $wpdb->posts WHERE post_content LIKE %s", $like );
	 *
	 * Example Escape Chain:
	 *  $sql  = esc_sql( $wpdb->esc_like( $input ) );
	 *
	 * @since 4.0.0
	 * @access public
	 *
	 * @param string $text The raw text to be escaped. The input typed by the user should have no
	 *                     extra or deleted slashes.
	 * @return string Text in the form of a LIKE phrase. The output is not SQL safe. Call $wpdb::prepare()
	 *                or real_escape next.
	 */
	public function esc_like($text) {
		return addcslashes($text, '_%\\');
	}

	/**
	 * Print SQL/DB error.
	 *
	 * @since 0.71
	 * @global array $EZSQL_ERROR Stores error information of query and error string
	 *
	 * @param string $str The error to display
	 * @return false|null False if the showing of errors is disabled.
	 */
	public function print_error($str = '') {
		global $EZSQL_ERROR;

		if (!$str) {
			if ($this->use_mysqli) {
				$str = mysqli_error($this->dbh);
			} else {
				$str = mysql_error($this->dbh);
			}
		}
		$EZSQL_ERROR[] = array('query' => $this->last_query, 'error_str' => $str);

		if ($this->suppress_errors) {
			return false;
		}

		wp_load_translations_early();

		if ($caller = $this->get_caller()) {
			$error_str = sprintf(__('WordPress database error %1$s for query %2$s made by %3$s'), $str, $this->last_query, $caller);
		} else {
			$error_str = sprintf(__('WordPress database error %1$s for query %2$s'), $str, $this->last_query);
		}

		error_log($error_str);

		// Are we showing errors?
		if (!$this->show_errors) {
			return false;
		}

		// If there is an error then take note of it
		if (is_multisite()) {
			$msg = "WordPress database error: [$str]\n{$this->last_query}\n";
			if (defined('ERRORLOGFILE')) {
				error_log($msg, 3, ERRORLOGFILE);
			}

			if (defined('DIEONDBERROR')) {
				die($msg);
			}

		} else {
			$str = htmlspecialchars($str, ENT_QUOTES);
			$query = htmlspecialchars($this->last_query, ENT_QUOTES);

			print "<div id='error'>
			<p class='wpdberror'><strong>WordPress database error:</strong> [$str]<br />
			<code>$query</code></p>
			</div>";
		}
	}

	/**
	 * Enables showing of database errors.
	 *
	 * This function should be used only to enable showing of errors.
	 * wpdb::hide_errors() should be used instead for hiding of errors. However,
	 * this function can be used to enable and disable showing of database
	 * errors.
	 *
	 * @since 0.71
	 * @see wpdb::hide_errors()
	 *
	 * @param bool $show Whether to show or hide errors
	 * @return bool Old value for showing errors.
	 */
	public function show_errors($show = true) {
		$errors = $this->show_errors;
		$this->show_errors = $show;
		return $errors;
	}

	/**
	 * Disables showing of database errors.
	 *
	 * By default database errors are not shown.
	 *
	 * @since 0.71
	 * @see wpdb::show_errors()
	 *
	 * @return bool Whether showing of errors was active
	 */
	public function hide_errors() {
		$show = $this->show_errors;
		$this->show_errors = false;
		return $show;
	}

	/**
	 * Whether to suppress database errors.
	 *
	 * By default database errors are suppressed, with a simple
	 * call to this function they can be enabled.
	 *
	 * @since 2.5.0
	 * @see wpdb::hide_errors()
	 * @param bool $suppress Optional. New value. Defaults to true.
	 * @return bool Old value
	 */
	public function suppress_errors($suppress = true) {
		$errors = $this->suppress_errors;
		$this->suppress_errors = (bool) $suppress;
		return $errors;
	}

	/**
	 * Kill cached query results.
	 *
	 * @since 0.71
	 * @return void
	 */
	public function flush() {
		$this->last_result = array();
		$this->col_info = null;
		$this->last_query = null;
		$this->rows_affected = $this->num_rows = 0;
		$this->last_error = '';

		if ($this->use_mysqli && $this->result instanceof mysqli_result) {
			mysqli_free_result($this->result);
			$this->result = null;

			// Sanity check before using the handle
			if (empty($this->dbh) || !($this->dbh instanceof mysqli)) {
				return;
			}

			// Clear out any results from a multi-query
			while (mysqli_more_results($this->dbh)) {
				mysqli_next_result($this->dbh);
			}
		} else if (is_resource($this->result)) {
			mysql_free_result($this->result);
		}
	}

	/**
	 * Connect to and select database.
	 *
	 * If $allow_bail is false, the lack of database connection will need
	 * to be handled manually.
	 *
	 * @since 3.0.0
	 * @since 3.9.0 $allow_bail parameter added.
	 *
	 * @param bool $allow_bail Optional. Allows the function to bail. Default true.
	 * @return null|bool True with a successful connection, false on failure.
	 */
	public function db_connect($allow_bail = true) {

		$this->is_mysql = true;

		/*
			 * Deprecated in 3.9+ when using MySQLi. No equivalent
			 * $new_link parameter exists for mysqli_* functions.
		*/
		$new_link = defined('MYSQL_NEW_LINK') ? MYSQL_NEW_LINK : true;
		$client_flags = defined('MYSQL_CLIENT_FLAGS') ? MYSQL_CLIENT_FLAGS : 0;

		if ($this->use_mysqli) {
			$this->dbh = mysqli_init();

			// mysqli_real_connect doesn't support the host param including a port or socket
			// like mysql_connect does. This duplicates how mysql_connect detects a port and/or socket file.
			$port = null;
			$socket = null;
			$host = $this->dbhost;
			$port_or_socket = strstr($host, ':');
			if (!empty($port_or_socket)) {
				$host = substr($host, 0, strpos($host, ':'));
				$port_or_socket = substr($port_or_socket, 1);
				if (0 !== strpos($port_or_socket, '/')) {
					$port = intval($port_or_socket);
					$maybe_socket = strstr($port_or_socket, ':');
					if (!empty($maybe_socket)) {
						$socket = substr($maybe_socket, 1);
					}
				} else {
					$socket = $port_or_socket;
				}
			}

			if (WP_DEBUG) {
				mysqli_real_connect($this->dbh, $host, $this->dbuser, $this->dbpassword, null, $port, $socket, $client_flags);
			} else {
				@mysqli_real_connect($this->dbh, $host, $this->dbuser, $this->dbpassword, null, $port, $socket, $client_flags);
			}

			if ($this->dbh->connect_errno) {
				$this->dbh = null;

				/* It's possible ext/mysqli is misconfigured. Fall back to ext/mysql if:
					 		 *  - We haven't previously connected, and
					 		 *  - WP_USE_EXT_MYSQL isn't set to false, and
					 		 *  - ext/mysql is loaded.
				*/
				$attempt_fallback = true;

				if ($this->has_connected) {
					$attempt_fallback = false;
				} else if (defined('WP_USE_EXT_MYSQL') && !WP_USE_EXT_MYSQL) {
					$attempt_fallback = false;
				} else if (!function_exists('mysql_connect')) {
					$attempt_fallback = false;
				}

				if ($attempt_fallback) {
					$this->use_mysqli = false;
					$this->db_connect();
				}
			}
		} else {
			if (WP_DEBUG) {
				$this->dbh = mysql_connect($this->dbhost, $this->dbuser, $this->dbpassword, $new_link, $client_flags);
			} else {
				$this->dbh = @mysql_connect($this->dbhost, $this->dbuser, $this->dbpassword, $new_link, $client_flags);
			}
		}

		if (!$this->dbh && $allow_bail) {
			wp_load_translations_early();

			// Load custom DB error template, if present.
			if (file_exists(WP_CONTENT_DIR . '/db-error.php')) {
				require_once WP_CONTENT_DIR . '/db-error.php';
				die();
			}

			$this->bail(sprintf(__("
<h1>Error establishing a database connection</h1>
<p>This either means that the username and password information in your <code>wp-config.php</code> file is incorrect or we can't contact the database server at <code>%s</code>. This could mean your host's database server is down.</p>
<ul>
	<li>Are you sure you have the correct username and password?</li>
	<li>Are you sure that you have typed the correct hostname?</li>
	<li>Are you sure that the database server is running?</li>
</ul>
<p>If you're unsure what these terms mean you should probably contact your host. If you still need help you can always visit the <a href='https://wordpress.org/support/'>WordPress Support Forums</a>.</p>
"), htmlspecialchars($this->dbhost, ENT_QUOTES)), 'db_connect_fail');

			return false;
		} else if ($this->dbh) {
			$this->has_connected = true;
			$this->set_charset($this->dbh);
			$this->set_sql_mode();
			$this->ready = true;
			$this->select($this->dbname, $this->dbh);

			return true;
		}

		return false;
	}

	/**
	 * Check that the connection to the database is still up. If not, try to reconnect.
	 *
	 * If this function is unable to reconnect, it will forcibly die, or if after the
	 * the template_redirect hook has been fired, return false instead.
	 *
	 * If $allow_bail is false, the lack of database connection will need
	 * to be handled manually.
	 *
	 * @since 3.9.0
	 *
	 * @param bool $allow_bail Optional. Allows the function to bail. Default true.
	 * @return bool|null True if the connection is up.
	 */
	public function check_connection($allow_bail = true) {
		if ($this->use_mysqli) {
			if (@mysqli_ping($this->dbh)) {
				return true;
			}
		} else {
			if (@mysql_ping($this->dbh)) {
				return true;
			}
		}

		$error_reporting = false;

		// Disable warnings, as we don't want to see a multitude of "unable to connect" messages
		if (WP_DEBUG) {
			$error_reporting = error_reporting();
			error_reporting($error_reporting & ~E_WARNING);
		}

		for ($tries = 1; $tries <= $this->reconnect_retries; $tries++) {
			// On the last try, re-enable warnings. We want to see a single instance of the
			// "unable to connect" message on the bail() screen, if it appears.
			if ($this->reconnect_retries === $tries && WP_DEBUG) {
				error_reporting($error_reporting);
			}

			if ($this->db_connect(false)) {
				if ($error_reporting) {
					error_reporting($error_reporting);
				}

				return true;
			}

			sleep(1);
		}

		// If template_redirect has already happened, it's too late for die()/dead_db().
		// Let's just return and hope for the best.
		if (did_action('template_redirect')) {
			return false;
		}

		if (!$allow_bail) {
			return false;
		}

		// We weren't able to reconnect, so we better bail.
		$this->bail(sprintf(("
<h1>Error reconnecting to the database</h1>
<p>This means that we lost contact with the database server at <code>%s</code>. This could mean your host's database server is down.</p>
<ul>
	<li>Are you sure that the database server is running?</li>
	<li>Are you sure that the database server is not under particularly heavy load?</li>
</ul>
<p>If you're unsure what these terms mean you should probably contact your host. If you still need help you can always visit the <a href='https://wordpress.org/support/'>WordPress Support Forums</a>.</p>
"), htmlspecialchars($this->dbhost, ENT_QUOTES)), 'db_connect_fail');

		// Call dead_db() if bail didn't die, because this database is no more. It has ceased to be (at least temporarily).
		dead_db();
	}

	/**
	 * Perform a MySQL database query, using current database connection.
	 *
	 * More information can be found on the codex page.
	 *
	 * @since 0.71
	 *
	 * @param string $query Database query
	 * @return int|false Number of rows affected/selected or false on error
	 */
	public function query($query) {
		if (!$this->ready) {
			return false;
		}

		/**
		 * Filter the database query.
		 *
		 * Some queries are made before the plugins have been loaded,
		 * and thus cannot be filtered with this method.
		 *
		 * @since 2.1.0
		 *
		 * @param string $query Database query.
		 */
		//$query = apply_filters('query', $query);			//custom change

		$this->flush();

		// Log how the function was called
		$this->func_call = "\$db->query(\"$query\")";

		// Keep track of the last query for debug..
		$this->last_query = $query;

		$this->_do_query($query);

		// MySQL server has gone away, try to reconnect
		$mysql_errno = 0;
		if (!empty($this->dbh)) {
			if ($this->use_mysqli) {
				$mysql_errno = mysqli_errno($this->dbh);
			} else {
				$mysql_errno = mysql_errno($this->dbh);
			}
		}

		if (empty($this->dbh) || 2006 == $mysql_errno) {
			if ($this->check_connection()) {
				$this->_do_query($query);
			} else {
				$this->insert_id = 0;
				return false;
			}
		}

		// If there is an error then take note of it..
		if ($this->use_mysqli) {
			$this->last_error = mysqli_error($this->dbh);
		} else {
			$this->last_error = mysql_error($this->dbh);
		}

		if ($this->last_error) {
			// Clear insert_id on a subsequent failed insert.
			if ($this->insert_id && preg_match('/^\s*(insert|replace)\s/i', $query)) {
				$this->insert_id = 0;
			}

			$this->print_error();
			return false;
		}

		if (preg_match('/^\s*(create|alter|truncate|drop)\s/i', $query)) {
			$return_val = $this->result;
		} elseif (preg_match('/^\s*(insert|delete|update|replace)\s/i', $query)) {
			if ($this->use_mysqli) {
				$this->rows_affected = mysqli_affected_rows($this->dbh);
			} else {
				$this->rows_affected = mysql_affected_rows($this->dbh);
			}
			// Take note of the insert_id
			if (preg_match('/^\s*(insert|replace)\s/i', $query)) {
				if ($this->use_mysqli) {
					$this->insert_id = mysqli_insert_id($this->dbh);
				} else {
					$this->insert_id = mysql_insert_id($this->dbh);
				}
			}
			// Return number of rows affected
			$return_val = $this->rows_affected;
		} else {
			$num_rows = 0;
			if ($this->use_mysqli && $this->result instanceof mysqli_result) {
				while ($row = @mysqli_fetch_object($this->result)) {
					$this->last_result[$num_rows] = $row;
					$num_rows++;
				}
			} else if (is_resource($this->result)) {
				while ($row = @mysql_fetch_object($this->result)) {
					$this->last_result[$num_rows] = $row;
					$num_rows++;
				}
			}

			// Log number of rows the query returned
			// and return number of rows selected
			$this->num_rows = $num_rows;
			$return_val = $num_rows;
		}

		return $return_val;
	}

	/**
	 * Internal function to perform the mysql_query() call.
	 *
	 * @since 3.9.0
	 *
	 * @access public
	 * @see wpdb::query()
	 *
	 * @param string $query The query to run.
	 */
	public function _do_query($query) {
		if (defined('SAVEQUERIES') && SAVEQUERIES) {
			$this->timer_start();
		}

		if ($this->use_mysqli) {
			$this->result = @mysqli_query($this->dbh, $query);
		} else {
			$this->result = @mysql_query($query, $this->dbh);
		}
		$this->num_queries++;

		if (defined('SAVEQUERIES') && SAVEQUERIES) {
			$this->queries[] = array($query, $this->timer_stop(), $this->get_caller());
		}
	}

	public function local_sync_do_query( $query ) {

		if ( ! empty( $this->dbh ) && $this->use_mysqli ) {
			return  mysqli_query( $this->dbh, $query, MYSQLI_USE_RESULT);
		} elseif ( ! empty( $this->dbh ) ) {
			 return mysql_query( $query, $this->dbh );
		}
	}

	/**
	 * Insert a row into a table.
	 *
	 *     wpdb::insert( 'table', array( 'column' => 'foo', 'field' => 'bar' ) )
	 *     wpdb::insert( 'table', array( 'column' => 'foo', 'field' => 1337 ), array( '%s', '%d' ) )
	 *
	 * @since 2.5.0
	 * @see wpdb::prepare()
	 * @see wpdb::$field_types
	 * @see wp_set_wpdb_vars()
	 *
	 * @param string $table table name
	 * @param array $data Data to insert (in column => value pairs). Both $data columns and $data values should be "raw" (neither should be SQL escaped).
	 * @param array|string $format Optional. An array of formats to be mapped to each of the value in $data. If string, that format will be used for all of the values in $data.
	 * 	A format is one of '%d', '%f', '%s' (integer, float, string). If omitted, all values in $data will be treated as strings unless otherwise specified in wpdb::$field_types.
	 * @return int|false The number of rows inserted, or false on error.
	 */
	public function insert($table, $data, $format = null) {
		return $this->_insert_replace_helper($table, $data, $format, 'INSERT');
	}

	/**
	 * Replace a row into a table.
	 *
	 *     wpdb::replace( 'table', array( 'column' => 'foo', 'field' => 'bar' ) )
	 *     wpdb::replace( 'table', array( 'column' => 'foo', 'field' => 1337 ), array( '%s', '%d' ) )
	 *
	 * @since 3.0.0
	 * @see wpdb::prepare()
	 * @see wpdb::$field_types
	 * @see wp_set_wpdb_vars()
	 *
	 * @param string $table table name
	 * @param array $data Data to insert (in column => value pairs). Both $data columns and $data values should be "raw" (neither should be SQL escaped).
	 * @param array|string $format Optional. An array of formats to be mapped to each of the value in $data. If string, that format will be used for all of the values in $data.
	 * 	A format is one of '%d', '%f', '%s' (integer, float, string). If omitted, all values in $data will be treated as strings unless otherwise specified in wpdb::$field_types.
	 * @return int|false The number of rows affected, or false on error.
	 */
	public function replace($table, $data, $format = null) {
		return $this->_insert_replace_helper($table, $data, $format, 'REPLACE');
	}

	/**
	 * Helper function for insert and replace.
	 *
	 * Runs an insert or replace query based on $type argument.
	 *
	 * @access private
	 * @since 3.0.0
	 * @see wpdb::prepare()
	 * @see wpdb::$field_types
	 * @see wp_set_wpdb_vars()
	 *
	 * @param string $table table name
	 * @param array $data Data to insert (in column => value pairs). Both $data columns and $data values should be "raw" (neither should be SQL escaped).
	 * @param array|string $format Optional. An array of formats to be mapped to each of the value in $data. If string, that format will be used for all of the values in $data.
	 * 	A format is one of '%d', '%f', '%s' (integer, float, string). If omitted, all values in $data will be treated as strings unless otherwise specified in wpdb::$field_types.
	 * @param string $type Optional. What type of operation is this? INSERT or REPLACE. Defaults to INSERT.
	 * @return int|false The number of rows affected, or false on error.
	 */
	function _insert_replace_helper($table, $data, $format = null, $type = 'INSERT') {
		if (!in_array(strtoupper($type), array('REPLACE', 'INSERT'))) {
			return false;
		}

		$this->insert_id = 0;
		$formats = $format = (array) $format;
		$fields = array_keys($data);
		$formatted_fields = array();
		foreach ($fields as $field) {
			if (!empty($format)) {
				$form = ($form = array_shift($formats)) ? $form : $format[0];
			} elseif (isset($this->field_types[$field])) {
				$form = $this->field_types[$field];
			} else {
				$form = '%s';
			}

			$formatted_fields[] = $form;
		}
		$sql = "{$type} INTO `$table` (`" . implode('`,`', $fields) . "`) VALUES (" . implode(",", $formatted_fields) . ")";
		return $this->query($this->prepare($sql, $data));
	}

	/**
	 * Update a row in the table
	 *
	 *     wpdb::update( 'table', array( 'column' => 'foo', 'field' => 'bar' ), array( 'ID' => 1 ) )
	 *     wpdb::update( 'table', array( 'column' => 'foo', 'field' => 1337 ), array( 'ID' => 1 ), array( '%s', '%d' ), array( '%d' ) )
	 *
	 * @since 2.5.0
	 * @see wpdb::prepare()
	 * @see wpdb::$field_types
	 * @see wp_set_wpdb_vars()
	 *
	 * @param string $table table name
	 * @param array $data Data to update (in column => value pairs). Both $data columns and $data values should be "raw" (neither should be SQL escaped).
	 * @param array $where A named array of WHERE clauses (in column => value pairs). Multiple clauses will be joined with ANDs. Both $where columns and $where values should be "raw".
	 * @param array|string $format Optional. An array of formats to be mapped to each of the values in $data. If string, that format will be used for all of the values in $data.
	 * 	A format is one of '%d', '%f', '%s' (integer, float, string). If omitted, all values in $data will be treated as strings unless otherwise specified in wpdb::$field_types.
	 * @param array|string $where_format Optional. An array of formats to be mapped to each of the values in $where. If string, that format will be used for all of the items in $where. A format is one of '%d', '%f', '%s' (integer, float, string). If omitted, all values in $where will be treated as strings.
	 * @return int|false The number of rows updated, or false on error.
	 */
	public function update($table, $data, $where, $format = null, $where_format = null) {
		if (!is_array($data) || !is_array($where)) {
			return false;
		}

		$formats = $format = (array) $format;
		$bits = $wheres = array();
		foreach ((array) array_keys($data) as $field) {
			if (!empty($format)) {
				$form = ($form = array_shift($formats)) ? $form : $format[0];
			} elseif (isset($this->field_types[$field])) {
				$form = $this->field_types[$field];
			} else {
				$form = '%s';
			}

			$bits[] = "`$field` = {$form}";
		}

		$where_formats = $where_format = (array) $where_format;
		foreach ((array) array_keys($where) as $field) {
			if (!empty($where_format)) {
				$form = ($form = array_shift($where_formats)) ? $form : $where_format[0];
			} elseif (isset($this->field_types[$field])) {
				$form = $this->field_types[$field];
			} else {
				$form = '%s';
			}

			$wheres[] = "`$field` = {$form}";
		}

		$sql = "UPDATE `$table` SET " . implode(', ', $bits) . ' WHERE ' . implode(' AND ', $wheres);
		return $this->query($this->prepare($sql, array_merge(array_values($data), array_values($where))));
	}

	/**
	 * Delete a row in the table
	 *
	 *     wpdb::delete( 'table', array( 'ID' => 1 ) )
	 *     wpdb::delete( 'table', array( 'ID' => 1 ), array( '%d' ) )
	 *
	 * @since 3.4.0
	 * @see wpdb::prepare()
	 * @see wpdb::$field_types
	 * @see wp_set_wpdb_vars()
	 *
	 * @param string $table table name
	 * @param array $where A named array of WHERE clauses (in column => value pairs). Multiple clauses will be joined with ANDs. Both $where columns and $where values should be "raw".
	 * @param array|string $where_format Optional. An array of formats to be mapped to each of the values in $where. If string, that format will be used for all of the items in $where. A format is one of '%d', '%f', '%s' (integer, float, string). If omitted, all values in $where will be treated as strings unless otherwise specified in wpdb::$field_types.
	 * @return int|false The number of rows updated, or false on error.
	 */
	public function delete($table, $where, $where_format = null) {
		if (!is_array($where)) {
			return false;
		}

		$wheres = array();

		$where_formats = $where_format = (array) $where_format;

		foreach (array_keys($where) as $field) {
			if (!empty($where_format)) {
				$form = ($form = array_shift($where_formats)) ? $form : $where_format[0];
			} elseif (isset($this->field_types[$field])) {
				$form = $this->field_types[$field];
			} else {
				$form = '%s';
			}

			$wheres[] = "$field = $form";
		}

		$sql = "DELETE FROM $table WHERE " . implode(' AND ', $wheres);
		return $this->query($this->prepare($sql, $where));
	}

	/**
	 * Retrieve one variable from the database.
	 *
	 * Executes a SQL query and returns the value from the SQL result.
	 * If the SQL result contains more than one column and/or more than one row, this function returns the value in the column and row specified.
	 * If $query is null, this function returns the value in the specified column and row from the previous SQL result.
	 *
	 * @since 0.71
	 *
	 * @param string|null $query Optional. SQL query. Defaults to null, use the result from the previous query.
	 * @param int $x Optional. Column of value to return. Indexed from 0.
	 * @param int $y Optional. Row of value to return. Indexed from 0.
	 * @return string|null Database query result (as string), or null on failure
	 */
	public function get_var($query = null, $x = 0, $y = 0) {
		$this->func_call = "\$db->get_var(\"$query\", $x, $y)";

		if ($query) {
			$this->query($query);
		}

		// Extract var out of cached results based x,y vals
		if (!empty($this->last_result[$y])) {
			$values = array_values(get_object_vars($this->last_result[$y]));
		}

		// If there is a value return it else return null
		return (isset($values[$x]) && $values[$x] !== '') ? $values[$x] : null;
	}

	/**
	 * Retrieve one row from the database.
	 *
	 * Executes a SQL query and returns the row from the SQL result.
	 *
	 * @since 0.71
	 *
	 * @param string|null $query SQL query.
	 * @param string $output Optional. one of ARRAY_A | ARRAY_N | OBJECT constants. Return an associative array (column => value, ...),
	 * 	a numerically indexed array (0 => value, ...) or an object ( ->column = value ), respectively.
	 * @param int $y Optional. Row to return. Indexed from 0.
	 * @return mixed Database query result in format specified by $output or null on failure
	 */
	public function get_row($query = null, $output = OBJECT, $y = 0) {
		$this->func_call = "\$db->get_row(\"$query\",$output,$y)";
		if ($query) {
			$this->query($query);
		} else {
			return null;
		}

		if (!isset($this->last_result[$y])) {
			return null;
		}

		if ($output == OBJECT) {
			return $this->last_result[$y] ? $this->last_result[$y] : null;
		} elseif ($output == ARRAY_A) {
			return $this->last_result[$y] ? get_object_vars($this->last_result[$y]) : null;
		} elseif ($output == ARRAY_N) {
			return $this->last_result[$y] ? array_values(get_object_vars($this->last_result[$y])) : null;
		} elseif (strtoupper($output) === OBJECT) {
			// Back compat for OBJECT being previously case insensitive.
			return $this->last_result[$y] ? $this->last_result[$y] : null;
		} else {
			$this->print_error(" \$db->get_row(string query, output type, int offset) -- Output type must be one of: OBJECT, ARRAY_A, ARRAY_N");
		}
	}

	/**
	 * Retrieve one column from the database.
	 *
	 * Executes a SQL query and returns the column from the SQL result.
	 * If the SQL result contains more than one column, this function returns the column specified.
	 * If $query is null, this function returns the specified column from the previous SQL result.
	 *
	 * @since 0.71
	 *
	 * @param string|null $query Optional. SQL query. Defaults to previous query.
	 * @param int $x Optional. Column to return. Indexed from 0.
	 * @return array Database query result. Array indexed from 0 by SQL result row number.
	 */
	public function get_col($query = null, $x = 0) {
		if ($query) {
			$this->query($query);
		}

		$new_array = array();
		// Extract the column values
		for ($i = 0, $j = count($this->last_result); $i < $j; $i++) {
			$new_array[$i] = $this->get_var(null, $x, $i);
		}
		return $new_array;
	}

	/**
	 * Retrieve an entire SQL result set from the database (i.e., many rows)
	 *
	 * Executes a SQL query and returns the entire SQL result.
	 *
	 * @since 0.71
	 *
	 * @param string $query SQL query.
	 * @param string $output Optional. Any of ARRAY_A | ARRAY_N | OBJECT | OBJECT_K constants. With one of the first three, return an array of rows indexed from 0 by SQL result row number.
	 * 	Each row is an associative array (column => value, ...), a numerically indexed array (0 => value, ...), or an object. ( ->column = value ), respectively.
	 * 	With OBJECT_K, return an associative array of row objects keyed by the value of each row's first column's value. Duplicate keys are discarded.
	 * @return mixed Database query results
	 */
	public function get_results($query = null, $output = OBJECT) {
		$this->func_call = "\$db->get_results(\"$query\", $output)";

		if ($query) {
			$this->query($query);
		} else {
			return null;
		}

		$new_array = array();
		if ($output == OBJECT) {
			// Return an integer-keyed array of row objects
			return $this->last_result;
		} elseif ($output == OBJECT_K) {
			// Return an array of row objects with keys from column 1
			// (Duplicates are discarded)
			foreach ($this->last_result as $row) {
				$var_by_ref = get_object_vars($row);
				$key = array_shift($var_by_ref);
				if (!isset($new_array[$key])) {
					$new_array[$key] = $row;
				}

			}
			return $new_array;
		} elseif ($output == ARRAY_A || $output == ARRAY_N) {
			// Return an integer-keyed array of...
			if ($this->last_result) {
				foreach ((array) $this->last_result as $row) {
					if ($output == ARRAY_N) {
						// ...integer-keyed row arrays
						$new_array[] = array_values(get_object_vars($row));
					} else {
						// ...column name-keyed row arrays
						$new_array[] = get_object_vars($row);
					}
				}
			}
			return $new_array;
		} elseif (strtoupper($output) === OBJECT) {
			// Back compat for OBJECT being previously case insensitive.
			return $this->last_result;
		}
		return null;
	}

	/**
	 * Load the column metadata from the last query.
	 *
	 * @since 3.5.0
	 *
	 * @access protected
	 */
	protected function load_col_info() {
		if ($this->col_info) {
			return;
		}

		if ($this->use_mysqli) {
			for ($i = 0; $i < @mysqli_num_fields($this->result); $i++) {
				$this->col_info[$i] = @mysqli_fetch_field($this->result);
			}
		} else {
			for ($i = 0; $i < @mysql_num_fields($this->result); $i++) {
				$this->col_info[$i] = @mysql_fetch_field($this->result, $i);
			}
		}
	}

	/**
	 * Retrieve column metadata from the last query.
	 *
	 * @since 0.71
	 *
	 * @param string $info_type Optional. Type one of name, table, def, max_length, not_null, primary_key, multiple_key, unique_key, numeric, blob, type, unsigned, zerofill
	 * @param int $col_offset Optional. 0: col name. 1: which table the col's in. 2: col's max length. 3: if the col is numeric. 4: col's type
	 * @return mixed Column Results
	 */
	public function get_col_info($info_type = 'name', $col_offset = -1) {
		$this->load_col_info();

		if ($this->col_info) {
			if ($col_offset == -1) {
				$i = 0;
				$new_array = array();
				foreach ((array) $this->col_info as $col) {
					$new_array[$i] = $col->{$info_type};
					$i++;
				}
				return $new_array;
			} else {
				return $this->col_info[$col_offset]->{$info_type};
			}
		}
	}

	/**
	 * Starts the timer, for debugging purposes.
	 *
	 * @since 1.5.0
	 *
	 * @return bool
	 */
	public function timer_start() {
		$this->time_start = time();
		return true;
	}

	/**
	 * Stops the debugging timer.
	 *
	 * @since 1.5.0
	 *
	 * @return float Total time spent on the query, in seconds
	 */
	public function timer_stop() {
		return (time() - $this->time_start);
	}

	/**
	 * Wraps errors in a nice header and footer and dies.
	 *
	 * Will not die if wpdb::$show_errors is false.
	 *
	 * @since 1.5.0
	 *
	 * @param string $message The Error message
	 * @param string $error_code Optional. A Computer readable string to identify the error.
	 * @return false|void
	 */
	public function bail($message, $error_code = '500') {
		if (!$this->show_errors) {
			if (class_exists('WP_Error')) {
				$this->error = new WP_Error($error_code, $message);
			} else {
				$this->error = $message;
			}

			return false;
		}
		die($message);
	}

	/**
	 * Whether MySQL database is at least the required minimum version.
	 *
	 * @since 2.5.0
	 * @uses $wp_version
	 * @uses $required_mysql_version
	 *
	 * @return WP_Error
	 */
	public function check_database_version() {
		global $wp_version, $required_mysql_version;
		// Make sure the server has the required MySQL version
		if (version_compare($this->db_version(), $required_mysql_version, '<')) {
			return new WP_Error('database_version', sprintf(__('<strong>ERROR</strong>: WordPress %1$s requires MySQL %2$s or higher'), $wp_version, $required_mysql_version));
		}

	}

	/**
	 * Whether the database supports collation.
	 *
	 * Called when WordPress is generating the table scheme.
	 *
	 * @since 2.5.0
	 * @deprecated 3.5.0
	 * @deprecated Use wpdb::has_cap( 'collation' )
	 *
	 * @return bool True if collation is supported, false if version does not
	 */
	public function supports_collation() {
		_deprecated_function(__FUNCTION__, '3.5', 'wpdb::has_cap( \'collation\' )');
		return $this->has_cap('collation');
	}

	/**
	 * The database character collate.
	 *
	 * @since 3.5.0
	 *
	 * @return string The database character collate.
	 */
	public function get_charset_collate() {
		$charset_collate = '';

		if (!empty($this->charset)) {
			$charset_collate = "DEFAULT CHARACTER SET $this->charset";
		}

		if (!empty($this->collate)) {
			$charset_collate .= " COLLATE $this->collate";
		}

		return $charset_collate;
	}

	/**
	 * Determine if a database supports a particular feature.
	 *
	 * @since 2.7.0
	 * @since 4.1.0 Support was added for the 'utf8mb4' feature.
	 *
	 * @see wpdb::db_version()
	 *
	 * @param string $db_cap The feature to check for. Accepts 'collation',
	 *                       'group_concat', 'subqueries', 'set_charset',
	 *                       or 'utf8mb4'.
	 * @return bool Whether the database feature is supported, false otherwise.
	 */
	public function has_cap($db_cap) {
		$version = $this->db_version();

		switch (strtolower($db_cap)) {
		case 'collation': // @since 2.5.0
		case 'group_concat': // @since 2.7.0
		case 'subqueries': // @since 2.7.0
			return version_compare($version, '4.1', '>=');
		case 'set_charset':
			return version_compare($version, '5.0.7', '>=');
		case 'utf8mb4': // @since 4.1.0
			return version_compare($version, '5.5.3', '>=');
		}

		return false;
	}

	/**
	 * Retrieve the name of the function that called wpdb.
	 *
	 * Searches up the list of functions until it reaches
	 * the one that would most logically had called this method.
	 *
	 * @since 2.5.0
	 *
	 * @return string The name of the calling function
	 */
	public function get_caller() {
		//return wp_debug_backtrace_summary( __CLASS__ );			//custom change
		return '';
	}

	/**
	 * The database version number.
	 *
	 * @since 2.7.0
	 *
	 * @return null|string Null on failure, version number on success.
	 */
	public function db_version() {
		if ($this->use_mysqli) {
			$server_info = mysqli_get_server_info($this->dbh);
		} else {
			$server_info = mysql_get_server_info($this->dbh);
		}
		return preg_replace('/[^0-9.].*/', '', $server_info);
	}
}
