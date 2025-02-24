<?php
// Debugging aanzetten
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Sessies starten
session_start();
require 'database.php';

// OAuth2 configuratie
define('OAUTH2_CLIENT_ID', '1341716621848346664');
define('OAUTH2_CLIENT_SECRET', '');
define('CALLBACK_APP_URL', 'http://127.0.0.1:8001/discord.php');

$authorizeURL = 'https://discord.com/api/oauth2/authorize';
$tokenURL = 'https://discord.com/api/oauth2/token';
$apiURLBase = 'https://discord.com/api/users/@me';
$revokeURL = 'https://discord.com/api/oauth2/token/revoke';

// Debugging start
error_log("==== Start script ====");

if (!session('access_token') && isset($_COOKIE['access_token'])) {
  $_SESSION['access_token'] = $_COOKIE['access_token'];
  $_SESSION['refresh_token'] = $_COOKIE['refresh_token'];
}

// Check of de access_token nog geldig is
$user = apiRequest($apiURLBase);
if (!$user && isset($_SESSION['refresh_token'])) {
  // Token verlopen? Vraag een nieuwe aan met refresh_token
  $newToken = apiRequest($tokenURL, array(
      "grant_type" => "refresh_token",
      'client_id' => OAUTH2_CLIENT_ID,
      'client_secret' => OAUTH2_CLIENT_SECRET,
      'refresh_token' => $_SESSION['refresh_token']
  ));

  if ($newToken) {
      $_SESSION['access_token'] = $newToken->access_token;
      $_SESSION['refresh_token'] = $newToken->refresh_token;

      // Update cookies
      setcookie("access_token", $newToken->access_token, time() + 3600, "/", "", false, true);
      setcookie("refresh_token", $newToken->refresh_token, time() + (30 * 24 * 60 * 60), "/", "", false, true);
  } else {
      // Als refresh_token niet werkt, verwijder de cookies en sessie
      setcookie("access_token", "", time() - 3600, "/");
      setcookie("refresh_token", "", time() - 3600, "/");
      session_destroy();
  }
}

// Inloggen met Discord
if (get('action') == 'login') {
    error_log("🔵 User clicked login.");
    $params = [
        'client_id' => OAUTH2_CLIENT_ID,
        'redirect_uri' => CALLBACK_APP_URL,
        'response_type' => 'code',
        'scope' => 'identify guilds guilds.join',
        'prompt' => 'none'
    ];
    header('Location: ' . $authorizeURL . '?' . http_build_query($params));
    exit();
}

// Gebruiker ophalen als access token bestaat
if (session('access_token') || isset($_COOKIE['access_token'])) {
      $user = apiRequest($apiURLBase);
      $access_token = session('access_token') || $_COOKIE['access_token'];

    if ($user) {
        // Avatar URL genereren
        $avatar_url = "https://cdn.discordapp.com/avatars/{$user->id}/{$user->avatar}.png?size=1024";
    } else {
        error_log("❌ Failed to fetch user data.");
    }
} else {
    error_log("❌ No valid session found. User not logged in.");
}

// API-aanvraag functie
function apiRequest($url, $post = FALSE, $headers = []) {
    error_log("🌐 API Request to: $url");

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
    curl_setopt($ch, CURLOPT_IPRESOLVE, CURL_IPRESOLVE_V4);

    if ($post) {
        curl_setopt($ch, CURLOPT_POST, TRUE);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($post));
    }

    $headers[] = 'Accept: application/json';
    if (session('access_token')) {
        $headers[] = 'Authorization: Bearer ' . session('access_token');
    }

    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

    $response = curl_exec($ch);
    
    if (curl_errno($ch)) {
        error_log("❌ CURL Error: " . curl_error($ch));
    }

    return json_decode($response);
}

// Helper functies
function get($key, $default = NULL) {
    return array_key_exists($key, $_GET) ? $_GET[$key] : $default;
}

function session($key, $default = NULL) {
    return array_key_exists($key, $_SESSION) ? $_SESSION[$key] : $default;
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Moon Creator - Dashboard</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <style>
        body {
            background-color: #2C2F33;
            color: white;
            font-family: Arial, sans-serif;
        }
        .profile-card {
            background: #23272A;
            padding: 20px;
            border-radius: 10px;
            text-align: center;
            max-width: 400px;
            margin: auto;
            box-shadow: 0px 0px 10px rgba(0, 0, 0, 0.5);
        }
        .profile-card img {
            border-radius: 50%;
            width: 120px;
            height: 120px;
            border: 4px solid white;
        }
        .logout-btn {
            margin-top: 15px;
        }
    </style>
</head>
<body>
    <div class="profile-card mt-5">
        <?php if (session('access_token')): ?>
            <img src="<?= $avatar_url ?>" alt="Avatar">
            <h2>Maak een nieuwe bot op account van <?= htmlspecialchars($user->global_name) ?></h2>
            <form method="post" action="/db/createbot.php">
            <p><strong>User:</strong> <?= $user->id ?> <input type="hidden" value="<?= $user->id ?>" id="id" name="id" required></p>
            <p><strong>Bot token:</strong> <input type="text" value="" id="token" name="token" required></p>
            <button type="submit" class="btn btn-info logout-btn">Maak bot</a>
        </form>
        <?php else: ?>
            <h3>Not logged in</h3>
            <p><a href="?action=login" class="btn btn-primary">Log In met Discord</a></p>
        <?php endif; ?>
    </div>
</body>
</html>
