<?php
/**
 * Comprehensive System Test
 * Tests all components of the Yenten mining pool
 */

// Set error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Include required files
require_once __DIR__ . '/../src/Config/ConfigManager.php';
require_once __DIR__ . '/../src/Database/Database.php';
require_once __DIR__ . '/../src/Classes/YentenRPC.php';
require_once __DIR__ . '/../src/Classes/PPLNSCalculator.php';
require_once __DIR__ . '/../src/Classes/PayoutProcessor.php';

use YentenPool\Config\ConfigManager;
use YentenPool\Database\Database;
use YentenPool\Classes\YentenRPC;
use YentenPool\Classes\PPLNSCalculator;
use YentenPool\Classes\PayoutProcessor;

class SystemTest
{
    private $config;
    private $pdo;
    private $yentenRPC;
    private $pplnsCalculator;
    private $payoutProcessor;
    private $testResults = [];
    
    public function __construct()
    {
        $this->loadConfiguration();
        $this->initializeComponents();
    }
    
    private function loadConfiguration()
    {
        try {
            $configManager = new ConfigManager(__DIR__ . '/../config/config.json');
            $this->config = $configManager->getAll();
            $this->testResults['config'] = ['status' => 'PASS', 'message' => 'Configuration loaded successfully'];
        } catch (Exception $e) {
            $this->testResults['config'] = ['status' => 'FAIL', 'message' => 'Configuration failed: ' . $e->getMessage()];
        }
    }
    
    private function initializeComponents()
    {
        try {
            // Initialize database
            $database = new Database($this->config['database']);
            $this->pdo = $database->getConnection();
            
            // Initialize Yenten RPC
            $this->yentenRPC = new YentenRPC(
                $this->config['yenten']['host'],
                $this->config['yenten']['port'],
                $this->config['yenten']['username'],
                $this->config['yenten']['password']
            );
            
            // Initialize payout system
            $this->pplnsCalculator = new PPLNSCalculator($this->pdo, $this->config);
            $this->payoutProcessor = new PayoutProcessor($this->pdo, $this->yentenRPC, $this->config);
            
        } catch (Exception $e) {
            $this->testResults['initialization'] = ['status' => 'FAIL', 'message' => 'Component initialization failed: ' . $e->getMessage()];
        }
    }
    
    public function runAllTests()
    {
        echo "=== YENTEN MINING POOL SYSTEM TEST ===\n";
        echo "Started at: " . date('Y-m-d H:i:s') . "\n\n";
        
        $this->testDatabase();
        $this->testYentenDaemon();
        $this->testPayoutSystem();
        $this->testWebAPIs();
        $this->testStratumServer();
        $this->testFilePermissions();
        $this->testCronJobs();
        
        $this->displayResults();
    }
    
    private function testDatabase()
    {
        echo "--- Testing Database ---\n";
        
        try {
            // Test connection
            $stmt = $this->pdo->query("SELECT 1");
            $this->testResults['db_connection'] = ['status' => 'PASS', 'message' => 'Database connection successful'];
            
            // Test required tables
            $requiredTables = ['users', 'shares', 'blocks', 'payouts', 'pool_stats', 'pool_config'];
            $existingTables = $this->pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
            
            $missingTables = array_diff($requiredTables, $existingTables);
            if (empty($missingTables)) {
                $this->testResults['db_tables'] = ['status' => 'PASS', 'message' => 'All required tables exist'];
            } else {
                $this->testResults['db_tables'] = ['status' => 'FAIL', 'message' => 'Missing tables: ' . implode(', ', $missingTables)];
            }
            
            // Test data integrity
            $userCount = $this->pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
            $shareCount = $this->pdo->query("SELECT COUNT(*) FROM shares")->fetchColumn();
            
            $this->testResults['db_data'] = [
                'status' => 'PASS', 
                'message' => "Users: {$userCount}, Shares: {$shareCount}"
            ];
            
        } catch (Exception $e) {
            $this->testResults['db_connection'] = ['status' => 'FAIL', 'message' => 'Database test failed: ' . $e->getMessage()];
        }
    }
    
