<?php

namespace YentenPool\Classes;

use PDO;
use Exception;

/**
 * Payout Processor
 * Handles actual Yenten transactions and payout processing
 */
class PayoutProcessor
{
    private $pdo;
    private $yentenRPC;
    private $config;
    
    public function __construct(PDO $pdo, YentenRPC $yentenRPC, array $config = [])
    {
        $this->pdo = $pdo;
        $this->yentenRPC = $yentenRPC;
        $this->config = $config;
    }
    
    /**
     * Process all pending payouts
     */
    public function processPendingPayouts()
    {
        $pendingPayouts = $this->getPendingPayouts();
        $processed = 0;
        $failed = 0;
        $totalAmount = 0;
        
        foreach ($pendingPayouts as $payout) {
            try {
                $result = $this->processPayout($payout);
                if ($result['success']) {
                    $processed++;
                    $totalAmount += $payout['net_amount'];
                } else {
                    $failed++;
                }
            } catch (Exception $e) {
                $this->markPayoutFailed($payout['id'], $e->getMessage());
                $failed++;
                error_log("Payout processing failed for payout {$payout['id']}: " . $e->getMessage());
            }
        }
        
        return [
            'processed' => $processed,
            'failed' => $failed,
            'total_amount' => $totalAmount
        ];
    }
    
    /**
     * Get pending payouts
     */
    private function getPendingPayouts()
    {
        $stmt = $this->pdo->query("
            SELECT 
                p.*,
                u.address
            FROM payouts p
            INNER JOIN users u ON p.user_id = u.id
            WHERE p.status = 'pending'
            ORDER BY p.created_at ASC
        ");
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Process a single payout
     */
    private function processPayout($payout)
    {
        try {
            // Mark as processing
            $this->markPayoutProcessing($payout['id']);
            
            // Check if we have enough balance
            $balance = $this->yentenRPC->getBalance();
            if ($balance < $payout['net_amount']) {
                throw new Exception("Insufficient pool balance: {$balance} < {$payout['net_amount']}");
            }
            
            // Send transaction
            $txHash = $this->sendPayment($payout['address'], $payout['net_amount']);
            
            if (!$txHash) {
                throw new Exception("Failed to send transaction");
            }
            
            // Mark as completed
            $this->markPayoutCompleted($payout['id'], $txHash);
            
            return [
                'success' => true,
                'transaction_hash' => $txHash,
                'amount' => $payout['net_amount']
            ];
            
        } catch (Exception $e) {
            $this->markPayoutFailed($payout['id'], $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * Send payment via Yenten daemon
     */
    private function sendPayment($address, $amount)
    {
        try {
            // Validate address
            $validateResult = $this->yentenRPC->validateAddress($address);
            if (!$validateResult['isvalid']) {
                throw new Exception("Invalid Yenten address: {$address}");
            }
            
            // Send transaction
            $result = $this->yentenRPC->sendToAddress($address, $amount);
            
            if (isset($result['error'])) {
                throw new Exception("RPC Error: " . $result['error']['message']);
            }
            
            return $result['result'] ?? null;
            
        } catch (Exception $e) {
            error_log("Payment failed: " . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * Mark payout as processing
     */
    private function markPayoutProcessing($payoutId)
    {
        $stmt = $this->pdo->prepare("
            UPDATE payouts 
            SET status = 'processing', processed_at = NOW()
            WHERE id = ?
        ");
        
        $stmt->execute([$payoutId]);
    }
    
    /**
     * Mark payout as completed
     */
    private function markPayoutCompleted($payoutId, $transactionHash)
    {
        $stmt = $this->pdo->prepare("
            UPDATE payouts 
            SET 
                status = 'completed',
                transaction_hash = ?,
                confirmed_at = NOW()
            WHERE id = ?
        ");
        
        $stmt->execute([$transactionHash, $payoutId]);
    }
    
    /**
     * Mark payout as failed
     */
    private function markPayoutFailed($payoutId, $errorMessage)
    {
        $stmt = $this->pdo->prepare("
            UPDATE payouts 
            SET 
                status = 'failed',
                error_message = ?,
                processed_at = NOW()
            WHERE id = ?
        ");
        
        $stmt->execute([$errorMessage, $payoutId]);
    }
    
    /**
     * Get pool balance
     */
    public function getPoolBalance()
    {
        try {
            return $this->yentenRPC->getBalance();
        } catch (Exception $e) {
            error_log("Failed to get pool balance: " . $e->getMessage());
            return 0;
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
                SUM(CASE WHEN status = 'processing' THEN amount ELSE 0 END) as processing_amount,
                SUM(CASE WHEN status = 'failed' THEN amount ELSE 0 END) as failed_amount
            FROM payouts
        ");
        
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    /**
     * Retry failed payouts
     */
    public function retryFailedPayouts()
    {
        $failedPayouts = $this->getFailedPayouts();
        $retried = 0;
        
        foreach ($failedPayouts as $payout) {
            try {
                // Reset to pending status
                $stmt = $this->pdo->prepare("
                    UPDATE payouts 
                    SET status = 'pending', error_message = NULL
                    WHERE id = ?
                ");
                
                $stmt->execute([$payout['id']]);
                $retried++;
                
            } catch (Exception $e) {
                error_log("Failed to retry payout {$payout['id']}: " . $e->getMessage());
            }
        }
        
        return $retried;
    }
    
    /**
     * Get failed payouts
     */
    private function getFailedPayouts()
    {
        $stmt = $this->pdo->query("
            SELECT * FROM payouts 
            WHERE status = 'failed'
            ORDER BY created_at DESC
        ");
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
