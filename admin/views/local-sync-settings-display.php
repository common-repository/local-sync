<?php

/**
 * Provide a admin area view for the plugin
 *
 * This file is used to markup the admin-facing aspects of the plugin.
 *
 * @link       https://revmakx.com
 * @since      1.0.0
 *
 * @package    Local_Sync
 * @subpackage Local_Sync/admin/partials
 */

	if ( ! current_user_can( 'manage_options' ) ) {

		local_sync_log('', "--------no manage options return--------");

		return;
    }
    
    wp_enqueue_script('local-sync-fancy-tree-ui-js', plugins_url() . '/' . LOCAL_SYNC_PLUGIN_NAME . '/treeView/fancy-tree-ui.min.js',   array(), LOCAL_SYNC_VERSION);
    wp_enqueue_script('local-sync-fancytree-js',        plugins_url() . '/' . LOCAL_SYNC_PLUGIN_NAME . '/treeView/jquery.fancytree.js',   array(), LOCAL_SYNC_VERSION);
    wp_enqueue_style('local-sync-fancytree-css',        plugins_url() . '/' . LOCAL_SYNC_PLUGIN_NAME . '/treeView/skin/ui.fancytree.css', array(), LOCAL_SYNC_VERSION);
    wp_enqueue_script('local-sync-fileTree-common-js',  plugins_url() . '/' . LOCAL_SYNC_PLUGIN_NAME . '/treeView/common.js',             array(), LOCAL_SYNC_VERSION);

	$local_sync_ajax_nonce = wp_create_nonce( "ls_revmakx" );

	$local_sync_options = new Local_Sync_Options();
	$all_configs = $local_sync_options->all_configs;

	// local_sync_log($all_configs, "--------all_configs--------");

	$show_initial_site_options = true;
	$show_local_site_options = false;
	$show_prod_site_options = false;

	if(!empty($all_configs['site_type']) && $all_configs['site_type'] == 'local'){
		$show_initial_site_options = false;
		$show_local_site_options = true;
	} elseif(!empty($all_configs['site_type']) && $all_configs['site_type'] == 'production'){
		$show_initial_site_options = false;
		$show_prod_site_options = true;
	}

	$prod_site_url = $all_configs['prod_site_url'] ?? '';
    $prod_key = $all_configs['prod_key'] ?? '';
	
	$local_site_settings = $all_configs['local_site_settings'] ?? false;
	$local_site_url = '';
	if(!empty($local_site_settings)){
		$local_site_settings = json_decode($local_site_settings, true);
		$local_site_url = $local_site_settings['site_url'];
	}

	$last_sync_time = date("F j, Y, g:i a");

	$user_excluded_extenstions = $all_configs['user_excluded_extenstions'];

    $prod_key_random_id = 0;
    if(!empty($all_configs['prod_key_random_id'])){
        $prod_key_random_id = $all_configs['prod_key_random_id'];
    }

	$debug_true = false;
	if(defined('LOCAL_SYNC_DEBUG') && LOCAL_SYNC_DEBUG){
		$debug_true = true;
	}

?>

<div id="choose_modified_files_modal_losy" style="display: none;">
    <div class="losy-parent-cont" style="height: auto; overflow-y: hidden; overflow-x: hidden; background: #fff;">
        <div class="main-cols-losy">
            <div class="m-box-losy">
                <h2 class="hd"> Keep / Replace Changed files? </h2>
                <div class="pad">
                    <em class="subtitle_modal_losy for_pulling"> We found a few modified files in the local site. Do you want to keep them or replace them with the ones from the live site? </em>
                    <em class="subtitle_modal_losy for_pushing" style="display: none;"> We found a few modified files in the live site. Do you want to keep them or replace them with the ones from the local site? </em>
                    <div class="modal_top_losy pad"></div>
                </div>
                <div class="pad modal_bottom_losy modal_bottoms_losy" style="border-top: 1px solid #e5e5e5;">
                    <input  type="submit" class="modify_all_files_modal_ok modal_button_losy" value="Replace All Changed Files">
                    <input type="submit" class="button button-primary modified_files_modal_ok modal_button_losy" value="Replace Selected Changed Files">
                </div>
                <div class="pad modal_bottom_losy_all modal_bottoms_losy" style="border-top: 1px solid #e5e5e5; display: none;">
                    <input  type="submit" class="modify_all_files_modal_cancel modal_button_losy" value="Ignore All Changed Files">
                    <input type="submit" class="button button-primary modify_all_files_modal_ok modal_button_losy"  value="Overwrite All Changed Files">
                </div>
            </div>
        </div>
    </div>
