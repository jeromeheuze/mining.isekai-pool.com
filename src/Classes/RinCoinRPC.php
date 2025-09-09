<?php

namespace YentenPool\Classes;

/**
 * RinCoin RPC Client
 * Handles communication with RinCoin daemon
 */
class RinCoinRPC
{
    private $host;
    private $port;
    private $username;
    private $password;
    private $timeout;

    public function __construct($host = 'localhost', $port = 9332, $username = '', $password = '', $timeout = 30)
    {
        $this->host = $host;
        $this->port = $port;
        $this->username = $username;
        $this->password = $password;
        $this->timeout = $timeout;
    }

    /**
     * Send RPC request to RinCoin daemon
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

        $result = file_get_contents($url, false, $context);
        
        if ($result === false) {
            throw new \Exception('Failed to connect to RinCoin daemon');
        }

        $response = json_decode($result, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \Exception('Invalid JSON response from RinCoin daemon');
        }

        if (isset($response['error']) && $response['error'] !== null) {
            throw new \Exception('RinCoin RPC error: ' . $response['error']['message']);
        }

        return $response['result'];
    }

    /**
     * Make RPC call
     */
    public function call($method, $params = [])
    {
        $request = [
            'jsonrpc' => '2.0',
            'id' => time(),
            'method' => $method,
            'params' => $params
        ];

        return $this->sendRequest($request);
    }

    /**
     * Get blockchain info
     */
    public function getBlockchainInfo()
    {
        return $this->call('getblockchaininfo');
    }

    /**
     * Get block count
     */
    public function getBlockCount()
    {
        return $this->call('getblockcount');
    }

    /**
     * Get block hash
     */
    public function getBlockHash($height)
    {
        return $this->call('getblockhash', [$height]);
    }

    /**
     * Get block
     */
    public function getBlock($hash, $verbosity = 1)
    {
        return $this->call('getblock', [$hash, $verbosity]);
    }

    /**
     * Get block template
     */
    public function getBlockTemplate($params = [])
    {
        // RinCoin requires specific rules for getblocktemplate
        if (empty($params)) {
            $params = ['rules' => ['mweb', 'segwit']];
        }
        return $this->call('getblocktemplate', [$params]);
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
     * Get difficulty
     */
    public function getDifficulty()
    {
        return $this->call('getdifficulty');
    }

    /**
     * Get mempool info
     */
    public function getMempoolInfo()
    {
        return $this->call('getmempoolinfo');
    }

    /**
     * Get raw transaction
     */
    public function getRawTransaction($txid, $verbose = true)
    {
        return $this->call('getrawtransaction', [$txid, $verbose]);
    }

    /**
     * Send raw transaction
     */
    public function sendRawTransaction($hex)
    {
        return $this->call('sendrawtransaction', [$hex]);
    }

    /**
     * Get transaction
     */
    public function getTransaction($txid)
    {
        return $this->call('gettransaction', [$txid]);
    }

    /**
     * Get balance
     */
    public function getBalance($account = '*', $minconf = 1)
    {
        return $this->call('getbalance', [$account, $minconf]);
    }

    /**
     * Get new address
     */
    public function getNewAddress($label = '', $address_type = 'legacy')
    {
        return $this->call('getnewaddress', [$label, $address_type]);
    }

    /**
     * Validate address
     */
    public function validateAddress($address)
    {
        return $this->call('validateaddress', [$address]);
    }

    /**
     * Get wallet info
     */
    public function getWalletInfo()
    {
        return $this->call('getwalletinfo');
    }

    /**
     * List transactions
     */
    public function listTransactions($account = '*', $count = 10, $skip = 0)
    {
        return $this->call('listtransactions', [$account, $count, $skip]);
    }

    /**
     * Get unspent outputs
     */
    public function listUnspent($minconf = 1, $maxconf = 9999999, $addresses = [])
    {
        return $this->call('listunspent', [$minconf, $maxconf, $addresses]);
    }

    /**
     * Create raw transaction
     */
    public function createRawTransaction($inputs, $outputs, $locktime = 0, $replaceable = false)
    {
        return $this->call('createrawtransaction', [$inputs, $outputs, $locktime, $replaceable]);
    }

    /**
     * Sign raw transaction
     */
    public function signRawTransactionWithKey($hex, $privkeys, $prevtxs = [], $sighashtype = 'ALL')
    {
        return $this->call('signrawtransactionwithkey', [$hex, $privkeys, $prevtxs, $sighashtype]);
    }

    /**
     * Test connection
     */
    public function testConnection()
    {
        try {
            $info = $this->getBlockchainInfo();
            return [
                'success' => true,
                'chain' => $info['chain'] ?? 'unknown',
                'blocks' => $info['blocks'] ?? 0,
                'synced' => !($info['initialblockdownload'] ?? true)
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Check if daemon is synced
     */
    public function isSynced()
    {
        try {
            $info = $this->getBlockchainInfo();
            return !($info['initialblockdownload'] ?? true);
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Get sync progress
     */
    public function getSyncProgress()
    {
        try {
            $info = $this->getBlockchainInfo();
            return $info['verificationprogress'] ?? 0;
        } catch (\Exception $e) {
            return 0;
        }
    }
}
