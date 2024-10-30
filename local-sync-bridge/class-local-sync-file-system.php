<?php

/**
 * 
 */
class LocalSyncFileSystem
{
	public $last_error;
	public $DOWNLOAD_CHUNK_SIZE = LOCAL_SYNC_DOWNLOAD_CHUNK_SIZE;
	public $UPLOAD_CHUNK_SIZE = LOCAL_SYNC_UPLOAD_CHUNK_SIZE;

	public function __construct() {
		$this->local_sync_options = new Local_Sync_Options();
	}

	public function wp_remote_post_local_sync_for_download($url, $post_body = array()) {

		if(!function_exists('wp_remote_post')){

			return false;
		}

		$post_body['is_local_sync'] = true;
		$post_body['prod_key_random_id'] = $this->local_sync_options->get_option('prod_key_random_id');

		$response = wp_remote_post( $url, array(
			'method' => 'POST',
			'timeout' => 60,
			'redirection' => 5,
			'sslverify' => false,
			'blocking' => true,
			'headers'     => array(
				'Connection' => 'Keep-Alive',
				'Keep-Alive' => 115,
				// 'content-type'  => 'application/binary',
			),
			'body' => base64_encode(json_encode($post_body)),
			'cookies' => array()
		    )
		);

		// local_sync_log($response, "--------wp_remote_post_local_sync_for_download-full_response----$startRange-$endRange---");

		$callResponse = wp_remote_retrieve_body( $response );

		$response = parse_local_sync_response_from_raw_data_php($callResponse);

		// local_sync_log($response, "-----wp_remote_post_local_sync_for_download---response-----$url---");

		$json_decoded_response = json_decode($response, true);

		if(empty($json_decoded_response)){
			local_sync_log($post_body, "--------post_body--wp_remote_post_local_sync_for_download--false----");
		}

		return $json_decoded_response;
	}

	public function away_post_call_get_file_data($url, &$fp, $startRange, $endRange, $file_name, $post_body = array()) {

		local_sync_log($file_name, "--------away_post_call_get_file_data--------");

		$dir_separator_replaced_abspath = wp_normalize_path(ABSPATH);
		$file_name_from_abspath = str_replace($dir_separator_replaced_abspath, '', $file_name);

		$post_body = array(
			'action' => 'get_file_data',
			'file_name' => $file_name_from_abspath,
			'startRange' => $startRange,
			'endRange' => $endRange,
		);

		$url = $this->local_sync_options->get_option('prod_site_url');
		$response = $this->wp_remote_post_local_sync_for_download($url, $post_body);

		if( empty($response) || 
			( !empty($response) && !empty($response['error']) ) ){
			local_sync_log($response, "--------away_post_call_get_file_data--error------");

			local_sync_die_with_json_encode(array(
				'error' => 'Not able to get file data for downloading the file',
				'sync_sub_action' => $this->local_sync_options->get_option('sync_sub_action'),
				'sync_current_action' => $this->local_sync_options->get_option('sync_current_action'),
				'requires_next_call' => false
			));

			return false;
		}

		$total_file_size = $response['total_file_size'];

		local_sync_log($total_file_size, "------response--total_file_size--------");

		$file_data = hex2bin($response['file_data']);

		$currentOffest = (empty($startRange)) ? 0 : $startRange;
		@fseek($fp, $currentOffest, SEEK_SET);
		@fwrite($fp, $file_data);
		
		// $info = curl_getinfo($ch);

		// $response = parse_local_sync_response_from_raw_data_php($response);

		// $http_code = wp_remote_retrieve_response_code($response);

		$call_again = false;
		if(filesize($file_name) < $total_file_size){
			$call_again = true;
		}

		local_sync_log($call_again, "-----away_post_call_get_file_data---call_again-----$file_name-$startRange-$endRange------$file_name_from_abspath------");
		local_sync_log($dir_separator_replaced_abspath, "-----abspath is-----");

		return array(
			'call_again' => $call_again,
			'http_code' => 200,
			'content_type' => 'Something'
		);
	}

