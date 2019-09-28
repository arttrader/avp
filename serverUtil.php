<?php
require_once 'AuthController.php';
require_once 'aws/aws-autoloader.php';

use Aws\Ec2\Ec2Client;

$sql = "SELECT * FROM server_info WHERE server_info_id=1";
$result = getDB($sql);
if (count($result)) {
	$serverinfo = $result[0];
	$region = $serverinfo['region'];
	$imageID = $serverinfo['ami_id']; // for new instances
	$keyPairName = $serverinfo['keyname'];
	$securityGroups = array($serverinfo['securitygroup']);
	$newInstanceSize = $serverinfo['instancetype'];
	$maxServers = $serverinfo['maxservernum'];
} else {
	die("Server info not found\n");
}

$sharedConfig = [
	'profile' => 'production',
	'region'  => $region,
	'version' => 'latest'
];
$serverType = 3;

$ec2Client = Ec2Client::factory($sharedConfig);
//$ec2 = new AmazonEC2();
//$ec2->set_hostname('ec2.ap-southeast-1.amazonaws.com');

$keyLocation = getenv('HOME') . "/.ssh/{$keyPairName}.pem";

// check the key pair, if not there create one
if (!file_exists($keyLocation)) {
	echo "creating key\n";
	$result = $ec2Client->createKeyPair(array(
    	'KeyName' => $keyPairName
	));
	echo "saving key\n".$result['keyMaterial']."\n";
	file_put_contents($keyLocation, $result['keyMaterial']);

	// Update the key's permissions so it can be used with SSH
	chmod($keyLocation, 0600);
}


function allocateMoreServer($n=1) {
	global $maxServers;

	echo "allocating $n servers...\n";
	$sql = "select * from production_servers where server_type>0 and server_type<10 and active=0 order by last_Process_time";
	$servers = getDB($sql);
	$ns = ($n>count($servers))?count($servers):$n;
	if (count($servers))
		for ($i=0; $i<$ns; $i++) {
			$server = $servers[$i];
			$sID = $server['server_id'];
			$isID = $server['instance_id'];
			startServer($sID, $isID);
		}
	if ($n>$ns) {
		$serverCount = getServerCount();
		for ($i=$ns; $i<$n; $i++)
			if ($serverCount<$maxServers) {
				launchNewInstance();
				updateDB();
			} else break;
	}
}

function allocateLessServer($n=1) {
	echo "stopping $n servers...\n";
	$sql = "select * from production_servers where active=1 and server_type>0 and server_type<10 and
	server_id not in (select prod_server_id from state_store where done=0) order by timestamp";
	$servers = getDB($sql);
	$n = (count($servers)<$n)?count($servers):$n;
	if ($n)
		for ($i=0; $i<$n; $i++) {
			$server = $servers[$i];
			$sID = $server['server_id'];
			$isID = $server['instance_id'];
			stopServer($sID, $isID);
		}
}

function startServer($sID, $isID='') {
	if (!$isID)
		$isID = getServerIstanceID($sID);
	if ($isID) {
		$startTime = microtime(true);
		$ins = startInstance($isID);
		$endTime = microtime(true);
		$timeTaken = $endTime - $startTime;
		if ($ins) {
			$pdns = $ins['PublicDnsName'];
			$ip = isset($ins['PublicIpAddress'])?$ins['PublicIpAddress']:'';
			$name = isset($ins['Tags'][0])?$ins['Tags'][0]['Value']:$pdns;
			$sql = "update production_servers set active=1,url='$ip',name='$name',last_startup_time=$timeTaken,last_start=now() where server_id=".$sID;
			$result = getDB($sql,false);
		}
	}
}

function stopServer($sID, $isID='') {
	if (!$isID)
		$isID = getServerIstanceID($sID);
	if ($isID) {
		$sql = "SELECT TIMESTAMPDIFF(MINUTE,last_start,now()) as elapsed FROM production_servers WHERE server_id=".$sID;
		$result = getDB($sql);
		if (count($result)) {
			$elapsed = $result[0]['elapsed'];
			// don't stop a server that hasn't been active for less than 3 minuites
			if ($elapsed>3) {
				stopInstance($isID);
				$sql = "update production_servers set active=0 where server_id=".$sID;
				$result = getDB($sql,false);
			}
		}
	}
}

function startInstance($instanceID) {
	global $ec2Client;

	$instanceIds = array($instanceID);
	echo "starting... ".$instanceID."\n";
	try {
		$result = $ec2Client->startInstances(array(
			'InstanceIds' => $instanceIds,
			'DryRun' => false,
		));
		// wait until running
		$ec2Client->waitUntil('InstanceRunning', array(
			'InstanceIds' => $instanceIds,
		));
		$result = $ec2Client->describeInstances(array(
			'InstanceIds' => $instanceIds,
		));
		$reservations = $result->getPath('Reservations');
		$res = $reservations[0];
		if (count($res)) {
			$instance = $res['Instances'][0];
			return $instance;
		} else
			return null;
	} catch (Exception $e) {
		echo 'Error: ', $e->getMessage(), "\n";
	}
}

function stopInstance($instanceID) {
	global $ec2Client;

	echo "stopping... ".$instanceID."\n";
	$result = $ec2Client->stopInstances(array(
		'DryRun' => false,
		'InstanceIds' => array($instanceID),
	));

	return $result;
}


