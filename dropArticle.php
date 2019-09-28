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

$mi = null;
$title = '';
$text = '';
$category = '';
$genre = '';
$quote = '';
$keywords = '';
$osmessage = '';
$projectId = 0;
$lastprojId = 0;

$items_per_page = 20;

if ($_POST) {
	$send = $_POST['send'];
	$projectId = $_POST['project'];
	$lastprojId = $_POST['lastproject'];
	$keywords = isset($_POST['keywords'])?$_POST['keywords']:'';
	$quote = $_POST['quote'];
	if ($send==="保存") {
		$mi = $_POST['mi'];
		$title = $_POST['title'];
		$text = str_replace("'", "\'", $_POST['text']);
		if ($projectId) {
			$sql = "select category, genre from os_projects where os_proj_id=".$projectId;
			$result = getDB($sql);
			$rec = $result[0];
			$category = $rec['category'];
			$genre = $rec['genre'];
			if ($projectId!=$lastprojId) { // project was changed
				if ($lastprojId) {
					$sql = "update os_proj_article set os_proj_id=$projectId where article_id=$mi and os_proj_id=".$lastprojId;
					$result = getDB($sql,false);
				}
			}
			$sql = sprintf("call update_article(%u,'%s','%s',%u,'%s','%s','%s',1)", $mi,$title,$text,$category,$genre,$keywords,$quote);
			$result = getDB($sql,false);
			$message = "記事を変更しました";
		} else
			$message = "タスクが選択されてません";
	} else if ($send==="追加") {
		if (isset($_POST['title']) && isset($_POST['text'])) {
			$mi = $_POST['mi'];
			$title = $_POST['title'];
			if ($projectId) {
				$text = str_replace("'", "\'", $_POST['text']);
				$sql = "select category, genre from os_projects where os_proj_id=".$projectId;
				$result = getDB($sql);
				$rec = $result[0];
				$category = $rec['category'];
				$genre = $rec['genre'];
				$sql = "insert into article (title,text,category,genre,keywords,quote,reuse,ready,group_id,user_id)
		values (:iTitle,:iText,:iCategory,:iGenre,:iKeywords,:Quote,1,0,:iGroupID,:iUserID);";
				$params = array (
					':iTitle'=>$title,
					':iText'=>$text,
					':iCategory'=>$category,
					':iGenre'=>$genre,
					':iKeywords'=>$keywords,
					':Quote'=>$quote,
					':iGroupID'=>$userGroup,
					':iUserID'=>$userID
				);
				$result = getDB($sql,false,$params);
				$articleID = getLastInsertedID();
				$sql = "insert into os_proj_article(os_proj_id,article_id,approved) values(:iProjID,:iArticle,0)";
				$params = array (
				':iProjID'=>$projectId,
				':iArticle'=>$articleID
				);
				$result = getDB($sql,false,$params);
				$message = "記事を追加しました";
			} else 
				$message = "タスクが選択されてません";
		} else {
			$message = "記事名かテキストが入力されてません";
		}
	} else if ($send==="削除") {
		if (isset($_POST['delete'])) {
			if (is_array($_POST['delete'])) {
				foreach($_POST['delete'] as $i) {
					$resId = $_POST['resId'.$i];
					deleteArticle($resId);
				}
			} else {
				$resId = $_POST['delete'];
				deleteArticle($resId);
			}
		}
	} else if ($send==="upload") {
		$filename = basename($_FILES['fileToUpload']['name']);
		$targetDir = "tmp".$userGroup."/";
		$target_file = $targetDir.$filename;
		$uploadOk = true;
		$fileType = pathinfo($target_file, PATHINFO_EXTENSION);
		// Check file size
		if ($_FILES["fileToUpload"]["size"] > 10000) {
			$message = "ファイルが大きすぎです！";
			$uploadOk = false;
		}
		// Check if $uploadOk is set to 0 by an error
		if (!$uploadOk) {
			$message = "ファイルがアップロードされてません！";
		} else {
			// if everything is ok, try to upload file
			if (move_uploaded_file($_FILES["fileToUpload"]["tmp_name"], $target_file)) {
				$message = "ファイルアップロード成功";
				extractArticles($target_file);
			} else
				$message = "ファイルアップロードエラー";
		}
	}
} else {
	if (isset($_GET['mi'])) {
		$mi = $_GET['mi'];
		$sql = "select * from article a left outer join os_message m on m.to_user=$userID and m.article_id=a.article_id left outer join os_proj_article o on a.article_id=o.article_id where a.article_id=".$mi;
		$result = getDB($sql);
		$rec = $result[0];
		$title = $rec['title'];
		$text = stripslashes($rec['text']);
		$category = $rec['category'];
		$genre = $rec['genre'];
		$keywords = $rec['keywords'];
		$quote = $rec['quote'];
		$osmessage = $rec['message'];
		$projectId = $rec['os_proj_id'];
	}
}
$lastprojId = $projectId;

