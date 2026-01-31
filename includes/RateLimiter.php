<?php
/**
 * Rate Limiter
 * 
 * Provides rate limiting for login/register endpoints to prevent brute force attacks.
 * Uses WordPress transients for storage (works with object caching).
 * 
 * @package PremierBioLabs\ResearchAccessGate
 * @since 2.0.2
 */

declare(strict_types=1);

namespace PremierBioLabs\ResearchAccessGate;

defined('ABSPATH') || exit;

class RateLimiter {
    
    /**
     * Default configuration
     */
    private const DEFAULTS = [
        'max_attempts'    => 5,           // Max attempts before lockout
        'lockout_time'    => 15 * 60,     // Lockout duration in seconds (15 minutes)
        'decay_time'      => 60 * 60,     // Time before attempts reset (1 hour)
        'enabled'         => true,        // Whether rate limiting is enabled
    ];
    
    /**
     * Transient prefix for rate limit data
     */
    private const TRANSIENT_PREFIX = 'rag_rate_';
    
    /**
     * Get rate limiter configuration
     * 
     * @return array Configuration array
     */
    public static function get_config(): array {
        $config = self::DEFAULTS;
        
        // Allow configuration via constants in wp-config.php
        if (defined('RAG_RATE_LIMIT_ATTEMPTS')) {
            $config['max_attempts'] = absint(RAG_RATE_LIMIT_ATTEMPTS);
        }
        if (defined('RAG_RATE_LIMIT_LOCKOUT')) {
            $config['lockout_time'] = absint(RAG_RATE_LIMIT_LOCKOUT);
        }
        if (defined('RAG_RATE_LIMIT_DECAY')) {
            $config['decay_time'] = absint(RAG_RATE_LIMIT_DECAY);
        }
        if (defined('RAG_RATE_LIMIT_ENABLED')) {
            $config['enabled'] = (bool) RAG_RATE_LIMIT_ENABLED;
        }
        
        // Allow filter override
        return apply_filters('rag_rate_limiter_config', $config);
    }
    
    /**
     * Check if rate limiting is enabled
     * 
     * @return bool
     */
    public static function is_enabled(): bool {
        $config = self::get_config();
        return $config['enabled'];
    }
    
    /**
     * Generate a unique key for the rate limit based on IP and action
     * 
     * @param string $action The action being rate limited (e.g., 'login', 'register')
     * @param string|null $identifier Optional additional identifier (e.g., username)
     * @return string The rate limit key
     */
    private static function get_key(string $action, ?string $identifier = null): string {
        $ip = Helpers::get_client_ip();
        $key_parts = [$action, md5($ip)];
        
        if ($identifier !== null) {
            $key_parts[] = md5($identifier);
        }
        
        return self::TRANSIENT_PREFIX . implode('_', $key_parts);
    }
    
    /**
     * Get current rate limit data for an action
     * 
     * @param string $action The action being rate limited
     * @param string|null $identifier Optional additional identifier
     * @return array Rate limit data
     */
    private static function get_data(string $action, ?string $identifier = null): array {
        $key = self::get_key($action, $identifier);
        $data = get_transient($key);
        
        if ($data === false) {
            return [
                'attempts'     => 0,
                'first_attempt'=> 0,
                'locked_until' => 0,
            ];
        }
        
        return $data;
    }
    
    /**
     * Save rate limit data
     * 
     * @param string $action The action being rate limited
     * @param array $data Rate limit data
     * @param string|null $identifier Optional additional identifier
     */
    private static function save_data(string $action, array $data, ?string $identifier = null): void {
        $key = self::get_key($action, $identifier);
        $config = self::get_config();
        
        set_transient($key, $data, $config['decay_time']);
    }
    