	public function multi_call_download_using_curl($URL, $file, &$downloadResponseHeaders, $total_file_size, $prevResult = array(), $wpContentURL= false){
		if (!function_exists('curl_init') || !function_exists('curl_exec')){
			return false;
		}

		$backup_dir = dirname($file);
		if (!is_dir($backup_dir) && !mkdir($backup_dir, 0755)) {
			$this->last_error = "Could not create backup directory ($backup_dir)";
			return false;
		}

		if(!file_exists($file)){
			touch($file);
		}

		if(!file_exists($file)){

			local_sync_log($file, "--------not able to touch file 22--------");

			$this->last_error = "Could not touch download file ($file)";

			return false;
		}

		if(empty($prevResult['file'])){
			$fp = fopen ($file, 'wb');
		} else{
			$file = $prevResult['file'];
			$fp = fopen ($file, 'rb+');
			fseek($fp, $prevResult['startRange']);
		}
		if(!$fp){
			$this->last_error = "Could not open download file ($file)";

			local_sync_log('', "--------Could not open download file--------");

			return false;
		}

		$isBreak = false;
		$isMultiPart = false;

		$startRange = (empty($prevResult['startRange']) && empty($prevResult['file']))? 0 : $prevResult['startRange'];
		$endRange = (empty($prevResult['endRange']) && empty($prevResult['file']))? $this->DOWNLOAD_CHUNK_SIZE : $prevResult['endRange'];

		// if ($wpContentURL && !strpos($URL, $GLOBALS['WPVersionName'])) {
		// 	$URL = $wpContentURL.'/infinitewp/backups/'.$URL;
		// 	if (!empty($GLOBALS['URLParseData'])) {
		// 		$URL = $this->build_folder_auth_url($GLOBALS['URLParseData'], $URL);
		// 	}
		// }

		// if (strpos($URL, $GLOBALS['WPVersionName'])) {
		// 	$endRange = $total_file_size;
		// }

		status_losy("Downloading file ".$URL, $success=true, $return=false);
		status_losy("Total size of the file ".$total_file_size, $success=true, $return=false);
		status_losy("Download start from ".$startRange, $success=true, $return=false);

		if ( !empty($_REQUEST['oneShotdownlaod']) ) {
			$endRange = $total_file_size;
		}

		do{
			local_sync_log('', "--------sleeping  during download--------");

			$info = $this->away_post_call_get_file_data($URL, $fp, $startRange, $endRange, $file);

			if($info['call_again']){
				//multiCallDownloadUsingCURL($URL, $file, $downloadResponseHeaders);
				$isMultiPart = true;
				$startRange = ftell($fp);
				$endRange = ($startRange + $this->DOWNLOAD_CHUNK_SIZE);
				if($endRange >= $total_file_size){
					$endRange = $total_file_size;
				}
				if($startRange == $endRange){
					$isMultiPart = false;
				}
			}
			$rangeVariable = $startRange . '-' . $endRange;
			$isBreak = is_local_sync_timeout_cut();
		}
		while(!($isBreak) && $isMultiPart);

		fclose($fp);
		
		$currentResult = array();

		$this->initialize_response_array($currentResult);
		
		$currentResult['file'] = $file;
		$currentResult['startRange'] = $startRange;
		$currentResult['endRange'] = $endRange;

		status_losy("File Downloaded size:".$startRange, $success=true, $return=false);

		if($isBreak == true){
			$currentResult['status'] = 'partiallyCompleted';
			$currentResult['is_download_multi_call'] = true;
		}

		if(!$isMultiPart){
			$currentResult['is_download_multi_call'] = false;
		}

		$downloadResponseHeaders[] = "HTTP/1.1 ".$info['http_code']." SOMETHING";
		$downloadResponseHeaders[] = "Content-Type: ".$info['content_type'];

		return $currentResult;
	}

	public function build_folder_auth_url($URLParseData, $bkURL){
		if (!empty($URLParseData['user']) && !empty($URLParseData['pass'])) {
			$URLParts = parse_url($bkURL);
			$URLParts['user'] = $URLParseData['user'];
			$URLParts['pass'] = $URLParseData['pass'];
			$bkURL = $this->http_build_url_custom($URLParts);
			return $bkURL;
		}else{
			return $bkURL;
		}
	}

