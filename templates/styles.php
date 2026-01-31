<?php
/**
 * Dynamic Styles Template
 * 
 * Outputs inline CSS with theme color variables.
 * For CSP-strict sites, consider using the external asset option.
 * 
 * @var array $options
 */

defined('ABSPATH') || exit;

use PremierBioLabs\ResearchAccessGate\Helpers;

// Extract colors
$primary    = esc_attr($options['primary_color'] ?? '#8a2be2');
$secondary  = esc_attr($options['secondary_color'] ?? '#6a1fb0');
$background = esc_attr($options['background_color'] ?? '#0d0d0d');
$text       = esc_attr($options['text_color'] ?? '#ffffff');
$muted      = esc_attr($options['muted_color'] ?? '#777777');
$error      = esc_attr($options['error_color'] ?? '#e74c3c');

// RGB values for rgba()
$primary_rgb = Helpers::hex_to_rgb($primary);
$background_rgb = Helpers::hex_to_rgb($background);
?>
<style id="rag-dynamic-styles">
/* Research Access Gate v2.0 - Dynamic Styles */
:root {
    --rag-primary: <?php echo $primary; ?>;
    --rag-primary-rgb: <?php echo $primary_rgb; ?>;
    --rag-secondary: <?php echo $secondary; ?>;
    --rag-background: <?php echo $background; ?>;
    --rag-background-rgb: <?php echo $background_rgb; ?>;
    --rag-text: <?php echo $text; ?>;
    --rag-muted: <?php echo $muted; ?>;
    --rag-error: <?php echo $error; ?>;
}

/* Body lock */
body.rag-locked {
    overflow: hidden !important;
    position: fixed !important;
    width: 100% !important;
    height: 100% !important;
}

/* Overlay */
.rag-overlay {
    position: fixed;
    inset: 0;
    z-index: 999999;
    display: flex;
    align-items: center;
    justify-content: center;
    background: rgba(var(--rag-background-rgb), 0.97);
    backdrop-filter: blur(8px);
    -webkit-backdrop-filter: blur(8px);
    padding: 1rem;
}

/* Modal */
.rag-modal {
    width: 100%;
    max-width: 480px;
    max-height: 90vh;
    overflow-y: auto;
    background: var(--rag-background);
    border: 1px solid rgba(255,255,255,0.08);
    border-radius: 16px;
    padding: 2rem;
    color: var(--rag-text);
    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, sans-serif;
}

/* Custom scrollbar */
.rag-modal::-webkit-scrollbar {
    width: 6px;
}
.rag-modal::-webkit-scrollbar-track {
    background: rgba(255,255,255,0.05);
    border-radius: 3px;
}
.rag-modal::-webkit-scrollbar-thumb {
    background: var(--rag-primary);
    border-radius: 3px;
}

/* Header */
.rag-modal-header {
    text-align: center;
    margin-bottom: 1.5rem;
}
.rag-logo {
    margin-bottom: 1rem;
}
.rag-logo img {
    max-width: 200px;
    max-height: 50px;
    height: auto;
}
.rag-modal-header h2 {
    font-size: 1.5rem;
    font-weight: 700;
    margin: 0 0 0.5rem;
    color: var(--rag-text);
}
.rag-modal-header p {
    font-size: 0.875rem;
    color: var(--rag-muted);
    margin: 0;
    line-height: 1.5;
}

/* Tabs */
.rag-tabs {
    display: flex;
    gap: 0.5rem;
    margin-bottom: 1.5rem;
    padding: 4px;
    background: rgba(255,255,255,0.05);
    border-radius: 10px;
}
.rag-tab {
    flex: 1;
    padding: 0.75rem 1rem;
    background: transparent;
    border: none;
    border-radius: 8px;
    color: var(--rag-muted);
    font-size: 0.875rem;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.2s ease;
}
.rag-tab:hover {
    color: var(--rag-text);
}
.rag-tab.active {
    background: linear-gradient(135deg, var(--rag-primary), var(--rag-secondary));
    color: #fff;
    box-shadow: 0 4px 12px rgba(var(--rag-primary-rgb), 0.3);
}

/* Forms */
.rag-form {
    display: none;
}
.rag-form.active {
    display: block;
}

