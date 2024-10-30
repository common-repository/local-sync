var continue_sync_interval_started = false;
var SELECTED_FILES_FOR_DELETE = {};
var USER_SELECTION_FILES_FOR_DELETE = {};
var RETRIES_LOCAL_SYNC = 0;
var LOCAL_SYNC_AJAX_RUNNING = false;

function import_steps_losy(steps_parent){
	var data = {
		'action': 'process_get_steps_for_steps_parent_echo',
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
		var is_bridge_process_came = false;
		jQuery.each(response.steps, function(k, v){
			if(k == 'header'){
				stepsHTML += '<h3>' + v + '</h3>';
			} else {
				if(k == 'continue_extract_from_bridge' || k == 'continue_extract_from_live_bridge'){
					is_bridge_process_came = true;
					k = 'continue_extract_from_bridge';
				}
				if(!is_bridge_process_came){
					stepsHTML += '<p class="done" id="'+k+'_losy">' + v + '</h3>';
				}else{
					stepsHTML += '<p class="waiting" id="'+k+'_losy">' + v + '</h3>';
				}
			}
		});

		stepsHTML += '<p class="result success bridge_success_losy" style="display: none;">Sync is performed successfully!</p><p class="result oops bridge_error_losy" style="display: none;">Oops... Something went wrong! Please try again.</p><button class="retry_button_losy">Retry</button><button class="bridge_test_button" style="display: none;">Test Button</button>';

		jQuery('.steps-result-losy').html(stepsHTML);

	});
}

function fill_steps_losy(steps){
	if(typeof steps == 'undefined' || !steps){
		return;
	}

	console.log('steps ya');
	console.log(steps);

	jQuery.each(steps, function(v, k){
		if(v == 'continue_extract_from_live_bridge'){
			v = 'continue_extract_from_bridge';
		}
		
		jQuery('#' + v + '_losy').removeClass().addClass(k);
	});
}

function parse_local_sync_response_from_raw_data(raw_response){
	return raw_response.split('<LOCAL_SYNC_START>').pop().split('<LOCAL_SYNC_END>').shift();
}

function process_bridge_requests_losy() {
	var data = {
		'action': 'sync_from_live_site',
		'data': {
			prod_site_url: jQuery('.prod_site_url_losy').val(),
			first_call: true
		}
	};


	jQuery.post(ajaxurl, data, function(fullResponse) {
		console.log('bridge sync fullResponse', fullResponse);

		if( typeof fullResponse == 'undefined' || !fullResponse ){

			return false;
		}

		var response = parse_local_sync_response_from_raw_data(fullResponse);

		jQuery('.sync_from_live_site_result').prepend(response + '<br>');

		try{
			response = JSON.parse(response);
		}catch(err){
			console.log('json parse error', response);
		}

		console.log(response);

		if(typeof response.process_steps != 'undefined'){
			fill_steps_losy(response.process_steps);
		}

		if(response.success && response.requires_next_call 
			&& (typeof continue_sync_interval_started == 'undefined' || !continue_sync_interval_started) ){
			continue_sync_interval_started = true;

			console.log('setting bridge continue_sync_interval_started as true');

			continue_sync_from_live_losy();
			sync_from_live_site_interval_losy = setInterval(function(){ continue_sync_from_live_losy(); }, 30000);
		}
	});
}

