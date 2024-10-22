<?php
session_start();
if (!isset($_SESSION['email'])) {
  header("Location: session.php");
  exit;
}

// Connect to the SQLite database
$db = new SQLite3('database.sqlite');

// Get the artist name from the database
$email = $_SESSION['email'];
$stmt = $db->prepare("SELECT id, artist, pic, `desc`, bgpic, twitter, pixiv, other, region FROM users WHERE email = :email");
$stmt->bindValue(':email', $email);
$result = $stmt->execute();
$row = $result->fetchArray();
$user_id = $row['id'];
$artist = $row['artist'];
$pic = $row['pic'];
$desc = $row['desc'];
$bgpic = $row['bgpic'];
$twitter = $row['twitter'];
$pixiv = $row['pixiv'];
$other = $row['other'];
$region = $row['region'];

// Function to format numbers
function formatNumber($num) {
  if ($num >= 1000000) {
    return round($num / 1000000, 1) . 'm';
  } elseif ($num >= 100000) {
    return round($num / 1000) . 'k';
  } elseif ($num >= 10000) {
    return round($num / 1000, 1) . 'k';
  } elseif ($num >= 1000) {
    return round($num / 1000) . 'k';
  } else {
    return $num;
  }
}

// Count the number of followers
$stmt = $db->prepare("SELECT COUNT(*) AS num_followers FROM following WHERE following_email = :email");
$stmt->bindValue(':email', $email);
$result = $stmt->execute();
$row = $result->fetchArray();
$num_followers = $row['num_followers'];

// Count the number of following
$stmt = $db->prepare("SELECT COUNT(*) AS num_following FROM following WHERE follower_email = :email");
$stmt->bindValue(':email', $email);
$result = $stmt->execute();
$row = $result->fetchArray();
$num_following = $row['num_following'];

// Format the numbers
$formatted_followers = formatNumber($num_followers);
$formatted_following = formatNumber($num_following);

// Process any favorite/unfavorite requests
if (isset($_POST['favorite'])) {
  $image_id = $_POST['image_id'];

  // Check if the image has already been favorited by the current user
  $existing_fav = $db->query("SELECT COUNT(*) FROM favorites WHERE email = '{$_SESSION['email']}' AND image_id = $image_id")->fetchArray()[0];

  if ($existing_fav == 0) {
    $db->exec("INSERT INTO favorites (email, image_id) VALUES ('{$_SESSION['email']}', $image_id)");
  }

  // Get the current page URL
  $currentUrl = $_SERVER['REQUEST_URI'];

  // Redirect to the current page to prevent duplicate form submissions
  header("Location: $currentUrl");
  exit();

} elseif (isset($_POST['unfavorite'])) {
  $image_id = $_POST['image_id'];
  $db->exec("DELETE FROM favorites WHERE email = '{$_SESSION['email']}' AND image_id = $image_id");

  // Get the current page URL
  $currentUrl = $_SERVER['REQUEST_URI'];

  // Redirect to the current page to prevent duplicate form submissions
  header("Location: $currentUrl");
  exit();
} 

// Get all of the images uploaded by the current user
$stmtI = $db->prepare("SELECT * FROM images WHERE email = :email ORDER BY id DESC");
$stmtI->bindValue(':email', $email);
$resultI = $stmtI->execute();

// Count the number of images uploaded by the current user
$count = 0;
while ($imageI = $resultI->fetchArray()) {
  $count++;
}

$fav_result = $db->query("SELECT COUNT(*) FROM favorites WHERE email = '{$_SESSION['email']}'");
$fav_count = $fav_result->fetchArray()[0];
?>

