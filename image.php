<?php
session_start();
if (!isset($_SESSION['email'])) {
  header("Location: session.php");
  exit;
}

// Connect to the database using PDO
$db = new PDO('sqlite:database.sqlite');

// Get the filename from the query string
$filename = $_GET['artworkid'];

// Get the current image information from the database
$stmt = $db->prepare("SELECT * FROM images WHERE id = :filename ");
$stmt->bindParam(':filename', $filename);
$stmt->execute();
$image = $stmt->fetch();

// Get the ID of the current image and the email of the owner
$image_id = $image['id'];
$email = $image['email'];

// Get the previous image information from the database
$stmt = $db->prepare("SELECT * FROM images WHERE id < :id AND email = :email ORDER BY id DESC LIMIT 1");
$stmt->bindParam(':id', $image_id);
$stmt->bindParam(':email', $email);
$stmt->execute();
$prev_image = $stmt->fetch();

// Get the next image information from the database
$stmt = $db->prepare("SELECT * FROM images WHERE id > :id AND email = :email ORDER BY id ASC LIMIT 1");
$stmt->bindParam(':id', $image_id);
$stmt->bindParam(':email', $email);
$stmt->execute();
$next_image = $stmt->fetch();

// Get the image information from the database
$stmt = $db->prepare("SELECT * FROM images WHERE id = :filename");
$stmt->bindParam(':filename', $filename);
$stmt->execute();
$image = $stmt->fetch();
$image_id = $image['id'];

// Check if the user is logged in and get their email
$email = '';
if (isset($_SESSION['email'])) {
  $email = $_SESSION['email'];
}

// Get the email of the selected user
$user_email = $image['email'];

// Get the selected user's information from the database
$query = $db->prepare('SELECT * FROM users WHERE email = :email');
$query->bindParam(':email', $user_email);
$query->execute();
$user = $query->fetch();

// Check if the logged-in user is already following the selected user
$query = $db->prepare('SELECT COUNT(*) FROM following WHERE follower_email = :follower_email AND following_email = :following_email');
$query->bindParam(':follower_email', $email);
$query->bindParam(':following_email', $user_email);
$query->execute();
$is_following = $query->fetchColumn();

// Handle following/unfollowing actions
if (isset($_POST['follow'])) {
  // Add a following relationship between the logged-in user and the selected user
  $query = $db->prepare('INSERT INTO following (follower_email, following_email) VALUES (:follower_email, :following_email)');
  $query->bindParam(':follower_email', $email);
  $query->bindParam(':following_email', $user_email);
  $query->execute();
  $is_following = true;
  header("Location: image.php?artworkid={$image['id']}");
  exit;
} elseif (isset($_POST['unfollow'])) {
  // Remove the following relationship between the logged-in user and the selected user
  $query = $db->prepare('DELETE FROM following WHERE follower_email = :follower_email AND following_email = :following_email');
  $query->bindParam(':follower_email', $email);
  $query->bindParam(':following_email', $user_email);
  $query->execute();
  $is_following = false;
  header("Location: image.php?artworkid={$image['id']}");
  exit;
} 
// Process any favorite/unfavorite requests
if (isset($_POST['favorite'])) {
  $image_id = $_POST['image_id'];

  // Check if the image has already been favorited by the current user
  $stmt = $db->prepare("SELECT COUNT(*) FROM favorites WHERE email = :email AND image_id = :image_id");
  $stmt->bindParam(':email', $_SESSION['email']);
  $stmt->bindParam(':image_id', $image_id);
  $stmt->execute();
  $existing_fav = $stmt->fetchColumn();

  if ($existing_fav == 0) {
    $stmt = $db->prepare("INSERT INTO favorites (email, image_id) VALUES (:email, :image_id)");
    $stmt->bindParam(':email', $_SESSION['email']);
    $stmt->bindParam(':image_id', $image_id);
    $stmt->execute();
  }

  // Redirect to the same page to prevent duplicate form submissions
  header("Location: image.php?artworkid={$image['id']}");
  exit();

} elseif (isset($_POST['unfavorite'])) {
  $image_id = $_POST['image_id'];
  $stmt = $db->prepare("DELETE FROM favorites WHERE email = :email AND image_id = :image_id");
  $stmt->bindParam(':email', $_SESSION['email']);
  $stmt->bindParam(':image_id', $image_id);
  $stmt->execute();

  // Redirect to the same page to prevent duplicate form submissions
  header("Location: image.php?artworkid={$image['id']}");
  exit();
}

$url = "comment_preview.php?imageid=" . $image_id;

// Increment the view count for the image
$stmt = $db->prepare("UPDATE images SET view_count = view_count + 1 WHERE id = :filename");
$stmt->bindParam(':filename', $filename);
$stmt->execute();

// Get the updated image information from the database
$stmt = $db->prepare("SELECT * FROM images WHERE id = :filename");
$stmt->bindParam(':filename', $filename);
$stmt->execute();
$image = $stmt->fetch();

// Retrieve the updated view count from the image information
$viewCount = $image['view_count'];

// Create the "history" table if it does not exist
$stmt = $db->prepare("CREATE TABLE IF NOT EXISTS history (id INTEGER PRIMARY KEY AUTOINCREMENT, history TEXT, email TEXT, image_artworkid TEXT, date_history DATETIME)");
$stmt->execute();

// Store the link URL and image ID into the "history" table
if (isset($_GET['artworkid'])) {
  $artworkId = $_GET['artworkid'];
  $uri = $_SERVER['REQUEST_URI'];
  $email = $_SESSION['email'];
  $currentDate = date('Y-m-d'); // Get the current date

  // Check if the same URL and image ID exist in the history for the current day
  $stmt = $db->prepare("SELECT * FROM history WHERE history = :history AND image_artworkid = :artworkId AND email = :email AND date_history = :date_history");
  $stmt->bindParam(':history', $uri);
  $stmt->bindParam(':artworkId', $artworkId);
  $stmt->bindParam(':email', $email);
  $stmt->bindParam(':date_history', $currentDate);
  $stmt->execute();
  $existing_entry = $stmt->fetch();

  if (!$existing_entry) {
    // Insert the URL and image ID into the history table
    $stmt = $db->prepare("INSERT INTO history (history, email, image_artworkid, date_history) VALUES (:history, :email, :artworkId, :date_history)");
    $stmt->bindParam(':history', $uri);
    $stmt->bindParam(':email', $email);
    $stmt->bindParam(':artworkId', $artworkId);
    $stmt->bindParam(':date_history', $currentDate);
    $stmt->execute();
  }
}

// Get all child images associated with the current image from the "image_child" table
$stmt = $db->prepare("SELECT * FROM image_child WHERE image_id = :image_id");
$stmt->bindParam(':image_id', $image_id);
$stmt->execute();
$child_images = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Count the total number of images from "images" table for the specific artworkid
$stmt = $db->prepare("SELECT COUNT(*) as total_images FROM images WHERE id = :filename");
$stmt->bindParam(':filename', $filename);
$stmt->execute();
$total_images = $stmt->fetch()['total_images'];

