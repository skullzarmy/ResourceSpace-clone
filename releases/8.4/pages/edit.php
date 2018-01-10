<?php
include_once "../include/db.php";
include_once "../include/general.php";
include "../include/authenticate.php"; 
include_once "../include/resource_functions.php";
include_once "../include/collections_functions.php";
include_once "../include/search_functions.php";
include_once "../include/image_processing.php";
include_once '../include/node_functions.php';
include_once '../include/render_functions.php';

# Editing resource or collection of resources (multiple)?
$ref=getvalescaped("ref","",true);
if(getval("create","")!="" && $ref==0 && $userref>0){$ref=0-$userref;} // Saves manual link creation having to work out user template ref

# Fetch search details (for next/back browsing and forwarding of search params)
$search=getvalescaped("search","");
$order_by=getvalescaped("order_by","relevance");
$offset=getvalescaped("offset",0,true);
$restypes=getvalescaped("restypes","");
if (strpos($search,"!")!==false) {$restypes="";}
$default_sort_direction="DESC";
if (substr($order_by,0,5)=="field"){$default_sort_direction="ASC";}
$sort=getval("sort",$default_sort_direction);
$modal=(getval("modal","")=="true");
$single=getval("single","")!="" || getval("forcesingle","")!="";
$disablenavlinks=getval("disablenav","")=="true";
$uploader = getvalescaped("uploader","");
$collection     = getvalescaped('collection', '', true);

$archive=getvalescaped("archive",0,true); // This is the archive state for searching, NOT the archive state to be set from the form POST which we get later
$autorotate = getval("autorotate","");

$collection_add = getvalescaped('collection_add', '');
$local = getvalescaped("local","");

if($embedded_data_user_select)
  {
  $no_exif=getval("exif_option","");
  }
else
  {
  $no_exif=getval("no_exif","");
  }

$uploadparams = array();
$uploadparams["relateto"] = getval("relateto","");
$uploadparams["redirecturl"] =  getval("redirecturl","");
$uploadparams["collection_add"] =  $collection_add;
$uploadparams["local"] =  $local;
$uploadparams["metadatatemplate"] = getval("metadatatemplate","");
$uploadparams["no_exif"] = $no_exif;
$uploadparams["themestring"] = getval("themestring","");
$uploadparams["public"] = getval("public","");
$uploadparams["autorotate"] = $autorotate;
$uploadparams["entercolname"] = getvalescaped("entercolname","");

# Are we in upload review mode?
$upload_review_mode=(getval("upload_review_mode","")!="" || $search=="!collection-" . $userref);
$lastedited = getval('lastedited',0,true);
$lockable_fields = $upload_review_lock_metadata && $upload_review_mode;
$locked_fields = (getval("lockedfields","") != "") ? trim_array(explode(",",getval("lockedfields",""))) : array();
$save_auto_next = getval("save_auto_next","") != "";

if ($upload_review_mode)
    {
    # Set the collection and ref if not already set.
    $collection=0-$userref;
    # Start reviewing at the first resource. Need to search all worflow states and remove filters as no data has been set yet
    $search_all_workflow_states_cache = $search_all_workflow_states;
    $usersearchfilter_cache = $usersearchfilter;
    $search_all_workflow_states = TRUE;
    $check_edit_checksums = false;
    $usersearchfilter = "";
    $review_collection_contents=do_search("!collection" . $collection);
    # Revert save settings
    $search_all_workflow_states = $search_all_workflow_states_cache;
    $usersearchfilter = $usersearchfilter_cache;
    # Set the resource to the first ref number. If the collection is empty then tagging is complete. Go to the recently added page.
    if (isset($review_collection_contents[0]["ref"]))
        {
        $ref=$review_collection_contents[0]["ref"];
        }
    else 
        {
        redirect("pages/search.php?search=!last1000");
        }
    }

// Ability to avoid editing conflicts by checking checksums.
// NOTE: this should NOT apply to upload.
$check_edit_checksums = true;
if($ref < 0 || $upload_review_mode)
    {
    $check_edit_checksums = false;
    }

global $merge_filename_with_title;
if($merge_filename_with_title && $ref < 0)
    {
    $merge_filename_with_title_option = getval('merge_filename_with_title_option', '');
    $merge_filename_with_title_include_extensions = getval('merge_filename_with_title_include_extensions', '');
    $merge_filename_with_title_spacer = getval('merge_filename_with_title_spacer', '');

    if($merge_filename_with_title_option != '') 
        {
        $uploadparams["merge_filename_with_title_option"] = $merge_filename_with_title_option;
        }
    if($merge_filename_with_title_include_extensions != '')
        {
        $uploadparams["merge_filename_with_title_include_extensions"] = $merge_filename_with_title_include_extensions;
        }
    if($merge_filename_with_title_spacer != '')
        {
        $uploadparams["merge_filename_with_title_spacer"] = $merge_filename_with_title_spacer;
        }
    }

global $tabs_on_edit;
$collapsible_sections=true;
if($tabs_on_edit || $upload_review_mode){$collapsible_sections=false;}

$errors=array(); # The results of the save operation (e.g. required field messages)

# Disable auto save for upload forms - it's not appropriate.
if ($ref<0 || $upload_review_mode) { $edit_autosave=false; }

# next / previous resource browsing
$go=getval("go","");
if ($go!="")
    {
    # Re-run the search and locate the next and previous records.
    $modified_result_set=hook("modifypagingresult"); 
    if ($modified_result_set)
        {
        $result=$modified_result_set;
        }
    else
        {    
        $result=do_search($search,$restypes,$order_by,$archive,240+$offset+1,$sort);
        }
    if (is_array($result))
        {
        # Locate this resource
        $pos=-1;
        for ($n=0;$n<count($result);$n++)
            {
            if ($result[$n]["ref"]==$ref) {$pos=$n;}
            }
        if ($pos!=-1)
            {
            if (($go=="previous") && ($pos>0)) 
                {
                $ref=$result[$pos-1]["ref"];
                }
            if (($go=="next") && ($pos<($n-1)))
                {
                $ref=$result[$pos+1]["ref"];
                if (($pos+1)>=($offset+72))
                    {
                    $offset=$pos+1;
                    }
                } # move to next page if we've advanced far enough
            }
        else
            {
            ?>
            <script type="text/javascript">
            alert("<?php echo $lang["resourcenotinresults"] ?>");
            </script>
            <?php
            }
        }
   }

$collection=getvalescaped("collection","",true);
if ($collection!="") 
    {
    # If editing multiple items, use the first resource as the template
     $multiple=true;
    $edit_autosave=false; # Do not allow auto saving for batch editing.
    $items=get_collection_resources($collection);
	if (count($items)==0) {
       $error=$lang['error-cannoteditemptycollection'];
       error_alert($error);
       exit();
    }
    
    # check editability
    if (!allow_multi_edit($collection)){
       $error=$lang['error-permissiondenied'];
       error_alert($error);
       exit();
    }
    $ref=$items[0];
    }
else
    {
    $multiple=false;
    }

# Fetch resource data.
$resource=get_resource_data($ref);
if ($lockable_fields && $lastedited > 0)
    {
    // Update resource data with locked resource data from last edited resource
    $resource = copy_locked_data($resource, $locked_fields, $lastedited);
    }

# Allow to specify resource type from url for new resources
$resource_type=getval("resource_type","");
if ($ref<0 && $resource_type!="" && $resource_type!=$resource["resource_type"] && !checkperm("XU{$resource_type}"))     // only if new resource specified and user has permission for that resource type
	{
	update_resource_type($ref,intval($resource_type));
    $resource["resource_type"] = $resource_type;
	}

if(in_array($resource['resource_type'], $data_only_resource_types))
    {
    $single=true;
    $uploadparams["single"] = "true";
    }
else
    {
    $uploadparams["forcesingle"] = '';
    $uploadparams["noupload"] = '';
    }

$uploadparams["resource_type"] = $resource['resource_type'];   
$setarchivestate = getvalescaped('status', $resource["archive"], TRUE);
$uploadparams["status"] = $setarchivestate;

# Allow alternative configuration settings for this resource type.
resource_type_config_override($resource["resource_type"]);

# File readonly?
$resource_file_readonly=resource_file_readonly($ref);

# If upload template, check if the user has upload permission.
if ($ref<0 && !(checkperm("c") || checkperm("d")))
{
  $error=$lang['error-permissiondenied'];
  error_alert($error);
  exit();
}

# Check edit permission.
if (!get_edit_access($ref,$resource["archive"],false,$resource))
{
    # The user is not allowed to edit this resource or the resource doesn't exist.
  $error=$lang['error-permissiondenied'];
  error_alert($error,!$modal);
  exit();
}

if (getval("regen","")!="")
{
  sql_query("update resource set preview_attempts=0 WHERE ref='" . $ref . "'");
  create_previews($ref,false,$resource["file_extension"]);
}

if (getval("regenexif","")!="")
{
  extract_exif_comment($ref);
}

# Establish if this is a metadata template resource, so we can switch off certain unnecessary features
$is_template=(isset($metadata_template_resource_type) && $resource["resource_type"]==$metadata_template_resource_type);

# If config option $blank_edit_template is set and form has not yet been submitted, blank the form for user edit templates.
if(0 > $ref && $blank_edit_template && '' == getval('submitted', ''))
    {
    clear_resource_data($ref);
    }

// If using metadata templates, make sure user templates are cleared but not when form is being submitted
if(0 > $ref && '' == getval('submitted', '') && isset($metadata_template_resource_type) && !$multiple)
    {
    clear_resource_data($ref);
    }

if($ref < 0 && $resource_type_force_selection)
  {
  $resource_type = "";
  $resource["resource_type"] = "";
  }
        
# check for upload disabled due to space limitations...
if ($ref<0 && isset($disk_quota_limit_size_warning_noupload))
  {
	# check free space
	if (isset($disksize)){ # Use disk quota rather than real disk size
		$avail=$disksize*(1024*1024*1024);
		$used=get_total_disk_usage();
		$free=$avail-$used;
	}
	else{		
		$avail=disk_total_space($storagedir);
		$free=disk_free_space($storagedir);
		$used=$avail-$free;
	}
	
	# echo "free: ".$free."<br/>";
	# convert limit
	$limit=$disk_quota_limit_size_warning_noupload*1024*1024*1024;
	# echo "no_upload: ".$limit."<br/>";
	# compare against size setting
	if($free<=$limit){
		# shut down uploading by redirecting to explanation page
		$explain=$baseurl_short."pages/no_uploads.php";
		redirect($explain);
	}
  }

  
