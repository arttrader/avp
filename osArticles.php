<?php
require_once 'AuthController.php';
require_once 'videoDataClass.php';

// authenticate the use of this system
// use this as a set on every page that requires authorization
//start session

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
$text = '';
$category = '';
$genre = '';
$quote = '';
$keywords = '';
$projectID = 0;
$authorId = 0;
$authorName = '';
$osmessage = '';
$approved = 0;

$items_per_page = 20;

if ($_POST) {
	$send = $_POST['send'];
	$category = $_POST['category'];
	$genre = isset($_POST['genre'])?$_POST['genre']:'';
	$keywords = $_POST['keywords'];
	$quote = $_POST['quote'];
	$osmessage = $_POST['message'];
	$authorId = $_POST['author'];
	$authorName = $_POST['osname'];
	$approved = $_POST['approved'];
	if ($send==="修正リクエスト") {
		$mi = $_POST['mi'];
		$title = $_POST['title'];
		$text = str_replace("'", "\'", $_POST['text']);
		$sql = sprintf("call update_article(%u,'%s','%s',%u,'%s','%s','%s',1)", $mi,$title,$text,$category,$genre,$keywords,$quote);
		$result = getDB($sql,false);
		if ($approved) {
			$approved = 0;
			$sql = "update os_proj_article set approved=0 where article_id=".$mi;
			$result = getDB($sql,false);
		}
		$message = "修正リクエストを送りました";		
	}
	if ($send==="承認") {
		$mi = $_POST['mi'];
		$title = $_POST['title'];
		$approved = 1;
		$text = str_replace("'", "\'", $_POST['text']);
		$sql = sprintf("call update_article(%u,'%s','%s',%u,'%s','%s','%s',1)", $mi,$title,$text,$category,$genre,$keywords,$quote);
		$result = getDB($sql,false);
		$sql = "update os_proj_article set approved=$approved where article_id=".$mi;
		$result = getDB($sql,false);
		$message = "記事を承認しました";		
	}
	if ($send==="修正") {
		$mi = $_POST['mi'];
		$title = $_POST['title'];
		$text = str_replace("'", "\'", $_POST['text']);
		$sql = sprintf("call update_article(%u,'%s','%s',%u,'%s','%s','%s',1)", $mi,$title,$text,$category,$genre,$keywords,$quote);
		$result = getDB($sql,false);
		$message = "記事を修正しました";		
	}
	if ($osmessage) {
		$sql = "call update_osmessage(:iToUser,:iFrUser,:iMessage,:iAId)";
		$params = array (
			':iToUser'=>$authorId,
			':iFrUser'=>$userID,
			':iMessage'=>$osmessage,
			':iAId'=>$mi
		);
		$result = getDB($sql,false,$params);
	}
}
if (isset($_GET['o']))
	$projectID = $_GET['o'];
if (isset($_GET['mi'])) {
	$mi = $_GET['mi'];
	$sql = "select * from article a left join users u on a.user_id=u.user_id left outer join os_message m on m.from_user=$userID and m.article_id=a.article_id left outer join os_proj_article o on a.article_id=o.article_id where a.article_id=".$mi;
	$result = getDB($sql);
	$rec = $result[0];
	$title = $rec['title'];
	$text = stripslashes($rec['text']);
	$category = $rec['category'];
	$genre = $rec['genre'];
	$keywords = $rec['keywords'];
	$quote = $rec['quote'];
	$authorId = $rec['user_id'];
	$authorName = $rec['name'];
	$osmessage = $rec['message'];
	$approved = $rec['approved'];
}


function getNumList() {
	global $projectID;
	$sql = "select count(*) n from os_proj_article p left join article a on p.article_id=a.article_id where p.os_proj_id=".$projectID;
	$result = getDB($sql);
	return $result[0]['n'];
}

function getArticleList($offset, $numItems) {
	global $projectID;
    $sql = "select a.article_id,title,a.text,a.category,a.user_id,p.approved,m.message from os_proj_article p left join article a on p.article_id=a.article_id left outer join os_message m on m.article_id=a.article_id where p.os_proj_id=$projectID group by article_id order by update_date desc limit ".$offset.",".$numItems;
	$result = getDB($sql);
	return $result;
}
?>
<html lang="ja">
<head>
	<meta Content-Type: text/html; charset=UTF-8 />
	<link rel="stylesheet" href="style.css" type="text/css" />
	<link rel="stylesheet" href="css/pb-style.css">
	<link rel="stylesheet" href="//code.jquery.com/ui/1.10.3/themes/smoothness/jquery-ui.css">
	<title>外注記事管理</title>
	<script src='//ajax.googleapis.com/ajax/libs/jquery/1.10.2/jquery.min.js'></script>
	<script src="//code.angularjs.org/1.4.5/angular.min.js"></script>
	<script type='text/javascript' src='../js/menu_app.js'></script>
