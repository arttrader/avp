<?php
require_once 'videoDataClass.php';

if ($_POST) {
	$send = $_POST['send'];
	$mItemRaw = $_POST['mItemData'];
	$fid = isset($_POST['fi'])?$_POST['fi']:'';
	if (isset($_POST['fn'])) {
		parse_str($_POST['fn'], $files);
	}
} else if ($_GET) {
	$send = $_GET['send'];
	$mItemRaw = $_GET['mItemData'];
	$fid = isset($_GET['fi'])?$_GET['fi']:'';
	if (isset($_GET['fn'])) {
		parse_str($_GET['fn'], $files);
		//echo $_GET['fn'];
		//writeLog(serialize($files));
	}
} else {
	die('no parameters');
}

$mItemData = new mDataClass();
if ($mItemRaw) {
	$mItemData->decode($mItemRaw);
}

if (strpos($send,"dp")!==false) {
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
		$item = new articleDataClass(null,$id,$title,'',null);
		$mItemData->push($item);
	}
} else if ($send==="upload") {
	// this has to be changed for csv file reading
	$fname = str_replace(' ','',$files['name']);
	//writeLog("upload file ".$fname);
	$item = new articleDataClass(0,0,'','');
//	$item->upload($fname,$files,0,0,'');
	$mItemData->push($item);
}


$n = $mItemData->count();
if ($n) {
	$exHtml = '<table>';
	for ($i=0; $i<$n; $i++) {
		$item = $mItemData->offsetGet($i);
		$exHtml .= '<tr class="articlecell" id="mv'.$i
			.'" style="width:620px;padding:0;margin:0;"><td style="width:200px;font-size:11px;">'
			.mb_strimwidth($item->title,0,42,'...','UTF-8').'</td><td style="font-size:10px;">'
			.mb_strimwidth($item->getText(),0,60,'...','UTF-8')
			.'</td><td><input type="button" class="delArticleBtn" name="send" value="x" id="dl'.$i.'"></td></tr>';
	}
	$exHtml .= "</table>\n";
} else $exHtml = '';



$result = array(
	'exhtml' => $exHtml,
	'mitemdata' => $mItemData->encode()
	);
echo json_encode($result);
