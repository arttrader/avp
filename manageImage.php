<?php
require_once 'AuthController.php';
require_once 'videoDataClass.php';
require_once 'php_image_magician.php';

$userLevel = 0;

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
$file = '';
$category = 0;
$genre = 0;
$keywords = '';
$share = false;
$imageObj = new imageDataClass(0,0,'','',$userGroup);
$imageObj->fileName = '';
$imageData = new mDataClass();
$imageChanged = false;

$items_per_page = 20;

if ($_POST) {
	$send = $_POST['send'];
	$title = $_POST['title'];
	$category = $_POST['category'];
	$genre = $_POST['genre'];
	$share = isset($_POST['share'])?true:false;
	$imageChanged = $_POST['imageChanged'];
	$imageData->decode($_POST['imageHtml']);
	if ($send==="変更") {
		$mi = $_POST['mi'];
		$imageObj->loadData($mi);
		$imageObj->title = $title;
		$imageObj->category = $category;
		$imageObj->genre =$genre;
		$imageObj->keywords = $keywords;
		$imageObj->saveData();
		echo "<br><br>画像データを変更しました";
	} else if ($send==="追加") {
//		echo "image data count = ".$imageData->count()."<br>";
		// need to check that category is also a shared one
		if ($share)
			if (isSharedCategory($category)) $imageObj->setGroupID(0);
			else echo "共有カテゴリではないので、共有設定は無効です！<br>";
		if ($imageData->count() && $title) {
			$n = $imageData->count();
			writeLog("updating $n images [".$title."], group id=".$userGroup);
			$imageData->rewind();
			for ($i=1; $i<=$n; $i++) {
				$imageData->current()->title = $title." ".$i;
				$imageData->current()->reuse = 1;
				$imageData->current()->category = $category;
				$imageData->current()->saveData();
				$imageData->next();
			}
		} else if (!empty($_FILES['file']) && $title) {
			$imageObj->upload($title,$_FILES['file'],$category,$genre,$keywords,1);
			$mi = $imageObj->imageId;
		} else 
			echo "<div class='font_red'>ファイル、タイトルが選択されてません</div><br>";
	} else if ($send==="削除") {
		if (isset($_POST['delete'])) {
			if (is_array($_POST['delete'])) {
				foreach($_POST['delete'] as $i) {
					$imageObj->imageId = $_POST['resId'.$i];
					$imageObj->delete();
				}
			} else {
				$i = $_POST['delete'];
				$imageObj->imageId = $_POST['resId'.$i];
				$imageObj->delete();
			}
		}
	}
} else {
	if (isset($_GET['mi'])) {
		$mi = $_GET['mi'];
		$imageObj->loadData($mi);
		$share = ($imageObj->getGroupID()==0)?true:false;
	}
}

function getNumList() {
	global $userGroup;
	$sql = "select count(*) n from image where reuse=1 and (group_id=0 or group_id=".$userGroup.")";
	$result = getDB($sql);
	return $result[0]['n'];
}

function getImageList($offset, $numItems) {
	global $userGroup;
    $sql = "select image_id,title,filename,category,genre,i.group_id,c.name cname,g.name gname from image i left join category c on i.category=c.category_id left join genre g on i.genre=g.genre_id where (i.group_id=0 or i.group_id=$userGroup) and reuse=1 order by image_id desc limit ".$offset.",".$numItems;
	$result = getDB($sql);
	return $result;
}
?>
<html lang="ja">
<head>
	<meta Content-Type: text/html; charset=UTF-8 />
	<link rel="stylesheet" href="style.css" type="text/css" />
	<link rel="stylesheet" href="css/pb-style.css">
	<link rel="stylesheet" href="http://code.jquery.com/ui/1.10.3/themes/smoothness/jquery-ui.css">
	<title>画像管理</title>
	<script src='//ajax.googleapis.com/ajax/libs/jquery/1.10.2/jquery.min.js'></script>
	<script src="//code.angularjs.org/1.4.5/angular.min.js"></script>
	<script type='text/javascript' src='../js/menu_app.js'></script>
	<script type='text/javascript' src="../js/jquery-ui.js"></script>
	<script type='text/javascript' src='../js/jQuery.download.js'></script>
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
<div class="subtitle">画像管理</div>
<span id="message"><?=$message?></span>

<div class="container_main">
<form class="select" method="POST" enctype="multipart/form-data">
<input type=hidden name="userid" value="<?=$userID?>" />

<table class="">
<tr style="text-align:left;">
<th>ファイル</th><th>タイトル</th><th>カテゴリー</th><th style="text-align:center;">ジャンル</th></tr>
<tr>
<td style="width:50px;">
<?php if ($imageObj->fileName)
	echo "<span style='font-size:12px;'>".$imageObj->fileName."</span>";
