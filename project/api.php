<?php
/**
 * SafeZone Emergency Alert System API - FIXED VERSION
 * Backend controller for mobile app and dispatcher dashboard
 */

// Headers first, before any output
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit(0);
}

// Database configuration
define('DB_HOST', 'localhost:3307');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'safezone_poc');

// File upload configuration
define('UPLOAD_DIR', 'uploads/');
define('MAX_FILE_SIZE', 10 * 1024 * 1024); // 10MB

// Error reporting - be careful in production
error_reporting(E_ALL);
ini_set('display_errors', 0); // Don't display errors to users
ini_set('log_errors', 1);

// Create uploads directory if it doesn't exist
if (!file_exists(UPLOAD_DIR)) {
    @mkdir(UPLOAD_DIR, 0777, true);
}

// Get action parameter
$action = $_GET['action'] ?? ($_POST['action'] ?? '');

try {
    // Database connection
    $dsn = "mysql:host=localhost;port=3307;dbname=" . DB_NAME . ";charset=utf8mb4";
    $options = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ];
    
    $db = new PDO($dsn, DB_USER, DB_PASS, $options);
    
    // Handle different actions
    switch ($action) {
        case 'alert_trigger':
            handleAlertTrigger($db);
            break;
        case 'get_alerts':
            handleGetAlerts($db);
            break;
        case 'get_chat':
            handleGetChat($db);
            break;
        case 'send_message':
            handleSendMessage($db);
            break;
        case 'test_connection':
            handleTestConnection($db);
            break;
        case 'solve_emergency':
            handleSolveEmergency($db);
            break;
        default:
            sendResponse(false, 'Invalid action. Available: alert_trigger, get_alerts, get_chat, send_message, test_connection, solve_emergency');
    }
} catch (PDOException $e) {
    error_log("Database error: " . $e->getMessage());
    sendResponse(false, 'Database connection failed');
} catch (Exception $e) {
    error_log("General error: " . $e->getMessage());
    sendResponse(false, 'Server error occurred');
}

/**
 * Test database connection
 */
function handleTestConnection($db) {
    try {
        $stmt = $db->query("SELECT 1 as test");
        $result = $stmt->fetch();
        sendResponse(true, ['message' => 'Database connection successful', 'test' => $result]);
    } catch (Exception $e) {
        sendResponse(false, 'Database test failed: ' . $e->getMessage());
    }
}

/**
 * Handle emergency alert trigger - SIMPLIFIED AND FIXED
 */
function handleAlertTrigger($db) {
    // Get input data
    $fullName = $_POST['full_name'] ?? '';
    $phoneNumber = $_POST['phone_number'] ?? '';
    $gpsLat = floatval($_POST['gps_lat'] ?? 0);
    $gpsLng = floatval($_POST['gps_lng'] ?? 0);
    $gpsAccuracy = floatval($_POST['gps_accuracy'] ?? 0);
    $locationMethod = $_POST['location_method'] ?? 'unknown';
    
    // Validate required fields
    if (empty($fullName) || empty($phoneNumber)) {
        sendResponse(false, 'Missing required fields: full_name and phone_number are required');
        return;
    }
    
    // Validate GPS coordinates - use defaults if invalid
    if ($gpsLat == 0 || $gpsLng == 0) {
        $gpsLat = 14.5995; // Default Manila coordinates
        $gpsLng = 120.9842;
        $gpsAccuracy = 50000;
        $locationMethod = 'default';
    }
    
    try {
        // Start transaction
        $db->beginTransaction();
        
        // Find or create user
        $stmt = $db->prepare("SELECT id FROM users WHERE phone_number = ?");
        $stmt->execute([$phoneNumber]);
        $user = $stmt->fetch();
        
        if (!$user) {
            $stmt = $db->prepare("INSERT INTO users (full_name, phone_number) VALUES (?, ?)");
            if (!$stmt->execute([$fullName, $phoneNumber])) {
                throw new Exception('Failed to create user');
            }
            $userId = $db->lastInsertId();
        } else {
            $userId = $user['id'];
        }
        
        // Create new incident with enhanced location data
        $stmt = $db->prepare("INSERT INTO incidents (user_id, gps_lat, gps_lng, gps_accuracy, location_method, status, latest_message) VALUES (?, ?, ?, ?, ?, 'active', ?)");
        $initialMessage = "Emergency alert triggered by {$fullName}";
        
        if (!$stmt->execute([$userId, $gpsLat, $gpsLng, $gpsAccuracy, $locationMethod, $initialMessage])) {
            throw new Exception('Failed to create incident');
        }
        
        $incidentId = $db->lastInsertId();
        
        // Create initial emergency message
        $stmt = $db->prepare("INSERT INTO messages (incident_id, sender, message_text) VALUES (?, 'user', ?)");
        $emergencyMessage = "🚨 EMERGENCY ALERT: I need help! Location: {$gpsLat}, {$gpsLng}";
        if (!$stmt->execute([$incidentId, $emergencyMessage])) {
            throw new Exception('Failed to create initial message');
        }
        
        // Commit transaction
        $db->commit();
        
        sendResponse(true, [
            'incident_id' => $incidentId,
            'message' => 'Emergency alert created successfully',
            'location' => [
                'lat' => $gpsLat,
                'lng' => $gpsLng,
                'accuracy' => $gpsAccuracy
            ]
        ]);
        
    } catch (Exception $e) {
        $db->rollBack();
        sendResponse(false, 'Failed to process emergency alert: ' . $e->getMessage());
    }
}

