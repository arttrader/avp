<?php
require_once 'AuthController.php';
require_once 'videoDataClass.php';
require_once 'createVideoClass.php';

if ($_POST) {
	$vid = $_POST['v'];
	$sid = $_POST['s'];
} else if($_GET) {
	$vid = $_GET['v'];
	$sid = $_GET['s'];
}

if (!$vid) die('Error: no video ID\n');

$vd = new videoProdDataClass($vid);
echo "creating video id= $vd->id\n\n";

$vidCreator = new videoCreatorClass($sid);

$vidCreator->createVideo($vd);

$vidCreator = null;
