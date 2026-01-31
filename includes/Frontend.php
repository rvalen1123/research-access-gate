<?php
/**
 * Frontend Handler
 * 
 * 2025 Best Practice: Only load assets when needed
 * 
 * @package PremierBioLabs\ResearchAccessGate
 * @since 2.0.0
 * @updated 2.0.1 - Security fixes for input sanitization and output escaping
 * @updated 2.0.2 - Added CAPTCHA integration support
 */

declare(strict_types=1);

namespace PremierBioLabs\ResearchAccessGate;

defined('ABSPATH') || exit;

class Frontend {
    
    public function __construct(
        private readonly Settings $settings
    ) {}
    
    /**
     * Check if gate should be displayed
     */
    public function should_show_gate(): bool {
        // Check if enabled
        if ($this->settings->get('enabled') !== 'yes') {
            return false;
        }
        
        // Skip for logged-in users
        if (is_user_logged_in()) {
            return false;
        }
        
        // Skip for AJAX/REST/Cron
        if (wp_doing_ajax() || wp_doing_cron()) {
            return false;
        }
        
        // Skip REST API requests
        if (defined('REST_REQUEST') && REST_REQUEST) {
            return false;
        }
        
        // Skip admin
        if (is_admin()) {
            return false;
        }
        
        // Skip WooCommerce my-account page
        if (function_exists('is_account_page') && is_account_page()) {
            return false;
        }
        
        // Skip by WooCommerce page ID
        if (function_exists('wc_get_page_id')) {
            $myaccount_id = wc_get_page_id('myaccount');
            if ($myaccount_id > 0 && is_page($myaccount_id)) {
                return false;
            }
        }
        
        // Skip excluded pages
        foreach ($this->settings->get_excluded_pages() as $slug) {
            if (is_page($slug)) {
                return false;
            }
        }
        
        // Skip wp-login.php
        global $pagenow;
        if ($pagenow === 'wp-login.php') {
            return false;
        }
        
        return true;
    }
    
    /**
     * Conditional asset loading (2025 best practice)
     * Only enqueue when needed
     */
    public function maybe_enqueue_assets(): void {
        if (!$this->should_show_gate()) {
            return;
        }
        
        // No external CSS/JS files needed - all inline for performance
        // This prevents additional HTTP requests
    }
    
    /**
     * Render dynamic styles
     */
    public function render_styles(): void {
        if (!$this->should_show_gate()) {
            return;
        }
        
        $options = $this->settings->get_all();
        include RAG_PLUGIN_DIR . 'templates/styles.php';
    }
    
    /**
     * Render modal
     */
    public function render_modal(): void {
        if (!$this->should_show_gate()) {
            return;
        }
        
        $options = $this->settings->get_all();
        
        // Template variables
        $terms_url      = esc_url($this->settings->get_terms_url());
        $contact_url    = esc_url(home_url($options['contact_page_slug']));
        $nonce          = wp_create_nonce('rag_auth');
        
        // Sanitize REQUEST_URI to prevent XSS
        $request_uri    = isset($_SERVER['REQUEST_URI']) 
            ? sanitize_text_field(wp_unslash($_SERVER['REQUEST_URI'])) 
            : '/';
        // Remove any potentially dangerous characters
        $redirect       = esc_attr(preg_replace('/[<>"\'\\\\]/', '', $request_uri));
        
        $rest_url       = esc_url(rest_url('rag/v1'));
        $business_types = $this->settings->get_business_types();
        
        // Parse Terms content - escape company name and apply wp_kses_post AFTER all transformations
        $terms_content = str_replace(
            '{{company_name}}',
            esc_html($options['company_name']),
            $options['terms_content']
        );
        // Apply nl2br first, then sanitize the entire output
        $terms_content = wp_kses_post(nl2br($terms_content));
        
        // CAPTCHA configuration
        $captcha = rag()->captcha();
        $captcha_enabled = $captcha->is_enabled();
        $captcha_provider = $captcha->get_provider();
        $captcha_site_key = $captcha->get_site_key();
        $captcha_script_url = $captcha->get_script_url();
        $captcha_response_field = $captcha->get_response_field();
        
        include RAG_PLUGIN_DIR . 'templates/modal.php';
    }
}
