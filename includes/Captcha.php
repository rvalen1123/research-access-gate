<?php
/**
 * CAPTCHA Integration
 * 
 * Provides support for multiple CAPTCHA providers:
 * - Google reCAPTCHA v2 (checkbox)
 * - Google reCAPTCHA v3 (invisible)
 * - hCaptcha
 * - Cloudflare Turnstile
 * 
 * @package PremierBioLabs\ResearchAccessGate
 * @since 2.0.2
 */

declare(strict_types=1);

namespace PremierBioLabs\ResearchAccessGate;

defined('ABSPATH') || exit;

class Captcha {
    
    /**
     * Supported CAPTCHA providers
     */
    public const PROVIDERS = [
        'none'       => 'Disabled',
        'recaptcha2' => 'Google reCAPTCHA v2 (Checkbox)',
        'recaptcha3' => 'Google reCAPTCHA v3 (Invisible)',
        'hcaptcha'   => 'hCaptcha',
        'turnstile'  => 'Cloudflare Turnstile',
    ];
    
    /**
     * Provider API endpoints
     */
    private const VERIFY_URLS = [
        'recaptcha2' => 'https://www.google.com/recaptcha/api/siteverify',
        'recaptcha3' => 'https://www.google.com/recaptcha/api/siteverify',
        'hcaptcha'   => 'https://hcaptcha.com/siteverify',
        'turnstile'  => 'https://challenges.cloudflare.com/turnstile/v0/siteverify',
    ];
    
    /**
     * Provider script URLs
     */
    private const SCRIPT_URLS = [
        'recaptcha2' => 'https://www.google.com/recaptcha/api.js',
        'recaptcha3' => 'https://www.google.com/recaptcha/api.js?render=',
        'hcaptcha'   => 'https://js.hcaptcha.com/1/api.js',
        'turnstile'  => 'https://challenges.cloudflare.com/turnstile/v0/api.js',
    ];
    
    /**
     * Response field names per provider
     */
    private const RESPONSE_FIELDS = [
        'recaptcha2' => 'g-recaptcha-response',
        'recaptcha3' => 'g-recaptcha-response',
        'hcaptcha'   => 'h-captcha-response',
        'turnstile'  => 'cf-turnstile-response',
    ];
    
    private Settings $settings;
    
    public function __construct(Settings $settings) {
        $this->settings = $settings;
    }
    
    /**
     * Check if CAPTCHA is enabled
     * 
     * @return bool
     */
    public function is_enabled(): bool {
        $provider = $this->get_provider();
        return !empty($provider) && $provider !== 'none' && $this->has_credentials();
    }
    
    /**
     * Get current CAPTCHA provider
     * 
     * @return string Provider key
     */
    public function get_provider(): string {
        return $this->settings->get('captcha_provider') ?: 'none';
    }
    
    /**
     * Check if credentials are configured
     * 
     * @return bool
     */
    public function has_credentials(): bool {
        $site_key = $this->get_site_key();
        $secret_key = $this->get_secret_key();
        return !empty($site_key) && !empty($secret_key);
    }
    
    /**
     * Get site key (public key)
     * 
     * Priority: wp-config constant > settings
     * 
     * @return string
     */
    public function get_site_key(): string {
        // Check for wp-config.php constant first
        if (defined('RAG_CAPTCHA_SITE_KEY') && !empty(RAG_CAPTCHA_SITE_KEY)) {
            return RAG_CAPTCHA_SITE_KEY;
        }
        
        return $this->settings->get('captcha_site_key') ?: '';
    }
    
    /**
     * Get secret key (private key)
     * 
     * Priority: wp-config constant > settings
     * 
     * @return string
     */
    public function get_secret_key(): string {
        // Check for wp-config.php constant first (recommended for security)
        if (defined('RAG_CAPTCHA_SECRET_KEY') && !empty(RAG_CAPTCHA_SECRET_KEY)) {
            return RAG_CAPTCHA_SECRET_KEY;
        }
        
        return $this->settings->get('captcha_secret_key') ?: '';
    }
    
    /**
     * Get the response field name for the current provider
     * 
     * @return string
     */
    public function get_response_field(): string {
        $provider = $this->get_provider();
        return self::RESPONSE_FIELDS[$provider] ?? 'captcha-response';
    }
    
    /**
     * Get script URL for the current provider
     * 
     * @return string
     */
    public function get_script_url(): string {
        $provider = $this->get_provider();
        $base_url = self::SCRIPT_URLS[$provider] ?? '';
        
        // reCAPTCHA v3 needs site key in URL
        if ($provider === 'recaptcha3') {
            return $base_url . $this->get_site_key();
        }
        
        return $base_url;
    }
    
    /**
     * Render CAPTCHA widget HTML
     * 
     * @param string $form_id Optional form identifier for multiple forms
     * @return string HTML output
     */
    public function render_widget(string $form_id = 'rag-form'): string {
        if (!$this->is_enabled()) {
            return '';
        }
        
        $provider = $this->get_provider();
        $site_key = esc_attr($this->get_site_key());
        
        switch ($provider) {
            case 'recaptcha2':
                return sprintf(
                    '<div class="g-recaptcha rag-captcha" data-sitekey="%s" data-callback="ragCaptchaCallback"></div>',
                    $site_key
                );
                
            case 'recaptcha3':
                // reCAPTCHA v3 is invisible - just needs a hidden input
                return sprintf(
                    '<input type="hidden" name="g-recaptcha-response" id="rag-recaptcha-response-%s" />',
                    esc_attr($form_id)
                );
                
            case 'hcaptcha':
                return sprintf(
                    '<div class="h-captcha rag-captcha" data-sitekey="%s" data-callback="ragCaptchaCallback"></div>',
                    $site_key
                );
                
            case 'turnstile':
                return sprintf(
                    '<div class="cf-turnstile rag-captcha" data-sitekey="%s" data-callback="ragCaptchaCallback"></div>',
                    $site_key
                );
                
            default:
                return '';
        }
    }
    
