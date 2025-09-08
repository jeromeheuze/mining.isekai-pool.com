<?php
/**
 * Help & Getting Started Page
 * Provides mining setup instructions and troubleshooting
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
    <title>Help & Getting Started - <?php echo htmlspecialchars(getConfig('pool.name', 'Isekai Pool')); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .help-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }
        .code-block {
            background: #f8f9fa;
            border: 1px solid #e9ecef;
            border-radius: 0.375rem;
            padding: 1rem;
            font-family: 'Courier New', monospace;
            font-size: 0.9em;
        }
        .step-card {
            transition: transform 0.2s;
        }
        .step-card:hover {
            transform: translateY(-2px);
        }
        .coin-section {
            border-left: 4px solid #007bff;
            padding-left: 1rem;
            margin-bottom: 2rem;
        }
        .coin-yenten { border-left-color: #ffc107; }
    </style>
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark">
        <div class="container">
            <a class="navbar-brand" href="/">
                <i class="fas fa-coins"></i> <?php echo htmlspecialchars(getConfig('pool.name', 'Isekai Pool')); ?>
            </a>
            <div class="navbar-nav ms-auto">
                <a class="nav-link" href="/">Home</a>
                <a class="nav-link" href="/stats.php">Pool Stats</a>
                <a class="nav-link" href="/miners.php">Top Miners</a>
                <a class="nav-link" href="/blocks.php">Recent Blocks</a>
                <a class="nav-link" href="/wallet.php">Wallet</a>
                <a class="nav-link active" href="/help.php">Help</a>
            </div>
        </div>
    </nav>

    <!-- Header -->
    <div class="help-header py-5">
        <div class="container">
            <div class="row text-center">
                <div class="col-12">
                    <h1 class="display-4 mb-4">
                        <i class="fas fa-question-circle"></i> Help & Getting Started
                    </h1>
                    <p class="lead">Everything you need to know to start mining with our pool</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Quick Start -->
    <div class="container my-5">
        <div class="row">
            <div class="col-12">
                <h2 class="mb-4"><i class="fas fa-rocket"></i> Quick Start Guide</h2>
            </div>
        </div>
        
        <div class="row">
            <div class="col-md-4 mb-4">
                <div class="card step-card h-100">
                    <div class="card-body text-center">
                        <i class="fas fa-wallet fa-3x text-primary mb-3"></i>
                        <h5>1. Get a Wallet</h5>
                        <p>Download and set up a wallet for your chosen cryptocurrency.</p>
                    </div>
                </div>
            </div>
            <div class="col-md-4 mb-4">
                <div class="card step-card h-100">
                    <div class="card-body text-center">
                        <i class="fas fa-download fa-3x text-success mb-3"></i>
                        <h5>2. Download Miner</h5>
                        <p>Download a compatible mining software for your hardware.</p>
                    </div>
                </div>
            </div>
            <div class="col-md-4 mb-4">
                <div class="card step-card h-100">
                    <div class="card-body text-center">
                        <i class="fas fa-cogs fa-3x text-warning mb-3"></i>
                        <h5>3. Configure & Start</h5>
                        <p>Configure your miner with our pool settings and start mining!</p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Mining Configuration -->
    <div class="container my-5">
        <div class="row">
            <div class="col-12">
                <h2 class="mb-4"><i class="fas fa-cogs"></i> Mining Configuration</h2>
            </div>
        </div>

        <!-- Yenten Mining -->
        <div class="coin-section coin-yenten">
            <h3><i class="fas fa-coins text-warning"></i> Yenten (YTN) Mining</h3>
            <div class="row">
                <div class="col-md-6">
                    <h5>Pool Settings:</h5>
                    <ul>
                        <li><strong>Algorithm:</strong> YespowerR16</li>
                        <li><strong>Pool URL:</strong> stratum+tcp://<?php echo parse_url(getConfig('pool.url', 'https://mining.isekai-pool.com'), PHP_URL_HOST); ?>:3333</li>
                        <li><strong>Username:</strong> Your Yenten wallet address</li>
                        <li><strong>Password:</strong> x (or any password)</li>
                    </ul>
                </div>
                <div class="col-md-6">
                    <h5>Example Commands:</h5>
                    <div class="code-block">
                        <strong>ccminer:</strong><br>
                        ccminer -a YespowerR16 -o stratum+tcp://<?php echo parse_url(getConfig('pool.url', 'https://mining.isekai-pool.com'), PHP_URL_HOST); ?>:3333 -u YOUR_YTN_ADDRESS -p x
                    </div>
                </div>
            </div>
        </div>


    </div>

    <!-- Supported Miners -->
    <div class="container my-5">
        <div class="row">
            <div class="col-12">
                <h2 class="mb-4"><i class="fas fa-tools"></i> Supported Mining Software</h2>
            </div>
        </div>
        
        <div class="row">
            <div class="col-md-6 mb-4">
                <div class="card">
                    <div class="card-header">
                        <h5><i class="fas fa-desktop"></i> CPU Miners</h5>
                    </div>
                    <div class="card-body">
                        <ul class="list-unstyled">
                            <li><i class="fas fa-check text-success"></i> <strong>cpuminer-multi</strong> - Multi-algorithm CPU miner</li>
                            <li><i class="fas fa-check text-success"></i> <strong>minerd</strong> - Simple CPU miner</li>
                            <li><i class="fas fa-check text-success"></i> <strong>ccminer</strong> - CUDA/OpenCL miner</li>
                        </ul>
                    </div>
                </div>
            </div>
            <div class="col-md-6 mb-4">
                <div class="card">
                    <div class="card-header">
                        <h5><i class="fas fa-microchip"></i> GPU Miners</h5>
                    </div>
                    <div class="card-body">
                        <ul class="list-unstyled">
                            <li><i class="fas fa-check text-success"></i> <strong>ccminer</strong> - NVIDIA CUDA miner</li>
                            <li><i class="fas fa-check text-success"></i> <strong>sgminer</strong> - AMD OpenCL miner</li>
                            <li><i class="fas fa-check text-success"></i> <strong>claymore</strong> - Multi-GPU miner</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Pool Information -->
    <div class="container my-5">
        <div class="row">
            <div class="col-12">
                <h2 class="mb-4"><i class="fas fa-info-circle"></i> Pool Information</h2>
            </div>
        </div>
        
        <div class="row">
            <div class="col-md-6 mb-4">
                <div class="card">
                    <div class="card-header">
                        <h5><i class="fas fa-percentage"></i> Fees & Payouts</h5>
                    </div>
                    <div class="card-body">
                        <ul class="list-unstyled">
                            <li><strong>Pool Fee:</strong> <?php echo getConfig('pool.fee_percent', 1.0); ?>%</li>
                            <li><strong>Minimum Payout:</strong> 0.1 YTN</li>
                            <li><strong>Payout Threshold:</strong> 0.5 YTN</li>
                            <li><strong>Payout Method:</strong> PPLNS (Pay Per Last N Shares)</li>
                            <li><strong>Payout Frequency:</strong> Automatic (when threshold reached)</li>
                        </ul>
                    </div>
                </div>
            </div>
            <div class="col-md-6 mb-4">
                <div class="card">
                    <div class="card-header">
                        <h5><i class="fas fa-server"></i> Pool Features</h5>
                    </div>
                    <div class="card-body">
                        <ul class="list-unstyled">
                            <li><i class="fas fa-check text-success"></i> 99.9% Uptime</li>
                            <li><i class="fas fa-check text-success"></i> Low Latency</li>
                            <li><i class="fas fa-check text-success"></i> Real-time Statistics</li>
                            <li><i class="fas fa-check text-success"></i> Multi-coin Support</li>
                            <li><i class="fas fa-check text-success"></i> SSL/TLS Support</li>
                            <li><i class="fas fa-check text-success"></i> 24/7 Monitoring</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Troubleshooting -->
    <div class="container my-5">
        <div class="row">
            <div class="col-12">
                <h2 class="mb-4"><i class="fas fa-wrench"></i> Troubleshooting</h2>
            </div>
        </div>
        
        <div class="row">
            <div class="col-md-6 mb-4">
                <div class="card">
                    <div class="card-header">
                        <h5><i class="fas fa-exclamation-triangle"></i> Common Issues</h5>
                    </div>
                    <div class="card-body">
                        <h6>Connection Refused</h6>
                        <p>Check your internet connection and firewall settings. Ensure the pool ports are not blocked.</p>
                        
                        <h6>Invalid Shares</h6>
                        <p>Make sure you're using the correct algorithm and pool settings for your chosen coin.</p>
                        
                        <h6>Low Hashrate</h6>
                        <p>Check your hardware temperature and power settings. Ensure your miner is properly configured.</p>
                    </div>
                </div>
            </div>
            <div class="col-md-6 mb-4">
                <div class="card">
                    <div class="card-header">
                        <h5><i class="fas fa-question"></i> Getting Help</h5>
                    </div>
                    <div class="card-body">
                        <p>If you're experiencing issues:</p>
                        <ul>
                            <li>Check the <a href="/stats.php">Pool Stats</a> page for current status</li>
                            <li>Verify your mining configuration</li>
                            <li>Check your wallet address is correct</li>
                            <li>Ensure your miner software is up to date</li>
                        </ul>
                        <p><strong>Contact:</strong> <a href="mailto:support@isekai-pool.com">support@isekai-pool.com</a></p>
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
</body>
</html>
