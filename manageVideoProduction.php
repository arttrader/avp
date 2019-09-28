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

$send = '';
$vID = null;
$title = '';
$file = '';
$category = 0;
$desc = '';
$tags = '';
$keywords = '';
$musicDir = musicDataClass::getFolder();
$imageFiles = 'images';
$menuselected = 0;
$items_per_page = 20;
$videoInfoData = null;
$genre = '';
$reuseArticle = 0;

if ($_POST) {
	$send = $_POST['send'];
	//echo "send = ".$send."<br>";
	$menuselected = isset($_POST['menuselected'])?$_POST['menuselected']:0;
	if ($send==="変更" || $send==="追加" || $send==="制作") {
		if ($_POST['mi'])
			$videoInfoData = new videoProdDataClass($_POST['mi']);
		else
			$videoInfoData = new videoProdDataClass();
		$videoInfoData->title = $_POST['title'];
		$videoInfoData->category = $_POST['category'];
		$videoInfoData->articleChanged = $_POST['articleChanged'];
		$videoInfoData->imageChanged = $_POST['imageChanged'];
		$videoInfoData->musicChanged = $_POST['musicChanged'];
		$videoInfoData->videoChanged = $_POST['videoChanged'];
		$articleText = trim($_POST['articleText']);
		$reuseArticle = isset($_POST['reuseArticle'])?1:0;
		if ($videoInfoData->articleChanged) {
			$aItemData = new mDataClass();
			$aItemData->decode($_POST['articleHtml']);
			$videoInfoData->articleData = $aItemData;
		} else {
			if ($articleText!==$videoInfoData->getNarration()) {
				$videoInfoData->setArticle($articleText, $reuseArticle);
				$videoInfoData->narrationText = $articleText;
			}
		}
		if ($videoInfoData->imageChanged) {
			$iItemData = new mDataClass();
			$iItemData->decode($_POST['imageHtml']);
			$videoInfoData->imageData = $iItemData;
		}
		if ($videoInfoData->musicChanged) {
			$mItemData = new mDataClass();
			$mItemData->decode($_POST['musicHtml']);
			$videoInfoData->musicData = $mItemData;
		}
		if ($videoInfoData->videoChanged) {
			$vItemData = new mDataClass();
			$vItemData->decode($_POST['videoHtml']);
			$videoInfoData->videoData = $vItemData;
		}
		$videoInfoData->videoFileName = $_POST['fileName'];
		$videoInfoData->useTextFlow = $_POST['showText'];
		$videoInfoData->fontSize = $_POST['fontSize'];
		$videoInfoData->useVoice = $_POST['voice'];
		$videoInfoData->voiceSpeed = $_POST['speed'];
		$videoInfoData->quoteTitle = $_POST['quoteTitle'];
		$videoInfoData->quoteUrl = $_POST['quoteUrl'];
		$videoInfoData->desc = isset($_POST['desc'])?$_POST['desc']:'';
		$videoInfoData->tags = isset($_POST['tags'])?$_POST['tags']:'';
		$videoInfoData->saveData();
		if ($send==="制作") {
			if (isset($_POST['produce'])) {
				if (is_array($_POST['produce'])) {
					foreach($_POST['produce'] as $i) {
						$resId = $_POST['resId'.$i];
						$sql = sprintf("call start_production(%u)", $resId);
						$result = getDB($sql);
					}
				} else {
					$resId = $_POST['produce'];
					echo "produce ".$resId."<br><br>\n";
					$videoInfoData->setStartProduction();
				}
			}
		}
		if ($send==="変更")
			$message = "動画を変更しました";
		else if ($send==="制作")
			$message = "動画制作を開始しました";
		else
			$message = "動画を追加しました";
	} else if ($send==="削除") {
		if (isset($_POST['delete'])) {
			if (is_array($_POST['delete'])) {
				foreach($_POST['delete'] as $i) {
					$resId = $_POST['resId'.$i];
					echo "deleting ".$resId."<br><br>\n";
					$sql = sprintf("call delete_videoInfo(%u)", $resId);
					$result = getDB($sql);
					if (count($result)) {
						$filename = $result['filename'];
						//$target_file = $targetDir.$filename;
						unlink($filename);
					} else echo "エラー: 削除できません！";
				}
			} else {
				$resId = $_POST['delete'];
				echo "deleting ".$resId."<br><br>\n";
				$sql = sprintf("call delete_videoInfo(%u)", $resId);
				$result = getDB($sql);
			}
		}
		$videoInfoData = new videoProdDataClass();
	} else if ($send==="選択") {
		$changed = true;
		$neworadd = isset($_POST['neworadd'])?$_POST['neworadd']:0;
		$itemChosen = isset($_POST['itemChosen'])?$_POST['itemChosen']:array();
		$videoInfoData = unserialize(gzinflate(base64_decode($_POST['videoData'])));
		$videoInfoData->title = $_POST['atitle'];
		$videoInfoData->category = $_POST['acategory'];
		$articleText = $_POST['aarticleText'];
		if ($articleText!==$videoInfoData->getNarration()) {
			$videoInfoData->setArticle($articleText);
			$videoInfoData->narrationText = $articleText;
		}

		switch ($menuselected) {
		case 1:
			$videoInfoData->articleData->clear();
			$videoInfoData->narrationText = '';
			foreach ($itemChosen as $i) {
				$id = $_POST['articleId'.$i];
				$title = $_POST['title'.$i];
				$text = $_POST['text'.$i];
				$category = $_POST['category'.$i];
				$item = new articleDataClass(null,$id,$title,$text,$category);
				$videoInfoData->articleData->push($item);
				$videoInfoData->narrationText .= $text."\n";
				break; // article should be only one
			}
			$videoInfoData->articleChanged = true;
			break;
		case 2:
			if ($neworadd)
				$videoInfoData->imageData->clear();
			foreach ($itemChosen as $i) {
				$id = $_POST['imageId'.$i];
				$title = $_POST['title'.$i];
				$file = $_POST['file'.$i];
				$item = new imageDataClass(null,$id,$title,$file);
				$videoInfoData->imageData->push($item);
			}
			$videoInfoData->imageChanged = true;
			break;
		case 3:
			$videoInfoData->musicData->clear();
			foreach ($itemChosen as $i) {
				$id = $_POST['musicId'.$i];
				$title = $_POST['title'.$i];
				$file = $_POST['file'.$i];
				$item = new musicDataClass(null,$id,$title,$file);
				$videoInfoData->musicData->push($item);
				break; // music should be only one
			}
			$videoInfoData->musicChanged = true;
			break;
		case 4:
			$i = $itemChosen[0];
			$id = $_POST['videoId'.$i];
			$title = $_POST['title'.$i];
			$file = $_POST['file'.$i];
			$video = $videoInfoData->getCMVideo();
			if (!$video) {
				$item = new videoDataClass(null,$id,$title,$file,1);
				$videoInfoData->videoData->push($item);
				$videoInfoData->videoChanged = true;
			} else // update video item
				$videoInfoData->videoData->current()->setData($id,$title,$file);
			break;
		case 5:
			$i = $itemChosen[0];
			$id = $_POST['videoId'.$i];
			$title = $_POST['title'.$i];
			$file = $_POST['file'.$i];
			$video = $videoInfoData->getEndVideo();
			if (!$video) {
				$item = new videoDataClass(null,$id,$title,$file,2);
				$videoInfoData->videoData->push($item);
				$videoInfoData->videoChanged = true;
			} else // update video item
				$videoInfoData->videoData->current()->setData($id,$title,$file);
			break;
		}
	}
} else {
	if (isset($_GET['p']))
		$pageNo = $_GET['p'];
	else
		$pageNo = 1;
	
	if (isset($_GET['mi'])) {
		$vID = $_GET['mi'];
		$videoInfoData = new videoProdDataClass($vID);
		if ($videoInfoData->articleData->count())
			$reuseArticle = $videoInfoData->articleData->current()->reuse;
	} else {
		$videoInfoData = new videoProdDataClass();
	}
}

