<?php
/*
	Data structure for Movie Generator

	version 1.1
	notice groupID is no longer for groups, it is now essentially a user ID (folder ID)
	groupID = FolderID = UserID
*/
require_once 'AuthController.php';
require_once 'php_image_magician.php';

$ffmpeg = $ini_array['ffmpeg'];
$volume = 'users/user';
$message = '';

abstract class itemData {
	public $id,$title,$desc,$category,$genre,$keywords,$userID,$groupID;
	private $loaded = false;

	public function setUserID($ui) { $this->userID = $ui; }
	public function getUserID() { return $this->userID; }
	public function setGroupID($ug) { $this->groupID = $ug; }
	public function getGroupID() { return $this->groupID; }
	abstract function getFolderName();
	public function getFolder() {
		global $volume;
		return $volume.$this->getGroupID()."/".$this->getFolderName()."/";
	}
	public function getFilePath() {
		return $this->getFolder().$this->fileName;
	}

	public static function compress($obj) {
		return base64_encode(gzdeflate(serialize($obj)));
	}

	public static function uncompress($objString) {
		return unserialize(gzinflate(base64_decode($objString)));
	}
}

class articleDataClass extends itemData {
	public $articleId;
	private $text;
	public $quote = '';
	public $reuse;
	private $textChanged = false;

	public function __construct($iID, $articleId, $title, $txt, $category, $reuse=0) {
		global $userID, $userGroup;

		$this->id = $iID;
		$this->articleId = $articleId;
		$this->title = $title;
		$this->text = $this->prepare4AITalk($txt);
		$this->category = $category;
		$this->reuse = $reuse;
		$this->setUserID($userID);
		$this->setGroupID($userGroup);
	}

	public function setText($txt, $reuse=0) {
		$this->text = $this->prepare4AITalk($txt);
		$this->reuse = $reuse;
		if ($this->id)
			$this->saveData();
	}

	public function getText() {
		return $this->text;
	}

	public function attach2Video($vID) {
		global $debugmode;
		$sql = sprintf("call attach_article2Video(%u,%u)",$vID,$this->articleId);
		//if ($debugmode) echo $sql."<br>";
		$result = getDB($sql,false);
		$this->id = getLastInsertedID();
		return $result;
	}

	public function attach2Job($jID) {
		global $debugmode;
		$sql = sprintf("call attach_article2Job(%u,%u)",$jID,$this->articleId);
		//if ($debugmode) echo $sql."<br>";
		$result = getDB($sql,false);
		$this->id = getLastInsertedID();
		return $result;
	}

	public function loadData() {
		global $debugmode;

		$sql = "select * from article where article_id=".$this->articleId;
		$result = getDB($sql);
		if (count($result)) {
			$rec = $result[0];
			$this->title = $rec['title'];
			$this->text = $rec['text'];
			$this->category = $rec['category'];
//			$this->genre = $rec['genre'];
			$this->keywords = $rec['keywords'];
			$this->quote = $rec['quote'];
			$this->reuse = $rec['reuse'];
			$this->setGroupID($rec['group_id']?$rec['group_id']:0);
			$this->userID = $rec['user_id']?$rec['user_id']:0;
			$this->loaded = true;
		}
	}

	public function saveData() {
		global $debugmode;

		$savTitle = htmlspecialchars($this->title,ENT_QUOTES);
		$savText = htmlspecialchars($this->text,ENT_QUOTES);
		if ($this->articleId) {
			$sql = sprintf("call update_article(%u,'%s','%s',%u,'%s','%s','%s',%u)",
				$this->articleId,$savTitle,$savText,$this->category,
				$this->genre,$this->keywords,$this->quote,$this->reuse);
			$result = getDB($sql,false);
		} else {
			$sql = "insert into article (title,text,category,genre,keywords,quote,reuse,group_id,user_id)
	values (:iTitle,:iText,:iCategory,:iGenre,:iKeywords,:Quote,:iReuse,:iGroupID,:iUserID);";
			$params = array (
				':iTitle'=>$savTitle,
				':iText'=>$savText,
				':iCategory'=>$this->category,
				':iGenre'=>$this->genre,
				':iKeywords'=>$this->keywords,
				':Quote'=>$this->quote,
				':iReuse'=>$this->reuse,
				':iGroupID'=>$this->getGroupID(),
				':iUserID'=>$this->userID
			);
			$result = getDB($sql,false,$params);
			$this->articleId = getLastInsertedID();
			if ($debugmode) echo "New article added ".$this->articleId."<br>";
		}
	}

	function getFolderName() {
		return '';
	}

	function prepare4AITalk($text) {
		$text = str_replace('%', '％', $text);
		$text = str_replace('&', '＆', $text);
		return $text;
	}
}

class imageDataClass extends itemData {
	public $imageId;
	public $fileName;
	public $thumbnail;
	public $reuse=1;
	public $type=0;

	public function __construct($iID, $imageID, $title, $file, $ug=0) {
		$this->id = $iID;
		$this->imageId = $imageID;
		$this->title = $title;
		$this->fileName = $file;
		$this->setGroupID($ug);
	}

	public function getID() {
		return $this->imageId;
	}

	public function setData($id,$title,$file) {
		$this->imageId = $id;
		$this->title = $title;
		$this->fileName = $file;
		if ($this->id) {
			$sql = "update job_image set image_id=$id where job_image_id=".$this->id;
			$result = getDB($sql,false);
			return $result;
		}
	}

	public function attach2Video($vID) {
		$sql = sprintf("call attach_image2Video(%u,%u,%u)",$vID,$this->imageId,$this->type);
		//echo $sql."<br>";
		$result = getDB($sql,false);
		$this->id = getLastInsertedID();
		return $result;
	}

