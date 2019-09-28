<?php
require_once 'AuthController.php';
require_once 'videoDataClass.php';
require_once('php_image_magician.php');

class videoCreatorClass {
	private $videoID;
	private $ffmpeg;
	private $fadeoutLen = 3;
	private $trueDuration;

	public $fontFile = 'fonts/ヒラギノ角ゴ ProN W6.otf';
	public $fontFile2 = 'fonts/hiragino-kakugo-pron-w6.otf';

	public $groupID;
	public $serverID;
	public $serverType;
	
	public $vWidth = 1280;
	public $vHeight = 720;

	public $reuseArticle = false;

	private $textFlow;
	private $tmpDir;
	private $imageTmpDir;

	private $quote = '';
	private $sourceVolPath;

	private $videoDef;
	private $onPrimaryServer;
	private $rootUrl;
	private $timeTaken = 0;
	private $fps = 30;

	public function __construct($serverID=null) {
		global $rootUrl, $ffmpeg;

		if (!$serverID) die("no server ID\n");
		
		$this->serverID = $serverID;
		$this->ffmpeg = $ffmpeg;
		//  get this server's url
		$sql = "select url,server_type from production_servers where server_id=".$serverID;
		$recs = getDB($sql);
		if (count($recs)) {
			$rec = $recs[0];
			$this->rootUrl = 'http://'.$rec['url'].'/avp/';
			$this->serverType = $rec['server_type'];
		} else
			die("no correct server ID\n");
	
		//  get the primary server's url
		$sql = "select url from production_servers where server_type=0";
		$recs = getDB($sql);
		if (count($recs)) {
			$rec = $recs[0];
			$this->sourceVolPath = 'http://'.$rec['url'].'/avp/';
		} else
			$this->sourceVolPath = $ini_array['baseserver'];
		
		$this->onPrimaryServer = 
			isset($ini_array['primaryserver'])?$ini_array['primaryserver']:false;
	}

