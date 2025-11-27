<?php
/**
 * SafeZone Emergency Alert System API
 * Backend controller for mobile app and dispatcher dashboard
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

// Database configuration for MySQL on port 3307
define('DB_HOST', 'localhost:3307');
define('DB_USER', 'root');
define('DB_PASS', ''); // Change to 'root' if that's your MySQL password
define('DB_NAME', 'safezone_poc');

// File upload configuration
define('UPLOAD_DIR', 'uploads/');
define('MAX_FILE_SIZE', 10 * 1024 * 1024); // 10MB
define('ALLOWED_IMAGE_TYPES', ['jpg', 'jpeg', 'png', 'gif']);
define('ALLOWED_VIDEO_TYPES', ['mp4', 'mov', 'avi']);

// Create uploads directory if it doesn't exist
if (!file_exists(UPLOAD_DIR)) {
    if (!mkdir(UPLOAD_DIR, 0777, true)) {
        sendResponse(false, 'Could not create upload directory');
    }
}

// Get action parameter from GET or POST
$action = $_GET['action'] ?? ($_POST['action'] ?? '');

// Debug logging
error_log("API Request - Action: " . $action . ", Method: " . $_SERVER['REQUEST_METHOD']);

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

try {
    // Database connection with port 3307
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
        case 'upload_media':
            handleUploadMedia($db);
            break;
        case 'test_connection':
            handleTestConnection($db);
            break;
        case 'solve_emergency':
            handleSolveEmergency($db);
            break;
        default:
            sendResponse(false, 'Invalid action: "' . $action . '". Available actions: alert_trigger, get_alerts, get_chat, send_message, test_connection');
    }
} catch (PDOException $e) {
    error_log("Database error: " . $e->getMessage());
    sendResponse(false, 'Database connection failed: ' . $e->getMessage());
} catch (Exception $e) {
    error_log("General error: " . $e->getMessage());
    sendResponse(false, 'Error: ' . $e->getMessage());
}

/**
 * Test database connection
 */
function handleTestConnection($db) {
    $stmt = $db->query("SELECT 1 as test");
    $result = $stmt->fetch();
    sendResponse(true, ['message' => 'Database connection successful', 'test' => $result]);
}

/**
 * Handle emergency alert trigger
 */
function handleAlertTrigger($db) {
    // Get JSON input for more reliable data handling
    $input = getJsonInput();
    $fullName = $input['full_name'] ?? ($_POST['full_name'] ?? '');
    $phoneNumber = $input['phone_number'] ?? ($_POST['phone_number'] ?? '');
    $gpsLat = $input['gps_lat'] ?? ($_POST['gps_lat'] ?? 0);
    $gpsLng = $input['gps_lng'] ?? ($_POST['gps_lng'] ?? 0);
    
    if (empty($fullName) || empty($phoneNumber)) {
        sendResponse(false, 'Missing required fields: full_name and phone_number are required');
    }
    
    // Validate GPS coordinates
    if (!is_numeric($gpsLat) || !is_numeric($gpsLng)) {
        $gpsLat = 37.7749; // Default San Francisco coordinates
        $gpsLng = -122.4194;
    }
    
    // Find or create user
    $stmt = $db->prepare("SELECT id FROM users WHERE phone_number = ?");
    $stmt->execute([$phoneNumber]);
    $user = $stmt->fetch();
    
    if (!$user) {
        $stmt = $db->prepare("INSERT INTO users (full_name, phone_number) VALUES (?, ?)");
        if (!$stmt->execute([$fullName, $phoneNumber])) {
            sendResponse(false, 'Failed to create user');
        }
        $userId = $db->lastInsertId();
    } else {
        $userId = $user['id'];
    }
    
    // Create new incident
    $stmt = $db->prepare("INSERT INTO incidents (user_id, gps_lat, gps_lng, status, latest_message) VALUES (?, ?, ?, 'active', ?)");
    $initialMessage = "Emergency alert triggered by {$fullName}";
    
    if (!$stmt->execute([$userId, $gpsLat, $gpsLng, $initialMessage])) {
        sendResponse(false, 'Failed to create incident');
    }
    
    $incidentId = $db->lastInsertId();
    
    // Verify incident was created
    $stmt = $db->prepare("SELECT id FROM incidents WHERE id = ?");
    $stmt->execute([$incidentId]);
    $incident = $stmt->fetch();
    
    if (!$incident) {
        sendResponse(false, 'Failed to verify incident creation');
    }
    
    // Create initial message
    $stmt = $db->prepare("INSERT INTO messages (incident_id, sender, message_text) VALUES (?, 'user', ?)");
    if (!$stmt->execute([$incidentId, "EMERGENCY ALERT: I need help!"])) {
        sendResponse(false, 'Failed to create initial message');
    }
    
    sendResponse(true, [
        'incident_id' => $incidentId,
        'message' => 'Emergency alert created successfully'
    ]);
}

