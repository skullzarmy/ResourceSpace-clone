<?php
if('cli' != PHP_SAPI)
    {
    exit('This utility is command line only.');
    }

$resource_1     = create_resource(1, 0);
$resource_2     = create_resource(1, 0);
$resource_3     = create_resource(1, 0);
$collection_ref = create_collection($userref, 'Test collection access keys');

add_resource_to_collection($resource_1, $collection_ref);
add_resource_to_collection($resource_2, $collection_ref);
add_resource_to_collection($resource_3, $collection_ref);

$valid_k   = generate_collection_access_key($collection_ref, 0, 'testEmail@testDomain.com', 0, date('Y-m-d H:i:s', strtotime('+3 days')));
$invalid_k = 'badKvalue';

// Valid K
if(!check_access_key_collection($collection_ref, $valid_k))
    {
    return false;
    }

// Invalid K
if(check_access_key_collection($collection_ref, $invalid_k))
    {
    return false;
    }

// Expired K
if(edit_collection_external_access($valid_k, 0, date('Y-m-d H:i:s', strtotime('-5 days'))) && check_access_key_collection($collection_ref, $valid_k))
    {
    return false;
    }

delete_collection_access_key($collection_ref, $valid_k);

return true;