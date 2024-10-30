var chosen_action_local_sync = '';
var RETRIES_LOCAL_SYNC = 0;
var SELECTED_MODIFIED_FILES = {};
var USER_SELECTION_MODIFIED_FILES = {};
var SHOW_FS_METHOD_ERROR_LOSY = false;
var LOCAL_SYNC_AJAX_RUNNING = false;

function import_steps_losy(steps_parent){
	var data = {
		'action': 'process_get_steps_for_steps_parent_echo',
		'security': losy_ajax_nonce,
		'data': {
			steps_parent: steps_parent
		}
	};

	jQuery.post(ajaxurl, data, function(response) {

		console.log('import_steps_losy response', response);

		if(typeof response == 'undefined' || !response){

			return false;
		}

		response = parse_local_sync_response_from_raw_data(response);

		try{
			response = JSON.parse(response);
		}catch(err){

			console.log(err);

			return;
		}

		console.log(response);

		jQuery('.steps-result-losy').show();

		if(typeof response.error != 'undefined' || typeof response.steps == 'undefined'){
				
			console.log('Error in getting steps');

			return;
		}

		var stepsHTML = '';
		jQuery.each(response.steps, function(k, v){
			if(k == 'header'){
				stepsHTML += '<h3>' + v + '</h3>';
			} else {
				stepsHTML += '<p class="waiting" id="'+k+'_losy">' + v + '</h3>';
			}
		});

		stepsHTML += '<p class="result success bridge_success_losy" style="display: none;">Sync is performed successfully!</p><p class="result oops bridge_error_losy" style="display: none;">Oops... Something went wrong! Please try again.</p><button class="retry_button_losy" style="display: none;">Retry</button>';

		jQuery('.steps-result-losy').html(stepsHTML);

		if(SHOW_FS_METHOD_ERROR_LOSY){

			jQuery('.bridge_error_losy').html(SHOW_FS_METHOD_ERROR_LOSY).show();

			SHOW_FS_METHOD_ERROR_LOSY = false;

			return;
		}

		jQuery('.steps-result-losy p:nth-child(2)').removeClass().addClass('processing');
	});
}

function fill_steps_losy(steps){
	if(typeof steps == 'undefined' || !steps){
		return;
	}

	console.log('steps ya');
	console.log(steps);

	jQuery.each(steps, function(v, k){
		jQuery('#' + v + '_losy').removeClass().addClass(k);
	});
}

