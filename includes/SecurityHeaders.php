<?php
/**
 * Security Headers
 * 
 * Implements Content Security Policy (CSP) and other security headers
 * for the Research Access Gate plugin.
 * 
 * @package PremierBioLabs\ResearchAccessGate
 * @since 2.0.2
 */

declare(strict_types=1);

namespace PremierBioLabs\ResearchAccessGate;

defined('ABSPATH') || exit;

class SecurityHeaders {
    
    /**
     * Default CSP directives
     */
    private const DEFAULT_CSP = [
        'default-src'     => ["'self'"],
        'script-src'      => ["'self'", "'unsafe-inline'"],  // Required for inline modal JS
        'style-src'       => ["'self'", "'unsafe-inline'"],  // Required for inline modal CSS
        'img-src'         => ["'self'", 'data:', 'https:'],
        'font-src'        => ["'self'", 'data:'],
        'connect-src'     => ["'self'"],
        'frame-ancestors' => ["'self'"],
        'form-action'     => ["'self'"],
        'base-uri'        => ["'self'"],
    ];
    
    /**
     * CSP directives for CAPTCHA providers
     */
    private const CAPTCHA_CSP = [
        'recaptcha' => [
            'script-src'  => ['https://www.google.com/recaptcha/', 'https://www.gstatic.com/recaptcha/'],
            'frame-src'   => ['https://www.google.com/recaptcha/', 'https://recaptcha.google.com/'],
            'connect-src' => ['https://www.google.com/recaptcha/'],
        ],
        'hcaptcha' => [
            'script-src'  => ['https://hcaptcha.com', 'https://*.hcaptcha.com'],
            'frame-src'   => ['https://hcaptcha.com', 'https://*.hcaptcha.com'],
            'style-src'   => ['https://hcaptcha.com', 'https://*.hcaptcha.com'],
            'connect-src' => ['https://hcaptcha.com', 'https://*.hcaptcha.com'],
        ],
        'turnstile' => [
            'script-src'  => ['https://challenges.cloudflare.com'],
            'frame-src'   => ['https://challenges.cloudflare.com'],
            'connect-src' => ['https://challenges.cloudflare.com'],
        ],
    ];
    
    private Settings $settings;
    private bool $headers_sent = false;
    
    public function __construct(Settings $settings) {
        $this->settings = $settings;
    }
    
    /**
     * Initialize security headers
     */
    public function init(): void {
        // Only add headers if enabled
        if ($this->settings->get('security_headers_enabled') !== 'yes') {
            return;
        }
        
        // Add headers on send_headers action (frontend only)
        add_action('send_headers', [$this, 'send_security_headers'], 10);
        
        // Also hook into REST API for our endpoints
        add_filter('rest_pre_serve_request', [$this, 'add_rest_security_headers'], 10, 4);
    }
    
    /**
     * Send security headers for frontend requests
     */
    public function send_security_headers(): void {
        // Skip if already sent or in admin/REST context
        if ($this->headers_sent || is_admin() || (defined('REST_REQUEST') && REST_REQUEST)) {
            return;
        }
        
        // Skip for login page
        global $pagenow;
        if ($pagenow === 'wp-login.php') {
            return;
        }
        
        $this->send_headers();
        $this->headers_sent = true;
    }
    
    /**
     * Add security headers to REST API responses
     * 
     * @param bool $served Whether the request has already been served
     * @param \WP_REST_Response $result Response object
     * @param \WP_REST_Request $request Request object
     * @param \WP_REST_Server $server Server instance
     * @return bool
     */
    public function add_rest_security_headers(bool $served, $result, $request, $server): bool {
        // Only add headers for our plugin's endpoints
        $route = $request->get_route();
        if (strpos($route, '/rag/v1/') !== 0) {
            return $served;
        }
        
        // Add basic security headers for REST responses
        if (!headers_sent()) {
            header('X-Content-Type-Options: nosniff');
            header('X-Frame-Options: DENY');
        }
        
        return $served;
    }
    
