<?php
require_once 'AuthController.php';
require_once 'aws/aws-autoloader.php';
require_once 'serverUtil.php';

ini_set('html_errors', false);
$currentImageID = $imageID;

updateDB();

// replace AMI

// create a new AMI from current primary satellite (server_type=2)
$sql = "select instance_id,name from production_servers where server_type=2";
$servers = getDB($sql);
if (count($servers)) {
	$insID = $servers[0]['instance_id'];
	$name = $servers[0]['name'];
	
	echo "getting image ".$name."\n";
	// deregister AMI
	$result = $ec2Client->describeImages([
		'ImageIds' => [$currentImageID]
	]);
	$images = $result->getPath('Images');
	if (!count($images)) {
		$result = $ec2Client->describeImages([
			'Filters'=>[
				[
					'Name'=>'name',
					'Values'=>[$name]
				]
			]
		]);
	}
	$imageID = '';
	if (count($images)) {	
//		var_dump($images);
		$imageID = $images[0]['ImageId'];
	}
	if (!$imageID) die("Couldn't get image ID\n");
//	die("test done\n");
	
	echo "deregister image...\n";
	$result = $ec2Client->deregisterImage([
		'ImageId' => $imageID
	]);
	
	// create AMI
	echo "create AMI...$name\n";
	$result = $ec2Client->createImage([
		'InstanceId' => $insID,
		'Name' => $name,
		'Description' => $name." Image"
	]);
	
	echo "getting image id...\n";
	try {
		$result = $ec2Client->describeImages([
			'Filters'=>[
				[
					'Name'=>'name',
					'Values'=>[$name]
				]
			]
		]);
		$images = $result->getPath('Images');
		$imageID = $images[0]['ImageId'];
	} catch (Exception $e) {
		echo "Error: ", $e->getMessage(), "\n";
		$imageID = '';
	}
	
	echo "New AMI ".$imageID."\n";
	
	if ($imageID) { // update image
		$sql = "UPDATE server_info SET ami_id='$imageID' WHERE server_info_id=1";
		$result = getDB($sql,false);
		
/*		$filename = "./ini/avptool.php";
		$lines = file($filename);
		$n = count($lines);
		for ($i=0; $i<$n; $i++) {
			if (strpos($lines[$i],'amiid')!==false)
				$lines[$i] = "'amiid' => '$imageID', // to create satellite server\n";
		}
		print_r($lines, true);
		file_put_contents($filename, print_r($lines, true)); */
	}
}




// terminate all child servers
$sql = "select instance_id from production_servers where server_type>2 and server_type<10";
$servers = getDB($sql);
$instanceIDs = array();
foreach ($servers as $server) {
	$insID = $server['instance_id'];
	array_push($instanceIDs, $insID);
	echo "adding to terminating list... ".$insID."\n";
}
if (count($instanceIDs)) {
	echo "terminating...\n";
	$result = $ec2Client->terminateInstances(array(
		'DryRun' => false,
		'InstanceIds' => $instanceIDs
	));
}


// update DB
// delete those servers from state_store table
$sql = "delete from state_store where prod_server_id in (select server_id from production_servers where server_type>2 and server_type<10)";
$result = getDB($sql,false);

// delete those servers from production_servers
$sql = "delete from production_servers where server_type>2 and server_type<10";
$result = getDB($sql,false);

