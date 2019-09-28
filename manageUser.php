<?php
require_once 'AuthController.php';

$debugmode = false;

// authenticate the use of this system
// use this as a set on every page that requires authorization
//start session
session_start();
if (isset($_SESSION[$sessionName])) {
	$authController = unserialize($_SESSION[$sessionName]);
	if (is_object($authController)) {
		$authController->checkLogin('userkanri.php');
		$name = $authController->name;
		$userID = $authController->userId;
		$userLevel = $authController->userLevel;
	} else
		header("Location:index.php?u=manageUser.php");
} else
	header("Location:index.php?u=manageUser.php");

if ($userLevel<19) // 準管理人以上のみ
	header("Location:$rootUrl");

$error = 0;
$done = false;

$items_per_page = 15;


$uagent = getenv("HTTP_USER_AGENT");
if (isset($_SERVER['HTTP_REFERER'])) {
	$ref = $_SERVER['HTTP_REFERER'];
} else {
	$ref = "";
}
if (getenv('HTTP_X_FORWARDED_FOR')) {
	$pipaddress = getenv('HTTP_X_FORWARDED_FOR');
	$ipaddress = getenv('REMOTE_ADDR');
	//echo "Your Proxy IP address is : ".$pipaddress. "(via $ipaddress)" ;
} else {
	$pipaddress = "";
	$ipaddress = getenv('REMOTE_ADDR');
	//echo "Your IP address is : $ipaddress";
}

$sn = null;

if($_POST) {
	$send = $_POST['send'];
	$pageNo = $_POST['pn'];
	$sn_raw = $_POST['Sn'];
	$em = $_POST['Em'];
	$space = array(' ', '　');
	$sn = str_replace($space, "", $sn_raw);
	$em = trim($em);
	$ui = null;
	if ($send==="検索")
		$pageNo = 1;

	if ($send==="変更") {
		$ui = $_POST['ui'];
		$userLn = $_POST['Ln'];
		$sn = $userLn;
		$email = $_POST['Em'];
		$expire = $_POST['expiration'];
		$userType = $_POST['usertype'];
		$permission = $_POST['permission'];
		$regdate = $_POST['regdate'];
		if (!$email) {
			$error = 1;
		} else {
			if ($expire)
				$sql = "call upgrade_user($ui,$userType,$permission,'$expire')";
			else
				$sql = "call upgrade_user($ui,$userType,$permission,null)";
			$result = getDB($sql);
//			if ($debugmode) var_dump($result);
			$error = 0;
			$sql = "call save_log1(17,'','Set expiration for user $ui to $expire','','$ipaddress','$pipaddress','$uagent','$ref',$userID);";
			if ($debugmode) echo $sql;
			$result = getDB($sql);
			$done = true;
		}
	}
} else {
	if (isset($_GET['p']))
		$pageNo = $_GET['p'];
	else
		$pageNo = 1;

	if (isset($_GET['ui'])) {
		$ui = $_GET['ui'];
		$ut = $_GET['ut'];
		$sql = "select name,email,user_type,permission,expires,reg_date from users u where u.user_id=$ui";
		$result = getDB($sql);
		$res = $result[0];
		$sn = "";
		$em = "";
		$userLn = $res['name'];
		$email = $res['email'];
		$userType = $res['member_type'];
		$permission = $res['permission'];
		$expire = $res['tool_exp'];
		$jd = $res['joined_date'];
		date_default_timezone_set("UTC");
		$date_in_localtime = strtotime($jd);
		date_default_timezone_set('Asia/Tokyo');
		$regdate = date("Y-m-d", $date_in_localtime);
	} else {
		$ui = null;
		$sn = "";
		$em = "";
		$userLn = "";
		$email = "";
		$userType = 1;
		$permission = 1;
		$expire = '';
		$regdate = '';
	}
}


function changeMemberSite($memberID, $expDate, $siteurl, $apiKey) {
	global $debugmode;

	// member id 0 は管理人なので絶対に変更しない！
	if (!$memberID) return null;

	$api = new wlmapiclass($siteurl, WLM_APIKEY);
	$api->return_format = 'php'; // <- value can also be xml or json

	$response = $api->get('/levels');
	$result = unserialize($response);
	$levelIDs = array($result['levels']['level'][0]['id']);
	$data = array(
		'' => $expDate,
		'Levels' => $levelIDs
	);
	$response = $api->put('/members'.$memberID, $data);
	return unserialize($response);
}

function getNumList() {
	$sql = "select user_id from users";
	$result = getDB($sql);
	return count($result);
}

function getUserList($offset, $numItems, $name=null, $email=null) {
	if ($name) {
		$nameT = str_replace($space, "", $name);
		$sql = "select u.user_id,name,email,member_type,description,t.display_name,m.permission,tool_exp,joined_date,timestamp from users u left join user_level t on u.user_type=t.user_type_id order by user_id, user_type where name like '$name%' or name like '$nameT%' order by user_id, user_type limit $offset,$numItems";
	} else if ($email)
		$sql = "select u.user_id,name,email,member_type,description,t.display_name,m.permission,tool_exp,joined_date,timestamp from users u left join user_level t on u.user_type=t.user_type_id where email='$email' or email_purchase='$email' or email_old='$email' order by user_id, member_type limit $offset,$numItems";
	else
    	$sql = "select u.user_id,name,email,user_type,description,t.display_name,permission,expires,reg_date,u.datetime from users u left join user_level t on u.permission=t.user_type_id order by user_id, user_type limit $offset,$numItems";
	$result = getDB($sql);
	return $result;
}