</div>

<?php if( $show_prod_site_options && empty($all_configs['is_logged_in']) ) { ?>

<div class="losy-parent-cont" >
    <div class="main-cols-losy">
        <div class="m-box-losy">
            <h2 class="hd">Login to your WP Duplicate account <a href="https://localsync.io" target="_blank" style="float: right; text-decoration: none;">Signup</a></h2>
            <div class="pad">
                <div class="losy_login_error losy_login_erorr_success_flaps error-box-losy" style="display: none;"></div>
                <div class="losy_login_success losy_login_erorr_success_flaps success-box-losy" style="display: none;"></div>
                <div style="margin-bottom: 10px;">
                    <fieldset>
                        <label for="losy_email_id">Email</label> <br>
                        <input type="text" class="losy_email" id="losy_email_id">
                    </fieldset>
                </div>
                <div style="margin-bottom: 10px;">
                    <fieldset>
                        <label for="losy_password_id">Password</label> <br>
                        <input type="password" class="losy_password" id="losy_password_id">
                    </fieldset>
                </div>
            </div>
            <div class="ft pad">
                <span style="float:left;"><a href="https://localsync.io/my-account/lost-password/" target="_blank">Forgot password?</a></span>
                <input type="submit" value="Login to your account" name="losy_login_button" class="button-primary losy_login_button">
            </div>
        </div>
    </div>
</div>

<?php } else { ?>

<div class="losy-parent-cont" >
    <!-- <input type="hidden" name="prod_site_url_losy" class="prod_site_url_losy" /> -->
    <?php if($show_initial_site_options) : ?>
		<div class="losy-main-cols-cont cf">
            <div class="main-cols-losy">
                <div class="m-box-losy">
                    <h2 class="hd">Is this the production site or duplicate site?</h2>
                    <div class="pad">
                        <em>Click <strong>"This is duplicate site"</strong> if you wish to set this site as the duplicate site, or , click <strong>"This is live site"</strong> if you wish to set this site as the production site. <a href="https://docs.localsync.io" target="_blank">help?</a></em>
                    </div>
                    <div class="pad cf" style="padding-top: 0;">
                        <div class="col col50">
            				<button class="button set_as_local_site_losy" style="float: left; margin: 5px 0 4px;" >This is duplicate site</button>
                        </div>
                        <div class="col col50">
            				<button class="button button-primary set_as_prod_site_losy" style="float: right; margin: 5px 0 4px;">This is live site</button>
                        </div>
                    </div>
                </div>
            </div>
		</div>
	<?php endif; ?>
    <div class="losy-main-cols-cont cf">
        <div class="main-cols-losy">
        	<?php if($show_local_site_options) : ?>
                <?php if( empty($prod_key) ){ ?>
                    <div class="m-box-losy">
                        <h2 class="hd">Add New Site</h2>
                        <div class="pad">
                            <em>
                                Enter the Prod Key copied from the live site.
                                <br>
                                (Live site WP admin -> WP Duplicate)
                            </em>
                        </div>
                        <div class="pad">
                            <div class="losy_add_site_error losy_add_site_error_success_flaps error-box-losy" style="display: none;"></div>
                            <div class="losy_add_site_success losy_add_site_error_success_flaps success-box-losy" style="display: none;"></div>
                            <input style="width: 420px; height: 30px;" type="text" name="prod_key_losy" class="prod_key_losy" value="<?php echo $prod_key; ?>">
                        </div>
                        <div class="pad" style="padding-top: 0;">
                            <div class="col col50" style="margin-top: 6px;    float: right;">
                                <button type="submit" class="button button-primary add_site_losy" style="float: right; margin: 5px 0 4px;">Add Site</button>
                            </div>
                            <div style="clear: both;"></div>
                        </div>
                    </div>
                <?php } else { ?>
                    <div class="m-box-losy">
                        <h2 class="hd"><span>Choose what you want to pull or push</span> <a href="https://docs.localsync.io" target="_blank">help?</a></h2>
                        <div class="pad">
                            <em>This is a clone of the Production site at </em><br>
                        	<input style="width: 340px; height: 30px;" type="text" readonly name="prod_site_url_losy" class="prod_site_url_losy" value="<?php echo $prod_site_url; ?>">
                            <input type="submit" style="float: right; margin-top: 2px;" class="button remove_site_losy" value="Reset">
                        </div>
                        <div class="pad">
                            <div class="radio-btns-cont-losy" style="margin-top: 15px;">
                                <?php echo $local_sync_options->get_sync_type_db_or_files_echo(); ?>
                            </div>
                        </div>
                        <div class="pad cf" style="padding-top: 0;">
                            <div class="col col50">
                                <button class="button sync_from_live_site both_sync_losy" disabled style="float: left; margin: 5px 0 4px;">Pull from Prod site</button>
                            </div>
                            <div class="col col50">
                                <button type="submit" class="button button-primary push_to_live_site both_sync_losy" disabled style="float: right; margin: 5px 0 4px;">Push to Prod site</button>
                            </div>
                        </div>
                    </div>
                    <div class="m-box-losy">
                        <h2 class="hd">
                            Dev site settings
                        </h2>
                        <div class="pad">
                            <div class="all-caps-heading">LOAD IMAGES FROM PRODUCTION SITE</div>
                            <div class="radio-btns-cont-losy">
                                <?php echo $local_sync_options->get_load_images_from_live_site_echo(); ?>
                            </div>
                        </div>
                        <hr>
                        <div class="pad">
                            <div class="all-caps-heading" style="margin-bottom:5px;">INCLUDE/EXCLUDE CONTENT FOR PUSHING TO
                                PRODUCTION</div>
                                <em>These settings apply when pushing to production from the dev site. For include/exclude
                                    settings for pulling from production, <a href="<?php echo $prod_site_url . '/wp-admin/admin.php?page=local-sync%2Fadmin%2Fviews%2Flocal-sync-settings-display.php'; ?>" target="_blank" style="color:#444;">click
                                        here</a>.</em><br><br>
                            <div class="form-row">
                                <label>Select folders, files or DB tables to include</label>
                                <button class="button button-secondary local_sync_dropdown" id="toggle_exlclude_files_n_folders_staging" style="width: 424px; margin-top: 10px; outline:none; text-align: left;">
        							<span class="dashicons dashicons-portfolio" style="position: relative; top: 3px; font-size: 20px"></span>
        							<span style="left: 10px; position: relative;">Folders &amp; Files </span>
        							<span class="dashicons dashicons-arrow-down" style="position: relative; top: 3px; float: right;"></span>
        						</button>
        						<div style="display:none; width: 424px;" id="local_sync_exc_files_staging"></div>
        						<button class="button button-secondary local_sync_dropdown" id="toggle_local_sync_db_tables_staging" style="width: 424px; margin-top: 10px; outline:none; text-align: left;">
        							<span class="dashicons dashicons-menu" style="position: relative;top: 3px; font-size: 20px"></span>
        							<span style="left: 10px; position: relative;">Database</span>
        							<span class="dashicons dashicons-arrow-down" style="position: relative;top: 3px; float: right;"></span>
        						</button>
        						<div style="display:none; width: 424px;font-weight: " id="local_sync_exc_db_files_staging"></div>
                            </div>
                            <div class="form-row">
                                <label>Exclude files of these extensions</label>
                                <textarea rows="3" name="user_excluded_extenstions" id="user_excluded_extenstions" ><?php echo $user_excluded_extenstions; ?></textarea>
                            </div>
                            <div class="form-row" style="margin-bottom:0px;">
                                <?php echo $local_sync_options->get_user_excluded_files_more_than_size_for_echo(); ?>
                            </div>
                        </div>
                        <div class="pad" style="border-top: 1px solid #e5e5e5; text-align: right;">
                            <input type="submit" class="button button-primary save_settings_local_sync" value="Save Changes">
                        </div>
                    </div>
                <?php } ?>
            <?php endif; ?>
            <?php if($show_prod_site_options) : ?>
            <div class="m-box-losy">
                <h2 class="hd">Connect Live site & Duplicate site <a href="https://docs.localsync.io" target="_blank">help?</a> </h2>
                <div class="pad">
                    Copy and paste this key on the duplicate site, you want to clone. 
                </div>
                <div class="pad">
                    <div class="cf" style="padding-top: 0;">
                        <input style="width: 360px; height: 30px;" type="text" name="prod_key_losy" class="prod_key_losy" id="prod_key_id_losy" value="<?php echo $prod_key; ?>">
                        <div class="col col20">
                            <button class="button copy_prod_url_losy" data-clipboard-target="#prod_key_id_losy" style="float: left; margin: 2px 0 4px;">Copy</button>
                        </div>
                    </div>
                    <div style="font-size: 11px; padding-top: 5px;">Copy &amp; Paste this key in the Dev site's WP Admin Dashboard -> WP Duplicate.</div>
                </div>
            </div>
            <div class="m-box-losy">
                <h2 class="hd">
                    Production site settings
                </h2>
                <div class="pad">
                    <div class="all-caps-heading" style="margin-bottom:5px;">INCLUDE/EXCLUDE CONTENT FOR PUSHING TO LOCAL</div>
                                <em>These settings apply when pulling this live site from the dev site. For include/exclude
                                    settings for pulling from dev site, go to the Dev site's <strong>WP Duplicate Plugin</strong> settings</em><br><br>
                    <div class="form-row">
                        <label>Select folders, files or DB tables to include</label>
                        <button class="button button-secondary local_sync_dropdown" id="toggle_exlclude_files_n_folders_staging" style="width: 424px; margin-top: 10px; outline:none; text-align: left;">
							<span class="dashicons dashicons-portfolio" style="position: relative; top: 3px; font-size: 20px"></span>
							<span style="left: 10px; position: relative;">Folders &amp; Files </span>
							<span class="dashicons dashicons-arrow-down" style="position: relative; top: 3px; float: right;"></span>
						</button>
						<div style="display:none; width: 424px;" id="local_sync_exc_files_staging"></div>
						<button class="button button-secondary local_sync_dropdown" id="toggle_local_sync_db_tables_staging" style="width: 424px; margin-top: 10px; outline:none; text-align: left;">
							<span class="dashicons dashicons-menu" style="position: relative;top: 3px; font-size: 20px"></span>
							<span style="left: 10px; position: relative;">Database</span>
							<span class="dashicons dashicons-arrow-down" style="position: relative;top: 3px; float: right;"></span>
						</button>
                        <div style="display:none; width: 424px;font-weight: " id="local_sync_exc_db_files_staging"></div>
                    </div>
                    <div class="form-row">
                        <label>Exclude files of these extensions</label>
                        <textarea rows="3" name="user_excluded_extenstions" id="user_excluded_extenstions" ><?php echo $user_excluded_extenstions; ?></textarea>
                    </div>
                    <div class="form-row" style="margin-bottom: 0px;"><?php echo $local_sync_options->get_user_excluded_files_more_than_size_for_echo(); ?></div>
                </div>
                <div class="pad" style="border-top: 1px solid #e5e5e5;">
                    <input type="submit" class="button button-primary logout_local_sync" value="Logout">
                    <input type="submit" style="float: right;" class="button button-primary save_settings_local_sync" value="Save Changes">
                    <div style="clear: both;"></div>
                </div>
            </div>
            <?php endif; ?>
        </div>
        <div class="main-cols-losy step_container_losy">
            <div class="process-steps-progress-losy steps-result-losy" style="padding: 10px 0 0 30px; display: none;">
            </div>
        </div>
        <div style="clear: both;"></div>
    </div>
    <div style="clear: both;"></div>
</div>

<?php } ?>

<script type="text/javascript">
	losy_ajax_nonce = '<?php echo $local_sync_ajax_nonce; ?>';
    PROD_RANDOM_KEY_ID = '<?php echo $prod_key_random_id ?>';
</script>
