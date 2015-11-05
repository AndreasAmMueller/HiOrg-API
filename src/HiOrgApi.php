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
 * @see        https://bitbucket.org/BlackyPanther/hiorg-api/wiki/Home
 * @version    v1.0-20151105 | semi stable; no testcases
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
		trigger_error('Undefined key for __get(): "'
		              .$name.'" in '
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
	 * @param string $code   HiOrg Login code (OV).
	 * @param string $apikey API Key from HiOrg to use for EFS Interface.
	 *
	 * @return HiOrgApi
	 */
	function __construct($code = null, $apikey = null) {
		$this->code = $code;
		$this->apikey = ($apikey == null) ? null : trim($apikey);

		if (isset($_SERVER['HTTP_HOST'])) {
			$tokenurl = str_replace($_SERVER['DOCUMENT_ROOT'], 'http://'.$_SERVER['HTTP_HOST'], __DIR__);
			if (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] == "on")
				$tokenurl = str_replace("http", "https", $tokenurl);

			$tokenurl .= '/token.php';
		} else {
			$trace = debug_backtrace();
			trigger_error('Unable to resolve url for token (url_token).'
			              .' Using fallback. In '
			              .$trace[0]['file'].' at row '
			              .$trace[0]['line']
			, E_USER_NOTICE);
			// no warranty for this url to be valid!
			$tokenurl = 'http://dev.am-wd.de/sampler/token.php';
		}

		// URL stub for SSO actions.
		$this->url_sso = 'https://www.hiorg-server.de/logmein.php';

		// URL stub for EFS actions.
		$this->url_efs = 'https://www.hiorg-server.de/api/efs/';

		// Url to receive token (as text without any thing else)
		// for backend login (without redirect).
		$this->url_token  = str_replace('localhost', '127.0.0.1', $tokenurl);
		// Url to return to after successful login via SSO (not backend).
		$this->url_return = null;
		// Do not enforce user to login via HiOrg. If there is no active session
		// (not logged in) return to this url (not backend).
		$this->url_abort  = null;
		// Url to return to after user logout (not backend).
		$this->url_logout = null;
		// Should the method redirect or return redirect url
		$this->sso_autoredirect = true;

		// By default we will handle sso in backend actions.
		// => no redirect to SSO page.
		$this->sso_backend = true;
		// Logout from SSO after we received user data
		$this->sso_autologout = true;
		// token to authenticate at HiOrg Server
		$this->sso_token = null;

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
	public function efs_check_apikey() {
		unset($this->last_error, $this->org_name, $this->org_id);
		if ($this->apikey == null || empty($this->apikey))
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
	public function efs_get_operations() {
		unset($this->last_error);
		if ($this->apikey == null || empty($this->apikey))
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
	public function efs_get_op_details($id) {
		unset($this->last_error);
		if ($this->apikey == null || empty($this->apikey))
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
	public function efs_get_ressources($filter, $begin = null, $end = null) {
		unset($this->last_error);
		if ($this->apikey == null || empty($this->apikey))
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
	public function efs_get_personnel($begin = null, $end = null) {
		throw new \Exception('Not implemented by now');

		unset($this->last_error);
		if ($this->apikey == null || empty($this->apikey))
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
	public function efs_set_working_hours($operation, $personnel) {
		throw new \Exception('Not implemented by now');

		unset($this->last_error);
		if ($this->apikey == null || empty($this->apikey))
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



	/**
	 * Gets token for datarequest or link for redirect to SSO.
	 * 
	 * If this class works as backend for an application (sso_backend => true)
	 * The username and password have to be set as parameter and the method will
	 * use the url_token to receive the authentification token (for data-request).
	 * Otherwise the redirect url will be build and returned or redirected to SSO page.
	 * 
	 * @param string $user     Name of user at HiOrg Server.
	 * @param string $password Password for user at HiOrg Server.
	 * 
	 * @return mixed token or redirect url on success or false on error
	 */
	public function sso_get_token($user = null, $password = null) {
		if ($this->sso_backend) {
			if ($user == null || $password == null) {
				$this->last_error = 'HiOrg API is working as backend login.'
					.' Therefore username and password have to be set as parameter.';
				return false;
			}

			if (!self::is_available('hiorg-server.de')) {
				$this->last_error = 'HiOrg API page not available.';
				return false;
			}

			$uri = array();
			$uri[] = 'ov='.$this->code;
			$uri[] = 'weiter='.urlencode($this->url_token);
			$uri[] = 'getuserinfo='.urlencode(implode(',', $this->sso_data));

			$post = array();
			$post[] = 'username='.$user;
			$post[] = 'password='.$password;
			$post[] = 'submit=Login';

			$ch = curl_init($this->url_sso.'?'.implode('&', $uri));
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
			curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
			curl_setopt($ch, CURLOPT_POST, true);
			curl_setopt($ch, CURLOPT_POSTFIELDS, implode('&', $post));

			$result = self::curl_exec_follow_location($ch);

			if (curl_errno($ch)) {
				$this->last_error = curl_error($ch);

				curl_close($ch);
				return false;
			}

			curl_close($ch);
			
			$result = self::mb_trim($result);
			$lines = explode("\n", $result);

			$token = trim($lines[(count($lines)-1)]);

			$this->token = $token;

			return $token;
		} else {
			$uri = array();
			$uri[] = 'ov='.$this->code;
			$uri[] = 'weiter='.urlencode($this->url_return);

			if ($this->sso_data != null && count($this->sso_data) > 0)
				$uri[] = 'getuserinfo='.urlencode(implode(',', $this->sso_data));

			if ($this->url_abort != null)
				$uri[] = 'silent='.urlencode($this->url_abort);

			$redirect = $this->url_sso.'?'.implode('&', $uri);

			if ($this->sso_autoredirect) {
				header('Location: '.$redirect);
				exit;
			}

			return $redirect;
		}
	}

	/**
	 * Gets requested user information from HiOrg Server.
	 * 
	 * After a valid and successful login via SSO we have a token.
	 * With this token we can receive the previously requested user information.
	 * 
	 * @param string $token Token we got from HiOrg Server to receive user infos.
	 * 
	 * @return mixed array with user infos on success, false on error.
	 */
	public function sso_get_data($token = null) {
		if ($token == null)
			$token = $this->token;

		$this->token = $token;
		if ($token == null || empty($token)) {
			$this->last_error = 'Token is not set.';
			return false;
		}

		$ch = curl_init($this->url_sso.'?token='.$token);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

		$result = curl_exec($ch);

		if (curl_errno($ch)) {
			$this->last_error = curl_error($ch);

			curl_close($ch);
			return false;
		}

		curl_close($ch);
		
		$result = self::mb_trim($result);
		
		if (mb_substr($result, 0, 2) != 'OK') {
			$this->last_error = 'Token is invalid.';
			return false;
		} else {
			$encoded = mb_substr($result, 3);
			$serialized = base64_decode($encoded);
			$data = unserialize($serialized);

			if ($this->sso_autologout) {
				$this->sso_logout($token);
				$data['login_expires'] = time();
			}

			if ($data['ov'] != $this->code) {
				$this->last_error = 'Wrong HiOrg Login Code (OV) returned.';
				return false;
			} else {
				return $data;
			}
		}
	}

	/**
	 * Performs a clean logout from HiOrg Server for a given token.
	 * 
	 * @param string $token Token to define what to logout.
	 * 
	 * @return bool true on success, false on error.
	 */
	public function sso_logout($token = null) {
		if ($token == null)
			$token = $this->token;

		if ($token == null || empty($token)) {
			$this->last_error = 'Token is not set.';
			return false;
		}

		$ch = curl_init($this->url_sso.'?logout=1&token='.$token);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
		curl_exec($ch);

		if (curl_errno($ch)) {
			$this->last_error = curl_error($ch);

			curl_close($ch);
			return false;
		}

		curl_close($ch);
		unset($this->token);

		if (!$this->sso_backend && $this->sso_logout != null && !empty($this->sso_logout)) {
			header('Location: '.$this->sso_logout);
			exit;
		}

		return true;
	}


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

		$data->apikey = $this->apikey;
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
	 * Executes curl_exec without security bug of
	 * CURLOPT_FOLLOWLOCATION and open_basedir.
	 *
	 * @param resource $ch         curl handler
	 * @param int      $maxredirs  max. number of redirects; default -1, unlimited
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
	 * Tries to connect to given url.
	 *
	 * @param string $url     Hostname/Server (URL) or IP Address
	 * @param int    $timeout Time for response in milliseconds
	 * 
	 * @return bool true on successful connect, false on error (timeout)
	 */
	private static function is_available($url, $timeout = 1000) {
		$ch = curl_init($url);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
		curl_setopt($ch, CURLOPT_CONNECTTIMEOUT_MS, $timeout);

		curl_exec($ch);

		if (curl_errno($ch)) {
			curl_close($ch);
			return false;
		}

		return true;
	}

	/**
	 * Trims string to content.
	 * 
	 * @param string $str  string to trim.
	 * @param string $trim chars to search for.
	 * 
	 * @return string trimed string.
	 */
	private static function mb_trim($str, $trim = '\s') {
		return preg_replace('/^['.$trim.']*(?U)(.*)['.$trim.']*$/u', '\\1', $str);
	}
}



?>