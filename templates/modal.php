<?php
/**
 * Modal Template - REST API Version
 * 
 * Uses modern fetch() API with WordPress REST endpoints
 * instead of legacy admin-ajax.php
 * 
 * @var array $options
 * @var string $terms_url
 * @var string $contact_url
 * @var string $nonce
 * @var string $redirect
 * @var string $rest_url
 * @var string $terms_content
 * @var array $business_types
 * @var bool $captcha_enabled
 * @var string $captcha_provider
 * @var string $captcha_site_key
 * @var string $captcha_script_url
 * @var string $captcha_response_field
 */

defined('ABSPATH') || exit;
?>

<div id="rag-access-gate" class="rag-overlay" role="dialog" aria-modal="true" aria-labelledby="rag-title">
    <div class="rag-modal">
        
        <!-- Header -->
        <div class="rag-modal-header">
            <?php if (!empty($options['logo_url'])): ?>
                <div class="rag-logo">
                    <img src="<?php echo esc_url($options['logo_url']); ?>" alt="<?php echo esc_attr($options['company_name']); ?>" loading="lazy">
                </div>
            <?php endif; ?>
            <h2 id="rag-title"><?php echo esc_html($options['modal_title']); ?></h2>
            <p><?php echo esc_html($options['modal_description']); ?></p>
        </div>
        
        <!-- Tabs -->
        <div class="rag-tabs" role="tablist">
            <button type="button" class="rag-tab active" data-tab="login" role="tab" aria-selected="true" aria-controls="rag-login-form">
                <?php esc_html_e('Log In', 'research-access-gate'); ?>
            </button>
            <button type="button" class="rag-tab" data-tab="register" role="tab" aria-selected="false" aria-controls="rag-register-form">
                <?php esc_html_e('Create Account', 'research-access-gate'); ?>
            </button>
        </div>
        
        <!-- Login Form -->
        <form id="rag-login-form" class="rag-form active" autocomplete="on" role="tabpanel">
            <input type="hidden" name="_wpnonce" value="<?php echo esc_attr($nonce); ?>">
            <input type="hidden" name="redirect" value="<?php echo esc_attr($redirect); ?>">
            
            <div class="rag-field">
                <label for="rag-login-email"><?php esc_html_e('Email address', 'research-access-gate'); ?> <span class="req" aria-hidden="true">*</span></label>
                <input type="text" id="rag-login-email" name="username" required autocomplete="username">
            </div>
            
            <div class="rag-field">
                <label for="rag-login-password"><?php esc_html_e('Password', 'research-access-gate'); ?> <span class="req" aria-hidden="true">*</span></label>
                <div class="rag-password-wrap">
                    <input type="password" id="rag-login-password" name="password" required autocomplete="current-password">
                    <button type="button" class="rag-toggle-pw" aria-label="<?php esc_attr_e('Toggle password visibility', 'research-access-gate'); ?>">
                        <svg class="eye-open" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                        <svg class="eye-closed" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="display:none"><path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"/><line x1="1" y1="1" x2="23" y2="23"/></svg>
                    </button>
                </div>
            </div>
            
            <?php if ($captcha_enabled): ?>
            <!-- CAPTCHA Widget for Login -->
            <div class="rag-captcha-container" id="rag-login-captcha">
                <?php if ($captcha_provider === 'recaptcha2'): ?>
                    <div class="g-recaptcha" data-sitekey="<?php echo esc_attr($captcha_site_key); ?>" data-callback="ragLoginCaptchaCallback" data-expired-callback="ragLoginCaptchaExpired"></div>
                <?php elseif ($captcha_provider === 'recaptcha3'): ?>
                    <input type="hidden" name="<?php echo esc_attr($captcha_response_field); ?>" id="rag-login-captcha-token">
                <?php elseif ($captcha_provider === 'hcaptcha'): ?>
                    <div class="h-captcha" data-sitekey="<?php echo esc_attr($captcha_site_key); ?>" data-callback="ragLoginCaptchaCallback" data-expired-callback="ragLoginCaptchaExpired"></div>
                <?php elseif ($captcha_provider === 'turnstile'): ?>
                    <div class="cf-turnstile" data-sitekey="<?php echo esc_attr($captcha_site_key); ?>" data-callback="ragLoginCaptchaCallback" data-expired-callback="ragLoginCaptchaExpired"></div>
                <?php endif; ?>
            </div>
            <?php endif; ?>
            
            <div class="rag-error" id="rag-login-error" role="alert" aria-live="polite"></div>
            
            <button type="submit" class="rag-btn rag-btn-primary">
                <span class="rag-btn-text"><?php esc_html_e('LOG IN', 'research-access-gate'); ?></span>
                <span class="rag-btn-loading" style="display:none;"><?php esc_html_e('Logging in...', 'research-access-gate'); ?></span>
            </button>
            
            <div class="rag-forgot">
                <a href="<?php echo esc_url(wp_lostpassword_url()); ?>"><?php esc_html_e('Forgot password?', 'research-access-gate'); ?></a>
            </div>
        </form>
        
        <!-- Register Form -->
        <form id="rag-register-form" class="rag-form" autocomplete="on" role="tabpanel" hidden>
            <input type="hidden" name="_wpnonce" value="<?php echo esc_attr($nonce); ?>">
            <input type="hidden" name="redirect" value="<?php echo esc_attr($redirect); ?>">
            
            <div class="rag-field">
                <label for="rag-reg-email"><?php esc_html_e('Email address', 'research-access-gate'); ?> <span class="req">*</span></label>
                <input type="email" id="rag-reg-email" name="email" required autocomplete="email">
            </div>
            
            <div class="rag-field">
                <label for="rag-reg-password"><?php esc_html_e('Password', 'research-access-gate'); ?> <span class="req">*</span></label>
                <div class="rag-password-wrap">
                    <input type="password" id="rag-reg-password" name="password" required minlength="8" autocomplete="new-password">
                    <button type="button" class="rag-toggle-pw" aria-label="<?php esc_attr_e('Toggle password visibility', 'research-access-gate'); ?>">
                        <svg class="eye-open" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                        <svg class="eye-closed" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="display:none"><path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"/><line x1="1" y1="1" x2="23" y2="23"/></svg>
                    </button>
                </div>
            </div>
            
            <div class="rag-field-row">
                <div class="rag-field rag-field-half">
                    <label for="rag-first-name"><?php esc_html_e('First Name', 'research-access-gate'); ?> <span class="req">*</span></label>
                    <input type="text" id="rag-first-name" name="first_name" required autocomplete="given-name">
                </div>
                <div class="rag-field rag-field-half">
                    <label for="rag-last-name"><?php esc_html_e('Last Name', 'research-access-gate'); ?> <span class="req">*</span></label>
                    <input type="text" id="rag-last-name" name="last_name" required autocomplete="family-name">
                </div>
            </div>
            
            <?php if ($options['require_phone'] === 'yes'): ?>
            <div class="rag-field">
                <label for="rag-phone"><?php esc_html_e('Phone Number', 'research-access-gate'); ?> <span class="req">*</span></label>
                <input type="tel" id="rag-phone" name="phone" placeholder="123-456-7890" required autocomplete="tel">
            </div>
            <?php endif; ?>
            
            <?php if ($options['require_business'] === 'yes' && !empty($business_types)): ?>
            <div class="rag-field">
                <label for="rag-business"><?php esc_html_e('Business Type / Industry', 'research-access-gate'); ?> <span class="req">*</span></label>
                <!-- Custom dropdown for consistent styling -->
                <div class="rag-custom-select" data-name="business_type" data-required="true">
                    <input type="hidden" name="business_type" id="rag-business" value="">
                    <div class="rag-select-trigger" tabindex="0" role="combobox" aria-expanded="false" aria-haspopup="listbox">
                        <span class="rag-select-value" data-placeholder="<?php esc_attr_e('Select your business type', 'research-access-gate'); ?>"><?php esc_html_e('Select your business type', 'research-access-gate'); ?></span>
                        <svg class="rag-select-arrow" width="12" height="12" viewBox="0 0 12 12" fill="currentColor"><path d="M6 8L1 3h10z"/></svg>
                    </div>
                    <ul class="rag-select-options" role="listbox">
                        <?php foreach ($business_types as $type): ?>
                            <li role="option" data-value="<?php echo esc_attr(sanitize_title($type)); ?>"><?php echo esc_html($type); ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            </div>
            <?php endif; ?>
            
            <!-- Terms Preview Box -->
            <div class="rag-terms-box">
                <div class="rag-terms-box-header"><?php echo esc_html($options['company_name'] . ' ' . $options['terms_title']); ?></div>
                <div class="rag-terms-box-content"><?php echo wp_kses_post($terms_content); ?></div>
            </div>
            
            <!-- Terms Checkbox -->
            <div class="rag-terms-checkbox">
                <label>
                    <input type="checkbox" name="terms_accepted" value="1" required>
                    <span class="rag-checkmark"></span>
                    <span class="rag-terms-text">
                        <?php echo esc_html($options['checkbox_text']); ?>
                        <a href="<?php echo esc_url($terms_url); ?>" target="_blank" rel="noopener"><?php esc_html_e('Terms of Service', 'research-access-gate'); ?></a>.
                        <span class="req">*</span>
                    </span>
                </label>
            </div>
            
            <?php if ($captcha_enabled): ?>
            <!-- CAPTCHA Widget for Register -->
            <div class="rag-captcha-container" id="rag-register-captcha">
                <?php if ($captcha_provider === 'recaptcha2'): ?>
                    <div class="g-recaptcha" data-sitekey="<?php echo esc_attr($captcha_site_key); ?>" data-callback="ragRegisterCaptchaCallback" data-expired-callback="ragRegisterCaptchaExpired"></div>
                <?php elseif ($captcha_provider === 'recaptcha3'): ?>
                    <input type="hidden" name="<?php echo esc_attr($captcha_response_field); ?>" id="rag-register-captcha-token">
                <?php elseif ($captcha_provider === 'hcaptcha'): ?>
                    <div class="h-captcha" data-sitekey="<?php echo esc_attr($captcha_site_key); ?>" data-callback="ragRegisterCaptchaCallback" data-expired-callback="ragRegisterCaptchaExpired"></div>
                <?php elseif ($captcha_provider === 'turnstile'): ?>
                    <div class="cf-turnstile" data-sitekey="<?php echo esc_attr($captcha_site_key); ?>" data-callback="ragRegisterCaptchaCallback" data-expired-callback="ragRegisterCaptchaExpired"></div>
                <?php endif; ?>
            </div>
            <?php endif; ?>
            
            <div class="rag-error" id="rag-register-error" role="alert" aria-live="polite"></div>
            
            <button type="submit" class="rag-btn rag-btn-primary">
                <span class="rag-btn-text"><?php esc_html_e('REGISTER', 'research-access-gate'); ?></span>
                <span class="rag-btn-loading" style="display:none;"><?php esc_html_e('Creating account...', 'research-access-gate'); ?></span>
            </button>
        </form>
        
        <!-- Footer -->
        <div class="rag-modal-footer">
            <a href="<?php echo esc_url($contact_url); ?>"><?php esc_html_e('Need help? Contact us', 'research-access-gate'); ?></a>
        </div>
        
    </div>