	public function createVideo($videoDef) {
		global $debugmode;
		
		$useFade = false;
		// set these first
		$this->videoDef = $videoDef;
		$this->videoID = $videoDef->id;
		$this->groupID = $videoDef->groupID;
		$this->fps = $videoDef->fps;
		
		if (!$this->startProcess($videoDef)) die("Couldn't start ".$videoDef->id);
		
		date_default_timezone_set('Asia/Tokyo');
		$startDate = date("Y-m-d H:i:s");
		$startTime = microtime(true);
		echo "\n========== Video Production Start [".$videoDef->title."] $startDate\n\n";
		$iFiles = array('int0.mp4','int1.mp4','int2.mp4','int3.mp4','int4.mp4','int5.mp4');
		if (!$videoDef->userID) {
			echo "Error no user ID\n";
			$this->errorEnd("Error no user ID in video definition");
		}
		if ($this->serverType) { // satellite server only
			if (file_exists($videoDef->getFolder())) {
				$cmd = "rm -r ".$videoDef->getFolder(); // clean up folder
				exec($cmd);
			}
			$this->createFolders($this->groupID);
		}
		$tmpDir = $videoDef->getFolder().'tmp/';
		$this->tmpDir = $tmpDir;
		$this->imageTmpDir = $videoDef->getFolder().'tmpImages/';
		$tAudio = $tmpDir.'temp.aac';
		$vFile = $tmpDir.'narration.mp3';
		$dfname = $videoDef-> getFilePath();
		// clean up working dir
		$files = glob($tmpDir.'*');
		foreach($files as $file) unlink($file);
		if (file_exists($dfname)) unlink($dfname);
		$this->textFlow = $videoDef->useTextFlow;
		if ($videoDef->quoteTitle)
			$this->quote = '引用元： '.$videoDef->quoteTitle;
		else
			$this->quote = '';

		// to create a video from images
		$nImages = $videoDef->imageData->count();
		if ($nImages) {
			$this->setProdStatus(1);

			$ok = $this->prepareImages($videoDef->imageData, $this->imageTmpDir,
					 $videoDef->fontSize, $this->textFlow);
			if (!$ok) return; // if error
			$images = $this->imageTmpDir.'img%03d.png'; // default images
		}

		if ($videoDef->getMusic()) {
			$music = $videoDef->getMusic()->getFilePath();
			$this->httpGetFile($music);
		} else
			$music = '';
		$text = $videoDef->getArticle();
		$txtLen = mb_strlen($text, 'UTF-8');
		if ($videoDef->useVoice) {
			$duration = 0;
			$this->setProdStatus(2);
			if ($txtLen>5000) {
				echo "divide text into several\n";
				// divide text into several
			}
			else {
				$try = 0;
				$success = false;
				do {
					echo "requesting voice ".$try."\n";
					$duration = $this->requestVoice($text, $vFile, $videoDef);
					if (!$success = ($duration>0)) {
						$this->setProdLog($videoDef,"AITalk failure ".$try);
						echo "AITalk failure\n";
						sleep(rand(4,10));
					}
				} while(!$success && $try++<4);
			}
			$r = 0;
			// get duration
			//$duration = getDuration($vFile);
			if ($duration>0) {
				$r = $nImages/$duration;
				$this->trueDuration = $duration;
			} else { // error
				echo "Error duration = 0\n";
				$this->errorEnd("Error duration, not completed");
			}
			echo "Voice Duration = ".$duration." (".$this->trueDuration.")  R = ".$r."\n";

		} else {
			echo "Creating video without voice\n";
			// calculate input frame rate
			$duration = $txtLen/5.2; // using approximate average
			$this->trueDuration = $duration;
			$r = $nImages/$duration;
			$vFile = '';
		}
		
		// mix voice with background music
		if ($music) {
			$this->setProdStatus(3);
			$this->mixVoiceMusic($vFile, $music, $tAudio, $videoDef->musicVolume);
		} else
			$tAudio = $vFile;

		$i = 0;
		// start building actual video
		if ($nImages) {
			echo "Make video from $nImages images\n";
			$this->setProdStatus(4);
			if ($useFade && $duration)
				$this->makeVideoFromImageswFade($images, $tmpDir.$iFiles[$i], $nImages, $duration);
			else
				$this->makeVideoFromImages($images, $tmpDir.$iFiles[$i], $r);
		}

		if ($this->textFlow) {
			echo "Adding scrolling text to $iFiles[$i]\n";
			$this->setProdStatus(5);
			$this->addScrollText($text, $tmpDir.$iFiles[$i], $tmpDir.$iFiles[$i+1],
					$this->textFlow, $videoDef->fontSize, $videoDef->scrollSpeed);
			$i++;
		}
		
		if ($videoDef->annotation && ($videoDef->annoEnd - $videoDef->annoStart)) {
			echo "Adding annotation\n";
			$this->setProdStatus(6);
			$this->addAnnotation($videoDef, $tmpDir.$iFiles[$i], $tmpDir.$iFiles[$i+1]);
			$i++;
		}

		if ($videoDef->useWatermark) {
			echo "Adding watermark\n";
			$this->setProdStatus(6);
			$this->addWatermark($videoDef->watermark, $tmpDir.$iFiles[$i],
					$tmpDir.$iFiles[$i+1]);
			$i++;
		}

		if ($tAudio) {
			echo "Adding audio to $iFiles[$i]\n";
			$this->setProdStatus(7);
			$this->addAudio($tmpDir.$iFiles[$i], $tAudio, $tmpDir.$iFiles[$i+1]);
			$i++;
		}

		if ($videoDef->getOpenVideo()) {
			echo "Adding opening video to $iFiles[$i]\n";
			$this->setProdStatus(8);
			$cmVideoFile = $videoDef->getOpenVideo()->getFilePath();
			if ($this->httpGetFile($cmVideoFile)) {
				$this->concatVideos($cmVideoFile,
						$tmpDir.$iFiles[$i],
						$tmpDir.$iFiles[$i+1]);
				$i++;
			}
		}

		if ($videoDef->getEndVideo()) {
			echo "Adding ending video to $iFiles[$i]\n";
			$this->setProdStatus(9);
			$endVideoFile = $videoDef->getEndVideo()->getFilePath();
			if ($this->httpGetFile($endVideoFile)) {
				$this->concatVideos($tmpDir.$iFiles[$i],
					$endVideoFile, $dfname);
			}
		} else {
			echo "Renaming the video $iFiles[$i] to final name\n";
			rename($tmpDir.$iFiles[$i], $dfname);
		}

		if (file_exists($dfname) && filesize($dfname)>0) {
			// copy the result video back to the server
			$this->sendFile($dfname);
			// set creation flag and date
			$videoDef->setCompletion(1);
			echo "\nVideo completed $dfname\n";
		} else {
			if ($debugmode) 
				echo "\nSomehow final file creation failed, last file was $iFiles[$i]\n";
			$this->errorEnd("Error, not completed");
		}
		$endTime = microtime(true);
		$this->timeTaken = $endTime - $startTime;
		$this->stopProcess($this->videoDef);
		$this->setProdLog($videoDef,"End Production: ".$this->rootUrl);
		$eTime = date("Y-m-d H:i:s");
		echo "\n========== Video Production End [".$videoDef->title."] $eTime\n\n\n";
		sleep(2); // to give enough time to transfer a file
		// if success
		if ($videoDef->isCompleted())
			$this->incrementCount($videoDef->userID);
		if ($this->serverType) { // satellite server only
			if (file_exists($videoDef->getFolder())) {
				$cmd = "rm -r ".$videoDef->getFolder(); // clean up folder
				exec($cmd);
			}
		}
	}