$urlparams= array(
	'ref'				=> $ref,
    'search'			=> $search,
    'order_by'			=> $order_by,
    'offset'			=> $offset,
    'restypes'			=> $restypes,
    'archive'			=> $archive,
    'default_sort_direction' => $default_sort_direction,
    'sort'				=> $sort,
    'uploader'          => $uploader,
    'single'            => ($single ? "true" : ""),
    'collection'        => $collection
);

hook("editbeforeheader");

if(($embedded_data_user_select && getval("exif_option","")=="custom") || isset($embedded_data_user_select_fields))  
    {
    $exif_override=false;
    foreach($_POST as $postname=>$postvar)
        {
        if (strpos($postname,"exif_option_")!==false)
            {
            $uploadparams [$postname] = $postvar;
            $exif_override=true;
            }
        }
        
    if($exif_override)
        {
        $uploadparams["exif_override"] = "true";
        }
    }
        
# -----------------------------------
#           PERFORM SAVE
# -----------------------------------

if ((getval("autosave","")!="") || (getval("tweak","")=="" && getval("submitted","")!="" && getval("resetform","")=="" && getval("copyfrom","")==""))
    {
    hook("editbeforesave"); 
    
	if(!$multiple)
        {
        $save_errors = process_edit_form($ref, $resource);
        
        if (($save_errors === true || $is_template) && getval("tweak","")=="")
            {
            if ($ref > 0 && getval("save","") != "")
                {
                # Log this
                daily_stat("Resource edit",$ref);
                if ($upload_review_mode)
                    {
                    # Drop this resource from the collection and either save all subsequent resources, or redirect thus picking the next resource.
                    remove_resource_from_collection($ref,0-$userref);refresh_collection_frame();
                    
                    if($save_auto_next)
                        {
                        // Process all remaining resources in the collection
                        $autosave_errors = false; 
                        $lastedited = $ref;
                        for($n=1;$n<count($review_collection_contents);$n++)
                            {
                            $auto_errors = array();
                            $ref = $review_collection_contents[$n]["ref"];           
                            # Fetch resource data.
                            $resource=get_resource_data($ref);
                            
                            // Load resource metadata
                            $fields=get_resource_field_data($ref,$multiple,!hook("customgetresourceperms"),-1,"",$tabs_on_edit);
                            $all_selected_nodes = get_resource_nodes($ref);
                            
                            // Update resource data with locked resource data from last edited resource
                            $resource = copy_locked_data($resource, $locked_fields, $lastedited, true);
                                 
                            // Update $fields and all_selected_nodes with details of the last resource edited for locked fields
                            // NOTE: $fields and $all_selected_nodes are passed by reference
                            copy_locked_fields($ref,$fields,$all_selected_nodes,$locked_fields,$lastedited, true);
                            
                            // Update related resources if required
                            if(in_array("related_resources",$locked_fields))
                                {
                                copyRelatedResources($lastedited, $ref);
                                }
                                
                            // Check for any missing fields
                            $exemptfields =array();
                            foreach($fields as $field)
                                {
                                $fielderror = false;
                                if($field['required'] == 1)
                                    {
                                    $displaycondition = check_display_condition(0, $field, true); 
                                    if($displaycondition)
                                        {
                                        if(in_array($field['type'], $FIXED_LIST_FIELD_TYPES))
                                            {
                                            $field_nodes = get_resource_nodes($ref, $field['ref']);                                
                                            if(count($field_nodes) == 0)
                                                {
                                                $fielderror = true;
                                                }
                                            }
                                        else
                                            {
                                            if (trim(strip_leading_comma($field["value"]) == ''))
                                                {
                                                $fielderror = true;
                                                }
                                            }
                                        }
                                    }
                                    
                                # Also check for regular expression match
                                if (trim(strlen($field["regexp_filter"]))>=1)
                                    {
                                    if(preg_match("#^" . $field["regexp_filter"] . "$#",$field["value"],$matches) <= 0)
                                        {
                                        $fielderror = true;
                                        }
                                    }
                                
                                if($fielderror)
                                    {
                                    $auto_errors[$field['ref']] = i18n_get_translated($field['title']) . ": {$lang['requiredfield']}";
                                    }
                                }              
                            
                            // If no errors, remove from collection and continue
                            if(count($auto_errors) == 0)
                                {                
                                remove_resource_from_collection($ref,0-$userref);
                                }
                            else
                                { 
                                $autosave_errors = true;                   
                                }
                            }
                        if($autosave_errors)
                            {
                            // Redirect to upload_review_mode in order to finish editing remaining resources that errored, include submit to generate required error message
                            ?>
                            <script>CentralSpaceLoad('<?php echo generateURL($baseurl_short . "pages/edit.php",$urlparams, array("upload_review_mode"=>"true","lastedited"=>$lastedited,"submitted"=>"true","save"=>"true")); ?>',true);</script>
                            <?php
                            exit();
                            }
                        else
                            {
                            // All saved, redirect to recent user uploads to the set archive state
                            ?>
                            <script>CentralSpaceLoad('<?php echo generateURL($baseurl_short . "pages/search.php",array("search"=>"!contributions" . $userref,"order_by"=>"resourceid","sort"=>"DESC","archive"=>$setarchivestate,"refreshcollectionframe"=>"true","resetlockedfields"=>"true")); ?>',true);</script>
                            <?php
                            exit();
                            }
                        }
                    else
                        {
                        // Redirect to next resource
                        ?>
                        <script>CentralSpaceLoad('<?php echo generateURL($baseurl_short . "pages/edit.php",$urlparams, array("upload_review_mode"=>"true","lastedited"=>$ref)); ?>',true);</script>
                        <?php
                        exit();
                        }
                  }
                if (!hook('redirectaftersave') && !$modal)
                  {
                  redirect(generateURL($baseurl_short . "pages/view.php",$urlparams, array("refreshcollectionframe"=>"true")));
                  }
                }
            else
                {
                // Upload template
                if (getval("save","")!="")
                    {
                    # Save button pressed? Move to next step.
                    if ($single) // Test if single upload (archived or not).
                        {
                        // If noupload is set - create resource without uploading stage
                        if (getval("noupload","") != "")
                            {
                            $ref=copy_resource(0-$userref);
                            if(is_numeric($collection_add))
                                {
                                add_resource_to_collection($ref, $collection_add,false,"",$resource_type);
                                set_user_collection($userref, $collection_add);
                                }
                            redirect(generateURL($baseurl_short . "pages/view.php",$urlparams, array("refreshcollectionframe"=>"true")));
                            exit();
                            }
                        redirect(generateURL($baseurl_short . "pages/upload.php",array_merge($urlparams,$uploadparams), array("resource_type"=>$resource_type)));
                        }
                    elseif ($uploader != "" && $uploader !="local")
                        {
                        redirect(generateURL($baseurl_short . "pages/upload_" . urlencode($uploader) . ".php",array_merge($urlparams,$uploadparams)) . hook("addtouploadurl"));
                        }
                    elseif (getval("local","") != "" || $uploader == "local")// Test if fetching resource from local upload folder.
                        {
                        redirect(generateURL($baseurl_short . "pages/team/team_batch_select.php",array_merge($urlparams,$uploadparams), array("use_local"=>"yes")) . hook("addtouploadurl"));
                        }
                    else // Hence fetching from ftp.
                        {
                        redirect(generateURL($baseurl_short . "pages/team/team_batch.php",array_merge($urlparams,$uploadparams)) . hook("addtouploadurl"));
                        }
                    }
                }
            }
        elseif (getval("save","")!="")
            {  
            $show_error=true;
            }
        # If auto-saving, no need to continue as it will only add to bandwidth usage to send the whole edit page back to the client. Send a simple 'SAVED' message instead.
        if (getval("autosave","")!="")
            {
            $return=array();
            if(!is_array($save_errors))
                {
                $return["result"] = "SAVED";
                if(isset($new_checksums))
                    {
                    $return["checksums"] = array();
                    foreach($new_checksums as $fieldref=>$checksum)
                        {
                        $return["checksums"][$fieldref] = $checksum;
                        }
                    }
                }
            else
                {
                $return["result"] = "ERROR";
                $return["errors"] = $save_errors;
                }
            echo json_encode($return);
            exit();
            }
            
        }
    else    
        {
		# Save multiple resources
		$save_errors=save_resource_data_multi($collection);
		if(!is_array($save_errors) && !hook("redirectaftermultisave"))
            {
            redirect(generateURL($baseurl_short . "pages/search.php",$urlparams,array("refreshcollectionframe"=>"true","search"=>"!collection" . $collection)));
            }      
		$show_error=true;
		}
    }
    
if (getval("tweak","")!="" && !$resource_file_readonly)
   {
   $tweak=getval("tweak","");
   switch($tweak)
      {
      case "rotateclock":
         tweak_preview_images($ref,270,0,$resource["preview_extension"]);
         break;
      case "rotateanti":
         tweak_preview_images($ref,90,0,$resource["preview_extension"]);
         break;
      case "gammaplus":
         tweak_preview_images($ref,0,1.3,$resource["preview_extension"]);
         break;
      case "gammaminus":
         tweak_preview_images($ref,0,0.7,$resource["preview_extension"]);
         break;
      case "restore":
		delete_previews($resource);
        sql_query("update resource set has_image=0, preview_attempts=0 WHERE ref='" . $ref . "'");
        if ($enable_thumbnail_creation_on_upload)
            {
            create_previews($ref,false,$resource["file_extension"],false,false,-1,true);
            refresh_collection_frame();
            }
        else
            {
            sql_query("update resource set preview_attempts=0, has_image=0 where ref='$ref'");
            }
        break;
      }
   hook("moretweakingaction", "", array($tweak, $ref, $resource));
   # Reload resource data.
   $resource=get_resource_data($ref,false);
   }

