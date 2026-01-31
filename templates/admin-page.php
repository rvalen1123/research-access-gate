<?php
/**
 * Admin Settings Page Template
 * 
 * @var array $options
 * @var bool $mu_installed
 */

defined('ABSPATH') || exit;

use PremierBioLabs\ResearchAccessGate\Settings;
use PremierBioLabs\ResearchAccessGate\License;

$license = License::get_license();
$license_status = License::get_status();
$is_licensed = License::is_valid();
$is_dev = License::is_dev_environment();

// Check if constants are defined for security settings
$captcha_site_key_from_constant = defined('RAG_CAPTCHA_SITE_KEY');
$captcha_secret_key_from_constant = defined('RAG_CAPTCHA_SECRET_KEY');
$license_salt_from_constant = defined('RAG_LICENSE_SALT');

// Get current tab
$current_tab = isset($_GET['tab']) ? sanitize_key($_GET['tab']) : 'general';
?>
<div class="wrap rag-admin">
    <h1><?php esc_html_e('Research Access Gate', 'research-access-gate'); ?></h1>
    
    <div class="rag-admin-header">
        <div class="rag-version">
            <?php printf(esc_html__('Version %s', 'research-access-gate'), \PremierBioLabs\ResearchAccessGate\VERSION); ?>
            <span class="rag-badge <?php echo $options['enabled'] === 'yes' ? 'rag-badge-success' : 'rag-badge-warning'; ?>">
                <?php echo $options['enabled'] === 'yes' ? esc_html__('Active', 'research-access-gate') : esc_html__('Disabled', 'research-access-gate'); ?>
            </span>
        </div>
    </div>
    
    <?php if ( isset( $_GET['settings-updated'] ) && 'true' === $_GET['settings-updated'] ): // phpcs:ignore WordPress.Security.NonceVerification.Recommended ?>
        <div class="notice notice-success is-dismissible">
            <p><?php esc_html_e('Settings saved successfully.', 'research-access-gate'); ?></p>
        </div>
    <?php endif; ?>
    
    <!-- Tab Navigation -->
    <nav class="nav-tab-wrapper rag-nav-tabs">
        <a href="<?php echo esc_url(add_query_arg('tab', 'general', remove_query_arg('settings-updated'))); ?>" 
           class="nav-tab <?php echo $current_tab === 'general' ? 'nav-tab-active' : ''; ?>">
            <span class="dashicons dashicons-admin-settings"></span>
            <?php esc_html_e('General', 'research-access-gate'); ?>
        </a>
        <a href="<?php echo esc_url(add_query_arg('tab', 'security', remove_query_arg('settings-updated'))); ?>" 
           class="nav-tab <?php echo $current_tab === 'security' ? 'nav-tab-active' : ''; ?>">
            <span class="dashicons dashicons-shield"></span>
            <?php esc_html_e('Security', 'research-access-gate'); ?>
        </a>
        <a href="<?php echo esc_url(add_query_arg('tab', 'appearance', remove_query_arg('settings-updated'))); ?>" 
           class="nav-tab <?php echo $current_tab === 'appearance' ? 'nav-tab-active' : ''; ?>">
            <span class="dashicons dashicons-art"></span>
            <?php esc_html_e('Appearance', 'research-access-gate'); ?>
        </a>
        <a href="<?php echo esc_url(add_query_arg('tab', 'advanced', remove_query_arg('settings-updated'))); ?>" 
           class="nav-tab <?php echo $current_tab === 'advanced' ? 'nav-tab-active' : ''; ?>">
            <span class="dashicons dashicons-admin-tools"></span>
            <?php esc_html_e('Advanced', 'research-access-gate'); ?>
        </a>
    </nav>
    
    <form method="post" action="options.php" class="rag-settings-form">
        <?php settings_fields('rag_settings_group'); ?>
        
        <!-- General Tab -->
        <div class="rag-tab-content <?php echo $current_tab === 'general' ? 'is-active' : ''; ?>" id="tab-general">
            <div class="rag-admin-grid">
                <!-- Left Column -->
                <div class="rag-admin-main">
                    
                    <!-- General Settings -->
                    <div class="rag-card">
                        <h2 class="rag-card-title"><?php esc_html_e('General Settings', 'research-access-gate'); ?></h2>
                        
                        <table class="form-table">
                            <tr>
                                <th scope="row"><?php esc_html_e('Enable Gate', 'research-access-gate'); ?></th>
                                <td>
                                    <label class="rag-toggle">
                                        <input type="checkbox" name="rag_settings[enabled]" value="yes" <?php checked($options['enabled'], 'yes'); ?>>
                                        <span class="rag-toggle-slider"></span>
                                    </label>
                                    <p class="description"><?php esc_html_e('Show login/register modal to non-logged-in users.', 'research-access-gate'); ?></p>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><?php esc_html_e('Company Name', 'research-access-gate'); ?></th>
                                <td>
                                    <input type="text" name="rag_settings[company_name]" value="<?php echo esc_attr($options['company_name']); ?>" class="regular-text">
                                    <p class="description"><?php esc_html_e('Used in Terms content ({{company_name}} placeholder).', 'research-access-gate'); ?></p>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><?php esc_html_e('Logo URL', 'research-access-gate'); ?></th>
                                <td>
                                    <input type="url" name="rag_settings[logo_url]" id="rag-logo-url" value="<?php echo esc_url($options['logo_url']); ?>" class="regular-text">
                                    <button type="button" class="button" id="rag-upload-logo"><?php esc_html_e('Select Image', 'research-access-gate'); ?></button>
                                    <?php if (!empty($options['logo_url'])): ?>
                                        <div class="rag-logo-preview">
                                            <img src="<?php echo esc_url($options['logo_url']); ?>" alt="Logo preview">
                                        </div>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        </table>
                    </div>
                    
                    <!-- Modal Content -->
                    <div class="rag-card">
                        <h2 class="rag-card-title"><?php esc_html_e('Modal Content', 'research-access-gate'); ?></h2>
                        
                        <table class="form-table">
                            <tr>
                                <th scope="row"><?php esc_html_e('Modal Title', 'research-access-gate'); ?></th>
                                <td>
                                    <input type="text" name="rag_settings[modal_title]" value="<?php echo esc_attr($options['modal_title']); ?>" class="large-text">
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><?php esc_html_e('Modal Description', 'research-access-gate'); ?></th>
                                <td>
                                    <textarea name="rag_settings[modal_description]" rows="3" class="large-text"><?php echo esc_textarea($options['modal_description']); ?></textarea>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><?php esc_html_e('Terms Title', 'research-access-gate'); ?></th>
                                <td>
                                    <input type="text" name="rag_settings[terms_title]" value="<?php echo esc_attr($options['terms_title']); ?>" class="regular-text">
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><?php esc_html_e('Terms Content', 'research-access-gate'); ?></th>
                                <td>
                                    <textarea name="rag_settings[terms_content]" rows="8" class="large-text code"><?php echo esc_textarea($options['terms_content']); ?></textarea>
                                    <p class="description"><?php esc_html_e('Use {{company_name}} placeholder. Basic HTML allowed.', 'research-access-gate'); ?></p>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><?php esc_html_e('Checkbox Text', 'research-access-gate'); ?></th>
                                <td>
                                    <textarea name="rag_settings[checkbox_text]" rows="3" class="large-text"><?php echo esc_textarea($options['checkbox_text']); ?></textarea>
                                </td>
                            </tr>
                        </table>
                    </div>
                    
                    <!-- Registration Fields -->
                    <div class="rag-card">
                        <h2 class="rag-card-title"><?php esc_html_e('Registration Fields', 'research-access-gate'); ?></h2>
                        
                        <table class="form-table">
                            <tr>
                                <th scope="row"><?php esc_html_e('Require Phone', 'research-access-gate'); ?></th>
                                <td>
                                    <label class="rag-toggle">
                                        <input type="checkbox" name="rag_settings[require_phone]" value="yes" <?php checked($options['require_phone'], 'yes'); ?>>
                                        <span class="rag-toggle-slider"></span>
                                    </label>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><?php esc_html_e('Require Business Type', 'research-access-gate'); ?></th>
                                <td>
                                    <label class="rag-toggle">
                                        <input type="checkbox" name="rag_settings[require_business]" value="yes" <?php checked($options['require_business'], 'yes'); ?>>
                                        <span class="rag-toggle-slider"></span>
                                    </label>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><?php esc_html_e('Business Types', 'research-access-gate'); ?></th>
                                <td>
                                    <textarea name="rag_settings[business_types]" rows="4" class="regular-text"><?php echo esc_textarea($options['business_types']); ?></textarea>
                                    <p class="description"><?php esc_html_e('One per line. These appear in the dropdown.', 'research-access-gate'); ?></p>
                                </td>
                            </tr>
                        </table>
                    </div>
                    
                    <!-- Page Settings -->
                    <div class="rag-card">
                        <h2 class="rag-card-title"><?php esc_html_e('Page Settings', 'research-access-gate'); ?></h2>
                        
                        <table class="form-table">
                            <tr>
                                <th scope="row"><?php esc_html_e('Terms Page Slug', 'research-access-gate'); ?></th>
                                <td>
                                    <input type="text" name="rag_settings[terms_page_slug]" value="<?php echo esc_attr($options['terms_page_slug']); ?>" class="regular-text">
                                    <p class="description"><?php esc_html_e('Fallback if WooCommerce T&C page not set.', 'research-access-gate'); ?></p>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><?php esc_html_e('Contact Page Slug', 'research-access-gate'); ?></th>
                                <td>
                                    <input type="text" name="rag_settings[contact_page_slug]" value="<?php echo esc_attr($options['contact_page_slug']); ?>" class="regular-text">
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><?php esc_html_e('Excluded Pages', 'research-access-gate'); ?></th>
                                <td>
                                    <textarea name="rag_settings[excluded_pages]" rows="5" class="regular-text"><?php echo esc_textarea($options['excluded_pages']); ?></textarea>
                                    <p class="description"><?php esc_html_e('Page slugs (one per line) where gate will NOT show.', 'research-access-gate'); ?></p>
                                </td>
                            </tr>
                        </table>
                    </div>
                    
                </div>
                
                <!-- Right Column -->
                <div class="rag-admin-sidebar">
                    
                    <!-- License -->
                    <div class="rag-card rag-card-license <?php echo $is_licensed ? 'is-licensed' : 'is-unlicensed'; ?>">
                        <h2 class="rag-card-title">
                            <?php esc_html_e('License', 'research-access-gate'); ?>
                            <?php if ($is_licensed): ?>
                                <span class="rag-badge rag-badge-success"><?php esc_html_e('Active', 'research-access-gate'); ?></span>
                            <?php elseif ($is_dev): ?>
                                <span class="rag-badge rag-badge-info"><?php esc_html_e('Dev Mode', 'research-access-gate'); ?></span>
                            <?php else: ?>
                                <span class="rag-badge rag-badge-error"><?php esc_html_e('Inactive', 'research-access-gate'); ?></span>
                            <?php endif; ?>
                        </h2>
                        
                        <?php if ($is_licensed): ?>
                            <div class="rag-license-info">
                                <div class="rag-license-key-display">
                                    <code><?php echo esc_html(substr($license['key'], 0, 8) . '••••••••••••••••'); ?></code>
                                </div>
                                <p class="rag-license-meta">
                                    <?php if (!empty($license['email'])): ?>
                                        <span><?php echo esc_html($license['email']); ?></span><br>
                                    <?php endif; ?>
                                    <span class="rag-muted"><?php printf(esc_html__('Activated: %s', 'research-access-gate'), date_i18n(get_option('date_format'), strtotime($license['activated_at']))); ?></span>
                                </p>
                                <button type="button" class="button" id="rag-deactivate-license"><?php esc_html_e('Deactivate', 'research-access-gate'); ?></button>
                            </div>
                        <?php else: ?>
                            <?php if ($is_dev): ?>
                                <p class="rag-dev-notice">
                                    <span class="dashicons dashicons-info"></span>
                                    <?php esc_html_e('Development environment detected. License not required for local testing.', 'research-access-gate'); ?>
                                </p>
                            <?php endif; ?>
                            
                            <div class="rag-license-form">
                                <div class="rag-field-group">
                                    <label for="rag-license-key"><?php esc_html_e('License Key', 'research-access-gate'); ?></label>
                                    <input type="text" id="rag-license-key" placeholder="RAG-XXXX-XXXX-XXXX-XXXX" class="regular-text" autocomplete="off">
                                </div>
                                <div class="rag-field-group">
                                    <label for="rag-license-email"><?php esc_html_e('Email (optional)', 'research-access-gate'); ?></label>
                                    <input type="email" id="rag-license-email" placeholder="you@example.com" class="regular-text">
                                </div>
                                <div class="rag-license-error" id="rag-license-error"></div>
                                <button type="button" class="button button-primary" id="rag-activate-license"><?php esc_html_e('Activate License', 'research-access-gate'); ?></button>
                            </div>
                            
                            <?php if (!$is_dev): ?>
                                <p class="rag-purchase-link">
                                    <a href="https://premierbioresearch.com/plugins/research-access-gate" target="_blank" rel="noopener">
                                        <?php esc_html_e('Purchase a license →', 'research-access-gate'); ?>
                                    </a>
                                </p>
                            <?php endif; ?>
                        <?php endif; ?>
                    </div>
                    
                    <!-- Info -->
                    <div class="rag-card rag-card-info">
                        <h2 class="rag-card-title"><?php esc_html_e('How It Works', 'research-access-gate'); ?></h2>
                        <ul>
                            <li><?php esc_html_e('Non-logged-in users see a full-screen login/register modal', 'research-access-gate'); ?></li>
                            <li><?php esc_html_e('Registration requires 21+ Terms acknowledgment', 'research-access-gate'); ?></li>
                            <li><?php esc_html_e('WooCommerce checkout also enforces Terms', 'research-access-gate'); ?></li>
                            <li><?php esc_html_e('My Account and excluded pages are accessible without login', 'research-access-gate'); ?></li>
                        </ul>
                    </div>
                    
                </div>
            </div>
        </div>
        
        <!-- Security Tab -->
        <div class="rag-tab-content <?php echo $current_tab === 'security' ? 'is-active' : ''; ?>" id="tab-security">
            <div class="rag-admin-grid">
                <div class="rag-admin-main">
                    
                    <!-- CAPTCHA Settings -->
                    <div class="rag-card">
                        <h2 class="rag-card-title">
                            <span class="dashicons dashicons-shield-alt"></span>
                            <?php esc_html_e('CAPTCHA Protection', 'research-access-gate'); ?>
                        </h2>
                        
                        <p class="rag-card-description">
                            <?php esc_html_e('Protect login and registration forms from bots and automated attacks.', 'research-access-gate'); ?>
                        </p>
                        
                        <!-- Provider Selector -->
                        <div class="rag-captcha-providers">
                            <label class="rag-provider-card <?php echo ($options['captcha_provider'] ?? 'none') === 'none' ? 'is-selected' : ''; ?>">
                                <input type="radio" name="rag_settings[captcha_provider]" value="none" <?php checked($options['captcha_provider'] ?? 'none', 'none'); ?>>
                                <div class="rag-provider-card-inner">
                                    <div class="rag-provider-icon rag-provider-none">
                                        <span class="dashicons dashicons-dismiss"></span>
                                    </div>
                                    <div class="rag-provider-name"><?php esc_html_e('None', 'research-access-gate'); ?></div>
                                    <div class="rag-provider-desc"><?php esc_html_e('No CAPTCHA', 'research-access-gate'); ?></div>
                                </div>
                            </label>
                            
                            <label class="rag-provider-card <?php echo ($options['captcha_provider'] ?? 'none') === 'recaptcha2' ? 'is-selected' : ''; ?>">
                                <input type="radio" name="rag_settings[captcha_provider]" value="recaptcha2" <?php checked($options['captcha_provider'] ?? 'none', 'recaptcha2'); ?>>
                                <div class="rag-provider-card-inner">
                                    <div class="rag-provider-icon rag-provider-recaptcha">
                                        <svg viewBox="0 0 24 24" width="32" height="32"><path fill="#4285F4" d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-1 17.93c-3.95-.49-7-3.85-7-7.93 0-.62.08-1.21.21-1.79L9 15v1c0 1.1.9 2 2 2v1.93zm6.9-2.54c-.26-.81-1-1.39-1.9-1.39h-1v-3c0-.55-.45-1-1-1H8v-2h2c.55 0 1-.45 1-1V7h2c1.1 0 2-.9 2-2v-.41c2.93 1.19 5 4.06 5 7.41 0 2.08-.8 3.97-2.1 5.39z"/></svg>
                                    </div>
                                    <div class="rag-provider-name"><?php esc_html_e('reCAPTCHA v2', 'research-access-gate'); ?></div>
                                    <div class="rag-provider-desc"><?php esc_html_e('Checkbox', 'research-access-gate'); ?></div>
                                </div>
                            </label>
                            
                            <label class="rag-provider-card <?php echo ($options['captcha_provider'] ?? 'none') === 'recaptcha3' ? 'is-selected' : ''; ?>">
                                <input type="radio" name="rag_settings[captcha_provider]" value="recaptcha3" <?php checked($options['captcha_provider'] ?? 'none', 'recaptcha3'); ?>>
                                <div class="rag-provider-card-inner">
                                    <div class="rag-provider-icon rag-provider-recaptcha">
                                        <svg viewBox="0 0 24 24" width="32" height="32"><path fill="#4285F4" d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-1 17.93c-3.95-.49-7-3.85-7-7.93 0-.62.08-1.21.21-1.79L9 15v1c0 1.1.9 2 2 2v1.93zm6.9-2.54c-.26-.81-1-1.39-1.9-1.39h-1v-3c0-.55-.45-1-1-1H8v-2h2c.55 0 1-.45 1-1V7h2c1.1 0 2-.9 2-2v-.41c2.93 1.19 5 4.06 5 7.41 0 2.08-.8 3.97-2.1 5.39z"/></svg>
                                    </div>
                                    <div class="rag-provider-name"><?php esc_html_e('reCAPTCHA v3', 'research-access-gate'); ?></div>
                                    <div class="rag-provider-desc"><?php esc_html_e('Invisible', 'research-access-gate'); ?></div>
                                </div>
                            </label>
                            
                            <label class="rag-provider-card <?php echo ($options['captcha_provider'] ?? 'none') === 'hcaptcha' ? 'is-selected' : ''; ?>">
                                <input type="radio" name="rag_settings[captcha_provider]" value="hcaptcha" <?php checked($options['captcha_provider'] ?? 'none', 'hcaptcha'); ?>>
                                <div class="rag-provider-card-inner">
                                    <div class="rag-provider-icon rag-provider-hcaptcha">
                                        <svg viewBox="0 0 24 24" width="32" height="32"><path fill="#0074BF" d="M12 2L2 7v10l10 5 10-5V7L12 2zm0 2.18l7.5 3.75v7.14L12 18.82l-7.5-3.75V7.93L12 4.18z"/><path fill="#0074BF" d="M12 8a4 4 0 100 8 4 4 0 000-8z"/></svg>
                                    </div>
                                    <div class="rag-provider-name"><?php esc_html_e('hCaptcha', 'research-access-gate'); ?></div>
                                    <div class="rag-provider-desc"><?php esc_html_e('Privacy-focused', 'research-access-gate'); ?></div>
                                </div>
                            </label>
                            
                            <label class="rag-provider-card <?php echo ($options['captcha_provider'] ?? 'none') === 'turnstile' ? 'is-selected' : ''; ?>">
                                <input type="radio" name="rag_settings[captcha_provider]" value="turnstile" <?php checked($options['captcha_provider'] ?? 'none', 'turnstile'); ?>>
                                <div class="rag-provider-card-inner">
                                    <div class="rag-provider-icon rag-provider-turnstile">
                                        <svg viewBox="0 0 24 24" width="32" height="32"><path fill="#F38020" d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm0 18c-4.41 0-8-3.59-8-8s3.59-8 8-8 8 3.59 8 8-3.59 8-8 8z"/><path fill="#F38020" d="M12 6v6l4 2"/></svg>
                                    </div>
                                    <div class="rag-provider-name"><?php esc_html_e('Turnstile', 'research-access-gate'); ?></div>
                                    <div class="rag-provider-desc"><?php esc_html_e('Cloudflare', 'research-access-gate'); ?></div>
                                </div>
                            </label>
                        </div>
                        
                        <!-- CAPTCHA Keys -->
                        <div class="rag-captcha-keys" id="rag-captcha-keys" style="<?php echo ($options['captcha_provider'] ?? 'none') === 'none' ? 'display:none;' : ''; ?>">
                            <table class="form-table">
                                <tr>
                                    <th scope="row">
                                        <?php esc_html_e('Site Key', 'research-access-gate'); ?>
                                        <?php if ($captcha_site_key_from_constant): ?>
                                            <span class="rag-badge rag-badge-info rag-badge-small"><?php esc_html_e('wp-config.php', 'research-access-gate'); ?></span>
                                        <?php endif; ?>
                                    </th>
                                    <td>
                                        <?php if ($captcha_site_key_from_constant): ?>
                                            <input type="text" value="<?php echo esc_attr(substr(RAG_CAPTCHA_SITE_KEY, 0, 10) . '••••••••••'); ?>" class="regular-text" disabled>
                                            <p class="description"><?php esc_html_e('Defined in wp-config.php as RAG_CAPTCHA_SITE_KEY', 'research-access-gate'); ?></p>
                                        <?php else: ?>
                                            <input type="text" name="rag_settings[captcha_site_key]" value="<?php echo esc_attr($options['captcha_site_key'] ?? ''); ?>" class="regular-text" placeholder="6Le...">
                                            <p class="description"><?php esc_html_e('Public key from your CAPTCHA provider.', 'research-access-gate'); ?></p>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row">
                                        <?php esc_html_e('Secret Key', 'research-access-gate'); ?>
                                        <?php if ($captcha_secret_key_from_constant): ?>
                                            <span class="rag-badge rag-badge-info rag-badge-small"><?php esc_html_e('wp-config.php', 'research-access-gate'); ?></span>
                                        <?php endif; ?>
                                    </th>
                                    <td>
                                        <?php if ($captcha_secret_key_from_constant): ?>
                                            <input type="text" value="••••••••••••••••••••" class="regular-text" disabled>
                                            <p class="description"><?php esc_html_e('Defined in wp-config.php as RAG_CAPTCHA_SECRET_KEY', 'research-access-gate'); ?></p>
                                        <?php else: ?>
                                            <input type="password" name="rag_settings[captcha_secret_key]" value="<?php echo esc_attr($options['captcha_secret_key'] ?? ''); ?>" class="regular-text" placeholder="6Le...">
                                            <p class="description">
                                                <?php esc_html_e('Private key from your CAPTCHA provider.', 'research-access-gate'); ?>
                                                <strong><?php esc_html_e('Recommended:', 'research-access-gate'); ?></strong>
                                                <?php esc_html_e('Define in wp-config.php instead.', 'research-access-gate'); ?>
                                            </p>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <tr class="rag-recaptcha3-only" style="<?php echo ($options['captcha_provider'] ?? 'none') !== 'recaptcha3' ? 'display:none;' : ''; ?>">
                                    <th scope="row"><?php esc_html_e('Score Threshold', 'research-access-gate'); ?></th>
                                    <td>
                                        <input type="range" name="rag_settings[captcha_threshold]" id="rag-captcha-threshold" value="<?php echo esc_attr($options['captcha_threshold'] ?? '0.5'); ?>" min="0.1" max="0.9" step="0.1" class="rag-range-slider">
                                        <span class="rag-range-value" id="rag-threshold-value"><?php echo esc_html($options['captcha_threshold'] ?? '0.5'); ?></span>
                                        <p class="description"><?php esc_html_e('0.1 = lenient (allows more), 0.9 = strict (blocks more). Default: 0.5', 'research-access-gate'); ?></p>
                                    </td>
                                </tr>
                            </table>
                            
                            <!-- Provider Links -->
                            <div class="rag-provider-links">
                                <span class="rag-provider-link" data-provider="recaptcha2,recaptcha3">
                                    <a href="https://www.google.com/recaptcha/admin" target="_blank" rel="noopener">
                                        <?php esc_html_e('Get reCAPTCHA keys →', 'research-access-gate'); ?>
                                    </a>
                                </span>
                                <span class="rag-provider-link" data-provider="hcaptcha">
                                    <a href="https://dashboard.hcaptcha.com" target="_blank" rel="noopener">
                                        <?php esc_html_e('Get hCaptcha keys →', 'research-access-gate'); ?>
                                    </a>
                                </span>
                                <span class="rag-provider-link" data-provider="turnstile">
                                    <a href="https://dash.cloudflare.com/?to=/:account/turnstile" target="_blank" rel="noopener">
                                        <?php esc_html_e('Get Turnstile keys →', 'research-access-gate'); ?>
                                    </a>
                                </span>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Rate Limiting -->
                    <div class="rag-card">
                        <h2 class="rag-card-title">
                            <span class="dashicons dashicons-clock"></span>
                            <?php esc_html_e('Rate Limiting', 'research-access-gate'); ?>
                        </h2>
                        
                        <p class="rag-card-description">
                            <?php esc_html_e('Protect against brute force attacks by limiting login attempts.', 'research-access-gate'); ?>
                        </p>
                        
                        <table class="form-table">
                            <tr>
                                <th scope="row"><?php esc_html_e('Enable Rate Limiting', 'research-access-gate'); ?></th>
                                <td>
                                    <label class="rag-toggle">
                                        <input type="checkbox" name="rag_settings[rate_limit_enabled]" value="yes" <?php checked($options['rate_limit_enabled'] ?? 'yes', 'yes'); ?>>
                                        <span class="rag-toggle-slider"></span>
                                    </label>
                                    <p class="description"><?php esc_html_e('Temporarily block IPs after too many failed attempts.', 'research-access-gate'); ?></p>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><?php esc_html_e('Max Attempts', 'research-access-gate'); ?></th>
                                <td>
                                    <input type="number" name="rag_settings[rate_limit_attempts]" value="<?php echo esc_attr($options['rate_limit_attempts'] ?? '5'); ?>" min="1" max="20" class="small-text">
                                    <p class="description"><?php esc_html_e('Number of failed attempts before lockout. Default: 5', 'research-access-gate'); ?></p>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><?php esc_html_e('Lockout Duration', 'research-access-gate'); ?></th>
                                <td>
                                    <select name="rag_settings[rate_limit_lockout]">
                                        <option value="300" <?php selected($options['rate_limit_lockout'] ?? '900', '300'); ?>><?php esc_html_e('5 minutes', 'research-access-gate'); ?></option>
                                        <option value="900" <?php selected($options['rate_limit_lockout'] ?? '900', '900'); ?>><?php esc_html_e('15 minutes', 'research-access-gate'); ?></option>
                                        <option value="1800" <?php selected($options['rate_limit_lockout'] ?? '900', '1800'); ?>><?php esc_html_e('30 minutes', 'research-access-gate'); ?></option>
                                        <option value="3600" <?php selected($options['rate_limit_lockout'] ?? '900', '3600'); ?>><?php esc_html_e('1 hour', 'research-access-gate'); ?></option>
                                        <option value="86400" <?php selected($options['rate_limit_lockout'] ?? '900', '86400'); ?>><?php esc_html_e('24 hours', 'research-access-gate'); ?></option>
                                    </select>
                                    <p class="description"><?php esc_html_e('How long to block after max attempts reached.', 'research-access-gate'); ?></p>
                                </td>
                            </tr>
                        </table>
                    </div>
                    
                    <!-- Security Headers -->
                    <div class="rag-card">
                        <h2 class="rag-card-title">
                            <span class="dashicons dashicons-lock"></span>
                            <?php esc_html_e('Security Headers', 'research-access-gate'); ?>
                        </h2>
                        
                        <p class="rag-card-description">
                            <?php esc_html_e('Add Content Security Policy and other security headers to protect against XSS and clickjacking.', 'research-access-gate'); ?>
                        </p>
                        
                        <table class="form-table">
                            <tr>
                                <th scope="row"><?php esc_html_e('Enable Security Headers', 'research-access-gate'); ?></th>
                                <td>
                                    <label class="rag-toggle">
                                        <input type="checkbox" name="rag_settings[security_headers_enabled]" value="yes" <?php checked($options['security_headers_enabled'] ?? 'no', 'yes'); ?>>
                                        <span class="rag-toggle-slider"></span>
                                    </label>
                                    <p class="description"><?php esc_html_e('Add CSP, X-Frame-Options, and other security headers.', 'research-access-gate'); ?></p>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><?php esc_html_e('CSP Report-Only Mode', 'research-access-gate'); ?></th>
                                <td>
                                    <label class="rag-toggle">
                                        <input type="checkbox" name="rag_settings[csp_report_only]" value="yes" <?php checked($options['csp_report_only'] ?? 'yes', 'yes'); ?>>
                                        <span class="rag-toggle-slider"></span>
                                    </label>
                                    <p class="description"><?php esc_html_e('Test CSP without blocking. Check browser console for violations before enforcing.', 'research-access-gate'); ?></p>
                                </td>
                            </tr>
                        </table>
                        
                        <div class="rag-security-headers-list">
                            <h4><?php esc_html_e('Headers Added When Enabled:', 'research-access-gate'); ?></h4>
                            <ul>
                                <li><code>Content-Security-Policy</code> - <?php esc_html_e('Prevents XSS and injection attacks', 'research-access-gate'); ?></li>
                                <li><code>X-Frame-Options: SAMEORIGIN</code> - <?php esc_html_e('Prevents clickjacking', 'research-access-gate'); ?></li>
                                <li><code>X-Content-Type-Options: nosniff</code> - <?php esc_html_e('Prevents MIME sniffing', 'research-access-gate'); ?></li>
                                <li><code>Referrer-Policy</code> - <?php esc_html_e('Controls referrer information', 'research-access-gate'); ?></li>
                                <li><code>Permissions-Policy</code> - <?php esc_html_e('Disables unused browser features', 'research-access-gate'); ?></li>
                            </ul>
                        </div>
                    </div>
                    
                </div>
                
                <!-- Right Column -->
                <div class="rag-admin-sidebar">
                    
                    <!-- Security Status -->
                    <div class="rag-card rag-card-status">
                        <h2 class="rag-card-title"><?php esc_html_e('Security Status', 'research-access-gate'); ?></h2>
                        
                        <ul class="rag-status-list">
                            <li class="<?php echo ($options['captcha_provider'] ?? 'none') !== 'none' ? 'is-secure' : 'is-warning'; ?>">
                                <span class="dashicons <?php echo ($options['captcha_provider'] ?? 'none') !== 'none' ? 'dashicons-yes-alt' : 'dashicons-warning'; ?>"></span>
                                <?php esc_html_e('CAPTCHA Protection', 'research-access-gate'); ?>
                            </li>
                            <li class="<?php echo ($options['rate_limit_enabled'] ?? 'yes') === 'yes' ? 'is-secure' : 'is-warning'; ?>">
                                <span class="dashicons <?php echo ($options['rate_limit_enabled'] ?? 'yes') === 'yes' ? 'dashicons-yes-alt' : 'dashicons-warning'; ?>"></span>
                                <?php esc_html_e('Rate Limiting', 'research-access-gate'); ?>
                            </li>
                            <li class="<?php echo ($options['security_headers_enabled'] ?? 'no') === 'yes' ? 'is-secure' : 'is-warning'; ?>">
                                <span class="dashicons <?php echo ($options['security_headers_enabled'] ?? 'no') === 'yes' ? 'dashicons-yes-alt' : 'dashicons-warning'; ?>"></span>
                                <?php esc_html_e('Security Headers', 'research-access-gate'); ?>
                            </li>
                            <li class="<?php echo $license_salt_from_constant ? 'is-secure' : 'is-warning'; ?>">
                                <span class="dashicons <?php echo $license_salt_from_constant ? 'dashicons-yes-alt' : 'dashicons-warning'; ?>"></span>
                                <?php esc_html_e('License Salt Configured', 'research-access-gate'); ?>
                            </li>
                        </ul>
                        
                        <?php if (!$license_salt_from_constant): ?>
                            <div class="rag-status-notice">
                                <p><?php esc_html_e('Add to wp-config.php:', 'research-access-gate'); ?></p>
                                <code>define('RAG_LICENSE_SALT', '<?php echo esc_html(wp_generate_password(32, false)); ?>');</code>
                            </div>
                        <?php endif; ?>
                    </div>
                    
                    <!-- wp-config.php Reference -->
                    <div class="rag-card rag-card-info">
                        <h2 class="rag-card-title"><?php esc_html_e('wp-config.php Constants', 'research-access-gate'); ?></h2>
                        <p class="rag-card-description"><?php esc_html_e('For better security, define sensitive values in wp-config.php:', 'research-access-gate'); ?></p>
                        <pre class="rag-code-block">// CAPTCHA Keys
