<?php
/**
 * Database connection test script
 * Run this first to test your database connection
 */

header('Content-Type: text/plain; charset=utf-8');

echo "SafeZone POC - Database Connection Test\n";
echo "========================================\n\n";

// Database configuration - UPDATE THESE
$configs = [
    ['localhost', 'root', ''],      // Typical WAMP
    ['localhost', 'root', 'root'],  // Typical XAMPP/MAMP
    ['127.0.0.1', 'root', ''],      // Alternative localhost
];

$success = false;

foreach ($configs as $config) {
    list($host, $user, $pass) = $config;
    
    echo "Testing: host=$host, user=$user\n";
    
    try {
        // Test connection
        $pdo = new PDO("mysql:host=$host", $user, $pass);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        
        echo "✓ Connected to MySQL server successfully\n";
        
        // Test database
        $result = $pdo->query("SHOW DATABASES LIKE 'safezone_poc'");
        if ($result->rowCount() > 0) {
            echo "✓ Database 'safezone_poc' exists\n";
            
            // Test tables
            $pdo->exec("USE safezone_poc");
            $tables = ['users', 'incidents', 'messages'];
            foreach ($tables as $table) {
                $result = $pdo->query("SHOW TABLES LIKE '$table'");
                if ($result->rowCount() > 0) {
                    echo "✓ Table '$table' exists\n";
                } else {
                    echo "✗ Table '$table' missing\n";
                }
            }
        } else {
            echo "✗ Database 'safezone_poc' does not exist. Run schema.sql first.\n";
        }
        
        $success = true;
        break;
        
    } catch (PDOException $e) {
        echo "✗ Connection failed: " . $e->getMessage() . "\n\n";
    }
}

if (!$success) {
    echo "\n❌ All connection attempts failed.\n";
    echo "Please check:\n";
    echo "1. Is MySQL server running?\n";
    echo "2. Are the credentials correct?\n";
    echo "3. Update the DB_HOST, DB_USER, DB_PASS in api.php\n";
} else {
    echo "\n✅ Database setup looks good!\n";
    echo "You can now test the application.\n";
}

echo "\nAPI Test: " . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'] . "\n";
?>