<?php
/**
* Functions used to render HTML & Javascript
*
* @package ResourceSpace
*/


/**
* Renders the HTML for the provided $field for inclusion in a search form, for example the
* advanced search page. Standard field titles are translated using $lang.  Custom field titles are i18n translated.
*
* $field    an associative array of field data, i.e. a row from the resource_type_field table.
* $name     the input name to use in the form (post name)
* $value    the default value to set for this field, if any
* @param array $searched_nodes Array of all the searched nodes previously
*/
function render_search_field($field,$value="",$autoupdate,$class="stdwidth",$forsearchbar=false,$limit_keywords=array(), $searched_nodes = array())
    {
    node_field_options_override($field);
	
	global $auto_order_checkbox, $auto_order_checkbox_case_insensitive, $lang, $category_tree_open, $minyear, $daterange_search, $searchbyday, $is_search, $values, $n, $simple_search_show_dynamic_as_dropdown, $clear_function, $simple_search_display_condition, $autocomplete_search, $baseurl, $fields, $baseurl_short, $extrafooterhtml,$FIXED_LIST_FIELD_TYPES;
    
    // set this to zero since this does not apply to collections
    if (!isset($field['field_constraint'])){$field['field_constraint']=0;}
      
    $name="field_" . ($forsearchbar ? htmlspecialchars($field["name"]) : $field["ref"]);
    $id="field_" . $field["ref"];

    $scriptconditions=array();
        
    #Check if field has a display condition set
    $displaycondition=true;
    if ($field["display_condition"]!="" && (!$forsearchbar || ($forsearchbar && !empty($simple_search_display_condition) && in_array($field['ref'],$simple_search_display_condition))))
        {
        $s=explode(";",$field["display_condition"]);
        $condref=0;
        foreach ($s as $condition) # Check each condition
            {
            $displayconditioncheck=false;
            $s=explode("=",$condition);
            global $fields;
            for ($cf=0;$cf<count($fields);$cf++) # Check each field to see if needs to be checked
                {
                if ($s[0]==$fields[$cf]["name"] && ($fields[$cf]["resource_type"]==0 || $fields[$cf]["resource_type"]==$field["resource_type"])) # this field needs to be checked
                    {
                    $display_condition_js_prepend=($forsearchbar ? "#simplesearch_".$fields[$cf]["ref"]." " : "");
                    
                    $scriptconditions[$condref]["field"]               = $fields[$cf]["ref"];  # add new jQuery code to check value
                    $scriptconditions[$condref]['type']                = $fields[$cf]['type'];
                    $scriptconditions[$condref]['display_as_dropdown'] = $fields[$cf]['display_as_dropdown'];
					$scriptconditionnodes = get_nodes($fields[$cf]['ref'], null, (FIELD_TYPE_CATEGORY_TREE == $fields[$cf]['type'] ? true : false));
                    
                    //$scriptconditions[$condref]['node_options'] = array();

                    $checkvalues=$s[1];
                    $validvalues=explode("|",strtoupper($checkvalues));
					$scriptconditions[$condref]['valid'] = array();
					foreach($validvalues as $validvalue)
						{
						$found_validvalue = get_node_by_name($scriptconditionnodes, $validvalue);

						if(0 != count($found_validvalue))
							{
							$scriptconditions[$condref]['valid'][] = $found_validvalue['ref'];

							if(in_array($found_validvalue['ref'],$searched_nodes))
								{
								$displayconditioncheck = true;
								}
							}
						}
				

                    if (!$displayconditioncheck) {$displaycondition=false;}
					
					// Certain fixed list types allow for multiple nodes to be passed at the same time
					if(in_array($fields[$cf]['type'], $FIXED_LIST_FIELD_TYPES))
						{
						if(FIELD_TYPE_CATEGORY_TREE == $fields[$cf]['type'])
							{
							?>
							<script>
							jQuery(document).ready(function()
								{
								jQuery('#CentralSpace').on('categoryTreeChanged', function(e,node)
									{
									checkSearchDisplayCondition<?php echo $field['ref']; ?>(node);
									});
								});
							</script>
							<?php

							// Move on to the next field now
							continue;
							}
						else if(FIELD_TYPE_DYNAMIC_KEYWORDS_LIST == $fields[$cf]['type'])
							{
							?>
							<script>
							jQuery(document).ready(function()
								{
								jQuery('#CentralSpace').on('dynamicKeywordChanged', function(e,node)
									{
									checkSearchDisplayCondition<?php echo $field['ref']; ?>(node);
									});
								});
							</script>
							<?php

							// Move on to the next field now
							continue;
							}
                        else
                            {

                            $checkname = "nodes_searched[{$fields[$cf]['ref']}][]";
                            $jquery_selector = "input[name=\"{$checkname}\"]";
                            if(FIELD_TYPE_DROP_DOWN_LIST == $fields[$cf]['type'] && true == $fields[$cf]['display_as_dropdown'])
                                {
                                $checkname       = "nodes_searched[{$fields[$cf]['ref']}]";
                                $jquery_selector = "select[name=\"{$checkname}\"]";
                                }
                            ?>
                            <script type="text/javascript">
                            jQuery(document).ready(function()
                                {
                                jQuery('<?php echo $jquery_selector; ?>').change(function ()
                                    {
                                    checkSearchDisplayCondition<?php echo $field['ref']; ?>(jQuery(this).val());
                                    });
                                });
                            </script>
                            <?php
                            }
						}
					else
						{
						?>
						<script type="text/javascript">
						jQuery(document).ready(function()
							{
							jQuery('#field_<?php echo $fields[$cf]["ref"]; ?>').change(function ()
								{
								checkSearchDisplayCondition<?php echo $field['ref']; ?>();
								});
							});
						</script>
						<?php
						}
					}
                } # see if next field needs to be checked

            $condref++;
            } # check next condition

        ?>
        <script type="text/javascript">
        
        function checkSearchDisplayCondition<?php echo $field["ref"];?>(node)
			{
            field<?php echo $field['ref']; ?>status    = jQuery('#question_<?php echo $n; ?>').css('display');
			newfield<?php echo $field['ref']; ?>status = 'none';
			newfield<?php echo $field['ref']; ?>show   = false;
			<?php
			foreach($scriptconditions as $scriptcondition)
				{
                /*
                Example of $scriptcondition:
                Array
                    (
                    [field] => 73
                    [type] => 2
                    [valid] => Array
                        (
                            [0] => 267
                            [1] => 266
                        )
                    )
                */
				?>
                fieldokvalues<?php echo $scriptcondition['field']; ?> = <?php echo json_encode($scriptcondition['valid']); ?>;
				<?php
                ############################
                ### Field type specific
                ############################
                if(in_array($scriptcondition['type'], $FIXED_LIST_FIELD_TYPES))
                    {
                    $jquery_condition_selector = "input[name=\"nodes_searched[{$scriptcondition['field']}][]\"]";
                    $js_conditional_statement  = "fieldokvalues{$scriptcondition['field']}.indexOf(element.value) != -1";

                    if(in_array($scriptcondition['type'], array(FIELD_TYPE_CHECK_BOX_LIST, FIELD_TYPE_RADIO_BUTTONS))
                        || (FIELD_TYPE_DROP_DOWN_LIST == $scriptcondition['type'] && false == $scriptcondition['display_as_dropdown']))
                        {
                        $js_conditional_statement = "jQuery(this).prop('checked') && {$js_conditional_statement}";
                        }

                    if(FIELD_TYPE_DROP_DOWN_LIST == $scriptcondition['type'] && true == $scriptcondition['display_as_dropdown'])
                        {
                        $jquery_condition_selector = "select[name=\"nodes_searched[{$scriptcondition['field']}]\"] option:selected";
                        }
						
						?>
                    if(!newfield<?php echo $field['ref']; ?>show)
                        {
                        jQuery('<?php echo $jquery_condition_selector; ?>').each(function(index, element)
                            {
							 if(<?php echo $js_conditional_statement; ?>)
                                {
                                newfield<?php echo $field['ref']; ?>show = true;
                                }
                            });
                        }
                    <?php
                    }
                ############################
                ############################
                }
                ?>

                if(newfield<?php echo $field['ref']; ?>show)
                    {
                    newfield<?php echo $field['ref']; ?>status = 'block';
                    }

                if(newfield<?php echo $field['ref']; ?>status != field<?php echo $field['ref']; ?>status)
                    {
                    jQuery('#question_<?php echo $n ?>').slideToggle();

                    if(jQuery('#question_<?php echo $n ?>').css('display') == 'block')
                        {
                        jQuery('#question_<?php echo $n ?>').css('border-top', '');
                        }
                    else
                        {
                        jQuery('#question_<?php echo $n ?>').css('border-top', 'none');
                        }
                    }
        }
        </script>
    	<?php
    	if($forsearchbar)
    		{
    		// add the display condition check to the clear function
    		$clear_function.="checkSearchDisplayCondition".$field['ref']."();";
    		}
        }

    $is_search = true;

    if (!$forsearchbar)
        {
        ?>
        <div class="Question" id="question_<?php echo $n ?>" <?php if (!$displaycondition) {?>style="display:none;border-top:none;"<?php } ?><?php
        if (strlen($field["tooltip_text"])>=1)
            {
            echo "title=\"" . htmlspecialchars(lang_or_i18n_get_translated($field["tooltip_text"], "fieldtooltip-")) . "\"";
            }
        ?>>
        <label><?php echo htmlspecialchars(lang_or_i18n_get_translated($field["title"], "fieldtitle-")) ?></label>
        <?php
        }
    else
        {
        hook("modifysearchfieldtitle");
        ?>
        <div class="SearchItem" id="simplesearch_<?php echo $field["ref"] ?>" <?php if (!$displaycondition) {?>style="display:none;"<?php } if (strlen($field["tooltip_text"]) >= 1){ echo "title=\"" . htmlspecialchars(lang_or_i18n_get_translated($field["tooltip_text"], "fieldtooltip-")) . "\"";} ?> ><?php echo htmlspecialchars(lang_or_i18n_get_translated($field["title"], "fieldtitle-")) ?></br>
        
        <?php
        #hook to modify field type in special case. Returning zero (to get a standard text box) doesn't work, so return 1 for type 0, 2 for type 1, etc.
		if(hook("modifyfieldtype")){$fields[$n]["type"]=hook("modifyfieldtype")-1;}
        }

    //hook("rendersearchhtml", "", array($field, $class, $value, $autoupdate));

    switch ($field["type"]) {
        case FIELD_TYPE_TEXT_BOX_SINGLE_LINE:
        case FIELD_TYPE_TEXT_BOX_MULTI_LINE:
        case FIELD_TYPE_TEXT_BOX_LARGE_MULTI_LINE:
        case FIELD_TYPE_TEXT_BOX_FORMATTED_AND_CKEDITOR:
        case ($forsearchbar && $field["type"]==FIELD_TYPE_DYNAMIC_KEYWORDS_LIST && !$simple_search_show_dynamic_as_dropdown):
        if ($field['field_constraint']==0){ 
			
			?><input class="<?php echo $class ?>" type=text name="<?php echo $name ?>" id="<?php echo $id ?>" value="<?php echo htmlspecialchars($value)?>" <?php if($forsearchbar && !$displaycondition) { ?> disabled <?php } ?> <?php if ($autoupdate) { ?>onChange="UpdateResultCount();"<?php } if(!$forsearchbar){ ?> onKeyPress="if (!(updating)) {setTimeout('UpdateResultCount()',2000);updating=true;}"<?php } if($forsearchbar){?>onKeyUp="if('' != jQuery(this).val()){FilterBasicSearchOptions('<?php echo htmlspecialchars($field["name"]) ?>',<?php echo htmlspecialchars($field["resource_type"]) ?>);}"<?php } ?>><?php 
			# Add to the clear function so clicking 'clear' clears this box.
			$clear_function.="document.getElementById('field_" . ($forsearchbar? $field["ref"] : $field["name"]) . "').value='';";
		}
        // number view - manipulate the form value (don't send these but send a compiled numrange value instead
        else if ($field['field_constraint']==1){ // parse value for to/from simple search
			$minmax=explode('|',str_replace("numrange","",$value));
			($minmax[0]=='')?$minvalue='':$minvalue=str_replace("neg","-",$minmax[0]);
			(isset($minmax[1]))?$maxvalue=str_replace("neg","-",$minmax[1]):$maxvalue='';
			?>
			<input id="<?php echo $name ?>_min" onChange="jQuery('#<?php echo $name?>').val('numrange'+jQuery(this).val().replace('-','neg')+'|'+jQuery('#<?php echo $name?>_max').val().replace('-','neg'));" class="NumberSearchWidth" type="number" value="<?php echo htmlspecialchars($minvalue)?>"> ...
			<input id="<?php echo $name ?>_max" onChange="jQuery('#<?php echo $name?>').val('numrange'+jQuery('#<?php echo $name?>_min').val().replace('-','neg')+'|'+jQuery(this).val().replace('-','neg'));" class="NumberSearchWidth" type="number" value="<?php echo htmlspecialchars($maxvalue)?>">
			<input id="<?php echo $name?>" name="<?php echo $name?>" type="hidden" value="<?php echo $value?>">
		<?php 
			# Add to the clear function so clicking 'clear' clears this box.
			 $clear_function.="document.getElementById('".$name."_max').value='';";
			 $clear_function.="document.getElementById('".$name."_min').value='';";
			 $clear_function.="document.getElementById('".$name."').value='';";
		}
		

        
        if ($forsearchbar && $autocomplete_search) { 
				# Auto-complete search functionality
				?></div>
				<script type="text/javascript">
				
				jQuery(document).ready(function () { 
				
					jQuery("#field_<?php echo htmlspecialchars($field["name"])?>").autocomplete( { source: "<?php echo $baseurl?>/pages/ajax/autocomplete_search.php?field=<?php echo htmlspecialchars($field["name"]) ?>&fieldref=<?php echo $field["ref"]?>"} );
					})
				
				</script>
				<div class="SearchItem">
			<?php }
            
        break;
    
        case FIELD_TYPE_CHECK_BOX_LIST: 
        case FIELD_TYPE_DROP_DOWN_LIST:
        case ($forsearchbar && $field["type"]==FIELD_TYPE_DYNAMIC_KEYWORDS_LIST && $simple_search_show_dynamic_as_dropdown):
       if(!hook("customchkboxes", "", array($field, $value, $autoupdate, $class, $forsearchbar, $limit_keywords)))
            {
            global $checkbox_ordered_vertically;

            # -------- Show a check list or dropdown for dropdowns and check lists?
            # By default show a checkbox list for both (for multiple selections this enabled OR functionality)
            
            $setnames  = trim_array(explode(";",cleanse_string($value,true)));
            # Translate all options
            $adjusted_dropdownoptions=hook("adjustdropdownoptions");
            if ($adjusted_dropdownoptions){$options=$adjusted_dropdownoptions;}
            
            if($forsearchbar)
            	{
            	$optionfields[]=$field["name"]; # Append to the option fields array, used by the AJAX dropdown filtering
            	}

            $node_options = array();
            foreach($field['nodes'] as $node)
                {
                $node_options[] = $node['name'];
                }

            if((bool) $field['automatic_nodes_ordering'])
                {
                $field['nodes'] = reorder_nodes($field['nodes']);
                }

            $order_by_resetter = 0;
            foreach($field['nodes'] as $node_index => $node)
                {
                // Special case for vertically ordered checkboxes.
                // Order by needs to be reset as per the new order so that we can reshuffle them using the order by as a reference
                if($checkbox_ordered_vertically)
                    {
                    $field['nodes'][$node_index]['order_by'] = $order_by_resetter++;
                    }
                }

            if ($field["display_as_dropdown"] || $forsearchbar)
                {
                # Show as a dropdown box
                $name = "nodes_searched[{$field['ref']}]";
                ?>
                <select class="<?php echo $class ?>" name="<?php echo $name ?>" id="<?php echo $id ?>" <?php if($forsearchbar && !$displaycondition) { ?> disabled <?php } ?> <?php if ($autoupdate) { ?>onChange="UpdateResultCount();"<?php } if($forsearchbar){?> onChange="FilterBasicSearchOptions('<?php echo htmlspecialchars($field["name"]) ?>',<?php echo htmlspecialchars($field["resource_type"]) ?>);" <?php } ?>>
                    <option value=""></option>
                <?php
                foreach($field['nodes'] as $node)
                    {
                    if('' != trim($node['name']))
                        {
                        ?>
                        <option value="<?php echo htmlspecialchars(trim($node['ref'])); ?>" <?php if (0 < count($searched_nodes) && in_array($node['ref'], $searched_nodes)) { ?>selected<?php } ?>><?php echo htmlspecialchars(trim(i18n_get_translated($node['name']))); ?></option>
                        <?php
                        }
                    }
                ?></select><?php
                if($forsearchbar)
                	{
                	// Add to the clear function so clicking 'clear' clears this box.
					$clear_function .= "document.getElementById('{$id}').selectedIndex = -1;";
                	}
                }
            else
                {
                # Show as a checkbox list (default)
                $setnames=trim_array(explode(";",$value));
                $wrap=0;

                $l    = average_length($node_options);
                switch($l)
                    {
                    case($l > 40): $cols = 1; break; 
                    case($l > 25): $cols = 2; break;
                    case($l > 15): $cols = 3; break;
                    case($l > 10): $cols = 4; break;
                    case($l > 5):  $cols = 5; break;
                    default:       $cols = 10;
                    }

                $height = ceil(count($field['nodes']) / $cols);

                global $checkbox_ordered_vertically, $checkbox_vertical_columns;
                if($checkbox_ordered_vertically)
                    {
                    if(!hook('rendersearchchkboxes'))
                        {
                        # ---------------- Vertical Ordering (only if configured) -----------
                        ?>
                        <table cellpadding=2 cellspacing=0>
                            <tbody>
                                <tr>
                                <?php
                                for($i = 0; $i < $height; $i++)
                                    {
                                    for($j = 0; $j < $cols; $j++)
                                        {
                                        $order_by = ($height * $j) + $i;

                                        $node_index_to_be_reshuffled = array_search($order_by, array_column($field['nodes'], 'order_by', 'ref'));

                                        if(false === $node_index_to_be_reshuffled)
                                            {
                                            continue;
                                            }

                                        $node = $field['nodes'][$node_index_to_be_reshuffled];
                                        ?>
                                        <td valign=middle>
                                            <input id="nodes_searched_<?php echo $node['ref']; ?>" type="checkbox" name="nodes_searched[<?php echo $field['ref']; ?>][]" value="<?php echo $node['ref']; ?>" <?php if((0 < count($searched_nodes) && in_array($node['ref'], $searched_nodes)) || in_array(i18n_get_translated($node['name']),$setnames)) { ?>checked<?php } ?> <?php if($autoupdate) { ?>onClick="UpdateResultCount();"<?php } ?>>
                                        </td>
                                        <td valign=middle>
                                            <?php echo htmlspecialchars(i18n_get_translated($node['name'])); ?>&nbsp;&nbsp;
                                        </td>
                                        <?php
                                        }
                                        ?>
                                    </tr>
                                    <tr>
                                    <?php
                                    }
                                    ?>
                            </tbody>
                        </table>
                        <?php
                        }
                    }
                else
                    {
                    # ---------------- Horizontal Ordering (Standard) ---------------------             
                    ?>
                    <table cellpadding=2 cellspacing=0>
                        <tr>
                    <?php
                    foreach($field['nodes'] as $node)
                        {
                        $wrap++;

                        if($wrap > $cols)
                            {
                            $wrap = 1;
                            ?>
                            </tr>
                            <tr>
                            <?php
                            }

                        if('' != $node['name'])
                            {
                            ?>
                            <td valign=middle>
                                <input id="nodes_searched_<?php echo $node['ref']; ?>" type="checkbox" name="nodes_searched[<?php echo $field['ref']; ?>][]" value="<?php echo $node['ref']; ?>" <?php if ((0 < count($searched_nodes) && in_array($node['ref'], $searched_nodes)) || in_array(i18n_get_translated($node['name']),$setnames)) {?>checked<?php } ?> <?php if ($autoupdate) { ?>onClick="UpdateResultCount();"<?php } ?>>
                            </td>
                            <td valign=middle>
                                <?php echo htmlspecialchars(i18n_get_translated($node['name'])); ?>&nbsp;&nbsp;
                            </td>
                            <?php
                            }
                        }
                        ?>
                        </tr>
                    </table>
                    <?php
                    }
                    
                }
            }
        break;
        
        case FIELD_TYPE_DATE_AND_OPTIONAL_TIME:
        case FIELD_TYPE_EXPIRY_DATE: 
        case FIELD_TYPE_DATE: 
        case FIELD_TYPE_DATE_RANGE: 
        $found_year='';$found_month='';$found_day='';$found_start_year='';$found_start_month='';$found_start_day='';$found_end_year='';$found_end_month='';$found_end_day='';
        if (!$forsearchbar && $daterange_search)
            {
			render_date_range_field($name,$value,true, $autoupdate);
            }
        else
            {
            $s=explode("|",$value);
            if (count($s)>=3)
            {
            $found_year=$s[0];
            $found_month=$s[1];
            $found_day=$s[2];
            }
            ?>      
            <select name="<?php echo $name?>_year" id="<?php echo $id?>_year" class="SearchWidth" style="width:100px;" <?php if ($autoupdate) { ?>onChange="UpdateResultCount();"<?php } ?>>
              <option value=""><?php echo $lang["anyyear"]?></option>
              <?php
              $y=date("Y");
              for ($d=$minyear;$d<=$y;$d++)
                {
                ?><option <?php if ($d==$found_year) { ?>selected<?php } ?>><?php echo $d?></option><?php
                }
              ?>
            </select>
            
            <?php if ($forsearchbar && $searchbyday) { ?><br /><?php } ?>
            
            <select name="<?php echo $name?>_month" id="<?php echo $id?>_month" class="SearchWidth" style="width:100px;" <?php if ($autoupdate) { ?>onChange="UpdateResultCount();"<?php } ?>>
              <option value=""><?php echo $lang["anymonth"]?></option>
              <?php
              for ($d=1;$d<=12;$d++)
                {
                $m=str_pad($d,2,"0",STR_PAD_LEFT);
                ?><option <?php if ($d==$found_month) { ?>selected<?php } ?> value="<?php echo $m?>"><?php echo $lang["months"][$d-1]?></option><?php
                }
              ?>
            </select>
            
            <?php if (!$forsearchbar || ($forsearchbar && $searchbyday)) 
            	{ 
            	?>
				<select name="<?php echo $name?>_day" id="<?php echo $id?>_day" class="SearchWidth" style="width:100px;" <?php if ($autoupdate) { ?>onChange="UpdateResultCount();"<?php } ?>>
				  <option value=""><?php echo $lang["anyday"]?></option>
				  <?php
				  for ($d=1;$d<=31;$d++)
					{
					$m=str_pad($d,2,"0",STR_PAD_LEFT);
					?><option <?php if ($d==$found_day) { ?>selected<?php } ?> value="<?php echo $m?>"><?php echo $m?></option><?php
					}
				  ?>
				</select>
            	<?php 
            	}
            if($forsearchbar)
            	{
            	# Add to the clear function so clicking 'clear' clears this box.
				$clear_function.="
					document.getElementById('field_" . $field["ref"] . "_year').selectedIndex=0;
					document.getElementById('field_" . $field["ref"] . "_month').selectedIndex=0;
					";
				if($searchbyday)
					{
					$clear_function.="document.getElementById('field_" . $field["ref"] . "_day').selectedIndex=0;";
					}
				}
            }
                    
        break;
        
        
        case FIELD_TYPE_CATEGORY_TREE:
        global $category_tree_add_parents, $category_tree_search_use_and;

        $set  = preg_split('/[;\|]/', cleanse_string($value, true));
        $name = "nodes_searched[{$field['ref']}][]";

        /*
        For search, category trees work slightly different than the intended behaviour shown in edit_fields/7.php:
        Intended behaviour:
        1. Selecting a sub (child) node will automatically select all parent nodes up to and including the root level,
        unless the option $category_tree_add_parents is set to false

        On search this should work like this:
        Selecting a sub (child) node will NOT select all parent nodes unless the system is configured to search using AND
        */
        $category_tree_add_parents = $category_tree_search_use_and;

        if($forsearchbar)
            {
            $original_category_tree_open = $category_tree_open;
            $category_tree_open          = true;
            $treeonly                    = true;
            $status_box_elements         = '';

            foreach($field['nodes'] as $node)
                {
                if(!in_array($node['ref'], $searched_nodes))
                    {
                    continue;
                    }

                // Show previously searched options on the status box
                $status_box_elements .= "<span id=\"nodes_searched_{$field['ref']}_statusbox_option_{$node['ref']}\">{$node['name']}</span><br />";
                }
            ?>
			<div id="field_<?php echo htmlspecialchars($field['name']); ?>">
    			<div id="nodes_searched_<?php echo $field['ref']; ?>_statusbox" class="MiniCategoryBox">
                    <?php echo $status_box_elements; ?>
                </div>
                <div id="cattree_<?php echo $fields[$n]['name']; ?>" class="RecordPanel PopupCategoryTree">
                    <p align="right">
                        <a href="#" onClick="document.getElementById('cattree_<?php echo $field['name']; ?>').style.display='none'; return false;"><?php echo $lang['close']; ?></a>
                    </p>
                    <?php
                    include __DIR__ . '/../pages/edit_fields/7.php';

                    // Reset category_tree_open because normally searchbar occurs before edit/ advanced search page
                    $category_tree_open = $original_category_tree_open;
                    ?>
                 </div>
                <a href="#"
                   onClick="
                        jQuery('#cattree_<?php echo $field['name']; ?>').css('top', (jQuery(this).position().top) - 200);
                        jQuery('#cattree_<?php echo $field['name']; ?>').css('left', (jQuery(this).position().left) - 400);
                        jQuery('#cattree_<?php echo $field['name']; ?>').show();
                        jQuery('#cattree_<?php echo $field['name']; ?>').draggable();
                        return false;"><?php echo $lang['select']; ?></a>
            </div>
			<?php
			# Add to clear function
			$clear_function .= "
                    jQuery('#search_tree_{$field['ref']}').jstree(true).deselect_all();

                    /* remove the hidden inputs */
                    var elements = document.getElementsByName('nodes_searched[{$field['ref']}][]');
                    while(elements[0])
                        {
                        elements[0].parentNode.removeChild(elements[0]);
                        }

                    /* update status box */
                    var node_statusbox = document.getElementById('nodes_searched_{$field['ref']}_statusbox');
                    while(node_statusbox.lastChild)
                        {
                        node_statusbox.removeChild(node_statusbox.lastChild);
                        }
                ";
            }
        else
            {
            # For advanced search and elsewhere, include the category tree.
            include __DIR__ . "/../pages/edit_fields/7.php";
            }
        break;
        
        // Dynamic keywords list
        case FIELD_TYPE_DYNAMIC_KEYWORDS_LIST:
            include __DIR__ . '/../pages/edit_fields/9.php';
        break;      

        // Radio buttons:
        case FIELD_TYPE_RADIO_BUTTONS:
            // auto save is not needed when searching
            $edit_autosave           = false;
            $display_as_radiobuttons = false;
            $display_as_checkbox     = true;
            $name                    = "nodes_searched[{$field['ref']}][]";

            if($forsearchbar || $field['display_as_dropdown'])
                {
                $display_as_dropdown = true;
                $display_as_checkbox = false;
                $name                = "nodes_searched[{$field['ref']}]";

                $clear_function .= "document.getElementsByName('{$name}')[0].selectedIndex = -1;";
                }
            
            include __DIR__ . '/../pages/edit_fields/12.php';
            // need to adjust the field's name value
            ?>
        	<script type="text/javascript">
        		jQuery("#field_<?php echo $field['ref']?>").attr('name', 'field_<?php echo $field["name"]?>');
        	</script>
            <?php
        break;
        }
    ?>
    <div class="clearerleft"> </div>
    </div>
    <?php
    }

