<?php
// modified for the new schema with multiple memberships
include_once 'ini/twttool.php';
$appversion = empty($ini_array['appversion'])?1:$ini_array['appversion'];
$debugmode = $ini_array['debugmode'];
$levelRequired = $ini_array['appversion'];
$rootUrl = $ini_array['rooturl'];
if (!$debugmode) error_reporting(0);

require_once $ini_array['zendpath'].'library/Zend/Loader/StandardAutoloader.php';
$loader = new Zend\Loader\StandardAutoloader(array('autoregister_zf' => true));
// Register the "User" namespace:
$loader->registerNamespace('User', __FILE__ . '/../library/User');
// Register with spl_autoload:
$loader->register();

use Zend\Db\Adapter\Adapter;
use Zend\Db\Adapter\Platform;
use Zend\Db\Adapter\Driver;
use Zend\Db\Adapter\Driver\DriverInterface;
use Zend\Db\ResultSet\ResultSet;

//use Zend\Di;
//use Zend\Mvc\Controller\Plugin\Redirect;


class AuthController {
	public $userId;
	public $userName;
	public $name;
	public $email;
	public $userType;
	public $userLevel;
	public $daysremaining;
	
	public function __construct() {
		$this->userId = "";
	}

	public function login($login_name, $login_pass, $ip, $pip, $agent, $ref) {
		global $levelRequired;
		
		$loginID = substr($login_name,0,20); // 20 character max
		$pass = substr($login_pass,0,20); // 20 character max
		$sql = sprintf("call keyword_tool.user_effective_for(%u,'%s','%s');",
						$levelRequired, $loginID, $pass);
		$rows = getDB($sql);
		//var_dump($rows);
		if ($rows) {
			$row = array_shift($rows);
			$this->daysremaining = $row['days_until_expire'];
			if ($this->daysremaining>-2) {
				$this->userId = $row['user_id'];
				$this->loginId = $row['login_id'];
				$this->name = $row['name'];
				$this->email = $row['email'];
				$this->userLevel = $row['permission'];
				$this->userType = $row['user_type'];
				$this->userName = $loginID;
				$this->putStorage($this->userId);
				$sql = sprintf("call keyword_tool.save_log1(10,'%s','%s','%s','%s','%s','%s','%s',%u);",
							$loginID,'login',$pass,$ip,$pip,$agent,$ref,$this->userId);
				$rs = getDB($sql);

				return true;
			} else
				echo "<font color='red'>認証失敗：あなたのアカウントは期限切れです</font><br><br>";
		} else {
			echo "<font color='red'>認証失敗：アカウントが無効、またはユーザーIDかパスワードが間違っております</font><br><br>";
		}
		$sql = sprintf("call keyword_tool.save_log(10,'%s','%s','%s','%s','%s','%s','%s');",
						$loginID,'login failure',$pass,$ip,$pip,$agent,$ref);
		$rs = getDB($sql);
		return false;
	}

	public function checkLogin() {
		global $rootUrl;
		// Clear authorization
		if ($this->userId==$this->getStorage())
			return true;
		else
			return false;
	}

	public function logout() {
		// delete storage and authorization
		$this->putStorage("");
	}

	function putStorage($value) {
		$_SESSION['authInfo'] = $value;
	}

	function getStorage() {
		return $_SESSION['authInfo'];
	}
}



function getDB($sql) {
	global $ini_array, $dbAdapter, $pdo;

	if (!isset($dbAdapter))
		$dbAdapter = new Adapter(array(
					'driver' => 'Mysqli',
					'database' => $ini_array['database'],
					'username' => $ini_array['dbuser'],
					'password' => $ini_array['dbpass'],
					'hostname' => $ini_array['dbhost']
				));
/*		$dbAdapter = Zend_Db_Adapter_Pdo_Mysql(array(
					'dbname' => $ini_array['database'],
					'username' => $ini_array['dbuser'],
					'password' => $ini_array['dbpass'],
					'host' => $ini_array['dbhost']
				)); */

	$stmt = $dbAdapter->query($sql);
	$result = $stmt->execute();
	$result->buffer();
	//$dbAdapter = null;
	if ($result->count()>0) {
		$results = new ResultSet();
		$records = $results->initialize($result)->toArray();
		
		return $records;
	}
	else return array(); // empty array
}

