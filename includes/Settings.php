<?php
/**
 * Settings Handler with Transient Caching
 * 
 * 2025-2026 Best Practice: Cache settings in transients
 * to reduce database queries on every page load
 * 
 * @package PremierBioLabs\ResearchAccessGate
 * @since 2.0.0
 * @updated 2.0.2 - Added security settings (CAPTCHA, CSP, rate limiting)
 * @updated 2.0.4 - Fixed cache invalidation on Settings API save
 */

declare(strict_types=1);

namespace PremierBioLabs\ResearchAccessGate;

defined('ABSPATH') || exit;

class Settings {
    
    private const OPTION_KEY = 'rag_settings';
    private const CACHE_KEY = 'rag_settings_cache';
    private const CACHE_EXPIRATION = DAY_IN_SECONDS;
    
    private ?array $settings = null;
    
    /**
     * Constructor - register cache invalidation hook
     */
    public function __construct() {
        // Clear cache when option is updated via Settings API
        add_action('update_option_' . self::OPTION_KEY, [$this, 'clear_cache'], 10, 0);
        add_action('add_option_' . self::OPTION_KEY, [$this, 'clear_cache'], 10, 0);
    }
    
    /**
     * Clear settings cache
     */
    public function clear_cache(): void {
        delete_transient(self::CACHE_KEY);
        $this->settings = null;
    }
    
    /**
     * Get all settings with caching
     */
    public function get_all(): array {
        if ($this->settings !== null) {
            return $this->settings;
        }
        
        // Try transient cache first (faster than options)
        $cached = get_transient(self::CACHE_KEY);
        if ($cached !== false && is_array($cached)) {
            $this->settings = $cached;
            return $this->settings;
        }
        
        // Fallback to database
        $options = get_option(self::OPTION_KEY, []);
        $this->settings = wp_parse_args($options, self::get_defaults());
        
        // Cache for future requests
        set_transient(self::CACHE_KEY, $this->settings, self::CACHE_EXPIRATION);
        
        return $this->settings;
    }
    
    /**
     * Get single setting
     */
    public function get(string $key, mixed $default = null): mixed {
        $settings = $this->get_all();
        return $settings[$key] ?? $default ?? (self::get_defaults()[$key] ?? null);
    }
    
    /**
     * Update settings (programmatic use)
     */
    public function update(array $new_settings): bool {
        $sanitized = $this->sanitize($new_settings);
        $result = update_option(self::OPTION_KEY, $sanitized, false);
        
        // Cache will be cleared by update_option hook
        
        return $result;
    }
    
    /**
     * Get Terms URL
     */
    public function get_terms_url(): string {
        if (function_exists('wc_terms_and_conditions_page_id')) {
            $terms_id = (int) wc_terms_and_conditions_page_id();
            if ($terms_id > 0) {
                $permalink = get_permalink($terms_id);
                if ($permalink) {
                    return $permalink;
                }
            }
        }
        return home_url($this->get('terms_page_slug', '/terms-of-use/'));
    }
    
    /**
     * Get excluded page slugs
     */
    public function get_excluded_pages(): array {
        $pages = $this->get('excluded_pages', '');
        return array_filter(array_map('trim', explode("\n", $pages)));
    }
    
    /**
     * Get business types
     */
    public function get_business_types(): array {
        $types = $this->get('business_types', '');
        return array_filter(array_map('trim', explode("\n", $types)));
    }
    
