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

//start session
session_start();
if ($_POST) {
	$key = $_POST['key'];
	$pass = $_POST['pass'];
	$authController = new AuthController();
	if ($authController->login($key, $pass, $ipaddress, $pipaddress, $uagent, $ref))
		$_SESSION['authController'] = serialize($authController);
} else {
	$key = "";
	$pass = "";
}
if (isset($_SESSION['authController'])) {
	$authController = unserialize($_SESSION['authController']);
	if (is_object($authController) && $authController->checkLogin()) {
		if ($authController->checkLogin()) {
			$name = $authController->name;
			$userID = $authController->userId;
			if ($appversion>=7)
				if ($authController->userLevel<7) {
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
	<link rel='stylesheet' type='text/css' href='style.css'>
	<meta http-equiv="content-script-type" content="text/javascript" />
	<script src='http://ajax.googleapis.com/ajax/libs/jquery/1.10.2/jquery.min.js'></script>
	<script type='text/javascript' src="../js/jquery.bpopup.min.js"></script>
	<script type='text/javascript' src='../js/menu_jquery.js'></script>
	<style>
#popup {
    background-color:transparent;
    border-radius:2px;
    color:black;
    display:none; 
    padding:0px;
    min-width:400px;
    min-height:250px;
}

#popup .bMulti {
    background-color: #fff;
    border-radius: 10px 10px 10px 10px;
    box-shadow: 0 0 5px 5px #999;
    color: #111;
    display: none;
    min-width: 600px;
    padding: 25px
}
	</style>
</head>
<body>
<div id='cssmenu'>
<ul>
   <li class='active'><a href='index.php'><span>Home</span></a></li>
   <li class='has-sub'><a href='#'><span>MATERIALS</span></a>
      <ul>
         <li><a href='manageMusic.php' target='_blank'><span>音楽素材</span></a></li>
         <li><a href='manageImage.php' target='_blank'><span>画像素材</span></a></li>
         <li><a href='manageVideo.php' target='_blank'><span>動画素材</span></a></li>
         <li class='last'><a href='manageArticle.php' target='_blank'><span>記事素材</span></a></li>
      </ul>
   </li>
   <li class='has-sub'><a href='#'><span>PRODUCER</span></a>
      <ul>
      	 <li class='last'><a href='manageVideoProduction.php' target='_blank'><span>自動動画制作</span></a></li>
      </ul>
   </li>
   <li class='has-sub last'><a href='#'><span>LOGOUT</span></a>
      <ul>
         <li><a href='logout.php'><span>ログアウト</span></a></li>
         <li class='last'><a href='changelogininfo.php'><span>ログイン情報変更</span></a></li>
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
<div id="popup">
<div class="container">
<form class="login" method="POST">
<table class="loginbox">
<tr><td style="width:120px; text-align:right;">ユーザーID：</td><td style="text-align:left;">
<input type="text" name="key" size=16 value="" /></td></tr>
<tr style="text-align:right;"><td>パスワード：</td><td style="text-align:left;">
<input type="password" name="pass" size=16 value="" /></td></tr>
<tr><td colspan=2 style="text-align:center; font-size:10px"></td></tr>
<tr><td colspan=2 style="padding-top:25px; text-align: center;"><input type="submit" class="loginbuttonclass" value="ログイン"><input type="hidden" name="ip" value="" /></td></tr>
</table>
</form>
</div><!--end container-->
</div>
<?php } ?>
<div class="center"><img class="logo" src=""></div>

<p class="footer_img"><br />Copyright © 2015 J Hirota. All rights reserved.</p>

<script>

$(function() {
	if (!$.support.leadingWhitespace) {
		//IE7 and 8 stuff
		$('#iewarning').show();
	} else {
		$('#iewarning').hide();
	}
	
	
	$('#popup').bPopup({
		opacity: 0.6,
		positionStyle: 'fixed' //'fixed' or 'absolute'
	});

	$('.logo').click(function() {
		//$('#content').attr('src', url);
		$('#popup').bPopup({
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