<?php
require_once 'AuthController.php';
require_once 'videoDataClass.php';

$keyword = "";
$category = 0;

if (isset($_GET['k'])) {
	$keyword = $_GET['k'];
	$category = isset($_GET['c'])?$_GET['c']:0;
	$genre = isset($_GET['g'])?$_GET['g']:0;
	$page = isset($_GET['p'])?$_GET['p']:1;
	$userGroup = isset($_GET['u'])?$_GET['u']:0;
	selectItems($keyword,$category,$genre,$page);
}

function selectItems($keyword, $category, $genre, $page) {
	global $userGroup;
	
	$maxItems = 32;
	$targetDir = 'image';
	
	$html = <<<EOF
<script>
$(function() {
	$('#prevPage').click(function() {
		getImages('$keyword', $category, $genre, $page-1);
	});
	$('#nextPage').click(function() {
		getImages('$keyword', $category, $genre, $page+1);
	});
});
</script>
EOF;
	$offset = ($page - 1) * $maxItems;
	$files = getImageList($keyword, $category, $genre, $offset, $maxItems);
	$i=0;
	if (count($files)) {
		echo "\n<table>\n";
		$j=0;
		foreach ($files as $item) {
			if ($j==0) echo "<tr>\n";
			$imageId = $item['image_id'];
			$title = $item['title'];
			$file = $item['filename'];
			$thumb = $item['thumbnail'];
			$gi = $item['group_id'];
			$imageObj = new imageDataClass(0,$imageId,$title,$file,$gi);
			// this is a hack, may be cleaned up later
			$content = "<a href='".$imageObj->getFilePath()."' target='_blank'>"
					.'<img src="'.$imageObj->getThumbPath()
					.'" style="max-width:100px;max-height:62px;"></a>';
			$content .= "<br><input type='checkbox' id='im$i' class='incitem' name='itemChosen[]'"
					." value='".$i."'><label for='im$i'><span></span></label>".$title;
			$content .= "<input type='hidden' name='imageId$i' value='".$imageId."'>";
			$content .= "<input type='hidden' name='title$i' value='".$title."'>";
			$content .= "<input type='hidden' name='file$i' value='".$file."'>";
			echo "<td style='padding:5px;font-size:12px;'>".$content."</td>\n";
			$i++;
			if ($i>=$maxItems) {
				echo "</tr>\n";
				break;
			}
			$j++;
			if ($j>=4) {
				echo "</tr>\n";
				$j=0;
			}
		}
		echo "</table>\n";
	} else
		echo "<p class='center'><font color='red'>該当する結果がありません</font></p>\n";
	echo "<input type='hidden' name='keyword' value='".$keyword."'>";
	echo "<input type='hidden' name='menuselected' value='2'><br>\n";
	if ($page>1)
		echo "<input type='button' class='prevPage' id='prevPage' name='prevButton' value='prev'> &nbsp; ";
	if ($i==$maxItems)
		echo "<input type='button' class='nextPage' id='nextPage' name='nextButton' value='next'>\n";
	echo $html;
}

function getImageList($keyword, $category, $genre, $offset, $numItems) {
	global $userGroup;
	if ($category)
		$catSql = "category=$category and ";
	else
		$catSql = '';
	if ($genre)
		$genreSql = "genre=$genre and ";
	else
		$genreSql = '';
	
	if ($keyword)
    	$sql = "select * from image where ".$catSql.$genreSql."keywords like '%$keyword%' and (group_id=0 or group_id=$userGroup) and reuse=1 limit "
    			.$offset.",".$numItems;
    else
    	$sql = "select * from image where ".$catSql.$genreSql."(group_id=0 or group_id=$userGroup) and reuse=1 limit ".$offset.",".$numItems;


	$result = getDB($sql);
	return $result;
}
?>