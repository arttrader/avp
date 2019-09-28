<?php
$ug = isset($argv[1])?$argv[1]:0;

if (!$ug) die("no user group given\n");

$mdir1 = "users/user".$ug;
$mdir2 = $mdir1."/production";
$mdir3 = $mdir2."/tmp";
$mdir4 = $mdir2."/tmpImages";
$mdir5 = $mdir1."/music";
$mdir6 = $mdir1."/video";
$mdir7 = $mdir1."/image";

// create directories
mkdir($mdir1);
mkdir($mdir2);
mkdir($mdir3);
mkdir($mdir4);
mkdir($mdir5);
mkdir($mdir6);
mkdir($mdir7);

// make all the directories writable
exec('chmod 777 $(find '.$mdir1.' -type d)');

$file = 'noimage.png';
copy('users/user0/image/'.$file, $mdir7.'/'.$file);