	public function http_build_url_custom($parts){
		
		if(is_array($parts['query'])){
			$parts['query'] = http_build_query($parts['query'], NULL, '&');
		}
		$URL = $parts['scheme'].'://'
			.($parts['user'] ? $parts['user'].':'.$parts['pass'].'@' : '')
			.$parts['host']
			.((!empty($parts['port']) && $parts['port'] != 80) ? ':'.$parts['port'] : '')
			.($parts['path'] ? $parts['path'] : '')
			.($parts['query'] ? '?'.$parts['query'] : '')
			.($parts['fragment'] ? '#'.$parts['fragment'] : '');
		return $URL;
	}

	public function initialize_response_array(&$response_arr){
		// $response_arr['db_table_prefix'] = $GLOBALS['db_table_prefix'];
		// $response_arr['temp_unzip_dir'] = $_REQUEST['temp_unzip_dir'];
		// $response_arr['temp_pclzip'] = $_REQUEST['temp_pclzip'];
		// $response_arr['bkfile'] = $_REQUEST['bkfile'];
		// $response_arr['extractParentHID'] = $_REQUEST['extractParentHID'];
		// $response_arr['is_download_multi_call'] = false;
		// $response_arr['is_file_append'] = false;
		// $response_arr['DBDetails']['DB_HOST'] = DB_HOST;
		// $response_arr['DBDetails']['DB_NAME'] = DB_NAME;
		// $response_arr['DBDetails']['DB_USER'] = DB_USER;
		// $response_arr['DBDetails']['DB_PASSWORD'] = DB_PASSWORD;
		// $response_arr['DBDetails']['prefix'] = $GLOBALS['db_table_prefix'];
		// $response_arr['dbModification'] = false;
		// $response_arr['URLParseData'] = $GLOBALS['URLParseData'];
		// $response_arr['wp_content_url'] = $GLOBALS['wp_content_url'];
		// $response_arr['is_new_backup'] = $GLOBALS['is_new_backup'];
		// $response_arr['isStagingToLive'] = $GLOBALS['isStagingToLive'];
		// $response_arr['isExistingSite'] = $GLOBALS['isExistingSite'];
		// $response_arr['dbBkFile'] = $GLOBALS['dbBkFile'];
		// $response_arr['backup_meta_files'] = $GLOBALS['backup_meta_files'];
		// $response_arr['WPVersionName'] = $GLOBALS['WPVersionName'];
		// $response_arr['oldURLReplacement'] = false;
		// $response_arr['next_extract_id'] = 0;
		// $response_arr['file_iterator'] = false;
		// $response_arr['isStaging'] = $_REQUEST['isStaging'];
		// $response_arr['oneShotdownlaod'] = $_REQUEST['oneShotdownlaod'];
		// $response_arr['status'] = 'completed';
		// $response_arr['break'] = false;

	}

	public function wp_remote_post_local_sync_for_upload($url, $post_body = array()) {
		if(!function_exists('wp_remote_post')){

			return false;
		}

		$post_body['is_local_sync'] = true;
		$post_body['prod_key_random_id'] = $this->local_sync_options->get_option('prod_key_random_id');

		$response = wp_remote_post( $url, array(
			'method' => 'POST',
			'timeout' => 30,
			'redirection' => 5,
			'sslverify' => false,
			'blocking' => true,
			'headers'     => array(
				// 'content-type'  => 'application/binary',
			),
			'body' => base64_encode(json_encode($post_body)),
			'cookies' => array()
		    )
		);

		// local_sync_log($response, "--------wp_remote_post_local_sync_for_upload-full_response-------");

		$response = wp_remote_retrieve_body( $response );

		$response = parse_local_sync_response_from_raw_data_php($response);

		local_sync_log($response, "-----wp_remote_post_local_sync_for_upload---response-----$url---");

		return json_decode($response, true);
	}

