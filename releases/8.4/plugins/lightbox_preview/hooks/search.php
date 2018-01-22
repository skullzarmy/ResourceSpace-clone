<?php

include dirname(__FILE__) . "/../include/utility.php";

function HookLightbox_previewSearchReplacefullscreenpreviewicon()
	{
	global $baseurl_short, $ref, $result, $n, $k, $search, $offset, $sort, $order_by, $archive,
			$lang, $showkeypreview, $value, $view_title_field;

	$url = getPreviewURL($result[$n]);
	if ($url === false)
		return false;

	$showkeypreview = true;

	# Replace the link to add the 'previewlink' ID
	?>
		<span class="IconPreview"><a aria-hidden="true" class="fa fa-expand" id="previewlink<?php echo $ref ?>" href="<?php
			echo $baseurl_short?>pages/preview.php?from=search&ref=<?php
			echo urlencode($ref)?>&ext=<?php echo $result[$n]["preview_extension"]?>&search=<?php
			echo urlencode($search)?>&offset=<?php echo urlencode($offset)?>&order_by=<?php
			echo urlencode($order_by)?>&sort=<?php echo urlencode($sort)?>&archive=<?php
			echo urlencode($archive)?>&k=<?php echo urlencode($k)?>" title="<?php
			echo $lang["fullscreenpreview"]?>"></a></span>
	<?php
	setLink('#previewlink' . $ref, $url, str_replace(array("\"","'"),"",htmlspecialchars(i18n_get_translated(strip_tags(strip_tags_and_attributes($result[$n]["field".$view_title_field]))))));
	return true;
	}

function HookLightbox_previewSearchEndofsearchpage()
	{
	addLightBox('.IconPreview a');
	}

?>