/**
 * Get active alerts - FIXED
 */
function handleGetAlerts($db) {
    try {
        $stmt = $db->prepare("
            SELECT i.*, u.full_name as user_name 
            FROM incidents i 
            JOIN users u ON i.user_id = u.id 
            WHERE i.status = 'active' 
            ORDER BY i.created_at DESC
        ");
        
        if (!$stmt->execute()) {
            sendResponse(false, 'Failed to fetch alerts');
            return;
        }
        
        $alerts = $stmt->fetchAll();
        
        // Ensure all alerts have proper data
        foreach ($alerts as &$alert) {
            $alert['user_name'] = $alert['user_name'] ?? 'Unknown User';
            $alert['gps_lat'] = floatval($alert['gps_lat'] ?? 0);
            $alert['gps_lng'] = floatval($alert['gps_lng'] ?? 0);
        }
        
        sendResponse(true, ['alerts' => $alerts]);
        
    } catch (Exception $e) {
        sendResponse(false, 'Error fetching alerts: ' . $e->getMessage());
    }
}

/**
 * Get chat messages for an incident
 */
function handleGetChat($db) {
    $incidentId = intval($_GET['incident_id'] ?? 0);
    
    if ($incidentId <= 0) {
        sendResponse(false, 'Valid incident ID required');
        return;
    }
    
    try {
        $stmt = $db->prepare("
            SELECT * FROM messages 
            WHERE incident_id = ? 
            ORDER BY created_at ASC
        ");
        
        if (!$stmt->execute([$incidentId])) {
            sendResponse(false, 'Failed to fetch messages');
            return;
        }
        
        $messages = $stmt->fetchAll();
        
        // Ensure proper data types
        foreach ($messages as &$message) {
            $message['incident_id'] = intval($message['incident_id']);
        }
        
        sendResponse(true, ['messages' => $messages]);
        
    } catch (Exception $e) {
        sendResponse(false, 'Error fetching chat: ' . $e->getMessage());
    }
}

/**
 * Send a message in chat - FIXED
 */
function handleSendMessage($db) {
    $incidentId = intval($_POST['incident_id'] ?? 0);
    $sender = $_POST['sender'] ?? '';
    $messageText = $_POST['message_text'] ?? '';
    $mediaPath = null;
    
    // Validate inputs
    if ($incidentId <= 0) {
        sendResponse(false, 'Valid incident ID required');
        return;
    }
    
    if (!in_array($sender, ['user', 'dispatcher'])) {
        sendResponse(false, 'Valid sender required (user or dispatcher)');
        return;
    }
    
    if (empty($messageText) && empty($_FILES['media'])) {
        sendResponse(false, 'Message text or media is required');
        return;
    }
    
    try {
        // Validate that the incident exists
        $stmt = $db->prepare("SELECT id FROM incidents WHERE id = ?");
        $stmt->execute([$incidentId]);
        $incident = $stmt->fetch();
        
        if (!$incident) {
            sendResponse(false, 'Incident not found');
            return;
        }
        
        // Handle file upload
        if (isset($_FILES['media']) && $_FILES['media']['error'] === UPLOAD_ERR_OK) {
            $mediaPath = handleFileUpload($_FILES['media']);
            if (!$mediaPath) {
                sendResponse(false, 'File upload failed');
                return;
            }
        }
        
        // Insert message
        $stmt = $db->prepare("
            INSERT INTO messages (incident_id, sender, message_text, media_path) 
            VALUES (?, ?, ?, ?)
        ");
        
        if (!$stmt->execute([$incidentId, $sender, $messageText, $mediaPath])) {
            sendResponse(false, 'Failed to send message');
            return;
        }
        
        $messageId = $db->lastInsertId();
        
        // Update incident's latest message
        $latestMessage = !empty($messageText) ? $messageText : 'Media shared';
        $stmt = $db->prepare("UPDATE incidents SET latest_message = ? WHERE id = ?");
        $stmt->execute([$latestMessage, $incidentId]);
        
        sendResponse(true, [
            'message_id' => $messageId,
            'message' => 'Message sent successfully'
        ]);
        
    } catch (Exception $e) {
        sendResponse(false, 'Error sending message: ' . $e->getMessage());
    }
}

/**
 * Handle file upload
 */
function handleFileUpload($file) {
    // Basic validation
    if ($file['error'] !== UPLOAD_ERR_OK) {
        return false;
    }
    
    if ($file['size'] > MAX_FILE_SIZE) {
        return false;
    }
    
    // Generate safe filename
    $fileExt = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $allowedTypes = ['jpg', 'jpeg', 'png', 'gif', 'mp4', 'mov', 'avi'];
    
    if (!in_array($fileExt, $allowedTypes)) {
        return false;
    }
    
    $newFileName = uniqid('', true) . '.' . $fileExt;
    $fileDestination = UPLOAD_DIR . $newFileName;
    
    if (move_uploaded_file($file['tmp_name'], $fileDestination)) {
        return $fileDestination;
    }
    
    return false;
}

/**
 * Handle solving/ending an emergency
 */
function handleSolveEmergency($db) {
    $incidentId = intval($_POST['incident_id'] ?? 0);
    
    if ($incidentId <= 0) {
        sendResponse(false, 'Valid incident ID required');
        return;
    }
    
    try {
        // Verify incident exists and is active
        $stmt = $db->prepare("SELECT id, status FROM incidents WHERE id = ?");
        $stmt->execute([$incidentId]);
        $incident = $stmt->fetch();
        
        if (!$incident) {
            sendResponse(false, 'Incident not found');
            return;
        }
        
        if ($incident['status'] !== 'active') {
            sendResponse(false, 'Incident is already closed');
            return;
        }
        
        // Update incident status to closed
        $stmt = $db->prepare("UPDATE incidents SET status = 'closed', latest_message = ? WHERE id = ?");
        $solvedMessage = "Emergency resolved by dispatcher";
        if (!$stmt->execute([$solvedMessage, $incidentId])) {
            sendResponse(false, 'Failed to update incident status');
            return;
        }
        
        // Add resolution message to chat
        $stmt = $db->prepare("INSERT INTO messages (incident_id, sender, message_text) VALUES (?, 'dispatcher', ?)");
        $resolutionMessage = "🚨 EMERGENCY RESOLVED: This incident has been marked as solved and is now closed.";
        $stmt->execute([$incidentId, $resolutionMessage]);
        
        sendResponse(true, [
            'message' => 'Emergency solved successfully',
            'incident_id' => $incidentId
        ]);
        
    } catch (Exception $e) {
        sendResponse(false, 'Error solving emergency: ' . $e->getMessage());
    }
}

/**
 * Send JSON response - ENSURES PROPER JSON OUTPUT
 */
function sendResponse($success, $data) {
    $response = [
        'success' => (bool)$success,
        'timestamp' => date('Y-m-d H:i:s')
    ];
    
    if ($success) {
        $response['data'] = $data;
    } else {
        $response['error'] = $data;
    }
    
    // Ensure no extra output
    ob_clean();
    
    echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}

?>