<?php
require_once 'AuthController.php';
require_once 'videoDataClass.php';

$keyword = "";

if (isset($_GET['k'])) {
	$keyword = $_GET['k'];
	$category = isset($_GET['c'])?$_GET['c']:0;
	$genre = isset($_GET['g'])?$_GET['g']:0;
	$tags = isset($_GET['t'])?$_GET['t']:'';
	$page = isset($_GET['p'])?$_GET['p']:1;
	$userGroup = isset($_GET['u'])?$_GET['u']:0;
	selectItems($keyword,$category,$genre,$tags,$page);
}

function selectItems($keyword, $category, $genre, $tags, $page) {
	global $userGroup;
	
	$maxItems = 30;
	$targetDir = "music"; 

	$html = <<<EOF
<script>
$(function() {
	$('#prevPage').click(function() {
		getMusic('$keyword', $category, $genre, '$tags', $page-1);
	});
	$('#nextPage').click(function() {
		getMusic('$keyword', $category, $genre, '$tags', $page+1);
	});

	$(".audio").mb_miniPlayer({
		width:300,
		inLine:true,
		id3:true,
		addShadow:false,
		pauseOnWindowBlur:true,
		downloadPage:null
	});
});
</script>
EOF;
	$offset = ($page - 1) * $maxItems;
	// this is dangerous, we should use the class even if performance may be a little lower
	$files = getMusicList($keyword, $category, $genre, $tags, $offset, $maxItems);
	$i=0;
	if (count($files)) {
		echo "<table style='text-align:left;'>\n";
		$j=0;
		foreach ($files as $item) {
			//var_dump($item);
			$musicId = $item['music_id'];
			$title = htmlspecialchars($item['title']);
			$file = htmlspecialchars($item['filename']);
			$lg = $item['length'];
			$gi = $item['group_id'];
			$musicObj = new musicDataClass(0,$musicId,$title,$file,$gi);
			$content = "<td style='width:250px;'><input type='checkbox' id='m$i' class='incitem' name='itemChosen[]'"
					." value='".$i."'><label for='m$i'><span></span></label>".$title
					."</td><td style='text-align:left;'>"
					.'<a id="a'.$i.'" class="audio {skin:\'mvpSkin\', autoPlay:false, showRew:false}" href="'.$musicObj->getFilePath().'">'.$title.'</a>'
					."</td><td style='font-size:10px;text-align:right;'>".$lg;
;

			echo '<tr style="text-align:left;">'.$content;
			echo "<input type='hidden' name='musicId$i' value='".$musicId."'>";
			echo "<input type='hidden' name='title$i' value='".$title."'>";
			echo "<input type='hidden' name='file$i' value='".$file."'>";
			echo "</td></tr>\n";
			if ($i++>=$maxItems) break;
		}
		echo "</table>\n";
	} else
		echo "<p class='center'><font color='red'>該当する結果がありません</font></p>\n";
	echo "<input type='hidden' name='keyword' value='".$keyword."'>";
	echo "<input type='hidden' name='menuselected' value='2'><br>\n";
	if ($page>1)
		echo "<input type='button' class='prevPage' id='prevPage' name='prevButton' value='prev'> &nbsp; ";
	if ($i==$maxItems)
		echo "<input type='button' class='nextPage' id='nextPage' name='nextButton' value='next'>";
	echo $html;
}

function getMusicList($keyword, $category, $genre, $tags, $offset, $numItems) {
	global $userGroup;
	if ($genre)
		$genreSql = "genre=$genre and ";
	else
		$genreSql = '';
	if ($category)
		$catSql = "category=$category and ";
	else
		$catSql = '';
	if ($tags)
    	$sql = "select * from music m join music_tag mt on m.music_id=mt.music_id where mt.tag_id in($tags) and "
    		.$genreSql.$catSql."(group_id=0 or group_id=$userGroup) limit ".$offset.",".$numItems;
	else
    	$sql = "select * from music where ".$genreSql.$catSql
    		."(group_id=0 or group_id=$userGroup) limit ".$offset.",".$numItems;
    //echo $sql;
	$result = getDB($sql);
	
	return $result;
}
?>