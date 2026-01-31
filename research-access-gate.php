<?php
/**
 * Plugin Name: Research Access Gate
 * Plugin URI: https://premierbioresearch.com
 * Description: Site-wide login/registration modal with 21+ Terms acceptance for research chemical e-commerce. REST API powered, cache-compatible, MU-plugin option.
 * Version: 2.0.5
 * Author: Premier Bio Labs
 * Author URI: https://premierbioresearch.com
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: research-access-gate
 * Domain Path: /languages
 * Requires at least: 6.4
 * Requires PHP: 8.0
 * WC requires at least: 8.0
 * WC tested up to: 9.5
 */

declare(strict_types=1);

namespace PremierBioLabs\ResearchAccessGate;

// Prevent direct access
defined('ABSPATH') || exit;

// Plugin constants
const VERSION = '2.0.5';
const MIN_PHP = '8.0.0';
const MIN_WP = '6.4';

define('RAG_PLUGIN_FILE', __FILE__);
define('RAG_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('RAG_PLUGIN_URL', plugin_dir_url(__FILE__));
define('RAG_PLUGIN_BASENAME', plugin_basename(__FILE__));

/**
 * PHP version check
 * 
 * WordPress 6.7+ requires translations to load at 'init' or later.
 * We defer the admin notice to avoid _load_textdomain_just_in_time warning.
 */
if (version_compare(PHP_VERSION, MIN_PHP, '<')) {
    add_action('admin_notices', function(): void {
        // Translation is safe here - admin_notices fires after init
        printf(
            '<div class="notice notice-error"><p>%s</p></div>',
            sprintf(
                /* translators: 1: Required PHP version, 2: Current PHP version */
                esc_html__('Research Access Gate requires PHP %1$s or higher. You are running PHP %2$s.', 'research-access-gate'),
                MIN_PHP,
                PHP_VERSION
            )
        );
    });
    return;
}

/**
 * Autoloader
 */
spl_autoload_register(function (string $class): void {
    $prefix = 'PremierBioLabs\\ResearchAccessGate\\';
    $base_dir = RAG_PLUGIN_DIR . 'includes/';
    
    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) {
        return;
    }
    
    $relative_class = substr($class, $len);
    $file = $base_dir . str_replace('\\', '/', $relative_class) . '.php';
    
    if (file_exists($file)) {
        require $file;
    }
});

/**
 * WooCommerce HPOS Compatibility Declaration
 * Required for WooCommerce 8.0+ (2024-2026 standard)
 */
add_action('before_woocommerce_init', function(): void {
    if (class_exists(\Automattic\WooCommerce\Utilities\FeaturesUtil::class)) {
        \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility(
            'custom_order_tables',
            RAG_PLUGIN_FILE,
            true
        );
        // Also declare cart/checkout blocks compatibility
        \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility(
            'cart_checkout_blocks',
            RAG_PLUGIN_FILE,
            true
        );
    }
});

/**
 * Initialize Plugin
 * 
 * WordPress 6.7+ best practice: Load text domain at 'init' action.
 * We use 'init' priority 0 to load translations before other init hooks.
 */
add_action('init', function(): void {
    load_plugin_textdomain(
        'research-access-gate',
        false,
        dirname(RAG_PLUGIN_BASENAME) . '/languages'
    );
}, 0);

/**
 * Initialize plugin components after plugins are loaded
 */
add_action('plugins_loaded', function(): void {
    Plugin::instance();
}, 10);

/**
 * Activation hook
 */
register_activation_hook(__FILE__, function(): void {
    if (!get_option('rag_settings')) {
        update_option('rag_settings', Settings::get_defaults(), false);
    }
    
    // Set version for future upgrades
    update_option('rag_version', VERSION, false);
    
    // Clear any cached settings
    delete_transient('rag_settings_cache');
    
    // Flush rewrite rules for REST API
    flush_rewrite_rules();
});

/**
 * Deactivation hook
 */
register_deactivation_hook(__FILE__, function(): void {
    // Remove MU-Plugin on deactivation
    $mu_file = WPMU_PLUGIN_DIR . '/research-access-gate-loader.php';
    if (file_exists($mu_file)) {
        @unlink($mu_file);
    }
    
    delete_transient('rag_settings_cache');
    flush_rewrite_rules();
});

/**
 * Global accessor function
 */
function rag(): Plugin {
    return Plugin::instance();
}
