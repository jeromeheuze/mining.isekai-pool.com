<?php

namespace YentenPool\Classes;

use YentenPool\Config\ConfigManager;
use YentenPool\Database\Database;
use YentenPool\Classes\YentenRPC;
use YentenPool\Classes\KotoRPC;

/**
 * Stratum Server for Yenten Mining Pool
 * Handles JSON-RPC communication with miners
 */
class StratumServer
{
    private $config;
    private $db;
    private $yentenRPC;
    private $kotoRPC;
    private $sockets = [];
    private $clients = [];
    private $running = false;
    private $logFile;
    
    // Stratum protocol constants
    const STRATUM_VERSION = "2.0.0";
    const DIFFICULTY_BASE = 1000000;
    
    public function __construct()
    {
        $this->config = ConfigManager::getInstance();
        $this->db = Database::getInstance();
        $this->yentenRPC = new YentenRPC();
        $this->kotoRPC = new KotoRPC();
        $this->logFile = __DIR__ . '/../../logs/stratum.log';
        $this->setupLogging();
    }
    
    /**
     * Start the Stratum server
     */
    public function start()
    {
        $this->log("Starting Yenten Stratum Server...");
        $this->running = true;
        
        // Get ports from configuration
        $ports = $this->config->get('pool.stratum_ports', [3333, 4444, 5555]);
        
        // Create sockets for each port with coin mapping
        foreach ($ports as $port) {
            $this->createSocket($port);
        }
        
        if (empty($this->sockets)) {
            throw new \Exception("Failed to create any listening sockets");
        }
        
        $this->log("Stratum server listening on ports: " . implode(', ', $ports));
        
        // Main server loop
        $this->serverLoop();
    }
    
    /**
     * Get coin type based on port
     */
    private function getCoinByPort($port)
    {
        switch ($port) {
            case 3333:
                return 'yenten';
            case 4444:
                return 'koto';
            case 5555:
                return 'yenten'; // Default to yenten for now
            default:
                return 'yenten';
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
            case 'koto':
                return $this->kotoRPC;
            default:
                return $this->yentenRPC;
        }
    }
    
    /**
     * Create a listening socket for a specific port
     */
    private function createSocket($port)
    {
        $socket = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
        if (!$socket) {
            $this->log("Failed to create socket for port $port: " . socket_strerror(socket_last_error()));
            return false;
        }
        
        // Set socket options
        socket_set_option($socket, SOL_SOCKET, SO_REUSEADDR, 1);
        socket_set_option($socket, SOL_SOCKET, SO_KEEPALIVE, 1);
        
        // Bind and listen
        if (!socket_bind($socket, '0.0.0.0', $port)) {
            $this->log("Failed to bind socket to port $port: " . socket_strerror(socket_last_error()));
            socket_close($socket);
            return false;
        }
        
        if (!socket_listen($socket, 100)) {
            $this->log("Failed to listen on port $port: " . socket_strerror(socket_last_error()));
            socket_close($socket);
            return false;
        }
        
        // Set non-blocking mode
        socket_set_nonblock($socket);
        
        $this->sockets[$port] = $socket;
        $this->log("Socket created and listening on port $port");
        
        return true;
    }
    
    /**
     * Main server loop
     */
    private function serverLoop()
    {
        while ($this->running) {
            $read = array_merge($this->sockets, array_column($this->clients, 'socket'));
            $write = [];
            $except = [];
            
            // Wait for activity on sockets
            $ready = socket_select($read, $write, $except, 1);
            
            if ($ready === false) {
                $error = socket_last_error();
                if ($error === SOCKET_EINTR) {
                    // Interrupted system call - this is normal, continue
                    continue;
                }
                $this->log("Socket select error: " . socket_strerror($error));
                continue;
            }
            
            if ($ready === 0) {
                // Timeout - check for stale connections
                $this->cleanupStaleConnections();
                continue;
            }
            
            // Handle new connections
            foreach ($read as $socket) {
                if (in_array($socket, $this->sockets)) {
                    $this->handleNewConnection($socket);
                } else {
                    $this->handleClientData($socket);
                }
            }
            
            // Process any pending work
            $this->processWork();
        }
    }
    
