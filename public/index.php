<?php
/**
 * Yenten Mining Pool - Main Dashboard
 * https://mining.isekai-pool.com
 */

// Set error reporting for development
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Start session
session_start();

// Load configuration with error handling
$config = [];
$configFile = __DIR__ . '/../config/config.json';

if (file_exists($configFile)) {
    $configJson = file_get_contents($configFile);
    $config = json_decode($configJson, true);
    
    if (json_last_error() !== JSON_ERROR_NONE) {
        error_log("Config JSON error: " . json_last_error_msg());
        $config = [];
    }
}

// Set default values if config is empty
if (empty($config)) {
    $config = [
        'pool' => [
            'name' => 'Isekai Yenten Pool',
            'url' => 'https://mining.isekai-pool.com',
            'fee_percent' => 1.0,
            'minimum_payout' => 0.1,
            'payout_threshold' => 0.5,
            'block_reward' => 50.0,
            'stratum_ports' => [3333, 4444, 5555]
        ]
    ];
}

// Set timezone
date_default_timezone_set('UTC');

// Helper function to safely get config values
function getConfig($key, $default = '') {
    global $config;
    $keys = explode('.', $key);
    $value = $config;
    
    foreach ($keys as $k) {
        if (!isset($value[$k])) {
            return $default;
        }
        $value = $value[$k];
    }
    
    return $value;
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars(getConfig('pool.name', 'Isekai Multi-Coin Pool')); ?> - Yenten & KOTO Mining Pool</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .mining-stats {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }
        .stat-card {
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.2);
        }
        .pool-info {
            background: #f8f9fa;
        }
        .mining-ports {
            background: #e9ecef;
        }
    </style>
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark">
        <div class="container">
            <a class="navbar-brand" href="/">
                <i class="fas fa-coins"></i> <?php echo htmlspecialchars(getConfig('pool.name', 'Isekai Multi-Coin Pool')); ?>
            </a>
            <div class="navbar-nav ms-auto">
                <a class="nav-link" href="#stats">Pool Stats</a>
                <a class="nav-link" href="#miners">Top Miners</a>
                <a class="nav-link" href="#blocks">Recent Blocks</a>
                <a class="nav-link" href="#help">Help</a>
            </div>
        </div>
    </nav>

    <!-- Hero Section -->
    <div class="mining-stats py-5">
        <div class="container">
            <div class="row text-center">
                <div class="col-12">
                    <h1 class="display-4 mb-4">
                        <i class="fas fa-server"></i> Multi-Coin Mining Pool
                    </h1>
                    <p class="lead">Mine Yenten (YTN) and KOTO (KOTO) with our reliable and efficient mining pool</p>
                </div>
            </div>
            
            <!-- Pool Statistics -->
            <div class="row mt-5">
                <div class="col-md-3 mb-4">
                    <div class="stat-card rounded p-4 text-center">
                        <i class="fas fa-tachometer-alt fa-2x mb-3"></i>
                        <h3 id="pool-hashrate">0 H/s</h3>
                        <p class="mb-0">Pool Hashrate</p>
                    </div>
                </div>
                <div class="col-md-3 mb-4">
                    <div class="stat-card rounded p-4 text-center">
                        <i class="fas fa-users fa-2x mb-3"></i>
                        <h3 id="active-miners">0</h3>
                        <p class="mb-0">Active Miners</p>
                    </div>
                </div>
                <div class="col-md-3 mb-4">
                    <div class="stat-card rounded p-4 text-center">
                        <i class="fas fa-cube fa-2x mb-3"></i>
                        <h3 id="blocks-found">0</h3>
                        <p class="mb-0">Blocks Found</p>
                    </div>
                </div>
                <div class="col-md-3 mb-4">
                    <div class="stat-card rounded p-4 text-center">
                        <i class="fas fa-percentage fa-2x mb-3"></i>
                        <h3 id="pool-fee"><?php echo getConfig('pool.fee_percent', 1.0); ?>%</h3>
                        <p class="mb-0">Pool Fee</p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Pool Information -->
    <div class="container my-5">
        <div class="row">
            <div class="col-lg-8">
                <div class="pool-info rounded p-4 mb-4">
                    <h3><i class="fas fa-info-circle"></i> Pool Information</h3>
                    <div class="row">
                        <div class="col-md-6">
                            <p><strong>Pool URL:</strong> <?php echo htmlspecialchars(getConfig('pool.url', 'https://mining.isekai-pool.com')); ?></p>
                            <p><strong>Supported Coins:</strong> Yenten (YTN) & KOTO (KOTO)</p>
                            <p><strong>Pool Fee:</strong> <?php echo getConfig('pool.fee_percent', 1.0); ?>%</p>
                        </div>
                        <div class="col-md-6">
                            <p><strong>Payout Method:</strong> PPLNS</p>
                            <p><strong>Minimum Payout:</strong> 0.1 YTN / 0.1 KOTO</p>
                            <p><strong>Payout Threshold:</strong> 0.5 YTN / 0.5 KOTO</p>
                        </div>
                    </div>
                </div>

                <!-- Mining Configuration -->
                <div class="mining-ports rounded p-4 mb-4">
                    <h3><i class="fas fa-cogs"></i> Mining Configuration</h3>
                    <p><strong>Stratum Server:</strong> <?php echo htmlspecialchars(getConfig('pool.url', 'https://mining.isekai-pool.com')); ?></p>
                    
                    <!-- Yenten Mining -->
                    <div class="mb-4">
                        <h5><i class="fas fa-coins text-warning"></i> Yenten (YTN) Mining</h5>
                        <p><strong>Algorithm:</strong> YescryptR16</p>
                        <p><strong>Port:</strong> 3333</p>
                        <p><strong>Username:</strong> Your Yenten wallet address</p>
                        <p><strong>Password:</strong> x (or any password)</p>
                        <div class="alert alert-info">
                            <strong>Example:</strong> ccminer -a yescryptr16 -o stratum+tcp://mining.isekai-pool.com:3333 -u YOUR_YTN_ADDRESS -p x
                        </div>
                    </div>

                    <!-- KOTO Mining -->
                    <div class="mb-4">
                        <h5><i class="fas fa-coins text-success"></i> KOTO (KOTO) Mining</h5>
                        <p><strong>Algorithm:</strong> Yescrypt</p>
                        <p><strong>Port:</strong> 4444</p>
                        <p><strong>Username:</strong> Your KOTO wallet address</p>
                        <p><strong>Password:</strong> x (or any password)</p>
                        <div class="alert alert-info">
                            <strong>Example:</strong> ccminer -a yescrypt -o stratum+tcp://mining.isekai-pool.com:4444 -u YOUR_KOTO_ADDRESS -p x
                        </div>
                    </div>

                    <!-- Additional Ports -->
                    <div class="mb-4">
                        <h5><i class="fas fa-server text-primary"></i> Additional Ports</h5>
                        <p><strong>Port 5555:</strong> Backup/Alternative (Default: Yenten)</p>
                    </div>
                </div>
            </div>

            <div class="col-lg-4">
                <!-- Quick Start -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h5><i class="fas fa-rocket"></i> Quick Start</h5>
                    </div>
                    <div class="card-body">
                        <p>Start mining with your favorite miner:</p>
                        
                        <!-- Coin Selection -->
                        <div class="mb-3">
                            <label class="form-label">Select Coin:</label>
                            <select class="form-select" id="coin-select" onchange="updateMiningForm()">
                                <option value="yenten">Yenten (YTN) - YescryptR16</option>
                                <option value="koto">KOTO (KOTO) - Yescrypt</option>
                            </select>
                        </div>

                        <!-- Wallet Address -->
                        <div class="mb-3">
                            <label class="form-label" id="wallet-label">Your Yenten Address:</label>
                            <input type="text" class="form-control" id="wallet-address" placeholder="Enter your wallet address">
                        </div>

                        <!-- Generate Command -->
                        <button class="btn btn-primary" onclick="generateMiningCommand()">
                            <i class="fas fa-copy"></i> Generate Command
                        </button>
                        <div class="mt-3">
                            <textarea class="form-control" id="mining-command" rows="4" readonly></textarea>
                        </div>
                    </div>
                </div>

                <!-- Recent Blocks -->
                <div class="card">
                    <div class="card-header">
                        <h5><i class="fas fa-cube"></i> Recent Blocks</h5>
                    </div>
                    <div class="card-body">
                        <div id="recent-blocks">
                            <p class="text-muted">Loading blocks...</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Footer -->
    <footer class="bg-dark text-light py-4 mt-5">
        <div class="container text-center">
            <p>&copy; 2024 <?php echo htmlspecialchars(getConfig('pool.name', 'Isekai Multi-Coin Pool')); ?>. All rights reserved.</p>
            <p>Mining Yenten (YTN) & KOTO (KOTO) - Secure, Reliable, Profitable</p>
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Update mining form based on coin selection
        function updateMiningForm() {
            const coinSelect = document.getElementById('coin-select');
            const walletLabel = document.getElementById('wallet-label');
            const walletInput = document.getElementById('wallet-address');
            
            if (coinSelect.value === 'yenten') {
                walletLabel.textContent = 'Your Yenten Address:';
                walletInput.placeholder = 'Enter your YTN address';
            } else if (coinSelect.value === 'koto') {
                walletLabel.textContent = 'Your KOTO Address:';
                walletInput.placeholder = 'Enter your KOTO address';
            }
        }

        // Generate mining command
        function generateMiningCommand() {
            const walletAddress = document.getElementById('wallet-address').value;
            const coinSelect = document.getElementById('coin-select');
            
            if (!walletAddress) {
                alert('Please enter your wallet address');
                return;
            }
            
            let command = '';
            const poolHost = '<?php echo parse_url(getConfig('pool.url', 'https://mining.isekai-pool.com'), PHP_URL_HOST); ?>';
            
            if (coinSelect.value === 'yenten') {
                command = `ccminer -a yescryptr16 -o stratum+tcp://${poolHost}:3333 -u ${walletAddress} -p x`;
            } else if (coinSelect.value === 'koto') {
                command = `ccminer -a yescrypt -o stratum+tcp://${poolHost}:4444 -u ${walletAddress} -p x`;
            }
            
            document.getElementById('mining-command').value = command;
        }

        // Auto-refresh pool stats every 30 seconds
        function updatePoolStats() {
            // This will be implemented with AJAX calls to the API
            console.log('Updating pool stats...');
        }

        // Initialize
        document.addEventListener('DOMContentLoaded', function() {
            updatePoolStats();
            setInterval(updatePoolStats, 30000); // Update every 30 seconds
        });
    </script>
</body>
</html>
