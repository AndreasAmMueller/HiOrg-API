<?php

error_reporting(E_ALL ^ E_NOTICE);
require_once __DIR__.'/src/HiOrgApi.php';

if (php_sapi_name() === 'cli') {
	// --- CLI service
	// ==========================================================================

	// define needed vars
	$ov = 'aerh';
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
} else {
	// --- HTTP service
	// ==========================================================================
	session_name('HiOrgApi');
	session_start();

	if (!empty($_POST['action'])) {
		$_SESSION['ov']       = $_POST['ov'];
		$_SESSION['apikey']   = $_POST['apikey'];
		$_SESSION['username'] = $_POST['username'];
	}


	if (isset($_POST['action']) && $_POST['action'] == 'Login') {
		// Hit the Login button for SSO
		$api = new AMWD\HiOrgApi($_SESSION['ov'], $_SESSION['apikey']);

		if ($_POST['type'] == 'backend') {
			$content = array();

			$api = new AMWD\HiOrgApi($_SESSION['ov'], $_SESSION['apikey']);

			$token = $api->sso_get_token($_SESSION['username'], $_POST['password']);
			$user = $api->sso_get_data();

			$content[] = 'Token: '.$token;
			$content[] = 'Benutzer:';
			$content[] = '<pre>'.print_r($user, 1).'</pre>';
			$content[] = '';
			$content[] = 'Vollständige Klasse:';
			$content[] = '<pre>'.print_r($api, 1).'</pre>';
			$content[] = '<a href="'.$_SERVER['PHP_SELF'].'">Zurück</a>';

			echo implode('<br />', $content);
		} else {
			$api->sso_backend = false;
			$api->url_return = str_replace('localhost', '127.0.0.1', (empty($_SERVER['HTTPS']) ? 'http://' : 'https://').$_SERVER['HTTP_HOST'].$_SERVER['PHP_SELF']);

			$api->sso_get_token();
		}
	} else if (isset($_POST['action']) && $_POST['action'] == 'Request EFS') {
		// Hit the button for EFS
		$content = array();

		$api = new AMWD\HiOrgApi($_SESSION['ov'], $_SESSION['apikey']);

		$valid = $api->efs_check_apikey();

		$content[] = 'API Key gültig: '.($valid ? 'Ja' : 'Nein');

		if ($valid) {
			$ops = $api->efs_get_operations();

			$content[] = 'Einsätze:';
			$content[] = '<pre>'.print_r($ops, 1).'</pre>';
			$content[] = 'Erster Einsatz in der Liste:';
			$content[] = '<pre>'.print_r($api->efs_get_op_details($ops[0]->id), 1).'</pre>';
			$content[] = 'Verfügbare Ressourcen (Autos) mit Filter \'KT\':';
			$content[] = '<pre>'.print_r($api->efs_get_ressources('KT'), 1).'</pre>';
			//$content[] = 'Verfügbares Personal:';
			//$content[] = '<pre>'.print_r($api->efs_get_personnel(), 1).'</pre>';
			$content[] = '<a href="'.$_SERVER['PHP_SELF'].'">Zurück</a>';
		}

		echo implode('<br />', $content);
	} else if (!empty($_GET['token'])) {
		// came back from SSO
		$api = new AMWD\HiOrgApi($_SESSION['ov'], $_SESSION['apikey']);

		$user = $api->sso_get_data($_GET['token']);

		$content[] = 'Token: '.$_GET['token'];
		$content[] = 'Benutzer:';
		$content[] = '<pre>'.print_r($user, 1).'</pre>';
		$content[] = '';
		$content[] = 'Vollständige Klasse:';
		$content[] = '<pre>'.print_r($api, 1).'</pre>';
		$content[] = '<a href="'.$_SERVER['PHP_SELF'].'">Zurück</a>';

		echo implode('<br />', $content);

	} else {

		// enforce ip instead of name at localhost => SSO needs it ;)
		if ($_SERVER['SERVER_NAME'] == 'localhost') {
			$url = (empty($_SERVER['HTTPS']) ? 'http://' : 'https://').str_replace('localhost', '127.0.0.1', $_SERVER['HTTP_HOST']).$_SERVER['PHP_SELF'];
			header('Location: '.$url);
			exit;
		}

		echo '
		<form action="'.$_SERVER['PHP_SELF'].'" method="post">
			<table>
				<tr>
					<th>Type:</th>
					<td>
						<label><input type="radio" name="type" value="redirect" /> Weiterleitung</label>
						<br />
						<label><input type="radio" name="type" value="backend" checked="checked" /> Hintergrund</label>
					</td>
				</tr>
				<tr>
					<th>Kuerzel:</th>
					<td><input type="text" name="ov" placeholder="Code to identify organisation" value="'.$_SESSION['ov'].'" /></td>
				</tr>
				<tr>
					<th>API Key:</th>
					<td><input type="text" name="apikey" placeholder="API Key for efs program" value="'.$_SESSION['apikey'].'" /></td>
				</tr>
				<tr>
					<th>Benutzer:</th>
					<td><input type="text" name="username" placeholder="Username at HiOrg Server" value="'.$_SESSION['username'].'" /></td>
				</tr>
				<tr>
					<th>Passwort:</th>
					<td><input type="password" name="password" placeholder="Password at HiOrg Server" /></td>
				</tr>
				<tr>
					<th>&nbsp;</th>
					<td>&nbsp;</td>
				</tr>
				<tr>
					<th></th>
					<td>
						<input type="submit" name="action" value="Login" />
						<input type="submit" name="action" value="Request EFS" />
					</td>
				</tr>
		</form>
		';
	}
}

?>
