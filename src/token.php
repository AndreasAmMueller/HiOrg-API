<?php

/**
 * token.php
 *
 * (c) Andreas Mueller <webmaster@am-wd.de>
 *
 * @description
 * This snippet just prints the received token.
 */

error_reporting(0);

$data = json_decode(file_get_contents('php://input'));

if (!empty($data)) {
	switch (gettype($data)) {
		case 'string':
			$token = $data;
			break;
		case 'object':
			$token = $data->token;
			break;
		case 'array':
			$token = $data['token'];
			break;
		default:
			$token = '';
			break;
	}
} else {
	$token = '';
}

if (empty($token)) {
	$token = $_POST['token'];
}

if (empty($token)) {
	$token = $_GET['token'];
}

echo empty($token) ? 'Error: no token detected' : $token;

?>