# Simulate reupload (preserving filename and thumbs, but otherwise resetting metadata).
if (getval("exif","")!="")
   {
   upload_file($ref,$no_exif=false,true);
   resource_log($ref,"r","");
   }   

# If requested, refresh the collection frame (for redirects from saves)
if (getval("refreshcollectionframe","")!="")
   {
   refresh_collection_frame();
   }

include "../include/header.php";
?>
<script>
<?php
if ($lockable_fields)
        {
        echo "lockedfields = " . (count($locked_fields) > 0 ? json_encode($locked_fields) : "new Array()") . ";";
        }?>

registerCollapsibleSections();

jQuery(document).ready(function()
{
   <?php
   if($ctrls_to_save)
     {?>
        jQuery(document).bind('keydown',function (e)
        {
          if (!(e.which == 115 && (e.ctrlKey || e.metaKey)) && !(e.which == 83 && (e.ctrlKey || e.metaKey)) && !(e.which == 19) )
          {
            return true;
         }
         else
         {
            event.preventDefault();
            if(jQuery('#mainform'))
            {
               jQuery('.AutoSaveStatus').html('<?php echo urlencode($lang["saving"]) ?>');
               jQuery('.AutoSaveStatus').show();
               jQuery.post(jQuery('#mainform').attr('action') + '&autosave=true',jQuery('#mainform').serialize(),

                  function(data)
                  {
				  saveresult=JSON.parse(data)
				  if (saveresult['result']=="SAVED")
					{
                    jQuery('.AutoSaveStatus').html('<?php echo urlencode($lang["saved"]) ?>');
                    jQuery('.AutoSaveStatus').fadeOut('slow');
					if (typeof(saveresult['checksums']) !== undefined)
						{
						for (var i in saveresult['checksums']) 
							{
                            if (jQuery.isNumeric(i))
                              {
                              jQuery("#field_" + i + "_checksum").val(saveresult['checksums'][i]);
                              }
                            else
                              {
                              jQuery('#' + i + '_checksum').val(saveresult['checksums'][i]);
                              }
							}
						}
					}
				  else
					{
					saveerrors = '<?php echo urlencode($lang["error_generic"]); ?>';
					if (typeof(saveresult['errors']) !== undefined)
						{
						saveerrors = "";
						for (var i in saveresult['errors']) 
							{
							saveerrors += saveresult['errors'][i] + "<br />";
							}
						}
					jQuery('.AutoSaveStatus').html('<?php echo urlencode($lang["save-error"]) ?>');
					jQuery('.AutoSaveStatus').fadeOut('slow');
					styledalert('<?php echo urlencode($lang["error"]) ?>',saveerrors,450);
					}
               });
            }
            return false;
         }
      });
<?php
}?>

});
<?php hook("editadditionaljs") ?>

function ShowHelp(field)
{
    // Show the help box if available.
    if (document.getElementById('help_' + field))
    {
       jQuery('#help_' + field).fadeIn();
    }
 }
 function HideHelp(field)
 {
    // Hide the help box if available.
    if (document.getElementById('help_' + field))
    {
       document.getElementById('help_' + field).style.display='none';
    }
 }

 jQuery(document).ready(function() {
    jQuery('#collection_add').change(function (){
      if(jQuery('#collection_add').val()=='new'){
        jQuery('#collectioninfo').fadeIn();
     } 
     else {
        jQuery('#collectioninfo').fadeOut();
     }
  });
    jQuery('#collection_add').change();
 }); 

 <?php
# Function to automatically save the form on field changes, if configured.
 if ($edit_autosave)
    { ?>
    preventautosave=false;
    
    // Disable autosave on enter keypress as form will be submitted by this keypress anyway which can result in duplicate data
    jQuery(document).bind('keydown',function (e)
        {               
        if (e.which == 13)
            {
              preventautosave=true;
            }
       else
            {       
            preventautosave=false;  
            }
        })
    

    // Disable autosave on enter keypress as form will be submitted by this keypress anyway which can result in duplicate data
    jQuery(document).bind('keydown',function (e)
    {               
      if (e.which == 13)
      {
        preventautosave=true;
     }
     else
     {       
        preventautosave=false;  
     }
    })


    function AutoSave(field, stop_recurrence)
        {
console.log('AUTOSAVE ' + field);
        stop_recurrence = typeof stop_recurrence === 'undefined' ? false : stop_recurrence;

        // If user has edited a field (autosave on) but then clicks straight on Save, this will prevent double save which can
        // lead to edit conflicts.
        if(!preventautosave && !stop_recurrence)
            {
            setTimeout(function()
                {
                AutoSave(field, true);
                }, 150);

            return false;
            }

        if(preventautosave)
            {
            return false;
            }

        jQuery('#AutoSaveStatus' + field).html('<?php echo $lang["saving"] ?>');
        jQuery('#AutoSaveStatus' + field).show();
        jQuery.post(jQuery('#mainform').attr('action') + '&autosave=true&autosave_field=' + field,jQuery('#mainform').serialize(),
            function(data)
                {
                saveresult=JSON.parse(data);
                if (saveresult['result']=="SAVED")
                    {
                    jQuery('#AutoSaveStatus' + field).html('<?php echo $lang["saved"] ?>');
                    jQuery('#AutoSaveStatus' + field).fadeOut('slow');
                    if (typeof(saveresult['checksums']) !== undefined)
                        {
                        for (var i in saveresult['checksums']) 
                            {
                            if (jQuery.isNumeric(i))
                                 {
                                 jQuery("#field_" + i + "_checksum").val(saveresult['checksums'][i]);
                                 }
                               else
                                 {
                                 jQuery('#' + i + '_checksum').val(saveresult['checksums'][i]);
                                 }
                            }
                        }					
                    }
                else
                    {   
                    saveerrors = '<?php echo urlencode($lang["error_generic"]); ?>';
                    if (typeof(saveresult['errors']) !== undefined)
                        {
                        saveerrors = "";
                        for (var i in saveresult['errors']) 
                            {
                            saveerrors += saveresult['errors'][i] + "<br />";
                            }
                        }
                    jQuery('#AutoSaveStatus' + field).html('<?php echo $lang["save-error"] ?>');
                    jQuery('#AutoSaveStatus' + field).fadeOut('slow');
                    styledalert('<?php echo $lang["error"] ?>',saveerrors);
                    }
                });
	}
<?php } 

# Resource next / back browsing.
function EditNav() # Create a function so this can be repeated at the end of the form also.
    {
    global $baseurl_short,$ref,$search,$offset,$order_by,$sort,$archive,$lang,$modal,$restypes,$disablenavlinks,$upload_review_mode, $urlparams;
    ?>
    <div class="backtoresults"> 
    <?php
    if(!$disablenavlinks && !$upload_review_mode)
        {?>
        <a class="prevLink fa fa-arrow-left" onClick="return <?php echo ($modal?"Modal":"CentralSpace") ?>Load(this,true);" href="<?php echo generateURL($baseurl_short . "pages/edit.php",$urlparams, array("go"=>"previous")); ?>"></a>
    
        <a class="upLink" onClick="return CentralSpaceLoad(this,true);" href="<?php echo generateURL($baseurl_short . "pages/search.php",$urlparams, array("go"=>"previous")); ?>"><?php echo $lang["viewallresults"]?></a>
    
        <a class="nextLink fa fa-arrow-right" onClick="return <?php echo ($modal?"Modal":"CentralSpace") ?>Load(this,true);" href="<?php echo generateURL($baseurl_short . "pages/edit.php",$urlparams, array("go"=>"next")); ?>"></a>
    
        <?php
        }
    if ($modal)
        { ?>
        &nbsp;&nbsp;<a class="maxLink fa fa-expand" href="<?php echo generateURL($baseurl_short . "pages/edit.php",$urlparams); ?>" onClick="return CentralSpaceLoad(this);"></a>
        &nbsp;<a href="#"  class="closeLink fa fa-times" onClick="ModalClose();"></a>
        <?php
        } ?>
    </div><?php
  }
  
function SaveAndClearButtons($extraclass="")
   { 
   global $lang,$multiple,$ref,$clearbutton_on_edit,$upload_review_mode;
   ?>
   <div class="QuestionSubmit <?php echo $extraclass ?>">
   <?php
   if($clearbutton_on_edit)
      { 
      ?>
      <input name="resetform" class="resetform" type="submit" value="<?php echo $lang["clearbutton"]?>" />&nbsp;
      <?php
      } ?>
    <input <?php if ($multiple) { ?>onclick="return confirm('<?php echo $lang["confirmeditall"]?>');"<?php } ?> name="save" class="editsave" type="submit" value="&nbsp;&nbsp;<?php echo ($ref>0)?($upload_review_mode?$lang["saveandnext"]:$lang["save"]):$lang["next"]?>&nbsp;&nbsp;" />
    <?php
    if ($upload_review_mode)
        {
        ?>&nbsp;<input name="save_auto_next" class="editsave save_auto_next" type="submit" value="&nbsp;&nbsp;<?php echo $lang["save_and_auto"] ?>&nbsp;&nbsp;" />
        <?php
        }?>
    <br /><br />
    
   <div class="clearerleft"> </div>
   </div>
   <?php 
   }

?>
</script>

<?php

if(0 > $ref)
    {
    // Include upload_params in form action url
    if(in_array($resource['resource_type'], $data_only_resource_types))
        {
        $uploadparams["noupload"] = "true";
        }
    else
        {
        $uploadparams["forcesingle"] = "";
        $uploadparams["noupload"] = "";
        }
    $form_action = generateURL($baseurl_short . "pages/edit.php",array_merge($urlparams,$uploadparams));
    }
else
    {
    $form_action = generateURL($baseurl_short . "pages/edit.php",array_merge($urlparams));
    }
?>

<form method="post"
      action="<?php echo $form_action; ?>"
      id="mainform"
      onsubmit="
        preventautosave = true;
        return <?php echo ($modal ? 'Modal' : 'CentralSpace'); ?>Post(this, true);
      ">
