<?php
session_start();

if (!isset($_SESSION['email'])) {
  header('Location: session.php');
  exit();
}

$db = new SQLite3('database.sqlite');
$stmt = $db->prepare("CREATE TABLE IF NOT EXISTS comments (id INTEGER PRIMARY KEY, filename TEXT, email TEXT, comment TEXT, created_at TEXT)");
$stmt->execute();

$comment_id = $_GET['commentid'];

$stmt = $db->prepare("SELECT * FROM comments WHERE id=:comment_id");
$stmt->bindValue(':comment_id', $comment_id, SQLITE3_INTEGER);
$comment = $stmt->execute()->fetchArray(SQLITE3_ASSOC);

if (!$comment) {
  header('Location: index.php');
  exit();
}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['comment'])) {
  $reply = filter_var($_POST['comment'], FILTER_SANITIZE_FULL_SPECIAL_CHARS, FILTER_FLAG_STRIP_LOW);

  if (!empty(trim($reply))) {
    $stmt = $db->prepare("UPDATE comments SET comment=:reply WHERE id=:comment_id");
    $stmt->bindValue(':reply', $reply, SQLITE3_TEXT);
    $stmt->bindValue(':comment_id', $comment_id, SQLITE3_INTEGER);
    $stmt->execute();
  }

  header("Location: comment_preview.php?imageid={$comment['filename']}");
  exit();
}
?>

<!DOCTYPE html>
<html>
  <head>
    <title>Edit Comment</title>
    <meta charset="UTF-8"> 
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="icon" type="image/png" href="icon/favicon.png">
    <?php include('bootstrapcss.php'); ?>
  </head>
  <body>
    <div class="modal-dialog" role="document">
      <div class="modal-content border-3 border-bottom">
        <div class="modal-body p-4">
          <h5 class="mb-0 fw-bold text-center">Edit Comment</h5>
        </div>
      </div>
    </div> 
    <div class="container-fluid mt-2">
      <form method="post">
        <div class="mb-3">
          <textarea class="form-control" id="comment" name="comment" rows="10" oninput="stripHtmlTags(this)" required><?php echo strip_tags($comment['comment']); ?></textarea>
        </div>
        <button type="submit" class="btn btn-primary w-100 fw-bold">Save</button>
      </form>
    </div>
    <div class="ms-2 d-none-sm position-absolute top-0 mt-2 start-0">
      <button class="btn btn-sm btn-secondary rounded-pill fw-bold opacity-50" onclick="goBack()">
        <i class="bi bi-arrow-left-circle-fill"></i> Back
      </button>
    </div>
    <script>
      function goBack() {
        window.location.href = "comment_preview.php?imageid=<?php echo $comment['filename']; ?>";
      }
    </script> 
  </body>
</html>
