<?php
require_once 'AuthController.php';
require_once 'videoDataClass.php';
require_once 'array_column.php';

error_reporting(E_ALL);

$userLevel = 0;

// authenticate the use of this system
// use this as a set on every page that requires authorization
session_start();
if (isset($_SESSION[$sessionName])) {
	$authController = unserialize($_SESSION[$sessionName]);
	if (is_object($authController) && $authController->checkLogin()) {
		$name = $authController->name;
		$userID = $authController->userId;
		$userGroup = $authController->userGroup;
		$userLevel = $authController->userLevel;
		if ($userLevel<2)
			header("Location:$rootUrl");
	} else
		header("Location:$rootUrl");
} else
	header("Location:$rootUrl");

$selfName = basename(__FILE__);

if (isset($_GET['p']))
	$pageNo = $_GET['p'];
else
	$pageNo = 1;

$mi = null;
$title = '';
$category = null; // not used
$genre = null;
$keywords = '';
$share = false;
$ffmpeg = $ini_array['ffmpeg'];
$musicObj = new musicDataClass(0,0,'','',$userGroup);

$items_per_page = 20;

if ($_POST) {
	$send = $_POST['send'];
	$title = $_POST['title'];
	$tags = isset($_POST['tags'])?$_POST['tags']:array();
	$share = isset($_POST['share'])?true:false;
	if ($send==="変更") {
		$mi = $_POST['mi'];
		$musicObj->loadData($mi);
		$musicObj->title = $title;
		$musicObj->category = null; // not used
		$musicObj->genre = null;
		$musicObj->tga = $tags;
		$musicObj->saveData();
		$message = "音楽データを変更しました";
	} else if ($send==="追加") {
		// need to check that category is also a shared one
		if ($share)
			if (isSharedCategory($category)) $musicObj->setGroupID(0);
			else $message = "共有カテゴリではないので、共有設定は無効です！";
		if (!empty($_FILES['file']) && $title) {
				$musicObj->upload($title,$_FILES['file'],$category,$genre,$tags);
				$mi = $musicObj->musicId;
		} else
			$message = "ファイル、タイトルが選択されてません";
	} else if ($send==="削除") {
		if (isset($_POST['delete'])) {
			if (is_array($_POST['delete'])) {
				foreach($_POST['delete'] as $i) {
					$musicObj->musicId = $_POST['resId'.$i];
					$musicObj->delete();
				}
			} else {
				$i = $_POST['delete'];
				$musicObj->musicId = $_POST['resId'.$i];
				$musicObj->delete();
			}
		}
	}
} else {
	if (isset($_GET['mi'])) {
		$mi = $_GET['mi'];
		$musicObj->loadData($mi);
		$share = ($musicObj->getGroupID()==0)?true:false;
	}
}

function getNumList() {
	global $userGroup;
	$sql = "select count(*) as n from music where (group_id=0 or group_id=".$userGroup.") and reuse=1";
	$result = getDB($sql);
//	var_dump($result);
	return $result[0]['n'];
}

function getMusicList($offset, $numItems) {
	global $userGroup;
    $sql = "select * from music where (group_id=0 or group_id=$userGroup) and reuse=1 order by music_id desc limit ".$offset.",".$numItems;
	$result = getDB($sql);
	return $result;
}

function getTags($mId) {
	$sql = "select tag from tags t join music_tag mt on t.tag_id=mt.tag_id where mt.music_id=".$mId;
	$result = getDB($sql);
	return $result;
}
?>
<!DOCTYPE html>
<html lang="ja">
<head>
	<meta Content-Type: text/html; charset=UTF-8 />
	<title>ミュージック管理</title>
	<link rel="stylesheet" href="style.css" type="text/css" />
	<link rel="stylesheet" href="css/pb-style.css">
	<link rel="stylesheet" href="http://code.jquery.com/ui/1.10.3/themes/smoothness/jquery-ui.css">
	<link rel="stylesheet" type="text/css" href="../js/css/jQuery.mb.miniAudioPlayer.min.css" title="style"  media="screen"/>
	<script src='//ajax.googleapis.com/ajax/libs/jquery/1.10.2/jquery.min.js'></script>
	<script src="//code.angularjs.org/1.4.5/angular.min.js"></script>
	<script type='text/javascript' src='../js/menu_app.js'></script>
	<script type="text/javascript" src="../js/jQuery.mb.miniAudioPlayer.min.js"></script>
	<style>
	.tags { font-size:12px; }
	</style>
</head>
<body>
<div id='cssmenu' ng-app="menuApp" ng-controller="menuController">
<ul>
  <li ng-repeat="mi in menus" ng-class="getClass(mi,menus)"><a href='{{ mi.link }}'><span>{{ mi.title }}</span></a>
    <ul>
    	<li ng-repeat="s in mi.subs" ng-class="getClass(s,mi.subs)"><a href='{{ s.link }}' target="{{ getTarget(s) }}"><span>{{ s.title }}</span></a>
    </ul>
  </li>
</ul>
</div>

<div class="user_name">こんにちは、<?=$name?>さん</div>
<div class="subtitle">ミュージック管理</div>
<span id="message"><?=$message?></span>

<div class="container_main">
<form class="select" method="POST" enctype="multipart/form-data">
<input type=hidden name="userid" value="<?=$userID?>" />

<table class="">
<tr style="text-align:left;">
<th>ファイル</th><th>タイトル</th></tr>
<tr>
<td style="width:50px;">
<?php if ($musicObj->fileName)
	echo "<span style='font-size:12px;'>".$musicObj->fileName."</span>";