<input type="hidden" name="upload_review_mode" value="<?php echo ($upload_review_mode?"true":"")?>" />
   <div class="BasicsBox">
    
      <input type="hidden" name="submitted" value="true">
   <?php 
   if ($multiple) 
      { ?>
      <h1 id="editmultipleresources"><?php echo $lang["editmultipleresources"]?></h1>
      <p style="padding-bottom:20px;"><?php $qty = count($items);
      echo ($qty==1 ? $lang["resources_selected-1"] : str_replace("%number", $qty, $lang["resources_selected-2"])) . ". ";
      # The script doesn't allow editing of empty collections, no need to handle that case here.
      echo text("multiple");
      ?> </p> <?php
      if ($edit_show_save_clear_buttons_at_top) {SaveAndClearButtons("NoPaddingSaveClear");}
      } 
   elseif ($ref>0)
      {
      if (!hook('replacebacklink') && !$modal && !$upload_review_mode) 
         {?>
         <p><a href="<?php echo generateURL($baseurl_short . "pages/view.php",$urlparams); ?>" onClick="return CentralSpaceLoad(this,true);"><?php echo LINK_CARET_BACK ?><?php echo $lang["backtoresourceview"]?></a></p>
         <?php
         }
      if (!hook("replaceeditheader")) 
         { ?>
         <div class="RecordHeader">
          <?php
         # Draw nav
         if (!$multiple  && $ref>0  && !hook("dontshoweditnav")) { EditNav(); }
         
         if (!$upload_review_mode) { ?>
         <h1 id="editresource"><?php echo $lang["editresource"]?></h1>
         <?php } else { ?>
        <h1 id="editresource"><?php echo $lang["refinemetadata"]?></h1>
        <?php } ?>
        
         </div><!-- end of RecordHeader -->
         <?php
         if ($edit_show_save_clear_buttons_at_top || $upload_review_mode) { SaveAndClearButtons("NoPaddingSaveClear");}
         
         if (!$upload_review_mode)
            { ?>
            <div class="Question" id="resource_ref_div" style="border-top:none;">
               <label><?php echo $lang["resourceid"]?></label>
               <div class="Fixed"><?php echo urlencode($ref) ?></div>
               <div class="clearerleft"> </div>
            </div>
            <?php
            }
         }
      $custompermshowfile = hook('custompermshowfile');
      if((!$is_template && !checkperm('F*')) || $custompermshowfile)
         { ?>
         <div class="Question" id="question_file">
            <label><?php echo $lang["file"]?></label>
         <div class="Fixed">
         <?php
         if ($resource["has_image"]==1)
            { ?>
            <img id="preview" align="top" src="<?php echo get_resource_path($ref,false,($edit_large_preview && !$modal?"pre":"thm"),false,$resource["preview_extension"],-1,1,false)?>" class="ImageBorder" style="margin-right:10px;"/>
            <?php // check for watermarked version and show it if it exists
            if (checkperm("w"))
               {
               $wmpath=get_resource_path($ref,true,($edit_large_preview?"pre":"thm"),false,$resource["preview_extension"],-1,1,true);
               if (file_exists($wmpath))
                  { ?>
                  <img style="display:none;" id="wmpreview" align="top" src="<?php echo get_resource_path($ref,false,($edit_large_preview?"pre":"thm"),false,$resource["preview_extension"],-1,1,true)?>" class="ImageBorder"/>
                  <?php 
                  }
               } ?>
            <br />
            <?php
            }
         else
            {
            # Show the no-preview icon
              ?>
              <img src="<?php echo $baseurl_short ?>gfx/<?php echo get_nopreview_icon($resource["resource_type"],$resource["file_extension"],true)?>" />
              <br />
              <?php
            }
         if ($resource["file_extension"]!="") 
            { ?>           
            <strong>
            <?php 
            echo str_replace_formatted_placeholder("%extension", $resource["file_extension"], $lang["cell-fileoftype"]) . " (" . formatfilesize(@filesize_unlimited(get_resource_path($ref,true,"",false,$resource["file_extension"]))) . ")";
            ?>
            </strong>
            <?php 
            if (checkperm("w") && $resource["has_image"]==1 && file_exists($wmpath))
               { ?> 
               &nbsp;&nbsp;
               <a href="#" onclick="jQuery('#wmpreview').toggle();jQuery('#preview').toggle();if (jQuery(this).text()=='<?php echo $lang['showwatermark']?>'){jQuery(this).text('<?php echo $lang['hidewatermark']?>');} else {jQuery(this).text('<?php echo $lang['showwatermark']?>');}"><?php echo $lang['showwatermark']?></a>
               <?php 
               } ?>
            <br />
            <?php 
            }

        if($top_nav_upload_type == 'local')
            {
            $replace_upload_type = 'plupload';
            }
        else 
            {
            $replace_upload_type=$top_nav_upload_type;
            }

        // Allow to upload only if resource is not a data only type
        if (0 < $ref && !in_array($resource['resource_type'], $data_only_resource_types) && !$resource_file_readonly && !$upload_review_mode)
            {
            ?>
            <a href="<?php echo generateURL($baseurl_short . "pages/upload_" . $replace_upload_type . ".php",$urlparams, array("replace_resource"=>$ref, "resource_type"=>$resource['resource_type'])); ?>" onClick="return CentralSpaceLoad(this,true);"><?php echo LINK_CARET ?><?php echo (($resource["file_extension"]!="")?$lang["replacefile"]:$lang["uploadafile"]) ?></a>
            <?php
            }
         if ($resource["file_extension"]!="") 
            {hook("afterreplacefile");} 
         else 
            {hook("afteruploadfile");}
         if (!$disable_upload_preview && !$resource_file_readonly && !$upload_review_mode) 
            { ?>
            <br />
     <a href="<?php echo generateURL($baseurl_short . "pages/upload_preview.php",$urlparams); ?>" onClick="return CentralSpaceLoad(this,true);"><?php echo LINK_CARET ?><?php echo $lang["uploadpreview"]?></a><?php } ?>
     <?php
     if (!$disable_alternative_files && !checkperm('A') && !$upload_review_mode) { ?><br />
     <a href="<?php echo generateURL($baseurl_short . "pages/alternative_files.php",$urlparams); ?>"  onClick="return CentralSpaceLoad(this,true);"><?php echo LINK_CARET ?><?php echo $lang["managealternativefiles"]?></a><?php } ?>
     <?php if ($allow_metadata_revert){?><br />
     <a href="<?php echo generateURL($baseurl_short . "pages/edit.php",$urlparams,array("exif"=>"true")); ?>" onClick="return confirm('<?php echo $lang["confirm-revertmetadata"]?>');"><?php echo LINK_CARET ?><?php echo $lang["action-revertmetadata"]?></a><?php } ?>
        <?php hook("afterfileoptions"); ?>
     </div>
     <div class="clearerleft"> </div>
  </div>
  <?php }
  hook("beforeimagecorrection");

  if (!checkperm("F*") && !$resource_file_readonly && !$upload_review_mode) { ?>
  <div class="Question" id="question_imagecorrection">
   <label><?php echo $lang["imagecorrection"]?><br/><?php echo $lang["previewthumbonly"]?></label><select class="stdwidth" name="tweak" id="tweak" onChange="<?php echo ($modal?"Modal":"CentralSpace") ?>Post(document.getElementById('mainform'),true);">
   <option value=""><?php echo $lang["select"]?></option>
   <?php if ($resource["has_image"]==1) { ?>
   <?php
# On some PHP installations, the imagerotate() function is wrong and images are turned incorrectly.
# A local configuration setting allows this to be rectified
   if (!$image_rotate_reverse_options)
   {
     ?>
     <option value="rotateclock"><?php echo $lang["rotateclockwise"]?></option>
     <option value="rotateanti"><?php echo $lang["rotateanticlockwise"]?></option>
     <?php
  }
  else
  {
     ?>
     <option value="rotateanti"><?php echo $lang["rotateclockwise"]?></option>
     <option value="rotateclock"><?php echo $lang["rotateanticlockwise"]?></option>
     <?php
  }
  ?>
  <?php if ($tweak_allow_gamma){?>
  <option value="gammaplus"><?php echo $lang["increasegamma"]?></option>
  <option value="gammaminus"><?php echo $lang["decreasegamma"]?></option>
  <?php } ?>
  <option value="restore"><?php echo $lang["recreatepreviews"]?></option>
  <?php } else { ?>
  <option value="restore"><?php echo $lang["retrypreviews"]?></option>
  <?php } ?>
  <?php hook("moretweakingopt"); ?>
</select>
<div class="clearerleft"> </div>
</div>
<?php } ?>


<?php }
else
  { # Upload template: (writes to resource with ID [negative user ref])
   if (!hook("replaceeditheader"))
   {
    # Define the title h1:
    if ($single)
      {
      if (getval("status","")=="2")
        {
        $titleh1 = $lang["newarchiveresource"]; # Add Single Archived Resource
        }
      else
        {
        $titleh1 = $lang["addresource"]; # Add Single Resource
        }
      }
    elseif ($uploader == "" || getval("uploader","")=="plupload") {$titleh1 = $lang["addresourcebatchbrowser"];} # Add Resource Batch - In Browser
    elseif ((getval("local","")!="")||($uploader == "local")) {$titleh1 = $lang["addresourcebatchlocalfolder"];} # Add Resource Batch - Fetch from local upload folder
    else $titleh1 = $lang["addresourcebatchftp"]; # Add Resource Batch - Fetch from FTP server
    
    ?>
    
    <h1><?php echo $titleh1 ?></h1>
    <p><?php echo $lang["intro-batch_edit"] ?></p>
    
    <?php
 }
// Upload template: Show the required fields note at the top of the form.
if(!$is_template && $show_required_field_label)
    {
    ?>
    <p class="greyText noPadding"><sup>*</sup> <?php echo $lang['requiredfield']; ?></p>
    <?php
    }

# Upload template: Show the save / clear buttons at the top too, to avoid unnecessary scrolling.
?>
<div class="QuestionSubmit">
   <?php
   global $clearbutton_on_upload;
   if(($clearbutton_on_upload && $ref<0 && !$multiple) || ($ref>0 && $clearbutton_on_edit)) 
     { ?>
  <input name="resetform" class="resetform" type="submit" value="<?php echo $lang["clearbutton"]?>" />&nbsp;
  <?php
    }

    $save_btn_value = $lang['next'];
    if(0 > $ref && in_array($resource['resource_type'], $data_only_resource_types))
        {
        $save_btn_value = $lang['create'];
        }
?>
<input name="save" class="editsave" type="submit" value="&nbsp;&nbsp;<?php echo $save_btn_value; ?>&nbsp;&nbsp;" /><br />
<div class="clearerleft"> </div>
</div>

<?php } ?>

