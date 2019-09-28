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
$osname = '';
$desc = '';
$login = '';
$pass = '';
$email = '';
$rate = 0;

$items_per_page = 20;

if ($_POST) {
	$send = $_POST['send'];
	$osname = $_POST['name'];
	$login = $_POST['login'];
	$pass = $_POST['pass'];
	$desc = $_POST['desc'];
	$rate = $_POST['rate'];
	$email = $_POST['email'];
	$mi = $_POST['mi'];
	$osuserid = $_POST['osuserid'];
	if ($send==="変更") {
		$sql = "update outsourcing set misc=:iDesc,rate=:iRate where os_id=".$mi;
		$params = array (':iDesc'=>$desc,':iRate'=>$rate);
		$result = getDB($sql,false,$params);
		$sql = "update users set name=:iName,login_id=:iLogin,login_pass=:iPass where user_id=".$osuserid;
		$params = array (
			':iName'=>$osname,
			':iLogin'=>$login,
			':iPass'=>$pass
		);
		$result = getDB($sql,false,$params);
		$message = "外注を変更しました";
	} else if ($send==="追加") {
		if ($osname) {
			$sql = "call add_new_user(:iGroupID,:iName,:iEmail,:iLogin,:iPass,:iLevel,:iUserType)";
			$params = array (
				':iGroupID'=>$userGroup,
				':iName'=>$osname,
				':iEmail'=>$email,
				':iLogin'=>$login,
				':iPass'=>$pass,
				':iUserType'=>1,
				':iLevel'=>1
			);
			$result = getDB($sql,false,$params);
			$sql = "select user_id from users where email='$email'";
			$result = getDB($sql);
			if (count($result)) {
				$rec = $result[0];
				$osuserid = $rec['user_id'];
			}
			$sql = sprintf("insert into outsourcing(primary_user,misc,rate,user_id) values(%u,'%s',%f,%u)",
					$userID,$desc,$rate,$osuserid);
			$result = getDB($sql,false);
		} else
			$message = "名前が入力されてません";
	} else if ($send==="削除") {
		if (isset($_POST['delete'])) {
			if (is_array($_POST['delete'])) {
				foreach($_POST['delete'] as $i) {
					$Id = $_POST['osId'.$i];
					$uId = $_POST['uId'.$i];
					//echo "deleting ".$Id."<br>\n";
					$sql = "delete from outsourcing where os_id=".$Id;
					$result = getDB($sql,false);
					$sql = "delete from users where user_id=".$uId;
					$result = getDB($sql,false);
				}
			} else {
				$i = $_POST['delete'];
				$Id = $_POST['osId'.$i];
				$uId = $_POST['uId'.$i];
				$sql = "delete from outsourcing where os_id=".$Id;
				$result = getDB($sql,false);
				$sql = "delete from users where user_id=".$uId;
				$result = getDB($sql,false);
			}
		}
	}
} else {
	if (isset($_GET['mi'])) {
		$mi = $_GET['mi'];
		// load
		$sql = "select u.name,u.email,o.misc,rate,u.login_id,u.login_pass,u.user_id from outsourcing o left join users u on o.user_id=u.user_id where os_id=".$mi;
		$result = getDB($sql);
		if (count($result)) {
			$rec = $result[0];
			$osname = $rec['name'];
			$email = $rec['email'];
			$desc = $rec['misc'];
			$rate = $rec['rate'];
			$login = $rec['login_id'];
			$pass = $rec['login_pass'];
			$osuserid = $rec['user_id'];
		}
	}
}


function getNumList() {
	global $userID;
	$sql = "select count(*) n from outsourcing where primary_user=".$userID;
	$result = getDB($sql);
	return $result[0]['n'];
}

function getOSList($offset, $numItems) {
	global $userID;
    $sql = "select * from outsourcing o left join users u on o.user_id=u.user_id where primary_user=$userID limit "
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
	<title>外注アカウント管理</title>
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
<div class="subtitle">外注アカウント管理</div>
<span id="message"><?=$message?></span>

<div class="container_main">
<form class="select" method="POST" enctype="multipart/form-data">
<input type=hidden name="userid" value="<?=$userID?>" />

<table>
<tr style="text-align:left;">
<th>名前</th><th>ユーザーID</th><th>パスワード</th></tr>
<tr>
<td><input id="name" name="name" type="text" size="40" value="<?=$osname?>"></td>
<td><input id="login" name="login" type="text" size="20" value="<?=$login?>"></td>
<td><input id="pass" name="pass" type="text" size="20" value="<?=$pass?>"></td>
</tr>
<tr style="text-align:left;"><th colspan=2>メールアドレス</th><th>単価</th></tr>
<tr>
<td colspan=2><input id="email" name="email" type="text" size="60" value="<?=$email?>"></td><td><input name="rate" type="text" size="8" value="<?=$rate?>"></td>
</tr>
<tr><th colspan=2  style="text-align:left;">備考</th></tr>
<tr>
<td colspan=2><textarea id="desc" name="desc"><?=$desc?></textarea></td>
</tr>
<tr><td>&nbsp;</td></tr>
<tr>
<td colspan=2></td>
<td style="text-align:right;">
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
<th>外注アカウント一覧</th><td style="text-align:right;">
 &nbsp; &nbsp;
<input class="buttonclass" type="submit" name="send" value="削除"></td>
</tr>
</table>

<table class="kwtool" style="width:98%;">
<tr><th>ID</th><th>名前</th><th>備考</th><th>単価</th><th>登録日</th><th>編集</th><th>削除</th></tr>
<?php
$rowCount = getNumList();
$pageCount = (int)ceil($rowCount/$items_per_page);
if (!$pageNo) $pageNo = $pageCount;

// get table data
$offset = ($pageNo - 1) * $items_per_page;
$osList = getOSList($offset, $items_per_page);
if (count($osList)) {
	$i=1;
	foreach ($osList as $rec) {
		$id = $rec['os_id'];
		$na = $rec['name'];
		$de = $rec['misc'];
		$ra = $rec['rate'];
		$ui = $rec['user_id'];
		$rd = $rec['reg_date'];

		echo "<tr>";
//		echo "<td>".$id."</td>";
		echo "<td>".$ui."</td>";
		echo "<td>".$na."</td>";
		echo "<td>".$de."</td>";
		echo "<td>".$ra."</td>";
		echo "<td>".$rd."</td>";
		echo '<td><span class="small"><a href="'.$selfName.'?mi='.$id.'&p='.$pageNo.'"><img src="img/icon_edit.png"></a></span>';
		echo "<input type='hidden' name='osId".$i."' value='".$id."' /></td>";
		echo "<input type='hidden' name='uId".$i."' value='".$ui."' /></td>";
		echo "<td><input type='checkbox' id='d$i' name='delete[]' value='".$i."' /><label for='d$i'><span></span></label></td>";
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
