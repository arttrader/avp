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

$items_per_page = 20;

if ($_POST) {
	$send = $_POST['send'];
	$category = $_POST['category'];
	$genre = isset($_POST['genre'])?$_POST['genre']:'';
	$quote = $_POST['quote'];
	if ($send==="変更") {
		$mi = $_POST['mi'];
		$title = $_POST['title'];
		$text = str_replace("'", "\'", $_POST['text']);
		$sql = sprintf("call update_article(%u,'%s','%s',%u,'%s','%s','%s',1)", $mi,$title,$text,$category,$genre,$keywords,$quote);
		$result = getDB($sql,false);
		$message = "記事を変更しました";
	} else if ($send==="追加") {
		if (isset($_POST['title']) && isset($_POST['text'])) {
			$mi = $_POST['mi'];
			$title = $_POST['title'];
			$text = str_replace("'", "\'", $_POST['text']);
			$sql = sprintf("call add_article('%s','%s',%u,'%s','%s','%s',%u,%u,%u)", $title,$text,$category,$genre,$keywords,$quote,1,$userGroup,$userID);
			$result = getDB($sql,false);
			$message = "記事を追加しました";
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
		$csv = $_FILES['fileToUpload']['tmp_name'];
		$utf8 = isset($_POST['utf8'])?true:false;
		if (!$utf8)
			$buffer = mb_convert_encoding(file_get_contents($csv), "UTF-8", "sjis");
		else
			$buffer = file_get_contents($csv);
		$fp = tmpfile();
		fwrite($fp, $buffer);
		rewind($fp);
		extractArticles($fp);
		fclose($fp);
	}
} else {
	if (isset($_GET['mi'])) {
		$mi = $_GET['mi'];
		$sql = "select * from article where article_id=".$mi;
		$result = getDB($sql);
		$rec = $result[0];
		$title = $rec['title'];
		$text = stripslashes($rec['text']);
		$category = $rec['category'];
		$genre = $rec['genre'];
		$keywords = $rec['keywords'];
		$quote = $rec['quote'];
	}
}

function deleteArticle($resId) {
	global $debugmode;
	if ($debugmode) echo "deleting ".$resId."<br /><br />\n";
	$sql = "delete from os_proj_article where article_id=".$resId;
	$result = getDB($sql,false);
	$sql = sprintf("call delete_article(%s)", $resId);
	$result = getDB($sql,false);
}

function getNumList() {
	global $userGroup;
	$sql = "select count(*) n from article where reuse=1 and group_id=".$userGroup;
	$result = getDB($sql);
	return $result[0]['n'];
}

function getArticleList($offset, $numItems) {
	global $userGroup;
    $sql = "select * from article a left join category c on a.category=c.category_id where reuse=1 and a.group_id=$userGroup order by update_date desc limit ".$offset.",".$numItems;
	$result = getDB($sql);
	return $result;
}

function extractArticles(&$handle) {
	global $category, $debugmode;

	$delimiter = ",";
	$list = array();

	if ($handle) {
		$i = 1;
		ini_set("auto_detect_line_endings", true);
//		$header = fgetcsv($handle, 1000, $delimiter);
		while ($i<=100 && ($row = fgetcsv($handle, 2000, $delimiter)) !== FALSE) {
			$title = $row[0];
			if ($title==="タイトル") continue; // skip a header line
			if (isset($row[1])) {
				$text = $row[1];
				$category = isset($row[2])?$row[2]:0;
				$quote = isset($row[3])?$row[3]:'';
				if ($debugmode) echo "$i [".$title."] &nbsp; [".$text."] &nbsp; [".$category."]<br>\n";
				ob_flush();
				flush();
				$article = new articleDataClass(null,null,$title,$text,$category);
				$article->quote = $quote;
				$article->reuse = 1;
				$article->saveData();
				$i++;
			}
		}
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
	<title>記事管理</title>
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
<div class="subtitle">記事管理</div>
<span id="message"><?=$message?></span>

<div class="container_main">
<form class="select" method="POST" enctype="multipart/form-data">
<input type=hidden name="userid" value="<?=$userID?>" />

<table class="">
<tr style="text-align:left;">
<th>タイトル</th><th>カテゴリー</th></tr>
<tr>
<td><input class="" name="title" type="text" size="60" value="<?=$title?>" /></td>
<td>
	<select name="category">
<?php
	$sql = "select * from category order by category_id";
	$perms = getDB($sql);
	$i = 1;
	foreach ($perms as $line) {
		$id = $line['category_id'];
		$pname = $id.' '.$line['name'];
		echo "<option value='".$id."'".(($category===$id)?" selected":"")
			.">".$pname."</option>\n";
	}
?>
	</select>
</td>
</tr>
<tr><td>テキスト</td></tr>
<tr>
<td colspan=3><textarea id="articleText" name="text" style="height:200px;"><?=$text?></textarea><br>
<div class="nlines" id="nChars"> </div>
</td>
<td>
<?php
if ($mi) {
	echo '<input class="button" type="submit" name="send" value="変更">';
} else {
	echo '<input class="button" type="submit" name="send" value="追加">';
}
?>
</td></tr>
<tr><td colspan=2>引用元</td></tr>
<tr><td colspan=2><input name="quote" type="text" size="40" value="<?=$quote?>"></td></tr>
<tr>
<td style="text-align:left;">
　<table class="search">
	<tr><td>CSVファイルアップロード</td></tr>
	<tr>
	<td style="font-size:10px;"><input type="file" name="fileToUpload" id="fileToUpload"> &nbsp; &nbsp; <a href="kiji_template.csv" target="blank">CSVテンプレートはこちら</a><br><input type="checkbox" id="utf8" name="utf8"><label for="utf8"><span></span></label>UTF-8ファイルを使用</td>
	<td>
	<div id="search-div"><input class="button" type="submit" name="send" value="upload"> </div></td></tr>
	<tr><td style="font-size:10px;">*タイトル、記事、カテゴリ(数字のみ)、引用をコンマ(,)で区切ったリストのCSVファイルをShift-JIS形式（Excelなど）に保存されたものを使用</td><td></td>
	</tr>
  </table>
</td>
<td colspan=3 style="border:0;text-align:right;">
<a href="<?=$selfName?>"><input class="buttonclass" type="button" value="新規"></a> &nbsp; &nbsp;
<input class="buttonclass" type="submit" name="send" value="削除"></td>
</tr>
</table>
<input type='hidden' name='mi' value='<?=$mi?>'>
<br>

<table class="kwtool" style="width:98%;">
<tr><th>タイトル</th><th style="width:70%;">テキスト</th><th>カテゴリー</th><th style="width:10%;">引用元</th><th>編集</th><th><input type="checkbox" id="selectAll"><label for='selectAll'><span></span></label>削除</th></tr>
<?php
$rowCount = getNumList();
$pageCount = (int)ceil($rowCount/$items_per_page);
if (!$pageNo) {
	$pageNo = $pageCount;
}
// get reservation table data
$offset = ($pageNo - 1) * $items_per_page;
$limit = $items_per_page * $pageNo;
$musicList = getArticleList($offset, $items_per_page);
if (count($musicList)) {
	$i=1;
	foreach ($musicList as $rec) {
		$id = $rec['article_id'];
		$tt = $rec['title'];
		$tx = $rec['text'];
		$ca = $rec['category'];
		$cn = $rec['name']; // category name
		$qt = $rec['quote'];
//		$ge = $rec['genre'];
//		$ke = $rec['keywords'];

		echo "<tr>";
		echo "<td>".$tt."</td>";
		echo "<td>".$tx."</td>";
		echo "<td style='text-align:center;'>".$cn."</td>";
		echo "<td>".$qt."</td>";
//		echo "<td>".$ke."</td>";
		echo '<td><span class="small"><a href="'.$selfName.'?mi='.$id.'&p='.$pageNo.'"><img src="img/icon_edit.png"></a></span>';
		echo "<input type='hidden' name='resId".$i."' value='".$id."' /></td>";
		echo "<td><input type='checkbox' id='d$i' class='delbtn' name='delete[]' value='".$i."' /><label for='d$i'><span></span></label></td>";
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


<script>
$(function() {
	$('#selectAll').click(function() {
		var setvar = $(this).prop('checked');
		$('.delbtn').each(function() {
			$(this).prop('checked', setvar);
		});
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