	function errorEnd($message) {
		$this->setPS2Error();
		$this->stopProcess($this->videoDef);
		$this->setProdLog($this->videoDef, $message);
		$eTime = date("Y-m-d H:i:s");
		exit("\n========== Video Production End with error [".$this->videoDef->title."] $eTime\n\n\n");
	}

	function divideText($text, &$textArr) {
		
	}

	function makeVideoFromImages($images, $outVid, $r) {
		if (file_exists($outVid)) unlink($outVid);

		$cmd = $this->ffmpeg." -framerate $r -i $images -r ".$this->fps." -c:v libx264 -pix_fmt yuv420p ".$outVid;
		exec($cmd);
	}

	function makeVideoFromImageswFade($images, $outVid, $n, $duration) {
		// does not work yet, needs some work
//		if (file_exists($outVid)) unlink($outVid);

		$ed = $duration/$n;
		$fd = ($ed<2)?($ed*.05):0.8;
		$nd = $ed-$fd;
		$imageList = '';
		$filterList = '';
		$hl = '';
		for($i=0;$i<$n;$i++) {
			$imageList .= '-loop 1 -i '.sprintf($images, $i+1)." \ \n";
			$filterList .= '['.$i.':v]trim=duration='.$ed.',fade=t=in:st=0:d='.$fd.',fade=t=out:st='.$nd.':d='.$fd.'[v'.$i."]; \ \n";
			$hl .= '[v'.$i.']';
		}
		$cmd = $this->ffmpeg.' -r '.$this->fps.' '.$imageList.' -filter_complex "'.$filterList.$hl
				.'concat=n='.$n.':v=1:a=0,format=yuv420p[v]" -map "[v]" '.$outVid;
		echo "-------------------------\n";
		echo $cmd."\n";
		echo "-------------------------\n";
		exec($cmd);
	}

	function addScrollText($text, $inVid, $outVid, $pos=1, $fs, $speed=1) {
		$tmpTextFile = $this->tmpDir."textfile.txt";

//		if (file_exists($outVid)) unlink($outVid);

		$bh = ceil($fs*1.18);

		switch ($pos) {
		case 1:
			$by = "ih-".$bh;
			$fy = "h-".($bh-($bh-$fs)/2);
			break;
		case 2:
			$by = "ih/2-".$bh/2;
			$fy = "h/2-".$fs/2;
			break;
		case 3:
			$by = "0";
			$fy = ($bh-$fs)/2;
			break;
		case 4: // 1/6 from top
			$by = "ih/6-".$bh/2;
			$fy = "h/6-".$fs/2;
			break;
		case 5: // 1/3 from top
			$by = "ih/3-".$bh/2;
			$fy = "h/3-".$fs/2;
			break;
		case 6: // 2/3 from top
			$by = "ih*2/3-".$bh/2;
			$fy = "h*2/3-".$fs/2;
			break;
		case 7: // 5/6 from top
			$by = "ih*5/6-".$bh/2;
			$fy = "h*5/6-".$fs/2;
			break;
		}
		$fontFile = $this->fontFile;
		// has trouble with line breaks, so remove them
		$string = strip_tags(trim(preg_replace('/\s+/', ' ', $text)));
		$myfile = fopen($tmpTextFile, "w");
		fwrite($myfile, $string);
		fclose($myfile);
		$tl = mb_strlen($string, 'UTF-8');
		$a = 19.79346;
		$b = 0.9685671;
		$c = -0.00002595846;
		$tla = $a+$b*$tl+$c*$tl*$tl;
		echo "tla = ".$tla."\n";
		$d = $this->trueDuration;
		// to match text and voice, when fs=42 0.01325757/42 = 0.00031565642857 ,
		// this value is almost doubled since changing to mb_strlen
		if ($speed)
			$cf = (1+$speed/10)*0.0008*$fs*$tla/$d;
		else
			$cf = 0.0008*$fs*$tla/$d;
		// note: still a little too fast when the text is mixed with number or roman
		// 2nd degree paranomial to fit the required curve closer

		echo "\ntl= $tl, d= $d, ratio= ".($tl/$d)."\n\n";

		if (file_exists($outVid)) unlink($outVid);

		$cmd = $this->ffmpeg." -y -i $inVid -vf 'format=yuv444p, drawbox=y=$by"
			.":c=black@0.4:w=iw:h=$bh:t=max, drawtext=fontfile=".$fontFile
			.":textfile=".$tmpTextFile.":fontcolor=white:fontsize=$fs:y=$fy:x=w-w*"
			.$cf."*t+60, format=yuv420p' -acodec copy $outVid";
		echo $cmd."\n";
		exec($cmd);
		if (!file_exists($outVid)) {
			$this->errorEnd("Error adding text scroll to ".$inVid);
		}
	}