// 未使用
function wbsRequest2($method, $url, $params = array())
{
    $data = http_build_query($params);
    if($method == 'GET') {
        $url = ($data != '')?$url.'?'.$data:$url;
    }

    $ch = curl_init($url);

    if($method == 'POST'){
        curl_setopt($ch,CURLOPT_POST,1);
        curl_setopt($ch,CURLOPT_POSTFIELDS,$data);
    }

    //curl_setopt($ch, CURLOPT_HEADER,true); //header情報も一緒に欲しい場合
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE); // AutoBizからのレスポンスを表示しない
    curl_setopt($ch, CURLOPT_TIMEOUT, 2);
    $res = curl_exec($ch);

    //ステータスをチェック
    $respons = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    if(preg_match("/^(404|403|500)$/",$respons)){
        return false;
    }

    return $res;
}
?>
<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN" "http://www.w3.org/TR/html4/loose.dtd">
<html lang="ja">
<head>
<meta http-equiv="Content-Type" content="text/html; charset=UTF-8">

<title>忍びツールユーザー管理</title>
<link rel="stylesheet" href="style.css" type="text/css">
<link rel="stylesheet" href="//code.jquery.com/ui/1.11.1/themes/smoothness/jquery-ui.css">
<script type="text/javascript" src="http://ajax.googleapis.com/ajax/libs/jquery/1.6.2/jquery.min.js"></script>
<script src="http://code.jquery.com/ui/1.11.1/jquery-ui.js"></script>
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


  $('.extperiod').click(function() {
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
		var dt = new Date($('#expiration').val());
		dt.setMonth(dt.getMonth() + months);
		var day = dt.getDate();
		var year = dt.getFullYear();
		var month = dt.getMonth()+1;
  		$('#expiration').val(year+'-'+month+'-'+day);
  	}
  });

	// script snippet for tooltip
	$('.masterTooltip').hover(function(){
		// Hover over code
		var title = $(this).attr('title');
		$(this).data('tipText', title).removeAttr('title');
		$('<p class="tooltip"></p>')
		.text(title)
		.appendTo('body')
		.fadeIn('slow');
	}, function() {
		// Hover out code
		$(this).attr('title', $(this).data('tipText'));
		$('.tooltip').remove();
	}).mousemove(function(e) {
		var mousex = e.pageX + 20; //Get X coordinates
		var mousey = e.pageY + 10; //Get Y coordinates
		$('.tooltip')
		.css({ top: mousey, left: mousex })
	});
});
</script>
</head>
<body>
<div class="container_main">
<tr><td>こんにちは、<?php echo $name; ?>さん</td>

<?php if ($done) {
	if (!$error)
		echo "<p>変更完了！</p>";
	else if ($error==2)
		echo "<p><font color='red'>変更失敗！</font></p>";
//	echo '<input class="backbuttonclass" type="button" value="戻る" onClick="window.location.href = window.location.pathname;">';
} ?>
<form class="searchform" method="POST">
	<h3 style="text-align:center;">忍びツールユーザー管理</h3>
	<table class="kwtool">
<tr><th>ID</th><th>名前</th><th>メール</th><th>タイプ</th><th>許可</th><th>有効期限</th><th>登録日</th><th>最終変更日</th><th></th></tr>
<?php
$rowCount = getNumList();
$pageCount = (int)ceil($rowCount/$items_per_page);

// get user table data
$offset = ($pageNo - 1) * $items_per_page;
$limit = $items_per_page*$pageNo;
if ($sn)
	$users = getUserList($offset, $items_per_page, $sn_raw);
else if ($em)
	$users = getUserList($offset, $items_per_page, null, $em);
else
	$users = getUserList($offset, $items_per_page);
