<?php
include dirname(__FILE__) . "/../../include/db.php";
include_once dirname(__FILE__) . "/../../include/general.php";
include dirname(__FILE__) . "/../../include/resource_functions.php";
include dirname(__FILE__) . "/../../include/image_processing.php";

$sapi_type = php_sapi_name();
if (substr($sapi_type, 0, 3) != 'cli')
    {
    exit("Command line execution only.");
    }

if(isset($staticsync_userref))
    {
    # If a user is specified, log them in.
    $userref=$staticsync_userref;
    $userdata=get_user($userref);
    setup_user($userdata);
    }

ob_end_clean();
set_time_limit(60*60*40);

if ($argc == 2)
    {
    if ( in_array($argv[1], array('--help', '-help', '-h', '-?')) )
        {
        echo "To clear the lock after a failed run, ";
        echo "pass in '--clearlock', '-clearlock', '-c' or '--c'." . PHP_EOL;
        exit("Bye!");
        }
    else if ( in_array($argv[1], array('--clearlock', '-clearlock', '-c', '--c')) )
        {
        if ( is_process_lock("staticsync") )
            {
            clear_process_lock("staticsync");
            }
        }
    else
        {
        exit("Unknown argv: " . $argv[1]);
        }
    } 

# Check for a process lock
if (is_process_lock("staticsync")) 
    {
    echo 'Process lock is in place. Deferring.' . PHP_EOL;
    echo 'To clear the lock after a failed run use --clearlock flag.' . PHP_EOL;
    exit();
    }
set_process_lock("staticsync");

// Strip trailing slash if it has been left in
$syncdir=rtrim($syncdir,"/");

echo "Preloading data... ";

// Set options that don't make sense here
$merge_filename_with_title=false;

$count = 0;
$done=array();
$syncedresources = sql_query("SELECT ref, file_path, file_modified, archive FROM resource WHERE LENGTH(file_path)>0 AND file_path LIKE '%/%'");
foreach($syncedresources as $syncedresource)
    {
    $done[$syncedresource["file_path"]]["ref"]=$syncedresource["ref"];
    $done[$syncedresource["file_path"]]["modified"]=$syncedresource["file_modified"];
    $done[$syncedresource["file_path"]]["archive"]=$syncedresource["archive"];
    }
    
// Set up an array to monitor processing of new alternative files
$alternativefiles=array();

// Add all the synced alternative files to the list of completed
if(isset($staticsync_alternative_file_text) && !$staticsync_ingest)
    {
    // Add any staticsynced alternative files to the array so we don't process them unnecessarily
    $syncedalternatives = sql_query("SELECT ref, file_name, resource, creation_date FROM resource_alt_files WHERE file_name like '%" . escape_check($syncdir) . "%'");
    foreach($syncedalternatives as $syncedalternative)
        {
        $shortpath=str_replace($syncdir . '/', '', $syncedalternative["file_name"]);      
        $done[$shortpath]["ref"]=$syncedalternative["resource"];
        $done[$shortpath]["modified"]=$syncedalternative["creation_date"];
        $done[$shortpath]["alternative"]=$syncedalternative["ref"];
        }
    }
    
    

$lastsync = sql_value("SELECT value FROM sysvars WHERE name='lastsync'","");
$lastsync = (strlen($lastsync) > 0) ? strtotime($lastsync) : '';

echo "done." . PHP_EOL;
echo "Looking for changes..." . PHP_EOL;

# Pre-load the category tree, if configured.
if (isset($staticsync_mapped_category_tree))
    {
    $treefield=get_resource_type_field($staticsync_mapped_category_tree);
    migrate_resource_type_field_check($treefield);
    $tree = get_nodes($staticsync_mapped_category_tree,'',TRUE);
    }

