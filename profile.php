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
$stmt = $db->prepare("SELECT id, artist, pic, `desc`, bgpic, twitter, pixiv, other FROM users WHERE email = :email");
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

// Process any favorite/unfavorite requests
if (isset($_POST['favorite'])) {
  $image_id = $_POST['image_id'];

  // Check if the image has already been favorited by the current user
  $existing_fav = $db->query("SELECT COUNT(*) FROM favorites WHERE email = '{$_SESSION['email']}' AND image_id = $image_id")->fetchArray()[0];

  if ($existing_fav == 0) {
    $db->exec("INSERT INTO favorites (email, image_id) VALUES ('{$_SESSION['email']}', $image_id)");
  }

  // Redirect to the same page to prevent duplicate form submissions
  header("Location: profile.php");
  exit();

} elseif (isset($_POST['unfavorite'])) {
  $image_id = $_POST['image_id'];
  $db->exec("DELETE FROM favorites WHERE email = '{$_SESSION['email']}' AND image_id = $image_id");

  // Redirect to the same page to prevent duplicate form submissions
  header("Location: profile.php");
  exit();
} 

// Get all of the images uploaded by the current user
$stmt = $db->prepare("SELECT * FROM images WHERE email = :email ORDER BY id DESC");
$stmt->bindValue(':email', $email);
$result = $stmt->execute();

