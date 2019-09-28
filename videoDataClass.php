<?php
/* 
	Data structure for Movie Generator
*/
require_once 'AuthController.php';

abstract class itemData {
	public $id, $title, $desc, $category, $genre, $keywords;
}

class imageDataClass extends itemData {
	public $imageId;
	public $fileName;

	static $imageDir = "images/";
	
	public function __construct($iID, $imageID, $title, $file) {
		$this->id = $iID;
		$this->imageId = $imageID;
		$this->title = $title;
		$this->fileName = $file;
	}	

	public function attach2Video($vID) {
		$sql = sprintf("call attach_image2Video(%u,%u)",$vID,$this->imageId);
		//echo $sql."<br>";
		$result = getDB($sql);
		return $result;
	}
	
	public function getFilePath() {
		return self::$imageDir.$this->fileName;
	}

	public static function getFolder() {
		return self::$imageDir;
	}
}

class articleDataClass extends itemData {
	public $articleId;
	public $text;
	public $reuse;
	private $textChanged = false;
	
	public function __construct($iID, $articleId, $title, $txt, $category, $reuse=0) {
		$this->id = $iID;
		$this->articleId = $articleId;
		$this->title = $title;
		$this->text = $txt;
		$this->category = $category;
		$this->reuse = $reuse;
	}

	public function setText($txt, $reuse=0) {
		$this->text = $txt;
		$this->reuse = $reuse;
		$this->save();
	}

	public function attach2Video($vID) {
		$sql = sprintf("call attach_article2Video(%u,%u)",$vID,$this->articleId);
		echo $sql."<br>";
		$result = getDB($sql);
		return $result;
	}

	public function save() {
		global $dbAdapter, $userID;
		
		if ($this->articleId) {
			$sql = sprintf("call update_article(%u,'%s','%s',%u,'%s','%s',%u)",
				$this->articleId,$this->title,$this->text,$this->category,
				$this->genre,$this->keywords,$this->reuse);
			$result = getDB($sql);
		} else {
			$sql = sprintf("call add_article('%s','%s',%u,'%s','%s',%u,%u)",
				$this->title,$this->text,$this->category,$this->genre,
				$this->keywords,$this->reuse,$userID);
			echo $sql."<br>";
			$result = getDB($sql);
			//$this->articleId = $dbAdapter->Driver->getLastGeneratedValue();
			$this->articleId = getLastInsertedID();
			echo "New article added ".$this->articleId."<br>";
		}
	}
}

class musicDataClass extends itemData {
	public $musicId;
	public $fileName;
	public $duration;
	
	static $musicDir = "music/";
	private $ffmpeg;
	
	public function __construct($iID, $musicId, $title, $file) {
		global $ini_array;
		
		$this->ffmpeg = $ini_array['ffmpeg'];
		$this->id = $iID;
		$this->musicId = $musicId;
		$this->title = $title;
		$this->fileName = $file;
		$this->duration = $this->getDuration(self::$musicDir.$file);
	}

	public function attach2Video($vID) {
		$sql = sprintf("call attach_music2Video(%u,%u)", $vID,$this->musicId);
		//echo $sql."<br>";
		$result = getDB($sql);
		return $result;
	}

	public function getFilePath() {
		return self::$musicDir.$this->fileName;
	}

	public static function getFolder() {
		return self::$musicDir;
	}

	function getDuration($file) {
		$cmd = $this->ffmpeg
				." -i $file 2>&1 | grep 'Duration' | cut -d ' ' -f 4 | sed s/,//";
		$dStr = exec($cmd);
		if ($dStr) {
			$dArray = explode(':', $dStr);
			$duration = 3600*$dArray[0] + 60*$dArray[1] + $dArray[2];
		} else $duration = 0;
		
		return $duration;
	}
}

class videoDataClass extends itemData {
	public $videoId;
	public $fileName;
	public $duration;
	public $type;
	public $start, $end;
	
	static $videoDir = "video/";
	private $ffmpeg;
	
	public function __construct($iID, $videoId, $title, $file, $type=1) {
		global $ini_array;
		
		$this->ffmpeg = $ini_array['ffmpeg'];
		$this->id = $iID;
		$this->videoId = $videoId;
		$this->title = $title;
		$this->type= $type;
		$this->fileName = $file;
		$this->duration = $this->getDuration(self::$videoDir.$file);
	}