function touch_category_tree_level($path_parts)
    {
    # For each level of the mapped category tree field, ensure that the matching path_parts path exists
    global $staticsync_mapped_category_tree, $tree;

    $parent_search = '';
    $nodename      = '';
	$order_by =10;
    $treenodes = array();
    for ($n=0;$n<count($path_parts);$n++)
        {
        $nodename = $path_parts[$n];
        
        echo " - Looking for folder '" . $nodename . "' @ level " . $n  . " in linked metadata field... ";
        # Look for this node in the tree.       
        $found = false;
        foreach($tree as $treenode)
            {
			if($treenode["parent"]==$parent_search)
                {
        		if ($treenode["name"]==$nodename)
					{
					# A match!
					echo "FOUND" . PHP_EOL;
					$found = true;
                    $treenodes[]=$treenode["ref"];
					$parent_search = $treenode["ref"]; # Search for this as the parent node on the pass for the next level.
					}
				else
					{
					if($order_by<=$treenode["order_by"])
						{$order_by=$order_by+10;}
					}
                }			
            }
        if (!$found)
            {
            echo "NOT FOUND. Updating tree field" .PHP_EOL;
            # Add this node
            $newnode=set_node(NULL, $staticsync_mapped_category_tree, $nodename, $parent_search, $order_by);
       	    $tree[]=array("ref"=>$newnode,"parent"=>$parent_search,"name"=>$nodename,"order_by"=>$order_by);
            $parent_search = $newnode; # Search for this as the parent node on the pass for the next level.
            $treenodes[]=$newnode;
            }
        }
    // Return the matching path nodes
    return $treenodes;
    }

