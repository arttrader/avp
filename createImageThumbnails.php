<?php
require_once 'AuthController.php';
//require_once 'videoDataClass.php';
require_once('php_image_magician.php');


$userID = 1002;
$userLogin = 'tester3';


$sql = "select * from image";
$recs = getDB($sql);

echo "processing ".count($recs)." images\n";

foreach ($recs as $rec) {
	$id = $rec['image_id'];
	$file = $rec['filename'];
	$group = $rec['group_id'];
	$src = 'user'.$group.'/image/'.$file;
	$thumb = pathinfo($file, PATHINFO_FILENAME).'_th.jpg';
	$dest = 'user'.$group.'/image/'.$thumb;
	
	list($width, $height) = getimagesize($src);
	if ($width>1280 || $height>720) {
		echo "Adjusting image size ".$src."\n";
		if (adjustImage($src, $src, 1280, 720))
			echo "Image adjustment success! ".$src."\n";
	}
	echo "Making thumbnail as ".$dest."\n";
	if (createThumb($src, $dest)) {
		$sql = "update image set thumbnail='".$thumb."' where image_id=".$id;
		$result = getDB($sql,false);
	} else
		echo "Error making thumb for ".$dest."\n";

}

function adjustImage($src, $dest, $desired_width, $desired_height) {
	try {
		$magObj = new imageLib($src);
		if ($magObj) {
			$height = $magObj->getOriginalHeight();
			$width = $magObj->getOriginalWidth();
			$magObj->resizeImage($desired_width, $desired_height, 4);
			$magObj->saveImage($dest);
			$magObj = null;
		}
/*		$imagick = new Imagick($src);
		$height = $imagick->getImageHeight();
		$width = $imagick->getImageWidth();
		$desired_height = floor($height * ($desired_width / $width));
		$imagick->resizeImage($desired_width, $desired_height);
		$imagick->writeImage($dest); */
		return true;
	}
	catch(Exception $e) {
		echo 'Error when resizing a image: ' . $e->getMessage();
		return false;
	}
	return false;
}

function createThumb($src, $dest) {
	$desired_width = 100;
	try {
		$magObj = new imageLib($src);
		if ($magObj) {
			$height = $magObj->getOriginalHeight();
			$width = $magObj->getOriginalWidth();
			$desired_height = floor($height * ($desired_width / $width));
			$magObj->resizeImage($desired_width, $desired_height, 4);
			$magObj->saveImage($dest);
			$magObj = null;
		}
/*		$imagick = new Imagick($src);
		$imagick->thumbnailImage(100, 0);
		$imagick->writeImage($dest); */
		return true;
	}
	catch(Exception $e) {
		echo 'Error when creating a thumbnail: ' . $e->getMessage();
		return false;
	}
	return false;
}