else { ?>
<input type="file" name="file" accept=".jpg,.png,image/jpeg,image/png" value="<?=$file?>"  onchange="readURL(this);">
<?php } ?>
</td>
<td><input id="title" name="title" type="text" size="40" value="<?=$imageObj->title?>" /></td>
<td style="text-align:center;">
<select name="category">
<?php getCategoryList($imageObj->category); ?>
</select>
</td>
<td style="text-align:center;">
<select name="genre">
<?php getGenreList($imageObj->genre); ?>
</select>
</td></tr>
<tr><td></td><td>
</td></tr>
<tr><td>
<?php if ($imageObj->fileName) { ?>
<img style="width:200px;" id="thum" src="<?=$imageObj->getFilePath()?>" alt="your image">
<?php } else { ?>
<img style="width:200px;" id="thum" src="img/noimage.png" alt="your image">
<?php } ?>
</td>
<td>
<?php if (!$mi && $userLevel>10) {
echo '<input type="checkbox" id="share" name="share"><label for="share"><span></span></label>共有する<br><p style="font-size:12px;">
他のユーザーと共有することができます。ただし、一度共有したものは変更ができません！</p>
<p style="font-size:10px;">選択されてるカテゴリが共有カテゴリでない場合は無効です</p>';
} ?>
</td>
<!--</tr>
<tr><td>キーワード</td></tr>
<tr>
<td colspan=3><textarea name="keywords"><?=$imageObj->keywords?></textarea>
</td>-->
<td>
<?php
if ($mi) {
	if (!$share || $userLevel>10)
		echo '<input class="button" type="submit" name="send" value="変更">';
	else
		echo '共有されてる画像は変更できません！';
} else {
	echo '<input class="button" type="submit" name="send" value="追加">';
}
?>
</td></tr>
<tr><td>&nbsp;</td></tr>

<?php if (!$mi) { ?>
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
<input type="hidden" id="imageData" name="imageHtml" value="<?=$imageData->encode()?>">
<input type="hidden" id="imageChanged" name="imageChanged" value="<?=$imageChanged?>">
</td></tr>
<?php } ?>
<tr>
<td style="text-align:left;"></td>
<td colspan=3 style="border:0;text-align:right;">
<a href="<?=$selfName?>"><input class="buttonclass" type="button" value="新規"></a> &nbsp; &nbsp; 
<input class="buttonclass" type="submit" name="send" value="削除" /></td>
</tr>
</table>
<input type='hidden' name='mi' value='<?=$mi?>'>
<input type='hidden' name='file' value='<?=$file?>'>
<br>

<table class="kwtool" style="width:98%;">
<tr><th>タイトル</th><th>ファイル名</th><th>カテゴリー</th><th>ジャンル</th><th>編集</th><th><input type="checkbox" id="selectAll"><label for='selectAll'><span></span></label>削除</th></tr>
<?php
$rowCount = getNumList();
$pageCount = (int)ceil($rowCount/$items_per_page);
if (!$pageNo)
	$pageNo = $pageCount;

// get reservation table data
$offset = ($pageNo - 1) * $items_per_page;
$imageList = getImageList($offset, $items_per_page);
if (count($imageList)) {
	$i=1;
	foreach ($imageList as $rec) {
		$id = $rec['image_id'];
		$tt = $rec['title'];
		$fi = mb_strimwidth($rec['filename'],0,40,'...','UTF-8');
		$ca = $rec['cname'];
		$ge = $rec['gname'];
		$sh = $rec['group_id'];

		echo "<tr>";
		echo "<td>".$tt."</td>";
		echo "<td>".$fi."</td>";
		echo "<td style='text-align:center;'>".$ca."</td>";
		echo "<td>".$ge."</td>";
		echo '<td><span class="small"><a href="'.$selfName.'?mi='.$id.'&p='.$pageNo.'"><img src="img/icon_edit.png"></a></span>';
		echo "<input type='hidden' name='resId".$i."' value='".$id."' /></td>";
		if ($sh)
			echo "<td><input type='checkbox'  id='d$i' class='delbtn' name='delete[]' value='".$i."' /><label for='d$i'><span></span></label></td>";
		else
			echo "<td><input type='checkbox'  id='d$i' class='delbtn' name='delete[]' disabled/></td>";
		echo "</tr>\n";

		$i++;
	}
}
?>
</table>
</form>

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

<?php
session_write_close();
?>
</div><!--end container_main-->
<p class="footer_img"><br />Copyright © 2015-2016 J Hirota. All rights Reserved.</p>

<script>
$(function() {
	bindButtonClick();

	$('#selectAll').click(function() {
		var setvar = $(this).prop('checked');
		selectAllDelItems(setvar);
	});
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

function selectAllDelItems(setvar) {
	$('.delbtn').each(function() {
		$(this).prop('checked', setvar);
	});
}

function readURL(input) {
	if (input.files && input.files[0]) {
		var reader = new FileReader();

		reader.onload = function (e) {
			$('#thum')
				.attr('src', e.target.result)
				.width(200)
				.height(150);
		};

		reader.readAsDataURL(input.files[0]);
		$('#title').val(input.files[0].name.replace(/\.[^/.]+$/, ""));
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

</script>

</body>
</html>