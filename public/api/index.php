<?php
/**
 * Yenten Mining Pool API
 * RESTful API endpoints for pool data
 */

// Set headers
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Load dependencies
require_once __DIR__ . '/../../src/Config/ConfigManager.php';
require_once __DIR__ . '/../../src/Database/Database.php';

use YentenPool\Config\ConfigManager;
use YentenPool\Database\Database;

try {
    // Initialize configuration and database
    $config = ConfigManager::getInstance();
    $db = Database::getInstance();
    
    // Parse request
    $requestUri = $_SERVER['REQUEST_URI'];
    $path = parse_url($requestUri, PHP_URL_PATH);
    $pathParts = explode('/', trim($path, '/'));
    
    // Remove 'api' from path parts
    if ($pathParts[0] === 'api') {
        array_shift($pathParts);
    }
    
    $endpoint = $pathParts[0] ?? '';
    $method = $_SERVER['REQUEST_METHOD'];
    
    // Route requests
    switch ($endpoint) {
        case 'pool':
            handlePoolEndpoint($pathParts, $method, $db, $config);
            break;
            
        case 'miner':
            handleMinerEndpoint($pathParts, $method, $db, $config);
            break;
            
        case 'blocks':
            handleBlocksEndpoint($pathParts, $method, $db, $config);
            break;
            
        case 'stats':
            handleStatsEndpoint($pathParts, $method, $db, $config);
            break;
            
        default:
            sendError('Endpoint not found', 404);
            break;
    }
    
} catch (Exception $e) {
    sendError('Internal server error: ' . $e->getMessage(), 500);
}

/**
 * Handle pool-related endpoints
 */
function handlePoolEndpoint($pathParts, $method, $db, $config)
{
    if ($method !== 'GET') {
        sendError('Method not allowed', 405);
        return;
    }
    
    $action = $pathParts[1] ?? 'stats';
    
    switch ($action) {
        case 'stats':
            getPoolStats($db, $config);
            break;
            
        case 'config':
            getPoolConfig($config);
            break;
            
        default:
            sendError('Pool action not found', 404);
            break;
    }
}

/**
 * Handle miner-related endpoints
 */
function handleMinerEndpoint($pathParts, $method, $db, $config)
{
    if ($method !== 'GET') {
        sendError('Method not allowed', 405);
        return;
    }
    
    $address = $pathParts[1] ?? '';
    $action = $pathParts[2] ?? 'stats';
    
    if (empty($address)) {
        sendError('Miner address required', 400);
        return;
    }
    
    switch ($action) {
        case 'stats':
            getMinerStats($address, $db);
            break;
            
        case 'workers':
            getMinerWorkers($address, $db);
            break;
            
        case 'payments':
            getMinerPayments($address, $db);
            break;
            
        default:
            sendError('Miner action not found', 404);
            break;
    }
}

/**
 * Handle blocks-related endpoints
 */
function handleBlocksEndpoint($pathParts, $method, $db, $config)
{
    if ($method !== 'GET') {
        sendError('Method not allowed', 405);
        return;
    }
    
    $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 10;
    $limit = min($limit, 100); // Max 100 blocks
    
    getRecentBlocks($limit, $db);
}

/**
 * Handle stats-related endpoints
 */
function handleStatsEndpoint($pathParts, $method, $db, $config)
{
    if ($method !== 'GET') {
        sendError('Method not allowed', 405);
        return;
    }
    
    getGlobalStats($db, $config);
}

/**
 * Get pool statistics
 */
function getPoolStats($db, $config)
{
    try {
        // Get basic pool stats
        $stats = $db->fetch("
            SELECT 
                COUNT(DISTINCT u.id) as active_miners,
                COUNT(DISTINCT w.id) as active_workers,
                COALESCE(SUM(w.hashrate), 0) as total_hashrate,
                COUNT(DISTINCT b.id) as blocks_found_24h,
                COALESCE(SUM(p.amount), 0) as total_paid_out_24h
            FROM users u
            LEFT JOIN workers w ON u.id = w.user_id AND w.is_active = 1 AND w.last_seen > DATE_SUB(NOW(), INTERVAL 5 MINUTE)
            LEFT JOIN blocks b ON b.created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR) AND b.is_confirmed = 1
            LEFT JOIN payouts p ON p.status = 'completed' AND p.created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
            WHERE u.is_active = 1
        ");
        
        // Get network stats (placeholder - would come from Yenten daemon)
        $networkStats = [
            'difficulty' => 1.0,
            'height' => 1000000,
            'hashrate' => 1000000000
        ];
        
        $response = [
            'success' => true,
            'data' => [
                'pool' => $stats,
                'network' => $networkStats,
                'config' => $config->getPoolConfig()
            ]
        ];
        
        sendResponse($response);
        
    } catch (Exception $e) {
        sendError('Failed to get pool stats: ' . $e->getMessage(), 500);
    }
}