/**
* Renders sort order functionality as a dropdown box
*
*/
if (!function_exists("render_sort_order")){
function render_sort_order(array $order_fields)
    {
    global $order_by, $baseurl_short, $lang, $search, $archive, $restypes, $k, $sort, $date_field;

    // use query strings here as this is used to render elements and sometimes it
    // can depend on other params
    $modal  = ('true' == getval('modal', ''));
    ?>
    <select id="sort_order_selection">
    
    <?php
    $options = '';
    foreach($order_fields as $name => $label)
        {
        // date shows as 'field'.$date_field rather than 'date' for collection searches so let's fix it
        if($name=='field'.$date_field)
			{
			$name='date';
			}
		
        $fixed_order = $name == 'relevance';
        $selected    = ($order_by == $name || ($name=='date' && $order_by=='field'.$date_field));
		
        // Build the option:
        $option = '<option value="' . $name . '"';

        if(($selected && $fixed_order) || $selected)
            {
            $option .= ' selected';
            }

        $option .= sprintf('
                data-url="%spages/search.php?search=%s&amp;order_by=%s&amp;archive=%s&amp;k=%s&amp;restypes=%s"
            ',
            $baseurl_short,
            urlencode($search),
            $name,
            urlencode($archive),
            urlencode($k),
            urlencode($restypes)
        );

        $option .= '>';
        $option .= $label;
        $option .= '</option>';

        // Add option to the options list
        $options .= $option;
        }

        hook('render_sort_order_add_option', '', array($options));
        echo $options;
    ?>
    
    </select>
    <select id="sort_selection">
        <option value="ASC" <?php if($sort == 'ASC') {echo 'selected';} ?>><?php echo $lang['sortorder-asc']; ?></option>
        <option value="DESC" <?php if($sort == 'DESC') {echo 'selected';} ?>><?php echo $lang['sortorder-desc']; ?></option>
    </select>
    &nbsp;<i class="fa fa-sort-amount-<?php echo strtolower(safe_file_name($sort)) ?>"></i>

    <script>
    function updateCollectionActions(order_by,sort_direction){
    	jQuery("#CollectionDiv .ActionsContainer select option").each(function(){
    		dataURL = jQuery(this).data("url");
    		if(typeof dataURL!=='undefined'){
    			dataURLVars = dataURL.split('&');
    			
    			replace_needed=false;
    			
    			for (i = 0; i < dataURLVars.length; i++) {
        			dataURLParameterName = dataURLVars[i].split('=');
	
   	     			if (dataURLParameterName[0] === 'order_by') {
   	        			dataURLVars[i] = dataURLParameterName[0]+'='+order_by;
   	        			replace_needed=true;
   		     		}
       		 		else if (dataURLParameterName[0] === 'sort') {
      	     			dataURLVars[i] = dataURLParameterName[0]+'='+sort_direction;
      	     			replace_needed=true;
      		  		}
     		   	}
   		     	if(replace_needed){
   		     		newDataURL=dataURLVars.join("&");
    				jQuery(this).attr("data-url", newDataURL);
    			}
    		}
    	});
    }
    
    jQuery('#sort_order_selection').change(function() {
        var selected_option      = jQuery('#sort_order_selection option[value="' + this.value + '"]');
        var selected_sort_option = jQuery('#sort_selection option:selected').val();
        var option_url           = selected_option.data('url');

        option_url += '&sort=' + selected_sort_option;

         <?php echo $modal ? 'Modal' : 'CentralSpace'; ?>Load(option_url);
        updateCollectionActions(selected_option.val(), selected_sort_option);

        // Update collection
        var query_strings = getQueryStrings();
        if(is_special_search('!collection', 11) && !is_empty(query_strings) && query_strings.search.substring(11) == usercollection)
            {
            /*Because we are looking at the same collection in both CentralSpace and CollectionDiv,
            make sure to keep both sections in sync*/
            CollectionDivLoad(baseurl_short + 'pages/collections.php?collection=' + usercollection + '&k=<?php echo htmlspecialchars($k); ?>' + '&order_by=' + selected_option.val() + '&sort=' + selected_sort_option);
            }
    });

    jQuery('#sort_selection').change(function() {
        var selected_option                = this.value;
        var selected_sort_order_option     = jQuery('#sort_order_selection option:selected');
        var selected_sort_order_option_url = selected_sort_order_option.data('url');

        selected_sort_order_option_url += '&sort=' + selected_option;

        <?php echo $modal ? 'Modal' : 'CentralSpace'; ?>Load(selected_sort_order_option_url);
        updateCollectionActions(selected_sort_order_option.val(), selected_option);

        // Update collection
        var query_strings = getQueryStrings();
        if(is_special_search('!collection', 11) && !is_empty(query_strings) && query_strings.search.substring(11) == usercollection)
            {
            /*Because we are looking at the same collection in both CentralSpace and CollectionDiv,
            make sure to keep both sections in sync*/
            CollectionDivLoad(baseurl_short + 'pages/collections.php?collection=' + usercollection + '&k=<?php echo htmlspecialchars($k); ?>' + '&order_by=' + selected_sort_order_option.val() + '&sort=' + selected_option);
            }
    });
    </script>
    <?php
    return;
    }
}
/**
* Renders a dropdown option
* 
*/
if (!function_exists("render_dropdown_option")){
function render_dropdown_option($value, $label, array $data_attr = array(), $extra_tag_attributes  = '')
    {
    $result = '<option value="' . $value . '"';

    // Add any extra tag attributes
    if(trim($extra_tag_attributes) !== '')
        {
        $result .= ' ' . $extra_tag_attributes;
        }

    // Add any data attributes you may need
    foreach($data_attr as $data_attr_key => $data_attr_value)
        {
        $data_attr_key = str_replace(' ', '_', $data_attr_key);

        $result .= ' data-' . $data_attr_key . '="' . $data_attr_value . '"';
        }

    $result .= '>' . $label . '</option>';

    return $result;
    }
}

