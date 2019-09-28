<?php
require_once 'AuthController.php';

if ($_POST) {
	$fname = isset($_POST['f'])?$_POST['f']:'';
	$url = isset($_POST['u'])?$_POST['u']:'';
	$id = isset($_POST['i'])?$_POST['i']:0;
	$vid = isset($_POST['v'])?$_POST['v']:0;
	
	$source = $url.$fname;
	$dest = $fname;
	echo "file: ".$source."<br>";
	echo "to: ".$dest."<br>";
	
	$try = 0;
	do {
		echo "copying ".$source."  ".$dest."\n";
		if (file_exists($dest)) unlink($dest);
		$success = copy($source,$dest);
		sleep(rand(1,2));
	} while (!$success && $try++<4);
	if ($success) {
		$desc = 'File received '.$source;
		$sql = "call set_production_status($vid,10);";
		$result = getDB($sql,false);
	} else {
		$desc = 'File receive failed '.$source;
		if ($vid) {
			$sql = "call set_production_status($vid,-10);";
			$result = getDB($sql,false);
		}
	}
	$sql = sprintf("call add_prod_log(%u,%u,%u,'%s')", $id,$vid,0,$desc);
	$result = getDB($sql,false);
} else {
	// for testing
	$fname = isset($_GET['f'])?$_GET['f']:'';
	$url = isset($_GET['u'])?$_GET['u']:'';

	$source = $url.$fname;
	$dest = $fname;
	echo "file: <a href='$source'>".$source."</a><br>";
	echo "to: <a href='$dest'>".$dest."</a><br>";
	
//	if (fileExists($source)) {
		$success = copy($source,$dest);
		if ($success)
			$desc = 'g File received ';
		else
			$desc = 'g File transfer failed ';
		echo $desc."<br>";

/*		$file = fopen ($source, "rb");
		if($file) {
			$newf = fopen ($dest, "wb");
			if($newf) {
				while(!feof($file)) {
					fwrite($newf, fread($file, 1024 * 8 ), 1024 * 8 );
					echo '1 MB File Chunk Written!<br>';
				}
				fclose($newf);
			}
			fclose($file);
		} else echo 'error opening file<br>';
*/
//	} else
//		echo "File does not exist in ".$source."<br>";
}



function fileExists($path) {
$ch = curl_init($path);

curl_setopt($ch, CURLOPT_NOBODY, true);
curl_exec($ch);
$retcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
// $retcode >= 400 -> not found, $retcode = 200, found.
curl_close($ch);
return ($retcode==200);
}