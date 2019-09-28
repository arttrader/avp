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
		if ($userLevel<1)
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

$mi = 0;
$title = '';
$desc = '';


$items_per_page = 20;

if ($_POST) {
	$send = $_POST['send'];
	$title = $_POST['title'];
	$desc = $_POST['desc'];
	if ($send==="変更") {
		$mi = $_POST['mi'];
		$sql = sprintf("update genre set name='%s',description='%s' where genre_id=%u", $title,$desc,$mi);
		$result = getDB($sql,false);
		$message = "<br>ジャンルデータを変更しました";		
	} else if ($send==="追加") {
		if ($title) {
			$sql = sprintf("insert into genre(name,description,group_id) values('%s','%s',%u)", $title,$desc,$userGroup);
			$result = getDB($sql,false);
		} else
			$message = "ジャンルが入力されてません";
	} else if ($send==="削除") {
		if (isset($_POST['delete'])) {
			if (is_array($_POST['delete'])) {
				foreach($_POST['delete'] as $i) {
					$catId = $_POST['catId'.$i];
					//echo "deleting ".$catId."<br /><br />\n";
					$sql = sprintf("delete from genre where genre_id=%u;", $catId);
					$result = getDB($sql,false);
				}
			} else {
				$i = $_POST['delete'];
				$catId = $_POST['catId'.$i];
				$sql = sprintf("delete from genre where genre_id=%u;", $catId);
				$result = getDB($sql,false);
			}
		}
	}
} else {
	if (isset($_GET['mi'])) {
		$mi = $_GET['mi'];
		// load
		$sql = "select * from genre where genre_id=".$mi;
		$result = getDB($sql);
		if (count($result)) {
			$rec = $result[0];
			$title = $rec['name'];
			$desc = $rec['description'];
		}
	}
}


function getNumList() {
	global $userGroup;
	$sql = "select count(*) n from genre where group_id=".$userGroup;
	$result = getDB($sql);
	return $result[0]['n'];
}

function getCatList($offset, $numItems) {
	global $userGroup;
    $sql = "select * from genre where group_id=$userGroup order by genre_id desc limit ".$offset.",".$numItems;
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
	<link rel="stylesheet" href="css/jquery-ui-timepicker-addon.css" />
	<title>カスタムジャンル管理</title>
	<script src="//code.angularjs.org/1.4.5/angular.min.js"></script>
	<script src="http://ajax.googleapis.com/ajax/libs/jquery/1.9.1/jquery.min.js"></script>
	<script src="http://code.jquery.com/ui/1.10.3/jquery-ui.js"></script>
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
<div class="subtitle">カスタムジャンル管理</div>
<span id="message"><?=$message?></span>

<div class="container_main">
<form class="select" method="POST" enctype="multipart/form-data">
<input type=hidden name="userid" value="<?=$userID?>" />

<table class="">
<tr style="text-align:left;">
<th>ジャンル</th><th>詳細</th></tr>
<tr>
<td><input id="title" name="title" type="text" size="40" value="<?=$title?>"></td>
<td style="">
<input id="desc" name="desc" type="text" size="80" value="<?=$desc?>">
</td>
</tr>
<tr><td>&nbsp;</td></tr>
<tr>
<td></td>
<td style="text-align:right;">
<?php
if ($mi) {
	echo '<input class="button" type="submit" name="send" value="変更">';
} else {
	echo '<input class="button" type="submit" name="send" value="追加">';
}
?>
</td></tr>
<tr>
<td style="text-align:left;"></td>
<td colspan=2 style="text-align:right;">
 &nbsp; &nbsp; 
<input class="buttonclass" type="submit" name="send" value="削除"></td>
</tr>
</table>
<input type='hidden' name='mi' value='<?=$mi?>'>
<br>

<table class="kwtool" style="width:98%;">
<tr><th>ID</th><th>タイトル</th><th>詳細</th><th>編集</th><th><input type="checkbox" id="selectAll"><label for='selectAll'><span></span></label>削除</th></tr>
<?php
$rowCount = getNumList();
$pageCount = (int)ceil($rowCount/$items_per_page);
if (!$pageNo) $pageNo = $pageCount;

// get table data
$offset = ($pageNo - 1) * $items_per_page;
$itemList = getCatList($offset, $items_per_page);
if (count($itemList)) {
	$i=1;
	foreach ($itemList as $rec) {
		$id = $rec['genre_id'];
		$na = $rec['name'];
		$de = $rec['description'];

		echo "<tr>";
		echo "<td>".$id."</td>";
		echo "<td>".$na."</td>";
		echo "<td>".$de;
		echo '<td><span class="small"><a href="'.$selfName.'?mi='.$id.'&p='.$pageNo.'"><img src="img/icon_edit.png"></a></span>';
		echo "<input type='hidden' name='catId".$i."' value='".$id."' /></td>";
		echo "<td><input type='checkbox' id='d$i' class='delbtn' name='delete[]' value='".$i."' /><label for='d$i'><span></span></label></td>";
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

<script>
$(function() {
	$('#selectAll').click(function() {
		var setvar = $(this).prop('checked');
		$('.delbtn').each(function() {
			$(this).prop('checked', setvar);
		});
	});
});
</script>
</div><!--end container_main-->
<p class="footer_img"><br />Copyright © 2015-2016 J Hirota. All rights Reserved.</p>

</body>
</html>