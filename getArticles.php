<?php
require_once 'AuthController.php';

$keyword = "";
$category = 0;

if (isset($_GET['k'])) {
	$keyword = $_GET['k'];
	if (isset($_GET['c'])) $category = $_GET['c'];
	$page = isset($_GET['p'])?$_GET['p']:1;
	$userID = isset($_GET['u'])?$_GET['u']:0;
	selectItems($keyword,$category,$page);
} else if (isset($_GET['u'])) {
	$imageurl = $_GET['u'];
}

function selectItems($keyword, $category, $page) {
	$maxItems = 30;
	$html = <<<EOF
<script>
$(function() {
	$('#prevPage').click(function() {
		getArticles('$keyword', $category, $page-1);
	});
	$('#nextPage').click(function() {
		getArticles('$keyword', $category, $page+1);
	});
});
</script>
EOF;
	$offset = ($page - 1) * $maxItems;
	$files = getArticleList($keyword, $category, $offset, $maxItems);
	$i=0;
	if (count($files)) {
		echo "<table>\n";
		$j=0;
		echo "<tr>";
		foreach ($files as $item) {
			echo "<tr>";
			//var_dump($item);
			$articleId = $item['article_id'];
			$title = $item['title'];
			$text = $item['text'];
			$category = $item['category'];
			echo "<input type='hidden' name='articleId$i' value='".$articleId."'>";
			echo "<input type='hidden' name='title$i' value='".$title."'>";
			echo "<input type='hidden' name='text$i' value='".$text."'>";
			echo "<input type='hidden' name='category$i' value='".$category."'>";
			$content = '<td style="font-size:12px;"><span class="pop1" name="'
					.$title.'"></span>';
			$content .= "<br><input type='checkbox' class='incitem' name='itemChosen[]'"
					." value='".$i."'>".$title;
			$content .= "<br>".$text;
			echo "<td　style='padding:5px;'><div id='htmlDisplay"
					.$i."' class='shown'>".$content."</div></td>\n";
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

function getArticleList($keyword, $category, $offset, $numItems) {
	global $userID;
	if ($category)
		$sql = "select * from article where category=$category and keywords like '%"
				.$keyword."%' and isnull(user_id) or user_id=$userID limit ".$offset.",".$numItems;
	else
    	$sql = "select * from article where keywords like '%$keyword%' and isnull(user_id) or user_id=$userID limit "
    			.$offset.",".$numItems;
	$result = getDB($sql);
	return $result;
}
?>