<?php hook("editbefresmetadata"); ?>
<?php if (!hook("replaceedittype")) { ?>
<?php
if(!$multiple)
    {
    ?>
    <div class="Question <?php if($lockable_fields && in_array("resource_type",$locked_fields)){echo "lockedQuestion ";}if(isset($save_errors) && is_array($save_errors) && array_key_exists('resource_type',$save_errors)) { echo 'FieldSaveError'; } ?>" id="question_resourcetype">
        <label for="resourcetype"><?php echo $lang["resourcetype"] . (($ref < 0 && $resource_type_force_selection) ? " <sup>*</sup>" : "" );
        if ($lockable_fields)
            {
            renderLockButton('resource_type', $locked_fields);
            }?>
       </label>
       <?php if ($check_edit_checksums)
        {?>
        <input id='resource_type_checksum' name='resource_type_checksum' type='hidden' value='<?php echo $resource['resource_type']; ?>'>
        <?php
        }
        ?>

        <select name="resource_type" id="resourcetype" class="stdwidth" 
                onChange="<?php if ($ref>0) { ?>if (confirm('<?php echo $lang["editresourcetypewarning"]; ?>')){<?php } ?><?php echo ($modal?"Modal":"CentralSpace") ?>Post(document.getElementById('mainform'),true);<?php if ($ref>0) { ?>}else {return}<?php } ?>">
        <?php
        $types                = get_resource_types();
        $shown_resource_types = array();
        if($ref < 0 && $resource_type_force_selection && $resource_type=="") // $resource_type is obtained from getval
          {
          echo "<option value='' selected>" . $lang["select"] . "</option>";
          }
          
        for($n = 0; $n < count($types); $n++)
            {
            // skip showing a resource type that we do not to have permission to change to (unless it is currently set to that). Applies to upload only
            if(0 > $ref && (checkperm("XU{$types[$n]['ref']}") || in_array($types[$n]['ref'], $hide_resource_types)))
                {
                continue;
                }

            $shown_resource_types[] = $types[$n]['ref'];
            ?>
            <option value="<?php echo $types[$n]['ref']; ?>"
                <?php
                if($resource['resource_type'] == $types[$n]['ref'])
                    {
                    $selected_type = $types[$n]['ref'];
                    ?>selected<?php
                    }
                    ?>
            ><?php echo htmlspecialchars($types[$n]["name"])?></option>
            <?php
            }

        // make sure the user template resource (edit template) has the correct resource type when they upload so they can see the correct specific fields
        if('' == getval('submitted', ''))
            {
            if(!isset($selected_type))
                {
                $selected_type = $shown_resource_types[0];
                }

            update_resource_type($ref, $selected_type);
            $resource['resource_type'] = $selected_type;
            }
            ?>
        </select>
        <div class="clearerleft"></div>
    </div>
    <?php
    }
else
    {
    # Multiple method of changing resource type.
    ?>
    <h2 <?php echo ($collapsible_sections)?"class=\"CollapsibleSectionHead\"":""?>><?php echo $lang["resourcetype"] ?></h2>
    <div <?php echo ($collapsible_sections)?"class=\"CollapsibleSection\"":""?> id="ResourceTypeSection<?php if ($ref==-1) echo "Upload"; ?>">
      <div class="Question">
        <input name="editresourcetype" id="editresourcetype" type="checkbox" value="yes" onClick="var q=document.getElementById('editresourcetype_question');if (this.checked) {q.style.display='block';alert('<?php echo $lang["editallresourcetypewarning"] ?>');} else {q.style.display='none';}">
        &nbsp;
        <label for="editresourcetype"><?php echo $lang["resourcetype"] ?></label>
      </div>
    <div class="Question" style="display:none;" id="editresourcetype_question">
        <label for="resourcetype"><?php echo $lang["resourcetype"]?></label>
        <select name="resource_type" id="resourcetype" class="stdwidth">
            <?php
            $types = get_resource_types();
            for($n = 0; $n < count($types); $n++)
                {
                if(in_array($types[$n]['ref'], $hide_resource_types))
                    {
                    continue;
                    }
                ?>
                <option value="<?php echo $types[$n]["ref"]?>" <?php if ($resource["resource_type"]==$types[$n]["ref"]) {?>selected<?php } ?>><?php echo htmlspecialchars($types[$n]["name"])?></option>
                <?php
                }
            ?>
        </select>
        <div class="clearerleft"></div>
    </div>
    <?php
    }
} # end hook("replaceedittype")

$lastrt=-1;

if(isset($metadata_template_resource_type) && !$multiple)
    {
    // Show metadata templates here
    $metadatatemplate = getvalescaped(
    	'metadatatemplate',
    	($metadata_template_default_option == 0 ? 0 : $metadata_template_default_option),
    	true
    );

    $templates = get_metadata_templates();

    $first_option_conditions = ($metadata_template_default_option == 0 && $metadatatemplate == 0);
    ?>
    <div class="Question" id="question_metadatatemplate">
        <label for="metadatatemplate"><?php echo $lang['usemetadatatemplate']; ?></label>
        <select name="metadatatemplate" class="stdwidth" onchange="MetadataTemplateOptionChanged(jQuery(this).val());">
            <option value=""<?php echo $first_option_conditions ? ' selected' : ''; ?>><?php echo $first_option_conditions ? $lang['select'] : $lang['undometadatatemplate']; ?></option>
        <?php
        foreach($templates as $template)
            {
            $template_selected = '';

            if(
                ($metadatatemplate == 0 && $metadata_template_default_option == $template['ref'])
                || ($metadatatemplate > 0 && $template['ref'] == $metadatatemplate)
            )
                {
                $template_selected = ' selected';
                }
                ?>
            <option value="<?php echo $template["ref"] ?>" <?php echo $template_selected; ?>><?php echo htmlspecialchars($template["field{$metadata_template_title_field}"]); ?></option>
            <?php   
            }
            ?>
        </select>
        <script>
        function MetadataTemplateOptionChanged(value)
            {
            // Undo template selection <=> clear out the form
            if(value == '')
                {
                jQuery('#mainform').append(
                    jQuery('<input type="hidden">').attr(
                        {
                        name: 'resetform',
                        value: 'true'
                        })
                );
                }

            return CentralSpacePost(document.getElementById('mainform'), true);
            }
        </script>
        <div class="clearerleft"></div>
    </div><!-- end of question_metadatatemplate --> 
    <?php
    }

if($embedded_data_user_select && $ref<0 && !$multiple)
 {?>
<div class="Question" id="question_exif">
 <label for="exif_option"><?php echo $lang["embedded_metadata"]?></label>
 <table id="" cellpadding="3" cellspacing="3" style="display: block;">                    
   <tbody>
     <tr>        
       <td width="10" valign="middle">
         <input type="radio" id="exif_extract" name="exif_option" value="extract" onClick="jQuery('.ExifOptions').hide();" <?php if($metadata_read_default) echo "checked" ?>>
      </td>
      <td align="left" valign="middle">
         <label class="customFieldLabel" for="exif_extract"><?php echo $lang["embedded_metadata_extract_option"] ?></label>
      </td>


      <td width="10" valign="middle">
         <input type="radio" id="no_exif" name="exif_option" value="yes" onClick="jQuery('.ExifOptions').hide();" <?php if(!$metadata_read_default) echo "checked" ?>>
      </td>
      <td align="left" valign="middle">
         <label class="customFieldLabel" for="no_exif"><?php echo $lang["embedded_metadata_donot_extract_option"] ?></label>
      </td>


      <td width="10" valign="middle">
         <input type="radio" id="exif_append" name="exif_option" value="append" onClick="jQuery('.ExifOptions').hide();">
      </td>
      <td align="left" valign="middle">
         <label class="customFieldLabel" for="exif_append"><?php echo $lang["embedded_metadata_append_option"] ?></label>
      </td>


      <td width="10" valign="middle">
         <input type="radio" id="exif_prepend" name="exif_option" value="prepend" onClick="jQuery('.ExifOptions').hide();">
      </td>
      <td align="left" valign="middle">
         <label class="customFieldLabel" for="exif_prepend"><?php echo $lang["embedded_metadata_prepend_option"] ?></label>
      </td>

      <td width="10" valign="middle">
         <input type="radio" id="exif_custom" name="exif_option" value="custom" onClick="jQuery('.ExifOptions').show();">
      </td>
      <td align="left" valign="middle">
         <label class="customFieldLabel" for="exif_custom"><?php echo $lang["embedded_metadata_custom_option"] ?></label>
      </td>

   </tr>
</tbody>
</table>



<div class="clearerleft"> </div>
</div>
<?php   
}

if ($edit_upload_options_at_top || $upload_review_mode){include '../include/edit_upload_options.php';}


$use=$ref;

# Resource aliasing.
# 'Copy from' or 'Metadata template' been supplied? Load data from this resource instead.
$originalref=$use;

if (getval("copyfrom","")!="")
  {
  # Copy from function
  $copyfrom=getvalescaped("copyfrom","");
  $copyfrom_access=get_resource_access($copyfrom);

  # Check access level
  if ($copyfrom_access!=2) # Do not allow confidential resources (or at least, confidential to that user) to be copied from
    {
    $use=$copyfrom;
    $original_fields=get_resource_field_data($ref,$multiple,true,-1,"",$tabs_on_edit);
    $original_nodes = get_resource_nodes($ref);
    }
  }

if(isset($metadata_template_resource_type) && $metadatatemplate != 0)
    {
    $use             = $metadatatemplate;
    $original_fields = get_resource_field_data($ref, $multiple, true, -1, '', $tabs_on_edit);
    $original_nodes  = get_resource_nodes($ref);

    if($ref < 0 || $upload_review_mode)
        {
        copyAllDataToResource($use, $ref);
        }
    }