function continue_sync_from_live_losy(){
	if(typeof chosen_action_local_sync == 'undefined' || chosen_action_local_sync == '' || LOCAL_SYNC_AJAX_RUNNING){
		return;
	}
	LOCAL_SYNC_AJAX_RUNNING = true;

	var data = {
		'action': chosen_action_local_sync,
		'security': losy_ajax_nonce,
		'data': {
			prod_key: jQuery('.prod_key_losy').val()
		}
	};

	console.log('calling continue_sync_from_live_losy');
	console.log(new Date().toLocaleTimeString());

	jQuery.post(ajaxurl, data, function(response) {

		console.log('continuing sync response', response);
		LOCAL_SYNC_AJAX_RUNNING = false;

		if(typeof response == 'undefined' || !response){
			stop_continuing_sync_process_losy();

			return false;
		}

		response = parse_local_sync_response_from_raw_data(response);

		jQuery('.sync_from_live_site_result').prepend(response + '\n');

		try{
			response = JSON.parse(response);
		}catch(err){
			RETRIES_LOCAL_SYNC++;

			if(RETRIES_LOCAL_SYNC < 5){
				jQuery('.sync_from_live_site_result').prepend('Retrying...' + '\n');

				return;
			}

			stop_continuing_sync_process_losy();

			return;
		}

		console.log(response);

		if(typeof response.process_steps != 'undefined'){
			fill_steps_losy(response.process_steps);
		}

		if(typeof response.success != 'undefined' && response.show_modified_files_dialog){
			stop_continuing_sync_process_losy();

			USER_SELECTION_MODIFIED_FILES = response.modified_files;
			SELECTED_MODIFIED_FILES = {};

			jQuery.each(USER_SELECTION_MODIFIED_FILES, function(kk, vv){
				if(vv.file_path.indexOf('wp-content/uploads/local_sync') < 0){
					SELECTED_MODIFIED_FILES[vv.file_path] = 1;
				}
			});

			var show_all_option = 0;

			if(response.modified_files_count >= 100){
				var show_all_option = 1;
			}

			prepare_modified_file_list_modal_losy(false, show_all_option);
			open_modal_losy('#choose_modified_files_modal_losy', 'Select the files to be replaced');
			jQuery( "#choose_modified_files_modal_losy" ).dialog( "option", "width", 486 );
			jQuery('.ui-dialog-titlebar:visible').hide();

			return;
		}

		if(typeof response.error != 'undefined' || typeof response.success == 'undefined' || !response.success ){
			stop_continuing_sync_process_losy();

			jQuery('.bridge_error_losy').text(response.error).show();

			jQuery('.retry_button_losy').hide();
			if(typeof response.is_validity_check_request != 'undefined' && response.is_validity_check_request){
				jQuery('.retry_button_losy').hide();
			} else {
				jQuery('.retry_button_losy').show();
			}

			return;
		} else if( !response.requires_next_call){
			stop_continuing_sync_process_losy();
		}

		if(typeof response.queued_files_count != 'undefined'){
			if(jQuery('#send_batch_download_requests_losy').length){
				jQuery('#send_batch_download_requests_losy').text(`Downloading ${response.queued_files_count} files...`);
			} else if(jQuery('#batch_upload_files_losy').length){
				jQuery('#batch_upload_files_losy').text(`Uploading ${response.queued_files_count} files...`);
			}
		}

		if(response.sync_current_action == 'continue_extract_from_bridge'){
			transfer_flow_to_bridge_losy();
		} else if(response.sync_current_action == 'continue_extract_from_live_bridge'){
			transfer_flow_to_live_bridge_losy();
		} else if(response.sync_current_action == 'copy_files_from_live_bridge'){
			transfer_flow_to_live_bridge_losy();
		} else if(response.sync_current_action == 'copy_files_from_bridge'){
			transfer_flow_to_bridge_losy();
		}

		if( response.requires_next_call 
			&& typeof response.ajax_time_taken != 'undefined' 
			&& response.ajax_time_taken < 26 ){
			console.log('stopping and immediately resuming, only ', response.ajax_time_taken, ' secs');
			console.log(new Date().toLocaleTimeString());
			stop_and_re_init_continue_sync_from_live_losy();
		}
	});
}

function stop_and_re_init_continue_sync_from_live_losy(){
	if( (typeof continue_sync_interval_started != 'undefined' && continue_sync_interval_started) ){
		stop_continuing_sync_process_losy(true);

		continue_sync_interval_started = true;
		continue_sync_from_live_losy();
		sync_from_live_site_interval_losy = setInterval(function(){ continue_sync_from_live_losy(); }, 30000);
	}
}

function stop_continuing_sync_process_losy(retry){
	if(typeof retry != 'undefined' && retry){
		// jQuery('.sync_from_live_site_result').prepend('Stopping sync...' + '\n');
	} else {
		jQuery('.sync_from_live_site_result').prepend('Stopping sync...' + '\n');
	}

	if(typeof sync_from_live_site_interval_losy != 'undefined'){
		continue_sync_interval_started = false;
		clearInterval(sync_from_live_site_interval_losy);
	}
}

function transfer_flow_to_bridge_losy(argument) {
	var bridge_url = ajaxurl.replace("wp-admin/admin-ajax.php", "local_sync_bridge_"+PROD_RANDOM_KEY_ID+"/index.php?prod_key_random_id="+PROD_RANDOM_KEY_ID);
	window.location = bridge_url;
}