	public function attach2Job($jID) {
		$sql = sprintf("insert into job_image(job_id, image_id, image_type)
	values(%u, %u, %u);",$jID,$this->imageId,$this->type);
		//echo $sql."<br>";
		$result = getDB($sql,false);
		$this->id = getLastInsertedID();
		return $result;
	}

	public function detachJob() {
		if (!$this->id) return false;
		$sql = sprintf("delete from job_image where job_image_id=".$this->id);
		//echo $sql."<br>";
		$result = getDB($sql,false);
		return $result;
	}

	public function loadData($mi) {
		global $debugmode;

		$this->imageId = $mi;
		$sql = "select * from image where image_id=".$mi;
		$result = getDB($sql);
		if (count($result)) {
			$rec = $result[0];
			$this->title = $rec['title'];
			$this->fileName = $rec['filename'];
			$this->category = $rec['category'];
			$this->genre = $rec['genre'];
			$this->keywords = $rec['keywords'];
			$this->thumbnail = $rec['thumbnail'];
			$this->setGroupID($rec['group_id']?$rec['group_id']:0);
			$this->loaded = true;
		}
	}

	public function saveData() {
		global $debugmode;

		if ($this->imageId) {
//			$sql = sprintf("call update_image(%u,'%s','%s',%u,%u,'%s')",
//				$this->imageId,$this->title,$this->fileName,$this->category,
//				$this->genre,$this->reuse,$this->keywords);
			$sql = "update image
		set title=:iTitle, filename=:iFile, category=:iCategory, genre=:iGenre, keywords=:iKeywords, reuse=:iReuse, update_date=now()
	where image_id=".$this->imageId;
			$params = array (
				':iTitle'=>$this->title,
				':iFile'=>$this->fileName,
				':iCategory'=>$this->category,
				':iGenre'=>$this->genre,
				':iKeywords'=>$this->keywords,
				':iReuse'=>$this->reuse
			);
			$result = getDB($sql,false,$params);
		} else {
			$sql = "insert into image (title,filename,category,genre,keywords,reuse,group_id,thumbnail)
	values (:iTitle,:iFile,:iCategory,:iGenre,:iKeywords,:iReuse,:iGroupID,:iThumb);";
			$params = array (
				':iTitle'=>$this->title,
				':iFile'=>$this->fileName,
				':iCategory'=>$this->category,
				':iGenre'=>$this->genre,
				':iKeywords'=>$this->keywords,
				':iReuse'=>$this->reuse,
				':iGroupID'=>$this->getGroupID(),
				':iThumb'=>$this->thumbnail
			);
			$result = getDB($sql,false,$params);
			$this->imageId = getLastInsertedID();
			//if ($debugmode) echo "New image added ".$this->imageId."<br>";
		}
	}

	public function upload($title,$files,$category,$genre,$keywords,$resue=0,$fname='') {
		global $debugmode;

		$filename = basename($files['name']);
		//if ($debugmode) writeLog("upload started ".$filename);
		$uploadOk = true;
		// Check file size
		if ($files["size"] > 3000000) {
			$message = "ファイルが大きすぎです！縮小してからアップロードしてください！";
			$uploadOk = false;
			// automatic resizing may be added here!
		}
		if ($uploadOk) {
			if ($fname) {
				if (!pathinfo($fname,PATHINFO_EXTENSION)) {
					$ext = pathinfo($filename, PATHINFO_EXTENSION);
					$fname .= ".".$ext;
				}
			}
			else
				$fname = $filename;
			$fname = escapeQute(str_replace(' ','',$fname));
			$i = 1;
			while ($this->fileNameExists($fname)) {
				$name = pathinfo($fname, PATHINFO_FILENAME);
				$ext = pathinfo($filename, PATHINFO_EXTENSION);
				$fname = $name."-".$i.".".$ext;
				$i++;
			}
			$filename = sanitizeFileName($fname);
			$target_file = $this->getFolder().$filename;
			// if everything is ok, try to upload file
			if ($debugmode) writeLog("moving from=".$files["tmp_name"]." to=".$target_file);
			if (move_uploaded_file($files["tmp_name"], $target_file)) {
				// create a thumbnail
				//if ($debugmode) writeLog("creating thumbnail [".$target_file."]");
				$dest = $this->getFolder().pathinfo($target_file, PATHINFO_FILENAME)."_th.jpg";
				if (createThumb($target_file, $dest))
					$this->thumbnail = pathinfo($dest, PATHINFO_BASENAME);
				else
					$this->thumbnail = '';
				if ($debugmode) writeLog("thumb created [".$this->thumbnail."]");
				list($width, $height) = getimagesize($target_file);
				if ($width>1280 || $height>720) {
					//echo "Adjusting image size ".$target_file."\n";
					if (adjustImage($target_file, $target_file, 1280, 720))
						if ($debugmode) writeLog("Image adjustment success!".$target_file."\n");
				}
				$this->title = $title;
				$this->fileName = $filename;
				$this->category = $category;
				$this->genre = $genre;
				$this->keywords = $keywords;
				$this->reuse = $resue;
				$this->saveData();
				//if ($debugmode) echo "<br>画像を追加しました<br>";
				return true;
			}
		} else
			$message = "ファイルをアップロードできません！";

		return false;
	}

	public function delete() {
		global $debugmode;
		$resId = $this->imageId;
		if ($debugmode) echo "deleting ".$resId."<br><br>\n";
		$sql = "select filename,group_id from image where image_id=".$resId;
		$result = getDB($sql);
		if (count($result)) {
			$rec = $result[0];
			$this->fileName = $rec['filename'];
			$this->setGroupID($rec['group_id']); // fix for shared file
			unlink($this->getFilePath());
			$sql = sprintf("call delete_image(%s)", $resId);
			$result = getDB($sql,false);
		} else $message = "エラー: 削除できません！";
	}

	public function getThumbPath() {
		if ($this->thumbnail)
			return $this->getFolder().$this->thumbnail;
		else
			return $this->getFilePath();
	}

	private function fileNameExists($fname) {
		$sql = "select filename from image where filename='".$fname."' and group_id=".$this->getGroupID();
		$result = getDB($sql);

		return count($result)>0;
	}

	function getFolderName() {
		return "image";
	}
}

class musicDataClass extends itemData {
	public $musicId;
	public $fileName;
	public $duration;
	public $tga;
	public $reuse=1;
	private $musicDir;

	public function __construct($iID, $musicId, $title, $file, $ug=0) {
		$this->id = $iID;
		$this->musicId = $musicId;
		$this->setGroupID($ug);
		$this->title = $title;
		$this->fileName = $file;
		$this->tga = array();
	}

	public function getID() {
		return $this->musicId;
	}

	public function attach2Video($vID) {
		$sql = sprintf("call attach_music2Video(%u,%u)", $vID,$this->musicId);
		//echo $sql."<br>";
		$result = getDB($sql,false);
		$this->id = getLastInsertedID();
		return $result;
	}

	public function loadData($mi=null) {
		global $debugmode;

		if ($mi) {
			$this->musicId = $mi;
			$sql = "select * from music where music_id=".$mi;
		} else {
			$sql = "select * from music m join video_music v on m.music_id=v.music_id where v.video_music_id=".$this->id;
		}
		$result = getDB($sql);
		if (count($result)) {
			$rec = $result[0];
			$this->title = $rec['title'];
			$this->fileName = $rec['filename'];
			$this->category = $rec['category'];
			$this->genre = $rec['genre'];
			$this->keywords = $rec['keywords'];
			$this->duration = $rec['length'];
			$this->setGroupID($rec['group_id']?$rec['group_id']:0);
			$sql = "select tag_id from music_tag where music_id=".$mi;
			$tags = getDB($sql);
			$this->tga = array();
			foreach ($tags as $t) {
				$this->tga[] = $t['tag_id'];
			}
			$this->loaded = true;
		}
	}

	public function saveData() {
		global $debugmode;

		if (!$this->duration) {
			$fileName = $this->getFolder().$this->fileName;
			$this->duration = getDuration($fileName);
		}
		if ($this->musicId) {
			$sql = sprintf("call update_music(%u,'%s',%u,%u,'%s',%F)",
				$this->musicId,$this->title,$this->category,
				$this->genre,$this->keywords,$this->duration);
			$result = getDB($sql,false);
			// change tags
			$sql = "delete from music_tag where music_id=".$this->musicId;
			$result = getDB($sql,false);
			if (count($result) && $result[0][0]===$n2)
				if ($debugmode) echo "deleted $n2 tags<br>";
		} else {
			$sql = "insert into music (title,filename,category,genre,keywords,length,reuse,group_id)
	values (:iTitle,:iFile,:iCategory,:iGenre,:iKeywords,:iLength,:iReuse,:iGroupID);";
			$params = array (
				':iTitle'=>$this->title,
				':iFile'=>$this->fileName,
				':iCategory'=>$this->category,
				':iGenre'=>$this->genre,
				':iKeywords'=>$this->keywords,
				':iLength'=>$this->duration,
				':iReuse'=>$this->reuse,
				':iGroupID'=>$this->getGroupID()
			);
			$result = getDB($sql,false,$params);
			$this->musicId = getLastInsertedID();
			if ($debugmode) echo "New music added ".$this->musicId."<br>";
		}
		$mi = $this->musicId;
		foreach ($this->tga as $tid) {
			$sql = sprintf("call attach_tag2music(%u, %u);",$tid, $mi);
			$result = getDB($sql,false);
		}
	}

