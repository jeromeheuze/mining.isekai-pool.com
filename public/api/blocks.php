<?php
/**
 * Blocks API
 * Returns information about found blocks
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

// Get parameters
$coin = $_GET['coin'] ?? 'all';
$limit = (int)($_GET['limit'] ?? 20);
$limit = min($limit, 100); // Max 100 results

try {
    $blocks = [
        'success' => true,
        'timestamp' => time(),
        'blocks' => []
    ];

    $supportedCoins = ['yenten', 'koto', 'ukkeycoin'];
    
    if ($coin !== 'all' && in_array($coin, $supportedCoins)) {
        $supportedCoins = [$coin];
    }

    foreach ($supportedCoins as $coinName) {
        // Get recent blocks for this coin
        $stmt = $pdo->prepare("
            SELECT 
                b.*,
                w.worker_name,
                u.username,
                COUNT(s.id) as share_count
            FROM blocks b
            LEFT JOIN workers w ON b.worker_id = w.id
            LEFT JOIN users u ON w.user_id = u.id
            LEFT JOIN shares s ON b.id = s.block_id
            WHERE b.coin = ?
            GROUP BY b.id
            ORDER BY b.created_at DESC
            LIMIT ?
        ");
        $stmt->execute([$coinName, $limit]);
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($results as $row) {
            // Calculate time since block was found
            $blockTime = new DateTime($row['created_at']);
            $now = new DateTime();
            $timeDiff = $now->diff($blockTime);
            
            $timeAgo = '';
            if ($timeDiff->d > 0) {
                $timeAgo = $timeDiff->d . ' day' . ($timeDiff->d > 1 ? 's' : '') . ' ago';
            } elseif ($timeDiff->h > 0) {
                $timeAgo = $timeDiff->h . ' hour' . ($timeDiff->h > 1 ? 's' : '') . ' ago';
            } elseif ($timeDiff->i > 0) {
                $timeAgo = $timeDiff->i . ' minute' . ($timeDiff->i > 1 ? 's' : '') . ' ago';
            } else {
                $timeAgo = 'Just now';
            }

            $blocks['blocks'][] = [
                'id' => (int)$row['id'],
                'coin' => $coinName,
                'block_hash' => $row['block_hash'],
                'block_height' => (int)$row['block_height'],
                'difficulty' => (float)$row['difficulty'],
                'reward' => (float)$row['reward'],
                'status' => $row['status'],
                'created_at' => $row['created_at'],
                'time_ago' => $timeAgo,
                'worker_name' => $row['worker_name'],
                'username' => $row['username'] ?? 'Unknown',
                'share_count' => (int)$row['share_count'],
                'confirmed_at' => $row['confirmed_at']
            ];
        }
    }

    // Sort by creation time (newest first)
    usort($blocks['blocks'], function($a, $b) {
        return strtotime($b['created_at']) - strtotime($a['created_at']);
    });

    // Limit total results
    $blocks['blocks'] = array_slice($blocks['blocks'], 0, $limit);

    // Add summary statistics
    $blocks['summary'] = [
        'total_blocks' => count($blocks['blocks']),
        'confirmed_blocks' => count(array_filter($blocks['blocks'], function($b) { return $b['status'] === 'confirmed'; })),
        'pending_blocks' => count(array_filter($blocks['blocks'], function($b) { return $b['status'] === 'pending'; })),
        'total_reward' => array_sum(array_column($blocks['blocks'], 'reward'))
    ];

    echo json_encode($blocks);

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>
