<?php
$servername = "localhost";
$username = "root";
$password = "VPS123";
$dbname = "storage";

try {
    // Verbinding maken met de database
    $conn = new PDO("mysql:host=$servername;dbname=$dbname", $username, $password);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Verwijder bestaande tabellen
    $sql = "DROP TABLE IF EXISTS bots";
    $conn->exec($sql);

    // SQL om de tabel opnieuw te maken met een 64-bit integer voor id
    $sql = "CREATE TABLE bots (
    id BIGINT UNSIGNED PRIMARY KEY,
    token VARCHAR(100) NOT NULL
    )";

    // Tabel aanmaken
    $conn->exec($sql);
    echo "Table 'bots' created successfully";

} catch(PDOException $e) {
    echo "Error: " . $e->getMessage();
}

$conn = null;
?>
