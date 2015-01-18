<?php
  require_once 'core/init.php';
  require_once 'functions/security.php';
  require_once 'functions/S3.php';
  require_once 'functions/xml.php';
  require_once 'functions/zip.php';

  error_reporting(1);

  $user = new User();

  if(!$user->isLoggedIn()) {
    Redirect::to('noaccess.php');
  }

  $s3 = new S3('AccessKey', 'SecretKey');

  $pageState = 1;
  $allowedImage = array('jpg', 'jpeg', 'bmp', 'png');
  $allowedVideo = array('mp4', 'avi', 'wmv');
  $allowedAudio = array('mp3', 'wav', 'flac');

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
    if ($_POST['First']) {
      if(!empty($_POST['TrackName'])) {
        $_SESSION['trackName'] = $_POST['TrackName'];
        $imgDir = './godiscover_tmp/'.$_SESSION['trackName'].'/res/image';
        $videoDir = './godiscover_tmp/'.$_SESSION['trackName'].'/res/video';
        $audioDir = './godiscover_tmp/'.$_SESSION['trackName'].'/res/audio';
        $mapDir = './godiscover_tmp/'.$_SESSION['trackName'].'/res/map';
        $mainDir = './godiscover_tmp/'.$_SESSION['trackName'];
        mkdir($imgDir, 0777, true);
        mkdir($videoDir, 0777, true);
        mkdir($audioDir, 0777, true);
        mkdir($mapDir, 0777, true);
        $_SESSION['mapFile'] = $mapDir.'/'.basename($_FILES['MapFile']['name']);
        $_SESSION['trackFile'] = $imgDir.'/'.basename($_FILES['TrackFile']['name']);
        move_uploaded_file($_FILES['MapFile']['tmp_name'], $_SESSION['mapFile']);
        move_uploaded_file($_FILES['TrackFile']['tmp_name'], $_SESSION['trackFile']);
      }
      if(!empty($_POST['TrackDescription'])) {
        $_SESSION['trackDescription'] = $_POST['TrackDescription'];
      }
      $pageState = 2;
    }
    if ($_POST['Second']) {
      $pageState = 3;
    }
    if ($_POST['Done']) {
        $imgDir = './godiscover_tmp/'.$_SESSION['trackName'].'/res/image';
        $videoDir = './godiscover_tmp/'.$_SESSION['trackName'].'/res/video';
        $audioDir = './godiscover_tmp/'.$_SESSION['trackName'].'/res/audio';
        $mapDir = './godiscover_tmp/'.$_SESSION['trackName'].'/res/map';
        $mainDir = './godiscover_tmp/'.$_SESSION['trackName'];
      if (isset($_POST['Finish'])) {
        for ($i = 0; $i < sizeof($_SESSION['spotsXY']); $i++) {
          $namingIndex = $i+1;
          array_push($_SESSION['spotsLatLong'], array($_POST['Latitude'.$namingIndex], $_POST['Longitude'.$namingIndex]));
          array_push($_SESSION['spotsName'], $_POST['SpotName'.$namingIndex]);
          array_push($_SESSION['spotsInformation'], $_POST['Information'.$namingIndex]);
        }
        $XMLName = $mainDir.'/'.$_SESSION['trackName'].'.xml';
        $ZipName = './zips/'.$_SESSION['trackName'].'.zip';
        $sourceDir = './godiscover_tmp/'.$_SESSION['trackName'].'/';
        createTrackXML($_SESSION['trackName'], $_SESSION['trackDescription'], $_SESSION['trackFile'], $_SESSION['mapFile'], $_SESSION['spotsXY'], $_SESSION['spotsContent'], $_SESSION['spotsLatLong'], $_SESSION['spotsName'], $_SESSION['spotsInformation'], $XMLName);
        createZip($sourceDir, $ZipName, $_SESSION['trackName'].'.zip');
        createIndexXML($_SESSION['trackName'].'.zip');
        S3::putObject($s3->inputFile($ZipName), 'godiscover', $_SESSION['trackName'].'.zip', S3::ACL_PUBLIC_READ, array(), array(), S3::STORAGE_CLASS_RRS);
        S3::putObject($s3->inputFile('./zips/index.xml'), 'godiscover', 'index.xml', S3::ACL_PUBLIC_READ, array(), array(), S3::STORAGE_CLASS_RRS);
        $_SESSION['trackName'] = '';
        $_SESSION['trackDescription'] = '';
        $_SESSION['trackFile'] = '';
        $_SESSION['mapFile'] = '';
        $_SESSION['selectedSpots'] = 0;
        $_SESSION['spotsXY'] = Array();
        $_SESSION['spotsContent'] = Array();
        $_SESSION['spotsLatLong'] = Array();
        $_SESSION['spotsName'] = Array();
        $_SESSION['spotsInformation'] = Array();
        $pageState = 1;
      } else {
        for ($i = 0; $i < sizeof($_SESSION['spotsXY']); $i++) {
          $namingIndex = $i+1;
          if ($_POST['AddContent'.$namingIndex]) {
            $contentType = '';
            $fileExtension = explode('.', $_FILES['Content'.$namingIndex]['name']);
            $fileExtension = strtolower(end($fileExtension));
            if (in_array($fileExtension, $allowedImage)) {
              $contentType = 'image';
              $contentFile = $imgDir.'/'.basename($_FILES['Content'.$namingIndex]['name']);
            } else if (in_array($fileExtension, $allowedAudio)) {
              $contentType = 'audio';
              $contentFile = $audioDir.'/'.basename($_FILES['Content'.$namingIndex]['name']);
            } else if (in_array($fileExtension, $allowedVideo)) {
              $contentType = 'video';
              $contentFile = $videoDir.'/'.basename($_FILES['Content'.$namingIndex]['name']);
            } else {
              $contentType = 'error';
            }
            if ($contentType != 'error') {
              $contentName = $_POST['ContentName'.$namingIndex];
              $contentStory = $_POST['ContentStory'.$namingIndex];
              move_uploaded_file($_FILES['Content'.$namingIndex]['tmp_name'], $contentFile);
              $tempArray = Array();
              if (sizeof($_SESSION['spotsContent'][$i]) == 0) {
                array_push($tempArray, $contentFile);
                array_push($tempArray, $contentName);
                array_push($tempArray, $contentStory);
                array_push($tempArray, $contentType);
              } else {
                $tempArray = $_SESSION['spotsContent'][$i];
                array_push($tempArray, $contentFile);
                array_push($tempArray, $contentName);
                array_push($tempArray, $contentStory);
                array_push($tempArray, $contentType);
              }
              $_SESSION['spotsContent'][$i] = $tempArray;
            }
          }
        }
        $pageState = 3;
      }
    }
    if ($_POST['MapXY']) {
      $_SESSION['selectedSpots']++;
      array_push($_SESSION['spotsXY'], array($_POST['MapImage_x'], $_POST['MapImage_y']));
      $pageState = 2;
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

        <?php
        if ($pageState == 1) {
        ?>

        <div class="jumbotron">
          <h3>Track Information</h3>
          <form role="form" action="" method="post" enctype="multipart/form-data">
            <div class="form-group">
              <label for="TrackName">Track Name</label>
              <input type="text" class="form-control" name="TrackName" id="TrackName" placeholder="Name of the Track">
              <label for="TrackDescription">Track Description</label>
              <textarea type="text" class="form-control" rows="3" name="TrackDescription" id="TrackDescription" placeholder="Description of the Track"></textarea>
              <label for="TrackFile">Track Image</label>
              <input type="file" name="TrackFile">
              <label for="MapFile">Map Image</label>
              <input type="file" name="MapFile">
              <input type="hidden" class="form-control" name="First" id="First" value='Hidden'>
            </div>
            <input type="submit" class="btn btn-primary" value="Next">
          </form>
        </div>

        <?php
        } else if ($pageState == 2) {
        ?>

        <div class="jumbotron">
          <h3>Map Setup</h3>
          <form role="form" action="" method="post">
            <input type="image" src="<?php echo $_SESSION['mapFile']?>" name="MapImage" style=cursor:crosshair;/>
            <p>Number of Spots Selected: <?php echo $_SESSION[selectedSpots];?></p>
            <input type="hidden" class="form-control" name="MapXY" id="MapXY" value='Hidden'>
          </form>
          <form role="form" action="" method="post">
            <div class="form-group">
              <input type="hidden" class="form-control" name="Second" id="Second" value='Hidden'>
            </div>
            <input type="submit" class="btn btn-primary" value="Next">
          </form>
        </div>

        <?php
        } else if ($pageState == 3) {
        ?>

        <div class="jumbotron">
          <h3>Spots Setup</h3>
          <form role="form" action="" method="post" enctype="multipart/form-data">
            <?php 
            $coordIndex = 1;
            foreach ($_SESSION['spotsXY'] as $spot) {
            ?>
              <div class="form-group">
                <p>Spot <?php echo $coordIndex; ?></p>
                <label for="Latitude<?php echo $coordIndex;?>">Latitude</label>
                <input type="text" class="form-control" name="Latitude<?php echo $coordIndex;?>" id="Latitude<?php echo $coordIndex;?>" placeholder="Latitude">
                <label for="Longitude<?php echo $coordIndex;?>">Longitude</label>
                <input type="text" class="form-control" name="Longitude<?php echo $coordIndex;?>" id="Longitude<?php echo $coordIndex;?>" placeholder="Longitude">
                <p>X: <?php echo $spot[0]?></p>
                <p>Y: <?php echo $spot[1]?></p>
                <label for="SpotName<?php echo $coordIndex;?>">Name</label>
                <input type="text" class="form-control" name="SpotName<?php echo $coordIndex;?>" id="SpotName<?php echo $coordIndex;?>" placeholder="Name of the Spot.">
                <label for="Information<?php echo $coordIndex;?>">Information</label>
                <textarea type="text" class="form-control" rows="3" name="Information<?php echo $coordIndex;?>" id="Information<?php echo $coordIndex;?>" placeholder="Information about the spot."></textarea>
                <h4>Content for Spot <?php echo $coordIndex;?></h4>
                <h5>Number of Assets Uploaded: <?php echo sizeof($_SESSION['spotsContent'][$coordIndex-1])/4;?></h5>
                <label for="Content<?php echo $coordIndex;?>">Content Upload</label>
                <input type="file" name="Content<?php echo $coordIndex;?>">
                <label for="ContentName<?php echo $coordIndex;?>">Content Name</label>
                <input type="text" class="form-control" name="ContentName<?php echo $coordIndex;?>" id="ContentStory<?php echo $coordIndex;?>" placeholder="Content Name.">
                <label for="ContentStory<?php echo $coordIndex;?>">Content Story</label>
                <input type="text" class="form-control" name="ContentStory<?php echo $coordIndex;?>" id="ContentStory<?php echo $coordIndex;?>" placeholder="Content Story.">
                <p> </p>
                <input type="submit" class="btn btn-primary" value="Add More" name="AddContent<?php echo $coordIndex;?>">
              </div>
            <?php  $coordIndex++;} ?>
            <input type="hidden" class="form-control" name="Done" id="Done" value='Hidden'>
            <input type="submit" class="btn btn-success" value="Finish" name="Finish">
          </form>
        </div>

        <?php
        }
        ?>


      </div> <!-- /container -->


    <!-- Bootstrap core JavaScript
    ================================================== -->
    <!-- Placed at the end of the document so the pages load faster -->
    <script src="https://ajax.googleapis.com/ajax/libs/jquery/1.11.0/jquery.min.js"></script>
    <script src="dist/bootstrap/js/bootstrap.min.js"></script>
    <script src="dist/bootstrap/js/docs.min.js"></script>
  </body>
</html>