function deleteArticle($resId) {
	global $debugmode;
	if ($debugmode) echo "deleting ".$resId."<br /><br />\n";
	$sql = "delete from os_proj_article where article_id=".$resId;
	$result = getDB($sql,false);
	$sql = sprintf("call delete_article(%s)", $resId);
	$result = getDB($sql,false);
}

function getNumList() {
	global $userID;
	$sql = "select count(*) n from article where user_id=".$userID;
	$result = getDB($sql);
	return $result[0]['n'];
}

function getArticleList($offset, $numItems) {
	global $userID;
    $sql = "select a.article_id,title,text,category,os_proj_id,approved,message from article a left outer join os_proj_article o on a.article_id=o.article_id left outer join os_message m on m.to_user=$userID and m.article_id=a.article_id where a.user_id=$userID order by update_date desc limit ".$offset.",".$numItems;
	$result = getDB($sql);
	return $result;
}

function extractArticles($filename) {
	global $category, $debugmode;
	
	$delimiter = ",";
	$list = array();
	
	if (($handle = fopen($filename, 'r')) !== FALSE) {
		$i = 1;
		$header = fgetcsv($handle, 1000, $delimiter);
		while ($i<=20 && ($row = fgetcsv($handle, 1000, $delimiter)) !== FALSE) {
			$title = $row[0];
			$text = $row[1];
			if ($debugmode) echo "$i [".$title."] &nbsp; [".$text."]<br>\n";
			ob_flush();
			flush();
			$article = new articleDataClass(null,null,$title,$text,$category);
			$article->quote = $quote;
			$article->reuse = 1;
			$article->saveData();
			$i++;
		}
		fclose($handle);
	} else $message = "ファイルが見つかりません";
	return $list;
}
?>
<html lang="ja">
<head>
	<meta Content-Type: text/html; charset=UTF-8 />
	<link rel="stylesheet" href="style.css" type="text/css" />
	<link rel="stylesheet" href="css/pb-style.css">
	<link rel="stylesheet" href="//code.jquery.com/ui/1.10.3/themes/smoothness/jquery-ui.css">
	<title>記事追加</title>
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
<div class="subtitle">記事追加</div>
<span id="message"><?=$message?></span>

<div class="container_main">
<form class="select" method="POST" enctype="multipart/form-data">
<input type=hidden name="userid" value="<?=$userID?>" />

<table>
<tr style="text-align:left;">
<th>タイトル</th><th></th><th>タスク</th></tr>
<tr>
<td><input class="" name="title" type="text" size="60" value="<?=$title?>" /></td>
<td>
<div id='category'></div>
</td>
<td>
	<select name="project">
<?php
	$sql = "select * from os_projects o left join outsourcing s on s.os_id=o.os_id left join users u on s.user_id=u.user_id where u.user_id=".$userID;
	$projects = getDB($sql);
	$i = 1;
	foreach ($projects as $row) {
		$id = $row['os_proj_id'];
		$pname = $id.' '.$row['proj_name'];
		echo "<option value='".$id."'".(($projectId==$id)?" selected":"")
			.">".$pname."</option>\n";
	}
