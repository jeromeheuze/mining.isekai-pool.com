-- Add pool_work table for storing work templates
-- Migration: 001_add_pool_work_table.sql

USE yenten_pool;

CREATE TABLE IF NOT EXISTS pool_work (
    id INT AUTO_INCREMENT PRIMARY KEY,
    job_id VARCHAR(100) NOT NULL UNIQUE,
    height INT NOT NULL,
    previous_hash VARCHAR(64) NOT NULL,
    coinbase1 TEXT NOT NULL,
    coinbase2 TEXT NOT NULL,
    merkle_branches JSON,
    version VARCHAR(8) NOT NULL,
    nbits VARCHAR(8) NOT NULL,
    ntime VARCHAR(8) NOT NULL,
    target VARCHAR(64) NOT NULL,
    difficulty DOUBLE NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_job_id (job_id),
    INDEX idx_height (height),
    INDEX idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