function transfer_flow_to_live_bridge_losy(argument) {
	var bridge_url = jQuery('.prod_site_url_losy').val() + "/local_sync_bridge_"+PROD_RANDOM_KEY_ID+"/index.php?prod_key_random_id="+PROD_RANDOM_KEY_ID;
	window.location = bridge_url;
}

function get_message_in_between_losy(response_str){
	var start_str = '<LOCAL_SYNC_START>';
	var start_str_len = start_str.length;
	var end_str = '<LOCAL_SYNC_END>';
	var end_str_len = end_str.length;

	if(response_str.indexOf(start_str) === false){
		return false;
	}

	var start_str_full_pos = response_str.indexOf(start_str) + start_str_len;
	var in_between = response_str.substr(start_str_full_pos);

	var end_str_full_pos = in_between.indexOf(end_str);
	in_between = in_between.substr(0, end_str_full_pos);

	return in_between;
}

function parse_local_sync_response_from_raw_data(raw_response){

	if(raw_response.indexOf('Please enter your FTP credentials to proceed') > 0){

		SHOW_FS_METHOD_ERROR_LOSY = 'Please enter <strong>define("FS_METHOD", "direct");</strong> <br> in wp-config.php file and try again.';
	}

	return raw_response.split('<LOCAL_SYNC_START>').pop().split('<LOCAL_SYNC_END>').shift();
}

function prepare_modified_file_list_modal_losy(preventAction, show_all_option){
	var thisDiv = '';

	jQuery('.modal_bottoms_losy').hide();

	if(preventAction){
		jQuery('.modal_bottom_losy').show();

		thisDiv = 'Please upgrade for selecting the modified files during sync.';

		jQuery('#choose_modified_files_modal_losy .modal_top_losy').html(thisDiv);

		return;
	}

	if(show_all_option){
		jQuery('.modal_bottom_losy_all').show();

		jQuery('.subtitle_modal_losy').hide();

		if(chosen_action_local_sync == 'sync_from_live_site'){
			thisDiv = 'You have more than 100 changed files. Do you want to keep them or replace them with the ones from the live site?';
		} else {
			thisDiv = 'You have more than 100 changed files. Do you want to keep them or replace them with the ones from the local site?';
		}

		jQuery('#choose_modified_files_modal_losy .modal_top_losy').html(thisDiv);		

		return;
	}

	jQuery('.modal_bottom_losy').show();

	jQuery.each(USER_SELECTION_MODIFIED_FILES, function(kk, vv){

		// if( vv.file_path.indexOf('.png') != -1 
		// 	|| vv.file_path.indexOf('.jpg') != -1  
		// 	|| vv.file_path.indexOf('.jpeg') != -1  
		// 	|| vv.file_path.indexOf('.svg') != -1  ){
		// 	if( vv.file_path.indexOf('wp-content/uploads') != -1 ){

		// 		return true;
		// 	}
		// }

		if(vv.file_path.indexOf('wp-content/uploads/local_sync') >= 0){

			return;
		}

		if(SELECTED_MODIFIED_FILES[vv.file_path] == 1){
			thisDiv += ' \
			<div class="user_mod_file_single_losy" >\
				<input name="user_mod_file_single_losy_check" class="user_mod_file_single_losy_check" checked type="checkbox" value="'+vv.file_path+'" />\
				<span class="">'+vv.file_path+'</span>\
			</div>';
		}else{
			thisDiv += ' \
			<div class="user_mod_file_single_losy" >\
				<input name="user_mod_file_single_losy_check" class="user_mod_file_single_losy_check" type="checkbox" value="'+vv.file_path+'" />\
				<span class="">'+vv.file_path+'</span>\
			</div>';
		}
		
	});

	if(thisDiv == ''){
		thisDiv = 'There are no files to be replaced, click OK to continue the Sync Process.';

		jQuery('.subtitle_modal_losy').hide();
		jQuery('.modify_all_files_modal_ok').hide();
		jQuery('.modified_files_modal_ok').val('OK');
	} else {
		if(chosen_action_local_sync == 'push_to_live_site'){
			jQuery(".subtitle_modal_losy").hide();
			jQuery(".subtitle_modal_losy.for_pushing").show();
		}
	}

	if(preventAction){
		thisDiv = 'Please upgrade for selecting the modified files during sync.';
	}

	jQuery('#choose_modified_files_modal_losy .modal_top_losy').html(thisDiv);
}

