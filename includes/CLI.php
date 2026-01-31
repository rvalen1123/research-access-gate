<?php
/**
 * WP-CLI Commands for Research Access Gate
 * 
 * Generate and manage license keys from command line.
 * 
 * Usage:
 *   wp rag license generate --tier=pro
 *   wp rag license generate --tier=unlimited --count=10
 *   wp rag license validate RAG-XXXX-XXXX-XXXX-XXXX
 *   wp rag license status
 */

declare(strict_types=1);

namespace PremierBioLabs\ResearchAccessGate;

defined('ABSPATH') || exit;

if (!class_exists('WP_CLI')) {
    return;
}

class CLI {
    
    /**
     * Generate license keys
     * 
     * ## OPTIONS
     * 
     * [--tier=<tier>]
     * : License tier (single, pro, unlimited, developer)
     * ---
     * default: pro
     * options:
     *   - single
     *   - pro
     *   - unlimited
     *   - developer
     * ---
     * 
     * [--count=<count>]
     * : Number of keys to generate
     * ---
     * default: 1
     * ---
     * 
     * [--format=<format>]
     * : Output format
     * ---
     * default: table
     * options:
     *   - table
     *   - csv
     *   - json
     * ---
     * 
     * ## EXAMPLES
     * 
     *     # Generate a single pro license
     *     $ wp rag license generate
     * 
     *     # Generate 10 unlimited licenses
     *     $ wp rag license generate --tier=unlimited --count=10
     * 
     *     # Generate developer licenses as CSV
     *     $ wp rag license generate --tier=developer --count=5 --format=csv
     * 
     * @param array $args
     * @param array $assoc_args
     */
    public function generate($args, $assoc_args): void {
        $tier = $assoc_args['tier'] ?? 'pro';
        $count = (int) ($assoc_args['count'] ?? 1);
        $format = $assoc_args['format'] ?? 'table';
        
        if ($count < 1 || $count > 100) {
            \WP_CLI::error('Count must be between 1 and 100.');
            return;
        }
        
        $keys = [];
        for ($i = 0; $i < $count; $i++) {
            $key = License::generate_key($tier);
            $keys[] = [
                'key'  => $key,
                'tier' => $tier,
            ];
        }
        
        if ($format === 'json') {
            \WP_CLI::log(json_encode($keys, JSON_PRETTY_PRINT));
        } elseif ($format === 'csv') {
            \WP_CLI::log('key,tier');
            foreach ($keys as $item) {
                \WP_CLI::log($item['key'] . ',' . $item['tier']);
            }
        } else {
            \WP_CLI\Utils\format_items('table', $keys, ['key', 'tier']);
        }
        
        \WP_CLI::success(sprintf('Generated %d %s license key(s).', $count, $tier));
    }
    
    /**
     * Validate a license key
     * 
     * ## OPTIONS
     * 
     * <key>
     * : The license key to validate
     * 
     * ## EXAMPLES
     * 
     *     $ wp rag license validate RAG-PRO0-ABCD-EFGH-IJKL
     * 
     * @param array $args
     */
    public function validate($args): void {
        if (empty($args[0])) {
            \WP_CLI::error('Please provide a license key.');
            return;
        }
        
        $key = strtoupper(trim($args[0]));
        $result = License::validate_key($key);
        
        if ($result['valid']) {
            \WP_CLI::success(sprintf(
                'Valid %s license key. Features: %s',
                $result['tier'],
                implode(', ', $result['features'])
            ));
        } else {
            \WP_CLI::error($result['message']);
        }
    }
    
    /**
     * Show current license status
     * 
     * ## EXAMPLES
     * 
     *     $ wp rag license status
     */
    public function status(): void {
        $license = License::get_license();
        $status = License::get_status();
        
        $data = [
            ['Property', 'Value'],
            ['Status', $status['status']],
            ['Valid', $status['valid'] ? 'Yes' : 'No'],
            ['Message', $status['message']],
            ['Key', !empty($license['key']) ? substr($license['key'], 0, 8) . '••••••••' : 'None'],
            ['Domain', $license['domain'] ?? 'Not set'],
            ['Activated', $license['activated_at'] ?? 'Never'],
            ['Dev Environment', License::is_dev_environment() ? 'Yes' : 'No'],
        ];
        
        foreach ($data as $row) {
            if (isset($row[1])) {
                \WP_CLI::log(sprintf('%-20s %s', $row[0] . ':', $row[1]));
            }
        }
    }
    
    /**
     * Activate a license
     * 
     * ## OPTIONS
     * 
     * <key>
     * : The license key to activate
     * 
     * [--email=<email>]
     * : Email address for the license
     * 
     * ## EXAMPLES
     * 
     *     $ wp rag license activate RAG-PRO0-ABCD-EFGH-IJKL --email=you@example.com
     * 
     * @param array $args
     * @param array $assoc_args
     */
    public function activate($args, $assoc_args): void {
        if (empty($args[0])) {
            \WP_CLI::error('Please provide a license key.');
            return;
        }
        
        $key = strtoupper(trim($args[0]));
        $email = $assoc_args['email'] ?? '';
        
        $result = License::activate($key, $email);
        
        if ($result['success']) {
            \WP_CLI::success($result['message']);
        } else {
            \WP_CLI::error($result['message']);
        }
    }
    
    /**
     * Deactivate the current license
     * 
     * ## EXAMPLES
     * 
     *     $ wp rag license deactivate
     */
    public function deactivate(): void {
        $result = License::deactivate();
        \WP_CLI::success($result['message']);
    }
}

// Register commands
\WP_CLI::add_command('rag license', __NAMESPACE__ . '\\CLI');