	public function multi_call_upload_using_curl($URL, $file, &$uploadResponseHeaders, $prevResult = array(), $wpContentURL= false, $file_list_or_full = 'full'){
		if (!function_exists('curl_init') || !function_exists('curl_exec')){
			return false;
		}
		local_sync_log($file, "----multi_call_upload_using_curl----file--------");

		if(!file_exists($file)){

			$this->last_error = "Local file list dump file not found for upload ($file)";
			
			return false;
		}

		if(empty($prevResult['file'])){
			$fp = fopen ($file, 'rb');
		} else{
			$file = $prevResult['file'];
			$fp = fopen ($file, 'rb');
			fseek($fp, $prevResult['startRange']);
		}

		if(!$fp){
			$this->last_error = "Could not open zip file for upload ($file)";

			return false;
		}

		$isBreak = false;
		$isMultiPart = false;

		$startRange = (empty($prevResult['startRange']) && empty($prevResult['file']))? 0 : $prevResult['startRange'];
		$endRange = (empty($prevResult['endRange']) && empty($prevResult['file']))? $this->UPLOAD_CHUNK_SIZE : $prevResult['endRange'];

		$total_file_size = filesize($file);
		
		status_losy("Uploading file ".$file, $success=true, $return=false);
		status_losy("Total size of the file ".$total_file_size, $success=true, $return=false);
		status_losy("Upload start from ".$startRange, $success=true, $return=false);

		if ( !empty($_REQUEST['oneShotdownlaod']) ) {
			$endRange = $total_file_size;
		}

		$current_sync_unique_id = $this->local_sync_options->get_option('current_sync_unique_id');

		$false_break = false;

		do{

			//read from file
			$currentOffest = (empty($startRange)) ? 0 : $startRange;
			@fseek($fp, $currentOffest, SEEK_SET);
			$file_data = @fread($fp, $this->UPLOAD_CHUNK_SIZE);

			// local_sync_log($file_data, "--------file_data_binary--------");

			$file_data_enc = bin2hex($file_data);

			$upload_action = 'handle_upload_file';

			// exit;
			if( $file_list_or_full == 'full' ){
				$upload_action = 'handle_upload_zip_file';
			}

			$post_arr = array(
				'is_local_sync' => true,
				'start_range' => $startRange,
				'end_range' => $endRange,
				'action' => $upload_action,
				'file_data' => $file_data_enc,
				'current_sync_unique_id' => $current_sync_unique_id,
			);

			$post_arr['prod_key_random_id'] = $this->local_sync_options->get_option('prod_key_random_id');

			$post_arr_copy = array(
				'is_local_sync' => true,
				'start_range' => $startRange,
				'end_range' => $endRange,
				'action' => $upload_action,
			);

			local_sync_log($post_arr_copy, "--------post_arr_copy--------");

			$callResponse = $this->wp_remote_post_local_sync_for_upload($URL, $post_arr);

			$info = array(
				'http_code' => 200,
				'content_type' => 'something'
			);

			// local_sync_log($info, "--------info--------");

			if( !empty($callResponse['success']) ){

				// local_sync_log('', "--------http_code is 206 ya--------");

				if(!empty($callResponse['file_size']) && $callResponse['file_size'] > $total_file_size){
					$this->last_error = 'Upload process corrupted.';

					$isBreak = true;
					$false_break = true;
				}

				//multiCallDownloadUsingCURL($URL, $file, $uploadResponseHeaders);
				$isMultiPart = true;
				$startRange = ftell($fp);
				// $startRange = $callResponse['file_size'];
				$endRange = ($startRange + $this->UPLOAD_CHUNK_SIZE);
				if($endRange >= $total_file_size){
					$endRange = $total_file_size;
				}
				if($startRange == $endRange){
					$isMultiPart = false;
				}
			} else {
				local_sync_log($callResponse, "--------multicall upload response error--------");
				$this->last_error = 'Upload process failed.';

				$isBreak = true;
				$false_break = true;
			}

			$rangeVariable = $startRange . '-' . $endRange;
			if(!$isBreak){
				$isBreak = is_local_sync_timeout_cut();
			}
		}
		while(!($isBreak) && $isMultiPart);

		fclose($fp);

		if($false_break){

			return false;
		}
		
		$currentResult = array();

		$currentResult['file'] = $file;
		$currentResult['startRange'] = $startRange;
		$currentResult['endRange'] = $endRange;

		status_losy("File Uploaded size:".$endRange, $success=true, $return=false);

		if($isBreak == true){
			$currentResult['status'] = 'partiallyCompleted';
			$currentResult['is_upload_multi_call'] = true;
		}

		if(!$isMultiPart){
			$currentResult['is_upload_multi_call'] = false;
		}

		$uploadResponseHeaders[] = "HTTP/1.1 ".$info['http_code']." SOMETHING";
		$uploadResponseHeaders[] = "Content-Type: ".$info['content_type'];
		return $currentResult;
	}

