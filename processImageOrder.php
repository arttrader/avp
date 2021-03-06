<?php
require_once 'AuthController.php';
require_once 'videoDataClass.php';

if ($_POST) {
	$send = $_POST['send'];
	$mItemRaw = $_POST['mItemData'];
	$fid = isset($_POST['fi'])?$_POST['fi']:'';
	$groupID = isset($_POST['ug'])?$_POST['ug']:0;
	$category = isset($_POST['ct'])?$_POST['ct']:0;
} else if ($_GET) {
	$send = $_GET['send'];
	$mItemRaw = $_GET['mItemData'];
	$fid = isset($_GET['fi'])?$_GET['fi']:'';
	$groupID = isset($_POST['ug'])?$_POST['ug']:0;
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
} else if (strpos($send,"up")!==false) {
	$indx = substr($send, 2);
	if ($indx>0) {
		$tmpobj = $mItemData->offsetGet($indx-1);
		$mItemData->offsetSet($indx-1, $mItemData->offsetGet($indx));
		$mItemData->offsetSet($indx, $tmpobj);
	}
} else if (strpos($send,"dn")!==false) {
	$indx = substr($send, 2);
	if ($indx<$mItemData->count()) {
		$tmpobj = $mItemData->offsetGet($indx+1);
		$mItemData->offsetSet($indx+1, $mItemData->offsetGet($indx));
		$mItemData->offsetSet($indx, $tmpobj);
	}
} else if (strpos($send,"tp")!==false) {
	$indx = substr($send, 2);
	if ($indx>0) {
		$tmpobj = $mItemData->offsetGet($indx);
		$mItemData->offsetUnset($indx);
		$mItemData->unshift($tmpobj);
	}
} else if (strpos($send,"bt")!==false) {
	$indx = substr($send, 2);
	if ($indx<$mItemData->count()) {
		$tmpobj = $mItemData->offsetGet($indx);
		$mItemData->offsetUnset($indx);
		$mItemData->push($tmpobj);
	}
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
//		writeLog($i." path=".$item->getThumbPath());
		$exHtml .= "<img class='imagecell' id='mv".$i
			."' style='max-width:100px;max-height:62px;' src='".$item->getThumbPath()."'>"
			."<input type='button' class='delImageBtn' name='send' value='x' id='dl".$i."'> ";
		$col++;
		if ($col>9) {
			$exHtml .= "<br>";
			$col = 1;
		}
	}
//	$exHtml .= "\n";
//	writeLog($exHtml);
}
$result = array(
	'exhtml' => $exHtml,
	'mitemdata' => $mItemData->encode()
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
