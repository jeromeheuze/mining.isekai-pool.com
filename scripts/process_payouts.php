<?php
/**
 * Payout Processing Script
 * Processes pending payouts and distributes block rewards
 */

// Set error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Include required files
require_once __DIR__ . '/../src/Config/ConfigManager.php';
require_once __DIR__ . '/../src/Database/Database.php';
require_once __DIR__ . '/../src/Classes/YentenRPC.php';
require_once __DIR__ . '/../src/Classes/PPLNSCalculator.php';
require_once __DIR__ . '/../src/Classes/PayoutProcessor.php';

use YentenPool\Config\ConfigManager;
use YentenPool\Database\Database;
use YentenPool\Classes\YentenRPC;
use YentenPool\Classes\PPLNSCalculator;
use YentenPool\Classes\PayoutProcessor;

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
    
    // Initialize payout system
    $pplnsCalculator = new PPLNSCalculator($pdo, $config);
    $payoutProcessor = new PayoutProcessor($pdo, $yentenRPC, $config);
    
    echo "=== Yenten Pool Payout Processor ===\n";
    echo "Started at: " . date('Y-m-d H:i:s') . "\n\n";
    
    // Check pool balance
    $poolBalance = $payoutProcessor->getPoolBalance();
    echo "Pool Balance: {$poolBalance} YTN\n";
    
    if ($poolBalance < 1.0) {
        echo "WARNING: Low pool balance! Consider adding funds.\n";
    }
    
    // Process pending payouts
    echo "\n--- Processing Pending Payouts ---\n";
    $payoutResult = $payoutProcessor->processPendingPayouts();
    
    echo "Processed: {$payoutResult['processed']} payouts\n";
    echo "Failed: {$payoutResult['failed']} payouts\n";
    echo "Total Amount: {$payoutResult['total_amount']} YTN\n";
    
    // Get payout statistics
    $stats = $payoutProcessor->getPayoutStats();
    echo "\n--- Payout Statistics ---\n";
    echo "Total Payouts: {$stats['total_payouts']}\n";
    echo "Total Paid: {$stats['total_paid']} YTN\n";
    echo "Pending: {$stats['pending_amount']} YTN\n";
    echo "Processing: {$stats['processing_amount']} YTN\n";
    echo "Failed: {$stats['failed_amount']} YTN\n";
    
    // Check for eligible payouts
    $eligiblePayouts = $pplnsCalculator->getEligiblePayouts();
    echo "\n--- Eligible Payouts ---\n";
    echo "Users eligible for payout: " . count($eligiblePayouts) . "\n";
    
    if (!empty($eligiblePayouts)) {
        echo "Top eligible users:\n";
        foreach (array_slice($eligiblePayouts, 0, 5) as $user) {
            echo "  - {$user['address']}: {$user['pending_balance']} YTN\n";
        }
    }
    
    // Check for recent blocks that need reward distribution
    echo "\n--- Block Reward Distribution ---\n";
    $recentBlocks = $pdo->query("
        SELECT COUNT(*) as count 
        FROM blocks 
        WHERE created_at >= DATE_SUB(NOW(), INTERVAL 1 HOUR)
    ")->fetch();
    
    echo "Recent blocks (last hour): {$recentBlocks['count']}\n";
    
    if ($recentBlocks['count'] > 0) {
        echo "NOTE: Block reward distribution should be handled by the stratum server\n";
        echo "when blocks are found. This script only processes payouts.\n";
    }
    
    echo "\n=== Payout Processing Complete ===\n";
    echo "Finished at: " . date('Y-m-d H:i:s') . "\n";
    
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
    exit(1);
}
?>