/**
* Renders search actions functionality as a dropdown box
* 
*/
if (!function_exists("render_actions")){
function render_actions(array $collection_data, $top_actions = true, $two_line = true, $id = '',$resource_data=array(),$optionsonly=false, $forpage="")
    {
    if(hook('prevent_running_render_actions'))
        {
        return;
        }

    global $baseurl, $lang, $k, $pagename, $order_by, $sort, $chosen_dropdowns, $allow_resource_deletion;

    
    // globals that could also be passed as a reference
    global $result /*search result*/;

    $action_selection_id = ($forpage!=""?$forpage:$pagename) . '_action_selection' . $id;
    if(!$top_actions)
        {
        $action_selection_id .= '_bottom';
        }
    if(isset($collection_data['ref']))
        {
        $action_selection_id .= '_' . str_replace("-","_",$collection_data['ref']);
        }
        
        
    if(!$optionsonly)
            {?>
    
            <div class="ActionsContainer  <?php if($top_actions) { echo 'InpageNavLeftBlock'; } ?>">
                <?php
        
                if($two_line)
                    {
                    ?>
                    <br />
                    <?php
                    }
                    ?>
                <select onchange="action_onchange_<?php echo $action_selection_id; ?>(this.value);" id="<?php echo $action_selection_id; ?>" <?php if(!$top_actions) { echo 'class="SearchWidth"'; } ?>>
            <?php } ?>
            <option class="SelectAction" value=""><?php echo $lang["actions-select"]?></option>
            <?php

            // Collection Actions
            $collection_actions_array = compile_collection_actions($collection_data, $top_actions, $resource_data);

            // Usual search actions
            $search_actions_array = compile_search_actions($top_actions);

            // Remove certain actions that apply only to searches
            if(!$top_actions)
                {
                $action_index_to_remove = array_search('search_items_disk_usage', array_column($search_actions_array, 'value'));
                unset($search_actions_array[$action_index_to_remove]);
                $search_actions_array = array_values($search_actions_array);
				
				$action_index_to_remove = array_search('save_search_items_to_collection', array_column($search_actions_array, 'value'));
                unset($search_actions_array[$action_index_to_remove]);
				$search_actions_array = array_values($search_actions_array);
                }
    
            $actions_array = array_merge($collection_actions_array, $search_actions_array);
            
            $modify_actions_array = hook('modify_unified_dropdown_actions_options', '', array($actions_array,$top_actions));

	if(!empty($modify_actions_array))
                {
                $actions_array = $modify_actions_array;
                }

            // loop and display
			$options='';
			for($a = 0; $a < count($actions_array); $a++)
				{
				if(!isset($actions_array[$a]['data_attr']))
					{
					$actions_array[$a]['data_attr'] = array();
					}

				if(!isset($actions_array[$a]['extra_tag_attributes']))
					{
					$actions_array[$a]['extra_tag_attributes'] = '';
					}

				$options .= render_dropdown_option($actions_array[$a]['value'], $actions_array[$a]['label'], $actions_array[$a]['data_attr'], $actions_array[$a]['extra_tag_attributes']);

				$add_to_options = hook('after_render_dropdown_option', '', array($actions_array, $a));
				if($add_to_options != '')
					{
					$options .= $add_to_options;
					}
				}

			echo $options;
            
            if(!$optionsonly)
                { ?>
                </select>
                <?php } ?>
        <script>
        function action_onchange_<?php echo $action_selection_id; ?>(v)
            {
            if(v == '')
                {
                return false;
                }

            switch(v)
                {
            <?php
            if(0 !== count($collection_data) && collection_readable($collection_data['ref']))
                {
                ?>
                case 'select_collection':
                    ChangeCollection(<?php echo $collection_data['ref']; ?>, '');
                    break;

                case 'remove_collection':
                    if(confirm("<?php echo $lang['removecollectionareyousure']; ?>")) {
                        // most likely will need to be done the same way as delete_collection
                        document.getElementById('collectionremove').value = '<?php echo urlencode($collection_data["ref"]); ?>';
                        document.getElementById('collectionform').submit();
                    }
                    break;

                case 'purge_collection':
                    if(confirm('<?php echo $lang["purgecollectionareyousure"]; ?>'))
                        {
                        document.getElementById('collectionpurge').value='".urlencode($collections[$n]["ref"])."';
                        document.getElementById('collectionform').submit();
                        }
                    break;
                <?php
                }

            if(!$top_actions || !empty($collection_data))
                {
                global $search;
                $search_collection='';
                if(substr($search,0,11)=='!collection'){
                	$search_collection=substr($search,11);
                }
                ?>
                case 'delete_collection':
                    if(confirm('<?php echo $lang["collectiondeleteconfirm"]; ?>')) {
                        var post_data = {
                            ajax: true,
                            dropdown_actions: true,
                            delete: <?php echo urlencode($collection_data['ref']); ?> 
                        };

                        jQuery.post('<?php echo $baseurl; ?>/pages/collection_manage.php', post_data, function(response) {
                            if(response.success === 'Yes')
                                {
                                CollectionDivLoad('<?php echo $baseurl; ?>/pages/collections.php?collection=' + response.redirect_to_collection + '&k=' + response.k + '&nc=' + response.nc);

                                if(basename(document.URL).substr(0, 17) === 'collection_manage')
                                    {
                                    CentralSpaceLoad(document.URL);
                                    }
                                else if(basename(document.URL).substr(0, 6) === 'search' && '<?php echo $search_collection?>'=='<?php echo $collection_data["ref"]?>')
                                    {
                                    CentralSpaceLoad('<?php echo $baseurl; ?>/pages/search.php?search=!collection' + response.redirect_to_collection, true);
                                    }
                                }
                        }, 'json');    
                    }
                    break;
                <?php
                }

            // Add extra collection actions javascript case through plugins
            // Note: if you are just going to a different page, it should be easily picked by the default case
            $extra_options_js_case = hook('render_actions_add_option_js_case');
            if(trim($extra_options_js_case) !== '')
                {
                echo $extra_options_js_case;
                }
            ?>

                case 'save_search_to_collection':
                    var option_url = jQuery('#<?php echo $action_selection_id; ?> option:selected').data('url');
                    CollectionDivLoad(option_url);
                    break;

                case 'save_search_to_dash':
                    var option_url  = jQuery('#<?php echo $action_selection_id; ?> option:selected').data('url');
                    var option_link = jQuery('#<?php echo $action_selection_id; ?> option:selected').data('link');
                    
                    // Dash requires to have some search parameters (even if they are the default ones)
                    if((basename(option_link).substr(0, 10)) != 'search.php')
                        {
                        option_link = (window.location.href).replace(window.baseurl, '');
                        }

                    option_url    += '&link=' + option_link;

                    CentralSpaceLoad(option_url);
                    break;

                case 'save_search_smart_collection':
                    var option_url = jQuery('#<?php echo $action_selection_id; ?> option:selected').data('url');
                    CollectionDivLoad(option_url);
                    break;

                case 'save_search_items_to_collection':
                    var option_url = jQuery('#<?php echo $action_selection_id; ?> option:selected').data('url');
                    CollectionDivLoad(option_url);
                    break;

                case 'empty_collection':
                    if(!confirm('<?php echo $lang["emptycollectionareyousure"]; ?>'))
                        {
                        break;
                        }

                    var option_url = jQuery('#<?php echo $action_selection_id; ?> option:selected').data('url');
                    CollectionDivLoad(option_url);
                    break;

            <?php
            if(!$top_actions)
                {
                if($allow_resource_deletion)
                    {
                    ?>
                    case 'delete_all_in_collection':
                        if(confirm('<?php echo $lang["deleteallsure"]; ?>'))
                            {
                            var post_data = {
                                submitted: true,
                                ref: '<?php echo $collection_data["ref"]; ?>',
                                name: <?php echo json_encode($collection_data["name"]); ?>,
                                public: '<?php echo $collection_data["public"]; ?>',
                                deleteall: 'on'
                            };

                            jQuery.post('<?php echo $baseurl; ?>/pages/collection_edit.php?ajax=true', post_data, function()
                                {
                                CollectionDivLoad('<?php echo $baseurl; ?>/pages/collections.php?collection=<?php echo $collection_data["ref"] ?>');
                                });
                            }
                        break;
                    <?php
                    }
                    ?>

					case 'hide_collection':
						var action = 'hidecollection';
						var collection = <?php echo urlencode($collection_data['ref']);?>;
						var mycol = jQuery('#<?php echo $action_selection_id; ?> option:selected').data('mycol');
						
						jQuery.ajax({
							type: 'POST',
							url: baseurl_short + 'pages/ajax/showhide_collection.php?action=' + action + '&collection=' + collection,
							success: function(data) {
								if (data.trim() == "HIDDEN") {
									CollectionDivLoad('<?php echo $baseurl; ?>/pages/collections.php?collection='+mycol);
								}
							},
							error: function (err) {
								console.log("AJAX error : " + JSON.stringify(err, null, 2));
							}
						}); 
						break;
                <?php
                }
                ?>

                case 'csv_export_results_metadata':
                    var option_url = jQuery('#<?php echo $action_selection_id; ?> option:selected').data('url');
                    window.location.href = option_url;
                    break;

                default:
                    var option_url = jQuery('#<?php echo $action_selection_id; ?> option:selected').data('url');
                    CentralSpaceLoad(option_url, true);
                    break;
                }
				
                // Go back to no action option
                jQuery('#<?php echo $action_selection_id; ?> option[value=""]').prop('selected', true);
                <?php
                if($chosen_dropdowns)
                	{
                	?>
                	jQuery('#<?php echo $action_selection_id; ?>').trigger('chosen:updated');
                	<?php
                	}
                ?>

        }
        </script>
        
    <?php if (!$optionsonly)
        {?>
        </div>
        <?php
        }
    return;
    }
}