# Load resource data
$fields=get_resource_field_data($use,$multiple,!hook("customgetresourceperms"),$originalref,"",$tabs_on_edit);
$all_selected_nodes = get_resource_nodes($use);

if ($lockable_fields && count($locked_fields) > 0 && $lastedited > 0)
        {
        // Update $fields and all_selected_nodes with details of the last resource edited for locked fields
        // $fields and $all_selected_nodes are passed by reference and so changed by this
        copy_locked_fields($ref,$fields,$all_selected_nodes,$locked_fields,$lastedited);
        }

# if this is a metadata template, set the metadata template title field at the top
if (isset($metadata_template_resource_type)&&(isset($metadata_template_title_field)) && $resource["resource_type"]==$metadata_template_resource_type){
    # recreate fields array, first with metadata template field
  $x=0;
  for ($n=0;$n<count($fields);$n++){
    if ($fields[$n]["resource_type"]==$metadata_template_resource_type){
      $newfields[$x]=$fields[$n];
      $x++;
   }
}
    # then add the others
for ($n=0;$n<count($fields);$n++){
 if ($fields[$n]["resource_type"]!=$metadata_template_resource_type){
   $newfields[$x]=$fields[$n];
   $x++;
}
}
$fields=$newfields;
}

$required_fields_exempt=array(); # new array to contain required fields that have not met the display condition

# Work out if any fields are displayed, and if so, enable copy from feature (+others)
$display_any_fields=false;
$fieldcount=0;
$tabname="";
$tabcount=0;
for ($n=0;$n<count($fields);$n++)
  {
   if (is_field_displayed($fields[$n]))
     {
     $display_any_fields=true;
     break;
    }
}
 
# "copy data from" feature
if ($display_any_fields && $enable_copy_data_from && !$upload_review_mode)
    { ?>
 <div class="Question" id="question_copyfrom">
    <label for="copyfrom"><?php echo $lang["batchcopyfrom"]?></label>
    <input class="stdwidth" type="text" name="copyfrom" id="copyfrom" value="" style="width:80px;">
    <input type="submit" id="copyfromsubmit" name="copyfromsubmit" value="<?php echo $lang["copy"]?>" onClick="return CentralSpacePost(document.getElementById('mainform'),true);">
    <input type="submit" name="save" value="<?php echo $lang['save']; ?>">
    <div class="clearerleft"> </div>
 </div><!-- end of question_copyfrom -->
 <?php
}
?>
</div>
<?php hook('editbeforesectionhead');

global $collapsible_sections;
if($collapsible_sections)
{
  ?>
  <div id="CollapsibleSections">
     <?php
  }


 if ($display_any_fields)
 {
 ?>

<?php if (!$upload_review_mode) { ?>
<br /><br /><?php hook('addcollapsiblesection'); ?><h2  <?php if($collapsible_sections){echo'class="CollapsibleSectionHead"';}?> id="ResourceMetadataSectionHead"><?php echo $lang["resourcemetadata"]?></h2><?php
 } 

?><div <?php if($collapsible_sections){echo'class="CollapsibleSection"';}?> id="ResourceMetadataSection<?php if ($ref<0) echo "Upload"; ?>"><?php
}

if($tabs_on_edit)
{
    #  -----------------------------  Draw tabs ---------------------------
  $tabname="";
  $tabcount=0;
  if (count($fields)>0 && $fields[0]["tab_name"]!="")
  { 
    ?>

    <?php
    $extra="";
    $tabname="";
    $tabcount=0;
    $tabtophtml="";
    for ($n=0;$n<count($fields);$n++)
    {   
      $value=$fields[$n]["value"];

            # draw new tab?
      if ($tabname!=$fields[$n]["tab_name"] && is_field_displayed($fields[$n]))
      {
        if($tabcount==0){$tabtophtml.="<div class=\"BasicsBox\" id=\"BasicsBoxTabs\"><div class=\"TabBar\">";}
        $tabtophtml.="<div id=\"tabswitch" . $tabcount . "\" class=\"Tab";
        if($tabcount==0){$tabtophtml.=" TabSelected ";}
        $tabtophtml.="\"><a href=\"#\" onclick=\"SelectTab(" . $tabcount . ");return false;\">" .  i18n_get_translated($fields[$n]["tab_name"]) . "</a></div>";
        $tabcount++;
        $tabname=$fields[$n]["tab_name"];
     }
  }

  if ($tabcount>1)
  {
   echo $tabtophtml;
   echo "</div><!-- end of TabBar -->";
}

if ($tabcount>1)
   {?>
<script type="text/javascript">
function SelectTab(tab)
{
                // Deselect all tabs
                <?php for ($n=0;$n<$tabcount;$n++) { ?>
                 document.getElementById("tab<?php echo $n?>").style.display="none";
                 document.getElementById("tabswitch<?php echo $n?>").className="Tab";
                 <?php } ?>
                 document.getElementById("tab" + tab).style.display="block";
                 document.getElementById("tabswitch" + tab).className="Tab TabSelected";
              }
              </script>
              <?php
           }
        }


        if ($tabcount>1)
        {
          ?>
          <div id="tab0" class="TabbedPanel<?php if ($tabcount>0) { ?> StyledTabbedPanel<?php } ?>">
             <div class="clearerleft"> </div>
             <div class="TabPanelInner">

                <?php
             }
          }


          $tabname="";
          $tabcount=0;    
          for ($n=0;$n<count($fields);$n++)
          {
    # Should this field be displayed?
           if (is_field_displayed($fields[$n]))
           {
            if(in_array($fields[$n]['resource_type'], $hide_resource_types)) { continue; }
            $newtab=false;  
            if($n==0 && $tabs_on_edit){$newtab=true;}
        # draw new tab panel?
            if ($tabs_on_edit && ($tabname!=$fields[$n]["tab_name"]) && ($fieldcount>0))
            {
               $tabcount++;
            # Also display the custom formatted data $extra at the bottom of this tab panel.
               ?><div class="clearerleft"> </div><?php if(isset($extra)){echo $extra;} ?></div><!-- end of TabPanelInner --></div><!-- end of TabbedPanel --><div class="TabbedPanel StyledTabbedPanel" style="display:none;" id="tab<?php echo $tabcount?>"><div class="TabPanelInner"><?php  
               $extra="";
               $newtab=true;
            }
            $tabname=$fields[$n]["tab_name"];
            $fieldcount++;
            
			node_field_options_override($fields[$n]);
            display_field($n, $fields[$n], $newtab, $modal);
         }
      }



      if ($tabs_on_edit && $tabcount>0)
      {
        ?>
        <div class="clearerleft"> </div>
     </div><!-- end of TabPanelInner -->
  </div><!-- end of TabbedPanel -->
</div><!-- end of Tabs BasicsBox -->
<?php
}


# Add required_fields_exempt so it is submitted with POST
echo " <input type=hidden name=\"exemptfields\" id=\"exemptfields\" value=\"" . implode(",",$required_fields_exempt) . "\">";   

# Work out the correct archive status.
if ($ref<0) # Upload template.
   {
   global $override_status_default;
   $modified_defaultstatus = hook("modifydefaultstatusmode");
   if ($setarchivestate==2)
      {
      if (checkperm("e2")) {$setarchivestate = 2;} # Set status to Archived - if the user has the required permission.
      elseif ($modified_defaultstatus) {$setarchivestate = $modified_defaultstatus;}  # Set the modified default status - if set.
      elseif (checkperm("e" . $resource["archive"])) {$setarchivestate = $resource["archive"];} # Else, set status to the status stored in the user template - if the user has the required permission.
      elseif (checkperm("c")) {$setarchivestate = 0;} # Else, set status to Active - if the user has the required permission.
      elseif (checkperm("d")) {$setarchivestate = -2;} # Else, set status to Pending Submission.
      }
   else
      {
      if ($modified_defaultstatus) {$setarchivestate = $modified_defaultstatus;}  # Set the modified default status - if set.
      elseif ($override_status_default!==false) {$setarchivestate = $override_status_default;}
      elseif ($resource["archive"]!=2 && checkperm("e" . $resource["archive"])) {$setarchivestate = $resource["archive"];} # Set status to the status stored in the user template - if the status is not Archived and if the user has the required permission.
      elseif (checkperm("c")) {$setarchivestate = 0;} # Else, set status to Active - if the user has the required permission.
      elseif (checkperm("d") && !checkperm('e-2') && checkperm('e-1')) {$setarchivestate = -1;} # Else, set status to Pending Review if the user has only edit access to Pending review
      elseif (checkperm("d")) {$setarchivestate = -2;} # Else, set status to Pending Submission.   
      }
   if ($show_status_and_access_on_upload==false)
      {
      # Hide the dropdown, and set the default status.
      ?>
      <input type=hidden name="status" id="status" value="<?php echo htmlspecialchars($setarchivestate)?>"><?php
      }
   }
else # Edit Resource(s).
   {
   $setarchivestate = $resource["archive"];
   }

# Status / Access / Related Resources

