/**
 * AI Dashboard JavaScript
 * 
 * Handles interactions on the AI Features Dashboard
 */

jQuery(document).ready(function($) {
    'use strict';
    
    var wsaAIDashboard = {
        
        init: function() {
            this.bindEvents();
            this.loadInitialData();
        },
        
        bindEvents: function() {
            // Debug: Check if buttons exist
            
            // Bind click events for feature buttons using explicit event delegation
            $(document).on('click', '.wsa-dashboard-card .button-primary', this.handleFeatureAction);
            
            // Also bind to specific button IDs as backup
            $('#run-optimizer, #analyze-content, #generate-predictions, #run-pagespeed, #generate-report').on('click', this.handleFeatureAction);
            
            // Auto-refresh status every 30 seconds
            setInterval(this.refreshStatuses, 30000);
        },
        
        handleFeatureAction: function(e) {
            e.preventDefault();
            
            var $button = $(this);
            var feature = $button.data('feature');
            
            // Debug logging
            
            if (!feature) {
                return;
            }
            
            if (!window.wsaAIDashboard) {
                return;
            }
            
            // Disable button and show loading
            $button.prop('disabled', true).text(window.wsaAIDashboard.strings.analyzing);
            
            // Make AJAX request
            
            $.ajax({
                url: window.wsaAIDashboard.ajaxurl,
                type: 'POST',
                data: {
                    action: 'wsa_run_ai_analysis',
                    feature: feature,
                    nonce: window.wsaAIDashboard.nonce
                },
                success: function(response) {
                    if (response.success) {
                        wsaAIDashboard.showSuccess($button, response.data);
                    } else {
                        wsaAIDashboard.showError($button, response.data || window.wsaAIDashboard.strings.error);
                    }
                },
                error: function(xhr, status, error) {
                    wsaAIDashboard.showError($button, window.wsaAIDashboard.strings.error + ': ' + error);
                },
                complete: function() {
                    // Re-enable button after 2 seconds
                    setTimeout(function() {
                        wsaAIDashboard.resetButton($button);
                    }, 2000);
                }
            });
        },
        
        showSuccess: function($button, data) {
            $button.removeClass('button-primary').addClass('button-secondary')
                   .text(window.wsaAIDashboard.strings.complete);
            
            // Special handling for test button
            if ($button.attr('id') === 'test-ajax') {
                $('#test-results').html('<div style="color: green; font-weight: bold;">✓ ' + JSON.stringify(data) + '</div>');
            } else {
                // Show notification for other features
                this.showNotification('Analysis completed successfully!', 'success');
                
                // Refresh page data
                this.refreshStatuses();
            }
        },
        
        showError: function($button, message) {
            $button.removeClass('button-primary').addClass('button-secondary')
                   .text('Error');
            
            // Special handling for test button
            if ($button.attr('id') === 'test-ajax') {
                $('#test-results').html('<div style="color: red; font-weight: bold;">✗ Error: ' + message + '</div>');
            } else {
                // Show notification for other features
                this.showNotification(message, 'error');
            }
        },
        
        resetButton: function($button) {
            var originalText = $button.data('original-text') || 'Run Analysis';
            
            $button.prop('disabled', false)
                   .removeClass('button-secondary')
                   .addClass('button-primary')
                   .text(originalText);
        },
        
        showNotification: function(message, type) {
            var $notification = $('<div class="notice notice-' + type + ' is-dismissible"><p>' + message + '</p></div>');
            
            $('.wsa-ai-dashboard h1').after($notification);
            
            // Auto-dismiss after 5 seconds
            setTimeout(function() {
                $notification.fadeOut(function() {
                    $(this).remove();
                });
            }, 5000);
        },
        
        refreshStatuses: function() {
            // Update status indicators
            $.ajax({
                url: window.wsaAIDashboard.ajaxurl,
                type: 'POST',
                data: {
                    action: 'wsa_get_ai_status',
                    nonce: window.wsaAIDashboard.nonce
                },
                success: function(response) {
                    if (response.success && response.data) {
                        wsaAIDashboard.updateStatuses(response.data);
                    }
                }
            });
        },
        
        updateStatuses: function(data) {
            // Update each status indicator
            Object.keys(data.statuses || {}).forEach(function(feature) {
                var $indicator = $('#' + feature + '-status');
                if ($indicator.length) {
                    $indicator.html(data.statuses[feature]);
                }
            });
            
            // Update quick stats
            Object.keys(data.stats || {}).forEach(function(stat) {
                var $stat = $('#' + stat);
                if ($stat.length) {
                    $stat.text(data.stats[stat]);
                }
            });
        },
        
        loadInitialData: function() {
            // Store original button texts
            $('.wsa-dashboard-card .button-primary').each(function() {
                $(this).data('original-text', $(this).text());
            });
            
            // Load initial status
            this.refreshStatuses();
        }
    };
    
    // Debug: Check if wsaAIDashboard object is available
    
    if (window.wsaAIDashboard && window.wsaAIDashboard.debug) {
    }
    
    // Initialize dashboard
    wsaAIDashboard.init();
});
