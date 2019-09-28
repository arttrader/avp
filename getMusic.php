<?php
require_once 'AuthController.php';

$keyword = "";

if (isset($_GET['k'])) {
	$keyword = $_GET['k'];
	$category = isset($_GET['c'])?$_GET['c']:0;
	$genre = isset($_GET['g'])?$_GET['g']:0;
	$tags = isset($_GET['t'])?$_GET['t']:'';
	$page = isset($_GET['p'])?$_GET['p']:1;
	$userID = isset($_GET['u'])?$_GET['u']:0;
	selectItems($keyword,$category,$genre,$tags,$page);
}

function selectItems($keyword, $category, $genre, $tags, $page) {
	$maxItems = 30;
	$targetDir = "music/";
		
	$html = <<<EOF
<script>
$(function() {
	$('#prevPage').click(function() {
		getMusic('$keyword', $category, $genre, '$tags', $page-1);
	});
	$('#nextPage').click(function() {
		getMusic('$keyword', $category, $genre, '$tags', $page+1);
	});
});
</script>
EOF;
	$offset = ($page - 1) * $maxItems;
	$files = getMusicList($keyword, $category, $genre, $tags, $offset, $maxItems);
	$i=0;
	if (count($files)) {
		echo "<table style='text-align:left;'>\n";
		$j=0;
		foreach ($files as $item) {
			echo "<tr>";
			//var_dump($item);
			$musicId = $item['music_id'];
			$title = $item['title'];
			$file = $item['filename'];
			$content = '<tr>';
			$content .= "<td><input type='checkbox' class='incitem' name='itemChosen[]'"
					." value='".$i."'>".$title."</td><td style='text-align:left;padding-left:10px;'>".$file;
			echo "".$content."</td>\n";
			echo '<td><a id="a'.$i.'" class="audio {autoPlay:false, showRew:false}" href="'.$targetDir.$file.'">'.$title.'</a>';
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
	if ($i>$maxItems)
		echo "<input type='button' class='nextPage' id='nextPage' name='nextButton' value='next'>";
	echo $html;
}

function getMusicList($keyword, $category, $genre, $tags, $offset, $numItems) {
	global $userID;
	if ($genre)
		$genreSql = "genre=$genre and ";
	else
		$genreSql = '';
	if ($category)
		$catSql = "category=$category and ";
	else
		$catSql = '';
	if ($tags)
    	$sql = "select * from music m join music_tag mt on m.music_id=mt.music_id where mt.tag_id in ($tags) and "
    		.$genreSql.$catSql."keywords like '%$keyword%' and isnull(user_id) or user_id=$userID limit ".$offset.",".$numItems;
	else
    	$sql = "select * from music where ".$genreSql.$catSql
    		."keywords like '%$keyword%' and isnull(user_id) or user_id=$userID limit ".$offset.",".$numItems;
    //echo $sql;
	$result = getDB($sql);
	
	return $result;
}
?>