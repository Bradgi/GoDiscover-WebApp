<?php
	require_once 'core/init.php';

	error_reporting(0);

	// Logout current user.
	$user = new User();
	$user->logout();

	// Redirect to main page.
	Redirect::to('index.php');