function getNumList() {
	$sql = "select * from video_info";
	$result = getDB($sql);
	return count($result);
}

function getVideoList($offset, $numItems) {
	global $userID;
    $sql = "select *,(select count(*) from video_narration n where v.video_info_id=n.video_info_id) articlec, 
		(select count(*) from video_image i where v.video_info_id=i.video_info_id) imagec, 
		(select count(*) from video_music m where v.video_info_id=m.video_info_id) musicc,
        (select count(*) from video_video c join video vv on c.video_id=vv.video_id where vv.video_type=1 and v.video_info_id=c.video_info_id) videoc,
        (select count(*) from video_video e join video vv on e.video_id=vv.video_id where vv.video_type=2 and v.video_info_id=e.video_info_id) videoe
from video_info v where isnull(user_id) or user_id=$userID order by update_date desc limit ".$offset.",".$numItems;
	$result = getDB($sql);
	return $result;
}
?>
<!DOCTYPE html>
<html lang="ja">
<head>
	<meta Content-Type: text/html; charset=UTF-8 />
	<title>AUTO VIDEO PRODUCER 動画制作管理</title>
	<link rel="stylesheet" href="style.css">
	<link rel="stylesheet" href="../js/jquery-ui.css">
	<link rel="stylesheet" href="../js/jquery-ui-slider-pips.css">
	<link rel="stylesheet" href="../js/nanoscroller.css">
	<link rel="stylesheet" type="text/css" href="../js/css/jQuery.mb.miniAudioPlayer.min.css" title="style" media="screen"/>
	<script type='text/javascript' src="../js/jquery-2.1.1.min.js"></script>
	<!--<script type='text/javascript' src='../js/menu_jquery.js'></script>-->
	<script type='text/javascript' src="../js/jquery.bpopup.min.js"></script>
	<script type='text/javascript' src="../js/jquery-ui.js"></script>
	<script type='text/javascript' src='../js/jquery-ui-slider-pips.js'></script>
	<script type='text/javascript' src="../js/jquery.nanoscroller.min.js"></script>
	<script type='text/javascript' src='../js/jQuery.download.js'></script>
	<script type="text/javascript" src="../js/jQuery.mb.miniAudioPlayer.min.js"></script>