/**
* @param string $name
* @param array  $current  Current selected values (eg. array(1, 3) for Admins and Super admins user groups selected)
* @param int    $size     How many options to show before user has to scroll
*/
function render_user_group_multi_select($name, array $current = array(), $size = 10, $style = '')
    {
    ?>
    <select id="<?php echo $name; ?>" name="<?php echo $name; ?>[]" multiple="multiple" size="<?php echo $size; ?>" style="<?php echo $style; ?>">
    <?php
    foreach(get_usergroups() as $usergroup)
        {
        ?>
        <option value="<?php echo $usergroup['ref']; ?>"<?php echo (in_array($usergroup['ref'], $current) ? ' selected' : ''); ?>><?php echo $usergroup['name']; ?></option>
        <?php
        }
        ?>
    </select>
    <?php
    }


/**
* @param string  $name
* @param integer $current  Current selected value. Use user group ID
*/
function render_user_group_select($name, $current = null, $style = '')
    {
    ?>
    <select id="<?php echo $name; ?>" name="<?php echo $name; ?>" style="<?php echo $style; ?>">
    <?php
    foreach(get_usergroups() as $usergroup)
        {
        ?>
        <option value="<?php echo $usergroup['ref']; ?>"<?php echo ((!is_null($current) && $usergroup['ref'] == $current) ? ' selected' : ''); ?>><?php echo $usergroup['name']; ?></option>
        <?php
        }
        ?>
    </select>
    <?php
    }


