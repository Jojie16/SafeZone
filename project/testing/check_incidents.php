<?php
header('Content-Type: text/plain');
define('DB_HOST', 'localhost:3307');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'safezone_poc');

try {
    $db = new PDO("mysql:host=localhost;port=3307;dbname=safezone_poc", DB_USER, DB_PASS);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "=== USERS ===\n";
    $stmt = $db->query("SELECT * FROM users");
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($users as $user) {
        echo "User ID: {$user['id']}, Name: {$user['full_name']}, Phone: {$user['phone_number']}\n";
    }
    
    echo "\n=== INCIDENTS ===\n";
    $stmt = $db->query("SELECT * FROM incidents");
    $incidents = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($incidents as $incident) {
        echo "Incident ID: {$incident['id']}, User ID: {$incident['user_id']}, Status: {$incident['status']}\n";
    }
    
    echo "\n=== MESSAGES ===\n";
    $stmt = $db->query("SELECT * FROM messages");
    $messages = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($messages as $message) {
        echo "Message ID: {$message['id']}, Incident ID: {$message['incident_id']}, Sender: {$message['sender']}\n";
    }
    
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
}
?>