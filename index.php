<?php
session_start();
if (!isset($_SESSION['email'])) {
  header("Location: session.php");
  exit;
}

$email = $_SESSION['email'];

// Connect to the SQLite database using parameterized query
$db = new SQLite3('database.sqlite');
$stmt = $db->prepare("CREATE TABLE IF NOT EXISTS images (id INTEGER PRIMARY KEY AUTOINCREMENT, filename TEXT, email TEXT, tags TEXT, title TEXT, imgdesc TEXT, link TEXT, date DATETIME, view_count INT DEFAULT 0)");
$stmt->execute();
$stmt = $db->prepare("CREATE TABLE IF NOT EXISTS favorites (id INTEGER PRIMARY KEY AUTOINCREMENT, image_id INTEGER, email TEXT)");
$stmt->execute();
$stmt = $db->prepare('CREATE TABLE IF NOT EXISTS following (id INTEGER PRIMARY KEY AUTOINCREMENT, follower_email TEXT NOT NULL, following_email TEXT NOT NULL)');
$stmt->execute();
$stmt = $db->prepare('CREATE TABLE IF NOT EXISTS news (id INTEGER PRIMARY KEY, title TEXT, description TEXT, created_at DATETIME DEFAULT CURRENT_TIMESTAMP, ver TEXT, verlink TEXT, preview TEXT)');
$stmt->execute();
$stmt = $db->prepare('CREATE TABLE IF NOT EXISTS status ( id INTEGER PRIMARY KEY AUTOINCREMENT, email TEXT, message TEXT, date DATETIME)');
$stmt->execute();
$stmt = $db->prepare('CREATE TABLE IF NOT EXISTS image_album (id INTEGER PRIMARY KEY AUTOINCREMENT, image_id INTEGER NOT NULL, email TEXT NOT NULL, album_id INTEGER NOT NULL, FOREIGN KEY (image_id) REFERENCES image(id), FOREIGN KEY (album_id) REFERENCES album(id));');
$stmt->execute();
$stmt = $db->prepare('CREATE TABLE IF NOT EXISTS album ( id INTEGER PRIMARY KEY AUTOINCREMENT, email TEXT NOT NULL, album_name TEXT NOT NULL);');
$stmt->execute();

// Process any favorite/unfavorite requests
if (isset($_POST['favorite'])) {
  $image_id = $_POST['image_id'];

  // Check if the image has already been favorited by the current user
  $existing_fav = $db->querySingle("SELECT COUNT(*) FROM favorites WHERE email = '$email' AND image_id = $image_id");

  if ($existing_fav == 0) {
    $db->exec("INSERT INTO favorites (email, image_id) VALUES ('$email', $image_id)");
  }
  
  // Redirect to the same page with the appropriate sorting parameter
  $page = isset($_GET['page']) ? $_GET['page'] : 1; // check if page is set, default to 1
  $by = isset($_GET['by']) ? $_GET['by'] : 'newest'; // check if by is set, default to newest
  header('Location: ' . $_SERVER['PHP_SELF'] . '?by=' . $by . '&page=' . $page);
  exit(); 
  
} elseif (isset($_POST['unfavorite'])) {
  $image_id = $_POST['image_id'];
  $db->exec("DELETE FROM favorites WHERE email = '$email' AND image_id = $image_id");

  // Redirect to the same page with the appropriate sorting parameter
  $page = isset($_GET['page']) ? $_GET['page'] : 1; // check if page is set, default to 1
  $by = isset($_GET['by']) ? $_GET['by'] : 'newest'; // check if by is set, default to newest
  header('Location: ' . $_SERVER['PHP_SELF'] . '?by=' . $by . '&page=' . $page);
  exit();
}
?>

<!DOCTYPE html>
<html lang="en">
  <head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>ArtCODE</title>
    <link rel="manifest" href="manifest.json">
    <link rel="icon" type="image/png" href="icon/favicon.png">
    <?php include('bootstrapcss.php'); ?>
  </head>
  <body>
    <?php include('header.php'); ?>
    <?php include 'popular-content.php'; ?>
        <?php 
        if(isset($_GET['by'])){
          $sort = $_GET['by'];
 
          switch ($sort) {
            case 'newest':
            include "index_desc.php";
            break;
            case 'oldest':
            include "index_asc.php";
            break;
          }
        }
        else {
          include "index_desc.php";
        }
        
        ?>
    <script>
      function shareImage(userId) {
        // Compose the share URL
        var shareUrl = 'image.php?artworkid=' + userId;

        // Check if the Share API is supported by the browser
        if (navigator.share) {
          navigator.share({
          url: shareUrl
        })
          .then(() => console.log('Shared successfully.'))
          .catch((error) => console.error('Error sharing:', error));
        } else {
          console.log('Share API is not supported in this browser.');
          // Provide an alternative action for browsers that do not support the Share API
          // For example, you can open a new window with the share URL
          window.open(shareUrl, '_blank');
        }
      }
    </script>
    <?php include('bootstrapjs.php'); ?>
  </body>
</html>