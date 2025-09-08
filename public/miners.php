<?php
/**
 * Top Miners Page
 * Shows active miners and their statistics
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
    <title>Top Miners - <?php echo htmlspecialchars(getConfig('pool.name', 'Isekai Multi-Coin Pool')); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .miners-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }
        .miner-card {
            transition: transform 0.2s;
        }
        .miner-card:hover {
            transform: translateY(-2px);
        }
        .hashrate-display {
            font-family: 'Courier New', monospace;
            font-size: 1.1em;
        }
        .coin-badge {
            font-size: 0.8em;
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
                <a class="nav-link" href="/stats.php">Pool Stats</a>
                <a class="nav-link active" href="/miners.php">Top Miners</a>
                <a class="nav-link" href="/blocks.php">Recent Blocks</a>
                <a class="nav-link" href="/help.php">Help</a>
            </div>
        </div>
    </nav>

    <!-- Header -->
    <div class="miners-header py-5">
        <div class="container">
            <div class="row text-center">
                <div class="col-12">
                    <h1 class="display-4 mb-4">
                        <i class="fas fa-users"></i> Top Miners
                    </h1>
                    <p class="lead">Active miners and their performance statistics</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Filters -->
    <div class="container my-4">
        <div class="row">
            <div class="col-md-6">
                <div class="card">
                    <div class="card-body">
                        <h5><i class="fas fa-filter"></i> Filter Miners</h5>
                        <div class="row">
                            <div class="col-md-6">
                                <label class="form-label">Coin:</label>
                                <select class="form-select" id="coin-filter" onchange="filterMiners()">
                                    <option value="all">All Coins</option>
                                    <option value="yenten">Yenten (YTN)</option>
                                    <option value="koto">KOTO (KOTO)</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Limit:</label>
                                <select class="form-select" id="limit-filter" onchange="filterMiners()">
                                    <option value="25">Top 25</option>
                                    <option value="50" selected>Top 50</option>
                                    <option value="100">Top 100</option>
                                </select>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="card">
                    <div class="card-body">
                        <h5><i class="fas fa-info-circle"></i> Statistics</h5>
                        <p><strong>Total Active Miners:</strong> <span id="total-miners">0</span></p>
                        <p><strong>Last Updated:</strong> <span id="last-updated">Loading...</span></p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Miners List -->
    <div class="container my-5">
        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <h5><i class="fas fa-list"></i> Miners List</h5>
                    </div>
                    <div class="card-body">
                        <div id="miners-loading" class="text-center py-5">
                            <div class="spinner-border text-primary" role="status">
                                <span class="visually-hidden">Loading...</span>
                            </div>
                            <p class="mt-3">Loading miners...</p>
                        </div>
                        <div id="miners-list" style="display: none;">
                            <!-- Miners will be loaded here -->
                        </div>
                        <div id="no-miners" class="text-center py-5" style="display: none;">
                            <i class="fas fa-users fa-3x text-muted mb-3"></i>
                            <h5>No Active Miners</h5>
                            <p class="text-muted">No miners have submitted shares in the last 24 hours.</p>
                        </div>
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

        // Get coin badge class
        function getCoinBadgeClass(coin) {
            switch(coin) {
                case 'yenten': return 'bg-warning text-dark';
                case 'koto': return 'bg-success';
                default: return 'bg-secondary';
            }
        }

        // Get coin display name
        function getCoinDisplayName(coin) {
            switch(coin) {
                case 'yenten': return 'YTN';
                case 'koto': return 'KOTO';
                default: return coin.toUpperCase();
            }
        }

        // Load miners
        function loadMiners() {
            const coin = document.getElementById('coin-filter').value;
            const limit = document.getElementById('limit-filter').value;
            
            document.getElementById('miners-loading').style.display = 'block';
            document.getElementById('miners-list').style.display = 'none';
            document.getElementById('no-miners').style.display = 'none';

            const url = `/api/miners.php?coin=${coin}&limit=${limit}`;
            
            fetch(url)
                .then(response => response.json())
                .then(data => {
                    document.getElementById('miners-loading').style.display = 'none';
                    
                    if (data.success && data.miners.length > 0) {
                        displayMiners(data.miners);
                        document.getElementById('total-miners').textContent = data.miners.length;
                    } else {
                        document.getElementById('no-miners').style.display = 'block';
                        document.getElementById('total-miners').textContent = '0';
                    }
                    
                    document.getElementById('last-updated').textContent = new Date().toLocaleString();
                })
                .catch(error => {
                    document.getElementById('miners-loading').style.display = 'none';
                    document.getElementById('no-miners').style.display = 'block';
                    console.error('Error loading miners:', error);
                });
        }

        // Display miners
        function displayMiners(miners) {
            const container = document.getElementById('miners-list');
            container.innerHTML = '';

            if (miners.length === 0) {
                container.innerHTML = '<p class="text-muted text-center">No miners found.</p>';
                return;
            }

            const table = document.createElement('table');
            table.className = 'table table-striped table-hover';
            
            table.innerHTML = `
                <thead>
                    <tr>
                        <th>Rank</th>
                        <th>Worker</th>
                        <th>Coin</th>
                        <th>Hashrate</th>
                        <th>Shares (24h)</th>
                        <th>Last Share</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                </tbody>
            `;

            const tbody = table.querySelector('tbody');
            
            miners.forEach((miner, index) => {
                const row = document.createElement('tr');
                
                // Calculate status
                const lastShare = new Date(miner.last_share);
                const now = new Date();
                const timeDiff = now - lastShare;
                const minutesAgo = Math.floor(timeDiff / (1000 * 60));
                
                let statusClass = 'text-success';
                let statusText = 'Active';
                
                if (minutesAgo > 60) {
                    statusClass = 'text-warning';
                    statusText = `${minutesAgo}m ago`;
                }
                if (minutesAgo > 1440) { // 24 hours
                    statusClass = 'text-danger';
                    statusText = 'Inactive';
                }

                row.innerHTML = `
                    <td><strong>#${index + 1}</strong></td>
                    <td>
                        <strong>${miner.worker_name}</strong><br>
                        <small class="text-muted">${miner.username}</small>
                    </td>
                    <td>
                        <span class="badge ${getCoinBadgeClass(miner.coin)} coin-badge">
                            ${getCoinDisplayName(miner.coin)}
                        </span>
                    </td>
                    <td class="hashrate-display">${formatHashrate(miner.estimated_hashrate)}</td>
                    <td>${formatNumber(miner.shares_last_24h)}</td>
                    <td><small>${formatTimestamp(miner.last_share)}</small></td>
                    <td><span class="${statusClass}">${statusText}</span></td>
                `;
                
                tbody.appendChild(row);
            });

            container.appendChild(table);
            container.style.display = 'block';
        }

        // Filter miners
        function filterMiners() {
            loadMiners();
        }

        // Initialize
        document.addEventListener('DOMContentLoaded', function() {
            loadMiners();
            setInterval(loadMiners, 60000); // Update every minute
        });
    </script>
</body>
</html>
