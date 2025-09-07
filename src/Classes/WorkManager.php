<?php

namespace YentenPool\Classes;

use YentenPool\Config\ConfigManager;
use YentenPool\Database\Database;
use YentenPool\Classes\UkkeyCoinRPC;

/**
 * Work Manager for Yenten Mining Pool
 * Handles work distribution and block template management
 */
class WorkManager
{
    private $config;
    private $db;
    private $yentenRPC;
    private $ukkeyCoinRPC;
    private $currentWork;
    private $lastWorkUpdate;
    private $workUpdateInterval;
    
    public function __construct()
    {
        $this->config = ConfigManager::getInstance();
        $this->db = Database::getInstance();
        $this->yentenRPC = new YentenRPC();
        $this->ukkeyCoinRPC = new UkkeyCoinRPC();
        $this->workUpdateInterval = 30; // Update work every 30 seconds
        $this->lastWorkUpdate = 0;
    }
    
    /**
     * Get current work for mining
     */
    public function getCurrentWork($coin = 'yenten')
    {
        $now = time();
        
        // Update work if needed
        if ($now - $this->lastWorkUpdate > $this->workUpdateInterval) {
            $this->updateWork($coin);
        }
        
        return $this->currentWork;
    }
    
    /**
     * Update work from daemon
     */
    public function updateWork($coin = 'yenten')
    {
        try {
            $rpc = $this->getRPCForCoin($coin);
            
            // Check if daemon is synced
            if (!$rpc->isSynced()) {
                $this->log("$coin daemon is not synced, using fallback work");
                $this->currentWork = $this->getFallbackWork($coin);
                $this->lastWorkUpdate = time();
                return;
            }
            
            // Get block template
            $template = $rpc->getBlockTemplate([
                'rules' => ['segwit'],
                'capabilities' => ['proposal']
            ]);
            
            if (!$template) {
                throw new \Exception("Failed to get block template");
            }
            
            // Process block template
            $work = $this->processBlockTemplate($template, $coin);
            
            // Store work in database
            $this->storeWork($work);
            
            $this->currentWork = $work;
            $this->lastWorkUpdate = time();
            
            $this->log("$coin work updated successfully - Height: {$work['height']}, Hash: {$work['previous_hash']}");
            
        } catch (\Exception $e) {
            $this->log("Failed to update $coin work: " . $e->getMessage());
            
            // Use fallback work if available
            if (!$this->currentWork) {
                $this->currentWork = $this->getFallbackWork($coin);
            }
        }
    }
    
    /**
     * Get RPC client for coin
     */
    private function getRPCForCoin($coin)
    {
        switch ($coin) {
            case 'yenten':
                return $this->yentenRPC;
            case 'ukkeycoin':
                return $this->ukkeyCoinRPC;
            default:
                return $this->yentenRPC;
        }
    }
    
    /**
     * Process block template from daemon
     */
    private function processBlockTemplate($template, $coin = 'yenten')
    {
        $work = [
            'job_id' => $this->generateJobId(),
            'height' => $template['height'],
            'previous_hash' => $template['previousblockhash'],
            'coinbase1' => $template['coinbasevalue'] ? $this->buildCoinbase1($template) : '',
            'coinbase2' => $template['coinbasevalue'] ? $this->buildCoinbase2($template) : '',
            'merkle_branches' => $template['merkle_branch'] ?? [],
            'version' => $template['version'],
            'nbits' => $template['bits'],
            'ntime' => $template['curtime'],
            'target' => $this->calculateTarget($template['bits']),
            'difficulty' => $this->calculateDifficulty($template['bits']),
            'created_at' => time(),
            'template' => $template,
            'coin' => $coin
        ];
        
        return $work;
    }
    
    /**
     * Build coinbase1 (first part of coinbase transaction)
     */
    private function buildCoinbase1($template)
    {
        // This is a simplified coinbase1 construction
        // In a real implementation, you would build the proper coinbase transaction
        
        $height = $template['height'];
        $coinbaseValue = $template['coinbasevalue'];
        
        // Encode height in script
        $heightScript = $this->encodeHeight($height);
        
        // Build coinbase1 (simplified)
        $coinbase1 = bin2hex($heightScript) . str_repeat('00', 32);
        
        return $coinbase1;
    }
    
