<?php
/**
 * REST API Handler
 * 
 * 2025-2026 Best Practice: Use REST API instead of admin-ajax.php
 * - 15-20% faster response times
 * - Better caching support
 * - Modern HTTP standards
 * - Built-in permission callbacks
 * - Automatic JSON encoding
 * 
 * @package PremierBioLabs\ResearchAccessGate
 * @since 2.0.0
 * @updated 2.0.1 - Security fixes for open redirect and input validation
 * @updated 2.0.2 - Added rate limiting for login/register endpoints
 * @updated 2.0.2 - Added CAPTCHA verification for login/register endpoints
 */

declare(strict_types=1);

namespace PremierBioLabs\ResearchAccessGate;

defined('ABSPATH') || exit;

class RestApi {
    
    private const NAMESPACE = 'rag/v1';
    
    public function __construct(
        private readonly Settings $settings
    ) {}
    
    /**
     * Register REST API routes
     */
    public function register_routes(): void {
        // Login endpoint
        register_rest_route(self::NAMESPACE, '/login', [
            'methods'             => \WP_REST_Server::CREATABLE,
            'callback'            => [$this, 'handle_login'],
            'permission_callback' => [$this, 'permission_public'],
            'args'                => $this->get_login_args(),
        ]);
        
        // Register endpoint
        register_rest_route(self::NAMESPACE, '/register', [
            'methods'             => \WP_REST_Server::CREATABLE,
            'callback'            => [$this, 'handle_register'],
            'permission_callback' => [$this, 'permission_public'],
            'args'                => $this->get_register_args(),
        ]);
        
        // Install MU-Plugin (admin only)
        register_rest_route(self::NAMESPACE, '/mu-plugin/install', [
            'methods'             => \WP_REST_Server::CREATABLE,
            'callback'            => [$this, 'install_mu_plugin'],
            'permission_callback' => [$this, 'permission_admin'],
        ]);
        
        // Remove MU-Plugin (admin only)
        register_rest_route(self::NAMESPACE, '/mu-plugin/remove', [
            'methods'             => \WP_REST_Server::DELETABLE,
            'callback'            => [$this, 'remove_mu_plugin'],
            'permission_callback' => [$this, 'permission_admin'],
        ]);
        
        // License activation (admin only)
        register_rest_route(self::NAMESPACE, '/license/activate', [
            'methods'             => \WP_REST_Server::CREATABLE,
            'callback'            => [$this, 'activate_license'],
            'permission_callback' => [$this, 'permission_admin'],
            'args'                => [
                'license_key' => [
                    'required'          => true,
                    'type'              => 'string',
                    'sanitize_callback' => 'sanitize_text_field',
                ],
                'email' => [
                    'required'          => false,
                    'type'              => 'string',
                    'sanitize_callback' => 'sanitize_email',
                ],
            ],
        ]);
        
        // License deactivation (admin only)
        register_rest_route(self::NAMESPACE, '/license/deactivate', [
            'methods'             => \WP_REST_Server::DELETABLE,
            'callback'            => [$this, 'deactivate_license'],
            'permission_callback' => [$this, 'permission_admin'],
        ]);
        
        // License status (admin only)
        register_rest_route(self::NAMESPACE, '/license/status', [
            'methods'             => \WP_REST_Server::READABLE,
            'callback'            => [$this, 'get_license_status'],
            'permission_callback' => [$this, 'permission_admin'],
        ]);
    }
    
    /**
     * Public endpoints - allow non-logged-in users
     */
    public function permission_public(): bool {
        return true;
    }
    
    /**
     * Admin-only endpoints
     */
    public function permission_admin(): bool {
        return current_user_can('manage_options');
    }
    
    /**
     * Validate and sanitize redirect URL to prevent open redirect attacks
     * 
     * @param string $redirect User-provided redirect path
     * @return string Safe redirect URL
     */
    private function sanitize_redirect(string $redirect): string {
        // Sanitize the input
        $redirect = sanitize_text_field(wp_unslash($redirect));
        
        // Remove any protocol or domain to ensure relative path
        $redirect = preg_replace('#^https?://[^/]+#i', '', $redirect);
        
        // Ensure it starts with /
        if (empty($redirect) || $redirect[0] !== '/') {
            $redirect = '/' . ltrim($redirect, '/');
        }
        
        // Remove any dangerous characters
        $redirect = preg_replace('/[<>"\'\\\\]/', '', $redirect);
        
        // Validate it's a local path using WordPress function
        $full_url = home_url($redirect);
        
        // Use wp_validate_redirect to ensure it's safe
        $validated = wp_validate_redirect($full_url, home_url('/'));
        
        // If validation changed the URL, return home
        if ($validated !== $full_url) {
            return home_url('/');
        }
        
        return $full_url;
    }
    
