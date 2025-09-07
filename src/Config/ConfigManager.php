<?php

namespace YentenPool\Config;

/**
 * Configuration Management System
 * Loads and manages pool configuration settings
 */
class ConfigManager
{
    private static $instance = null;
    private $config = [];
    private $configFile;

    /**
     * Private constructor for singleton pattern
     */
    private function __construct($configFile = null)
    {
        $this->configFile = $configFile ?: __DIR__ . '/../../config/config.json';
        $this->loadConfig();
    }

    /**
     * Get singleton instance
     */
    public static function getInstance($configFile = null)
    {
        if (self::$instance === null) {
            self::$instance = new self($configFile);
        }
        return self::$instance;
    }

    /**
     * Load configuration from JSON file
     */
    private function loadConfig()
    {
        if (!file_exists($this->configFile)) {
            $templateFile = str_replace('config.json', 'config.template.json', $this->configFile);
            
            if (file_exists($templateFile)) {
                throw new \Exception("Configuration file not found: {$this->configFile}\n" .
                    "Please copy config/config.template.json to config/config.json and update the settings.\n" .
                    "cp config/config.template.json config/config.json");
            } else {
                throw new \Exception("Configuration file not found: {$this->configFile}");
            }
        }

        $json = file_get_contents($this->configFile);
        $this->config = json_decode($json, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \Exception("Invalid JSON in configuration file: " . json_last_error_msg());
        }

        $this->validateConfig();
    }

    /**
     * Validate required configuration settings
     */
    private function validateConfig()
    {
        $required = [
            'database.host',
            'database.name',
            'database.username',
            'database.password',
            'pool.name',
            'pool.url',
            'yenten.daemon_host',
            'yenten.daemon_port'
        ];

        foreach ($required as $key) {
            if (!$this->get($key)) {
                throw new \Exception("Required configuration missing: {$key}");
            }
        }
    }

    /**
     * Get configuration value using dot notation
     */
    public function get($key, $default = null)
    {
        $keys = explode('.', $key);
        $value = $this->config;

        foreach ($keys as $k) {
            if (!isset($value[$k])) {
                return $default;
            }
            $value = $value[$k];
        }

        return $value;
    }

    /**
     * Set configuration value using dot notation
     */
    public function set($key, $value)
    {
        $keys = explode('.', $key);
        $config = &$this->config;

        foreach ($keys as $k) {
            if (!isset($config[$k]) || !is_array($config[$k])) {
                $config[$k] = [];
            }
            $config = &$config[$k];
        }

        $config = $value;
    }

    /**
     * Get all configuration
     */
    public function getAll()
    {
        return $this->config;
    }

    /**
     * Get database configuration
     */
    public function getDatabaseConfig()
    {
        return [
            'host' => $this->get('database.host'),
            'port' => $this->get('database.port', 3306),
            'name' => $this->get('database.name'),
            'username' => $this->get('database.username'),
            'password' => $this->get('database.password')
        ];
    }

    /**
     * Get pool configuration
     */
    public function getPoolConfig()
    {
        return [
            'name' => $this->get('pool.name'),
            'url' => $this->get('pool.url'),
            'fee_percent' => $this->get('pool.fee_percent', 1.0),
            'minimum_payout' => $this->get('pool.minimum_payout', 0.1),
            'payout_threshold' => $this->get('pool.payout_threshold', 0.5),
            'block_reward' => $this->get('pool.block_reward', 50.0),
            'stratum_ports' => $this->get('pool.stratum_ports', [3333, 4444, 5555]),
            'difficulty_multiplier' => $this->get('pool.difficulty_multiplier', 1.0)
        ];
    }

    /**
     * Get Yenten daemon configuration
     */
    public function getYentenConfig()
    {
        return [
            'daemon_host' => $this->get('yenten.daemon_host'),
            'daemon_port' => $this->get('yenten.daemon_port'),
            'daemon_user' => $this->get('yenten.daemon_user'),
            'daemon_password' => $this->get('yenten.daemon_password'),
            'wallet_address' => $this->get('yenten.wallet_address')
        ];
    }

    /**
     * Get security configuration
     */
    public function getSecurityConfig()
    {
        return [
            'session_timeout' => $this->get('security.session_timeout', 3600),
            'max_login_attempts' => $this->get('security.max_login_attempts', 5),
            'rate_limit_requests' => $this->get('security.rate_limit_requests', 100),
            'rate_limit_window' => $this->get('security.rate_limit_window', 3600)
        ];
    }

    /**
     * Get logging configuration
     */
    public function getLoggingConfig()
    {
        return [
            'level' => $this->get('logging.level', 'INFO'),
            'file' => $this->get('logging.file', 'logs/pool.log'),
            'max_size' => $this->get('logging.max_size', '10MB'),
            'max_files' => $this->get('logging.max_files', 5)
        ];
    }

    /**
     * Get API configuration
     */
    public function getApiConfig()
    {
        return [
            'enabled' => $this->get('api.enabled', true),
            'rate_limit' => $this->get('api.rate_limit', 1000),
            'cors_origins' => $this->get('api.cors_origins', [])
        ];
    }

    /**
     * Check if running in production environment
     */
    public function isProduction()
    {
        return $this->get('environment') === 'production';
    }

    /**
     * Check if running in development environment
     */
    public function isDevelopment()
    {
        return $this->get('environment') === 'development';
    }

    /**
     * Reload configuration from file
     */
    public function reload()
    {
        $this->loadConfig();
    }

    /**
     * Save configuration to file
     */
    public function save()
    {
        $json = json_encode($this->config, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        if (file_put_contents($this->configFile, $json) === false) {
            throw new \Exception("Failed to save configuration file: {$this->configFile}");
        }
    }
}
