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
		if ($userLevel<2 || !$userID || !$userGroup) {
			writeLog("Log out: user id=".$userID."  group id=".$userGroup);
			header("Location:$rootUrl");
		} else
			$numLeft = getUsageQuota($userID,$maxN,$CUsage);
	} else {
		writeLog("Auth Lost: user id=".$userID."  group id=".$userGroup);
		header("Location:$rootUrl");
	}
} else
	header("Location:$rootUrl");

if (!$userID) header("Location:$rootUrl");

// AITalk security code
$tcode = sha1($userID.$loginId);

$selfName = basename(__FILE__);
$pageNo = isset($_GET['p'])?$_GET['p']:1;

$send = '';
$actionselected = 0;
$reuseArticle = 0;

if ($_POST) {
	$send = $_POST['send'];
	$actionselected = isset($_POST['actionselected'])?$_POST['actionselected']:0;
	if ($send==="保存" || $send==="制作" || $send==="upload" || $send==="Op動画を削除"
		|| $send==="End動画を削除") {
		if ($_POST['mi'])
			$videoInfoData = new videoProdDataClass($_POST['mi']);
		else
			$videoInfoData = new videoProdDataClass();
		getInfoFields($videoInfoData);
		$articleText = trim($_POST['articleText']);
		$reuseArticle = isset($_POST['reuseArticle'])?1:0;
		if ($articleText!==$videoInfoData->getArticle()) {
			$videoInfoData->setArticle($articleText, $reuseArticle);
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
		if ($send==="upload") {
			if ($_FILES["imageFile"]) {
				$fileArr = rearangeFiles($_FILES["imageFile"]);
				foreach ($fileArr as $file) {
					$imageObj = new imageDataClass(0,0,'','',$userGroup);
					$imageObj->reuse = 0;
					if ($imageObj->upload('',$file, $videoInfoData->category,0,'')) {
						$videoInfoData->imageData->push($imageObj);
						$videoInfoData->imageChanged = true;
					}
				}
			} else echo "image file empty<br>";
		} else if ($send==="Op動画を削除") {
			$videoInfoData->getOpenVideo()->detachVideo();
			$videoInfoData->videoData->deleteCurrent();
		} else if ($send==="End動画を削除") {
			$videoInfoData->getEndVideo()->detachVideo();
			$videoInfoData->videoData->deleteCurrent();
		}
		if ($send!=="制作" ||
		  ($send==="制作" && $videoInfoData->title && $_POST['fileName']))
			$videoInfoData->saveData();
		if ($send==="制作") {
			if ($userID && isset($_POST['produce']) && $numLeft>0) {
				if (is_array($_POST['produce'])) {
					foreach($_POST['produce'] as $i) {
						$resId = $_POST['resId'.$i];
						$sql = sprintf("call start_production(%u,%u)",$resId,$userID);
						$result = getDB($sql,false);
						if ($numLeft--<1) break;
					}
				} else {
					$resId = $_POST['produce'];
					$sql = sprintf("call start_production(%u,%u)",$resId,$userID);
					$result = getDB($sql,false);
				}
			}
		}
		if ($send==="保存")
			$message = "動画を保存しました";
		else if ($send==="制作")
			$message = "動画制作を開始しました";
		else
			$message = "動画を追加しました";
	} else if ($send==="削除") {
		if (isset($_POST['delete'])) {
			if (is_array($_POST['delete'])) {
				foreach($_POST['delete'] as $i) {
					$resId = $_POST['resId'.$i];
					deleteVideoProd($resId);
				}
			} else {
				$i = $_POST['delete'];
				$resId = $_POST['resId'.$i];
				deleteVideoProd($resId);
			}
		}
		header("Location:".$selfName."?p=".$pageNo);
	} else if ($send==="選択") {
		$changed = true;
		$neworadd = isset($_POST['neworadd'])?$_POST['neworadd']:0;
		$itemChosen = isset($_POST['itemChosen'])?$_POST['itemChosen']:array();
		$videoInfoData = itemData::uncompress($_POST['videoInfoData']);
		$videoInfoData->title = $_POST['atitle'];
		$videoInfoData->category = $_POST['acategory'];
		$videoInfoData->genre = $_POST['agenre'];
		$videoInfoData->videoFileName = $_POST['afile'];
		$videoInfoData->useTextFlow = $_POST['ashowText'];
		$videoInfoData->fontSize = $_POST['afontSize'];
		$videoInfoData->scrollSpeed = $_POST['asspeed'];
		$videoInfoData->useVoice = $_POST['avoice'];
		$videoInfoData->voiceVolume = $_POST['avolume'];
		$videoInfoData->voiceSpeed = $_POST['aspeed'];
		$videoInfoData->voicePitch = $_POST['apitch'];
		$videoInfoData->voiceRange = $_POST['arange'];
		$videoInfoData->musicVolume = $_POST['amvolume'];
		$videoInfoData->fps = $_POST['afps'];
		$videoInfoData->quoteTitle = $_POST['aquoteTitle'];
		$videoInfoData->annotation = $_POST['aannotxt'];
		$videoInfoData->annoStart = $_POST['aastart'];
		$videoInfoData->annoEnd = $_POST['aaend'];
		$articleText = $_POST['aarticleText'];
		if ($articleText!==$videoInfoData->getArticle())
			$videoInfoData->setArticle($articleText);
		$iItemData = new mDataClass();
		$iItemData->decode($_POST['aimageData']);
		$videoInfoData->imageData = $iItemData;
		$videoInfoData->desc = $_POST['adesc'];
		$videoInfoData->tags = $_POST['atags'];

		switch ($actionselected) {
		case 1:
			$videoInfoData->articleData->clear();
			foreach ($itemChosen as $i) {
				$id = $_POST['articleId'.$i];
				$title = $_POST['title'.$i];
				$text = $_POST['text'.$i];
				$category = $_POST['category'.$i];
				$quote = $_POST['quote'.$i];
				$item = new articleDataClass(null,$id,$title,$text,$category);
				$item->quote = $quote;
				$videoInfoData->articleData->push($item);
				$videoInfoData->quoteTitle = $quote;
				if ($videoInfoData->title=='') $videoInfoData->title = $title;
				break; // article should be only one
			}
			$videoInfoData->articleChanged = true; // to force linking
			break;
		case 2:
			if ($neworadd)
				$videoInfoData->imageData->clear();
			foreach ($itemChosen as $i) {
				$id = $_POST['imageId'.$i];
				$title = $_POST['title'.$i];
				$file = $_POST['file'.$i];
				$item = new imageDataClass(null,$id,$title,$file);
				$item->loadData($id);
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
				$item->loadData($id);
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
			if (!$videoInfoData->getOpenVideo()) {
				$item = new videoDataClass(null,$id,$title,$file,1);
				$item->loadData($id);
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
			if (!$videoInfoData->getEndVideo()) {
				$item = new videoDataClass(null,$id,$title,$file,2);
				$item->loadData($id);
				$videoInfoData->videoData->push($item);
				$videoInfoData->videoChanged = true;
			} else // update video item
				$videoInfoData->videoData->current()->setData($id,$title,$file);
			break;
		}
	}
} else {
	if (isset($_GET['mi'])) {
		$vID = $_GET['mi'];
		$videoInfoData = new videoProdDataClass($vID);
		if ($videoInfoData->articleData->count())
			$reuseArticle = $videoInfoData->articleData->current()->reuse;
	} else
		$videoInfoData = new videoProdDataClass();
}

function rearangeFiles(&$file_post) {
    $file_ary = array();
    $file_count = count($file_post['name']);
    $file_keys = array_keys($file_post);
    for ($i=0; $i<$file_count; $i++) {
        foreach ($file_keys as $key)
            $file_ary[$i][$key] = $file_post[$key][$i];
    }

    return $file_ary;
}

function getInfoFields(&$videoInfoData) {
	$videoInfoData->title = $_POST['title'];
	// sanitize filename
	$filename = sanitizeFileName($_POST['fileName']);
	if ($videoInfoData->videoFileName!==$filename) {
		$videoInfoData->deleteFile(); // delete file if changing file name
		$videoInfoData->videoFileName = $filename;
	}
	$videoInfoData->category = $_POST['category'];
	$videoInfoData->genre = $_POST['genre'];
	if ($videoInfoData->videoFileName=="")
		$videoInfoData->videoFileName = sanitizeFileName($videoInfoData->title);
	$videoInfoData->category = $_POST['category'];
	$videoInfoData->articleChanged = $_POST['articleChanged'];
	$videoInfoData->imageChanged = $_POST['imageChanged'];
	$videoInfoData->musicChanged = $_POST['musicChanged'];
	$videoInfoData->videoChanged = $_POST['videoChanged'];
	$videoInfoData->useTextFlow = $_POST['showText'];
	$videoInfoData->fontSize = $_POST['fontSize'];
	$videoInfoData->scrollSpeed = $_POST['sspeed'];
	$videoInfoData->useVoice = $_POST['voice'];
	$videoInfoData->voiceVolume = $_POST['volume'];
	$videoInfoData->voiceSpeed = $_POST['speed'];
	$videoInfoData->voicePitch = $_POST['pitch'];
	$videoInfoData->voiceRange = $_POST['range'];
	$videoInfoData->musicVolume = $_POST['mvolume'];
	$videoInfoData->fps = $_POST['fps'];
	$videoInfoData->quoteTitle = $_POST['quoteTitle'];
	$videoInfoData->annotation = $_POST['annotxt'];
	$videoInfoData->annoStart = $_POST['astart'];
	$videoInfoData->annoEnd = $_POST['aend'];
	$videoInfoData->desc = isset($_POST['desc'])?$_POST['desc']:'';
	$videoInfoData->tags = isset($_POST['tags'])?$_POST['tags']:'';
}

function deleteVideoProd($resId) {
	echo "deleting ".$resId."<br><br>\n";
	$videoInfoData = new videoProdDataClass($resId);
	if (!$videoInfoData->delete())
		echo "エラー: $resIdを削除できません！";
}

function getUsageQuota($userID,&$maxN,&$cu) {
	$sql = "select permission,maxProdNum,usage_count from users u left join user_level l on u.permission=user_level_id left outer join monthly_usage m on u.user_id=m.user_id and year_idx=YEAR(now()) and month_idx=MONTH(now()) where u.user_id=".$userID;
	$result = getDB($sql);
	if (count($result)) {
		$rec = $result[0];
		$maxN = $rec['maxProdNum'];
		$cu = $rec['usage_count']?$rec['usage_count']:0;
		return $maxN - $cu;
	} else
		$maxN = 0;
		$cu = 0;
		return 0;
}
?>
<!DOCTYPE html>
<html lang="ja">
<head>
	<meta Content-Type: text/html; charset=UTF-8 />
	<title>個別動画制作管理</title>
	<link rel="stylesheet" href="style.css">
	<link rel="stylesheet" href="css/pb-style.css">
	<link rel="stylesheet" href="../js/jquery-ui.css">
	<link rel="stylesheet" href="../js/jquery-ui-slider-pips.css">
	<link rel="stylesheet" href="../js/nanoscroller.css">
	<link rel="stylesheet" type="text/css" href="../js/css/jQuery.mb.miniAudioPlayer.min.css" title="style" media="screen"/>
	<script src='//ajax.googleapis.com/ajax/libs/jquery/1.10.2/jquery.min.js'></script>
	<script src="//code.angularjs.org/1.4.5/angular.min.js"></script>
	<script type='text/javascript' src='../js/menu_app.js'></script>
	<script type='text/javascript' src="../js/jquery.bpopup.min.js"></script>
	<script type='text/javascript' src="../js/jquery-ui.js"></script>
	<script type='text/javascript' src='../js/jquery-ui-slider-pips.js'></script>
  	<script type='text/javascript' src='../js/rangyinputs-jquery.js'></script>
  	<script type='text/javascript' src="../js/jquery.nanoscroller.min.js"></script>
	<script type="text/javascript" src="../js/jQuery.mb.miniAudioPlayer.min.js"></script>
	<script type='text/javascript' src='../js/jQuery.download.js'></script>
	<script type='text/javascript' src='../js/fhconvert.js'></script>
<style>
#filedrop {
	padding: 1em 0;
	margin: 1em 0;
	color: #555;
	border: 2px dashed #555;
	border-radius: 7px;
	cursor: default;
}

#filedrop.hover {
	color: #aaa;
	border: 2px;
	border-color: #f00;
	border-style: solid;
	box-shadow: inset 0 3px 4px #888;
	background-color:rgba(255,255,255,0.3);
}
#filedrop p { margin: 10px; font-size: 14px; }
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
<input type="hidden" name="mi" value="<?=$videoInfoData->id?>">
<input type="hidden" name="articleChanged" value="<?=$videoInfoData->articleChanged?>">
<input type="hidden" id="imageChanged" name="imageChanged" value="<?=$videoInfoData->imageChanged?>">
<input type="hidden" name="musicChanged" value="<?=$videoInfoData->musicChanged?>">
<input type="hidden" name="videoChanged" value="<?=$videoInfoData->videoChanged?>">
<table class="tube">
<tr style="text-align:left;">
<td style="padding-left:15px;width:320px;">タイトル</td><td style="width:200px;">ファイル名</td><td>カテゴリ <span style="margin-left:100px;">ジャンル</span></td></tr>
<tr id="titleApp" ng-app="titleApp" ng-controller="fileCtrl">
<td><input id="title" class="titleinput" style="width:310px;" ng-model="title" ng-change="update()" name="title" type="text"></td>
<td>
<input type="text" id="file" ng-model="fileName" ng-change="update()" name="fileName" style="width:190px;">
</td>
<td>
<select id="category" name="category">
<?php getCategoryList($videoInfoData->category); ?>
</select>
<select id="genre" name="genre">
<?php getGenreList($videoInfoData->genre); ?>
</select>
</td>
</tr>
<tr><td></td><td></td></tr>
<tr><td colspan=4><hr></td></tr>
<tr><td colspan=4><div class="style_01" id="part1">記事素材</div></td></tr>
<tr><td colspan=4>
<input type="button" class="showhide" id="showArtSearch" value="記事を探す">
<table class="tubecell" id="articleSearch" style="display:none;"><tr>
<td>カテゴリから探す
<select id="articleCat" name="articleCat">
<?php getCategoryList($videoInfoData->category); ?>
</select>
ジャンル
<select id="articleGen" name="articleGen">
<?php getGenreList($videoInfoData->genre); ?>
</select>
<input class="searchbuttonclass" id="searchArticleBtn" type="button" name="send" value="選択して記事を探す"></td></tr>
</table>
</td></tr>
<tr><td colspan=2><textarea id="articleText" name="articleText"><?=htmlspecialchars($videoInfoData->getArticle())?></textarea><br>
<div class="nlines" id="nChars"></div>
<input type="hidden" name="articleHtml" value="<?=$videoInfoData->articleData->encode()?>">
</td>
<td>
テキストの表示位置
<select id="showText" name="showText">
<option value="0" <?=($videoInfoData->useTextFlow==0)?"selected":""?>>無し</option>
<option value="1" <?=($videoInfoData->useTextFlow==1)?"selected":""?>>下</option>
<option value="2" <?=($videoInfoData->useTextFlow==2)?"selected":""?>>中</option>
<option value="3" <?=($videoInfoData->useTextFlow==3)?"selected":""?>>上</option>
<option value="4" <?=($videoInfoData->useTextFlow==4)?"selected":""?>>上から1/6</option>
<option value="5" <?=($videoInfoData->useTextFlow==5)?"selected":""?>>上から1/3</option>
<option value="6" <?=($videoInfoData->useTextFlow==6)?"selected":""?>>下から1/3</option>
<option value="7" <?=($videoInfoData->useTextFlow==7)?"selected":""?>>下から6/1</option>
<option value="8" <?=($videoInfoData->useTextFlow==8)?"selected":""?>>縦スクロール</option>
</select><br>
 フォントサイズ
<select id="fontSize" name="fontSize">
<option value="42" <?=($videoInfoData->fontSize==42)?"selected":""?>>42</option>
<option value="50" <?=($videoInfoData->fontSize==50)?"selected":""?>>50</option>
<option value="60" <?=($videoInfoData->fontSize==60)?"selected":""?>>60</option>
<option value="70" <?=($videoInfoData->fontSize==70)?"selected":""?>>70</option>
<option value="80" <?=($videoInfoData->fontSize==80)?"selected":""?>>80</option>
<option value="100" <?=($videoInfoData->fontSize==100)?"selected":""?>>100</option>
</select><br>
<table>
<tr><td style="width:60px;">
<font style="font-size:11px;">スクロール 速度調整：</font><input type="text" class="sliderValue" id="sspeed" name="sspeed" value="<?=$videoInfoData->scrollSpeed?>"></td>
<td style="width:150px;height:40px;"><div id="ss_slider"></div></td></tr>
</table><br>
音声
<select id="voice" name="voice">
<option value="0" <?=($videoInfoData->useVoice==0)?"selected":""?>>読み上げ無し</option>
<option value="1" <?=($videoInfoData->useVoice==1)?"selected":""?>>せいじ</option>
<option value="2" <?=($videoInfoData->useVoice==2)?"selected":""?>>おさむ</option>
<option value="3" <?=($videoInfoData->useVoice==3)?"selected":""?>>のぞみ</option>
<option value="4" <?=($videoInfoData->useVoice==4)?"selected":""?>>すみれ</option>
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
<div style="text-align:center;">
<input class="btnPlay" id="btnPlay" type="button">
<input type="button" class="btnStop" id="btnStop"><br>
<input type="checkbox" id="playOnlySelected"><label for='playOnlySelected'><span></span></label><span style="font-size:10px;">選択されたテキストだけを再生</span>
<div style='font-size:8px;color:gray' id='ptime'></div>
</div>
</td>
</tr>
<tr><td><!--<div class="nlines" id="nChars"></div>--></td><td></td></tr>
<tr>
<td colspan=2>
	<table class="adjuster">
	<tr><td>
	<select id="pvoice" name="pvoice">
	<option value="seiji">せいじ</option>
	<option value="osamu">おさむ</option>
	<option value="nozomi">のぞみ</option>
	<option value="sumire">すみれ</option>
	</select>
	<input type="button" class="voiceBtn" id="changeVoice" value="音声変更" title="部分的に使用したい音声、文章を選択して、このボタンをクリックしてください"> &nbsp;
	<input type="button" class="emphBtn" id="addEmphasis1" value="強調1" title="選択されたテキストを強調する">
	<input type="button" class="emphBtn" id="addEmphasis2" value="強調2" title="選択されたテキストをさらに強調する"> &nbsp;
	<input type="button" class="upBtn" id="addUp1" value="上げる1" value="強調1" title="選択されたテキストのピッチを上げる">
	<input type="button" class="upBtn" id="addUp2" value="上げる2" title="選択されたテキストのピッチをもっと上げる"> &nbsp;
	<input type="button" class="downBtn" id="addDown1" value="下げる1" title="選択されたテキストのピッチを下げる">
	<input type="button" class="downBtn" id="addDown2" value="下げる2" title="選択されたテキストのピッチをもっと下げる"><br>
	<input type="button" class="upBtn" id="addSpdUp1" value="速く1" title="選択されたテキストの速度を上げる">
	<input type="button" class="upBtn" id="addSpdUp2" value="速く2" title="選択されたテキストの速度をもっと上げる"> &nbsp;
	<input type="button" class="downBtn" id="addSpdDn1" value="遅く1" title="選択されたテキストの速度を下げる">
	<input type="button" class="downBtn" id="addSpdDn2" value="遅く2" title="選択されたテキストの速度をもっと下げる"> &nbsp;
	<input type="button" class="breakBtn" id="addBreak1" value="ポーズ1" title="カーソルの所に１秒ポーズを入れる">
	<input type="button" class="breakBtn" id="addBreak2" value="ポーズ2" title="カーソルの所に1.5秒ポーズを入れる"> &nbsp;
	<input type="button" class="characterBtn" id="addCharacter" value="文字として読む" title="アルファベットなどを文字として読む"><br>
	<input type="button" class="phonemeBtn" id="addPronunciation" value="読み方" title="選択されたテキストの読み方を指定する">
	<input type="text" class="" id="phmtxt" size=30 placeholder="読み方（カナ）"> &nbsp;
	<input type="button" class="undoBtn" id="undo" value="戻す">
	</td></tr>
	</table>
</td>
<td>
引用元 <input type="text" id="quoteTitle" name="quoteTitle" size=40 value="<?=$videoInfoData->quoteTitle?>"><br><br>
<input type="checkbox" id="reuseArticle" name="reuseArticle"<?=$reuseArticle?" checked":""?>><label for='reuseArticle'><span></span></label>記事を再利用のために保存する
<input type="hidden" id="txtSave"></td>
</tr>
<tr><td colspan=2>
<div id="voicePlayerArea" style="display:none; margin:2px; width:500px;">
<audio id="voicePlayer" controls class="" style="width:600px;">
<source src="http://sinobi2.bitnamiapp.com/ts/v/default.mp3" type="audio/mp3">
<p>音声を再生するには、audioタグをサポートしたブラウザが必要です。</p>
</audio>
</div>
</td>
</tr>
<tr><td colspan=2>
</td><td></td></tr>

<tr><td colspan=4><hr></td></tr>
<tr><td colspan=4><div class="style_01" id="part2">画像素材</div></td></tr>
<tr><td colspan=4>
</td></tr>
<tr><td colspan=4>
<table class="tubecell"><tr><td style="padding:4px;">
<div id="imageDisplay"><span style='color:gray;margin-left:10px;'>画像が選択されてません</span></div>
</td></tr></table>

<!-- drag & drop用 -->
<div id="filedrop" style="text-align:center;margin:0 16px 0 4px;">
<p id="filereader"></p>
<p id="formdata"><input type="file" multiple="multiple" accept=".jpg,.png,image/jpeg,image/png" name="imageFile[]" id="imageFile"> &nbsp;
<input type="submit" class="hidden" id="upload" name="send" value="upload">
選択した画像をアップロード</p>
<p id="progress"></p>
ここに画像ファイルをドラッグしてください &nbsp;
<progress id="uploadprogress" min="0" max="100" value="0">0</progress>
</div>

<input type="button" class="showhide" id="showImgSearch" value="画像を探す">
<input type="hidden" id="showImgSearchState" value="0">
<table class="tubecell" id="imageSearch" style="display:none;"><tr>
<td>カテゴリから探す
<select id="imageCat" name="imageCat">
<?php getCategoryList($videoInfoData->category); ?>
</select>
</td>
<td>ジャンル
<select id="imageGen" name="imageGen">
<?php getGenreList($videoInfoData->genre); ?>
</select>
</td>
<td><input class="searchbuttonclass" id="searchImageBtn" type="button" name="send" value="選択して画像を探す"></td></tr>
</table>
<input type="hidden" id="imageData" name="imageHtml" value="<?=$videoInfoData->imageData->encode()?>">
</td></tr>

<tr><td colspan=4><hr></td></tr>
<tr><td colspan=4><div class="style_01" id="part3">音楽素材</div></td></tr>
<tr><td colspan=4>
<table class="tubecell"><tr><td style="padding:4px;">
<div style="margin-left:10px;">
<?php
if ($videoInfoData->getMusic()) {
	$musicTitle = $videoInfoData->getMusic()->title;
	$music = $videoInfoData->getMusic()->getFilePath();
	$mLength = $videoInfoData->getMusic()->duration;
	echo $musicTitle;
	$html = <<<EOD
 &nbsp;
<a id="m3" class="audio {skin:'mvpSkin', autoPlay:false, inLine:true}" href="$music">$music</a> &nbsp; $mLength sec
EOD;
	echo $html;
} else
	echo "<span style='color:gray;'>音楽が選択されてません</span>";
?>
</div><input type="hidden" id="musicData" name="musicHtml" value="<?=$videoInfoData->musicData->encode()?>"></td>
<td style="width:130px;">BGM Mix音量：<input type="text" class="sliderValue" id="mvolume" name="mvolume" value="<?=$videoInfoData->musicVolume?>"></td>
<td style="width:190px;height:40px;"><div id="mv_slider"></div></td></tr></table>
</td></tr>
<tr><td colspan=4>
<input type="button" class="showhide" id="showMusicSearch" value="音楽を探す">
<table class="tubecell" id="musicSearch" style="display:none;">
<tr><td colspan=2 style="font-size:10px;text-align:left;">
<?php
	$sql = "select * from tags";
	$tags = getDB($sql);
	$i = 0;
	foreach ($tags as $tag) {
		$id = $tag['tag_id'];
		$name = $tag['tag'];
		echo '<input type="checkbox" id="t'.$id.'" class="ts" name="tags[]" value="'
			.$id.'"><label for="t'.$id.'"><span></span></label>'.$name;
		echo "<input type='hidden' name='tag$i' value='".$name."'>";
		$i++;
	}
?>
</td>
<td>
<input class="searchbuttonclass" id="searchMusicBtn" type="button" name="send" value="音楽を探す"></td>
</tr></table>
</td></tr>
<tr><td colspan=3><hr></td></tr>
<tr><td colspan=3><div class="style_01" id="part3">オープニング ＆ エンディング動画</div></td></tr>
<tr><td>オープニング <input class="searchbuttonclass" id="searchCMVideoBtn" type="button" name="send" value="Op動画を探す"></td>
<td colspan=2 style="padding-left:100px;">エンディング動画 <input class="searchbuttonclass" id="searchEndVideoBtn" type="button" name="send" value="End動画を探す"></td></tr>
<tr><td colspan=3>
<table class="tubecell"><tr><td style="padding:4px;width:50%;">
<?php if ($videoInfoData->getOpenVideo()) { ?>
<div style="height:140px;">
<video id="cmVideo" style="width:240px;" controls>
    <source src="<?=$videoInfoData->getOpenVideo()?$videoInfoData->getOpenVideo()->getFilePath():''?>">
</video>
<input id="deleteCMVideoBtn" class="delVideoBtn" type="submit" name="send" value="Op動画を削除">
</div>
<?php } else {
	echo "<span style='color:gray;margin-left:10px;'>動画が選択されてません</span>";
} ?>
</td>
<td>
<?php if ($videoInfoData->getEndVideo()) { ?>
<div>
<video id="enVideo" style="width:240px;" controls>
	<source src="<?=$videoInfoData->getEndVideo()?$videoInfoData->getEndVideo()->getFilePath():''?>">
</video>
<input id="deleteEndVideoBtn" class="delVideoBtn" type="submit" name="send" value="End動画を削除">
</div>
<?php } else {
	echo "<span style='color:gray;margin-left:10px;'>動画が選択されてません</span>";
} ?>
</table>
<input type="hidden" id="videoData" name="videoHtml" value="<?=$videoInfoData->videoData->encode()?>">
</td></tr>
<tr><td colspan=4><hr></td></tr>
<!--
<tr><td colspan=3><div class="style_01" id="part3">動画アップロード情報</div></td></tr>
<tr><td>
<input type="button" class="showhide" id="showUploadInfo" value="アップロード情報"></td></tr>
<tr><td colspan=4>
<table class="tubecell" id="uploadInfo" style="display:none;">
<tr><td>説明 <textarea id="descText" name="desc"><?=htmlspecialchars($videoInfoData->desc)?></textarea></td>
<tr><td>タグ
<textarea class='tagarea' id='tagEdit' style="height:30px;" name='tags'><?=htmlspecialchars($videoInfoData->tags)?></textarea>
</td></tr>
</table>
</td></tr>
<tr><td colspan=4><hr></td></tr>
-->
<tr><td colspan=3><div class="style_01" id="part4">アノテーション</div></td></tr>
<tr><td>
<textarea id="annotxt" name="annotxt"><?=htmlspecialchars($videoInfoData->annotation)?></textarea>
</td>
<td>開始時間（秒）<input type="text" id="astart" name="astart" size=5 value="<?=$videoInfoData->annoStart?>"></td>
<td>終了時間（秒）<input type="text" id="aend" name="aend" size=5 value="<?=$videoInfoData->annoEnd?>"></td>
</tr>
<tr><td colspan=3><hr></td></tr>
<tr><td>動画FPS
<select id="fps" name="fps">
<option value="30" <?php if ($videoInfoData->fps==30) echo "selected";?>>30 fps</option>
<option value="60" <?php if ($videoInfoData->fps==60) echo "selected";?>>60 fps</option>
</select>
</td>
<td></td>
<td style="text-align:right;">
<a href="<?=$selfName?>"><input class="buttonclass" type="button" value="新規"></a>  &nbsp; &nbsp; &nbsp;
<input class="button" type="submit" name="send" value="保存">
</td></tr>
<tr><td colspan=3> &nbsp; </td></tr>
<tr><td colspan=3><hr></td></tr>
<tr><td colspan=2><div class="style_01" id="part3">動画制作管理</div></td><td style="text-align:right; color:gray;">今月の使用状況：制作本数 <?=$CUsage?> &nbsp; 残り <?=$numLeft?></td></tr>
<tr><td colspan=3> </td></tr>
</table>
<table>
<tr>
<td><div id="errorMessage" style="color:blue;" /></td>
<td style="border:0;text-align:right;">
ダウンロードされた動画をすべて<input class="buttonclass" type="button" value="選択" onclick="selectAllDldedItems()"> &nbsp;
<input class="buttonclass" type="submit" name="send" value="削除"> &nbsp; &nbsp; &nbsp; &nbsp; &nbsp;
未制作の動画すべて<input class="buttonclass" type="button" value="選択" onclick="selectAllUnprodItems()"> &nbsp; &nbsp;
動画制作<input class="startBtn" id="startBtn" type="submit" name="send" value="制作"></td>
</tr>
</table>
<div id="videoList"><p style="text-align:center;"><img src="img/ajax-loader.gif"></p></div>
</form>

<div id="popup2">
<form id="selectform" method="POST">
<br>
<div class="selectButtons" style="text-align:left;margin-left:8px;font-size:14px;">
<div class="triangle">使用したい素材を選択してください。</div>
<input type="submit" class="green small button" name="send" value="選択"> &nbsp;
<div class="neworad">
<input class="neworadd" type="radio" name="neworadd" value="0" checked>追加
<input class="neworadd" type="radio" name="neworadd" value="1">新規
</div></div>
<span class="cbutton b-close"><span><img src="img/dialog_close.png"></span></span>
<div class="nano" id="content2">
<div class="nano-content" id="scrollarea">
</div></div>
<input type="hidden" name="videoInfoData" value="<?=itemData::compress($videoInfoData)?>">
<input type="hidden" class="actionselected" name="actionselected" value="<?=$actionselected?>">
<input type="hidden" id="atitle" name="atitle">
<input type="hidden" id="afile" name="afile">
<input type="hidden" id="aarticleText" name="aarticleText">
<input type="hidden" id="acategory" name="acategory">
<input type="hidden" id="agenre" name="agenre">
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
<input type="hidden" id="asaveArticle" name="asaveArticle">
<input type="hidden" id="adesc" name="adesc">
<input type="hidden" id="atags" name="atags">
<input type="hidden" id="aimageData" name="aimageData">
<!-- <input type="hidden" id="amusicData" name="amusicData">
<input type="hidden" id="avideoData" name="avideoData"> -->
</form>
</div><!--end popup2-->

<div id="popup">
<span class="cbutton b-close"><span><img src="img/dialog_close.png"></span></span>
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
// Variable to store your files
var files;

// Determine the correct object to use
var notification = window.Notification || window.mozNotification || window.webkitNotification;

// The user needs to allow this
if ('undefined' === typeof notification)
    alert('Web notification not supported');
else
    notification.requestPermission(function(permission){});

// A function handler
function Notify(titleText, bodyText)
{
    if ('undefined' === typeof notification)
        return false;       //Not supported....
    var noty = new notification(
        titleText, {
            body: bodyText,
            dir: 'auto', // or ltr, rtl
            lang: 'EN', //lang used within the notification.
            tag: 'notificationPopup', //An element ID to get/set the content
            icon: '' //The URL of an image to be used as an icon
        }
    );
    noty.onclick = function () {
        console.log('notification.Click');
    };
    noty.onerror = function () {
        console.log('notification.Error');
    };
    noty.onshow = function () {
        console.log('notification.Show');
    };
    noty.onclose = function () {
        console.log('notification.Close');
    };
    return true;
}

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

	updateImages('update', 'undefined');
	updateVideoList();

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

	/* Voice Processing Functions */
	$('#btnPlay').click(function() {
		if ($('#playOnlySelected').prop('checked')) {
			var sel = $("#articleText").getSelection();
			var voiceText = sel.text;
			$("#articleText").setSelection(sel.start, sel.end);
		} else
			var voiceText = $("#articleText").val();
		voiceText = voiceText.replace('/\s+/g', "");
		voiceText = voiceText.replace('&', '＆');
		if(voiceText == ""){
			alert('合成するテキストを入力してください。');
		} else if (voiceText.length>2000)
			alert('合成するテキストは1000文字以下にしてください。');
		else {
			change2Processing();
			var voice = 'nozomi';
			switch(parseInt($("#voice").val())) {
			case 1:
				voice = 'seiji';
				break;
			case 2:
				voice = 'osamu';
				break;
			case 3:
				voice = 'nozomi';
				break;
			case 4:
				voice = 'sumire';
				break;
			}
			//alert($("#voice").val()+"  "+voice);
			$.ajax({
			  type:'POST',
			  url:serverUrl + '/ts/ytmmsp.php',
			  data: {
				'ui':'<?=$userID?>',
				'tc':'<?=$tcode?>',
				'voice':voice,
				'tx':voiceText,
				'volume':$("#volume").val(),
				'speed':$("#speed").val(),
				'pitch':$("#pitch").val(),
				'range':$("#range").val(),
				'af':1
			  },
			  dataType:'jsonp',
			  jsonpCallback:'callback',
			  success: function(result) {
			  	var url = serverUrl + '/ts/' + result['url'];
			  	if (result['vstatus']!='success') {
			  		$('#ptime').html('<font style="font-size:12px;color:red;">合成失敗：'
			  				+getErrorCode(result['errorCode'])+'</font>');
			  		change2ProcReady();
			  	} else {
					$('#dlUrl').val(url);
					play(url);
					var file = result['url'].match(/\/(\w+\.\w{3})/);
					$('#dlFile').val(file);
					$('#ptime').html('Voice process time: '+result['ptime']);
					$('#downloadVoice').attr({target: '_blank',
										download: file[1],
										href: url});
					$('.downloadBtn').removeClass('hideClass');
				}
			  },
			  error: function(request, textStatus, errorThrown) {
//			  	alert('エラー：サーバーが正しく反応してません');
				alert(textStatus+'\n'+errorThrown+'\n'
					+JSON.stringify(request, null, 4));
			  }
			});
		}
	});

	$('#btnStop').click(function() {
		stop();
	});

	function play(url) {
	  change2Playing();

	  if (playerType == 'jPlayer') {
		$('#player_info').html('jPlayer');
		$('#player').jPlayer("setMedia", {
			mp3: url
		});
		$("#player").jPlayer("play");
	  } else {
		$('#player_info').html('HTML5 audio');
		if (audio != null && audio.canPlayType) {
			audio.src = url;
			if(smpflag) change2PlayReady();
			else {
				audio.play();
				audio.addEventListener("ended", function(){
					change2PlayReady();
				}, false);
			}
		}
	  }
	}

	function stop() {
	  change2PlayReady();
	  if (playerType == 'jPlayer') {
		$("#player").jPlayer("stop");
	  } else {
		if (audio != null) {
		  audio.pause();
		}
	  }
	}

	//再生中→合成
	function change2PlayReady(){
		$('#btnPlay').switchClass('btnPlaying', 'btnPlay');
	}

	//合成→合成中
	function change2Processing(){
		$('#btnPlay').switchClass('btnPlay', 'btnProcessing');
	}

	//合成中→再生中
	function change2Playing(){
		//合成ボタン非表示・再生中
		$('#btnPlay').switchClass('btnProcessing', 'btnPlaying');
	}

	//再生中→合成
	function change2ProcReady(){
		$('#btnPlay').switchClass('btnProcessing', 'btnPlay');
	}

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
    $("#ss_slider").slider("value", <?=$videoInfoData->scrollSpeed?>*10);
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
    $("#v_slider").slider("value", <?=$videoInfoData->voiceVolume?>*10);
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
    $("#s_slider").slider("value", <?=$videoInfoData->voiceSpeed?>*10);
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
	$("#p_slider").slider("value", <?=$videoInfoData->voicePitch?>*10);
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
    $("#r_slider").slider("value", <?=$videoInfoData->voiceRange?>*10);
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
    $("#mv_slider").slider("value", <?=$videoInfoData->musicVolume?>*10);
    $("#mvolume").val(($("#mv_slider").slider("value")/10).toPrecision(2));
    $("#mvolume").change(function(){
    	var tmp = $(this).val();
    	if (tmp>2) tmp = 2;
    	else if (tmp<0) tmp = 0;
    	$("#mv_slider").slider("value", tmp*10);
    	$(this).val(tmp);
    });


	$('#addBreak1').click(function(){
		$('#txtSave').val($('#articleText').val());
		$('#articleText').surroundSelectedText('','<break time="1000ms" />');
		tagAdded = true;
	});
	$('#addBreak2').click(function(){
		$('#txtSave').val($('#articleText').val());
		$('#articleText').surroundSelectedText('','<break time="1500ms" />');
		tagAdded = true;
	});

	$('#addUp1').click(function(){
		$('#txtSave').val($('#articleText').val());
		$('#articleText').surroundSelectedText('<prosody pitch="1.1">','</prosody>');
		tagAdded = true;
	});
	$('#addUp2').click(function(){
		$('#txtSave').val($('#articleText').val());
		$('#articleText').surroundSelectedText('<prosody pitch="1.2">','</prosody>');
		tagAdded = true;
	});

	$('#addDown1').click(function(){
		$('#txtSave').val($('#articleText').val());
		$('#articleText').surroundSelectedText('<prosody pitch="0.9">','</prosody>');
		tagAdded = true;
	});
	$('#addDown2').click(function(){
		$('#txtSave').val($('#articleText').val());
		$('#articleText').surroundSelectedText('<prosody pitch="0.8">','</prosody>');
		tagAdded = true;
	});

	$('#addSpdUp1').click(function(){
		$('#txtSave').val($('#articleText').val());
		$('#articleText').surroundSelectedText('<prosody rate="1.2">','</prosody>');
		tagAdded = true;
	});
	$('#addSpdUp2').click(function(){
		$('#txtSave').val($('#articleText').val());
		$('#articleText').surroundSelectedText('<prosody rate="1.5">','</prosody>');
		tagAdded = true;
	});

	$('#addSpdDn1').click(function(){
		$('#txtSave').val($('#articleText').val());
		$('#articleText').surroundSelectedText('<prosody rate="0.8">','</prosody>');
		tagAdded = true;
	});
	$('#addSpdDn2').click(function(){
		$('#txtSave').val($('#articleText').val());
		$('#articleText').surroundSelectedText('<prosody rate="0.5">','</prosody>');
		tagAdded = true;
	});

	$('#addEmphasis1').click(function(){
		$('#txtSave').val($('#articleText').val());
		$('#articleText').surroundSelectedText('<prosody volume="1.3" pitch="1.1" rate="1.1">','</prosody>');
		tagAdded = true;
	});
	$('#addEmphasis2').click(function(){
		$('#txtSave').val($('#articleText').val());
		$('#articleText').surroundSelectedText('<prosody volume="1.4" pitch="1.2" rate="1.2">','</prosody>');
		tagAdded = true;
	});

	$('#changeVoice').click(function(){
		$('#txtSave').val($('#articleText').val());
		$('#articleText').surroundSelectedText('<voice name="'
			+$('#pvoice').val()+'">','</voice>');
		tagAdded = true;
	});

	$('#addCharacter').click(function(){
		$('#txtSave').val($('#articleText').val());
		$('#articleText').surroundSelectedText('<say-as interpret-as="characters">','</say-as>');
		tagAdded = true;
	});

	$('#addDate').click(function(){
		$('#txtSave').val($('#articleText').val());
		$('#articleText').surroundSelectedText('<say-as interpret-as="date" format="ym">','</say-as>');
		tagAdded = true;
	});

	$('#addTime').click(function(){
		$('#txtSave').val($('#articleText').val());
		$('#articleText').surroundSelectedText('<say-as interpret-as="time">','</say-as>');
		tagAdded = true;
	});

	$('#addPhone').click(function(){
		$('#txtSave').val($('#articleText').val());
		$('#articleText').surroundSelectedText('<say-as interpret-as="telephone">','</say-as>');
		tagAdded = true;
	});

	$('#addPronunciation').click(function(){
		$('#txtSave').val($('#articleText').val());
		$('#articleText').surroundSelectedText('<phoneme ph="'
			+$('#phmtxt').val()+'">','</phoneme>');
		tagAdded = true;
	});

	$('#undo').click(function(){
		if (tagAdded) {
			$('#articleText').val($('#txtSave').val());
			tagAdded = false;
		}
	});

	$('#showAdjuster').click(function(){
		$('#adjuster').toggle();
		$('#adjusterp').toggle();
	});

    $( "#tabs" ).tabs();


/* Resource manipulation functions */
	$('#searchArticleBtn').click(function() {
		$('.actionselected').val('1');
		var k = '';
		var c = $('#articleCat').val();
		var pt = 1;
		getArticles(k,c,pt);

		$('#popup2').bPopup({
			opacity: 0.6,
			positionStyle: 'fixed'
		});
	});

	$('#searchImageBtn').click(function() {
		$('.actionselected').val('2');
		var k = '';
		var c = $('#imageCat').val();
		var g = $('#imageGen').val();
		var pt = 1;
		var n = <?=$videoInfoData->imageData->count()?>;
		if (n>0) $('.neworadd').val(0);
		getImages(k,c,g,pt);

		$('#popup2').bPopup({
			opacity: 0.6,
			positionStyle: 'fixed',
		});
	});

	$('#searchMusicBtn').click(function() {
		$('.actionselected').val('3');
		var k = '';
		var c = 0;
		var g = 0;
		var t = [];
		$('.ts:checked').each(function() {
			t.push($(this).val());
		});

		var tags = t.join(", ");
		var pt = 1;
		getMusic(k,c,g,tags,pt);
		$('neworad').hide();
		$('#popup2').bPopup({
			opacity: 0.6,
			positionStyle: 'fixed',
			onClose: function(){ $('.audio').mb_miniPlayer_stop(); }
		});
	});

	$('#searchCMVideoBtn').click(function() {
		$('.actionselected').val('4');
		var k = '';
		var c = 0; //$('#category').val();
		var t = 1;
		var pt = 1;
		getVideo(k,c,t,pt);

		$('#popup2').bPopup({
			opacity: 0.6,
			positionStyle: 'fixed',
		});
	});

	$('#searchEndVideoBtn').click(function() {
		$('.actionselected').val('5');
		var k = '';
		var c = 0; //$('#category').val();
		var t = 2;
		var pt = 1;
		getVideo(k,c,t,pt);

		$('#popup2').bPopup({
			opacity: 0.6,
			positionStyle: 'fixed',
		});
	});
/*
	$('.dlBtn').click(function() {
		// set link url and click
		// update database & set downloaded flag
		var url = '<?=$rootUrl?>dlfile.php';
		var id = $(this).attr('id');
		var idx = id.substring(3);
//		var idx = $(this).attr('alt');
		alert("id="+idx);
//		var data = {id:idx, fn:$(this).attr('name'), g:<?=$userGroup?>};
		var data = {id:idx};
		$.download(url, data);
	});
	$('#updateBtn').click(function() {
		updateVideoList();
	});
*/
	if (<?=$videoInfoData->articleData->count()?0:1?>) $('#articleSearch').show();
	if (<?=$videoInfoData->imageData->count()?0:1?>) $('#imageSearch').show();
	if (<?=$videoInfoData->getMusic()?0:1?>) $('#musicSearch').show();

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

	bindButtonClick();

	$('#fps').change(function() {
		if ($(this).val()=='60')
			alert('60fps動画はサイズ、制作時間ともに倍になるので、２動画としてカウントされます');
	});

	window.setInterval("setCharNum()", 1000);
	window.setInterval("updateVideoList()", 10*1000);
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

	$('.delImageBtn').click(function() {
		var id = $(this).attr("id");
		var i = id.substring(2);
		updateImages('dl'+i);
	});
}

