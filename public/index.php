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
            'name' => 'Isekai Pool',
            'url' => 'https://mining.isekai-pool.com',
            'fee_percent' => 1.0,
            'minimum_payout' => 0.1,
            'payout_threshold' => 0.5,
            'block_reward' => 50.0,
            'stratum_ports' => [3333, 4444, 5555, 6666]
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
    <title><?php echo htmlspecialchars(getConfig('pool.name', 'Isekai Multi-Coin Pool')); ?> - Yenten, KOTO & UkkeyCoin Mining Pool</title>
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
                    <p class="lead">Mine Yenten (YTN), KOTO (KOTO), and UkkeyCoin (UKY) with our reliable and efficient mining pool</p>
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

            <!-- Blockchain Sync Status -->
            <div class="row mt-4">
                <div class="col-md-4 mb-4">
                    <div class="stat-card rounded p-4">
                        <h5><i class="fas fa-coins text-warning"></i> Yenten (YTN) Sync Status</h5>
                        <div class="progress mb-2" style="height: 25px;">
                            <div class="progress-bar bg-warning progress-bar-striped progress-bar-animated" 
                                 role="progressbar" id="yenten-progress" style="width: 0%">
                                <span id="yenten-progress-text">Loading...</span>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-6">
                                <small><strong>Block Height:</strong> <span id="yenten-height">0</span></small>
                            </div>
                            <div class="col-6">
                                <small><strong>Status:</strong> <span id="yenten-status">Connecting...</span></small>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-4 mb-4">
                    <div class="stat-card rounded p-4">
                        <h5><i class="fas fa-coins text-success"></i> KOTO (KOTO) Sync Status</h5>
                        <div class="progress mb-2" style="height: 25px;">
                            <div class="progress-bar bg-success progress-bar-striped progress-bar-animated" 
                                 role="progressbar" id="koto-progress" style="width: 0%">
                                <span id="koto-progress-text">Loading...</span>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-6">
                                <small><strong>Block Height:</strong> <span id="koto-height">0</span></small>
                            </div>
                            <div class="col-6">
                                <small><strong>Status:</strong> <span id="koto-status">Connecting...</span></small>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-4 mb-4">
                    <div class="stat-card rounded p-4">
                        <h5><i class="fas fa-coins text-info"></i> UkkeyCoin (UKY) Sync Status</h5>
                        <div class="progress mb-2" style="height: 25px;">
                            <div class="progress-bar bg-info progress-bar-striped progress-bar-animated" 
                                 role="progressbar" id="ukkeycoin-progress" style="width: 0%">
                                <span id="ukkeycoin-progress-text">Loading...</span>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-6">
                                <small><strong>Block Height:</strong> <span id="ukkeycoin-height">0</span></small>
                            </div>
                            <div class="col-6">
                                <small><strong>Status:</strong> <span id="ukkeycoin-status">Connecting...</span></small>
                            </div>
                        </div>
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
                            <p><strong>Supported Coins:</strong> Yenten (YTN), KOTO (KOTO) & UkkeyCoin (UKY)</p>
                            <p><strong>Pool Fee:</strong> <?php echo getConfig('pool.fee_percent', 1.0); ?>%</p>
                        </div>
                        <div class="col-md-6">
                            <p><strong>Payout Method:</strong> PPLNS</p>
                            <p><strong>Minimum Payout:</strong> 0.1 YTN / 0.1 KOTO / 0.1 UKY</p>
                            <p><strong>Payout Threshold:</strong> 0.5 YTN / 0.5 KOTO / 0.5 UKY</p>
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
                        <p><strong>Algorithm:</strong> YespowerR16</p>
                        <p><strong>Port:</strong> 3333</p>
                        <p><strong>Username:</strong> Your Yenten wallet address</p>
                        <p><strong>Password:</strong> x (or any password)</p>
                        <div class="alert alert-info">
                            <strong>Example:</strong> ccminer -a YespowerR16 -o stratum+tcp://mining.isekai-pool.com:3333 -u YOUR_YTN_ADDRESS -p x
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

                    <!-- UkkeyCoin Mining -->
                    <div class="mb-4">
                        <h5><i class="fas fa-coins text-info"></i> UkkeyCoin (UKY) Mining</h5>
                        <p><strong>Algorithm:</strong> YesPoWer</p>
                        <p><strong>Port:</strong> 6666</p>
                        <p><strong>Username:</strong> Your UkkeyCoin wallet address</p>
                        <p><strong>Password:</strong> x (or any password)</p>
                        <div class="alert alert-info">
                            <strong>Example:</strong> ccminer -a YesPoWer -o stratum+tcp://mining.isekai-pool.com:6666 -u YOUR_UKY_ADDRESS -p x
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
                                <option value="yenten">Yenten (YTN) - YespowerR16</option>
                                <option value="koto">KOTO (KOTO) - Yescrypt</option>
                                <option value="ukkeycoin">UkkeyCoin (UKY) - YesPoWer</option>
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
            <p>&copy; <?=date('Y');?> <?php echo htmlspecialchars(getConfig('pool.name', 'Isekai Multi-Coin Pool')); ?>. All rights reserved. Maintained by <a href="https://isekai-pool.com">Isekai Poll</a></a></p>
            <p>Mining Yenten (YTN), KOTO (KOTO) & UkkeyCoin (UKY) - Secure, Reliable, Profitable</p>
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
            } else if (coinSelect.value === 'ukkeycoin') {
                walletLabel.textContent = 'Your UkkeyCoin Address:';
                walletInput.placeholder = 'Enter your UKY address';
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
                command = `ccminer -a YespowerR16 -o stratum+tcp://${poolHost}:3333 -u ${walletAddress} -p x`;
            } else if (coinSelect.value === 'koto') {
                command = `ccminer -a yescrypt -o stratum+tcp://${poolHost}:4444 -u ${walletAddress} -p x`;
            } else if (coinSelect.value === 'ukkeycoin') {
                command = `ccminer -a YesPoWer -o stratum+tcp://${poolHost}:6666 -u ${walletAddress} -p x`;
            }
            
            document.getElementById('mining-command').value = command;
        }

        // Update blockchain sync status
        function updateBlockchainStatus() {
            // Update Yenten status
            fetch('/api/blockchain-status.php?coin=yenten')
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        const progress = Math.min(data.progress * 100, 100);
                        document.getElementById('yenten-progress').style.width = progress + '%';
                        document.getElementById('yenten-progress-text').textContent = progress.toFixed(2) + '%';
                        document.getElementById('yenten-height').textContent = data.height.toLocaleString();
                        document.getElementById('yenten-status').textContent = data.synced ? 'Synced' : 'Syncing...';
                        
                        if (data.synced) {
                            document.getElementById('yenten-progress').classList.remove('progress-bar-animated');
                            document.getElementById('yenten-progress').classList.add('bg-success');
                        }
                    } else {
                        document.getElementById('yenten-status').textContent = 'Error: ' + data.error;
                        document.getElementById('yenten-progress').classList.add('bg-danger');
                    }
                })
                .catch(error => {
                    console.error('Yenten status error:', error);
                    document.getElementById('yenten-status').textContent = 'Connection Error';
                    document.getElementById('yenten-progress').classList.add('bg-danger');
                });

            // Update KOTO status
            fetch('/api/blockchain-status.php?coin=koto')
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        const progress = Math.min(data.progress * 100, 100);
                        document.getElementById('koto-progress').style.width = progress + '%';
                        document.getElementById('koto-progress-text').textContent = progress.toFixed(2) + '%';
                        document.getElementById('koto-height').textContent = data.height.toLocaleString();
                        document.getElementById('koto-status').textContent = data.synced ? 'Synced' : 'Syncing...';
                        
                        if (data.synced) {
                            document.getElementById('koto-progress').classList.remove('progress-bar-animated');
                            document.getElementById('koto-progress').classList.add('bg-success');
                        }
                    } else {
                        document.getElementById('koto-status').textContent = 'Error: ' + data.error;
                        document.getElementById('koto-progress').classList.add('bg-danger');
                    }
                })
                .catch(error => {
                    console.error('KOTO status error:', error);
                    document.getElementById('koto-status').textContent = 'Connection Error';
                    document.getElementById('koto-progress').classList.add('bg-danger');
                });

            // Update UkkeyCoin status
            fetch('/api/blockchain-status.php?coin=ukkeycoin')
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        const progress = Math.min(data.progress * 100, 100);
                        document.getElementById('ukkeycoin-progress').style.width = progress + '%';
                        document.getElementById('ukkeycoin-progress-text').textContent = progress.toFixed(2) + '%';
                        document.getElementById('ukkeycoin-height').textContent = data.height.toLocaleString();
                        document.getElementById('ukkeycoin-status').textContent = data.synced ? 'Synced' : 'Syncing...';
                        
                        if (data.synced) {
                            document.getElementById('ukkeycoin-progress').classList.remove('progress-bar-animated');
                            document.getElementById('ukkeycoin-progress').classList.add('bg-success');
                        }
                    } else {
                        document.getElementById('ukkeycoin-status').textContent = 'Error: ' + data.error;
                        document.getElementById('ukkeycoin-progress').classList.add('bg-danger');
                    }
                })
                .catch(error => {
                    console.error('UkkeyCoin status error:', error);
                    document.getElementById('ukkeycoin-status').textContent = 'Connection Error';
                    document.getElementById('ukkeycoin-progress').classList.add('bg-danger');
                });
        }

        // Auto-refresh pool stats every 30 seconds
        function updatePoolStats() {
            // This will be implemented with AJAX calls to the API
            console.log('Updating pool stats...');
        }

        // Initialize
        document.addEventListener('DOMContentLoaded', function() {
            updatePoolStats();
            updateBlockchainStatus();
            setInterval(updatePoolStats, 30000); // Update every 30 seconds
            setInterval(updateBlockchainStatus, 10000); // Update blockchain status every 10 seconds
        });
    </script>
</body>
</html>