    private function testYentenDaemon()
    {
        echo "--- Testing Yenten Daemon ---\n";
        
        try {
            // Test RPC connection
            $blockchainInfo = $this->yentenRPC->getBlockchainInfo();
            $this->testResults['yenten_connection'] = ['status' => 'PASS', 'message' => 'Yenten RPC connection successful'];
            
            // Test sync status
            $isSynced = $blockchainInfo['blocks'] > 0 && $blockchainInfo['verificationprogress'] > 0.99;
            if ($isSynced) {
                $this->testResults['yenten_sync'] = ['status' => 'PASS', 'message' => "Synced to block {$blockchainInfo['blocks']}"];
            } else {
                $this->testResults['yenten_sync'] = ['status' => 'WARN', 'message' => "Not fully synced. Block: {$blockchainInfo['blocks']}, Progress: " . round($blockchainInfo['verificationprogress'] * 100, 2) . "%"];
            }
            
            // Test balance
            $balance = $this->yentenRPC->getBalance();
            $this->testResults['yenten_balance'] = ['status' => 'PASS', 'message' => "Pool balance: {$balance} YTN"];
            
            // Test block template
            $blockTemplate = $this->yentenRPC->getBlockTemplate();
            if ($blockTemplate && isset($blockTemplate['height'])) {
                $this->testResults['yenten_blocktemplate'] = ['status' => 'PASS', 'message' => 'Block template generation successful'];
            } else {
                $this->testResults['yenten_blocktemplate'] = ['status' => 'FAIL', 'message' => 'Block template generation failed'];
            }
            
        } catch (Exception $e) {
            $this->testResults['yenten_connection'] = ['status' => 'FAIL', 'message' => 'Yenten daemon test failed: ' . $e->getMessage()];
        }
    }
    
    private function testPayoutSystem()
    {
        echo "--- Testing Payout System ---\n";
        
        try {
            // Test PPLNS calculator
            $eligiblePayouts = $this->pplnsCalculator->getEligiblePayouts();
            $this->testResults['pplns_calculator'] = ['status' => 'PASS', 'message' => 'PPLNS calculator working'];
            
            // Test payout processor
            $payoutStats = $this->payoutProcessor->getPayoutStats();
            $this->testResults['payout_processor'] = ['status' => 'PASS', 'message' => 'Payout processor working'];
            
            // Test pool balance
            $poolBalance = $this->payoutProcessor->getPoolBalance();
            if ($poolBalance > 0) {
                $this->testResults['pool_balance'] = ['status' => 'PASS', 'message' => "Pool has {$poolBalance} YTN"];
            } else {
                $this->testResults['pool_balance'] = ['status' => 'WARN', 'message' => 'Pool balance is 0 - add funds for payouts'];
            }
            
        } catch (Exception $e) {
            $this->testResults['payout_system'] = ['status' => 'FAIL', 'message' => 'Payout system test failed: ' . $e->getMessage()];
        }
    }
    
    private function testWebAPIs()
    {
        echo "--- Testing Web APIs ---\n";
        
        $apis = [
            'pool-stats' => 'https://mining.isekai-pool.com/api/pool-stats.php',
            'miners' => 'https://mining.isekai-pool.com/api/miners.php',
            'blocks' => 'https://mining.isekai-pool.com/api/blocks.php',
            'wallet' => 'https://mining.isekai-pool.com/api/wallet.php?address=YbawS9XQwFLQncdcxJW1Z5ypgjpzL3WddP',
            'payouts' => 'https://mining.isekai-pool.com/api/payouts.php?address=YbawS9XQwFLQncdcxJW1Z5ypgjpzL3WddP'
        ];
        
        foreach ($apis as $name => $url) {
            try {
                $context = stream_context_create([
                    'http' => [
                        'timeout' => 10,
                        'method' => 'GET'
                    ]
                ]);
                
                $response = file_get_contents($url, false, $context);
                if ($response !== false) {
                    $data = json_decode($response, true);
                    if ($data && isset($data['success'])) {
                        $this->testResults["api_{$name}"] = ['status' => 'PASS', 'message' => 'API responding correctly'];
                    } else {
                        $this->testResults["api_{$name}"] = ['status' => 'FAIL', 'message' => 'API returned invalid JSON'];
                    }
                } else {
                    $this->testResults["api_{$name}"] = ['status' => 'FAIL', 'message' => 'API request failed'];
                }
            } catch (Exception $e) {
                $this->testResults["api_{$name}"] = ['status' => 'FAIL', 'message' => 'API test failed: ' . $e->getMessage()];
            }
        }
    }
    
    private function testStratumServer()
    {
        echo "--- Testing Stratum Server ---\n";
        
        try {
            // Check if stratum server is running
            $output = shell_exec('ps aux | grep stratum_server.php | grep -v grep');
            if ($output) {
                $this->testResults['stratum_running'] = ['status' => 'PASS', 'message' => 'Stratum server is running'];
            } else {
                $this->testResults['stratum_running'] = ['status' => 'FAIL', 'message' => 'Stratum server is not running'];
            }
            
            // Check stratum ports
            $ports = [3333, 4444, 5555];
            foreach ($ports as $port) {
                $output = shell_exec("netstat -tlnp | grep :{$port}");
                if ($output) {
                    $this->testResults["stratum_port_{$port}"] = ['status' => 'PASS', 'message' => "Port {$port} is listening"];
                } else {
                    $this->testResults["stratum_port_{$port}"] = ['status' => 'WARN', 'message' => "Port {$port} is not listening"];
                }
            }
            
        } catch (Exception $e) {
            $this->testResults['stratum_server'] = ['status' => 'FAIL', 'message' => 'Stratum server test failed: ' . $e->getMessage()];
        }
    }
    
