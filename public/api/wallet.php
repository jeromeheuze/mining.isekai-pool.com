<?php
/**
 * Wallet API
 * Returns wallet information and statistics
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

try {
    // Database connection
    $pdo = new PDO('mysql:host=localhost;port=3306;dbname=yenten_pool', 'pool_user', 'D|Hm3"K12<Zv');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Get wallet address parameter
    $walletAddress = $_GET['address'] ?? '';
    
    if (empty($walletAddress)) {
        echo json_encode([
            'success' => false,
            'error' => 'Wallet address is required'
        ]);
        exit;
    }
    
    // Get user information
    $stmt = $pdo->prepare("SELECT * FROM users WHERE address = ?");
    $stmt->execute([$walletAddress]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$user) {
        echo json_encode([
            'success' => false,
            'error' => 'Wallet address not found'
        ]);
        exit;
    }
    
    // Get mining statistics
    $stmt = $pdo->prepare("
        SELECT 
            COUNT(s.id) as total_shares,
            MAX(s.submitted_at) as last_share,
            COUNT(DISTINCT s.worker_id) as active_workers
        FROM shares s
        WHERE s.user_id = ? AND s.submitted_at > DATE_SUB(NOW(), INTERVAL 24 HOUR)
    ");
    $stmt->execute([$user['id']]);
    $miningStats = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Get payout statistics
    $stmt = $pdo->prepare("
        SELECT 
            SUM(CASE WHEN status = 'completed' THEN amount ELSE 0 END) as total_paid,
            COUNT(CASE WHEN status = 'completed' THEN 1 END) as completed_payouts,
            COUNT(CASE WHEN status = 'pending' THEN 1 END) as pending_payouts
        FROM payouts 
        WHERE user_id = ?
    ");
    $stmt->execute([$user['id']]);
    $payoutStats = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Calculate total earnings
    $totalEarnings = $user['pending_balance'] + $user['paid_balance'];
    
    echo json_encode([
        'success' => true,
        'timestamp' => time(),
        'wallet_address' => $walletAddress,
        'pending_balance' => (float)$user['pending_balance'],
        'paid_balance' => (float)$user['paid_balance'],
        'total_earnings' => $totalEarnings,
        'total_shares' => (int)($miningStats['total_shares'] ?? 0),
        'active_workers' => (int)($miningStats['active_workers'] ?? 0),
        'last_share' => $miningStats['last_share'],
        'total_paid' => (float)($payoutStats['total_paid'] ?? 0),
        'completed_payouts' => (int)($payoutStats['completed_payouts'] ?? 0),
        'pending_payouts' => (int)($payoutStats['pending_payouts'] ?? 0),
        'user_info' => [
            'id' => (int)$user['id'],
            'username' => $user['username'],
            'created_at' => $user['created_at'],
            'last_seen' => $user['last_seen']
        ]
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>