	public function single_big_file_upload_using_curl($URL, $file_obj, &$uploadResponseHeaders, $prev_uploaded_size = 0, $wpContentURL= false, $file_list_or_full = 'full'){
		if (!function_exists('curl_init') || !function_exists('curl_exec')){
			return false;
		}
		
		$file = $file_obj->file_full_path;

		local_sync_log($file, "----single_big_file_upload_using_curl----file--------");

		if(empty($prev_uploaded_size)){
			$fp = fopen ($file, 'rb');
		} else{
			$fp = fopen ($file, 'rb');
			fseek($fp, $prev_uploaded_size);
		}

		if(!$fp){
			$this->last_error = "Could not open zip file for upload ($file)";

			return false;
		}

		$isBreak = false;
		$isMultiPart = false;

		$total_file_size = filesize($file);

		$startRange = (empty($prev_uploaded_size)) ? 0 : $prev_uploaded_size;

		$newEndRange = $startRange + $this->UPLOAD_CHUNK_SIZE;
		$endRange = ($newEndRange > $total_file_size) ? $total_file_size : $newEndRange;
		
		status_losy("Uploading Big file ".$file, $success=true, $return=false);
		status_losy("Total size of the file ".$total_file_size, $success=true, $return=false);
		status_losy("Upload start from ".$startRange, $success=true, $return=false);

		$current_sync_unique_id = $this->local_sync_options->get_option('current_sync_unique_id');

		$false_break = false;

		do{

			//read from file
			$currentOffest = (empty($startRange)) ? 0 : $startRange;
			@fseek($fp, $currentOffest, SEEK_SET);
			$file_data = @fread($fp, $this->UPLOAD_CHUNK_SIZE);

			$gz_encoded_file_data = gzcompress($file_data, 9);
			$file_data_enc = bin2hex($gz_encoded_file_data);

			$upload_action = 'handle_upload_single_big_file';

			$post_arr = array(
				'is_local_sync' => true,
				'start_range' => $startRange,
				'end_range' => $endRange,
				'action' => $upload_action,
				'file_name' => $file_obj->add_as,
				'file_data' => $file_data_enc,
				'current_sync_unique_id' => $current_sync_unique_id,
			);

			$post_arr['prod_key_random_id'] = $this->local_sync_options->get_option('prod_key_random_id');

			$post_arr_copy = array(
				'is_local_sync' => true,
				'file_name' => $file_obj->add_as,
				'start_range' => $startRange,
				'end_range' => $endRange,
				'action' => $upload_action,
			);

			local_sync_log($post_arr_copy, "--------post_arr_copy--------");

			$callResponse = $this->wp_remote_post_local_sync_for_upload($URL, $post_arr);

			$info = array(
				'http_code' => 200,
				'content_type' => 'something'
			);

			// local_sync_log($info, "--------info--------");

			if( !empty($callResponse['success']) ){

				// local_sync_log('', "--------http_code is 206 ya--------");

				if(!empty($callResponse['file_size']) && $callResponse['file_size'] > $total_file_size){
					$this->last_error = 'Upload process corrupted.';

					$isBreak = true;
					$false_break = true;
				}

				//multiCallDownloadUsingCURL($URL, $file, $uploadResponseHeaders);
				$isMultiPart = true;
				$startRange = ftell($fp);
				// $startRange = $callResponse['file_size'];
				$endRange = ($startRange + $this->UPLOAD_CHUNK_SIZE);
				if($endRange >= $total_file_size){
					$endRange = $total_file_size;
				}
				if($startRange == $endRange){
					$isMultiPart = false;
				}
			} else {
				local_sync_log($callResponse, "--------multicall upload response error--------");
				$this->last_error = 'Upload process failed.';

				$isBreak = true;
				$false_break = true;
			}

			$rangeVariable = $startRange . '-' . $endRange;
			if(!$isBreak){
				$isBreak = is_local_sync_timeout_cut();
			}
		}
		while(!$isBreak && $isMultiPart);

		fclose($fp);

		if($false_break){

			return false;
		}
		
		$currentResult = array();

		$currentResult['file'] = $file;
		$currentResult['startRange'] = $startRange;
		$currentResult['endRange'] = $endRange;

		status_losy("File Uploaded size:".$endRange, $success=true, $return=false);

		if($isBreak == true){
			$currentResult['status'] = 'partiallyCompleted';
			$currentResult['is_upload_multi_call'] = true;
		}

		if(!$isMultiPart){
			$currentResult['is_upload_multi_call'] = false;
		}

		$uploadResponseHeaders[] = "HTTP/1.1 ".$info['http_code']." SOMETHING";
		$uploadResponseHeaders[] = "Content-Type: ".$info['content_type'];

		return $currentResult;
	}