function updateServerInfo($instanceID) {
	// Describe the now-running instance to get the public URL
	$result = $ec2Client->describeInstances(array(
		'InstanceIds' => array($instanceID),
	));
	return $result->getPath('Reservations');

	$res = $reservations[0];
	$instance = $res['Instances'][0];
	$st = $instance['State']['Name'];
	echo "update db for server id ".$id."\n";
	$ip = isset($ins['PublicIpAddress'])?$ins['PublicIpAddress']:'';
	$name = isset($ins['Tags'][0])?$ins['Tags'][0]['Value']:'';
	if ($st==="running") $ac = 1;
	else $ac = 0;
	$sql = "update production_servers set active=$ac,url='$ip' where instance_id=".$instanceID;
	$result = getDB($sql,false);
}


function launchNewInstance() {
	global $imageID, $ec2Client, $keyPairName, $securityGroups, $serverType, $newInstanceSize;

	echo "Launch new instance...\n";
	// Launch an instance with the key pair and security group
	$startTime = microtime(true);
	$result = $ec2Client->runInstances(array(
		'ImageId'        => $imageID,
		'MinCount'       => 1,
		'MaxCount'       => 1,
		'InstanceType'   => $newInstanceSize,
		'KeyName'        => $keyPairName,
		'SecurityGroups' => $securityGroups,
	));
	$endTime = microtime(true);
	$timeTaken = $endTime - $startTime;
	$instances = $result->getPath('Instances');
	$instanceId = $instances[0]['InstanceId'];
	echo "Instance createed ".$instanceId."\n";
	//var_dump($result);

	if (!isset($instanceId) || !$instanceId) return false;

	// Wait until the instance is launched
	$ec2Client->waitUntil('InstanceRunning', array(
		'InstanceIds' => array($instanceId),
	));
	// Describe the now-running instance to get the public URL
	$result = $ec2Client->describeInstances(array(
		'InstanceIds' => array($instanceId),
	));
	//var_dump($result);

	$reservations = $result->getPath('Reservations');
	$res = $reservations[0];
	$instance = $res['Instances'][0];
	$id = $instance['InstanceId'];
	$st = $instance['State']['Name'];
	$key = $instance['KeyName'];
	$sql = "select * from production_servers where instance_id='".$id."'";
	$servers = getDB($sql);
	if (!count($servers)) { // to make sure there's no duplicate
		$pdns = $instance['PublicDnsName'];
		$name = isset($ins['Tags'][0])?$ins['Tags'][0]['Value']:$pdns;
		$ip = isset($instance['PublicIpAddress'])?$instance['PublicIpAddress']:'';
		$sql = "insert into production_servers(instance_id,url,name,server_type,last_startup_time,last_start) values('$id','$ip','$name',$serverType,$timeTaken,now())";
		$result = getDB($sql,false);
	}
	return $id;
}


function getServerIstanceID($sID) {
	$sql = "select * from production_servers where server_id='".$sID."'";
	$servers = getDB($sql);
	if (count($servers))
		return $servers[0]['instance_id'];
	else
		return '';
}


function getServerCount() {
	$sql = "select count(*) as scount from production_servers
where url!='' and active=1 and server_id not in (select prod_server_id from state_store where done=0) and server_type>0";
	$servers = getDB($sql);
	if (count($servers)) {
		$rec = $servers[0];
		$serverCount = $rec['scount'];
	} else $serverCount = 0;

	return $serverCount;
}


function updateDB() {
	global $ec2Client,$serverType;

	$result = $ec2Client->describeInstances(); // get all instances in this region
	$reservations = $result->getPath('Reservations');
	//var_dump($reservations);
	foreach($reservations as $res) {
		$addchange = "";
		$ins = $res['Instances'][0];
		$id = $ins['InstanceId'];
		$st = $ins['State']['Name'];
		$key = $ins['KeyName'];
		$ip = isset($ins['PublicIpAddress'])?$ins['PublicIpAddress']:'';
		$pdns = $ins['PublicDnsName'];
		$name = isset($ins['Tags'][0])?$ins['Tags'][0]['Value']:$pdns;
		if ($name==='nowpress' or $st==='terminated') continue;
		echo $id." "." ".$st." ".$name." ".$ip." ".$key."\n";
		$sql = sprintf("select * from production_servers where instance_id='%s'",$id);
		$servers = getDB($sql);
		if (count($servers)) {
			$server = $servers[0];
			if (!$server['server_type']) continue; // exclude primary server
			if ($st==="running") {
				echo "update db for server id ".$id."\n";
				if ($ip!==$server['url']) $addchange = ",url='$ip'";
				$sql = sprintf("update production_servers set active=1".$addchange." where instance_id='%s'",$id);
				$result = getDB($sql,false);
			} else {
				if ($server['active']==1) {
					echo "update db for server id ".$id."\n";
					$sql = sprintf("update production_servers set active=0 where instance_id='%s'",$id);
					$result = getDB($sql,false);
				}
			}
		} else {
			echo "insert a new server ".$id."\n";
			$sql = sprintf("insert into production_servers(instance_id,url,name,server_type,last_start) values('%s','%s','%s',%d,now())",$id,$ip,$name,$serverType);
			$result = getDB($sql,false);
		}
	}
}
