/**
 * WP SiteAdvisor Pro Admin JavaScript
 */

jQuery(document).ready(function($) {
    'use strict';

    // Initialize the Pro admin interface
    WSA_Pro_Admin.init($);
});

var WSA_Pro_Admin = {
    
    /**
     * Initialize admin functionality
     */
    init: function($) {
        this.$ = $; // Store jQuery reference
        this.initLicenseManagement();
        this.initReportGenerator();
        this.initNotifications();
        this.initTooltips();
        this.initDashboardWidget();
    },

    /**
     * License Management Functions
     */
    initLicenseManagement: function() {
        var self = this;
        var $ = this.$; // Use stored jQuery reference

        // License activation form
        $('#wsa-pro-license-form').on('submit', function(e) {
            e.preventDefault();
            self.activateLicense();
        });

        // License activation button
        $('#wsa-pro-activate-license').on('click', function(e) {
            e.preventDefault();
            self.activateLicense();
        });

        // License deactivation
        $('#wsa-pro-deactivate-license').on('click', function(e) {
            e.preventDefault();
            if (confirm(wsa_pro_admin.deactivate_confirm)) {
                self.deactivateLicense();
            }
        });

        // License validation
        $('#wsa-pro-check-license').on('click', function(e) {
            e.preventDefault();
            self.validateLicense();
        });

        // Check license status on page load
        if ($('#wsa_pro_license_key').length && $('#wsa_pro_license_key').val()) {
            setTimeout(function() {
                self.checkLicenseStatus();
            }, 1000);
        }
    },

    /**
     * Activate license
     */
    activateLicense: function() {
        var $ = this.$; // Use stored jQuery reference
        var $form = $('#wsa-pro-license-form');
        var $button = $('#wsa-pro-activate-license');
        var $status = $('.wsa-license-status');
        
        var licenseKey = $('#wsa_pro_license_key').val();
        var email = ''; // No email field in current form
        
        // Safely trim the license key
        if (licenseKey) {
            licenseKey = licenseKey.trim();
        }

        if (!licenseKey) {
            this.showNotice('error', wsa_pro_admin.license_key_required);
            return;
        }

        // Show loading state
        $button.prop('disabled', true).text(wsa_pro_admin.activating);
        $form.addClass('wsa-loading');

        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'wsa_pro_activate_license',
                license_key: licenseKey,
                email: email,
                nonce: wsa_pro_admin.nonce
            },
            success: function(response) {
                if (response.success) {
                    // Update license status display
                    $status.removeClass('inactive expired').addClass('active');
                    $status.find('.wsa-status-icon').removeClass('inactive expired').addClass('active').html('✓');
                    $status.find('.wsa-status-details h3').text(wsa_pro_admin.license_active);
                    $status.find('.wsa-status-details p').text(response.data.expires_text || wsa_pro_admin.license_valid);
                    
                    WSA_Pro_Admin.showNotice('success', response.data.message);
                    WSA_Pro_Admin.updateLicenseActions('active');
                    
                    // Refresh page after short delay
                    setTimeout(function() {
                        window.location.reload();
                    }, 1500);
                } else {
                    WSA_Pro_Admin.showNotice('error', response.data.message || response.data);
                }
            },
            error: function(xhr, status, error) {
                WSA_Pro_Admin.showNotice('error', wsa_pro_admin.activation_error + ': ' + error);
            },
            complete: function() {
                $button.prop('disabled', false).text(wsa_pro_admin.activate_license);
                $form.removeClass('wsa-loading');
            }
        });
    },

    /**
     * Deactivate license
     */
    deactivateLicense: function() {
        var $ = this.$; // Use stored jQuery reference
        var $button = $('#wsa-pro-deactivate-license');
        var $status = $('.wsa-license-status');

        $button.prop('disabled', true).text(wsa_pro_admin.deactivating);

        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'wsa_pro_deactivate_license',
                nonce: wsa_pro_admin.nonce
            },
            success: function(response) {
                if (response.success) {
                    // Update license status display
                    $status.removeClass('active expired').addClass('inactive');
                    $status.find('.wsa-status-icon').removeClass('active expired').addClass('inactive').html('✗');
                    $status.find('.wsa-status-details h3').text(wsa_pro_admin.license_inactive);
                    $status.find('.wsa-status-details p').text(wsa_pro_admin.enter_license_key);
                    
                    WSA_Pro_Admin.showNotice('success', response.data.message);
                    WSA_Pro_Admin.updateLicenseActions('inactive');
                    
                    // Clear form fields
                    $('#wsa_pro_license_key').val('');
                } else {
                    WSA_Pro_Admin.showNotice('error', response.data.message || response.data);
                }
            },
            error: function(xhr, status, error) {
                WSA_Pro_Admin.showNotice('error', wsa_pro_admin.deactivation_error + ': ' + error);
            },
            complete: function() {
                $button.prop('disabled', false).text(wsa_pro_admin.deactivate_license);
            }
        });
    },

    /**
     * Validate license
     */
    validateLicense: function() {
        var $ = this.$; // Use stored jQuery reference
        var $button = $('#wsa-pro-check-license');

        $button.prop('disabled', true).text(wsa_pro_admin.validating);

        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'wsa_pro_validate_license',
                nonce: wsa_pro_admin.nonce
            },
            success: function(response) {
                if (response.success) {
                    WSA_Pro_Admin.showNotice('success', response.data.message);
                    if (response.data.status) {
                        WSA_Pro_Admin.updateLicenseStatus(response.data.status, response.data);
                    }
                } else {
                    WSA_Pro_Admin.showNotice('error', response.data.message || response.data);
                }
            },
            error: function(xhr, status, error) {
                WSA_Pro_Admin.showNotice('error', wsa_pro_admin.validation_error + ': ' + error);
            },
            complete: function() {
                $button.prop('disabled', false).text(wsa_pro_admin.validate_license);
            }
        });
    },

    /**
     * Check license status
     */
    checkLicenseStatus: function() {
        var $ = this.$; // Use stored jQuery reference
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'wsa_pro_check_license',
                nonce: wsa_pro_admin.nonce
            },
            success: function(response) {
                if (response.success && response.data.status) {
                    WSA_Pro_Admin.updateLicenseStatus(response.data.status, response.data);
                }
            }
        });
    },

    /**
     * Update license status display
     */
    updateLicenseStatus: function(status, data) {
        var $ = this.$; // Use stored jQuery reference
        var $status = $('.wsa-license-status');
        var $icon = $status.find('.wsa-status-icon');
        var $title = $status.find('.wsa-status-details h3');
        var $desc = $status.find('.wsa-status-details p');

        $status.removeClass('active inactive expired').addClass(status);

        switch (status) {
            case 'active':
                $icon.removeClass('inactive expired').addClass('active').html('✓');
                $title.text(wsa_pro_admin.license_active);
                $desc.text(data.expires_text || wsa_pro_admin.license_valid);
                break;
            case 'expired':
                $icon.removeClass('active inactive').addClass('expired').html('⚠');
                $title.text(wsa_pro_admin.license_expired);
                $desc.text(data.expires_text || wsa_pro_admin.license_expired_desc);
                break;
            default:
                $icon.removeClass('active expired').addClass('inactive').html('✗');
                $title.text(wsa_pro_admin.license_inactive);
                $desc.text(wsa_pro_admin.enter_license_key);
        }

        this.updateLicenseActions(status);
    },

    /**
     * Update license action buttons
     */
    updateLicenseActions: function(status) {
        var $ = this.$; // Use stored jQuery reference
        var $actions = $('.wsa-license-actions');
        
        if (status === 'active') {
            $actions.find('.wsa-activate-license').hide();
            $actions.find('.wsa-deactivate-license, .wsa-validate-license').show();
        } else {
            $actions.find('.wsa-activate-license').show();
            $actions.find('.wsa-deactivate-license, .wsa-validate-license').hide();
        }
    },

    /**
     * Report Generator Functions
     */
    initReportGenerator: function() {
        var self = this;
        var $ = this.$; // Use stored jQuery reference

        // Generate report button
        $('.wsa-generate-report').on('click', function(e) {
            e.preventDefault();
            self.generateReport();
        });

        // Section toggles
        $('.wsa-section-toggle').on('change', function() {
            self.updateReportPreview();
        });

        // Branding options
        $('.wsa-branding-input').on('input change', function() {
            self.updateBrandingPreview();
        });
    },

    /**
     * Generate report
     */
    generateReport: function() {
        var $ = this.$; // Use stored jQuery reference
        var $button = $('.wsa-generate-report');
        var $progress = $('.wsa-progress');
        var $progressBar = $('.wsa-progress-bar');

        // Collect form data
        var config = {
            format: $('input[name="report_format"]:checked').val() || 'pdf',
            sections: [],
            branding: {
                logo: $('#report-logo').val(),
                company_name: $('#company-name').val(),
                company_url: $('#company-url').val(),
                primary_color: $('#primary-color').val(),
                secondary_color: $('#secondary-color').val()
            },
            client_info: {
                name: $('#client-name').val(),
                email: $('#client-email').val()
            }
        };

        // Collect selected sections
        $('.wsa-section-toggle:checked').each(function() {
            config.sections.push($(this).val());
        });

        // Show loading state
        $button.prop('disabled', true).text(wsa_pro_admin.generating_report);
        $progress.show();
        $progressBar.css('width', '0%');

        // Animate progress bar
        var progress = 0;
        var progressInterval = setInterval(function() {
            progress += Math.random() * 15;
            if (progress > 90) progress = 90;
            $progressBar.css('width', progress + '%');
        }, 200);

        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'wsa_pro_generate_report',
                config: JSON.stringify(config),
                nonce: wsa_pro_admin.nonce
            },
            success: function(response) {
                clearInterval(progressInterval);
                $progressBar.css('width', '100%');
                
                setTimeout(function() {
                    if (response.success) {
                        WSA_Pro_Admin.showNotice('success', wsa_pro_admin.report_generated);
                        
                        // Show download link
                        var downloadHtml = '<div class="wsa-report-download wsa-fade-in">' +
                            '<h4>' + wsa_pro_admin.report_ready + '</h4>' +
                            '<p><a href="' + response.data.file_url + '" target="_blank" class="wsa-btn">' +
                            wsa_pro_admin.download_report + '</a></p>' +
                            '</div>';
                        
                        $('.wsa-report-generator').append(downloadHtml);
                    } else {
                        if (response.data && response.data.license_required) {
                            WSA_Pro_Admin.showNotice('error', response.data.message + ' <a href="#license">' + wsa_pro_admin.manage_license + '</a>');
                        } else {
                            WSA_Pro_Admin.showNotice('error', response.data.message || response.data);
                        }
                    }
                    
                    $progress.hide();
                }, 500);
            },
            error: function(xhr, status, error) {
                clearInterval(progressInterval);
                WSA_Pro_Admin.showNotice('error', wsa_pro_admin.report_error + ': ' + error);
                $progress.hide();
            },
            complete: function() {
                $button.prop('disabled', false).text(wsa_pro_admin.generate_report);
            }
        });
    },

    /**
     * Update report preview
     */
    updateReportPreview: function() {
        var $ = this.$; // Use stored jQuery reference
        // Update section count
        var selectedSections = $('.wsa-section-toggle:checked').length;
        $('.wsa-section-count').text(selectedSections);
        
        // Show/hide related options
        if ($('#section-branding').is(':checked')) {
            $('.wsa-branding-options').slideDown();
        } else {
            $('.wsa-branding-options').slideUp();
        }
    },

    /**
     * Update branding preview
     */
    updateBrandingPreview: function() {
        var $ = this.$; // Use stored jQuery reference
        var primaryColor = $('#primary-color').val();
        var secondaryColor = $('#secondary-color').val();
        
        // Update color previews
        $('.wsa-primary-preview').css('background-color', primaryColor);
        $('.wsa-secondary-preview').css('background-color', secondaryColor);
        
        // Update company name preview
        var companyName = $('#company-name').val();
        if (companyName) {
            $('.wsa-company-preview').text(companyName);
        }
    },

    /**
     * Notification Functions
     */
    initNotifications: function() {
        var $ = this.$; // Use stored jQuery reference
        // Dismissible notices
        $(document).on('click', '.wsa-notice-dismiss', function() {
            $(this).closest('.wsa-notice').fadeOut();
        });

        // Auto-hide success notices
        setTimeout(function() {
            $('.wsa-notice.success').fadeOut();
        }, 5000);
    },

    /**
     * Show admin notice
     */
    showNotice: function(type, message) {
        var $ = this.$; // Use stored jQuery reference
        var $notice = $('<div class="wsa-notice ' + type + ' wsa-fade-in">' +
            '<p>' + message + '</p>' +
            '<button type="button" class="wsa-notice-dismiss">&times;</button>' +
        '</div>');
        
        // Remove existing notices of the same type
        $('.wsa-notice.' + type).remove();
        
        // Add new notice
        if ($('.wsa-notices').length) {
            $('.wsa-notices').append($notice);
        } else {
            $notice.insertAfter('h1').first();
        }
        
        // Auto-hide success notices
        if (type === 'success') {
            setTimeout(function() {
                $notice.fadeOut();
            }, 5000);
        }
    },

    /**
     * Tooltip Functions
     */
    initTooltips: function() {
        var $ = this.$; // Use stored jQuery reference
        // Initialize tooltips
        $('.wsa-tooltip').hover(
            function() {
                $(this).find('.wsa-tooltiptext').stop(true, true).fadeIn(200);
            },
            function() {
                $(this).find('.wsa-tooltiptext').stop(true, true).fadeOut(200);
            }
        );
    },

    /**
     * Dashboard Widget Functions
     */
    initDashboardWidget: function() {
        var $ = this.$; // Use stored jQuery reference
        // Refresh widget data
        $('.wsa-refresh-widget').on('click', function(e) {
            e.preventDefault();
            
            var $widget = $(this).closest('.wsa-pro-dashboard-widget');
            $widget.addClass('wsa-loading');
            
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'wsa_pro_refresh_dashboard',
                    nonce: wsa_pro_admin.nonce
                },
                success: function(response) {
                    if (response.success) {
                        $widget.html(response.data.html);
                    }
                },
                complete: function() {
                    $widget.removeClass('wsa-loading');
                }
            });
        });
    },

    /**
     * Utility Functions
     */
    
    /**
     * Format number with commas
     */
    numberFormat: function(num) {
        return num.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ",");
    },

    /**
     * Debounce function
     */
    debounce: function(func, wait, immediate) {
        var timeout;
        return function() {
            var context = this, args = arguments;
            var later = function() {
                timeout = null;
                if (!immediate) func.apply(context, args);
            };
            var callNow = immediate && !timeout;
            clearTimeout(timeout);
            timeout = setTimeout(later, wait);
            if (callNow) func.apply(context, args);
        };
    },

    /**
     * Get URL parameter
     */
    getUrlParameter: function(name) {
        name = name.replace(/[\[]/, '\\[').replace(/[\]]/, '\\]');
        var regex = new RegExp('[\\?&]' + name + '=([^&#]*)');
        var results = regex.exec(location.search);
        return results === null ? '' : decodeURIComponent(results[1].replace(/\+/g, ' '));
    }
};

