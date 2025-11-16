<?php
/**
 * VideoStoreConfig.php
 * 
 * Configuration management for the video store module.
 * This class handles all configuration defaults and environment-specific settings
 * for video store operations.
 */

class VideoStoreConfig
{
    /**
     * @var array Default configuration values
     */
    private static $defaults = [
        'items_per_page' => 20,
        'max_items_per_page' => 100,
        'cache_ttl' => 3600, // 1 hour in seconds
        'enable_caching' => true,
        'default_sort' => 'date',
        'allowed_sort_fields' => ['date', 'title', 'price', 'rating'],
    ];

    /**
     * Get a configuration value
     * 
     * @param string $key Configuration key
     * @param mixed $default Default value if not found
     * @return mixed Configuration value
     */
    public static function get($key, $default = null)
    {
        // Check environment variable first
        $envKey = 'VIDEO_STORE_' . strtoupper($key);
        $envValue = getenv($envKey);
        
        if ($envValue !== false) {
            return $envValue;
        }
        
        // Fall back to defaults
        return self::$defaults[$key] ?? $default;
    }

    /**
     * Set a configuration value at runtime
     * 
     * @param string $key Configuration key
     * @param mixed $value Configuration value
     */
    public static function set($key, $value)
    {
        self::$defaults[$key] = $value;
    }

    /**
     * Get all configuration values
     * 
     * @return array All configuration values
     */
    public static function all()
    {
        return self::$defaults;
    }
}
