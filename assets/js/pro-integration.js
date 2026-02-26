/**
 * WP SiteAdvisor Pro Integration JavaScript
 * Handles Pro features integration with the main dashboard
 */

(function($) {
    'use strict';
    
    class WSAProIntegration {
        constructor() {
            this.init();
        }
        
        init() {
            this.bindEvents();
            this.initFeatures();
            this.initTabBindings();
            this.updateLocalhostMessage();
            this.initPersistedData();
        }
        
        initTabBindings() {
            // Re-bind events when tabs are switched to Pro tabs or tabs with integrated Pro features
            $(document).on('wsa:tabChanged', (event, tabId) => {
                if (['overview', 'system', 'ai-conflicts', 'pro-reports', 'ai-assistant'].includes(tabId)) {
                    // Small delay to ensure content is loaded
                    setTimeout(() => {
                        this.bindEvents();
                    }, 100);
                }
            });
        }
        
        bindEvents() {
            // Use event delegation for dynamically added content
            $(document).off('click.wsa-pro').on('click.wsa-pro', '#wsa-run-vulnerability-scan', (e) => {
                e.preventDefault();
                this.runVulnerabilityScann();
            });
            
            $(document).off('click.wsa-pro-vuln-report').on('click.wsa-pro-vuln-report', '#wsa-view-vulnerability-report', (e) => {
                e.preventDefault();
                this.viewVulnerabilityReport();
            });
            
            // Performance Analysis
            $(document).off('click.wsa-pro-perf').on('click.wsa-pro-perf', '#wsa-run-pagespeed-analysis', (e) => {
                e.preventDefault();
                this.runPageSpeedAnalysis();
            });
            
            $(document).off('click.wsa-pro-perf-report').on('click.wsa-pro-perf-report', '#wsa-view-performance-report', (e) => {
                e.preventDefault();
                this.viewPerformanceReport();
            });
            
            $(document).off('click.wsa-pro-ai-insights').on('click.wsa-pro-ai-insights', '#wsa-get-ai-insights', (e) => {
                e.preventDefault();
                this.getAIInsights();
            });
            
            // Conflict Detection
            $(document).off('click.wsa-pro-conflict').on('click.wsa-pro-conflict', '#wsa-run-conflict-check', (e) => {
                e.preventDefault();
                this.runConflictCheck();
            });
            
            $(document).off('click.wsa-pro-conflict-report').on('click.wsa-pro-conflict-report', '#wsa-view-conflict-report', (e) => {
                e.preventDefault();
                this.viewConflictAnalysis();
            });
            
            // White Label Reports
            $(document).off('click.wsa-pro-reports').on('click.wsa-pro-reports', '#wsa-generate-pdf-report', (e) => {
                e.preventDefault();
                this.generateProReport();
            });
            
            $(document).off('click.wsa-pro-download').on('click.wsa-pro-download', '#wsa-download-latest-report', (e) => {
                e.preventDefault();
                this.scheduleReport();
            });
            
            // AI Chatbot
            $(document).off('click.wsa-pro-chat').on('click.wsa-pro-chat', '#wsa-ask-question', (e) => {
                e.preventDefault();
                this.openAIChat();
            });
            
            $(document).off('click.wsa-pro-chat-history').on('click.wsa-pro-chat-history', '#wsa-view-chat-history', (e) => {
                e.preventDefault();
                this.viewChatHistory();
            });
        }
        
        initFeatures() {
            // Initialize any features that need setup
            this.updateProStats();
            
            // Refresh stats every 30 seconds
            setInterval(() => {
                this.updateProStats();
            }, 30000);
        }
        
        // Vulnerability Scanner Methods
        runVulnerabilityScann() {
            const $button = $('#wsa-run-vulnerability-scan');
            const $results = $('#wsa-vulnerability-results');
            
            $button.prop('disabled', true).text('Scanning...');
            $results.hide();
            
            $.ajax({
                url: wsa_pro_admin.ajax_url,
                type: 'POST',
                data: {
                    action: 'wsa_run_vulnerability_scan',
                    nonce: wsa_pro_admin.nonce
                },
                success: (response) => {
                    if (response.success) {
                        $results.html('<h4>Vulnerability Scan Complete</h4><p>' + response.data.message + '</p>').show();
                        this.updateProStats();
                    } else {
                        $results.html('<div class="notice notice-error"><p>' + (response.data || 'Scan failed') + '</p></div>').show();
                    }
                },
                error: () => {
                    $results.html('<div class="notice notice-error"><p>Failed to run vulnerability scan. Please try again.</p></div>').show();
                },
                complete: () => {
                    $button.prop('disabled', false).text('Run Vulnerability Scan');
                }
            });
        }
        
        viewVulnerabilityReport() {
            const $results = $('#wsa-vulnerability-results');
            
            $.ajax({
                url: wsa_pro_admin.ajax_url,
                type: 'POST',
                data: {
                    action: 'wsa_get_vulnerability_report',
                    nonce: wsa_pro_admin.nonce
                },
                success: (response) => {
                    if (response.success) {
                        this.displayVulnerabilityReport(response.data, $results);
                    } else {
                        $results.html('<div class="notice notice-warning"><p>No vulnerability report available. Run a scan first.</p></div>').show();
                    }
                },
                error: () => {
                    $results.html('<div class="notice notice-error"><p>Failed to load vulnerability report.</p></div>').show();
                }
            });
        }
        
        displayVulnerabilityReport(data, $container) {
            let html = '<h4>Vulnerability Report</h4>';
            
            if (data.summary && data.summary.total_vulnerabilities > 0) {
                html += '<div class="wsa-vulnerability-summary">';
                html += '<p><strong>Total Vulnerabilities Found: ' + data.summary.total_vulnerabilities + '</strong></p>';
                html += '<p>Core: ' + (data.summary.core_vulnerabilities || 0) + ' | ';
                html += 'Plugins: ' + (data.summary.plugin_vulnerabilities || 0) + ' | ';
                html += 'Themes: ' + (data.summary.theme_vulnerabilities || 0) + '</p>';
                html += '</div>';
            } else {
                html += '<div class="notice notice-success"><p>No vulnerabilities found! Your site is secure.</p></div>';
            }
            
            $container.html(html).show();
        }
        
        // Performance Analysis Methods
        runPageSpeedAnalysis() {
            const $button = $('#wsa-run-pagespeed-analysis');
            const $results = $('#wsa-performance-results');
            
            if (typeof wsa_pro_admin === 'undefined') {
                console.error('wsa_pro_admin object not found!');
                return;
            }
            
            // Get the URL to analyze
            const urlToAnalyze = $('#wsa-performance-url').val();
            if (!urlToAnalyze) {
                return;
            }
            
            // Check if it's a localhost URL
            if (urlToAnalyze.includes('localhost') || urlToAnalyze.includes('127.0.0.1')) {
                if (!confirm('This appears to be a localhost URL. PageSpeed Insights requires a publicly accessible URL. Do you want to continue anyway?')) {
                    return;
                }
            }
            
            $button.prop('disabled', true).text('Analyzing...');
            $results.html('<div class="notice notice-info"><p><span class="spinner is-active" style="float: left; margin-right: 10px;"></span>Running PageSpeed Insights analysis for: <strong>' + urlToAnalyze + '</strong><br>This may take 30-60 seconds.</p></div>').show();
            
            $.ajax({
                url: wsa_pro_admin.ajax_url,
                type: 'POST',
                data: {
                    action: 'wsa_run_pagespeed_analysis',
                    nonce: wsa_pro_admin.nonce,
                    url: urlToAnalyze
                },
                success: (response) => {
                    if (response.success) {
                        // Store the results for the modal
                        this.latestPerformanceData = response.data;
                        
                        let successMessage = '<div class="notice notice-success inline" style="margin: 10px 0; padding: 8px 12px;">';
                        successMessage += '<p><strong>✅ Analysis Complete!</strong> Results will open automatically in a popup...</p>';
                        successMessage += '</div>';
                        
                        // Check if this was a Lighthouse simulation
                        if (response.data && response.data.source === 'lighthouse_simulation') {
                            successMessage = '<div class="notice notice-info inline" style="margin: 10px 0; padding: 8px 12px;">';
                            successMessage += '<p><strong>🚀 Lighthouse Simulation Complete!</strong> ';
                            successMessage += 'Local development analysis finished. Results opening in popup...</p>';
                            successMessage += '</div>';
                        }
                        
                        $results.html(successMessage).show();
                        
                        // Show "View Report" button
                        $('#wsa-view-performance-report').show();
                        
                        // Update performance metrics if available
                        if (response.data && response.data.summary) {
                            this.updatePerformanceMetrics(response.data.summary);
                        }
                        
                        this.updateProStats();
                        
                        // Auto-show modal with results
                        setTimeout(() => {
                            this.showPerformanceModal(response.data);
                        }, 800);
                    } else {
                        $results.html('<div class="notice notice-error"><p>' + (response.data || 'Analysis failed') + '</p></div>').show();
                    }
                },
                error: (xhr, status, error) => {
                    console.error('AJAX error:', xhr, status, error);
                    $results.html('<div class="notice notice-error"><p>Failed to run performance analysis. Please check your PageSpeed Insights API key configuration.</p></div>').show();
                },
                complete: () => {
                    $button.prop('disabled', false).text('Run Performance Analysis');
                }
            });
        }
        
        viewPerformanceReport() {
            
            // Check if we have cached data first
            if (this.latestPerformanceData) {
                this.showPerformanceModal(this.latestPerformanceData);
                return;
            }
            
            // Check localStorage for persisted data
            const persistedData = localStorage.getItem('wsa_last_performance_data');
            if (persistedData) {
                try {
                    const data = JSON.parse(persistedData);
                    this.showPerformanceModal(data);
                    return;
                } catch (e) {
                    console.error('Error parsing persisted data:', e);
                }
            }
            
            // Otherwise fetch from server
            $.ajax({
                url: wsa_pro_admin.ajax_url,
                type: 'POST',
                data: {
                    action: 'wsa_get_performance_report',
                    nonce: wsa_pro_admin.nonce
                },
                success: (response) => {
                    if (response.success) {
                        this.showPerformanceModal(response.data);
                    } else {
                        this.showModalError('No performance report available. Run an analysis first.');
                    }
                },
                error: (xhr, status, error) => {
                    console.error('AJAX error:', xhr, status, error);
                    this.showModalError('Error loading performance report.');
                }
            });
        }
        
        displayPerformanceReport(data, $container) {
            // Results now display only in modal - show simple message in tab
            let html = '<div class="notice notice-info">';
            html += '<p><strong>✨ Results Ready!</strong> Performance analysis complete. ';
            html += 'Results are displayed in the popup modal. ';
            html += '<a href="#" id="wsa-reopen-modal" class="button button-small">View Results Again</a></p>';
            html += '</div>';
            
            $container.html(html).show();
            
            // Bind reopen modal button
            $('#wsa-reopen-modal').off('click').on('click', (e) => {
                e.preventDefault();
                this.viewPerformanceReport();
            });
        }
        

        
        updatePerformanceMetrics(summary) {
            
            // Check for new data structure with scores object
            if (summary && summary.scores && typeof summary.scores === 'object') {
                // Show the metrics section
                $('#wsa-performance-metrics').show();
                
                // Update performance score - check for mobile first, then desktop
                let performanceScore = null;
                if (summary.scores.performance && summary.scores.performance.score !== undefined) {
                    performanceScore = summary.scores.performance.score;
                } else if (summary.scores.mobile && summary.scores.mobile.performance) {
                    performanceScore = summary.scores.mobile.performance.score;
                } else if (summary.scores.desktop && summary.scores.desktop.performance) {
                    performanceScore = summary.scores.desktop.performance.score;
                }
                
                if (performanceScore !== null) {
                    $('#wsa-perf-score').text(performanceScore + '/100');
                }
                
                // Update Core Web Vitals - check if available in summary
                if (summary.core_web_vitals) {
                    if (summary.core_web_vitals.fcp) {
                        $('#wsa-fcp').text(summary.core_web_vitals.fcp + 's');
                    }
                    if (summary.core_web_vitals.lcp) {
                        $('#wsa-lcp').text(summary.core_web_vitals.lcp + 's');
                    }
                    if (summary.core_web_vitals.tbt) {
                        $('#wsa-tbt').text(summary.core_web_vitals.tbt + 'ms');
                    }
                }
                
            } else if (summary && summary.scores && summary.scores.length > 0) {
                // Legacy array structure fallback
                $('#wsa-performance-metrics').show();
                
                if (summary.scores.desktop && summary.scores.desktop.performance) {
                    $('#wsa-perf-score').text(summary.scores.desktop.performance.score + '/100');
                }
                
                if (summary.core_web_vitals) {
                    if (summary.core_web_vitals.fcp) {
                        $('#wsa-fcp').text(summary.core_web_vitals.fcp + 's');
                    }
                    if (summary.core_web_vitals.lcp) {
                        $('#wsa-lcp').text(summary.core_web_vitals.lcp + 's');
                    }
                    if (summary.core_web_vitals.tbt) {
                        $('#wsa-tbt').text(summary.core_web_vitals.tbt + 'ms');
                    }
                }
            } else {
                // Hide metrics section if no valid data
                $('#wsa-performance-metrics').hide();
            }
        }
        
        getAIInsights() {
            const $button = $('#wsa-get-ai-insights');
            const $results = $('#wsa-performance-results');
            
            $button.prop('disabled', true).text('Getting AI Insights...');
            
            $.ajax({
                url: wsa_pro_admin.ajax_url,
                type: 'POST',
                data: {
                    action: 'wsa_get_ai_performance_insights',
                    nonce: wsa_pro_admin.nonce
                },
                success: (response) => {
                    if (response.success) {
                        const insightsHtml = '<div class="wsa-ai-insights"><h4>AI Performance Recommendations</h4><div class="wsa-insights-content">' + 
                                           response.data.content.replace(/\n/g, '<br>') + '</div></div>';
                        $results.append(insightsHtml);
                    } else {
                        $results.append('<div class="notice notice-error"><p>' + (response.data || 'Failed to get AI insights') + '</p></div>');
                    }
                },
                error: () => {
                    $results.append('<div class="notice notice-error"><p>Failed to get AI insights. Please check your OpenAI API key.</p></div>');
                },
                complete: () => {
                    $button.prop('disabled', false).text('Get AI Recommendations');
                }
            });
        }
        
        // Conflict Detection Methods
        runConflictCheck() {
            const $button = $('#wsa-run-conflict-check');
            const $results = $('#wsa-conflict-results');
            
            $button.prop('disabled', true).text('Checking...');
            $results.hide();
            
            // For conflict detection, we'll just get the current analysis
            this.viewConflictAnalysis();
            
            $button.prop('disabled', false).text('Check for Conflicts');
        }
        
        viewConflictAnalysis() {
            const $results = $('#wsa-conflict-results');
            
            $.ajax({
                url: wsa_pro_admin.ajax_url,
                type: 'POST',
                data: {
                    action: 'wsa_get_conflict_analysis',
                    nonce: wsa_pro_admin.nonce
                },
                success: (response) => {
                    if (response.success) {
                        this.displayConflictAnalysis(response.data, $results);
                    } else {
                        $results.html('<div class="notice notice-info"><p>No conflicts detected. Your plugins and themes are working well together.</p></div>').show();
                    }
                },
                error: () => {
                    $results.html('<div class="notice notice-error"><p>Failed to load conflict analysis.</p></div>').show();
                }
            });
        }
        
        displayConflictAnalysis(data, $container) {
            let html = '<h4>Conflict Analysis</h4>';
            
            if (data.conflicts && data.conflicts.length > 0) {
                html += '<div class="wsa-conflicts-list">';
                data.conflicts.forEach(conflict => {
                    html += '<div class="wsa-conflict-item">';
                    html += '<strong>' + conflict.source + '</strong>';
                    html += '<p>' + conflict.description + '</p>';
                    if (conflict.ai_analysis) {
                        html += '<div class="wsa-ai-suggestion">' + conflict.ai_analysis + '</div>';
                    }
                    html += '</div>';
                });
                html += '</div>';
            } else {
                html += '<div class="notice notice-success"><p>No conflicts detected! Your site is running smoothly.</p></div>';
            }
            
            $container.html(html).show();
        }
        
        // White Label Reports Methods
        generateProReport() {
            const $button = $('#wsa-generate-pro-report');
            const $results = $('#wsa-report-results');
            
            $button.prop('disabled', true).text('Generating...');
            $results.hide();
            
            $.ajax({
                url: wsa_pro_admin.ajax_url,
                type: 'POST',
                data: {
                    action: 'wsa_generate_pro_report',
                    nonce: wsa_pro_admin.nonce
                },
                success: (response) => {
                    if (response.success) {
                        $results.html('<h4>Report Generated</h4><p>Your professional report has been generated successfully.</p>' +
                                    '<p><a href="' + response.data.download_url + '" class="button">Download PDF</a></p>').show();
                    } else {
                        $results.html('<div class="notice notice-error"><p>' + (response.data || 'Report generation failed') + '</p></div>').show();
                    }
                },
                error: () => {
                    $results.html('<div class="notice notice-error"><p>Failed to generate report. Please try again.</p></div>').show();
                },
                complete: () => {
                    $button.prop('disabled', false).text('Generate Report');
                }
            });
        }
        
        previewProReport() {
            window.open(wsa_pro_admin.ajax_url + '?action=wsa_preview_pro_report&nonce=' + wsa_pro_admin.nonce, '_blank');
        }
        
        sendTestReport() {
            const $button = $('#wsa-send-test-report');
            const $results = $('#wsa-report-results');
            
            $button.prop('disabled', true).text('Sending...');
            
            $.ajax({
                url: wsa_pro_admin.ajax_url,
                type: 'POST',
                data: {
                    action: 'wsa_send_test_report',
                    nonce: wsa_pro_admin.nonce
                },
                success: (response) => {
                    if (response.success) {
                        $results.html('<div class="notice notice-success"><p>Test report sent successfully!</p></div>').show();
                    } else {
                        $results.html('<div class="notice notice-error"><p>' + (response.data || 'Failed to send test report') + '</p></div>').show();
                    }
                },
                error: () => {
                    $results.html('<div class="notice notice-error"><p>Failed to send test report. Please check your email settings.</p></div>').show();
                },
                complete: () => {
                    $button.prop('disabled', false).text('Send Test Email');
                }
            });
        }
        
        scheduleReport() {
            const frequency = $('#wsa-report-frequency').val();
            const $button = $('#wsa-schedule-report');
            const $results = $('#wsa-report-results');
            
            $button.prop('disabled', true).text('Updating...');
            
            $.ajax({
                url: wsa_pro_admin.ajax_url,
                type: 'POST',
                data: {
                    action: 'wsa_schedule_report',
                    frequency: frequency,
                    nonce: wsa_pro_admin.nonce
                },
                success: (response) => {
                    if (response.success) {
                        $results.html('<div class="notice notice-success"><p>Report schedule updated successfully!</p></div>').show();
                    } else {
                        $results.html('<div class="notice notice-error"><p>' + (response.data || 'Failed to update schedule') + '</p></div>').show();
                    }
                },
                error: () => {
                    $results.html('<div class="notice notice-error"><p>Failed to update report schedule.</p></div>').show();
                },
                complete: () => {
                    $button.prop('disabled', false).text('Update Schedule');
                }
            });
        }
        
        // Update Pro stats in the dashboard
        updateProStats() {
            // Update vulnerability status
            $.ajax({
                url: wsa_pro_admin.ajax_url,
                type: 'POST',
                data: {
                    action: 'wsa_get_vulnerability_report',
                    nonce: wsa_pro_admin.nonce
                },
                success: (response) => {
                    if (response.success && response.data.summary) {
                        const count = response.data.summary.total_vulnerabilities || 0;
                        const status = count > 0 ? 
                            '<span class="wsa-status-disconnected">' + count + ' Vulnerabilities</span>' :
                            '<span class="wsa-status-connected">No Vulnerabilities</span>';
                        $('#wsa-pro-vulnerability-status').html(status);
                    }
                }
            });
            
            // Update performance status
            $.ajax({
                url: wsa_pro_admin.ajax_url,
                type: 'POST',
                data: {
                    action: 'wsa_get_performance_report',
                    nonce: wsa_pro_admin.nonce
                },
                success: (response) => {
                    if (response.success && response.data.summary && response.data.summary.scores) {
                        const scores = response.data.summary.scores;
                        if (scores.mobile && scores.mobile.performance) {
                            const score = scores.mobile.performance.score;
                            let statusClass = 'wsa-status-connected';
                            if (score < 50) statusClass = 'wsa-status-disconnected';
                            else if (score < 90) statusClass = 'wsa-status-warning';
                            
                            const status = '<span class="' + statusClass + '">' + score + '/100</span>';
                            $('#wsa-pro-performance-status').html(status);
                        }
                    }
                }
            });
        }

        // AI Chatbot Methods
        openAIChat() {
            // Toggle or open the AI chatbot
            if (typeof WSAAIChatbot !== 'undefined') {
                WSAAIChatbot.toggle();
            } else {
            }
        }

        viewChatHistory() {
            // Show chat history modal or panel
            $.ajax({
                url: wsa_pro_admin.ajax_url,
                type: 'POST',
                data: {
                    action: 'wsa_get_chat_history',
                    nonce: wsa_pro_admin.nonce
                },
                success: (response) => {
                    if (response.success) {
                        // Display chat history in modal
                        this.showChatHistoryModal(response.data);
                    }
                }
            });
        }

        showChatHistoryModal(history) {
            // Create and show chat history modal
            const modalHtml = `
                <div class="wsa-modal" id="wsa-chat-history-modal">
                    <div class="wsa-modal-content">
                        <div class="wsa-modal-header">
                            <h3>Chat History</h3>
                            <button class="wsa-modal-close">&times;</button>
                        </div>
                        <div class="wsa-modal-body">
                            <div class="wsa-chat-history">
                                ${history.map(chat => `
                                    <div class="wsa-chat-session">
                                        <div class="wsa-chat-date">${chat.date}</div>
                                        <div class="wsa-chat-messages">
                                            ${chat.messages.map(msg => `
                                                <div class="wsa-chat-message wsa-${msg.type}">
                                                    ${msg.content}
                                                </div>
                                            `).join('')}
                                        </div>
                                    </div>
                                `).join('')}
                            </div>
                        </div>
                    </div>
                </div>
            `;
            
            $('body').append(modalHtml);
            
            // Close modal events
            $('#wsa-chat-history-modal .wsa-modal-close, #wsa-chat-history-modal').on('click', function(e) {
                if (e.target === this) {
                    $('#wsa-chat-history-modal').remove();
                }
            });
        }
        
        updateLocalhostMessage() {
            // Update the localhost warning to mention Pro capabilities
            const $localhostWarning = $('.wsa-performance-analysis .notice-warning');
            if ($localhostWarning.length && $localhostWarning.text().includes('localhost')) {
                $localhostWarning.removeClass('notice-warning').addClass('notice-info');
                $localhostWarning.html(
                    '<p><strong>🚀 Pro Enhancement:</strong> ' +
                    'Localhost detected! The Pro plugin will use Lighthouse simulation to provide ' +
                    'realistic performance analysis for local development. For production analysis, ' +
                    'deploy to a public server.</p>'
                );
            }
        }
        
        initPersistedData() {
            // Check if we have persisted performance data and show "View Report" button
            const persistedData = localStorage.getItem('wsa_last_performance_data');
            if (persistedData) {
                try {
                    const data = JSON.parse(persistedData);
                    
                    // Show the "View Report" button if we have cached data
                    $('#wsa-view-performance-report').show();
                    
                    // Update the results area with a "results ready" message
                    const $results = $('#wsa-performance-results');
                } catch (e) {
                    console.error('Error parsing persisted performance data:', e);
                    localStorage.removeItem('wsa_last_performance_data');
                }
            }
        }
        
        showPerformanceModal(data) {
            
            const $modal = $('#wsa-performance-modal');
            const $content = $('#wsa-modal-performance-content');
            
            if (!$modal.length) {
                console.error('Performance modal not found!');
                return;
            }
            
            // Store the data for persistence
            localStorage.setItem('wsa_last_performance_data', JSON.stringify(data));
            
            // Generate modal content using existing display method
            let html = '';
            
            // Check for API errors first
            if (data.report && data.report.desktop && data.report.desktop.error) {
                html += '<div class="notice notice-error">';
                html += '<p><strong>PageSpeed API Error:</strong></p>';
                html += '<p>' + data.report.desktop.error + '</p>';
                html += '</div>';
            } else if (data && (data.report || data.summary || data.desktop || data.mobile)) {
                // Handle multiple data structures
                const reportData = data.report || data;
                
                // Check for different data structures
                let hasDesktop = false;
                let hasMobile = false;
                let desktopScores = null;
                let mobileScores = null;
                
                // Structure 1: data.report.desktop.scores
                if (reportData.desktop && reportData.desktop.scores) {
                    hasDesktop = true;
                    hasMobile = reportData.mobile && reportData.mobile.scores;
                    desktopScores = reportData.desktop.scores;
                    mobileScores = hasMobile ? reportData.mobile.scores : null;
                }
                // Structure 2: data.summary.scores.desktop
                else if (data.summary && data.summary.scores) {
                    hasDesktop = data.summary.scores.desktop ? true : false;
                    hasMobile = data.summary.scores.mobile ? true : false;
                    desktopScores = data.summary.scores.desktop;
                    mobileScores = data.summary.scores.mobile;
                }
                
                // Check if this is a Lighthouse simulation
                if (data.source === 'lighthouse_simulation' || reportData.source === 'lighthouse_simulation') {
                    html += '<div class="wsa-modal-simulation-badge">';
                    html += '<span class="dashicons dashicons-performance"></span>';
                    html += '<div class="wsa-modal-simulation-badge-text">';
                    html += '<strong>🚀 Lighthouse Simulation Results</strong>';
                    html += '<p>Local development analysis completed. Deploy to a public server for full PageSpeed Insights data.</p>';
                    html += '</div>';
                    html += '</div>';
                }
                
                html += '<div class="wsa-performance-scores">';
                
                if (hasDesktop) {
                    html += '<h5>Desktop Performance Scores:</h5>';
                    html += '<div class="wsa-score-grid">';
                    
                    Object.keys(desktopScores).forEach(scoreType => {
                        const scoreData = desktopScores[scoreType];
                        html += '<div class="wsa-score-item">';
                        html += '<strong>' + scoreType.charAt(0).toUpperCase() + scoreType.slice(1).replace('-', ' ') + '</strong>';
                        html += '<span class="score-value" data-score="' + scoreData.score + '">' + scoreData.score + '/100</span>';
                        html += '</div>';
                    });
                    
                    html += '</div>';
                    
                    // Add Core Web Vitals for desktop if available
                    const desktopMetrics = reportData.desktop?.metrics || data.summary?.metrics?.desktop;
                    if (desktopMetrics) {
                        html += '<h6>Desktop Core Web Vitals:</h6>';
                        html += '<div class="wsa-metrics-grid">';
                        
                        Object.keys(desktopMetrics).forEach(metricType => {
                            const metricData = desktopMetrics[metricType];
                            if (metricData.displayValue) {
                                html += '<div class="wsa-metric-item">';
                                html += '<strong>' + metricType.replace(/-/g, ' ').replace(/\b\w/g, l => l.toUpperCase()) + '</strong>';
                                html += '<span class="metric-value">' + metricData.displayValue + '</span>';
                                html += '</div>';
                            }
                        });
                        
                        html += '</div>';
                    }
                }
                
                if (hasMobile) {
                    html += '<h5>Mobile Performance Scores:</h5>';
                    html += '<div class="wsa-score-grid">';
                    
                    Object.keys(mobileScores).forEach(scoreType => {
                        const scoreData = mobileScores[scoreType];
                        html += '<div class="wsa-score-item">';
                        html += '<strong>' + scoreType.charAt(0).toUpperCase() + scoreType.slice(1).replace('-', ' ') + '</strong>';
                        html += '<span class="score-value" data-score="' + scoreData.score + '">' + scoreData.score + '/100</span>';
                        html += '</div>';
                    });
                    
                    html += '</div>';
                    
                    // Add Core Web Vitals for mobile if available
                    const mobileMetrics = reportData.mobile?.metrics || data.summary?.metrics?.mobile;
                    if (mobileMetrics) {
                        html += '<h6>Mobile Core Web Vitals:</h6>';
                        html += '<div class="wsa-metrics-grid">';
                        
                        Object.keys(mobileMetrics).forEach(metricType => {
                            const metricData = mobileMetrics[metricType];
                            if (metricData.displayValue) {
                                html += '<div class="wsa-metric-item">';
                                html += '<strong>' + metricType.replace(/-/g, ' ').replace(/\b\w/g, l => l.toUpperCase()) + '</strong>';
                                html += '<span class="metric-value">' + metricData.displayValue + '</span>';
                                html += '</div>';
                            }
                        });
                        
                        html += '</div>';
                    }
                }
                
                // Show AI insights if available
                const aiInsights = reportData.ai_insights || data.ai_insights;
                if (aiInsights && aiInsights.content) {
                    html += '<div class="wsa-ai-insights">';
                    html += '<h5>🤖 AI Performance Recommendations</h5>';
                    html += '<div class="wsa-ai-content">' + aiInsights.content.replace(/\n/g, '<br>') + '</div>';
                    html += '</div>';
                }
                
                // If no scores found, show summary scores as fallback
                if (!hasDesktop && !hasMobile && data.summary && data.summary.scores) {
                    html += '<h5>Performance Scores:</h5>';
                    html += '<div class="wsa-score-grid">';
                    
                    Object.keys(data.summary.scores).forEach(scoreType => {
                        const scoreData = data.summary.scores[scoreType];
                        if (scoreData && scoreData.score !== undefined) {
                            html += '<div class="wsa-score-item">';
                            html += '<strong>' + scoreType.charAt(0).toUpperCase() + scoreType.slice(1) + '</strong>';
                            html += '<span class="score-value" data-score="' + scoreData.score + '">' + scoreData.score + '/100</span>';
                            html += '</div>';
                        }
                    });
                    
                    html += '</div>';
                }
                
                html += '</div>';
            } else {
                html += '<div class="notice notice-warning"><p>No performance data available.</p></div>';
            }
            
            $content.html(html);
            
            // Show modal with animation
            $modal.addClass('wsa-modal-open');
            $('body').css('overflow', 'hidden'); // Prevent background scrolling
            
            // Bind modal close events
            this.bindModalEvents();
        }
        
        showModalError(message) {
            const $modal = $('#wsa-performance-modal');
            const $content = $('#wsa-modal-performance-content');
            
            const html = '<div class="notice notice-error"><p>' + message + '</p></div>';
            $content.html(html);
            
            $modal.addClass('wsa-modal-open');
            $('body').css('overflow', 'hidden');
            
            this.bindModalEvents();
        }
        
        closeModal() {
            const $modal = $('#wsa-performance-modal');
            $modal.removeClass('wsa-modal-open');
            $('body').css('overflow', ''); // Restore scrolling
        }
        
        bindModalEvents() {
            const self = this;
            
            // Close button
            $('.wsa-modal-close, #wsa-modal-close-btn').off('click.modal').on('click.modal', function(e) {
                e.preventDefault();
                self.closeModal();
            });
            
            // Run new analysis button
            $('#wsa-run-new-analysis').off('click.modal').on('click.modal', function(e) {
                e.preventDefault();
                self.closeModal();
                // Trigger new analysis
                setTimeout(() => {
                    $('#wsa-run-pagespeed-analysis').click();
                }, 300);
            });
            
            // Close on overlay click
            $('.wsa-modal-overlay').off('click.modal').on('click.modal', function(e) {
                if (e.target === this) {
                    self.closeModal();
                }
            });
            
            // Close on Escape key
            $(document).off('keydown.modal').on('keydown.modal', function(e) {
                if (e.keyCode === 27) { // Escape key
                    self.closeModal();
                }
            });
        }
        
        // Test method to verify modal with sample data
        testModalWithSampleData() {
            const sampleData = {
                "report": {
                    "url": "//localhost:5500/wpsiteadvisor",
                    "desktop": {
                        "strategy": "desktop",
                        "scores": {
                            "performance": { "score": 75 },
                            "accessibility": { "score": 85 },
                            "best-practices": { "score": 80 },
                            "seo": { "score": 90 }
                        },
                        "metrics": {
                            "first-contentful-paint": { "displayValue": "1.5 s" },
                            "largest-contentful-paint": { "displayValue": "2.5 s" },
                            "total-blocking-time": { "displayValue": "320 ms" },
                            "cumulative-layout-shift": { "displayValue": "0.10" },
                            "speed-index": { "displayValue": "3.0 s" }
                        }
                    },
                    "mobile": {
                        "strategy": "mobile",
                        "scores": {
                            "performance": { "score": 65 },
                            "accessibility": { "score": 85 },
                            "best-practices": { "score": 80 },
                            "seo": { "score": 88 }
                        },
                        "metrics": {
                            "first-contentful-paint": { "displayValue": "2.0 s" },
                            "largest-contentful-paint": { "displayValue": "3.5 s" },
                            "total-blocking-time": { "displayValue": "420 ms" },
                            "cumulative-layout-shift": { "displayValue": "0.10" },
                            "speed-index": { "displayValue": "4.0 s" }
                        }
                    },
                    "ai_insights": {
                        "content": "Sample AI insights for testing..."
                    },
                    "source": "lighthouse_simulation"
                }
            };
            
            this.showPerformanceModal(sampleData);
        }
    }
    
    // Initialize when document is ready and create global instance
    $(document).ready(function() {
        // Only initialize on WP SiteAdvisor pages
        if ($('.wsa-dashboard').length) {
            window.WSAProIntegration = new WSAProIntegration();
        }
    });
    
})(jQuery);
