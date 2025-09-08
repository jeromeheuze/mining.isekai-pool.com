<?php
/**
 * Miners API
 * Returns information about active miners
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
$limit = (int)($_GET['limit'] ?? 50);
$limit = min($limit, 100); // Max 100 results

try {
    $miners = [
        'success' => true,
        'timestamp' => time(),
        'miners' => []
    ];

    $supportedCoins = ['yenten', 'koto'];
    
    if ($coin !== 'all' && in_array($coin, $supportedCoins)) {
        $supportedCoins = [$coin];
    }

    foreach ($supportedCoins as $coinName) {
        // Get top miners for this coin
        $stmt = $pdo->prepare("
            SELECT 
                w.worker_name,
                w.user_id,
                u.username,
                COUNT(s.id) as share_count,
                AVG(s.difficulty) as avg_difficulty,
                MAX(s.created_at) as last_share,
                MIN(s.created_at) as first_share,
                SUM(CASE WHEN s.created_at > DATE_SUB(NOW(), INTERVAL 1 HOUR) THEN 1 ELSE 0 END) as shares_last_hour,
                SUM(CASE WHEN s.created_at > DATE_SUB(NOW(), INTERVAL 24 HOUR) THEN 1 ELSE 0 END) as shares_last_24h
            FROM workers w
            LEFT JOIN users u ON w.user_id = u.id
            LEFT JOIN shares s ON w.id = s.worker_id AND s.coin = ?
            WHERE w.coin = ? AND s.created_at > DATE_SUB(NOW(), INTERVAL 24 HOUR)
            GROUP BY w.id, w.worker_name, w.user_id, u.username
            HAVING share_count > 0
            ORDER BY share_count DESC
            LIMIT ?
        ");
        $stmt->execute([$coinName, $coinName, $limit]);
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($results as $row) {
            // Calculate estimated hashrate (shares per second * average difficulty)
            $sharesPerSecond = $row['shares_last_hour'] / 3600; // 1 hour = 3600 seconds
            $estimatedHashrate = $sharesPerSecond * ($row['avg_difficulty'] ?? 1);

            $miners['miners'][] = [
                'coin' => $coinName,
                'worker_name' => $row['worker_name'],
                'user_id' => (int)$row['user_id'],
                'username' => $row['username'] ?? 'Unknown',
                'share_count' => (int)$row['share_count'],
                'avg_difficulty' => (float)($row['avg_difficulty'] ?? 0),
                'estimated_hashrate' => $estimatedHashrate,
                'last_share' => $row['last_share'],
                'first_share' => $row['first_share'],
                'shares_last_hour' => (int)$row['shares_last_hour'],
                'shares_last_24h' => (int)$row['shares_last_24h']
            ];
        }
    }

    // Sort by estimated hashrate
    usort($miners['miners'], function($a, $b) {
        return $b['estimated_hashrate'] <=> $a['estimated_hashrate'];
    });

    // Limit total results
    $miners['miners'] = array_slice($miners['miners'], 0, $limit);

    echo json_encode($miners);

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>
