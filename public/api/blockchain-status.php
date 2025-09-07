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
    // Get coin configuration
    $coinConfig = [];
    if ($coin === 'yenten') {
        $coinConfig = [
            'host' => $config['yenten']['daemon_host'] ?? 'localhost',
            'port' => $config['yenten']['daemon_port'] ?? 9982,
            'user' => $config['yenten']['daemon_user'] ?? 'yenten_rpc_user',
            'password' => $config['yenten']['daemon_password'] ?? '4rlcawahlfrovIchEtrlcre0huWakephl0'
        ];
    } else if ($coin === 'koto') {
        $coinConfig = [
            'host' => $config['koto']['daemon_host'] ?? 'localhost',
            'port' => $config['koto']['daemon_port'] ?? 9983,
            'user' => $config['koto']['daemon_user'] ?? 'koto_rpc_user',
            'password' => $config['koto']['daemon_password'] ?? 'koto_rpc_password'
        ];
    } else if ($coin === 'ukkeycoin') {
        $coinConfig = [
            'host' => $config['ukkeycoin']['daemon_host'] ?? 'localhost',
            'port' => $config['ukkeycoin']['daemon_port'] ?? 9985,
            'user' => $config['ukkeycoin']['daemon_user'] ?? 'uky_rpc_user',
            'password' => $config['ukkeycoin']['daemon_password'] ?? 'uky_rpc_password'
        ];
    }

    // Make RPC call
    $rpcUrl = "http://{$coinConfig['host']}:{$coinConfig['port']}";
    $rpcData = [
        'jsonrpc' => '1.0',
        'id' => 'status_check',
        'method' => 'getblockchaininfo',
        'params' => []
    ];

    $context = stream_context_create([
        'http' => [
            'method' => 'POST',
            'header' => [
                'Content-Type: application/json',
                'Authorization: Basic ' . base64_encode($coinConfig['user'] . ':' . $coinConfig['password'])
            ],
            'content' => json_encode($rpcData),
            'timeout' => 10
        ]
    ]);

    $response = file_get_contents($rpcUrl, false, $context);
    
    if ($response === false) {
        throw new Exception('Failed to connect to ' . $coin . ' daemon');
    }

    $data = json_decode($response, true);
    
    if (isset($data['error'])) {
        throw new Exception($data['error']['message'] ?? 'RPC error');
    }

    if (!isset($data['result'])) {
        throw new Exception('Invalid response from daemon');
    }

    $result = $data['result'];
    
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
