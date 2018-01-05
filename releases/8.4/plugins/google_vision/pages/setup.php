<?php

// Do the include and authorization checking ritual
include '../../../include/db.php';
include_once '../../../include/general.php';
include '../../../include/authenticate.php'; if (!checkperm('a')) {exit ($lang['error-permissiondenied']);}

// Specify the name of this plugin and the heading to display for the page.
$plugin_name = 'google_vision';
if (!in_array($plugin_name, $plugins))
	{plugin_activate_for_setup($plugin_name);}
$page_heading = $lang['google_vision_api'];
$page_intro = '';

// Build configuration variable descriptions
$page_def[] = config_add_text_input("google_vision_api_key",$lang["google_vision_api_key"]);
$page_def[]= config_add_single_ftype_select("google_vision_label_field", $lang["google_vision_label_field"],300,false,$FIXED_LIST_FIELD_TYPES); 
$page_def[]= config_add_single_ftype_select("google_vision_landmarks_field", $lang["google_vision_landmarks_field"],300,false,$TEXT_FIELD_TYPES); 
$page_def[]= config_add_single_ftype_select("google_vision_text_field", $lang["google_vision_text_field"],300,false,$TEXT_FIELD_TYPES); 


$page_def[] = config_add_multi_rtype_select("google_vision_restypes", $lang["google_vision_restypes"]);

$page_def[] = config_add_multi_select("google_vision_features", $lang["google_vision_features"], array("LABEL_DETECTION","LANDMARK_DETECTION","TEXT_DETECTION"), false);

$page_def[] = config_add_boolean_select("google_vision_autotitle", $lang["google_vision_autotitle"],'',300);


// Do the page generation ritual
$upload_status = config_gen_setup_post($page_def, $plugin_name);
include '../../../include/header.php';
config_gen_setup_html($page_def, $plugin_name, $upload_status, $page_heading, $page_intro);
include '../../../include/footer.php';