<!DOCTYPE html>
<html lang="en">
  <head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?php echo $artist; ?></title>
    <link rel="manifest" href="manifest.json">
    <link rel="icon" type="image/png" href="icon/favicon.png">
    <?php include('bootstrapcss.php'); ?>
  </head>
  <body>
    <?php include('header.php'); ?>
    <div class="roow mb-2">
      <div class="cool-6 text-center">
        <div class="caard art">
          <div class="col-md-5 order-md-1 mt-3 b-radius" style="background-image: url('<?php echo !empty($bgpic) ? $bgpic : "default_bg_thumbnail.jpg"; ?>'); background-size: cover; height: 200px; width: 100%;">
            <img class="img-thumbnail rounded-circle text-secondary" src="<?php echo !empty($pic) ? $pic : "icon/profile.svg"; ?>" alt="Profile Picture" style="object-fit: cover; width: 110px; height: 110px; border-radius: 4px; margin-left: -167px; margin-top: 45px;">
            <a class="btn-sm btn btn-secondary fw-bold float-start mt-2 ms-2 rounded-pill opacity-50" type="button" href="setting.php">change background <i class="bi bi-camera-fill"></i></a>
          </div>
          <div class="btn-group d-none-sm-b b-section" role="group" aria-label="Basic example">
            <a class="btn btn-sm btn-secondary disabled rounded opacity-50 fw-bold"><i class="bi bi-images"></i> <?php echo $count; ?> <small>images</small></a>
            <a class="btn btn-sm btn-secondary ms-1 rounded fw-bold opacity-50" href="list_favorite.php?id=<?php echo $user_id; ?>"><i class="bi bi-heart"></i> <?php echo $fav_count;?> <small>favorites</small></a> 
            <button class="btn btn-sm btn-secondary ms-1 rounded fw-bold opacity-50" onclick="shareArtist(<?php echo $user_id; ?>)"><i class="bi bi-share-fill"></i> <small>share</small></button>
          </div>
        </div>
      </div>
      <div class="cool-6">
        <div class="caard art text-center">
          <h3 class="text-secondary mt-2 fw-bold"><?php echo $artist; ?></h3>
          <p class="text-center text-muted fw-semibold"><small><i class="bi bi-globe-asia-australia"></i> <?php echo $region; ?></small></p>
          <div class="btn-group mt-2 mb-3" role="group" aria-label="Basic example">
            <a class="btn btn-sm btn-secondary rounded fw-bold opacity-50" href="follower.php?id=<?php echo $user_id; ?>"><?php echo $num_followers ?> <small>followers</small></a>
            <a class="btn btn-sm btn-secondary ms-1 rounded fw-bold opacity-50" href="following.php?id=<?php echo $user_id; ?>"><?php echo $num_following ?> <small>following</small></a>
            <a class="btn btn-sm btn-secondary ms-1 rounded fw-bold opacity-50" href="album.php"><i class="bi bi-images"></i> <small>my albums</small></a>
          </div>
          <p class="text-center text-secondary fw-bold container-fluid" style="word-break: break-word; width: 97%;">
            <small>
              <?php
                $messageText = $desc;
                $messageTextWithoutTags = is_null($messageText) ? '' : strip_tags($messageText);
                $pattern = '/\bhttps?:\/\/\S+/i';

                $formattedText = preg_replace_callback($pattern, function ($matches) {
                  $url = htmlspecialchars($matches[0]);
                  return '<a href="' . $url . '" target="_blank">' . $url . '</a>';
                }, $messageTextWithoutTags);

                $formattedTextWithLineBreaks = nl2br($formattedText);
                echo $formattedTextWithLineBreaks;
              ?>
            </small>
          </p>
          <ul class="nav justify-content-center pb-3 mb-3">
            <li class="nav-item fw-bold"><a href="<?php echo $twitter; ?>" class="nav-link px-2 text-secondary"><img class="img-sns" width="16" height="16" src="icon/twitter.svg"> Twitter</a></li>
            <li class="nav-item fw-bold"><a href="<?php echo $pixiv; ?>" class="nav-link px-2 text-secondary"><img class="img-sns" width="16" height="16" src="icon/pixiv.svg"> Pixiv</a></li>
            <li class="nav-item fw-bold"><a href="<?php echo $other; ?>" class="nav-link px-2 text-secondary"><img class="img-sns" width="16" height="16" src="icon/globe-asia-australia.svg"> Other</a></li>
          </ul>
          <div class="btn-group d-md-none d-lg-none" role="group" aria-label="Basic example">
            <a class="btn btn-sm btn-secondary disabled rounded opacity-50 fw-bold"><i class="bi bi-images"></i> <?php echo $count; ?> <small>images</small></a>
            <a class="btn btn-sm btn-secondary ms-1 rounded fw-bold opacity-50" href="list_favorite.php?id=<?php echo $user_id; ?>"><i class="bi bi-heart"></i> <?php echo $fav_count;?> <small>favorites</small></a> 
            <button class="btn btn-sm btn-secondary ms-1 rounded fw-bold opacity-50" onclick="shareArtist(<?php echo $user_id; ?>)"><i class="bi bi-share-fill"></i> <small>share</small></button>
          </div>
        </div>
      </div>
    </div>
    <h5 class="container-fluid fw-bold text-secondary"><i class="bi bi-images"></i> All <?php echo $artist; ?>'s Images</h5>
    <div class="btn-group container-fluid w-100 mb-3">
      <a href="?by=newest" class="btn btn-outline-secondary fw-bold <?php if(!isset($_GET['by']) || $_GET['by'] == 'newest') echo 'active'; ?>">newest</a>
      <a href="?by=oldest" class="btn btn-outline-secondary fw-bold <?php if(isset($_GET['by']) && $_GET['by'] == 'oldest') echo 'active'; ?>">oldest</a>
      <a href="?by=popular" class="btn btn-outline-secondary fw-bold <?php if(isset($_GET['by']) && $_GET['by'] == 'popular') echo 'active'; ?>">popular</a>
    </div>
        <?php 
        if(isset($_GET['by'])){
          $sort = $_GET['by'];
 
          switch ($sort) {
            case 'newest':
            include "profile_desc.php";
            break;
            case 'oldest':
            include "profile_asc.php";
            break;
            case 'popular':
            include "profile_pop.php";
            break;
          }
        }
        else {
          include "profile_desc.php";
        }
        
        ?>
    <div class="mt-5"></div>
    <style>
      .img-sns {
        margin-top: -4px;
      }
    
      @media (min-width: 768px) {
        .b-section {
          margin-top: 50px;
        }
      }
      
      @media (max-width: 767px) {
        .d-none-sm-b {
          display: none;
        }
      }
      
      .images {
        display: grid;
        grid-template-columns: repeat(2, 1fr); /* Two columns in mobile view */
        grid-gap: 3px;
        justify-content: center;
        margin-right: 3px;
        margin-left: 3px;
      }

      .text-stroke {
        -webkit-text-stroke: 1px;
      }
      
      @media (min-width: 768px) {
        /* For desktop view, change the grid layout */
        .images {
          grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
        }
      }

      .imagesA {
        display: block;
        border-radius: 4px;
        overflow: hidden;
      }

      .imagesImg {
        width: 100%;
        height: auto;
        object-fit: cover;
        height: 200px;
        transition: transform 0.5s ease-in-out;
      }

      .roow {
        display: flex;
        flex-wrap: wrap;
        border-radius: 5px;
        border: 2px solid lightgray;
        margin-right: 10px;
        margin-left: 10px;
        margin-top: 10px;
      }

      .cool-6 {
        width: 50%;
        padding: 0 15px;
      }

      .caard {
        background-color: #fff;
        margin-bottom: 15px;
      }
      
      .b-radius {
        border-radius: 10px;
      }

      .art {
        border-radius: 10px;
      }

      @media (max-width: 768px) {
        .roow {
          border: none;
          margin-right: 0;
          margin-left: 0;
          margin-top: -15px;
        }
        
        .cool-6 {
          width: 100%;
          padding: 0;
        }
        
        .b-radius {
          border-right: none;
          border-left: none;
          border-top: 1px solid lightgray;
          border-bottom: 1px solid lightgray;
          border-radius: 0;
        }
        
        .border-down {
          border-bottom: 2px solid lightgray;
        }
      }
    </style>
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
    <script>
      document.addEventListener("DOMContentLoaded", function() {
        let lazyloadImages;
        if("IntersectionObserver" in window) {
          lazyloadImages = document.querySelectorAll(".lazy-load");
          let imageObserver = new IntersectionObserver(function(entries, observer) {
            entries.forEach(function(entry) {
              if(entry.isIntersecting) {
                let image = entry.target;
                image.src = image.dataset.src;
                image.classList.remove("lazy-load");
                imageObserver.unobserve(image);
              }
            });
          });
          lazyloadImages.forEach(function(image) {
            imageObserver.observe(image);
          });
        } else {
          let lazyloadThrottleTimeout;
          lazyloadImages = document.querySelectorAll(".lazy-load");

          function lazyload() {
            if(lazyloadThrottleTimeout) {
              clearTimeout(lazyloadThrottleTimeout);
            }
            lazyloadThrottleTimeout = setTimeout(function() {
              let scrollTop = window.pageYOffset;
              lazyloadImages.forEach(function(img) {
                if(img.offsetTop < (window.innerHeight + scrollTop)) {
                  img.src = img.dataset.src;
                  img.classList.remove('lazy-load');
                }
              });
              if(lazyloadImages.length == 0) {
                document.removeEventListener("scroll", lazyload);
                window.removeEventListener("resize", lazyload);
                window.removeEventListener("orientationChange", lazyload);
              }
            }, 20);
          }
          document.addEventListener("scroll", lazyload);
          window.addEventListener("resize", lazyload);
          window.addEventListener("orientationChange", lazyload);
        }
      })
    </script>
    <script>
      function shareArtist(userId) {
        // Compose the share URL
        var shareUrl = 'artist.php?id=' + userId;

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