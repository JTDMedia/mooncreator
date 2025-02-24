<?php
// Debugging aanzetten
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Sessies starten
session_start();

// Database Configuration
$dsn = 'mysql:host=localhost;dbname=bot_management'; // Change as needed
$username = 'root'; // Change as needed
$password = 'VPS123'; // Change as needed

try {
    $pdo = new PDO($dsn, $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die('Could not connect to the database: ' . $e->getMessage());
}

// OAuth2 configuratie
define('OAUTH2_CLIENT_ID', '1341716621848346664');
define('OAUTH2_CLIENT_SECRET', '');
define('CALLBACK_APP_URL', 'http://127.0.0.1:8001/discord.php');

$botToken = isset($_GET['bot']) ? $_GET['bot'] : null;
$baseApiUrl = 'http://mooncreator.worldmc.online/'; // Pas dit aan naar de werkelijke API base URL

// Als geen bot token is opgegeven, redirect naar de dashboard
if (!$botToken) {
    header('Location: /dashboard.php');
    exit();
}

// Functie om API requests te maken
function apiRequest($url, $method = 'GET', $data = null) {
    global $botToken;
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
    curl_setopt($ch, CURLOPT_IPRESOLVE, CURL_IPRESOLVE_V4);

    // Voeg headers toe
    $headers = [
        'Authorization: Bot ' . $botToken,
        'Accept: application/json',
    ];
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

    // Voeg body toe voor POST requests
    if ($method === 'POST' && $data) {
        curl_setopt($ch, CURLOPT_POST, TRUE);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_HTTPHEADER, array_merge($headers, ['Content-Type: application/json']));
    }

    $response = curl_exec($ch);
    curl_close($ch);
    return json_decode($response);
}

// Functie om bot info op te halen
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

// Haal bot data op
$botData = getBotInfo("https://discord.com/api/oauth2/applications/@me", false, [
    "Authorization: Bot $botToken"
]);

// Retrieve modules from the database
$stmt = $pdo->query('SELECT * FROM modules');
$modules = $stmt->fetchAll(PDO::FETCH_OBJ);

// Bestanden ophalen
$files = apiRequest($baseApiUrl . 'api/list-files');

// Functies voor het verwerken van acties
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'start':
                apiRequest($baseApiUrl . 'api/start', 'POST');
                break;
            case 'stop':
                apiRequest($baseApiUrl . 'api/stop', 'POST');
                break;
            case 'restart':
                apiRequest($baseApiUrl . 'api/restart', 'POST');
                break;
            case 'load-template':
                if (isset($_POST['module'])) {
                    $moduleName = $_POST['module'];
                    $enabled = isset($_POST['enabled']) ? 1 : 0;

                    // Save the module to the database
                    $stmt = $pdo->prepare('INSERT INTO modules (name, enabled) VALUES (:name, :enabled) ON DUPLICATE KEY UPDATE enabled = :enabled');
                    $stmt->execute(['name' => $moduleName, 'enabled' => $enabled]);
                    
                    apiRequest($baseApiUrl . 'api/load-template', 'POST', ['module' => $moduleName]);
                }
                break;
            case 'delete-file':
                if (isset($_POST['file'])) {
                    apiRequest($baseApiUrl . 'api/delete-file', 'POST', ['file' => $_POST['file']]);
                }
                break;
            case 'upload-code':
                if (isset($_FILES['discordjs-code'])) {
                    $file = $_FILES['discordjs-code'];
                    $ch = curl_init($baseApiUrl . 'api/upload');
                    curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
                    curl_setopt($ch, CURLOPT_POST, TRUE);
                    curl_setopt($ch, CURLOPT_POSTFIELDS, [
                        'file' => new CURLFile($file['tmp_name'], $file['type'], $file['name']),
                    ]);
                    curl_setopt($ch, CURLOPT_HTTPHEADER, [
                        'Authorization: Bot ' . $botToken,
                    ]);
                    curl_exec($ch);
                    curl_close($ch);
                }
                break;
            case 'npm':
                if (isset($_POST['npm-action']) && isset($_POST['npm-module'])) {
                    apiRequest($baseApiUrl . 'api/npm', 'POST', [
                        'action' => $_POST['npm-action'],
                        'module' => $_POST['npm-module'],
                    ]);
                }
                break;
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Bot - <?= htmlspecialchars($botData->name) ?></title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <style>
        body {
            background-color: #2C2F33;
            color: white;
            font-family: Arial, sans-serif;
        }
        .container {
            max-width: 900px;
        }
        .section {
            margin-top: 30px;
        }
        .module-item {
            padding: 12px;
            background: #23272A;
            border-radius: 8px;
            margin-bottom: 12px;
        }
        .file-item {
            display: flex;
            align-items: center;
            justify-content: space-between;
            background: #23272A;
            padding: 12px;
            border-radius: 8px;
            margin-bottom: 12px;
        }
        .file-item button {
            background-color: red;
            color: white;
            border: none;
            border-radius: 5px;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1 class="text-center">Beheer Bot: <?= htmlspecialchars($botData->name) ?></h1>

        <!-- Bot acties: Start, Stop, Restart -->
        <div class="section">
            <h3>Bot Acties</h3>
            <form method="POST">
                <button type="submit" name="action" value="start" class="btn btn-success">Start</button>
                <button type="submit" name="action" value="stop" class="btn btn-danger">Stop</button>
                <button type="submit" name="action" value="restart" class="btn btn-warning">Restart</button>
            </form>
        </div>

        <!-- Prebuilt Modules -->
        <div class="section">
            <h3>Prebuilt Modules</h3>
            <form method="POST">
                <?php foreach ($modules as $module): ?>
                    <div class="module-item">
                        <label>
                            <input type="checkbox" name="module" value="<?= htmlspecialchars($module->name) ?>" <?= $module->enabled ? 'checked' : '' ?>>
                            <?= htmlspecialchars($module->name) ?>
                        </label>
                    </div>
                <?php endforeach; ?>
                <button type="submit" name="action" value="load-template" class="btn btn-primary">In- of Uitzetten</button>
            </form>
        </div>

        <!-- File Manager -->
        <div class="section">
            <h3>Bestanden</h3>
            <?php foreach ($files as $file): ?>
                <div class="file-item">
                    <span><?= htmlspecialchars($file->name) ?></span>
                    <form method="POST" style="display:inline;">
                        <button type="submit" name="action" value="delete-file" class="btn btn-danger" onclick="return confirm('Weet je zeker dat je dit bestand wilt verwijderen?')">Verwijderen</button>
                        <input type="hidden" name="file" value="<?= htmlspecialchars($file->name) ?>">
                    </form>
                </div>
            <?php endforeach; ?>
        </div>

        <!-- Discord.js Code Upload -->
        <div class="section">
            <h3>Discord.js Code Uploaden</h3>
            <form method="POST" enctype="multipart/form-data">
                <input type="file" name="discordjs-code" class="form-control" required>
                <button type="submit" name="action" value="upload-code" class="btn btn-primary mt-2">Upload Code</button>
            </form>
        </div>

        <!-- NPM Commands -->
        <div class="section">
            <h3>NPM Commands</h3>
            <form method="POST">
                <select name="npm-action" class="form-control" required>
                    <option value="install">npm install</option>
                    <option value="remove">npm remove</option>
                    <option value="list">npm list</option>
                </select>
                <input type="text" name="npm-module" class="form-control mt-2" placeholder="Module naam" required>
                <button type="submit" name="action" value="npm" class="btn btn-primary mt-2">Voer npm Commando uit</button>
            </form>
        </div>
    </div>
</body>
</html>
