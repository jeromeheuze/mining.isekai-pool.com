<?php
/**
 * Test RPC Connections
 * Tests connectivity to all configured daemons using PHP
 */

require_once __DIR__ . '/../src/Config/ConfigManager.php';
require_once __DIR__ . '/../src/Classes/YentenRPC.php';
require_once __DIR__ . '/../src/Classes/KotoRPC.php';
require_once __DIR__ . '/../src/Classes/UkkeyCoinRPC.php';

use YentenPool\Config\ConfigManager;
use YentenPool\Classes\YentenRPC;
use YentenPool\Classes\KotoRPC;
use YentenPool\Classes\UkkeyCoinRPC;

echo "Testing RPC Connections...\n";
echo "==========================\n\n";

try {
    $config = ConfigManager::getInstance();
    
    // Test Yenten
    echo "Testing Yenten daemon...\n";
    $yentenRPC = new YentenRPC();
    
    if ($yentenRPC->testConnection()) {
        echo "✓ Yenten RPC connection successful\n";
        
        $blockchainInfo = $yentenRPC->getBlockchainInfo();
        $height = $blockchainInfo['blocks'] ?? 'unknown';
        $synced = !($blockchainInfo['initialblockdownload'] ?? true);
        $chain = $blockchainInfo['chain'] ?? 'unknown';
        
        echo "  Chain: $chain\n";
        echo "  Block height: $height\n";
        echo "  Synced: " . ($synced ? "Yes" : "No") . "\n";
        
        if (!$synced) {
            $progress = $blockchainInfo['verificationprogress'] ?? 0;
            echo "  Progress: " . round($progress * 100, 2) . "%\n";
        }
    } else {
        echo "✗ Yenten RPC connection failed\n";
    }
    
    echo "\n";
    
    // Test Koto
    echo "Testing Koto daemon...\n";
    $kotoRPC = new KotoRPC();
    
    if ($kotoRPC->testConnection()) {
        echo "✓ Koto RPC connection successful\n";
        
        $blockchainInfo = $kotoRPC->getBlockchainInfo();
        $height = $blockchainInfo['blocks'] ?? 'unknown';
        $synced = !($blockchainInfo['initialblockdownload'] ?? true);
        $chain = $blockchainInfo['chain'] ?? 'unknown';
        
        echo "  Chain: $chain\n";
        echo "  Block height: $height\n";
        echo "  Synced: " . ($synced ? "Yes" : "No") . "\n";
        
        if (!$synced) {
            $progress = $blockchainInfo['verificationprogress'] ?? 0;
            echo "  Progress: " . round($progress * 100, 2) . "%\n";
        }
    } else {
        echo "✗ Koto RPC connection failed\n";
    }
    
    echo "\n";
    
    // Test UkkeyCoin
    echo "Testing UkkeyCoin daemon...\n";
    $ukkeyRPC = new UkkeyCoinRPC();
    
    if ($ukkeyRPC->testConnection()) {
        echo "✓ UkkeyCoin RPC connection successful\n";
        
        $blockchainInfo = $ukkeyRPC->getBlockchainInfo();
        $height = $blockchainInfo['blocks'] ?? 'unknown';
        $synced = !($blockchainInfo['initialblockdownload'] ?? true);
        $chain = $blockchainInfo['chain'] ?? 'unknown';
        
        echo "  Chain: $chain\n";
        echo "  Block height: $height\n";
        echo "  Synced: " . ($synced ? "Yes" : "No") . "\n";
        
        if (!$synced) {
            $progress = $blockchainInfo['verificationprogress'] ?? 0;
            echo "  Progress: " . round($progress * 100, 2) . "%\n";
        }
    } else {
        echo "✗ UkkeyCoin RPC connection failed\n";
    }
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
}

echo "\n==========================\n";
echo "RPC connection test complete!\n";
?>