if (count($users)) {
	$i=1;
	$lid = 0;
	foreach ($users as $res) {
		$id = $res['user_id'];
		$un = $res['name'];
		$em = $res['email'];
		$ut = $res['user_type'];
		$utn = $res['display_name'];
		$perm = $res['permission'];
		$reg = substr($res['reg_date'],0,10);
		$exp = $res['expires'];
		$datetime = $res['datetime'];
		date_default_timezone_set("UTC");
		$timestamp_in_localtime = strtotime($datetime);
		date_default_timezone_set('Asia/Tokyo');
		$local_time = date("Y-m-d H:i:s", $timestamp_in_localtime);
		echo "<tr>";
		if ($id!=$lid) {
			echo "<td>".$id."</td>";
			echo "<td>".$un."</td>";
			echo "<td>".$em."</td>";
		} else {
			echo "<td></td>";
			echo "<td></td>";
			echo "<td></td>";
		}
		echo "<td class='masterTooltip' title='".$utn."'>".$ut."</td>";
		echo "<td>".$perm."</td>";
		echo "<td>".$exp."</td>";
		echo "<td>".$reg."</td>";
		echo "<td style='font-size:10px;'>".$local_time."</td>";
		echo '<td><span class="button small"><a href="userkanri.php?ui='.$id.'&ut='.$ut.'&p='.$pageNo.'"><img src="img/icon_edit.png"></a></span></td></tr>';
		echo "</tr>\n";
		$lid = $id;
		$i++;
	}
}
if ($ui)
	if ($permission>8 || $userType>=97)
		echo '<p class="center" style="color:red;">管理者などの特別アカウントの変更は許可されてません！</p>';
	else {
?>
    <table class="search">
    <tr>
	<td style="text-align:right;">名前</td>
	<td class="data">
	<?=$userLn?>
	</td>
	</tr>
    <tr>
    <td style="text-align:right;">メールアドレス</td>
    <td><?=$email?>
    <input type="hidden" name="Em" value="<?=$email?>">
	</td>
	</tr>
	<tr>
	<td style="text-align:right;">ユーザータイプ</td>
	<td><select id="usertype" name="usertype">
<?php
	$sql = "select * from user_type order by user_type_id";
	$userTypes = getDB($sql);
	$i = 0;
	foreach ($userTypes as $line) {
		$id = $line['user_type_id'];
		if ($id<97) { // 準管理人以下に限定
			$tname = $id.' '.$line['description'];
			echo "<option value='".$id."'".(($userType==$id)?" selected":"").">".$tname."</option>\n";
		}
	}
?>
	</select></td>
	</tr>
	<tr>
	<td style="text-align:right;">アクセス許可</td>
	<td><select name="permission">
<?php
	$sql = "select * from permissions order by perm_id";
	$perms = getDB($sql);
	$i = 0;
	foreach ($perms as $line) {
		$id = $line['perm_id'];
		if ($id<8) { // テスター、準管理人以下に限定
			$pname = $id.' '.$line['perm_desc'];
			echo "<option ".(($permission==$id)?"selected ":"")
				."value='".$id."'>".$pname."</option>\n";
		}
	}
?>
	</select></td>
	</tr>
	</tr>
	<tr>
	<td style="text-align:right;">登録日</td>
	<td><?=$regdate?>
	<input type="hidden" name="regdate" value="<?=$regdate?>"></td>
	</tr>
	<tr>
	<td style="text-align:right;">有効期限</td>
	<td><input type="text" name="expiration" class="datepicker" id="expiration" size=16 value="<?=$expire?>"></td>
	</tr>
	<tr><td style="text-align:right;">有効期間延長</td>
	<td>
	<input type="radio" class="extperiod" name="period" value="1">1ヶ月
	<input type="radio" class="extperiod" name="period" value="2">3ヶ月
	<input type="radio" class="extperiod" name="period" value="3">半年
	<input type="radio" class="extperiod" name="period" value="0">無期限
	</td></tr>
	<tr><td style="text-align:right;">有効期間（今から）</td>
	<td>
	<input type="radio" class="period" name="period" value="1">1ヶ月
	<input type="radio" class="period" name="period" value="2">3ヶ月
	<input type="radio" class="period" name="period" value="3">半年
	<input type="radio" class="period" name="period" value="0">無期限
	</td></tr>
    </table>
    <input type="hidden" name="ui" value="<?=$ui?>">
    <input type="hidden" name="Ln" value="<?=$userLn?>">
    <input type="hidden" name="Sn" value="<?=$sn?>">
	<p class="center"><input type="submit" name="send" value="変更"> &nbsp;
	<a href="userkanri.php"><input type="button" name="" value="最初から検索する"></a></p>
<?php
} else {
?>
<table class="search"><tr>
<td>名前で検索</td>
</tr>
<tr>
	<td style="width:200px;"><input type="text" class="searchkey" name="Sn" value="" placeholder="名前を入力して[検索]をクリック" style="font-size:14px; width:200px; height:32px;"/>
	</td></tr>
	<td style="width:400px;"><input type="text" class="searchkey" name="Em" value="" placeholder="またはメールを入力して[検索]をクリック" style="font-size:14px; width:400px; height:32px;"/></td>
<td><input class="searchbuttonclass" type="submit" name="send" value="検索" value="Send"></td>
</tr></table>
<?php
}
?>
<input type="hidden" name="pn" value="<?=$pageNo?>">
<table><tr><td style="font-size:11px">

<?php
for ($i = 1; $i <= $pageCount; $i++) {
   if ($i == $pageNo) { // this is current page
       echo 'Page ' . $i . '&nbsp;';
   } else { // show link to other page
       echo '<a href="userkanri.php?p=' . $i . '">Page ' . $i . '</a>&nbsp;';
   }
}
?>
</td></tr></table>
</form>
</div>
<p class="footer_img"><br>Copyright © 2014-2015 J Hirota. All rights reserved.</p>
</body>
</html>