	function addAnnotation($vRef, $inVid, $outVid) {
		$antxtArr = explode("\n", $vRef->annotation);
		$as = $vRef->annoStart;
		$ae = $vRef->annoEnd;
		$pos = $vRef->useTextFlow;
		$fs = $vRef->fontSize;
		var_dump($antxtArr);
		// need to calculate width of text here and set the size of box accordingly
		$nlines = count($antxtArr);
		echo "Annotation has $nlines lines\n";
		$w = mb_strlen($antxtArr[0],'utf8');
		if ($nlines>1)
			for ($i=1; $i<$nlines; $i++)
				if (mb_strlen($antxtArr[$i],'utf8')>$w) 
					$w = mb_strlen($antxtArr[$i],'utf8');
		$tw = $w*30 + 20;
		$th = $nlines*30 + 20;
		// need to set position based on text scroll position
		$bh = ceil($fs*1.18);
		switch ($pos) {
		case 1:
			$by = "ih-".($bh + $th + 30);
			$fy = "h-".($bh + $th + 22);
			break;
		case 7: // 5/6 from top
			$by = "ih/2+".(22 - $bh);
			$fy = "h/2+".(30 - $bh);
			break;
		default:
			$by = "ih-".($th + 30);
			$fy = "h-".($th + 22);
		}
		$annoCode = ",drawbox=enable='between(t,$as,$ae)':x=iw/2-$tw/2:y=$by:w=$tw:h=$th:c=black@0.5:t=max";
		for ($i=0; $i<$nlines; $i++) {
			$annoCode .= ",drawtext=enable='between(t,$as,$ae)':fontsize=30:fontcolor=white:fontfile=".$this->fontFile.":text='".$antxtArr[$i]."':x=(w-tw)/2:y=".$fy."+".($i*30);
		}
		$cmd = $this->ffmpeg." -y -i $inVid -vf \"format=yuv444p $annoCode, format=yuv420p\" -acodec copy ".$outVid;
		echo $cmd."\n";
		exec($cmd);
		if (!file_exists($outVid))
			$this->errorEnd("Error adding annotation to ".$inVid);
	}

	function addWatermark($watermark, $inVid, $outVid) {
//		if (file_exists($outVid)) unlink($outVid);

		$cmd = $this->ffmpeg.' -i '.$inVid.' -i '.$watermark.' -filter_complex "overlay=main_w-overlay_w-10:main_h-overlay_h" -acodec copy '.$outVid;
		exec($cmd);
		if (!file_exists($outVid))
			$this->errorEnd("Error adding watermark to ".$inVid);
	}
	