?>
	</select>
</td>
</tr>
<tr style="text-align:left;"><th>テキスト</th></tr>
<tr>
<td colspan=3><textarea id="articleText" name="text" style="height:200px;"><?=$text?></textarea><br>
<div class="nlines" id="nChars"> </div>
</td>
<td style="text-align:center;">
<?php
if ($mi) {
	if ($osmessage) 
		echo '<img class="masterTooltip" title="'.$osmessage.'" src="img/message-2-s.png"><br><br>';
	echo '<input class="button" type="submit" name="send" value="保存">';
} else {
	echo '<input class="button" type="submit" name="send" value="追加">';
} 
?>
</td></tr>
<tr style="text-align:left;"><th colspan=2>引用元</th></tr>
<td colspan=2><input class="" name="quote" type="text" size="60" value="<?=$quote?>"></td>
</tr>
<tr>
<td style="text-align:left;">
</td>
<td colspan=3 style="border:0;text-align:right;">
<a href="<?=$selfName?>"><input class="buttonclass" type="button" value="新規"></a> &nbsp; &nbsp; 
<input class="buttonclass" type="submit" name="send" value="削除"></td>
</tr>
</table>
<input type='hidden' name='mi' value='<?=$mi?>'>
<input type='hidden' name='lastproject' value='<?=$lastprojId?>'>
<br>

<table class="kwtool" style="width:98%;">
<tr><th>タイトル</th><th>テキスト</th><th>タスク</th><th>承認</th><th>編集</th><th>削除</th></tr>
<?php
$rowCount = getNumList();
$pageCount = (int)ceil($rowCount/$items_per_page);
if (!$pageNo) {
	$pageNo = $pageCount;
}
// get reservation table data
$offset = ($pageNo - 1) * $items_per_page;
$limit = $items_per_page * $pageNo;
$list = getArticleList($offset, $items_per_page);
if (count($list)) {
	$i=1;
	foreach ($list as $rec) {
		$id = $rec['article_id'];
		$tt = $rec['title'];
		$tx = $rec['text'];
//		$ca = $rec['category'];
		$pr = $rec['os_proj_id'];
		$ap = $rec['approved']?'<img src="img/check-t07.png">':'';
		$me = $rec['message'];
		if ($ap)
			$me = $ap;
		else
			if ($me) $me = '<img class="masterTooltip" title="'.$me.'" src="img/message-2-s.png">';

		echo "<tr>";
		echo "<td>".$tt."</td>";
		echo "<td width='70%'>".$tx."</td>";
//		echo "<td style='text-align:center;'>".$ca."</td>";
		echo "<td style='text-align:center;'>".$pr."</td>";
		echo "<td>".$me."</td>";
		echo '<td>';
		if (!$ap) 
			echo '<span class="small"><a href="'.$selfName.'?mi='.$id.'&p='.$pageNo.'"><img src="img/icon_edit.png"></a></span>';
		echo "<input type='hidden' name='resId".$i."' value='".$id."' /></td><td>";
		if (!$ap) echo "<input type='checkbox' id='d$i' name='delete[]' value='".$i."' /><label for='d$i'><span></span></label>";
		echo "</td></tr>\n";
		
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


<script type="text/JavaScript">
$(function() {
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

	window.setInterval("setCharNum()", 1000);
});

function setCharNum() {
	var mess = "";
	var nChars = $("#articleText").val().length;
	if (nChars>1000) {
		$("#nChars").css('color','red');
//		mess = " 　1000文字以上の文章はいくつかに分けて制作してください。";
	} else if (nChars>800) {
			$("#nChars").css('color','DarkOrange ');
//			mess = " 　文字数が多くなると途中で止まることがあります。";
		}
	else $("#nChars").css('color','black');
	$("#nChars").text("文字数: " + nChars + mess);
}

</script>
</body>
</html>