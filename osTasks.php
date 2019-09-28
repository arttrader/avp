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
$pname = '';
$desc = '';
$misc = '';
$adate = '';
$ddate = '';
$message = '';
$messages = array();
$complete = 0;
$narticles = 0;

$items_per_page = 20;

if ($_POST) {
	$send = $_POST['send'];
	$pname = $_POST['pname'];
	$desc = $_POST['desc'];
	$puid = $_POST['puid'];
	$narticles = $_POST['narticles'];
	$message = $_POST['message'];
	$prid = $_POST['prid'];
	$osid = $_POST['osid'];
	$ddate = $_POST['ddate'];
	$complete = $_POST['complete'];
	$mi = $_POST['mi'];
	if ($send==="送信") {
		if ($pname) {
			$sql = "insert into os_message(from_user,to_user,header,message,project_id) values(:iUserId,:iPUserId,'',:iMessages,:iPId)";
			$params = array (
				':iUserId'=>$userID,
				':iPUserId'=>$puid,
				':iMessages'=>$message,
				':iPId'=>$prid
			);
			$result = getDB($sql,false,$params);
		} else
			echo "<div class='font_red'>タスク名が入力されてません</div><br>";
	}
} else {
	if (isset($_GET['mi'])) {
		$mi = $_GET['mi'];
		// load
		$sql = "select os_proj_id,user_id,proj_name,o.proj_desc,o.misc,n_articles,os_id,deadline,completed from os_projects o where os_proj_id=".$mi;
		$result = getDB($sql);
		if (count($result)) {
			$rec = $result[0];
			$prid = $rec['os_proj_id'];
			$puid = $rec['user_id'];
			$pname = $rec['proj_name'];
			$desc = $rec['proj_desc'];
			$misc = $rec['misc'];
			$narticles = $rec['n_articles'];
			$osid = $rec['os_id'];
			$ddate = substr($rec['deadline'],0,10);
			$complete = $rec['completed'];
		}
		$sql = "select to_user,header,message from os_message m where from_user=$userID and project_id=".$mi;
		$result = getDB($sql);
		if (count($result)) {
			foreach ($result as $rec) {
				$messages[] = $rec;
			}
			$message = array_pop($messages)['message'];
		}
	}
}


function getNumList() {
	global $userID;
	$sql = "select count(*) n from os_projects p left join outsourcing o on o.os_id=p.os_id where o.user_id=".$userID;
	$result = getDB($sql);
	return $result[0]['n'];
}

