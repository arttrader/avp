<?php
define('HOST_URL', "http://avp.example.com:7777");
define('API_URL', "/v410/speak/");
include_once 'ini/avptool.php'; // setup file
$rooturl = $ini_array['rooturl'];
$debugmode = false;

$zendpath = "/home/bitnami/htdocs/script/ZendFramework-2.2.4/";

require_once $zendpath.'library/Zend/Loader/StandardAutoloader.php';
$loader = new Zend\Loader\StandardAutoloader(array('autoregister_zf' => true));
$loader->registerNamespace('User', __FILE__ . '/../library/User');
// Register with spl_autoload:
$loader->register();

use Zend\Http\Client;


if ($_POST) {
	$voice = isset($_POST['voice'])?$_POST['voice']:"seiji";
	$volume = isset($_POST['volume'])?$_POST['volume']:1;
	$speed = isset($_POST['speed'])?$_POST['speed']:1;
	$pitch = isset($_POST['pitch'])?$_POST['pitch']:1;
	$range = isset($_POST['range'])?$_POST['range']:1;
	$quality = isset($_POST['quality'])?$_POST['quality']:1;
	$tx = $_POST['tx'];
	$userID = $_POST['ui'];
//	$tc = $_POST['tc'];
	$af = isset($_POST['af'])?$_POST['af']:0;
} else {
	$voice = isset($_GET['voice'])?$_GET['voice']:"seiji";
	$volume = isset($_GET['volume'])?$_GET['volume']:1;
	$speed = isset($_GET['speed'])?$_GET['speed']:1;
	$pitch = isset($_GET['pitch'])?$_GET['pitch']:1;
	$range = isset($_GET['range'])?$_GET['range']:1;
	$quality = isset($_GET['quality'])?$_GET['quality']:1;
	$tx = $_GET['tx'];
	$userID = $_GET['ui'];
	$af = isset($_GET['af'])?$_GET['af']:0;
}

$vt = '<?xml version="1.0" encoding="utf-8"?><speak version="1.1">'
	.'<voice name="'.$voice.'">';
$vt .= $tx."</voice></speak>";


if ($debugmode) echo "Encoding text: ".$vt."<br>";

$req = new Client();
$req->setUri( HOST_URL . API_URL . $userID );
$req->setMethod('POST');
$req->setHeaders(array('Access-Control-Allow-Origin' => '*'));
$req->setOptions(array('timeout' => 120, 'httpversion' => '1.1'));
$req->setHeaders(array('X-Volume' => number_format($volume,1)));
$req->setHeaders(array('X-SilenceOfEnd' => 0));
$req->setHeaders(array('Accept' => 'audio/L16'));
$req->setHeaders(array('Content-Type' => 'application/ssml+xml', 
	'Content-Length' => strlen($vt)));
$req->setHeaders(array('X-Voice' => $voice));
$preset = '{"'.$voice.'": ['.number_format($volume,1).', '.number_format($speed,1)
	.', '.number_format($pitch,1).', '.number_format($range,1).']}';
$req->setHeaders(array('X-Preset' => $preset));
if ($debugmode) echo 'X-Preset: '
	.'{"'.$voice.'": ['.$volume.','.$speed.','.$pitch.','.$range.']}<br>';
//		$req->setParameterPost($headers);
$req->setRawBody($vt);

try {
	// start request
	$startTime = microtime(true);
//	$result = startProcess($userID,$voice.", ".substr($tx,0,30));
	$res = $req->send();
	unset($req);
	if ($res) {
	  // display HTTP status
	  if ($debugmode) echo $res->getStatusCode() .' '. $res->getReasonPhrase() . "<br><br>";
	  // display response header
	  $header = $res->getHeaders();
	  if ($debugmode)
		foreach ($header->toArray() as $hname => $value)
			echo "  $hname: $value<br>";
	  if ($res->getStatusCode()==200) {
			$endTime = microtime(true);
			$fname = save_result($res, $userID);
			if ($af) 
				$dfname = str_replace('.wav','.mp3',$fname);
			else
				$dfname = str_replace('.wav','.aac',$fname);
			unlink($dfname);
			exec("ffmpeg -i $fname -ab 64k $dfname");
			unlink($fname);
			$timeTaken = $endTime - $startTime;
			$result = array('vstatus'=>'success','url'=>$dfname,
				'ptime'=>$timeTaken,'execout'=>$return);
	  } else {
			$result = array('vstatus'=>'error','errorCode'=>$res->getStatusCode(),
	  			'message'=>$res->getReasonPhrase(),'url'=>'');
	  }
    } else {
		$result = array('vstatus'=>'error','errorCode'=>-1,'message'=>'nothing is returned','url'=>'');
    }
} catch (Exception $e) {
	$result = array('vstatus'=>'error','message'=>$e->getMessage(),'url'=>'');
}
echo sprintf("callback(%s)",json_encode($result));




function save_result($res, $id)	{ // ID, Response
	global $debugmode;
	
	$len = -1;
	if (preg_match('/^audio/i', $res->getHeaders()->toArray()['CONTENT-TYPE'])) {
		// save received audio data in wave format
		$fname = "v2/v$id.wav";
		$len = save_wavefile($res, $fname);
	} else if (getBody($res)) {
		// save received other data
		$fname = "recv/result$id";
		$fp = fopen($fname, 'wb');
		if ($fp) {
			$len = fwrite($fp, getBody($res));
			fclose($fp);
		}
	}
//	if ($len>=0) {
//		if ($debugmode) echo '>> "'.$fname.'" (', $len, "Byte) Saved.<br>";
//	}
	return $fname;
}

