<?php
/**
 * Block Reward Distribution Script
 * Distributes rewards when blocks are found
 */

// Set error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Include required files
require_once __DIR__ . '/../src/Config/ConfigManager.php';
require_once __DIR__ . '/../src/Database/Database.php';
require_once __DIR__ . '/../src/Classes/YentenRPC.php';
require_once __DIR__ . '/../src/Classes/PPLNSCalculator.php';

use YentenPool\Config\ConfigManager;
use YentenPool\Database\Database;
use YentenPool\Classes\YentenRPC;
use YentenPool\Classes\PPLNSCalculator;

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
    
    // Initialize PPLNS calculator
    $pplnsCalculator = new PPLNSCalculator($pdo, $config);
    
    echo "=== Block Reward Distribution ===\n";
    echo "Started at: " . date('Y-m-d H:i:s') . "\n\n";
    
    // Get current block height
    $blockchainInfo = $yentenRPC->getBlockchainInfo();
    $currentHeight = $blockchainInfo['blocks'];
    
    echo "Current block height: {$currentHeight}\n";
    
    // Check for new blocks that need reward distribution
    $lastProcessedBlock = $pdo->query("
        SELECT MAX(height) as last_height FROM blocks
    ")->fetch();
    
    $lastHeight = $lastProcessedBlock['last_height'] ?? 0;
    echo "Last processed block: {$lastHeight}\n";
    
    if ($currentHeight > $lastHeight) {
        $newBlocks = $currentHeight - $lastHeight;
        echo "New blocks to process: {$newBlocks}\n";
        
        // Process each new block
        for ($height = $lastHeight + 1; $height <= $currentHeight; $height++) {
            try {
                echo "\nProcessing block {$height}...\n";
                
                // Get block information
                $blockHash = $yentenRPC->getBlockHash($height);
                $blockInfo = $yentenRPC->getBlock($blockHash);
                
                // Calculate block reward (typically 50 YTN for Yenten)
                $blockReward = 50.0; // You might want to get this from block info
                
                // Find who found the block (if any)
                $foundByUserId = null;
                // This would need to be implemented based on your block finding logic
                
                // Distribute rewards
                $result = $pplnsCalculator->distributeBlockReward(
                    $height,
                    $blockHash,
                    $blockReward,
                    $foundByUserId
                );
                
                if ($result['success']) {
                    echo "  ✓ Block {$height} processed successfully\n";
                    echo "  - Total reward: {$result['total_reward']} YTN\n";
                    echo "  - Pool fee: {$result['pool_fee']} YTN\n";
                    echo "  - Distributed: {$result['total_distributed']} YTN\n";
                    echo "  - Miners paid: {$result['miners_paid']}\n";
                } else {
                    echo "  ✗ Failed to process block {$height}\n";
                }
                
            } catch (Exception $e) {
                echo "  ✗ Error processing block {$height}: " . $e->getMessage() . "\n";
            }
        }
    } else {
        echo "No new blocks to process.\n";
    }
    
    // Show current pool statistics
    echo "\n--- Pool Statistics ---\n";
    
    $totalPending = $pdo->query("
        SELECT SUM(pending_balance) as total FROM users
    ")->fetch();
    
    $totalPaid = $pdo->query("
        SELECT SUM(paid_balance) as total FROM users
    ")->fetch();
    
    $activeMiners = $pdo->query("
        SELECT COUNT(DISTINCT user_id) as count 
        FROM shares 
        WHERE submitted_at >= DATE_SUB(NOW(), INTERVAL 1 HOUR)
    ")->fetch();
    
    echo "Total pending balance: {$totalPending['total']} YTN\n";
    echo "Total paid out: {$totalPaid['total']} YTN\n";
    echo "Active miners (last hour): {$activeMiners['count']}\n";
    
    echo "\n=== Block Reward Distribution Complete ===\n";
    echo "Finished at: " . date('Y-m-d H:i:s') . "\n";
    
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
    exit(1);
}
?>