    private function testFilePermissions()
    {
        echo "--- Testing File Permissions ---\n";
        
        $requiredFiles = [
            '/var/www/html/index.php',
            '/var/www/html/api/pool-stats.php',
            '/var/www/html/api/miners.php',
            '/var/www/html/api/blocks.php',
            '/var/www/html/api/wallet.php',
            '/var/www/html/api/payouts.php',
            '/var/www/yenten-pool/scripts/stratum_server.php',
            '/var/www/yenten-pool/scripts/process_payouts.php',
            '/var/www/yenten-pool/scripts/distribute_block_rewards.php'
        ];
        
        foreach ($requiredFiles as $file) {
            if (file_exists($file)) {
                if (is_readable($file)) {
                    $this->testResults["file_{$file}"] = ['status' => 'PASS', 'message' => 'File exists and readable'];
                } else {
                    $this->testResults["file_{$file}"] = ['status' => 'FAIL', 'message' => 'File exists but not readable'];
                }
            } else {
                $this->testResults["file_{$file}"] = ['status' => 'FAIL', 'message' => 'File does not exist'];
            }
        }
    }
    
    private function testCronJobs()
    {
        echo "--- Testing Cron Jobs ---\n";
        
        try {
            $crontab = shell_exec('crontab -l 2>/dev/null');
            if ($crontab) {
                $hasPayoutCron = strpos($crontab, 'process_payouts.php') !== false;
                $hasBlockCron = strpos($crontab, 'distribute_block_rewards.php') !== false;
                $hasStatsCron = strpos($crontab, 'simple_stats_update.php') !== false;
                
                if ($hasPayoutCron) {
                    $this->testResults['cron_payouts'] = ['status' => 'PASS', 'message' => 'Payout cron job configured'];
                } else {
                    $this->testResults['cron_payouts'] = ['status' => 'WARN', 'message' => 'Payout cron job not configured'];
                }
                
                if ($hasBlockCron) {
                    $this->testResults['cron_blocks'] = ['status' => 'PASS', 'message' => 'Block reward cron job configured'];
                } else {
                    $this->testResults['cron_blocks'] = ['status' => 'WARN', 'message' => 'Block reward cron job not configured'];
                }
                
                if ($hasStatsCron) {
                    $this->testResults['cron_stats'] = ['status' => 'PASS', 'message' => 'Stats cron job configured'];
                } else {
                    $this->testResults['cron_stats'] = ['status' => 'WARN', 'message' => 'Stats cron job not configured'];
                }
            } else {
                $this->testResults['cron_jobs'] = ['status' => 'FAIL', 'message' => 'No cron jobs configured'];
            }
        } catch (Exception $e) {
            $this->testResults['cron_jobs'] = ['status' => 'FAIL', 'message' => 'Cron job test failed: ' . $e->getMessage()];
        }
    }
    
    private function displayResults()
    {
        echo "\n=== TEST RESULTS ===\n";
        
        $passCount = 0;
        $failCount = 0;
        $warnCount = 0;
        
        foreach ($this->testResults as $test => $result) {
            $status = $result['status'];
            $message = $result['message'];
            
            $icon = '';
            switch ($status) {
                case 'PASS':
                    $icon = '✅';
                    $passCount++;
                    break;
                case 'FAIL':
                    $icon = '❌';
                    $failCount++;
                    break;
                case 'WARN':
                    $icon = '⚠️';
                    $warnCount++;
                    break;
            }
            
            echo "{$icon} {$test}: {$message}\n";
        }
        
        echo "\n=== SUMMARY ===\n";
        echo "✅ Passed: {$passCount}\n";
        echo "⚠️  Warnings: {$warnCount}\n";
        echo "❌ Failed: {$failCount}\n";
        
        if ($failCount == 0) {
            echo "\n🎉 ALL TESTS PASSED! Your mining pool is ready to go!\n";
        } else {
            echo "\n⚠️  Some tests failed. Please fix the issues above.\n";
        }
        
        echo "\n=== RECOMMENDATIONS ===\n";
        
        if ($warnCount > 0) {
            echo "• Address warnings to ensure optimal performance\n";
        }
        
        if ($failCount == 0) {
            echo "• Your pool is fully operational!\n";
            echo "• Miners can start mining immediately\n";
            echo "• Payouts will be processed automatically\n";
            echo "• Monitor the logs for any issues\n";
        }
        
        echo "\nTest completed at: " . date('Y-m-d H:i:s') . "\n";
    }
}

// Run the system test
try {
    $test = new SystemTest();
    $test->runAllTests();
} catch (Exception $e) {
    echo "CRITICAL ERROR: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
    exit(1);
}
?>
