<?php
/**
 * Pool Statistics API
 * Returns real-time pool statistics for all supported coins
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

// Database connection
try {
    $dbConfig = $config['database'] ?? [];
    $pdo = new PDO(
        "mysql:host={$dbConfig['host']};dbname={$dbConfig['name']};charset=utf8mb4",
        $dbConfig['user'],
        $dbConfig['password'],
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
} catch (PDOException $e) {
    echo json_encode([
        'success' => false,
        'error' => 'Database connection failed: ' . $e->getMessage()
    ]);
    exit;
}

// Get coin parameter (optional)
$coin = $_GET['coin'] ?? 'all';

try {
    $stats = [
        'success' => true,
        'timestamp' => time(),
        'coins' => []
    ];

    $supportedCoins = ['yenten', 'koto'];
    
    if ($coin !== 'all' && in_array($coin, $supportedCoins)) {
        $supportedCoins = [$coin];
    }

    foreach ($supportedCoins as $coinName) {
        $coinStats = [
            'coin' => $coinName,
            'pool_hashrate' => 0,
            'active_miners' => 0,
            'blocks_found' => 0,
            'network_difficulty' => 0,
            'network_hashrate' => 0,
            'block_height' => 0,
            'last_block_time' => null,
            'pool_fee' => $config['pool']['fee_percent'] ?? 1.0,
            'minimum_payout' => $config['pool']['minimum_payout'] ?? 0.1,
            'payout_threshold' => $config['pool']['payout_threshold'] ?? 0.5
        ];

        // Get active miners count
        $stmt = $pdo->prepare("
            SELECT COUNT(DISTINCT worker_id) as active_miners 
            FROM shares 
            WHERE coin = ? AND created_at > DATE_SUB(NOW(), INTERVAL 10 MINUTE)
        ");
        $stmt->execute([$coinName]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        $coinStats['active_miners'] = (int)($result['active_miners'] ?? 0);

        // Get blocks found
        $stmt = $pdo->prepare("
            SELECT COUNT(*) as blocks_found 
            FROM blocks 
            WHERE coin = ? AND status = 'confirmed'
        ");
        $stmt->execute([$coinName]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        $coinStats['blocks_found'] = (int)($result['blocks_found'] ?? 0);

        // Get last block time
        $stmt = $pdo->prepare("
            SELECT created_at 
            FROM blocks 
            WHERE coin = ? AND status = 'confirmed' 
            ORDER BY created_at DESC 
            LIMIT 1
        ");
        $stmt->execute([$coinName]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        $coinStats['last_block_time'] = $result['created_at'] ?? null;

        // Get pool hashrate (estimated from recent shares)
        $stmt = $pdo->prepare("
            SELECT COUNT(*) as share_count, AVG(difficulty) as avg_difficulty
            FROM shares 
            WHERE coin = ? AND created_at > DATE_SUB(NOW(), INTERVAL 5 MINUTE)
        ");
        $stmt->execute([$coinName]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($result['share_count'] > 0) {
            // Estimate hashrate: shares per second * average difficulty
            $sharesPerSecond = $result['share_count'] / 300; // 5 minutes = 300 seconds
            $coinStats['pool_hashrate'] = $sharesPerSecond * ($result['avg_difficulty'] ?? 1);
        }

        // Get blockchain info from daemon
        $coinConfig = [];
        if ($coinName === 'yenten') {
            $coinConfig = [
                'host' => $config['yenten']['daemon_host'] ?? 'localhost',
                'port' => $config['yenten']['daemon_port'] ?? 9982,
                'user' => $config['yenten']['daemon_user'] ?? 'yenten_rpc_user',
                'password' => $config['yenten']['daemon_password'] ?? '4rlcawahlfrovIchEtrlcre0huWakephl0'
            ];
        } else if ($coinName === 'koto') {
            $coinConfig = [
                'host' => $config['koto']['daemon_host'] ?? 'localhost',
                'port' => $config['koto']['daemon_port'] ?? 9983,
                'user' => $config['koto']['daemon_user'] ?? 'koto_rpc_user',
                'password' => $config['koto']['daemon_password'] ?? 'koto_rpc_password'
            ];
        }

        // Try to get blockchain info
        if (!empty($coinConfig)) {
            try {
                $rpcUrl = "http://{$coinConfig['host']}:{$coinConfig['port']}";
                $rpcData = [
                    'jsonrpc' => '1.0',
                    'id' => 'pool_stats',
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
                        'timeout' => 5
                    ]
                ]);

                $response = file_get_contents($rpcUrl, false, $context);
                
                if ($response !== false) {
                    $data = json_decode($response, true);
                    
                    if (isset($data['result'])) {
                        $result = $data['result'];
                        $coinStats['block_height'] = (int)($result['blocks'] ?? 0);
                        $coinStats['network_difficulty'] = (float)($result['difficulty'] ?? 0);
                        $coinStats['network_hashrate'] = (float)($result['networkhashps'] ?? 0);
                    }
                }
            } catch (Exception $e) {
                // Daemon not available, use default values
            }
        }

        $stats['coins'][$coinName] = $coinStats;
    }

    // Calculate total pool stats
    $stats['total'] = [
        'pool_hashrate' => array_sum(array_column($stats['coins'], 'pool_hashrate')),
        'active_miners' => array_sum(array_column($stats['coins'], 'active_miners')),
        'blocks_found' => array_sum(array_column($stats['coins'], 'blocks_found'))
    ];

    echo json_encode($stats);

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>
