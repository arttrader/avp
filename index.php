<?php
require_once("AuthController.php");

$uagent = getenv("HTTP_USER_AGENT");
if (isset($_SERVER['HTTP_REFERER'])) {
	$ref = $_SERVER['HTTP_REFERER'];
} else {
	$ref = "";
}
if (getenv('HTTP_X_FORWARDED_FOR')) {
	$pipaddress = getenv('HTTP_X_FORWARDED_FOR');
	$ipaddress = getenv('REMOTE_ADDR');
} else {
	$pipaddress = "";
	$ipaddress = getenv('REMOTE_ADDR');
}

$needlogin = false;
$userLevel = 0;

// set session parameter
$session_expiration = time() + 3600 * 24 * 2; // +2 days
session_set_cookie_params($session_expiration);
//start session
session_start();
if ($_POST) {
	$key = $_POST['key'];
	$pass = $_POST['pass'];
	$authController = new AuthController();
	if ($authController->login($key, $pass, $ipaddress, $pipaddress, $uagent, $ref))
		$_SESSION[$sessionName] = serialize($authController);
} else {
	$key = "";
	$pass = "";
}
if (isset($_SESSION[$sessionName])) {
	$authController = unserialize($_SESSION[$sessionName]);
	if (is_object($authController) && $authController->checkLogin()) {
		if ($authController->checkLogin()) {
			$name = $authController->name;
			$userID = $authController->userId;
			$userLevel = $authController->userLevel;
			if ($appversion>=1)
				if ($userLevel<1) {
					$needlogin = true;
					echo "ここはVideo Producerユーザーのみご利用いただけます";
					$authController->logout();
				}
			if ($authController->userLevel >= 20) error_reporting(E_ALL);
		} else $needlogin = true;
	} else
		$needlogin = true;
} else
	$needlogin = true;

?>
<html lang="ja">
<head>
	<meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
	<title>AUTO VIDEO PRODUCER</title>
	<link rel="shortcut icon" href="/favicon.ico" type="image/x-icon">
	<link rel="icon" href="/favicon.ico" type="image/x-icon">
	<link rel='stylesheet' type='text/css' href='style.css'>
	<meta http-equiv="content-script-type" content="text/javascript" />
	<script src='//ajax.googleapis.com/ajax/libs/jquery/1.10.2/jquery.min.js'></script>
	<script src="//code.angularjs.org/1.4.5/angular.min.js"></script>
	<script type='text/javascript' src="../js/jquery.bpopup.min.js"></script>
	<script type='text/javascript' src='../js/menu_app.js'></script>
	<style>
.login{
width:450px;
height:250px;
}
.loginbox{
position:relative;
margin-top:30px;
border:0;
width :450px;
}
.loginbox tr{margin:0 60px; 0 auto;}
.loginbox td{
padding-left:15px;
}
.loginbox input{
font-size:large;
}
.loginbuttonclass {
    background-image:url(img/login_off.png);
    color:transparent;
    background-color:transparent;
    background-repeat:no-repeat;
    width:290px;
    height:62px;
    border:0px;
}
.loginbuttonclass:hover{ 
    background-image:url(img/login_on.png);
}
#popup1 {
    background-color:transparent;
    border-radius:2px;
    display:none; 
    padding:0;
    width:450px;
    height:250px;
	box-shadow: 0 0 40px #AAA;
background-image:url(img/login.png);
background-position:top center;
background-repeat:no-repeat;
}
/*
#popup1 .bMulti {
    background-color: #fff;
    border-radius: 10px 10px 10px 10px;
    box-shadow: 0 0 5px 5px #999;
    color: #111;
    display:none;
    width: 450px;
    padding:0
}
*/
.loginLabel {
	width:120px; 
	text-align:right;
	color:white;
}
	</style>
</head>
<body>
<div id='cssmenu' ng-app="menuApp" ng-controller="menuController">
<ul>
  <li ng-repeat="mi in menus | filter:lessThanE('level',<?=$userLevel?>)" ng-class="getClass(mi,menus)"><a href='{{ mi.link }}'><span>{{ mi.title }}</span></a>
    <ul>
    	<li ng-repeat="s in mi.subs | filter:lessThanE('level',<?=$userLevel?>)" ng-class="getClass(s,mi.subs)"><a href='{{ s.link }}'><span>{{ s.title }}</span></a>
    </ul>
  </li>
</ul>
</div>

<?php if (!$needlogin) { ?>

<div class="user_name_bg">
<div class="user_name">こんにちは、<?=$name?>さん
<?php
$daysremaining = $authController->daysremaining;
if ($daysremaining<20)
	echo "<font color='red'>あなたのアカウントの期限切れが近づいてます！"
							."あと".$daysremaining."日以内に更新してください！</font><br>";
?>
</div>
</div>
<div id="iewarning" class="font_red" style="display:none;">
現在、IE(インターネットエクスプローラー)11未満のブラウザなどはサポートされておりません。Firefox、Chrome、または最新のIEをダウンロードしてお使いください。
</div>
<?php } else { ?>
<div id="popup1">
<form class="login" method="POST">
<table class="loginbox">
<tr><td class="loginLabel">ユーザーID：</td><td style="text-align:left;">
<input type="text" name="key" size=16 value="" /></td></tr>
<tr class="loginLabel"><td>パスワード：</td><td style="text-align:left;">
<input type="password" name="pass" size=16 value="" /></td></tr>
<tr><td colspan=2 style="text-align:center; font-size:10px"></td></tr>
<tr><td colspan=2 style="padding-top:25px; text-align: center;"><input type="submit" class="loginbuttonclass" value="ログイン"><input type="hidden" name="ip" value="" /></td></tr>
</table>
</form>
</div>
<?php } ?>
<div class="center"><img class="logo" src="img/logo.png"></div>

<p class="footer_img"><br />Copyright © 2015 J Hirota. All rights reserved.</p>

<script>
$(function() {
	if (!$.support.leadingWhitespace) {
		//IE7 and 8 stuff
		$('#iewarning').show();
	} else {
		$('#iewarning').hide();
	}
	
	
	$('#popup1').bPopup({
		opacity: 0.6,
		positionStyle: 'fixed' //'fixed' or 'absolute'
	});

	$('.logo').click(function() {
		//$('#content').attr('src', url);
		$('#popup1').bPopup({
			opacity: 0.6,
			positionStyle: 'fixed' //'fixed' or 'absolute'
		});
	});

});
</script>

<script>
  (function(i,s,o,g,r,a,m){i['GoogleAnalyticsObject']=r;i[r]=i[r]||function(){
  (i[r].q=i[r].q||[]).push(arguments)},i[r].l=1*new Date();a=s.createElement(o),
  m=s.getElementsByTagName(o)[0];a.async=1;a.src=g;m.parentNode.insertBefore(a,m)
  })(window,document,'script','//www.google-analytics.com/analytics.js','ga');

  ga('create', 'UA-49992425-1', 'auto');
  ga('send', 'pageview');

</script>
</body>
</html>