function open_modal_losy(id_name, title){
	jQuery(id_name).dialog({
		autoOpen: true,
		title: title,
		modal: true,
		width: 450
	});
}

function save_settings_losy(isAlert, pullSteps){
	jQuery('.save_settings_local_sync').val('Saving...');
	var ls_settings = {};
	// ls_settings['prod_key'] = jQuery('.prod_key_losy').val();
	ls_settings['load_images_from_live_site_settings'] = jQuery('input[name=load_images_from_live_settings_radio_losy]:checked').val();
	ls_settings['sync_type_db_or_files'] = jQuery('input[name=sync_type_db_or_files_losy]:checked').val();
	ls_settings['user_excluded_extenstions'] = jQuery('#user_excluded_extenstions').val();
	ls_settings['user_excluded_files_more_than_size_settings'] = {
		status: jQuery('input[name=user_excluded_files_more_than_size_status]').prop('checked'),
		size: jQuery("#user_excluded_files_more_than_size").val() 
	};

	var data = {
		'action': 'save_settings_local_sync',
		'security': losy_ajax_nonce,
		'data': {
			'settings': ls_settings,
		}
	};

	console.log(data);

	jQuery.post(ajaxurl, data, function(response) {
		try{
			console.log('save_settings_losy');

			jQuery('.save_settings_local_sync').val('Save Changes');

			response = parse_local_sync_response_from_raw_data(response);

			response = JSON.parse(response);
			
			console.log(response);
			
			if( typeof response != 'undefined' 
			&& typeof response.prod_site_url != 'undefined' 
			&& response.prod_site_url ){
				jQuery('.prod_site_url_losy').val(response.prod_site_url);
			}
			
			if(isAlert){
				alert('Success');
			}
			if(pullSteps){
				import_steps_losy(chosen_action_local_sync);
			}

		}catch(err){
			console.log('json parse error', response);
		}
	});
}

