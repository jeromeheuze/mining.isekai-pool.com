<?php
/**
 * Pool Statistics Page
 * Real-time statistics for the mining pool
 */

// Load configuration
$configFile = __DIR__ . '/../config/config.json';
$config = [];

if (file_exists($configFile)) {
    $configJson = file_get_contents($configFile);
    $config = json_decode($configJson, true);
}

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

// Set timezone
date_default_timezone_set('UTC');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pool Statistics - <?php echo htmlspecialchars(getConfig('pool.name', 'Isekai Multi-Coin Pool')); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .stats-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }
        .stat-card {
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.2);
        }
        .coin-stats {
            background: #f8f9fa;
        }
        .hashrate-display {
            font-family: 'Courier New', monospace;
            font-size: 1.2em;
        }
        .refresh-indicator {
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 1000;
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
                <a class="nav-link" href="/">Home</a>
                <a class="nav-link active" href="/stats.php">Pool Stats</a>
                <a class="nav-link" href="/miners.php">Top Miners</a>
                <a class="nav-link" href="/blocks.php">Recent Blocks</a>
                <a class="nav-link" href="/help.php">Help</a>
            </div>
        </div>
    </nav>

    <!-- Refresh Indicator -->
    <div class="refresh-indicator">
        <div class="alert alert-info alert-dismissible fade show" role="alert">
            <i class="fas fa-sync-alt fa-spin"></i> Auto-refresh every 30 seconds
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    </div>

    <!-- Header -->
    <div class="stats-header py-5">
        <div class="container">
            <div class="row text-center">
                <div class="col-12">
                    <h1 class="display-4 mb-4">
                        <i class="fas fa-chart-line"></i> Pool Statistics
                    </h1>
                    <p class="lead">Real-time mining pool statistics and performance metrics</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Total Pool Statistics -->
    <div class="container my-5">
        <div class="row">
            <div class="col-12">
                <h2 class="mb-4"><i class="fas fa-globe"></i> Total Pool Statistics</h2>
            </div>
        </div>
        <div class="row">
            <div class="col-md-4 mb-4">
                <div class="card text-center">
                    <div class="card-body">
                        <i class="fas fa-tachometer-alt fa-3x text-primary mb-3"></i>
                        <h3 class="card-title hashrate-display" id="total-hashrate">0 H/s</h3>
                        <p class="card-text">Total Pool Hashrate</p>
                    </div>
                </div>
            </div>
            <div class="col-md-4 mb-4">
                <div class="card text-center">
                    <div class="card-body">
                        <i class="fas fa-users fa-3x text-success mb-3"></i>
                        <h3 class="card-title" id="total-miners">0</h3>
                        <p class="card-text">Active Miners</p>
                    </div>
                </div>
            </div>
            <div class="col-md-4 mb-4">
                <div class="card text-center">
                    <div class="card-body">
                        <i class="fas fa-cube fa-3x text-warning mb-3"></i>
                        <h3 class="card-title" id="total-blocks">0</h3>
                        <p class="card-text">Blocks Found</p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Individual Coin Statistics -->
    <div class="coin-stats py-5">
        <div class="container">
            <div class="row">
                <div class="col-12">
                    <h2 class="mb-4"><i class="fas fa-coins"></i> Coin Statistics</h2>
                </div>
            </div>
            
            <!-- Yenten Stats -->
            <div class="row mb-4">
                <div class="col-12">
                    <div class="card">
                        <div class="card-header bg-warning text-dark">
                            <h5 class="mb-0"><i class="fas fa-coins"></i> Yenten (YTN) Statistics</h5>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-3 text-center">
                                    <h6>Pool Hashrate</h6>
                                    <p class="hashrate-display" id="yenten-hashrate">0 H/s</p>
                                </div>
                                <div class="col-md-3 text-center">
                                    <h6>Active Miners</h6>
                                    <p id="yenten-miners">0</p>
                                </div>
                                <div class="col-md-3 text-center">
                                    <h6>Blocks Found</h6>
                                    <p id="yenten-blocks">0</p>
                                </div>
                                <div class="col-md-3 text-center">
                                    <h6>Block Height</h6>
                                    <p id="yenten-height">0</p>
                                </div>
                            </div>
                            <div class="row mt-3">
                                <div class="col-md-4 text-center">
                                    <h6>Network Difficulty</h6>
                                    <p id="yenten-difficulty">0</p>
                                </div>
                                <div class="col-md-4 text-center">
                                    <h6>Network Hashrate</h6>
                                    <p class="hashrate-display" id="yenten-network-hashrate">0 H/s</p>
                                </div>
                                <div class="col-md-4 text-center">
                                    <h6>Last Block</h6>
                                    <p id="yenten-last-block">Never</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- KOTO Stats -->
            <div class="row mb-4">
                <div class="col-12">
                    <div class="card">
                        <div class="card-header bg-success text-white">
                            <h5 class="mb-0"><i class="fas fa-coins"></i> KOTO (KOTO) Statistics</h5>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-3 text-center">
                                    <h6>Pool Hashrate</h6>
                                    <p class="hashrate-display" id="koto-hashrate">0 H/s</p>
                                </div>
                                <div class="col-md-3 text-center">
                                    <h6>Active Miners</h6>
                                    <p id="koto-miners">0</p>
                                </div>
                                <div class="col-md-3 text-center">
                                    <h6>Blocks Found</h6>
                                    <p id="koto-blocks">0</p>
                                </div>
                                <div class="col-md-3 text-center">
                                    <h6>Block Height</h6>
                                    <p id="koto-height">0</p>
                                </div>
                            </div>
                            <div class="row mt-3">
                                <div class="col-md-4 text-center">
                                    <h6>Network Difficulty</h6>
                                    <p id="koto-difficulty">0</p>
                                </div>
                                <div class="col-md-4 text-center">
                                    <h6>Network Hashrate</h6>
                                    <p class="hashrate-display" id="koto-network-hashrate">0 H/s</p>
                                </div>
                                <div class="col-md-4 text-center">
                                    <h6>Last Block</h6>
                                    <p id="koto-last-block">Never</p>
                                </div>
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
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">
                        <h5><i class="fas fa-info-circle"></i> Pool Information</h5>
                    </div>
                    <div class="card-body">
                        <p><strong>Pool Fee:</strong> <?php echo getConfig('pool.fee_percent', 1.0); ?>%</p>
                        <p><strong>Minimum Payout:</strong> 0.1 YTN / 0.1 KOTO</p>
                        <p><strong>Payout Threshold:</strong> 0.5 YTN / 0.5 KOTO</p>
                        <p><strong>Payout Method:</strong> PPLNS</p>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">
                        <h5><i class="fas fa-clock"></i> Last Updated</h5>
                    </div>
                    <div class="card-body">
                        <p id="last-updated">Loading...</p>
                        <small class="text-muted">Statistics are updated every 30 seconds</small>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Footer -->
    <footer class="bg-dark text-light py-4 mt-5">
        <div class="container text-center">
            <p>&copy; <?=date('Y');?> <?php echo htmlspecialchars(getConfig('pool.name', 'Isekai Multi-Coin Pool')); ?>. All rights reserved.</p>
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Format hashrate
        function formatHashrate(hashrate) {
            if (hashrate === 0) return '0 H/s';
            
            const units = ['H/s', 'KH/s', 'MH/s', 'GH/s', 'TH/s'];
            let unitIndex = 0;
            
            while (hashrate >= 1000 && unitIndex < units.length - 1) {
                hashrate /= 1000;
                unitIndex++;
            }
            
            return hashrate.toFixed(2) + ' ' + units[unitIndex];
        }

        // Format number
        function formatNumber(num) {
            return new Intl.NumberFormat().format(num);
        }

        // Format timestamp
        function formatTimestamp(timestamp) {
            if (!timestamp) return 'Never';
            return new Date(timestamp).toLocaleString();
        }

        // Update statistics
        function updateStats() {
            fetch('/api/pool-stats.php')
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        // Update total stats
                        document.getElementById('total-hashrate').textContent = formatHashrate(data.total.pool_hashrate);
                        document.getElementById('total-miners').textContent = formatNumber(data.total.active_miners);
                        document.getElementById('total-blocks').textContent = formatNumber(data.total.blocks_found);

                        // Update individual coin stats
                        ['yenten', 'koto'].forEach(coin => {
                            if (data.coins[coin]) {
                                const coinData = data.coins[coin];
                                
                                document.getElementById(`${coin}-hashrate`).textContent = formatHashrate(coinData.pool_hashrate);
                                document.getElementById(`${coin}-miners`).textContent = formatNumber(coinData.active_miners);
                                document.getElementById(`${coin}-blocks`).textContent = formatNumber(coinData.blocks_found);
                                document.getElementById(`${coin}-height`).textContent = formatNumber(coinData.block_height);
                                document.getElementById(`${coin}-difficulty`).textContent = formatNumber(coinData.network_difficulty);
                                document.getElementById(`${coin}-network-hashrate`).textContent = formatHashrate(coinData.network_hashrate);
                                document.getElementById(`${coin}-last-block`).textContent = formatTimestamp(coinData.last_block_time);
                            }
                        });

                        // Update last updated time
                        document.getElementById('last-updated').textContent = new Date().toLocaleString();
                    } else {
                        console.error('Failed to fetch stats:', data.error);
                    }
                })
                .catch(error => {
                    console.error('Error fetching stats:', error);
                });
        }

        // Initialize
        document.addEventListener('DOMContentLoaded', function() {
            updateStats();
            setInterval(updateStats, 30000); // Update every 30 seconds
        });
    </script>
</body>
</html>
