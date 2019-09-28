<?php
define('HOST_URL', "http://sinobi2.bitnamiapp.com:7777");
define('API_URL', "/v410/speak/");
include_once 'ini/twttool.php';
$rooturl = $ini_array['rooturl'];

$zendpath = "/home/bitnami/htdocs/script/ZendFramework-2.2.4/";
$debugmode = false;

require_once $zendpath.'library/Zend/Loader/StandardAutoloader.php';
$loader = new Zend\Loader\StandardAutoloader(array('autoregister_zf' => true));
$loader->registerNamespace('User', __FILE__ . '/../library/User');
// Register with spl_autoload:
$loader->register();

use Zend\Db\Adapter\Adapter;
use Zend\Db\Adapter\Platform;
use Zend\Db\Adapter\Driver;
use Zend\Db\ResultSet\ResultSet;
use Zend\Http\Client;


$userID = 1002;
$userLogin = 'tester3';

// security code for AITalk server
$tcode = sha1($userID.$userLogin);
$voice = 'nozomi';
$volume = 1.5;
$speed = 1.0;
$pitch = 1.0;
$range = 1.0;

// to create a video from images
// assume image files are named img001.png, img002.png, etc.

// calculate input frame rate
//$r = $videoLength / $nImages;
$ffmpeg = "/home/bitnami/bin/ffmpeg";
$images = 'movieImages/img%03d.png';
$imfiles = scandir('movieImages/');
$nImages = count($imfiles) - 2;
$music = 'music/SC-7310.wav';
$ifname = 'int.mp4';
$ifname1 = 'int1.mp4';
$ifname2 = 'int2.mp4';
$ifname3 = 'int3.mp4';
$ifname4 = 'int4.mp4';
$dfname = 'test.mp4';
$cmfname = 'cm1.mp4';
$lfname = 'filelist.txt';
$txtFile = 'news1.txt';
$fontFile = 'fonts/kanjukugothic.otf';
//$watermark = 'bear.png';
$watermark = 'newtuber.png';
$tAudio = 'temp.wav';
$vFile = 'voice.mp3';
//var_dump($imfiles);


//echo $txtFile;
//$success = requestVoice($txtFile, $vFile);


// get duration
$duration = getDuration($vFile);
$r = ceil($duration / $nImages);
$trueDuration = $r * $nImages;
$fdduration = $trueDuration - $fadeoutLen;
echo "Duration = ".$duration." (".$trueDuration.")  R = ".$r."\n";


// mix voice with background music
//mixVoiceMusic($vFile, $music, $tAudio);


// creating video from series of images
//$cmd = $ffmpeg." -framerate 1/$r -i $images -r 30 -c:v libx264 -pix_fmt yuv420p $ifname";
//exec($cmd);


// adding scrolling text
addScrollText($txtFile, $ifname, $ifname1);


// adding a watermark
addWatermark($watermark, $ifname1, $ifname2);


// adding background music
addAudio($tAudio, $ifname2, $ifname3);


// converting the video to intermediate format
$cmd = $ffmpeg." -i $cmfname -c copy -bsf:v h264_mp4toannexb -f mpegts intermediate1.ts";
//exec($cmd);N
$cmd = $ffmpeg." -i $ifname3 -c copy -bsf:v h264_mp4toannexb -f mpegts intermediate2.ts";
exec($cmd);
//unlink($ifname3);

// concatenating cm video
$flist = '"concat:$cmfname|$ifname2"';
$cmd = $ffmpeg.' -f mpegts -i "concat:intermediate1.ts|intermediate2.ts" -c copy -bsf:a aac_adtstoasc '.$dfname;
exec($cmd);
unlink('intermediate2.ts');




function addScrollText($txtFile, $inVid, $outVid) {
	global $ffmpeg, $trueDuration;
	
	$fontFile = 'fonts/kanjukugothic.otf';
	$cmd = $ffmpeg." -y -i $inVid -vf 'format=yuv444p, drawbox=y=ih-48:color=black@0.4:width=iw:height=48:t=max, drawtext=fontfile=$fontFile:textfile=$txtFile:fontcolor=white:fontsize=42:y=h-45:x=w-w*7/$trueDuration*t+120, format=yuv420p' -acodec copy $outVid";
	exec($cmd);
	//unlink($inVid);
}

function addWatermark($watermark, $inVid, $outVid) {
	global $ffmpeg;
	$cmd = $ffmpeg.' -i '.$inVid.' -i '.$watermark.' -filter_complex "overlay=main_w-overlay_w-10:main_h-overlay_h" -acodec copy '.$outVid;
	return exec($cmd);
}

function mixVoiceMusic($inVoice, $inMusic, $outFile) {
	global $ffmpeg;
	$cmd = $ffmpeg.' -i '.$inVoice.' -i '.$inMusic.' -filter_complex "[0:a]aformat=sample_fmts=fltp:channel_layouts=mono,volume=1.2[a1];
	 [1:a]aformat=sample_fmts=fltp:channel_layouts=stereo,volume=0.2[a2];
	 [a1][a2]amix=inputs=2:duration=first:dropout_transition=3[aout]" -map "[aout]" -ac 2 '.$outFile;
	exec($cmd);
	//unlink($inVoice);
}

function addAudio($inAudio, $inVideo, $outVideo) {
	global $ffmpeg,$fdduration;
	$fadeoutLen = 3;

	// adding background music
	$cmd = $ffmpeg." -i $inVideo -i $inAudio -map 0:0 -map 1:0 -c:a aac -strict experimental -b:a 128k -filter:a afade=t=out:st=$fdduration:d=$fadeoutLen $outVideo";
	exec($cmd);
	//unlink($inVideo);
}

function getDuration($file) {
	global $ffmpeg;

	$cmd = $ffmpeg." -i $file 2>&1 | grep 'Duration' | cut -d ' ' -f 4 | sed s/,//";
	$dStr = exec($cmd);
	$dArray = explode(':', $dStr);
	$duration = 3600*$dArray[0] + 60*$dArray[1] + $dArray[2];

	return $duration;
}

function requestVoice($txtFile, $vFile) {
	global $userID,$tcode,$voice,$volume,$speed,$pitch,$range;
	
	$text = file_get_contents($txtFile);
	$url = 'http://sinobi2.bitnamiapp.com/ts/tspeech.php';
	$fields = array(
		'ui' => $userID,
		'tc' => $tcode, 
		'voice' => $voice,
		'tx' => $text,
		'volume' => $volume,
		'speed' => $speed,
		'pitch' => $pitch,
		'range' => $range
	);
	
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
		file_put_contents($vFile, fopen('http://sinobi2.bitnamiapp.com/ts/'.$result->url, 'r'));
		return true;
	}
	return false;
}