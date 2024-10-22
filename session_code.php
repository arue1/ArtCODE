<?php
session_start();

// Connect to the SQLite database
$db = new SQLite3('database.sqlite');

// Create the users table if it doesn't exist
$stmt = $db->prepare("CREATE TABLE IF NOT EXISTS users (id INTEGER PRIMARY KEY AUTOINCREMENT, email TEXT, password TEXT, artist TEXT, pic TEXT, desc TEXT, bgpic TEXT, token TEXT, twitter TEXT, pixiv TEXT, other TEXT, region TEXT)");
$stmt->execute();
 
if (isset($_POST['login'])) {
  $email = filter_var($_POST['email'], FILTER_SANITIZE_FULL_SPECIAL_CHARS, FILTER_FLAG_STRIP_LOW);
  $password = filter_var($_POST['password'], FILTER_SANITIZE_FULL_SPECIAL_CHARS, FILTER_FLAG_STRIP_LOW);

  // Check if the email and password fields are not empty
  if (empty($email) || empty($password)) {
    echo "Please enter both email and password.";
  } else {
    // Check if the user exists in the database
    $stmt = $db->prepare("SELECT * FROM users WHERE email = :email AND password = :password");
    $stmt->bindValue(':email', $email, SQLITE3_TEXT);
    $stmt->bindValue(':password', $password, SQLITE3_TEXT);
    $result = $stmt->execute();
    $user = $result->fetchArray();
    if ($user) {
      // Generate a unique session ID and token and store them in cookies
      $session_id = uniqid();
      $token = bin2hex(random_bytes(32));
      setcookie('session_id', $session_id, time() + (7 * 24 * 60 * 60), '/');
      setcookie('token', $token, time() + (7 * 24 * 60 * 60), '/');
    
      // Store the session ID and email in the session for future use
      $_SESSION['session_id'] = $session_id;
      $_SESSION['email'] = $email;
    
      // Update the user's token in the database
      $stmt = $db->prepare("UPDATE users SET token = :token WHERE email = :email");
      $stmt->bindValue(':token', $token, SQLITE3_TEXT);
      $stmt->bindValue(':email', $email, SQLITE3_TEXT);
      $stmt->execute();
    
      // Redirect the user to the homepage
      header("Location: index.php");
      exit;
    } else {
      echo '
            <meta charset="UTF-8"> 
            <meta name="viewport" content="width=device-width, initial-scale=1">
            <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.3/font/bootstrap-icons.css">
            <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-GLhlTQ8iRABdZLl6O3oVMWSktQOp6b7In1Zl3/Jr59b6EGGoI1aFkw7cmDA6j6gD" crossorigin="anonymous">
            <h5 class="position-absolute top-50 start-50 translate-middle fs-2 fw-bold text-center text-secondary">Incorrect email or password!</h5>
           ';
    }
  }
} elseif (isset($_POST['register'])) {
  $email = substr(htmlspecialchars(trim($_POST['email'])), 0, 40);
  $password = substr(htmlspecialchars(trim($_POST['password'])), 0, 40);
  $artist = substr(htmlspecialchars(trim($_POST['artist'])), 0, 40);

  // Check if the email and password fields are empty
  if (empty($email) || empty($password)) {
    echo '
          <meta charset="UTF-8"> 
          <meta name="viewport" content="width=device-width, initial-scale=1">
          <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.3/font/bootstrap-icons.css">
          <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-GLhlTQ8iRABdZLl6O3oVMWSktQOp6b7In1Zl3/Jr59b6EGGoI1aFkw7cmDA6j6gD" crossorigin="anonymous">
          <h5 class="position-absolute top-50 start-50 translate-middle fs-2 fw-bold text-center text-secondary">Email or password required!</h5>
         ';
    exit;
  }

  // Sanitize the user input
  $email = filter_var($email, FILTER_SANITIZE_FULL_SPECIAL_CHARS, FILTER_FLAG_STRIP_LOW);
  $password = filter_var($password, FILTER_SANITIZE_FULL_SPECIAL_CHARS, FILTER_FLAG_STRIP_LOW);
  $artist = filter_var($artist, FILTER_SANITIZE_FULL_SPECIAL_CHARS, FILTER_FLAG_STRIP_LOW);

  // Check if the email is already taken
  $stmt = $db->prepare("SELECT * FROM users WHERE email = :email");
  $stmt->bindValue(':email', $email, SQLITE3_TEXT);
  $result = $stmt->execute();
  $user = $result->fetchArray();
  if ($user) {
    echo '
          <meta charset="UTF-8"> 
          <meta name="viewport" content="width=device-width, initial-scale=1">
          <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.3/font/bootstrap-icons.css">
          <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-GLhlTQ8iRABdZLl6O3oVMWSktQOp6b7In1Zl3/Jr59b6EGGoI1aFkw7cmDA6j6gD" crossorigin="anonymous">
          <h5 class="position-absolute top-50 start-50 translate-middle fs-2 fw-bold text-center text-secondary">Email already taken!</h5>
         ';
    exit;
  } else {
    // Add the new user to the database
    $stmt = $db->prepare("INSERT INTO users (email, password, artist) VALUES (:email, :password, :artist)");
    $stmt->bindValue(':email', $email, SQLITE3_TEXT);
    $stmt->bindValue(':password', $password, SQLITE3_TEXT);
    $stmt->bindValue(':artist', $artist, SQLITE3_TEXT);
    $stmt->execute();

    // Generate a unique token and store it in the database for the user
    $token = bin2hex(random_bytes(16));
    $stmt = $db->prepare("UPDATE users SET token = :token WHERE email = :email");
    $stmt->bindValue(':token', $token, SQLITE3_TEXT);
    $stmt->bindValue(':email', $email, SQLITE3_TEXT);
    $stmt->execute();

    // Store the token in a cookie
    setcookie('token', $token, time() + (7 * 24 * 60 * 60), '/');

    // Generate a unique session ID and store it in a cookie
    $session_id = uniqid();
    setcookie('session_id', $session_id, time() + (7 * 24 * 60 * 60), '/');

    // Store the email in a cookie
    setcookie('email', $email, time() + (7 * 24 * 60 * 60), '/');

    // Store the email in the session for future use
    $_SESSION['email'] = $email;

    // Redirect the user to the homepage
    header("Location: regrg.php");
    exit;
  }
} else {
  // Check if the session ID cookie exists and restore the session if it does
  if (isset($_COOKIE['session_id'])) {
    $session_id = substr(htmlspecialchars($_COOKIE['session_id']), 0, 13);
    $session_id = filter_var($session_id, FILTER_SANITIZE_FULL_SPECIAL_CHARS, FILTER_FLAG_STRIP_LOW);
    session_id($session_id);

    // Check if the user has a valid session token
    if (isset($_SESSION['email'])) {
      $email = $_SESSION['email'];
      $stmt = $db->prepare("SELECT * FROM users WHERE email = :email");
      $stmt->bindValue(':email', $email, SQLITE3_TEXT);
      $result = $stmt->execute();
      $user = $result->fetchArray();
      if ($user && isset($_COOKIE['token']) && $_COOKIE['token'] === $user['token']) {
        // If the user has a valid token, update the session and token cookies
        $session_id = session_id();
        $token = $_COOKIE['token'];
        setcookie('session_id', $session_id, time() + (7 * 24 * 60 * 60), '/');
        setcookie('token', $token, time() + (7 * 24 * 60 * 60), '/');
      } else {
        // If the user does not have a valid token, clear the session and redirect to login
        session_unset();
        session_destroy();
        setcookie('session_id', '', time() - 3600, '/');
        setcookie('token', '', time() - 3600, '/');
        header("Location: session.php");
        exit;
      }
    }
  }
}
?>