	function mixVoiceMusic($inVoice, $inMusic, &$outFile, $musicVolume=1.0) {
		$temp = $this->tmpDir.'tempmix.aac';
		$vVol = 2.0;
		$mVol = 0.5*$musicVolume;
		$fl = $this->fadeoutLen;
		$d = $this->trueDuration;
		$md = getDuration($inMusic);
		echo "fadeout at ".$d."\n";

		// delete output file if exists
		if (file_exists($temp)) unlink($temp);
		if (file_exists($outFile)) unlink($outFile);

		if ($d>$md+10) { // if music is shorter than the video we're trying to make
			echo "Music is shorter than necessary, so loop it\n";
			$this->extendMusic($inMusic,$d,$md);
		}
		echo "Creating fadeout\n";
		$cmd = $this->ffmpeg.' -t '.($d+1).' -i '.$inMusic.' -af "afade=t=out:st='.($d-$fl).':d='.$fl.'" '.$temp;
		echo $cmd."\n";
		exec($cmd);

		if ($inVoice) {
			echo "Mixing voice with music\n";
			$cmd = $this->ffmpeg.' -i '.$inVoice.' -i '.$temp.' -filter_complex "[0:a]aformat=sample_fmts=fltp:channel_layouts=mono,volume='.$vVol.'[a1];
	 [1:a]aformat=sample_fmts=fltp:channel_layouts=stereo,volume='.$mVol.'[a2];
	 [a1][a2]amix=inputs=2:duration=first:dropout_transition=1[aout]" -map "[aout]" -ac 2 '.$outFile;
 		} else {
 			// adjust volume
 			$cmd = $this->ffmpeg.' -i '.$temp.' -af "volume='.$musicVolume.'" '.$outFile;
 		}
 		echo $cmd."\n";
		exec($cmd);
//		unlink($inMusic);
		if (!file_exists($outFile)) {
			echo "there was an error mixing voice with music!\n";
			$this->errorEnd("Error mixing voice ".$inVoice." and music ".$inMusic);
		}
	}

	function extendMusic(&$inMusic,$d,$md) {
		$tempMusic = $this->tmpDir.'tempmusic.aac';
		if (file_exists($tempMusic)) unlink($tempMusic);
		$n = ceil($d/$md);
		$string = "";
		for ($i=0;$i<$n;$i++)
			$string .= "file '".getcwd().'/'.$inMusic."'\n";
		$concatFile = $this->tmpDir."concat.txt";
		$myfile = fopen($concatFile, "w");
		fwrite($myfile, $string);
		fclose($myfile);
		$cmd = $this->ffmpeg.' -f concat -i '.$concatFile.' -c copy '.$tempMusic;
		echo $cmd."\n";
		exec($cmd);
		$inMusic = $tempMusic;
	}

	function addAudio($inVideo, $inAudio, $outVideo) {
//		if (file_exists($outVideo)) unlink($outVideo);

		// adding voice & music
		$cmd = $this->ffmpeg." -i $inVideo -i $inAudio -map 0:0 -map 1:0 -c:a aac -strict experimental -b:a 128k ".$outVideo;
		exec($cmd);
		
		if (!file_exists($outVideo)) {
			echo "there was an error with adding audio!\n";
			$this->errorEnd("Error adding audio ".$inAudio." to ".$inVideo);
		}
	}

	function concatVideos($video1, $video2, $outVideo) {
		if (file_exists($outVideo)) unlink($outVideo);
		
		// get fps
		//$cmd = $this->ffmpeg.'-i '.$video1.' | sed -n "s/.*, \(.*\) fps.*/\1/p"';
		$cmd = $this->ffmpeg.'-i '.$video1." 2>&1 | grep -m 1 'Stream' | cut -d ',' -f 6 | sed -r 's/\s(.*) fps.*/\1/";
		$v1fps = exec($cmd);
		echo "current v1 fpm ".$v1fps;
		if ($v1fps<30 && $this->fps==60) { // convert input video
			$convtmp = $this->tmpDir.'convtmp.mp4';
			$cmd = $this->ffmpeg.' -i '.$video1.' -r '.$this->fps.' '.$convtmp;
			exec($cmd);
			if (file_exists($convtmp)) $video1 = $convtmp;
		}
		
		// concat with filter, slower but works
/*		$cmd = $this->ffmpeg." -i $video1 -i $video2 \
-filter_complex '[0:0] [0:1] [1:0] [1:1] concat=n=2:v=1:a=1 [v] [a]' \
-map '[v]' -map '[a]' -r '.$this->fps.' -c:v libx264 -pix_fmt yuv420p ".$outVideo; */
		$cmd = $this->ffmpeg." -i $video1 -i $video2 \
-filter_complex '[0:0] [0:1] [1:0] [1:1] concat=n=2:v=1:a=1 [v] [a]' \
-map '[v]' -map '[a]' -c:v libx264 -pix_fmt yuv420p ".$outVideo;
		exec($cmd);

		// check to make sure it was a success
		if (file_exists($outVideo) && filesize($outVideo)<1) {
			echo "there was an error concatenating video!\n";
			unlink($outVideo);
			$this->errorEnd("Error concatenating ".$video1." and ".$video2);
		}

/*
		// concatenate three videos
$cmd = <<<EOF
mkfifo temp1 temp2 temp3
ffmpeg -i GOPR0009.mp4 -c copy -bsf:v h264_mp4toannexb -f mpegts temp1 2> /dev/null & \
ffmpeg -i GP010009.mp4 -c copy -bsf:v h264_mp4toannexb -f mpegts temp2 2> /dev/null & \
ffmpeg -i GP020009.mp4 -c copy -bsf:v h264_mp4toannexb -f mpegts temp3 2> /dev/null & \
ffmpeg -f mpegts -i "concat:temp1|temp2|temp3" -c copy -bsf:a aac_adtstoasc Cam01.mp4
EOF;
*/
		//$cmd = "mencoder -oac copy -ovc copy body1.avi transition.avi body2.avi -o finalmovie.avi";
	}

