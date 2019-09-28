<?php
require_once 'AuthController.php';

$keyword = "";
$category = 0;

if (isset($_GET['k'])) {
	$keyword = $_GET['k'];
	if (isset($_GET['c'])) $category = $_GET['c'];
	$page = isset($_GET['p'])?$_GET['p']:1;
	$category = $category?$category:0;
	$userID = isset($_GET['u'])?$_GET['u']:0;
	selectItems($keyword,$category,$page);
} else if (isset($_GET['u'])) {
	$imageurl = $_GET['u'];
}

function selectItems($keyword, $category, $page) {
	$maxItems = 32;
	$html = <<<EOF
<script>
$(function() {
	$('#prevPage').click(function() {
		getImages('$keyword', $category, $page-1);
	});
	$('#nextPage').click(function() {
		getImages('$keyword', $category, $page+1);
	});
});
</script>
EOF;
	$offset = ($page - 1) * $maxItems;
	$files = getImageList($keyword, $category, $offset, $maxItems);
	$i=0;
	if (count($files)) {
		echo "\n<table>\n";
		$j=0;
		foreach ($files as $item) {
			if ($j==0) echo "<tr>\n";
			$imageId = $item['image_id'];
			$title = $item['title'];
			$fileName = $item['filename'];
			$content = "<a href='images/".$fileName."' target='_blank'>"
					.'<img src="images/'.$fileName
					.'" style="max-width:100px;max-height:62px;"></a>';
			$content .= "<br><input type='checkbox' class='incitem' name='itemChosen[]'"
					." value='".$i."'>"
					.$title;
			$content .= "<input type='hidden' name='imageId$i' value='".$imageId."'>";
			$content .= "<input type='hidden' name='title$i' value='".$title."'>";
			$content .= "<input type='hidden' name='file$i' value='".$fileName."'>";
//			$content .= "<br>";
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

function getImageList($keyword, $category, $offset, $numItems) {
	global $userID;
	if ($category)
		$sql = "select * from image where category=$category and keywords like '%"
				.$keyword."%' and isnull(user_id) or user_id=$userID limit ".$offset.",".$numItems;
	else
    	$sql = "select * from image where keywords like '%$keyword%' and isnull(user_id) or user_id=$userID limit "
    			.$offset.",".$numItems;
	$result = getDB($sql);
	return $result;
}
?>