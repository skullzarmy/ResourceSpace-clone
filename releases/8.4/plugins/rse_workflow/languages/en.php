<?php
# English
# Language File for the rse_workflow plugin
# -------
#
#

$lang['rse_workflow_configuration']="Workflow configuration";
$lang['rse_workflow_summary'] = "<div>This plugin allows you to create additional archive (workflow) states, as well as defining actions to describe the movement between states.   <br><br></div>";
$lang['rse_workflow_introduction']="To amend workflow states and actions, use the 'Manage workflow actions' and 'Manage Workflow states' from Team Centre. Click %%HERE to go to Team Centre";
$lang['rse_workflow_user_info'] = "You can perform the following actions on this resource. These actions will change the workflow status of this resource, and may trigger actions for other users.";
$lang['rse_workflow_actions_heading']="Workflow actions";
$lang['rse_workflow_manage_workflow']="Manage workflow";
$lang['rse_workflow_manage_actions']="Manage workflow actions";
$lang['rse_workflow_manage_states']="Manage workflow states";
$lang['rse_workflow_status_heading']="Actions defined ";
$lang['rse_workflow_action_new']="Create new action";
$lang['rse_workflow_state_new']="Create new workflow state";
$lang['rse_workflow_action_reference']="Action reference (permission)";
$lang['rse_workflow_action_name']="Action name";
$lang['rse_workflow_action_filter']="Filter actions applicable to a state";
$lang['rse_workflow_action_text']="Action text";
$lang['rse_workflow_button_text']="Button text";
$lang['rse_workflow_new_action']="Create new action";
$lang['rse_workflow_action_status_from']="From status";
$lang['rse_workflow_action_status_to']="Destination status";
$lang['rse_workflow_action_check_fields']="Invalid options for workflow action, please check your selected options";
$lang['rse_workflow_action_none_defined']="No workflow actions have been defined";
$lang['rse_workflow_action_edit_action']="Edit action";
$lang['rse_workflow_action_none_specified']="No action specified";
$lang['rse_workflow_action_deleted']="Action deleted";
$lang["rse_workflow_access"]="Access to workflow action";
$lang["rse_workflow_saved"]="Resource successfully moved to state:";
$lang['rse_workflow_edit_state']="Edit workflow state";
$lang['rse_workflow_state_reference']="Workflow state reference";
$lang['rse_workflow_state_name']="Workflow state name";
$lang['rse_workflow_state_fixed']="Fixed in config.php";
$lang["rse_workflow_state_not_editable"]="This archive state is not editable, it either is a required system state, has been set in config.php or does not exist";
$lang['rse_workflow_state_check_fields']="Invalid name or reference for workflow state, please check your entries";
$lang['rse_workflow_state_deleted']="Workflow state deleted";
$lang["rse_workflow_confirm_action_delete"]="Are you sure you want to delete this action?";
$lang["rse_workflow_confirm_state_delete"]="Are you sure you want to delete this workflow state?";
$lang["rse_workflow_state_need_target"]="Please specify a target state reference for any existing resources in this workflow state";


$lang["rse_workflow_state_notify_group"]="When resources enter this state, notify user group:";

$lang["rse_workflow_state_notify_message"]="There are new resources in the workflow state: ";

// For more notes functionality:
$lang['rse_workflow_more_notes_label'] = 'More notes when changing workflow?';
$lang['rse_workflow_notify_user_label'] = 'Should contributor be notified?';
$lang['rse_workflow_link_open'] = 'More';
$lang['rse_workflow_link_close'] = 'Close';
$lang['rse_workflow_more_notes_title'] = 'Notes:';
$lang['rse_workflow_email_from'] = 'Email address to send notification from (will use %EMAILFROM% if blank):';
$lang['rse_workflow_bcc_admin'] = 'Check to BCC the system admin email address (%ADMINEMAIL%) if the contributor is notified';