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
$volume = 1.6;
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
$fadeoutLen = 3;


//echo $txtFile;
//$success = requestVoice($txtFile);


// get duration
$duration = getDuration($vFile);
$r = ceil($duration / $nImages);
$trueDuration = $r * $nImages;
$fdduration = $trueDuration - $fadeoutLen;
echo "Duration = ".$duration." (".$trueDuration.")  R = ".$r."\n";


//$fade = "'fade=out:4:5,fade=in:5:6,fade=out:9:10,fade=in:10:11'";


// creating video from series of images
$cmd = $ffmpeg." -framerate 1/$r -i $images -r 30 -c:v libx264 -pix_fmt yuv420p $ifname";
//exec($cmd);


// adding a watermark
$cmd = $ffmpeg.' -i '.$ifname.' -i '.$watermark.' -filter_complex "overlay=main_w-overlay_w-10:main_h-overlay_h" -acodec copy '.$ifname1;
exec($cmd);
//unlink($ifname3);



// adding scrolling text
//$cmd = $ffmpeg." -y -i $ifname -vf drawtext=\"fontfile=$fontFile:textfile=$txtFile:fontcolor=white:fontsize=42:y=h-45:x=w-120*t\" -b:v 9000k -maxrate 9000k -minrate 9000k -bufsize 1890k -acodec copy $ifname2";
$cmd = $ffmpeg." -y -i $ifname1 -vf drawtext='fontfile=$fontFile:textfile=$txtFile:fontcolor=white:fontsize=42:y=h-45:x=w-w*7/$trueDuration*t+120' -acodec copy $ifname2";
exec($cmd);
//unlink($ifname);


// mix voice with background music
//$cmd = $ffmpeg.' -i '.$vFile.' -i '.$music.' -filter_complex "[0:a][1:a]amerge=inputs=2[aout]" -map "[aout]" -ac 2 '.$tAudio;
/*
$cmd = $ffmpeg.' -i '.$vFile.' -i '.$music.' -filter_complex "[0:a]aformat=sample_fmts=fltp:channel_layouts=mono,volume=1.4[a1];
 [1:a]aformat=sample_fmts=fltp:channel_layouts=stereo,volume=0.1[a2]; [a1][a2]amerge=inputs=2[aout]" -map "[aout]" -ac 2 '.$tAudio; */
$cmd = $ffmpeg.' -i '.$vFile.' -i '.$music.' -filter_complex "[0:a]aformat=sample_fmts=fltp:channel_layouts=mono,volume=1.3[a1];
 [1:a]aformat=sample_fmts=fltp:channel_layouts=stereo,volume=0.2[a2];
 [a1][a2]amix=inputs=2:duration=first:dropout_transition=3[aout]" -map "[aout]" -ac 2 '.$tAudio;
exec($cmd);


/*
// adding voice only
//$cmd = $ffmpeg." -i $ifname1 -i $vFile -map 0 -map 1 -c:a aac -strict experimental -b:a 64k -shortest $ifname2";
$cmd = $ffmpeg." -i $ifname1 -i $vFile -map 0 -map 1 -c:a aac -strict experimental -b:a 64k -shortest $ifname2";
exec($cmd);
unlink($ifname1);
*/


// adding background music
$cmd = $ffmpeg." -i $ifname2 -i $tAudio -map 0:0 -map 1:0 -c:a aac -strict experimental -b:a 128k -filter:a afade=t=out:st=$fdduration:d=$fadeoutLen $ifname3";
exec($cmd);
//unlink($ifname2);



// converting the video to intermediate format
$cmd = $ffmpeg." -i $cmfname -c copy -bsf:v h264_mp4toannexb -f mpegts intermediate1.ts";
//exec($cmd);N
$cmd = $ffmpeg." -i $ifname3 -c copy -bsf:v h264_mp4toannexb -f mpegts intermediate2.ts";
exec($cmd);
//unlink($ifname4);

// concatenating cm video
$flist = '"concat:$cmfname|$ifname2"';
$cmd = $ffmpeg.' -f mpegts -i "concat:intermediate1.ts|intermediate2.ts" -c copy -bsf:a aac_adtstoasc '.$dfname;
exec($cmd);
//unlink('intermediate2.ts');



function getDuration($file) {
// get duration
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
		file_put_contents($vFile, fopen('http://sinobi2.bitnamiapp.com/ts/'.$result->url, 'r'));
		return true;
	}
	return false;
}