    /**
     * Sanitize settings (called by Settings API and programmatic updates)
     */
    public function sanitize(array $input): array {
        $defaults = self::get_defaults();
        $sanitized = [];
        
        foreach ($defaults as $key => $default) {
            if (!isset($input[$key])) {
                // For checkboxes that aren't checked, they won't be in $_POST
                // So we need to handle boolean fields specially
                if (in_array($key, ['enabled', 'require_business', 'require_phone', 'mu_installed',
                    'security_headers_enabled', 'csp_report_only', 'rate_limit_enabled'], true)) {
                    $sanitized[$key] = 'no';
                } else {
                    $sanitized[$key] = $default;
                }
                continue;
            }
            
            $sanitized[$key] = match ($key) {
                // Boolean toggles
                'enabled', 'require_business', 'require_phone', 'mu_installed',
                'security_headers_enabled', 'csp_report_only', 'rate_limit_enabled'
                    => $input[$key] === 'yes' ? 'yes' : 'no',
                
                // Rate limiting integers
                'rate_limit_attempts' => max(1, min(20, (int) $input[$key])),
                'rate_limit_lockout' => in_array((int) $input[$key], [300, 900, 1800, 3600, 86400], true) 
                    ? (int) $input[$key] 
                    : 900,
                
                // Hex colors
                'primary_color', 'secondary_color', 'background_color', 
                'text_color', 'muted_color', 'error_color' 
                    => sanitize_hex_color($input[$key]) ?: $default,
                
                // URL
                'logo_url' => esc_url_raw($input[$key]),
                
                // HTML allowed (Terms content)
                'terms_content', 'excluded_pages', 'business_types' 
                    => wp_kses_post($input[$key]),
                
                // CAPTCHA provider (enum)
                'captcha_provider' => in_array($input[$key], array_keys(Captcha::PROVIDERS), true) 
                    ? $input[$key] 
                    : 'none',
                
                // CAPTCHA threshold (float 0-1)
                'captcha_threshold' => max(0, min(1, (float) $input[$key])),
                
                // Plain text
                default => sanitize_text_field($input[$key])
            };
        }
        
        return $sanitized;
    }
    
    /**
     * Default settings
     */
    public static function get_defaults(): array {
        return [
            // General
            'enabled'             => 'yes',
            'primary_color'       => '#8a2be2',
            'secondary_color'     => '#6a1fb0',
            'background_color'    => '#0d0d0d',
            'text_color'          => '#ffffff',
            'muted_color'         => '#777777',
            'error_color'         => '#e74c3c',
            'logo_url'            => '',
            'company_name'        => 'Petratide Science',
            'modal_title'         => 'Research Access Only',
            'modal_description'   => 'You must be registered to view our catalog. Please log in or create an accredited account to continue.',
            'terms_title'         => 'Terms of Use',
            'terms_content'       => self::get_default_terms(),
            'checkbox_text'       => 'Yes, I Acknowledge & Confirm – I confirm that I am accessing these materials solely for in-vitro research use, that I have read and agree to the Terms of Service, and that I am authorized to handle and use them.',
            'excluded_pages'      => "my-account\naccount\nterms-of-use\nprivacy-policy\ncontact",
            'terms_page_slug'     => '/terms-of-use/',
            'contact_page_slug'   => '/contact/',
            'business_types'      => "Researcher\nDistributor\nClinic",
            'require_business'    => 'yes',
            'require_phone'       => 'yes',
            'mu_installed'        => 'no',
            
            // Security - CAPTCHA
            'captcha_provider'    => 'none',
            'captcha_site_key'    => '',
            'captcha_secret_key'  => '',
            'captcha_threshold'   => 0.5,  // For reCAPTCHA v3 (0-1 score)
            
            // Security - Headers
            'security_headers_enabled' => 'no',
            'csp_report_only'     => 'yes',  // Start in report-only mode
            
            // Security - Rate Limiting
            'rate_limit_enabled'  => 'yes',
            'rate_limit_attempts' => 5,
            'rate_limit_lockout'  => 900,  // 15 minutes
        ];
    }
    
    /**
     * Default Terms content
     */
    private static function get_default_terms(): string {
        return 'All products sold by <strong>{{company_name}}</strong> are intended strictly for in-vitro laboratory research use only. They are not for human or animal use, consumption, or application, and are not approved by the FDA for any therapeutic, diagnostic, or medicinal purposes.

By creating an account and purchasing from {{company_name}}, you confirm that:

• You are at least 21 years of age.
• You are a qualified researcher, laboratory professional, or authorized representative of a research institution.
• You will use all products solely for legitimate in-vitro research purposes.
• You will not use, distribute, or resell any products for human or animal consumption.
• You understand that {{company_name}} assumes no liability for misuse of products.

{{company_name}} reserves the right to refuse or cancel any order that does not comply with these terms.';
    }
}