    /**
     * Build coinbase2 (second part of coinbase transaction)
     */
    private function buildCoinbase2($template)
    {
        // This is a simplified coinbase2 construction
        // In a real implementation, you would build the proper coinbase transaction
        
        $coinbaseValue = $template['coinbasevalue'];
        
        // Build coinbase2 (simplified)
        $coinbase2 = str_pad(dechex($coinbaseValue), 16, '0', STR_PAD_LEFT) . str_repeat('00', 16);
        
        return $coinbase2;
    }
    
    /**
     * Encode block height in script
     */
    private function encodeHeight($height)
    {
        if ($height < 17) {
            return chr($height);
        } elseif ($height < 0x100) {
            return chr(0x51) . chr($height);
        } elseif ($height < 0x10000) {
            return chr(0x52) . pack('v', $height);
        } else {
            return chr(0x53) . pack('V', $height);
        }
    }
    
    /**
     * Calculate target from nbits
     */
    private function calculateTarget($nbits)
    {
        $exponent = hexdec(substr($nbits, 0, 2));
        $mantissa = hexdec(substr($nbits, 2, 6));
        
        if ($exponent <= 3) {
            $target = $mantissa >> (8 * (3 - $exponent));
        } else {
            $target = $mantissa << (8 * ($exponent - 3));
        }
        
        return str_pad(dechex($target), 64, '0', STR_PAD_LEFT);
    }
    
    /**
     * Calculate difficulty from nbits
     */
    private function calculateDifficulty($nbits)
    {
        $target = $this->calculateTarget($nbits);
        $targetValue = hexdec($target);
        
        if ($targetValue == 0) {
            return 0;
        }
        
        // Yenten uses a different difficulty calculation
        $maxTarget = hexdec('00000000ffff0000000000000000000000000000000000000000000000000000');
        return $maxTarget / $targetValue;
    }
    
    /**
     * Generate unique job ID
     */
    private function generateJobId()
    {
        return uniqid('job_', true);
    }
    
    /**
     * Store work in database
     */
    private function storeWork($work)
    {
        try {
            // Store work information
            $this->db->insert('pool_work', [
                'job_id' => $work['job_id'],
                'height' => $work['height'],
                'previous_hash' => $work['previous_hash'],
                'coinbase1' => $work['coinbase1'],
                'coinbase2' => $work['coinbase2'],
                'merkle_branches' => json_encode($work['merkle_branches']),
                'version' => $work['version'],
                'nbits' => $work['nbits'],
                'ntime' => $work['ntime'],
                'target' => $work['target'],
                'difficulty' => $work['difficulty'],
                'created_at' => date('Y-m-d H:i:s', $work['created_at'])
            ]);
            
        } catch (\Exception $e) {
            $this->log("Failed to store work: " . $e->getMessage());
        }
    }
    
