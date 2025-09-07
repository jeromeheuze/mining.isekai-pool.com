-- Yenten Mining Pool Database Schema
-- Created for mining.isekai-pool.com

CREATE DATABASE IF NOT EXISTS yenten_pool;
USE yenten_pool;

-- Users table (miner addresses, registration, settings)
CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    address VARCHAR(95) NOT NULL UNIQUE COMMENT 'Yenten wallet address',
    username VARCHAR(50) UNIQUE COMMENT 'Optional username',
    email VARCHAR(255) COMMENT 'Contact email',
    password_hash VARCHAR(255) COMMENT 'Hashed password for web access',
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    last_seen TIMESTAMP NULL,
    total_shares BIGINT DEFAULT 0,
    total_earnings DECIMAL(20,8) DEFAULT 0.00000000,
    pending_balance DECIMAL(20,8) DEFAULT 0.00000000,
    paid_balance DECIMAL(20,8) DEFAULT 0.00000000,
    INDEX idx_address (address),
    INDEX idx_username (username),
    INDEX idx_active (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Workers table (individual mining rigs per user)
CREATE TABLE workers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    worker_name VARCHAR(50) NOT NULL,
    password VARCHAR(255) DEFAULT 'x',
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    last_seen TIMESTAMP NULL,
    hashrate BIGINT DEFAULT 0 COMMENT 'Current hashrate in H/s',
    shares_submitted BIGINT DEFAULT 0,
    shares_accepted BIGINT DEFAULT 0,
    shares_rejected BIGINT DEFAULT 0,
    difficulty DOUBLE DEFAULT 1.0,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY unique_worker (user_id, worker_name),
    INDEX idx_user_active (user_id, is_active),
    INDEX idx_last_seen (last_seen)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Shares table (submitted mining shares with validation)
CREATE TABLE shares (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    worker_id INT NOT NULL,
    block_height INT NOT NULL,
    difficulty DOUBLE NOT NULL,
    share_difficulty DOUBLE NOT NULL,
    nonce VARCHAR(16) NOT NULL,
    hash VARCHAR(64) NOT NULL,
    is_valid BOOLEAN NOT NULL,
    is_stale BOOLEAN DEFAULT FALSE,
    is_duplicate BOOLEAN DEFAULT FALSE,
    submitted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    processed_at TIMESTAMP NULL,
    reward DECIMAL(20,8) DEFAULT 0.00000000,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (worker_id) REFERENCES workers(id) ON DELETE CASCADE,
    INDEX idx_user_time (user_id, submitted_at),
    INDEX idx_worker_time (worker_id, submitted_at),
    INDEX idx_block_height (block_height),
    INDEX idx_valid (is_valid),
    INDEX idx_submitted (submitted_at),
    UNIQUE KEY unique_share (hash, nonce)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Blocks table (found blocks and confirmations)
CREATE TABLE blocks (
    id INT AUTO_INCREMENT PRIMARY KEY,
    height INT NOT NULL UNIQUE,
    hash VARCHAR(64) NOT NULL UNIQUE,
    previous_hash VARCHAR(64) NOT NULL,
    merkle_root VARCHAR(64) NOT NULL,
    timestamp TIMESTAMP NOT NULL,
    difficulty DOUBLE NOT NULL,
    nonce VARCHAR(16) NOT NULL,
    found_by_user_id INT,
    found_by_worker_id INT,
    pool_reward DECIMAL(20,8) NOT NULL,
    network_reward DECIMAL(20,8) NOT NULL,
    confirmations INT DEFAULT 0,
    is_orphaned BOOLEAN DEFAULT FALSE,
    is_confirmed BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    confirmed_at TIMESTAMP NULL,
    FOREIGN KEY (found_by_user_id) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (found_by_worker_id) REFERENCES workers(id) ON DELETE SET NULL,
    INDEX idx_height (height),
    INDEX idx_hash (hash),
    INDEX idx_confirmations (confirmations),
    INDEX idx_confirmed (is_confirmed),
    INDEX idx_orphaned (is_orphaned)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Payouts table (payment history and pending)
CREATE TABLE payouts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    amount DECIMAL(20,8) NOT NULL,
    transaction_hash VARCHAR(64) NULL,
    status ENUM('pending', 'processing', 'completed', 'failed') DEFAULT 'pending',
    fee DECIMAL(20,8) DEFAULT 0.00000000,
    net_amount DECIMAL(20,8) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    processed_at TIMESTAMP NULL,
    confirmed_at TIMESTAMP NULL,
    error_message TEXT NULL,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_user_status (user_id, status),
    INDEX idx_status (status),
    INDEX idx_created (created_at),
    INDEX idx_transaction (transaction_hash)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Pool stats table (historical statistics)
CREATE TABLE pool_stats (
    id INT AUTO_INCREMENT PRIMARY KEY,
    timestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    active_miners INT NOT NULL DEFAULT 0,
    active_workers INT NOT NULL DEFAULT 0,
    total_hashrate BIGINT NOT NULL DEFAULT 0,
    network_hashrate BIGINT NOT NULL DEFAULT 0,
    network_difficulty DOUBLE NOT NULL DEFAULT 0,
    pool_difficulty DOUBLE NOT NULL DEFAULT 0,
    shares_per_second DOUBLE NOT NULL DEFAULT 0,
    blocks_found_24h INT NOT NULL DEFAULT 0,
    total_blocks_found INT NOT NULL DEFAULT 0,
    pool_efficiency DECIMAL(5,2) NOT NULL DEFAULT 0.00,
    pending_payouts DECIMAL(20,8) NOT NULL DEFAULT 0.00000000,
    total_paid_out DECIMAL(20,8) NOT NULL DEFAULT 0.00000000,
    INDEX idx_timestamp (timestamp)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Pool configuration table
CREATE TABLE pool_config (
    id INT AUTO_INCREMENT PRIMARY KEY,
    config_key VARCHAR(100) NOT NULL UNIQUE,
    config_value TEXT NOT NULL,
    description TEXT,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_key (config_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Insert default pool configuration
INSERT INTO pool_config (config_key, config_value, description) VALUES
('pool_fee_percent', '1.0', 'Pool fee percentage'),
('minimum_payout', '0.1', 'Minimum payout amount in YTN'),
('payout_threshold', '0.5', 'Automatic payout threshold'),
('block_reward', '50.0', 'Block reward in YTN'),
('network_difficulty', '1.0', 'Current network difficulty'),
('pool_difficulty', '1.0', 'Pool difficulty setting'),
('stratum_ports', '3333,4444,5555', 'Stratum server ports'),
('yenten_daemon_host', 'localhost', 'Yenten daemon host'),
('yenten_daemon_port', '9981', 'Yenten daemon RPC port'),
('yenten_daemon_user', 'rpcuser', 'Yenten daemon RPC username'),
('yenten_daemon_password', 'rpcpassword', 'Yenten daemon RPC password');

-- Create indexes for performance
CREATE INDEX idx_shares_user_time ON shares(user_id, submitted_at DESC);
CREATE INDEX idx_shares_valid_time ON shares(is_valid, submitted_at DESC);
CREATE INDEX idx_workers_user_active ON workers(user_id, is_active);
CREATE INDEX idx_blocks_height_desc ON blocks(height DESC);
CREATE INDEX idx_payouts_user_status ON payouts(user_id, status);

-- Create views for common queries
CREATE VIEW miner_stats AS
SELECT 
    u.id,
    u.address,
    u.username,
    u.total_shares,
    u.total_earnings,
    u.pending_balance,
    u.paid_balance,
    u.last_seen,
    COUNT(DISTINCT w.id) as active_workers,
    SUM(w.hashrate) as total_hashrate,
    SUM(w.shares_accepted) as total_accepted_shares,
    SUM(w.shares_rejected) as total_rejected_shares,
    CASE 
        WHEN SUM(w.shares_accepted + w.shares_rejected) > 0 
        THEN (SUM(w.shares_accepted) / SUM(w.shares_accepted + w.shares_rejected)) * 100 
        ELSE 0 
    END as acceptance_rate
FROM users u
LEFT JOIN workers w ON u.id = w.user_id AND w.is_active = TRUE
WHERE u.is_active = TRUE
GROUP BY u.id, u.address, u.username, u.total_shares, u.total_earnings, u.pending_balance, u.paid_balance, u.last_seen;

CREATE VIEW pool_summary AS
SELECT 
    COUNT(DISTINCT u.id) as total_miners,
    COUNT(DISTINCT w.id) as total_workers,
    SUM(w.hashrate) as total_hashrate,
    AVG(ps.pool_efficiency) as avg_efficiency,
    COUNT(DISTINCT b.id) as blocks_found_24h,
    SUM(p.amount) as total_paid_out
FROM users u
LEFT JOIN workers w ON u.id = w.user_id AND w.is_active = TRUE
LEFT JOIN pool_stats ps ON ps.timestamp >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
LEFT JOIN blocks b ON b.created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR) AND b.is_confirmed = TRUE
LEFT JOIN payouts p ON p.status = 'completed' AND p.created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
WHERE u.is_active = TRUE;