define('RAG_CAPTCHA_SITE_KEY', 'your-key');
define('RAG_CAPTCHA_SECRET_KEY', 'your-secret');

// License Salt
define('RAG_LICENSE_SALT', 'random-string');

// Rate Limiting
define('RAG_RATE_LIMIT_ATTEMPTS', 5);
define('RAG_RATE_LIMIT_LOCKOUT', 900);</pre>
                    </div>
                    
                </div>
            </div>
        </div>
        
        <!-- Appearance Tab -->
        <div class="rag-tab-content <?php echo $current_tab === 'appearance' ? 'is-active' : ''; ?>" id="tab-appearance">
            <div class="rag-admin-grid">
                <div class="rag-admin-main">
                    
                    <!-- Colors -->
                    <div class="rag-card">
                        <h2 class="rag-card-title"><?php esc_html_e('Colors', 'research-access-gate'); ?></h2>
                        
                        <div class="rag-color-grid rag-color-grid-large">
                            <div class="rag-color-field">
                                <label><?php esc_html_e('Primary', 'research-access-gate'); ?></label>
                                <input type="text" name="rag_settings[primary_color]" value="<?php echo esc_attr($options['primary_color']); ?>" class="rag-color-picker">
                                <p class="description"><?php esc_html_e('Buttons, links', 'research-access-gate'); ?></p>
                            </div>
                            <div class="rag-color-field">
                                <label><?php esc_html_e('Secondary', 'research-access-gate'); ?></label>
                                <input type="text" name="rag_settings[secondary_color]" value="<?php echo esc_attr($options['secondary_color']); ?>" class="rag-color-picker">
                                <p class="description"><?php esc_html_e('Hover states', 'research-access-gate'); ?></p>
                            </div>
                            <div class="rag-color-field">
                                <label><?php esc_html_e('Background', 'research-access-gate'); ?></label>
                                <input type="text" name="rag_settings[background_color]" value="<?php echo esc_attr($options['background_color']); ?>" class="rag-color-picker">
                                <p class="description"><?php esc_html_e('Modal background', 'research-access-gate'); ?></p>
                            </div>
                            <div class="rag-color-field">
                                <label><?php esc_html_e('Text', 'research-access-gate'); ?></label>
                                <input type="text" name="rag_settings[text_color]" value="<?php echo esc_attr($options['text_color']); ?>" class="rag-color-picker">
                                <p class="description"><?php esc_html_e('Main text color', 'research-access-gate'); ?></p>
                            </div>
                            <div class="rag-color-field">
                                <label><?php esc_html_e('Muted', 'research-access-gate'); ?></label>
                                <input type="text" name="rag_settings[muted_color]" value="<?php echo esc_attr($options['muted_color']); ?>" class="rag-color-picker">
                                <p class="description"><?php esc_html_e('Secondary text', 'research-access-gate'); ?></p>
                            </div>
                            <div class="rag-color-field">
                                <label><?php esc_html_e('Error', 'research-access-gate'); ?></label>
                                <input type="text" name="rag_settings[error_color]" value="<?php echo esc_attr($options['error_color']); ?>" class="rag-color-picker">
                                <p class="description"><?php esc_html_e('Error messages', 'research-access-gate'); ?></p>
                            </div>
                        </div>
                    </div>
                    
                </div>
                
                <div class="rag-admin-sidebar">
                    <!-- Preview placeholder -->
                    <div class="rag-card">
                        <h2 class="rag-card-title"><?php esc_html_e('Preview', 'research-access-gate'); ?></h2>
                        <p class="description"><?php esc_html_e('Save settings and visit your site logged out to preview the modal.', 'research-access-gate'); ?></p>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Advanced Tab -->
        <div class="rag-tab-content <?php echo $current_tab === 'advanced' ? 'is-active' : ''; ?>" id="tab-advanced">
            <div class="rag-admin-grid">
                <div class="rag-admin-main">
                    
                    <!-- MU-Plugin -->
                    <div class="rag-card">
                        <h2 class="rag-card-title"><?php esc_html_e('Cache Bypass (MU-Plugin)', 'research-access-gate'); ?></h2>
                        
                        <p class="rag-mu-description">
                            <?php esc_html_e('Install as MU-Plugin to ensure the gate loads before page caching plugins (WP Rocket, LiteSpeed, etc).', 'research-access-gate'); ?>
                        </p>
                        
                        <div class="rag-mu-status">
                            <?php if ($mu_installed): ?>
                                <span class="rag-badge rag-badge-success"><?php esc_html_e('Installed', 'research-access-gate'); ?></span>
                                <button type="button" class="button" id="rag-remove-mu"><?php esc_html_e('Remove MU-Plugin', 'research-access-gate'); ?></button>
                            <?php else: ?>
                                <span class="rag-badge rag-badge-warning"><?php esc_html_e('Not Installed', 'research-access-gate'); ?></span>
                                <button type="button" class="button button-primary" id="rag-install-mu"><?php esc_html_e('Install MU-Plugin', 'research-access-gate'); ?></button>
                            <?php endif; ?>
                        </div>
                        
                        <input type="hidden" name="rag_settings[mu_installed]" value="<?php echo $mu_installed ? 'yes' : 'no'; ?>">
                    </div>
                    
                </div>
                
                <div class="rag-admin-sidebar">
                    <!-- Info -->
                    <div class="rag-card rag-card-info">
                        <h2 class="rag-card-title"><?php esc_html_e('2026 Standards', 'research-access-gate'); ?></h2>
                        <ul>
                            <li>✅ <?php esc_html_e('REST API (replaces admin-ajax)', 'research-access-gate'); ?></li>
                            <li>✅ <?php esc_html_e('PHP 8.0+ with typed properties', 'research-access-gate'); ?></li>
                            <li>✅ <?php esc_html_e('WooCommerce HPOS compatible', 'research-access-gate'); ?></li>
                            <li>✅ <?php esc_html_e('PSR-4 namespacing', 'research-access-gate'); ?></li>
                            <li>✅ <?php esc_html_e('Transient caching', 'research-access-gate'); ?></li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
        
        <?php submit_button(__('Save Settings', 'research-access-gate')); ?>
    </form>
