<?php
/**
 * Blockchain Status API
 * Returns sync status for Yenten, KOTO and UkkeyCoin daemons
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');
header('Access-Control-Allow-Headers: Content-Type');

// Load configuration
$configFile = __DIR__ . '/../../config/config.json';
$config = [];

if (file_exists($configFile)) {
    $configJson = file_get_contents($configFile);
    $config = json_decode($configJson, true);
}

// Get coin parameter
$coin = $_GET['coin'] ?? '';

if (!in_array($coin, ['yenten', 'koto', 'ukkeycoin'])) {
    echo json_encode([
        'success' => false,
        'error' => 'Invalid coin parameter. Use "yenten", "koto", or "ukkeycoin".'
    ]);
    exit;
}

try {
    // Use the wrapper script to get blockchain info
    $command = "sudo /usr/local/bin/get-blockchain-info $coin";
    $output = shell_exec($command . ' 2>&1');
    
    if (empty($output)) {
        throw new Exception('Failed to connect to ' . $coin . ' daemon');
    }

    $result = json_decode($output, true);
    
    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new Exception('Invalid JSON response from daemon: ' . $output);
    }
    
    // Calculate progress
    $progress = 0;
    $synced = false;
    
    if (isset($result['verificationprogress'])) {
        $progress = (float)$result['verificationprogress'];
        $synced = $progress >= 0.999; // Consider synced at 99.9%
    } else if (isset($result['initial_block_download_complete'])) {
        $synced = $result['initial_block_download_complete'];
        $progress = $synced ? 1.0 : 0.0;
    } else if (isset($result['initialblockdownload'])) {
        $synced = !$result['initialblockdownload'];
        $progress = $synced ? 1.0 : 0.0;
    }

    // Get block height
    $height = $result['blocks'] ?? 0;
    
    // Get estimated height for progress calculation
    $estimatedHeight = $result['estimatedheight'] ?? $height;
    if ($estimatedHeight > 0 && $height > 0) {
        $progress = min($height / $estimatedHeight, 1.0);
    }

    echo json_encode([
        'success' => true,
        'coin' => $coin,
        'height' => (int)$height,
        'estimated_height' => (int)$estimatedHeight,
        'progress' => $progress,
        'synced' => $synced,
        'chain' => $result['chain'] ?? 'unknown',
        'difficulty' => $result['difficulty'] ?? 0,
        'network_hashrate' => $result['networkhashps'] ?? 0
    ]);

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'coin' => $coin
    ]);
}
?>
