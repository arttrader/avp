<?php
require_once 'AuthController.php';

require_once 'videoDataClass.php';

require_once 'array_column.php';

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
$category = '';
$genre = '';
$keywords = '';
$targetDir = musicDataClass::getFolder();
$musicFile = 'music/default.mp3';
$ffmpeg = $ini_array['ffmpeg'];
$tga = array();

$items_per_page = 20;

if ($_POST) {
	$send = $_POST['send'];
	if ($send==="変更") {
		$mi = $_POST['mi'];
		$title = $_POST['title'];
//		$file = $_POST['file'];
		$category = isset($_POST['category']);
		$genre = $_POST['genre'];
		//$keywords = $_POST['keywords'];
		$sql = sprintf("call update_music(%u,'%s',%u,%u,'%s')",
					$mi,$title,$category,$genre,$keywords);
		$result = getDB($sql);
		// change tags
		$sql = "delete from music_tag where music_id=".$mi;
		$result = getDB($sql);
		if (count($result) && $result[0][0]===$n2)
			echo "deleted $n2 tags<br>";
		$tags = isset($_POST['tags'])?$_POST['tags']:array();
		foreach ($tags as $i) {
			$tid = $_POST['tagId'.$i];
			$sql = sprintf("call attach_tag2music(%u, %u);",$tid, $mi);
			echo $sql."<br>";
			$result = getDB($sql);
		}			
		echo "<br /><br />ミュージックを変更しました";		
	} else if ($send==="追加") {
		if (isset($_POST['category']) && isset($_FILES['file'])) {
			$filename = str_replace(' ','',basename($_FILES['file']['name']));
			$musicFile = $targetDir.$filename;
			$uploadOk = true;
			$fileType = pathinfo($musicFile, PATHINFO_EXTENSION);
			// Check file size
			if ($_FILES["file"]["size"] > 100000000) {
				echo "ファイルが大きすぎです！";
				$uploadOk = false;
			}
			// Check if $uploadOk is set to 0 by an error
			if (!$uploadOk) {
				echo "ファイルをアップロードできません！";
			} else {
				// if everything is ok, try to upload file
				if (move_uploaded_file($_FILES["file"]["tmp_name"], $musicFile)) {
		//			$musicType = $_POST['musicType'];
					$title = $_POST['title'];
					$file = $filename;
					$ext = pathinfo($file, PATHINFO_EXTENSION);
					$fname = pathinfo($file, PATHINFO_FILENAME);
					if ($ext!=='mp3') {
						$file = $fname.'.mp3';
						$cmd = $ffmpeg.' -i '.$musicFile.' -ab 192k '.$targetDir.$file;
						exec($cmd);
						unlink($musicFile);
					}
					$category = isset($_POST['category']);
					$genre = $_POST['genre'];
					//$keywords = $_POST['keywords'];
					$sql = sprintf("call add_music('%s','%s','%s','%s','%s',%u)", 
										$title,$file,$category,$genre,$keywords,$userID);
					$result = getDB($sql);
					$mID = getLastInsertedID();
					$tags = isset($_POST['tags'])?$_POST['tags']:array();
					foreach ($tags as $i) {
						$tid = $_POST['tagId'.$i];
						$sql = "call attach_tag2music($tid, $mID);";
						$result = getDB($sql);
					}			
					echo "<br /><br />ミュージックを追加しました";
				}
			}
		} else
			echo "<div class='font_red'>音楽ファイルかカテゴリーが選択されてません</div><br />";
	} else if ($send==="削除") {
		if (isset($_POST['delete'])) {
			if (is_array($_POST['delete'])) {
				foreach($_POST['delete'] as $i) {
					$resId = $_POST['resId'.$i];
					deleteMusic($resId);
				}
			} else {
				$i = $_POST['delete'];
				$resId = $_POST['resId'.$i];
				deleteMusic($resId);
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
		$sql = "select * from music where music_id=".$mi;
		$result = getDB($sql);
		if (count($result)) {
			$rec = $result[0];
			$title = $rec['title'];
			$file = $rec['filename'];
			$musicFile = $targetDir.$file;
			$category = $rec['category'];
			$genre = $rec['genre'];
			$keywords = $rec['keywords'];
			$sql = "select * from music_tag where music_id=".$mi;
			$tags = getDB($sql);
			foreach ($tags as $t) {
				$tga[] = $t['tag_id'];
			}
		} else {
			$title = '';
			$file = '';
			$category = '';
			$genre = '';
			$keywords = '';
		}
	} else {
		$mi = null;
		$title = '';
		$file = '';
		$category = '';
		$genre = '';
		$keywords = '';
	}
}

function deleteMusic($resId) {
	global $targetDir;
	echo "deleting ".$resId."<br /><br />\n";
	$sql = "select filename from music where music_id=".$resId;
	$result = getDB($sql);
	if (count($result)) {
		$rec = $result[0];
		$filename = $rec['filename'];
		$musicFile = $targetDir.$filename;
		unlink($musicFile);
		$sql = sprintf("call delete_music(%s)", $resId);
		$result = getDB($sql);
	} else echo "エラー: 削除できません！";
}

function getNumList() {
	global $userID;
	$sql = "select music_id from music where isnull(user_id) or user_id=".$userID;
	$result = getDB($sql);
	return count($result);
}

function getMusicList($offset, $numItems) {
	global $userID;
    $sql = "select * from music where isnull(user_id) or user_id=$userID limit ".$offset.",".$numItems;
	$result = getDB($sql);
	return $result;
}

function getTags($mId) {
	$sql = "select tag from tags t join music_tag mt on t.tag_id=mt.tag_id where mt.music_id=".$mId;
	$result = getDB($sql);
	return $result;
}
?>
<html lang="ja">
<head>
	<meta Content-Type: text/html; charset=UTF-8 />
	<title>ミュージック管理</title>
	<link rel="stylesheet" href="style.css" type="text/css" />
	<link rel="stylesheet" href="../js/jquery-ui.css">
	<link rel="stylesheet" type="text/css" href="../js/css/jQuery.mb.miniAudioPlayer.min.css" title="style"  media="screen"/>
	<script type='text/javascript' src="http://code.jquery.com/jquery-latest.min.js"></script>
<!--	<script type='text/javascript' src='../js/menu_jquery.js'></script> -->
	<script type='text/javascript' src="../js/jquery-ui.js"></script>
	<script type="text/javascript" src="../js/jQuery.mb.miniAudioPlayer.min.js"></script>
	<style>
	.tags {
		font-size:12px;
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

<div class="user_name">こんにちは、<?=$name?>さん</div>
<div class="subtitle">ミュージック管理</div>

<div class="container_main">
<form class="select" method="POST" enctype="multipart/form-data">
<input type=hidden name="userid" value="<?=$userID?>" />

<table class="">
<tr style="text-align:left;">
<th>タイトル</th><th>ファイル</th><th>カテゴリー</th><th style="text-align:center;">ジャンル</th></tr>
<tr>
<td><input id="title" name="title" type="text" size="40" value="<?=$title?>" /></td>
<td style="width:50px;"><input type="file" name="file" accept=".mp3,.aac,.wav,audio/mp3,audio/wav,audio/aac" value="<?=$file?>"  onchange="readURL(this);"></td>
<td style="text-align:center;">
<select name="category">
<?php
	$sql = "select * from music_category";
	$perms = getDB($sql);
	$i = 1;
	foreach ($perms as $line) {
		$id = $line['music_category_id'];
		$pname = $id.' '.$line['name'];
		echo "<option value='".$id."'".(($category===$id)?" selected":"")
			.">".$pname."</option>\n";
	}
?>
</select>
</td>
<td style="text-align:center;">
<select name="genre">
<?php
	$sql = "select * from music_genre";
	$perms = getDB($sql);
	$i = 1;
	foreach ($perms as $line) {
		$id = $line['genre_id'];
		$pname = $id.' '.$line['name'];
		echo "<option value='".$id."'".(($genre===$id)?" selected":"")
			.">".$pname."</option>\n";
	}
?>
</select>
</td></tr>
<tr><td> </td></tr>
<tr><td>タグ</td></tr>
<tr>
<td colspan=3>
<div class="tags">
<?php
	$sql = "select * from tags";
	$tags = getDB($sql);
	$i = 0;
	foreach ($tags as $tag) {
		$id = $tag['tag_id'];
		$name = $tag['tag'];
		if (array_search($id, $tga)!==false) $check = " checked";
		else $check = '';
		echo '<label><input type="checkbox" name="tags[]" value="'
				.$i.'" '.$check.'>'.$name.'</label> ';
		echo "<input type='hidden' name='tagId$i' value='".$id."'>\n";
		$i++;
	}
?>
</div>
<!--<textarea name="keywords"><?=$keywords?></textarea>-->
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
<!--<tr><td colspan=3>
<div id="voicePlayerArea" style="margin: 10px; width: 600px;">
<audio id="voicePlayer" controls class="" style="width:600px;">
<source src="<?=$musicFile?>" type="audio/mp3">
<p>音声を再生するには、audioタグをサポートしたブラウザが必要です。</p>
</audio>
</div>
</td></tr>-->
<!--<td><a id="m3" class="audio {autoPlay:false,showRew:false}" href=""></a></td>-->
<tr><td style="text-align:left;"></td>
<td colspan=3 style="border:0;text-align:right;">
<a href="<?=$selfName?>"><input class="buttonclass" type="button" value="新規"></a> &nbsp; &nbsp; 
<input class="buttonclass" type="submit" name="send" value="削除" /></td>
</tr>
</table>
<input type='hidden' name='mi' value='<?=$mi?>'>
<br>

<table class="kwtool" style="width:98%;">
<tr><th></th><th>ファイル</th><th>カテゴリー</th><th>ジャンル</th><th>タグ</th><th>編集</th><th>削除</th></tr>
<?php
$rowCount = getNumList();
$pageCount = (int)ceil($rowCount/$items_per_page);
if (!$pageNo) {
	$pageNo = $pageCount;
}
// get reservation table data
$offset = ($pageNo - 1) * $items_per_page;
$limit = $items_per_page * $pageNo;
$musicList = getMusicList($offset, $items_per_page);
if (count($musicList)) {
	$i=1;
	foreach ($musicList as $rec) {
		$id = $rec['music_id'];
		$tt = $rec['title'];
		$fi = $rec['filename'];
		$ca = $rec['category'];
		$ge = $rec['genre'];
		$recs = getTags($id);
		$tags = implode(', ', array_column($recs,'tag'));

		echo "<tr>";
		echo '<td><a id="a'.$i.'" class="audio {mp3:"audio/'.$fi.'", autoPlay:false,showRew:false}" href="'.$targetDir.$fi.'">'.$tt.'</a></td>';
		echo "<td>".$fi."</td>";
		echo "<td style='text-align:center;'>".$ca."</td>";
		echo "<td>".$ge."</td>";
		echo "<td style='font-size:10px;'>".$tags."</td>";
		echo '<td><span class="button small"><a href="'.$selfName.'?mi='.$id
			.'&p='.$pageNo.'"><img src="img/icon_edit.png"></a></span>';
		echo "<input type='hidden' name='resId".$i."' value='".$id."' /></td>";
		echo "<td><input type='checkbox' name='delete[]' value='".$i."' /></td>";
		echo "</tr>\n";
		
		$i++;
	}
}
?>
</table>

<table><tr><td style="font-size:11px">
<?php
for ($i=1; $i<=$pageCount; $i++) {
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

<script type="text/javascript">

$(function() {
	$(".audio").mb_miniPlayer({
		width:300,
		inLine:false,
		id3:true,
		addShadow:false,
		pauseOnWindowBlur: false,
		downloadPage:null
	});
});


// to put title from file name
function readURL(input) {
	if (input.files && input.files[0]) {
		var fname = input.files[0].name.replace(/\.[^/.]+$/, "");
		$('#title').val(fname);
		//$('#m3').mb_miniPlayer_changeFile({mp3: '<?=$targetDir?>'+fname},fname);
	}
}
</script>

</body>
</html>