// Count the total number of images from "image_child" table for the specific artworkid
$stmt = $db->prepare("SELECT COUNT(*) as total_child_images FROM image_child WHERE image_id = :filename");
$stmt->bindParam(':filename', $filename);
$stmt->execute();
$total_child_images = $stmt->fetch()['total_child_images'];

// Calculate the combined total
$total_all_images = $total_images + $total_child_images;
?> 

<!DOCTYPE html>
<html lang="en">
  <head>
    <meta charset="UTF-8"> 
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?php echo $image['title']; ?></title>
    <link rel="icon" type="image/png" href="icon/favicon.png">
    <?php include('bootstrapcss.php'); ?>
  </head>
  <body>
    <?php include('header.php'); ?>
    <div class="mt-2">
      <div class="container-fluid mb-2 d-flex d-md-none d-lg-none">
        <?php
          $stmt = $db->prepare("SELECT u.id, u.email, u.password, u.artist, u.pic, u.desc, u.bgpic, i.id AS image_id, i.filename, i.tags FROM users u INNER JOIN images i ON u.id = i.id WHERE u.id = :id");
          $stmt->bindParam(':id', $id);
          $stmt->execute();
          $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
        ?>
        <div class="d-flex">
          <a class="text-decoration-none text-dark fw-bold rounded-pill" href="#" data-bs-toggle="modal" data-bs-target="#userModal">
            <?php if (!empty($user['pic'])): ?>
              <img class="object-fit-cover border border-1 rounded-circle" src="<?php echo $user['pic']; ?>" style="width: 32px; height: 32px;">
            <?php else: ?>
              <img class="object-fit-cover border border-1 rounded-circle" src="icon/profile.svg" style="width: 32px; height: 32px;">
            <?php endif; ?>
            <?php echo (mb_strlen($user['artist']) > 10) ? mb_substr($user['artist'], 0, 10) . '...' : $user['artist']; ?> <small class="badge rounded-pill bg-primary"><i class="bi bi-globe-asia-australia"></i> <?php echo $user['region']; ?></small>
          </a>
        </div>
        <div class="ms-auto">
          <form method="post">
            <?php if ($is_following): ?>
              <button class="btn btn-sm btn-secondary rounded-pill fw-bold opacity-50" type="submit" name="unfollow"><i class="bi bi-person-dash-fill"></i> unfollow</button>
            <?php else: ?>
              <button class="btn btn-sm btn-secondary rounded-pill fw-bold opacity-50" type="submit" name="follow"><i class="bi bi-person-fill-add"></i> follow</button>
            <?php endif; ?>
          </form>
        </div>
      </div>
      <div class="modal fade" id="userModal" tabindex="-1" aria-labelledby="exampleModalLabel" aria-hidden="true">
        <div class="modal-dialog">
          <div class="modal-content">
            <div class="modal-header border-bottom-0">
              <h5 class="modal-title fw-bold fs-5" id="exampleModalLabel"><?php echo $user['artist']; ?></h5>
              <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
              <div class="row featurette">
                <div class="col-5 order-1">
                  <a class="text-decoration-none d-flex justify-content-center text-dark fw-bold rounded-pill" href="artist.php?id=<?= $user['id'] ?>">
                    <?php if (!empty($user['pic'])): ?>
                      <img class="object-fit-cover border border-3 rounded-circle" src="<?php echo $user['pic']; ?>" style="width: 103px; height: 103px;">
                    <?php else: ?>
                      <img class="object-fit-cover border border-3 rounded-circle" src="icon/profile.svg" style="width: 103px; height: 103px;">
                    <?php endif; ?>
                  </a>
                </div>
                <div class="col-7 order-2">
                  <div class="btn-group w-100 mb-1 gap-1" role="group" aria-label="Basic example">
                    <a class="btn btn-sm btn-outline-secondary rounded fw-bold" href="follower.php?id=<?php echo $user['id']; ?>"><small>followers</small></a>
                    <a class="btn btn-sm btn-outline-secondary rounded fw-bold" href="following.php?id=<?php echo $user['id']; ?>"><small>following</small></a>
                  </div>
                  <div class="btn-group w-100 mb-1 gap-1" role="group" aria-label="Basic example">
                    <a class="btn btn-sm btn-outline-secondary rounded fw-bold" href="artist.php?id=<?php echo $user['id']; ?>"><small>images</small></a>
                    <a class="btn btn-sm btn-outline-secondary rounded fw-bold" href="list_favorite.php?id=<?php echo $user['id']; ?>"><small>favorites</small></a> 
                  </div>
                  <a class="btn btn-sm btn-outline-secondary w-100 rounded fw-bold" href="artist.php?id=<?php echo $user['id']; ?>"><small>view profile</small></a>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
      <div class="roow">
        <div class="cool-6">
          <div class="caard position-relative">
            <a href="#" id="originalImageLink" data-bs-toggle="modal" data-bs-target="#originalImageModal" data-original-src="images/<?php echo $image['filename']; ?>">
              <img class="img-fluid rounded-r" src="thumbnails/<?= $image['filename'] ?>" alt="<?php echo $image['title']; ?>" style="height: 100%; width: 100%;">
            </a>
            <!-- Original Image Modal -->
            <div class="modal fade" id="originalImageModal" tabindex="-1" aria-labelledby="exampleModalLabel" aria-hidden="true">
              <div class="modal-dialog modal-dialog-centered modal-fullscreen modal-dialog-scrollable"> <!-- Add the modal-dialog-scrollable class -->
                <div class="modal-content border-0 bg-dark">
                  <div class="modal-body scrollable-div d-flex align-items-center justify-content-center p-0">
                    <div class="position-relative h-100 w-100 align-items-center">
                      <div class="position-relative">
                        <img id="originalImage" class="img-fluid mb-1" src="" alt="Original Image" style="width: 100%; height: auto;">
                        <div class="card-img-overlay position-absolute bottom-0 start-0 m-3">
                          <h6 class="card-title text-white fw-bold shadowed-text">
                            <?php echo $image['title']; ?>          
                          </h6>
                        </div>
                        <div class="position-absolute bottom-0 start-0 m-3">
                          <h6 class="card-title text-white fw-bold shadowed-text">by           
                            <a class="text-decoration-none text-white shadowed-text fw-bold rounded-pill" href="artist.php?id=<?= $user['id'] ?>">
                              <?php if (!empty($user['pic'])): ?>
                                <img class="object-fit-cover border border-1 rounded-circle" src="<?php echo $user['pic']; ?>" style="width: 24px; height: 24px;">
                              <?php else: ?>
                                <img class="object-fit-cover border border-1 rounded-circle" src="icon/profile.svg" style="width: 24px; height: 24px;">
                              <?php endif; ?>
                              <?php echo (mb_strlen($user['artist']) > 25) ? mb_substr($user['artist'], 0, 25) . '...' : $user['artist']; ?>
                            </a> 
                          </h6>
                        </div>
                      </div>
                      <div class="image-container position-relative">
                        <a href="images/<?php echo $image['filename']; ?>" class="btn btn-sm btn-dark rounded-3 opacity-75 fw-bold position-absolute bottom-0 end-0 m-3" download>
                          <i class="bi bi-cloud-arrow-down-fill"></i> download
                        </a>
                      </div>
                      <?php foreach ($child_images as $child_image) : ?>
                        <div class="image-container position-relative">
                          <img data-src="images/<?php echo $child_image['filename']; ?>" class="mb-1 lazy-load" style="height: 100%; width: 100%;" alt="<?php echo $image['title']; ?>">
                          <a href="images/<?php echo $child_image['filename']; ?>" class="btn btn-sm btn-dark rounded-3 opacity-75 fw-bold position-absolute bottom-0 end-0 m-3" download>
                            <i class="bi bi-cloud-arrow-down-fill"></i> download
                          </a>
                          <div class="card-img-overlay position-absolute bottom-0 start-0 m-3">
                            <h6 class="card-title text-white fw-bold shadowed-text">
                              <?php echo $image['title']; ?>          
                            </h6>
                          </div>
                          <button type="button" class="btn position-absolute border-0 top-0 end-0 m-2" data-bs-dismiss="modal">
                            <i class="bi bi-chevron-down text-white shadowed-text text-stroke fs-5"></i>
                          </button>
                          <div class="position-absolute bottom-0 start-0 m-3">
                            <h6 class="card-title text-white fw-bold shadowed-text">by           
                              <a class="text-decoration-none text-white shadowed-text fw-bold rounded-pill" href="artist.php?id=<?= $user['id'] ?>">
                                <?php if (!empty($user['pic'])): ?>
                                  <img class="object-fit-cover border border-1 rounded-circle" src="<?php echo $user['pic']; ?>" style="width: 24px; height: 24px;">
                                <?php else: ?>
                                  <img class="object-fit-cover border border-1 rounded-circle" src="icon/profile.svg" style="width: 24px; height: 24px;">
                                <?php endif; ?>
                                <?php echo (mb_strlen($user['artist']) > 25) ? mb_substr($user['artist'], 0, 25) . '...' : $user['artist']; ?>
                              </a> 
                            </h6>
                          </div>
                        </div>
                      <?php endforeach; ?>
                      <button type="button" class="btn position-absolute border-0 top-0 end-0 m-2" data-bs-dismiss="modal">
                        <i class="bi bi-chevron-down text-white shadowed-text text-stroke fs-5"></i>
                      </button>
                      <?php
                        // Get all images for the given user_email
                        $stmt = $db->prepare("SELECT id, filename, tags, title FROM images WHERE email = :email ORDER BY id DESC");
                        $stmt->bindParam(':email', $user_email);
                        $stmt->execute();
                        $images = $stmt->fetchAll(PDO::FETCH_ASSOC);
                      ?>

                      <div class="mt-2 mb-2 w-98 scroll-container media-scrollerF snaps-inlineF overflow-auto">
                        <?php $count = 0; ?>
                        <?php foreach ($images as $imageU): ?>
                          <?php
                            $image_idF = $imageU['id'];
                            $image_urlF = $imageU['filename'];
                            $image_titleF = $imageU['title'];
                            $current_image_idF = isset($_GET['artworkid']) ? $_GET['artworkid'] : null;
                            ?>
                            <div class="media-elementF d-inline-flex">
                              <a href="image.php?artworkid=<?php echo $image_idF; ?>">
                                <img class="hori <?php echo ($image_idF == $current_image_idF) ? 'opacity-50' : ''; ?>" src="thumbnails/<?php echo $image_urlF; ?>" alt="<?php echo $image_titleF; ?>">
                              </a>
                            </div>
                          <?php $count++; ?>
                          <?php if ($count >= 25) break; ?>
                        <?php endforeach; ?>
                        <button id="loadMoreBtnF" class="btn btn-secondary hori opacity-25"><i class="bi bi-plus-circle display-5 text-stroke"></i></button>
                        <script>
                          var currentIndexF = <?php echo $count; ?>;
                          var imagesF = <?php echo json_encode($images); ?>;
                          var containerF = document.querySelector('.media-scrollerF');
                          var loadMoreBtnF = document.getElementById('loadMoreBtnF');

                          function loadMoreImagesF() {
                            for (var i = currentIndexF; i < currentIndexF + 25 && i < imagesF.length; i++) {
                              var imageUF = imagesF[i];
                              var image_idF = imageUF['id'];
                              var image_urlF = imageUF['filename'];
                              var image_titleF = imageUF['title'];
                              var current_image_idF = '<?php echo $current_image_idF; ?>';

                              var mediaElementF = document.createElement('div');
                              mediaElementF.classList.add('media-elementF');
                              mediaElementF.classList.add('d-inline-flex');

                              var linkF = document.createElement('a');
                              linkF.href = 'image.php?artworkid=' + image_idF;

                              var imageF = document.createElement('img');
                              imageF.classList.add('hori');
                              if (image_idF == current_image_idF) {
                                imageF.classList.add('opacity-50');
                              }
                              imageF.src = 'thumbnails/' + image_urlF;
                              imageF.alt = image_titleF;

                              linkF.appendChild(imageF);
                              mediaElementF.appendChild(linkF);
                              containerF.insertBefore(mediaElementF, loadMoreBtnF);
                            }

                            currentIndexF += 25;
                            if (currentIndexF >= imagesF.length) {
                              loadMoreBtnF.style.display = 'none';
                            }
                          }

                          loadMoreBtnF.addEventListener('click', loadMoreImagesF);
                        </script>
                      </div>
                      <div class="roow mb-5">
                        <div class="cool-6 d-flex mb-3 justify-content-center">
                          <?php
                            // Get all images for the given user_email
                            $stmt = $db->prepare("SELECT * FROM images WHERE email = :email ORDER BY id DESC");
                            $stmt->bindParam(':email', $user_email);
                            $stmt->execute();
                            $images = $stmt->fetchAll(PDO::FETCH_ASSOC);
                          ?>
                          <div id="image-carouselF" class="carousel slide carousel-fade mt-2 w-98">
                            <div class="carousel-inner">
                              <?php
                                $current_image_id = isset($_GET['artworkid']) ? $_GET['artworkid'] : null;
                                $active_index = 0;
                              ?>
                              <?php foreach ($images as $index => $imageU): ?>
                                <?php
                                  $image_id = $imageU['id'];
                                  $user_email = $imageU['email'];
                                  $image_url = $imageU['filename'];
                                  $image_title = $imageU['title'];
                                  $active_class = ($image_id == $current_image_id) ? 'active' : '';

                                  if ($active_class === 'active') {
                                    $active_index = $index;
                                  }
                                ?>
                                <div class="carousel-item <?php echo $active_class; ?>">
                                  <a href="image.php?artworkid=<?php echo $image_id; ?>">
                                    <img class="lazy-load d-block w-100 rounded object-fit-cover img-UF" style="object-position: top;" data-src="thumbnails/<?php echo $image_url; ?>" alt="<?php echo $image_title; ?>">
                                    <div class="carousel-caption">
                                      <h5 class="fw-bold shadowed-text"><?php echo $image_title; ?></h5>
                                    </div>
                                  </a>
                                </div>
                              <?php endforeach; ?>
                            </div>
                            <div class="btn-group w-100 mt-3">
                              <button class="btn btn-outline-light" type="button" data-bs-target="#image-carouselF" data-bs-slide="prev">
                                <i class="bi bi-arrow-left-circle-fill"></i>
                                <span class="visually-hidden">Previous</span>
                              </button>
                              <button class="btn btn-outline-light" type="button" data-bs-target="#image-carouselF" data-bs-slide="next">
                                <i class="bi bi-arrow-right-circle-fill"></i>
                                <span class="visually-hidden">Next</span>
                              </button>
                            </div>
                          </div>
                        </div>
                        <div class="cool-6 mt-2">
                          <div class="d-flex justify-content-center">
                            <div class="w-98 fw-bold">
                              <div class="btn-group mb-3 w-100">
                                <?php
                                  $image_id = $image['id'];
                                  $stmt = $db->query("SELECT COUNT(*) FROM favorites WHERE image_id = $image_id");
                                  $fav_count = $stmt->fetchColumn();
                                  if ($fav_count >= 1000000000) {
                                    $fav_count = round($fav_count / 1000000000, 1) . 'b';
                                  } elseif ($fav_count >= 1000000) {
                                    $fav_count = round($fav_count / 1000000, 1) . 'm';
                                  } elseif ($fav_count >= 1000) {
                                    $fav_count = round($fav_count / 1000, 1) . 'k';
                                  }
                                  $stmt = $db->prepare("SELECT COUNT(*) FROM favorites WHERE email = :email AND image_id = :image_id");
                                  $stmt->bindParam(':email', $email);
                                  $stmt->bindParam(':image_id', $image_id);
                                  $stmt->execute();
                                  $is_favorited = $stmt->fetchColumn();
                                  if ($is_favorited) {
                                ?>
                                  <form action="image.php?artworkid=<?php echo $image['id']; ?>" method="POST">
                                    <input type="hidden" name="image_id" value="<?php echo $image['id']; ?>">
                                    <button type="submit" class="btn btn-sm btn-outline-light rounded-2 rounded-end-0 fw-bold" name="unfavorite"><i class="bi bi-heart-fill"></i> <small>unfavorite</small></button>
                                  </form>
                                <?php } else { ?>
                                  <form action="image.php?artworkid=<?php echo $image['id']; ?>" method="POST">
                                    <input type="hidden" name="image_id" value="<?php echo $image['id']; ?>">
                                    <button type="submit" class="btn btn-sm btn-outline-light rounded-2 rounded-end-0 fw-bold" name="favorite"><i class="bi bi-heart"></i> <small>favorite</small></button>
                                  </form>
                                <?php } ?>
                                <button class="btn btn-sm btn-outline-light rounded-0 rounded-start-0 fw-bold" onclick="sharePage()">
                                  <i class="bi bi-share-fill"></i> <small>share</small>
                                </button>
                                <a class="btn btn-sm btn-outline-light rounded-0 rounded-end-0 fw-bold" href="images/<?php echo $image['filename']; ?>">
                                  <i class="bi bi-eye-fill"></i> <small>full res</small>
                                </a>
                                <a class="btn btn-sm btn-outline-light fw-bold rounded-2 rounded-start-0" href="download_images.php?artworkid=<?= $image_id; ?>">
                                  <i class="bi bi-cloud-arrow-down-fill"></i> <small>download all</small>
                                </a>
                              </div>
                              <div class="container-fluid mb-4 text-white text-center align-items-center d-flex justify-content-center">
                                <button class="btn border-0 disabled fw-semibold">
                                  <small>
                                    <?php echo date('Y/m/d', strtotime($image['date'])); ?>
                                  </small
                                </button>
                                <button class="btn border-0 disabled fw-semibold"><i class="bi bi-heart-fill text-sm"></i> <small><?php echo $fav_count; ?> </small></button>
                                <button class="btn border-0 disabled fw-semibold"><i class="bi bi-eye-fill"></i> <small><?php echo $viewCount; ?> </small></button>
                              </div>
                              <h5 class="text-white text-center shadowed-text fw-bold"><?php echo $image['title']; ?></h5>
                              <div style="word-break: break-word;">
                                <p class="text-white shadowed-text" style="word-break: break-word;">
                                  <small>
                                    <?php
                                      $messageText = $image['imgdesc'];
                                      $messageTextWithoutTags = strip_tags($messageText);
                                      $pattern = '/\bhttps?:\/\/\S+/i';

                                      $formattedText = preg_replace_callback($pattern, function ($matches) {
                                        $url = htmlspecialchars($matches[0]);
                                        return '<a target="_blank" href="' . $url . '">' . $url . '</a>';
                                      }, $messageTextWithoutTags);

                                      $formattedTextWithLineBreaks = nl2br($formattedText);
                                      echo $formattedTextWithLineBreaks;
                                    ?>
                                  </small>
                                </p>
                              </div>
                              <div class="mb-3">
                                <?php
                                  $tags = explode(',', $image['tags']);
                                  foreach ($tags as $tag) {
                                    $tag = trim($tag);
                                    if (!empty($tag)) {
                                  ?>
                                    <a href="tagged_images.php?tag=<?php echo urlencode($tag); ?>"
                                      class="btn btn-sm btn-outline-light mb-1 rounded-3 fw-bold">
                                      <?php echo $tag; ?>
                                    </a>
                                  <?php }
                                  }
                                ?>
                              </div>
                              <a class="mb-4 btn btn-outline-light w-100 fw-bold text-center" data-bs-toggle="collapse" href="#collapseExample1" role="button" aria-expanded="false" aria-controls="collapseExample">
                                <i class="bi bi-caret-down-fill"></i> <small>more</small>
                              </a> 
                              <div class="collapse" id="collapseExample1">
                                <?php
                                    // Function to calculate the size of an image in MB
                                    function getImageSizeInMB($filename) {
                                      return round(filesize('images/' . $filename) / (1024 * 1024), 2);
                                    }

                                    // Get the total size of images from 'images' table
                                    $stmt = $db->prepare("SELECT * FROM images WHERE id = :filename");
                                    $stmt->bindParam(':filename', $filename);
                                    $stmt->execute();
                                    $images = $stmt->fetchAll(PDO::FETCH_ASSOC);

                                    // Get the total size of images from 'image_child' table
                                    $stmt = $db->prepare("SELECT * FROM image_child WHERE image_id = :filename");
                                    $stmt->bindParam(':filename', $filename);
                                    $stmt->execute();
                                    $image_childs = $stmt->fetchAll(PDO::FETCH_ASSOC);

                                    // Function to format the date
                                    function formatDate($date) {
                                        return date('Y/F/l jS') ;
                                    }
                                ?>
                                <?php foreach ($images as $index => $image) { ?>
                                  <div class="text-white mt-3 mb-3">
                                    <ul class="list-unstyled m-0">
                                      <li class="mb-2"><i class="bi bi-file-earmark"></i> Filename: <?php echo $image['filename']; ?></li>
                                      <li class="mb-2"><i class="bi bi-file-earmark-bar-graph"></i> Image data size: <?php echo getImageSizeInMB($image['filename']); ?> MB</li>
                                      <li class="mb-2"><i class="bi bi-arrows-angle-expand text-stroke"></i> Image dimensions: <?php list($width, $height) = getimagesize('images/' . $image['filename']); echo $width . 'x' . $height; ?></li>
                                      <li class="mb-2"><i class="bi bi-file-earmark-text"></i> MIME type: <?php echo mime_content_type('images/' . $image['filename']); ?></li>
                                      <li class="mb-2"><i class="bi bi-calendar"></i> Image date: <?php echo formatDate($image['date']); ?></li>
                                      <li>
                                        <a class="text-decoration-none text-primary" href="images/<?php echo $image['filename']; ?>">
                                          <i class="bi bi-arrows-fullscreen text-stroke"></i> View original image
                                        </a>
                                      </li>
                                    </ul>
                                  </div>
                                <?php } ?>
                                <?php foreach ($image_childs as $index => $image_child) { ?>
                                  <div class="text-white mt-3 mb-3">
                                    <ul class="list-unstyled m-0">
                                      <li class="mb-2"><i class="bi bi-file-earmark"></i> Filename: <?php echo $image_child['filename']; ?></li>
                                      <li class="mb-2"><i class="bi bi-file-earmark-bar-graph"></i> Image data size: <?php echo getImageSizeInMB($image_child['filename']); ?> MB</li>
                                      <li class="mb-2"><i class="bi bi-arrows-angle-expand text-stroke"></i> Image dimensions: <?php list($width, $height) = getimagesize('images/' . $image_child['filename']); echo $width . 'x' . $height; ?></li>
                                      <li class="mb-2"><i class="bi bi-file-earmark-text"></i> MIME type: <?php echo mime_content_type('images/' . $image_child['filename']); ?></li>
                                      <li class="mb-2"><i class="bi bi-calendar"></i> Image date: <?php echo formatDate($image['date']); ?></li>
                                      <li>
                                        <a class="text-decoration-none text-primary" href="images/<?php echo $image_child['filename']; ?>">
                                          <i class="bi bi-arrows-fullscreen text-stroke"></i> View original image
                                        </a>
                                      </li>
                                    </ul>
                                  </div>
                                <?php } ?>
                                <?php
                                  $images_total_size = 0;
                                  foreach ($images as $image) {
                                      $images_total_size += getImageSizeInMB($image['filename']);
                                  }

                                  $image_child_total_size = 0;
                                  foreach ($image_childs as $image_child) {
                                      $image_child_total_size += getImageSizeInMB($image_child['filename']);
                                  }
                                
                                  $total_size = $images_total_size + $image_child_total_size;
                                ?>
                                <div class="text-white mt-3 mb-3">
                                  <ul class="list-unstyled m-0">
                                    <li class="mb-2"><i class="bi bi-file-earmark-plus"></i> Total size of all images: <?php echo $total_size; ?> MB</li>
                                  </ul>
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
            <div class="position-absolute top-0 end-0 me-2 mt-2">
              <div class="btn-group">
                <button class="btn btn-sm btn-dark fw-bold opacity-75 rounded-3 text-white"><i class="bi bi-images"> </i><?php echo $total_all_images; ?></button>
              </div>
            </div>
            <div class="position-absolute bottom-0 end-0 me-2 mb-2">
              <div class="btn-group">
                <a class="btn btn-sm btn-dark opacity-75 rounded-3 rounded-end-0" data-bs-toggle="modal" data-bs-target="#originalImageModal"><i class="bi bi-eye-fill"></i></a>
                <button class="btn btn-sm btn-dark fw-bold opacity-75 rounded-0 text-white" id="loadOriginalBtn">Load Original Image</button>
                <a class="btn btn-sm btn-dark fw-bold opacity-75 rounded-3 rounded-start-0 text-white" data-bs-toggle="modal" data-bs-target="#downloadOption">
                  <i class="bi bi-cloud-arrow-down-fill"></i>
                </a>
              </div>
              <!-- Download Option Modal -->
              <div class="modal fade" id="downloadOption" tabindex="-1" aria-labelledby="exampleModalLabel" aria-hidden="true">
                <div class="modal-dialog">
                  <div class="modal-content">
                    <div class="modal-body">
                      <a class="btn btn-outline-dark fw-bold w-100 mb-2 text-center rounded-3" href="images/<?php echo $image['filename']; ?>" download>
                        <i class="bi bi-cloud-arrow-down-fill"></i> download first image (<?php echo getImageSizeInMB($image['filename']); ?> MB)
                      </a>
                      <a class="btn btn-outline-dark fw-bold w-100 mb-2 text-center rounded-3" href="download_images.php?artworkid=<?= $image_id; ?>">
                        <i class="bi bi-file-earmark-zip-fill"></i> download all images (<?php echo $total_size; ?> MB)
                      </a>
                      <button type="button" class="btn btn-secondary fw-bold w-100 text-center rounded-3" data-bs-dismiss="modal">cancel</button>
                    </div>
                  </div>
                </div>
              </div>
            </div>
            <?php if ($next_image): ?>
              <button class="btn btn-sm opacity-75 rounded fw-bold position-absolute start-0 top-50 translate-middle-y ms-1"  onclick="location.href='image.php?artworkid=<?= $next_image['id'] ?>'">
                <i class="bi bi-arrow-left-circle-fill display-f"></i>
              </button>
            <?php endif; ?> 
            <?php if ($prev_image): ?>
              <button class="btn btn-sm opacity-75 rounded fw-bold position-absolute end-0 top-50 translate-middle-y me-1"  onclick="location.href='image.php?artworkid=<?= $prev_image['id'] ?>'">
                <i class="bi bi-arrow-right-circle-fill display-f"></i>
              </button>
            <?php endif; ?> 
            <div class="position-absolute bottom-0 start-0 ms-2 mb-2">
              <div class="btn-group">
                <?php
                  $image_id = $image['id'];
                  $stmt = $db->query("SELECT COUNT(*) FROM favorites WHERE image_id = $image_id");
                  $fav_count = $stmt->fetchColumn();
                  if ($fav_count >= 1000000000) {
                    $fav_count = round($fav_count / 1000000000, 1) . 'b';
                  } elseif ($fav_count >= 1000000) {
                    $fav_count = round($fav_count / 1000000, 1) . 'm';
                  } elseif ($fav_count >= 1000) {
                    $fav_count = round($fav_count / 1000, 1) . 'k';
                  }
                  $stmt = $db->prepare("SELECT COUNT(*) FROM favorites WHERE email = :email AND image_id = :image_id");
                  $stmt->bindParam(':email', $email);
                  $stmt->bindParam(':image_id', $image_id);
                  $stmt->execute();
                  $is_favorited = $stmt->fetchColumn();
                  if ($is_favorited) {
                ?>
                  <form action="image.php?artworkid=<?php echo $image['id']; ?>" method="POST">
                    <input type="hidden" name="image_id" value="<?php echo $image['id']; ?>">
                    <button type="submit" class="btn btn-sm btn-dark opacity-75 rounded-3 rounded-end-0" name="unfavorite"><i class="bi bi-heart-fill"></i></button>
                  </form>
                <?php } else { ?>
                  <form action="image.php?artworkid=<?php echo $image['id']; ?>" method="POST">
                    <input type="hidden" name="image_id" value="<?php echo $image['id']; ?>">
                    <button type="submit" class="btn btn-sm btn-dark opacity-75 rounded-3 rounded-end-0" name="favorite"><i class="bi bi-heart"></i></button>
                  </form>
                <?php } ?>
                <button class="btn btn-sm btn-dark opacity-75 rounded-3 rounded-start-0" onclick="sharePage()"><i class="bi bi-share-fill"></i></button>
              </div>
            </div>
          </div>
        </div>
        <div class="cool-6">
          <div class="caard border-md-lg">
            <div class="container-fluid mb-4 d-none d-md-flex d-lg-flex">
              <?php
                $stmt = $db->prepare("SELECT u.id, u.email, u.password, u.region, u.artist, u.pic, u.desc, u.bgpic, i.id AS image_id, i.filename, i.tags FROM users u INNER JOIN images i ON u.id = i.id WHERE u.id = :id");
                $stmt->bindParam(':id', $id);
                $stmt->execute();
                $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
              ?>
              <div class="d-flex">
                <a class="text-decoration-none text-dark fw-bold rounded-pill" href="#" data-bs-toggle="modal" data-bs-target="#userModal">
                 <?php if (!empty($user['pic'])): ?>
                   <img class="object-fit-cover border border-1 rounded-circle" src="<?php echo $user['pic']; ?>" style="width: 32px; height: 32px;">
                  <?php else: ?>
                    <img class="object-fit-cover border border-1 rounded-circle" src="icon/profile.svg" style="width: 32px; height: 32px;">
                  <?php endif; ?>
                  <?php echo (mb_strlen($user['artist']) > 20) ? mb_substr($user['artist'], 0, 20) . '...' : $user['artist']; ?> <small class="badge rounded-pill bg-primary"><i class="bi bi-globe-asia-australia"></i> <?php echo $user['region']; ?></small>
                </a>
              </div>
              <div class="ms-auto">
                <form method="post">
                  <?php if ($is_following): ?>
                    <button class="btn btn-sm btn-secondary rounded-pill fw-bold opacity-50" type="submit" name="unfollow"><i class="bi bi-person-dash-fill"></i> unfollow</button>
                  <?php else: ?>
                    <button class="btn btn-sm btn-secondary rounded-pill fw-bold opacity-50" type="submit" name="follow"><i class="bi bi-person-fill-add"></i> follow</button>
                  <?php endif; ?>
                </form>
              </div>
            </div>
            <div class="me-2 ms-2 rounded fw-bold">
              <div class="d-flex d-md-none d-lg-none gap-2">
                <?php if ($next_image): ?>
                  <a class="image-containerA shadow rounded" href="image.php?artworkid=<?= $next_image['id'] ?>">
                    <img class="object-fit-cover rounded" style="width: 100%; height: 120px;" src="thumbnails/<?php echo $next_image['filename']; ?>" alt="<?php echo $next_image['title']; ?>">
                  </a>
                <?php endif; ?>
                <a class="image-containerA shadow rounded" href="image.php?artworkid=<?= $image['id'] ?>">
                  <img class="object-fit-cover opacity-50 rounded" style="width: 100%; height: 120px;" src="thumbnails/<?= $image['filename'] ?>" alt="<?php echo $image['title']; ?>">
                </a>
                <?php if ($prev_image): ?>
                  <a class="image-containerA shadow rounded" href="image.php?artworkid=<?= $prev_image['id'] ?>">
                    <img class="object-fit-cover rounded" style="width: 100%; height: 120px;" src="thumbnails/<?php echo $prev_image['filename']; ?>" alt="<?php echo $prev_image['title']; ?>">
                  </a>
                <?php endif; ?>
              </div>
              <h5 class="text-dark fw-bold text-center mt-3"><?php echo $image['title']; ?></h5>
              <div style="word-break: break-word;" data-lazyload>
                <p class="text-secondary" style="word-break: break-word;">
                  <small>
                    <?php
                      $messageText = $image['imgdesc'];
                      $messageTextWithoutTags = strip_tags($messageText);
                      $pattern = '/\bhttps?:\/\/\S+/i';

                      $formattedText = preg_replace_callback($pattern, function ($matches) {
                        $url = htmlspecialchars($matches[0]);
                        return '<a target="_blank" href="' . $url . '">' . $url . '</a>';
                      }, $messageTextWithoutTags);

                      $formattedTextWithLineBreaks = nl2br($formattedText);
                      echo $formattedTextWithLineBreaks;
                    ?>
                  </small>
                </p>
              </div>
              <p class="text-secondary" style="word-wrap: break-word;">
                <a class="text-primary" href="<?php echo $image['link']; ?>">
                  <small>
                    <?php echo (strlen($image['link']) > 40) ? substr($image['link'], 0, 40) . '...' : $image['link']; ?>
                  </small>
                </a>
              </p>
              <div class="container-fluid bg-body-secondary p-2 mt-2 mb-2 rounded-4 text-center align-items-center d-flex justify-content-center">
                <button class="btn border-0 disabled fw-semibold">
                  <small>
                    <?php echo date('Y/m/d', strtotime($image['date'])); ?>
                  </small
                </button>
                <button class="btn border-0 disabled fw-semibold"><i class="bi bi-heart-fill text-sm"></i> <small><?php echo $fav_count; ?> </small></button>
                <button class="btn border-0 disabled fw-semibold"><i class="bi bi-eye-fill"></i> <small><?php echo $viewCount; ?> </small></button>
              </div>
              <div class="btn-group w-100" role="group" aria-label="Basic example">
                <button class="btn btn-primary fw-bold rounded-start-4" data-bs-toggle="modal" data-bs-target="#shareLink">
                  <i class="bi bi-share-fill"></i> <small>share</small>
                </button>
                <a class="btn btn-primary fw-bold" data-bs-toggle="modal" data-bs-target="#downloadOption">
                  <i class="bi bi-cloud-arrow-down-fill"></i> <small>download</small>
                </a>
                <button class="btn btn-primary dropdown-toggle fw-bold rounded-end-4" type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#dataModal">
                  <i class="bi bi-info-circle-fill"></i> <small>info</small>
                </button>
                <!-- Data Modal -->
                <div class="modal fade" id="dataModal" tabindex="-1" aria-labelledby="exampleModalLabel" aria-hidden="true">
                  <div class="modal-dialog">
                    <div class="modal-content">
                      <div class="modal-header">
                        <h1 class="modal-title fs-5 fw-bold" id="exampleModalLabel">All Data from <?php echo $image['title']; ?></h1>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                      </div>
                      <div class="modal-body">
                        <div class="container-fluid">
                          <div class="text-dark text-center mt-3 mb-3">
                            <ul class="list-unstyled m-0">
                                <li class="mb-2"><i class="bi bi-file-earmark-plus"></i> Total size of all images: <?php echo $total_size; ?> MB</li>
                            </ul>
                          </div>
                          <?php foreach ($images as $index => $image) { ?>
                            <div class="mt-3 mb-3 img-thumbnail">
                              <ul class="list-unstyled m-0">
                                <li class="mb-2"><i class="bi bi-file-earmark"></i> Filename: <?php echo $image['filename']; ?></li>
                                <li class="mb-2"><i class="bi bi-file-earmark-bar-graph"></i> Image data size: <?php echo getImageSizeInMB($image['filename']); ?> MB</li>
                                <li class="mb-2"><i class="bi bi-arrows-angle-expand text-stroke"></i> Image dimensions: <?php list($width, $height) = getimagesize('images/' . $image['filename']); echo $width . 'x' . $height; ?></li>
                                <li class="mb-2"><i class="bi bi-file-earmark-text"></i> MIME type: <?php echo mime_content_type('images/' . $image['filename']); ?></li>
                                <li class="mb-2"><i class="bi bi-calendar"></i> Image date: <?php echo formatDate($image['date']); ?></li>
                                <li>
                                  <a class="text-decoration-none text-primary" href="images/<?php echo $image['filename']; ?>">
                                    <i class="bi bi-arrows-fullscreen text-stroke"></i> View original image
                                  </a>
                                </li>
                              </ul>
                            </div>
                          <?php } ?>
                          <?php foreach ($image_childs as $index => $image_child) { ?>
                            <div class="mt-3 mb-3 img-thumbnail">
                              <ul class="list-unstyled m-0">
                                <li class="mb-2"><i class="bi bi-file-earmark"></i> Filename: <?php echo $image_child['filename']; ?></li>
                                <li class="mb-2"><i class="bi bi-file-earmark-bar-graph"></i> Image data size: <?php echo getImageSizeInMB($image_child['filename']); ?> MB</li>
                                <li class="mb-2"><i class="bi bi-arrows-angle-expand text-stroke"></i> Image dimensions: <?php list($width, $height) = getimagesize('images/' . $image_child['filename']); echo $width . 'x' . $height; ?></li>
                                <li class="mb-2"><i class="bi bi-file-earmark-text"></i> MIME type: <?php echo mime_content_type('images/' . $image_child['filename']); ?></li>
                                <li class="mb-2"><i class="bi bi-calendar"></i> Image date: <?php echo formatDate($image['date']); ?></li>
                                <li>
                                  <a class="text-decoration-none text-primary" href="images/<?php echo $image_child['filename']; ?>">
                                    <i class="bi bi-arrows-fullscreen text-stroke"></i> View original image
                                  </a>
                                </li>
                              </ul>
                            </div>
                          <?php } ?>
                          <?php
                            $images_total_size = 0;
                            foreach ($images as $image) {
                                $images_total_size += getImageSizeInMB($image['filename']);
                            }

                            $image_child_total_size = 0;
                            foreach ($image_childs as $image_child) {
                                $image_child_total_size += getImageSizeInMB($image_child['filename']);
                            }
                                
                            $total_size = $images_total_size + $image_child_total_size;
                          ?>
                        </div>
                      </div>
                    <div class="modal-footer">
                      <button type="button" class="btn btn-secondary w-100 fw-bold" data-bs-dismiss="modal">close</button>
                    </div>
                  </div>
                </div>
              </div>
              </div>
              <div class="d-none d-none d-md-flex d-lg-flex mt-2 gap-2">
                <?php if ($next_image): ?>
                  <a class="image-containerA shadow rounded" href="image.php?artworkid=<?= $next_image['id'] ?>">
                    <img class="object-fit-cover rounded" style="width: 100%; height: 160px;" src="thumbnails/<?php echo $next_image['filename']; ?>" alt="<?php echo $next_image['title']; ?>">
                  </a>
                <?php endif; ?>
                <a class="image-containerA shadow rounded" href="image.php?artworkid=<?= $image['id'] ?>">
                  <img class="object-fit-cover opacity-50 rounded" style="width: 100%; height: 160px;" src="thumbnails/<?= $image['filename'] ?>" alt="<?php echo $image['title']; ?>">
                </a>
                <?php if ($prev_image): ?>
                  <a class="image-containerA shadow rounded" href="image.php?artworkid=<?= $prev_image['id'] ?>">
                    <img class="object-fit-cover rounded" style="width: 100%; height: 160px;" src="thumbnails/<?php echo $prev_image['filename']; ?>" alt="<?php echo $prev_image['title']; ?>">
                  </a>
                <?php endif; ?>
              </div>
              <a class="btn btn-primary rounded-4 w-100 mt-2 fw-bold" style="word-wrap: break-word;" href="artist.php?id=<?= $user['id'] ?>"><small><i class="bi bi-images"></i> view all <?php echo $user['artist']; ?>'s images</small></a>
              <?php include 'imguser.php'; ?>
              <a class="btn btn-primary rounded-4 w-100 fw-bold text-center" data-bs-toggle="collapse" href="#collapseExample" role="button" aria-expanded="false" aria-controls="collapseExample">
                <i class="bi bi-caret-down-fill"></i> <small>more</small>
              </a> 
              <div class="collapse" id="collapseExample">
                <form class="mt-2" action="add_to_album.php" method="post">
                  <input class="form-control" type="hidden" name="image_id" value="<?= $image['id']; ?>">
                  <select class="form-select fw-bold text-secondary rounded-4 mb-2" name="album_id">
                    <option class="form-control" value=""><small>add to album:</small></option>
                    <?php
                      // Connect to the SQLite database
                      $db = new SQLite3('database.sqlite');

                      // Get the email of the current user
                      $email = $_SESSION['email'];

                      // Retrieve the list of albums created by the current user
                      $stmt = $db->prepare('SELECT album_name, id FROM album WHERE email = :email');
                      $stmt->bindValue(':email', $email, SQLITE3_TEXT);
                      $results = $stmt->execute();

                      // Loop through each album and create an option in the dropdown list
                      while ($row = $results->fetchArray(SQLITE3_ASSOC)) {
                        $album_name = $row['album_name'];
                        $id = $row['id'];
                        echo '<option value="' . $id. '">' . htmlspecialchars($album_name). '</option>';
                      }

                      $db->close();
                    ?>
                  </select>
                  <button class="form-control bg-primary text-white fw-bold rounded-4" type="submit"><small>add to album</small></button>
                </form>
                <iframe class="mt-2 rounded" style="width: 100%; height: 300px;" src="<?php echo $url; ?>"></iframe>
                <a class="btn btn-primary w-100 rounded-4 fw-bold mt-2" href="comment.php?imageid=<?php echo $image['id']; ?>"><i class="bi bi-chat-left-text-fill"></i> <small>view all comments</small></a>
              </div>
              <p class="text-secondary mt-3"><i class="bi bi-tags-fill"></i> tags</p>
              <div class="tag-buttons container">
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
                    <?php
                    }
                  }
                ?>
              </div>
            </div>
          </div> 
        </div>
      </div>
    </div>
    <!-- Share Modal -->
    <div class="modal fade" id="shareLink" tabindex="-1" aria-labelledby="exampleModalLabel" aria-hidden="true">
      <div class="modal-dialog">
        <div class="modal-content">
          <div class="modal-header">
            <h1 class="modal-title fs-5" id="exampleModalLabel">share to:</h1>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
          </div>
          <div class="modal-body">
            <div class="btn-group w-100 mb-2" role="group" aria-label="Share Buttons">
              <!-- Twitter -->
              <a class="btn btn-outline-dark" href="https://twitter.com/intent/tweet?url=<?php echo (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https://' : 'http://') . $_SERVER['HTTP_HOST'] . '/image.php?artworkid=' . $image['id']; ?>">
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
            <div class="input-group mb-2">
              <input type="text" id="urlInput" value="<?php echo 'http' . (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 's' : '') . '://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI']; ?>" class="form-control border-2 fw-bold" readonly>
              <button class="btn btn-secondary opacity-50 fw-bold" onclick="copyToClipboard()">Copy URL</button>
            </div>
            <button class="btn btn-secondary opacity-50 fw-bold w-100" onclick="sharePage()"><i class="bi bi-share-fill"></i> <small>share</small></button>
          </div>
        </div>
      </div>
    </div>
    <style>
      .media-scrollerF {
        display: grid;
        gap: 3px; /* Updated gap value */
        grid-auto-flow: column;
        overflow-x: auto;
        overscroll-behavior-inline: contain;
      }

      .snaps-inlineF {
        scroll-snap-type: inline mandatory;
        scroll-padding-inline: var(--_spacer, 1rem);
      }

      .snaps-inlineF > * {
        scroll-snap-align: start;
      }
  
      .scroll-container {
        scrollbar-width: none;  /* Firefox */
        -ms-overflow-style: none;  /* Internet Explorer 10+ */
        margin-left: auto;
        margin-right: auto;
      }
      
      .w-98 {
        width: 98%;
      }

      .scroll-container::-webkit-scrollbar {
        width: 0;  /* Safari and Chrome */
        height: 0;
      }
      
      .scrollable-div {
        overflow: auto;
        scrollbar-width: thin;  /* For Firefox */
        -ms-overflow-style: none;  /* For Internet Explorer and Edge */
        scrollbar-color: transparent transparent;  /* For Chrome, Safari, and Opera */
      }

      .scrollable-div::-webkit-scrollbar {
        width: 0;
        background-color: transparent;
      }
      
      .scrollable-div::-webkit-scrollbar-thumb {
        background-color: transparent;
      }

      .image-containerA {
        width: 33.33%;
        flex-grow: 1;
      }
  
      .text-sm {
        font-size: 13px;
      }
      
      .display-f {
        font-size: 33px;
      } 

      .roow {
        display: flex;
        flex-wrap: wrap;
      }

      .cool-6 {
        width: 50%;
        padding: 0 15px;
        box-sizing: border-box;
      }

      .caard {
        margin-bottom: 15px;
      }
      
      .rounded-r {
        border-radius: 15px;
      }

      @media (max-width: 767px) {
        .cool-6 {
          width: 100%;
          padding: 0;
        }
        
        .display-small-none {
          display: none;
        }
        
        .rounded-r {
          border-radius: 0;
        }

        .img-UF {
          width: 100%;
          height: 150px;
        }
      }
      
      @media (min-width: 768px) {
        .img-UF {
          width: 100%;
          height: 200px;
        }
      }
    </style> 
    <p class="text-secondary fw-bold ms-2 mt-2">Latest Images</p>
    <?php
      include 'latest.php';
    ?>
    <p class="text-secondary fw-bold ms-2 mt-4">Popular Images</p>
    <?php
      include 'most_popular.php';
    ?>
    <div class="mt-5"></div>
    <script>
      function copyToClipboard() {
        var urlInput = document.getElementById('urlInput');
        urlInput.select();
        urlInput.setSelectionRange(0, 99999); // For mobile devices

        document.execCommand('copy');
      }
    </script>
    <script>
      var originalImageLink = document.getElementById("originalImageLink");
      var originalImage = document.getElementById("originalImage");
      var originalImageSrc = originalImageLink.getAttribute("data-original-src");

      originalImageLink.addEventListener("click", function(event) {
        event.preventDefault();
        originalImage.setAttribute("src", originalImageSrc);
      });

      var modal = document.getElementById("originalImageModal");
      modal.addEventListener("hidden.bs.modal", function() {
        originalImage.setAttribute("src", "");
      });

      modal.addEventListener("shown.bs.modal", function() {
        originalImage.setAttribute("src", originalImageSrc);
      });

      // Update the Load Original button functionality
      var loadOriginalBtn = document.getElementById("loadOriginalBtn");
      var thumbnailImage = document.querySelector("#originalImageLink img");

      loadOriginalBtn.addEventListener("click", function(event) {
        event.preventDefault();
        var originalSrc = originalImageLink.getAttribute("data-original-src");
        thumbnailImage.setAttribute("src", originalSrc);
      });
    </script>
    <script>
      function sharePage() {
        if (navigator.share) {
          navigator.share({
            title: document.title,
            url: window.location.href
          }).then(() => {
            console.log('Page shared successfully.');
          }).catch((error) => {
            console.error('Error sharing page:', error);
          });
        } else {
          console.log('Web Share API not supported.');
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
    <?php include('bootstrapjs.php'); ?>
  </body>
</html>