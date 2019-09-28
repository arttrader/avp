<?php
require_once 'AuthController.php';
require_once 'videoDataClass.php';
require_once 'createVideoClass.php';
require_once 'createRndVideos.php';
require_once 'serverUtil.php';

$timeLimit = $ini_array['interval']; // set this value according to cron timing
$timeStampL = date("Y-m-d H:i:s");
$timeStamp = substr($timeStampL,-8);

$sql = "SELECT video_info_id, title, user_id, group_id, update_date, fileName,
  (select count(*) from video_info where startProduction=1 and start_time is null
	 and group_id=v.group_id) as wc
  FROM video_info v WHERE startProduction=1 and start_time is null
  order by update_date, wc";
$vidList = getDB($sql);
$nVidWaiting = count($vidList);

$serverCount = 0;
$startTime = microtime(true);
foreach ($vidList as $vidRec) {
	$vID = $vidRec['video_info_id'];
	$gID = $vidRec['group_id'];
	$uId = $vidRec['user_id'];
	$serverCount = getServerCount();
	echo "Server count = ".$serverCount."\n";
	// if server is available, start production
	if ($serverCount>0) {
		// make sure the video hasn't been produced by other server
		$sql = "select start_time,startProduction from video_info where video_info_id=".$vID;
		$result = getDB($sql);
		$rec = $result[0];
		if ($rec['startProduction'] && $uId && $gID) {
			$nVidWaiting--;
			$fname = $vidRec['fileName'];
			$fpath = 'users/user'.$gID.'/production/'.$fname.'.mp4';
			if (file_exists($fpath))
				unlink($fpath);
			$i = 0;
			$server = getNextServer();
//			var_dump($server);
			if (!$server) break; // no server available
			$serverID = $server['server_id'];
			$serverUrl = $server['url'];
			$serverType = $server['server_type'];

			if (strpos($serverUrl,'http://')===false)
				$serverUrl = 'http://'.$serverUrl.'/avp/';
			if ($serverType==0) {
				$desc = $timeStamp." Producing video here for ".$vID."   ".$uId;
				echo $desc."\n";
				$vidDef = new videoProdDataClass($vID);
				$vidCreator = new videoCreatorClass($serverID);
				$vidCreator->createVideo($vidDef);
			} else { // server is not this one
				$desc = $timeStamp." Submitting video ".$vID." to ".$serverUrl;
				echo $desc."\n";
				$sql = sprintf("call add_prod_log(%u,%u,%u,'%s')", $uId,$vID,$serverID,$desc);
				$result = getDB($sql,false);
				submitServer($serverUrl, $vID, $serverID);
			}
		} else echo "Video ID ".$vID." has no user or group ID\n";
	} else {
		echo "There's no free server\n";
		break; // if there's no free server, stop
	}
	$endTime = microtime(true);
	$elapsedTime = $endTime-$startTime;
	echo $timeStamp." Elapsed time = ".$elapsedTime."\n";
	// if more than a minute passed, a new process will start
	if ($elapsedTime > $timeLimit) {
		echo $timeStamp." Time limit reached!\n";
		break;
		//die($timeStamp." Time limit reached!\n");
	}
	sleep(3); // offset starting time to improve AITalk success rate
}
if (!$serverCount)
	$serverCount = getServerCount();
if ($nVidWaiting || $serverCount)
	echo "current vid waiting $nVidWaiting, server count $serverCount\n";

$n = $nVidWaiting-$serverCount;
if ($n>0) {
	$sql = "select avg(if(last_startup_time>30,last_startup_time,30)) avg_startup from production_servers;";
	$result = getDB($sql);
	if (count($result)) {
		// for each server allocation takes certain amount of time on average
		// so, take that into account and calculate number of servers being started
		// and subtract that number from current server deficit
		// if some servers are still needed, then should these servers be started
		$avgStartup = array_shift($result[0]);
		$timediff = round($n * $avgStartup / 60);
		$sql = "select sum(server_count) from server_management where TIMESTAMPDIFF(MINUTE,process_time,'$timeStampL')<$timediff AND TIMESTAMPDIFF(MINUTE,process_time,'$timeStampL')>0";
		//echo $sql."\n";
		$result = getDB($sql);
		$alreadyStarting = array_shift($result[0]);
		$n -= $alreadyStarting;
		echo "starting $n servers\n";
	}
	if ($n>0) {
//    if ($n>2) $n = 2; // limit the number of servers starting at once
		$sql = "call update_sm('$timeStampL',$n)";
		$result = getDB($sql,false);
		$desc = $timeStamp." AllocateMoreServer ".$n;
		$sql = sprintf("call add_prod_log(%u,%u,%u,'%s')", $uId,$vID,0,$desc);
		$result = getDB($sql,false);
		allocateMoreServer($n);
		$desc = $timeStamp." Server allocated ".$n;
		$sql = sprintf("call add_prod_log(%u,%u,%u,'%s')", $uId,$vID,0,$desc);
		$result = getDB($sql,false);
	}
} else if (!$nVidWaiting && $serverCount>0) {
	allocateLessServer($serverCount); // shut down unused servers
}


// for random movie generation
$sql = "SELECT * FROM job WHERE startMakeVideos=1 and start_time is null order by update_date";
$result = getDB($sql);
foreach ($result as $jRec) {
	$jID = $jRec['job_id'];
	echo "Creating random videos for ".$jID."\n";
	$rndVideo = new rndVideoGeneratorClass($jID);
	$rndVideo->prodVideos();
}




function isUnderQuota($userID) {
	$sql = "select permission,maxProdNum,usage_count from users u left join user_level l on u.permission=user_type_id left outer join monthly_usage m on u.user_id=m.user_id and year_idx=YEAR(now()) and month_idx=MONTH(now()) where u.user_id=".$userID;
	$result = getDB($sql);
	if (count($result)) {
		$rec = $result[0];
		$maxN = $rec['maxProdNum'];
		$cu = $rec['usage_count'];
		return $maxN>$cu;
	} else
		return false;
}

function getNextServer() {
	$sql = "call get_next_server();";
	$servers = getDB($sql);
	if (count($servers)) {
		$svrRec = $servers[0];
		return $svrRec;
	}
	return null;
}

function submitServer($baseUrl, $vID, $sID=0) {
	if (substr($baseUrl,-1)==="/")
		$url = $baseUrl.'submitVideoMake.php';
	else
		$url = $baseUrl.'/avp/submitVideoMake.php';
	$fields = array(
		'v' => $vID,
		's' => $sID
	);

//	echo "submitting video $vID to $sID ".$url."\n";

	$ch = curl_init($url);
	curl_setopt($ch,CURLOPT_POST, 1);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
	curl_setopt($ch, CURLOPT_POSTFIELDS, $fields);
	$result = curl_exec($ch);
	curl_close($ch);
}


function getProcesses() {
	$cmd = "ps ax | grep -i ffmpeg";
	$pStr = exec($cmd);
	if ($pStr) {
		$pArray = explode('\n', $pStr);
		//var_dump($pArray);

		return false;
	}

	return false;
}