    /**
     * Check if an action is currently rate limited
     * 
     * @param string $action The action to check (e.g., 'login', 'register')
     * @param string|null $identifier Optional additional identifier
     * @return array Result with 'allowed' boolean and 'message' if blocked
     */
    public static function check(string $action, ?string $identifier = null): array {
        if (!self::is_enabled()) {
            return ['allowed' => true];
        }
        
        $config = self::get_config();
        $data = self::get_data($action, $identifier);
        $now = time();
        
        // Check if currently locked out
        if ($data['locked_until'] > $now) {
            $remaining = $data['locked_until'] - $now;
            $minutes = ceil($remaining / 60);
            
            return [
                'allowed'   => false,
                'message'   => sprintf(
                    /* translators: %d: minutes remaining */
                    __('Too many failed attempts. Please try again in %d minute(s).', 'research-access-gate'),
                    $minutes
                ),
                'retry_after' => $remaining,
                'code'      => 'rate_limited',
            ];
        }
        
        // Check if attempts should be reset (decay time passed)
        if ($data['first_attempt'] > 0 && ($now - $data['first_attempt']) > $config['decay_time']) {
            self::reset($action, $identifier);
            return ['allowed' => true];
        }
        
        // Check if at max attempts
        if ($data['attempts'] >= $config['max_attempts']) {
            // Trigger lockout
            $data['locked_until'] = $now + $config['lockout_time'];
            self::save_data($action, $data, $identifier);
            
            $minutes = ceil($config['lockout_time'] / 60);
            
            return [
                'allowed'   => false,
                'message'   => sprintf(
                    /* translators: %d: minutes remaining */
                    __('Too many failed attempts. Please try again in %d minute(s).', 'research-access-gate'),
                    $minutes
                ),
                'retry_after' => $config['lockout_time'],
                'code'      => 'rate_limited',
            ];
        }
        
        return ['allowed' => true];
    }
    
    /**
     * Record a failed attempt
     * 
     * @param string $action The action that failed
     * @param string|null $identifier Optional additional identifier
     * @return array Updated rate limit status
     */
    public static function record_failure(string $action, ?string $identifier = null): array {
        if (!self::is_enabled()) {
            return ['attempts' => 0, 'remaining' => PHP_INT_MAX];
        }
        
        $config = self::get_config();
        $data = self::get_data($action, $identifier);
        $now = time();
        
        // Initialize first attempt time if needed
        if ($data['first_attempt'] === 0) {
            $data['first_attempt'] = $now;
        }
        
        // Increment attempts
        $data['attempts']++;
        
        // Check if this triggers a lockout
        if ($data['attempts'] >= $config['max_attempts']) {
            $data['locked_until'] = $now + $config['lockout_time'];
        }
        
        self::save_data($action, $data, $identifier);
        
        return [
            'attempts'  => $data['attempts'],
            'remaining' => max(0, $config['max_attempts'] - $data['attempts']),
            'locked'    => $data['locked_until'] > $now,
        ];
    }
    
    /**
     * Record a successful attempt (clears rate limit)
     * 
     * @param string $action The action that succeeded
     * @param string|null $identifier Optional additional identifier
     */
    public static function record_success(string $action, ?string $identifier = null): void {
        self::reset($action, $identifier);
    }
    
    /**
     * Reset rate limit data for an action
     * 
     * @param string $action The action to reset
     * @param string|null $identifier Optional additional identifier
     */
    public static function reset(string $action, ?string $identifier = null): void {
        $key = self::get_key($action, $identifier);
        delete_transient($key);
    }
    
    /**
     * Get remaining attempts before lockout
     * 
     * @param string $action The action to check
     * @param string|null $identifier Optional additional identifier
     * @return int Number of remaining attempts
     */
    public static function get_remaining_attempts(string $action, ?string $identifier = null): int {
        if (!self::is_enabled()) {
            return PHP_INT_MAX;
        }
        
        $config = self::get_config();
        $data = self::get_data($action, $identifier);
        
        return max(0, $config['max_attempts'] - $data['attempts']);
    }
    
    /**
     * Check if currently locked out
     * 
     * @param string $action The action to check
     * @param string|null $identifier Optional additional identifier
     * @return bool True if locked out
     */
    public static function is_locked_out(string $action, ?string $identifier = null): bool {
        $data = self::get_data($action, $identifier);
        return $data['locked_until'] > time();
    }
    
    /**
     * Get lockout remaining time in seconds
     * 
     * @param string $action The action to check
     * @param string|null $identifier Optional additional identifier
     * @return int Seconds remaining, 0 if not locked
     */
    public static function get_lockout_remaining(string $action, ?string $identifier = null): int {
        $data = self::get_data($action, $identifier);
        $remaining = $data['locked_until'] - time();
        return max(0, $remaining);
    }
    
    /**
     * Clean up expired rate limit transients
     * Called via cron or manually
     */
    public static function cleanup_expired(): int {
        global $wpdb;
        
        $deleted = $wpdb->query($wpdb->prepare("
            DELETE a, b FROM {$wpdb->options} a
            INNER JOIN {$wpdb->options} b 
                ON b.option_name = CONCAT('_transient_timeout_', SUBSTRING(a.option_name, 12))
            WHERE a.option_name LIKE %s
            AND b.option_value < %d
        ", '_transient_' . self::TRANSIENT_PREFIX . '%', time()));
        
        return (int) $deleted;
    }
}
