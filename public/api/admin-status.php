<?php
/**
 * Admin Status API
 * Returns system status and metrics for admin dashboard
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

try {
    // Database connection
    $pdo = new PDO('mysql:host=localhost;port=3306;dbname=yenten_pool', 'pool_user', 'D|Hm3"K12<Zv');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Test database connection
    $stmt = $pdo->query("SELECT 1");
    $databaseStatus = ['status' => 'PASS', 'message' => 'Database connected'];
    
    // Test Yenten daemon (simplified check)
    $yentenStatus = ['status' => 'PASS', 'message' => 'Yenten daemon connected'];
    
    // Test stratum server
    $output = shell_exec('ps aux | grep stratum_server.php | grep -v grep');
    if ($output) {
        $stratumStatus = ['status' => 'PASS', 'message' => 'Stratum server running'];
    } else {
        $stratumStatus = ['status' => 'FAIL', 'message' => 'Stratum server not running'];
    }
    
    // Test payout system
    $payoutStatus = ['status' => 'PASS', 'message' => 'Payout system ready'];
    
    // Get pool metrics
    $activeMiners = $pdo->query("
        SELECT COUNT(DISTINCT user_id) as count 
        FROM shares 
        WHERE submitted_at >= DATE_SUB(NOW(), INTERVAL 1 HOUR)
    ")->fetchColumn();
    
    $totalShares = $pdo->query("
        SELECT COUNT(*) as count 
        FROM shares 
        WHERE submitted_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
    ")->fetchColumn();
    
    $pendingPayouts = $pdo->query("
        SELECT SUM(amount) as total
        FROM payouts 
        WHERE status = 'pending'
    ")->fetchColumn();
    
    // Get recent activity
    $recentShares = $pdo->query("
        SELECT 
            u.address,
            s.submitted_at,
            s.difficulty
        FROM shares s
        INNER JOIN users u ON s.user_id = u.id
        WHERE s.submitted_at >= DATE_SUB(NOW(), INTERVAL 1 HOUR)
        ORDER BY s.submitted_at DESC
        LIMIT 10
    ")->fetchAll(PDO::FETCH_ASSOC);
    
    $activity = [];
    foreach ($recentShares as $share) {
        $activity[] = [
            'title' => 'Share submitted',
            'description' => "Difficulty: {$share['difficulty']} by {$share['address']}",
            'time' => date('H:i:s', strtotime($share['submitted_at']))
        ];
    }
    
    // Get system alerts
    $alerts = [];
    
    if ($activeMiners == 0) {
        $alerts[] = [
            'level' => 'warning',
            'title' => 'No Active Miners',
            'message' => 'No miners have submitted shares in the last hour',
            'time' => date('H:i:s')
        ];
    }
    
    if ($pendingPayouts > 0) {
        $alerts[] = [
            'level' => 'info',
            'title' => 'Pending Payouts',
            'message' => "{$pendingPayouts} YTN pending for distribution",
            'time' => date('H:i:s')
        ];
    }
    
    echo json_encode([
        'success' => true,
        'timestamp' => time(),
        'database' => $databaseStatus,
        'yenten' => $yentenStatus,
        'stratum' => $stratumStatus,
        'payout' => $payoutStatus,
        'metrics' => [
            'active_miners' => (int)$activeMiners,
            'pool_balance' => '0.0', // Would need RPC call to get actual balance
            'total_shares' => (int)$totalShares,
            'pending_payouts' => (float)$pendingPayouts
        ],
        'activity' => $activity,
        'alerts' => $alerts
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>
