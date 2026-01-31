<?php
/**
 * License Key Manager
 * 
 * Handles license key validation for Research Access Gate.
 * Uses HMAC-based validation with optional server verification.
 * 
 * @package PremierBioLabs\ResearchAccessGate
 * @since 2.0.0
 * @updated 2.0.2 - Moved secret salt to wp-config.php constant
 */

declare(strict_types=1);

namespace PremierBioLabs\ResearchAccessGate;

defined('ABSPATH') || exit;

class License {
    
    private const OPTION_KEY = 'rag_license';
    private const TRANSIENT_STATUS = 'rag_license_status';
    private const KEY_PREFIX = 'RAG';
    
    /**
     * Default fallback salt (for backward compatibility)
     * IMPORTANT: For production, define RAG_LICENSE_SALT in wp-config.php
     */
    private const DEFAULT_SALT = 'PBL_RAG_2026_CHANGE_ME';
    
    /**
     * Get the license secret salt
     * 
     * Priority:
     * 1. RAG_LICENSE_SALT constant (recommended - define in wp-config.php)
     * 2. rag_license_salt filter (for programmatic override)
     * 3. Default fallback (not recommended for production)
     * 
     * @return string The secret salt for HMAC validation
     */
    private static function get_secret_salt(): string {
        // Check for wp-config.php constant first (most secure)
        if (defined('RAG_LICENSE_SALT') && !empty(RAG_LICENSE_SALT)) {
            return RAG_LICENSE_SALT;
        }
        
        // Allow filter override (for advanced use cases)
        $filtered_salt = apply_filters('rag_license_salt', null);
        if (!empty($filtered_salt) && is_string($filtered_salt)) {
            return $filtered_salt;
        }
        
        // Fallback to default (triggers admin notice)
        return self::DEFAULT_SALT;
    }
    
    /**
     * Check if custom salt is configured
     * 
     * @return bool True if a custom salt is defined
     */
    public static function is_salt_configured(): bool {
        return defined('RAG_LICENSE_SALT') && !empty(RAG_LICENSE_SALT);
    }
    
    private static ?array $status = null;
    
    /**
     * Get current license data
     */
    public static function get_license(): array {
        $default = [
            'key'           => '',
            'email'         => '',
            'activated_at'  => '',
            'domain'        => '',
            'status'        => 'inactive',
        ];
        
        return get_option(self::OPTION_KEY, $default);
    }
    
    /**
     * Save license data
     */
    public static function save_license(array $data): bool {
        delete_transient(self::TRANSIENT_STATUS);
        self::$status = null;
        return update_option(self::OPTION_KEY, $data, false);
    }
    
    /**
     * Check if license is valid
     */
    public static function is_valid(): bool {
        $status = self::get_status();
        return $status['valid'] === true;
    }
    
    /**
     * Get detailed license status
     */
    public static function get_status(): array {
        if (self::$status !== null) {
            return self::$status;
        }
        
        // Check transient cache
        $cached = get_transient(self::TRANSIENT_STATUS);
        if ($cached !== false) {
            self::$status = $cached;
            return self::$status;
        }
        
        $license = self::get_license();
        $status = [
            'valid'         => false,
            'status'        => 'inactive',
            'message'       => __('No license key entered.', 'research-access-gate'),
            'expires'       => null,
            'features'      => [],
        ];
        
        if (empty($license['key'])) {
            self::$status = $status;
            return $status;
        }
        
        // Validate key format and signature
        $validation = self::validate_key($license['key']);
        
        if (!$validation['valid']) {
            $status['status'] = 'invalid';
            $status['message'] = $validation['message'];
            self::$status = $status;
            set_transient(self::TRANSIENT_STATUS, $status, HOUR_IN_SECONDS);
            return $status;
        }
        
        // Check domain binding
        $current_domain = self::get_domain();
        if (!empty($license['domain']) && $license['domain'] !== $current_domain) {
            $status['status'] = 'domain_mismatch';
            $status['message'] = __('License is registered to a different domain.', 'research-access-gate');
            self::$status = $status;
            set_transient(self::TRANSIENT_STATUS, $status, HOUR_IN_SECONDS);
            return $status;
        }
        
        // License is valid
        $status['valid'] = true;
        $status['status'] = 'active';
        $status['message'] = __('License is active.', 'research-access-gate');
        $status['features'] = $validation['features'] ?? ['full'];
        $status['tier'] = $validation['tier'] ?? 'pro';
        
        self::$status = $status;
        set_transient(self::TRANSIENT_STATUS, $status, 12 * HOUR_IN_SECONDS);
        
        return $status;
    }
    
    /**
     * Activate a license key
     */
    public static function activate(string $key, string $email = ''): array {
        $key = strtoupper(trim($key));
        
        // Validate key
        $validation = self::validate_key($key);
        
        if (!$validation['valid']) {
            return [
                'success' => false,
                'message' => $validation['message'],
            ];
        }
        
        // Save license
        $license_data = [
            'key'           => $key,
            'email'         => sanitize_email($email),
            'activated_at'  => current_time('mysql'),
            'domain'        => self::get_domain(),
            'status'        => 'active',
        ];
        
        self::save_license($license_data);
        
        return [
            'success' => true,
            'message' => __('License activated successfully!', 'research-access-gate'),
            'tier'    => $validation['tier'] ?? 'pro',
        ];
    }
    
