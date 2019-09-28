<?php
require_once 'AuthController.php';
require_once 'videoDataClass.php';

$type = 0;
$category = 0;

if (isset($_GET['t'])) {
	$type = $_GET['t'];
	if (isset($_GET['c'])) $category = $_GET['c'];
	$keyword = $_GET['k'];
	$page = isset($_GET['p'])?$_GET['p']:1;
	$userGroup = isset($_GET['u'])?$_GET['u']:0;
	$userLevel = isset($_GET['l'])?$_GET['l']:3;
	selectItems($type,$category,$page);
} else if (isset($_GET['u'])) {
	$imageurl = $_GET['u'];
}

function selectItems($type, $category, $page) {
	global $userGroup;
	
	$maxItems = 30;
	$targetDir = "video";
	
	$html = <<<EOF
<script>
$(function() {
	$('#prevPage').click(function() {
		getVideo('', $category, $type, $page-1);
	});
	$('#nextPage').click(function() {
		getVideo('', $category, $type, $page+1);
	});
});
</script>
EOF;
	$offset = ($page - 1) * $maxItems;
	$files = getVideoList($type, $category, $offset, $maxItems);
	$i=0;
	if (count($files)) {
		echo "<table>\n";
		$j=0;
		echo "<tr>";
		foreach ($files as $item) {
			if ($j==0) echo "<tr>";
			//var_dump($item);
			$videoId = $item['video_id'];
			$title = $item['title'];
			$file = $item['filename'];
			$thumb = $item['thumbnail'];
			$gi = $item['group_id']?$item['group_id']:0;
			$videoObj = new videoDataClass(0,$videoId,$title,$file,0,$gi);
			$videoObj->thumbnail = $thumb;
			if ($thumb)
				$video = '<img style="max-width:200px;max-height:160px;" src="'
					.$videoObj->getThumbPath().'" />';
			else
				$video = '<video style="max-width:200px;max-height:160px;">'
					.'<source src="'.$videoObj->getFilePath().'"></video>';
			$content = '<td style="font-size:12px;"><span class="pop1" name="'
					.$title.'">'.$video.'</span>';
			$content .= "<br><input type='checkbox' id='v$i' class='incitem' name='itemChosen[]'"
					." value='".$i."'><label for='v$i'><span></span></label>"
					."<a href='".$videoObj->getFilePath()."' target='_blank'>".$title."</a>";
			$content .= "<br>";
			echo "<td　style='padding:5px;'><div id='htmlDisplay"
					.$i."' class='shown'>".$content;
			echo "<input type='hidden' name='videoId$i' value='".$videoId."'>";
			echo "<input type='hidden' name='title$i' value='".$title."'>";
			echo "<input type='hidden' name='file$i' value='".$file."'>";
			echo "</div></td>\n";
			$i++;
			if ($i>=$maxItems) {
				echo "</tr>";
				break;
			}
			$j++;
			if ($j>=3) {
				echo "</tr>";
				$j=0;
			}
		}
		echo "</table>\n";
	} else
		echo "<p class='center'><font color='red'>該当する結果がありません</font></p>\n";
	echo "<input type='hidden' name='type' value='".$type."'>";
	echo "<input type='hidden' name='menuselected' value='2'><br>\n";
	if ($page>1)
		echo "<input type='button' class='prevPage' id='prevPage' name='prevButton' value='prev'> &nbsp; ";
	if ($i==$maxItems)
		echo "<input type='button' class='nextPage' id='nextPage' name='nextButton' value='next'>";
	echo $html;
}

function getVideoList($type, $category, $offset, $numItems) {
	global $userLevel,$userGroup;
	if ($userLevel<3) {
		$sql = "select * from video where category in (1,2,3) and video_type="
				.$type." and group_id=0 limit ".$offset.",".$numItems;
	} else if ($category)
		$sql = "select * from video where category=$category and video_type="
				.$type." and (isnull(group_id) or group_id=0 or group_id=$userGroup) limit ".$offset.",".$numItems;
	else
    	$sql = "select * from video where video_type=$type and (isnull(group_id) or group_id=0 or group_id=$userGroup) limit "
    			.$offset.",".$numItems;
	$result = getDB($sql);
	return $result;
}
?>