/**
* Renders a list of user groups
* 
* @param string $name
* @param array  $current  Current selected values (eg. array(1, 3) for Admins and Super admins user groups selected)
* @param string $style    CSS styling that will apply to the outer container (ie. table element)
*
* @return void
*/
function render_user_group_checkbox_select($name, array $current = array(), $style = '')
    {
    ?>
    <table id="<?php echo $name; ?>"<?php if('' !== $style) { ?>style="<?php echo $style; ?>"<?php } ?>>
        <tbody>
    <?php
    foreach(get_usergroups() as $group)
        {
        ?>
        <tr>
            <td><input id="<?php echo $name . '_' . $group['ref']; ?>" type="checkbox" name="<?php echo $name; ?>[]" value="<?php echo $group['ref']; ?>"<?php if(in_array($group['ref'], $current)) { ?> checked<?php } ?> /></td>
            <td><label for="<?php echo $name . '_' . $group['ref']; ?>"><?php echo $group['name']; ?></label></td>
        </tr>
        <?php
        }
        ?>
        </tbody>
    </table>
    <?php

    return;
    }

/**
* render_text_input_question - Used to display a question with simple text input
* 
* @param string $label						Label of question
* @param string $input  					Name of input field
* @param string $additionaltext (optional) 	Text to to display after input
* @param boolean $numeric 					Set to true to force numeric input
*/
function render_text_question($label, $input, $additionaltext="", $numeric=false, $extra="", $current="")
    {
	?>
	<div class="Question" id = "pixelwidth">
		<label><?php echo $label; ?></label>
		<div>
		<?php
		echo "<input name=\"" . $input . "\" type=\"text\" ". ($numeric?"numericinput":"") . "\" value=\"" . $current . "\"" . $extra . "/>\n";
			
		echo $additionaltext;
		?>
		</div>
	</div>
	<div class="clearerleft"> </div>
	<?php
	}
	
/**
* render_split_text_question - Used to display a question with two inputs e.g. for a from/to range
* 
* @param string $label	Label of question
* @param array  $inputs  Array of input names and labels(eg. array('pixelwidthmin'=>'From','pixelwidthmin'=>'To')
* @param string $additionaltext (optional) 	Text to to display after input
* @param boolean $numeric 					Set to true to force numeric input
*/
function render_split_text_question($label, $inputs = array(), $additionaltext="", $numeric=false, $extra="", $currentvals=array())
    {
	?>
	<div class="Question" id = "pixelwidth">
		<label><?php echo $label; ?></label>
		<div>
		<?php
		foreach ($inputs as $inputname=>$inputtext)
			{
			echo "<div class=\"SplitSearch\">" . $inputtext . "</div>\n";
			echo "<input name=\"" . $inputname . "\" class=\"SplitSearch\" type=\"text\"". ($numeric?"numericinput":"") . "\" value=\"" . $currentvals[$inputname] . "\"" . $extra . " />\n";
			}
		echo $additionaltext;
		?>
		</div>
	</div>
	<div class="clearerleft"> </div>
	<?php
	}

/**
* render_dropdown_question - Used to display a question with a dropdown selector
* 
* @param string $label	Label of question
* @param string $input  name of input field
* @param array  $options  Array of options (value and text pairs) (eg. array('pixelwidthmin'=>'From','pixelwidthmin'=>'To')
*/
function render_dropdown_question($label, $inputname, $options = array(), $current="", $extra="")
    {
	?>
	<div class="Question" id = "pixelwidth">
		<label><?php echo $label; ?></label>
		<select  name="<?php echo $inputname?>" id="<?php echo $inputname?>" <?php echo $extra; ?>>
		<?php
		foreach ($options as $optionvalue=>$optiontext)
			{
			?>
			<option value="<?php echo htmlspecialchars(trim($optionvalue))?>" <?php if (trim($optionvalue)==trim($current)) {?>selected<?php } ?>><?php echo htmlspecialchars(trim($optiontext))?></option>
			<?php
			}
		?>
		</select>

	</div>
	<div class="clearerleft"> </div>
	<?php
	}

/**
* Render a table row (tr) for a single access key
* 
* @param array $record Access key record details
* 
* @return void
*/
function render_access_key_tr(array $record)
    {
    global $baseurl, $baseurl_short, $lang;
    $link      = '';
    $type      = '';
    $edit_link = '';

    // Set variable dependent on type (ie. Resource / Collection)
    if('' == $record['collection'] && '' != $record['resource'])
        {
        // For resource
        $link      = $baseurl . '?r=' . urlencode($record['resource']) . '&k=' . urlencode($record['access_key']);
        $type      = $lang['share-resource'];
        $edit_link = sprintf('%spages/resource_share.php?ref=%s&editaccess=%s&editexpiration=%s&editaccesslevel=%s&editgroup=',
            $baseurl_short,
            urlencode($record['resource']),
            urlencode($record['access_key']),
            urlencode($record['expires']),
            urlencode($record['access']),
            urlencode($record['usergroup'])
        );
        }
    else
        {
        // For collection
        $link      = $baseurl . '?c=' . urlencode($record['collection']) . '&k=' . urlencode($record['access_key']);
        $type      = $lang['sharecollection'];
        $edit_link = sprintf('%spages/collection_share.php?ref=%s&editaccess=%s&editexpiration=%s&editaccesslevel=%s&editgroup=',
            $baseurl_short,
            urlencode($record['collection']),
            urlencode($record['access_key']),
            urlencode($record['expires']),
            urlencode($record['access']),
            urlencode($record['usergroup'])
        );
        }
        ?>


    <tr id="access_key_<?php echo $record['access_key']; ?>">
        <td>
            <div class="ListTitle">
                <a href="<?php echo $link; ?>" target="_blank"><?php echo htmlspecialchars($record['access_key']); ?></a>
            </div>
        </td>
        <td><?php echo htmlspecialchars($type); ?></td>
        <td><?php echo htmlspecialchars(resolve_users($record['users'])); ?></td>
        <td><?php echo htmlspecialchars($record['emails']); ?></td>
        <td><?php echo htmlspecialchars(nicedate($record['maxdate'], true)); ?></td>
        <td><?php echo htmlspecialchars(nicedate($record['lastused'], true)); ?></td>
        <td><?php echo htmlspecialchars(('' == $record['expires']) ? $lang['never'] : nicedate($record['expires'], false)); ?></td>
        <td><?php echo htmlspecialchars((-1 == $record['access']) ? '' : $lang['access' . $record['access']]); ?></td>
        <td>
            <div class="ListTools">
                <a href="#" onClick="delete_access_key('<?php echo $record['access_key']; ?>', '<?php echo $record['resource']; ?>', '<?php echo $record['collection']; ?>');"><?php echo LINK_CARET ?><?php echo $lang['action-delete']; ?></a>
                <a href="<?php echo $edit_link; ?>"><?php echo LINK_CARET ?><?php echo $lang['action-edit']; ?></a>
            </div>
        </td>
    </tr>
    <?php

    return;
    }

# The functions is_field_displayed, display_multilingual_text_field and display_field below moved from edit.php
function is_field_displayed($field)
    {
    global $ref, $resource, $upload_review_mode;

    # Field is an archive only field
    return !(($resource["archive"]==0 && $field["resource_type"]==999)
        # Field has write access denied
        || (checkperm("F*") && !checkperm("F-" . $field["ref"])
        && !($ref < 0 && checkperm("P" . $field["ref"])))
        || checkperm("F" . $field["ref"])
        # Upload only field
        || (($ref < 0 || $upload_review_mode) && $field["hide_when_uploading"] && $field["required"]==0)
        || hook('edithidefield', '', array('field' => $field))
        || hook('edithidefield2', '', array('field' => $field)));
    }

# Allows language alternatives to be entered for free text metadata fields.
function display_multilingual_text_field($n, $field, $translations)
  {
  global $language, $languages, $lang;
  ?>
  <p><a href="#" class="OptionToggle" onClick="l=document.getElementById('LanguageEntry_<?php echo $n?>');if (l.style.display=='block') {l.style.display='none';this.innerHTML='<?php echo $lang["showtranslations"]?>';} else {l.style.display='block';this.innerHTML='<?php echo $lang["hidetranslations"]?>';} return false;"><?php echo $lang["showtranslations"]?></a></p>
  <table class="OptionTable" style="display:none;" id="LanguageEntry_<?php echo $n?>">
     <?php
     reset($languages);
     foreach ($languages as $langkey => $langname)
     {
       if ($language!=$langkey)
       {
         if (array_key_exists($langkey,$translations)) {$transval=$translations[$langkey];} else {$transval="";}
         ?>
         <tr>
            <td nowrap valign="top"><?php echo htmlspecialchars($langname)?>&nbsp;&nbsp;</td>

            <?php
            if ($field["type"]==0)
            {
              ?>
              <td><input type="text" class="stdwidth" name="multilingual_<?php echo $n?>_<?php echo $langkey?>" value="<?php echo htmlspecialchars($transval)?>"></td>
              <?php
           }
           else
           {
              ?>
              <td><textarea rows=6 cols=50 name="multilingual_<?php echo $n?>_<?php echo $langkey?>"><?php echo htmlspecialchars($transval)?></textarea></td>
              <?php
           }
           ?>
        </tr>
        <?php
     }
  }
  ?></table><?php
  }

