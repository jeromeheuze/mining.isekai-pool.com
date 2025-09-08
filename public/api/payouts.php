<?php
/**
 * Payouts API
 * Returns payout history for a wallet
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

try {
    // Database connection
    $pdo = new PDO('mysql:host=localhost;port=3306;dbname=yenten_pool', 'pool_user', 'D|Hm3"K12<Zv');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Get wallet address parameter
    $walletAddress = $_GET['address'] ?? '';
    $limit = (int)($_GET['limit'] ?? 20);
    $limit = min($limit, 100); // Max 100 results
    
    if (empty($walletAddress)) {
        echo json_encode([
            'success' => false,
            'error' => 'Wallet address is required'
        ]);
        exit;
    }
    
    // Get user ID
    $stmt = $pdo->prepare("SELECT id FROM users WHERE address = ?");
    $stmt->execute([$walletAddress]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$user) {
        echo json_encode([
            'success' => false,
            'error' => 'Wallet address not found'
        ]);
        exit;
    }
    
    // Get payouts
    $stmt = $pdo->prepare("
        SELECT 
            p.*,
            u.address as wallet_address
        FROM payouts p
        LEFT JOIN users u ON p.user_id = u.id
        WHERE p.user_id = ?
        ORDER BY p.created_at DESC
        LIMIT " . intval($limit)
    ");
    $stmt->execute([$user['id']]);
    $payouts = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Format payouts
    $formattedPayouts = [];
    foreach ($payouts as $payout) {
        $formattedPayouts[] = [
            'id' => (int)$payout['id'],
            'amount' => (float)$payout['amount'],
            'net_amount' => (float)$payout['net_amount'],
            'fee' => (float)$payout['fee'],
            'status' => $payout['status'],
            'transaction_hash' => $payout['transaction_hash'],
            'created_at' => $payout['created_at'],
            'processed_at' => $payout['processed_at'],
            'confirmed_at' => $payout['confirmed_at'],
            'error_message' => $payout['error_message']
        ];
    }
    
    // Get summary statistics
    $stmt = $pdo->prepare("
        SELECT 
            COUNT(*) as total_payouts,
            SUM(CASE WHEN status = 'completed' THEN amount ELSE 0 END) as total_paid,
            SUM(CASE WHEN status = 'pending' THEN amount ELSE 0 END) as pending_amount,
            SUM(CASE WHEN status = 'failed' THEN amount ELSE 0 END) as failed_amount
        FROM payouts 
        WHERE user_id = ?
    ");
    $stmt->execute([$user['id']]);
    $summary = $stmt->fetch(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'timestamp' => time(),
        'wallet_address' => $walletAddress,
        'payouts' => $formattedPayouts,
        'summary' => [
            'total_payouts' => (int)$summary['total_payouts'],
            'total_paid' => (float)$summary['total_paid'],
            'pending_amount' => (float)$summary['pending_amount'],
            'failed_amount' => (float)$summary['failed_amount']
        ]
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>
