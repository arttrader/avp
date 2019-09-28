<?php
require_once 'AuthController.php';

require_once('php_image_magician.php');

require_once 'videoDataClass.php';

$userID = 1002;
$userLogin = 'tester3';


class videoCreatorClass {
	private $ffmpeg;
	private $fadeoutLen = 3;
	private $trueDuration;

	public $fontFile = 'fonts/ヒラギノ角ゴ ProN W6.otf';
	public $userID, $userLogin;
	public $vWidth = 1280;
	public $vHeight = 720;
	
	public static $prodDir = 'production/';

	public $reuseArticle = false;
	
	private $imageDir;
	private $textFlow;
	private $tmpDir = 'tmp/';
	
	private $quote = '';
	
	
	public function __construct($userID, $userLogin, $vWidth=1280, $vHeight=720) {
		global $ini_array;
		
		$this->ffmpeg = $ini_array['ffmpeg'];
		$this->userID = $userID;
		$this->userLogin = $userLogin;
		$this->vWidth = $vWidth;
		$this->vHeight = $vHeight;
	}

	public function createVideo($videoDef) {
		$iFiles = array('int0.mp4','int1.mp4','int2.mp4','int3.mp4','int4.mp4','int5.mp4');
		$imageTmpDir = 'tmpImages/';
		$tmpDir = $this->tmpDir;
		$tAudio = $tmpDir.'temp.aac';
		$vFile = $tmpDir.'narration.aac';
		$dfname = self::$prodDir.$videoDef->videoFileName.'.mp4';
		$this->textFlow = $videoDef->useTextFlow;
		$this->quote = '引用元：'.$videoDef->quoteTitle." ".$videoDef->quoteUrl;

		// to create a video from images
		$this->imageDir = imageDataClass::getFolder();
		$this->prepareImages($videoDef->imageData, $imageTmpDir, $videoDef->fontSize);
		
		$images = $imageTmpDir.'img%03d.png'; // default images
		$nImages = $videoDef->imageData->count();
		
		$music = $videoDef->getMusic();
		
		$text = $videoDef->getNarration();
		
		echo "Narration Text: ".$text."\n";
		//var_dump($imfiles);

		if ($videoDef->useVoice) {
			switch ($videoDef->useVoice) {
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
			$success = $this->requestVoice($text, $vFile);

			// get duration
			$duration = $this->getDuration($vFile);
			$r = $nImages/$duration;
			$this->trueDuration = $duration;
			echo "Voice Duration = ".$duration." (".$this->trueDuration.")  R = ".$r."\n";
			
			// mix voice with background music
			$this->mixVoiceMusic($vFile, $music, $tAudio);
			//unlink($vFile);
		} else {
			// calculate input frame rate
			$r = 5; // default
			$tAudio = $music;
			$this->trueDuration = 1;
		}

		// start building actual video
		$i = 0;
		$this->makeVideoFromImages($images, $tmpDir.$iFiles[$i], $r);

		if ($videoDef->useTextFlow) {
			$this->addScrollText($text, $tmpDir.$iFiles[$i], $tmpDir.$iFiles[$i+1], $videoDef->useTextFlow, $videoDef->fontSize);
			$i++;
		}

		if ($videoDef->useWatermark) {
			$this->addWatermark($videoDef->watermark, $tmpDir.$iFiles[$i], $tmpDir.$iFiles[$i+1]);
			$i++;
		}


		$this->addAudio($tAudio, $tmpDir.$iFiles[$i], $tmpDir.$iFiles[$i+1]);
		$i++;


		if ($videoDef->getCMVideo()) {
			$this->concatVideos($videoDef->getCMVideo()->getFilePath(), $tmpDir.$iFiles[$i], $tmpDir.$iFiles[$i+1]);
			$i++;
		}
		
		if ($videoDef->getEndVideo())
			$this->concatVideos($tmpDir.$iFiles[$i], $videoDef->getEndVideo()->getFilePath(), $dfname);
		else
			rename($tmpDir.$iFiles[$i], $dfname);
		
		if (file_exists($dfname)) {
			// set creation flag and date
			$videoDef->setCompletion();
		}

	}


	function makeVideoFromImages($images, $outVid, $r=0.2) {
		if (file_exists($outVid)) unlink($outVid);
		
		$cmd = $this->ffmpeg." -framerate $r -i $images -r 29.97 -c:v libx264 -pix_fmt yuv420p ".$outVid;
		exec($cmd);
	}

	function addScrollText($text, $inVid, $outVid, $pos=1, $fs=50) {
		if (file_exists($outVid)) unlink($outVid);
	
		$bh = ceil($fs*1.18);
		
		if ($this->textFlow==1) {
			$by = "ih-".$bh;
			$fy = "h-".($bh-($bh-$fs)/2);
		} else if ($this->textFlow==2) {
			$by = "ih/2-".$bh/2;
			$fy = "h/2-".$fs/2;
		} else if ($this->textFlow==3) {
			$by = "0";
			$fy = ($bh-$fs)/2;
		}
		$fontFile = $this->fontFile;
		// has trouble with line breaks, so remove them
		$string = trim(preg_replace('/\s+/', ' ', $text)); 
		$myfile = fopen("textfile.txt", "w");
		fwrite($myfile, $string);
		fclose($myfile);
		$tl = strlen($string);
		$d = $this->trueDuration;
		$c = 0.00030*$fs*$tl/$d; // to match text and voice, when fs=42 0.01325757/42 = 0.00031565642857
		
		echo "\ntl= $tl, d= $d, ratio= ".($tl/$d)."\n\n";
		
		if (file_exists($outVid)) unlink($outVid);
		$cmd = $this->ffmpeg." -y -i $inVid -vf 'format=yuv444p, drawbox=y=$by"
			.":color=black@0.4:width=iw:height=$bh:t=max, drawtext=fontfile=".$fontFile
			.":textfile=textfile.txt:fontcolor=white:fontsize=$fs:y=$fy:x=w-w*"
			.$c."*t+60, format=yuv420p' -acodec copy $outVid";
		echo $cmd."\n";
		exec($cmd);
	}

	function addWatermark($watermark, $inVid, $outVid) {
		if (file_exists($outVid)) unlink($outVid);
		
		$cmd = $this->ffmpeg.' -i '.$inVid.' -i '.$watermark.' -filter_complex "overlay=main_w-overlay_w-10:main_h-overlay_h" -acodec copy '.$outVid;
		return exec($cmd);
	}

	function mixVoiceMusic($inVoice, $inMusic, $outFile) {
		$temp = $this->tmpDir.'tempmix.aac';
		$tempMusic = $this->tmpDir.'tempmusic.aac';
		$vVol = 2.0;
		$mVol = 0.5;
		$fl = $this->fadeoutLen;
		$d = $this->trueDuration;
		$md = $this->getDuration($inMusic);
		echo "fadeout at ".$d."\n";
		
		// delete output file if exists
		if (file_exists($temp)) unlink($temp);
		if (file_exists($outFile)) unlink($outFile);

		if ($d>$md+10) { // if music is shorter than the video we're trying to make
			if (file_exists($tempMusic)) unlink($tempMusic);
			$repeat = '-i '.$inMusic.' -filter_complex "[0:a][0:a][0:a]concat=n=3:v=0:a=1[audio]" \
-map "[audio]" -q:a 4 '.$tempMusic;
			$inMusic = $tempMusic;
		}
		$cmd = $this->ffmpeg.' -i '.$inMusic.' -af "afade=t=out:st='.($d-$fl).':d='.$fl.'" '.$temp;
		exec($cmd);
		
		$cmd = $this->ffmpeg.' -i '.$inVoice.' -i '.$temp.' -filter_complex "[0:a]aformat=sample_fmts=fltp:channel_layouts=mono,volume='.$vVol.'[a1];
 [1:a]aformat=sample_fmts=fltp:channel_layouts=stereo,volume='.$mVol.'[a2];
 [a1][a2]amix=inputs=2:duration=first:dropout_transition=1[aout]" -map "[aout]" -ac 2 '.$outFile;
		//$cmd = $ffmpeg.' -i '.$inVoice.' -i '.$temp.' -filter_complex "[0:a][1:a]amerge=inputs=2[aout]" -map "[aout]" -ac 2 '.$outFile;
		exec($cmd);
		//unlink($temp);
		//unlink($inVoice);
	}

	function addAudio($inAudio, $inVideo, $outVideo) {
/*
		// adding voice only
		//$cmd = $ffmpeg." -i $ifname1 -i $vFile -map 0 -map 1 -c:a aac -strict experimental -b:a 64k -shortest $ifname2";
		$cmd = $ffmpeg." -i $ifname1 -i $vFile -map 0 -map 1 -c:a aac -strict experimental -b:a 64k -shortest $ifname2";
		exec($cmd);
		unlink($ifname1);
*/
		if (file_exists($outVideo)) unlink($outVideo);

		// adding voice & music
		$cmd = $this->ffmpeg." -i $inVideo -i $inAudio -map 0:0 -map 1:0 -c:a aac -strict experimental -b:a 128k ".$outVideo;
		exec($cmd);
		//unlink($inVideo);
	}

	function concatVideos($video1, $video2, $outVideo) {
		// converting the video to intermediate format
/*		$cmd = $this->ffmpeg." -i $video1 -c copy -bsf:v h264_mp4toannexb -f mpegts temp1.ts";
		exec($cmd);
		//unlink($video1);
		$cmd = $this->ffmpeg." -i $video2 -c copy -bsf:v h264_mp4toannexb -f mpegts temp2.ts";
		exec($cmd);
		//unlink($video2);

		$txt = "file '$video1'\nfile '$video2'\n";

		$myfile = fopen("concat.txt", "w");
		fwrite($myfile, $txt);
		fclose($myfile);

		// concatenating cm video
		$cmd = $this->ffmpeg.' -f concat -i concat.txt -c copy '.$outVideo;
		//$cmd = $this->ffmpeg.' -i "concat:temp1.ts|temp2.ts" -c copy -bsf:a aac_adtstoasc '.$outVideo;
		//unlink('intermediate1.ts');
		//unlink('intermediate2.ts');
*/

		if (file_exists($outVideo)) unlink($outVideo);

		// concat with filter, slower but works
		$cmd = $this->ffmpeg." -i $video1 -i $video2 \
-filter_complex '[0:0] [0:1] [1:0] [1:1] concat=n=2:v=1:a=1 [v] [a]' \
-map '[v]' -map '[a]' -r 29.97 -c:v libx264 -pix_fmt yuv420p ".$outVideo;
		exec($cmd);
		
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

	function getDuration($file) {
		$cmd = $this->ffmpeg." -i $file 2>&1 | grep 'Duration' | cut -d ' ' -f 4 | sed s/,//";
		$dStr = exec($cmd);
		if ($dStr) {
			$dArray = explode(':', $dStr);
			$duration = 3600*$dArray[0] + 60*$dArray[1] + $dArray[2];
		} else $duration = 0;
		
		return $duration;
	}

	function prepareImages($imageData, $destFolder, $fs, $watermark='') {
		$cmd = "rm -rfv ".$destFolder."/*";
		exec($cmd);
		if ($imageData->getArray())
			$n = count($imageData->getArray());
		else $n = 0;
		
		$i = 1;
		foreach($imageData->getArray() as $iData) {
			$fName = $iData->fileName;
			$id = sprintf("%03d", $i);
			echo 'Processing '.$this->imageDir.$fName."\n";
			$magicianObj = new imageLib($this->imageDir.$fName);
			$magicianObj -> resizeImage($this->vWidth, $this->vHeight, 4);
			if ($watermark) {
			// *** Add watermark to bottom right, 50px from the edges
				$magicianObj->addWatermark($watermark, 'br', 0, 80);
				$magicianObj->addCaptionBox('b', 50, 0, '#000', 50);
			}
			if ($this->quote && $i==$n-1) {
				$bh = ceil($fs*1.18);
				$txtLen = strlen($this->quote);
				$fontSize = 12;
				$magicianObj->addText($this->quote,
						($this->vWidth-$txtLen*$fontSize/2).'x'.($this->vHeight-$bh-20), 
							0, '#ccc', $fontSize, 0, $this->fontFile);
			}
			echo 'Saving to '.$destFolder.'img'.$id."png\n";
			$magicianObj -> saveImage($destFolder.'img'.$id.'.png');
			$magicianObj = null;
			$i++;
		}
	}

	function requestVoice($text, $vFile, $voice='nozomi') {		
		// security code for AITalk server
		$tcode = sha1($this->userID.$this->userLogin);
		$volume = 1.8;
		$speed = 1.0;
		$pitch = 1.0;
		$range = 1.0;

		$url = 'http://sinobi2.bitnamiapp.com/ts/ytmmsp.php';
		$fields = array(
			'ui' => $this->userID,
			'tc' => $tcode,
			'voice' => $voice,
			'tx' => $text,
			'volume' => $volume,
			'speed' => $speed,
			'pitch' => $pitch,
			'range' => $range
		);
		//echo $url."\n";

		$ch = curl_init($url);
		curl_setopt($ch,CURLOPT_POST, 1);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
		curl_setopt($ch, CURLOPT_POSTFIELDS, $fields); 
		$result = curl_exec($ch);
		curl_close($ch);
		//var_dump( $result);
		$matches = array();
		preg_match('/callback\((.*)\)/', $result, $matches);
		$result = json_decode($matches[1]);
		if ($result->vstatus==='success') {
			file_put_contents($vFile, 
				fopen('http://sinobi2.bitnamiapp.com/ts/'.$result->url, 'r'));
			return true;
		}
		return false;
	}
}