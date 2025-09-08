<?php
/**
 * Admin Dashboard
 * Real-time monitoring of pool status
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
    <title>Admin Dashboard - <?php echo htmlspecialchars(getConfig('pool.name', 'Isekai Pool')); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .admin-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }
        .status-card {
            border-left: 4px solid #28a745;
        }
        .status-card.warning {
            border-left-color: #ffc107;
        }
        .status-card.danger {
            border-left-color: #dc3545;
        }
        .metric-value {
            font-size: 2rem;
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
                <a class="nav-link" href="/wallet.php">Wallet</a>
                <a class="nav-link active" href="/admin.php">Admin</a>
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
    <div class="admin-header py-5">
        <div class="container">
            <div class="row text-center">
                <div class="col-12">
                    <h1 class="display-4 mb-4">
                        <i class="fas fa-tachometer-alt"></i> Admin Dashboard
                    </h1>
                    <p class="lead">Real-time monitoring of your mining pool</p>
                </div>
            </div>
        </div>
    </div>

    <!-- System Status -->
    <div class="container my-5">
        <div class="row">
            <div class="col-12">
                <h2 class="mb-4"><i class="fas fa-heartbeat"></i> System Status</h2>
            </div>
        </div>
        
        <div class="row mb-4">
            <div class="col-md-3 mb-3">
                <div class="card status-card" id="database-status">
                    <div class="card-body text-center">
                        <i class="fas fa-database fa-3x mb-3"></i>
                        <h5 class="card-title">Database</h5>
                        <p class="card-text" id="database-status-text">Checking...</p>
                    </div>
                </div>
            </div>
            <div class="col-md-3 mb-3">
                <div class="card status-card" id="yenten-status">
                    <div class="card-body text-center">
                        <i class="fas fa-server fa-3x mb-3"></i>
                        <h5 class="card-title">Yenten Daemon</h5>
                        <p class="card-text" id="yenten-status-text">Checking...</p>
                    </div>
                </div>
            </div>
            <div class="col-md-3 mb-3">
                <div class="card status-card" id="stratum-status">
                    <div class="card-body text-center">
                        <i class="fas fa-network-wired fa-3x mb-3"></i>
                        <h5 class="card-title">Stratum Server</h5>
                        <p class="card-text" id="stratum-status-text">Checking...</p>
                    </div>
                </div>
            </div>
            <div class="col-md-3 mb-3">
                <div class="card status-card" id="payout-status">
                    <div class="card-body text-center">
                        <i class="fas fa-wallet fa-3x mb-3"></i>
                        <h5 class="card-title">Payout System</h5>
                        <p class="card-text" id="payout-status-text">Checking...</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Pool Metrics -->
        <div class="row mb-4">
            <div class="col-12">
                <h2 class="mb-4"><i class="fas fa-chart-line"></i> Pool Metrics</h2>
            </div>
        </div>
        
        <div class="row mb-4">
            <div class="col-md-3 mb-3">
                <div class="card">
                    <div class="card-body text-center">
                        <i class="fas fa-users fa-2x text-primary mb-3"></i>
                        <h3 class="metric-value text-primary" id="active-miners">0</h3>
                        <p class="card-text">Active Miners</p>
                    </div>
                </div>
            </div>
            <div class="col-md-3 mb-3">
                <div class="card">
                    <div class="card-body text-center">
                        <i class="fas fa-coins fa-2x text-warning mb-3"></i>
                        <h3 class="metric-value text-warning" id="pool-balance">0</h3>
                        <p class="card-text">Pool Balance (YTN)</p>
                    </div>
                </div>
            </div>
            <div class="col-md-3 mb-3">
                <div class="card">
                    <div class="card-body text-center">
                        <i class="fas fa-chart-bar fa-2x text-success mb-3"></i>
                        <h3 class="metric-value text-success" id="total-shares">0</h3>
                        <p class="card-text">Total Shares (24h)</p>
                    </div>
                </div>
            </div>
            <div class="col-md-3 mb-3">
                <div class="card">
                    <div class="card-body text-center">
                        <i class="fas fa-money-bill-wave fa-2x text-info mb-3"></i>
                        <h3 class="metric-value text-info" id="pending-payouts">0</h3>
                        <p class="card-text">Pending Payouts (YTN)</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Recent Activity -->
        <div class="row">
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">
                        <h5><i class="fas fa-clock"></i> Recent Activity</h5>
                    </div>
                    <div class="card-body">
                        <div id="recent-activity">
                            <div class="text-center py-3">
                                <div class="spinner-border text-primary" role="status">
                                    <span class="visually-hidden">Loading...</span>
                                </div>
                                <p class="mt-2">Loading activity...</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">
                        <h5><i class="fas fa-exclamation-triangle"></i> System Alerts</h5>
                    </div>
                    <div class="card-body">
                        <div id="system-alerts">
                            <div class="text-center py-3">
                                <div class="spinner-border text-primary" role="status">
                                    <span class="visually-hidden">Loading...</span>
                                </div>
                                <p class="mt-2">Loading alerts...</p>
                            </div>
                        </div>
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
        // Update system status
        function updateSystemStatus() {
            fetch('/api/admin-status.php')
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        updateStatusCard('database-status', data.database.status, data.database.message);
                        updateStatusCard('yenten-status', data.yenten.status, data.yenten.message);
                        updateStatusCard('stratum-status', data.stratum.status, data.stratum.message);
                        updateStatusCard('payout-status', data.payout.status, data.payout.message);
                        
                        // Update metrics
                        document.getElementById('active-miners').textContent = data.metrics.active_miners;
                        document.getElementById('pool-balance').textContent = data.metrics.pool_balance;
                        document.getElementById('total-shares').textContent = data.metrics.total_shares;
                        document.getElementById('pending-payouts').textContent = data.metrics.pending_payouts;
                        
                        // Update activity
                        updateRecentActivity(data.activity);
                        
                        // Update alerts
                        updateSystemAlerts(data.alerts);
                    }
                })
                .catch(error => {
                    console.error('Error updating system status:', error);
                });
        }
        
        // Update status card
        function updateStatusCard(cardId, status, message) {
            const card = document.getElementById(cardId);
            const textElement = document.getElementById(cardId + '-text');
            
            // Remove existing status classes
            card.classList.remove('status-card', 'warning', 'danger');
            
            // Add appropriate status class
            if (status === 'PASS') {
                card.classList.add('status-card');
                textElement.innerHTML = '<span class="text-success">✅ ' + message + '</span>';
            } else if (status === 'WARN') {
                card.classList.add('status-card', 'warning');
                textElement.innerHTML = '<span class="text-warning">⚠️ ' + message + '</span>';
            } else {
                card.classList.add('status-card', 'danger');
                textElement.innerHTML = '<span class="text-danger">❌ ' + message + '</span>';
            }
        }
        
        // Update recent activity
        function updateRecentActivity(activity) {
            const container = document.getElementById('recent-activity');
            
            if (activity.length === 0) {
                container.innerHTML = '<p class="text-muted text-center">No recent activity</p>';
                return;
            }
            
            let html = '<div class="list-group list-group-flush">';
            activity.forEach(item => {
                html += `
                    <div class="list-group-item">
                        <div class="d-flex w-100 justify-content-between">
                            <h6 class="mb-1">${item.title}</h6>
                            <small>${item.time}</small>
                        </div>
                        <p class="mb-1">${item.description}</p>
                    </div>
                `;
            });
            html += '</div>';
            
            container.innerHTML = html;
        }
        
        // Update system alerts
        function updateSystemAlerts(alerts) {
            const container = document.getElementById('system-alerts');
            
            if (alerts.length === 0) {
                container.innerHTML = '<p class="text-success text-center">✅ No alerts</p>';
                return;
            }
            
            let html = '<div class="list-group list-group-flush">';
            alerts.forEach(alert => {
                const alertClass = alert.level === 'error' ? 'danger' : alert.level;
                html += `
                    <div class="list-group-item list-group-item-${alertClass}">
                        <div class="d-flex w-100 justify-content-between">
                            <h6 class="mb-1">${alert.title}</h6>
                            <small>${alert.time}</small>
                        </div>
                        <p class="mb-1">${alert.message}</p>
                    </div>
                `;
            });
            html += '</div>';
            
            container.innerHTML = html;
        }
        
        // Initialize
        document.addEventListener('DOMContentLoaded', function() {
            updateSystemStatus();
            
            // Auto-refresh every 30 seconds
            setInterval(updateSystemStatus, 30000);
        });
    </script>
</body>
</html>
