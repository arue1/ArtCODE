<?php
session_start();
if (!isset($_SESSION['email'])) {
  header("Location: session.php");
  exit;
}

$email = $_SESSION['email'];

// Connect to the database
$db = new PDO('sqlite:database.sqlite');

// Get the current user's ID
$current_user_id = $_GET['id'];

// Get the current user's email and artist
$query = $db->prepare('SELECT email, artist FROM users WHERE id = :id');
$query->bindParam(':id', $current_user_id);
$query->execute();
$current_user = $query->fetch();
$current_email = $current_user['email'];
$current_artist = $current_user['artist'];

// Process any favorite/unfavorite requests
if (isset($_POST['favorite']) || isset($_POST['unfavorite'])) {
  $image_id = $_POST['image_id'];
  
  // Check if the image ID is valid
  $query = $db->prepare('SELECT COUNT(*) FROM images WHERE id = :id');
  $query->bindParam(':id', $image_id);
  $query->execute();
  $valid_image_id = $query->fetchColumn();
  
  if ($valid_image_id) {
    // Check if the image has already been favorited by the current user
    $query = $db->prepare('SELECT COUNT(*) FROM favorites WHERE email = :email AND image_id = :image_id');
    $query->bindParam(':email', $email);
    $query->bindParam(':image_id', $image_id);
    $query->execute();
    $existing_fav = $query->fetchColumn();

    if (isset($_POST['favorite'])) {
      if ($existing_fav == 0) {
        $query = $db->prepare('INSERT INTO favorites (email, image_id) VALUES (:email, :image_id)');
        $query->bindParam(':email', $email);
        $query->bindParam(':image_id', $image_id);
        $query->execute();
      }
    } elseif (isset($_POST['unfavorite'])) {
      if ($existing_fav > 0) {
        $query = $db->prepare('DELETE FROM favorites WHERE email = :email AND image_id = :image_id');
        $query->bindParam(':email', $email);
        $query->bindParam(':image_id', $image_id);
        $query->execute();
      }
    }
  }
  
  // Redirect to the same page to prevent duplicate form submissions
  header("Location: list_favorite.php?id={$current_user_id}");
  exit();
}

