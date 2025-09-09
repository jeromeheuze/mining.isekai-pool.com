<?php
/**
 * Yenten Mining Pool Stratum Server
 * Main entry point for the stratum server
 */

// Set error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Set timezone
date_default_timezone_set('UTC');

// Include autoloader and dependencies
require_once __DIR__ . '/../src/Config/ConfigManager.php';
require_once __DIR__ . '/../src/Database/Database.php';
require_once __DIR__ . '/../src/Classes/YentenRPC.php';
require_once __DIR__ . '/../src/Classes/KotoRPC.php';
require_once __DIR__ . '/../src/Classes/UkkeyCoinRPC.php';
require_once __DIR__ . '/../src/Classes/RinCoinRPC.php';
require_once __DIR__ . '/../src/Classes/StratumServer.php';

use YentenPool\Config\ConfigManager;
use YentenPool\Database\Database;
use YentenPool\Classes\StratumServer;

// Handle signals for graceful shutdown
$shutdown = false;
pcntl_signal(SIGTERM, function() use (&$shutdown) {
    $shutdown = true;
    echo "Received SIGTERM, shutting down gracefully...\n";
});

pcntl_signal(SIGINT, function() use (&$shutdown) {
    $shutdown = true;
    echo "Received SIGINT, shutting down gracefully...\n";
});

try {
    echo "Starting Yenten Mining Pool Stratum Server...\n";
    echo "Time: " . date('Y-m-d H:i:s') . "\n";
    echo "PID: " . getmypid() . "\n";
    
    // Initialize configuration
    $config = ConfigManager::getInstance();
    echo "Configuration loaded successfully\n";
    
    // Test database connection
    $db = Database::getInstance();
    if ($db->testConnection()) {
        echo "Database connection successful\n";
    } else {
        throw new Exception("Database connection failed");
    }
    
    // Create and start stratum server
    $stratumServer = new StratumServer();
    
    // Set up signal handling
    if (function_exists('pcntl_async_signals')) {
        pcntl_async_signals(true);
    }
    
    echo "Stratum server initialized successfully\n";
    echo "Starting server loop...\n";
    
    // Start the server (this will block)
    $stratumServer->start();
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
    exit(1);
}

echo "Stratum server stopped\n";
