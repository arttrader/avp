<?php
//var_dump($argv);
$ug = $argv[1]?$argv[1]:4;
echo "group ".$ug."\n";

$mdir1 = "image".$ug."/";
$mdir2 = "music".$ug."/";
$mdir3 = "video".$ug."/";
$mdir4 = "tmp".$ug."/";
$mdir5 = "tmpImages".$ug."/";
$mdir6 = "production".$ug."/";

// create directories
mkdir($mdir1);
mkdir($mdir2);
mkdir($mdir3);
mkdir($mdir4);
mkdir($mdir5);
mkdir($mdir6);

// make all the directories writable
exec('chmod 777 '.$mdir1);
exec('chmod 777 '.$mdir2);
exec('chmod 777 '.$mdir3);
exec('chmod 777 '.$mdir4);
exec('chmod 777 '.$mdir5);
exec('chmod 777 '.$mdir6);