/**
 * Get pool configuration
 */
function getPoolConfig($config)
{
    $response = [
        'success' => true,
        'data' => $config->getPoolConfig()
    ];
    
    sendResponse($response);
}

/**
 * Get miner statistics
 */
function getMinerStats($address, $db)
{
    try {
        $miner = $db->fetch("
            SELECT 
                u.*,
                COUNT(DISTINCT w.id) as active_workers,
                COALESCE(SUM(w.hashrate), 0) as total_hashrate,
                COALESCE(SUM(w.shares_accepted), 0) as total_accepted_shares,
                COALESCE(SUM(w.shares_rejected), 0) as total_rejected_shares
            FROM users u
            LEFT JOIN workers w ON u.id = w.user_id AND w.is_active = 1
            WHERE u.address = :address AND u.is_active = 1
            GROUP BY u.id
        ", ['address' => $address]);
        
        if (!$miner) {
            sendError('Miner not found', 404);
            return;
        }
        
        // Calculate acceptance rate
        $totalShares = $miner['total_accepted_shares'] + $miner['total_rejected_shares'];
        $miner['acceptance_rate'] = $totalShares > 0 ? ($miner['total_accepted_shares'] / $totalShares) * 100 : 0;
        
        $response = [
            'success' => true,
            'data' => $miner
        ];
        
        sendResponse($response);
        
    } catch (Exception $e) {
        sendError('Failed to get miner stats: ' . $e->getMessage(), 500);
    }
}

/**
 * Get miner workers
 */
function getMinerWorkers($address, $db)
{
    try {
        $workers = $db->fetchAll("
            SELECT w.*
            FROM workers w
            JOIN users u ON w.user_id = u.id
            WHERE u.address = :address AND u.is_active = 1
            ORDER BY w.last_seen DESC
        ", ['address' => $address]);
        
        $response = [
            'success' => true,
            'data' => $workers
        ];
        
        sendResponse($response);
        
    } catch (Exception $e) {
        sendError('Failed to get miner workers: ' . $e->getMessage(), 500);
    }
}

/**
 * Get miner payments
 */
function getMinerPayments($address, $db)
{
    try {
        $payments = $db->fetchAll("
            SELECT p.*
            FROM payouts p
            JOIN users u ON p.user_id = u.id
            WHERE u.address = :address
            ORDER BY p.created_at DESC
            LIMIT 50
        ", ['address' => $address]);
        
        $response = [
            'success' => true,
            'data' => $payments
        ];
        
        sendResponse($response);
        
    } catch (Exception $e) {
        sendError('Failed to get miner payments: ' . $e->getMessage(), 500);
    }
}

/**
 * Get recent blocks
 */
function getRecentBlocks($limit, $db)
{
    try {
        $blocks = $db->fetchAll("
            SELECT 
                b.*,
                u.address as found_by_address,
                w.worker_name as found_by_worker
            FROM blocks b
            LEFT JOIN users u ON b.found_by_user_id = u.id
            LEFT JOIN workers w ON b.found_by_worker_id = w.id
            ORDER BY b.height DESC
            LIMIT :limit
        ", ['limit' => $limit]);
        
        $response = [
            'success' => true,
            'data' => $blocks
        ];
        
        sendResponse($response);
        
    } catch (Exception $e) {
        sendError('Failed to get recent blocks: ' . $e->getMessage(), 500);
    }
}

/**
 * Get global statistics
 */
function getGlobalStats($db, $config)
{
    try {
        $stats = $db->fetch("
            SELECT 
                COUNT(DISTINCT u.id) as total_miners,
                COUNT(DISTINCT w.id) as total_workers,
                COALESCE(SUM(w.hashrate), 0) as total_hashrate,
                COUNT(DISTINCT b.id) as total_blocks,
                COALESCE(SUM(p.amount), 0) as total_paid_out
            FROM users u
            LEFT JOIN workers w ON u.id = w.user_id
            LEFT JOIN blocks b ON b.is_confirmed = 1
            LEFT JOIN payouts p ON p.status = 'completed'
            WHERE u.is_active = 1
        ");
        
        $response = [
            'success' => true,
            'data' => $stats
        ];
        
        sendResponse($response);
        
    } catch (Exception $e) {
        sendError('Failed to get global stats: ' . $e->getMessage(), 500);
    }
}

/**
 * Send JSON response
 */
function sendResponse($data)
{
    echo json_encode($data, JSON_PRETTY_PRINT);
    exit();
}

/**
 * Send error response
 */
function sendError($message, $code = 400)
{
    http_response_code($code);
    $response = [
        'success' => false,
        'error' => $message,
        'code' => $code
    ];
    echo json_encode($response, JSON_PRETTY_PRINT);
    exit();
}