function getPRList($offset, $numItems) {
	global $userID;
    $sql = "select os_proj_id,proj_name,proj_desc,assign_date,deadline,DATEDIFF(deadline,CURDATE()) as dleft,completed from os_projects p left join outsourcing o on p.os_id=o.os_id where o.user_id=$userID limit "
    		.$offset.",".$numItems;
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
	<title>外注タスク管理</title>
	<script src="//code.angularjs.org/1.4.5/angular.min.js"></script>
	<script src="http://ajax.googleapis.com/ajax/libs/jquery/1.9.1/jquery.min.js"></script>
	<script src="http://code.jquery.com/ui/1.11.1/jquery-ui.js"></script>
	<script type='text/javascript' src='../js/menu_app.js'></script>
<script type="text/javascript">
$(function() {
  $(".datepicker").datepicker({
	closeText:'閉じる',
	currentText:'今現在',
	timeText:'時間',
	hourText:'時',
	minuteText:'分',
	monthNames:['1月','2月','3月','4月','5月','6月','7月','8月','9月','10月','11月','12月'],
	dayNamesMin:['日','月','火','水','木','金','土'],
	dateFormat:'yy/mm/dd'
  });

  $('.period').click(function() {
  	var period = $(this).val();
  	if (period==0)
  		$('#expiration').val("");
  	else {
		var months = 0;
		switch (period) {
			case '1':
				months = 1;
				break;
			case '2':
				months = 3;
				break;
			case '3':
				months = 6;
		}
		var dt = new Date();
		dt.setMonth(dt.getMonth() + months);
		var day = dt.getDate();
		var year = dt.getFullYear();
		var month = dt.getMonth()+1;
  	$('#expiration').val(year+'/'+month+'/'+day);
  	}
  });
});
</script>
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
<div class="subtitle">外注タスク管理</div>

<div class="container_main">
<form class="select" method="POST" enctype="multipart/form-data">
<input type=hidden name="userid" value="<?=$userID?>">

<table>
<tr style="text-align:left;">
<th>タスク名</th><th>締め切り</th><th><?=$mi?'ステータス':''?></th></tr>
<tr>
<td><input type="text" size="50" value="<?=$pname?>" disabled><input name="pname" type="hidden" value="<?=$pname?>"></td>
<td><input  type="text" size="20" value="<?=$ddate?>" disabled><input  type="hidden" name="ddate" value="<?=$ddate?>"></td>
<td>
<?php if ($mi) { ?>
<select disabled>
<option value="0" <?=$complete?'selected':''?>>未完了</option>
<option value="1" <?=$complete?'selected':''?>>完了</option>
</select>
<input type="hidden" name="complete" value="<?=$complete?>">
<?php } else { ?>
<input type="hidden" name="complete" value="0">
<?php } ?>
</td>
</tr>
<tr style="text-align:left;"><th colspan=2>タスク内容</th>
<th>記事数</th></tr>
<tr>
<td colspan=2><textarea disabled><?=$desc?></textarea><input name="desc" type="hidden" value="<?=$desc?>"></td>
<td style="vertical-align:top;"><input type="text" size=8 value="<?=$narticles?>" disabled><input name="narticles" type="hidden" value="<?=$narticles?>"></td>
</tr>
<!--
<tr><th colspan=2 style="text-align:left;">メッセージ（このタスクに関する質問、要望など）</th></tr>
<tr>
<td colspan=2><textarea name="message" style="height:40px"><?=$message?></textarea></td>
<td style="text-align:right;">
<?php
if ($mi) {
	echo '<input class="button" type="submit" name="send" value="送信">';
} else {
	echo '<input class="button" type="submit" name="send" value="送信">';
}
?>
</td>
<td style="text-align:left;"></td>
</tr>
<?php
foreach ($messages as $mess) {
	echo "<tr><td colspan=2>".$mess['message']."</td></tr>";
}
?>
-->
</table>
<input type='hidden' name='mi' value='<?=$mi?>'>
<input type='hidden' name='osid' value='<?=$osid?>'>
<input type='hidden' name='prid' value='<?=$prid?>'>
<input type='hidden' name='puid' value='<?=$puid?>'>
</form>
<br>
<table class="">
<tr style="text-align:left;">
<th>現在委託されてるタスク</th><td style="text-align:right;"></td>
</tr>
</table>

<table class="kwtool" style="width:98%;">
<tr><th>ID</th><th>タスク名</th><th>詳細</th><th>登録日</th><th>締め切り</th><th>状態</th><th>詳細</th></tr>
<?php
$rowCount = getNumList();
$pageCount = (int)ceil($rowCount/$items_per_page);
if (!$pageNo) $pageNo = $pageCount;

// get table data
$offset = ($pageNo - 1) * $items_per_page;
$osList = getPRList($offset, $items_per_page);
if (count($osList)) {
	$i=1;
	foreach ($osList as $rec) {
		$id = $rec['os_proj_id'];
		$na = $rec['proj_name'];
		$de = $rec['proj_desc'];
		$ad = substr($rec['assign_date'],0,10);
		$dd = substr($rec['deadline'],0,10);
		$dl = $rec['dleft'];;
		$st = $rec['completed'];
		if ($st)
			echo "<tr style='color:gray;'>";
		else
			if ($dl<0)
				echo "<tr style='color:red;'>";
			else if ($dl<2)
				echo "<tr style='color:orange;'>";
			else
				echo "<tr style='color:black;'>";

		echo "<td>".$id."</td>";
		echo "<td>".$na."</td>";
		echo "<td>".$de."</td>";
		echo "<td>".$ad."</td>";
		echo "<td>".$dd."</td>";
		echo "<td>".($st?'完了':'未完了')."</td>";
		echo '<td><span class="small"><a href="'.$selfName.'?mi='.$id.'&p='.$pageNo.'"><img src="img/icon_edit.png"></a></span>';
		echo "<input type='hidden' name='prId".$i."' value='".$id."'></td>";
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

<?php
session_write_close();
?>
</div><!--end container_main-->
<p class="footer_img"><br />Copyright © 2015 J Hirota. All rights Reserved.</p>

</body>
</html>
