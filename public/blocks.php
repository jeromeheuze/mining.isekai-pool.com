<?php
/**
 * Recent Blocks Page
 * Shows recently found blocks by the pool
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
    <title>Recent Blocks - <?php echo htmlspecialchars(getConfig('pool.name', 'Isekai Pool')); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .blocks-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }
        .block-card {
            transition: transform 0.2s;
        }
        .block-card:hover {
            transform: translateY(-2px);
        }
        .block-hash {
            font-family: 'Courier New', monospace;
            font-size: 0.9em;
            word-break: break-all;
        }
        .coin-badge {
            font-size: 0.8em;
        }
        .status-confirmed {
            color: #28a745;
        }
        .status-pending {
            color: #ffc107;
        }
        .status-orphaned {
            color: #dc3545;
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
                <a class="nav-link active" href="/blocks.php">Recent Blocks</a>
                <a class="nav-link" href="/wallet.php">Wallet</a>
                <a class="nav-link" href="/help.php">Help</a>
            </div>
        </div>
    </nav>

    <!-- Header -->
    <div class="blocks-header py-5">
        <div class="container">
            <div class="row text-center">
                <div class="col-12">
                    <h1 class="display-4 mb-4">
                        <i class="fas fa-cube"></i> Recent Blocks
                    </h1>
                    <p class="lead">Blocks found by our mining pool</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Summary Statistics -->
    <div class="container my-4">
        <div class="row">
            <div class="col-md-3 mb-3">
                <div class="card text-center">
                    <div class="card-body">
                        <i class="fas fa-cube fa-2x text-primary mb-2"></i>
                        <h5 id="total-blocks">0</h5>
                        <p class="text-muted">Total Blocks</p>
                    </div>
                </div>
            </div>
            <div class="col-md-3 mb-3">
                <div class="card text-center">
                    <div class="card-body">
                        <i class="fas fa-check-circle fa-2x text-success mb-2"></i>
                        <h5 id="confirmed-blocks">0</h5>
                        <p class="text-muted">Confirmed</p>
                    </div>
                </div>
            </div>
            <div class="col-md-3 mb-3">
                <div class="card text-center">
                    <div class="card-body">
                        <i class="fas fa-clock fa-2x text-warning mb-2"></i>
                        <h5 id="pending-blocks">0</h5>
                        <p class="text-muted">Pending</p>
                    </div>
                </div>
            </div>
            <div class="col-md-3 mb-3">
                <div class="card text-center">
                    <div class="card-body">
                        <i class="fas fa-coins fa-2x text-info mb-2"></i>
                        <h5 id="total-reward">0</h5>
                        <p class="text-muted">Total Reward</p>
                    </div>
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
                        <h5><i class="fas fa-filter"></i> Filter Blocks</h5>
                        <div class="row">
                            <div class="col-md-6">
                                <label class="form-label">Coin:</label>
                                <select class="form-select" id="coin-filter" onchange="filterBlocks()">
                                    <option value="all">All Coins</option>
                                    <option value="yenten">Yenten (YTN)</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Limit:</label>
                                <select class="form-select" id="limit-filter" onchange="filterBlocks()">
                                    <option value="10">Last 10</option>
                                    <option value="20" selected>Last 20</option>
                                    <option value="50">Last 50</option>
                                </select>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="card">
                    <div class="card-body">
                        <h5><i class="fas fa-info-circle"></i> Information</h5>
                        <p><strong>Last Updated:</strong> <span id="last-updated">Loading...</span></p>
                        <small class="text-muted">Blocks are updated in real-time</small>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Blocks List -->
    <div class="container my-5">
        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <h5><i class="fas fa-list"></i> Blocks List</h5>
                    </div>
                    <div class="card-body">
                        <div id="blocks-loading" class="text-center py-5">
                            <div class="spinner-border text-primary" role="status">
                                <span class="visually-hidden">Loading...</span>
                            </div>
                            <p class="mt-3">Loading blocks...</p>
                        </div>
                        <div id="blocks-list" style="display: none;">
                            <!-- Blocks will be loaded here -->
                        </div>
                        <div id="no-blocks" class="text-center py-5" style="display: none;">
                            <i class="fas fa-cube fa-3x text-muted mb-3"></i>
                            <h5>No Blocks Found</h5>
                            <p class="text-muted">No blocks have been found yet. Start mining to find blocks!</p>
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
                default: return 'bg-secondary';
            }
        }

        // Get coin display name
        function getCoinDisplayName(coin) {
            switch(coin) {
                case 'yenten': return 'YTN';
                default: return coin.toUpperCase();
            }
        }

        // Get status class
        function getStatusClass(status) {
            switch(status) {
                case 'confirmed': return 'status-confirmed';
                case 'pending': return 'status-pending';
                case 'orphaned': return 'status-orphaned';
                default: return 'text-muted';
            }
        }

        // Load blocks
        function loadBlocks() {
            const coin = document.getElementById('coin-filter').value;
            const limit = document.getElementById('limit-filter').value;
            
            document.getElementById('blocks-loading').style.display = 'block';
            document.getElementById('blocks-list').style.display = 'none';
            document.getElementById('no-blocks').style.display = 'none';

            const url = `/api/blocks.php?coin=${coin}&limit=${limit}`;
            
            fetch(url)
                .then(response => response.json())
                .then(data => {
                    document.getElementById('blocks-loading').style.display = 'none';
                    
                    if (data.success && data.blocks.length > 0) {
                        displayBlocks(data.blocks);
                        updateSummary(data.summary);
                    } else {
                        document.getElementById('no-blocks').style.display = 'block';
                        updateSummary({ total_blocks: 0, confirmed_blocks: 0, pending_blocks: 0, total_reward: 0 });
                    }
                    
                    document.getElementById('last-updated').textContent = new Date().toLocaleString();
                })
                .catch(error => {
                    document.getElementById('blocks-loading').style.display = 'none';
                    document.getElementById('no-blocks').style.display = 'block';
                    console.error('Error loading blocks:', error);
                });
        }

        // Update summary statistics
        function updateSummary(summary) {
            document.getElementById('total-blocks').textContent = formatNumber(summary.total_blocks);
            document.getElementById('confirmed-blocks').textContent = formatNumber(summary.confirmed_blocks);
            document.getElementById('pending-blocks').textContent = formatNumber(summary.pending_blocks);
            document.getElementById('total-reward').textContent = formatNumber(summary.total_reward);
        }

        // Display blocks
        function displayBlocks(blocks) {
            const container = document.getElementById('blocks-list');
            container.innerHTML = '';

            if (blocks.length === 0) {
                container.innerHTML = '<p class="text-muted text-center">No blocks found.</p>';
                return;
            }

            const table = document.createElement('table');
            table.className = 'table table-striped table-hover';
            
            table.innerHTML = `
                <thead>
                    <tr>
                        <th>Block</th>
                        <th>Coin</th>
                        <th>Height</th>
                        <th>Hash</th>
                        <th>Reward</th>
                        <th>Difficulty</th>
                        <th>Found By</th>
                        <th>Time</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                </tbody>
            `;

            const tbody = table.querySelector('tbody');
            
            blocks.forEach((block, index) => {
                const row = document.createElement('tr');
                
                row.innerHTML = `
                    <td><strong>#${index + 1}</strong></td>
                    <td>
                        <span class="badge ${getCoinBadgeClass(block.coin)} coin-badge">
                            ${getCoinDisplayName(block.coin)}
                        </span>
                    </td>
                    <td><strong>${formatNumber(block.block_height)}</strong></td>
                    <td>
                        <code class="block-hash" title="${block.block_hash}">
                            ${block.block_hash.substring(0, 16)}...
                        </code>
                    </td>
                    <td><strong>${formatNumber(block.reward)}</strong></td>
                    <td>${formatNumber(block.difficulty)}</td>
                    <td>
                        <strong>${block.worker_name}</strong><br>
                        <small class="text-muted">${block.username}</small>
                    </td>
                    <td>
                        <small>${formatTimestamp(block.created_at)}</small><br>
                        <span class="text-muted">${block.time_ago}</span>
                    </td>
                    <td>
                        <span class="${getStatusClass(block.status)}">
                            <i class="fas fa-${block.status === 'confirmed' ? 'check-circle' : block.status === 'pending' ? 'clock' : 'times-circle'}"></i>
                            ${block.status.charAt(0).toUpperCase() + block.status.slice(1)}
                        </span>
                    </td>
                `;
                
                tbody.appendChild(row);
            });

            container.appendChild(table);
            container.style.display = 'block';
        }

        // Filter blocks
        function filterBlocks() {
            loadBlocks();
        }

        // Initialize
        document.addEventListener('DOMContentLoaded', function() {
            loadBlocks();
            setInterval(loadBlocks, 60000); // Update every minute
        });
    </script>
</body>
</html>