function getBody(&$res) {
	if (strcasecmp($res->getHeaders()->toArray()['TRANSFER-ENCODING'], 'Chunked') == 0)
		 // need to decode if chunked format
		return http_chunked_decode($res->getContent());
	else
		return $res->getContent(); 
}

function http_chunked_decode($chunk) {
  global $debugmode;
  
  $pos = 0;
  $len = strlen($chunk);
  $dechunk = null;
  while( ($pos < $len) && ($chunkLenHex = substr($chunk, $pos, ($newlineAt = strpos($chunk, "\n", $pos+1)) - $pos)) ) {
	if (!is_hex($chunkLenHex)) {
	  trigger_error('Value is not properly chunk encoded', E_USER_WARNING);
	  return $chunk;
	}
	$pos = $newlineAt + 1;
	$chunkLen = hexdec(rtrim($chunkLenHex, "\r\n"));
	if ($chunkLen > 0) {
	  $dechunk .= substr($chunk, $pos, $chunkLen);
	} else {
	  // Trailer
//	  if ($debugmode) echo " {Trailer}<br>" . substr($chunk, $pos);
	}
	$pos = strpos($chunk, "\n", $pos + $chunkLen) + 1;
  }
  return $dechunk;
}

function is_hex($hex) {
  // regex is for weenies ;)
  $hex = strtolower(trim(ltrim($hex, "0")));
  if (empty($hex)) { $hex = 0; };
  $dec = hexdec($hex);
  return ($hex == dechex($dec));
}


function save_wavefile(&$res, $basename) {
  global $debugmode;

  foreach (preg_split("/;\s*/", $res->getHeaders()->toArray()['CONTENT-TYPE']) as $a) {
    $a = preg_split("/[=\/]/", $a);
    $fmt[ strtolower($a[0]) ] = $a[1];
    if ($debugmode) echo $a[1]."<br>";
  }
  $body = getBody($res);
  $len = $body ? strlen($body) : -1;
  $channels = $fmt['channels'] ? $fmt['channels'] : 1;
  $rate = $fmt['rate'] ? $fmt['rate'] : 44100;

  // data type check
  if (! isset($fmt['audio'])) {
    trigger_error('Not Audio', E_USER_WARNING);
    return -1;
  }

  // save if there's response body
  if ($body) {
    $fp = fopen($basename, 'wb');
    if ($fp) {
      if (strcasecmp($fmt['audio'], 'L16') == 0) {	// 16bit linear PCM
          // create Wave header
          $ret = fwrite($fp, pack("A4VA4A4VvvVVvvA4V", "RIFF", $len+36, "WAVE", "fmt ", 
            16, 1, $channels, $rate, $rate*$channels*2, $channels*2, 16, "data", $len));
          // save while converting endian (audio/L16 is BE, so convert to LE)
          for ($i = 0; $i < $len; $i+=2)
            $ret += fwrite($fp, substr($body, $i + 1, 1) . substr($body, $i, 1));

      } else if (strcasecmp($fmt['audio'], 'L8') == 0) {	// 8bit linear PCM
          // create Wave header
          $ret = fwrite($fp, pack("A4VA4A4VvvVVvvA4V", "RIFF", $len+36, "WAVE", "fmt ",
             16, 1, $channels, $rate, $rate*$channels*1, $channels*1, 8, "data", $len));
          // save sound data
          $ret += fwrite($fp, $body);

      } else if (strcasecmp($fmt['audio'], 'PCMU') == 0) {	// μ-Law
          // create Wave header
          $ret = fwrite($fp, pack("A4VA4A4VvvVVvvA4V",
            "RIFF", $len+36, "WAVE", "fmt ", 16, 7, $channels, $rate, $rate*$channels*1, $channels*1, 8, "data", $len));
          // save sound data
          $ret += fwrite($fp, $body);

      } else {
          trigger_error('Not support:' . getHeader($res, 'Content-Type'), E_USER_WARNING);
          $ret = -1;
      }
      fclose($fp);
    }
  }
  return $ret;	// saved file size
}


function getDB($sql,$getResult=true,&$params=null) {
	global $ini_array, $dbAdapter, $debugmode;

	$server = $ini_array['dbhost'];
	$dbname = $ini_array['database'];
	$username = $ini_array['dbuser'];
	$password = $ini_array['dbpass'];

	try {
		if (!isset($dbAdapter)) {
			$dbAdapter = new PDO("mysql:host=$server;dbname=$dbname;charset=utf8",$username,$password);
			$dbAdapter->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
		}
		$data = array();
		if ($getResult) {
			$result = $dbAdapter->query($sql);
			foreach($result as $row)
				$data[] = $row;
		} else {
			 $stmt = $dbAdapter->prepare($sql);
			 $stmt->execute($params);
		}

	} catch (PDOException $e) {
		echo $e->getMessage()."\n";
		if ($debugmode) echo $sql."\n\n";
	}

	return $data;
}