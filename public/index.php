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

// Load configuration
$config = json_decode(file_get_contents(__DIR__ . '/../config/config.json'), true);

// Set timezone
date_default_timezone_set('UTC');

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($config['pool']['name']); ?> - Yenten Mining Pool</title>
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
                <i class="fas fa-coins"></i> <?php echo htmlspecialchars($config['pool']['name']); ?>
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
                        <i class="fas fa-server"></i> Yenten Mining Pool
                    </h1>
                    <p class="lead">Mine Yenten (YTN) with our reliable and efficient mining pool</p>
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
                        <h3 id="pool-fee"><?php echo $config['pool']['fee_percent']; ?>%</h3>
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
                            <p><strong>Pool URL:</strong> <?php echo htmlspecialchars($config['pool']['url']); ?></p>
                            <p><strong>Minimum Payout:</strong> <?php echo $config['pool']['minimum_payout']; ?> YTN</p>
                            <p><strong>Payout Threshold:</strong> <?php echo $config['pool']['payout_threshold']; ?> YTN</p>
                        </div>
                        <div class="col-md-6">
                            <p><strong>Block Reward:</strong> <?php echo $config['pool']['block_reward']; ?> YTN</p>
                            <p><strong>Pool Fee:</strong> <?php echo $config['pool']['fee_percent']; ?>%</p>
                            <p><strong>Payout Method:</strong> PPLNS</p>
                        </div>
                    </div>
                </div>

                <!-- Mining Configuration -->
                <div class="mining-ports rounded p-4 mb-4">
                    <h3><i class="fas fa-cogs"></i> Mining Configuration</h3>
                    <p><strong>Stratum Server:</strong> <?php echo htmlspecialchars($config['pool']['url']); ?></p>
                    <p><strong>Ports:</strong></p>
                    <ul>
                        <?php foreach ($config['pool']['stratum_ports'] as $port): ?>
                        <li>Port <?php echo $port; ?> - Difficulty: <?php echo $port / 1000; ?></li>
                        <?php endforeach; ?>
                    </ul>
                    <p><strong>Username:</strong> Your Yenten wallet address</p>
                    <p><strong>Password:</strong> x (or any password)</p>
                </div>
            </div>

            <div class="col-lg-4">
                <!-- Quick Start -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h5><i class="fas fa-rocket"></i> Quick Start</h5>
                    </div>
                    <div class="card-body">
                        <p>Start mining Yenten with your favorite miner:</p>
                        <div class="mb-3">
                            <label class="form-label">Your Yenten Address:</label>
                            <input type="text" class="form-control" id="wallet-address" placeholder="Enter your YTN address">
                        </div>
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
            <p>&copy; 2024 <?php echo htmlspecialchars($config['pool']['name']); ?>. All rights reserved.</p>
            <p>Mining Yenten (YTN) - Secure, Reliable, Profitable</p>
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Generate mining command
        function generateMiningCommand() {
            const walletAddress = document.getElementById('wallet-address').value;
            if (!walletAddress) {
                alert('Please enter your Yenten wallet address');
                return;
            }
            
            const command = `ccminer -a yescryptr16 -o stratum+tcp://<?php echo parse_url($config['pool']['url'], PHP_URL_HOST); ?>:3333 -u ${walletAddress} -p x`;
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
