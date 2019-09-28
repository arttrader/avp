<?php
require_once 'AuthController.php';
require_once 'videoDataClass.php';

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
	$pageNo = 1;

$mi = null;
$title = '';
$file = '';
$category = 0;
$genre = 0;
$keywords = '';
$targetDir = videoDataClass::getFolder();
$target_file = '';

$items_per_page = 20;

if ($_POST) {
	$send = $_POST['send'];
	if ($send==="変更") {
		$mi = $_POST['mi'];
//		$imageType = $_POST['imageType'];
		$title = $_POST['title'];
		$file = $_POST['file'];
		$category = isset($_POST['category']);
//		$genre = $_POST['genre'];
		$keywords = $_POST['keywords'];
		$type = $_POST['type'];
		$sql = sprintf("call update_video(%u,'%s',%u,'%s',%u)",
						$mi,$title,$category,$keywords,$type);
		$result = getDB($sql);
		echo "<br><br>動画データを変更しました";		
	} else if ($send==="追加") {
		if (!empty($_FILES['file']) && isset($_POST['title'])) {
			$filename = basename($_FILES['file']['name']);
			$target_file = $targetDir.$filename;
			$uploadOk = true;
			$fileType = pathinfo($target_file, PATHINFO_EXTENSION);
			// Check file size
			if ($_FILES["file"]["size"] > 20000000) {
				echo "ファイルが大きすぎです！";
				$uploadOk = false;
			}
			// Check if $uploadOk is set to 0 by an error
			if (!$uploadOk) {
				echo "ファイルをアップロードできません！";
			} else {
				// if everything is ok, try to upload file
				if (move_uploaded_file($_FILES["file"]["tmp_name"], $target_file)) {
					$title = $_POST['title'];
					$file = $filename;
/*					$ext = pathinfo($file, PATHINFO_EXTENSION);
					$fname = pathinfo($file, PATHINFO_FILENAME);
					$magicianObj = new imageLib($target_file);
					$magicianObj -> resizeImage(1920, 1080, 4); // 1080p default size 
					$magicianObj -> saveImage($targetDir.$fname.'.png');
					$magicianObj = null;
					unlink($target_file);
					$file = $fname.'.png';
					$target_file = $targetDir.$file; */
					$category = isset($_POST['category']);
					$type = $_POST['type'];
					$keywords = $_POST['keywords'];
					$sql = sprintf("call add_video('%s','%s',%u,'%s',%u,%u)",
									$title,$file,$category,$keywords,$type,$userID);
					$result = getDB($sql);
					echo "<br /><br />動画を追加しました";
				}
			}
		} else
			echo "<div class='font_red'>ファイルが選択されてません</div><br />";
	} else if ($send==="削除") {
		if (isset($_POST['delete'])) {
			if (is_array($_POST['delete'])) {
				foreach($_POST['delete'] as $i) {
					$resId = $_POST['resId'.$i];
					deleteVideo($resId);
				}
			} else {
				$i = $_POST['delete'];
				$resId = $_POST['resId'.$i];
				deleteVideo($resId);
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
		$sql = "select * from video where video_id=".$mi;
		$result = getDB($sql);
		$rec = $result[0];
		$title = $rec['title'];
		$file = $rec['filename'];
		$target_file = $targetDir.$file;
		$category = $rec['category'];
		//$genre = $rec['genre'];
		$keywords = $rec['keywords'];
		$type = $rec['video_type'];
	}
}

function deleteVideo($resId) {
	global $targetDir;
	echo "deleting ".$resId."<br /><br />\n";
	$sql = "select filename from video where video_id=".$resId;
	$result = getDB($sql);
	if (count($result)) {
		$rec = $result[0];
		$filename = $rec['filename'];
		$target_file = $targetDir.$filename;
		unlink($target_file);
		$sql = sprintf("call delete_video(%s)", $resId);
		$result = getDB($sql);
	} else echo "エラー: 削除できません！";
}

function getNumList() {
	global $userID;
	$sql = "select video_id from video where isnull(user_id) or user_id=".$userID;
	$result = getDB($sql);
	return count($result);
}

function getImageList($offset, $numItems) {
	global $userID;
    $sql = "select * from video where isnull(user_id) or user_id=$userID limit ".$offset.",".$numItems;
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
	<link href="videojs.thumbnails.css" rel="stylesheet">
	<title>CM動画管理</title>
	<script src="http://ajax.googleapis.com/ajax/libs/jquery/1.9.1/jquery.min.js"></script>
	<script src="http://code.jquery.com/ui/1.10.3/jquery-ui.js"></script>
	<script src='videojs.thumbnails.js'></script>
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
<div class="subtitle">CM動画管理</div>

<div class="container_main">
<form class="select" method="POST" enctype="multipart/form-data">
<input type=hidden name="userid" value="<?=$userID?>" />

<table class="">
<tr style="text-align:left;">
<th>タイトル</th><th>ファイル</th><th>カテゴリー</th><th>タイプ</th></tr>
<tr>
<td><input id="title" name="title" type="text" size="40" value="<?=$title?>"></td>
<td style="width:50px;"><input type="file" accept=".mp4,video/mp4" name="file" value="<?=$file?>"  onchange="readURL(this);"></td>
<td style="text-align:center;">
<select name="category">
<?php
	$sql = "select * from category";
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
<select name="type">
<option value='1'>CM</option>
<option value='2'>エンディング</option>
<option value='3'>その他</option>
</select>
</td></tr>
<tr><td><video id="thum" style="max-width:300px;max-height:240px;" controls>
    <source src="<?=$target_file?>">
</video></td></tr>
<tr><td>キーワード</td></tr>
<tr>
<td colspan=3><textarea name="keywords"><?=$keywords?></textarea>
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
<tr><td>&nbsp;</td></tr>
<tr>
<td style="text-align:left;"></td>
<td colspan=3 style="border:0;text-align:right;">
<a href="<?=$selfName?>"><input class="buttonclass" type="button" value="新規"></a> &nbsp; &nbsp; 
<input class="buttonclass" type="submit" name="send" value="削除"></td>
</tr>
</table>
<input type='hidden' name='mi' value='<?=$mi?>'>
<input type='hidden' name='file' value='<?=$file?>'>
<br>

<table class="kwtool" style="width:98%;">
<tr><th>タイトル</th><th>ファイル</th><th>カテゴリー</th><th>キーワード</th><th>タイプ</th><th>編集</th><th>削除</th></tr>
<?php
$rowCount = getNumList();
$pageCount = (int)ceil($rowCount/$items_per_page);
if (!$pageNo) {
	$pageNo = $pageCount;
}
// get reservation table data
$offset = ($pageNo - 1) * $items_per_page;
$imageList = getImageList($offset, $items_per_page);
if (count($imageList)) {
	$i=1;
	foreach ($imageList as $rec) {
		$id = $rec['video_id'];
		$tt = $rec['title'];
		$fi = $rec['filename'];
		$ca = $rec['category'];
//		$ge = $rec['genre'];
		$ke = $rec['keywords'];
		$ty = $rec['video_type'];

		echo "<tr>";
		echo "<td>".$tt."</td>";
		echo "<td>".$fi."</td>";
		echo "<td style='text-align:center;'>".$ca."</td>";
//		echo "<td>".$ge."</td>";
		echo "<td>".$ke."</td>";
		echo "<td>".$ty."</td>";
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
function readURL(input) {
	if (input.files && input.files[0]) {
		var reader = new FileReader();

		reader.onload = function (e) {
			$('#thum')
				.attr('src', e.target.result)
		};

		reader.readAsDataURL(input.files[0]);
		$('#title').val(input.files[0].name.replace(/\.[^/.]+$/, ""));
	}
}
</script>

</body>
</html>