	public function setData($id,$title,$file) {
		$this->videoId = $id;
		$this->title = $title;
		$this->fileName = $file;
		$sql = "update video_video set video_id=$id where video_video_id=".$this->id;
		echo $sql."<br>";
		$result = getDB($sql);
		return $result;
	}

	public function attach2Video($vID) {
		$sql = sprintf("call attach_video2Video(%u,%u)", $vID,$this->videoId);
		//echo $sql."<br>";
		$result = getDB($sql);
		return $result;
	}

	public function getFilePath() {
		return self::$videoDir.$this->fileName;
	}

	public static function getFolder() {
		return self::$videoDir;
	}
	
	function getDuration($file) {
		$cmd = $this->ffmpeg." -i $file 2>&1 | grep 'Duration' | cut -d ' ' -f 4 | sed s/,//";
		$dStr = exec($cmd);
		if ($dStr) {
			$dArray = explode(':', $dStr);
			$duration = 3600*$dArray[0] + 60*$dArray[1] + $dArray[2];
		} else $duration = 0;
		
		return $duration;
	}
}


class videoProdDataClass extends itemData {
	public $tags;
	public $videoFileName;
	
	public $useVoice = 1;
	public $voiceSpeed = 1.0;
	public $useTextFlow = 1;
	public $fontSize = 50;
	
	public $imageDir = 'images';
	public $targetDir = "video/";
	public $watermark = 'newtuber.png';
	public $useWatermark = '';
	public $articleChanged, $imageChanged, $musicChanged, $videoChanged;
	public $articleTextChanged;
	public $startProduction = false;
	
	public $quoteTitle = "";
	public $quoteUrl = "";
	
	public $articleData, $imageData, $musicData, $videoData;
	
	public function __construct($vID=null) {
		$this->articleChanged = false;
		$this->imageChanged = false;
		$this->musicChanged = false;
		$this->videoChanged = false;
		$this->articleTextChanged = false;
		$this->articleData = new mDataClass();
		$this->imageData = new mDataClass();
		$this->musicData = new mDataClass();
		$this->videoData = new mDataClass();
		if ($vID) {
			$this->id = $vID;
			$this->loadData();
		} else
			$this->id = null;
	}
	
	public function getNarration() {
		$txt = '';
		foreach ($this->articleData->getArray() as $aData)
			$txt .= $aData->text."\n";
		return $txt;
	}
	
	public function setArticle($txt,$resuse=0) {
		if (!$this->articleData->count()) {
			$item = new articleDataClass(null,null,$this->title,$txt,$this->category,$reuse);
			$this->articleData->push($item);
			$this->articleChanged = true;
		}
		$this->articleData->current()->setText($txt,$resuse);
	}
	
	public function setStartProduction() {
		$sql = sprintf("call start_production(%u)", $this->id);
		$result = getDB($sql);
		$this->startProduction = true;
	}
	
	public function getMusic() {
		if ($this->musicData->count())
			return $this->musicData->current()->getFilePath();
		else
			return '';
	}
	
	public function getCMVideo() {
		$n = $this->videoData->count();
		$this->videoData->rewind();
		if ($n) {
			for ($i=0; $i<$n; $i++)
				if ($this->videoData->current()->type==1)
					return $this->videoData->current();
				else
					$this->videoData->next();
		}
		return null;
	}
	
	public function getEndVideo() {
		$n = $this->videoData->count();
		$this->videoData->rewind();
		if ($n) {
			for ($i=0; $i<$n; $i++)
				if ($this->videoData->current()->type==2)
					return $this->videoData->current();
				else
					$this->videoData->next();
		}
		return null;
	}

	public function SearchVideoByID($id) {
//		echo "searching video...".$id."<br>";
		$n = $this->videoData->count();
		$this->videoData->rewind();
		if ($n) {
			for ($i=0; $i<$n; $i++) {
//				echo "video_video_id= ".$this->videoData->current()->id."<br>";
				if ($this->videoData->current()->videoId==$id) {
					return $this->videoData->current();
				} else
					$this->videoData->next();
			}
		}
		return null;
	}
	
