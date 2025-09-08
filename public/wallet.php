<?php
/**
 * Wallet Page
 * Shows miner earnings, payouts, and statistics
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
    <title>Wallet - <?php echo htmlspecialchars(getConfig('pool.name', 'Isekai Pool')); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .wallet-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }
        .balance-card {
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.2);
        }
        .wallet-info {
            background: #f8f9fa;
        }
        .amount-display {
            font-family: 'Courier New', monospace;
            font-size: 1.5em;
            font-weight: bold;
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
                <i class="fas fa-coins"></i> <?php echo htmlspecialchars(getConfig('pool.name', 'Isekai Pool')); ?>
            </a>
            <div class="navbar-nav ms-auto">
                <a class="nav-link" href="/">Home</a>
                <a class="nav-link" href="/stats.php">Pool Stats</a>
                <a class="nav-link" href="/miners.php">Top Miners</a>
                <a class="nav-link" href="/blocks.php">Recent Blocks</a>
                <a class="nav-link active" href="/wallet.php">Wallet</a>
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
    <div class="wallet-header py-5">
        <div class="container">
            <div class="row text-center">
                <div class="col-12">
                    <h1 class="display-4 mb-4">
                        <i class="fas fa-wallet"></i> Wallet
                    </h1>
                    <p class="lead">View your mining earnings and payout history</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Wallet Address Input -->
    <div class="container my-4">
        <div class="row justify-content-center">
            <div class="col-md-8">
                <div class="card">
                    <div class="card-body">
                        <h5><i class="fas fa-search"></i> Lookup Wallet</h5>
                        <div class="input-group">
                            <input type="text" class="form-control" id="wallet-address" placeholder="Enter your Yenten wallet address">
                            <button class="btn btn-primary" onclick="loadWalletData()">
                                <i class="fas fa-search"></i> Lookup
                            </button>
                            <button class="btn btn-outline-secondary" onclick="clearWalletMemory()" title="Clear remembered wallet">
                                <i class="fas fa-times"></i>
                            </button>
                        </div>
                        <small class="text-muted">Enter your Yenten wallet address to view your mining statistics and earnings.</small>
                        <div id="remembered-wallet-info" class="mt-2" style="display: none;">
                            <small class="text-success">
                                <i class="fas fa-memory"></i> Wallet address remembered from previous visit
                            </small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Wallet Information -->
    <div class="container my-5" id="wallet-info" style="display: none;">
        <div class="row">
            <div class="col-12">
                <h2 class="mb-4"><i class="fas fa-info-circle"></i> Wallet Information</h2>
            </div>
        </div>
        
        <!-- Balance Cards -->
        <div class="row mb-4">
            <div class="col-md-4 mb-3">
                <div class="card text-center">
                    <div class="card-body">
                        <i class="fas fa-coins fa-3x text-warning mb-3"></i>
                        <h3 class="card-title amount-display" id="pending-balance">0.00000000 YTN</h3>
                        <p class="card-text">Pending Balance</p>
                    </div>
                </div>
            </div>
            <div class="col-md-4 mb-3">
                <div class="card text-center">
                    <div class="card-body">
                        <i class="fas fa-check-circle fa-3x text-success mb-3"></i>
                        <h3 class="card-title amount-display" id="paid-balance">0.00000000 YTN</h3>
                        <p class="card-text">Total Paid</p>
                    </div>
                </div>
            </div>
            <div class="col-md-4 mb-3">
                <div class="card text-center">
                    <div class="card-body">
                        <i class="fas fa-chart-line fa-3x text-primary mb-3"></i>
                        <h3 class="card-title amount-display" id="total-earnings">0.00000000 YTN</h3>
                        <p class="card-text">Total Earnings</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Mining Statistics -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <h5><i class="fas fa-chart-bar"></i> Mining Statistics</h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-3 text-center">
                                <h6>Total Shares</h6>
                                <p class="amount-display" id="total-shares">0</p>
                            </div>
                            <div class="col-md-3 text-center">
                                <h6>Active Workers</h6>
                                <p class="amount-display" id="active-workers">0</p>
                            </div>
                            <div class="col-md-3 text-center">
                                <h6>Last Share</h6>
                                <p id="last-share">Never</p>
                            </div>
                            <div class="col-md-3 text-center">
                                <h6>Status</h6>
                                <p id="mining-status">Inactive</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Recent Payouts -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <h5><i class="fas fa-history"></i> Recent Payouts</h5>
                    </div>
                    <div class="card-body">
                        <div id="payouts-loading" class="text-center py-3">
                            <div class="spinner-border text-primary" role="status">
                                <span class="visually-hidden">Loading...</span>
                            </div>
                            <p class="mt-2">Loading payouts...</p>
                        </div>
                        <div id="payouts-list" style="display: none;">
                            <!-- Payouts will be loaded here -->
                        </div>
                        <div id="no-payouts" class="text-center py-5" style="display: none;">
                            <i class="fas fa-wallet fa-3x text-muted mb-3"></i>
                            <h5>No Payouts Yet</h5>
                            <p class="text-muted">You haven't received any payouts yet. Keep mining to earn YTN!</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Pool Information -->
        <div class="row">
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">
                        <h5><i class="fas fa-info-circle"></i> Pool Information</h5>
                    </div>
                    <div class="card-body">
                        <p><strong>Pool Fee:</strong> <?php echo getConfig('pool.fee_percent', 1.0); ?>%</p>
                        <p><strong>Minimum Payout:</strong> 0.1 YTN</p>
                        <p><strong>Payout Threshold:</strong> 0.5 YTN</p>
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
                        <small class="text-muted">Wallet information is updated every 30 seconds</small>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Footer -->
    <footer class="bg-dark text-light py-4 mt-5">
        <div class="container text-center">
            <p>&copy; <?=date('Y');?> <?php echo htmlspecialchars(getConfig('pool.name', 'Isekai Pool')); ?>. All rights reserved.</p>
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Format amount
        function formatAmount(amount) {
            return parseFloat(amount).toFixed(8) + ' YTN';
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

        // Load wallet data
        function loadWalletData() {
            const walletAddress = document.getElementById('wallet-address').value.trim();
            
            if (!walletAddress) {
                alert('Please enter your wallet address');
                return;
            }

            // Save wallet address to localStorage
            localStorage.setItem('yenten_pool_wallet', walletAddress);
            
            // Show wallet info section
            document.getElementById('wallet-info').style.display = 'block';
            
            // Load wallet data
            fetchWalletData(walletAddress);
        }

        // Fetch wallet data from API
        function fetchWalletData(walletAddress) {
            fetch(`/api/wallet.php?address=${encodeURIComponent(walletAddress)}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        displayWalletData(data);
                        // Also load payouts
                        loadPayouts(walletAddress);
                    } else {
                        console.error('Failed to fetch wallet data:', data.error);
                        alert('Failed to load wallet data: ' + data.error);
                    }
                })
                .catch(error => {
                    console.error('Error fetching wallet data:', error);
                    alert('Error loading wallet data');
                });
        }

        // Display wallet data
        function displayWalletData(data) {
            // Update balances
            document.getElementById('pending-balance').textContent = formatAmount(data.pending_balance);
            document.getElementById('paid-balance').textContent = formatAmount(data.paid_balance);
            document.getElementById('total-earnings').textContent = formatAmount(data.total_earnings);
            
            // Update mining statistics
            document.getElementById('total-shares').textContent = formatNumber(data.total_shares);
            document.getElementById('active-workers').textContent = formatNumber(data.active_workers);
            document.getElementById('last-share').textContent = formatTimestamp(data.last_share);
            
            // Update mining status
            const lastShare = new Date(data.last_share);
            const now = new Date();
            const timeDiff = now - lastShare;
            const minutesAgo = Math.floor(timeDiff / (1000 * 60));
            
            let statusText = 'Active';
            let statusClass = 'text-success';
            
            if (minutesAgo > 60) {
                statusText = `${minutesAgo}m ago`;
                statusClass = 'text-warning';
            }
            if (minutesAgo > 1440) { // 24 hours
                statusText = 'Inactive';
                statusClass = 'text-danger';
            }
            
            document.getElementById('mining-status').textContent = statusText;
            document.getElementById('mining-status').className = statusClass;
            
            // Update last updated time
            document.getElementById('last-updated').textContent = new Date().toLocaleString();
        }

        // Load payouts
        function loadPayouts(walletAddress) {
            document.getElementById('payouts-loading').style.display = 'block';
            document.getElementById('payouts-list').style.display = 'none';
            document.getElementById('no-payouts').style.display = 'none';

            fetch(`/api/payouts.php?address=${encodeURIComponent(walletAddress)}`)
                .then(response => response.json())
                .then(data => {
                    document.getElementById('payouts-loading').style.display = 'none';
                    
                    if (data.success && data.payouts.length > 0) {
                        displayPayouts(data.payouts);
                    } else {
                        document.getElementById('no-payouts').style.display = 'block';
                    }
                })
                .catch(error => {
                    document.getElementById('payouts-loading').style.display = 'none';
                    document.getElementById('no-payouts').style.display = 'block';
                    console.error('Error loading payouts:', error);
                });
        }

        // Display payouts
        function displayPayouts(payouts) {
            const container = document.getElementById('payouts-list');
            container.innerHTML = '';

            if (payouts.length === 0) {
                container.innerHTML = '<p class="text-muted text-center">No payouts found.</p>';
                return;
            }

            const table = document.createElement('table');
            table.className = 'table table-striped table-hover';
            
            table.innerHTML = `
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Amount</th>
                        <th>Status</th>
                        <th>Transaction Hash</th>
                    </tr>
                </thead>
                <tbody>
                </tbody>
            `;

            const tbody = table.querySelector('tbody');
            
            payouts.forEach(payout => {
                const row = document.createElement('tr');
                
                let statusClass = 'text-warning';
                if (payout.status === 'completed') {
                    statusClass = 'text-success';
                } else if (payout.status === 'failed') {
                    statusClass = 'text-danger';
                }

                row.innerHTML = `
                    <td>${formatTimestamp(payout.created_at)}</td>
                    <td class="amount-display">${formatAmount(payout.amount)}</td>
                    <td><span class="${statusClass}">${payout.status.charAt(0).toUpperCase() + payout.status.slice(1)}</span></td>
                    <td><small>${payout.transaction_hash || 'Pending'}</small></td>
                `;
                
                tbody.appendChild(row);
            });

            container.appendChild(table);
            container.style.display = 'block';
        }

        // Clear wallet memory
        function clearWalletMemory() {
            localStorage.removeItem('yenten_pool_wallet');
            document.getElementById('wallet-address').value = '';
            document.getElementById('wallet-info').style.display = 'none';
            document.getElementById('remembered-wallet-info').style.display = 'none';
        }
        
        // Load remembered wallet on page load
        function loadRememberedWallet() {
            const rememberedWallet = localStorage.getItem('yenten_pool_wallet');
            if (rememberedWallet) {
                document.getElementById('wallet-address').value = rememberedWallet;
                document.getElementById('remembered-wallet-info').style.display = 'block';
                
                // Auto-load wallet data if remembered
                document.getElementById('wallet-info').style.display = 'block';
                fetchWalletData(rememberedWallet);
            }
        }
        
        // Initialize
        document.addEventListener('DOMContentLoaded', function() {
            // Load remembered wallet on page load
            loadRememberedWallet();
            
            // Auto-refresh every 30 seconds if wallet is loaded
            setInterval(function() {
                const walletAddress = document.getElementById('wallet-address').value.trim();
                if (walletAddress && document.getElementById('wallet-info').style.display !== 'none') {
                    fetchWalletData(walletAddress);
                }
            }, 30000);
        });
    </script>
</body>
</html>
