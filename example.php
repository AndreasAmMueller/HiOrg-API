<?php

// define needed vars
$ov = '';
$apikey = '';
$usersername = '';
$password = '';

// include class
require_once __DIR__.'/src/HiOrgApi.php';

$api = new AMWD\HiOrgApi($ov, $apikey);

echo "Check API Key: ";
print_r($api->efs_check_apikey());
echo PHP_EOL;

echo "Get list of operations:".PHP_EOL;
print_r($ops = $api->efs_get_operations());
echo PHP_EOL;

$opid = $ops[0]->id;
echo "Get Details of operation #$opid:".PHP_EOL;
print_r($api->efs_get_op_details($opid));
echo PHP_EOL;

echo "Get Ressouces:".PHP_EOL;
print_r($api->efs_get_ressources('KT'));
echo PHP_EOL;

// not implemented yet
//echo "Get Personnel:".PHP_EOL;
//print_r($api->efs_get_personnel());
//echo PHP_EOL;

echo PHP_EOL;
echo PHP_EOL;


echo "Show Token:".PHP_EOL;
$token = $api->sso_get_token($username, $password);
print_r($api);
echo PHP_EOL;

echo "Show User:".PHP_EOL;
$user = $api->sso_get_data();
print_r($user);
echo PHP_EOL;

?>