	function prepareImages($imageData, $destFolder, $fs, $pos, $watermark='') {
		global $videoID;

		$cmd = "rm -rfv ".$destFolder."/*";
		exec($cmd);
		if ($imageData->getArray())
			$n = count($imageData->getArray());
		else $n = 0;

		$i = 1;
		foreach($imageData->getArray() as $iData) {
			$fName = $this->sourceVolPath.$iData->getFilePath();
			$id = sprintf("%03d", $i);
			echo 'Processing image '.$fName."\n";
			try {
				$magicianObj = new imageLib($fName);
				$w = $magicianObj->getWidth();
				$h = $magicianObj->getHeight();
				if ($this->vWidth!=$w || $this->vHeight!=$h)
					$magicianObj->resizeImage($this->vWidth, $this->vHeight, 4);
				if ($watermark) {
				// *** Add watermark to bottom right, 50px from the edges
					$magicianObj->addWatermark($watermark, 'br', 0, 80);
					$magicianObj->addCaptionBox('b', 50, 0, '#000', 50);
				}
				if ($this->quote && $i==$n) {
					$bh = ceil($fs*1.18);
					$txtLen = mb_strlen($this->quote, 'UTF-8');
					$fontSize = 26;
					$vAdjust = round($fontSize*1.9); // adjust this when changing the font size
					if ($pos==1)
						$y = $this->vHeight-$bh-$vAdjust-4;
					else
						$y = $this->vHeight-$vAdjust-4;
					$x = $this->vWidth-round(1.1*$txtLen*$fontSize)-80;
					if ($x<5) $x = 5;
					$pos = $x.'x'.$y;
					$fontColor = array(
						'r' => 198,
						'g' => 198,
						'b' => 198,
						'a' => 20
					);
					$magicianObj->addText($this->quote, $pos,
							4, $fontColor, $fontSize, 0, $this->fontFile);
				}
				echo 'Saving to '.$destFolder.'img'.$id.".png\n";
				$magicianObj -> saveImage($destFolder.'img'.$id.'.png');
			} catch (ImageMagicianException $e) {
				$magicianObj = null;
				$this->errorEnd($e->getMessage());
				return false;
			}
			$magicianObj = null;
			$i++;
		}
		return true;
	}

	function requestVoice($text, $vFile, $vDef) {
		// security code for AITalk server
//		$tcode = sha1($this->userID.$this->userLogin);
		switch ($vDef->useVoice) {
		case 1:
			$voice = 'seiji';
			break;
		case 2:
			$voice = 'osamu';
			break;
		case 3:
			$voice = 'nozomi';
			break;
		case 4:
			$voice = 'sumire';
			break;
		default:
			$voice = 'nozomi';
		}
		$volume = 1.8*$vDef->voiceVolume;
		$speed = $vDef->voiceSpeed;
		$pitch = $vDef->voicePitch;
		$range = $vDef->voiceRange;

		$url = 'http://sinobi2.bitnamiapp.com/ts/ytmmsp.php';
		$fields = array(
			'ui' => $vDef->getGroupID(),
//			'tc' => $tcode,
			'voice' => $voice,
			'tx' => $text,
			'volume' => $volume,
			'speed' => $speed,
			'pitch' => $pitch,
			'range' => $range,
			'af' => 1
		);
		//echo $url."\n";

		$ch = curl_init($url);
		curl_setopt($ch,CURLOPT_POST, 1);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
		curl_setopt($ch, CURLOPT_POSTFIELDS, $fields);
		$result = curl_exec($ch);
		curl_close($ch);
		//var_dump($result);
		$matches = array();
		preg_match('/callback\((.*)\)/', $result, $matches);
		$result = json_decode($matches[1]);
		if ($result->vstatus==='success') {
			$try = 0;
			do {
				$success = copy('http://sinobi2.bitnamiapp.com/ts/'.$result->url,$vFile);
			} while (!$success && $try++<4);
			if ($success)
				$duration = getDuration($vFile);
			else
				$duration = 0;
			return $duration;
		}
		return 0;
	}

