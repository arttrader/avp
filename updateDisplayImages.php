<?php
require_once 'AuthController.php';
require_once 'videoDataClass.php';

if ($_POST) {
	$send = $_POST['send'];
	$mItemRaw = $_POST['mItemData'];
	$fid = isset($_POST['fi'])?$_POST['fi']:'';
	$groupID = isset($_POST['ug'])?$_POST['ug']:0;
	$category = isset($_POST['ct'])?$_POST['ct']:0;
	$type = isset($_POST['tp'])?$_POST['tp']:0;
} else {
	writeLog("Error: no parameters");
	die('no parameters');
}
//writeLog("post: ".var_export($_POST,true));

$mItemData = new mDataClass();
if ($mItemRaw) $mItemData->decode($mItemRaw);

if ($send==="upload") { // has to be first because of up command
	if ($debugmode) writeLog("upload command");
	if (isset($_FILES['file'])) {
		$fileArr = rearangeFiles($_FILES["file"]);
		foreach ($fileArr as $file) {
			$title = pathinfo($file['name'], PATHINFO_FILENAME);
			$fname = str_replace(' ','',$file['name']);
			$item = new imageDataClass(0,0,'','',$groupID);
			$item->reuse = 0;
			if ($item->upload($title,$file,$category,0,'')) {
				$mItemData->push($item);
				$mItemData->imageChanged = true;
				if ($debugmode) writeLog("upload complete! [".$fname."] id=".$item->imageId);
			}
		}
	}
} if ($send==="upo") { // upload to the first item
	if ($debugmode) writeLog("upload to 1st command");
	$type = 1;
	if (isset($_FILES['file'])) {
		$fileArr = rearangeFiles($_FILES["file"]);
		$file = $fileArr[0];
		$title = pathinfo($file['name'], PATHINFO_FILENAME);
		$fname = str_replace(' ','',$file['name']);
		$item = new imageDataClass(0,0,'','',$groupID);
		$item->type = $type;
		$item->reuse = 0;
		if ($item->upload($title,$file,$category,0,'')) {
//			$mItemData->offsetUnset(0);
			$mItemData->unshift($item);
			$mItemData->imageChanged = true;
			if ($debugmode) writeLog("upload first complete! [".$fname."] id=".$item->imageId);
		}
	}
} if ($send==="upe") { // upload to the last item
	if ($debugmode) writeLog("upload to last command");
	$type = 2;
	if (isset($_FILES['file'])) {
		$fileArr = rearangeFiles($_FILES["file"]);
		$file = $fileArr[0];
		$title = pathinfo($file['name'], PATHINFO_FILENAME);
		$fname = str_replace(' ','',$file['name']);
		$item = new imageDataClass(0,0,'','',$groupID);
		$item->type = $type;
		$item->reuse = 0;
		if ($item->upload($title,$file,$category,0,'')) {
//			$mItemData->pop();
			$mItemData->push($item);
			$mItemData->imageChanged = true;
			if ($debugmode) writeLog("upload end complete! [".$fname."] id=".$item->imageId);
		}
	}
} else if (strpos($send,"dp")!==false) {
	$indx = substr($send, 2);
	$tmpobj = $mItemData->offsetGet($fid);
	$mItemData->offsetUnset($fid);
	$mItemData->offsetInsert($indx, $tmpobj);
} else if (strpos($send,"dl")!==false) {
	$indx = substr($send, 2);
	if ($mItemData->count()>1)
		$mItemData->offsetUnset($indx);
	else
		$mItemData->clear();
} else if ($send==="clear") {
	$mItemData->clear();
} else if ($send==="select") {
	if ($neworadd)
		$mItemData->clear();
	foreach ($itemChosen as $i) {
		$id = $_POST['imageId'.$i];
		$title = $_POST['title'.$i];
		$file = $_POST['file'.$i];
		$item = new imageDataClass(null,$id,$title,$file);
		$mItemData->push($item);
	}
}

$n = $mItemData->count();

$exHtml = '';
if ($n>0) {
	$col = 1;
	for ($i=0; $i<$n; $i++) {
		$item = $mItemData->offsetGet($i);
		if (!$type || $item->type==$type) {
	//		writeLog($i." path=".$item->getThumbPath());
			$exHtml .= "<img class='imagecell' id='mv".$i
				."' style='max-width:100px;max-height:62px;' src='".$item->getThumbPath()."'>"
				."<input type='button' class='delImageBtn' name='send' value='x' id='dl".$i."'> ";
			$col++;
/*			if (!$type && $col>9) {
				$exHtml .= "<br>";
				$col = 1;
			} */
		}
	}
//	writeLog($exHtml);
}
$result = array(
	'exhtml' => $exHtml,
	'mitemdata' => $mItemData->encode(),
	'type' => $type
	);
$json = json_encode($result);
echo $json;
//writeLog($json);


function rearangeFiles(&$file_post) {
    $file_ary = array();
    $file_count = count($file_post['name']);
    $file_keys = array_keys($file_post);

    for ($i=0; $i<$file_count; $i++) {
        foreach ($file_keys as $key) {
            $file_ary[$i][$key] = $file_post[$key][$i];
        }
    }

    return $file_ary;
}