	public function upload($title,$files,$category,$genre,$tags,$reuse=1) {
		global $debugmode,$ffmpeg;

		$filename = escapeQute(str_replace(' ','',$files['name']));
		if ($debugmode) writeLog("Music upload started ".$filename);
		$uploadOk = true;
		// Check file size
		if ($files["size"] > 10000000) {
			$message = "ファイルが大きすぎです！";
			$uploadOk = false;
		}
		// Check if $uploadOk is set to 0 by an error
		if ($uploadOk) {
			$filename = sanitizeFileName($filename);
			if ($debugmode) writeLog("sanitized filename=".$filename);
			$musicFile = $this->getFolder().$filename;
			if ($debugmode) writeLog("moving from=".$files["tmp_name"]." to=".$musicFile);
			// if everything is ok, try to upload file
			if (move_uploaded_file($files["tmp_name"], $musicFile)) {
				$ext = pathinfo($filename, PATHINFO_EXTENSION);
				$fname = pathinfo($filename, PATHINFO_FILENAME);
				if ($ext!=='mp3') { // non-mp3 files will be converted
					$file = $fname.'.mp3';
					$cmd = $ffmpeg.' -i '.$musicFile.' -ab 192k '.$this->getFolder().$file;
					exec($cmd);
					unlink($musicFile);
					$filename = $file;
				}
				$this->title = $title?$title:$fname;
				$this->fileName = $filename;
				$this->category = $category;
				$this->genre = $genre;
				$this->tga = $tags;
				$this->reuse = $reuse;
				$this->saveData();
				$message = "ミュージックを追加しました";
				return true;
			} else {
				$message =  "エラーでファイルをアップロードできません！";
				switch ($files['error']) {
				case 1:
		            writeLog('The file is bigger than this PHP installation allows '.$this->getGroupID());
		            break;
		        case 2:
		            writeLog('The file is bigger than this form allows '.$this->getGroupID());
		            break;
		        case 3:
		            writeLog('Only part of the file was uploaded '.$this->getGroupID());
		            break;
		        case 4:
		            writeLog('No file was uploaded '.$this->getGroupID());
		            break;
		    	}
			}
		} else {
			$message = "ファイルをアップロードできません！";
		}
		return false;
	}

	public function delete() {
		global $debugmode;

		if ($debugmode) echo "deleting ".$this->musicId."<br><br>\n";
		$sql = "select filename,group_id from music where music_id=".$this->musicId;
		$result = getDB($sql);
		if (count($result)) {
			$rec = $result[0];
			$this->fileName = $rec['filename'];
			$this->setGroupID($rec['group_id']); // fix for shared file
			unlink($this->getFilePath());
			$sql = sprintf("call delete_music(%s);", $this->musicId);
			$result = getDB($sql,false);
		} else $message = "エラー: 削除できません！";
	}

	function getFolderName() {
		return "music";
	}
}

class videoDataClass extends itemData {
	public $videoId;
	public $fileName;
	public $duration;
	public $type;
	public $start, $end;
	public $thumbnail;
	public $reuse=1;
	public $status=0;
	private $videoDir;

	public function __construct($iID, $videoId, $title, $file, $type, $ug=0) {
		$this->id = $iID;
//		$this->folderName = "video";
		$this->videoId = $videoId;
		$this->setGroupID($ug);
		$this->title = $title;
		$this->type = $type;
		$this->fileName = $file;
	}

	public function getID() {
		return $this->videoId;
	}

	public function setData($id,$title,$file) {
		global $debugmode;

		$this->videoId = $id;
		$this->title = $title;
		$this->fileName = $file;
		$sql = "update video_video set video_id=$id where video_video_id=".$this->id;
		if ($debugmode) echo $sql."<br>";
		$result = getDB($sql,false);
		return $result;
	}

	public function attach2Video($vID) {
		// saving type here may not be necessary
		$sql = sprintf("call attach_video2Video(%u,%u,%u)",
					$vID,$this->videoId,$this->type);
		//echo $sql."<br>";
		$result = getDB($sql,false);
		$this->id = getLastInsertedID();
		return $result;
	}

	public function detachVideo() {
		if (!$this->id) return false;
		$sql = sprintf("delete from video_video where video_video_id=".$this->id);
		//echo $sql."<br>";
		$result = getDB($sql,false);
		return $result;
	}

	public function loadData($mi) {
		global $debugmode;

		$this->videoId = $mi;
		$sql = "select * from video where video_id=".$mi;
		$result = getDB($sql);
		if (count($result)) {
			$rec = $result[0];
			$this->title = $rec['title'];
			$this->fileName = $rec['filename'];
			$this->category = $rec['category'];
			$this->keywords = $rec['keywords'];
			$this->type = $rec['video_type'];
			$this->thumbnail = $rec['thumbnail'];
			$this->setGroupID($rec['group_id']?$rec['group_id']:0);
			$this->loaded = true;
		}
	}

	public function saveData() {
		global $debugmode;

		if ($this->videoId) {
			$sql = sprintf("call update_video(%u,'%s',%u,'%s',%u)",
				$this->videoId,escapeQute($this->title),$this->category,
				$this->keywords,$this->type);
			$result = getDB($sql,false);
		} else {
			$sql = "insert into video (title,filename,category,video_type,keywords,group_id,thumbnail)
	values (:iTitle,:iFile,:iCategory,:iType,:iKeywords,:iGroupID,:iThumb);";
			$params = array (
				':iTitle'=>$this->title,
				':iFile'=>$this->fileName,
				':iCategory'=>$this->category,
				':iType'=>$this->type,
				':iKeywords'=>$this->keywords,
				':iGroupID'=>$this->getGroupID(),
				':iThumb'=>$this->thumbnail
			);
			$result = getDB($sql,false,$params);
			$this->videoId = getLastInsertedID();
			//if ($debugmode) echo "New video added ".$this->videoId."<br>";
		}
	}

	public function delete() {
		global $debugmode;

		$resId = $this->videoId;
		if ($debugmode) echo "deleting ".$resId."<br><br>\n";
		$sql = "select filename,group_id from video where video_id=".$resId;
		$result = getDB($sql);
		if (count($result)) {
			$rec = $result[0];
			$this->fileName = $rec['filename'];
			$this->setGroupID($rec['group_id']); // fix for shared file
			unlink($this->getFilePath());
			if ($this->thumbnail) {
				if ($debugmode) echo "delete thumbnail<br>";
				$thumbfile = $this->getFolder().$this->thumbnail;
				unlink($thumbfile);
			}
			$sql = sprintf("call delete_video(%s)", $resId);
			$result = getDB($sql,false);
		} else $message = "エラー: 削除できません！";
	}