    /**
     * Handle login request
     */
    public function handle_login(\WP_REST_Request $request): \WP_REST_Response|\WP_Error {
        $username = $request->get_param('username');
        $password = $request->get_param('password');
        $redirect = $request->get_param('redirect') ?? '/';
        
        // Check rate limit FIRST (before any processing)
        $rate_check = RateLimiter::check('login', $username);
        if (!$rate_check['allowed']) {
            return new \WP_Error(
                'rate_limited',
                $rate_check['message'],
                [
                    'status'      => 429,
                    'retry_after' => $rate_check['retry_after'] ?? 900,
                ]
            );
        }
        
        // Verify CAPTCHA if enabled
        $captcha_error = $this->verify_captcha($request, 'login');
        if ($captcha_error !== null) {
            return $captcha_error;
        }
        
        // Validate nonce (WordPress cookie auth for REST)
        if (!wp_verify_nonce($request->get_header('X-WP-Nonce'), 'wp_rest')) {
            // For public login, we use a custom nonce
            $nonce = $request->get_param('_wpnonce');
            if (!wp_verify_nonce($nonce, 'rag_auth')) {
                return new \WP_Error(
                    'invalid_nonce',
                    __('Security check failed. Please refresh and try again.', 'research-access-gate'),
                    ['status' => 403]
                );
            }
        }
        
        if (empty($username) || empty($password)) {
            return new \WP_Error(
                'missing_credentials',
                __('Please enter username and password.', 'research-access-gate'),
                ['status' => 400]
            );
        }
        
        $user = wp_signon([
            'user_login'    => $username,
            'user_password' => $password,
            'remember'      => true,
        ]);
        
        if (is_wp_error($user)) {
            // Record failed attempt for rate limiting
            $rate_status = RateLimiter::record_failure('login', $username);
            
            $error_data = ['status' => 401];
            
            // Add remaining attempts info if close to lockout
            if ($rate_status['remaining'] <= 2 && $rate_status['remaining'] > 0) {
                $error_data['remaining_attempts'] = $rate_status['remaining'];
            }
            
            return new \WP_Error(
                'login_failed',
                __('Invalid username or password.', 'research-access-gate'),
                $error_data
            );
        }
        
        // Clear rate limit on successful login
        RateLimiter::record_success('login', $username);
        
        return new \WP_REST_Response([
            'success'  => true,
            'message'  => __('Login successful!', 'research-access-gate'),
            'redirect' => $this->sanitize_redirect($redirect),
        ], 200);
    }
    
    /**
     * Handle registration request
     */
    public function handle_register(\WP_REST_Request $request): \WP_REST_Response|\WP_Error {
        // Check rate limit FIRST (before any processing)
        // Use IP-only for registration (no username yet)
        $rate_check = RateLimiter::check('register');
        if (!$rate_check['allowed']) {
            return new \WP_Error(
                'rate_limited',
                $rate_check['message'],
                [
                    'status'      => 429,
                    'retry_after' => $rate_check['retry_after'] ?? 900,
                ]
            );
        }
        
        // Verify CAPTCHA if enabled
        $captcha_error = $this->verify_captcha($request, 'register');
        if ($captcha_error !== null) {
            return $captcha_error;
        }
        
        // Verify nonce
        $nonce = $request->get_param('_wpnonce');
        if (!wp_verify_nonce($nonce, 'rag_auth')) {
            return new \WP_Error(
                'invalid_nonce',
                __('Security check failed. Please refresh and try again.', 'research-access-gate'),
                ['status' => 403]
            );
        }
        
        $email         = sanitize_email($request->get_param('email') ?? '');
        $first_name    = sanitize_text_field($request->get_param('first_name') ?? '');
        $last_name     = sanitize_text_field($request->get_param('last_name') ?? '');
        $phone         = sanitize_text_field($request->get_param('phone') ?? '');
        $business_type = sanitize_text_field($request->get_param('business_type') ?? '');
        $password      = $request->get_param('password') ?? '';
        $terms         = (bool) $request->get_param('terms_accepted');
        $redirect      = $request->get_param('redirect') ?? '/';
        
        // Validation
        $validation_error = $this->validate_registration(
            $email, $first_name, $last_name, $phone, $business_type, $password, $terms
        );
        
        if ($validation_error !== null) {
            // Record failed attempt (validation failure)
            RateLimiter::record_failure('register');
            return $validation_error;
        }
        
        // Check if email exists
        if (email_exists($email)) {
            // Record failed attempt (email exists counts as failure for rate limiting)
            RateLimiter::record_failure('register');
            return new \WP_Error(
                'email_exists',
                __('An account with this email already exists. Please log in.', 'research-access-gate'),
                ['status' => 409]
            );
        }
        
        // Generate username from email
        $username = $this->generate_username($email);
        
        // Create user
        $user_id = wp_create_user($username, $password, $email);
        
        if (is_wp_error($user_id)) {
            // Record failed attempt
            RateLimiter::record_failure('register');
            return new \WP_Error(
                'registration_failed',
                __('Registration failed. Please try again.', 'research-access-gate'),
                ['status' => 500]
            );
        }
        
        // Store user meta
        $this->store_user_meta($user_id, [
            'first_name'    => $first_name,
            'last_name'     => $last_name,
            'phone'         => $phone,
            'business_type' => $business_type,
        ]);
        
        // Auto-login
        wp_set_current_user($user_id);
        wp_set_auth_cookie($user_id, true);
        
        // Clear rate limit on successful registration
        RateLimiter::record_success('register');
        
        // WooCommerce hook
        if (function_exists('wc_create_new_customer')) {
            do_action('woocommerce_created_customer', $user_id, [], $password);
        }
        
        return new \WP_REST_Response([
            'success'  => true,
            'message'  => __('Account created!', 'research-access-gate'),
            'redirect' => $this->sanitize_redirect($redirect),
        ], 201);
    }
    