	public function batch_files_upload_using_curl($URL, $files_this_batch, &$uploadResponseHeaders, $prevResult = array(), $wpContentURL= false, $file_list_or_full = 'full'){
		if (!function_exists('curl_init') || !function_exists('curl_exec')){
			return false;
		}

		local_sync_log(count($files_this_batch), "----batch_upload_using_curl----file--------");

		status_losy("Uploading batch of files ");

		$this_files_data_to_upload = [];

		foreach($files_this_batch as $vv){

			// $file = '/Applications/MAMP/htdocs/wp-site/vid-2.mp4';
			
			$file = $vv->file_full_path;
			$fp = fopen ($file, 'rb');
			$this_start_range = 0;
			fseek($fp, $this_start_range);

			if(!$fp){
				status_losy("Not able to open file for uploading ".$file, $success=true, $return=false);
			}

			$isBreak = false;
			$isMultiPart = false;

			$startRange = 0;
			$total_file_size = filesize($file);
			$endRange = $total_file_size;

			$current_sync_unique_id = $this->local_sync_options->get_option('current_sync_unique_id');

			$false_break = false;

			//read from file
			$currentOffest = (empty($startRange)) ? 0 : $startRange;
			@fseek($fp, $currentOffest, SEEK_SET);
			$file_data = @fread($fp, $this->UPLOAD_CHUNK_SIZE);

			$gz_encoded_file_data = gzcompress($file_data, 9);
			$file_data_enc = bin2hex($gz_encoded_file_data);

			$file_name_base_64 = base64_encode($vv->add_as);
			$this_files_data_to_upload[$file_name_base_64] = $file_data_enc;

			fclose($fp);

			if($false_break){

				return false;
			}
			
		}

		$upload_action = 'handle_batch_upload_files';

		$post_arr = array(
			'is_local_sync' => true,
			'start_range' => 1,
			'end_range' => 1,
			'action' => $upload_action,
			'files_data' => $this_files_data_to_upload,
			'current_sync_unique_id' => $current_sync_unique_id,
		);

		$post_arr['prod_key_random_id'] = $this->local_sync_options->get_option('prod_key_random_id');

		$post_arr_copy = array(
			'is_local_sync' => true,
			'start_range' => 1,
			'end_range' => 1,
			'action' => $upload_action,
		);

		local_sync_log($post_arr_copy, "--------post_arr_copy--------");

		$callResponse = $this->wp_remote_post_local_sync_for_upload($URL, $post_arr);

		$info = array(
			'http_code' => 200,
			'content_type' => 'something'
		);

		// local_sync_log($info, "--------info--------");

		if( !empty($callResponse['success']) ){

			// local_sync_log('', "--------http_code is 206 ya--------");

			if(false){
				$this->last_error = 'Upload process corrupted.';

				$isBreak = true;
				$false_break = true;
			}
			
		} else {
			local_sync_log($callResponse, "--------multicall upload response error--------");
			$this->last_error = 'Upload process failed.';

			$isBreak = true;
			$false_break = true;
		}

		$rangeVariable = $startRange . '-' . $endRange;
		if(!$isBreak){
			$isBreak = is_local_sync_timeout_cut();
		}

		$currentResult = array();

		$currentResult['file'] = $file;
		$currentResult['startRange'] = $startRange;
		$currentResult['endRange'] = $endRange;

		status_losy("File Uploaded size:".$endRange, $success=true, $return=false);

		if($isBreak == true){
			$currentResult['status'] = 'partiallyCompleted';
			$currentResult['is_upload_multi_call'] = true;
		}

		if(!$isMultiPart){
			$currentResult['is_upload_multi_call'] = false;
		}

		$uploadResponseHeaders[] = "HTTP/1.1 ".$info['http_code']." SOMETHING";
		$uploadResponseHeaders[] = "Content-Type: ".$info['content_type'];

		return $currentResult;
	}