// Count the number of images uploaded by the current user
$count = 0;
while ($image = $result->fetchArray()) {
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
          <p class="text-center text-muted fw-semibold"><small>user id: <?php echo $user_id; ?></small></p>
          <div class="btn-group mt-2 mb-3" role="group" aria-label="Basic example">
            <a class="btn btn-sm btn-secondary rounded fw-bold opacity-50" href="follower.php?id=<?php echo $user_id; ?>"><?php echo $num_followers ?> <small>followers</small></a>
            <a class="btn btn-sm btn-secondary ms-1 rounded fw-bold opacity-50" href="following.php?id=<?php echo $user_id; ?>"><?php echo $num_following ?> <small>following</small></a>
            <a class="btn btn-sm btn-secondary ms-1 rounded fw-bold opacity-50" href="album.php"><i class="bi bi-images"></i> <small>my albums</small></a>
          </div>
          <p class="text-center text-secondary fw-bold container-fluid" style="word-break: break-word; width: 97%;">
          <?php
            if (!empty($desc)) {
              $replacedDesc = preg_replace('/\b(https?:\/\/\S+)/i', '<a class="text-decoration-none" target="_blank" href="$1">$1</a>', $desc);
              echo $replacedDesc;
            }
          ?>
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
    <div class="images">
      <?php while ($image = $result->fetchArray()): ?>
        <div class="image-container">
          <a class="shadow" href="image.php?artworkid=<?php echo $image['id']; ?>">
            <img class="lazy-load" data-src="thumbnails/<?php echo $image['filename']; ?>">
          </a> 
          <div>
            <button class="p-b1 btn btn-sm btn-dark opacity-50 fw-bold" data-bs-toggle="modal" data-bs-target="#deleteImage_<?php echo $image['id']; ?>"><i class="bi bi-trash-fill"></i></button>
            <form action="delete.php" method="post">
              <!-- Modal -->
              <div class="modal fade" id="deleteImage_<?php echo $image['id']; ?>" tabindex="-1" aria-labelledby="exampleModalLabel" aria-hidden="true">
                <div class="modal-dialog" role="document">
                  <div class="modal-content rounded-3 shadow">
                    <div class="modal-body p-4 text-center">
                      <h5 class="mb-0">Are you sure want to delete the selected image?</h5>
                      <p class="mb-0 mt-2">This action can't be undo! Make sure you download the image before you delete it.</p>
                    </div>
                    <div class="modal-footer flex-nowrap p-0">
                      <input type="hidden" name="id" value="<?php echo $image['id']; ?>">
                      <button class="btn btn-lg text-danger btn-link fs-6 text-decoration-none col-6 py-3 m-0 rounded-0 border-end" type="submit" value="Delete"><strong>Yes, delete the image!</strong></button>
                      <button type="button" class="btn btn-lg btn-link fs-6 text-decoration-none col-6 py-3 m-0 rounded-0" data-bs-dismiss="modal">Cancel, keep it!</button>
                    </div>
                  </div>
                </div>
              </div>
            </form>
            <button class="p-b2 btn btn-sm btn-dark opacity-50 fw-bold" onclick="location.href='edit_image.php?id=<?php echo $image['id']; ?>'" ><i class="bi bi-pencil-fill"></i></button> 
            <?php
              $is_favorited = $db->querySingle("SELECT COUNT(*) FROM favorites WHERE email = '$email' AND image_id = {$image['id']}");
              if ($is_favorited) {
            ?>
              <form method="POST">
                <input type="hidden" name="image_id" value="<?php echo $image['id']; ?>">
                <button type="submit" class="p-b3 btn btn-sm btn-dark opacity-50 fw-bold" name="unfavorite"><i class="bi bi-heart-fill"></i></button>
              </form>
            <?php } else { ?>
              <form method="POST">
                <input type="hidden" name="image_id" value="<?php echo $image['id']; ?>">
                <button type="submit" class="p-b3 btn btn-sm btn-dark opacity-50 fw-bold" name="favorite"><i class="bi bi-heart"></i></button>
              </form>
            <?php } ?> 
          </div>
        </div>
      <?php endwhile; ?>
    </div>
    <style>
      .img-sns {
        margin-top: -4px;
      }
    
      @media (min-width: 768px) {
        .p-b1 {
          margin-top: -396px;
          margin-left: 5px;
          border-radius: 4px 4px 4px 4px;
        }
        
        .p-b2 {
          margin-top: -180px; 
          margin-left: 5px;
          border-radius: 4px 4px 0 0;
        }
        
        .p-b3 {
          margin-top: -166px; 
          margin-left: 5px;
          border-radius: 0 0 4px 4px; 
        } 
        
        .image-container {
          margin-bottom: -71px;  
        }
        
        .b-section {
          margin-top: 50px;
        }
      }
      
      @media (max-width: 767px) {
        .p-b1 {
          margin-top: -393px;
          margin-left: 5px;
          border-radius: 4px 4px 4px 4px;
        }
        
        .p-b2 {
          margin-top: -177px; 
          margin-left: 5px;
          border-radius: 4px 4px 0 0;
        }
        
        .p-b3 {
          margin-top: -163px; 
          margin-left: 5px;
          border-radius: 0 0 4px 4px; 
        } 
        
        .image-container {
          margin-bottom: -71px;  
        }
        
        .d-none-sm-b {
          display: none;
        }
      }

      @media (max-width: 600px) {
        .p-b1 {
          margin-top: -195px;
          margin-left: 5px;
          border-radius: 4px 4px 0 0;
        }
        
        .p-b2 {
          margin-top: -181px; 
          margin-left: 5px;
          border-radius: 0 0 0 0;
        }
        
        .p-b3 {
          margin-top: -167px;
          margin-left: 5px;
          border-radius: 0 0 4px 4px; 
        }
        
        .image-container {
          margin-bottom: -72px;  
        }
      } 
       
      @media (max-width: 540px) {
        .p-b1 {
          margin-top: -195px;
          margin-left: 5px;
          border-radius: 4px 4px 0 0;
        }
        
        .p-b2 {
          margin-top: -182px; 
          margin-left: 5px;
          border-radius: 0 0 0 0;
        }
        
        .p-b3 {
          margin-top: -168px;
          margin-left: 5px;
          border-radius: 0 0 4px 4px; 
        }
        
        .image-container {
          margin-bottom: -72px;  
        }
      } 
      
      @media (max-width: 450px) {
        .p-b1 {
          margin-top: -194px;
          margin-left: 5px;
          border-radius: 4px 4px 0 0;
        }
        
        .p-b2 {
          margin-top: -180px; 
          margin-left: 5px;
          border-radius: 0 0 0 0;
        }
        
        .p-b3 {
          margin-top: -166px;
          margin-left: 5px;
          border-radius: 0 0 4px 4px; 
        }
        
        .image-container {
          margin-bottom: -72px;  
        }
      } 
      
      @media (max-width: 415px) {
        .p-b1 {
          margin-top: -194px;
          margin-left: 5px;
          border-radius: 4px 4px 0 0;
        }
        
        .p-b2 {
          margin-top: -180px; 
          margin-left: 5px;
          border-radius: 0 0 0 0;
        }
        
        .p-b3 {
          margin-top: -166px;
          margin-left: 5px;
          border-radius: 0 0 4px 4px; 
        }
        
        .image-container {
          margin-bottom: -72px;  
        }
      } 
      
      @media (max-width: 380px) {
        .p-b1 {
          margin-top: -192px;
          margin-left: 5px;
          border-radius: 4px 4px 0 0;
        }
        
        .p-b2 {
          margin-top: -178px; 
          margin-left: 5px;
          border-radius: 0 0 0 0;
        }
        
        .p-b3 {
          margin-top: -164px;
          margin-left: 5px;
          border-radius: 0 0 4px 4px; 
        }
        
        .image-container {
          margin-bottom: -72px;  
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

      @media (min-width: 768px) {
        /* For desktop view, change the grid layout */
        .images {
          grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
        }
      }

      .images a {
        display: block;
        border-radius: 4px;
        overflow: hidden;
      }

      .images img {
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
