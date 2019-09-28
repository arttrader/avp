<?php
require_once 'AuthController.php';

require_once 'videoDataClass.php';
require_once 'createVideoClass.php';

$userID = 1002;
$userLogin = 'tester3';

$title = '';


//$job = new jDataClass(1);

$vid = new vDataClass(2);

//var_dump($vid);

//exit();

$vidCreator = new videoCreatorClass($userID,$userLogin);
//$vidCreator->watermark = 'newtuber.png';

//foreach ($job->videoData as $video)
$vidCreator->createVideo($vid);




/*
<html langu="ja">
<head>
	<meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
	<title>動画クリエイター</title>
	<link rel="stylesheet" href="style.css">
</head>

<body>
<div class="tool_00">
<div class="subtitle"><img src="img/none_01.png" alt="動画クリエイター">
<p style="position:relative;top:-120px;left:100px;text-align:left;">動画クリエイター</p></div>
<div class="toolbox" style="margin-top:-90px;">

<div id="tabs" style="height:140px;">
<form class="searchform" method="POST">
  <table class="search">
	<tr>
	<td style="width:400px;"><input type="text" class="searchinput" id="searchkey" name="title" value="<?=$title?>" placeholder="" style="font-size:14px; width:400px; height:32px;"/>
	</td>
	<td>
	</td>
	<td><div id="search-div"><input class="" type="submit" name="send" value="create"></div></td>
	</tr>
  </table>
</form>

</body>
</html>
*/