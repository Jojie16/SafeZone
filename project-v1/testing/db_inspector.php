<?php
/**
 * Database Inspector - Check what's actually in the database
 */

header('Content-Type: text/html; charset=utf-8');

// Database configuration
define('DB_HOST', 'localhost:3307');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'safezone_poc');

try {
    $db = new PDO("mysql:host=localhost;port=3307;dbname=" . DB_NAME, DB_USER, DB_PASS);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "<h1>Database Inspector - SafeZone POC</h1>";
    echo "<p>Checking database contents...</p>";
    
    // Check users
    echo "<h2>Users Table</h2>";
    $stmt = $db->query("SELECT * FROM users ORDER BY id DESC");
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($users)) {
        echo "<p style='color: red;'>No users found in database!</p>";
    } else {
        echo "<table border='1' cellpadding='8' style='border-collapse: collapse;'>";
        echo "<tr><th>ID</th><th>Name</th><th>Phone</th><th>Created</th></tr>";
        foreach ($users as $user) {
            echo "<tr>";
            echo "<td>{$user['id']}</td>";
            echo "<td>{$user['full_name']}</td>";
            echo "<td>{$user['phone_number']}</td>";
            echo "<td>{$user['created_at']}</td>";
            echo "</tr>";
        }
        echo "</table>";
    }
    
    // Check incidents
    echo "<h2>Incidents Table</h2>";
    $stmt = $db->query("SELECT i.*, u.full_name as user_name FROM incidents i LEFT JOIN users u ON i.user_id = u.id ORDER BY i.id DESC");
    $incidents = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($incidents)) {
        echo "<p style='color: red;'>No incidents found in database!</p>";
    } else {
        echo "<table border='1' cellpadding='8' style='border-collapse: collapse;'>";
        echo "<tr><th>ID</th><th>User</th><th>Status</th><th>GPS</th><th>Latest Message</th><th>Created</th></tr>";
        foreach ($incidents as $incident) {
            $statusColor = $incident['status'] === 'active' ? 'green' : 'red';
            echo "<tr>";
            echo "<td>{$incident['id']}</td>";
            echo "<td>{$incident['user_name']} (ID: {$incident['user_id']})</td>";
            echo "<td style='color: {$statusColor};'><strong>{$incident['status']}</strong></td>";
            echo "<td>{$incident['gps_lat']}, {$incident['gps_lng']}</td>";
            echo "<td>{$incident['latest_message']}</td>";
            echo "<td>{$incident['created_at']}</td>";
            echo "</tr>";
        }
        echo "</table>";
    }
    
    // Check messages
    echo "<h2>Messages Table</h2>";
    $stmt = $db->query("SELECT m.*, i.status as incident_status FROM messages m LEFT JOIN incidents i ON m.incident_id = i.id ORDER BY m.id DESC");
    $messages = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($messages)) {
        echo "<p style='color: red;'>No messages found in database!</p>";
    } else {
        echo "<table border='1' cellpadding='8' style='border-collapse: collapse;'>";
        echo "<tr><th>ID</th><th>Incident ID</th><th>Sender</th><th>Message</th><th>Media</th><th>Incident Status</th><th>Created</th></tr>";
        foreach ($messages as $message) {
            echo "<tr>";
            echo "<td>{$message['id']}</td>";
            echo "<td>{$message['incident_id']}</td>";
            echo "<td>{$message['sender']}</td>";
            echo "<td>{$message['message_text']}</td>";
            echo "<td>" . ($message['media_path'] ? $message['media_path'] : 'None') . "</td>";
            echo "<td>{$message['incident_status']}</td>";
            echo "<td>{$message['created_at']}</td>";
            echo "</tr>";
        }
        echo "</table>";
    }
    
    // Test API endpoint directly
    echo "<h2>API Test - get_alerts</h2>";
    $stmt = $db->query("SELECT i.*, u.full_name as user_name FROM incidents i JOIN users u ON i.user_id = u.id WHERE i.status = 'active' ORDER BY i.created_at DESC");
    $apiAlerts = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "<p>Active incidents found by API query: " . count($apiAlerts) . "</p>";
    echo "<pre>" . json_encode($apiAlerts, JSON_PRETTY_PRINT) . "</pre>";
    
} catch (PDOException $e) {
    echo "<p style='color: red;'>Database error: " . $e->getMessage() . "</p>";
}
?>