if (eval($show_status_and_access_on_upload_perm) && !hook("editstatushide")) # Only display Status / Access / Related Resources if permissions match.
{
  if(!hook("replacestatusandrelationshipsheader"))
  {
    if ($ref>0 || $show_status_and_access_on_upload===true || $show_access_on_upload===true)
    {
            if ($enable_related_resources && ($multiple || $ref>0)) # Showing relationships
            {
              ?></div><!-- end of ResourceMetadataSection --><h2 <?php echo ($collapsible_sections)?"class=\"CollapsibleSectionHead\"":""?> id="StatusRelationshipsSectionHead"><?php echo $lang["statusandrelationships"]?></h2><div <?php echo ($collapsible_sections)?"class=\"CollapsibleSection\"":""?> id="StatusRelationshipsSection<?php if ($ref==-1) echo "Upload"; ?>"><?php
           }
           else
           {
                ?></div><!-- end of ResourceMetadataSection --><h2 <?php echo ($collapsible_sections)?"class=\"CollapsibleSectionHead\"":""?>><?php echo $lang["status"]?></h2><div <?php echo ($collapsible_sections)?"class=\"CollapsibleSection\"":""?> id="StatusSection<?php if ($ref==-1) echo "Upload"; ?>"><?php # Not showing relationships
             }
          }

       } /* end hook replacestatusandrelationshipsheader */

       hook("statreladdtopfields");

# Status
if ($ref>0 || $show_status_and_access_on_upload===true)
   {
   if(!hook("replacestatusselector"))
      {
      if ($multiple)
         { ?>
         <div class="Question" id="editmultiple_status"><input name="editthis_status" id="editthis_status" value="yes" type="checkbox" onClick="var q=document.getElementById('question_status');if (q.style.display!='block') {q.style.display='block';} else {q.style.display='none';}">&nbsp;<label id="editthis_status_label" for="editthis<?php echo $n?>"><?php echo $lang["status"]?></label></div>
         <?php
         } ?>
      <div class="Question <?php if($lockable_fields && in_array("archive",$locked_fields)){echo "lockedQuestion ";} if(isset($save_errors) && is_array($save_errors) && array_key_exists('status',$save_errors)) { echo 'FieldSaveError'; } ?>" id="question_status" <?php if ($multiple) {?>style="display:none;"<?php } ?>>
         <label for="status">
         <?php echo $lang["status"];
         if ($lockable_fields)
            {
            renderLockButton('archive', $locked_fields);
            }?>
          </label><?php

         # Autosave display
         if ($edit_autosave || $ctrls_to_save)
            { ?>
            <div class="AutoSaveStatus" id="AutoSaveStatusStatus" style="display:none;"></div>
            <?php
            } 
		 if(!$multiple && getval("copyfrom","")=="" && $check_edit_checksums)
			{
			echo "<input id='status_checksum' name='status_checksum' type='hidden' value='" . $setarchivestate . "'>";
			}?>
         <select class="stdwidth" name="status" id="status" <?php if ($edit_autosave) {?>onChange="AutoSave('Status');"<?php } ?>><?php
         for ($n=-2;$n<=3;$n++)
            {
            if (checkperm("e" . $n)) { ?><option value="<?php echo $n?>" <?php if ($setarchivestate==$n) { ?>selected<?php } ?>><?php echo $lang["status" . $n]?></option><?php }
            }
         foreach ($additional_archive_states as $additional_archive_state)
            {
            if (checkperm("e" . $additional_archive_state)) { ?><option value="<?php echo $additional_archive_state?>" <?php if ($setarchivestate==$additional_archive_state) { ?>selected<?php } ?>><?php echo isset($lang["status" . $additional_archive_state])?$lang["status" . $additional_archive_state]:$additional_archive_state ?></option><?php }
            }?>
         </select>
         <div class="clearerleft"> </div>
      </div><?php
      } /* end hook replacestatusselector */
   }

    # Access
hook("beforeaccessselector");
if (!hook("replaceaccessselector"))
{
 if($ref<0 && $override_access_default!==false)
 {
   $resource["access"]=$override_access_default;
}

if ($ref<0 && (($show_status_and_access_on_upload== false && $show_access_on_upload == false) || ($show_access_on_upload == false || ($show_access_on_upload == true && !eval($show_access_on_upload_perm)))))            # Upload template and the status and access fields are configured to be hidden on uploads.
   {?>
   <input type=hidden name="access" value="<?php echo htmlspecialchars($resource["access"])?>"><?php
}
else
{
   if ($multiple) { ?><div class="Question"><input name="editthis_access" id="editthis_access" value="yes" type="checkbox" onClick="var q=document.getElementById('question_access');if (q.style.display!='block') {q.style.display='block';} else {q.style.display='none';}">&nbsp;<label for="editthis<?php echo $n?>"><?php echo $lang["access"]?></label></div><?php } ?>

   <div class="Question <?php if($lockable_fields && in_array("access",$locked_fields)){echo "lockedQuestion ";} if(isset($save_errors) && is_array($save_errors) && array_key_exists('access',$save_errors)) { echo 'FieldSaveError'; } ?>" id="question_access" <?php if ($multiple) {?>style="display:none;"<?php } ?>>
      <label for="access">
      <?php echo $lang["access"];
      if ($lockable_fields)
            {
            renderLockButton('access', $locked_fields);
            }
      ?></label><?php

            # Autosave display
      if ($edit_autosave || $ctrls_to_save) { ?><div class="AutoSaveStatus" id="AutoSaveStatusAccess" style="display:none;"></div><?php }

      $ea0=!checkperm('ea0');
      $ea1=!checkperm('ea1');
      $ea2=checkperm("v")?(!checkperm('ea2')?true:false):false;
      $ea3=$custom_access?!checkperm('ea3'):false;
      if(($ea0 && $resource["access"]==0) || ($ea1 && $resource["access"]==1) || ($ea2 && $resource["access"]==2) || ($ea3 && $resource["access"]==3))
      {
        if(!$multiple && getval("copyfrom","")=="" && $check_edit_checksums)
			{
			echo "<input id='access_checksum' name='access_checksum' type='hidden' value='" . $resource["access"] . "'>";
			}?>
        <select class="stdwidth" name="access" id="access" onChange="var c=document.getElementById('custom_access');<?php if ($resource["access"]==3) { ?>if (!confirm('<?php echo $lang["confirm_remove_custom_usergroup_access"] ?>')) {this.value=<?php echo $resource["access"] ?>;return false;}<?php } ?>if (this.value==3) {c.style.display='block';} else {c.style.display='none';}<?php if ($edit_autosave) {?>AutoSave('Access');<?php } ?>">
          <?php
                    if($ea0)    //0 - open
                    {$n=0;?><option value="<?php echo $n?>" <?php if ($resource["access"]==$n) { ?>selected<?php } ?>><?php echo $lang["access" . $n]?></option><?php }
                    if($ea1)    //1 - restricted
                    {$n=1;?><option value="<?php echo $n?>" <?php if ($resource["access"]==$n) { ?>selected<?php } ?>><?php echo $lang["access" . $n]?></option><?php }
                    if($ea2)    //2 - confidential
                    {$n=2;?><option value="<?php echo $n?>" <?php if ($resource["access"]==$n) { ?>selected<?php } ?>><?php echo $lang["access" . $n]?></option><?php }
                    if($ea3)    //3 - custom
                    {$n=3;?><option value="<?php echo $n?>" <?php if ($resource["access"]==$n) { ?>selected<?php } ?>><?php echo $lang["access" . $n]?></option><?php }
                    ?>
                 </select>
                 <?php
              }
              else
              {
                 ?>
                 <label class="stdwidth" id="access"><?php echo $lang["access" .$resource["access"]];?></label>
                 <?php
              }
              ?>
              <div class="clearerleft"> </div>
              <?php
              if($ea3 || $resource["access"]==3)
              {
                 ?>
                 <table id="custom_access" cellpadding=3 cellspacing=3 style="padding-left:150px;<?php if ($resource["access"]!=3) { ?>display:none;<?php } ?>"><?php
                 global $default_customaccess;
                 $customaccesssource = ($lockable_fields && in_array("access",$locked_fields) && $lastedited > 0) ? $lastedited : $ref;
                 $groups=get_resource_custom_access($customaccesssource);
                 for ($n=0;$n<count($groups);$n++)
                 {
                   $access=$default_customaccess;
                   $editable= (!$ea3)?false:true;
                   if ($groups[$n]["access"]!="") {$access=$groups[$n]["access"];}
                   $perms=explode(",",$groups[$n]["permissions"]);
                   if (in_array("v",$perms)) {$access=0;$editable=false;} ?>
                   <tr>
                      <td valign=middle nowrap><?php echo htmlspecialchars($groups[$n]["name"])?>&nbsp;&nbsp;</td>

                      <td width=10 valign=middle><input type=radio name="custom_<?php echo $groups[$n]["ref"]?>" value="0" <?php if (!$editable) { ?>disabled<?php } ?> <?php if ($access==0) { ?>checked <?php }
                      if ($edit_autosave) {?> onChange="AutoSave('Access');"<?php } ?>></td>

                      <td align=left valign=middle><?php echo $lang["access0"]?></td>

                      <td width=10 valign=middle><input type=radio name="custom_<?php echo $groups[$n]["ref"]?>" value="1" <?php if (!$editable) { ?>disabled<?php } ?> <?php if ($access==1) { ?>checked <?php }
                      if ($edit_autosave) {?> onChange="AutoSave('Access');"<?php } ?>></td>

                      <td align=left valign=middle><?php echo $lang["access1"]?></td><?php

                      if (checkperm("v"))
                        { ?>
                     <td width=10 valign=middle><input type=radio name="custom_<?php echo $groups[$n]["ref"]?>" value="2" <?php if (!$editable) { ?>disabled<?php } ?> <?php if ($access==2) { ?>checked <?php }
                     if ($edit_autosave) {?> onChange="AutoSave('Access');"<?php } ?>></td>

                     <td align=left valign=middle><?php echo $lang["access2"]?></td><?php
                  } ?>
                  </tr><?php
               } ?>
            </table>
            <div class="clearerleft"> </div>
            <?php
         }
         ?>
         </div><?php
      }
   } /* end hook replaceaccessselector */

    # Related Resources
    if ($enable_related_resources && ($multiple || $ref>0)) # Not when uploading
    {
       if ($multiple) { ?><div class="Question"><input name="editthis_related" id="editthis_related" value="yes" type="checkbox" onClick="var q=document.getElementById('question_related');if (q.style.display!='block') {q.style.display='block';} else {q.style.display='none';}">&nbsp;<label for="editthis_related"><?php echo $lang["relatedresources"]?></label></div><?php } ?>

       <div class="Question<?php if($lockable_fields && in_array("related_resources",$locked_fields)){echo " lockedQuestion ";} ?>" id="question_related" <?php if ($multiple) {?>style="display:none;"<?php } ?>>
          <label for="related"><?php echo $lang["relatedresources"];
           if ($lockable_fields)
            {
            renderLockButton('related_resources', $locked_fields);
            }?>
           </label><?php

        # Autosave display
          if ($edit_autosave  || $ctrls_to_save) { ?><div class="AutoSaveStatus" id="AutoSaveStatusRelated" style="display:none;"></div><?php } ?>

          <textarea class="stdwidth" rows=3 cols=50 name="related" id="related"<?php
          if ($edit_autosave) {?>onChange="AutoSave('Related');"<?php } ?>><?php
          
          $relatedref = ($lockable_fields && in_array("related_resources",$locked_fields) && $lastedited > 0) ? $lastedited : $ref;
          $related = get_related_resources($relatedref);

          echo ((getval("resetform","")!="")?"":join(", ", $related))?></textarea>

          <div class="clearerleft"> </div>
          </div><?php
       } 
    }
    
    // Edit the 'contributed by' value of the resource table
    if($ref > 0 && $edit_contributed_by)
      {
      $sharing_userlists = false;
      $single_user_select_field_id = "created_by";
	  $autocomplete_user_scope = "created_by";
      $single_user_select_field_value = $resource["created_by"];
      if ($edit_autosave) {$single_user_select_field_onchange = "AutoSave('created_by');"; }
      if ($multiple) { ?><div><input name="editthis_created_by" id="editthis_created_by" value="yes" type="checkbox" onClick="var q=document.getElementById('question_created_by');if (q.style.display!='block') {q.style.display='block';} else {q.style.display='none';}">&nbsp;<label for="editthis_created_by>"><?php echo $lang["contributedby"] ?></label></div><?php } ?>
      <div class="Question" id="question_created_by" <?php if ($multiple) {?>style="display:none;"<?php } ?>>
        <label><?php echo $lang["contributedby"] ?></label><?php include __DIR__ . "/../include/user_select.php"; ?>
        <div class="clearerleft"> </div>
      </div>
      <?php
      }
    


    if($ref > 0 && !$upload_review_mode && $delete_resource_custom_access)
    {
       $query = sprintf('
          SELECT rca.user AS user_ref,
          IF(u.fullname IS NOT NULL, u.fullname, u.username) AS user
          FROM resource_custom_access AS rca
          INNER JOIN user AS u ON rca.user = u.ref
          WHERE resource = "%s";
          ',
          $ref
          );
       $rca_users = sql_query($query);
       
       $group_query = sprintf('
          SELECT rca.usergroup AS usergroup_ref,
          u.name AS name
          FROM resource_custom_access AS rca
          INNER JOIN usergroup AS u ON rca.usergroup = u.ref
          WHERE resource = "%s";
          ',
          $ref
          );
       $rca_usergroups = sql_query($group_query);

       ?>
    </div> <!-- end of previous collapsible section -->
    <h2 id="resource_custom_access" <?php echo ($collapsible_sections) ? ' class="CollapsibleSectionHead"' : ''; ?>><?php echo $lang["resource_custom_access"]?></h2>
    <div  id="ResourceCustomAccessSection" <?php echo ($collapsible_sections) ? 'class="CollapsibleSection"' : ''; ?>>
       <script type="text/javascript">
       function removeCustomAccess(ref,type) {
        jQuery.ajax({
          type: 'POST',
          url: '<?php echo $baseurl_short; ?>pages/ajax/remove_custom_access.php',
          data: {
            ajax: 'true',
            resource: <?php echo $ref; ?>,
            ref: ref,
            type: type
         },
         success: function() {
			 if(type=='user')
				{
				jQuery('#rca_user_' + ref).remove();
				}
			else if (type=='usergroup')
				{
				jQuery('#rca_usergroup_' + ref).remove();	
				}
         }
      });
     }
     </script>
     <div class="Question" id="question_resource_custom_access">
      <label for="res_custom_access"><?php echo $lang['remove_custom_access_users_groups']?></label>
      <!-- table here -->
      <table id="res_custom_access" cellpadding="3" cellspacing="3">
        <tbody>
          <?php
          foreach ($rca_users as $rca_user_info)
			{
             ?>
             <tr id="rca_user_<?php echo $rca_user_info['user_ref'] ?>">
               <td valign="middle" nowrap=""><?php echo $rca_user_info['user']; ?></td>
               <td valign="middle" nowrap="">&nbsp;</td>
               <td width="10" valign="middle">
                 <input type="hidden" name="remove_access_user_ref" value="<?php echo $rca_user_info['user_ref'] ?>">
                 <input type="submit" name="remove_access" value="Remove access" onClick="removeCustomAccess(<?php echo $rca_user_info['user_ref']; ?>,'user'); return false;">
              </td>
           </tr>
           <?php
			}
        foreach ($rca_usergroups as $rca_usergroup_info)
			{
             ?>
             <tr id="rca_group_<?php echo $rca_usergroup_info['usergroup_ref'] ?>">
               <td valign="middle" nowrap=""><?php echo $rca_usergroup_info['name']." (".$lang['group'].")"?></td>
               <td valign="middle" nowrap="">&nbsp;</td>
               <td width="10" valign="middle">
                 <input type="hidden" name="remove_access_usergroup_ref" value="<?php echo $rca_usergroup_info['usergroup_ref'] ?>">
                 <input type="submit" name="remove_access_group" value="Remove access" onClick="removeCustomAccess(<?php echo $rca_usergroup_info['usergroup_ref']; ?>,'usergroup'); return false;">
              </td>
           </tr>
           <?php
        }

                    // Add a default message if no users are attached
        if(count($rca_users) == 0 && count($rca_usergroups) == 0)
        {
          ?>
          <tr>
            <td><?php echo $lang['remove_custom_access_no_users_found']; ?></td>
         </tr>
         <?php
      }
      ?>
   </tbody>
</table>
<!-- end of table -->
<div class="clearerleft"> </div>
</div>
<?php
}