function display_field($n, $field, $newtab=false,$modal=false)
  {
  global $use, $ref, $original_fields, $multilingual_text_fields, $multiple, $lastrt,$is_template, $language, $lang,
  $blank_edit_template, $edit_autosave, $errors, $tabs_on_edit, $collapsible_sections, $ctrls_to_save,
  $embedded_data_user_select, $embedded_data_user_select_fields, $show_error, $save_errors, $baseurl, $is_search,
  $all_selected_nodes,$original_nodes, $FIXED_LIST_FIELD_TYPES, $TEXT_FIELD_TYPES, $upload_review_mode, $check_edit_checksums, $upload_review_lock_metadata, $locked_fields;

  // Set $is_search to false in case page request is not an ajax load and $is_search hs been set from the searchbar
  $is_search=false;
  
  if(!isset($locked_fields))
    {
    $locked_fields = explode(",",getval("lockedfields",""));
    }
  
  $name="field_" . $field["ref"];
  $value=$field["value"];
  $value=trim($value);

  if (($field["omit_when_copying"] || strip_leading_comma($value) == "") && $use!=$ref)
    {
    # Omit when copying, or there is no data for this field associated with the copied resource - return this field back to the value it was originally, instead of using the current value which has been fetched from the copied resource.
    reset($original_fields);
    foreach ($original_fields as $original_field)
      {
      if ($original_field["ref"]==$field["ref"])
        {
        $value=$original_field["value"];
        }
      }
    $selected_nodes = $original_nodes;
    }
  else
    {
    $selected_nodes = $all_selected_nodes;
    }
    
  $displaycondition=true;
  if ($field["display_condition"]!="")
    {
    #Check if field has a display condition set
    $displaycondition=check_display_condition($n,$field);
    }

  if ($multilingual_text_fields)
    {
    # Multilingual text fields - find all translations and display the translation for the current language.
    $translations=i18n_get_translations($value);
    if (array_key_exists($language,$translations)) {$value=$translations[$language];} else {$value="";}
    }

  if ($multiple && ((getval("copyfrom","") == "" && getval('metadatatemplate', '') == "") || str_replace(array(" ",","),"",$value)=="")) {$value="";} # Blank the value for multi-edits  unless copying data from resource.

  if ($field["resource_type"]!=$lastrt && $lastrt!=-1 && $collapsible_sections)
      {
      ?></div><h2 class="CollapsibleSectionHead" id="resource_type_properties"><?php echo htmlspecialchars(get_resource_type_name($field["resource_type"]))?> <?php echo $lang["properties"]?></h2><div class="CollapsibleSection" id="ResourceProperties<?php if ($ref==-1) echo "Upload"; ?><?php echo $field["resource_type"]; ?>Section"><?php
      }
    $lastrt=$field["resource_type"];

    # Blank form if 'reset form' has been clicked
    if('' != getval('resetform', ''))
        {
        $value = '';

        if(in_array($field['type'], $FIXED_LIST_FIELD_TYPES))
            {
            $selected_nodes = array();
            }
        }

    /****************************** Errors on saving ***************************************/
    $field_save_error = FALSE;
    if (isset($show_error) && isset($save_errors))
      {
      if(array_key_exists($field['ref'], $save_errors))
        {
        $field_save_error = TRUE;
        }
      }
     
    if ($multiple && !hook("replace_edit_all_checkbox","",array($field["ref"])))
      {
      # Multiple items, a toggle checkbox appears which activates the question
      ?>
      <div class="Question edit_multi_checkbox"><input name="editthis_<?php echo htmlspecialchars($name) ?>" id="editthis_<?php echo $n?>" type="checkbox" value="yes"<?php if($field_save_error){?> checked<?php }?> onClick="var q=document.getElementById('question_<?php echo $n?>');var m=document.getElementById('modeselect_<?php echo $n?>');var f=document.getElementById('findreplace_<?php echo $n?>');if (this.checked) {q.style.display='block';m.style.display='block';} else {q.style.display='none';m.style.display='none';f.style.display='none';document.getElementById('modeselectinput_<?php echo $n?>').selectedIndex=0;}" <?php if(getval("copyfrom","")!="" && $value!=""){echo " checked" ;} ?>>&nbsp;<label for="editthis<?php echo $n?>"><?php echo htmlspecialchars($field["title"]) ?></label></div><div class="clearerleft"> </div><!-- End of edit_multi_checkbox -->
      <?php
      }
      
  if ($multiple && !hook("replace_edit_all_mode_select","",array($field["ref"])))
      {
      # When editing multiple, give option to select Replace All Text or Find and Replace
      ?>
      <div class="Question" id="modeselect_<?php echo $n?>" style="<?php if($value=="" && !$field_save_error ){echo "display:none;";} ?>padding-bottom:0;margin-bottom:0;">
      <label for="modeselectinput"><?php echo $lang["editmode"]?></label>
      <select id="modeselectinput_<?php echo $n?>" name="modeselect_<?php echo $field["ref"]?>" class="stdwidth" onChange="var fr=document.getElementById('findreplace_<?php echo $n?>');var q=document.getElementById('question_<?php echo $n?>');<?php if ($field["type"]==7){?>if (this.value=='RM'){branch_limit_field['field_<?php echo $field["ref"]?>']=1;}else{branch_limit_field['field_<?php echo $field["ref"]?>']=0;}<?php } ?>if (this.value=='FR') {fr.style.display='block';q.style.display='none';} else {fr.style.display='none';q.style.display='block';}<?php hook ("edit_all_mode_js"); ?>">
      <option value="RT"><?php echo $lang["replacealltext"]?></option>
      <?php
      if (in_array($field["type"], $TEXT_FIELD_TYPES ))
        {
        # Find and replace applies to text boxes only.
        ?>
        <option value="FR" <?php if(getval("modeselect_" . $field["ref"],"")=="FR"){?> selected<?php } ?>><?php echo $lang["findandreplace"]?></option>
        <?php
        }
      if (in_array($field["type"], $TEXT_FIELD_TYPES))
        {
        # Prepend applies to text boxes only.
        ?>
        <option value="PP"<?php if(getval("modeselect_" . $field["ref"],"")=="PP"){?> selected<?php } ?>><?php echo $lang["prependtext"]?></option>
        <?php
        }
      if(in_array($field['type'], array_merge($TEXT_FIELD_TYPES, array(FIELD_TYPE_CHECK_BOX_LIST, FIELD_TYPE_CATEGORY_TREE, FIELD_TYPE_DYNAMIC_KEYWORDS_LIST))))
        {
        # Append applies to text boxes, checkboxes ,category tree and dynamic keyword fields only.
        ?>
        <option value="AP"<?php if(getval("modeselect_" . $field["ref"],"")=="AP"){?> selected<?php } ?>><?php echo $lang["appendtext"]?></option>
        <?php
        }
      if(in_array($field['type'], array_merge($TEXT_FIELD_TYPES, array(FIELD_TYPE_CHECK_BOX_LIST, FIELD_TYPE_DROP_DOWN_LIST, FIELD_TYPE_CATEGORY_TREE, FIELD_TYPE_DYNAMIC_KEYWORDS_LIST))))
        {
        # Remove applies to text boxes, checkboxes, dropdowns, category trees and dynamic keywords only. 
        ?>
        <option value="RM"<?php if(getval("modeselect_" . $field["ref"],"")=="RM"){?> selected<?php } ?>><?php echo $lang["removetext"]?></option>
        <?php
        }
        hook ("edit_all_extra_modes");
        ?>
        </select>
      </div><!-- End of modeselect_<?php echo $n?> -->

      <div class="Question" id="findreplace_<?php echo $n?>" style="display:none;border-top:none;">
        <label>&nbsp;</label>
        <?php echo $lang["find"]?> <input type="text" name="find_<?php echo $field["ref"]?>" class="shrtwidth">
        <?php echo $lang["andreplacewith"]?> <input type="text" name="replace_<?php echo $field["ref"]?>" class="shrtwidth">
      </div><!-- End of findreplace_<?php echo $n?> -->

      <?php hook ("edit_all_after_findreplace","",array($field,$n)); 
      }
      ?>

      <div class="Question <?php if($upload_review_mode && in_array($field["ref"],$locked_fields)){echo " lockedQuestion ";} if($field_save_error) { echo 'FieldSaveError'; } ?>" id="question_<?php echo $n?>" <?php
      if (($multiple && !$field_save_error) || !$displaycondition || $newtab)
        {?>style="border-top:none;<?php 
        if (($multiple && $value=="") || !$displaycondition) # Hide this
        {
        ?>
        display:none;
        <?php
        }
        ?>"<?php
        }
     ?>>
     <?php 
     $labelname = $name;

     // Add _selector to label so it will keep working:
     if($field['type'] == 9)
      {
      $labelname .= '_selector';
      }

      // Add -d to label so it will keep working
     if($field['type'] == 4)
        {
        $labelname .= '-d';
        }
        ?>
     <label for="<?php echo htmlspecialchars($labelname)?>" >
     <?php 
     if (!$multiple) 
        {
        echo htmlspecialchars($field["title"]);
        if (!$is_template && $field["required"]==1)
            {
            echo "<sup>*</sup>";
            }
        } 
     if ($upload_review_mode && $upload_review_lock_metadata)
        {
        renderLockButton($field["ref"], $locked_fields);
        }
        ?>
     </label>

     <?php
    # Autosave display
     if ($edit_autosave || $ctrls_to_save)
      {
      ?>
      <div class="AutoSaveStatus">
      <span id="AutoSaveStatus<?php echo $field["ref"] ?>" style="display:none;"></span>
      </div>
      <?php
      } 
    # Define some Javascript for help actions (applies to all fields)
    # Help actions for CKEditor fields are set in pages/edit_fields/8.php
     $help_js="onBlur=\"HideHelp(" . $field["ref"] . ");return false;\" onFocus=\"ShowHelp(" . $field["ref"] . ");return false;\"";

    #hook to modify field type in special case. Returning zero (to get a standard text box) doesn't work, so return 1 for type 0, 2 for type 1, etc.
     $modified_field_type="";
     $modified_field_type=(hook("modifyfieldtype"));
     if ($modified_field_type){$field["type"]=$modified_field_type-1;}

     hook("addfieldextras");
    # ----------------------------  Show field -----------------------------------
    $type = $field['type'];

    // Default to text type.
    if('' == $type)
        {
        $type = 0;
        }

    if(!hook('replacefield', '', array($field['type'], $field['ref'], $n)))
        {
        global $auto_order_checkbox, $auto_order_checkbox_case_insensitive, $FIXED_LIST_FIELD_TYPES, $is_search;

        if(in_array($field['type'], $FIXED_LIST_FIELD_TYPES))
            {
            $name = "nodes[{$field['ref']}]";

            // Sometimes we need to pass multiple options
            if(in_array($field['type'], array(FIELD_TYPE_CHECK_BOX_LIST, FIELD_TYPE_CATEGORY_TREE)))
                {
                $name = "nodes[{$field['ref']}][]";
                }
            else if(FIELD_TYPE_DYNAMIC_KEYWORDS_LIST == $field['type'])
                {
                $name = "field_{$field['ref']}";
                }
			
			$field_nodes = array();
			foreach($selected_nodes as $selected_node)
				{
				if(in_array($selected_node,array_column($field['nodes'],"ref")))
					{
					$field_nodes[] = $selected_node;
					}
				natsort($field_nodes);
				}
			if(!$multiple && !$blank_edit_template && getval("copyfrom","") == "" && getval('metadatatemplate', '') == "" && $check_edit_checksums)
				{
				echo "<input id='field_" . $field['ref']  . "_checksum' name='" . "field_" . $field['ref']  . "_checksum' type='hidden' value='" . md5(implode(",",$field_nodes)) . "'>";
				echo "<input name='" . "field_" . $field['ref']  . "_currentval' type='hidden' value='" . implode(",",$field_nodes) . "'>";
				}
            }
        elseif($field['type']==FIELD_TYPE_DATE_RANGE && !$blank_edit_template && getval("copyfrom","") == "" && getval('metadatatemplate', '') == "" && $check_edit_checksums)
			{
            $field['nodes'] = get_nodes($field['ref'], NULL, FALSE);
            $field_nodes = array();
			foreach($selected_nodes as $selected_node)
				{
				if(in_array($selected_node,array_column($field['nodes'],"ref")))
					{
					$field_nodes[] = $selected_node;
					}
				}
			natsort($field_nodes);
			
			echo "<input id='field_" . $field['ref']  . "_checksum' name='" . "field_" . $field['ref']  . "_checksum' type='hidden' value='" . md5(implode(",",$field_nodes)) . "'>";
			}
		elseif(!$multiple && !$blank_edit_template && getval("copyfrom","")=="" && getval('metadatatemplate', '') == "" && $check_edit_checksums)
			{
			echo "<input id='field_" . $field['ref']  . "_checksum' name='" . "field_" . $field['ref']  . "_checksum' type='hidden' value='" . md5(trim(preg_replace('/\s\s+/', ' ', $field['value']))) . "'>";
			}

        $is_search = false;

        include "edit_fields/{$type}.php";
        }
		
    # ----------------------------------------------------------------------------

    # Display any error messages from previous save
    if (array_key_exists($field["ref"],$errors))
      {
       ?>
       <div class="FormError">!! <?php echo $errors[$field["ref"]]?> !!</div>
       <?php
      }

    if (trim($field["help_text"]!=""))
     {
        # Show inline help for this field.
        # For certain field types that have no obvious focus, the help always appears.
       ?>
       <div class="FormHelp" style="padding:0;<?php if (!in_array($field["type"],array(2,4,6,7,10))) { ?> display:none;<?php } else { ?> clear:left;<?php } ?>" id="help_<?php echo $field["ref"]?>"><div class="FormHelpInner"><?php echo nl2br(trim(i18n_get_translated($field["help_text"],false)))?></div></div>
       <?php
     }

    # If enabled, include code to produce extra fields to allow multilingual free text to be entered.
    if ($multilingual_text_fields && ($field["type"]==0 || $field["type"]==1 || $field["type"]==5))
      {
       display_multilingual_text_field($n, $field, $translations);
      }
    
    if(($embedded_data_user_select || (isset($embedded_data_user_select_fields) && in_array($field["ref"],$embedded_data_user_select_fields))) && ($ref<0 && !$multiple))
    {
      ?>
      <table id="exif_<?php echo $field["ref"] ?>" class="ExifOptions" cellpadding="3" cellspacing="3" <?php if ($embedded_data_user_select){?> style="display: none;" <?php } ?>>                    
         <tbody>
           <tr>        
             <td>
                <?php echo "&nbsp;&nbsp;" . $lang["embeddedvalue"] . ": " ?>
             </td>
             <td width="10" valign="middle">
               <input type="radio" id="exif_extract_<?php echo $field["ref"] ?>" name="exif_option_<?php echo $field["ref"] ?>" value="yes" checked>
            </td>
            <td align="left" valign="middle">
               <label class="customFieldLabel" for="exif_extract_<?php echo $field["ref"] ?>"><?php echo $lang["embedded_metadata_extract_option"] ?></label>
            </td>


            <td width="10" valign="middle">
               <input type="radio" id="no_exif_<?php echo $field["ref"] ?>" name="exif_option_<?php echo $field["ref"] ?>" value="no">
            </td>
            <td align="left" valign="middle">
               <label class="customFieldLabel" for="no_exif_<?php echo $field["ref"] ?>"><?php echo $lang["embedded_metadata_donot_extract_option"] ?></label>
            </td>


            <td width="10" valign="middle">
               <input type="radio" id="exif_append_<?php echo $field["ref"] ?>" name="exif_option_<?php echo $field["ref"] ?>" value="append">
            </td>
            <td align="left" valign="middle">
               <label class="customFieldLabel" for="exif_append_<?php echo $field["ref"] ?>"><?php echo $lang["embedded_metadata_append_option"] ?></label>
            </td>


            <td width="10" valign="middle">
               <input type="radio" id="exif_prepend_<?php echo $field["ref"] ?>" name="exif_option_<?php echo $field["ref"] ?>" value="prepend">
            </td>
            <td align="left" valign="middle">
               <label class="customFieldLabel" for="exif_prepend_<?php echo $field["ref"] ?>"><?php echo $lang["embedded_metadata_prepend_option"] ?></label>
            </td>

         </tr>
      </tbody>
   </table>        
   <?php
  }
  ?>
  <div class="clearerleft"> </div>
  </div><!-- end of question_<?php echo $n?> div -->
  <?php     
  
  hook('afterfielddisplay', '', array($n, $field));
  }

	