    /**
     * Validate registration data
     */
    private function validate_registration(
        string $email,
        string $first_name,
        string $last_name,
        string $phone,
        string $business_type,
        string $password,
        bool $terms
    ): ?\WP_Error {
        if (empty($email) || !is_email($email)) {
            return new \WP_Error(
                'invalid_email',
                __('Please enter a valid email address.', 'research-access-gate'),
                ['status' => 400]
            );
        }
        
        if (empty($first_name)) {
            return new \WP_Error(
                'missing_first_name',
                __('Please enter your first name.', 'research-access-gate'),
                ['status' => 400]
            );
        }
        
        if (empty($last_name)) {
            return new \WP_Error(
                'missing_last_name',
                __('Please enter your last name.', 'research-access-gate'),
                ['status' => 400]
            );
        }
        
        if ($this->settings->get('require_phone') === 'yes' && empty($phone)) {
            return new \WP_Error(
                'missing_phone',
                __('Please enter your phone number.', 'research-access-gate'),
                ['status' => 400]
            );
        }
        
        if ($this->settings->get('require_business') === 'yes' && empty($business_type)) {
            return new \WP_Error(
                'missing_business_type',
                __('Please select your business type.', 'research-access-gate'),
                ['status' => 400]
            );
        }
        
        if (strlen($password) < 8) {
            return new \WP_Error(
                'weak_password',
                __('Password must be at least 8 characters.', 'research-access-gate'),
                ['status' => 400]
            );
        }
        
        if (!$terms) {
            return new \WP_Error(
                'terms_not_accepted',
                __('You must acknowledge and confirm the Terms of Use.', 'research-access-gate'),
                ['status' => 400]
            );
        }
        
        return null;
    }
    
    /**
     * Generate unique username from email
     */
    private function generate_username(string $email): string {
        $base = strstr($email, '@', true) ?: 'user';
        $base = sanitize_user($base);
        $username = $base;
        $counter = 1;
        
        while (username_exists($username)) {
            $username = $base . $counter;
            $counter++;
        }
        
        return $username;
    }
    
    /**
     * Store user meta data
     */
    private function store_user_meta(int $user_id, array $data): void {
        // WordPress standard meta
        update_user_meta($user_id, 'first_name', $data['first_name']);
        update_user_meta($user_id, 'last_name', $data['last_name']);
        
        // WooCommerce billing fields
        update_user_meta($user_id, 'billing_first_name', $data['first_name']);
        update_user_meta($user_id, 'billing_last_name', $data['last_name']);
        update_user_meta($user_id, 'billing_phone', $data['phone']);
        
        // Plugin-specific meta
        update_user_meta($user_id, 'rag_phone', $data['phone']);
        update_user_meta($user_id, 'rag_business_type', $data['business_type']);
        
        // Terms acceptance with audit trail
        update_user_meta($user_id, 'rag_terms_accepted', 'yes');
        update_user_meta($user_id, 'rag_terms_accepted_at', current_time('mysql'));
        update_user_meta($user_id, 'rag_terms_accepted_ip', Helpers::get_client_ip());
        update_user_meta($user_id, 'rag_terms_version', VERSION);
    }
    
    /**
     * Install MU-Plugin
     */
    public function install_mu_plugin(\WP_REST_Request $request): \WP_REST_Response|\WP_Error {
        $mu_plugin = new MuPlugin();
        $result = $mu_plugin->install();
        
        if (is_wp_error($result)) {
            return $result;
        }
        
        return new \WP_REST_Response([
            'success' => true,
            'message' => __('MU-Plugin installed successfully!', 'research-access-gate'),
        ], 200);
    }
    