	public function loadData() {
		// get video info and article
		$sql = "SELECT title,description,category,fileName,tags,displayText,useTextVoice,voiceSpeed,fontSize,useWatermark,startProduction,quote_title,quote_url FROM youtube_data.video_info WHERE video_info_id=".$this->id;
		$result = getDB($sql);
		if (count($result)) {
			$rec = $result[0];
			$this->title = $rec['title'];
			$this->desc = $rec['description'];
			$this->tags = $rec['tags'];
			$this->useTextFlow = $rec['displayText'];
			$this->fontSize = $rec['fontSize'];
			$this->useWatermark = $rec['useWatermark'];
			$this->useVoice = $rec['useTextVoice'];
			$this->voiceSpeed = $rec['voiceSpeed'];
			$this->videoFileName = $rec['fileName'];
			$this->quoteTitle = $rec['quote_title'];
			$this->quoteUrl = $rec['quote_url'];
			$this->imageDir = 'images';
			$this->category = $rec['category'];
			$this->startProduction = $rec['startProduction'];

			$sql = "SELECT video_narration_id,i.article_id,i.title,i.text,i.category,i.reuse FROM article i JOIN video_narration vi ON i.article_id=vi.article_id WHERE vi.video_info_id=".$this->id;
			$result = getDB($sql);
			foreach ($result as $rec) {
				$id = $rec['video_narration_id'];
				$articleId = $rec['article_id'];
				$title = $rec['title'];
				$text = $rec['text'];
				$category = $rec['category'];
				$reuse = $rec['reuse'];
				$item = new articleDataClass($id,$articleId,$title,$text,$category,$reuse);
				$this->articleData->push($item);
			}

			$sql = "SELECT video_image_id,i.image_id,i.title,i.filename FROM image i JOIN video_image vi ON i.image_id=vi.image_id WHERE vi.video_info_id=".$this->id;
			$result = getDB($sql);
			foreach ($result as $rec) {
				$id = $rec['video_image_id'];
				$imageId = $rec['image_id'];
				$title = $rec['title'];
				$file = $rec['filename'];
				$item = new imageDataClass($id,$imageId,$title,$file);
				$this->imageData->push($item);
			}

			$sql = "SELECT video_music_id,i.music_id,i.title,i.filename FROM music i JOIN video_music vi ON i.music_id=vi.music_id WHERE vi.video_info_id=".$this->id;
			$result = getDB($sql);
			foreach ($result as $rec) {
				$id = $rec['video_music_id'];
				$musicId = $rec['music_id'];
				$title = $rec['title'];
				$file = $rec['filename'];
				$item = new musicDataClass($id,$musicId,$title,$file);
				$this->musicData->push($item);
			}

			$sql = "SELECT video_video_id,i.video_id,i.title,i.video_type,i.filename FROM video i JOIN video_video vi ON i.video_id=vi.video_id WHERE vi.video_info_id=".$this->id;
			$result = getDB($sql);
			foreach ($result as $rec) {
				$id = $rec['video_video_id'];
				$videoId = $rec['video_id'];
				$title = $rec['title'];
				$file = $rec['filename'];
				$type = $rec['video_type'];
				$item = new videoDataClass($id,$videoId,$title,$file,$type);
				$this->videoData->push($item);
			}
		}
	}
	
	public function setCompletion() {
		global $dbAdapter;
		
		$sql = "UPDATE video_info set startProduction=0,production_date=now() WHERE video_info_id=".$this->id;
		$result = getDB($sql);
	}
	