    /**
     * Send all security headers
     */
    private function send_headers(): void {
        if (headers_sent()) {
            return;
        }
        
        // Prevent clickjacking
        header('X-Frame-Options: SAMEORIGIN');
        
        // Prevent MIME type sniffing
        header('X-Content-Type-Options: nosniff');
        
        // XSS Protection (legacy, but still useful)
        header('X-XSS-Protection: 1; mode=block');
        
        // Referrer Policy
        header('Referrer-Policy: strict-origin-when-cross-origin');
        
        // Permissions Policy (disable unnecessary features)
        $permissions = $this->build_permissions_policy();
        header("Permissions-Policy: {$permissions}");
        
        // Content Security Policy
        $csp = $this->build_csp();
        
        // Use Report-Only mode if configured (for testing)
        $csp_mode = $this->settings->get('csp_report_only') === 'yes' 
            ? 'Content-Security-Policy-Report-Only' 
            : 'Content-Security-Policy';
        
        header("{$csp_mode}: {$csp}");
    }
    
    /**
     * Build Content Security Policy string
     * 
     * @return string CSP header value
     */
    private function build_csp(): string {
        $directives = self::DEFAULT_CSP;
        
        // Add CAPTCHA provider CSP if configured
        $captcha_provider = $this->settings->get('captcha_provider');
        if (!empty($captcha_provider) && isset(self::CAPTCHA_CSP[$captcha_provider])) {
            $directives = $this->merge_csp_directives($directives, self::CAPTCHA_CSP[$captcha_provider]);
        }
        
        // Add custom logo URL domain if set
        $logo_url = $this->settings->get('logo_url');
        if (!empty($logo_url)) {
            $logo_domain = $this->extract_domain($logo_url);
            if ($logo_domain && $logo_domain !== 'self') {
                $directives['img-src'][] = $logo_domain;
            }
        }
        
        // Allow filtering for advanced customization
        $directives = apply_filters('rag_csp_directives', $directives);
        
        // Build CSP string
        $csp_parts = [];
        foreach ($directives as $directive => $values) {
            if (!empty($values)) {
                $csp_parts[] = $directive . ' ' . implode(' ', array_unique($values));
            }
        }
        
        return implode('; ', $csp_parts);
    }
    
    /**
     * Merge CSP directives
     * 
     * @param array $base Base directives
     * @param array $additional Additional directives to merge
     * @return array Merged directives
     */
    private function merge_csp_directives(array $base, array $additional): array {
        foreach ($additional as $directive => $values) {
            if (isset($base[$directive])) {
                $base[$directive] = array_merge($base[$directive], $values);
            } else {
                $base[$directive] = $values;
            }
        }
        return $base;
    }
    
    /**
     * Build Permissions Policy string
     * 
     * @return string Permissions Policy header value
     */
    private function build_permissions_policy(): string {
        $policies = [
            'geolocation'        => '()',
            'microphone'         => '()',
            'camera'             => '()',
            'payment'            => '()',
            'usb'                => '()',
            'magnetometer'       => '()',
            'gyroscope'          => '()',
            'accelerometer'      => '()',
            'autoplay'           => '(self)',
            'fullscreen'         => '(self)',
        ];
        
        // Allow filtering
        $policies = apply_filters('rag_permissions_policy', $policies);
        
        $parts = [];
        foreach ($policies as $feature => $value) {
            $parts[] = "{$feature}={$value}";
        }
        
        return implode(', ', $parts);
    }
    
    /**
     * Extract domain from URL for CSP
     * 
     * @param string $url URL to extract domain from
     * @return string|null Domain or null if invalid
     */
    private function extract_domain(string $url): ?string {
        $parsed = parse_url($url);
        if (!isset($parsed['host'])) {
            return null;
        }
        
        $scheme = $parsed['scheme'] ?? 'https';
        return "{$scheme}://{$parsed['host']}";
    }
    
    /**
     * Get current CSP configuration for display
     * 
     * @return array CSP directives array
     */
    public function get_current_csp(): array {
        $directives = self::DEFAULT_CSP;
        
        $captcha_provider = $this->settings->get('captcha_provider');
        if (!empty($captcha_provider) && isset(self::CAPTCHA_CSP[$captcha_provider])) {
            $directives = $this->merge_csp_directives($directives, self::CAPTCHA_CSP[$captcha_provider]);
        }
        
        return apply_filters('rag_csp_directives', $directives);
    }
    
    /**
     * Check if security headers are enabled
     * 
     * @return bool
     */
    public function is_enabled(): bool {
        return $this->settings->get('security_headers_enabled') === 'yes';
    }
}
