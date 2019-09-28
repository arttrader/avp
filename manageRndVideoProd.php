<?php
require_once 'AuthController.php';
require_once 'videoDataClass.php';


// authenticate the use of this system
// use this as a set on every page that requires authorization
session_start();
if (isset($_SESSION[$sessionName])) {
	$authController = unserialize($_SESSION[$sessionName]);
	if (is_object($authController) && $authController->checkLogin()) {
		$name = $authController->name;
		$userID = $authController->userId;
		$userGroup = $authController->userGroup;
		$userLevel = $authController->userLevel;
		$loginId = $authController->userName;
		if ($userLevel<4 || !$userID)
			header("Location:$rootUrl");
	} else
		header("Location:$rootUrl");
} else
	header("Location:$rootUrl");

if (!$userID) header("Location:$rootUrl");

// AITalk security code
$tcode = sha1($userID.$loginId);

$selfName = basename(__FILE__);
$pageNo = isset($_GET['p'])?$_GET['p']:1;

$send = '';
$menuselected = 0;
$reuseArticle = 0;
$message = "";

if ($_POST) {
	$send = $_POST['send'];
	$menuselected = isset($_POST['menuselected'])?$_POST['menuselected']:0;
	if ($send==="保存" || $send==="制作" || $send==="upload" || $send==="カバー画像を削除"
		|| $send==="End画像を削除" || $send==="OP動画を削除"
		|| $send==="End動画を削除") {
		if ($_POST['mi'])
			$jData = new jDataClass($_POST['mi']);
		else
			$jData = new jDataClass();
		getInfoFields($jData);
		if ($jData->articleChanged) {
			//echo "article changed<br>";
			$aItemData = new mDataClass();
			$aItemData->decode($_POST['articleHtml']);
			$jData->articleData = $aItemData;
		}
		if ($jData->imageChanged) {
			$iItemData = new mDataClass();
			$iItemData->decode($_POST['imageHtml']);
			$jData->imageData = $iItemData;
		}
		if ($jData->videoChanged) {
			$vID = $_POST['cmVideoHtml']?$_POST['cmVideoHtml']:0;
			if ($vID) {
				$jData->cmVideo = new videoDataClass(null,$vID,'','',0);
				$jData->cmVideo->loadData($vID);
			}
			$vID = $_POST['endVideoHtml']?$_POST['endVideoHtml']:0;
			if ($vID) {
				$jData->endVideo = new videoDataClass(null,$vID,'','',0);
				$jData->endVideo->loadData($vID);
			}
		}
		$jData->tga = isset($_POST['tags'])?$_POST['tags']:array();
		if ($send==="upload1") {
			if (isset($_FILES["imageFile"])) {
				$imageObj = new imageDataClass(0,0,'','',$userGroup);
				$imageObj->reuse = 0;
				if ($imageObj->upload('',$_FILES["imageFile"], $jData->category,0,'')) {
					$imageObj->type = 1;
					$jData->imageData->push($imageObj);
					$jData->imageChanged = true;
				}
			} else echo "image file empty<br>";
		} else if ($send==="upload2") {
			if (isset($_FILES["imageFile"])) {
				$imageObj = new imageDataClass(0,0,'','',$userGroup);
				$imageObj->reuse = 0;
				if ($imageObj->upload('',$_FILES["imageFile"], $jData->category,0,'')) {
					$imageObj->type = 2;
					$jData->imageData->push($imageObj);
					$jData->imageChanged = true;
				}
			} else echo "image file empty<br>";
/*		} else if ($send==="カバー画像を削除") {
			$n = $jData->imageData->count();
			for ($i=0; $i<$n; $i++) {
				if ($jData->imageData->type==1) {
					$jData->imageData->current()->detachJob();
					$jData->imageData->deleteCurrent();
				}
			}
		} else if ($send==="End画像を削除") {
			$n = $jData->imageData->count();
			for ($i=0; $i<$n; $i++) {
				if ($jData->imageData->type==2) {
					$jData->imageData->current()->detachJob();
					$jData->imageData->deleteCurrent();
				}
			} */
		} else if ($send==="OP動画を削除") {
			$jData->cmVideo = null;
			$jData->videoChanged = true;
		} else if ($send==="End動画を削除") {
			$jData->endVideo = null;
			$jData->videoChanged = true;
		}
		
		$jData->saveData();
		
		if ($send==="制作") {
			if (isset($_POST['produce'])) {
				if (is_array($_POST['produce'])) {
					foreach($_POST['produce'] as $i) {
						$resId = $_POST['resId'.$i];
						$sql = sprintf("call start_rnd_production(%u)",$resId);
						$result = getDB($sql);
					}
				} else {
					$i = $_POST['produce'];
					$resId = $_POST['resId'.$i];
					$sql = sprintf("call start_rnd_production(%u)",$resId);
					$result = getDB($sql);
				}
			}
		}
		if ($send==="保存")
			$message = "ジョブを保存しました";
		else if ($send==="制作")
			$message = "動画制作を開始しました";
		else
			$message = "";
	} else if ($send==="削除") {
		if (isset($_POST['delete'])) {
			if (is_array($_POST['delete'])) {
				foreach($_POST['delete'] as $i) {
					$resId = $_POST['resId'.$i];
					deleteJob($resId);
				}
			} else {
				$i = $_POST['delete'];
				$resId = $_POST['resId'.$i];
				deleteJob($resId);
			}
		}
		header("Location:".$selfName."?p=".$pageNo);
	} else if ($send==="選択") {
		$changed = true;
		$neworadd = isset($_POST['neworadd'])?$_POST['neworadd']:0;
		$itemChosen = isset($_POST['itemChosen'])?$_POST['itemChosen']:array();
		$jData = itemData::uncompress($_POST['jobData']);
		$jData->title = $_POST['atitle'];
		$jData->category = $_POST['acategory'];
		$jData->genre = $_POST['agenre'];
		$jData->filePrefix = $_POST['afile'];
		$jData->nImages = $_POST['animages'];
		$jData->useTextFlow = $_POST['ashowText'];
		$jData->fontSize = $_POST['afontSize'];
		$jData->scrollSpeed = $_POST['asspeed'];
		$jData->useVoice = $_POST['avoice'];
		$jData->voiceVolume = $_POST['avolume'];
		$jData->voiceSpeed = $_POST['aspeed'];
		$jData->voicePitch = $_POST['apitch'];
		$jData->voiceRange = $_POST['arange'];
		$jData->musicVolume = $_POST['amvolume'];
		$jData->annotation = $_POST['aannotxt'];
		$jData->annoStart = $_POST['aastart'];
		$jData->annoEnd = $_POST['aaend'];
		$jData->fps = $_POST['afps'];
		$iItemData = new mDataClass();
		$iItemData->decode($_POST['aimageData']);
		$jData->imageData = $iItemData;
		$jData->desc = $_POST['adesc'];
		if ($_POST['atags'])
			$jData->tga = explode(",",$_POST['atags']);
		else
			$jData->tga = array();

		switch ($menuselected) {
		case 1:
			if ($neworadd) {
				$jData->articleData->clear();
				$jData->narrationText = '';
			}
			foreach ($itemChosen as $i) {
				$id = $_POST['articleId'.$i];
				//echo "adding article ".$id."<br>";
				$title = $_POST['title'.$i];
				$text = $_POST['text'.$i];
				$category = $_POST['category'.$i];
				$quote = $_POST['quote'.$i];
				$item = new articleDataClass(null,$id,$title,$text,$category);
				$item->loadData();
				$jData->articleData->push($item);
			}
			$jData->articleChanged = true;
			break;
		case 2:
			if ($neworadd) {
				$jData->imageData->clear(function($item) { return $item->type==1; });
			}
			foreach ($itemChosen as $i) {
				$id = $_POST['imageId'.$i];
				$title = $_POST['title'.$i];
				$file = $_POST['file'.$i];
				$item = new imageDataClass(null,$id,$title,$file);
				$item->type=1;
				$item->loadData($id);
				$jData->imageData->push($item);
			}
			$jData->imageChanged = true;
			break;
		case 3:
			if ($neworadd) {
				$jData->imageData->clear(function($item) { return $item->type==2; });
			}
			foreach ($itemChosen as $i) {
				$id = $_POST['imageId'.$i];
				$title = $_POST['title'.$i];
				$file = $_POST['file'.$i];
				$item = new imageDataClass(null,$id,$title,$file);
				$item->type=2;
				$item->loadData($id);
				$jData->imageData->push($item);
			}
			$jData->imageChanged = true;
			break;
		case 4:
			$i = $itemChosen[0];
			$id = $_POST['videoId'.$i];
			$title = $_POST['title'.$i];
			$file = $_POST['file'.$i];
			if (!$jData->getOpenVideo()) {
				$item = new videoDataClass(null,$id,$title,$file,1,$userGroup);
				$item->loadData($id);
				$jData->cmVideo = $item;
			} else { // update video item
				$jData->getOpenVideo()->videoId = $id;
				$jData->getOpenVideo()->title = $title;
				$jData->getOpenVideo()->fileName = $file;
			}
			$jData->videoChanged = true;
			break;
		case 5:
			$i = $itemChosen[0];
			$id = $_POST['videoId'.$i];
			$title = $_POST['title'.$i];
			$file = $_POST['file'.$i];
			if (!$jData->getEndVideo()) {
				$item = new videoDataClass(null,$id,$title,$file,2,$userGroup);
				$item->loadData($id);
				$jData->endVideo = $item;
			} else { // update video item
				$jData->getEndVideo()->videoId = $id;
				$jData->getEndVideo()->title = $title;
				$jData->getEndVideo()->fileName = $file;
			}
			$jData->videoChanged = true;
			break;
		}
	}
} else {
	if (isset($_GET['mi'])) {
		$jID = $_GET['mi'];
		$jData = new jDataClass($jID);
	} else {
		$jData = new jDataClass();
		$jData->setGroupID($userGroup);
	}
}


