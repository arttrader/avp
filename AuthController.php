<?php
// AVP only version 2.0
// no longer using Zend stuff
include_once 'ini/avptool.php';
$appversion = empty($ini_array['appversion'])?1:$ini_array['appversion'];
$debugmode = $ini_array['debugmode'];
$levelRequired = $ini_array['appversion'];
$rootUrl = $ini_array['rooturl'];
$sessionName = $ini_array['sessionname'];
if ($debugmode)
	ini_set( 'display_errors', 1 );
else
	error_reporting(0);

// chanage environment parameters, so you don't have to log in as often
ini_set('session.gc_maxlifetime', 3600 * 24 * 10);
$session_expiration = time() + 3600 * 24 * 2; // +2 days
session_set_cookie_params($session_expiration);

class AuthController {
	public $userId;
	public $userName;
	public $name;
	public $email;
	public $userType;
	public $userLevel;
	public $userGroup;
	public $daysremaining;

	public function __construct() {
		$this->userId = "";
	}

	public function login($login_name, $login_pass, $ip, $pip, $agent, $ref) {
		global $levelRequired;

		$loginID = substr($login_name,0,20); // 20 character max
		$pass = substr($login_pass,0,20); // 20 character max
		$sql = sprintf("call user_effective_for(%u,'%s','%s');",
						$levelRequired, $loginID, $pass);
		$rows = getDB($sql);
		//var_dump($rows);
		if ($rows) {
			$row = array_shift($rows);
			$rows = null;
			$this->daysremaining = $row['days_until_expire'];
			if ($this->daysremaining>-2) {
				$this->userId = $row['user_id'];
				$this->name = $row['name'];
				$this->email = $row['email'];
				$this->userLevel = $row['permission'];
				$this->userType = $row['user_type'];
				$this->userName = $loginID;
				$this->userGroup = $row['group_id'];
				$this->putStorage($this->userId);
				$sql = sprintf("call save_log(10,'%s','%s','%s','%s','%s','%s','%s',%u);",
							$loginID,'login',$pass,$ip,$pip,$agent,$ref,$this->userId);
				$rs = getDB($sql,false);

				return true;
			} else
				echo "<font color='red'>認証失敗：あなたのアカウントは期限切れです</font><br><br>";
		} else {
			echo "<font color='red'>認証失敗：アカウントが無効、またはユーザーIDかパスワードが間違っております</font><br><br>";
		}
		$sql = sprintf("call save_log(10,'%s','%s','%s','%s','%s','%s','%s',%u);",
						$loginID,'login failure',$pass,$ip,$pip,$agent,$ref,$this->userId);
		$rs = getDB($sql,false);
		return false;
	}

	public function checkLogin() {
		global $rootUrl;
		// 認証を確認する
		if ($this->userId==$this->getStorage())
			return true;
		else
			return false;
	}

	public function logout() {
		// ストレージと認証情報を破棄する
		$this->putStorage("");
	}

	function putStorage($value) {
		$_SESSION['authInfo'] = $value;
	}

	function getStorage() {
		return $_SESSION['authInfo'];
	}
}


function getDB($sql,$getResult=true,&$params=null) {
	global $ini_array, $dbAdapter, $debugmode;

	$server = $ini_array['dbhost'];
	$dbname = $ini_array['database'];
	$username = $ini_array['dbuser'];
	$password = $ini_array['dbpass'];

	getNewDBAdapter();
	try {
/*		if (!isset($dbAdapter)) {
			$dbAdapter = new PDO("mysql:host=$server;dbname=$dbname;charset=utf8",$username,$password);
			$dbAdapter->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
		} */
		$data = array();
		if ($getResult) {
			$result = $dbAdapter->query($sql);
			foreach($result as $row)
				$data[] = $row;
		} else {
			 $stmt = $dbAdapter->prepare($sql);
//			 $dbAdapter->beginTransaction();
			 $stmt->execute($params);
//			 $dbAdapter->commit();
		}

	} catch (PDOException $e) {
		echo $e->getMessage()."\n";
		if ($debugmode) echo $sql."\n\n";
	}

	return $data;
}

function getNewDBAdapter() {
	global $ini_array, $dbAdapter, $debugmode;

	$server = $ini_array['dbhost'];
	$dbname = $ini_array['database'];
	$username = $ini_array['dbuser'];
	$password = $ini_array['dbpass'];

	try {
		if (!isset($dbAdapter)) {
			$dbAdapter = new PDO("mysql:host=$server;dbname=$dbname;charset=utf8",$username,$password);
			$dbAdapter->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
		}
	} catch (PDOException $e) {
		echo $e->getMessage()."\n";
		if ($debugmode) echo $sql."\n\n";
		return false;
	}
	return true;
}

function getLastInsertedID() {
	global $dbAdapter;

	$lastID = $dbAdapter->lastInsertId();
	return $lastID;
}

function escapeQute($strdata) {
	return str_replace("'", "\'", $strdata);
}