function ProcessFolder($folder)
    {
    global $lang, $syncdir, $nogo, $staticsync_max_files, $count, $done, $lastsync, $ffmpeg_preview_extension, 
           $staticsync_autotheme, $staticsync_folder_structure, $staticsync_extension_mapping_default, 
           $staticsync_extension_mapping, $staticsync_mapped_category_tree, $staticsync_title_includes_path, 
           $staticsync_ingest, $staticsync_mapfolders, $staticsync_alternatives_suffix, $theme_category_levels,
           $staticsync_defaultstate, $additional_archive_states, $staticsync_extension_mapping_append_values,
           $staticsync_deleted_state, $staticsync_alternative_file_text, $staticsync_filepath_to_field, 
           $resource_deletion_state, $alternativefiles, $staticsync_revive_state, $enable_thumbnail_creation_on_upload,
           $FIXED_LIST_FIELD_TYPES, $staticsync_extension_mapping_append_values_fields, $view_title_field, $filename_field,
           $staticsync_whitelist_folders;
    
    $collection = 0;
    $treeprocessed=false;
    
    echo "Processing Folder: " . $folder . PHP_EOL;
    
    # List all files in this folder.
    $dh = opendir($folder);
    while (($file = readdir($dh)) !== false)
        {
        if($file == '.' || $file == '..')
            {
            continue;
            }

        $filetype        = filetype($folder . '/' . $file);
        $fullpath        = $folder . '/' . $file;
        $shortpath       = str_replace($syncdir . '/', '', $fullpath);
        $shortpath_parts = explode('/', $shortpath);
        
        if(isset($staticsync_alternative_file_text) && strpos($file,$staticsync_alternative_file_text)!==false)
            {
            // Set a flag so we can process this later in case we don't processs this along with a primary resource file (it may be a new alternative file for an existing resource)
            $alternativefiles[]=$syncdir . '/' . $shortpath;
            continue;
            }
            
        # Work out extension
        $extension = explode(".", $file);
        if(count($extension)>1)
            {
            $extension = trim(strtolower($extension[count($extension)-1]));
            }
        else
            {
            //No extension
            $extension="";
            }
       
        
        if ($staticsync_mapped_category_tree && !$treeprocessed)
            {
            $path_parts = explode("/", $shortpath);
            array_pop($path_parts);
            $treenodes=touch_category_tree_level($path_parts);
            $treeprocessed=true;
            }

        # -----FOLDERS-------------
        if(
            ($filetype == 'dir' || $filetype == 'link')
            && count($staticsync_whitelist_folders) > 0
            && !isPathWhitelisted($shortpath, $staticsync_whitelist_folders)
        )
            {
            // Folders which are not whitelisted or underneath a whitelisted folder will not be processed any further
            continue;
            }

        if(
            ($filetype == 'dir' || $filetype == 'link')
            && strpos($nogo, "[{$file}]") === false
            && strpos($file, $staticsync_alternatives_suffix) === false
        )
            {
            // Recurse
            ProcessFolder("{$folder}/{$file}");
            }

        # -------FILES---------------
        if (($filetype == "file") && (substr($file,0,1) != ".") && (strtolower($file) != "thumbs.db"))
            {                

            /* Below Code Adapted  from CMay's bug report */
            global $banned_extensions;
            # Check to see if extension is banned, do not add if it is banned
            if(array_search($extension, $banned_extensions)){continue;}
            /* Above Code Adapted from CMay's bug report */
            
            if ($count > $staticsync_max_files) { return(true); }

            # Already exists or deleted/archived in which case we won't proceed?
            if (!isset($done[$shortpath]))
                {
                // Extra check to make sure we don't end up with duplicates
                $existing=sql_value("SELECT ref value FROM resource WHERE file_path = '" . escape_check($shortpath) . "'",0);
                if($existing>0 || hook('staticsync_plugin_add_to_done'))
                    {
                    $done[$shortpath]["processed"]=true;
                    $done[$shortpath]["modified"]=date('Y-m-d H:i:s',time());
                    continue;
                    }
                $count++;
                echo "Processing file: $fullpath" . PHP_EOL;
                if ($collection == 0 && $staticsync_autotheme)
                    {
                    # Make a new collection for this folder.
                    $e = explode("/", $shortpath);
                    $theme        = ucwords($e[0]);
                    $themesql     = "theme='" . ucwords(escape_check($e[0])) . "'";
                    $themecolumns = "theme";
                    $themevalues  = "'" . ucwords(escape_check($e[0])) . "'";
                    
                    if ($staticsync_folder_structure)
                        {
                        for ($x=0;$x<count($e)-2;$x++)
                            {
                            if ($x != 0)
                                {
                                $themeindex = $x+1;
                                if ($themeindex >$theme_category_levels)
                                    {
                                    $theme_category_levels = $themeindex;
                                    if ($x == count($e)-2)
                                        {
                                        echo PHP_EOL . PHP_EOL . 
                                             "UPDATE THEME_CATEGORY_LEVELS TO $themeindex IN CONFIG!!!!" . 
                                             PHP_EOL . PHP_EOL;
                                        }
                                    }
                                $th_name       = ucwords(escape_check($e[$x]));
                                $themesql     .= " AND theme{$themeindex} = '$th_name'";
                                $themevalues  .= ",'$th_name'";
                                $themecolumns .= ",theme{$themeindex}";
                                }
                            }
                        }

                    $name = (count($e) == 1) ? '' : $e[count($e)-2];
                    echo "Collection $name, theme=$theme" . PHP_EOL;
                    $escaped_name = escape_check($name);
                    $collection = sql_value("SELECT ref value FROM collection WHERE name='$escaped_name' AND $themesql", 0);
                    if ($collection == 0)
                        {
                        sql_query("INSERT INTO collection (name,created,public,$themecolumns,allow_changes) 
                                                   VALUES ('$escaped_name', NOW(), 1, $themevalues, 0)");
                        $collection = sql_insert_id();
                        }
                    }

                # Work out a resource type based on the extension.
                $type = $staticsync_extension_mapping_default;
                reset($staticsync_extension_mapping);
                foreach ($staticsync_extension_mapping as $rt => $extensions)
                    {
                    if (in_array($extension,$extensions)) { $type = $rt; }
                    }
                $modified_type = hook('modify_type', 'staticsync', array( $type ));
                if (is_numeric($modified_type)) { $type = $modified_type; }

                # Formulate a title
                if ($staticsync_title_includes_path && $view_title_field!==$filename_field)
                    {
                    $title_find = array('/',   '_', ".$extension" );
                    $title_repl = array(' - ', ' ', '');
                    $title      = ucfirst(str_ireplace($title_find, $title_repl, $shortpath));
                    }
                else
                    {
                    $title = str_ireplace(".$extension", '', $file);
                    }
                $modified_title = hook('modify_title', 'staticsync', array( $title ));
                if ($modified_title !== false) { $title = $modified_title; }

                # Import this file
                $r = import_resource($shortpath, $type, $title, $staticsync_ingest,$enable_thumbnail_creation_on_upload);
                if ($r !== false)
                    {
                    # Add to mapped category tree (if configured)
                    if (isset($staticsync_mapped_category_tree) && isset($treenodes))
                        {
                        // Add path nodes to resource
                        add_resource_nodes($r,$treenodes);
                        }           

                    # default access level. This may be overridden by metadata mapping.
                    $accessval = 0;

                    # StaticSync path / metadata mapping
                    # Extract metadata from the file path as per $staticsync_mapfolders in config.php
                    if (isset($staticsync_mapfolders))
                        {
                        $field_nodes    = array();
                        foreach ($staticsync_mapfolders as $mapfolder)
                            {
                            $match = $mapfolder["match"];
                            $field = $mapfolder["field"];
                            $level = $mapfolder["level"];

                            if (strpos("/" . $shortpath, $match) !== false)
                                {
                                # Match. Extract metadata.
                                $path_parts = explode("/", $shortpath);
                                if ($level < count($path_parts))
                                    {
                                    // special cases first.
                                    if ($field == 'access')
                                        {
                                        # access level is a special case
                                        # first determine if the value matches a defined access level

                                        $value = $path_parts[$level-1];

                                        for ($n=0; $n<3; $n++){
                                            # if we get an exact match or a match except for case
                                            if ($value == $lang["access" . $n] || strtoupper($value) == strtoupper($lang['access' . $n]))
                                                {
                                                $accessval = $n;
                                                echo "Will set access level to " . $lang['access' . $n] . " ($n)" . PHP_EOL;
                                                }
                                            }

                                        }
                                    else if ($field == 'archive')
										{
										# archive level is a special case
										# first determin if the value matches a defined archive level
										
										$value = $mapfolder["archive"];
										$archive_array=array_merge(array(-2,-1,0,1,2,3),$additional_archive_states);
										
										if(in_array($value,$archive_array))
											{
											$archiveval = $value;
											echo "Will set archive level to " . $lang['status' . $value] . " ($archiveval)". PHP_EOL;
											}
										
										}
                                    else 
                                        {
                                        # Save the value
                                        $value = $path_parts[$level-1];
                                        $field_info=get_resource_type_field($field);
                                        if(in_array($field_info['type'], $FIXED_LIST_FIELD_TYPES))
                                            {
                                            $fieldnodes=get_nodes($field);
                                            if(in_array($value, array_column($fieldnodes,"name")) || ($field_info['type']==FIELD_TYPE_DYNAMIC_KEYWORDS_LIST && !checkperm('bdk' . $field)))
                                                {
                                                // Add this to array of nodes to add
                                                $newnode = set_node(null, $field, trim($value), null, null, true);
                                                echo "Adding node" . trim($value) . "\n";
                                                
                                                if($staticsync_extension_mapping_append_values && !in_array($field_info['type'],array(FIELD_TYPE_DROP_DOWN_LIST,FIELD_TYPE_RADIO_BUTTONS)) && (!isset($staticsync_extension_mapping_append_values_fields) || in_array($field_info['ref'], $staticsync_extension_mapping_append_values_fields)))
                                                    {
                                                    // The $staticsync_extension_mapping_append_values variable actually refers to folder->metadata mapping, not the file extension
                                                    $field_nodes[$field][]   = $newnode;
                                                    }
                                                else
                                                    {
                                                    // We have got a new value for this field and we are not appending values,
                                                    // replace any existing value the array 
                                                    $field_nodes[$field]   = array($newnode);
                                                    }
                                                }                                            
                                            }
                                        else
                                            {
                                            if($staticsync_extension_mapping_append_values && (!isset($staticsync_extension_mapping_append_values_fields) || in_array($field_info['ref'], $staticsync_extension_mapping_append_values_fields)))
                                                {
                                                $given_value=$value;
                                                // append the values if possible...not used on dropdown, date, category tree, datetime, or radio buttons
                                                if(in_array($field['type'],array(0,1,4,5,6,8)))
                                                    {
                                                    $old_value=sql_value("select value value from resource_data where resource=$r and resource_type_field=$field","");
                                                    $value=append_field_value($field_info,$value,$old_value);
                                                    }
                                                }
                                            update_field ($r, $field, $value);
                                            if($staticsync_extension_mapping_append_values && (!isset($staticsync_extension_mapping_append_values_fields) || in_array($field_info['ref'], $staticsync_extension_mapping_append_values_fields)) && isset($given_value))
                                                {
                                                $value=$given_value;
                                                }
                                            }
                                        echo " - Extracted metadata from path: $value" . PHP_EOL;
                                        }
                                    }
                                }
                            }
                        if(count($field_nodes)>0)
                            {
                            $nodes_to_add = array();
                            foreach($field_nodes as $field_id=>$nodeids)
                                {
                                $nodes_to_add = array_merge($nodes_to_add,$nodeids);
                                }
                            add_resource_nodes($r,$nodes_to_add);
                            }
                        }
                        
                    if(isset($staticsync_filepath_to_field))
						{
						update_field($r,$staticsync_filepath_to_field,$shortpath);
						}

                    # update access level
                    sql_query("UPDATE resource SET access = '$accessval',archive='$staticsync_defaultstate' " . ((!$enable_thumbnail_creation_on_upload)?", has_image=0, preview_attempts=0 ":"") . " WHERE ref = '$r'");

                    # Add any alternative files
                    $altpath = $fullpath . $staticsync_alternatives_suffix;
                    if ($staticsync_ingest && file_exists($altpath))
                        {
                        $adh = opendir($altpath);
                        while (($altfile = readdir($adh)) !== false)
                            {
                            $filetype = filetype($altpath . "/" . $altfile);
                            if (($filetype == "file") && (substr($file,0,1) != ".") && (strtolower($file) != "thumbs.db"))
                                {
                                # Create alternative file
                                # Find extension
                                $ext = explode(".", $altfile);
                                $ext = $ext[count($ext)-1];
                                
                                $description = str_replace("?", strtoupper($ext), $lang["originalfileoftype"]);
                                $file_size   = filesize_unlimited($altpath . "/" . $altfile);
                                
                                $aref = add_alternative_file($r, $altfile, $description, $altfile, $ext, $file_size);
                                $path = get_resource_path($r, true, '', true, $ext, -1, 1, false, '', $aref);
                                rename($altpath . "/" . $altfile,$path); # Move alternative file
                                }
                            }   
                        }
					elseif(isset($staticsync_alternative_file_text))
						{
						$basefilename=str_ireplace(".$extension", '', $file);
						$altfilematch = $folder . "/" . $basefilename . $staticsync_alternative_file_text . "*.*";
						echo "Searching for alternative files for base file: " . $basefilename , PHP_EOL; 
						echo "checking " . $altfilematch . PHP_EOL;
						$altfiles = glob($altfilematch);
						foreach ($altfiles as $altfile)
							{
                            staticsync_process_alt($altfile,$r);
							echo "Processed alternative: " . $shortpath . PHP_EOL;
                            }
						continue;
						}

                    # Add to collection
                    if ($staticsync_autotheme)
                        {
                        $test = ''; 
                        $test = sql_query("SELECT * FROM collection_resource WHERE collection='$collection' AND resource='$r'");
                        if (count($test) == 0)
                            {
                            sql_query("INSERT INTO collection_resource (collection, resource, date_added) 
                                            VALUES ('$collection', '$r', NOW())");
                            }
                        }                        
                    $done[$shortpath]["ref"]=$r;
                    $done[$shortpath]["processed"]=true;
                    $done[$shortpath]["modified"]=date('Y-m-d H:i:s',time());
                    }
                else
                    {
                    # Import failed - file still being uploaded?
                    echo " *** Skipping file - it was not possible to move the file (still being imported/uploaded?)" . PHP_EOL;
                    }                
                }
            elseif (!isset($done[$shortpath]["archive"]) // Check modified times and and update previews if no existing archive state is set,
                    || (isset($resource_deletion_state) && $done[$shortpath]["archive"]!=$resource_deletion_state) // or if resource is not in system deleted state,
                    || (isset($staticsync_revive_state) && $done[$shortpath]["archive"]==$staticsync_deleted_state)) // or resource is currently in staticsync deleted state and needs to be reinstated
                {
                $filemod = filemtime($fullpath);
                if (isset($done[$shortpath]["modified"]) && $filemod > strtotime($done[$shortpath]["modified"]) || (isset($staticsync_revive_state) && $done[$shortpath]["archive"]==$staticsync_deleted_state))
                    {
                    
                    $count++;
                    # File has been modified since we last created previews. Create again.
                    $rd = sql_query("SELECT ref, has_image, file_modified, file_extension, archive FROM resource 
                                        WHERE file_path='" . escape_check($shortpath) . "'");
                    if (count($rd) > 0)
                        {
                        $rd   = $rd[0];
                        $rref = $rd["ref"];

                        echo "Resource $rref has changed, regenerating previews: $fullpath" . PHP_EOL;
                        extract_exif_comment($rref,$rd["file_extension"]);

                        # extract text from documents (e.g. PDF, DOC).
                        global $extracted_text_field;
                        if (isset($extracted_text_field)) {
                            if (isset($unoconv_path) && in_array($extension,$unoconv_extensions)){
                                // omit, since the unoconv process will do it during preview creation below
                                }
                            else {
                            extract_text($rref,$extension);
                            }
                        }

                        # Store original filename in field, if set
                        global $filename_field;
                        if (isset($filename_field))
                            {
                            update_field($rref,$filename_field,$file);  
                            }
                        if($enable_thumbnail_creation_on_upload)
                            {
                            create_previews($rref, false, $rd["file_extension"], false, false, -1, false, $staticsync_ingest);
                            }
                        sql_query("UPDATE resource SET file_modified=NOW() " . ((isset($staticsync_revive_state) && ($rd["archive"]==$staticsync_deleted_state))?", archive='" . $staticsync_revive_state . "'":"") . ((!$enable_thumbnail_creation_on_upload)?", has_image=0, preview_attempts=0 ":"") . " WHERE ref='$rref'");

                        if(isset($staticsync_revive_state) && ($rd["archive"]==$staticsync_deleted_state))
                            {
                            # Log this
                            resource_log($rref,LOG_CODE_STATUS_CHANGED,'','',$staticsync_deleted_state,$staticsync_revive_state);
                            }
                        }
                    }
                }
            }   
        }   
    }
    
function staticsync_process_alt($alternativefile, $ref="", $alternative="")
    {
    // Process an alternative file
    global $staticsync_alternative_file_text, $syncdir, $lang, $staticsync_ingest, $alternative_file_previews, $done;
	
    $shortpath = str_replace($syncdir . '/', '', $alternativefile);
	if(!isset($done[$shortpath]))
		{
		$alt_parts=pathinfo($alternativefile);
		$altfilenameparts = explode($staticsync_alternative_file_text,$alt_parts['filename']);
		$altbasename=$altfilenameparts[0];
		if($ref=="")
			{
			// We need to find which resource this relates to
			echo "Searching for primary resource related to " . $alternativefile . "  in " . $alt_parts['dirname'] . '/' . $altbasename . "." .  PHP_EOL;
			foreach($done as $syncedfile=>$synceddetails)
				{
				if(strpos($syncdir . '/' . $syncedfile,$alt_parts['dirname'] . '/' . $altbasename . ".")!==false)
					{
					// This synced file has the same base name as the resource
					$ref= $synceddetails["ref"];
					break;
					}
				}        
			}
        
         if($ref=="")
            {
            echo "No primary resource found for " . $alternativefile . ". Skipping file" . PHP_EOL;
            debug("staticsync - No primary resource found for " . $alternativefile . ". Skipping file");
            return false;
            }
         
        echo "Processing alternative file - '" . $alternativefile . "' for resource #" . $ref . PHP_EOL;
		
		$alt["file_size"]   = filesize_unlimited($alternativefile);
		$altparts = explode(".", $alternativefile);
		$alt["extension"] = $altparts[count($altparts)-1];
		
		if($alternative=="")
			{
			// Create a new alternative file
			$alt["altdescription"] = $altfilenameparts[1];
			$alt["name"] = str_replace("?", strtoupper($alt["extension"]), $lang["fileoftype"]);
			$alt["ref"] = add_alternative_file($ref, $alt["name"], $alt["altdescription"], $alternativefile, $alt["extension"], $alt["file_size"]);
			
			echo "Created a new alternative file - '" . $alt["ref"] . "' for resource #" . $ref . PHP_EOL;
            debug("Staticsync - Created a new alternative file - '" . $alt["ref"] . "' for resource #" . $ref);
			$alt["path"] = get_resource_path($ref, true, '', false, $alt["extension"], -1, 1, false, '',  $alt["ref"]);
			echo "- alternative file path - " . $alt["path"] . PHP_EOL;
            debug("Staticsync - alternative file path - " . $alt["path"]);
			$alt["basefilename"] = $altbasename;
			if($staticsync_ingest)
				{
				echo "- moving file to " . $alt["path"] . PHP_EOL;
				rename($alternativefile,$alt["path"]); # Move alternative file
				}
			if ($alternative_file_previews)
				{create_previews($ref,false,$alt["extension"],false,false,$alt["ref"],false, $staticsync_ingest);}
			hook("staticsync_after_alt", '',array($ref,$alt));
			echo "Added alternative file ref:"  . $alt["ref"] . ", name: " . $alt["name"] . ". " . "(" . $alt["altdescription"] . ") Size: " . $alt["file_size"] . PHP_EOL;
            debug("Staticsync - added alternative file ref:"  . $alt["ref"] . ", name: " . $alt["name"] . ". " . "(" . $alt["altdescription"] . ") Size: " . $alt["file_size"]);
            $done[$shortpath]["processed"]=true;
			}  
		}
    elseif($alternative!="" && $alternative_file_previews)
        {
        // An existing alternative file has changed, update previews if required
        debug("Alternative file changed, recreating previews");
		create_previews($ref, false,  pathinfo($alternativefile, PATHINFO_EXTENSION), false, false, $alternative, false, $staticsync_ingest);
        sql_query("UPDATE resource_alt_files SET creation_date=NOW() WHERE ref='$alternative'"); 
        $done[$shortpath]["processed"]=true;           
        }	
	echo "Completed path : " . $shortpath . PHP_EOL;
	$done[$shortpath]["ref"]=$ref;
    $done[$shortpath]["alternative"]=$alternative;
    set_process_lock("staticsync"); // Update the lock so we know it is still processing resources
    }

# Recurse through the folder structure.
ProcessFolder($syncdir);

// Look for alternative files that may have not been processed
foreach($alternativefiles as $alternativefile)
    {
    $shortpath = str_replace($syncdir . "/", '', $alternativefile);
    echo "Processing alternative file " . $shortpath . PHP_EOL;
    debug("Staticsync -  Processing altfile " . $shortpath);
    if (!isset($done[$shortpath]))
        {
        staticsync_process_alt($alternativefile);        
        }
    elseif($alternative_file_previews)
        {
        // File already synced but check if it has been modified as may need to update previews
        $altfilemod = filemtime($alternativefile);
        if (isset($done[$shortpath]["modified"]) && $altfilemod > strtotime($done[$shortpath]["modified"]))
            {
            // Update the alternative file
            staticsync_process_alt($alternativefile,$done[$shortpath]["resource"],$done[$shortpath]["alternative"]);
            }
        }
    }

echo "...done." . PHP_EOL;

if (!$staticsync_ingest)
    {
    # If not ingesting files, look for deleted files in the sync folder and archive the appropriate file from ResourceSpace.
    echo "Looking for deleted files..." . PHP_EOL;
    # For all resources with filepaths, check they still exist and archive if not.
    //$resources_to_archive = sql_query("SELECT ref,file_path FROM resource WHERE archive=0 AND LENGTH(file_path)>0 AND file_path LIKE '%/%'");
    $resources_to_archive =array();
    $n=0;
    foreach($done as $syncedfile=>$synceddetails)    
        {
        if(!isset($synceddetails["processed"]) && isset($synceddetails["archive"]) && !(isset($staticsync_ignore_deletion_states) && in_array($synceddetails["archive"],$staticsync_ignore_deletion_states)) && $synceddetails["archive"]!=$staticsync_deleted_state || isset($synceddetails["alternative"]))
            {
            $resources_to_archive[$n]["file_path"]=$syncedfile;
            $resources_to_archive[$n]["ref"]=$synceddetails["ref"];
            $resources_to_archive[$n]["archive"]=isset($synceddetails["archive"])?$synceddetails["archive"]:"";
            if(isset($synceddetails["alternative"]))
                {$resources_to_archive[$n]["alternative"]=$synceddetails["alternative"];}
            $n++;
            }
        }
        
    # ***for modified syncdir directories:
    $syncdonemodified = hook("modifysyncdonerf");
    if (!empty($syncdonemodified)) { $resources_to_archive = $syncdonemodified; }
    
    foreach ($resources_to_archive as $rf)
        {
        $fp = $syncdir . '/' . $rf["file_path"];
        if (isset($rf['syncdir']) && $rf['syncdir'] != '')
               {
               # ***for modified syncdir directories:
               $fp = $rf['syncdir'].$rf["file_path"];
               }
         
        if ($fp!="" && !file_exists($fp))
            {
			// Additional check - make sure the archive state hasn't changed since the start of the script
			$cas=sql_value("SELECT archive value FROM resource where ref='{$rf["ref"]}'",0);
			if(isset($staticsync_ignore_deletion_states) && !in_array($cas,$staticsync_ignore_deletion_states))
				{
				if(!isset($rf["alternative"]))
					{
					echo "File no longer exists: " . $rf["ref"] . " " . $fp . PHP_EOL;
					# Set to archived, unless state hasn't changed since script started.
					sql_query("UPDATE resource SET archive='" . $staticsync_deleted_state . "' WHERE ref='{$rf["ref"]}'");
					if(isset($resource_deletion_state) && $staticsync_deleted_state==$resource_deletion_state)
						{
						// Only remove from collections if we are really deleting this. Some configurations may have a separate state or synced resources may be temporarily absent
						sql_query("DELETE FROM collection_resource WHERE resource='{$rf["ref"]}'");
						}
					# Log this
					resource_log($rf['ref'],LOG_CODE_STATUS_CHANGED,'','',$rf["archive"],$staticsync_deleted_state);
					} 
				else
					{
					echo "Alternative file no longer exists: resource " . $rf["ref"] . " alt:" . $rf["alternative"] . " " . $fp . PHP_EOL;
					sql_query("DELETE FROM resource_alt_files WHERE ref='" . $rf["alternative"] . "'");
					}
				}
            }
        }
        
    # Remove any themes that are now empty as a result of deleted files.
    sql_query("DELETE FROM collection WHERE theme IS NOT NULL AND LENGTH(theme) > 0 AND 
                (SELECT count(*) FROM collection_resource cr WHERE cr.collection=collection.ref) = 0;");

    echo "...Complete" . PHP_EOL;
    }

sql_query("UPDATE sysvars SET value=now() WHERE name='lastsync'");

clear_process_lock("staticsync");

?>

