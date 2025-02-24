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

// Guild join config
$guild_id = '1221385825464487936'; //Moon Productions

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

// Callback: code ontvangen en access token ophalen
if (get('code')) {
    error_log("🔵 Received authorization code: " . get('code'));

    $token = apiRequest($tokenURL, [
        "grant_type" => "authorization_code",
        'client_id' => OAUTH2_CLIENT_ID,
        'client_secret' => OAUTH2_CLIENT_SECRET,
        'redirect_uri' => CALLBACK_APP_URL,
        'code' => get('code')
    ]);

    if ($token) {
      $_SESSION['access_token'] = $token->access_token;
      $_SESSION['refresh_token'] = $token->refresh_token;
  
      // Sla tokens op in cookies voor 30 dagen
      setcookie("access_token", $token->access_token, time() + 3600, "/", "", false, true);
      setcookie("refresh_token", $token->refresh_token, time() + (30 * 24 * 60 * 60), "/", "", false, true);
      
      

      header('Location: ' . $_SERVER['PHP_SELF']);
      exit;
  } else {
      die("❌ Kon geen token ophalen van Discord.");
  }  
}

// Gebruiker ophalen als access token bestaat
if (session('access_token') || isset($_COOKIE['access_token'])) {
      $user = apiRequest($apiURLBase);
      $access_token = session('access_token') || $_COOKIE['access_token'];

    if ($user) {
        // Avatar URL genereren
        $avatar_url = "https://cdn.discordapp.com/avatars/{$user->id}/{$user->avatar}.png?size=1024";

        // Join the guild after login (Replace with your guild ID)
    $user_id = $user->id; // The logged-in user's ID
    $join_url = "https://discord.com/api/v10/guilds/$guild_id/members/$user_id";

    // Send the request to join the guild
    $join_guild_response = joinGuild($join_url, $access_token);

    // Check if joining was successful
    if ($join_guild_response) {
        error_log("Successfully joined the guild.");
    } else {
        error_log("Failed to join the guild.");
    }
    } else {
        error_log("❌ Failed to fetch user data.");
    }
} else {
    error_log("❌ No valid session found. User not logged in.");
}

// Uitloggen
if (get('action') == 'logout') {
    error_log("🔴 User logged out.");

    logout($revokeURL, [
        'token' => session('access_token'),
        'token_type_hint' => 'access_token',
        'client_id' => OAUTH2_CLIENT_ID,
        'client_secret' => OAUTH2_CLIENT_SECRET,
    ]);

    foreach ($_COOKIE as $cookie_name => $cookie_value) {
      setcookie($cookie_name, "", time() - 3600, "/"); // Set cookie expiry time to the past
      unset($_COOKIE[$cookie_name]); // Remove the cookie from $_COOKIE array
  }

    header('Location: ' . $_SERVER['PHP_SELF']);
    exit();
}

// Function to make the PUT request to join the guild
function joinGuild($url, $access_token) {
  $ch = curl_init($url);

  // Prepare the data
  $data = array("access_token"=>$access_token);

  // Encode the data to JSON
  $json_data = json_encode($data);

  // Set the necessary headers
  $headers = array(
      'Authorization: Bot MTM0MTcxNjYyMTg0ODM0NjY2NA.Gga262.VHnOGc3id5JiXWE9-SeWJUAWxvbYyvFxcgtZOU',
      'Content-Type: application/json',
      'Content-Length: ' . strlen($json_data)
  );

  curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
  curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "PUT");
  curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

  // Set the request body
  curl_setopt($ch, CURLOPT_POSTFIELDS, $json_data);
  curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
  
  curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

  // Execute the request
  $response = curl_exec($ch);

  // Check if there was an error
  if (curl_errno($ch)) {
      error_log("❌ CURL Error: " . curl_error($ch));
  }

  // Decode and return the response
  return json_decode($response);
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

// Logout functie
function logout($url, $data = []) {
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_POST => TRUE,
        CURLOPT_RETURNTRANSFER => TRUE,
        CURLOPT_IPRESOLVE => CURL_IPRESOLVE_V4,
        CURLOPT_HTTPHEADER => ['Content-Type: application/x-www-form-urlencoded'],
        CURLOPT_POSTFIELDS => http_build_query($data),
    ]);
    
    $response = curl_exec($ch);
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
            <h2>Welkom <?= htmlspecialchars($user->global_name) ?>!</h2>
            <p><strong>User:</strong> <?= $user->id ?></p>
            <p><strong>Taal:</strong> <?= $user->locale ?></p>
            <p><strong>2FA Aangezet:</strong> <?= $user->mfa_enabled ? "✅ Ja" : "❌ Nee" ?></p>
            <p><strong>Nitro:</strong> <?= $user->premium_type ? "🔥 Nitro User" : "❌ Geen Nitro" ?></p>
            <a href="?action=logout" class="btn btn-danger logout-btn">Uitloggen</a>
        <?php else: ?>
            <h3>Not logged in</h3>
            <p><a href="?action=login" class="btn btn-primary">Log In met Discord</a></p>
        <?php endif; ?>
    </div>
</body>
</html>
