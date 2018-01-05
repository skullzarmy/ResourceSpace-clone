<?php
include '../../../include/db.php';
include "../../../include/authenticate.php";
if(!checkperm("t")){exit ("Access denied"); }
include_once "../../../include/general.php";
include_once "../../../include/resource_functions.php";
include_once "../include/tms_link_functions.php";


$ref=getvalescaped("ref","",true);
$tmsid=getvalescaped("tmsid","",true);

if($ref=="" && $tmsid==""){exit($lang["tms_link_no_resource"]);}

$tmsdata=tms_link_get_tms_data($ref, $tmsid);

include "../../../include/header.php";
echo "<h2>" . $lang["tms_link_tms_data"] . "</h2>";
if(!is_array($tmsdata))
    {
    echo $tmsdata;
    include "../../../include/footer.php";
    die();
    }


echo "<div class='Listview'>";
echo "<table style='border=1;'>";


foreach($tmsdata as $key=>$value)
	{
	echo "<tr>"; 
	echo "<td><strong>" . $key . "</strong></td>";
	echo "<td>" . mb_convert_encoding($value, 'UTF-8') . "</td>";
	echo "</tr>";
	}

	

echo "</table>";
echo "</div>";	



	
include "../../../include/footer.php";