function getInfoFields(&$jData) {
	$jData->title = $_POST['title'];
	// sanitize filename
	$jData->filePrefix = sanitizeFileName($_POST['prefix']);
	$jData->nImages = $_POST['nimages'];
	$jData->category = $_POST['category'];
	$jData->genre = $_POST['genre'];
	$jData->articleChanged = $_POST['articleChanged'];
	$jData->imageChanged = $_POST['imageChanged'];
	$jData->videoChanged = $_POST['videoChanged'];
	$jData->useTextFlow = $_POST['showText'];
	$jData->fontSize = $_POST['fontSize'];
	$jData->scrollSpeed = $_POST['sspeed'];
	$jData->useVoice = $_POST['voice'];
	$jData->voiceVolume = $_POST['volume'];
	$jData->voiceSpeed = $_POST['speed'];
	$jData->voicePitch = $_POST['pitch'];
	$jData->voiceRange = $_POST['range'];
	$jData->musicVolume = $_POST['mvolume'];
	$jData->fps = $_POST['fps'];
	$jData->annotation = $_POST['annotxt'];
	$jData->annoStart = $_POST['astart'];
	$jData->annoEnd = $_POST['aend'];
	$jData->desc = isset($_POST['desc'])?$_POST['desc']:'';
	$jData->tga = isset($_POST['tags'])?$_POST['tags']:array();
}

function deleteJob($resId) {
	echo "deleting ".$resId."<br><br>\n";
	$jData = new jDataClass($resId);
	if (!$jData->delete())
		$message = "エラー: $resIdを削除できません！";
}
?>
<!DOCTYPE html>
<html lang="ja">
<head>
	<meta Content-Type: text/html; charset=UTF-8 />
	<title>ランダム一括動画制作</title>
	<link rel="stylesheet" href="style.css">
	<link rel="stylesheet" href="css/pb-style.css">
	<link rel="stylesheet" href="../js/jquery-ui.css">
	<link rel="stylesheet" href="../js/jquery-ui-slider-pips.css">
	<link rel="stylesheet" href="../js/nanoscroller.css">
	<link rel="stylesheet" type="text/css" href="../js/css/jQuery.mb.miniAudioPlayer.min.css" title="style" media="screen"/>
	<script type='text/javascript' src="../js/jquery-2.1.1.min.js"></script>
	<script src="//code.angularjs.org/1.4.5/angular.min.js"></script>
	<script type='text/javascript' src='../js/menu_app.js'></script>
	<script type='text/javascript' src="../js/jquery.bpopup.min.js"></script>
	<script type='text/javascript' src="../js/jquery-ui.js"></script>
	<script type='text/javascript' src='../js/jquery-ui-slider-pips.js'></script>
  	<script type='text/javascript' src='../js/rangyinputs-jquery.js'></script>
  	<script type='text/javascript' src="../js/jquery.nanoscroller.min.js"></script>
	<script type="text/javascript" src="../js/jQuery.mb.miniAudioPlayer.min.js"></script>
	<script type='text/javascript' src='../js/jQuery.download.js'></script>
<style>
#filedrop1 {
	padding: 1em 0;
	margin: 1em 0;
	color: #555;
	border: 2px dashed #555;
	border-radius: 7px;
	cursor: default;
}

