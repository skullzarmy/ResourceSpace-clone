<?php

include dirname(__FILE__) . "/../include/utility.php";

function HookLightbox_previewViewRenderbeforerecorddownload()
	{
	global $resource, $title_field;

    $url = getPreviewURL($resource);
    
    if(false === $url)
        {
        return;
        }

    $title             = get_data_by_field($resource['ref'], $title_field);
    $page_count        = get_page_count($resource);

    for($i = 1; $i < $page_count + 1; $i++)
        {
        // Handle first preview (regardless if it is multi page or just one preview)
        if(1 == $i)
            {
            setLink('#previewimagelink', $url, $title);
            setLink('#previewlink', $url, $title, 'lightbox-other');

            continue;
            }

        // This applies only to resources that have multi page previews
        $preview_url = getPreviewURL($resource, -1, $i);

        if(false === $preview_url)
            {
            continue;
            }
            ?>
        <a href="<?php echo $preview_url; ?>"
           rel="lightbox"
           title="<?php echo htmlspecialchars(i18n_get_translated($title)); ?>"
           onmouseup="closeModalOnLightBoxEnable();">
       </a>
        <?php
        }
    }

function HookLightbox_previewViewRenderaltthumb()
	{
	global $baseurl_short, $ref, $resource, $alt_thm, $altfiles, $n, $k, $search,
			$offset, $sort, $order_by, $archive;

	$url = getPreviewURL($resource, $altfiles[$n]['ref']);
	if ($url === false)
		return false;

	# Replace the link to add the 'altlink' ID
	?>
	<a id="altlink_<?php echo $n; ?>" href="<?php echo $baseurl_short?>pages/preview.php?ref=<?php
			echo urlencode($ref)?>&alternative=<?php echo $altfiles[$n]['ref']?>&k=<?php
			echo urlencode($k)?>&search=<?php echo urlencode($search)?>&offset=<?php echo
			urlencode($offset)?>&order_by=<?php echo urlencode($order_by)?>&sort=<?php echo
			urlencode($sort)?>&archive=<?php echo urlencode($archive)?>&<?php
			echo hook("previewextraurl") ?>">
		<img src="<?php echo $alt_thm; ?>" class="AltThumb">
	</a>
	<?php
	setLink('#altlink_' . $n, $url, $altfiles[$n]['name']);

	return true;
	}

function HookLightbox_previewViewRenderbeforeresourcedetails()
    {
    addLightBox('a[rel="lightbox"]');
    addLightBox('a[rel="lightbox-other"]');
    }

function HookLightbox_previewViewAftersearchimg()
	{
	// Prevent loading of Central Space when clicking preview image
	?>

	<script type="text/javascript">
		jQuery(document).ready(function() {
			jQuery('#previewimagelink').removeAttr('onclick');
		});
	</script>

	<?php
	}

?>

