<?php
/**
 * MU-Plugin Manager
 * 
 * Handles installation/removal of Must-Use plugin
 * for cache bypass protection
 * 
 * @package PremierBioLabs\ResearchAccessGate
 * @since 2.0.0
 * @updated 2.0.1 - Added path validation for security
 */

declare(strict_types=1);

namespace PremierBioLabs\ResearchAccessGate;

defined('ABSPATH') || exit;

class MuPlugin {
    
    private const LOADER_FILE = 'research-access-gate-loader.php';
    
    /**
     * Check if MU-Plugin is installed
     */
    public function is_installed(): bool {
        return file_exists($this->get_loader_path());
    }
    
    /**
     * Get MU-Plugin loader path
     */
    private function get_loader_path(): string {
        return WPMU_PLUGIN_DIR . '/' . self::LOADER_FILE;
    }
    
    /**
     * Install MU-Plugin
     */
    public function install(): true|\WP_Error {
        $mu_dir = WPMU_PLUGIN_DIR;
        
        // Create mu-plugins directory if needed
        if (!file_exists($mu_dir)) {
            if (!wp_mkdir_p($mu_dir)) {
                return new \WP_Error(
                    'mkdir_failed',
                    __('Could not create mu-plugins directory. Check file permissions.', 'research-access-gate')
                );
            }
        }
        
        // Write loader file
        $content = $this->get_loader_content();
        
        if (file_put_contents($this->get_loader_path(), $content) === false) {
            return new \WP_Error(
                'write_failed',
                __('Could not write MU-Plugin file. Check file permissions.', 'research-access-gate')
            );
        }
        
        // Update settings
        $settings = new Settings();
        $all = $settings->get_all();
        $all['mu_installed'] = 'yes';
        $settings->update($all);
        
        return true;
    }
    
    /**
     * Remove MU-Plugin
     */
    public function remove(): true|\WP_Error {
        $loader_path = $this->get_loader_path();
        
        if (file_exists($loader_path)) {
            if (!unlink($loader_path)) {
                return new \WP_Error(
                    'delete_failed',
                    __('Could not remove MU-Plugin file. Check file permissions.', 'research-access-gate')
                );
            }
        }
        
        // Update settings
        $settings = new Settings();
        $all = $settings->get_all();
        $all['mu_installed'] = 'no';
        $settings->update($all);
        
        return true;
    }
    
    /**
     * Validate that a path is within the WordPress installation
     * 
     * @param string $path Path to validate
     * @return bool True if path is safe
     */
    private function is_valid_plugin_path(string $path): bool {
        // Resolve to real path
        $real_path = realpath($path);
        if ($real_path === false) {
            // File doesn't exist yet, check parent directory
            $real_path = realpath(dirname($path));
            if ($real_path === false) {
                return false;
            }
            $real_path .= '/' . basename($path);
        }
        
        // Must be within WP_PLUGIN_DIR
        $plugin_dir = realpath(WP_PLUGIN_DIR);
        if ($plugin_dir === false) {
            return false;
        }
        
        return str_starts_with($real_path, $plugin_dir);
    }
    
    /**
     * Get MU-Plugin loader content
     */
    private function get_loader_content(): string {
        $plugin_file = RAG_PLUGIN_FILE;
        
        // Validate the plugin file path is within expected directory
        if (!$this->is_valid_plugin_path($plugin_file)) {
            // Fallback to a safe default path
            $plugin_file = WP_PLUGIN_DIR . '/research-access-gate/research-access-gate.php';
        }
        
        // Escape the path for safe inclusion in PHP code
        $plugin_file = addslashes($plugin_file);
        
        return <<<PHP
<?php
/**
 * Plugin Name: Research Access Gate (MU Loader)
 * Description: Ensures Research Access Gate loads before caching plugins for bypass protection.
 * Version: 2.0.1
 * Author: Premier Bio Labs
 */

defined('ABSPATH') || exit;

// Load the main plugin early (before page caching kicks in)
\$plugin_file = '{$plugin_file}';

// Validate path before including
if (file_exists(\$plugin_file) && strpos(realpath(\$plugin_file), realpath(WP_PLUGIN_DIR)) === 0) {
    require_once \$plugin_file;
}
PHP;
    }
}