/* Fields */
.rag-field {
    margin-bottom: 1rem;
}
.rag-field label {
    display: block;
    font-size: 0.8125rem;
    font-weight: 500;
    color: var(--rag-text);
    margin-bottom: 0.375rem;
}
.rag-field .req {
    color: var(--rag-error);
}
.rag-field input,
.rag-field select {
    width: 100%;
    padding: 0.75rem 1rem;
    background: rgba(255,255,255,0.05);
    border: 1px solid rgba(255,255,255,0.1);
    border-radius: 8px;
    color: var(--rag-text);
    font-size: 0.9375rem;
    transition: border-color 0.2s, box-shadow 0.2s;
    color-scheme: dark;
}
.rag-field input:focus,
.rag-field select:focus {
    outline: none;
    border-color: var(--rag-primary);
    box-shadow: 0 0 0 3px rgba(var(--rag-primary-rgb), 0.15);
}
.rag-field input::placeholder {
    color: var(--rag-muted);
}
.rag-field select {
    cursor: pointer;
    appearance: none;
    background-color: #1a1a1a;
    background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 12 12'%3E%3Cpath fill='%23777' d='M6 8L1 3h10z'/%3E%3C/svg%3E");
    background-repeat: no-repeat;
    background-position: right 1rem center;
    padding-right: 2.5rem;
}

/* Dropdown options - ensure visibility on all browsers/themes */
.rag-field select option {
    background-color: #1a1a1a;
    color: #ffffff;
    padding: 10px;
}
.rag-field select option:hover,
.rag-field select option:focus,
.rag-field select option:checked {
    background-color: var(--rag-primary);
    background: linear-gradient(135deg, var(--rag-primary), var(--rag-secondary));
    color: #ffffff;
}
/* Disabled/placeholder option */
.rag-field select option[disabled] {
    color: #888888;
}

/* Custom Select Dropdown - Full control over styling */
.rag-custom-select {
    position: relative;
    width: 100%;
}
.rag-select-trigger {
    display: flex;
    align-items: center;
    justify-content: space-between;
    width: 100%;
    padding: 0.75rem 1rem;
    background: #1a1a1a;
    border: 1px solid rgba(255,255,255,0.1);
    border-radius: 8px;
    color: var(--rag-text);
    font-size: 0.9375rem;
    cursor: pointer;
    transition: border-color 0.2s, box-shadow 0.2s;
}
.rag-select-trigger:hover {
    border-color: rgba(255,255,255,0.2);
}
.rag-select-trigger:focus {
    outline: none;
    border-color: var(--rag-primary);
    box-shadow: 0 0 0 3px rgba(var(--rag-primary-rgb), 0.15);
}
.rag-select-value {
    flex: 1;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}
.rag-select-value.is-placeholder {
    color: var(--rag-muted);
}
.rag-select-arrow {
    flex-shrink: 0;
    margin-left: 0.5rem;
    color: var(--rag-muted);
    transition: transform 0.2s;
}
.rag-custom-select.is-open .rag-select-arrow {
    transform: rotate(180deg);
}
.rag-select-options {
    display: none;
    position: absolute;
    top: calc(100% + 4px);
    left: 0;
    right: 0;
    max-height: 200px;
    overflow-y: auto;
    background: #1a1a1a;
    border: 1px solid rgba(255,255,255,0.15);
    border-radius: 8px;
    padding: 0.5rem 0;
    margin: 0;
    list-style: none;
    z-index: 100;
    box-shadow: 0 8px 24px rgba(0,0,0,0.4);
}
.rag-custom-select.is-open .rag-select-options {
    display: block;
}
.rag-select-options li {
    padding: 0.625rem 1rem;
    color: #ffffff;
    cursor: pointer;
    transition: background 0.15s;
    font-size: 0.9375rem;
}
.rag-select-options li:hover,
.rag-select-options li:focus {
    background: rgba(var(--rag-primary-rgb), 0.3);
}
.rag-select-options li.is-selected {
    background: var(--rag-primary);
    color: #ffffff;
}
.rag-select-options::-webkit-scrollbar {
    width: 6px;
}
.rag-select-options::-webkit-scrollbar-track {
    background: rgba(255,255,255,0.05);
}
.rag-select-options::-webkit-scrollbar-thumb {
    background: var(--rag-primary);
    border-radius: 3px;
}