#filedrop1.hover {
	color: #aaa;
	border: 2px;
	border-color: #f00;
	border-style: solid;
	box-shadow: inset 0 3px 4px #888;
	background-color:rgba(255,255,255,0.3);
}
#filedrop1 p { margin: 10px; font-size: 14px; }

#filedrop2 {
	padding: 1em 0;
	margin: 1em 0;
	color: #555;
	border: 2px dashed #555;
	border-radius: 7px;
	cursor: default;
}

#filedrop2.hover {
	color: #aaa;
	border: 2px;
	border-color: #f00;
	border-style: solid;
	box-shadow: inset 0 3px 4px #888;
	background-color:rgba(255,255,255,0.3);
}
#filedrop2 p { margin: 10px; font-size: 14px; }
progress:after { content: '%'; }
.fail { background: #c00; padding: 2px; color: #fff; }
.hidden { display: none !important;}
</style>
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
<div class="subtitle">AUTO VIDEO PRODUCER</div>
<span id="message"><?=$message?></span>

<div class="container_main">
<form class="select" method="POST" enctype="multipart/form-data">
<input type="hidden" name="userid" value="<?=$userID?>">
<input type="hidden" name="mi" value="<?=$jData->id?>">
<input type="hidden" id="articleChanged" name="articleChanged" value="<?=$jData->articleChanged?>">
<input type="hidden" id="imageChanged" name="imageChanged" value="<?=$jData->imageChanged?>">
<input type="hidden" name="videoChanged" value="<?=$jData->videoChanged?>">
<table class="tube">
<tr style="text-align:left;"><td colspan=4>
<table><tr>
<td style="padding-left:15px;">ジョブ名</td><td>prefix</td><td>カテゴリ</td><td>ジャンル</td></tr>
<tr>
<td><input id="title" class="titleinput" name="title" type="text" size="40" value="<?=$jData->title?>"></td>
<td style="width:50px;">
<input type="text" id="file" name="prefix" size="35" value="<?=$jData->filePrefix?>"><br>
<font style="font-size:10px;">半角ローマ字だけを推奨します！</font>
</td>
<td>
<select id="category" name="category">
<?php getCategoryList($jData->category); ?>
</select>
</td>
<td>
<select id="genre" name="genre">
<?php getGenreList($jData->genre); ?>
</select>
</td>
</tr></table>
</td>
</tr>
<tr><td></td><td></td></tr>
<tr><td colspan=4><hr></td></tr>
<tr><td colspan=2><div class="style_01" id="part1">記事素材</div></td><td></td></tr>
<tr><td colspan=2>
<div id="articleContent">
<div class="nano-content" id="articleDisplay"><span style='color:gray;margin-left:10px;'>記事が選択されてません</span></div>
</div>
<input type="hidden" id="articleData" name="articleHtml" value="<?=$jData->articleData->encode()?>">
</td>
<td style="width:20%">
テキストの表示位置
<select id="showText" name="showText">
<option value="0" <?=($jData->useTextFlow==0)?"selected":""?>>無し</option>
<option value="1" <?=($jData->useTextFlow==1)?"selected":""?>>下</option>
<option value="2" <?=($jData->useTextFlow==2)?"selected":""?>>中</option>
<option value="3" <?=($jData->useTextFlow==3)?"selected":""?>>上</option>
<option value="4" <?=($jData->useTextFlow==4)?"selected":""?>>上から1/6</option>
<option value="5" <?=($jData->useTextFlow==5)?"selected":""?>>上から1/3</option>
<option value="6" <?=($jData->useTextFlow==6)?"selected":""?>>下から1/3</option>
<option value="7" <?=($jData->useTextFlow==7)?"selected":""?>>下から6/1</option>
<option value="8" <?=($jData->useTextFlow==8)?"selected":""?>>垂直スクロール</option>
</select><br>
 フォントサイズ
<select id="fontSize" name="fontSize">
<option value="42" <?=($jData->fontSize==42)?"selected":""?>>42</option>
<option value="50" <?=($jData->fontSize==50)?"selected":""?>>50</option>
<option value="60" <?=($jData->fontSize==60)?"selected":""?>>60</option>
<option value="70" <?=($jData->fontSize==70)?"selected":""?>>70</option>
<option value="80" <?=($jData->fontSize==80)?"selected":""?>>80</option>
<option value="100" <?=($jData->fontSize==100)?"selected":""?>>100</option>
</select><br>
<table>
<tr><td style="width:60px;">
<font style="font-size:11px;">スクロール 速度調整：</font><input type="text" class="sliderValue" id="sspeed" name="sspeed" value="<?=$jData->scrollSpeed?>"></td>
<td style="width:150px;height:40px;"><div id="ss_slider"></div></td></tr>
</table><br>
音声
<select id="voice" name="voice">
<option value="0" <?=($jData->useVoice==0)?"selected":""?>>読み上げ無し</option>
<option value="1" <?=($jData->useVoice==1)?"selected":""?>>せいじ</option>
<option value="2" <?=($jData->useVoice==2)?"selected":""?>>おさむ</option>
<option value="3" <?=($jData->useVoice==3)?"selected":""?>>のぞみ</option>
<option value="4" <?=($jData->useVoice==4)?"selected":""?>>すみれ</option>
</select><br>
<table>
<tr><td>音量：<input type="mv_slider" class="sliderValue" id="volume" name="volume"></td>
<td style="height:40px;"><div id="v_slider"></div></td></tr>
<tr><td>話速：<input type="mv_slider" class="sliderValue" id="speed" name="speed"></td>
<td style="height:40px;"><div id="s_slider"></div></td></tr>
<tr><td>高さ：<input type="mv_slider" class="sliderValue" id="pitch" name="pitch"></td>
<td style="height:40px;"><div id="p_slider"></div></td></tr>
<tr><td>抑揚：<input type="mv_slider" class="sliderValue" id="range" name="range"></td>
<td style="height:40px;"><div id="r_slider"></div></td></tr>
<tr><td></td></tr>
</table>
<br>
</td>
</tr>
<tr><td colspan=3>
<input type="button" class="showhide" id="showArtSearch" value="記事を探す">
<table class="tubecell" id="articleSearch"><tr>
<td><input class="searchbuttonclass" id="searchArticleBtn" type="button" name="send" value="記事を探す"></td></tr>
</table>
</td></tr>

<tr><td colspan=3><hr></td></tr>
<tr><td colspan=3><div class="style_01" id="part2">画像素材</div></td></tr>
<tr><td style="width:38%;text-align:center;">カバー画像</td><td></td><td>エンディング画像</td></tr>
<tr><td colspan=3>
<table class="tubecell"><tr><td style="padding:4px;width:35%;">
<div id="imageDisplay1"></div>
</td>
<td style="width:30%;text-align:center;">ランダム画像数<br><input id='nimages' name='nimages' size=8 value='<?=$jData->nImages?>' /></td>
<td style="text-align:right;">
<div id="imageDisplay2"></div>
</table>

<!-- drag & drop用 -->
<table style="margin:0;border:0"><tr><td>
<div id="filedrop1" style="text-align:center;margin:0 16px 0 4px;">
<p id="filereader1"></p>
<p id="formdata1"><input type="file" multiple="multiple" accept=".jpg,.png,image/jpeg,image/png" name="imageFile[]" id="imageFile"> &nbsp;
<input type="submit" class="hidden" id="upload1" name="send" value="upload">
選択した画像をアップロード</p>
<p id="progress1"></p>
ここにカバー画像ファイルをドラッグしてください &nbsp;
<progress id="uploadprogress1" min="0" max="100" value="0">0</progress>
</div>
</td><td>
<div id="filedrop2" style="text-align:center;margin:0 16px 0 4px;">
<p id="filereader2"></p>
<p id="formdata2"><input type="file" multiple="multiple" accept=".jpg,.png,image/jpeg,image/png" name="imageFile[]" id="imageFile"> &nbsp;
<input type="submit" class="hidden" id="upload2" name="send" value="upload">
選択した画像をアップロード</p>
<p id="progress2"></p>
ここにエンディング画像ファイルをドラッグしてください &nbsp;
<progress id="uploadprogress2" min="0" max="100" value="0">0</progress>
</div>
</td></tr></table>

</td></tr>
<tr>
<td style="width:38%;text-align:center;">
<input type="hidden" id="showImgSearchState" value="0">
<input class="searchbuttonclass" id="searchImage1Btn" type="button" name="send" value="画像を探す"></td>
<td></td>
<td>
<input class="searchbuttonclass" id="searchImage2Btn" type="button" name="send" value="画像を探す"></td>
</tr>
<input type="hidden" id="imageData" name="imageHtml" value="<?=$jData->imageData->encode()?>">

<tr><td colspan=2>
<div id="voicePlayerArea" style="display:none; margin:2px; width:500px;">
<audio id="voicePlayer" controls class="" style="width:600px;">
<source src="http://sinobi2.bitnamiapp.com/ts/v/default.mp3" type="audio/mp3">
<p>音声を再生するには、audioタグをサポートしたブラウザが必要です。</p>
</audio>
</div>
</td>
</td></tr>

<tr><td colspan=4><hr></td></tr>
<tr><td colspan=4><div class="style_01" id="part3">音楽素材</div></td></tr>
<tr><td style="width:130px;">BGM Mix音量：<input type="text" class="sliderValue" id="mvolume" name="mvolume" value="<?=$jData->musicVolume?>"></td>
<td style="width:190px;height:40px;"><div id="mv_slider"></div></td></tr>
<tr><td colspan=4>
<input type="button" class="showhide" id="showMusicSearch" value="音楽を探す">
<table class="tubecell" id="musicSearch">
<tr><td colspan=2 style="font-size:10px;text-align:left;">
<?php
	$sql = "select * from tags";
	$tags = getDB($sql);
	$i = 0;
	foreach ($tags as $tag) {
		$id = $tag['tag_id'];
		$name = $tag['tag'];
		if (array_search($id, $jData->tga)!==false) $check = " checked";
		else $check = '';
		echo '<input type="checkbox" id="t'.$id.'" class="ts" name="tags[]" value="'
			.$id.'" '.$check.'><label for="t'.$id.'"><span></span></label>'.$name;
		echo "<input type='hidden' name='tagId$i' value='".$id."'>";
		$i++;
	}
?>
</td>

</tr></table>
</td></tr>

<tr><td colspan=3><hr></td></tr>
<tr><td colspan=3><div class="style_01" id="part3">オープニング ＆ エンディング動画</div></td></tr>
<tr><td style="text-align:center;">オープニング動画 <input class="searchbuttonclass" id="searchOPVideoBtn" type="button" name="send" value="Op動画を探す"></td><td></td>
<td>エンディング動画 <input class="searchbuttonclass" id="searchEndVideoBtn" type="button" name="send" value="End動画を探す"></td></tr>
<tr><td colspan=3>
<table class="tubecell"><tr><td style="padding:4px 0 4px 30px;width:50%;">
<?php if ($jData->getOpenVideo()) { ?>
<div style="height:140px;">
<video id="cmVideo" style="width:240px;" controls>
    <source src="<?=$jData->getOpenVideo()->getFilePath()?>">
</video>
<input id="deleteCMVideoBtn" class="delImageBtn" type="submit" name="send" value="OP動画を削除">
</div>
<?php } else {
	echo "<span style='color:gray;margin-left:10px;'>動画が選択されてません</span>";
} ?>
</td>
<td style="padding-left:70px;">
<?php if ($jData->getEndVideo()) { ?>
<div>
<video id="enVideo" style="width:240px;" controls>
	<source src="<?=$jData->getEndVideo()->getFilePath()?>">
</video>
<input id="deleteEndVideoBtn" class="delImageBtn" type="submit" name="send" value="End動画を削除">
</div>
<?php } else {
	echo "<span style='color:gray;margin-left:10px;'>動画が選択されてません</span>";
} ?>
</table>
<input type="hidden" name="cmVideoHtml" value="<?=$jData->getOpenVideo()?$jData->getOpenVideo()->getID():""?>">
<input type="hidden" name="endVideoHtml" value="<?=$jData->getEndVideo()?$jData->getEndVideo()->getID():""?>">
</td></tr>
<tr><td colspan=4><hr></td></tr>
<!--
<tr><td colspan=3><div class="style_01" id="part3">動画アップロード情報</div></td></tr>
<tr><td>
<input type="button" class="showhide" id="showUploadInfo" value="アップロード情報"></td></tr>
<tr><td colspan=4>
<table class="tubecell" id="uploadInfo" style="display:none;">
<tr><td>説明 <textarea id="descText" name="desc"><?=htmlspecialchars($jData->desc)?></textarea></td>
<tr><td>タグ
<textarea class='tagarea' id='tagEdit' style="height:30px;" name='tags'><?=htmlspecialchars($jData->tags)?></textarea>
</td></tr>
</table>
</td></tr>
<tr><td colspan=4><hr></td></tr>
-->
<tr><td colspan=3><div class="style_01" id="part4">アノテーション</div></td></tr>
<tr><td>
<textarea id="annotxt" name="annotxt"><?=htmlspecialchars($jData->annotation)?></textarea>
</td>
<td>開始時間（秒）<input type="text" id="astart" name="astart" size=5 value="<?=$jData->annoStart?>"></td>
<td>終了時間（秒）<input type="text" id="aend" name="aend" size=5 value="<?=$jData->annoEnd?>"></td>
</tr>
<tr><td colspan=3><hr></td></tr>
<tr><td>動画FPS
<select id="fps" name="fps">
<option value="30" <?php if ($jData->fps==30) echo "selected";?>>30 fps</option>
<option value="60" <?php if ($jData->fps==60) echo "selected";?>>60 fps</option>
</select>
</td>
<td></td>
<td style="text-align:right;">
<a href="<?=$selfName?>"><input class="buttonclass" type="button" value="新規"></a>  &nbsp; &nbsp; &nbsp;
<input class="button" type="submit" name="send" value="保存">
</td></tr>
<tr><td colspan=3><hr></td></tr>
<tr><td colspan=3><div class="style_01" id="part3">ジョブ管理</div></td></tr>
<tr><td colspan=3> </td></tr>
</table>
<table>
<tr>
<td><div id="errorMessage" style="color:blue;" /></td>
<td style="border:0;text-align:right;">
制作されたジョブをすべて<input class="buttonclass" type="button" value="選択" onclick="selectAllDldedItems()"> &nbsp;
<input class="buttonclass" type="submit" name="send" value="削除"> &nbsp; &nbsp; &nbsp; &nbsp; &nbsp;
未制作のジョブすべて<input class="buttonclass" type="button" value="選択" onclick="selectAllUnprodItems()"> &nbsp; &nbsp;
動画制作<input class="startBtn" type="submit" name="send" value="制作"></td>
</tr>
</table>
<div id="jobList"></div>
</form>

<div id="popup2">
<span class="cbutton b-close"><span><img src="img/dialog_close.png"></span></span>
<form id="selectform" method="POST">
<br>
<div class="selectButtons" style="text-align:left;margin-left:50px;font-size:14px;">
<div class="triangle">使用したい素材を選択してください。</div>
<table style="border:0;margin:0;padding:0;"><tr><td style="width:20px;">
<input type="submit" class="green small button" name="send" value="選択"></td>
<td><div class="neworad">
<input class="neworadd" type="radio" name="neworadd" value="0" checked>追加
<input class="neworadd" type="radio" name="neworadd" value="1">新規</div></td>
</tr>
<tr><td><input type="checkbox" id="checkall" class=neworad"><label for='checkall'><span></span></label></td></tr>
</table>
<div class="nano" id="content2">
<div class="nano-content" id="scrollarea">
<br></div></div>
<input type="hidden" name="jobData" value="<?=itemData::compress($jData)?>">
<input type="hidden" class="menuselected" name="menuselected" value="<?=$menuselected?>">
<input type="hidden" id="atitle" name="atitle">
<input type="hidden" id="afile" name="afile">
<input type="hidden" id="aarticleText" name="aarticleText">
<input type="hidden" id="acategory" name="acategory">
<input type="hidden" id="agenre" name="agenre">
<input type="hidden" id="animages" name="animages">
<input type="hidden" id="ashowText" name="ashowText">
<input type="hidden" id="afontSize" name="afontSize">
<input type="hidden" id="asspeed" name="asspeed">
<input type="hidden" id="avoice" name="avoice">
<input type="hidden" id="avolume" name="avolume">
<input type="hidden" id="aspeed" name="aspeed">
<input type="hidden" id="apitch" name="apitch">
<input type="hidden" id="arange" name="arange">
<input type="hidden" id="amvolume" name="amvolume">
<input type="hidden" id="aannotxt" name="aannotxt">
<input type="hidden" id="aastart" name="aastart">
<input type="hidden" id="aaend" name="aaend">
<input type="hidden" id="afps" name="afps">
<input type="hidden" id="aquoteTitle" name="aquoteTitle">
<!--<input type="hidden" id="aquoteURL" name="aquoteURL">-->
<input type="hidden" id="asaveArticle" name="asaveArticle">
<input type="hidden" id="adesc" name="adesc">
<input type="hidden" id="atags" name="atags">
<input type="hidden" id="aimageData" name="aimageData">
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
<p class="footer_img"><br>Copyright © 2015-2016 J Hirota. All rights Reserved.</p>


<script language="JavaScript" type="text/JavaScript">
var playerType = 'HTML5';
var audio;
var serverUrl = "http://sinobi2.bitnamiapp.com";

var smpflag = navigator.userAgent.match(/(iPhone|iPad|Android)/);

var tagAdded = false;

$(function() {
	// initialize HTML5 audio
	try {
		audio = document.createElement("audio");
		if (audio == null || !audio.canPlayType) {
			playerType = 'jPlayer';
		}
	　　if(smpflag){
			audio = document.getElementById("voicePlayer");
		} else {
			$('#voicePlayerArea').hide();
		}
	}
	catch (e) {
		alert('Err:' + e);
		playerType = 'jPlayer';
	}

	if (playerType == 'jPlayer') {
		$("#player").jPlayer({
			swfPath: "../js/jquery.jplayer.min.js",
				ready: function() {
	  		},
			ended: function (event){
				change2PlayReady();
			}
		});
	}

	updateArticles('', 'undefined');
	updateImages('update', 'undefined', 1);
	updateImages('update', 'undefined', 2);
	updateJobList();

	$(".audio").mb_miniPlayer({
		width:300,
		inLine:false,
		id3:true,
		addShadow:false,
		pauseOnWindowBlur:false,
		downloadPage:null
	});

	$('.b-close').click(function() {
		$('.audio').mb_miniPlayer_stop();
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

	var sslabels = ['-1.0','','','','','-0.5','','','','','0.0','','','','','+0.5','','','','', '+1.0'];
	$("#ss_slider").slider({
    	animate: "fast",
    	min: -10,
    	max: 10,
    	value: 0,
    	animate: true,
    	orientation: "horizontal",
    	slide: function(event, ui) {
    		$('#sspeed').val((ui.value/10).toPrecision(2));
    	}
    }).slider("pips", {
    	rest: "label",
    	labels: sslabels
    });
    $("#ss_slider").slider("value", <?=$jData->scrollSpeed?>*10);
    $("#sspeed").val(($("#ss_slider").slider("value")/10).toPrecision(2));
    $("#sspeed").change(function(){
    	var tmp = $(this).val();
    	if (tmp>2) tmp = 2;
    	else if (tmp<0) tmp = 0;
    	$("#ss_slider").slider("value", tmp*10);
    	$(this).val(tmp);
    });

	var vollabels = ['0.0','','','','','0.5','','','','','1.0','','','','','1.5','','','','', '2.0'];
	$("#v_slider").slider({
    	animate: "fast",
    	min: 0,
    	max: 20,
    	value: 10,
    	animate: true,
    	orientation: "horizontal",
    	slide: function(event, ui) {
    		$('#volume').val((ui.value/10).toPrecision(2));
    	}
    }).slider("pips", {
    	rest: "label",
    	labels: vollabels
    });
    $("#v_slider").slider("value", <?=$jData->voiceVolume?>*10);
    $("#volume").val(($("#v_slider").slider("value")/10).toPrecision(2));
    $("#volume").change(function(){
    	var tmp = $(this).val();
    	if (tmp>2) tmp = 2;
    	else if (tmp<0) tmp = 0;
    	$("#v_slider").slider("value", tmp*10);
    	$(this).val(tmp);
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
    $("#s_slider").slider("value", <?=$jData->voiceSpeed?>*10);
    $("#speed").val(($("#s_slider").slider("value")/10).toPrecision(2));
    $("#speed").change(function(){
    	var tmp = $(this).val();
    	if (tmp>2) tmp = 2;
    	else if (tmp<0) tmp = 0;
    	$("#s_slider").slider("value", tmp*10);
    	$(this).val(tmp);
    });

	var ptlabels = ['0.5','','','','','1.0','','','','','1.5','','','','','2.0'];
    $("#p_slider").slider({
    	animate: "fast",
    	min: 5,
    	max: 20,
    	value: 10,
    	animate: true,
    	orientation: "horizontal",
    	slide: function(event, ui) {
    		$('#pitch').val((ui.value/10).toPrecision(2));
    	}
    }).slider("pips", {
    	rest: "label",
    	labels: ptlabels
    });
	$("#p_slider").slider("value", <?=$jData->voicePitch?>*10);
    $("#pitch").val(($("#p_slider").slider("value")/10).toPrecision(2));
    $("#pitch").change(function(){
    	var tmp = $(this).val();
    	if (tmp>2) tmp = 2;
    	else if (tmp<0) tmp = 0;
    	$("#p_slider").slider("value", tmp*10);
    	$(this).val(tmp);
    });

    $("#r_slider").slider({
    	animate: "fast",
    	min: 5,
    	max: 20,
    	value:10,
    	animate: true,
    	orientation: "horizontal",
    	slide: function(event, ui) {
    		$('#range').val((ui.value/10).toPrecision(2));
    	}
    }).slider("pips", {
    	rest: "label",
    	labels: ptlabels
    });
    $("#r_slider").slider("value", <?=$jData->voiceRange?>*10);
    $("#range").val(($("#r_slider").slider("value")/10).toPrecision(2));
    $("#range").change(function(){
    	var tmp = $(this).val();
    	if (tmp>2) tmp = 2;
    	else if (tmp<0) tmp = 0;
    	$("#r_slider").slider("value", tmp*10);
    	$(this).val(tmp);
    });

    $("#mv_slider").slider({
    	animate: "fast",
    	min: 0,
    	max: 20,
    	value: 10,
    	animate: true,
    	orientation: "horizontal",
    	slide: function(event, ui) {
    		$('#mvolume').val((ui.value/10).toPrecision(2));
    	}
    }).slider("pips", {
    	rest: "label",
    	labels: vollabels
    });
    $("#mv_slider").slider("value", <?=$jData->musicVolume?>*10);
    $("#mvolume").val(($("#mv_slider").slider("value")/10).toPrecision(2));
    $("#mvolume").change(function(){
    	var tmp = $(this).val();
    	if (tmp>2) tmp = 2;
    	else if (tmp<0) tmp = 0;
    	$("#mv_slider").slider("value", tmp*10);
    	$(this).val(tmp);
    });




	$('#showAdjuster').click(function(){
		$('#adjuster').toggle();
		$('#adjusterp').toggle();
	});

    $( "#tabs" ).tabs();


/* Resource manipulation functions */
	$('#searchArticleBtn').click(function() {
		$('.menuselected').val('1');
		var k = '';
		var c = $('#category').val();
		var pt = 1;
		getArticles(k,c,pt);

		$('#popup2').bPopup({
			opacity: 0.6,
			positionStyle: 'fixed'
		});
	});

	$('#searchImage1Btn').click(function() {
		$('.menuselected').val('2');
		var k = '';
		var c = $('#category').val();
		var g = $('#genre').val();
		var pt = 1;
		getImages(k,c,g,pt);

		$('#popup2').bPopup({
			opacity: 0.6,
			positionStyle: 'fixed'
		});
	});

	$('#searchImage2Btn').click(function() {
		$('.menuselected').val('3');
		var k = '';
		var c = $('#category').val();
		var g = $('#genre').val();
		var pt = 1;
		getImages(k,c,g,pt);

		$('#popup2').bPopup({
			opacity: 0.6,
			positionStyle: 'fixed'
		});
	});

	$('#searchOPVideoBtn').click(function() {
		$('.menuselected').val('4');
		var k = '';
		var c = 0; //$('#category').val();
		var t = 1;
		var pt = 1;
		getVideo(k,c,t,pt);

		$('#popup2').bPopup({
			opacity: 0.6,
			positionStyle: 'fixed'
		});
	});

	$('#searchEndVideoBtn').click(function() {
		$('.menuselected').val('5');
		var k = '';
		var c = 0; //$('#category').val();
		var t = 2;
		var pt = 1;
		getVideo(k,c,t,pt);

		$('#popup2').bPopup({
			opacity: 0.6,
			positionStyle: 'fixed'
		});
	});

	$('#showArtSearch').click(function() {
		$('#articleSearch').toggle();
	});

	$('#showImgSearch').click(function() {
		$('#imageSearch').toggle();
		if ($('#showImgSearchState').val()=='1')
			$('#showImgSearchState').val('0');
		else
			$('#showImgSearchState').val('1');
	});

	$('#showMusicSearch').click(function() {
		$('#musicSearch').toggle();
	});

	$('#showUploadInfo').click(function() {
		$('#uploadInfo').toggle();
	});

	$('#checkall').click(function() {
		var setvar = $(this).prop('checked');
		$('.incitem').each(function() {
			$(this).prop('checked', setvar);
		});
	});


	bindTooltip();

	window.setInterval("updateJobList()", 10*1000);
});

function bindButtonClick() {
	$('.articlecell').draggable({
		cursor: "move",
		opacity: 0.4,
		revert: "invalid",
		revertDuration: 10
	});
	$('.articlecell').droppable({
		drop: function(event, ui) {
			var fid = ui.draggable.attr("id");
			var fi = fid.substring(2);
			var id = $(this).attr("id");
			var i = id.substring(2);
			updateArticles('dp'+i, fi);
		}
	});
	$('.delArticleBtn').click(function() {
		var id = $(this).attr("id");
		var i = id.substring(2);
		updateArticles('dl'+i);
	});

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
			var type = $(this).parent().prop('id').slice(-1);
			updateImages('dp'+i, fi, type);
		}
	});
	$('.delImageBtn').click(function() {
		var id = $(this).attr("id");
		var i = id.substring(2);
		var type = $(this).parent().prop('id').slice(-1);
		updateImages('dl'+i,'undefined', type);
	});
}

function bindTooltip() {
	$('.masterTooltip').hover(function() {
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
}

function selectAllDldedItems() {
	$('.dlBtn').each(function() {
		var id = $(this).attr('id');
		var idx = id.substring(3);
		if ($('#dlded'+idx).val()==1)
			$('#del'+idx).prop('checked', true);
	});
}

function selectAllUnprodItems() {
	$('.produce').each(function() {
		var id = $(this).attr('id');
		var idx = id.substring(4);
		if (!$('#dwl'+idx).length) $(this).prop('checked', true);
	});
}


function updateAudio() {
	$(".audio").mb_miniPlayer({
		width:300,
		inLine:false,
		id3:true,
		addShadow:true,
		pauseOnWindowBlur:false,
		downloadPage:null
	});
}

var waitMessageHTML = '<br><br><img style="height:100px;width:100px;margin:30px;" src="img/ajax-loader.gif" alt="処理中.."/><br>少々お待ちください・・・・';

function updateImages(cmd, fid, typ) {
	if (cmd!="") $('#imageChanged').val(true);
	if (typeof fid!=='undefined')
		var data = {send:cmd, fi:fid, tp:typ, mItemData:$('#imageData').val()};
	else {
		var data = {send:cmd, tp:typ, mItemData:$('#imageData').val()};
	}
	$.ajax({
		type:'POST',
		url:'updateDisplayImages.php',
		data:data,
		dataType:'json',
		success: function(result) {
			$('#imageData').val(result['mitemdata']);
			var exhtml = (result['exhtml'])?result['exhtml']:"<span style='color:gray;margin-left:10px;'>画像が選択されてません</span>";
			if (result['type']==2)
				$('#imageDisplay2').html(exhtml);
			else
				$('#imageDisplay1').html(exhtml);
			bindButtonClick();
		},
		error: function(result) {
			alert('サーバーからの読み込み失敗: '+result['exhtml']);
		}
	});
}

function updateArticles(cmd, fid) {
	var url = "processArticleData.php";
	if (cmd!="") $('#articleChanged').val(true);
	if (typeof fid!=='undefined')
		var data = {send:cmd, fi:fid, mItemData:$('#articleData').val()};
	else
		var data = {send:cmd, mItemData:$('#articleData').val()};
	$.ajax({
		type: 'POST',
		url: url,
		data: data,
		dataType: 'json',
		success: function(result) {
			$('#articleData').val(result['mitemdata']);
			//alert ('('+result['exhtml']+')');
			if (result['exhtml']!="")
				$('#articleDisplay').html(result['exhtml']);
			else
				$('#articleDisplay').html("<span style='color:gray;margin-left:10px;'>記事が選択されてません</span>");
			bindButtonClick();
		},
		error: function(result) {
			alert('サーバーからの読み込み失敗 '+result);
		}
	});
}


function getArticles(key, cat, page) {
	$('#scrollarea').html(waitMessageHTML);
	var url = "getArticles.php";
	var data = {k:key, c:cat, u:<?=$userGroup?>, p:page};
	saveFields();
	$.ajax({
		url: url,
		data: data,
		dataType: 'text',
		success: function(data) {
			$('#scrollarea').html(data);
			$(".nano").nanoScroller();
			$('.selectButtons').show();
			$('.neworad').show();
			$('.checkall').show();
		},
		error: ajaxError(data)
	});
}


function getImages(key, cat, gen, page) {
	$('#scrollarea').html(waitMessageHTML);
	var url = "getImages.php";
	var data = {k:key, c:cat, g:gen, u:<?=$userGroup?>, p:page};
	saveFields();
	$.ajax({
		url: url,
		data: data,
		dataType: 'text',
		success: function(data) {
			$('#scrollarea').html(data);
			$(".nano").nanoScroller();
			$('.selectButtons').show();
			$('.neworad').show();
			$('.checkall').hide();
		},
		error: ajaxError(data)
	});
}


function getVideo(key, cat, type, page) {
	$('#scrollarea').html(waitMessageHTML);
	var url = "getVideo.php";
	var data = {k:key, c:cat, t:type, u:<?=$userGroup?>, p:page};
	saveFields();
	$.ajax({
		url: url,
		data: data,
		dataType: 'text',
		success: function(data) {
			$('#scrollarea').html(data);
			$(".nano").nanoScroller();
			$('.selectButtons').show();
			$('.neworad').hide();
			$('.checkall').hide();
		},
		error: ajaxError(data)
	});
}

function ajaxError(data) {
	$('#scrollarea').html( '読み込み失敗' );
	$('#scrollarea').append(data);
	$('.selectButtons').hide();
}

function updateJobList() {
	var url = 'updateJobList.php';
	var dels = [];
	var prods = [];
	$('#videoList .delete:checked').each(function() {
		dels.push($(this).val());
	});
	$('#videoList .produce:checked').each(function() {
		prods.push($(this).val());
	});
	var data = {p:<?=$pageNo?>, dl:dels, pr:prods, nw:0, ug:<?=$userGroup?>};
	$.ajax({
		url: url,
		data: data,
		dataType: 'text',
		success: function(data) {
			$('#jobList').html(data);
			bindTooltip();
		},
		error: function(data) {
			$('#errorMessage').html( 'アップデート読み込み失敗' );
		}
	});
}

var tags = [];

function saveFields() {
	$('#atitle').val($('#title').val());
	$('#aarticleText').val($('#articleText').val());
	$('#acategory').val($('#category').val());
	$('#agenre').val($('#genre').val());
	$('#animages').val($('#nimages').val());
	if ($('#file').val())
		$('#afile').val($('#file').val());
	else
		$('#afile').val($('#title').val());
	$('#ashowText').val($('#showText').val());
	$('#afontSize').val($('#fontSize').val());
	$('#asspeed').val($('#sspeed').val());
	$('#avoice').val($('#voice').val());
	$('#avolume').val($('#volume').val());
	$('#aspeed').val($('#speed').val());
	$('#apitch').val($('#pitch').val());
	$('#arange').val($('#range').val());
	$('#amvolume').val($('#mvolume').val());
//	$('#aquoteTitle').val($('#quoteTitle').val());
	$('#aannotxt').val($('#annotxt').val());
	$('#aastart').val($('#astart').val());
	$('#aaend').val($('#aend').val());
	$('#afps').val($('#fps').val());
	$('#asaveArticle').val($('#saveArticle').val());
	$('#adesc').val($('#desc').val());
	$('input .ts').each(function(i, e) {
		alert($(this).val());
	});
	$('#aimageData').val($('#imageData').val());
	// need to convert class 'ts' items into tags array
	$('.ts:checked').each(function() {
		tags.push($(this).val());
	});
	$('#atags').val(tags.join());
}

function setCharNum() {
	var mess = "";
	var nChars = $("#articleText").val().length;
	if (nChars>1000) {
		$("#nChars").css('color','red');
		mess = " 　1000文字以上の文章はいくつかに分けて制作してください。";
	} else if (nChars>800) {
			$("#nChars").css('color','DarkOrange ');
			mess = " 　文字数が多くなると途中で止まることがあります。";
		}
	else $("#nChars").css('color','black');
	$("#nChars").text("文字数: " + nChars + mess);
}




// Obviously a bad practice to keep two sets of almost identical code
// should be refactored when there's time
var holder1 = document.getElementById('filedrop1'),
    tests1 = {
      filereader: typeof FileReader != 'undefined',
      dnd: 'draggable' in document.createElement('span'),
      formdata: !!window.FormData,
      progress: "upload" in new XMLHttpRequest
    },
    support1 = {
      filereader: document.getElementById('filereader1'),
      formdata: document.getElementById('formdata1'),
      progress: document.getElementById('progress1')
    },
    acceptedTypes = {
      'image/png': true,
      'image/jpeg': true,
      'image/gif': false
    },
    progress1 = document.getElementById('uploadprogress1'),
    fileupload1 = document.getElementById('upload1');

var holder2 = document.getElementById('filedrop2'),
    tests2 = {
      filereader: typeof FileReader != 'undefined',
      dnd: 'draggable' in document.createElement('span'),
      formdata: !!window.FormData,
      progress: "upload" in new XMLHttpRequest
    },
    support2 = {
      filereader: document.getElementById('filereader2'),
      formdata: document.getElementById('formdata2'),
      progress: document.getElementById('progress2')
    },
    progress2 = document.getElementById('uploadprogress2'),
    fileupload2 = document.getElementById('upload2');

"filereader formdata progress".split(' ').forEach(function (api) {
  if (tests1[api] === false) {
    support1[api].className = 'fail';
  } else {
    support1[api].className = 'hidden';
  }
  if (tests2[api] === false) {
    support2[api].className = 'fail';
  } else {
    support2[api].className = 'hidden';
  }
});

if (tests1.dnd) {
  holder1.ondragover = function () { this.className = 'hover'; return false; };
  holder1.ondragend = function () { this.className = ''; return false; };
  holder1.ondrop = function (e) {
    this.className = '';
    e.preventDefault();
    readfiles1(e.dataTransfer.files);
  }
} else {
  fileupload1.className = 'hidden';
  fileupload1.querySelector('input').onchange = function () {
    readfiles1(this.files);
  };
}

function readfiles1(files) {
    var fd = tests1.formdata ? new FormData() : null;
    for (var i = 0; i < files.length; i++) {
      if (tests1.formdata) fd.append('file[]', files[i]);
    }
	fd.append("send", "upo");
	fd.append("mItemData", $('#imageData').val());
	fd.append("ug", <?=$userGroup?>);
	fd.append("ct", $('#category').val());

    // now post a new XHR request
    if (tests1.formdata) {
		var xhr = new XMLHttpRequest();
		xhr.open('POST', 'updateDisplayImages.php');
		xhr.onload = function() {
		  progress1.value = progress1.innerHTML = 100;
		};
	}

	if (tests1.progress) {
        xhr.upload.onprogress = function (event) {
          if (event.lengthComputable) {
            var complete = (event.loaded / event.total * 100 | 0);
            progress1.value = progress1.innerHTML = complete;
          }
        }
	}

	xhr.onreadystatechange = function(event) {
		var xhr = event.target;
		if (xhr.readyState === 4 && xhr.status === 200) {
//			alert(xhr.responseText);
			var json = JSON.parse(xhr.responseText);
			$('#imageData').val(json['mitemdata']);
			$('#imageDisplay1').html(json['exhtml']);
			$('#imageChanged').val(true);
			bindButtonClick();
		} else if (xhr.status!==0 && xhr.status!==200) {
			console.log('ERROR: '+xhr.status);
			alert('ERROR: '+xhr.readyState+'  '+xhr.status);
		}
	}
	xhr.send(fd);
}

if (tests2.dnd) {
  holder2.ondragover = function () { this.className = 'hover'; return false; };
  holder2.ondragend = function () { this.className = ''; return false; };
  holder2.ondrop = function (e) {
    this.className = '';
    e.preventDefault();
    readfiles2(e.dataTransfer.files);
  }
} else {
  fileupload2.className = 'hidden';
  fileupload2.querySelector('input').onchange = function () {
    readfiles2(this.files);
  };
}

function readfiles2(files) {
    var fd = tests2.formdata ? new FormData() : null;
    for (var i = 0; i < files.length; i++) {
      if (tests2.formdata) fd.append('file[]', files[i]);
    }
	fd.append("send", "upe");
	fd.append("mItemData", $('#imageData').val());
	fd.append("ug", <?=$userGroup?>);
	fd.append("ct", $('#category').val());

    // now post a new XHR request
    if (tests1.formdata) {
		var xhr = new XMLHttpRequest();
		xhr.open('POST', 'updateDisplayImages.php');
		xhr.onload = function() {
		  progress2.value = progress2.innerHTML = 100;
		};
	}

	if (tests2.progress) {
        xhr.upload.onprogress = function (event) {
          if (event.lengthComputable) {
            var complete = (event.loaded / event.total * 100 | 0);
            progress2.value = progress2.innerHTML = complete;
          }
        }
	}

	xhr.onreadystatechange = function(event) {
		var xhr = event.target;
		if (xhr.readyState === 4 && xhr.status === 200) {
//			alert(xhr.responseText);
			var json = JSON.parse(xhr.responseText);
			$('#imageData').val(json['mitemdata']);
			$('#imageDisplay2').html(json['exhtml']);
			$('#imageChanged').val(true);
			bindButtonClick();
		} else if (xhr.status!==0 && xhr.status!==200) {
			console.log('ERROR: '+xhr.status);
			alert('ERROR: '+xhr.readyState+'  '+xhr.status);
		}
	}
	xhr.send(fd);
}

</script>
</body>
</html>
