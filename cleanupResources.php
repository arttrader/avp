<?php
require_once 'AuthController.php';
require_once 'videoDataClass.php';

// file clean up

$timeStampL = date("Y-m-d H:i:s");

$sql = "select * from users group by group_id";
$groups = getDB($sql);

foreach($groups as $group) {
	$dir = "users/user".$group['group_id']."/image/";
	$files = scandir($dir);
	if ($files!==false) {
		foreach($files as $file) {
			if (is_dir($file)) continue;
			
			$filename = str_replace('_th','',$file);
			//echo $file."\n";
			$sql = "select image_id from image where filename='".$filename."'";
			$recs = getDB($sql);
			if (!count($recs)) {
				echo $file."not found in db, so delete ".$file."\n";
				unlink($dir.$file);
			}
		}
	}
}

$sql = "select image_id,filename,thumbnail,group_id from image where reuse=0 and image_id not in (select image_id from video_image) and image_id not in (select image_id from job_image)";
$images = getDB($sql);

foreach ($images as $imageRec) {
	echo "not used so delete ".$imageRec['filename']."\n";
	$dir = "users/user".$imageRec['group_id']."/image/";
	try {
		unlink($dir.$imageRec['filename']);
		unlink($dir.$imageRec['thumbnail']);
	}
	catch(Exception $e) {
		echo $e->getMessage();
	}
	$sql = "delete from image where image_id=".$imageRec['image_id'];
	$images = getDB($sql,false);
}


// need to delete video files that are downloaded and more than
// a week old, or that user has more than 100 videos
$sql = "SELECT * FROM avp.video_info WHERE downloaded=1 and not isnull(production_date) and DATEDIFF(CURDATE(),production_date)>7";
$vlist = getDB($sql);
deleteVideos($vlist);

$sql = "SELECT group_id,count(*) as nvideos FROM avp.video_info WHERE not isnull(production_date) group by group_id";
$groups = getDB($sql);
if (count($groups)) {
	foreach ($groups as $rec) {
		$id = $rec['group_id'];
		$n = $rec['nvideos'];
		if ($n>100) {
			echo "More than 100 videos for group id ".$id."\n";
			$sql = "SELECT * FROM avp.video_info WHERE not isnull(production_date) AND group_id=$id ORDER BY production_date LIMIT ".($n-100);
			$vlist = getDB($sql);
			deleteVideos($vlist, $id);
		}
	}
}

function deleteVideos($vlist, $groupID=null) {
	foreach ($vlist as $v) {
		$vid = $v['video_info_id'];
		$update = $v['video_info_id']; // not to change update date
		$gID = $groupID?$groupID:$v['group_id'];
		$fname = "users/user".$gID."/production/".$v['fileName'].".mp4";
		echo "Deleting video ".$v['fileName']."\n";
		if (file_exists($fname))
			unlink($fname);
		$sql = "UPDATE video_info set start_time=null,production_date=null,production_status=null,update_date='".$update."' WHERE video_info_id=".$vid;
		$result = getDB($sql,false);
	}
}