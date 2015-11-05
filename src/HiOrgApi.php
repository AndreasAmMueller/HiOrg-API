<?php

/**
 * HiOrgApi.php
 *
 * (c) Andreas Mueller <webmaster@am-wd.de>
 */

namespace AMWD;

/**
 * Class to implement API of HiOrg-Server.de.
 *
 * HiOrg Server provides some API to gather information and Single-Sign-On.
 * HiOrg-Server can be found at http://hiorg-server.de and is not part of my service!
 *
 * @package    AMWD
 * @author     Andreas Mueller <webmaster@am-wd.de>
 * @copyright  (c) 2015 Andreas Mueller
 * @license    MIT - http://am-wd.de/index.php?p=about#license
 * @link       https://bitbucket.org/BlackyPanther/hiorg-api
 * @version    v1.0-20151104 | in developement
 */
class HiOrgApi {

	// --- Fields
	// ===========================================================================

	/**
	 * Array for all data/properties.
	 * @var mixed[]
	 */
	private $data;

	/**
	 * Internal version number.
	 * @var string
	 */
	private $version = "1.0";



	// --- Magic functions
	// ===========================================================================

	/**
	 * Tries to get the value of a property.
	 *
	 * @param string $name Name of property.
	 *
	 * @return mixed Content of property.
	 */
	function __get($name) {
		if (array_key_exists($name, $this->data)) {
			return $this->data[$name];
		}

		$trace = debug_backtrace();
		trigger_error('Undefined key for __get(): '
		              .$name.' in '
		              .$trace[0]['file'].' at row '
		              .$trace[0]['line']
		, E_USER_NOTICE);

		return null;
	}

	/**
	 * Sets value of a property.
	 *
	 * @param string $name  Name of property.
	 * @param mixed  $value Value to save.
	 *
	 * @return void
	 */
	function __set($name, $value) {
		$this->data[$name] = $value;
	}

	/**
	 * Checks whether a property exits or not.
	 *
	 * @param string $name Name of property.
	 *
	 * @return bool true if property exists, false else.
	 */
	function __isset($name) {
		return isset($this->data[$name]);
	}

	/**
	 * Unsets / deletes a property.
	 *
	 * @param string $name Name of property.
	 *
	 * @return void
	 */
	function __unset($name) {
		if (isset($this->data[$name])) {
			unset($this->data[$name]);
		}
	}

	/**
	 * Overrides string representation.
	 *
	 * @return string
	 */
	function __toString() {
		return 'HiOrg API Class v'.$this->version.' by AM.WD - http://am-wd.de/';
	}



	// --- Constructor
	// ===========================================================================

	/**
	 * Initializes a new intance of HiOrgApi class.
	 *
	 * @param string $code HiOrg Login code (OV).
	 * @param string $key  API Key from HiOrg to use for EFS Interface.
	 *
	 * @return HiOrgApi
	 */
	function __construct($code = null, $key = null) {
		$this->code = $code;
		$this->key = ($key == null) ? null : trim($key);

		if (isset($_SERVER['HTTP_HOST'])) {
			$url = str_replace($_SERVER['DOCUMENT_ROOT'], 'http://'.$_SERVER['HTTP_HOST'], __DIR__);
			if (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] == "on") $url = str_replace("http", "https", $url);
		} else {
			trigger_error('Unable to resolve url for token validation. It has to be set manually (url_token).', E_USER_WARNING);
			// no warranty for this url to be valid!
			$url = 'http://dev.am-wd.de/sampler';
		}

		// URL stub for SSO actions.
		$this->url_sso = 'https://www.hiorg-server.de/logmein.php';

		// URL stub for EFS actions.
		$this->url_efs = 'https://www.hiorg-server.de/api/efs/';

		// Url to receive token (as text without any thing else)
		// for backend login (without redirect).
		$this->url_token  = $url.'/token.php';
		// Url to return to after successful login via SSO (not backend).
		$this->url_return = null;
		// Url to return to after user abort at SSO (not backend).
		$this->url_abort  = null;
		// Url to return to after user logout (not backend).
		$this->url_logout = null;

		// By default we will handle sso in backend actions.
		// => no redirect to SSO page.
		$this->sso_backend = true;

