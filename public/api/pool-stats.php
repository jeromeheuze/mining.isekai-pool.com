<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

try {
    // Direct database connection
    $pdo = new PDO('mysql:host=localhost;port=3306;dbname=yenten_pool', 'pool_user', 'D|Hm3"K12<Zv');
    
    // Get latest pool stats from pool_stats table
    $stmt = $pdo->query("SELECT * FROM pool_stats ORDER BY timestamp DESC LIMIT 1");
    $stats = $stmt->fetch();
    
    if ($stats) {
        $response = [
            'success' => true,
            'total' => [
                'pool_hashrate' => (int)$stats['total_hashrate'],
                'active_miners' => (int)$stats['active_miners'],
                'blocks_found' => (int)$stats['total_blocks_found']
            ],
            'coins' => [
                'yenten' => [
                    'pool_hashrate' => (int)$stats['total_hashrate'],
                    'active_miners' => (int)$stats['active_miners'],
                    'blocks_found' => (int)$stats['total_blocks_found'],
                    'block_height' => 2019455,
                    'network_difficulty' => (float)$stats['network_difficulty'],
                    'network_hashrate' => (int)$stats['network_hashrate'],
                    'last_block_time' => null
                ],
                'koto' => [
                    'pool_hashrate' => 0,
                    'active_miners' => 0,
                    'blocks_found' => 0,
                    'block_height' => 0,
                    'network_difficulty' => 0,
                    'network_hashrate' => 0,
                    'last_block_time' => null
                ]
            ],
            'timestamp' => time(),
            'last_updated' => $stats['timestamp']
        ];
        
        echo json_encode($response, JSON_PRETTY_PRINT);
    } else {
        echo json_encode([
            'success' => false,
            'error' => 'No pool stats found'
        ]);
    }
    
} catch (PDOException $e) {
    echo json_encode([
        'success' => false,
        'error' => 'Database connection failed: ' . $e->getMessage()
    ]);
}
?>
