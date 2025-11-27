<?php
/**
 * MySQL Connection Diagnostic Tool
 * Run this first to fix database connection issues
 */
header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html>
<head>
    <title>MySQL Connection Test</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 40px; }
        .success { color: green; font-weight: bold; }
        .error { color: red; font-weight: bold; }
        .info { background: #f0f0f0; padding: 20px; margin: 10px 0; }
    </style>
</head>
<body>
    <h1>MySQL Connection Diagnostic</h1>
    
    <?php
    echo "<div class='info'>";
    echo "<strong>Server Software:</strong> " . $_SERVER['SERVER_SOFTWARE'] . "<br>";
    echo "<strong>PHP Version:</strong> " . phpversion() . "<br>";
    echo "</div>";

    // Test different connection configurations
    $configs = [
        ['localhost', 'root', '', '3306'],
        ['127.0.0.1', 'root', '', '3306'],
        ['localhost', 'root', 'root', '3306'],
        ['127.0.0.1', 'root', 'root', '3306'],
        ['localhost:3307', 'root', '', '3307'], // Common XAMPP port
        ['127.0.0.1:3307', 'root', 'root', '3307'],
    ];

    $success = false;

    foreach ($configs as $config) {
        list($host, $user, $pass, $port) = $config;
        
        echo "<h3>Testing: host=$host, user=$user, port=$port</h3>";
        
        try {
            $pdo = new PDO("mysql:host=$host", $user, $pass);
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            
            echo "<p class='success'>✓ Connected to MySQL server successfully!</p>";
            
            // Check if database exists
            $result = $pdo->query("SHOW DATABASES LIKE 'safezone_poc'");
            if ($result->rowCount() > 0) {
                echo "<p class='success'>✓ Database 'safezone_poc' exists</p>";
                
                $pdo->exec("USE safezone_poc");
                $tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
                
                if (count($tables) > 0) {
                    echo "<p class='success'>✓ Tables found: " . implode(', ', $tables) . "</p>";
                } else {
                    echo "<p class='error'>✗ No tables found. Run schema.sql</p>";
                }
            } else {
                echo "<p class='error'>✗ Database 'safezone_poc' does not exist</p>";
            }
            
            // Generate working api.php content
            echo "<div class='info'>";
            echo "<h4>Working Configuration Found!</h4>";
            echo "Update your api.php with these settings:<br><br>";
            echo "<code style='background:white;padding:10px;display:block;'>";
            echo "&lt;?php<br>";
            echo "define('DB_HOST', '$host');<br>";
            echo "define('DB_USER', '$user');<br>";
            echo "define('DB_PASS', '$pass');<br>";
            echo "define('DB_NAME', 'safezone_poc');<br>";
            echo "?&gt;";
            echo "</code>";
            echo "</div>";
            
            $success = true;
            break;
            
        } catch (PDOException $e) {
            echo "<p class='error'>✗ Connection failed: " . $e->getMessage() . "</p>";
        }
    }

    if (!$success) {
        echo "<div class='error'>";
        echo "<h2>❌ No working configuration found</h2>";
        echo "<p><strong>Solutions:</strong></p>";
        echo "<ol>";
        echo "<li><strong>Start MySQL Service:</strong>";
        echo "<ul>";
        echo "<li><strong>XAMPP:</strong> Start MySQL from XAMPP Control Panel</li>";
        echo "<li><strong>WAMP:</strong> Click WAMP icon → MySQL → Start</li>";
        echo "<li><strong>MAMP:</strong> Start servers from MAMP application</li>";
        echo "<li><strong>Windows:</strong> Services → Start MySQL</li>";
        echo "<li><strong>Mac:</strong> System Preferences → MySQL → Start</li>";
        echo "</ul>";
        echo "</li>";
        echo "<li><strong>Install MySQL if missing:</strong>";
        echo "<ul>";
        echo "<li>Download XAMPP: https://www.apachefriends.org/</li>";
        echo "<li>Download WAMP: http://www.wampserver.com/</li>";
        echo "<li>Download MAMP: https://www.mamp.info/</li>";
        echo "</ul>";
        echo "</li>";
        echo "</ol>";
        echo "</div>";
    }
    ?>

    <hr>
    <h2>Quick Setup Instructions</h2>
    
    <h3>Option 1: Using XAMPP (Recommended for Windows)</h3>
    <ol>
        <li>Download and install XAMPP from https://www.apachefriends.org/</li>
        <li>Start XAMPP Control Panel</li>
        <li>Start Apache and MySQL</li>
        <li>Open http://localhost/phpmyadmin</li>
        <li>Create database 'safezone_poc'</li>
        <li>Import schema.sql</li>
    </ol>

    <h3>Option 2: Temporary Solution - Use SQLite (No MySQL needed)</h3>
    <p>Replace your api.php with this SQLite version that doesn't require MySQL:</p>
    <a href="?sqlite=1">Click here for SQLite version</a>

    <?php
    if (isset($_GET['sqlite'])) {
        echo "<div class='info'>";
        echo "<h3>SQLite Version of api.php</h3>";
        echo "<p>Replace your api.php with this code:</p>";
        echo "<textarea style='width:100%;height:400px;font-family:monospace;'>";
        echo htmlspecialchars(getSQLiteVersion());
        echo "</textarea>";
        echo "</div>";
    }
    ?>

</body>
</html>

<?php
function getSQLiteVersion() {
    return '<?php
/**
 * SafeZone Emergency Alert System API - SQLite Version
 * No MySQL required!
 */

header(\'Content-Type: application/json\');
header(\'Access-Control-Allow-Origin: *\');
header(\'Access-Control-Allow-Methods: GET, POST\');
header(\'Access-Control-Allow-Headers: Content-Type\');

// SQLite database file
define(\'DB_FILE\', \'safezone.db\');

// File upload configuration
define(\'UPLOAD_DIR\', \'uploads/\');
define(\'MAX_FILE_SIZE\', 10 * 1024 * 1024);

// Create uploads directory if needed
if (!file_exists(UPLOAD_DIR)) {
    mkdir(UPLOAD_DIR, 0777, true);
}

// Initialize SQLite database
function initDatabase() {
    if (!file_exists(DB_FILE)) {
        $pdo = new PDO("sqlite:" . DB_FILE);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        
        // Create tables
        $pdo->exec("
            CREATE TABLE users (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                full_name TEXT NOT NULL,
                phone_number TEXT NOT NULL,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP
            )
        ");
        
        $pdo->exec("
            CREATE TABLE incidents (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id INTEGER NOT NULL,
                timestamp DATETIME DEFAULT CURRENT_TIMESTAMP,
                gps_lat REAL NOT NULL,
                gps_lng REAL NOT NULL,
                status TEXT DEFAULT \'active\',
                latest_message TEXT,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (user_id) REFERENCES users(id)
            )
        ");
        
        $pdo->exec("
            CREATE TABLE messages (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                incident_id INTEGER NOT NULL,
                sender TEXT NOT NULL,
                message_text TEXT,
                media_path TEXT,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (incident_id) REFERENCES incidents(id)
            )
        ");
        
        // Sample data
        $pdo->exec("INSERT INTO users (full_name, phone_number) VALUES (\'John Smith\', \'+1234567890\')");
        $pdo->exec("INSERT INTO users (full_name, phone_number) VALUES (\'Sarah Johnson\', \'+1987654321\')");
        
        return $pdo;
    }
    return new PDO("sqlite:" . DB_FILE);
}

$action = $_GET[\'action\'] ?? \'\';

try {
    $db = initDatabase();
    
    switch ($action) {
        case \'alert_trigger\':
            $fullName = $_POST[\'full_name\'] ?? \'\';
            $phoneNumber = $_POST[\'phone_number\'] ?? \'\';
            $gpsLat = $_POST[\'gps_lat\'] ?? 37.7749;
            $gpsLng = $_POST[\'gps_lng\'] ?? -122.4194;
            
            if (empty($fullName) || empty($phoneNumber)) {
                sendResponse(false, \'Missing required fields\');
            }
            
            // Find or create user
            $stmt = $db->prepare("SELECT id FROM users WHERE phone_number = ?");
            $stmt->execute([$phoneNumber]);
            $user = $stmt->fetch();
            
            if (!$user) {
                $stmt = $db->prepare("INSERT INTO users (full_name, phone_number) VALUES (?, ?)");
                $stmt->execute([$fullName, $phoneNumber]);
                $userId = $db->lastInsertId();
            } else {
                $userId = $user[\'id\'];
            }
            
            // Create incident
            $stmt = $db->prepare("INSERT INTO incidents (user_id, gps_lat, gps_lng, latest_message) VALUES (?, ?, ?, ?)");
            $stmt->execute([$userId, $gpsLat, $gpsLng, \"Emergency alert by {$fullName}\"]);
            $incidentId = $db->lastInsertId();
            
            // Initial message
            $stmt = $db->prepare("INSERT INTO messages (incident_id, sender, message_text) VALUES (?, \'user\', ?)");
            $stmt->execute([$incidentId, \"EMERGENCY ALERT: I need help!\"]);
            
            sendResponse(true, [\'incident_id\' => $incidentId]);
            break;
            
        case \'get_alerts\':
            $stmt = $db->prepare("
                SELECT i.*, u.full_name as user_name 
                FROM incidents i 
                JOIN users u ON i.user_id = u.id 
                WHERE i.status = \'active\' 
                ORDER BY i.created_at DESC
            ");
            $stmt->execute();
            $alerts = $stmt->fetchAll(PDO::FETCH_ASSOC);
            sendResponse(true, [\'alerts\' => $alerts]);
            break;
            
        case \'get_chat\':
            $incidentId = $_GET[\'incident_id\'] ?? 0;
            $stmt = $db->prepare("SELECT * FROM messages WHERE incident_id = ? ORDER BY created_at ASC");
            $stmt->execute([$incidentId]);
            $messages = $stmt->fetchAll(PDO::FETCH_ASSOC);
            sendResponse(true, [\'messages\' => $messages]);
            break;
            
        case \'send_message\':
            $incidentId = $_POST[\'incident_id\'] ?? 0;
            $sender = $_POST[\'sender\'] ?? \'\';
            $messageText = $_POST[\'message_text\'] ?? \'\';
            
            if (!$incidentId || !in_array($sender, [\'user\', \'dispatcher\'])) {
                sendResponse(false, \'Invalid parameters\');
            }
            
            $mediaPath = null;
            if (isset($_FILES[\'media\']) && $_FILES[\'media\'][\'error\'] === UPLOAD_ERR_OK) {
                $file = $_FILES[\'media\'];
                $newFileName = uniqid() . \'.\' . pathinfo($file[\'name\'], PATHINFO_EXTENSION);
                $fileDestination = UPLOAD_DIR . $newFileName;
                if (move_uploaded_file($file[\'tmp_name\'], $fileDestination)) {
                    $mediaPath = $fileDestination;
                }
            }
            
            $stmt = $db->prepare("INSERT INTO messages (incident_id, sender, message_text, media_path) VALUES (?, ?, ?, ?)");
            $stmt->execute([$incidentId, $sender, $messageText, $mediaPath]);
            
            sendResponse(true, [\'message_id\' => $db->lastInsertId()]);
            break;
            
        case \'test_connection\':
            sendResponse(true, [\'message\' => \'SQLite database working!\', \'database_file\' => DB_FILE]);
            break;
            
        default:
            sendResponse(false, \'Invalid action\');
    }
} catch (Exception $e) {
    sendResponse(false, \'Error: \' . $e->getMessage());
}

function sendResponse($success, $data) {
    echo json_encode([
        \'success\' => $success,
        \'timestamp\' => date(\'Y-m-d H:i:s\'),
        \'data\' => $success ? $data : null,
        \'error\' => !$success ? $data : null
    ]);
    exit;
}
?>';
}
?>