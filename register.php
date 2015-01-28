<?php
	require_once 'core/init.php';
	require_once 'functions/security.php';
	require_once 'functions/S3.php';

	error_reporting(0);

	// Check for inputs and validate them.
	if (Input::exists()) {
		if (Token::check(Input::get('token'))) {
		$validate = new Validate();
		$validation = $validate->check($_POST, array(
			'Username' => array(
				'required' => true,
				'min' => 2,
				'max' => 20,
				'unique' => 'users'
			),
			'Password' => array(
				'required' => true,
				'min' => 6
			),
			'Password_again' => array(
				'required' => true,
				'matches' => 'Password'
			),
			'Name' => array(
				'required' =>true,
				'min' => 2,
				'max' => 50
			)
		));
		if ($validation->passed()) {
			$user = new User();
			$salt = Hash::salt(32);
			try {
				$user->create(array(
					'username' => Input::get('Username'),
					'password' => Hash::make(Input::get('Password'), $salt),
					'salt' => $salt,
					'name' => Input::get('Name'),
					'joined' => date('Y-m-d H:i:s'),
					'group' => 1
				));
				Session::flash('home', 'You have been registered an can now log in!');
				Redirect::to('index.php');
			} catch(Exception $e) {
				die($e->getMessage());
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
		<label for="Username">Username</label>
		<input type="text" name="Username" id="Username" value="<?php echo escape(Input::get('Username'));?>" autocomplete="off">
	</div>
	<div class="field">
		<label for="Password">Password</label>
		<input type="password" name="Password" id="Password">
	</div>
	<div class="field">
		<label for="Password_again">Repeat your Password</label>
		<input type="password" name="Password_again" id="Password_again">
	</div>
	<div class="field">
		<label for="Name">Name</label>
		<input type="text" name="Name" id="Name" value="<?php echo escape(Input::get('Name'));?>" autocomplete="off">
	</div>
	<input type="hidden" name="token" value="<?php echo Token::generate();?>">
	<input type="submit" value="Register">
</form>