	function startProcess($vDef) {
		$sql = sprintf("call start_process(1,%u,%u,%u,'%s')",$vDef->id,$vDef->userID,$this->serverID,$vDef->title);
		$result = getDB($sql);
		if (count($result))
			$status = array_shift($result[0]);
		else
			$status = -1;
		if ($status<0) return false;
		
		//if ($debugmode) var_dump($result);
		$sql = "update video_info set start_time=now(),startProduction=0, production_error=0,downloaded=0 where video_info_id=".$vDef->id;
		$result = getDB($sql,false);
		$this->setProdLog($vDef,"Start Production: ".$this->rootUrl);
		
		return true;
	}

	function stopProcess($vDef) {
		$sql = sprintf("update state_store set done=1,stop_time=now()
		where user_id=%u and prod_server_id=%u and done<1;", $vDef->userID,$this->serverID);
		$result = getDB($sql,false);
		$sql = "update production_servers set last_process_time=".$this->timeTaken.", timestamp=now(),last_status=".$vDef->status." where server_id=".$this->serverID;
		$result = getDB($sql,false);
	}

	function setProdLog($vDef, $desc) {
		$sql = sprintf("call add_prod_log(%u,%u,%u,'%s')", $vDef->userID,$vDef->id,$this->serverID,$desc);
		$result = getDB($sql,false);
	}

	function setProdStatus($status) {
		$sql = "call set_production_status($this->videoID,$status);";
		$result = getDB($sql,false);
		$this->videoDef->status = $status;
	}

	function setPS2Error() {
		$sql = "call set_ps2error($this->videoID)";
		$result = getDB($sql,false);
		$this->videoDef->status = -$this->videoDef->status;
	}

	function incrementCount($userID) {
		if ($this->fps>30) $count=2;
		else $count=1;
		$sql = "call set_prod_count($userID,$count)";
		$result = getDB($sql,false);
	}

	function httpGetFile(&$fpath) {
		$url = $this->sourceVolPath.$fpath;
		$dest = $this->tmpDir.pathinfo($fpath, PATHINFO_BASENAME);
//		$success = file_put_contents($dest, fopen($url,'r'));
		$success = copy($url,$dest);
		if ($success)
			$fpath = $dest;
		return $success;
	}

	function sendFile($fname) {
		if ($this->serverType==0) return true;

		$url = $this->sourceVolPath.'receiveVideo.php';
		$fields = array(
			'f' => $fname,
			'i' => $this->groupID,
			'v' => $this->videoID,
			'u' => $this->rootUrl // .'user'.$id.'/production/'.$fname
		);

		//var_dump($fields);
		echo "sending file ".$url."\n";

		$ch = curl_init($url);
		curl_setopt($ch,CURLOPT_POST, 1);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
		curl_setopt($ch, CURLOPT_POSTFIELDS, $fields);
		$result = curl_exec($ch);
		curl_close($ch);

		return true;
	}

	function createFolders($ug) {
		$mdir1 = "users/user".$ug;
		$mdir2 = $mdir1."/production";
		$mdir3 = $mdir2."/tmp";
		$mdir4 = $mdir2."/tmpImages";
//		$mdir5 = $mdir1."/music";
//		$mdir6 = $mdir1."/video";
//		$mdir7 = $mdir1."/image";

		// create directories
		mkdir($mdir1);
		mkdir($mdir2);
		mkdir($mdir3);
		mkdir($mdir4);
//		mkdir($mdir5);
//		mkdir($mdir6);
//		mkdir($mdir7);

		// make all the directories writable
		exec('chmod 777 $(find '.$mdir1.' -type d)');
	}
}