function continue_sync_from_live_losy(){
	if(LOCAL_SYNC_AJAX_RUNNING){

		return;
	}

	LOCAL_SYNC_AJAX_RUNNING = true;
	var data = {
		'action': 'sync_from_live_site',
		'data': {
			prod_site_url: jQuery('.prod_site_url_losy').val()
		}
	};

	console.log('calling continue_sync_from_live_losy');
	console.log(new Date().toLocaleTimeString());

	jQuery.post(ajaxurl, data, function(fullResponse) {
		LOCAL_SYNC_AJAX_RUNNING = false;

		console.log('continuing sync fullResponse', fullResponse);

		if(typeof fullResponse == 'undefined' || !fullResponse){
			stop_continuing_sync_process_losy();

			return false;
		}

		var response = parse_local_sync_response_from_raw_data(fullResponse);

		jQuery('.sync_from_live_site_result').prepend(response + '<br>');

		try{
			response = JSON.parse(response);
		}catch(err){
			RETRIES_LOCAL_SYNC++;

			if(RETRIES_LOCAL_SYNC < 5){
				jQuery('.sync_from_live_site_result').prepend('Retrying...' + '\n');

				return;
			}

			stop_continuing_sync_process_losy();
		}

		console.log(response);

		if(typeof response.process_steps != 'undefined'){
			fill_steps_losy(response.process_steps);
		}

		if(typeof response.error != 'undefined' || typeof response.success == 'undefined' || !response.success ){
			stop_continuing_sync_process_losy();

			jQuery('.sync-restore-error').text(response.error);
			jQuery('.sync-restore-over').show();

			return;
		} else if( !response.requires_next_call){
			stop_continuing_sync_process_losy();
		}

		if(response.sync_current_action == 'redirect_to_local_site'){

			jQuery('.sync-restore-over').show();
			jQuery('.sync_site_link').attr("href", response.local_site_url);

			console.log('Full restore over');

			fetch_files_to_be_deleted();

			if(typeof response.actions_time_taken != 'undefined'){
				jQuery('.sync_from_live_site_time_taken').text(JSON.stringify(response.actions_time_taken));
				jQuery('.sync_from_live_site_total_time_taken').text(response.total_time_taken);
			}

			return;
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

function stop_and_re_init_continue_sync_from_live_losy(){
	if( (typeof continue_sync_interval_started != 'undefined' && continue_sync_interval_started) ){stop_continuing_sync_process_losy(true);

		continue_sync_interval_started = true;
		continue_sync_from_live_losy();
		sync_from_live_site_interval_losy = setInterval(function(){ continue_sync_from_live_losy(); }, 30000);
	} else {
		console.log('not doing supposed stop_and_re_init_continue_sync_from_live_losy');
	}
}

function open_modal_losy(id_name, title){
	jQuery(id_name).dialog({
		autoOpen: true,
		title: title,
		modal: true,
		width: 486
	});
}

function prepare_delete_file_list_modal_losy(preventDelete, show_all_delete_option){
	var thisDiv = '';

	jQuery('.modal_bottoms_losy').hide();

	if(preventDelete){
		jQuery('.modal_bottom_losy').show();

		thisDiv = '<div style="background-color:#f5f1a6; padding:10px; text-align:center; margin-top: 5px;"><a href="http://localsync.io" style="color:#0085ba;">Upgrade to PRO</a> to see the list of extra files</div>';

		jQuery('#choose_delete_files_modal_losy .modal_top_losy').html(thisDiv);

		return;
	}

	if(show_all_delete_option){
		jQuery('.modal_bottom_losy_all').show();

		jQuery('.subtitle_modal_losy').hide();

		if(site_type_losy_global == 'local'){
			thisDiv = '<em>We found more than 100 extra files in the local site after pulling from the live site. Do you want to delete them.</em>';
		} else {
			thisDiv = '<em>We found more than 100 extra files in the live site after pushing to the live site. Do you want to delete them.</em>';
		}

		jQuery('#choose_delete_files_modal_losy .modal_top_losy').html(thisDiv);		

		return;
	}

	jQuery('.modal_bottom_losy').show();

	jQuery.each(USER_SELECTION_FILES_FOR_DELETE, function(kk, vv){

		if( vv.file.indexOf('.png') != -1 
			|| vv.file.indexOf('.jpg') != -1  
			|| vv.file.indexOf('.jpeg') != -1  
			|| vv.file.indexOf('.svg') != -1  ){
			if( site_type_losy_global != 'production' && vv.file.indexOf('wp-content/uploads/') != -1 ){

				return true;
			}
		}

		if(SELECTED_FILES_FOR_DELETE[vv.file] == 1){
			thisDiv += ' \
			<div class="user_del_file_single_losy" >\
				<input name="user_del_file_single_losy_check" class="user_del_file_single_losy_check" checked type="checkbox" value="'+vv.file+'" />\
				<span class="">'+vv.file+'</span>\
			</div>';
		}else{
			thisDiv += ' \
			<div class="user_del_file_single_losy" >\
				<input name="user_del_file_single_losy_check" class="user_del_file_single_losy_check" type="checkbox" value="'+vv.file+'" />\
				<span class="">'+vv.file+'</span>\
			</div>';
		}
		
	});

	if(thisDiv == ''){
		thisDiv = 'There are no files to be deleted, click OK to finish the Sync Process.';

		jQuery('.subtitle_modal_losy').hide();
		jQuery('.delete_files_modal_ok').val('OK');
	} else {
		if(site_type_losy_global == 'production'){
			jQuery(".subtitle_modal_losy").hide();
			jQuery(".subtitle_modal_losy.for_pushing").show();
		}
	}

	if(preventDelete){
		thisDiv = 'Please upgrade for deleting files during sync.'
	}

	jQuery('#choose_delete_files_modal_losy .modal_top_losy').html(thisDiv);
}

function fetch_files_to_be_deleted() {
	var data = {
		'action': 'fetch_files_to_be_deleted',
	};

	jQuery.post(ajaxurl, data, function(response) {
		console.log(response);
		// alert('Got this from the server: ' + response);

		response = parse_local_sync_response_from_raw_data(response);
		response = JSON.parse(response);

		USER_SELECTION_FILES_FOR_DELETE = response['user_selection_files'];
		SELECTED_FILES_FOR_DELETE = {};

		jQuery.each(USER_SELECTION_FILES_FOR_DELETE, function(kk, vv){
			SELECTED_FILES_FOR_DELETE[vv.file] = 1;
		});

		var preventDelete = !response['delete_files_allowed'];

		var show_all_delete_option = response['show_all_delete_option'];

		prepare_delete_file_list_modal_losy(preventDelete, show_all_delete_option);
		open_modal_losy('#choose_delete_files_modal_losy', 'Select The Files To Be Deleted');
		jQuery( "#choose_delete_files_modal_losy" ).dialog( "option", "width", 486 );
		jQuery('.ui-dialog-titlebar:visible').hide();
		
	});
}

jQuery(document).ready(function($) {

	if( (typeof continue_sync_interval_started == 'undefined' || !continue_sync_interval_started) ){
		continue_sync_interval_started = true;

		console.log('setting bridge continue_sync_interval_started as true');

		RETRIES_LOCAL_SYNC = 0;

		if( typeof site_type_losy_global == 'undefined' 
			|| !site_type_losy_global 
			|| site_type_losy_global == 'local' ){
			import_steps_losy('sync_from_live_site');
		} else {
			import_steps_losy('push_to_live_site');
		}
		
		continue_sync_from_live_losy();
		sync_from_live_site_interval_losy = setInterval(function(){ continue_sync_from_live_losy(); }, 30000);
	}

	jQuery('body').on('click', '.bridge_test_button', function () {
		fetch_files_to_be_deleted();
	});

	jQuery('body').on('click', '.delete_files_modal_ok', function () {
		var data = {
			'action': 'delete_files_modal_ok',
			'user_selected_files': SELECTED_FILES_FOR_DELETE
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
				if(!(response.requires_next_call)){
					jQuery('#choose_delete_files_modal_losy .modal_top_losy').html('<em>Success</em>');
					jQuery('.modal_bottoms_losy').hide();

					var parentUrlLosy = window.location.href;
					parentUrlLosy =parentUrlLosy.split('local_sync_bridge')[0];

					setTimeout(function(){
						window.location = parentUrlLosy;
					}, 5000);
				} else {
					jQuery('#choose_delete_files_modal_losy .modal_top_losy').html('<em>' + 'Deleted ' + response.deleted_files_count + 'files. Needs another call to delete remaining files.' + '</em>');
				}
			} else {
				jQuery('#choose_delete_files_modal_losy .modal_top_losy').html('<em>' + 'Error' + '</em>');
				if(typeof response.error != 'undefined'){
					jQuery('#choose_delete_files_modal_losy .modal_top_losy').html('<em>' + response.error + '</em>');
				}
			}
			
		});
	});

	jQuery('body').on('click', '.delete_all_files_modal_ok', function () {
		var data = {
			'action': 'delete_all_files_modal_ok',
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
				if(!(response.requires_next_call)){
					jQuery('#choose_delete_files_modal_losy .modal_top_losy').text('Deleted ' + response.deleted_files_count + 'files');
					jQuery('.modal_bottom_losy_all').hide();

					var parentUrlLosy = window.location.href;
					parentUrlLosy =parentUrlLosy.split('local_sync_bridge')[0];

					jQuery('.ui-icon-closethick').click();

					setTimeout(function(){
						window.location = parentUrlLosy;
					}, 5000);
				} else {
					jQuery('#choose_delete_files_modal_losy .modal_top_losy').text('Deleted ' + response.deleted_files_count + 'files. Needs another call to delete remaining files.');
				}
			} else {
				jQuery('#choose_delete_files_modal_losy .modal_top_losy').text('Error');
				if(typeof response.error != 'undefined'){
					jQuery('#choose_delete_files_modal_losy .modal_top_losy').text(response.error);
				}
			}
			
		});
	});

	jQuery('body').on('click', '.user_del_file_single_losy', function () {
		var sel_key = jQuery(this).find('input').val();
		SELECTED_FILES_FOR_DELETE[sel_key] = !SELECTED_FILES_FOR_DELETE[sel_key];
		prepare_delete_file_list_modal_losy();
	});

	jQuery('body').on('click', '.user_del_file_single_losy_check', function (e) {
		e.preventDefault();
	});

	jQuery('body').on('click', '.retry_button_losy', function () {
		jQuery('.bridge_error_losy').hide();
		continue_sync_interval_started=true;
		stop_and_re_init_continue_sync_from_live_losy();
	});

	jQuery('body').on('click', '.delete_all_files_modal_cancel', function () {
		jQuery('.ui-icon-closethick').click();

		var parentUrlLosy = window.location.href;
		parentUrlLosy =parentUrlLosy.split('local_sync_bridge')[0];

		setTimeout(function(){
			window.location = parentUrlLosy;
		}, 5000);
	});

});