/* Field row (2-col) */
.rag-field-row {
    display: flex;
    gap: 1rem;
}
.rag-field-half {
    flex: 1;
}

/* Password wrapper */
.rag-password-wrap {
    position: relative;
}
.rag-password-wrap input {
    padding-right: 3rem;
}
.rag-toggle-pw {
    position: absolute;
    right: 0.75rem;
    top: 50%;
    transform: translateY(-50%);
    background: none;
    border: none;
    color: var(--rag-muted);
    cursor: pointer;
    padding: 0.25rem;
    display: flex;
    align-items: center;
    justify-content: center;
}
.rag-toggle-pw:hover {
    color: var(--rag-text);
}

/* Terms box */
.rag-terms-box {
    margin: 1.25rem 0;
    border: 1px solid rgba(255,255,255,0.1);
    border-radius: 8px;
    overflow: hidden;
}
.rag-terms-box-header {
    background: rgba(255,255,255,0.05);
    padding: 0.625rem 0.875rem;
    font-size: 0.8125rem;
    font-weight: 600;
    color: var(--rag-text);
    border-bottom: 1px solid rgba(255,255,255,0.1);
}
.rag-terms-box-content {
    padding: 0.875rem;
    max-height: 180px;
    overflow-y: auto;
    font-size: 0.8125rem;
    line-height: 1.6;
    color: var(--rag-muted);
}
.rag-terms-box-content strong {
    color: var(--rag-text);
}

/* Terms checkbox */
.rag-terms-checkbox {
    margin: 1rem 0;
}
.rag-terms-checkbox label {
    display: flex;
    align-items: flex-start;
    gap: 0.75rem;
    cursor: pointer;
    font-size: 0.8125rem;
    line-height: 1.5;
}
.rag-terms-checkbox input[type="checkbox"] {
    width: auto;
    flex-shrink: 0;
    margin-top: 0.125rem;
    accent-color: var(--rag-primary);
    cursor: pointer;
}
.rag-terms-text {
    color: var(--rag-muted);
}
.rag-terms-text a {
    color: var(--rag-primary);
    text-decoration: underline;
}
.rag-terms-text a:hover {
    color: var(--rag-text);
}

/* Error message */
.rag-error {
    color: var(--rag-error);
    font-size: 0.8125rem;
    margin-bottom: 1rem;
    min-height: 1.25rem;
}

/* Button */
.rag-btn {
    width: 100%;
    padding: 0.875rem 1.5rem;
    border: none;
    border-radius: 8px;
    font-size: 0.9375rem;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.2s ease;
}
.rag-btn-primary {
    background: linear-gradient(135deg, var(--rag-primary), var(--rag-secondary));
    color: #fff;
    box-shadow: 0 4px 12px rgba(var(--rag-primary-rgb), 0.25);
}
.rag-btn-primary:hover:not(:disabled) {
    transform: translateY(-1px);
    box-shadow: 0 6px 16px rgba(var(--rag-primary-rgb), 0.35);
}
.rag-btn-primary:disabled {
    opacity: 0.7;
    cursor: not-allowed;
}

/* Forgot password */
.rag-forgot {
    text-align: center;
    margin-top: 1rem;
}
.rag-forgot a {
    color: var(--rag-muted);
    font-size: 0.8125rem;
    text-decoration: none;
}
.rag-forgot a:hover {
    color: var(--rag-primary);
}

/* Footer */
.rag-modal-footer {
    text-align: center;
    margin-top: 1.5rem;
    padding-top: 1.5rem;
    border-top: 1px solid rgba(255,255,255,0.08);
}
.rag-modal-footer a {
    color: var(--rag-muted);
    font-size: 0.8125rem;
    text-decoration: none;
}
.rag-modal-footer a:hover {
    color: var(--rag-primary);
}

/* Mobile responsive */
@media (max-width: 480px) {
    .rag-modal {
        padding: 1.5rem;
        border-radius: 12px;
    }
    .rag-field-row {
        flex-direction: column;
        gap: 0;
    }
    .rag-modal-header h2 {
        font-size: 1.25rem;
    }
}

/* Reduced motion */
@media (prefers-reduced-motion: reduce) {
    .rag-tab,
    .rag-btn,
    .rag-field input,
    .rag-field select {
        transition: none;
    }
}
</style>
<?php
