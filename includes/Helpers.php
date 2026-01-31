<?php
/**
 * Helper Functions
 * 
 * @package PremierBioLabs\ResearchAccessGate
 * @since 2.0.0
 * @updated 2.0.1 - Improved IP address handling with validation
 */

declare(strict_types=1);

namespace PremierBioLabs\ResearchAccessGate;

defined('ABSPATH') || exit;

class Helpers {
    
    /**
     * Trusted proxy headers in order of preference
     * Can be filtered to customize for specific hosting environments
     */
    private const PROXY_HEADERS = [
        'HTTP_CF_CONNECTING_IP',    // Cloudflare
        'HTTP_X_REAL_IP',           // Nginx proxy
        'HTTP_X_FORWARDED_FOR',     // Standard proxy header
    ];
    
    /**
     * Get client IP address with validation
     * 
     * Note: Proxy headers can be spoofed. For security-critical applications,
     * configure your server to only trust specific proxy IPs.
     * 
     * @return string Sanitized IP address or 'unknown'
     */
    public static function get_client_ip(): string {
        $ip = null;
        
        // Allow filtering of trusted headers for specific environments
        $trusted_headers = apply_filters('rag_trusted_proxy_headers', self::PROXY_HEADERS);
        
        // Check proxy headers first (in order of trust)
        foreach ($trusted_headers as $header) {
            if (!empty($_SERVER[$header])) {
                $ip = $_SERVER[$header];
                break;
            }
        }
        
        // Fallback to REMOTE_ADDR
        if (empty($ip)) {
            $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        }
        
        // Handle comma-separated IPs (X-Forwarded-For can contain multiple)
        if (str_contains($ip, ',')) {
            $ips = explode(',', $ip);
            $ip = trim($ips[0]); // First IP is the original client
        }
        
        // Sanitize and validate
        $ip = sanitize_text_field(wp_unslash($ip));
        
        // Validate IP format (IPv4 or IPv6)
        if (!self::is_valid_ip($ip)) {
            return 'unknown';
        }
        
        return $ip;
    }
    
    /**
     * Validate IP address format
     * 
     * @param string $ip IP address to validate
     * @return bool True if valid IPv4 or IPv6 address
     */
    public static function is_valid_ip(string $ip): bool {
        return filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4 | FILTER_FLAG_IPV6) !== false;
    }
    
    /**
     * Convert hex color to RGB
     */
    public static function hex_to_rgb(string $hex): string {
        $hex = ltrim($hex, '#');
        
        if (strlen($hex) === 3) {
            $hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
        }
        
        $rgb = array_map('hexdec', str_split($hex, 2));
        
        return implode(',', $rgb);
    }
    
    /**
     * Check if WooCommerce is active
     */
    public static function is_woocommerce_active(): bool {
        return class_exists('WooCommerce');
    }
}