function render_date_range_field($name,$value,$forsearch=true, $autoupdate=false,$field=array())
	{
	$found_year='';$found_month='';$found_day='';$found_start_year='';$found_start_month='';$found_start_day='';$found_end_year='';$found_end_month='';$found_end_day=''; 
	global $daterange_edtf_support,$lang, $minyear,$date_d_m_y, $chosen_dropdowns, $tabs_on_edit,$edit_autosave,$forsearchbar;
	if($forsearch)
		{
		// Get the start/end date from the string
		$startvalue=strpos($value,"start")!==false?substr($value,strpos($value,"start")+5,10):"";
		$endvalue=strpos($value,"end")!==false?substr($value,strpos($value,"end")+3,10):"";
		}
	else
		{
		if($value!="" && strpos($value,",")!==false)
			{
			// Extract the start date from the value obtained from get_resource_field_data
			$rangevalues = explode(",",$value);
			$startvalue = $rangevalues[0];
			$endvalue = $rangevalues[1];
			}
		elseif(strlen($value)==10 && strpos($value,"-") !==  false)
			{
			$startvalue = $value;
			$endvalue = "";
			}
		else
			{
			$startvalue = "";
			$endvalue = "";
			}
		}
				
	$ss=explode("-",$startvalue);
	if (count($ss)>=3)
		{
		$found_start_year=$ss[0];
		$found_start_month=$ss[1];
		$found_start_day=$ss[2];
		}
	$se=explode("-",$endvalue);
	if (count($se)>=3)
		{
		$found_end_year=$se[0];
		$found_end_month=$se[1];
		$found_end_day=$se[2];
		}
	
	if($daterange_edtf_support)
		{
		// Use EDTF format for date input
		?>		
		<input class="<?php echo $forsearch?"SearchWidth":"stdwidth"; ?>"  name="<?php echo $name?>_edtf" id="<?php echo $name?>_edtf" type="text" value="<?php echo ($startvalue!=""|$endvalue!="")?$startvalue . "/" . $endvalue:""; ?>" style="display:none;" disabled <?php if ($forsearch && $autoupdate) { ?>onChange="UpdateResultCount();"<?php } if($forsearch && !$forsearchbar){ ?> onKeyPress="if (!(updating)) {setTimeout('UpdateResultCount()',2000);updating=true;}"<?php } else if (!$forsearch  && $edit_autosave){?>onChange="AutoSave('<?php echo $field["ref"]?>');"<?php } ?>>
		<?php
		}?>
    <!--  date range search start -->   		
    <!--- start date -->	
    <div class="stdwidth indent <?php echo $name?>_range" id="<?php echo $name?>_from">
    <label class="InnerLabel"><?php echo $lang["fromdate"]?></label>
    
        <?php 		
        if($date_d_m_y)
            {  
            ?>
            <label class="accessibility-hidden" for="<?php echo htmlspecialchars($name) ?>_startday"><?php echo $lang["day"]; ?></label>
            <select name="<?php echo $name?>_startday"
             <?php if ($chosen_dropdowns && $tabs_on_edit) {?>class="ChosenDateDay"<?php }
            if ($forsearch && $autoupdate) 
                    { ?>onChange="UpdateResultCount();"<?php }
            else if (!$forsearch  && $edit_autosave)
            {?>onChange="AutoSave('<?php echo $field["ref"]?>');"<?php } ?>
              >
              <option value=""><?php echo $forsearch?$lang["anyday"]:$lang["day"]; ?></option>
              <?php
              for ($d=1;$d<=31;$d++)
                {
                $m=str_pad($d,2,"0",STR_PAD_LEFT);
                ?><option <?php if ($d==$found_start_day) { ?>selected<?php } ?> value="<?php echo $m?>"><?php echo $m?></option><?php
                }
              ?>
            </select>
            <label class="accessibility-hidden" for="<?php echo htmlspecialchars($name) ?>_startmonth"><?php echo $lang["month"]; ?></label>
            <select name="<?php echo $name?>_startmonth"
                <?php if ($chosen_dropdowns && $tabs_on_edit) {?>class="ChosenDateMonth"<?php }
                if ($forsearch && $autoupdate) 
                    { ?>onChange="UpdateResultCount();"<?php }
                else if (!$forsearch  && $edit_autosave)
                    {?>onChange="AutoSave('<?php echo $field["ref"]?>');"<?php } ?>
                    >
                <option value=""><?php echo $forsearch?$lang["anymonth"]:$lang["month"]; ?></option>
                <?php
                for ($d=1;$d<=12;$d++)
                    {
                    $m=str_pad($d,2,"0",STR_PAD_LEFT);
                    ?><option <?php if ($d==$found_start_month) { ?>selected<?php } ?> value="<?php echo $m?>"><?php echo $lang["months"][$d-1]?></option><?php
                    }?>
            </select>
            <?php
            }
        else
            { 
            ?>		
            <label class="accessibility-hidden" for="<?php echo htmlspecialchars($name) ?>_startmonth"><?php echo $lang["month"]; ?></label>
            <select name="<?php echo $name?>_startmonth"
                <?php if ($chosen_dropdowns && $tabs_on_edit) {?>class="ChosenDateMonth"<?php }
                if ($forsearch && $autoupdate) 
                    { ?>onChange="UpdateResultCount();"<?php }
                else if (!$forsearch  && $edit_autosave)
                    {?>onChange="AutoSave('<?php echo $field["ref"]?>');"<?php } ?>
                    >					
                <option value=""><?php echo $forsearch?$lang["anymonth"]:$lang["month"]; ?></option>
                <?php
                for ($d=1;$d<=12;$d++)
                    {
                    $m=str_pad($d,2,"0",STR_PAD_LEFT);
                    ?><option <?php if ($d==$found_start_month) { ?>selected<?php } ?> value="<?php echo $m?>"><?php echo $lang["months"][$d-1]?></option><?php
                    }?>
            </select>
            <label class="accessibility-hidden" for="<?php echo htmlspecialchars($name) ?>_startday"><?php echo $lang["day"]; ?></label>
            <select name="<?php echo $name?>_startday"
              <?php if ($chosen_dropdowns && $tabs_on_edit) {?>class="ChosenDateDay"<?php }
                if ($forsearch && $autoupdate) 
                    { ?>onChange="UpdateResultCount();"<?php }
                else if (!$forsearch  && $edit_autosave)
                    {?>onChange="AutoSave('<?php echo $field["ref"]?>');"<?php } ?>
                    >
              <option value=""><?php echo $forsearch?$lang["anyday"]:$lang["day"]; ?></option>
              <?php
              for ($d=1;$d<=31;$d++)
                {
                $m=str_pad($d,2,"0",STR_PAD_LEFT);
                ?><option <?php if ($d==$found_start_day) { ?>selected<?php } ?> value="<?php echo $m?>"><?php echo $m?></option><?php
                }
              ?>
            </select>
            <?php			
            }
        if($forsearch)
            {?>
            <label class="accessibility-hidden" for="<?php echo htmlspecialchars($name) ?>_endyear"><?php echo $lang["year"]; ?></label>
            <select name="<?php echo htmlspecialchars($name) ?>_startyear"
                <?php if ($chosen_dropdowns && $tabs_on_edit) {?>class="ChosenDateMonth"<?php } 
                if ($forsearch && $autoupdate) 
                        { ?>onChange="UpdateResultCount();"<?php }
                else if (!$forsearch  && $edit_autosave)
                {?>onChange="AutoSave('<?php echo $field["ref"]?>');"<?php } ?>
                >
                <option value=""><?php echo $forsearch?$lang["anyyear"]:$lang["year"]; ?></option>
                <?php
                $y=date("Y");
                for ($d=$y;$d>=$minyear;$d--)
                    {
                    ?><option <?php if ($d==$found_start_year) { ?>selected<?php } ?>><?php echo $d?></option><?php
                    }?>
            </select>
            <?php
            }
        else
            {?>
            <label class="accessibility-hidden" for="<?php echo htmlspecialchars($name) ?>_endyear"><?php echo $lang["year"]; ?></label>
            <input size="5" name="<?php echo htmlspecialchars($name) ?>_startyear" id="<?php echo htmlspecialchars($name) ?>_startyear" type="text" value="<?php echo $found_start_year ?>"
                <?php
                if ($forsearch && $autoupdate)
                    { ?>onChange="UpdateResultCount();"<?php }
                if($forsearch && !$forsearchbar)
                    { ?> onKeyPress="if (!(updating)) {setTimeout('UpdateResultCount()',2000);updating=true;}"<?php }
                else if (!$forsearch  && $edit_autosave)
                    {?>onChange="AutoSave('<?php echo $field["ref"]?>');"<?php } ?>>
		    <?php
            }?>
    </div>
    
    <div class="clearerleft"> </div>
    
    <!--- to date -->
    <label></label>
    
    
    
    <div class="stdwidth indent <?php echo $name?>_range" id="<?php echo $name?>_to" >
    <label class="InnerLabel"><?php echo $lang["todate"]?></label>
    <?php 		
        if($date_d_m_y)
            {
            ?>
            <label class="accessibility-hidden" for="<?php echo htmlspecialchars($name) ?>_endday"><?php echo $lang["day"]; ?></label>
            <select name="<?php echo $name?>_endday"
              <?php if ($chosen_dropdowns && $tabs_on_edit) {?>class="ChosenDateDay"<?php }
                if ($forsearch && $autoupdate) 
                    { ?>onChange="UpdateResultCount();"<?php }
                else if (!$forsearch  && $edit_autosave)
                    {?>onChange="AutoSave('<?php echo $field["ref"]?>');"<?php } ?>
                    >
                <option value=""><?php echo $forsearch?$lang["anyday"]:$lang["day"]; ?></option>
                <?php
                for ($d=1;$d<=31;$d++)
                    {
                    $m=str_pad($d,2,"0",STR_PAD_LEFT);
                    ?><option <?php if ($d==$found_end_day) { ?>selected<?php } ?> value="<?php echo $m?>"><?php echo $m?></option><?php
                    }?>
            </select>
            <label class="accessibility-hidden" for="<?php echo htmlspecialchars($name) ?>_endmonth"><?php echo $lang["month"]; ?></label>
            <select name="<?php echo $name?>_endmonth"
                <?php if ($chosen_dropdowns && $tabs_on_edit) {?>class="ChosenDateMonth"<?php }
                if ($forsearch && $autoupdate) 
                    { ?>onChange="UpdateResultCount();"<?php }
                else if (!$forsearch  && $edit_autosave)
                    {?>onChange="AutoSave('<?php echo $field["ref"]?>');"<?php } ?>
                    >
                <option value=""><?php echo $forsearch?$lang["anymonth"]:$lang["month"]; ?></option>
                <?php
                for ($d=1;$d<=12;$d++)
                    {
                    $m=str_pad($d,2,"0",STR_PAD_LEFT);
                    ?><option <?php if ($d==$found_end_month) { ?>selected<?php } ?> value="<?php echo $m?>"><?php echo $lang["months"][$d-1]?></option><?php
                    }?>
            </select>
            <?php
            }
        else
            {
            ?>
            <label class="accessibility-hidden" for="<?php echo htmlspecialchars($name) ?>_endmonth"><?php echo $lang["month"]; ?></label>
            <select name="<?php echo $name?>_endmonth"
                <?php if ($chosen_dropdowns && $tabs_on_edit) {?>class="ChosenDateMonth"<?php }
                if ($forsearch && $autoupdate) 
                    { ?>onChange="UpdateResultCount();"<?php }
                else if (!$forsearch  && $edit_autosave)
                    {?>onChange="AutoSave('<?php echo $field["ref"]?>');"<?php } ?>
                    >					
                <option value=""><?php echo $forsearch?$lang["anymonth"]:$lang["month"]; ?></option>
                <?php
                for ($d=1;$d<=12;$d++)
                    {
                    $m=str_pad($d,2,"0",STR_PAD_LEFT);
                    ?><option <?php if ($d==$found_end_month) { ?>selected<?php } ?> value="<?php echo $m?>"><?php echo $lang["months"][$d-1]?></option><?php
                    }?>
            </select>
            <label class="accessibility-hidden" for="<?php echo htmlspecialchars($name) ?>_endday"><?php echo $lang["day"]; ?></label>
            <select name="<?php echo $name?>_endday"
              <?php if ($chosen_dropdowns && $tabs_on_edit) {?>class="ChosenDateDay"<?php }
                if ($forsearch && $autoupdate) 
                    { ?>onChange="UpdateResultCount();"<?php }
                else if (!$forsearch  && $edit_autosave)
                    {?>onChange="AutoSave('<?php echo $field["ref"]?>');"<?php } ?>
                    >
              <option value=""><?php echo $forsearch?$lang["anyday"]:$lang["day"]; ?></option>
              <?php
              for ($d=1;$d<=31;$d++)
                {
                $m=str_pad($d,2,"0",STR_PAD_LEFT);
                ?><option <?php if ($d==$found_end_day) { ?>selected<?php } ?> value="<?php echo $m?>"><?php echo $m?></option><?php
                }
              ?>
            </select>
            <?php			
            }
        if($forsearch)
            {?>
            <label class="accessibility-hidden" for="<?php echo htmlspecialchars($name) ?>_endyear"><?php echo $lang["year"]; ?></label>
            <select name="<?php echo $name?>_endyear" 
            <?php if ($forsearch && $autoupdate) { ?>onChange="UpdateResultCount();"<?php } 
                else if (!$forsearch  && $edit_autosave)
                    {?>onChange="AutoSave('<?php echo $field["ref"]?>');"<?php } ?>
                    >
              <option value=""><?php echo $forsearch?$lang["anyyear"]:$lang["year"]?></option>
              <?php
              $y=date("Y");
              for ($d=$y;$d>=$minyear;$d--)
                {
                ?><option <?php if ($d==$found_end_year ) { ?>selected<?php } ?>><?php echo $d?></option><?php
                }
              ?>
            </select>
             <?php
                }
            else
                {?>
                <label class="accessibility-hidden" for="<?php echo htmlspecialchars($name) ?>_endyear"><?php echo $lang["year"]; ?></label>
                <input size="5" name="<?php echo htmlspecialchars($name) ?>_endyear" id="<?php echo htmlspecialchars($name) ?>_endyear" type="text" value="<?php echo $found_end_year ?>"
                    <?php
                    if ($forsearch && $autoupdate)
                        { ?>onChange="UpdateResultCount();"<?php }
                    if($forsearch && !$forsearchbar)
                        { ?> onKeyPress="if (!(updating)) {setTimeout('UpdateResultCount()',2000);updating=true;}"<?php }
                    else if (!$forsearch  && $edit_autosave)
                        {?>onChange="AutoSave('<?php echo $field["ref"]?>');"<?php } ?>>
                <?php
                }?>
    <!--  date range search end date-->         
    </div>
    <div class="clearerleft"></div>
    <?php if($daterange_edtf_support)
		{?>
        <a href="#" onclick="if(jQuery('#<?php echo $name ?>_edtf').prop('disabled')){jQuery('#<?php echo $name ?>_edtf').prop('disabled',false);jQuery('#<?php echo $name ?>_edtf').show();jQuery('.<?php echo $name ?>_range').hide();}else{jQuery('#<?php echo $name ?>_edtf').prop('disabled',true);jQuery('#<?php echo $name ?>_edtf').hide();jQuery('.<?php echo $name ?>_range').show();}return false;">
            <i aria-hidden="true" class="fa fa-caret-right"></i>
            <?php echo "EDTF"; ?>
        </a>
        <?php
        }
	}

