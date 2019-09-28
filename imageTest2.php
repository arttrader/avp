<?php
require_once('php_image_magician.php');

$fileFolder = 'images';
$destFolder = 'movieImages';
//$width = 1280;
//$height = 720;
$width = 1280;
$height = 720;

$files = scandir($fileFolder);

$i = 1;
foreach($files as $fName) {
	if (preg_match('/\w+\.(png|jpg|bmp)$/i', $fName)) {
		$id = sprintf("%03d", $i);
		echo 'Processing '.$fileFolder.'/'.$fName.'<br>';
		$magicianObj = new imageLib($fileFolder.'/'.$fName);
		$magicianObj -> resizeImage($width, $height, 4);
		// *** Add watermark to bottom right, 50px from the edges
//  		$magicianObj -> addWatermark('bear.png', 'br', 0, 80);
		$magicianObj -> addCaptionBox('b', 50, 0, '#000', 50);
		echo 'Saving to '.$destFolder.'/img'.$id.'png<br>';
		 $magicianObj -> saveImage($destFolder.'/img'.$id.'.png');
		 $magicianObj = null;
		$i++;
	}
}



?>