<?php
$servername = "localhost";
$username = "root";
$password = "VPS123";
$dbname = "storage";
$custom_api_url = "http://mooncreator.worldmc.online:3000/create"; // Verander dit naar de gewenste API URL

try {
    $conn = new PDO("mysql:host=$servername;dbname=$dbname", $username, $password);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) {
    die("Connection failed: " . $e->getMessage());
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (isset($_POST['id']) && !empty($_POST['id']) && isset($_POST['token']) && !empty($_POST['token'])) {
        $id = $_POST['id'];
        $token = $_POST['token'];

        // Verifieer de token bij Discord API
        $ch = curl_init("https://discord.com/api/v10/users/@me");
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            "Authorization: Bot $token",
            "Content-Type: application/json"
        ]);
        $discord_response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($http_code !== 200) {
            die("Invalid bot token: API request failed with status code $http_code");
        }

        // Stuur een POST-verzoek naar de andere API met de bot token in de Authorization header
        $post_data = json_encode(["message" => "Hello, API! This is a test."]); // Pas aan wat je wilt verzenden
        $ch = curl_init($custom_api_url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $post_data);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            "Authorization: Bot $token",
            "Content-Type: application/json"
        ]);
        $custom_api_response = curl_exec($ch);
        $custom_api_http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        // Sla de bot op in de database
        try {
            $sql = "INSERT INTO bots (id, token) VALUES (:id, :token)";
            $stmt = $conn->prepare($sql);
            $stmt->bindParam(':id', $id, PDO::PARAM_INT);
            $stmt->bindParam(':token', $token, PDO::PARAM_STR);
            $stmt->execute();

            // Masker de token: toon alleen de eerste helft
            $token_parts = explode(".", $token);
            $masked_token = substr($token_parts[0], 0, strlen($token_parts[0]) / 2) . str_repeat("*", strlen($token_parts[0]) / 2) . "." . $token_parts[1] . ".***";

            // Output wat er is opgeslagen en de API-responsen
            echo "New record created successfully.<br>";
            echo "Stored Data: ID = $id, Token = $masked_token<br>";
            echo "Discord API Response: $discord_response<br>";
            echo "Custom API Response ($custom_api_http_code): $custom_api_response";

        } catch(PDOException $e) {
            echo "Database Error: " . $e->getMessage();
        }
    } else {
        echo "Both id and token are required!";
    }
}

// Sluit de verbinding
$conn = null;
?>
