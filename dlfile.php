<?php
require_once 'AuthController.php';
require_once 'videoDataClass.php';

if (isset($_POST['id'])) {
	$id = $_POST['id'];
	$videoInfo = new videoProdDataClass($id);
	$file_name = $videoInfo->getBasename();
//	$fname = mb_convert_encoding($file_name, 'Shift_JIS', 'UTF-8');
	$fname = $file_name;
	header('Content-Description: File Transfer');
	header('Content-Type: video/mp4');
	header('Content-Disposition: attachment; filename="'.$fname.'"');
	header('Content-Length: '.filesize($videoInfo->getFilePath()));
	ob_clean();
	flush();
	readfile($videoInfo->getFilePath());
	$sql = "update video_info set downloaded=1 where video_info_id=".$id;
	$res = getDB($sql,false);
}
