<?php
/**
 * Main Plugin Class
 * 
 * Uses modern PHP 8.0+ features:
 * - Typed properties
 * - Constructor property promotion
 * - Named arguments
 * - Match expressions
 * - Null-safe operator
 * 
 * @package PremierBioLabs\ResearchAccessGate
 * @since 2.0.0
 * @updated 2.0.2 - Added SecurityHeaders and Captcha components
 */

declare(strict_types=1);

namespace PremierBioLabs\ResearchAccessGate;

defined('ABSPATH') || exit;

final class Plugin {
    
    private static ?Plugin $instance = null;
    
    private Settings $settings;
    private RestApi $rest_api;
    private Frontend $frontend;
    private Admin $admin;
    private MuPlugin $mu_plugin;
    private SecurityHeaders $security_headers;
    private Captcha $captcha;
    
    /**
     * Singleton instance
     */
    public static function instance(): self {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Private constructor - use instance()
     */
    private function __construct() {
        $this->init_components();
        $this->register_hooks();
    }
    
    /**
     * Initialize plugin components
     */
    private function init_components(): void {
        $this->settings = new Settings();
        $this->rest_api = new RestApi($this->settings);
        $this->frontend = new Frontend($this->settings);
        $this->mu_plugin = new MuPlugin();
        $this->security_headers = new SecurityHeaders($this->settings);
        $this->captcha = new Captcha($this->settings);
        
        if (is_admin()) {
            $this->admin = new Admin($this->settings, $this->mu_plugin);
        }
    }
    
    /**
     * Register WordPress hooks
     */
    private function register_hooks(): void {
        // REST API routes (modern approach - replaces admin-ajax)
        add_action('rest_api_init', [$this->rest_api, 'register_routes']);
        
        // Security headers
        $this->security_headers->init();
        
        // Frontend hooks (only if licensed or in grace period)
        if ($this->should_enable_gate()) {
            add_action('wp_footer', [$this->frontend, 'render_modal'], 9999);
            add_action('wp_head', [$this->frontend, 'render_styles'], 9999);
        }
        
        // WooCommerce checkout Terms
        add_filter('woocommerce_get_terms_and_conditions_checkbox_text', [$this, 'checkout_terms_text'], 20);
        add_action('woocommerce_checkout_process', [$this, 'validate_checkout_terms'], 20);
        add_action('woocommerce_checkout_create_order', [$this, 'store_order_terms'], 20, 2);
        
        // Plugin action links
        add_filter('plugin_action_links_' . RAG_PLUGIN_BASENAME, [$this, 'plugin_action_links']);
        
        // Conditional asset loading (2025 best practice)
        add_action('wp_enqueue_scripts', [$this->frontend, 'maybe_enqueue_assets']);
        
        // Admin license notices
        add_action('admin_notices', [$this, 'admin_license_notice']);
    }
    
    /**
     * Check if gate should be enabled (license check)
     */
    private function should_enable_gate(): bool {
        // Always enable in dev environments
        if (License::is_dev_environment()) {
            return true;
        }
        
        // Check license
        if (License::is_valid()) {
            return true;
        }
        
        // Grace period: Allow 7 days without license for new installs
        $installed_at = get_option('rag_installed_at');
        if (!$installed_at) {
            update_option('rag_installed_at', time(), false);
            return true;
        }
        
        $grace_period = 7 * DAY_IN_SECONDS;
        if ((time() - $installed_at) < $grace_period) {
            return true;
        }
        
        // Grace period expired and no license
        return false;
    }
    
    /**
     * Admin notice for license status
     */
    public function admin_license_notice(): void {
        // Only show on plugin pages
        $screen = get_current_screen();
        if (!$screen || !current_user_can('manage_options')) {
            return;
        }
        
        // Skip if licensed or dev
        if (License::is_valid() || License::is_dev_environment()) {
            return;
        }
        
        // Check grace period
        $installed_at = get_option('rag_installed_at', time());
        $grace_period = 7 * DAY_IN_SECONDS;
        $time_remaining = ($installed_at + $grace_period) - time();
        
        $settings_url = admin_url('options-general.php?page=research-access-gate');
        
        if ($time_remaining > 0) {
            // Grace period active
            $days_left = ceil($time_remaining / DAY_IN_SECONDS);
            $message = sprintf(
                __('<strong>Research Access Gate:</strong> %d day(s) remaining in trial. <a href="%s">Activate your license</a> to continue using after the trial period.', 'research-access-gate'),
                $days_left,
                esc_url($settings_url)
            );
            $class = 'notice-warning';
        } else {
            // Grace period expired
            $message = sprintf(
                __('<strong>Research Access Gate:</strong> Trial period expired. The access gate is currently disabled. <a href="%s">Activate your license</a> to re-enable.', 'research-access-gate'),
                esc_url($settings_url)
            );
            $class = 'notice-error';
        }
        
        printf(
            '<div class="notice %s is-dismissible"><p>%s</p></div>',
            esc_attr($class),
            wp_kses_post($message)
        );
    }
    
    /**
     * Get settings instance
     */
    public function settings(): Settings {
        return $this->settings;
    }
    
    /**
     * Get CAPTCHA instance
     */
    public function captcha(): Captcha {
        return $this->captcha;
    }
    
    /**
     * Get SecurityHeaders instance
     */
    public function security_headers(): SecurityHeaders {
        return $this->security_headers;
    }
    
    /**
     * WooCommerce checkout Terms text
     */
    public function checkout_terms_text(string $text): string {
        $checkbox_text = $this->settings->get('checkbox_text');
        $terms_url = esc_url($this->settings->get_terms_url());
        
        return str_replace(
            'Terms of Service',
            '<a href="' . $terms_url . '" target="_blank" rel="noopener">' . 
                esc_html__('Terms of Service', 'research-access-gate') . '</a>',
            esc_html($checkbox_text)
        );
    }
    
    /**
     * Validate checkout Terms
     */
    public function validate_checkout_terms(): void {
        if (!function_exists('wc_terms_and_conditions_checkbox_enabled')) {
            return;
        }
        
        if (wc_terms_and_conditions_checkbox_enabled() && empty($_POST['terms'])) {
            wc_add_notice(
                __('You must acknowledge and confirm the Terms of Use to place an order.', 'research-access-gate'),
                'error'
            );
        }
    }
    
    /**
     * Store Terms acceptance on order
     */
    public function store_order_terms(\WC_Order $order, array $data): void {
        if (!function_exists('wc_terms_and_conditions_checkbox_enabled')) {
            return;
        }
        
        if (wc_terms_and_conditions_checkbox_enabled() && !empty($_POST['terms'])) {
            $order->update_meta_data('rag_terms_accepted', 'yes');
            $order->update_meta_data('rag_terms_accepted_at', current_time('mysql'));
            $order->update_meta_data('rag_terms_accepted_ip', Helpers::get_client_ip());
            $order->update_meta_data('rag_terms_version', VERSION);
        }
    }
    
    /**
     * Plugin action links
     */
    public function plugin_action_links(array $links): array {
        $settings_link = sprintf(
            '<a href="%s">%s</a>',
            admin_url('options-general.php?page=research-access-gate'),
            esc_html__('Settings', 'research-access-gate')
        );
        array_unshift($links, $settings_link);
        return $links;
    }
    
    /**
     * Prevent cloning
     */
    private function __clone() {}
    
    /**
     * Prevent unserialization
     */
    public function __wakeup(): void {
        throw new \Exception('Cannot unserialize singleton');
    }
}
