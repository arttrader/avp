<?php
require_once 'videoDataClass.php';

if ($_POST) {
	$send = $_POST['send'];
	$mItemRaw = $_POST['mItemData'];
	$fid = isset($_POST['fi'])?$_POST['fi']:'';
} else if ($_GET) {
	$send = $_GET['send'];
	$mItemRaw = $_GET['mItemData'];
	$fid = isset($_POST['fi'])?$_POST['fi']:'';
} else die('no parameters');

$mItemData = new mDataClass();
if ($mItemRaw) $mItemData->decode($mItemRaw);

if (strpos($send,"dp")!==false) {
	$indx = substr($send, 2);
	$tmpobj = $mItemData->offsetGet($fid);
	$mItemData->offsetUnset($fid);
	$mItemData->offsetInsert($indx, $tmpobj);
} else if (strpos($send,"dl")!==false) {
	$indx = substr($send, 2);
	$mItemData->offsetUnset($indx);
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
		$mItemData->->clear();
	foreach ($itemChosen as $i) {
		$id = $_POST['imageId'.$i];
		$title = $_POST['title'.$i];
		$file = $_POST['file'.$i];
		$item = new imageDataClass(null,$id,$title,$file);
		$mItemData->imageData->push($item);
	}
}

$n = $mItemData->count();
$exHtml = '';
$row = 1;
for ($i=0; $i<$n; $i++) {
	$item = $mItemData->offsetGet($i);
	$exHtml .= '<img class="imagecell" id="mv'.$i
		.'" style="max-width:100px;max-height:62px;" src="'.$item->getFilePath().'">';
	$row++;
	if ($row>9) {
		$exHtml .= "<br>\n";
		$row = 1;
	}
}
$exHtml .= "\n";
$result = array(
	'exhtml' => $exHtml,
	'mitemdata' => $mItemData->encode()
	);
echo json_encode($result);
