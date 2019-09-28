<?php
require_once 'AuthController.php';
require_once 'videoDataClass.php';

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
$category = 0;
$keywords = '';
$share = false;
$videoObj = new videoDataClass(0,0,'','',0,$userGroup);

$items_per_page = 20;

if ($_POST) {
	$send = $_POST['send'];
	$title = $_POST['title'];
	$category = $_POST['category'];
//	$keywords = $_POST['keywords'];
	$type = $_POST['type'];
	$share = isset($_POST['share'])?true:false;
	if ($send==="変更") {
		$mi = $_POST['mi'];
		$videoObj->loadData($mi);
		$videoObj->title = $title;
		$videoObj->category = $category;
		$videoObj->keywords = $keywords;
		$videoObj->type = $type;
		$videoObj->saveData();
		$message = "動画データを変更しました";		
	} else if ($send==="追加") {
		if (!empty($_FILES['file']) && $title) {
			if ($share) 
				if (isSharedCategory($category)) $videoObj->setGroupID(0);
				else $message = "共有カテゴリではないので、共有設定は無効です！";
			//echo "Uploading file...<br>";
			$fname = $_POST['fname'];
			$uploadOK =
				$videoObj->upload($title,$_FILES['file'],$category,$keywords,$type,$fname);
		} else
			$message = "タイトル、ファイルが選択されてません";
	} else if ($send==="削除") {
		if (isset($_POST['delete'])) {
			if (is_array($_POST['delete'])) {
				foreach($_POST['delete'] as $i) {
					$vId = $_POST['resId'.$i];
					$videoObj->loadData($vId);
					$videoObj->delete();
				}
			} else {
				$i = $_POST['delete'];
				$vId = $_POST['resId'.$i];
				$videoObj->loadData($vId);
				$videoObj->delete();
			}
		}
	}
} else {
	if (isset($_GET['mi'])) {
		$mi = $_GET['mi'];
		$videoObj->loadData($mi);
		$share = ($videoObj->getGroupID()==0)?true:false;
	}
}


function getNumList() {
	global $userGroup;
	$sql = "select count(*) n from video where group_id=0 or group_id=".$userGroup;
	$result = getDB($sql);
	return $result[0]['n'];
}

function getVideoList($offset, $numItems) {
	global $userGroup;
    $sql = "select video_id,title,filename,v.category,video_type,v.group_id,c.name cname from video v left join category c on v.category=c.category_id where v.group_id=0 or v.group_id=$userGroup order by video_id desc limit ".$offset.",".$numItems;
	$result = getDB($sql);
	return $result;
}
?>
<html lang="ja">
<head>
	<meta Content-Type: text/html; charset=UTF-8 />
	<link rel="stylesheet" href="style.css" type="text/css" />
	<link rel="stylesheet" href="css/pb-style.css">
	<link rel="stylesheet" href="http://code.jquery.com/ui/1.10.3/themes/smoothness/jquery-ui.css">
	<title>動画管理</title>
	<script src='//ajax.googleapis.com/ajax/libs/jquery/1.10.2/jquery.min.js'></script>
	<script src="//code.angularjs.org/1.4.5/angular.min.js"></script>
	<script type='text/javascript' src='../js/menu_app.js'></script>
</head>
<body>
<div id='cssmenu' ng-app="menuApp" ng-controller="menuController">
<ul>
  <li ng-repeat="mi in menus | filter:lessThanE('level',<?=$userLevel?>)" ng-class="getClass(mi,menus)"><a href='{{ mi.link }}'><span>{{ mi.title }}</span></a>
    <ul>
    	<li ng-repeat="s in mi.subs | filter:lessThanE('level',<?=$userLevel?>)" ng-class="getClass(s,mi.subs)"><a href='{{ s.link }}' target="{{ getTarget(s) }}"><span>{{ s.title }}</span></a>
    </ul>
  </li>
</ul>
</div>

<div class="user_name">こんにちは、<?=$name?>さん</div>
<div class="subtitle">動画管理</div>
<span id="message"><?=$message?></span>

<div class="container_main">
<form class="select" method="POST" enctype="multipart/form-data">
<input type=hidden name="userid" value="<?=$userID?>" />

<table class="">
<tr style="text-align:left;">
<th>ファイル</th><th>ファイル名</th><th>カテゴリー</th><th>タイプ</th></tr>
<tr style="vertical-align:text-top;">
<td>
<?php if (!$videoObj->fileName) { ?>
<input type="file" name="file" accept=".mp4,video/mp4" onchange="readURL(this);">
<div style="font-size:10px;">
動画は<span style="font-weight: bold;color:blue;">H.264 720p(1280x720) 30fpsのmp4</span>ファイルが使用できます。<br>
それ以外の動画ファイルは現在サポートされてませんのでご注意下さい。<br>
ファイル名は半角ローマ数字だけのものを御使用ください。<br>
出来ればファイルサイズは6MB以下にしてください。<br>
20MB以上のファイルはアップロードできません。</div>
<?php } ?>
</td>
<td style="width:180px;">
<?php if ($videoObj->fileName)
	echo "<span style='font-size:12px;'>".$videoObj->fileName."</span>";