    /**
     * Get fallback work when daemon is unavailable
     */
    private function getFallbackWork($coin = 'yenten')
    {
        // Get the last known work from database for this coin
        $lastWork = $this->db->fetch("
            SELECT * FROM pool_work 
            WHERE coin = :coin
            ORDER BY created_at DESC 
            LIMIT 1
        ", ['coin' => $coin]);
        
        if ($lastWork) {
            return [
                'job_id' => $this->generateJobId(),
                'height' => $lastWork['height'] + 1,
                'previous_hash' => $lastWork['previous_hash'],
                'coinbase1' => $lastWork['coinbase1'],
                'coinbase2' => $lastWork['coinbase2'],
                'merkle_branches' => json_decode($lastWork['merkle_branches'], true),
                'version' => $lastWork['version'],
                'nbits' => $lastWork['nbits'],
                'ntime' => dechex(time()),
                'target' => $lastWork['target'],
                'difficulty' => $lastWork['difficulty'],
                'created_at' => time(),
                'coin' => $coin
            ];
        }
        
        // Return default work if no previous work exists
        return [
            'job_id' => $this->generateJobId(),
            'height' => 1,
            'previous_hash' => str_repeat('0', 64),
            'coinbase1' => str_repeat('00', 64),
            'coinbase2' => str_repeat('00', 64),
            'merkle_branches' => [],
            'version' => '20000000',
            'nbits' => '1d00ffff',
            'ntime' => dechex(time()),
            'target' => '00000000ffff0000000000000000000000000000000000000000000000000000',
            'difficulty' => 1.0,
            'created_at' => time(),
            'coin' => $coin
        ];
    }
    
    /**
     * Validate submitted share
     */
    public function validateShare($shareData, $coin = 'yenten')
    {
        $work = $this->getCurrentWork($coin);
        
        // Basic validation
        if (!$work) {
            return ['valid' => false, 'reason' => 'No work available'];
        }
        
        // Check if job exists
        if ($shareData['job_id'] !== $work['job_id']) {
            return ['valid' => false, 'reason' => 'Invalid job ID'];
        }
        
        // Check ntime
        if ($shareData['ntime'] < $work['ntime']) {
            return ['valid' => false, 'reason' => 'Stale ntime'];
        }
        
        // Check if ntime is too far in the future
        $currentTime = time();
        $shareTime = hexdec($shareData['ntime']);
        if ($shareTime > $currentTime + 7200) { // 2 hours
            return ['valid' => false, 'reason' => 'ntime too far in future'];
        }
        
        // Validate proof of work
        $hash = $this->calculateShareHash($shareData, $work);
        $target = $work['target'];
        
        if (hexdec($hash) > hexdec($target)) {
            return ['valid' => false, 'reason' => 'Share does not meet target'];
        }
        
        return ['valid' => true, 'hash' => $hash, 'target' => $target];
    }
    
    /**
     * Calculate share hash
     */
    private function calculateShareHash($shareData, $work)
    {
        // This is a simplified hash calculation
        // In a real implementation, you would use the proper YescryptR16 algorithm
        
        $data = $shareData['extranonce1'] . 
                $shareData['extranonce2'] . 
                $work['coinbase1'] . 
                $work['coinbase2'] . 
                $work['merkle_branches'] . 
                $work['version'] . 
                $work['previous_hash'] . 
                $shareData['ntime'] . 
                $work['nbits'] . 
                $shareData['nonce'];
        
        return hash('sha256', hash('sha256', $data, true), true);
    }
    
    /**
     * Submit block to network
     */
    public function submitBlock($blockData, $coin = 'yenten')
    {
        try {
            $rpc = $this->getRPCForCoin($coin);
            $result = $rpc->submitBlock($blockData);
            
            if ($result === null) {
                // Block was accepted
                $this->log("Block submitted successfully");
                return ['success' => true, 'message' => 'Block accepted'];
            } else {
                // Block was rejected
                $this->log("Block rejected: " . $result);
                return ['success' => false, 'message' => $result];
            }
            
        } catch (\Exception $e) {
            $this->log("Failed to submit block: " . $e->getMessage());
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }
    
    /**
     * Get network statistics
     */
    public function getNetworkStats($coin = 'yenten')
    {
        try {
            $rpc = $this->getRPCForCoin($coin);
            $blockchainInfo = $rpc->getBlockchainInfo();
            $miningInfo = $rpc->getMiningInfo();
            
            return [
                'height' => $blockchainInfo['blocks'] ?? 0,
                'difficulty' => $miningInfo['difficulty'] ?? 1.0,
                'network_hashrate' => $miningInfo['networkhashps'] ?? 0,
                'synced' => !($blockchainInfo['initialblockdownload'] ?? true)
            ];
            
        } catch (\Exception $e) {
            $this->log("Failed to get network stats: " . $e->getMessage());
            return [
                'height' => 0,
                'difficulty' => 1.0,
                'network_hashrate' => 0,
                'synced' => false
            ];
        }
    }
    
    /**
     * Log message
     */
    private function log($message)
    {
        $timestamp = date('Y-m-d H:i:s');
        $logMessage = "[$timestamp] WorkManager: $message" . PHP_EOL;
        
        $logFile = __DIR__ . '/../../logs/work_manager.log';
        file_put_contents($logFile, $logMessage, FILE_APPEND | LOCK_EX);
        echo $logMessage;
    }
}