    /**
     * Handle new client connections
     */
    private function handleNewConnection($serverSocket)
    {
        $clientSocket = socket_accept($serverSocket);
        if ($clientSocket === false) {
            return;
        }
        
        // Get client info
        socket_getpeername($clientSocket, $ip, $port);
        $clientId = $this->generateClientId();
        
        // Determine coin based on port
        $coin = $this->getCoinByPort($port);
        
        // Set non-blocking mode
        socket_set_nonblock($clientSocket);
        
        // Create client object
        $client = [
            'id' => $clientId,
            'socket' => $clientSocket,
            'ip' => $ip,
            'port' => $port,
            'coin' => $coin,
            'connected_at' => time(),
            'last_activity' => time(),
            'authenticated' => false,
            'user_id' => null,
            'worker_id' => null,
            'difficulty' => 1.0,
            'extranonce1' => $this->generateExtranonce1(),
            'extranonce2_size' => 4,
            'subscribed' => false,
            'buffer' => ''
        ];
        
        $this->clients[$clientId] = $client;
        
        $this->log("New connection from $ip:$port (Client ID: $clientId)");
        
        // Send welcome message
        $this->sendResponse($clientId, null, 'Welcome to Yenten Mining Pool');
    }
    
    /**
     * Handle data from existing clients
     */
    private function handleClientData($clientSocket)
    {
        $clientId = $this->findClientBySocket($clientSocket);
        if (!$clientId) {
            return;
        }
        
        $client = &$this->clients[$clientId];
        
        // Read data
        $data = socket_read($clientSocket, 4096);
        if ($data === false || $data === '') {
            $this->disconnectClient($clientId, 'Connection closed by client');
            return;
        }
        
        $client['last_activity'] = time();
        $client['buffer'] .= $data;
        
        // Process complete JSON-RPC messages
        $this->processClientBuffer($clientId);
    }
    
    /**
     * Process client buffer for complete JSON-RPC messages
     */
    private function processClientBuffer($clientId)
    {
        $client = &$this->clients[$clientId];
        $buffer = $client['buffer'];
        
        // Look for complete JSON messages (separated by newlines)
        while (($pos = strpos($buffer, "\n")) !== false) {
            $message = substr($buffer, 0, $pos);
            $buffer = substr($buffer, $pos + 1);
            
            if (trim($message)) {
                $this->processMessage($clientId, trim($message));
            }
        }
        
        $client['buffer'] = $buffer;
    }
    
    /**
     * Process a single JSON-RPC message
     */
    private function processMessage($clientId, $message)
    {
        $client = &$this->clients[$clientId];
        
        $this->log("Received from client $clientId: $message");
        
        // Parse JSON-RPC message
        $data = json_decode($message, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            $this->sendError($clientId, null, -32700, 'Parse error');
            return;
        }
        
        // Validate JSON-RPC structure
        if (!isset($data['method'])) {
            $this->sendError($clientId, $data['id'] ?? null, -32600, 'Invalid Request');
            return;
        }
        
        $method = $data['method'];
        $params = $data['params'] ?? [];
        $id = $data['id'] ?? null;
        
        // Route method calls
        switch ($method) {
            case 'mining.subscribe':
                $this->handleSubscribe($clientId, $params, $id);
                break;
                
            case 'mining.authorize':
                $this->handleAuthorize($clientId, $params, $id);
                break;
                
            case 'mining.submit':
                $this->handleSubmit($clientId, $params, $id);
                break;
                
            case 'mining.get_transactions':
                $this->handleGetTransactions($clientId, $params, $id);
                break;
                
            case 'mining.extranonce.subscribe':
                $this->handleExtranonceSubscribe($clientId, $params, $id);
                break;
                
            default:
                $this->sendError($clientId, $id, -32601, 'Method not found');
                break;
        }
    }
    
    /**
     * Handle mining.subscribe
     */
    private function handleSubscribe($clientId, $params, $id)
    {
        $client = &$this->clients[$clientId];
        
        $response = [
            [
                [
                    ["mining.set_difficulty", $clientId],
                    ["mining.notify", $clientId]
                ],
                $client['extranonce1'],
                $client['extranonce2_size']
            ]
        ];
        
        $client['subscribed'] = true;
        $this->sendResponse($clientId, $id, $response);
        
        $this->log("Client $clientId subscribed successfully");
    }
    
    /**
     * Handle mining.authorize
     */
    private function handleAuthorize($clientId, $params, $id)
    {
        if (count($params) < 2) {
            $this->sendResponse($clientId, $id, false);
            return;
        }
        
        $username = $params[0];
        $password = $params[1];
        
        // Validate user/worker
        $user = $this->validateUser($username, $password);
        if (!$user) {
            $this->sendResponse($clientId, $id, false);
            $this->log("Authorization failed for client $clientId: $username");
            return;
        }
        
        $client = &$this->clients[$clientId];
        $client['authenticated'] = true;
        $client['user_id'] = $user['user_id'];
        $client['worker_id'] = $user['worker_id'];
        $client['difficulty'] = $user['difficulty'];
        
        $this->sendResponse($clientId, $id, true);
        $this->log("Client $clientId authorized as $username");
        
        // Send difficulty
        $this->sendDifficulty($clientId);
        
        // Send initial work
        $this->sendWork($clientId);
    }
    