else { ?>
	<input type="text" name="fname" size=30><br>
	<font style="font-size:10px;">半角ローマ字と数字だけを推奨します！</font>
<?php } ?>
</td>
<td style="text-align:center;">
<select name="category">
<?php getCategoryList($videoObj->category); ?>
</select>
</td>
<td style="text-align:center;">
<select name="type">
<option value='1'<?=($videoObj->type==1)?' selected':''?>>オープニング</option>
<option value='2'<?=($videoObj->type==2)?' selected':''?>>エンディング</option>
<option value='3'<?=($videoObj->type==3)?' selected':''?>>その他</option>
</select>
</td></tr>
<tr style="text-align:left;"><th></th><th>タイトル</th></tr>
<tr><td>
<?php if ($mi) { ?>
<video id="thum" style="max-width:300px;max-height:240px;" controls>
    <source src="<?=$mi?$videoObj->getFilePath():''?>">
</video>
<?php } else { ?>
<video id="thum" style="max-width:300px;max-height:240px;" controls />
<?php } ?>
</td>
<td colspan=2 style="vertical-align:text-top;"><input id="title" name="title" type="text" size="40" value="<?=$videoObj->title?>"></td>
<td style="vertical-align:text-top;">
<?php
if ($mi) {
	if (!$share || $userLevel>10)
		echo '<input class="button" type="submit" name="send" value="変更">';
	else
		$message = '共有されてる動画は変更できません！';
} else {
	echo '<input class="button" type="submit" name="send" value="追加">';
}
?>
</td></tr>
<tr><td colspan=2></td><td>
<?php if (!$mi && $userLevel>10) {
echo '<input type="checkbox" id="share" name="share"><label for="share"><span></span></label>共有する<br><p style="font-size:12px;">
他のユーザーと共有することができます。ただし、一度共有したものは変更ができません！</p>
<p style="font-size:10px;">選択されてるカテゴリが共有カテゴリでない場合は無効です</p>';
} ?>
</td>
</tr>
<tr><td>&nbsp;</td></tr>
<tr>
<td style="text-align:left;"></td>
<td colspan=3 style="border:0;text-align:right;">
<a href="<?=$selfName?>"><input class="buttonclass" type="button" value="新規"></a> &nbsp; &nbsp; 
<input class="buttonclass" type="submit" name="send" value="削除"></td>
</tr>
</table>
<input type='hidden' name='mi' value='<?=$mi?>'>
<br>

<table class="kwtool" style="width:98%;font-size:12px;">
<tr><th>タイトル</th><th>ファイル名</th><th>カテゴリー</th><th>タイプ</th><th>編集</th><th><input type="checkbox" id="selectAll"><label for='selectAll'><span></span></label>削除</th></tr>
<?php
$rowCount = getNumList();
$pageCount = (int)ceil($rowCount/$items_per_page);
if (!$pageNo) $pageNo = $pageCount;

// get reservation table data
$offset = ($pageNo - 1) * $items_per_page;
$imageList = getVideoList($offset, $items_per_page);
if (count($imageList)) {
	$i=1;
	foreach ($imageList as $rec) {
		$id = $rec['video_id'];
		$tt = $rec['title'];
		$fi = $rec['filename'];
		$ca = $rec['cname'];
//		$ke = $rec['keywords'];
		$ty = $rec['video_type'];
		$sh = $rec['group_id'];

		echo "<tr>";
		echo "<td>".$tt."</td>";
		echo "<td>".$fi."</td>";
		echo "<td style='text-align:center;'>".$ca."</td>";
//		echo "<td>".$ke."</td>";
		switch ($ty) {
		case 1: echo "<td>OP</td>"; break;
		case 2: echo "<td>ED</td>"; break;
		default: echo "<td></td>";
		}
//		echo "<td>".($sh?'×':'○')."</td>";
		echo '<td><span class="small"><a href="'.$selfName.'?mi='.$id.'&p='.$pageNo.'"><img src="img/icon_edit.png"></a></span>';
		echo "<input type='hidden' name='resId".$i."' value='".$id."' /></td>";
		if ($sh)
			echo "<td><input type='checkbox' id='d$i' class='delbtn' name='delete[]' value='".$i."' /><label for='d$i'><span></span></label></td>";
		else
			echo "<td><input type='checkbox' class='delbtn' name='delete[]' value='' disabled/></td>";
		echo "</tr>\n";
		
		$i++;
	}
}
?>
</table>

<table><tr><td style="font-size:11px">
<?php
for ($i = 1; $i <= $pageCount; $i++) {
   if ($i == $pageNo) { // this is current page
       echo 'Page ' . $i . '&nbsp;';
   } else { // show link to other page   
       echo '<a href="'.$selfName.'?p=' . $i . '">Page ' . $i . '</a>&nbsp;';
   }
}
?>
</td></tr></table>
</form>

<?php
session_write_close();
?>
</div><!--end container_main-->
<p class="footer_img"><br />Copyright © 2015-2016 J Hirota. All rights Reserved.</p>

<script>
$(function() {
	$('#selectAll').click(function() {
		var setvar = $(this).prop('checked');
		$('.delbtn').each(function() {
			$(this).prop('checked', setvar);
		});
	});
});


function readURL(input) {
	if (input.files && input.files[0]) {
		var reader = new FileReader();

		reader.onload = function (e) {
			$('#thum')
				.attr('src', e.target.result);
		};

//		reader.readAsDataURL(input.files[0]);
		if ($('#title').val()=="")
			$('#title').val(input.files[0].name.replace(/\.[^/.]+$/, ""));
	}
}
</script>

</body>
</html>