<script type="text/javascript">
$(function() {
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
<div class="subtitle">外注記事管理</div>
<span id="message"><?=$message?></span>

<div class="container_main">
<form class="select" method="POST" enctype="multipart/form-data">
<input type=hidden name="userid" value="<?=$userID?>" />
<?php if ($mi) { ?>
<table class="">
<tr style="text-align:left;">
<th>タイトル</th><th style="width:60px;">カテゴリー</th><th>判定</th></tr>
<tr>
<td><input name="title" type="text" size="60" value="<?=$title?>"></td>
<td><?=$category?></td>
<td rowspan=3 class="center">
<input class="button" type="submit" name="send" value="承認"><br><br>
<input class="button" type="submit" name="send" value="修正"><br><br>
<input class="button" type="submit" name="send" value="修正リクエスト"><br><br>
<span style="font-size:10px;">外注へのメッセージ</span><br>
<textarea name="message"><?=$osmessage?></textarea></td>
</tr>
<tr style="text-align:left;"><th colspan=2>テキスト</th>
</tr>
<tr>
<td colspan=2 style="width:70%;"><textarea name="text" style="height:200px;"><?=$text?></textarea>
</td>
</tr>
<tr style="text-align:left;"><th>キーワード</th><th>引用元</th><th>委託先外注</th></tr>
<tr>
<td><input class="" name="keywords" type="text" size="60" value="<?=$keywords?>"></td>
<td><input class="" name="quote" type="text" size="40" value="<?=$quote?>"></td>
<td><?=$authorName?><input name="osname" type="hidden" value="<?=$authorName?>"></td>
</tr>
</table>
<input type=hidden name="category" value="<?=$category?>" />
<?php } ?>
<input name="author" type="hidden" value="<?=$authorId?>">
<input type='hidden' name='mi' value='<?=$mi?>'>
<input type='hidden' name='approved' value='<?=$approved?>'>
<br>

<table class="kwtool" style="width:98%;">
<tr><th>タイトル</th><th>テキスト</th><th>カテゴリー</th><th>外注</th><th>承認</th><th>チェック</th></tr>
<?php
$rowCount = getNumList();
$pageCount = (int)ceil($rowCount/$items_per_page);
if (!$pageNo) {
	$pageNo = $pageCount;
}
// get reservation table data
$offset = ($pageNo - 1) * $items_per_page;
$limit = $items_per_page * $pageNo;
$alist = getArticleList($offset, $items_per_page);
if (count($alist)) {
	$i=1;
	foreach ($alist as $rec) {
		$id = $rec['article_id'];
		$tt = $rec['title'];
		$tx = $rec['text'];
		$ca = $rec['category'];
		$ui = $rec['user_id'];
		$me = $rec['message'];
		$ap = $rec['approved']?'<img class="masterTooltip" title="'.$me.'" src="img/check-t07.png">':'';
		if ($ap)
			$me = $ap;
		else
			if ($me) 
				$me = '<img class="masterTooltip" title="'.$me.'" src="img/message-2-s.png">';

		echo "<tr>";
		echo "<td>".$tt."</td>";
		echo "<td>".$tx."</td>";
		echo "<td style='text-align:center;'>".$ca."</td>";
		echo "<td>".$ui."</td>";
		echo "<td>".$me."</td>";
		echo '<td>';
		echo '<span class="small"><a href="'.$selfName.'?o='.$projectID.'&mi='.$id.'&p='.$pageNo.'"><img src="img/icon_edit.png"></a></span>';
		echo "<input type='hidden' name='resId".$i."' value='".$id."' /></td>";
//		echo "<td><input type='checkbox' name='delete[]' value='".$i."' /></td>";
		echo "</tr>\n";
		
		$i++;
	}
}
//echo $genre.'<br><br>';
?>
</table>
<br>

<table><tr><td style="font-size:11px">
<?php
for ($i = 1; $i <= $pageCount; $i++) {
   if ($i == $pageNo) { // this is current page
       echo 'Page ' . $i . '&nbsp;';
   } else { // show link to other page   
       echo '<a href="'.$selfName.'?o='.$projectID.'&p=' . $i . '">Page ' . $i . '</a>&nbsp;';
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