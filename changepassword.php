<?php
	require_once 'core/init.php';
	require_once 'functions/security.php';
	require_once 'functions/S3.php';

	error_reporting(0);

	$user = new User();
	if (!$user->isLoggedIn()) {
		Redirect::to('index.php');
	}

	if (Input::exists()) {
		if (Token::check(Input::get('token'))) {
			$validate = new Validate();
			$validation = $validate->check($_POST, array(
				'Password_current' => array(
					'required' => true,
					'min' => 6
				),
				'Password' => array(
					'required' => true,
					'min' => 6
				),
				'Password_again' => array(
					'required' => true,
					'min' => 6,
					'matches' => 'Password'
				)
			));
			if ($validation->passed()) {
				if (Hash::make(Input::get('Password_current'), $user->data()->salt) !== $user->data()->password) {
					echo 'Your current password is wrong.';
				} else {
					$salt = Hash::salt(32);
					$user->update(array(
						'password' => Hash::make(Input::get('Password'), $salt),
						'salt' => $salt
					));
					Session::flash('home', 'Your password has ben changed!');
					Redirect::to('index.php');
				}
			} else {
				foreach($validation->errors() as $error) {
					echo $error, '<br>';
				}
			}
		}
	}
?>

<form action="" method="post">
	<div class="field">
		<label for="Password_current">Current Password</label>
		<input type="password" name="Password_current" id="Password_current">
	</div>
	<div class="field">
		<label for="Password">New Password</label>
		<input type="password" name="Password" id="Password">
	</div>
	<div class="field">
		<label for="Password_again">Repeat your New Password</label>
		<input type="password" name="Password_again" id="Password_again">
	</div>
	<input type="submit" value="Change">
	<input type="hidden" name="token" value="<?php echo Token::generate();?>">
</form>