</div>

<?php if ($captcha_enabled && !empty($captcha_script_url)): ?>
<!-- CAPTCHA Provider Script -->
<script src="<?php echo esc_url($captcha_script_url); ?>" async defer></script>
<?php endif; ?>

<script>
/**
 * Research Access Gate - Frontend JavaScript
 * Uses modern fetch() API with WordPress REST endpoints
 * No jQuery dependency (2025 best practice)
 */
(function() {
    'use strict';
    
    const REST_URL = <?php echo wp_json_encode($rest_url); ?>;
    const CAPTCHA_ENABLED = <?php echo $captcha_enabled ? 'true' : 'false'; ?>;
    const CAPTCHA_PROVIDER = <?php echo wp_json_encode($captcha_provider); ?>;
    const CAPTCHA_SITE_KEY = <?php echo wp_json_encode($captcha_site_key); ?>;
    const CAPTCHA_RESPONSE_FIELD = <?php echo wp_json_encode($captcha_response_field); ?>;
    
    // CAPTCHA token storage
    let loginCaptchaToken = '';
    let registerCaptchaToken = '';
    
    // CAPTCHA callbacks (global for provider scripts)
    window.ragLoginCaptchaCallback = function(token) {
        loginCaptchaToken = token;
    };
    window.ragLoginCaptchaExpired = function() {
        loginCaptchaToken = '';
    };
    window.ragRegisterCaptchaCallback = function(token) {
        registerCaptchaToken = token;
    };
    window.ragRegisterCaptchaExpired = function() {
        registerCaptchaToken = '';
    };
    
    // Lock body scroll
    document.body.classList.add('rag-locked');
    
    // Tab switching
    document.querySelectorAll('.rag-tab').forEach(tab => {
        tab.addEventListener('click', () => {
            document.querySelectorAll('.rag-tab').forEach(t => {
                t.classList.remove('active');
                t.setAttribute('aria-selected', 'false');
            });
            document.querySelectorAll('.rag-form').forEach(f => {
                f.classList.remove('active');
                f.hidden = true;
            });
            
            tab.classList.add('active');
            tab.setAttribute('aria-selected', 'true');
            const form = document.getElementById('rag-' + tab.dataset.tab + '-form');
            form.classList.add('active');
            form.hidden = false;
            form.querySelector('input:not([type="hidden"])').focus();
        });
    });
    
    // Password toggle
    document.querySelectorAll('.rag-toggle-pw').forEach(btn => {
        btn.addEventListener('click', () => {
            const input = btn.parentElement.querySelector('input');
            const isPassword = input.type === 'password';
            input.type = isPassword ? 'text' : 'password';
            btn.querySelector('.eye-open').style.display = isPassword ? 'none' : 'block';
            btn.querySelector('.eye-closed').style.display = isPassword ? 'block' : 'none';
        });
    });
    
    /**
     * Get CAPTCHA token for reCAPTCHA v3 (invisible)
     * Returns a promise that resolves with the token
     */
    async function getCaptchaToken(action, isLogin) {
        if (!CAPTCHA_ENABLED) {
            return null;
        }
        
        if (CAPTCHA_PROVIDER === 'recaptcha3') {
            // reCAPTCHA v3 - get token on demand
            if (typeof grecaptcha !== 'undefined' && grecaptcha.execute) {
                try {
                    const token = await grecaptcha.execute(CAPTCHA_SITE_KEY, { action: action });
                    return token;
                } catch (err) {
                    console.error('reCAPTCHA v3 error:', err);
                    return null;
                }
            }
            return null;
        }
        
        // For checkbox-based CAPTCHAs, return the stored token
        return isLogin ? loginCaptchaToken : registerCaptchaToken;
    }
    
    /**
     * Reset CAPTCHA widget after failed submission
     */
    function resetCaptcha(isLogin) {
        if (!CAPTCHA_ENABLED) return;
        
        if (CAPTCHA_PROVIDER === 'recaptcha2' && typeof grecaptcha !== 'undefined') {
            grecaptcha.reset();
        } else if (CAPTCHA_PROVIDER === 'hcaptcha' && typeof hcaptcha !== 'undefined') {
            hcaptcha.reset();
        } else if (CAPTCHA_PROVIDER === 'turnstile' && typeof turnstile !== 'undefined') {
            turnstile.reset();
        }
        
        // Clear stored tokens
        if (isLogin) {
            loginCaptchaToken = '';
        } else {
            registerCaptchaToken = '';
        }
    }
    
    /**
     * Handle form submission via REST API
     * Modern approach - faster than admin-ajax.php
     */
    async function handleSubmit(form, endpoint, errorElId, isLogin) {
        const btn = form.querySelector('.rag-btn');
        const errorEl = document.getElementById(errorElId);
        
        // UI feedback
        btn.disabled = true;
        btn.querySelector('.rag-btn-text').style.display = 'none';
        btn.querySelector('.rag-btn-loading').style.display = 'inline';
        errorEl.textContent = '';
        
        // Build form data as JSON (REST API prefers JSON)
        const formData = new FormData(form);
        const data = Object.fromEntries(formData.entries());
        
        // Convert checkbox to boolean
        if ('terms_accepted' in data) {
            data.terms_accepted = data.terms_accepted === '1';
        }
        
        // Get CAPTCHA token
        if (CAPTCHA_ENABLED) {
            const action = isLogin ? 'login' : 'register';
            const captchaToken = await getCaptchaToken(action, isLogin);
            
            if (CAPTCHA_PROVIDER !== 'recaptcha3' && !captchaToken) {
                // Checkbox CAPTCHA not completed
                errorEl.textContent = <?php echo wp_json_encode(__('Please complete the CAPTCHA verification.', 'research-access-gate')); ?>;
                resetButton();
                return;
            }
            
            // Add CAPTCHA token to request
            data[CAPTCHA_RESPONSE_FIELD] = captchaToken;
            data.captcha_action = action;
        }
        
        try {
            const response = await fetch(REST_URL + endpoint, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify(data),
                credentials: 'same-origin'
            });
            
            const result = await response.json();
            
            if (response.ok && result.success) {
                // Success - redirect
                window.location.href = result.redirect;
            } else {
                // Error
                errorEl.textContent = result.message || result.data?.message || 'An error occurred';
                resetButton();
                resetCaptcha(isLogin);
                
                // Show remaining attempts warning if provided
                if (result.data?.remaining_attempts) {
                    errorEl.textContent += ' (' + result.data.remaining_attempts + ' attempts remaining)';
                }
            }
        } catch (err) {
            errorEl.textContent = <?php echo wp_json_encode(__('Connection error. Please try again.', 'research-access-gate')); ?>;
            resetButton();
            resetCaptcha(isLogin);
        }
        
        function resetButton() {
            btn.disabled = false;
            btn.querySelector('.rag-btn-text').style.display = 'inline';
            btn.querySelector('.rag-btn-loading').style.display = 'none';
        }
    }
    
    // Login form
    document.getElementById('rag-login-form').addEventListener('submit', e => {
        e.preventDefault();
        handleSubmit(e.target, '/login', 'rag-login-error', true);
    });
    
    // Register form
    document.getElementById('rag-register-form').addEventListener('submit', e => {
        e.preventDefault();
        handleSubmit(e.target, '/register', 'rag-register-error', false);
    });
    
    // Focus first input
    document.getElementById('rag-login-email').focus();
    
    /**
     * Custom Dropdown Handler
     * Replaces native select for consistent cross-browser styling
     */
    document.querySelectorAll('.rag-custom-select').forEach(select => {
        const trigger = select.querySelector('.rag-select-trigger');
        const valueDisplay = select.querySelector('.rag-select-value');
        const options = select.querySelector('.rag-select-options');
        const hiddenInput = select.querySelector('input[type="hidden"]');
        const placeholder = valueDisplay.dataset.placeholder;
        
        // Toggle dropdown
        trigger.addEventListener('click', (e) => {
            e.stopPropagation();
            const isOpen = select.classList.contains('is-open');
            // Close all other dropdowns
            document.querySelectorAll('.rag-custom-select.is-open').forEach(s => s.classList.remove('is-open'));
            if (!isOpen) {
                select.classList.add('is-open');
                trigger.setAttribute('aria-expanded', 'true');
            }
        });
        
        // Keyboard support
        trigger.addEventListener('keydown', (e) => {
            if (e.key === 'Enter' || e.key === ' ') {
                e.preventDefault();
                trigger.click();
            } else if (e.key === 'Escape') {
                select.classList.remove('is-open');
                trigger.setAttribute('aria-expanded', 'false');
            }
        });
        
        // Select option
        options.querySelectorAll('li').forEach(option => {
            option.addEventListener('click', () => {
                const value = option.dataset.value;
                const text = option.textContent;
                
                // Update hidden input
                hiddenInput.value = value;
                
                // Update display
                valueDisplay.textContent = text;
                valueDisplay.classList.remove('is-placeholder');
                
                // Mark as selected
                options.querySelectorAll('li').forEach(o => o.classList.remove('is-selected'));
                option.classList.add('is-selected');
                
                // Close dropdown
                select.classList.remove('is-open');
                trigger.setAttribute('aria-expanded', 'false');
            });
        });
        
        // Initialize placeholder state
        if (!hiddenInput.value) {
            valueDisplay.classList.add('is-placeholder');
        }
    });
    
    // Close dropdowns when clicking outside
    document.addEventListener('click', () => {
        document.querySelectorAll('.rag-custom-select.is-open').forEach(s => {
            s.classList.remove('is-open');
            s.querySelector('.rag-select-trigger').setAttribute('aria-expanded', 'false');
        });
    });
    
    // Escape key doesn't close (security - must authenticate)
    // But we trap focus within the modal for accessibility
    document.getElementById('rag-access-gate').addEventListener('keydown', e => {
        if (e.key === 'Tab') {
            const focusable = document.querySelectorAll('#rag-access-gate button, #rag-access-gate input, #rag-access-gate select, #rag-access-gate a');
            const first = focusable[0];
            const last = focusable[focusable.length - 1];
            
            if (e.shiftKey && document.activeElement === first) {
                e.preventDefault();
                last.focus();
            } else if (!e.shiftKey && document.activeElement === last) {
                e.preventDefault();
                first.focus();
            }
        }
    });
})();
</script>
