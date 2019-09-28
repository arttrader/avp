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
		if ($userLevel<4)
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
$complete = 0;
$narticles = 0;
$ncarticles = 0;
$category = 0;
$items_per_page = 20;

if ($_POST) {
	$send = $_POST['send'];
	$pname = $_POST['pname'];
	$desc = $_POST['desc'];
	$misc = $_POST['misc'];
	$narticles = $_POST['narticles'];
	$category = $_POST['category'];
	$osid = $_POST['osid'];
	$ddate = $_POST['ddate'];
	$complete = $_POST['complete'];
	$mi = $_POST['mi'];
	if ($send==="変更") {
		$sql = "update os_projects set proj_name=:iName,proj_desc=:iDesc,misc=:iMisc,n_articles=:iNArticles,category=:iCat,os_id=:iOId,deadline=:iDDate,completed=:iComplete where os_proj_id=".$mi;
		$params = array (
			':iName'=>$pname,
			':iDesc'=>$desc,
			':iMisc'=>$misc,
			':iNArticles'=>$narticles,
			':iCat'=>$category,
			':iOId'=>$osid,
			':iDDate'=>$ddate,
			':iComplete'=>$complete
		);
		$result = getDB($sql,false,$params);
		$message = "タスクを変更しました";
	} else if ($send==="追加") {
		if ($pname) {
			$sql = "insert into os_projects(user_id,proj_name,proj_desc,n_articles,category,os_id,assign_date,deadline,completed) values(:iUserId,:iName,:iDesc,:iNArticles,:iCat,:iOId,now(),:iDDate,0)";
			$params = array (
				':iUserId'=>$userID,
				':iName'=>$pname,
				':iDesc'=>$desc,
				':iNArticles'=>$narticles,
				':iCat'=>$category,
				':iOId'=>$osid,
				':iDDate'=>$ddate
			);
			$result = getDB($sql,false,$params);
		} else
			$message = "タスク名が入力されてません";
	} else if ($send==="削除") {
		if (isset($_POST['delete'])) {
			if (is_array($_POST['delete'])) {
				foreach($_POST['delete'] as $i) {
					$Id = $_POST['osId'.$i];
					//echo "deleting ".$Id."<br>\n";
					$sql = "delete from os_proj_article where os_proj_id=".$Id;
					$result = getDB($sql,false);
					$sql = "delete from os_projects where os_proj_id=".$Id;
					$result = getDB($sql,false);
				}
			} else {
				$i = $_POST['delete'];
				$Id = $_POST['osId'.$i];
				$sql = "delete from os_proj_article where os_proj_id=".$Id;
				$result = getDB($sql,false);
				$sql = "delete from os_projects where os_proj_id=".$Id;
				$result = getDB($sql,false);
			}
		}
	}
} else {
	if (isset($_GET['mi'])) {
		$mi = $_GET['mi'];
		// load
		$sql = "select proj_name,o.proj_desc,o.misc,n_articles,category,os_id,deadline,completed from os_projects o where os_proj_id=".$mi;
		$result = getDB($sql);
		if (count($result)) {
			$rec = $result[0];
			$pname = $rec['proj_name'];
			$desc = $rec['proj_desc'];
			$misc = $rec['misc'];
			$narticles = $rec['n_articles'];
			$category = $rec['category'];
			$osid = $rec['os_id'];
			$ddate = substr($rec['deadline'],0,10);
			$complete = $rec['completed'];
		}
		$sql = "select count(*) from os_proj_article where approved=1 and os_proj_id=".$mi;
		$result = getDB($sql);
		$result = $result[0];
		$ncarticles = $result[0];
	}
}


function getNumList() {
	global $userID;
	$sql = "select count(*) n from os_projects where user_id=".$userID;
	$result = getDB($sql);
	return $result[0]['n'];
}

function getPRList($offset, $numItems) {
	global $userID;
    $sql = "select os_proj_id,proj_name,proj_desc,o.user_id,assign_date,deadline,DATEDIFF(deadline,CURDATE()) as dleft,(select count(*) from os_proj_article a where a.os_proj_id=o.os_proj_id) as nac,n_articles,completed,u.name from os_projects o left join outsourcing os on o.os_id=os.os_id left join users u on os.user_id=u.user_id where o.user_id=$userID limit "
    		.$offset.",".$numItems;
	$result = getDB($sql);
	return $result;
}

function getOSList() {
	global $userID;
    $sql = "select * from outsourcing o left join users u on o.user_id=u.user_id where primary_user=".$userID;
	$result = getDB($sql);
	return $result;
}