else { ?>
<input type="file" name="file" accept=".mp3,.aac,.wav,audio/mp3,audio/wav,audio/aac" onchange="readURL(this);"><br>
音楽ファイルは10MB以下のものをご使用ください。
<?php } ?>
</td>
<td><input id="title" name="title" type="text" size="40" value="<?=$musicObj->title?>" /></td>
</tr>
<tr><td> </td></tr>
<tr><th style="text-align:left;">タグ</th></tr>
<tr><td> </td></tr>
<tr>
<td colspan=3>
<div class="tags">
<?php
	$sql = "select * from tags";
	$tags = getDB($sql);
	$i = 0;
	foreach ($tags as $tag) {
		$id = $tag['tag_id'];
		$name = $tag['tag'];
		if (array_search($id, $musicObj->tga)!==false) $check = " checked";
		else $check = '';
		echo '<input type="checkbox" id="t'.$id.'" name="tags[]" value="'
				.$id.'" '.$check.'><label for="t'.$id.'"><span></span></label>'.$name;
		echo "<input type='hidden' name='tagId$i' value='".$id."'>\n";
		$i++;
	}
?>
</div>
</td>
<td>
<?php
if ($mi) {
	if (!$share || $userLevel>10)
		echo '<input class="button" type="submit" name="send" value="変更">';
	else
		$message = '共有されてる音楽は変更できません！';
} else {
	echo '<input class="button" type="submit" name="send" value="追加">';
}
?>
</td></tr>
<tr><td>&nbsp;</td></tr>
<tr><td></td><td>
<?php if (!$mi && $userLevel>10) {
echo '<input type="checkbox" id="share" name="share">共有する<br><p style="font-size:12px;"><label for="share"><span></span></label>
他のユーザーと共有することができます。ただし、一度共有したものは変更ができません！</p>
<p style="font-size:10px;">選択されてるカテゴリが共有カテゴリでない場合は無効です</p>';
} ?>
</td></tr>
<tr><td style="text-align:left;"></td>
<td colspan=3 style="border:0;text-align:right;">
<a href="<?=$selfName?>"><input class="buttonclass" type="button" value="新規"></a> &nbsp; &nbsp;
<input class="buttonclass" type="submit" name="send" value="削除" /></td>
</tr>
</table>
<input type='hidden' name='mi' value='<?=$mi?>'>
<br>

<table class="kwtool" style="width:98%;font-size:14px;">
<tr><th></th><th>タイトル</th><th>長さ</th><th>タグ</th><th>編集</th>
<th><input type="checkbox" id="selectAll"><label for='selectAll'><span></span></label>削除</th></tr>
<?php
$rowCount = getNumList();
$pageCount = (int)ceil($rowCount/$items_per_page);
if (!$pageNo) {
	$pageNo = $pageCount;
}
// get reservation table data
$offset = ($pageNo - 1) * $items_per_page;
$limit = $items_per_page * $pageNo;
$musicList = getMusicList($offset, $items_per_page);
$folder = 'music';
if (count($musicList)) {
	$i=1;
	foreach ($musicList as $rec) {
		$id = $rec['music_id'];
		$tt = $rec['title'];
		$fi = $rec['filename'];
		$lg = $rec['length'];
		$sh = $rec['group_id'];
		$recs = getTags($id);
		$tags = implode(', ', array_column($recs,'tag'));

		echo "<tr>";
		echo '<td><a id="a'.$i.'" class="audio {mp3:"audio/'.$tt.'", autoPlay:false,showRew:false}" href="users/user'.$sh."/music/".$fi.'">'.$tt.'</a></td>';
		echo "<td>".$tt."</td>";
		echo "<td style='font-size:10px;text-align:right;'>".$lg."</td>";
		echo "<td style='font-size:10px;'>".$tags."</td>";
//		echo "<td>".($sh?'×':'○')."</td>";
		echo '<td><span class="small"><a href="'.$selfName.'?mi='.$id
			.'&p='.$pageNo.'"><img src="img/icon_edit.png"></a></span>';
		echo "<input type='hidden' name='resId".$i."' value='".$id."' /></td>";
		if ($sh)
			echo "<td><input type='checkbox' id='d$i' class='delbtn' name='delete[]' value='".$i."' /><label for='d$i'><span></span></label></td>";
		else
			echo "<td><input type='checkbox' id='d$i' class='delbtn' name='delete[]' disabled/></td>";
		echo "</tr>\n";

		$i++;
	}
}
?>
</table>
</form>

<table><tr><td style="font-size:11px">
<?php
for ($i=1; $i<=$pageCount; $i++) {
   if ($i == $pageNo) { // this is current page
       echo 'Page ' . $i . '&nbsp;';
   } else { // show link to other page
       echo '<a href="'.$selfName.'?p=' . $i . '">Page ' . $i . '</a>&nbsp;';
   }
}
?>
</td></tr></table>

<?php
session_write_close();
?>
</div><!--end container_main-->
<p class="footer_img"><br />Copyright © 2015-2016 J Hirota. All rights Reserved.</p>

<script type="text/javascript">
$(function() {
	$(".audio").mb_miniPlayer({
		width:300,
		inLine:false,
		id3:true,
		addShadow:false,
		pauseOnWindowBlur: false,
		downloadPage:null
	});

	$('#selectAll').click(function() {
		var setvar = $(this).prop('checked');
		$('.delbtn').each(function() {
			$(this).prop('checked', setvar);
		});
	});
});


// to put title from file name
function readURL(input) {
	if (input.files && input.files[0]) {
		var fname = input.files[0].name.replace(/\.[^/.]+$/, "");
		$('#title').val(fname);
	}
}
</script>

</body>
</html>