	public function saveData() {
		global $dbAdapter;
		
		$vID = $this->id;
		$title = $this->title;
		$fn = $this->videoFileName;
		$ca = $this->category;
		$desc = $this->desc;
		$ta = $this->tags;
		$tf = $this->useTextFlow;
		$uv = $this->useVoice;
		$vs = $this->voiceSpeed;
		$fs = $this->fontSize;
		$qt = $this->quoteTitle;
		$qu = $this->quoteUrl;
		if ($vID) {
			$sql = sprintf("call update_videoInfo(%u,'%s','%s',%u,'%s','%s',%u,%u,%u,%u,'%s','%s')",
						$vID,$title,$desc,$ca,$ta,$fn,$tf,$uv,$vs,$fs,$qt,$qu);
			$result = getDB($sql);
			
			if ($this->articleChanged) {
				$sql = "delete from video_narration where video_info_id=".$vID;
				$result = getDB($sql);
				echo "num articles = ".$this->articleData->count()."<br>";
				foreach ($this->articleData->getArray() as $iData)
					$result = $iData->attach2Video($vID);
			} else if ($this->articleTextChanged) {
				// if article hasn't been saved, it needs to be saved first
				$this->articleData->current()->save();
			}
			
			if ($this->imageChanged) {
				$sql = "delete from video_image where video_info_id=".$vID;
				$result = getDB($sql);
				if (count($result) && $result[0][0]===$n2)
					echo "deleted $n2 images<br>";
				foreach ($this->imageData->getArray() as $iData)
					$result = $iData->attach2Video($vID);
			}
			
			if ($this->musicChanged) {
				$sql = "delete from video_music where video_info_id=".$vID;
				$result = getDB($sql);
				if (count($result) && $result[0][0]===$n2)
					echo "deleted $n2 music<br>";
				foreach ($this->musicData->getArray() as $iData)
					$result = $iData->attach2Video($vID);
			}
			
			if ($this->videoChanged) {
				$sql = "delete from video_video where video_info_id=".$vID;
				$result = getDB($sql);
				if (count($result) && $result[0][0]===$n2)
					echo "deleted $n2 video<br>";
				foreach ($this->videoData->getArray() as $iData)
					$result = $iData->attach2Video($vID);
			}
			
			if ($this->startProduction) {
				$sql = sprintf("call start_production(%u)",$vID);
				$result = getDB($sql);
			}
		} else {
			$sql = sprintf("call add_videoInfo('%s','%s',%u,'%s','%s',%u)",
						$title,$desc,$ca,$ta,$fn,$tf);
			$result = getDB($sql);
			$vID = getLastInsertedID();
			echo "new video_info created ".$vID."<br>";
			// need to save all data link here
			foreach ($this->articleData->getArray() as $iData)
				$result = $iData->attach2Video($vID);
			foreach ($this->imageData->getArray() as $iData)
				$result = $iData->attach2Video($vID);
			foreach ($this->musicData->getArray() as $iData)
				$result = $iData->attach2Video($vID);
			foreach ($this->videoData->getArray() as $iData)
				$result = $iData->attach2Video($vID);
		}
	}
}


class jDataClass {
	public $videoData = array();
	public $ytAccount;
	public $watermark;
	public $cmVideo;
	public $endVideo;
	public $useVoice;
	public $completed;
	public $completionTime;
	
	public function __construct($jID) {
		$sql = "select * from job where job_id=".$jID;
		$result = getDB($sql);
		
		$sql = "select * from video_info where job_id=".$jID;
		$videoList = getDB($sql);
		if (count($videoList)) {
			foreach ($videoList as $video) {
				$viID = $video['video_info_id'];
				$videoInfo = new videoProdDataClass($viID);
				$videoData[] = $videoInfo;
			}
		}	
	}
}


function getLastInsertedID() {
	$sql = sprintf("SELECT LAST_INSERT_ID()");
	$rows = getDB($sql);
	if ($rows) {
		$row = $rows[0];
		return $row['LAST_INSERT_ID()'];
	}
	return null;
}


class mDataClass {
// to replace SplDoublyLinkedList with an ordinary array
	public $mData;
	public $index;
	
	public function __construct() {
		$this->clear();
		$this->index = 0;
	}
	
	public function getArray() {
		return $this->mData;
	}
	
	public function push($item) {
		$this->mData[] = $item;
	}
	
	public function offsetGet($i) {
		$this->index = $i;
		return $this->mData[$i];
	}
	
	public function offsetSet($i, $item) {
		$this->index = $i;
		$this->mData[$i] = $item;
	}
	
	public function offsetUnset($i) {
		$this->index = $i;
		array_splice($this->mData, $i, 1);
	}
	
	public function offsetInsert($i, $item) {
		$this->index = $i;
		$last = array_splice($this->mData, $i);
		array_push($this->mData, $item);
		$this->mData = array_merge($this->mData, $last);
	}
	
	public function unshift($item) {
		array_unshift($this->mData, $item);
	}
	
	public function count() {
		return count($this->mData);
	}
	
	public function current() {
		if (count($this->mData))
			return $this->mData[$this->index];
		else
			return null;
	}
	
	public function next() {
		if (count($this->mData)-1>$this->index) {
			$this->index++;
			return true;
		} else return false;
	}
	
	public function rewind() {
		$this->index = 0;
	}
	
	public function serialize() {
		return serialize($this->mData);
	}
	public function unserialize($strdata) {
		return $this->mData = unserialize($strdata);
	}
	
	public function encode() {
		return base64_encode(gzdeflate(serialize($this->mData)));
	}
	public function decode($encoded_data) {
		if ($encoded_data)
			return $this->mData = unserialize(gzinflate(base64_decode($encoded_data)));
	}

	public function clear() {
		$this->mData = array();
		$this->index = 0;
	}
}