function bindTooltip() {
	$('.playVideoBtn').click(function() {
		var v = "<video id='videoPlay' style='width:600px;height:340px;' controls autoplay><source src='"+$(this).attr('name')+"' type='video/mp4'></video>";
		$('#popcontent').html(v);
		var bPopup = $('#popup').bPopup({
			opacity: 0.6,
			onClose: function(){ $('#popcontent').html(''); },
			function() { $('#videoPlay').get(0).play(); }
		});
	});

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

function updateImages(cmd, fid) {
	if (cmd!="") $('#imageChanged').val(true);
	if (typeof fid!=='undefined')
		var data = {send:cmd, fi:fid, mItemData:$('#imageData').val()};
	else
		var data = {send:cmd, mItemData:$('#imageData').val()};
	$.ajax({
		type:'POST',
		url:'updateDisplayImages.php',
		data:data,
		dataType:'json',
		success: function(result) {
			$('#imageData').val(result['mitemdata']);
			if (result['exhtml'])
				$('#imageDisplay').html(result['exhtml']);
			else
				$('#imageDisplay').html("<span style='color:gray;margin-left:10px;'>画像が選択されてません</span>");
			bindButtonClick();
		},
		error: function(result) {
			alert('サーバーからの読み込み失敗: '+result['exhtml']);
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
			$('.neworad').hide();
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
		},
		error: ajaxError(data)
	});
}

function getMusic(key, cat, gen, tags, page) {
	$('#scrollarea').html(waitMessageHTML);
	var url = "getMusic.php";
	var data = {k:key, c:cat, g:gen, t:tags, u:<?=$userGroup?>, p:page};
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
		},
		error: ajaxError(data)
	});
}

