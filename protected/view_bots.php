<?php
$servername = "localhost";
$username = "root";
$password = "VPS123";
$dbname = "storage";

try {
    $conn = new PDO("mysql:host=$servername;dbname=$dbname", $username, $password);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Connection failed: " . $e->getMessage());
}

// Haal alle bots op
$sql = "SELECT id, token FROM bots";
$stmt = $conn->prepare($sql);
$stmt->execute();
$bots = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bot Tokens Overzicht</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            background-color: #f4f4f4;
            text-align: center;
            margin: 20px;
        }
        table {
            width: 80%;
            margin: auto;
            border-collapse: collapse;
            background: white;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
        }
        th, td {
            padding: 10px;
            border: 1px solid #ddd;
        }
        th {
            background: #333;
            color: white;
        }
        tr:nth-child(even) {
            background: #f9f9f9;
        }
    </style>
</head>
<body>
    <h2>Bot Tokens Overzicht</h2>
    <table>
        <tr>
            <th>ID</th>
            <th>Token (Gedeeltelijk)</th>
        </tr>
        <?php foreach ($bots as $bot): ?>
            <tr>
                <td><?= htmlspecialchars($bot['id']) ?></td>
                <td>
                    <?php
                        $token_parts = explode(".", $bot['token']);
                        $masked_token = substr($token_parts[0], 0, strlen($token_parts[0]) / 2) . str_repeat("*", strlen($token_parts[0]) / 2) . "." . $token_parts[1] . ".***";
                        echo htmlspecialchars($masked_token);
                    ?>
                </td>
            </tr>
        <?php endforeach; ?>
    </table>
</body>
</html>

<?php
$conn = null;
?>
