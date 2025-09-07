<?php

namespace YentenPool\Classes;

use YentenPool\Config\ConfigManager;

/**
 * UkkeyCoin RPC Client
 * Handles communication with UkkeyCoin daemon
 * Uses YesPoWer algorithm
 */
class UkkeyCoinRPC
{
    private $config;
    private $host;
    private $port;
    private $username;
    private $password;
    private $timeout;
    
    public function __construct()
    {
        $this->config = ConfigManager::getInstance();
        $ukyConfig = $this->config->getUkkeyCoinConfig();
        
        $this->host = $ukyConfig['daemon_host'];
        $this->port = $ukyConfig['daemon_port'];
        $this->username = $ukyConfig['daemon_user'];
        $this->password = $ukyConfig['daemon_password'];
        $this->timeout = 30;
    }
    
    /**
     * Make RPC call to UkkeyCoin daemon
     */
    public function call($method, $params = [])
    {
        $request = [
            'jsonrpc' => '2.0',
            'id' => uniqid(),
            'method' => $method,
            'params' => $params
        ];
        
        $response = $this->sendRequest($request);
        
        if (isset($response['error'])) {
            throw new \Exception("RPC Error: " . $response['error']['message']);
        }
        
        return $response['result'] ?? null;
    }
    
    /**
     * Send HTTP request to daemon
     */
    private function sendRequest($request)
    {
        $url = "http://{$this->host}:{$this->port}";
        $data = json_encode($request);
        
        $context = stream_context_create([
            'http' => [
                'method' => 'POST',
                'header' => [
                    'Content-Type: application/json',
                    'Authorization: Basic ' . base64_encode($this->username . ':' . $this->password)
                ],
                'content' => $data,
                'timeout' => $this->timeout
            ]
        ]);
        
        $response = file_get_contents($url, false, $context);
        
        if ($response === false) {
            throw new \Exception("Failed to connect to UkkeyCoin daemon");
        }
        
        $decoded = json_decode($response, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \Exception("Invalid JSON response from daemon");
        }
        
        return $decoded;
    }
    
    /**
     * Get blockchain info
     */
    public function getBlockchainInfo()
    {
        return $this->call('getblockchaininfo');
    }
    
    /**
     * Get network info
     */
    public function getNetworkInfo()
    {
        return $this->call('getnetworkinfo');
    }
    
    /**
     * Get mining info
     */
    public function getMiningInfo()
    {
        return $this->call('getmininginfo');
    }
    
    /**
     * Get block template
     */
    public function getBlockTemplate($params = [])
    {
        return $this->call('getblocktemplate', $params);
    }
    
    /**
     * Submit block
     */
    public function submitBlock($hexData)
    {
        return $this->call('submitblock', [$hexData]);
    }
    
    /**
     * Get block by hash
     */
    public function getBlock($hash, $verbosity = 1)
    {
        return $this->call('getblock', [$hash, $verbosity]);
    }
    
    /**
     * Get block by height
     */
    public function getBlockByHeight($height)
    {
        $hash = $this->call('getblockhash', [$height]);
        return $this->getBlock($hash);
    }
    
    /**
     * Get transaction
     */
    public function getTransaction($txid, $includeWatchonly = false)
    {
        return $this->call('gettransaction', [$txid, $includeWatchonly]);
    }
    
    /**
     * Get raw transaction
     */
    public function getRawTransaction($txid, $verbose = false)
    {
        return $this->call('getrawtransaction', [$txid, $verbose]);
    }
    
    /**
     * Send raw transaction
     */
    public function sendRawTransaction($hexString, $allowHighFees = false)
    {
        return $this->call('sendrawtransaction', [$hexString, $allowHighFees]);
    }
    
    /**
     * Get balance
     */
    public function getBalance($account = '*', $minconf = 1, $includeWatchonly = false)
    {
        return $this->call('getbalance', [$account, $minconf, $includeWatchonly]);
    }
    
    /**
     * List unspent outputs
     */
    public function listUnspent($minconf = 1, $maxconf = 9999999, $addresses = [])
    {
        return $this->call('listunspent', [$minconf, $maxconf, $addresses]);
    }
    
    /**
     * Create raw transaction
     */
    public function createRawTransaction($inputs, $outputs)
    {
        return $this->call('createrawtransaction', [$inputs, $outputs]);
    }
    
    /**
     * Sign raw transaction
     */
    public function signRawTransaction($hexString, $prevtxs = [], $privkeys = [], $sighashtype = 'ALL')
    {
        return $this->call('signrawtransaction', [$hexString, $prevtxs, $privkeys, $sighashtype]);
    }
    
    /**
     * Validate address
     */
    public function validateAddress($address)
    {
        return $this->call('validateaddress', [$address]);
    }
    
    /**
     * Get new address
     */
    public function getNewAddress($label = '')
    {
        return $this->call('getnewaddress', [$label]);
    }
    
    /**
     * Get account address
     */
    public function getAccountAddress($account)
    {
        return $this->call('getaccountaddress', [$account]);
    }
    
    /**
     * Test connection to daemon
     */
    public function testConnection()
    {
        try {
            $this->getBlockchainInfo();
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }
    
    /**
     * Get current block height
     */
    public function getBlockHeight()
    {
        $info = $this->getBlockchainInfo();
        return $info['blocks'] ?? 0;
    }
    
    /**
     * Get current difficulty
     */
    public function getDifficulty()
    {
        $info = $this->getMiningInfo();
        return $info['difficulty'] ?? 1.0;
    }
    
    /**
     * Get network hashrate
     */
    public function getNetworkHashrate()
    {
        $info = $this->getMiningInfo();
        return $info['networkhashps'] ?? 0;
    }
    
    /**
     * Check if daemon is synced
     */
    public function isSynced()
    {
        $info = $this->getBlockchainInfo();
        return !($info['initialblockdownload'] ?? false);
    }
    
    /**
     * Get algorithm info (YesPoWer specific)
     */
    public function getAlgorithmInfo()
    {
        return [
            'name' => 'YesPoWer',
            'description' => 'UkkeyCoin uses YesPoWer algorithm',
            'difficulty_target' => '00000000ffff0000000000000000000000000000000000000000000000000000'
        ];
    }
}