function getVideo(key, cat, type, page) {
	$('#scrollarea').html(waitMessageHTML);
	var url = "getVideo.php";
	var data = {k:key, c:cat, t:type, u:<?=$userGroup?>, l:<?=$userLevel?>, p:page};
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
		},
		error: ajaxError(data)
	});
}

function ajaxError(data) {
	$('#scrollarea').html( '読み込み失敗' );
	$('#scrollarea').append(data);
	$('.selectButtons').hide();
}

function updateVideoList() {
	var url = 'updateVideoList.php';
	var dels = [];
	var prods = [];
	$('#videoList .delete:checked').each(function() {
		dels.push($(this).val());
	});
	$('#videoList .produce:checked').each(function() {
		prods.push($(this).val());
	});
	var data = {p:<?=$pageNo?>, sn:'<?=$selfName?>', dl:dels, pr:prods, ug:<?=$userGroup?>};
	$.ajax({
		url: url,
		data: data,
		dataType: 'text',
		success: function(data) {
			$('#videoList').html(data);
			$('.dlBtn').click(function() {
				var url = '<?=$rootUrl?>dlfile.php';
				//var data = {fn:$(this).attr('name'), g:<?=$userGroup?>};
				var data = {id:$(this).attr('alt')};
				$.download(url, data);
			});
			bindTooltip();
		},
		error: function(data) {
			$('#errorMessage').html( 'アップデート読み込み失敗' );
		}
	});
}


