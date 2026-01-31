<?php
/**
 * Admin Settings Page
 */

declare(strict_types=1);

namespace PremierBioLabs\ResearchAccessGate;

defined('ABSPATH') || exit;

class Admin {
    
    public function __construct(
        private readonly Settings $settings,
        private readonly MuPlugin $mu_plugin
    ) {
        add_action('admin_menu', [$this, 'add_menu']);
        add_action('admin_init', [$this, 'register_settings']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_assets']);
    }
    
    /**
     * Add admin menu
     */
    public function add_menu(): void {
        add_options_page(
            __('Research Access Gate', 'research-access-gate'),
            __('Access Gate', 'research-access-gate'),
            'manage_options',
            'research-access-gate',
            [$this, 'render_page']
        );
    }
    
    /**
     * Register settings
     */
    public function register_settings(): void {
        register_setting('rag_settings_group', 'rag_settings', [
            'sanitize_callback' => [$this->settings, 'sanitize'],
            'default'           => Settings::get_defaults(),
        ]);
    }
    
    /**
     * Enqueue admin assets (conditional loading)
     */
    public function enqueue_assets(string $hook): void {
        if ($hook !== 'settings_page_research-access-gate') {
            return;
        }
        
        wp_enqueue_media();
        wp_enqueue_style('wp-color-picker');
        wp_enqueue_script('wp-color-picker');
        
        wp_enqueue_style(
            'rag-admin',
            RAG_PLUGIN_URL . 'assets/admin.css',
            [],
            VERSION
        );
        
        wp_enqueue_script(
            'rag-admin',
            RAG_PLUGIN_URL . 'assets/admin.js',
            ['jquery', 'wp-color-picker', 'wp-api-fetch'],
            VERSION,
            true
        );
        
        // Localize REST API info
        wp_localize_script('rag-admin', 'ragAdmin', [
            'restUrl'     => rest_url('rag/v1'),
            'nonce'       => wp_create_nonce('wp_rest'),
            'muInstalled' => $this->mu_plugin->is_installed(),
            'strings'     => [
                'installMu'     => __('Install as MU-Plugin', 'research-access-gate'),
                'removeMu'      => __('Remove MU-Plugin', 'research-access-gate'),
                'installing'    => __('Installing...', 'research-access-gate'),
                'removing'      => __('Removing...', 'research-access-gate'),
                'success'       => __('Success!', 'research-access-gate'),
                'error'         => __('Error occurred', 'research-access-gate'),
                'confirmRemove' => __('Are you sure? The gate will still work but could be bypassed by caching.', 'research-access-gate'),
            ],
        ]);
    }
    
    /**
     * Render settings page
     */
    public function render_page(): void {
        if (!current_user_can('manage_options')) {
            return;
        }
        
        $options = $this->settings->get_all();
        $mu_installed = $this->mu_plugin->is_installed();
        
        include RAG_PLUGIN_DIR . 'templates/admin-page.php';
    }
}