/**
* Renders a full breadcrumbs trail.
* 
* @param array  $links     List of link "objects" that create the trail
* @param string $pre_links Pre-rendered links in HTML form
* 
* @return void
*/
function renderBreadcrumbs(array $links, $pre_links = '')
    {
    global $lang;
    /*
    NOTE: implemented as seen on themes and search. There is a lot of room for improvement UI wise

    TODO: search_title_processing.php is using it intesively and at the moment there are differences in terms of 
    rendered HTML between themes/ search and search_title_processing.php. We should refactor all places were breadcrumbs
    are being created and make sure they all use this function (or any future related functions - like generateBreadcrumb() ).
    */

    if(0 === count($links))
        {
        return;
        }
        ?>
        <div class="SearchBreadcrumbs">
        <?php
        if('' !== $pre_links && $pre_links !== strip_tags($pre_links))
            {
            echo $pre_links . '&nbsp;' . LINK_CARET;
            }

        for($i = 0; $i < count($links); $i++)
            {
            if(0 < $i)
                {
                echo LINK_CARET;
                }
                ?>
            <a href="<?php echo htmlspecialchars($links[$i]['href']); ?>" onClick="return CentralSpaceLoad(this, true);">
                <span><?php echo htmlspecialchars($links[$i]['title']); ?></span>
            </a>
            <?php
            }
            ?>
        </div>
    <?php

    return;
    }


/**
* Render a blank tile used for call to actions (e.g: on featured collections, a tile for creating new collections)
* 
* @param string $link URL
* 
* @return void
*/
function renderCallToActionTile($link)
    {
    global $themes_simple_view;

    if(!$themes_simple_view || checkperm('b') || !checkperm('h'))
        {
        return;
        }

    if('' === $link)
        {
        return;
        }
        ?>
    <div id="FeaturedSimpleTile" class="FeaturedSimplePanel HomePanel DashTile FeaturedSimpleTile FeaturedCallToActionTile">
        <a href="<?php echo $link; ?>" onclick="return ModalLoad(this, true, true);" class="">
            <div class="FeaturedSimpleTileContents">
                <div class="FeaturedSimpleTileText">
                    <h2><span class='fa fa-plus-circle fa-2x'></span></h2>
                </div>
            </div>
        </a>
    </div>
    <?php
    return;
    }

/**
* Renders social media links in order to share a particular link
* 
* @param string $url The URL to be shared on social media networks
* 
* @return void
*/
function renderSocialMediaShareLinksForUrl($url)
    {
    global $facebook_app_id;

    $url_encoded = urlencode($url);

    if('' !== trim($facebook_app_id))
        {
        ?>
        <!-- Facebook -->
        <a target="_blank" href="https://www.facebook.com/dialog/feed?app_id=<?php echo $facebook_app_id; ?>&link=<?php echo $url_encoded; ?>"><i class="fa fa-lg fa-facebook-official" aria-hidden="true"></i></a>
        <?php
        }
        ?>
    
    <!-- Twitter -->
    <a target="_blank" href="https://twitter.com/?status=<?php echo $url_encoded; ?>"><i class="fa fa-lg fa-twitter-square" aria-hidden="true"></i></a>
    
    <!-- LinkedIn -->
    <a target="_blank" href="https://www.linkedin.com/shareArticle?mini=true&url=<?php echo $url_encoded; ?>"><i class="fa fa-lg fa-linkedin-square" aria-hidden="true"></i></a>
    <?php

    return;
    }
    
/**
* Renders a lock button for a field - used to 'lock' metadata in upload_review_mode
* 
* @param string $name  The field identifier e.g. 'resource_type', '18'
* @param array $locked_fields - Array of locked field identifiers
* 
* @return void
*/
function renderLockButton($name, $locked_fields=array())
    {
    ?>
    <button type="submit" class="lock_icon" id="lock_icon_<?php echo htmlspecialchars($name) ; ?>" onClick="toggleFieldLock('<?php echo htmlspecialchars($name) ; ?>');return false;">
        <i aria-hidden="true" class="fa <?php if(in_array($name,$locked_fields)){echo "fa-lock";} else {echo "fa-unlock";} ?> fa-fw"></i>
    </button>
    <?php    
    }
