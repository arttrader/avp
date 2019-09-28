<?php
require_once 'AuthController.php';

// authenticate the use of this system
// use this as a set on every page that requires authorization
//start session

session_start();
if (isset($_SESSION['authController'])) {
	$authController = unserialize($_SESSION['authController']);
	if (is_object($authController) && $authController->checkLogin()) {
		$name = $authController->name;
		$userID = $authController->userId;
		$userLevel = $authController->userLevel;
		if ($userLevel<7)
			header("Location:$rootUrl");
	} else
		header("Location:$rootUrl");
} else
	header("Location:$rootUrl");


$selfName = basename(__FILE__);


if (isset($_GET['p']))
	$pageNo = $_GET['p'];
else
	$pageNo = 0;

$items_per_page = 20;

if ($_POST) {
	$send = $_POST['send'];
	if ($send==="変更") {
		$mi = $_POST['mi'];
		$title = $_POST['title'];
		$text = str_replace("'", "\'", $_POST['text']);
		$category = isset($_POST['category']);
		$genre = $_POST['genre'];
		$keywords = $_POST['keywords'];
		$sql = sprintf("call update_article(%u,'%s','%s',%u,'%s','%s')", $mi,$title,$text,$category,$genre,$keywords);
		$result = getDB($sql);
		echo "<br /><br />記事を変更しました";		
	} else if ($send==="追加") {
		if (isset($_POST['title']) && isset($_POST['text'])) {
			$mi = $_POST['mi'];
			$title = $_POST['title'];
			$text = str_replace("'", "\'", $_POST['text']);
			$category = isset($_POST['category']);
			$genre = $_POST['genre'];
			$keywords = $_POST['keywords'];
			$sql = sprintf("call add_article('%s','%s',%u,'%s','%s',%u,%u)", $title,$text,$category,$genre,$keywords,1,$userID);
			$result = getDB($sql);
			echo "<br /><br />記事を追加しました";
		} else {
			echo "<div class='font_red'>記事名かテキストが入力されてません</div><br />";
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
	} else if (!strcmp($send,"戻る")) {
		header("Location:index.html");
		exit();
	}
} else {
	if (isset($_GET['p']))
		$pageNo = $_GET['p'];
	else
		$pageNo = 1;
	
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
	} else {
		$mi = null;
		$title = '';
		$text = '';
		$category = '';
		$genre = '';
		$keywords = '';
	}
}

function deleteArticle($resId) {
	echo "deleting ".$resId."<br /><br />\n";
	$sql = sprintf("call delete_article(%s)", $resId);
	$result = getDB($sql);
}

function getNumList() {
	global $userID;
	$sql = "select article_id from article where isnull(user_id) or user_id=".$userID;
	$result = getDB($sql);
	return count($result);
}

function getArticleList($offset, $numItems) {
	global $userID;
    $sql = "select * from article where isnull(user_id) or user_id=$userID limit ".$offset.",".$numItems;
	$result = getDB($sql);
	return $result;
}
?>
<html lang="ja">
<head>
	<meta Content-Type: text/html; charset=UTF-8 />
	<link rel="stylesheet" href="style.css" type="text/css" />
	<link rel="stylesheet" href="http://code.jquery.com/ui/1.10.3/themes/smoothness/jquery-ui.css">
	<link rel="stylesheet" href="css/jquery-ui-timepicker-addon.css" />
	<title>記事管理</title>
	<script src="http://ajax.googleapis.com/ajax/libs/jquery/1.9.1/jquery.min.js"></script>
	<script src="http://code.jquery.com/ui/1.10.3/jquery-ui.js"></script>
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

<div class="user_name">こんにちは、<?=$name?>さん</div>
<div class="subtitle">記事管理</div>

<div class="container_main">
<form class="select" method="POST" enctype="multipart/form-data">
<input type=hidden name="userid" value="<?=$userID?>" />

<table class="">
<tr style="text-align:left;">
<th>タイトル</th><th>カテゴリー</th><th style="text-align:center;">ジャンル</th></tr>
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
<td style="text-align:center;">
	<select name="genre">
	<option value="時事"<?=($genre==="時事")?' selected':''?>>時事</option>
	<option value=""<?=($genre==="")?' selected':''?>></option>
	<option value=""<?=($genre==="")?' selected':''?>></option>
	<option value=""<?=($genre==="")?' selected':''?>></option>
	</select>
</td></tr>
<tr><td>テキスト</td></tr>
<tr>
<td colspan=3><textarea name="text" style="height:200px;"><?=$text?></textarea>
</td>
<td>
<?php
if ($mi) {
	echo '<input class="" type="submit" name="send" value="変更">';
} else {
	echo '<input class="" type="submit" name="send" value="追加">';
} 
?>
</td></tr>
<tr><td>キーワード</td></tr>
<tr><td colspan=2><input class="" name="keywords" type="text" size="100" value="<?=$keywords?>"></td>
</tr>
<tr>
<td style="text-align:left;"></td>
<td colspan=3 style="border:0;text-align:right;">
<a href="<?=$selfName?>"><input class="buttonclass" type="button" value="新規"></a> &nbsp; &nbsp; 
<input class="buttonclass" type="submit" name="send" value="削除"></td>
</tr>
</table>
<input type='hidden' name='mi' value='<?=$mi?>'>
<br>

<table class="kwtool" style="width:98%;">
<tr><th>タイトル</th><th>テキスト</th><th>カテゴリー</th><th>ジャンル</th><th>キーワード</th><th>編集</th><th>削除</th></tr>
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
		$ge = $rec['genre'];
		$ke = $rec['keywords'];

		echo "<tr>";
		echo "<td>".$tt."</td>";
		echo "<td>".$tx."</td>";
		echo "<td style='text-align:center;'>".$ca."</td>";
		echo "<td>".$ge."</td>";
		echo "<td>".$ke."</td>";
		echo '<td><span class="button small"><a href="'.$selfName.'?mi='.$id.'&p='.$pageNo.'"><img src="img/icon_edit.png"></a></span>';
		echo "<input type='hidden' name='resId".$i."' value='".$id."' /></td>";
		echo "<td><input type='checkbox' name='delete[]' value='".$i."' /></td>";
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

</body>
</html>