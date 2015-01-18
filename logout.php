<?php
	require_once 'core/init.php';

	error_reporting(0);

	$user = new User();
	$user->logout();

	Redirect::to('index.php');