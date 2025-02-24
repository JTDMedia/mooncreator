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

// database
$servername = "localhost";
$username = "root";
$password = "VPS123";
$dbname = "storage";

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
        $avatar_url = "https://cdn.discordapp.com/avatars/{$user->id}/{$user->avatar}.png?size=1024";

        // Bots ophalen uit de database
        $pdo = new PDO("mysql:host=$servername;dbname=$dbname", $username, $password);
        $stmt = $pdo->prepare("SELECT token FROM bots WHERE id = :id");
        $stmt->bindParam(':id', $user->id, PDO::PARAM_STR); // ✅ ID als string binden!         
        $stmt->execute();
        $bots = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $botList = [];
        foreach ($bots as $bot) {
            $botToken = $bot['token'];

            $botData = getBotInfo("https://discord.com/api/oauth2/applications/@me", false, [
                "Authorization: Bot $botToken"
            ]);

            if ($botData) {
                $botList[] = [
                    "id" => $botData->id,
                    "name" => $botData->name,
                    "avatar" => "https://cdn.discordapp.com/avatars/{$botData->id}/{$botData->icon}.png?size=1024",
                    "token" => $botToken
                ];
            }
        }
    }
}

// Uitloggen
if (get('action') == 'logout') {
    session_destroy();
    header('Location: ' . $_SERVER['PHP_SELF']);
    exit();
}

function apiRequest($url, $post = FALSE, $headers = []) {
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

    return json_decode(curl_exec($ch));
}

function getBotInfo($url, $post = FALSE, $headers = []) {
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
    curl_setopt($ch, CURLOPT_IPRESOLVE, CURL_IPRESOLVE_V4);

    if ($post) {
        curl_setopt($ch, CURLOPT_POST, TRUE);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($post));
    }

    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

    return json_decode(curl_exec($ch));
}

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
        box-shadow: 0px 0px 15px rgba(0, 0, 0, 0.6);
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
    .bot-list {
        margin-top: 30px;
    }
    .bot-item {
        display: flex;
        align-items: center;
        background: #2C2F33;
        padding: 12px;
        border-radius: 8px;
        margin-bottom: 12px;
        transition: background 0.3s ease;
    }
    .bot-item:hover {
        background: #3A3F47;
    }
    .bot-item img {
        width: 50px;
        height: 50px;
        border-radius: 50%;
        margin-right: 15px;
    }
</style>

</head>
<body>
    <div class="container mt-5">
        <?php if (session('access_token')): ?>
            <div class="profile-card text-center">
                <img src="<?= $avatar_url ?>" alt="Avatar">
                <h2>Welkom <?= htmlspecialchars($user->global_name) ?>!</h2>
                <p><strong>User:</strong> <?= $user->id ?></p>
                <p><strong>Taal:</strong> <?= $user->locale ?></p>
                <a href="?action=logout" class="btn btn-danger">Uitloggen</a>
            </div>

            <center><h3 class="mt-4">Jouw Discord Bots:</h3></center>
            <ul class="list-group bot-item">
            <?php if (!empty($botList)): ?>
    <ul class="list-group bot-item">
        <?php foreach ($botList as $bot): ?>
            <li class="list-group-item d-flex align-items-center">
                <img src="<?= $bot['avatar'] ?>" width="50" height="50" class="me-3">
                <strong><?= htmlspecialchars($bot['name']) ?></strong>
                <a href="manage.php?bot=<?= urlencode($bot['token']) ?>" class="btn btn-primary ms-auto">Beheer</a>
            </li>
        <?php endforeach; ?>
    </ul>
<?php else: ?>
    <p class="text-center text-warning">Je hebt geen bots geregistreerd.</p>
<?php endif; ?>
                <center><a href="/create-bot.php" class="btn btn-primary">Maak nieuwe bot</a></center>
            </ul>
        <?php else: ?>
            <a href="?action=login" class="btn btn-primary">Log in met Discord</a>
        <?php endif; ?>
    </div>
</body>
</html>
