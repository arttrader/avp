<?php
require_once 'AuthController.php';

//start session
session_start();
if (isset($_SESSION[$sessionName])) {
	$authController = unserialize($_SESSION[$sessionName]);
	$authController->logout();
}
header("Location:$rootUrl");