    /**
     * Deactivate license
     */
    public static function deactivate(): array {
        self::save_license([
            'key'           => '',
            'email'         => '',
            'activated_at'  => '',
            'domain'        => '',
            'status'        => 'inactive',
        ]);
        
        return [
            'success' => true,
            'message' => __('License deactivated.', 'research-access-gate'),
        ];
    }
    
    /**
     * Validate license key format and signature
     * 
     * Key Format: RAG-XXXX-XXXX-XXXX-XXXX
     * Where the last segment is a checksum
     */
    public static function validate_key(string $key): array {
        $key = strtoupper(trim($key));
        
        // Check format: RAG-XXXX-XXXX-XXXX-XXXX (25 chars)
        if (!preg_match('/^RAG-[A-Z0-9]{4}-[A-Z0-9]{4}-[A-Z0-9]{4}-[A-Z0-9]{4}$/', $key)) {
            return [
                'valid'   => false,
                'message' => __('Invalid license key format.', 'research-access-gate'),
            ];
        }
        
        // Split key parts
        $parts = explode('-', $key);
        $prefix = $parts[0]; // RAG
        $segment1 = $parts[1];
        $segment2 = $parts[2];
        $segment3 = $parts[3];
        $checksum = $parts[4];
        
        // Validate checksum
        $data = $prefix . $segment1 . $segment2 . $segment3;
        $expected_checksum = self::generate_checksum($data);
        
        if ($checksum !== $expected_checksum) {
            return [
                'valid'   => false,
                'message' => __('Invalid license key.', 'research-access-gate'),
            ];
        }
        
        // Determine tier from segment1
        $tier = 'pro';
        $features = ['full'];
        
        if (str_starts_with($segment1, 'SITE')) {
            $tier = 'single';
            $features = ['single_site'];
        } elseif (str_starts_with($segment1, 'UNLM')) {
            $tier = 'unlimited';
            $features = ['unlimited_sites', 'priority_support'];
        } elseif (str_starts_with($segment1, 'DEV0')) {
            $tier = 'developer';
            $features = ['unlimited_sites', 'white_label', 'priority_support'];
        }
        
        return [
            'valid'    => true,
            'tier'     => $tier,
            'features' => $features,
            'message'  => __('Valid license key.', 'research-access-gate'),
        ];
    }
    
    /**
     * Generate a license key
     * 
     * @param string $tier 'single', 'pro', 'unlimited', 'developer'
     * @return string License key
     */
    public static function generate_key(string $tier = 'pro'): string {
        // Tier prefix
        $tier_prefix = match ($tier) {
            'single'    => 'SITE',
            'unlimited' => 'UNLM',
            'developer' => 'DEV0',
            default     => 'PRO0',
        };
        
        // Generate random segments
        $segment1 = $tier_prefix;
        $segment2 = self::random_segment();
        $segment3 = self::random_segment();
        
        // Generate checksum
        $data = self::KEY_PREFIX . $segment1 . $segment2 . $segment3;
        $checksum = self::generate_checksum($data);
        
        return self::KEY_PREFIX . '-' . $segment1 . '-' . $segment2 . '-' . $segment3 . '-' . $checksum;
    }
    
    /**
     * Generate checksum for key validation
     * 
     * Uses HMAC-SHA256 with configurable secret salt.
     * Salt should be defined as RAG_LICENSE_SALT in wp-config.php
     */
    private static function generate_checksum(string $data): string {
        $salt = self::get_secret_salt();
        $hash = hash_hmac('sha256', $data, $salt);
        // Take first 4 chars and convert to uppercase alphanumeric
        $checksum = strtoupper(substr(preg_replace('/[^A-Za-z0-9]/', '', $hash), 0, 4));
        return $checksum;
    }
    
    /**
     * Generate random 4-char segment
     */
    private static function random_segment(): string {
        $chars = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789'; // Exclude confusing chars (0,O,1,I)
        $segment = '';
        for ($i = 0; $i < 4; $i++) {
            $segment .= $chars[random_int(0, strlen($chars) - 1)];
        }
        return $segment;
    }
    
    /**
     * Get current domain
     */
    private static function get_domain(): string {
        $url = home_url();
        $parsed = parse_url($url);
        $domain = $parsed['host'] ?? '';
        
        // Remove www.
        $domain = preg_replace('/^www\./', '', $domain);
        
        return strtolower($domain);
    }
    
    /**
     * Check if this is a development/local environment
     */
    public static function is_dev_environment(): bool {
        $domain = self::get_domain();
        
        $dev_patterns = [
            'localhost',
            '127.0.0.1',
            '.local',
            '.test',
            '.dev',
            'staging.',
            'dev.',
            '.lndo.site',
        ];
        
        foreach ($dev_patterns as $pattern) {
            if (str_contains($domain, $pattern)) {
                return true;
            }
        }
        
        return false;
    }
}
