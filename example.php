<?php

// define needed vars
$ov = '';
$apikey = '';

// include class
require_once __DIR__.'/src/HiOrgApi.php';

$api = new AMWD\HiOrgApi($ov, $apikey);

print_r($api->check_key());
echo PHP_EOL;

print_r($api->get_operations());
echo PHP_EOL;

print_r($api->get_op_details(1234));
echo PHP_EOL;

print_r($api->get_ressources('KTW'));
echo PHP_EOL;

print_r($api->get_personnel());
echo PHP_EOL;

?>