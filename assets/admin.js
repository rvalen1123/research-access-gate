/**
 * Research Access Gate - Admin JavaScript
 * Version 2.0.0
 * 
 * Uses wp.apiFetch for REST API calls (2025 best practice)
 */

(function($) {
    'use strict';

    // Initialize when document ready
    $(document).ready(function() {
        initColorPickers();
        initMediaUploader();
        initMuPluginButtons();
        initLicenseButtons();
    });

    /**
     * Initialize WordPress color pickers
     */
    function initColorPickers() {
        $('.rag-color-picker').wpColorPicker({
            change: function(event, ui) {
                // Optional: Live preview updates
            }
        });
    }

    /**
     * Initialize WordPress media uploader for logo
     */
    function initMediaUploader() {
        var mediaUploader;
        var $logoInput = $('#rag-logo-url');
        var $uploadBtn = $('#rag-upload-logo');

        $uploadBtn.on('click', function(e) {
            e.preventDefault();

            // If uploader exists, open it
            if (mediaUploader) {
                mediaUploader.open();
                return;
            }

            // Create uploader
            mediaUploader = wp.media({
                title: 'Select Logo',
                button: { text: 'Use this image' },
                multiple: false,
                library: { type: 'image' }
            });

            // When image selected
            mediaUploader.on('select', function() {
                var attachment = mediaUploader.state().get('selection').first().toJSON();
                $logoInput.val(attachment.url);
                
                // Update preview
                var $preview = $('.rag-logo-preview');
                if ($preview.length) {
                    $preview.find('img').attr('src', attachment.url);
                } else {
                    $logoInput.after('<div class="rag-logo-preview"><img src="' + attachment.url + '" alt="Logo preview"></div>');
                }
            });

            mediaUploader.open();
        });
    }

    /**
     * Initialize MU-Plugin install/remove buttons
     * Uses WordPress REST API via wp.apiFetch
     */
    function initMuPluginButtons() {
        var $installBtn = $('#rag-install-mu');
        var $removeBtn = $('#rag-remove-mu');
        var strings = ragAdmin.strings || {};

        // Install MU-Plugin
        $installBtn.on('click', function() {
            var $btn = $(this);
            var originalText = $btn.text();
            
            $btn.prop('disabled', true).text(strings.installing || 'Installing...');

            wp.apiFetch({
                path: '/rag/v1/mu-plugin/install',
                method: 'POST'
            }).then(function(response) {
                if (response.success) {
                    // Update UI
                    $btn.closest('.rag-mu-status')
                        .html('<span class="rag-badge rag-badge-success">' + (strings.installed || 'Installed') + '</span>' +
                              '<button type="button" class="button" id="rag-remove-mu">' + (strings.removeMu || 'Remove MU-Plugin') + '</button>');
                    
                    // Re-init remove button
                    initMuPluginButtons();
                    
                    // Show success notice
                    showNotice('success', response.message || strings.success);
                }
            }).catch(function(error) {
                $btn.prop('disabled', false).text(originalText);
                showNotice('error', error.message || strings.error);
            });
        });

        // Remove MU-Plugin
        $removeBtn.on('click', function() {
            if (!confirm(strings.confirmRemove || 'Are you sure?')) {
                return;
            }

            var $btn = $(this);
            var originalText = $btn.text();
            
            $btn.prop('disabled', true).text(strings.removing || 'Removing...');

            wp.apiFetch({
                path: '/rag/v1/mu-plugin/remove',
                method: 'DELETE'
            }).then(function(response) {
                if (response.success) {
                    // Update UI
                    $btn.closest('.rag-mu-status')
                        .html('<span class="rag-badge rag-badge-warning">' + (strings.notInstalled || 'Not Installed') + '</span>' +
                              '<button type="button" class="button button-primary" id="rag-install-mu">' + (strings.installMu || 'Install MU-Plugin') + '</button>');
                    
                    // Re-init install button
                    initMuPluginButtons();
                    
                    // Show success notice
                    showNotice('success', response.message || strings.success);
                }
            }).catch(function(error) {
                $btn.prop('disabled', false).text(originalText);
                showNotice('error', error.message || strings.error);
            });
        });
    }

    /**
     * Show WordPress admin notice
     */
    function showNotice(type, message) {
        var $notice = $('<div class="notice notice-' + type + ' is-dismissible"><p>' + message + '</p></div>');
        
        // Remove existing notices
        $('.rag-admin .notice').remove();
        
        // Add new notice after h1
        $('.rag-admin h1').after($notice);
        
        // Make dismissible
        if (wp.a11y && wp.a11y.speak) {
            wp.a11y.speak(message);
        }
        
        // Auto-dismiss after 5s
        setTimeout(function() {
            $notice.fadeOut(300, function() {
                $(this).remove();
            });
        }, 5000);
    }

    /**
     * Initialize License activation/deactivation buttons
     */
    function initLicenseButtons() {
        var $activateBtn = $('#rag-activate-license');
        var $deactivateBtn = $('#rag-deactivate-license');
        var $keyInput = $('#rag-license-key');
        var $emailInput = $('#rag-license-email');
        var $errorEl = $('#rag-license-error');

        // Activate license
        $activateBtn.on('click', function() {
            var $btn = $(this);
            var key = $keyInput.val().trim().toUpperCase();
            var email = $emailInput.val().trim();

            if (!key) {
                $errorEl.text('Please enter a license key.');
                return;
            }

            $btn.prop('disabled', true).text('Activating...');
            $errorEl.text('');

            wp.apiFetch({
                path: '/rag/v1/license/activate',
                method: 'POST',
                data: {
                    license_key: key,
                    email: email
                }
            }).then(function(response) {
                if (response.success) {
                    showNotice('success', response.message);
                    // Reload page to show updated license status
                    setTimeout(function() {
                        location.reload();
                    }, 1000);
                }
            }).catch(function(error) {
                $btn.prop('disabled', false).text('Activate License');
                $errorEl.text(error.message || 'Activation failed.');
            });
        });

        // Deactivate license
        $deactivateBtn.on('click', function() {
            if (!confirm('Are you sure you want to deactivate this license?')) {
                return;
            }

            var $btn = $(this);
            $btn.prop('disabled', true).text('Deactivating...');

            wp.apiFetch({
                path: '/rag/v1/license/deactivate',
                method: 'DELETE'
            }).then(function(response) {
                if (response.success) {
                    showNotice('success', response.message);
                    setTimeout(function() {
                        location.reload();
                    }, 1000);
                }
            }).catch(function(error) {
                $btn.prop('disabled', false).text('Deactivate');
                showNotice('error', error.message || 'Deactivation failed.');
            });
        });

        // Format license key as user types
        $keyInput.on('input', function() {
            var val = $(this).val().toUpperCase().replace(/[^A-Z0-9]/g, '');
            var formatted = '';
            
            // Add prefix
            if (val.length > 0) {
                formatted = val.substring(0, 3);
            }
            if (val.length > 3) {
                formatted += '-' + val.substring(3, 7);
            }
            if (val.length > 7) {
                formatted += '-' + val.substring(7, 11);
            }
            if (val.length > 11) {
                formatted += '-' + val.substring(11, 15);
            }
            if (val.length > 15) {
                formatted += '-' + val.substring(15, 19);
            }
            
            $(this).val(formatted);
        });
    }

})(jQuery);