</div>

<script>
(function() {
    'use strict';
    
    // CAPTCHA provider selection
    const providerCards = document.querySelectorAll('.rag-provider-card');
    const captchaKeys = document.getElementById('rag-captcha-keys');
    const recaptcha3Only = document.querySelector('.rag-recaptcha3-only');
    const providerLinks = document.querySelectorAll('.rag-provider-link');
    
    providerCards.forEach(card => {
        card.addEventListener('click', function() {
            // Update selection state
            providerCards.forEach(c => c.classList.remove('is-selected'));
            this.classList.add('is-selected');
            
            const provider = this.querySelector('input').value;
            
            // Show/hide keys section
            if (provider === 'none') {
                captchaKeys.style.display = 'none';
            } else {
                captchaKeys.style.display = 'block';
            }
            
            // Show/hide reCAPTCHA v3 threshold
            if (provider === 'recaptcha3') {
                recaptcha3Only.style.display = 'table-row';
            } else {
                recaptcha3Only.style.display = 'none';
            }
            
            // Show/hide provider links
            providerLinks.forEach(link => {
                const providers = link.dataset.provider.split(',');
                if (providers.includes(provider)) {
                    link.style.display = 'inline';
                } else {
                    link.style.display = 'none';
                }
            });
        });
    });
    
    // Threshold slider value display
    const thresholdSlider = document.getElementById('rag-captcha-threshold');
    const thresholdValue = document.getElementById('rag-threshold-value');
    
    if (thresholdSlider && thresholdValue) {
        thresholdSlider.addEventListener('input', function() {
            thresholdValue.textContent = this.value;
        });
    }
    
    // Initialize provider links visibility
    const selectedProvider = document.querySelector('.rag-provider-card.is-selected input');
    if (selectedProvider) {
        const provider = selectedProvider.value;
        providerLinks.forEach(link => {
            const providers = link.dataset.provider.split(',');
            if (providers.includes(provider)) {
                link.style.display = 'inline';
            } else {
                link.style.display = 'none';
            }
        });
    }
})();
</script>