<style>
.imagecell {
	margin:1px;
}
#popcontent {
	width: 520;
	height: 293;
	text-align:center;
}
.grayclass {
	color:#BBBBBB;
}


#v_slider, #s_slider, #p_slider, #r_slider{
	float:left;
	width: 200px !important;
	margin: 0 50px 24px 0;
}

.ui-slider-handle{
	background:url(img/sliderknob.png)50% 50% repeat-x !important;
    width:11px !important;
    height:19px !important;
    border:none !important;
/*    top:3px !important; */
}

.sliderValue{
	color:gray;
	text-align:center;
	font-size:12px !important;
	width:26px;
	border: 1px solid #DDD; 
	box-shadow: 0 0 2px #DDD inset; 
}

table.adjuster {
	margin:0 0 20px 6px;
	padding: 0 0 20px 0;
	width:800px;
}

.ui-tabs .ui-tabs-nav
{
background: #fafafa;
}


/* DO NOT REMOVE OR MODIFY */
/*{"skinName": "mvpSkin", "borderRadius": 6, "main": "#000", "secondary": "rgb(238, 238, 238)", "playerPadding": 0}*/
/* END - DO NOT REMOVE OR MODIFY */
/*++++++++++++++++++++++++++++++++++++++++++++++++++
Copyright (c) 2001-2014. Matteo Bicocchi (Pupunzi);
http://pupunzi.com/mb.components/mb.miniAudioPlayer/demo/skinMaker.html

Skin name: mvpSkin
borderRadius: 6
background: #000
icons: rgb(238, 238, 238)
border: rgb(225, 225, 225)
borderLeft: rgb(26, 26, 26)
borderRight: rgb(0, 0, 0)
mute: rgba(238, 238, 238, 0.4)
download: rgba(0, 0, 0, 0.4)
downloadHover: rgb(0, 0, 0)
++++++++++++++++++++++++++++++++++++++++++++++++++*/