    /**
     * Handle mining.extranonce.subscribe
     */
    private function handleExtranonceSubscribe($clientId, $params, $id)
    {
        // This method is used by some miners to subscribe to extranonce changes
        // We'll respond with null to indicate we don't support dynamic extranonce changes
        $this->sendResponse($clientId, $id, null);
        $this->log("Client $clientId subscribed to extranonce changes");
    }
    
    /**
     * Handle mining.submit
     */
    private function handleSubmit($clientId, $params, $id)
    {
        $client = &$this->clients[$clientId];
        
        if (!$client['authenticated']) {
            $this->sendResponse($clientId, $id, false);
            return;
        }
        
        if (count($params) < 5) {
            $this->sendResponse($clientId, $id, false);
            return;
        }
        
        $username = $params[0];
        $jobId = $params[1];
        $extranonce2 = $params[2];
        $ntime = $params[3];
        $nonce = $params[4];
        
        // Validate share
        $result = $this->validateShare($client, $jobId, $extranonce2, $ntime, $nonce);
        
        $this->sendResponse($clientId, $id, $result['valid']);
        
        if ($result['valid']) {
            $this->log("Valid share submitted by client $clientId");
        } else {
            $this->log("Invalid share submitted by client $clientId: " . $result['reason']);
        }
    }
    
    /**
     * Send work to client
     */
    private function sendWork($clientId)
    {
        $client = &$this->clients[$clientId];
        $coin = $client['coin'];
        
        // Generate work using the appropriate daemon
        $jobId = $this->generateJobId();
        $previousHash = $this->getPreviousHash($coin);
        $coinb1 = $this->generateCoinbase1($coin);
        $coinb2 = $this->generateCoinbase2($coin);
        $merkleBranches = $this->getMerkleBranches($coin);
        $version = $this->getVersion($coin);
        $nbits = $this->getNbits($coin);
        $ntime = $this->getNtime($coin);
        $cleanJobs = true;
        
        $params = [
            $jobId,
            $previousHash,
            $coinb1,
            $coinb2,
            $merkleBranches,
            $version,
            $nbits,
            $ntime,
            $cleanJobs
        ];
        
        $this->sendNotification($clientId, 'mining.notify', $params);
    }
    
    /**
     * Send difficulty to client
     */
    private function sendDifficulty($clientId)
    {
        $client = &$this->clients[$clientId];
        $difficulty = $client['difficulty'];
        
        $this->sendNotification($clientId, 'mining.set_difficulty', [$difficulty]);
    }
    
    /**
     * Send JSON-RPC response
     */
    private function sendResponse($clientId, $id, $result)
    {
        $response = [
            'id' => $id,
            'result' => $result,
            'error' => null
        ];
        
        $this->sendToClient($clientId, json_encode($response) . "\n");
    }
    
    /**
     * Send JSON-RPC error
     */
    private function sendError($clientId, $id, $code, $message)
    {
        $response = [
            'id' => $id,
            'result' => null,
            'error' => [
                'code' => $code,
                'message' => $message
            ]
        ];
        
        $this->sendToClient($clientId, json_encode($response) . "\n");
    }
    
    /**
     * Send JSON-RPC notification
     */
    private function sendNotification($clientId, $method, $params)
    {
        $response = [
            'id' => null,
            'method' => $method,
            'params' => $params
        ];
        
        $this->sendToClient($clientId, json_encode($response) . "\n");
    }
    
    /**
     * Send data to client
     */
    private function sendToClient($clientId, $data)
    {
        if (!isset($this->clients[$clientId])) {
            return false;
        }
        
        $client = $this->clients[$clientId];
        $bytes = socket_write($client['socket'], $data);
        
        if ($bytes === false) {
            $this->disconnectClient($clientId, 'Failed to send data');
            return false;
        }
        
        $this->log("Sent to client $clientId: " . trim($data));
        return true;
    }
    
    /**
     * Disconnect a client
     */
    private function disconnectClient($clientId, $reason = 'Unknown')
    {
        if (!isset($this->clients[$clientId])) {
            return;
        }
        
        $client = $this->clients[$clientId];
        socket_close($client['socket']);
        
        $this->log("Client $clientId disconnected: $reason");
        unset($this->clients[$clientId]);
    }
    
