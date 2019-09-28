<?php
require_once 'AuthController.php';
require_once 'videoDataClass.php';

$items_per_page = 20;


$userGroup = isset($_GET['ug'])?$_GET['ug']:1;
$pageNo = isset($_GET['p'])?$_GET['p']:1;
$selfName = isset($_GET['sn'])?$_GET['sn']:'manageVideoProduction.php';
$newWindow = isset($_GET['sn'])?false:true;
$dels = isset($_GET['dl'])?$_GET['dl']:array();
$prods = isset($_GET['pr'])?$_GET['pr']:array();


function getNumList() {
	global $userGroup;
	$sql = "select count(*) as n from video_info where isnull(group_id) or group_id=".$userGroup;
	$result = getDB($sql);
	return $result[0]['n'];
}

function getVideoList($offset, $numItems) {
	global $userGroup;
    $sql = "select video_info_id,title,useTextVoice,startProduction,ifnull(start_time,'') stime,production_status,ifnull(production_date,'') pdate,downloaded,fileName,group_id,(select count(*) from video_narration n where v.video_info_id=n.video_info_id) articlec, 
		(select count(*) from video_image i where v.video_info_id=i.video_info_id) imagec, 
		(select count(*) from video_music m where v.video_info_id=m.video_info_id) musicc,
        (select count(*) from video_video c join video vv on c.video_id=vv.video_id where vv.video_type=1 and v.video_info_id=c.video_info_id) videoc,
        (select count(*) from video_video e join video vv on e.video_id=vv.video_id where vv.video_type=2 and v.video_info_id=e.video_info_id) videoe
from video_info v where group_id=$userGroup order by update_date desc,video_info_id desc limit ".$offset.",".$numItems;
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
		$status = "画像処理";
		break;
	case 2:
		$status = "音声合成";
		break;
	case 3:
		$status = "音声とBGMのミックス";
		break;
	case 4:
		$status = "画像から動画を作成";
		break;
	case 5:
		$status = "スクロールテキストの追加";
		break;
	case 6:
		$status = "アノーテーション、ウォーターマーク追加";
		break;
	case 7:
		$status = "動画にオーディオトラックを追加";
		break;
	case 8:
		$status = "オープニング動画を追加";
		break;
	case 9:
		$status = "エンディング動画を追加";
		break;
	case 10:
		$status = "動画ファイルを制作";
		break;
	default:
		$status = "未制作";
	}
	if ($ps<0) {
		if ($ps<-10)
			$status = "最終段階で不明なエラー";
		else
			$status = $status."中にエラー";
	}
	
	return $status;
}
?>
<table id="videoList" class="kwtool" style="width:98%;">
<tr><th>ID</th><th>タイトル</th><th>記事</th><th>ボイス</th><th>画像</th><th>音楽</th><th class="masterTooltip" title="オープニング動画">OP</th><th class="masterTooltip" title="エンディング動画">EN</th><th class="masterTooltip" title="制作状況">PS</th><th>編集</th><th>削除</th><th>制作</th><th></th><th class="masterTooltip" title="完成動画ダウンロード">DL</th></tr>
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
$vList = getVideoList($offset, $items_per_page);
if (count($vList)) {
	$i=1;
	foreach ($vList as $rec) {
		$id = $rec['video_info_id'];
		$tt = mb_strimwidth($rec['title'],0,65,'...','UTF-8');
		$ac = $rec['articlec'];
		$tv = $rec['useTextVoice'];
		$ic = $rec['imagec'];
		$mc = $rec['musicc'];
		$cm = $rec['videoc'];
		$ve = $rec['videoe'];
		$sp = $rec['startProduction'];
		$pd = $rec['stime'];
		$ps = $rec['production_status']?$rec['production_status']:'';
		$pdesc = getProdDesc($ps);
		$dd = $rec['pdate'];
		$dl = $rec['downloaded']?1:0;
		$fn = $rec['fileName'];
		$gi = $rec['group_id']?$rec['group_id']:0;
		if ($pd && !$dd) {
			$class = 'class="grayclass"';
			$pdesc .= "中";
		} else $class = '';
		if ($dd) $pdesc .= "完了";
		echo "<tr $class>";
		echo "<td>".$id."</td>";
		echo "<td class='masterTooltip'".((strlen($tt)>77)?" style='font-size:10px;'":"")." title='ファイル名：".$fn."'>".$tt."</td>";
		echo "<td>".($ac?"○":"×")."</td>";
		echo "<td>".($tv?"○":"×")."</td>";
		echo "<td>".($ic?$ic:"×")."</td>";
		echo "<td>".($mc?"○":"×")."</td>";
		echo "<td>".($cm?"○":"×")."</td>";
		echo "<td>".($ve?"○":"×")."</td>";
		echo "<td class='masterTooltip' title='".$pdesc."'".(($ps<0)?' style="color:red;"':'').">".(($ps!='null')?$ps:'')."</td>";
		if ($pd && !$dd) {
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
					."' checked><label for='prod$i'><span></span></label></td>";
			else
				echo "<td><input class='produce' id='prod".$i."' type='checkbox' name='produce[]' value='".$i."'".(in_array($i,$prods)?' checked':'').($ic?'':' disabled')."><label for='prod$i'><span></span></label></td>";
			if ($dd)
				echo "<td><span class='playVideoBtn' name='".$volume.$userGroup."/production/".$fn.".mp4'><img class='masterTooltip' title='クリックして動画をチェック' src='img/small_play_button.gif'></span></td><td><input class='dlBtn' id='dwl".$i."' type='button' alt='".$id."' name='".$fn
					."' value='DL'><input type='hidden' id='dlded".$i."' value='".$dl."'></td>";
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