/* Older browser (IE8) - not supporting rgba() */
.mbMiniPlayer.mvpSkin .playerTable span.map_volume.mute{color: #eeeeee;}
.mbMiniPlayer.mvpSkin .map_download{color: #eeeeee;}
.mbMiniPlayer.mvpSkin .map_download:hover{color: #eeeeee;}
.mbMiniPlayer.mvpSkin .playerTable span{color: #eeeeee;}
.mbMiniPlayer.mvpSkin .playerTable {border: 1px solid #eeeeee !important;}

/*++++++++++++++++++++++++++++++++++++++++++++++++*/

.mbMiniPlayer.mvpSkin .playerTable{background-color:transparent; border-radius:6px !important;}
.mbMiniPlayer.mvpSkin .playerTable span{background-color:#000; padding:3px !important; font-size: 20px;}
.mbMiniPlayer.mvpSkin .playerTable span.map_time{ font-size: 12px !important; width: 50px !important}
.mbMiniPlayer.mvpSkin .playerTable span.map_title{ padding:4px !important}
.mbMiniPlayer.mvpSkin .playerTable span.map_play{border-left:1px solid rgb(0, 0, 0); border-radius:0 5px 5px 0 !important;}
.mbMiniPlayer.mvpSkin .playerTable span.map_volume{padding-left:6px !important}
.mbMiniPlayer.mvpSkin .playerTable span.map_volume{border-right:1px solid rgb(26, 26, 26); border-radius:5px 0 0 5px !important;}
.mbMiniPlayer.mvpSkin .playerTable span.map_volume.mute{color: rgba(238, 238, 238, 0.4);}
.mbMiniPlayer.mvpSkin .map_download{color: rgba(0, 0, 0, 0.4);}
.mbMiniPlayer.mvpSkin .map_download:hover{color: rgb(0, 0, 0);}
.mbMiniPlayer.mvpSkin .playerTable span{color: rgb(238, 238, 238);text-shadow: none!important;}
.mbMiniPlayer.mvpSkin .playerTable span{color: rgb(238, 238, 238);}
.mbMiniPlayer.mvpSkin .playerTable {border: 1px solid rgb(225, 225, 225) !important;}
.mbMiniPlayer.mvpSkin .playerTable span.map_title{color: #000; text-shadow:none!important}
.mbMiniPlayer.mvpSkin .playerTable .jp-load-bar{background-color:rgba(0, 0, 0, 0.3);}
.mbMiniPlayer.mvpSkin .playerTable .jp-play-bar{background-color:#000;}
.mbMiniPlayer.mvpSkin .playerTable span.map_volumeLevel a{background-color:rgb(255, 255, 255); height:80%!important }
.mbMiniPlayer.mvpSkin .playerTable span.map_volumeLevel a.sel{background-color:#eeeeee;}
.mbMiniPlayer.mvpSkin  span.map_download{font-size:50px !important;}
/* Wordpress playlist select */
.map_pl_container .pl_item.sel{background-color:rgba(0, 0, 0, 0.1) !important; color: #999}
/*++++++++++++++++++++++++++++++++++++++++++++++++*/
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
<div class="subtitle">AUTO VIDEO PRODUCER</div>

<div class="container_main">

<form class="select" method="POST">
<input type="hidden" name="userid" value="<?=$userID?>">
<input type="hidden" name="mi" value="<?=$videoInfoData->id?>">
<input type="hidden" name="articleChanged" value="<?=$videoInfoData->articleChanged?>">
<input type="hidden" id="imageChanged" name="imageChanged" value="<?=$videoInfoData->imageChanged?>">
<input type="hidden" name="musicChanged" value="<?=$videoInfoData->musicChanged?>">
<input type="hidden" name="videoChanged" value="<?=$videoInfoData->videoChanged?>">

<table class="tube">
<tr style="text-align:left;">
<td style="padding-left:15px;">タイトル</td><td>ファイル</td><td>カテゴリ</td></tr>
<tr>
<td><input id="title" class="titleinput" name="title" type="text" size="40" value="<?=$videoInfoData->title?>"></td>
<td style="width:50px;">
<input type="text" name="fileName" value="<?=$videoInfoData->videoFileName?>">
</td>
<td>
<select id="category" name="category">
<?php
	$category = $videoInfoData->category;
	$sql = "select * from category";
	$perms = getDB($sql);
	$i = 1;
	foreach ($perms as $line) {
		$id = $line['category_id'];
		$pname = $id.' '.$line['name'];
		echo "<option value='".$id."'".(($category===$id)?" selected":"").">"
			.$pname."</option>\n";
	}
?>
</select>
</td>
<!--
<tr><td colspan=4><hr></td></tr>
<tr><td>YouTube用 説明文</td></tr>
<tr>
<td colspan=3><textarea name="desc"><?=$videoInfoData->desc?></textarea>
</td>
<tr><td>YouTube用 タグ</td></tr>
<tr>
<td colspan=3><textarea name="tags"><?=$videoInfoData->tags?></textarea>
</td>
-->
</tr>
<tr><td colspan=4><hr></td></tr>
<tr><td colspan=4><div class="style_01" id="part1">記事素材</div></td></tr>
<tr><td colspan=4>
<table class="tubecell"><tr><td>
<input type="text" class="searchinput" id="keyword" name="keyword" size="40" value="" placeholder="キーワードを入力して[検索]をクリック"></td>
<td><input type="checkbox" name="articleCat"> カテゴリに限定</td>
<td><input class="searchbuttonclass" id="searchArticleBtn" type="button" name="send" value="記事を探す"></td></tr>
</table></td></tr>
<tr><td colspan=2><textarea id="articleText" style="margin:2px;width:97%;" name="articleText"><?=htmlspecialchars($videoInfoData->getNarration())?></textarea>
<input type="hidden" name="articleHtml" value="<?=$videoInfoData->articleData->encode()?>">
</td>
<td><input type="checkbox" name="saveArticle"<?=$reuseArticle?" checked":""?>>記事を保存する</td>
</tr>
<tr><td>
テキストの表示
<select name="showText">
<option value="0" <?=($videoInfoData->useTextFlow==0)?"selected":""?>>無し</option>
<option value="1" <?=($videoInfoData->useTextFlow==1)?"selected":""?>>下</option>
<option value="2" <?=($videoInfoData->useTextFlow==2)?"selected":""?>>中</option>
<option value="3" <?=($videoInfoData->useTextFlow==3)?"selected":""?>>上</option>
</select> &nbsp;
 フォントサイズ
 <select id="fontSize" name="fontSize">
<option value="42" <?=($videoInfoData->fontSize==42)?"selected":""?>>42</option>
<option value="50" <?=($videoInfoData->fontSize==50)?"selected":""?>>50</option>
<option value="60" <?=($videoInfoData->fontSize==60)?"selected":""?>>60</option>
<option value="70" <?=($videoInfoData->fontSize==70)?"selected":""?>>70</option>
<option value="80" <?=($videoInfoData->fontSize==80)?"selected":""?>>80</option>
<option value="100" <?=($videoInfoData->fontSize==100)?"selected":""?>>100</option>
 </select>
</td>
<td colspan=2>音声
<select id="voice" name="voice">
<option value="0" <?=($videoInfoData->useVoice==0)?"selected":""?>>読み上げ無し</option>
<option value="1" <?=($videoInfoData->useVoice==1)?"selected":""?>>せいじ</option>
<option value="2" <?=($videoInfoData->useVoice==2)?"selected":""?>>おさむ</option>
<option value="3" <?=($videoInfoData->useVoice==3)?"selected":""?>>のぞみ</option>
<option value="4" <?=($videoInfoData->useVoice==4)?"selected":""?>>すみれ</option>
</select>
<div id="s_slider"></div>
<input type="text" class="sliderValue" id="speed" name="speed" value="<?=$videoInfoData->voiceSpeed?>">
</td>
</tr>
<tr><td colspan=2>引用元<input type="text" name="quoteTitle" size=30 value="<?=$videoInfoData->quoteTitle?>"> &nbsp; 引用URL<input type="text" name="quoteUrl" size=40 value="<?=$videoInfoData->quoteUrl?>"></td><td></td></tr>
<tr><td colspan=4><hr></td></tr>
<tr><td colspan=4><div class="style_01" id="part2">画像素材</div></td></tr>
<tr><td colspan=4>
<table class="tubecell"><tr><td>
<input type="text" class="searchinput" id="keyword" name="keyword" size="40" value="" placeholder="キーワードを入力して[検索]をクリック"></td>
<td><input type="checkbox" id="imageCat" name="imageCat">カテゴリに限定</td>
<td><input class="searchbuttonclass" id="searchImageBtn" type="button" name="send" value="画像を探す"></td></tr>
</table></td></tr>
<tr><td colspan=4>
<table class="tubecell"><tr><td style="padding:4px;">
<div id="imageDisplay">
<?php
if ($n = $videoInfoData->imageData->count()) {
	$row = 1;
	for ($i=0; $i<$n; $i++) {
		$item = $videoInfoData->imageData->offsetGet($i);
		echo '<img class="imagecell" id="mv'.$i
			.'" style="max-width:100px;max-height:62px;" src="'.$item->getFilePath().'"> ';
		$row++;
		if ($row>9) { 
			echo '<br>';
			$row = 1;
		}
	}
}
?>
</div>
</td></tr></table>
<input type="hidden" id="imageData" name="imageHtml" value="<?=$videoInfoData->imageData->encode()?>">
</td></tr>
<tr><td colspan=4><hr></td></tr>
<tr><td colspan=4><div class="style_01" id="part3">音楽素材</div></td></tr>
<tr><td colspan=4>
<table class="tubecell"><tr><td>
<input type="text" class="searchinput" id="keyword" name="keyword" size="40" value="" placeholder="キーワードを入力して[検索]をクリック"></td>
<td>音楽カテゴリ
<select id="musicCat" name="musicCat">
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
</select><br>
音楽ジャンル
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
</td>
<td>
<input class="searchbuttonclass" id="searchMusicBtn" type="button" name="send" value="音楽を探す"></td></tr>
<tr><td colspan=2 style="font-size:10px;text-align:left;">
<?php
	$sql = "select * from tags";
	$tags = getDB($sql);
	$i = 0;
	foreach ($tags as $tag) {
		$id = $tag['tag_id'];
		$name = $tag['tag'];
		echo '<label><input type="checkbox" class="ts" name="tags[]" value="'
				.$id.'">'.$name.'</label> ';
		echo "<input type='hidden' name='tag$i' value='".$name."'>";
		$i++;
	}
?>
</td></tr></table>
<div style="margin-left:10px;"><?=$videoInfoData->getMusic()?> &nbsp; 
<a id="m3" class="audio {skin:'mvpSkin', autoPlay:false, inLine:true}" href="<?=$videoInfoData->getMusic()?>"><?=$videoInfoData->getMusic()?></a></div><input type="hidden" name="musicHtml" value="<?=$videoInfoData->musicData->encode()?>">
</td><td></td></tr>
<tr><td colspan=3><hr></td></tr>
<tr><td colspan=3><div class="style_01" id="part3">CM ＆ エンディング動画</div></td></tr>
<tr><td colspan=3> </td></tr>
<tr>
<td>CM動画 <input class="searchbuttonclass" id="searchCMVideoBtn" type="button" name="send" value="CM動画を探す"></td>
<td>エンディング動画 <input class="searchbuttonclass" id="searchEndVideoBtn" type="button" name="send" value="エンディング動画を探す"></td></tr>
<tr><td>
<video id="cmVideo" style="width:300px;height:200px;" controls>
    <source src="<?=$videoInfoData->getCMVideo()?$videoInfoData->getCMVideo()->getFilePath():''?>">
</video>
</td>
<td>
<video id="enVideo" style="width:300px;height:200px;" controls>
	<source src="<?=$videoInfoData->getEndVideo()?$videoInfoData->getEndVideo()->getFilePath():''?>">
</video>
<input type="hidden" name="videoHtml" value="<?=$videoInfoData->videoData->encode()?>">
</td></tr>
<tr><td colspan=4><hr></td></tr>
<tr><td></td>
<td style="text-align:right;">
<a href="<?=$selfName?>"><input class="buttonclass" type="button" value="新規"></a> &nbsp; &nbsp; 
<?php
if ($videoInfoData->id) {
	echo '<input class="" type="submit" name="send" value="変更">';
} else {
	echo '<input class="" type="submit" name="send" value="追加">';
}
?>
</td>
<td colspan=3 style="border:0;text-align:left;">
<input class="buttonclass" type="submit" name="send" value="削除"> &nbsp; &nbsp; 
<input class="buttonclass" type="submit" name="send" value="制作"></td>
</tr>
</table>
<br>
<table class="kwtool" style="width:98%;">
<tr><th>ID</th><th>タイトル</th><th>記事</th><th>画像</th><th>音楽</th><th>CM</th><th>END</th><th>編集</th><th>削除</th><th>制作</th><th>DL</th></tr>
<?php
$rowCount = getNumList();
$pageCount = (int)ceil($rowCount/$items_per_page);
if (!$pageNo) {
	$pageNo = $pageCount;
}
// get image table data
$offset = ($pageNo - 1) * $items_per_page;
$limit = $items_per_page * $pageNo;
$imageList = getVideoList($offset, $items_per_page);
if (count($imageList)) {
	$i=1;
	foreach ($imageList as $rec) {
		$id = $rec['video_info_id'];
		$tt = $rec['title'];
		$ac = $rec['articlec'];
		$ic = $rec['imagec'];
		$mc = $rec['musicc'];
		$cm = $rec['videoc'];
		$ve = $rec['videoe'];
		$sp = $rec['startProduction'];
		$pd = $rec['start_time'];
		$dd = $rec['production_date'];
		$fn = $rec['fileName'];
		if ($pd) $class = 'class="grayclass"';
		else $class = '';
		echo "<tr $class>";
		echo "<td>".$id."</td>";
		echo "<td>".$tt."</td>";
		echo "<td>".$ac."</td>";
		echo "<td>".$ic."</td>";
		echo "<td>".$mc."</td>";
		echo "<td>".$cm."</td>";
		echo "<td>".$ve."</td>";
		if ($pd & !$dd) {
			if ($pd) $imgclass = 'style="opacity:0.4;" ';
			else $imgclass = '';
			echo '<td><span class="button small"><img '.$imgclass
				.'src="img/icon_edit.png"></span>';
			echo "<input type='hidden' name='resId".$i."' value='".$id."'></td>";
			echo "<td><input type='checkbox' name='delete[]' value='".$i."' disabled></td>";
			echo "<td><input type='checkbox' name='produce[]' value='".$i."'  disabled></td>";
			echo "<td>制作中</td></tr>\n";
		} else {
			echo '<td><span class="button small"><a href="'.$selfName.'?mi='
				.$id.'&p='.$pageNo.'"><img src="img/icon_edit.png"></a></span>';
			echo "<input type='hidden' name='resId".$i."' value='".$id."'></td>";
			echo "<td><input type='checkbox' name='delete[]' value='".$i."'></td>";
			if ($sp)
				echo "<td><input type='checkbox' name='produce[]' value='".$i."'  checked></td>";
			else
				echo "<td><input type='checkbox' name='produce[]' value='".$i."'></td>";
			if ($dd)
				echo "<td><input class='dlBtn' type='button' name='".$fn."' value='DL'></td>";
			else
				echo "<td>待機中</td>";
			echo "</tr>\n";
		}
		$i++;
	}
}
//echo $genre.'<br><br>';
?>
</table>
</form>
<br>
<!-- table for pagenation -->
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

<div id="popup2">
<form id="selectform" method="POST">
<br>
<div class="selectButtons" style="text-align:left;margin-left:50px;font-size:14px;">
<div class="triangle">使用したい素材を選択してください。</div>
<input type="submit" class="selectMBtn" name="send" value="選択"> &nbsp; 
<input class="neworadd" type="radio" name="neworadd" value="0">追加 
<input class="neworadd" type="radio" name="neworadd" value="1" checked>新規</div>
<span class="cbutton b-close"><span><img src="img/icon_close.jpg"></span></span>
<div class="nano" id="content2">
<div class="nano-content" id="scrollarea">
<br></div></div>
<input type="hidden" name="videoData" value="<?=base64_encode(gzdeflate(serialize($videoInfoData)))?>">
<input type="hidden" class="menuselected" name="menuselected" value="<?=$menuselected?>">
<input type="hidden" id="atitle" name="atitle">
<input type="hidden" id="aarticleText" name="aarticleText">
<input type="hidden" id="acategory" name="acategory">
</form>
</div><!--end popup2-->

<div id="popup">
<br><br>
<span class="cbutton b-close"><span><img src="img/icon_close.jpg"></span></span>
<div id="popcontent"></div>
</div><!--end popup-->

<?php
//session_write_close();
?>
</div><!--end container_main-->
<p class="footer_img"><br>Copyright © 2015 J Hirota. All rights Reserved.</p>


<script language="JavaScript" type="text/JavaScript">
//var playerType = 'HTML5';
//var audio;

//var smpflag = navigator.userAgent.match(/(iPhone|iPad|Android)/);

$(function() {
	$(".audio").mb_miniPlayer({
		width:300,
		inLine:false,
		id3:true,
		addShadow:true,
		pauseOnWindowBlur:false,
		downloadPage:null
	});


	$("#menu li").hover(function() {
		$(this).children('ul').show();
	}, function() {
		$(this).children('ul').hide();
	});

	var wh = $(window).height();
	$('#popup2').css("height", wh-180);
	$('.nano').css("height", wh-270);

	$(window).resize(function() {
		var wh = $(window).height();
		$('#popup2').css("height", wh-180);
		$('.nano').css("height", wh-270);	
	});

	var splabels = ['0.5','','','','','1.0','','','','','1.5','','','','','2.0'];
    $("#s_slider").slider({
    	animate: "fast",
    	min: 5,
    	max: 20,
    	value: 10,
    	animate: true,
    	orientation: "horizontal",
    	slide: function(event, ui) {
    		$('#speed').val((ui.value/10).toPrecision(2));
    	}
    }).slider("pips", {
    	rest: "label",
    	labels: splabels
    });
//    $("#speed").val(($("#s_slider").slider("value")/10).toPrecision(2));
	$("#s_slider").slider("value", $("#speed").val()*10);
    $("#speed").change(function(){
    	var tmp = $(this).val();
    	if (tmp>2) tmp = 2;
    	else if (tmp<0) tmp = 0;
    	$("#s_slider").slider("value", tmp*10);
    	$(this).val(tmp);
    });

	$('#searchArticleBtn').click(function() {
		$('.menuselected').val('1');
		var k = $('#keyword').val();
		if ($('#articleCat').prop('checked'))
			var c = $('#category').val();
		else
			var c = '';
		var pt = 1;
		getArticles(k,c,pt);

		$('#popup2').bPopup({
			opacity: 0.6,
			positionStyle: 'fixed',
		});
	});

	$('#searchImageBtn').click(function() {
		$('.menuselected').val('2');
		var k = $('#keyword').val();
		if ($('#imageCat').prop('checked'))
			var c = $('#category').val();
		else
			var c = '';
		var pt = 1;
		getImages(k,c,pt);

		$('#popup2').bPopup({
			opacity: 0.6,
			positionStyle: 'fixed',
		});
	});

	$('#searchMusicBtn').click(function() {
		$('.menuselected').val('3');
		var k = $('#keyword').val();
		var c = $('#musicCat').val();
		var g = $('#genre').val();
		var t = [];
		//var ts = $('.ts:checked').serialize();
		$('.ts:checked').each(function() {
			t.push($(this).val());
		});
		
		var tags = t.join(", ");
		var pt = 1;
		getMusic(k,c,g,tags,pt);

		$('#popup2').bPopup({
			opacity: 0.6,
			positionStyle: 'fixed',
		});
	});

	$('#searchCMVideoBtn').click(function() {
		$('.menuselected').val('4');
		var k = '';
		var c = $('#category').val();
		var t = 1;
		var pt = 1;
		getVideo(k,c,t,pt);

		$('#popup2').bPopup({
			opacity: 0.6,
			positionStyle: 'fixed',
		});
	});

	$('#searchEndVideoBtn').click(function() {
		$('.menuselected').val('5');
		var k = '';
		var c = $('#category').val();
		var t = 2;
		var pt = 1;
		getVideo(k,c,t,pt);

		$('#popup2').bPopup({
			opacity: 0.6,
			positionStyle: 'fixed',
		});
	});

	$('.dlBtn').click(function(e) {
		var url = '<?=$rootUrl?>dlfile.php';
		var data = {fn:$(this).attr('name')};
		$.download(url, data);
	});

	bindButtonClick();
});

function bindButtonClick() {
	$('.imagecell').draggable({
		cursor: "move", 
		opacity: 0.4, 
		revert: "invalid", 
		revertDuration: 10
	});
	
	$('.imagecell').droppable({
		drop: function(event, ui) {
			var fid = ui.draggable.attr("id");
			var fi = fid.substring(2);
			var id = $(this).attr("id");
			var i = id.substring(2);
			updateImages('dp'+i, fi);
		}
	});
}

var waitMessageHTML = '<br><br><img style="height:100px;width:100px;margin:30px;" src="img/ajax-loader.gif" alt="処理中.."/><br>少々お待ちください・・・・';

function updateImages(cmd, fid) {
	var url = "processImageOrder.php";
	if (typeof fid!=='undefined')
		var data = {send:cmd, fi:fid, mItemData:$('#imageData').val()};
	else	
		var data = {send:cmd, mItemData:$('#imageData').val()};
	$.ajax({
		type: 'POST',
		url: url,
		data: data,
		dataType: 'json',
		success: function(result) {
			$('#imageData').val(result['mitemdata']);
			$('#imageDisplay').html(result['exhtml']);
			$('#imageChanged').val(true);
			bindButtonClick();
		},
		error: function(result) {
			alert('サーバーからの読み込み失敗');
		}
	});
}

function getArticles(key, cat, page) {
	$('#scrollarea').html(waitMessageHTML);
	var url = "getArticles.php";
	var data = {k:key, c:cat, u:<?=$userID?>, p:page};
//	$('#atitle').val($('#title').val());
	$.ajax({
		url: url,
		data: data,
		dataType: 'text',
		success: function(data) {
			$('#scrollarea').html(data);
			$(".nano").nanoScroller();
			$('.selectMBtn').show();
			$('.neworad').hide();
		},
		error: function(data) {
			$('#scrollarea').html( '読み込み失敗' );   			
			$('#scrollarea').append(data);
			$('.selectMBtn').hide();
		}
	});
}

function getImages(key, cat, page) {
	$('#scrollarea').html(waitMessageHTML);
	var url = "getImages.php";
	var data = {k:key, c:cat, u:<?=$userID?>, p:page};
	$('#atitle').val($('#title').val());
	$('#aarticleText').val($('#articleText').val());
	$('#acategory').val($('#category').val());
	$.ajax({
		url: url,
		data: data,
		dataType: 'text',
		success: function(data) {
			$('#scrollarea').html(data);
			$(".nano").nanoScroller();
			$('.selectButtons').show();
			$('.neworad').show();
		},
		error: function(data) {
			$('#scrollarea').html( '読み込み失敗' );   			
			$('#scrollarea').append(data);
			$('.selectButtons').hide();
		}
	});
}

function getMusic(key, cat, gen, tags, page) {
	$('#scrollarea').html(waitMessageHTML);
	var url = "getMusic.php";
	var data = {k:key, c:cat, g:gen, t:tags, u:<?=$userID?>, p:page};
	$('#atitle').val($('#title').val());
	$('#aarticleText').val($('#articleText').val());
	$('#acategory').val($('#category').val());
	$.ajax({
		url: url,
		data: data,
		dataType: 'text',
		success: function(data) {
			$('#scrollarea').html(data);
			$(".nano").nanoScroller();
			$('.selectButtons').show();
			$('.neworad').hide();
		},
		error: function(data) {
			$('#scrollarea').html( '読み込み失敗' );   			
			$('#scrollarea').append(data);
			$('.selectButtons').hide();
		}
	});
}

function getVideo(key, cat, type, page) {
	$('#scrollarea').html(waitMessageHTML);
	var url = "getVideo.php";
	var data = {k:key, c:cat, t:type, u:<?=$userID?>, p:page};
	
	
	$('#atitle').val($('#title').val());
	$('#aarticleText').val($('#articleText').val());
	$('#acategory').val($('#category').val());
	$.ajax({
		url: url,
		data: data,
		dataType: 'text',
		success: function(data) {
			$('#scrollarea').html(data);
			$(".nano").nanoScroller();
			$('.selectButtons').show();
			$('.neworad').hide();
		},
		error: function(data) {
			$('#scrollarea').html( '読み込み失敗' );   			
			$('#scrollarea').append(data);
			$('.selectButtons').hide();
		}
	});
}

</script>
</body>
</html>