    /**
     * Clean up stale connections
     */
    private function cleanupStaleConnections()
    {
        $now = time();
        $timeout = 300; // 5 minutes
        
        foreach ($this->clients as $clientId => $client) {
            if ($now - $client['last_activity'] > $timeout) {
                $this->disconnectClient($clientId, 'Connection timeout');
            }
        }
    }
    
    /**
     * Find client by socket
     */
    private function findClientBySocket($socket)
    {
        foreach ($this->clients as $clientId => $client) {
            if ($client['socket'] === $socket) {
                return $clientId;
            }
        }
        return null;
    }
    
    /**
     * Generate unique client ID
     */
    private function generateClientId()
    {
        return 'client_' . uniqid() . '_' . mt_rand(1000, 9999);
    }
    
    /**
     * Generate extranonce1
     */
    private function generateExtranonce1()
    {
        return bin2hex(random_bytes(4));
    }
    
    /**
     * Generate job ID
     */
    private function generateJobId()
    {
        return uniqid('job_', true);
    }
    
    /**
     * Validate user credentials
     */
    private function validateUser($username, $password)
    {
        // Parse username (format: address.worker or just address)
        $parts = explode('.', $username);
        $address = $parts[0];
        $workerName = $parts[1] ?? 'default';
        
        // Get user from database
        $user = $this->db->fetch("
            SELECT u.id as user_id, u.address, u.is_active
            FROM users u
            WHERE u.address = :address AND u.is_active = 1
        ", ['address' => $address]);
        
        if (!$user) {
            return false;
        }
        
        // Get or create worker
        $worker = $this->db->fetch("
            SELECT id as worker_id, difficulty, is_active
            FROM workers
            WHERE user_id = :user_id AND worker_name = :worker_name AND coin = :coin
        ", [
            'user_id' => $user['user_id'],
            'worker_name' => $workerName,
            'coin' => $client['coin']
        ]);
        
        if (!$worker) {
            // Create new worker
            $workerId = $this->db->insert('workers', [
                'user_id' => $user['user_id'],
                'worker_name' => $workerName,
                'password' => $password,
                'difficulty' => 1.0,
                'is_active' => 1,
                'coin' => $client['coin'],
                'created_at' => date('Y-m-d H:i:s')
            ]);
            
            $worker = [
                'worker_id' => $workerId,
                'difficulty' => 1.0,
                'is_active' => 1
            ];
        }
        
        return [
            'user_id' => $user['user_id'],
            'worker_id' => $worker['worker_id'],
            'difficulty' => $worker['difficulty']
        ];
    }
    