		// List of data we wish to know.
		$this->sso_data = array(
			  'name'       // Lastname
			, 'vorname'    // Firstname
			, 'kuerzel'    // ident code (short)
			, 'gruppe'     // sum of group ids
			, 'perms'      // comma seperated permissions
			, 'username'   // unique username for organisation
			, 'email'      // e-mail address
			, 'quali'      // id of qualification
			, 'telpriv'    // telephone number at home
			, 'teldienst'  // telephone number at work
			, 'handy'      // mobile phone number
			, 'user_id'    // unique user id of HiOrg Server
		);
	}



	// --- Public functions
	// ===========================================================================

	/**
	 * Checks whether API key is valid or not.
	 *
	 * @return bool true if key is valid, else false.
	 */
	public function check_key() {
		unset($this->last_error, $this->org_name, $this->org_id);
		if ($this->key == null || empty($this->key))
			return false;

		try {
			$res = $this->request_efs('checkapikey');
			// parse response
			if ($res->status == 'OK') {
				$this->org_name = $res->orga;
				$this->org_id = $res->hiorg_org_id;

				return true;
			} else {
				$this->last_error = $res->fehler;
				return false;
			}
		} catch (\Exception $ex) {
			$this->last_error = $ex->getMessage();
			return false;
		}
	}


	/**
	 * Collects all current and future operations.
	 *
	 * @return object[] array with operations or false on error.
	 */
	public function get_operations() {
		unset($this->last_error);
		if ($this->key == null || empty($this->key))
			return false;

		try {
			$res = $this->request_efs('geteinsaetze');
			
			if ($res->status == 'OK') {
				return $res->einsaetze;
			} else {
				$this->last_error = $res->fehler;
				return false;
			}
		} catch (\Exception $ex) {
			$this->last_error = $ex->getMessage();
			return false;
		}
	}

	/**
	 * Collects all information to an operation.
	 *
	 * @param int $id HiOrg Server id of the operation.
	 *
	 * @return object with operation details or false on error.
	 */
	public function get_op_details($id) {
		unset($this->last_error);
		if ($this->key == null || empty($this->key))
			return false;
		
		$req = new \stdClass();
		$req->id = intval($id);
		try {
			$res = $this->request_efs('geteinsatz', $req);
			
			if ($res->status == 'OK') {
				unset($res->status);
				unset($res->apiversion);
				unset($res->timestamp);

				return $res;
			} else {
				$this->last_error = $res->fehler;
				return false;
			}
		} catch (\Exception $ex) {
			$this->last_error = $ex->getMessage();
			return false;
		}
	}

	/**
	 * Collects all ressources with $filter in name or type.
	 *
	 * @param string $filter filter to search in ressources. Has to be at least 2 chars long.
	 * @param int    $begin  unix timestamp to search ressources available from (optional).
	 * @param int    $end    unix timestamp to search ressourcest available to (optional).
	 *
	 * @return object[] with all available ressources or false on error.
	 */
	public function get_ressources($filter, $begin = null, $end = null) {
		unset($this->last_error);
		if ($this->key == null || empty($this->key))
			return false;

		$req = new \stdClass();
		$req->filter = trim($filter);

		if ($begin != null && $begin > 0)
			$req->start = intval($begin);
		if ($end != null && $end > 0)
			$req->ende = intval($end);

		try {
			$res = $this->request_efs('geteinsatzmittel', $req);

			if ($res->status == 'OK') {
				return $res->freie_einsatzmittel;
			} else {
				$this->last_error = $res->fehler;
				return false;
			}
		} catch (\Exception $ex) {
			$this->last_error = $ex->getMessage();
			return false;
		}
	}

	/**
	 * Collects all personnel, which is not in holidays or on duty.
	 *
	 * @param int $begin  unix timestamp to search ressources available from (optional).
	 * @param int $end    unix timestamp to search ressourcest available to (optional).
	 *
	 * @throws \Exception because it's not implemented @HiOrg by now (v1.3 @ 13.05.2015)
	 *
	 * @return object[] with all available personnel or false on error.
	 */
	public function get_personnel($begin = null, $end = null) {
		throw new \Exception('Not implemented by now');

		unset($this->last_error);
		if ($this->key == null || empty($this->key))
			return false;

		$req = new \stdClass();

		if ($begin != null && $begin > 0)
			$req->start = intval($begin);
		if ($end != null && $end > 0)
			$req->ende = intval($end);

		try {
			$res = $this->request_efs('geteinsatzkraefte', $req);

			if ($res->status == 'OK') {
				return $res->freie_einsatzkraefte;
			} else {
				$this->last_error = $res->fehler;
				return false;
			}
		} catch (\Exception $ex) {
			$this->last_error = $ex->getMessage();
			return false;
		}
	}

	/**
	 * Writes working hours of personnel back to HiOrg server after duty.
	 *
	 * @param int $operation id of operation.
	 * @param object[] Array with personnel id, start time and endtime each as unix timestamp.
	 *
	 * @throws \Exception because it's not implemented @HiOrg by now (v1.3 @ 13.05.2015)
	 *
	 * @return bool true on success, else false.
	 */
	public function set_working_hours($operation, $personnel) {
		throw new \Exception('Not implemented by now');

		unset($this->last_error);
		if ($this->key == null || empty($this->key))
			return false;

		$req = new \stdClass();
		$req->einsatz_id = intval($operation);
		$req->helferstunden = json_encode($personnel);

		try {
			$res = $this->request_efs('sethelferstunden', $req);

			if ($res->status == 'OK') {
				return true;
			} else {
				$this->last_error = $res->fehler;
				return false;
			}
		} catch (\Exception $ex) {
			$this->last_error = $ex->getMessage();
			return false;
		}
	}



	public function get_sso_token($)








	// --- Private functions
	// ===========================================================================

	/**
	 * Performs the request to EFS API @HiOrg Server.
	 *
	 * @param string $action Name of action/function to call.
	 * @param object $data   Dataobject with additional params.
	 *
	 * @throws \RuntimeException if request performs an error.
	 *
	 * @return object with response.
	 */
	private function request_efs($action, $data = null) {
		if ($data == null)
			$data = new \stdClass();

		$data->apikey = $this->key;
		$data->action = $action;

		$post = array();
		foreach ($data as $key => $val) {
			$post[] = $key.'='.$val;
		}

		$ch = curl_init($this->url_efs);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
		curl_setopt($ch, CURLOPT_POST, true);
		curl_setopt($ch, CURLOPT_POSTFIELDS, implode('&', $post));

		$result = curl_exec($ch);

		if (curl_errno($ch)) {
			$msg = curl_error($ch);
			curl_close($ch);

			throw new \RuntimeException($msg);
		}

		curl_close($ch);

		return json_decode($result);
	}


	/**
	 * curl_exec_follow_location
	 *
	 * executes curl_exec without security bug of
	 * CURLOPT_FOLLOWLOCATION and open_basedir.
	 *
	 * @param ressource $ch         curl handler
	 * @param int       $maxredirs  max. number of redirects; default -1, unlimited
	 *
	 * @return mixed result of curl_exec
	 */
	private static function curl_exec_follow_location($ch, $maxredirs = -1) {
		if (ini_get('open_basedir') === '' && ini_get('safe_mode') === 'Off') {
			// Requirements fit to native follow_location
			curl_setopt($ch, CURLOPT_FOLLOWLOCATION, $maxredirs != 0);
			if ($maxredirs > 0)
				curl_setopt($ch, CURLOPT_MAXREDIRS, $maxredirs);
		} else {
			// need to use a workaround
			curl_setopt($ch, CURLOPT_FOLLOWLOCATION, false);
			$origin = $url = curl_getinfo($ch, CURLINFO_EFFECTIVE_URL);
			$cp = curl_copy_handle($ch);

			curl_setopt($cp, CURLOPT_HEADER, true);
			curl_setopt($cp, CURLOPT_FORBID_REUSE, false);
			curl_setopt($cp, CURLOPT_RETURNTRANSFER, true);

			$run = $maxredirs != 0;
			$mr = $maxredirs;

			while($run) {
				curl_setopt($cp, CURLOPT_URL, $url);
				$header = curl_exec($cp);

				if (curl_errno($cp)) {
					$run = false;
				} else {
					$code = curl_getinfo($cp, CURLINFO_HTTP_CODE);
					if ($code == 301 || $code == 302) {
						preg_match("/Location:(.*?)\n/i", $header, $matches);
						$url = trim(array_pop($matches));

						if (!preg_match("/^https?:/i", $url))
							$url = $origin.$url;
					} else {
						$run = false;
					}
				}

				// max jumps reached
				$mr--;
				if ($maxredirs > 0 && $mr == 0) {
					$run = false;
				}
			}

			curl_close($cp);

			if ($maxredirs == -1 || ($maxredirs > 0 && $mr > 0)) {
				curl_setopt($ch, CURLOPT_URL, $url);
			}
		}

		return curl_exec($ch);
	}

	/**
	 * is_available
	 *
	 * Tries to ping the given server for availabillity.
	 *
	 * @param string $server  Hostname/Server (URL) or IP Address
	 * @param int    $timeout Time for response in seconds
	 */
	private static function is_available($server, $timeout = 1) {
		$cmdWin = 'ping -n 1 -w '.($timeout * 1000).' '.$server;
		$cmdMac = '/sbin/ping -c 1 -t '.$timeout.' '.$server;
		$cmdLnx = '/bin/ping -c 1 -W '.$timeout.' '.$server;

		if (substr($_SERVER['DOCUMENT_ROOT'], 0, 1) == '/') {
			if (exec('uname') == 'Darwin') {
				$res = exec($cmdMac, $out, $ret);
			} else {
				$res = exec($cmdLnx, $out, $ret);
			}
		} else {
			$res = exec($cmdWin, $out, $ret);
		}

		return $ret == 0;
	}
}

?>