    /**
     * Remove MU-Plugin
     */
    public function remove_mu_plugin(\WP_REST_Request $request): \WP_REST_Response|\WP_Error {
        $mu_plugin = new MuPlugin();
        $result = $mu_plugin->remove();
        
        if (is_wp_error($result)) {
            return $result;
        }
        
        return new \WP_REST_Response([
            'success' => true,
            'message' => __('MU-Plugin removed successfully!', 'research-access-gate'),
        ], 200);
    }
    
    /**
     * Activate license
     */
    public function activate_license(\WP_REST_Request $request): \WP_REST_Response|\WP_Error {
        $license_key = $request->get_param('license_key');
        $email = $request->get_param('email') ?? '';
        
        $result = License::activate($license_key, $email);
        
        if (!$result['success']) {
            return new \WP_Error(
                'license_invalid',
                $result['message'],
                ['status' => 400]
            );
        }
        
        return new \WP_REST_Response([
            'success' => true,
            'message' => $result['message'],
            'tier'    => $result['tier'] ?? 'pro',
            'status'  => License::get_status(),
        ], 200);
    }
    
    /**
     * Deactivate license
     */
    public function deactivate_license(\WP_REST_Request $request): \WP_REST_Response|\WP_Error {
        $result = License::deactivate();
        
        return new \WP_REST_Response([
            'success' => true,
            'message' => $result['message'],
        ], 200);
    }
    
    /**
     * Get license status
     */
    public function get_license_status(\WP_REST_Request $request): \WP_REST_Response {
        return new \WP_REST_Response([
            'success' => true,
            'license' => License::get_license(),
            'status'  => License::get_status(),
            'is_dev'  => License::is_dev_environment(),
        ], 200);
    }
    
    /**
     * Login endpoint arguments (validation/sanitization)
     */
    private function get_login_args(): array {
        return [
            'username' => [
                'required'          => true,
                'type'              => 'string',
                'sanitize_callback' => 'sanitize_user',
            ],
            'password' => [
                'required' => true,
                'type'     => 'string',
            ],
            'redirect' => [
                'required'          => false,
                'type'              => 'string',
                'default'           => '/',
                'sanitize_callback' => 'sanitize_text_field',
            ],
            '_wpnonce' => [
                'required' => true,
                'type'     => 'string',
            ],
        ];
    }
    
    /**
     * Register endpoint arguments
     */
    private function get_register_args(): array {
        return [
            'email' => [
                'required'          => true,
                'type'              => 'string',
                'format'            => 'email',
                'sanitize_callback' => 'sanitize_email',
            ],
            'password' => [
                'required'  => true,
                'type'      => 'string',
                'minLength' => 8,
            ],
            'first_name' => [
                'required'          => true,
                'type'              => 'string',
                'sanitize_callback' => 'sanitize_text_field',
            ],
            'last_name' => [
                'required'          => true,
                'type'              => 'string',
                'sanitize_callback' => 'sanitize_text_field',
            ],
            'phone' => [
                'required'          => false,
                'type'              => 'string',
                'sanitize_callback' => 'sanitize_text_field',
            ],
            'business_type' => [
                'required'          => false,
                'type'              => 'string',
                'sanitize_callback' => 'sanitize_text_field',
            ],
            'terms_accepted' => [
                'required' => true,
                'type'     => 'boolean',
            ],
            'redirect' => [
                'required'          => false,
                'type'              => 'string',
                'default'           => '/',
                'sanitize_callback' => 'sanitize_text_field',
            ],
            '_wpnonce' => [
                'required' => true,
                'type'     => 'string',
            ],
        ];
    }
    
    /**
     * Verify CAPTCHA response
     * 
     * @param \WP_REST_Request $request The request object
     * @param string $action The action being performed (login/register)
     * @return \WP_Error|null Error if CAPTCHA fails, null if passes
     */
    private function verify_captcha(\WP_REST_Request $request, string $action): ?\WP_Error {
        $captcha = rag()->captcha();
        
        // Skip if CAPTCHA not enabled
        if (!$captcha->is_enabled()) {
            return null;
        }
        
        // Get CAPTCHA response from request
        $response_field = $captcha->get_response_field();
        $captcha_response = $request->get_param($response_field) ?? '';
        
        // Also check common field names as fallback
        if (empty($captcha_response)) {
            $captcha_response = $request->get_param('g-recaptcha-response') 
                ?? $request->get_param('h-captcha-response')
                ?? $request->get_param('cf-turnstile-response')
                ?? '';
        }
        
        // Get action from request (for reCAPTCHA v3)
        $captcha_action = $request->get_param('captcha_action') ?? $action;
        
        // Verify the CAPTCHA
        $result = $captcha->verify($captcha_response, $captcha_action);
        
        if (!$result['success']) {
            return new \WP_Error(
                $result['code'] ?? 'captcha_failed',
                $result['message'],
                ['status' => 400]
            );
        }
        
        return null;
    }
}
