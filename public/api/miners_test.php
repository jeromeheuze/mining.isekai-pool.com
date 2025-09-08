<?php
/**
 * Simple Miners API Test
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

try {
    // Simple database connection
    $pdo = new PDO('mysql:host=localhost;port=3306;dbname=yenten_pool', 'pool_user', 'D|Hm3"K12<Zv');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Simple query to test
    $stmt = $pdo->query("SELECT COUNT(*) as total_shares FROM shares");
    $result = $stmt->fetch();
    
    echo json_encode([
        'success' => true,
        'test' => 'Database connection working',
        'total_shares' => $result['total_shares'],
        'timestamp' => time()
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>