	public function upload($title,$files,$category,$keywords,$type,$fname='') {
		global $ffmpeg, $debugmode;

		//$filename = escapeQute(str_replace(' ','',$files['name']));
		$filename = basename($files['name']);
		$fileType = pathinfo($filename, PATHINFO_EXTENSION);
		writeLog("uploading ".$filename);
		if ($fileType!=="mp4") {
			$message = "ファイルタイプが無効です";
			return false;
			// automatic conversion may be added!
		}
		$uploadOk = true;
		// Check file size
		if ($files["size"] > 20000000) {
			$message = "ファイルが大きすぎです！20MB以下に縮小してからアップロードしてください！";
			$uploadOk = false;
			// automatic resizing may be added!
		}
		if ($uploadOk) {
			if ($fname) {
				if (!pathinfo($fname,PATHINFO_EXTENSION)) {
					$ext = pathinfo($filename, PATHINFO_EXTENSION);
					$fname .= ".".$ext;
				}
			} else
				$fname = $filename;
			$fname = sanitizeFileName($fname);
			$target_file = $this->getFolder().$fname;
			// if everything is ok, try to upload file
			if (move_uploaded_file($files["tmp_name"], $target_file)) {
				if (!$this->checkVideoSize($target_file)) {
					$message = "動画サイズが正しくないです";
					return false;
				}
				// create a thumbnail
				if ($debugmode) writeLog("creating thumbnail [".$target_file."]");
				$tname = pathinfo($fname,PATHINFO_FILENAME);
				$tfile = $tname.'.png';
				$cmd = $ffmpeg.' -i '.$target_file.' -vf "thumbnail,scale=200:112" -vframes 1 '
						.$this->getFolder().$tfile;
				$res = exec($cmd);
				if ($debugmode) echo $res;
				$this->title = $title;
				$this->fileName = $fname;
				$this->category = $category;
				$this->keywords = $keywords;
				$this->thumbnail = $tfile;
				$this->type = $type;
				$this->saveData();
				//if ($debugmode) echo "<br>画像を追加しました<br>";
				return true;
			}
		} else {
			$message = "ファイルをアップロードできません！";
		}
		return false;
	}
	
	private function checkVideoSize($inFile) {
		global $ffmpeg,$debugmode;
		
		if ($debugmode) writeLog("getting size for ".$inFile);
		$cmd = $ffmpeg.' -i '.$inFile
			." 2>&1 | grep -m 1 'Stream' | cut -d ',' -f 3";
//			." 2>&1 | grep -m 1 'Stream' | cut -d ',' -f 3 | sed -r 's/\s+([0-9]+x[0-9]+)\s.*/\1/'";
		if ($debugmode) writeLog($cmd);
		$size = substr(exec($cmd),1,8);
		if ($debugmode) writeLog("current size [".$size."]");
		return $size==="1280x720";	
	}
	
	public function resizeVideo(&$inFile, $fname) {
		global $ffmpeg,$debugmode;
		
		$fname = pathinfo($fname,PATHINFO_FILENAME)."_r.mp4";
		if ($debugmode) writeLog("resizing to ".$fname);
		$rsfile = $this->getFolder().$fname;
		$cmd = $ffmpeg.'-i '.$target_file.' -vf scale=1280:720 '.$rsfile;
		if ($debugmode) writeLog($cmd);
		$res = exec($cmd);
		//unlink($target_file);
		$inFile = $rsfile;
	}

	public function getThumbPath() {
		if ($this->thumbnail)
			return $this->getFolder().$this->thumbnail;
		else
			return $this->getFilePath();
	}

	function getFolderName() {
		return "video";
	}
}


class videoProdDataClass extends itemData {
	public $tags;
	public $videoFileName;

	public $useVoice = 1;
	public $voiceVolume = 1.0;
	public $voiceSpeed = 1.0;
	public $voicePitch = 1.0;
	public $voiceRange = 1.0;
	public $useTextFlow = 7;
	public $fontSize = 60;
	public $scrollSpeed = 0.0;
	public $musicVolume = 1.0;
	public $watermark;
	public $fps = 30;
	public $useWatermark = 0;
	public $articleChanged, $imageChanged, $musicChanged, $videoChanged;
	public $articleTextChanged;
	public $startProduction = false;

	public $quoteTitle = "";
	public $annotation = "";
	public $annoStart = 0, $annoEnd = 0;
	
	public $articleData, $imageData, $musicData, $videoData;

	private $articleBuffer='';