// Form validation helpers
jQuery(function($) {
    
    // License key formatting - only for proper license keys
    $('#wsa_pro_license_key').on('input', function() {
        var value = $(this).val();
        
        // Only format if it looks like a license key (contains dashes or is longer than 10 chars)
        // Don't format short test keys like "tet"
        if (value.indexOf('-') !== -1 || value.length > 10) {
            value = value.replace(/[^a-zA-Z0-9-]/g, '').toUpperCase();
            $(this).val(value);
        }
    });
    
    // Color picker initialization
    if ($.fn.wpColorPicker) {
        $('.wsa-color-picker input[type="color"]').wpColorPicker({
            change: function(event, ui) {
                WSA_Pro_Admin.updateBrandingPreview();
            }
        });
    }
});

// Grace period countdown
if (typeof wsa_pro_admin.grace_period_end !== 'undefined' && wsa_pro_admin.grace_period_end) {
    
    function updateGracePeriodCountdown() {
        var now = new Date().getTime();
        var end = new Date(wsa_pro_admin.grace_period_end).getTime();
        var distance = end - now;
        
        if (distance > 0) {
            var hours = Math.floor(distance / (1000 * 60 * 60));
            var minutes = Math.floor((distance % (1000 * 60 * 60)) / (1000 * 60));
            
            $('.wsa-grace-countdown').text(hours + 'h ' + minutes + 'm');
        } else {
            $('.wsa-grace-period-notice').fadeOut();
        }
    }
    
    // Update countdown every minute
    setInterval(updateGracePeriodCountdown, 60000);
    updateGracePeriodCountdown(); // Initial update
}