jQuery(document).ready(function($) {
	// 'use strict';

	//For local site

	setTimeout(function(){
		jQuery('.prod_site_url_losy').focus();
		if(typeof ClipboardJS != 'undefined'){
			new ClipboardJS('.copy_prod_url_losy');
		}
	}, 1000);

	jQuery('body').on('keyup', '.prod_site_url_losy', function () {
		if($(this).val() == ""){
			jQuery('.both_sync_losy').prop('disabled', true);
		} else {
			jQuery('.both_sync_losy').prop('disabled', false);
		}
	});

	jQuery('body').on('focus', '.prod_site_url_losy', function () {
		if($(this).val() == ""){
			jQuery('.both_sync_losy').prop('disabled', true);
		} else {
			jQuery('.both_sync_losy').prop('disabled', false);
		}
	});

	// jQuery('body').on('mouseenter', '.sync_from_live_site.loadinglosy', function () {
	// 	jQuery('.sync_from_live_site').prop('disabled', true);
	// });

	// jQuery('body').on('mouseleave', '.sync_from_live_site', function () {
	// 	jQuery('.sync_from_live_site').prop('disabled', false);
	// });

	// jQuery('body').on('mouseenter', '.push_to_live_site.loadinglosy', function () {
	// 	jQuery('.push_to_live_site').prop('disabled', true);
	// });

	// jQuery('body').on('mouseleave', '.push_to_live_site', function () {
	// 	jQuery('.push_to_live_site').prop('disabled', false);
	// });

	jQuery('body').on('click', '.sync_from_live_site', function () {

		RETRIES_LOCAL_SYNC = 0;

		chosen_action_local_sync = 'sync_from_live_site';
		var data = {
			'action': chosen_action_local_sync,
			'security': losy_ajax_nonce,
			'data': {
				prod_key: jQuery('.prod_key_losy').val(),
				first_call: true
			}
		};

		save_settings_losy(false, true);

		jQuery('.both_sync_losy').prop('disabled', true);
		jQuery('.sync_from_live_site').addClass('loadinglosy');
		jQuery('.sync_from_live_site').prop('disabled', false);

		jQuery('.sync_from_live_site_result').prepend('Started' + '<br>');
		jQuery('#file_list_preparation_for_local_dump_losy').removeClass().addClass('processing');

		jQuery.post(ajaxurl, data, function(response) {
			console.log('start sync response', response);

			if(typeof response == 'undefined'){

				return false;
			}

			response = parse_local_sync_response_from_raw_data(response);

			jQuery('.sync_from_live_site_result').prepend(response + '\n');

			try{
				response = JSON.parse(response);
			}catch(err){
				console.log('json parse error', response);
			}

			console.log(response);

			if(typeof response.error != 'undefined' || typeof response.success == 'undefined' || !response.success ){
				SHOW_FS_METHOD_ERROR_LOSY = response.error || "Error";
				jQuery('.bridge_error_losy').text(response.error).show();

				jQuery('.retry_button_losy').hide();
				if(typeof response.is_validity_check_request != 'undefined' && response.is_validity_check_request){
					jQuery('.retry_button_losy').hide();
				} else {
					jQuery('.retry_button_losy').show();
				}

				return;
			}

			if(typeof response.process_steps != 'undefined'){
				fill_steps_losy(response.process_steps);
			}

			if(response.success && response.requires_next_call 
				&& (typeof continue_sync_interval_started == 'undefined' || !continue_sync_interval_started) ){
				continue_sync_interval_started = true;
				continue_sync_from_live_losy();
				sync_from_live_site_interval_losy = setInterval(function(){ continue_sync_from_live_losy(); }, 30000);
			}
		});
	});

	jQuery('body').on('click', '.push_to_live_site', function () {
		RETRIES_LOCAL_SYNC = 0;

		chosen_action_local_sync = 'push_to_live_site';
		var data = {
			'action': 'push_to_live_site',
			'security': losy_ajax_nonce,
			'data': {
				prod_key: jQuery('.prod_key_losy').val(),
				first_call: true
			}
		};

		save_settings_losy(false, true);

		jQuery('.both_sync_losy').prop('disabled', true);
		jQuery('.push_to_live_site').addClass('loadinglosy');

		jQuery('.sync_from_live_site_result').prepend('Started' + '<br>');

		jQuery('.steps-result-losy').show();

		jQuery.post(ajaxurl, data, function(response) {
			console.log('start sync response', response);

			if(typeof response == 'undefined'){

				return false;
			}

			response = parse_local_sync_response_from_raw_data(response);

			jQuery('.sync_from_live_site_result').prepend(response + '\n');

			try{
				response = JSON.parse(response);
			}catch(err){
				console.log('json parse error', response);
			}

			console.log(response);

			if(typeof response.error != 'undefined' || typeof response.success == 'undefined' || !response.success ){
				SHOW_FS_METHOD_ERROR_LOSY = response.error || "Error";
				jQuery('.bridge_error_losy').text(response.error).show();

				jQuery('.retry_button_losy').hide();
				if(typeof response.is_validity_check_request != 'undefined' && response.is_validity_check_request){
					jQuery('.retry_button_losy').hide();
				} else {
					jQuery('.retry_button_losy').show();
				}

				return;
			}

			if(response.success && response.requires_next_call 
				&& (typeof continue_sync_interval_started == 'undefined' || !continue_sync_interval_started) ){
				continue_sync_interval_started = true;
				continue_sync_from_live_losy();
				sync_from_live_site_interval_losy = setInterval(function(){ continue_sync_from_live_losy(); }, 30000);
			}
		});
	});

	jQuery('body').on('click', '.set_as_local_site_losy', function () {
		var data = {
			'action': 'set_as_local_site_losy',
			'security': losy_ajax_nonce,
		};

		jQuery.post(ajaxurl, data, function(response) {
			window.location.reload();
		});
	});

	jQuery('body').on('click', '.set_as_prod_site_losy', function () {
		var data = {
			'action': 'set_as_prod_site_losy',
			'security': losy_ajax_nonce,
		};

		jQuery.post(ajaxurl, data, function(response) {
			window.location.reload();
		});
	});

	// jQuery('body').on('click', '.start_db_dump', function () {
	// 	var data = {
	// 		'action': 'start_db_dump',
	// 		'security': losy_ajax_nonce,
	// 	};

	// 	jQuery.post(ajaxurl, data, function(response) {
	// 		alert('Got this from the server: ' + response);
	// 	});
	// });

	// jQuery('body').on('click', '.test_button', function () {
	// 	var data = {
	// 		'action': 'test_button',
	// 		'security': losy_ajax_nonce,
	// 	};

	// 	jQuery.post(ajaxurl, data, function(response) {
	// 		console.log(response);
	// 		alert('Got this from the server: ' + response);
	// 	});
	// });

	jQuery('body').on('click', '.save_settings_local_sync', function () {
		save_settings_losy(true);
	});

	jQuery('body').on('click', '.add_site_losy', function () {
		var data = {
			'action': 'process_add_site',
			'security': losy_ajax_nonce,
			'data': {
				prod_key: jQuery('.prod_key_losy').val(),
			}
		};

		jQuery(this).addClass('loadinglosy');

		jQuery('.losy_add_site_error_success_flaps').text('').hide();

		jQuery.post(ajaxurl, data, function(response) {
			console.log('add_site_losy response', response);

			jQuery('.add_site_losy').removeClass('loadinglosy');

			if(typeof response == 'undefined'){

				return false;
			}

			response = parse_local_sync_response_from_raw_data(response);

			try{
				response = JSON.parse(response);
			}catch(err){
				console.log('json parse error', response);
			}

			if(typeof response.error != 'undefined'){
				jQuery('.losy_add_site_error_success_flaps').text('').hide();
				jQuery('.losy_add_site_error').text(response.error).show();
			} else {
				window.location.reload();
			}

			console.log(response);
		});
	});

	jQuery('body').on('click', '.remove_site_losy', function () {
		var data = {
			'action': 'process_remove_site',
			'security': losy_ajax_nonce,
			'data': {
			
			}
		};

		jQuery.post(ajaxurl, data, function(response) {
			console.log('remove_site_losy response', response);

			if(typeof response == 'undefined'){

				return false;
			}

			response = parse_local_sync_response_from_raw_data(response);

			try{
				response = JSON.parse(response);
			}catch(err){
				console.log('json parse error', response);
			}

			if(typeof response.error != 'undefined'){

			} else {
				window.location.reload();
			}

			console.log(response);
		});
	});

	jQuery('body').on('click', '.losy_login_button', function () {
		var data = {
			'action': 'process_service_login',
			'security': losy_ajax_nonce,
			'data': {
				email: jQuery('.losy_email').val(),
				password: jQuery('.losy_password').val(),
			}
		};

		jQuery('.losy_login_erorr_success_flaps').text('').hide();
		jQuery(this).addClass('loadinglosy');
		jQuery('.losy_login_button').prop('disabled', true);

		jQuery.post(ajaxurl, data, function(response) {
			console.log('losy_login_button response', response);

			jQuery('.losy_login_button').prop('disabled', false);
			jQuery('.losy_login_button').removeClass('loadinglosy');

			if(typeof response == 'undefined'){

				return false;
			}

			response = parse_local_sync_response_from_raw_data(response);

			try{
				response = JSON.parse(response);
			}catch(err){
				console.log('json parse error', response);
			}

			if(typeof response.error != 'undefined'){
				jQuery('.losy_login_erorr_success_flaps').text('').hide();
				jQuery('.losy_login_error').text(response.error).show();
			} else {
				window.location.reload();
			}

			console.log(response);
		});
	});

	jQuery('body').on('click', '.logout_local_sync', function () {
		var data = {
			'action': 'process_service_logout',
			'security': losy_ajax_nonce,
			'data': {}
		};

		jQuery('.losy_login_erorr_success_flaps').text('').hide();

		jQuery(this).addClass('loadinglosy');
		jQuery('.logout_local_sync').prop('disabled', true);

		jQuery.post(ajaxurl, data, function(response) {
			console.log('logout_local_sync response', response);

			jQuery('.logout_local_sync').prop('disabled', false);
			jQuery('.logout_local_sync').removeClass('loadinglosy');

			if(typeof response == 'undefined'){

				return false;
			}

			try{
				response = JSON.parse(response);
			}catch(err){
				console.log('json parse error', response);
			}

			if(typeof response.success != 'undefined'){
				window.location.reload();
			}
			
			console.log(response);
		});
	});

	jQuery("input[name=user_excluded_files_more_than_size_status]").on("change", function() {
		jQuery("#user_excluded_files_more_than_size_div").toggle()
	});

	jQuery('body').on('click', '.retry_button_losy', function () {
		jQuery('.bridge_error_losy').hide();
		continue_sync_interval_started=true;
		stop_and_re_init_continue_sync_from_live_losy();
	});

	jQuery('body').on('click', '.user_mod_file_single_losy', function () {
		var sel_key = jQuery(this).find('input').val();
		SELECTED_MODIFIED_FILES[sel_key] = !SELECTED_MODIFIED_FILES[sel_key];
		prepare_modified_file_list_modal_losy();
	});

	jQuery('body').on('click', '.user_mod_file_single_losy_check', function (e) {
		e.preventDefault();
	});

	jQuery('body').on('click', '.modified_files_modal_ok', function () {
		var data = {
			'action': 'modified_files_modal_ok',
			'security': losy_ajax_nonce,
			'user_selected_files': SELECTED_MODIFIED_FILES,
			'chosen_action_local_sync': chosen_action_local_sync
		};

		jQuery.post(ajaxurl, data, function(response) {
			console.log(response);
			// alert('Got this from the server: ' + response);

			response = parse_local_sync_response_from_raw_data(response);
			response = JSON.parse(response);

			if(typeof response.process_steps != 'undefined'){
				fill_steps_losy(response.process_steps);
			}

			if(typeof response.success != 'undefined' && response.success){
				jQuery('#choose_modified_files_modal_losy .modal_top_losy').text('Success.');
			} else {
				jQuery('#choose_modified_files_modal_losy .modal_top_losy').text('Files selection error.');
			}

			continue_sync_interval_started=true;
			stop_and_re_init_continue_sync_from_live_losy();

			setTimeout(function(){
				jQuery('.ui-icon-closethick').click();
			}, 2000);
			
		});
	});

	jQuery('body').on('click', '.modify_all_files_modal_ok', function () {
		jQuery('.ui-icon-closethick').click();

		continue_sync_interval_started=true;
		stop_and_re_init_continue_sync_from_live_losy();
	});

	jQuery('body').on('click', '.modify_all_files_modal_cancel', function () {
		var data = {
			'action': 'modify_all_files_modal_cancel',
			'security': losy_ajax_nonce,
		};

		jQuery.post(ajaxurl, data, function(response) {
			console.log(response);
			// alert('Got this from the server: ' + response);

			response = parse_local_sync_response_from_raw_data(response);
			response = JSON.parse(response);

			if(typeof response.process_steps != 'undefined'){
				fill_steps_losy(response.process_steps);
			}

			if(typeof response.success != 'undefined' && response.success){
				jQuery('#choose_modified_files_modal_losy .modal_top_losy').text('Success.');
				jQuery('.modal_bottoms_losy').hide();
			} else {
				jQuery('#choose_modified_files_modal_losy .modal_top_losy').text('Files selection error.');
			}

			continue_sync_interval_started=true;
			stop_and_re_init_continue_sync_from_live_losy();

			setTimeout(function(){
				jQuery('.ui-icon-closethick').click();
			}, 2000);
			
		});
	});
});