	public function __construct($vID=null) {
		global $userID, $userGroup;

		$this->loaded = false;
		$this->setUserID($userID);
		$this->setGroupID($userGroup);
		$this->articleChanged = false;
		$this->articleTextChanged = false;
		$this->imageChanged = false;
		$this->musicChanged = false;
		$this->videoChanged = false;
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

	public function getArticle() {
		$aData = $this->articleData->current();
		if ($aData)
			return $aData->getText();
		else
			return $this->articleBuffer;
	}

	public function setArticle($txt, $reuse=0) {
		if ($this->id) {
			if (!$this->articleData->count()) {
				$item = new articleDataClass(null,null,$this->title,$txt,$this->category,$reuse);
				$this->articleData->push($item);
				$this->articleChanged = true; // to force linking
			} else {
				if ($txt!==$this->articleData->current()->getText()) {
					$this->articleData->current()->setText($txt,$reuse);
					$this->articleTextChanged = true;
				}
			}
		} else {
			$this->articleBuffer = $txt;
			$this->articleTextChanged = true;
		}
	}

	public function setStartProduction() {
		$sql = sprintf("call start_production(%u,%u)", $this->id,$this->userID);
		$result = getDB($sql,false);
		$this->startProduction = true;
	}

	public function getMusic() {
		if ($this->musicData->count())
			return $this->musicData->current();
		else
			return '';
	}

	// deprecated method, for compatibility only
	public function getCMVideo() {
		return $this->getOpenVideo();
	}

	public function getOpenVideo() {
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
		if ($this->loaded) return;
		// get video info and article
		$sql = "SELECT title,description,category,genre,fileName,tags,displayText,useTextVoice,voiceVolume,voiceSpeed,voicePitch,voiceRange,fontSize,scrollSpeed,useWatermark,watermark_file,musicVolume,startProduction,quote_title,annotation,anno_start,anno_end,fps,user_id,group_id FROM video_info WHERE video_info_id=".$this->id;
		$result = getDB($sql);
		if (count($result)) {
			$rec = $result[0];
			$this->title = trim($rec['title']);
			$this->desc = trim($rec['description']);
			$this->tags = $rec['tags'];
			$this->useTextFlow = $rec['displayText'];
			$this->fontSize = $rec['fontSize'];
			$this->useVoice = $rec['useTextVoice'];
			$this->voiceVolume = ($rec['voiceVolume'])?$rec['voiceVolume']:1.0;
			$this->voiceSpeed = ($rec['voiceSpeed'])?$rec['voiceSpeed']:1.0;
			$this->voicePitch = ($rec['voicePitch'])?$rec['voicePitch']:1.0;
			$this->voiceRange = ($rec['voiceRange'])?$rec['voiceRange']:1.0;
			$this->musicVolume = ($rec['musicVolume'])?$rec['musicVolume']:1.0;
			$this->scrollSpeed = ($rec['scrollSpeed'])?$rec['scrollSpeed']:0.0;
			$this->videoFileName = $rec['fileName'];
			$this->quoteTitle = $rec['quote_title'];
			$this->annotation = $rec['annotation'];
			$this->annoStart = $rec['anno_start'];
			$this->annoEnd = $rec['anno_end'];
			$this->fps = ($rec['fps'])?$rec['fps']:30;
			$this->useWatermark = $rec['useWatermark'];
			$this->watermark = $rec['watermark_file'];
			$this->category = $rec['category'];
			$this->genre = $rec['genre'];
			$this->startProduction = $rec['startProduction'];
			$this->userID = $rec['user_id'];
			$this->setGroupID($rec['group_id']?$rec['group_id']:0);
			// load article
			$sql = "SELECT video_narration_id,i.article_id,i.title,i.text,i.category,i.reuse,i.group_id FROM article i JOIN video_narration vi ON i.article_id=vi.article_id WHERE vi.video_info_id=".$this->id;
			$result = getDB($sql);
			foreach ($result as $rec) {
				$id = $rec['video_narration_id'];
				$articleId = $rec['article_id'];
				$title = htmlspecialchars_decode($rec['title'],ENT_QUOTES);
				$text = htmlspecialchars_decode($rec['text'],ENT_QUOTES);
				$category = $rec['category'];
				$reuse = $rec['reuse'];
				$gi = $rec['group_id']?$rec['group_id']:0;
				$item = new articleDataClass($id,$articleId,$title,$text,$category,$reuse,$gi);
				$this->articleData->push($item);
			}
			// load images
			$sql = "SELECT video_image_id,i.image_id,i.title,i.filename,i.group_id,IFNULL(vi.image_type,0) type FROM image i JOIN video_image vi ON i.image_id=vi.image_id WHERE vi.video_info_id=".$this->id;
			$result = getDB($sql);
			foreach ($result as $rec) {
				$id = $rec['video_image_id'];
				$imageId = $rec['image_id'];
				$title = $rec['title'];
				$file = $rec['filename'];
				$gi = $rec['group_id']?$rec['group_id']:0;
				$item = new imageDataClass($id,$imageId,$title,$file,$gi);
				$item->type = $rec['type'];
				$this->imageData->push($item);
			}
			// load music
			$sql = "SELECT video_music_id,i.music_id,i.title,i.filename,i.length,i.group_id FROM music i JOIN video_music vi ON i.music_id=vi.music_id WHERE vi.video_info_id=".$this->id;
			$result = getDB($sql);
			foreach ($result as $rec) {
				$id = $rec['video_music_id'];
				$musicId = $rec['music_id'];
				$title = $rec['title'];
				$file = $rec['filename'];
				$len = $rec['length'];
				$gi = $rec['group_id']?$rec['group_id']:0;
				$item = new musicDataClass($id,$musicId,$title,$file,$gi);
				$item->duration = $len;
				$this->musicData->push($item);
			}
			// load cm videos
			$sql = "SELECT video_video_id,i.video_id,i.title,i.video_type,i.filename,i.group_id FROM video i JOIN video_video vi ON i.video_id=vi.video_id WHERE vi.video_info_id=".$this->id;
			$result = getDB($sql);
			foreach ($result as $rec) {
				$id = $rec['video_video_id'];
				$videoId = $rec['video_id'];
				$title = $rec['title'];
				$file = $rec['filename'];
				$type = $rec['video_type'];
				$gi = $rec['group_id']?$rec['group_id']:0;
				$item = new videoDataClass($id,$videoId,$title,$file,$type,$gi);
				$this->videoData->push($item);
			}
			$this->loaded = true;
		}
	}

	public function setCompletion($status=1) {
		if ($status) {
			$sql = "UPDATE video_info set startProduction=0,production_date=now(),downloaded=0 WHERE video_info_id=".$this->id;
			$result = getDB($sql,false);
		}

	}

	public function isCompleted() {
		$sql = "SELECT production_status, production_error from video_info where video_info_id=".$this->id;
		$result = getDB($sql);

		if (count($result)) {
			$rec = $result[0];
			$prodStatus = $rec['production_status'];
			if ($prodStatus>0) {
				$fileStatus = file_exists($this->getFilePath());
				if (!$fileStatus) {
					$sql = "UPDATE video_info set production_status=null where video_info_id".$this->id;
					$result = getDB($sql,false);
				}
			} else
				$fileStatus = false;

			$error = $rec['production_error'];
			if ($prodStatus>0 && !$error && $fileStatus)
				return true;
			else
				return false;
		}
		return false;
	}

	public function saveData() {
		global $userID,$userGroup,$debugmode;

		if (!$this->userID || !$this->groupID) {
			if ($debugmode) echo "<font style='color:red;'>エラー：この動画にはユーザーIDかグループIDが関連づけられておりません！</font><br>";
			if ($debugmode) echo "user id=$this->userID, group id=$this->groupID";
			if ($userID)
				$this->userID = $userID;
			if ($userGroup)
				$this->groupID = $userGroup;
		}
		//echo "saving vID= ".$vID."<BR>";
		if ($this->id) {
			$vID = $this->id;
			$sql = "update video_info
	set title=:iTitle,description=:iDesc,fileName=:iFile,category=:iCategory,genre=:iGenre,
		tags=:iTags,displayText=:iDispText,fontSize=:iFont,scrollSpeed=:iScroll,
		useTextVoice=:iUseVoice,voiceVolume=:iVoiceVol,voiceSpeed=:iVoiceSpeed,
		voicePitch=:iVoicePitch,voiceRange=:iVoiceRange, 
        musicVolume=:iMusicVol,quote_title=:iQuote,
        annotation=:iAnnoTxt,anno_start=:iAnnoSt,anno_end=:iAnnoEd,fps=:iFPS,
        useWatermark=:iUseWatermark,watermark_file=:iWatermark
	where video_info_id=".$vID;
			$params = array (
			':iTitle'=>$this->title,
			':iDesc'=>$this->desc,
			':iFile'=>$this->videoFileName,
			':iCategory'=>$this->category,
			':iGenre'=>$this->genre,
			':iTags'=>$this->tags,
			':iDispText'=>$this->useTextFlow,
			':iFont'=>$this->fontSize,
			':iScroll'=>$this->scrollSpeed,
			':iUseVoice'=>$this->useVoice,
			':iVoiceVol'=>$this->voiceVolume,
			':iVoiceSpeed'=>$this->voiceSpeed,
			':iVoicePitch'=>$this->voicePitch,
			':iVoiceRange'=>$this->voiceRange,
			':iMusicVol'=>$this->musicVolume,
			':iQuote'=>$this->quoteTitle,
			':iAnnoTxt'=>$this->annotation,
			':iAnnoSt'=>$this->annoStart,
			':iAnnoEd'=>$this->annoEnd,
			':iFPS'=>$this->fps,
			':iUseWatermark'=>$this->useWatermark,
			':iWatermark'=>$this->watermark
			);
			$result = getDB($sql,false,$params);

			if ($this->articleChanged) {
				$sql = "delete from video_narration where video_info_id=".$vID;
				$result = getDB($sql,false);
				//if ($debugmode) echo "num articles deleted = ".$this->articleData->count()."<br>";
				foreach ($this->articleData->getArray() as $iData)
					$result = $iData->attach2Video($vID);
				$this->articleChanged = false; // reset flag
			} else if ($this->articleTextChanged) {
				// if article hasn't been saved, it needs to be saved first
				$this->articleData->current()->saveData();
				$this->articleTextChanged = false;
			}

			if ($this->imageChanged) {
				$sql = "delete from video_image where video_info_id=".$vID;
				$result = getDB($sql,false);
				if (count($result) && $result[0][0]===$n2)
					if ($debugmode) echo "deleted $n2 images<br>";
				foreach ($this->imageData->getArray() as $iData)
					$result = $iData->attach2Video($vID);
				$this->imageChanged = false;
			}

			if ($this->musicChanged) {
				$sql = "delete from video_music where video_info_id=".$vID;
				$result = getDB($sql,false);
				if (count($result) && $result[0][0]===$n2)
					if ($debugmode) echo "deleted $n2 music<br>";
				foreach ($this->musicData->getArray() as $iData)
					$result = $iData->attach2Video($vID);
				$this->musicChanged = false;
			}

			if ($this->videoChanged) {
				$sql = "delete from video_video where video_info_id=".$vID;
				$result = getDB($sql,false);
				if (count($result) && $result[0][0]===$n2)
					if ($debugmode) echo "deleted $n2 video<br>";
				foreach ($this->videoData->getArray() as $iData)
					$result = $iData->attach2Video($vID);
				$this->videoChanged = false;
			}
		} else {
			$sql = "insert into video_info(title,description,fileName,category,genre,tags,
			displayText,fontSize,scrollSpeed,
			useTextVoice,voiceVolume,voiceSpeed,voicePitch,voiceRange,musicVolume,
			quote_title,annotation,anno_start,anno_end,fps,useWatermark,
			watermark_file,user_id,group_id)
	values (:iTitle,:iDesc,:iFile,:iCategory,:iGenre,:iTags,:iDispText,:iFont,:iScroll,
			:iUseVoice,:iVoiceVol,:iVoiceSpeed,:iVoicePitch,:iVoiceRange,:iMusicVol,
			:iQuote,:iAnnoTxt,:iAnnoSt,:iAnnoEd,:iFPS,
			:iUseWatermark,:iWatermark,:iUserID,:iGroupID);";
			$params = array (
			':iTitle'=>$this->title,
			':iDesc'=>$this->desc,
			':iFile'=>$this->videoFileName,
			':iCategory'=>$this->category,
			':iGenre'=>$this->genre,
			':iTags'=>$this->tags,
			':iDispText'=>$this->useTextFlow,
			':iFont'=>$this->fontSize,
			':iScroll'=>$this->scrollSpeed,
			':iUseVoice'=>$this->useVoice,
			':iVoiceVol'=>$this->voiceVolume,
			':iVoiceSpeed'=>$this->voiceSpeed,
			':iVoicePitch'=>$this->voicePitch,
			':iVoiceRange'=>$this->voiceRange,
			':iMusicVol'=>$this->musicVolume,
			':iQuote'=>$this->quoteTitle,
			':iAnnoTxt'=>$this->annotation,
			':iAnnoSt'=>$this->annoStart,
			':iAnnoEd'=>$this->annoEnd,
			':iFPS'=>$this->fps,
			':iUseWatermark'=>$this->useWatermark,
			':iWatermark'=>$this->watermark,
			':iUserID'=>$this->userID,
			':iGroupID'=>$this->groupID
			);
			$result = getDB($sql,false,$params);
			$vID = getLastInsertedID();
			$this->id = $vID;
			// create article object and store
			$this->setArticle($this->articleBuffer);
			$this->articleData->current()->saveData();
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

	public function delete() {
		// make sure this is loaded!
		if (!$this->loaded) return false;

		// delete one time files
		foreach ($this->imageData->getArray() as $iData)
			if (!$iData->reuse)
				$result = $iData->delete();
		foreach ($this->musicData->getArray() as $iData)
			if (!$iData->reuse)
				$result = $iData->delete();
		foreach ($this->videoData->getArray() as $iData)
			if (!$iData->reuse)
				$result = $iData->delete();

		$target_file = $this->getFilePath();
		unlink($target_file);
		$sql = sprintf("call delete_videoInfo(%u)", $this->id);
		$result = getDB($sql,false);

		return true;
	}

	public function deleteFile() {
		if ($this->id) {
			if (file_exists($this->getFilePath()))
				unlink($this->getFilePath());
			$sql = "update video_info set start_time=null,production_date=null where video_info_id=".$this->id;
			$result = getDB($sql,false);
		}
	}

	function getFolderName() {
		return "production";
	}

	public function getFilePath() {
		return $this->getFolder().$this->videoFileName.".mp4";
	}

	public function getBasename() {
		return $this->videoFileName.".mp4";
	}
}


class jDataClass extends itemData {
	public $tags;
	public $ytAccount;

	public $filePrefix = '';
	public $useVoice = 1;
	public $voiceVolume = 1.0;
	public $voiceSpeed = 1.0;
	public $voicePitch = 1.0;
	public $voiceRange = 1.0;
	public $useTextFlow = 7;
	public $fontSize = 60;
	public $scrollSpeed = 0.0;
	public $musicVolume = 1.0;
	public $watermark;
	public $annotation = "";
	public $annoStart = 0, $annoEnd = 0;
	public $fps;

	public $nImages = 3;
	public $cmVideo = null;
	public $endVideo = null;

	public $articleData, $imageData, $cimageData, $eimageData, $videoData;
	public $articleChanged, $imageChanged, $videoChanged;

	public $completed;
	public $completionTime;
	public $tga = array();

	public function __construct($jID=null) {
		global $userID, $userGroup;

		$this->loaded = false;
		$this->setUserID($userID);
		$this->setGroupID($userGroup);
		$this->articleChanged = false;
		$this->imageChanged = false;
		$this->videoChanged = false;
		$this->articleData = new mDataClass();
		$this->imageData = new mDataClass();
		$this->cimageData = new mDataClass();
		$this->eimageData = new mDataClass();
		$this->cmVideo = null;
		$this->endVideo = null;
		if($jID) {
			$this->id = $jID;
			$this->loadData();
		} else
			$this->id = null;
	}

	public function getCoverImages() {
		global $debugmode;
//		if ($debugmode) echo "getCoverImages called..\n";
		if ($this->imageChanged && $this->imageData->count())
			foreach($this->imageData->getArray() as $item)
				if ($item->type==1) {
//					if ($debugmode) echo "pushing cover ".$item->title."\n";
					$this->cimageData->push($item);
				}
		return $this->cimageData;
	}

	public function getEndImages() {
		global $debugmode;
//		if ($debugmode) echo "getEndImages called..\n";
		if ($this->imageChanged && $this->imageData->count())
			foreach($this->imageData->getArray() as $item)
				if ($item->type==2) {
//					if ($debugmode) echo "pushing cover ".$item->title."\n";
					$this->eimageData->push($item);
				}
		return $this->eimageData;
	}

	public function getOpenVideo() {
		return $this->cmVideo;
	}

	public function getEndVideo() {
		return $this->endVideo;
	}

	public function loadData() {
		if ($this->loaded) return;
		$sql = "select * from job where job_id=".$this->id;
		$result = getDB($sql);
		if (count($result)) {
			$rec = $result[0];
			$this->title = $rec['job_name'];
			$this->category = $rec['category'];
			$this->genre = $rec['genre'];
			$this->filePrefix = $rec['file_prefix'];
			$this->nImages = $rec['nImages']?$rec['nImages']:4; // default
			$this->useVoice = $rec['useTextVoice'];
			$this->useTextFlow = $rec['displayText']?$rec['displayText']:1.0;
			$this->fontSize = $rec['fontSize'];
			$this->voiceVolume = $rec['voiceVolume']?$rec['voiceVolume']:1.0;
			$this->voiceSpeed = $rec['voiceSpeed']?$rec['voiceSpeed']:1.0;
			$this->voicePitch = $rec['voicePitch']?$rec['voicePitch']:1.0;
			$this->voiceRange = $rec['voiceRange']?$rec['voiceRange']:1.0;
			$this->musicVolume = $rec['musicVolume']?$rec['musicVolume']:1.0;
			$this->scrollSpeed = $rec['scrollSpeed']?$rec['scrollSpeed']:0.0;
			$this->annotation = $rec['annotation'];
			$this->annoStart = $rec['anno_start'];
			$this->annoEnd = $rec['anno_end'];
			$this->fps = ($rec['fps'])?$rec['fps']:30;
			$this->userID = $rec['user_id']?$rec['user_id']:0;
			$this->groupID = $rec['group_id']?$rec['group_id']:0;
//			$coverImgId = $rec['coverImage']?$rec['coverImage']:0;
//			$endImgId = $rec['endImage']?$rec['endImage']:0;
			$cmVId = $rec['cmVideo']?$rec['cmVideo']:0;
			$endVId = $rec['endVideo']?$rec['endVideo']:0;
			// load article
			$sql = "SELECT job_article_id,i.article_id,i.title,i.text,i.category,i.reuse,i.group_id FROM article i JOIN job_article vi ON i.article_id=vi.article_id WHERE vi.job_id=".$this->id;
			$result = getDB($sql);
			foreach ($result as $rec) {
				$id = $rec['job_article_id'];
				$articleId = $rec['article_id'];
				$title = htmlspecialchars_decode($rec['title'],ENT_QUOTES);
				$text = htmlspecialchars_decode($rec['text'],ENT_QUOTES);
				$category = $rec['category'];
				$reuse = $rec['reuse'];
				$gi = $rec['group_id']?$rec['group_id']:0;
				$item = new articleDataClass($id,$articleId,$title,$text,$category,$reuse,$gi);
				$this->articleData->push($item);
			}
			// load images
			$sql = "SELECT job_image_id,i.image_id,i.title,i.filename,i.group_id,IFNULL(vi.image_type,0) type FROM image i JOIN job_image vi ON i.image_id=vi.image_id WHERE vi.job_id=".$this->id;
			$result = getDB($sql);
			foreach ($result as $rec) {
				$id = $rec['job_image_id'];
				$imageId = $rec['image_id'];
				$title = $rec['title'];
				$file = $rec['filename'];
				$gi = $rec['group_id']?$rec['group_id']:0;
				$item = new imageDataClass($id,$imageId,$title,$file,$gi);
				$item->type = $rec['type'];
				$this->imageData->push($item);
				if ($item->type==1)
					$this->cimageData->push($item);
				else if ($item->type==2)
					$this->eimageData->push($item);
			}

			if ($cmVId) {
				$this->cmVideo = new videoDataClass(null,0,'','','');
				$this->cmVideo->loadData($cmVId);
			}
			if ($endVId) {
				$this->endVideo = new videoDataClass(null,0,'','','');
				$this->endVideo->loadData($endVId);
			}
		}

		$sql = "select * from video_info i join job_video jv on i.video_info_id=jv.video_info_id where job_id=".$this->id;
		$videoList = getDB($sql);
		if (count($videoList)) {
			foreach ($videoList as $video) {
				$viID = $video['video_info_id'];
				$videoInfo = new videoProdDataClass($viID);
				$videoData[] = $videoInfo;
			}
		}

		$sql = "select * from job_tag where job_id=".$this->id;
		$tags = getDB($sql);
		$this->tga = array();
		foreach ($tags as $t) {
			$this->tga[] = $t['tag_id'];
		}
		$this->loaded = true;
	}

	public function saveData() {
		global $debugmode;

		$jID = $this->id;
		if ($jID) {
			$params = array (
				':iTitle'=>$this->title,
				':iPrefix'=>$this->filePrefix,
				':nImages'=>$this->nImages,
				':iCategory'=>$this->category,
				':iGenre'=>$this->genre,
				':iDispText'=>$this->useTextFlow,
				':iFont'=>$this->fontSize,
				':iScroll'=>$this->scrollSpeed,
				':iUseVoice'=>$this->useVoice,
				':iVoiceVol'=>$this->voiceVolume,
				':iVoiceSpeed'=>$this->voiceSpeed,
				':iVoicePitch'=>$this->voicePitch,
				':iVoiceRange'=>$this->voiceRange,
				':iMusicVol'=>$this->musicVolume,
				':iAnnoTxt'=>$this->annotation,
				':iAnnoSt'=>$this->annoStart,
				':iAnnoEd'=>$this->annoEnd,
				':iFPS'=>$this->fps
			);
			$sql = "update job
	set job_name=:iTitle,file_prefix=:iPrefix,nImages=:nImages,category=:iCategory,
		genre=:iGenre,displayText=:iDispText,useTextVoice=:iUseVoice,
		scrollSpeed=:iScroll,voiceVolume=:iVoiceVol,voiceSpeed=:iVoiceSpeed,
		voicePitch=:iVoicePitch,voiceRange=:iVoiceRange,
        musicVolume=:iMusicVol,fontSize=:iFont,
        annotation=:iAnnoTxt,anno_start=:iAnnoSt,anno_end=:iAnnoEd,fps=:iFPS
where job_id=".$jID;
			$result = getDB($sql,false,$params);

			if ($this->articleChanged) {
				$sql = "delete from job_article where job_id=".$jID;
				$result = getDB($sql,false);
				if ($debugmode) echo "num articles = ".$this->articleData->count()."<br>";
				foreach ($this->articleData->getArray() as $iData)
					$result = $iData->attach2Job($jID);
			}
			if ($this->imageChanged) {
				$sql = "delete from job_image where job_id=".$jID;
				$result = getDB($sql,false);
				if (count($result) && $result[0][0]===$n2)
					if ($debugmode) echo "deleted $n2 images<br>";
				foreach ($this->imageData->getArray() as $iData)
					$result = $iData->attach2Job($jID);
				$this->imageChanged = false;
			// this is deprecated
/*				$ciID = ($this->getCoverImage())?$this->getCoverImage()->getID():0;
				$eiID = ($this->getEndImage())?$this->getEndImage()->getID():0;
				$sql = sprintf("update job set coverImage=%u, endImage=%u where job_id=%u",$ciID,$eiID,$jID);
				$res = getDB($sql,false); */
			}
		} else {
			$params = array (
				':iTitle'=>$this->title,
				':iPrefix'=>$this->filePrefix,
				':nImages'=>$this->nImages,
				':iCategory'=>$this->category,
				':iGenre'=>$this->genre,
				':iDispText'=>$this->useTextFlow,
				':iFont'=>$this->fontSize,
				':iScroll'=>$this->scrollSpeed,
				':iUseVoice'=>$this->useVoice,
				':iVoiceVol'=>$this->voiceVolume,
				':iVoiceSpeed'=>$this->voiceSpeed,
				':iVoicePitch'=>$this->voicePitch,
				':iVoiceRange'=>$this->voiceRange,
				':iMusicVol'=>$this->musicVolume,
				':iAnnoTxt'=>$this->annotation,
				':iAnnoSt'=>$this->annoStart,
				':iAnnoEd'=>$this->annoEnd,
				':iFPS'=>$this->fps,
				':iUserID'=>$this->userID,
				':iGroupID'=>$this->groupID
			);
			$sql = "insert into job(job_name,file_prefix,nImages,category,genre,displayText,fontSize,
			scrollSpeed,useTextVoice,voiceVolume,
		voiceSpeed,voicePitch,voiceRange,musicVolume,
		annotation,anno_start,anno_end,fps,user_id,group_id)
values(:iTitle,:iPrefix,:nImages,:iCategory,:iGenre,:iDispText,:iFont,
		:iScroll,:iUseVoice,:iVoiceVol,
		:iVoiceSpeed,:iVoicePitch,:iVoiceRange,:iMusicVol,
		:iAnnoTxt,:iAnnoSt,:iAnnoEd,:iFPS,:iUserID,:iGroupID);";
			$result = getDB($sql,false,$params);
			$jID = getLastInsertedID();
			$this->id = $jID;
			// need to save all data link here
			foreach ($this->articleData->getArray() as $iData)
				$result = $iData->attach2Job($jID);
			foreach ($this->imageData->getArray() as $iData)
				$result = $iData->attach2Job($jID);
/*			if ($this->videoData)
				foreach ($this->videoData->getArray() as $iData)
					$result = $iData->attach2Job($jID); */
		}
		$sql = "delete from job_tag where job_id=".$this->id;
		$result = getDB($sql,false);
		if (count($result) && $result[0][0]===$n2)
			if ($debugmode) echo "deleted $n2 tags<br>";
		foreach ($this->tga as $tid) {
			$sql = sprintf("call attach_tag2job(%u, %u);", $tid, $this->id);
			$result = getDB($sql,false);
		}
		if ($this->videoChanged) {
			$cvID = ($this->getOpenVideo())?$this->getOpenVideo()->getID():0;
			$evID = ($this->getEndVideo())?$this->getEndVideo()->getID():0;
			$sql = sprintf("update job set cmVideo=%u, endVideo=%u where job_id=%u",$cvID,$evID,$jID);
			$res = getDB($sql,false);
		}
	}

	public function setCompletion($status=1) {
		if ($status) {
			$sql = "UPDATE job set startMakeVideos=0,completed=1,completion_time=now() WHERE job_id=".$this->id;
			$result = getDB($sql,false);
		}

		$sql = sprintf("call add_prod_log(%u,%u,%u,'%s')",$this->userID,$this->id,1,"End Rnd Production");
		$result = getDB($sql,false);
	}

	public function delete() {
		// make sure this is loaded!
		if (!$this->loaded)
			return false;

		// delete one time files
		writeLog("deleting job ".$this->id);

//		foreach ($this->imageData->getArray() as $iData)
//			if (!$iData->reuse) $iData->delete();
		if ($this->cmVideo)
			if (!$this->cmVideo->reuse) $this->cmVideo->delete();
		if ($this->endVideo)
			if (!$this->endVideo->reuse) $this->endVideo->delete();

		$sql = sprintf("call delete_job(%u)", $this->id);
		$result = getDB($sql,false);
		writeLog($sql);
		
		return true;
	}


	function getFolderName() {
		return '';
	}
}


function getDuration($file) {
	global $ffmpeg;

	if (!$file) return 0;
	$cmd = $ffmpeg." -i $file 2>&1 | grep 'Duration' | cut -d ' ' -f 4 | sed s/,//";
	$dStr = exec($cmd);
	if ($dStr) {
		$dArray = explode(':', $dStr);
		$duration = 3600*$dArray[0] + 60*$dArray[1] + $dArray[2];
	} else $duration = 0;

	return $duration;
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
		if ($i)
			return array_splice($this->mData, $i, 1);
		else
			return array_shift($this->mData);
	}

