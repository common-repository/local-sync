<?php

class Local_Sync_Utils {

	public function __construct() {
		global $wpdb;

		$this->db = $wpdb;
		$this->bulk_limit = 500;
	}

	// public function createRecursiveFileSystemFolder($this_temp_folder, $this_absbath_length = null, $override_abspath_check = true) {

	// 	return true;
	// }
}
