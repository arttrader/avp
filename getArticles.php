<?php
require_once 'AuthController.php';

$keyword = "";
$category = 0;

if ($_POST) {
	$keyword = isset($_GET['k'])?$_GET['k']:'';
	$category = isset($_GET['c'])?$_GET['c']:0;
	$page = isset($_GET['p'])?$_GET['p']:1;
	$userGroup = isset($_GET['u'])?$_GET['u']:0;
	selectItems($keyword,$category,$page);
} else if ($_GET) {
	$keyword = isset($_GET['k'])?$_GET['k']:'';
	$category = isset($_GET['c'])?$_GET['c']:0;
	$page = isset($_GET['p'])?$_GET['p']:1;
	$userGroup = isset($_GET['u'])?$_GET['u']:0;
	selectItems($keyword,$category,$page);
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
	$files = getArticleList($category, $offset, $maxItems);
	$i=0;
	if (count($files)) {
		echo "<table>\n";
		$j=0;
		echo "<tr>";
		foreach ($files as $item) {
			echo "<tr style='text-align:left;'>";
			$articleId = $item['article_id'];
			$title = $item['title'];
			$text = $item['text'];
			$category = $item['category'];
			$quote = $item['quote'];
			echo "<input type='hidden' name='articleId$i' value='".$articleId."'>";
			echo "<input type='hidden' name='title$i' value='".$title."'>";
			echo "<input type='hidden' name='text$i' value='".$text."'>";
			echo "<input type='hidden' name='category$i' value='".$category."'>";
			echo "<input type='hidden' name='quote$i' value='".$quote."'>";
			$content = '<td style="font-size:12px;"><span class="pop1" name="'
					.$title.'"></span>';
			$content .= "<br><input type='checkbox' id='a$i' class='incitem' name='itemChosen[]'"
					." value='".$i."'><label for='a$i'><span></span></label>".$title;
			$content .= "<br>".mb_strimwidth($text,0,240,'...','UTF-8');
			echo "<td　style='padding:5px;'><div id='htmlDisplay"
					.$i."' class='shown'>".$content."</div></td>\n";
			echo "</td></tr>\n";
			$i++;
			if ($i>=$maxItems) break;
		}
		echo "</table>\n";
	} else
		echo "<p class='center'><font color='red'>該当する結果がありません</font></p>\n";
	echo "<input type='hidden' name='keyword' value='".$keyword."'>";
	echo "<input type='hidden' name='menuselected' value='2'>\n";
	if ($page>1)
		echo "<input type='button' class='prevPage' id='prevPage' name='prevButton' value='prev'> &nbsp; ";
	if ($i==$maxItems)
		echo "<input type='button' class='nextPage' id='nextPage' name='nextButton' value='next'>";
	echo $html;
}

function getArticleList($category, $offset, $numItems) {
	global $userGroup;
	if ($category)
		$sql = "select * from article where category=$category and group_id=$userGroup and reuse=1 order by update_date desc limit ".$offset.",".$numItems;
	else
    	$sql = "select * from article where group_id=$userGroup and reuse=1 order by update_date desc limit "
    			.$offset.",".$numItems;
	$result = getDB($sql);
	return $result;
}
?>
