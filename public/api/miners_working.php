<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

try {
    $pdo = new PDO('mysql:host=localhost;port=3306;dbname=yenten_pool', 'pool_user', 'D|Hm3"K12<Zv');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    $stmt = $pdo->query("
        SELECT 
            u.id as user_id,
            u.address as username,
            COUNT(s.id) as share_count,
            MAX(s.submitted_at) as last_share,
            s.worker_id,
            CASE 
                WHEN s.worker_id = 1 THEN CONCAT(u.address, ' (Main)')
                WHEN s.worker_id = 2 THEN CONCAT(u.address, ' (HiveOS)')
                ELSE CONCAT(u.address, ' (Worker ', s.worker_id, ')')
            END as worker_name
        FROM users u
        INNER JOIN shares s ON u.id = s.user_id
        WHERE s.submitted_at > DATE_SUB(NOW(), INTERVAL 24 HOUR)
        GROUP BY u.id, s.worker_id
        HAVING share_count > 0
        ORDER BY share_count DESC
        LIMIT 50
    ");
    
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $miners = [];
    
    foreach ($results as $row) {
        $miners[] = [
            'coin' => 'yenten',
            'worker_name' => $row['worker_name'],
            'user_id' => (int)$row['user_id'],
            'username' => $row['username'],
            'share_count' => (int)$row['share_count'],
            'estimated_hashrate' => (int)$row['share_count'] * 0.1,
            'last_share' => $row['last_share'],
            'shares_last_24h' => (int)$row['share_count']
        ];
    }
    
    echo json_encode([
        'success' => true,
        'timestamp' => time(),
        'miners' => $miners
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>