if ($multiple && !$disable_geocoding)
{
    # Multiple method of changing location.
 ?>
</div><h2 <?php echo ($collapsible_sections)?" class=\"CollapsibleSectionHead\"":""?> id="location_title"><?php echo $lang["location-title"] ?></h2><div <?php echo ($collapsible_sections)?"class=\"CollapsibleSection\"":""?> id="LocationSection<?php if ($ref=="new") echo "Upload"; ?>">
<div class="Question"><input name="editlocation" id="editlocation" type="checkbox" value="yes" onClick="var q=document.getElementById('editlocation_question');if (this.checked) {q.style.display='block';} else {q.style.display='none';}">&nbsp;<label for="editlocation"><?php echo $lang["location"] ?></label></div>
<div class="Question" style="display:none;" id="editlocation_question">
  <label for="location"><?php echo $lang["latlong"]?></label>
  <input type="text" name="location" id="location" class="stdwidth">
  <div class="clearerleft"> </div>
</div>
<div class="Question"><input name="editmapzoom" id="editmapzoom" type="checkbox" value="yes" onClick="var q=document.getElementById('editmapzoom_question');if (this.checked) {q.style.display='block';} else {q.style.display='none';}">&nbsp;<label for="editmapzoom"><?php echo $lang["mapzoom"] ?></label></div>
<div class="Question" style="display:none;" id="editmapzoom_question">
  <label for="mapzoom"><?php echo $lang["mapzoom"]?></label>
  <select name="mapzoom" id="mapzoom">
    <option value=""><?php echo $lang["select"]?></option>
    <option value="2">2</option>
    <option value="3">3</option>
    <option value="4">4</option>
    <option value="5">5</option>
    <option value="6">6</option>
    <option value="7">7</option>
    <option value="8">8</option>
    <option value="9">9</option>
    <option value="10">10</option>
    <option value="11">11</option>
    <option value="12">12</option>
    <option value="13">13</option>
    <option value="14">14</option>
    <option value="15">15</option>
    <option value="16">16</option>
    <option value="17">17</option>
    <option value="18">18</option>
 </select>
</div>
<div class="clearerleft"> </div>


<?php
hook("locationextras");
}

if($disablenavlinks)
        {
        ?>
        <input type=hidden name="disablenav" value="true">
        <?php
        }
        
if (!$edit_upload_options_at_top){include '../include/edit_upload_options.php';}

if(!hook('replacesubmitbuttons'))
    {
    SaveAndClearButtons("NoPaddingSaveClear");
    }
?>


</div>

<?php 
# Duplicate navigation
if (!$multiple && !$modal && $ref>0 && !hook("dontshoweditnav")) {EditNav();}

if(!$is_template && $show_required_field_label)
    {
    ?>
    <p><sup>*</sup> <?php echo $lang['requiredfield']; ?></p>
    <?php
    } 

if($collapsible_sections)
{
  ?>
</div><!-- end of collapsible section -->
<?php
}
if($multiple){echo "</div>";} ?>
</form>

<script>
// Helper script to assist with AJAX - when 'save' and 'reset' buttons are pressed, add a hidden value so the 'save'/'resetform' values are passed forward just as if those buttons had been clicked. jQuery doesn't do this for us.
 jQuery(".editsave").click(function(){
                jQuery("#mainform").append(
                    jQuery("<input type='hidden'>").attr( { 
                        name: "save", 
                        value: "true" }));}
                );
  jQuery(".resetform").click(function(){
                jQuery("#mainform").append(
                    jQuery("<input type='hidden'>").attr( { 
                        name: "resetform", 
                        value: "true" }));}
                );
   jQuery("#copyfromsubmit").click(function(){
                jQuery("#mainform").append(
                    jQuery("<input type='hidden'>").attr( { 
                        name: "copyfromsubmit", 
                        value: "true" }));}
                );
    jQuery(".save_auto_next").click(function(){
                jQuery("#mainform").append(
                    jQuery("<input type='hidden'>").attr( { 
                        name: "save_auto_next", 
                        value: "true" }));}
                );
</script>

<?php
if (isset($show_error) && isset($save_errors) && is_array($save_errors) && !hook('replacesaveerror'))
  {
  ?>
  <script>
  // Find the first field that triggered the error:
  var error_fields;

  error_fields = document.getElementsByClassName('FieldSaveError');
  window.location.hash = error_fields[0].id;

  styledalert('<?php echo $lang["error"]?>','<?php echo implode("<br />",$save_errors); ?>',450);
  </script>
  <?php 
  }

hook("autolivejs");

include "../include/footer.php";