	public function batch_files_download_using_curl($URL, &$uploadResponseHeaders, $wpContentURL= false, $file_list_or_full = 'full'){
		if (!function_exists('curl_init') || !function_exists('curl_exec')){
			return false;
		}

		local_sync_log($files_this_batch, "----batch_files_download_using_curl----file--------");

		status_losy("Downloading batch of files ");

		$this_files_data_to_upload = [];

		foreach($files_this_batch as $vv){

			// $file = '/Applications/MAMP/htdocs/wp-site/vid-2.mp4';
			
			$file = $vv->file_full_path;
			$fp = fopen ($file, 'rb');
			$this_start_range = 0;
			fseek($fp, $this_start_range);

			if(!$fp){
				status_losy("Not able to open file for uploading ".$file, $success=true, $return=false);
			}

			$isBreak = false;
			$isMultiPart = false;

			$startRange = 0;
			$total_file_size = filesize($file);
			$endRange = $total_file_size;

			$current_sync_unique_id = $this->local_sync_options->get_option('current_sync_unique_id');

			$false_break = false;

			//read from file
			$currentOffest = (empty($startRange)) ? 0 : $startRange;
			@fseek($fp, $currentOffest, SEEK_SET);
			$file_data = @fread($fp, $this->UPLOAD_CHUNK_SIZE);

			$gz_encoded_file_data = gzcompress($file_data, 9);
			$file_data_enc = bin2hex($gz_encoded_file_data);

			$file_name_base_64 = base64_encode($vv->add_as);
			$this_files_data_to_upload[$file_name_base_64] = $file_data_enc;

			fclose($fp);

			if($false_break){

				return false;
			}
			
		}

		$upload_action = 'handle_batch_upload_files';

		$post_arr = array(
			'is_local_sync' => true,
			'start_range' => 1,
			'end_range' => 1,
			'action' => $upload_action,
			'files_data' => $this_files_data_to_upload,
			'current_sync_unique_id' => $current_sync_unique_id,
		);

		$post_arr['prod_key_random_id'] = $this->local_sync_options->get_option('prod_key_random_id');

		$post_arr_copy = array(
			'is_local_sync' => true,
			'start_range' => 1,
			'end_range' => 1,
			'action' => $upload_action,
		);

		local_sync_log($post_arr_copy, "--------post_arr_copy--------");

		$callResponse = $this->wp_remote_post_local_sync_for_download($URL, $post_arr);

		$info = array(
			'http_code' => 200,
			'content_type' => 'something'
		);

		// local_sync_log($info, "--------info--------");

		if( !empty($callResponse['success']) ){

			// local_sync_log('', "--------http_code is 206 ya--------");

			if(false){
				$this->last_error = 'Upload process corrupted.';

				$isBreak = true;
				$false_break = true;
			}
			
		} else {
			local_sync_log($callResponse, "--------multicall upload response error--------");
			$this->last_error = 'Upload process failed.';

			$isBreak = true;
			$false_break = true;
		}

		$rangeVariable = $startRange . '-' . $endRange;
		if(!$isBreak){
			$isBreak = is_local_sync_timeout_cut();
		}

		$currentResult = array();

		$currentResult['file'] = $file;
		$currentResult['startRange'] = $startRange;
		$currentResult['endRange'] = $endRange;

		status_losy("File Uploaded size:".$endRange, $success=true, $return=false);

		if($isBreak == true){
			$currentResult['status'] = 'partiallyCompleted';
			$currentResult['is_upload_multi_call'] = true;
		}

		if(!$isMultiPart){
			$currentResult['is_upload_multi_call'] = false;
		}

		$uploadResponseHeaders[] = "HTTP/1.1 ".$info['http_code']." SOMETHING";
		$uploadResponseHeaders[] = "Content-Type: ".$info['content_type'];

		return $currentResult;
	}

