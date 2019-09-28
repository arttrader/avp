<?php
require_once 'AuthController.php';
require_once 'videoDataClass.php';


class rndVideoGeneratorClass {
	private $jobID;
	private $jobObj;

	public function __construct($jID) {
		$this->jobID = $jID;
		$this->jobObj = new jDataClass($jID);
	}

	public function prodVideos() {
		echo "\n\ncreating videos for Job id= ".$this->jobID."\n";
		date_default_timezone_set('Asia/Tokyo');
		echo "Time ".date('m/d/Y h:i:s a', time())."\n\n";
		$sql = sprintf("call set_job_start(%u)",$this->jobID);
		$result = getDB($sql,false);
		$this->setProdLog("Start Rnd Production");

		$artclList = $this->getArticles($this->jobID);
		//var_dump($artclList);

		$n = $this->jobObj->nImages;
		$vi = 1;
		foreach($artclList as $ar) {
			$videoInfo = new videoProdDataClass();
			// transfer all the parameters from the job
			$this->transferParams($this->jobObj, $videoInfo);
			$videoInfo->startProduction = false; // for now

			$arId = $ar['article_id'];
			$articleObj = new articleDataClass(0,$arId,'','',0);
			$articleObj->loadData();
			$videoInfo->title = $ar['title']; // title, category must be set before
			$videoInfo->category = $ar['category']; // setting article
			$videoInfo->setArticle($ar['text'], 0); // this will create a new article. this may need a rethinking!
			$videoInfo->quoteTitle = $ar['quote'];
//			$videoInfo->articleData->push($articleObj);
//			$videoInfo->articleChanged = true;
			echo "creating video... ".$videoInfo->title."\n";

			// set file name
			echo "set file name\n";
			
			$done = false;
			while (!$done) {
				$id = sprintf("%03d", $vi);
				$videoInfo->videoFileName = $this->jobObj->filePrefix."_".$id;
				$sql = "select video_info_id from video_info where group_id=".$this->jobObj->groupID." and fileName='".$videoInfo->videoFileName."'";
				$result = getDB($sql);
				if (count($result)>0)
					$vi++;
				else
					$done = true;
			}
			// set images
			echo "set images\n";
			
			// Cover images
			$idc = $this->jobObj->imageData->count();
			echo "image count = ".$idc."\n";
			$this->jobObj->imageData->rewind();
			for ($i=0; $i<$idc; $i++) {
				if ($this->jobObj->imageData->current()->type==1) {
					$im = $this->jobObj->imageData->current();
					echo "Adding cover ".$im->id." image ".$im->imageId."  ".$im->title."\n";
					$videoInfo->imageData->push($im);
				}
				$this->jobObj->imageData->next();
			}
			
			$images = $this->getImages($n, $this->jobObj->category);
			//var_dump($images);
			foreach($images as $im) {
				$videoInfo->imageData->push($im);
			}
			
			// Ending Images
			$this->jobObj->imageData->rewind();
			for ($i=0; $i<$idc; $i++) {
				if ($this->jobObj->imageData->current()->type==2) {
					$im = $this->jobObj->imageData->current();
					echo "Adding end ".$im->id." image ".$im->imageId."  ".$im->title."\n";
					$videoInfo->imageData->push($im);
				}
				$this->jobObj->imageData->next();
			}

			// set music
			$tga = $this->jobObj->tga;

			$music = $this->getMusic($tga, $videoInfo->category);
			echo "music id=".$music->getID()." is being added.\n\n";
			//var_dump($music);
			$videoInfo->musicData->push($music);

			// set videos
			if ($this->jobObj->getOpenVideo()) {
				$videoInfo->videoData->push($this->jobObj->getOpenVideo());
			}
			if ($this->jobObj->getEndVideo()) {
				$videoInfo->videoData->push($this->jobObj->getEndVideo());
			}

			// store the videoProdDataClass
			$videoInfo->saveData();
			// set article used flag
			$sql = "update article set used=1 where article_id=".$arId;
			$result = getDB($sql,false);

			$vi++;
		}
		$this->jobObj->setCompletion();
	}

