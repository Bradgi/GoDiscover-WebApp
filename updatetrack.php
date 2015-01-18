<?php
  require_once 'core/init.php';
  require_once 'functions/security.php';
  require_once 'functions/S3.php';

  error_reporting(0);

  $user = new User();

  if(!$user->isLoggedIn()) {
    Redirect::to('noaccess.php');
  }

  if (Input::exists()) {
    if (Token::check(Input::get('token'))) {
      $validate = new Validate();
      $validation = $validate->check($_POST, array(
        'Username' => array('required' => true),
        'Password' => array('required' => true)
      ));
      if ($validation->passed()) {
        $login = $user->login(Input::get('Username'), Input::get('Password'));
        if ($login) {
          Redirect::to('index.php');
        } else {
          echo 'Log in Failed';
        }
      } else {
        foreach($validation->errors() as $error) {
          echo $error, '<br>';
        }
      }
    }
  }
?>

<!DOCTYPE html>
<html lang="en-Us">
  <head>
    <meta charset="ISO-8859-15">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="">
    <meta name="author" content="">

    <title>Dargon Photo Sharing</title>

    <!-- Bootstrap core CSS -->
    <link href="../dist/bootstrap/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap theme -->
    <link href="dist/bootstrap/css/bootstrap-theme.min.css" rel="stylesheet">
    <!-- Custom styles for this template -->
    <link href="dist/bootstrap/css/theme.css" rel="stylesheet">

  </head>

  <body role="document">

    <div class="navbar navbar-default navbar-fixed-top" role="navigation">
      <div class="container">
        <div class="navbar-header">
          <button type="button" class="navbar-toggle" data-toggle="collapse" data-target=".navbar-collapse">
            <span class="sr-only">Toggle navigation</span>
            <span class="icon-bar"></span>
            <span class="icon-bar"></span>
            <span class="icon-bar"></span>
          </button>
          <a class="navbar-brand" href="index.php">Go Discover</a>
        </div>
          <ul class="nav navbar-nav">
            <li class="dropdown">
              <a href="#" class="dropdown-toggle" data-toggle="dropdown" role="button" aria-expanded="false">Tracks<span class="caret"></span></a>
              <ul class="dropdown-menu" role="menu">
                <li><a href="newtrack.php">Create Track</a></li>
                <li><a href="updatetrack.php">Update Track</a></li>
              </ul>
            </li>
          </ul>
          <div class="navbar-collapse collapse">
            <div class="navbar-collapse collapse">
              <?php
                if ($user->isLoggedIn()) {
              ?>
              <form class="navbar-form navbar-right" role="form">
                <div class="form-group">
                  <p><?php echo $user->data()->username; ?></p>
                </div>
                <div class="form-group">
                </div>
                <a href="changepassword.php">Change Password</a>
                <a href="logout.php">Log out</a>
              </form>
              <?php
                } else{
              ?>
              <form class="navbar-form navbar-right" role="form" method="post">
                <div class="form-group">
                  <input type="text" placeholder="Username" name="Username" id="Username" class="form-control">
                </div>
                <div class="form-group">
                  <input type="password" placeholder="Password" name="Password" id="Password" class="form-control">
                </div>
                <input type="hidden" name="token" value="<?php echo Token::generate();?>">
                <input type="submit" class="btn btn-default" value="Log in">
              </form>
              <?php
                }
              ?>
            </div><!--/.navbar-collapse -->
          </div><!--/.nav-collapse -->
        </div>
      </div>

      <div class="container theme-showcase" role="main">

        <!-- Main jumbotron for a primary marketing message or call to action -->
        <div class="jumbotron">
          <h1>Update Tracks</h1>
          <p>Update your tracks here.</p>
        </div>

      </div> <!-- /container -->


    <!-- Bootstrap core JavaScript
    ================================================== -->
    <!-- Placed at the end of the document so the pages load faster -->
    <script src="https://ajax.googleapis.com/ajax/libs/jquery/1.11.0/jquery.min.js"></script>
    <script src="dist/bootstrap/js/bootstrap.min.js"></script>
    <script src="dist/bootstrap/js/docs.min.js"></script>
  </body>
</html>
