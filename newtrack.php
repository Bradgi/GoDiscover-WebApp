<?php
  require_once 'core/init.php';
  require_once 'functions/security.php';
  require_once 'functions/S3.php';
  require_once 'functions/xml.php';
  require_once 'functions/zip.php';
  require_once 'functions/image.php';

  error_reporting(0);

  $user = new User();

  // Check if user is logged in.
  if(!$user->isLoggedIn()) {
    Redirect::to('noaccess.php');
  }

  // Amazon S3 object, needs the access key and secret key to interact with a bucket.
  $s3 = new S3('AcessKey', 'SecretKey');

  $pageState = 1; // Defines which page will be displayed, goes from 1 to 3.
  $allowedImage = array('jpg', 'jpeg', 'bmp', 'png'); // Image formats allowed for upload.
  $allowedVideo = array('mp4', 'avi', 'wmv'); // Video formats allowed for upload.
  $allowedAudio = array('mp3', 'wav', 'flac');  // Audio formats allowed for upload.

  // User login functionality.
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

    // Checks for inputs and the page state.
    // Triggered when the first page is submitted.
    if ($_POST['First']) {
      // Checks if the track has a name, if it does, proceed with adding a new track.

      // Clears the users session variables.
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

      if(!empty($_POST['TrackName'])) {
        // Gets the track name and track description.
        $_SESSION['trackName'] = $_POST['TrackName'];
        $_SESSION['trackDescription'] = $_POST['TrackDescription'];

        // Setup the paths of the directories to upload files.
        $imgDir = './godiscover_tmp/'.$_SESSION['trackName'].'/res/image';
        $videoDir = './godiscover_tmp/'.$_SESSION['trackName'].'/res/video';
        $audioDir = './godiscover_tmp/'.$_SESSION['trackName'].'/res/audio';
        $mapDir = './godiscover_tmp/'.$_SESSION['trackName'].'/res/map';
        $mainDir = './godiscover_tmp/'.$_SESSION['trackName'];
        $zipsDir = './zips';

        // Create the directories.
        mkdir($imgDir, 0777, true);
        mkdir($videoDir, 0777, true);
        mkdir($audioDir, 0777, true);
        mkdir($mapDir, 0777, true);
        mkdir($zipsDir, 0777, true);

        // Gets the track image files.
        $_SESSION['trackFile'] = $imgDir.'/'.basename($_FILES['TrackFile']['name']);
        $trackFile = $_FILES['TrackFile']['tmp_name'];

        // Uploads the map and track image files to its directories.
        process_image_upload($_FILES['MapFile']['tmp_name'], $_FILES['MapFile']['name'], $mainDir, $mapDir);
        $_SESSION['mapFile'] = $mapDir.'/'.preg_replace('{\\.[^\\.]+$}', '.jpg', $_FILES['MapFile']['name']);
        move_uploaded_file($trackFile, $_SESSION['trackFile']);

        // Goes to the next page state.
        $pageState = 2;
      }
    }
    // Triggered when there is a click on the map.
    if ($_POST['MapXY']) {
      // Increments the spot counter and gets the X and Y coordinates from the image for each spot.
      $_SESSION['selectedSpots']++;
      array_push($_SESSION['spotsXY'], array($_POST['MapImage_x'], $_POST['MapImage_y']));

      // Refreshes in the same page state.
      $pageState = 2;
    }
    // Triggered when the second page is submitted.
    if ($_POST['Second']) {
      // Goes to the next page state.
      $pageState = 3;
    }
    // Triggered when the last page is submitted or when content is uploaded.
    if ($_POST['Done']) {
      // Reference of the set directories.
        $imgDir = './godiscover_tmp/'.$_SESSION['trackName'].'/res/image';
        $videoDir = './godiscover_tmp/'.$_SESSION['trackName'].'/res/video';
        $audioDir = './godiscover_tmp/'.$_SESSION['trackName'].'/res/audio';
        $mapDir = './godiscover_tmp/'.$_SESSION['trackName'].'/res/map';
        $mainDir = './godiscover_tmp/'.$_SESSION['trackName'];

      // If is triggered when the page is submitted, else when some content is uploaded.
      if (isset($_POST['Finish'])) {
        // Sets the latitude and longitude, names and information for the spots.
        for ($i = 0; $i < sizeof($_SESSION['spotsXY']); $i++) {
          $namingIndex = $i+1;
          array_push($_SESSION['spotsLatLong'], array($_POST['Latitude'.$namingIndex], $_POST['Longitude'.$namingIndex]));
          array_push($_SESSION['spotsName'], $_POST['SpotName'.$namingIndex]);
          array_push($_SESSION['spotsInformation'], $_POST['Information'.$namingIndex]);
        }

        // Sets the xml name, zip name, and the track directory.
        $XMLName = $mainDir.'/'.$_SESSION['trackName'].'.xml';
        $ZipName = './zips/'.$_SESSION['trackName'].'.zip';
        $sourceDir = './godiscover_tmp/'.$_SESSION['trackName'].'/';

        // Creates the track xml, create the zip of the track and adds the entry for the zip on the index xml.
        createTrackXML($_SESSION['trackName'], $_SESSION['trackDescription'], $_SESSION['trackFile'], $_SESSION['mapFile'], $_SESSION['spotsXY'], $_SESSION['spotsContent'], $_SESSION['spotsLatLong'], $_SESSION['spotsName'], $_SESSION['spotsInformation'], $XMLName);
        createZip($sourceDir, $ZipName, $_SESSION['trackName'].'.zip');
        createIndexXML($_SESSION['trackName'].'.zip');

        // Pushes the index xml and the track zip to the Amazon S3 bucket.
        S3::putObject($s3->inputFile($ZipName), 'BucketName', $_SESSION['trackName'].'.zip', S3::ACL_PUBLIC_READ, array(), array(), S3::STORAGE_CLASS_STANDARD);
        S3::putObject($s3->inputFile('./zips/index.xml'), 'BucketName', 'index.xml', S3::ACL_PUBLIC_READ, array(), array(), S3::STORAGE_CLASS_STANDARD);

        // Go back to the first page state.
        $pageState = 1;
      } else {
        // Iterate through all the spots.
        for ($i = 0; $i < sizeof($_SESSION['spotsXY']); $i++) {
          $namingIndex = $i+1;

          // Checks to which spot the content being uploaded belongs to.
          if ($_POST['AddContent'.$namingIndex]) {
            // Separates the file extension from the file being uploaded.
            $contentType = '';
            $fileExtension = explode('.', $_FILES['Content'.$namingIndex]['name']);
            $fileExtension = strtolower(end($fileExtension));

            // Checks if the extension is on the allowed array for any of the types of content, and checks to which type it belongs to.
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

            // Checks if there was no problem with the image type.
            if ($contentType != 'error') {
              // Gets the content name and story and moves it to the upload folder.
              $contentName = $_POST['ContentName'.$namingIndex];
              $contentStory = $_POST['ContentStory'.$namingIndex];
              move_uploaded_file($_FILES['Content'.$namingIndex]['tmp_name'], $contentFile);

              // Sets the content name, story, type and path on a temporary array.
              // (The if applies if that is the first time there is content being added, else otherwise).
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

              // Adds the temporary array to the session variables for the content.
              $_SESSION['spotsContent'][$i] = $tempArray;
            }
          }
        }
        // Refreshes the current page after adding the content.
        $pageState = 3;
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

    <title>Go Discover</title>

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
                <input type="submit" class="btn btn-primary" value="Add Content" name="AddContent<?php echo $coordIndex;?>">
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