// In the get_alerts function, add debugging:
function handleGetAlerts($db) {
    error_log("=== GET_ALERTS CALLED ===");
    
    $stmt = $db->prepare("
        SELECT i.*, u.full_name as user_name 
        FROM incidents i 
        JOIN users u ON i.user_id = u.id 
        WHERE i.status = 'active' 
        ORDER BY i.created_at DESC
    ");
    
    if (!$stmt->execute()) {
        error_log("GET_ALERTS: Query execution failed");
        sendResponse(false, 'Failed to fetch alerts');
    }
    
    $alerts = $stmt->fetchAll();
    error_log("GET_ALERTS: Found " . count($alerts) . " active incidents");
    
    // Log each incident found
    foreach ($alerts as $alert) {
        error_log("GET_ALERTS - Incident: ID={$alert['id']}, User={$alert['user_name']}, Status={$alert['status']}");
    }
    
    sendResponse(true, ['alerts' => $alerts]);
}

/**
 * Get chat messages for an incident
 */
function handleGetChat($db) {
    $incidentId = $_GET['incident_id'] ?? 0;
    
    if (!$incidentId || !is_numeric($incidentId)) {
        sendResponse(false, 'Valid incident ID required');
    }
    
    $stmt = $db->prepare("
        SELECT * FROM messages 
        WHERE incident_id = ? 
        ORDER BY created_at ASC
    ");
    
    if (!$stmt->execute([$incidentId])) {
        sendResponse(false, 'Failed to fetch messages');
    }
    
    $messages = $stmt->fetchAll();
    
    sendResponse(true, ['messages' => $messages]);
}

/**
 * Send a message in chat
 */
function handleSendMessage($db) {
    $input = getJsonInput();
    $incidentId = $input['incident_id'] ?? ($_POST['incident_id'] ?? 0);
    $sender = $input['sender'] ?? ($_POST['sender'] ?? '');
    $messageText = $input['message_text'] ?? ($_POST['message_text'] ?? '');
    $mediaPath = null;
    
    if (!$incidentId || !in_array($sender, ['user', 'dispatcher'])) {
        sendResponse(false, 'Invalid parameters: incident_id and valid sender required');
    }
    
    // Validate that the incident exists
    $stmt = $db->prepare("SELECT id FROM incidents WHERE id = ?");
    if (!$stmt->execute([$incidentId])) {
        sendResponse(false, 'Failed to validate incident');
    }
    
    $incident = $stmt->fetch();
    if (!$incident) {
        sendResponse(false, 'Incident not found with ID: ' . $incidentId);
    }
    
    // Handle file upload
    if (isset($_FILES['media']) && $_FILES['media']['error'] === UPLOAD_ERR_OK) {
        $mediaPath = handleFileUpload($_FILES['media']);
        if (!$mediaPath) {
            sendResponse(false, 'File upload failed');
        }
    }
    
    // If no message text and no media, return error
    if (empty($messageText) && empty($mediaPath)) {
        sendResponse(false, 'Message text or media is required');
    }
    
    // Insert message
    $stmt = $db->prepare("
        INSERT INTO messages (incident_id, sender, message_text, media_path) 
        VALUES (?, ?, ?, ?)
    ");
    
    if (!$stmt->execute([$incidentId, $sender, $messageText, $mediaPath])) {
        sendResponse(false, 'Failed to send message');
    }
    
    // Update incident's latest message
    $latestMessage = $messageText ?: ($mediaPath ? 'Media shared' : 'Message sent');
    $stmt = $db->prepare("UPDATE incidents SET latest_message = ? WHERE id = ?");
    $stmt->execute([$latestMessage, $incidentId]);
    
    sendResponse(true, [
        'message_id' => $db->lastInsertId(),
        'message' => 'Message sent successfully'
    ]);
}

/**
 * Handle file upload
 */
function handleFileUpload($file) {
    $fileName = $file['name'];
    $fileTmpName = $file['tmp_name'];
    $fileSize = $file['size'];
    $fileError = $file['error'];
    
    // Check for errors
    if ($fileError !== UPLOAD_ERR_OK) {
        error_log("File upload error: " . $fileError);
        return false;
    }
    
    // Check file size
    if ($fileSize > MAX_FILE_SIZE) {
        error_log("File too large: " . $fileSize);
        return false;
    }
    
    // Get file extension
    $fileExt = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
    
    // Check allowed types
    $allowedTypes = array_merge(ALLOWED_IMAGE_TYPES, ALLOWED_VIDEO_TYPES);
    if (!in_array($fileExt, $allowedTypes)) {
        error_log("File type not allowed: " . $fileExt);
        return false;
    }
    
    // Generate unique filename
    $newFileName = uniqid('', true) . '.' . $fileExt;
    $fileDestination = UPLOAD_DIR . $newFileName;
    
    // Move uploaded file
    if (move_uploaded_file($fileTmpName, $fileDestination)) {
        return $fileDestination;
    }
    
    error_log("Failed to move uploaded file");
    return false;
}

/**
 * Get JSON input from request
 */
function getJsonInput() {
    $input = file_get_contents('php://input');
    if (!empty($input)) {
        return json_decode($input, true) ?? [];
    }
    return [];
}

/**
 * Send JSON response
 */
function sendResponse($success, $data) {
    $response = [
        'success' => $success,
        'timestamp' => date('Y-m-d H:i:s')
    ];
    
    if ($success) {
        $response['data'] = $data;
    } else {
        $response['error'] = $data;
    }
    
    echo json_encode($response);
    exit;
}

/**
 * Handle solving/ending an emergency
 */
function handleSolveEmergency($db) {
    $incidentId = $_POST['incident_id'] ?? 0;
    
    if (!$incidentId) {
        sendResponse(false, 'Incident ID required');
    }
    
    // Verify incident exists and is active
    $stmt = $db->prepare("SELECT id, status FROM incidents WHERE id = ?");
    $stmt->execute([$incidentId]);
    $incident = $stmt->fetch();
    
    if (!$incident) {
        sendResponse(false, 'Incident not found');
    }
    
    if ($incident['status'] !== 'active') {
        sendResponse(false, 'Incident is already closed');
    }
    
    // Update incident status to closed
    $stmt = $db->prepare("UPDATE incidents SET status = 'closed', latest_message = ? WHERE id = ?");
    $solvedMessage = "Emergency resolved by dispatcher";
    if (!$stmt->execute([$solvedMessage, $incidentId])) {
        sendResponse(false, 'Failed to update incident status');
    }
    
    // Add resolution message to chat
    $stmt = $db->prepare("INSERT INTO messages (incident_id, sender, message_text) VALUES (?, 'dispatcher', ?)");
    $resolutionMessage = "🚨 EMERGENCY RESOLVED: This incident has been marked as solved and is now closed.";
    $stmt->execute([$incidentId, $resolutionMessage]);
    
    sendResponse(true, [
        'message' => 'Emergency solved successfully',
        'incident_id' => $incidentId
    ]);
}


?>