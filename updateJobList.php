<?php
require_once 'AuthController.php';

require_once 'videoDataClass.php';

$items_per_page = 20;


$userGroup = isset($_GET['ug'])?$_GET['ug']:1;
$pageNo = isset($_GET['p'])?$_GET['p']:1;
$selfName = isset($_GET['sn'])?$_GET['sn']:'manageRndVideoProd.php';
$newWindow = isset($_GET['nw'])?false:true;
$dels = isset($_GET['dl'])?$_GET['dl']:array();
$prods = isset($_GET['pr'])?$_GET['pr']:array();


function getNumList() {
	global $userGroup;
	$sql = "select count(*) as n from job where isnull(group_id) or group_id=".$userGroup;
	$result = getDB($sql);
	return $result[0]['n'];
}

function getJobList($offset, $numItems) {
	global $userGroup;
    $sql = "select job_id,job_name,useTextVoice,startMakeVideos,start_time,completed,completion_time,file_prefix,group_id,
    (select count(*) from job_image i where j.job_id=i.job_id and image_type=1) opImage,
    (select count(*) from job_image i where j.job_id=i.job_id and image_type=2) endImage,
    cmVideo,endVideo,
    (select count(*) from job_article n where j.job_id=n.job_id) articlec 
from job j where group_id=$userGroup order by update_date desc limit ".$offset.",".$numItems;
	$result = getDB($sql);
	return $result;
}

function getWaitingNum($userGroup) {
	$sql = "select count(*) as n from video_info where (startProduction=1 or (start_time is not null and production_date is null)) and group_id!=".$userGroup;
	$result = getDB($sql);
	return $result[0]['n'];
}

function getProdDesc($ps) {
	switch (abs($ps)) {
	case 1:
		$status = "ランダム動画制作完了";
		break;
	default:
		$status = "未制作";
	}
	if ($ps<0) {
		switch($ps) {
		case -1: $status = "エラー：記事が設定されてません。"; break;
		case -2: $status = "エラー：画像がみつかりません。"; break;
		case -3: $status = "エラー：BGMがみつかりません。"; break;
		default: $status = "エラー";
		}
	}
	
	return $status;
}
?>
<table id="videoList" class="kwtool" style="width:98%;">
<tr><th>ID</th><th>ジョブ</th><th>記事</th><th>ボイス</th>
	<th class="masterTooltip" title="オープニングまたはイントロ画像">OI</th><th class="masterTooltip" title="エンディング画像">EI</th>	
	<th class="masterTooltip" title="オープニングまたはイントロ動画">OV</th><th class="masterTooltip" title="エンディング動画">EV</th>
	<th class="masterTooltip" title="制作状況">PS</th><th>編集</th><th>削除</th><th>制作</th><th></th><th></th></tr>
<?php
$rowCount = getNumList();
$pageCount = (int)ceil($rowCount/$items_per_page);
if (!$pageNo) {
	$pageNo = $pageCount;
}
// get image table data
$offset = ($pageNo - 1) * $items_per_page;
$limit = $items_per_page * $pageNo;
$nwv = getWaitingNum($userGroup);
if ($nwv==0) $nwv='';
$vList = getJobList($offset, $items_per_page);
if (count($vList)) {
	$i=1;
	foreach ($vList as $rec) {
		$id = $rec['job_id'];
		$tt = mb_strimwidth($rec['job_name'],0,65,'...','UTF-8');
		$ac = $rec['articlec'];
		$tv = $rec['useTextVoice'];
		$oi = $rec['opImage'];
		$ei = $rec['endImage'];
		$ov = $rec['cmVideo'];
		$ev = $rec['endVideo'];
		$sp = $rec['startMakeVideos'];
		$pd = $rec['start_time'];
		$ps = $rec['completed'];
		$pdesc = getProdDesc($ps);
		$dd = $rec['completion_time'];
		$fn = $rec['file_prefix'];
		$gi = $rec['group_id']?$rec['group_id']:0;
		if ($pd) $class = 'class="grayclass"';
		if ($pd & !$dd) $pdesc .= "中";
		else $class = '';
		echo "<tr $class>";
		echo "<td>".$id."</td>";
		echo "<td class='masterTooltip'".((strlen($tt)>77)?" style='font-size:10px;'":"")." title='ファイル名：".$fn."'>".$tt."</td>";
		echo "<td>".($ac?"○":"×")."</td>";
		echo "<td>".($tv?"○":"×")."</td>";
		echo "<td>".($oi?"○":"×")."</td>";
		echo "<td>".($ei?"○":"×")."</td>";
		echo "<td>".($ov?"○":"×")."</td>";
		echo "<td>".($ev?"○":"×")."</td>";
		echo "<td class='masterTooltip' title='".$pdesc."'".(($ps<0)?' style="color:red;"':'').">".(($ps!='null')?$ps:'')."</td>";
		if ($pd & !$dd) {
			if ($pd) $imgclass = 'style="opacity:0.4;" ';
			else $imgclass = '';
			echo '<td><span class="bt small"><img '.$imgclass
				.'src="img/icon_edit.png"></span>';
			echo "<input type='hidden' name='resId".$i."' value='".$id."'></td>";
			echo "<td><input class='delete' id='del".$i."' type='checkbox' name='delete[]' value='".$i
				."' disabled></td>";
			echo "<td><input class='produce' id='prod".$i."' type='checkbox' name='produce[]' value='".$i
				."' disabled></td>";
			echo "<td></td><td>制作中</td></tr>\n";
		} else {
			echo '<td><span class="bt small"><a href="'.$selfName.'?mi='
				.$id.'&p='.$pageNo.'"'.($newWindow?" target='_new'":"").'><img src="img/icon_edit.png"></a></span>';
			echo "<input type='hidden' name='resId".$i."' value='".$id."'></td>";
			echo "<td><input class='delete' id='del".$i."' type='checkbox' name='delete[]' value='".$i."' ".(in_array($i,$dels)?' checked':'')."><label for='del$i'><span></span></label></td>";
			if ($sp)
				echo "<td><input class='produce' id='prod".$i."' type='checkbox' name='produce[]' value='".$i
					."' disabled></td>";
			else
				echo "<td><input class='produce' id='prod".$i."' type='checkbox' name='produce[]' value='".$i."'><label for='prod$i'><span></span></label></td>";
			if ($dd)
				echo "<td><span class='done' name='".$gi.'/'.$fn."'>完了</span></td><td></td>";
			else
				if ($sp) 
					if ($nwv)
						echo "<td></td><td class='masterTooltip' title='現在他のユーザーの".$nwv."本の動画を製作中または待機中'>待機中 <span style='font-size:9px;'>$nwv</span></td>";
					else
						echo "<td></td><td class='masterTooltip' title='現在動画製作順番待ち'>待機中 <span style='font-size:9px;'>$nwv</span></td>";
				else echo "<td></td><td></td>";
			echo "</tr>\n";
		}
		$i++;
	}
} else {
	echo "<tr><td colspan=13 style='color:gray;border:0;background-color:transparent;'>現在製作中の動画はありません</td></tr>";
}
?>
</table>
<!-- table for pagenation -->
<table><tr><td style="font-size:11px">
<?php
$pageCount = (int)ceil($rowCount/$items_per_page);
for ($i = 1; $i <= $pageCount; $i++) {
   if ($i == $pageNo) { // this is current page
       echo 'Page ' . $i . '&nbsp;';
   } else { // show link to other page
       echo '<a href="'.$selfName.'?p='.$i.'">Page '.$i.'</a>&nbsp;';
   }
}
?>
</td></tr></table>