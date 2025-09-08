<?php
// Simple stats update script that works from web directory
try {
    // Direct database connection
    $pdo = new PDO('mysql:host=localhost;port=3306;dbname=yenten_pool', 'pool_user', 'D|Hm3"K12<Zv');
    
    // Get current mining data from shares table
    $shares = $pdo->query("SELECT COUNT(*) as total_shares, COUNT(DISTINCT user_id) as active_miners FROM shares WHERE submitted_at >= DATE_SUB(NOW(), INTERVAL 1 HOUR)")->fetch();
    
    // Set current stats (based on your mining)
    $total_hashrate = 1350; // Your current hashrate
    $active_miners = max(1, $shares['active_miners']); // At least 1 if you're mining
    $network_hashrate = 234941; // Current Yenten network hashrate
    $network_difficulty = 0.0089; // Current Yenten difficulty
    $pool_difficulty = 1.0;
    $shares_per_second = $shares['total_shares'] / 3600;
    
    // Update pool_stats table
    $stmt = $pdo->prepare("INSERT INTO pool_stats (
        active_miners, 
        active_workers, 
        total_hashrate, 
        network_hashrate, 
        network_difficulty, 
        pool_difficulty, 
        shares_per_second,
        timestamp
    ) VALUES (?, ?, ?, ?, ?, ?, ?, NOW())");
    
    $stmt->execute([
        $active_miners, 
        1, // active_workers
        $total_hashrate, 
        $network_hashrate, 
        $network_difficulty, 
        $pool_difficulty, 
        $shares_per_second
    ]);
    
    echo "Pool stats updated:\n";
    echo "- Active miners: {$active_miners}\n";
    echo "- Total hashrate: {$total_hashrate} H/s\n";
    echo "- Total shares (last hour): {$shares['total_shares']}\n";
    
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>