	public function deleteCurrent() {
		array_splice($this->mData, $this->index, 1);
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

	public function pop() {
		return array_pop($this->mData);
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

	public function clear($condFunction=null) {
		if ($condFunction) { // conditionally clear
			$n = count($this->mData);
			for ($i=$n-1; $i>=0; $i--)
				if ($condFunction($this->mData[$i]))
					array_splice($this->mData, $i, 1);
		} else
			$this->mData = array();
		$this->index = 0;
	}
}


function getCategoryList($category) {
	global $userGroup;

	echo "<option value='0'".(($category==0)?" selected":"").">指定なし</option>\n";
	$sql = "select * from category where group_id=0 or group_id=".$userGroup;
	$perms = getDB($sql);
	$i = 1;
	foreach ($perms as $line) {
		$id = $line['category_id'];
		$ug = $line['group_id'];
		$pname = (($ug==0)?'• ':'&nbsp;&nbsp; ').$id.' '.$line['name'];
		echo "<option value='".$id."'".(($category==$id)?" selected":"").">"
			.$pname."</option>\n";
	}
}

function getGenreList($genre) {
	global $userGroup;

	echo "<option value='0'".(($genre==0)?" selected":"").">指定なし</option>\n";
	$sql = "select * from genre where group_id=0 or group_id=".$userGroup;
	$perms = getDB($sql);
	$i = 1;
	foreach ($perms as $line) {
		$id = $line['genre_id'];
		$ug = $line['group_id'];
		$pname = (($ug==0)?'• ':'&nbsp;&nbsp; ').$id.' '.$line['name'];
		echo "<option value='".$id."'".(($genre==$id)?" selected":"").">"
			.$pname."</option>\n";
	}
}

function isSharedCategory($cId) {
	if ($cId==0) return true;
	$sql = "select category_id,group_id from category where category_id=".$cId;
	$rec = getDB($sql);
	if (count($rec) && ($rec[0]['group_id']==0)) return true;

	return false;
}

function adjustImage($src, $dest, $desired_width, $desired_height) {
	try {
		$magObj = new imageLib($src);
		if ($magObj) {
			$height = $magObj->getOriginalHeight();
			$width = $magObj->getOriginalWidth();
			if ($desired_height==0)
				$desired_height = floor($height * ($desired_width / $width));
			$magObj->resizeImage($desired_width, $desired_height, 4);
			$magObj->saveImage($dest);
			$magObj = null;
			return true;
		}
/*
		$imagick = new Imagick($src);
		$height = $imagick->getImageHeight();
		$width = $imagick->getImageWidth();
		$desired_height = floor($height * ($desired_width / $width));
		$imagick->resizeImage($desired_width, $desired_height);
		$imagick->writeImage($dest); */
		return false;
	}
	catch(Exception $e) {
		writeLog('Error when creating a thumbnail: ' . $e->getMessage());
	}
	return false;
}

function createThumb($src, $dest) {
	try {
		adjustImage($src,$dest,100,0);
/*
		$imagick = new Imagick($src);
		$imagick->thumbnailImage(100, 0);
		$imagick->writeImage($dest); */
		return true;
	}
	catch(Exception $e) {
		writeLog('Error when creating a thumbnail: ' . $e->getMessage());
	}
	return false;
}

function sanitizeFileName($fileName) {
	$string = trim($fileName);
	$string = filter_var($string, FILTER_SANITIZE_STRING);
	$string = mb_ereg_replace('[\&\^\%\$\#\(\)\s]+', '', $string);

	return $string;
}


function writeLog($message) {
	global $groupID,$userID;
	if ($userID) $groupID = $userID;
	$sql = sprintf("call save_log_debug(9,'%s',%u);",str_replace("'","",$message),$groupID);
	$rs = getDB($sql,false);
}