    /**
     * Validate submitted share
     */
    private function validateShare($client, $jobId, $extranonce2, $ntime, $nonce)
    {
        $this->log("DEBUG: validateShare called for client " . $client['id']);
        try {
            // Generate a hash for this share (simplified)
            $shareHash = hash('sha256', $jobId . $extranonce2 . $ntime . $nonce);
            $this->log("DEBUG: Generated share hash: " . $shareHash);
            
            // Check for duplicate shares
            $existingShare = $this->db->fetch("
                SELECT id FROM shares 
                WHERE hash = :hash AND nonce = :nonce
            ", [
                'hash' => $shareHash,
                'nonce' => $nonce
            ]);
            
            if ($existingShare) {
                return [
                    'valid' => false,
                    'reason' => 'Duplicate share'
                ];
            }
            
            // Calculate share difficulty (simplified)
            $shareDifficulty = $client['difficulty'];
            
            // Insert share into database
            $this->log("DEBUG: Inserting share into database for user_id: " . $client['user_id'] . ", worker_id: " . $client['worker_id'] . ", coin: " . $client['coin']);
            $shareId = $this->db->insert('shares', [
                'user_id' => $client['user_id'],
                'worker_id' => $client['worker_id'],
                'block_height' => 1, // Placeholder - would get from actual block
                'difficulty' => $client['difficulty'],
                'share_difficulty' => $shareDifficulty,
                'nonce' => $nonce,
                'hash' => $shareHash,
                'is_valid' => 1,
                'is_stale' => 0,
                'is_duplicate' => 0,
                'submitted_at' => date('Y-m-d H:i:s'),
                'processed_at' => date('Y-m-d H:i:s'),
                'reward' => 0.00000000,
                'coin' => $client['coin']
            ]);
            $this->log("DEBUG: Share inserted with ID: " . $shareId);
            
            // Update worker statistics
            $this->db->query("
                UPDATE workers 
                SET shares_submitted = shares_submitted + 1,
                    shares_accepted = shares_accepted + 1,
                    last_seen = NOW()
                WHERE id = :worker_id
            ", ['worker_id' => $client['worker_id']]);
            
            // Update user statistics
            $this->db->query("
                UPDATE users 
                SET total_shares = total_shares + 1,
                    last_seen = NOW()
                WHERE id = :user_id
            ", ['user_id' => $client['user_id']]);
            
            return [
                'valid' => true,
                'reason' => 'Share accepted',
                'share_id' => $shareId
            ];
            
        } catch (Exception $e) {
            $this->log("Error validating share: " . $e->getMessage());
            return [
                'valid' => false,
                'reason' => 'Database error'
            ];
        }
    }
    
    /**
     * Get real work data from daemon
     */
    private function getPreviousHash($coin = 'yenten') 
    { 
        try {
            $rpc = $this->getRPCForCoin($coin);
            $blockchainInfo = $rpc->getBlockchainInfo();
            $currentHeight = $blockchainInfo['blocks'];
            $currentBlock = $rpc->getBlockByHeight($currentHeight);
            return $currentBlock['hash'];
        } catch (Exception $e) {
            $this->log("Warning: Could not get real previous hash for $coin, using placeholder: " . $e->getMessage());
            return str_repeat('0', 64);
        }
    }
    
    private function generateCoinbase1($coin = 'yenten') 
    { 
        try {
            $rpc = $this->getRPCForCoin($coin);
            $template = $rpc->getBlockTemplate();
            return $template['coinbasetxn']['data'] ?? bin2hex(random_bytes(32));
        } catch (Exception $e) {
            $this->log("Warning: Could not get real coinbase1 for $coin, using placeholder: " . $e->getMessage());
            return bin2hex(random_bytes(32));
        }
    }
    
    private function generateCoinbase2($coin = 'yenten') 
    { 
        try {
            // For now, use placeholder - this would be generated based on pool address
            return bin2hex(random_bytes(32));
        } catch (Exception $e) {
            return bin2hex(random_bytes(32));
        }
    }
    
    private function getMerkleBranches($coin = 'yenten') 
    { 
        try {
            $rpc = $this->getRPCForCoin($coin);
            $template = $rpc->getBlockTemplate();
            return $template['merkle_branch'] ?? [];
        } catch (Exception $e) {
            return [];
        }
    }
    
    private function getVersion($coin = 'yenten') 
    { 
        try {
            $rpc = $this->getRPCForCoin($coin);
            $blockchainInfo = $rpc->getBlockchainInfo();
            $currentHeight = $blockchainInfo['blocks'];
            $currentBlock = $rpc->getBlockByHeight($currentHeight);
            return dechex($currentBlock['version']);
        } catch (Exception $e) {
            return '20000000';
        }
    }
    
    private function getNbits($coin = 'yenten') 
    { 
        try {
            $rpc = $this->getRPCForCoin($coin);
            $blockchainInfo = $rpc->getBlockchainInfo();
            $currentHeight = $blockchainInfo['blocks'];
            $currentBlock = $rpc->getBlockByHeight($currentHeight);
            return $currentBlock['bits'];
        } catch (Exception $e) {
            return '1d00ffff';
        }
    }
    
    private function getNtime($coin = 'yenten') 
    { 
        try {
            $rpc = $this->getRPCForCoin($coin);
            $blockchainInfo = $rpc->getBlockchainInfo();
            $currentHeight = $blockchainInfo['blocks'];
            $currentBlock = $rpc->getBlockByHeight($currentHeight);
            return dechex($currentBlock['time']);
        } catch (Exception $e) {
            return dechex(time());
        }
    }
    
    /**
     * Process work (placeholder)
     */
    private function processWork()
    {
        // This would handle:
        // - Getting new work from Yenten daemon
        // - Updating job templates
        // - Managing block templates
    }
    
    /**
     * Setup logging
     */
    private function setupLogging()
    {
        $logDir = dirname($this->logFile);
        if (!is_dir($logDir)) {
            mkdir($logDir, 0755, true);
        }
    }
    
    /**
     * Log message
     */
    private function log($message)
    {
        $timestamp = date('Y-m-d H:i:s');
        $logMessage = "[$timestamp] $message" . PHP_EOL;
        
        file_put_contents($this->logFile, $logMessage, FILE_APPEND | LOCK_EX);
        echo $logMessage;
    }
    
    /**
     * Stop the server
     */
    public function stop()
    {
        $this->running = false;
        $this->log("Stratum server stopping...");
        
        // Close all client connections
        foreach ($this->clients as $clientId => $client) {
            socket_close($client['socket']);
        }
        
        // Close all server sockets
        foreach ($this->sockets as $socket) {
            socket_close($socket);
        }
        
        $this->log("Stratum server stopped");
    }
}