    /**
     * Get JavaScript for CAPTCHA handling
     * 
     * @return string JavaScript code
     */
    public function get_script(): string {
        if (!$this->is_enabled()) {
            return '';
        }
        
        $provider = $this->get_provider();
        $site_key = esc_js($this->get_site_key());
        
        $script = "window.ragCaptchaReady = false;\n";
        $script .= "window.ragCaptchaResponse = '';\n";
        $script .= "function ragCaptchaCallback(response) { window.ragCaptchaResponse = response; }\n";
        
        switch ($provider) {
            case 'recaptcha3':
                $script .= <<<JS
grecaptcha.ready(function() {
    window.ragCaptchaReady = true;
});
function ragGetCaptchaToken(action) {
    return grecaptcha.execute('{$site_key}', {action: action}).then(function(token) {
        window.ragCaptchaResponse = token;
        document.getElementById('rag-recaptcha-response-rag-form').value = token;
        return token;
    });
}
JS;
                break;
                
            case 'recaptcha2':
            case 'hcaptcha':
            case 'turnstile':
                $script .= <<<JS
window.ragCaptchaReady = true;
function ragGetCaptchaToken(action) {
    return Promise.resolve(window.ragCaptchaResponse);
}
JS;
                break;
        }
        
        return $script;
    }
    
    /**
     * Verify CAPTCHA response
     * 
     * @param string $response CAPTCHA response token
     * @param string $action Action name (for reCAPTCHA v3)
     * @return array Result with 'success' boolean and 'message'
     */
    public function verify(string $response, string $action = 'submit'): array {
        if (!$this->is_enabled()) {
            return ['success' => true, 'message' => 'CAPTCHA disabled'];
        }
        
        if (empty($response)) {
            return [
                'success' => false,
                'message' => __('Please complete the CAPTCHA verification.', 'research-access-gate'),
                'code'    => 'captcha_missing',
            ];
        }
        
        $provider = $this->get_provider();
        $verify_url = self::VERIFY_URLS[$provider] ?? '';
        
        if (empty($verify_url)) {
            return ['success' => true, 'message' => 'Unknown provider'];
        }
        
        // Build verification request
        $body = [
            'secret'   => $this->get_secret_key(),
            'response' => $response,
            'remoteip' => Helpers::get_client_ip(),
        ];
        
        // Make verification request
        $result = wp_remote_post($verify_url, [
            'body'    => $body,
            'timeout' => 10,
        ]);
        
        if (is_wp_error($result)) {
            // Log error but don't block user if verification service is down
            error_log('RAG CAPTCHA verification failed: ' . $result->get_error_message());
            
            // Fail open or closed based on setting
            $fail_open = apply_filters('rag_captcha_fail_open', false);
            if ($fail_open) {
                return ['success' => true, 'message' => 'Verification service unavailable'];
            }
            
            return [
                'success' => false,
                'message' => __('CAPTCHA verification failed. Please try again.', 'research-access-gate'),
                'code'    => 'captcha_service_error',
            ];
        }
        
        $response_body = json_decode(wp_remote_retrieve_body($result), true);
        
        if (empty($response_body)) {
            return [
                'success' => false,
                'message' => __('CAPTCHA verification failed. Please try again.', 'research-access-gate'),
                'code'    => 'captcha_invalid_response',
            ];
        }
        
        // Check success
        if (empty($response_body['success'])) {
            $error_codes = $response_body['error-codes'] ?? [];
            error_log('RAG CAPTCHA verification failed: ' . implode(', ', $error_codes));
            
            return [
                'success' => false,
                'message' => __('CAPTCHA verification failed. Please try again.', 'research-access-gate'),
                'code'    => 'captcha_failed',
                'errors'  => $error_codes,
            ];
        }
        
        // For reCAPTCHA v3, check score
        if ($provider === 'recaptcha3') {
            $score = $response_body['score'] ?? 0;
            $threshold = (float) ($this->settings->get('captcha_threshold') ?: 0.5);
            
            if ($score < $threshold) {
                return [
                    'success' => false,
                    'message' => __('Suspicious activity detected. Please try again.', 'research-access-gate'),
                    'code'    => 'captcha_low_score',
                    'score'   => $score,
                ];
            }
            
            // Optionally verify action
            $returned_action = $response_body['action'] ?? '';
            if (!empty($action) && $returned_action !== $action) {
                return [
                    'success' => false,
                    'message' => __('CAPTCHA verification failed. Please try again.', 'research-access-gate'),
                    'code'    => 'captcha_action_mismatch',
                ];
            }
        }
        
        return [
            'success' => true,
            'message' => 'CAPTCHA verified',
            'score'   => $response_body['score'] ?? null,
        ];
    }
    
    /**
     * Get available providers for settings dropdown
     * 
     * @return array Provider options
     */
    public static function get_provider_options(): array {
        return self::PROVIDERS;
    }
}