	public function download_files_using_curl($URL){
		$current_sync_unique_id = $this->local_sync_options->get_option('current_sync_unique_id');

		$upload_action = 'handle_batch_download_files';

		$post_arr = array(
			'is_local_sync' => true,
			'start_range' => 1,
			'end_range' => 1,
			'action' => $upload_action,
			'current_sync_unique_id' => $current_sync_unique_id,
		);

		$post_arr['prod_key_random_id'] = $this->local_sync_options->get_option('prod_key_random_id');

		$post_arr_copy = array(
			'is_local_sync' => true,
			'start_range' => 1,
			'end_range' => 1,
			'action' => $upload_action,
		);

		local_sync_log($post_arr_copy, "--------post_arr_copy--------");

		$callResponse = $this->wp_remote_post_local_sync_for_download($URL, $post_arr);

		// local_sync_log($callResponse, "--------callResponse---download_files_using_curl-----");

		// local_sync_log('', "--------exiting by me 5--------");
		// exit;

		return $callResponse;
	}

	public function save_single_batch_file_during_download($URL, &$downloadResponseHeaders, $wpContentURL= false){
		if (!function_exists('curl_init') || !function_exists('curl_exec')){
			return false;
		}

		if(!file_exists($file)){
			touch($file);
		}

		if(!file_exists($file)){

			local_sync_log($file, "--------not able to touch file 22--------");

			$this->last_error = "Could not touch download file ($file)";

			return false;
		}

		if(empty($prevResult['file'])){
			$fp = fopen ($file, 'wb');
		} else{
			$file = $prevResult['file'];
			$fp = fopen ($file, 'rb+');
			fseek($fp, $prevResult['startRange']);
		}
		if(!$fp){
			$this->last_error = "Could not open download file ($file)";

			local_sync_log('', "--------Could not open download file--------");

			return false;
		}

		$isBreak = false;
		$isMultiPart = false;

		$startRange = (empty($prevResult['startRange']) && empty($prevResult['file']))? 0 : $prevResult['startRange'];
		$endRange = (empty($prevResult['endRange']) && empty($prevResult['file']))? $this->DOWNLOAD_CHUNK_SIZE : $prevResult['endRange'];

		status_losy("Downloading file ".$URL, $success=true, $return=false);
		status_losy("Total size of the file ".$total_file_size, $success=true, $return=false);
		status_losy("Download start from ".$startRange, $success=true, $return=false);

		do{
			local_sync_log('', "--------sleeping  during download--------");

			$info = $this->away_post_call_get_file_data($URL, $fp, $startRange, $endRange, $file);

			if($info['call_again']){
				//multiCallDownloadUsingCURL($URL, $file, $downloadResponseHeaders);
				$isMultiPart = true;
				$startRange = ftell($fp);
				$endRange = ($startRange + $this->DOWNLOAD_CHUNK_SIZE);
				if($endRange >= $total_file_size){
					$endRange = $total_file_size;
				}
				if($startRange == $endRange){
					$isMultiPart = false;
				}
			}
			$rangeVariable = $startRange . '-' . $endRange;
			$isBreak = is_local_sync_timeout_cut();
		}
		while(!($isBreak) && $isMultiPart);

		fclose($fp);
		
		$currentResult = array();

		$this->initialize_response_array($currentResult);
		
		$currentResult['file'] = $file;
		$currentResult['startRange'] = $startRange;
		$currentResult['endRange'] = $endRange;

		status_losy("File Downloaded size:".$startRange, $success=true, $return=false);

		if($isBreak == true){
			$currentResult['status'] = 'partiallyCompleted';
			$currentResult['is_download_multi_call'] = true;
		}

		if(!$isMultiPart){
			$currentResult['is_download_multi_call'] = false;
		}

		$downloadResponseHeaders[] = "HTTP/1.1 ".$info['http_code']." SOMETHING";
		$downloadResponseHeaders[] = "Content-Type: ".$info['content_type'];

		return $currentResult;
	}
}