// Get all the images favorited by the current user, ordered by ID in descending order
$query = $db->prepare('SELECT images.filename, images.id, images.imgdesc, images.title FROM images JOIN favorites ON images.id = favorites.image_id WHERE favorites.email = :email ORDER BY favorites.id DESC');
$query->bindParam(':email', $current_email);
$query->execute();
$favorite_images = $query->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html>
  <head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?php echo $current_artist; ?>'s favorite</title>
    <link rel="icon" type="image/png" href="icon/favicon.png">
    <?php include('bootstrapcss.php'); ?>
  </head>
  <body>
    <?php include('header.php'); ?>
    <h5 class="text-secondary fw-bold text-center text-break mt-2"><a class="text-decoration-none link-secondary" href="artist.php?id=<?php echo $current_user_id; ?>"><?php echo $current_artist; ?>'s</a> Favorites</h5>
    <?php if (count($favorite_images) > 0): ?>
      <div class="images">
        <?php foreach ($favorite_images as $image): ?>
          <div class="image-container">
            <div class="position-relative">
              <a class="shadow rounded imagesA" href="image.php?artworkid=<?php echo $image['id']; ?>">
                <img class="lazy-load imagesImg" data-src="thumbnails/<?php echo $image['filename']; ?>" alt="<?php echo $image['title']; ?>">
              </a> 
              <div class="position-absolute top-0 start-0">
                <div class="dropdown">
                  <button class="btn btn-sm btn-dark ms-1 mt-1 rounded-1 opacity-50" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                    <i class="bi bi-three-dots-vertical"></i>
                  </button>
                  <ul class="dropdown-menu">
                    <?php
                      $is_favorited = $db->query("SELECT COUNT(*) FROM favorites WHERE email = '{$_SESSION['email']}' AND image_id = {$image['id']}")->fetchColumn();
                      if ($is_favorited) {
                    ?>
                      <form method="POST">
                        <input type="hidden" name="image_id" value="<?php echo $image['id']; ?>">
                        <li><button type="submit" class="dropdown-item fw-bold" name="unfavorite"><i class="bi bi-heart-fill"></i> <small>unfavorite</small></button></li>
                      </form>
                    <?php } else { ?>
                      <form method="POST">
                        <input type="hidden" name="image_id" value="<?php echo $image['id']; ?>">
                        <li><button type="submit" class="dropdown-item fw-bold" name="favorite"><i class="bi bi-heart"></i> <small>favorite</small></button></li>
                      </form>
                    <?php } ?>
                    <li><button class="dropdown-item fw-bold" onclick="shareImage(<?php echo $image['id']; ?>)"><i class="bi bi-share-fill"></i> <small>share</small></button></li>
                    <li><button class="dropdown-item fw-bold" data-bs-toggle="modal" data-bs-target="#infoImage_<?php echo $image['id']; ?>"><i class="bi bi-info-circle-fill"></i> <small>info</small></button></li>
                  </ul>
                  <!-- Modal -->
                  <div class="modal fade" id="infoImage_<?php echo $image['id']; ?>" tabindex="-1" aria-labelledby="exampleModalLabel" aria-hidden="true">
                    <div class="modal-dialog modal-fullscreen" role="document">
                      <div class="modal-content shadow">
                        <div class="modal-body p-4 text-center">
                          <h5 class="modal-title fw-bold text-start mb-2"><?php echo $image['title']?></h5>
                          <div class="row featurette">
                            <div class="col-md-5 order-md-1 mb-2">
                              <div class="position-relative">
                                <a href="image.php?artworkid=<?php echo $image['id']; ?>">
                                  <img class="rounded object-fit-cover mb-3 shadow lazy-load" data-src="thumbnails/<?php echo $image['filename']; ?>" alt="<?php echo $image['title']; ?>" style="width: 100%; height: 100%;">
                                </a>
                                <button type="button" class="btn btn-dark rounded fw-bold opacity-75 position-absolute top-0 end-0 mt-1 me-1" data-bs-dismiss="modal"><i class="bi bi-x text-stroke"></i></button>
                              </div>
                            </div>
                            <div class="col-md-7 order-md-2">
                              <p class="text-start fw-semibold">share to:</p>
                              <div class="btn-group w-100 mb-2" role="group" aria-label="Share Buttons">
                                <!-- Twitter -->
                                <a class="btn btn-outline-dark" href="https://twitter.com/intent/tweet?url=<?php echo (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https://' : 'http://') . $_SERVER['HTTP_HOST'] . '/image.php?artworkid=' . $image['id']; ?>" target="_blank" rel="noopener noreferrer">
                                  <i class="bi bi-twitter"></i>
                                </a>
                              
                                <!-- Line -->
                                <a class="btn btn-outline-dark" href="https://social-plugins.line.me/lineit/share?url=<?php echo (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https://' : 'http://') . $_SERVER['HTTP_HOST'] . '/image.php?artworkid=' . $image['id']; ?>" target="_blank" rel="noopener noreferrer">
                                  <i class="bi bi-line"></i>
                                </a>
                              
                                <!-- Email -->
                                <a class="btn btn-outline-dark" href="mailto:?body=<?php echo (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https://' : 'http://') . $_SERVER['HTTP_HOST'] . '/image.php?artworkid=' . $image['id']; ?>">
                                  <i class="bi bi-envelope-fill"></i>
                                </a>
                              
                                <!-- Reddit -->
                                <a class="btn btn-outline-dark" href="https://www.reddit.com/submit?url=<?php echo (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https://' : 'http://') . $_SERVER['HTTP_HOST'] . '/image.php?artworkid=' . $image['id']; ?>" target="_blank" rel="noopener noreferrer">
                                  <i class="bi bi-reddit"></i>
                                </a>
                              
                                <!-- Instagram -->
                                <a class="btn btn-outline-dark" href="https://www.instagram.com/?url=<?php echo (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https://' : 'http://') . $_SERVER['HTTP_HOST'] . '/image.php?artworkid=' . $image['id']; ?>" target="_blank" rel="noopener noreferrer">
                                  <i class="bi bi-instagram"></i>
                                </a>
                              
                                <!-- Facebook -->
                                <a class="btn btn-outline-dark" href="https://www.facebook.com/sharer/sharer.php?u=<?php echo (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https://' : 'http://') . $_SERVER['HTTP_HOST'] . '/image.php?artworkid=' . $image['id']; ?>" target="_blank" rel="noopener noreferrer">
                                  <i class="bi bi-facebook"></i>
                                </a>
                              </div>
                              <div class="btn-group w-100 mt-2 mb-3">
                                <a class="btn btn-outline-dark fw-bold" href="image.php?artworkid=<?php echo $image['id']; ?>"><i class="bi bi-eye-fill"></i> view</a>
                                <a class="btn btn-outline-dark fw-bold" href="images/<?php echo $image['filename']; ?>" download><i class="bi bi-download text-stroke"></i> download</a>
                                <button class="btn btn-outline-dark fw-bold" onclick="shareImage(<?php echo $image['id']; ?>)"><i class="bi bi-share-fill text-stroke"></i> share</button>
                              </div>
                              <p class="text-start fw-semibold" style="word-wrap: break-word;">
                                <?php
                                  $messageText = $image['imgdesc'];
                                  $messageTextWithoutTags = strip_tags($messageText);
                                  $pattern = '/\bhttps?:\/\/\S+/i';

                                  $formattedText = preg_replace_callback($pattern, function ($matches) {
                                    $url = htmlspecialchars($matches[0]);
                                    return '<a href="' . $url . '">' . $url . '</a>';
                                  }, $messageTextWithoutTags);

                                  $formattedTextWithLineBreaks = nl2br($formattedText);
                                  echo $formattedTextWithLineBreaks;
                                ?>
                              </p>
                              <div class="card container">
                                <p class="text-center fw-semibold mt-2">Image Information</p>
                                <p class="text-start fw-semibold">Image ID: "<?php echo $image['id']?>"</p>
                                <?php
                                  // Get image size in megabytes
                                  $image_size = round(filesize('images/' . $image['filename']) / (1024 * 1024), 2);

                                  // Get image dimensions
                                  list($width, $height) = getimagesize('images/' . $image['filename']);
                            
                                  // Display image information
                                  echo "<p class='text-start fw-semibold'>Image data size: " . $image_size . " MB</p>";
                                  echo "<p class='text-start fw-semibold'>Image dimensions: " . $width . "x" . $height . "</p>";
                                  echo "<p class='text-start fw-semibold'><a class='text-decoration-none' href='images/" . $image['filename'] . "'>View original image</a></p>";
                                ?>
                              </div>
                              <div class="container mt-2">
                                <?php
                                  $tags = explode(',', $image['tags']);
                                  foreach ($tags as $tag) {
                                    $tag = trim($tag);
                                    if (!empty($tag)) {
                                ?>
                                  <a href="tagged_images.php?tag=<?php echo urlencode($tag); ?>"
                                    class="btn btn-sm btn-secondary mb-1 rounded-3 fw-bold opacity-50">
                                    <?php echo $tag; ?>
                                  </a>
                                <?php }
                                } ?>
                              </div>
                            </div>
                          </div>
                        </div>
                      </div>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    <?php else: ?>
      <div class='container'>
        <p class="text-secondary text-center fw-bold">Oops... sorry, no favorited images!</p>
        <p class='text-secondary text-center fw-bold'>The one that make sense is, this user hasn't favorited any image...</p>
        <img src='icon/Empty.svg' style='width: 100%; height: 100%;'>
      </div>
    <?php endif; ?>
    <div style="position: fixed; bottom: 20px; right: 20px;">
      <button class="btn btn-primary rounded-pill fw-bold btn-md" onclick="goBack()">
        <i class="bi bi-arrow-left-circle-fill"></i> Back
      </button>
    </div> 
    <div class="mt-5"></div>
    <style>
      .img-sns {
        margin-top: -4px;
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
      function goBack() {
        window.location.href = "artist.php?id=<?php echo $current_user_id; ?>";
      }
    </script>
    <?php include('bootstrapjs.php'); ?>
  </body>
</html> 