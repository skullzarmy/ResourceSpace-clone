<?php

if (!function_exists("rse_workflow_get_actions")){
    function rse_workflow_get_actions($status="",$ref="")
            {
            # Check if we are searching for actions specific to a status
            $condition="";
            if($status!="" && is_int($status)){$condition=" where wa.statusfrom='status' ";}
            if($ref!=""){$condition=" where wa.ref='$ref' ";}
            $actions=sql_query("select wa.ref, wa.text, wa.name, wa.buttontext, wa.statusfrom, wa.statusto,a.notify_group,a.name as statusto_name,a.more_notes_flag,a.notify_user_flag, a.email_from, a.bcc_admin from workflow_actions wa left outer join archive_states a on wa.statusto=a.code $condition group by wa.ref order by wa.ref,wa.statusfrom,wa.statusto asc");
            return $actions;
            }
    }

if (!function_exists("rse_workflow_save_action")){
    function rse_workflow_save_action($ref="")
            {
            if($ref==""){$ref=getvalescaped("ref","");};
            $fromstate=getvalescaped("actionfrom","");
            $tostate=getvalescaped("actionto","");
            $name=getvalescaped("actionname","");
            $text=getvalescaped("actiontext","");
            $buttontext=getvalescaped("actionbuttontext","");
            
            # Check if we are searching for actions specific to a status
            sql_query("update workflow_actions set name='$name', text='$text', buttontext='' statusfrom='$fromstate', statusto='$tostate' where ref='$ref'");
            return true;
            }
    }

if (!function_exists("rse_workflow_delete_action")){
    function rse_workflow_delete_action($action)
        {
        sql_query("delete from workflow_actions where ref='$action'");
        return true;  
        }
    }   

if (!function_exists("rse_workflow_get_archive_states")){
    function rse_workflow_get_archive_states()
            {
            $rawstates=sql_query("select code, name, notify_group, more_notes_flag, notify_user_flag, email_from, bcc_admin from archive_states order by code asc");
            global $additional_archive_states, $lang;
            $states=array();
            foreach($rawstates as $rawstate)
                {
                // Reformat into new array
                $states[$rawstate['code']]['name']=$rawstate['name'];
                $states[$rawstate['code']]['notify_group']=$rawstate['notify_group'];
                $states[$rawstate['code']]['more_notes_flag']=$rawstate['more_notes_flag'];
                $states[$rawstate['code']]['notify_user_flag']=$rawstate['notify_user_flag'];
                $states[$rawstate['code']]['rse_workflow_email_from']=$rawstate['email_from'];
                $states[$rawstate['code']]['rse_workflow_bcc_admin']=$rawstate['bcc_admin'];
                // Identify states that are set in config.php and cannot be deleted from plugin                
                if(in_array($rawstate['code'],$additional_archive_states))
                    {
                    $states[$rawstate['code']]['fixed']=true;
                    }
                else
                    {
                    $states[$rawstate['code']]['fixed']=false; 
                    }
                }
            
            //Add $additional_archive_states from config.php to table so can be managed by plugin if deleted from config                    
            foreach($additional_archive_states as $additional_archive_state)
                {
                if (!isset($states[$additional_archive_state]))
                    {
                    // Set name of archive state (will just be the ref if not set in lang file)
                    $statename= $additional_archive_state; 
                    if(isset($lang['status' . $additional_archive_state]))
                        {
                        $statename=$lang['status' . $additional_archive_state];
                        }
                    sql_query("insert into archive_states set code='$additional_archive_state', name='$statename'");
                    $states[$additional_archive_state]['name']=$lang['status' . $additional_archive_state];
                    $states[$additional_archive_state]['fixed']=true;
                    }
                }
				
	    //Add default system states                  
            for ($a=-2;$a<=3;$a++)
                {     
		$statename=$lang['status' . $a];
		if (!isset($states[$a]))
		   {
		   sql_query("insert into archive_states set code='$a', name='$statename'");  
		   } 
		$states[$a]['name']=$lang['status' . $a];
		$states[$a]['fixed']=true;		                   
                }
            return $states;
            }
    }
    
if (!function_exists("rse_workflow_delete_state")){
    function rse_workflow_delete_state($state,$newstate)
        {		
        sql_query("update resource set archive='$newstate' where archive='$state'");
        sql_query("delete from archive_states where code='$state'");
        return true;  
        }
    } 
