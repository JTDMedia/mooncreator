<?php
$servername = "localhost";
$username = "root";
$password = "VPS123";
$dbname = "bot_management";

try {
    $conn = new PDO("mysql:host=$servername;dbname=$dbname", $username, $password);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Fetch bots and modules
    $bots = $conn->query("SELECT * FROM bots")->fetchAll(PDO::FETCH_OBJ);
    $modules = $conn->query("SELECT * FROM modules")->fetchAll(PDO::FETCH_OBJ);

    // Handle actions
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (isset($_POST['action'])) {
            switch ($_POST['action']) {
                case 'toggle_module':
                    $bot_id = $_POST['bot_id'];
                    $module_id = $_POST['module_id'];
                    $enabled = $_POST['enabled'] ? 1 : 0;
                    $stmt = $conn->prepare("REPLACE INTO bot_modules (bot_id, module_id, enabled) VALUES (:bot_id, :module_id, :enabled)");
                    $stmt->execute(['bot_id' => $bot_id, 'module_id' => $module_id, 'enabled' => $enabled]);
                    break;
                case 'add_bot':
                    $stmt = $conn->prepare("INSERT INTO bots (name, token) VALUES (:name, :token)");
                    $stmt->execute(['name' => $_POST['bot_name'], 'token' => $_POST['bot_token']]);
                    break;
                case 'add_module':
                    $stmt = $conn->prepare("INSERT INTO modules (name, description) VALUES (:name, :description)");
                    $stmt->execute(['name' => $_POST['module_name'], 'description' => $_POST['module_description']]);
                    break;
            }
        }
    }
} catch(PDOException $e) {
    echo "Error: " . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin - Manage Bots and Modules</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
</head>
<body>
    <div class="container">
        <h1 class="text-center">Admin Panel</h1>

        <!-- Add Bot -->
        <h3>Add Bot</h3>
        <form method="POST">
            <div class="mb-3">
                <label for="bot_name" class="form-label">Bot Name</label>
                <input type="text" class="form-control" name="bot_name" id="bot_name" required>
            </div>
            <div class="mb-3">
                <label for="bot_token" class="form-label">Bot Token</label>
                <input type="text" class="form-control" name="bot_token" id="bot_token" required>
            </div>
            <button type="submit" name="action" value="add_bot" class="btn btn-success">Add Bot</button>
        </form>

        <!-- Add Module -->
        <h3>Add Module</h3>
        <form method="POST">
            <div class="mb-3">
                <label for="module_name" class="form-label">Module Name</label>
                <input type="text" class="form-control" name="module_name" id="module_name" required>
            </div>
            <div class="mb-3">
                <label for="module_description" class="form-label">Module Description</label>
                <textarea class="form-control" name="module_description" id="module_description" required></textarea>
            </div>
            <button type="submit" name="action" value="add_module" class="btn btn-primary">Add Module</button>
        </form>

        <!-- Manage Bots and Modules -->
        <h3>Manage Bots</h3>
        <?php foreach ($bots as $bot): ?>
            <h4>Bot: <?= htmlspecialchars($bot->name) ?></h4>
            <form method="POST">
                <?php foreach ($modules as $module): ?>
                    <div>
                        <label>
                            <input type="checkbox" name="enabled" value="1" 
                                   <?= isset($conn->query("SELECT * FROM bot_modules WHERE bot_id = {$bot->id} AND module_id = {$module->id}")->fetchObject()->enabled) && $conn->query("SELECT * FROM bot_modules WHERE bot_id = {$bot->id} AND module_id = {$module->id}")->fetchObject()->enabled == 1 ? 'checked' : '' ?>>
                            <?= htmlspecialchars($module->name) ?>
                        </label>
                        <input type="hidden" name="bot_id" value="<?= $bot->id ?>">
                        <input type="hidden" name="module_id" value="<?= $module->id ?>">
                        <button type="submit" name="action" value="toggle_module" class="btn btn-secondary">Toggle</button>
                    </div>
                <?php endforeach; ?>
            </form>
        <?php endforeach; ?>
    </div>
</body>
</html>