function getMSList($projID) {
	global $userID;
	$sql = "select * from os_message where project_id=".$projID;
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
  	$('#expiration').val(year+'-'+month+'-'+day);
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
<span id="message"><?=$message?></span>

<div class="container_main">
<form class="select" method="POST" enctype="multipart/form-data">
<input type=hidden name="userid" value="<?=$userID?>">

<table>
<tr style="text-align:left;">
<th>タスク名</th><th>締め切り</th><th>委託先</th><th><?=$mi?'ステータス':''?></th></tr>
<tr>
<td><input id="pname" name="pname" type="text" size="50" value="<?=$pname?>"></td>
<td><input id="ddate" class="datepicker" name="ddate" type="text" size="20" value="<?=$ddate?>"></td>
<td>
<select name="osid">
<?php
	$osList = getOSList();
	foreach ($osList as $rec) {
		if ($rec['os_id']==$osid) $selected = ' selected';
		else $selected = '';
		echo "<option value='".$rec['os_id']."'".$selected.">".$rec['name']."</option>";
	}
?>
</select>
</td>
<td>
<?php if ($mi) { ?>
<select name="complete">
<option value="0" <?=$complete?'selected':''?>>未完了</option>
<option value="1" <?=$complete?'selected':''?>>完了</option>
</select>
<?php } else { ?>
<input type="hidden" name="complete" value="0">
<?php } ?>
</td>
</tr>
<tr style="text-align:left;"><th colspan=2>タスク内容（外注さんにも表示されます）</th>
<th>必要記事数</th><th>完了記事数</th></tr>
<tr>
<td colspan=2 rowspan=3><textarea name="desc"><?=$desc?></textarea></td>
<td rowspan=1 style="vertical-align:top;"><input type="text" name="narticles" size=8 value="<?=$narticles?>"></td><td style="text-align:center;vertical-align:top;"><?=$ncarticles?></td>
</tr>
<tr><th style="text-align:left;">カテゴリー</th></tr>
<tr><td>
<select id="category" name="category">
<?php getCategoryList($category); ?>
</select>
</td></tr>
<tr><th colspan=2 style="text-align:left;">備考（外注さんは見れません）</th></tr>
<tr>
<td colspan=2><textarea name="misc" style="height:40px"><?=$misc?></textarea></td>
<td></td><td style="text-align:right;">
<a href="<?=$selfName?>"><input class="buttonclass" type="button" value="新規"></a> &nbsp; &nbsp;
<?php
if ($mi) {
	echo '<input class="button" type="submit" name="send" value="変更">';
} else {
	echo '<input class="button" type="submit" name="send" value="追加">';
}
?>
</td>
<td style="text-align:left;"></td>
</tr>
</table>
<input type='hidden' name='mi' value='<?=$mi?>'>
<input type='hidden' name='osuserid' value='<?=$osuserid?>'>
<br>
<table class="">
<tr style="text-align:left;">
<th>外注委託一覧</th><td style="text-align:right;">
 &nbsp; &nbsp;
<input class="buttonclass" type="submit" name="send" value="削除"></td>
</tr>
</table>

<table class="kwtool" style="width:98%;">
<tr><th>ID</th><th>タスク</th><th>詳細</th><th>登録日</th><th>締め切り</th><th>委託先</th><th>記事数</th><th>状態</th><th>編集</th><th>削除</th></tr>
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
		$pn = $rec['proj_name'];
		$de = $rec['proj_desc'];
		$ui = $rec['user_id'];
		$ad = substr($rec['assign_date'],0,10);
		$dd = substr($rec['deadline'],0,10);
		$dl = $rec['dleft'];
		$os = $rec['name'];
		$nc = $rec['nac'];
		$na = $rec['n_articles'];
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
		echo "<td>".$pn."</td>";
		echo "<td>".$de."</td>";
		echo "<td>".$ad."</td>";
		echo "<td>".$dd."</td>";
		echo "<td>".$os."</td>";
		echo "<td><a href='osArticles.php?o=$id' target='new'>".$nc."</a>/".$na."</td>";
		echo "<td>".($st?'完了':'未完了')."</td>";
		echo '<td><span class="small"><a href="'.$selfName.'?mi='.$id.'&p='.$pageNo.'"><img src="img/icon_edit.png"></a></span>';
		echo "<input type='hidden' name='osId".$i."' value='".$id."'></td>";
		echo "<input type='hidden' name='uId".$i."' value='".$ui."'></td>";
		echo "<td><input type='checkbox' id='d$i' name='delete[]' value='".$i."'><label for='d$i'><span></span></label></td>";
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
<p class="footer_img"><br />Copyright © 2015 J Hirota. All rights Reserved.</p>

</body>
</html>
