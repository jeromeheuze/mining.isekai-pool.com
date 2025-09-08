<?php

namespace YentenPool\Classes;

use PDO;
use Exception;

/**
 * PPLNS (Pay Per Last N Shares) Calculator
 * Distributes block rewards based on recent share contributions
 */
class PPLNSCalculator
{
    private $pdo;
    private $config;
    
    // PPLNS Configuration
    private $pplnsWindow = 100000; // Last N shares to consider
    private $poolFeePercent = 1.0; // Pool fee percentage
    private $minimumPayout = 0.1; // Minimum payout amount
    private $payoutThreshold = 0.5; // Automatic payout threshold
    
    public function __construct(PDO $pdo, array $config = [])
    {
        $this->pdo = $pdo;
        $this->config = $config;
        
        // Load configuration
        $this->poolFeePercent = $config['pool']['fee_percent'] ?? 1.0;
        $this->minimumPayout = $config['pool']['minimum_payout'] ?? 0.1;
        $this->payoutThreshold = $config['pool']['payout_threshold'] ?? 0.5;
    }
    
    /**
     * Calculate and distribute block rewards using PPLNS
     * 
     * @param int $blockHeight Block height
     * @param string $blockHash Block hash
     * @param float $blockReward Total block reward
     * @param int $foundByUserId User who found the block (bonus)
     * @return array Distribution results
     */
    public function distributeBlockReward($blockHeight, $blockHash, $blockReward, $foundByUserId = null)
    {
        try {
            $this->pdo->beginTransaction();
            
            // Get last N shares for PPLNS calculation
            $shares = $this->getLastNShares($this->pplnsWindow);
            
            if (empty($shares)) {
                throw new Exception("No shares found for PPLNS calculation");
            }
            
            // Calculate total share value
            $totalShareValue = array_sum(array_column($shares, 'difficulty'));
            
            if ($totalShareValue <= 0) {
                throw new Exception("Invalid total share value: {$totalShareValue}");
            }
            
            // Calculate pool fee
            $poolFee = $blockReward * ($this->poolFeePercent / 100);
            $distributableReward = $blockReward - $poolFee;
            
            // Calculate miner earnings
            $distributions = [];
            $totalDistributed = 0;
            
            foreach ($shares as $share) {
                $userShareValue = $share['difficulty'];
                $userEarnings = ($userShareValue / $totalShareValue) * $distributableReward;
                
                // Add block finder bonus (5% of total reward)
                if ($foundByUserId && $share['user_id'] == $foundByUserId) {
                    $finderBonus = $blockReward * 0.05;
                    $userEarnings += $finderBonus;
                }
                
                if ($userEarnings > 0) {
                    $distributions[] = [
                        'user_id' => $share['user_id'],
                        'address' => $share['address'],
                        'share_value' => $userShareValue,
                        'earnings' => $userEarnings,
                        'percentage' => ($userShareValue / $totalShareValue) * 100
                    ];
                    
                    $totalDistributed += $userEarnings;
                }
            }
            
            // Update user balances
            foreach ($distributions as $distribution) {
                $this->updateUserBalance(
                    $distribution['user_id'],
                    $distribution['earnings']
                );
            }
            
            // Record block in database
            $this->recordBlock($blockHeight, $blockHash, $blockReward, $foundByUserId);
            
            // Record pool fee
            $this->recordPoolFee($poolFee);
            
            $this->pdo->commit();
            
            return [
                'success' => true,
                'block_height' => $blockHeight,
                'block_hash' => $blockHash,
                'total_reward' => $blockReward,
                'pool_fee' => $poolFee,
                'distributable_reward' => $distributableReward,
                'total_distributed' => $totalDistributed,
                'miners_paid' => count($distributions),
                'distributions' => $distributions
            ];
            
        } catch (Exception $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }
    
    /**
     * Get last N shares for PPLNS calculation
     */
    private function getLastNShares($limit)
    {
        $stmt = $this->pdo->prepare("
            SELECT 
                s.user_id,
                s.difficulty,
                s.submitted_at,
                u.address
            FROM shares s
            INNER JOIN users u ON s.user_id = u.id
            WHERE s.is_valid = 1 
            AND s.submitted_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
            ORDER BY s.submitted_at DESC
            LIMIT ?
        ");
        
        $stmt->execute([$limit]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Update user's pending balance
     */
    private function updateUserBalance($userId, $earnings)
    {
        $stmt = $this->pdo->prepare("
            UPDATE users 
            SET 
                pending_balance = pending_balance + ?,
                total_earnings = total_earnings + ?,
                updated_at = NOW()
            WHERE id = ?
        ");
        
        $stmt->execute([$earnings, $earnings, $userId]);
    }
    
    /**
     * Record block in database
     */
    private function recordBlock($blockHeight, $blockHash, $blockReward, $foundByUserId)
    {
        $stmt = $this->pdo->prepare("
            INSERT INTO blocks (
                height, hash, pool_reward, network_reward, 
                found_by_user_id, created_at
            ) VALUES (?, ?, ?, ?, ?, NOW())
        ");
        
        $stmt->execute([
            $blockHeight,
            $blockHash,
            $blockReward,
            $blockReward,
            $foundByUserId
        ]);
    }
    
    /**
     * Record pool fee
     */
    private function recordPoolFee($fee)
    {
        // You could create a separate pool_fees table or add to pool_stats
        // For now, we'll just log it
        error_log("Pool fee collected: {$fee} YTN");
    }
    
    /**
     * Get users eligible for payout
     */
    public function getEligiblePayouts()
    {
        $stmt = $this->pdo->prepare("
            SELECT 
                id, address, pending_balance
            FROM users 
            WHERE pending_balance >= ? 
            AND is_active = 1
            ORDER BY pending_balance DESC
        ");
        
        $stmt->execute([$this->payoutThreshold]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Process pending payouts
     */
    public function processPayouts()
    {
        $eligibleUsers = $this->getEligiblePayouts();
        $processed = 0;
        $totalPaid = 0;
        
        foreach ($eligibleUsers as $user) {
            try {
                $result = $this->processUserPayout($user);
                if ($result['success']) {
                    $processed++;
                    $totalPaid += $user['pending_balance'];
                }
            } catch (Exception $e) {
                error_log("Failed to process payout for user {$user['id']}: " . $e->getMessage());
            }
        }
        
        return [
            'processed' => $processed,
            'total_paid' => $totalPaid,
            'eligible_users' => count($eligibleUsers)
        ];
    }
    
    /**
     * Process payout for a single user
     */
    private function processUserPayout($user)
    {
        $this->pdo->beginTransaction();
        
        try {
            $amount = $user['pending_balance'];
            $fee = $amount * 0.001; // 0.1% transaction fee
            $netAmount = $amount - $fee;
            
            // Create payout record
            $stmt = $this->pdo->prepare("
                INSERT INTO payouts (
                    user_id, amount, fee, net_amount, status, created_at
                ) VALUES (?, ?, ?, ?, 'pending', NOW())
            ");
            
            $stmt->execute([$user['id'], $amount, $fee, $netAmount]);
            $payoutId = $this->pdo->lastInsertId();
            
            // Update user balance
            $stmt = $this->pdo->prepare("
                UPDATE users 
                SET 
                    pending_balance = 0,
                    paid_balance = paid_balance + ?,
                    updated_at = NOW()
                WHERE id = ?
            ");
            
            $stmt->execute([$amount, $user['id']]);
            
            $this->pdo->commit();
            
            return [
                'success' => true,
                'payout_id' => $payoutId,
                'amount' => $amount,
                'net_amount' => $netAmount,
                'fee' => $fee
            ];
            
        } catch (Exception $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }
    
    /**
     * Get payout statistics
     */
    public function getPayoutStats()
    {
        $stmt = $this->pdo->query("
            SELECT 
                COUNT(*) as total_payouts,
                SUM(CASE WHEN status = 'completed' THEN amount ELSE 0 END) as total_paid,
                SUM(CASE WHEN status = 'pending' THEN amount ELSE 0 END) as pending_amount,
                SUM(CASE WHEN status = 'failed' THEN amount ELSE 0 END) as failed_amount
            FROM payouts
        ");
        
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
}