	private function getArticles($jID) {
		$sql = "select * from article a join job_article j on a.article_id=j.article_id where j.job_id=".$jID;

		echo $sql."\n";
		$result = getDB($sql);
		if (count($result))
			return $result;
		else
			$this->setError(-1,"Error, no article found for this job");
	}

	private function getImages($n, $cat, $gen=0) {
		$groupID = $this->jobObj->groupID;

		$imageArr = array();
		if ($cat)
			$catSql = "category=$cat and ";
		else
			$catSql = '';
		if ($gen)
			$genSql = "genre=$gen and ";
		else
			$genSql = '';
		$sql = "select image_id from image where ".$catSql.$genSql."reuse=1 and group_id in (0,$groupID)";
		echo $sql."\n";
		$result = getDB($sql);
		//var_dump($result);
		//$recs = array_rand($result, $n); // this causes problem often
		if (count($result)) {
			$recs = array();
			for($i=0; $i<$n; $i++) {
				$idx = rand(0, count($result)-1);
				$id = $result[$idx]['image_id'];
				$image = new imageDataClass(null,0,'','');
				$image->loadData($id);
				$imageArr[] = $image;
			}
		} else
			$this->setError(-2,"Error, no images found for this category");

		return $imageArr;
	}

	private function getMusic($tga, $category=null, $genre=null) {
		$groupID = $this->jobObj->groupID;

		if (count($tga)) {
			$tags = implode(",", $tga);
			$sql = "select m.music_id from music m join music_tag mt on m.music_id=mt.music_id where mt.tag_id in($tags) and group_id in (0, $groupID)";
		} else
			$sql = "select music_id from music where group_id in (0, $groupID)";
		echo $sql."\n\n";
		$result = getDB($sql);
		if (count($result)) {
			//var_dump($result);
			//$id = array_rand($result,1);
			$idx = rand(0, count($result)-1);
			$id = $result[$idx]["music_id"];
	//		var_dump($id);
			$music = new musicDataClass(null,$id,'','');
			$music->loadData($id);
			return $music;
		}
	}

	private function setError($errNo, $message) {
			$sql = "update job set startMakeVideos=0,start_time=null,completed=$errNo where job_id=".$this->jobID;
			$result = getDB($sql,false);
			$this->setProdLog($message);
			die($message."\n");
	}

	private function transferParams(&$jObj, &$vObj) {
		$vObj->userID = $jObj->userID;
		$vObj->groupID = $jObj->groupID;
		$vObj->category = $jObj->category;
		$vObj->genre = $jObj->genre;
		$vObj->tags = $jObj->tags;
		$vObj->useVoice = $jObj->useVoice;
		$vObj->voiceVolume = $jObj->voiceVolume;
		$vObj->voiceSpeed = $jObj->voiceSpeed;
		$vObj->voicePitch = $jObj->voicePitch;
		$vObj->voiceRange = $jObj->voiceRange;
		$vObj->useTextFlow = $jObj->useTextFlow;
		$vObj->fontSize = $jObj->fontSize;
		$vObj->scrollSpeed = $jObj->scrollSpeed;
		$vObj->musicVolume = $jObj->musicVolume;
		$vObj->useWatermark = ($jObj->watermark)?1:0;
		$vObj->watermark = $jObj->watermark;
		$vObj->annotation = $jObj->annotation;
		$vObj->annoStart = $jObj->annoStart;
		$vObj->annoEnd = $jObj->annoEnd;
		$vObj->fps = $jObj->fps;
		$vObj->groupID = $jObj->groupID;
	}

	function setProdLog($desc) {
		$sql = sprintf("call add_prod_log(%u,%u,%u,'%s')", $this->jobObj->groupID,$this->jobID,1,$desc);
		$result = getDB($sql,false);
	}
}