function saveFields() {
	$('#atitle').val($('#title').val());
	$('#aarticleText').val($('#articleText').val());
	$('#acategory').val($('#category').val());
	$('#agenre').val($('#genre').val());
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
	$('#aannotxt').val($('#annotxt').val());
	$('#aastart').val($('#astart').val());
	$('#aaend').val($('#aend').val());
	$('#afps').val($('#fps').val());
	$('#aquoteTitle').val($('#quoteTitle').val());
	$('#asaveArticle').val($('#saveArticle').val());
	$('#adesc').val($('#desc').val());
	$('#atags').val($('#tags').val());
	$('#aimageData').val($('#imageData').val());
//	$('#amusicData').val($('#musicData').val());
//	$('#avideoData').val($('#videoData').val());
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



function getErrorCode(code) {
	switch(code) {
	case 204: return '合成結果がありません。レスポンスボディのサイズが 0 となるような合成を実行しようとしています。';
	case 400: return 'リクエストボディ(入力テキスト)の SSML に誤りがあります。正しい SSML を設定してください。';
		break;
	case 500: return '指定の合成ジョブは実行できません。音声辞書を1つ以上ロードしてください。 または、日本語辞書の初期化を行ってください。';
		break;
	case 418: return '合成ジョブが予期せぬエラーで強制終了しました。';
		break;
	case 509: return '音声データのサイズが上限に到達したため、合成を中断しました。';
		break;
	default: return 'Unknown error '+code;
	}
}

var holder = document.getElementById('filedrop'),
    tests = {
      filereader: typeof FileReader != 'undefined',
      dnd: 'draggable' in document.createElement('span'),
      formdata: !!window.FormData,
      progress: "upload" in new XMLHttpRequest
    },
    support = {
      filereader: document.getElementById('filereader'),
      formdata: document.getElementById('formdata'),
      progress: document.getElementById('progress')
    },
    acceptedTypes = {
      'image/png': true,
      'image/jpeg': true,
      'image/gif': false
    },
    progress = document.getElementById('uploadprogress'),
    fileupload = document.getElementById('upload');

"filereader formdata progress".split(' ').forEach(function (api) {
  if (tests[api] === false) {
    support[api].className = 'fail';
  } else {
    // FFS. I could have done el.hidden = true, but IE doesn't support
    // hidden, so I tried to create a polyfill that would extend the
    // Element.prototype, but then IE10 doesn't even give me access
    // to the Element object. Brilliant!
    support[api].className = 'hidden';
  }
});

function readfiles(files) {
    var fd = tests.formdata ? new FormData() : null;
    for (var i = 0; i < files.length; i++) {
      if (tests.formdata) fd.append('file[]', files[i]);
    }
	fd.append("send","upload");
	fd.append("mItemData",$('#imageData').val());
	fd.append("ug",<?=$userGroup?>);
	fd.append("ct",$('#category').val());

    // now post a new XHR request
    if (tests.formdata) {
		var xhr = new XMLHttpRequest();
		xhr.open('POST', 'updateDisplayImages.php');
		xhr.onload = function() {
		  progress.value = progress.innerHTML = 100;
		};
	}

	if (tests.progress) {
        xhr.upload.onprogress = function (event) {
          if (event.lengthComputable) {
            var complete = (event.loaded / event.total * 100 | 0);
            progress.value = progress.innerHTML = complete;
          }
        }
	}

	xhr.onreadystatechange = function(event) {
		var xhr = event.target;
		if (xhr.readyState === 4 && xhr.status === 200) {
//			alert(xhr.responseText);
			var json = JSON.parse(xhr.responseText);
			$('#imageData').val(json['mitemdata']);
			$('#imageDisplay').html(json['exhtml']);
			$('#imageChanged').val(true);
			bindButtonClick();
		} else if (xhr.status!==0 && xhr.status!==200) {
			console.log('ERROR: '+xhr.status);
			alert('ERROR: '+xhr.readyState+'  '+xhr.status);
		}
	}
	xhr.send(fd);
}

if (tests.dnd) {
  holder.ondragover = function () { this.className = 'hover'; return false; };
  holder.ondragend = function () { this.className = ''; return false; };
  holder.ondrop = function (e) {
    this.className = '';
    e.preventDefault();
    readfiles(e.dataTransfer.files);
  }
} else {
  fileupload.className = 'hidden';
  fileupload.querySelector('input').onchange = function () {
    readfiles(this.files);
  };
}


var titleModule = angular.module('titleApp', []);
titleModule.controller('fileCtrl', function($scope) {
	$scope.title = "<?=$videoInfoData->title?>";
	$scope.fileName = "<?=$videoInfoData->videoFileName?>";

//	var convZenkaku = FHConvert._convert(value, param);
	$scope.update = function() {
		var tt = $scope.title.replace(/(\r\n|\n|\r)/gm,'');
		$scope.title = tt.trim();
		var fname = $scope.fileName.trim();
		if (fname=='') fname = tt;
		fname = fname.replace(/\s+/gm, '_');
//		var zenkaku = CheckMoji(fname,1);
//		if (zenkaku) {
//			$scope.fileName = convZenkaku(fname, {jaCode:true});
//		}
	}
});


/****************************************************************
* 全角/半角文字判定
*
* 引数 ： str チェックする文字列
* flg 0:半角文字、1:全角文字
* 戻り値： true:含まれている、false:含まれていない
*
****************************************************************/
function CheckMoji(str,flg) {
    for (var i = 0; i < str.length; i++) {
        var c = str.charCodeAt(i);
        // Shift_JIS: 0x0 ～ 0x80, 0xa0 , 0xa1 ～ 0xdf , 0xfd ～ 0xff
        // Unicode : 0x0 ～ 0x80, 0xf8f0, 0xff61 ～ 0xff9f, 0xf8f1 ～ 0xf8f3
        if ( (c >= 0x0 && c < 0x81) || (c == 0xf8f0) || (c >= 0xff61 && c < 0xffa0)
        		|| (c >= 0xf8f1 && c < 0xf8f4)) {
            if(!flg) return true;
        } else {
            if(flg) return true;
        }
    }
    return false;
}

angular.bootstrap(document.getElementById("titleApp"),['titleApp']);
</script>
</body>
</html>
