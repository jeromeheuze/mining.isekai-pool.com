<?php
/**
 * Quick Health Check
 * Fast check of critical system components
 */

// Set error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Include required files
require_once __DIR__ . '/../src/Config/ConfigManager.php';
require_once __DIR__ . '/../src/Database/Database.php';
require_once __DIR__ . '/../src/Classes/YentenRPC.php';

use YentenPool\Config\ConfigManager;
use YentenPool\Database\Database;
use YentenPool\Classes\YentenRPC;

try {
    // Load configuration
    $configManager = new ConfigManager(__DIR__ . '/../config/config.json');
    $config = $configManager->getAll();
    
    // Initialize database
    $database = new Database($config['database']);
    $pdo = $database->getConnection();
    
    // Initialize Yenten RPC
    $yentenRPC = new YentenRPC(
        $config['yenten']['host'],
        $config['yenten']['port'],
        $config['yenten']['username'],
        $config['yenten']['password']
    );
    
    echo "=== QUICK HEALTH CHECK ===\n";
    echo "Time: " . date('Y-m-d H:i:s') . "\n\n";
    
    // Check database
    $stmt = $pdo->query("SELECT 1");
    echo "✅ Database: Connected\n";
    
    // Check Yenten daemon
    $blockchainInfo = $yentenRPC->getBlockchainInfo();
    $isSynced = $blockchainInfo['verificationprogress'] > 0.99;
    echo $isSynced ? "✅ Yenten: Synced (Block {$blockchainInfo['blocks']})\n" : "⚠️  Yenten: Not fully synced\n";
    
    // Check pool balance
    $balance = $yentenRPC->getBalance();
    echo $balance > 0 ? "✅ Pool Balance: {$balance} YTN\n" : "⚠️  Pool Balance: 0 YTN\n";
    
    // Check stratum server
    $output = shell_exec('ps aux | grep stratum_server.php | grep -v grep');
    echo $output ? "✅ Stratum Server: Running\n" : "❌ Stratum Server: Not running\n";
    
    // Check active miners
    $activeMiners = $pdo->query("
        SELECT COUNT(DISTINCT user_id) as count 
        FROM shares 
        WHERE submitted_at >= DATE_SUB(NOW(), INTERVAL 1 HOUR)
    ")->fetchColumn();
    
    echo "📊 Active Miners (1h): {$activeMiners}\n";
    
    // Check pending payouts
    $pendingPayouts = $pdo->query("
        SELECT COUNT(*) as count, SUM(amount) as total
        FROM payouts 
        WHERE status = 'pending'
    ")->fetch();
    
    echo "💰 Pending Payouts: {$pendingPayouts['count']} ({$pendingPayouts['total']} YTN)\n";
    
    // Check recent shares
    $recentShares = $pdo->query("
        SELECT COUNT(*) as count
        FROM shares 
        WHERE submitted_at >= DATE_SUB(NOW(), INTERVAL 10 MINUTES)
    ")->fetchColumn();
    
    echo "⛏️  Recent Shares (10m): {$recentShares}\n";
    
    echo "\n=== STATUS ===\n";
    
    if ($isSynced && $output && $activeMiners > 0) {
        echo "🟢 SYSTEM HEALTHY - Pool is operational!\n";
    } elseif ($isSynced && $output) {
        echo "🟡 SYSTEM READY - Waiting for miners\n";
    } else {
        echo "🔴 SYSTEM ISSUES - Check the problems above\n";
    }
    
} catch (Exception $e) {
    echo "❌ HEALTH CHECK FAILED: " . $e->getMessage() . "\n";
    exit(1);
}
?>
