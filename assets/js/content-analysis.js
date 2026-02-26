/**
 * Content Analysis JavaScript
 * 
 * Handles content analysis functionality in the post editor
 */

jQuery(document).ready(function($) {
    'use strict';
    
    var wsaContentAnalysis = {
        
        init: function() {
            this.bindEvents();
        },
        
        bindEvents: function() {
            // Auto-analyze when content changes (debounced)
            var analyzeTimeout;
            $('#content, #title').on('input', function() {
                clearTimeout(analyzeTimeout);
                analyzeTimeout = setTimeout(wsaContentAnalysis.runAnalysis, 2000);
            });
            
            // Manual analysis button
            $('#wsa-run-analysis').on('click', function(e) {
                e.preventDefault();
                wsaContentAnalysis.runAnalysis();
            });
        },
        
        runAnalysis: function() {
            var postId = window.wsaContentAnalysis.post_id;
            var content = $('#content').val() || '';
            var title = $('#title').val() || '';
            
            if (!content && !title) {
                wsaContentAnalysis.showMessage(window.wsaContentAnalysis.messages.no_content, 'warning');
                return;
            }
            
            $('#wsa-analysis-results').html('<p>' + window.wsaContentAnalysis.messages.analyzing + '</p>');
            
            $.ajax({
                url: window.wsaContentAnalysis.ajaxurl,
                type: 'POST',
                data: {
                    action: 'wsa_analyze_content',
                    post_id: postId,
                    content: content,
                    title: title,
                    nonce: window.wsaContentAnalysis.nonce
                },
                success: function(response) {
                    if (response.success) {
                        wsaContentAnalysis.displayResults(response.data);
                    } else {
                        wsaContentAnalysis.showMessage(response.data || window.wsaContentAnalysis.messages.analysis_failed, 'error');
                    }
                },
                error: function() {
                    wsaContentAnalysis.showMessage(window.wsaContentAnalysis.messages.analysis_failed, 'error');
                }
            });
        },
        
        displayResults: function(data) {
            var html = '<div class="wsa-analysis-results">';
            
            // SEO Score
            if (data.seo_score) {
                html += '<div class="wsa-score-item">';
                html += '<strong>SEO Score: </strong>';
                html += '<span class="wsa-score wsa-score-' + wsaContentAnalysis.getScoreClass(data.seo_score) + '">' + data.seo_score + '/100</span>';
                html += '</div>';
            }
            
            // Readability Score  
            if (data.readability_score) {
                html += '<div class="wsa-score-item">';
                html += '<strong>Readability: </strong>';
                html += '<span class="wsa-score wsa-score-' + wsaContentAnalysis.getScoreClass(data.readability_score) + '">' + data.readability_score + '/100</span>';
                html += '</div>';
            }
            
            // Issues
            if (data.issues && data.issues.length > 0) {
                html += '<div class="wsa-issues">';
                html += '<strong>Issues Found:</strong>';
                html += '<ul>';
                data.issues.forEach(function(issue) {
                    html += '<li class="wsa-issue-' + issue.severity + '">' + issue.message + '</li>';
                });
                html += '</ul>';
                html += '</div>';
            }
            
            // Recommendations
            if (data.recommendations && data.recommendations.length > 0) {
                html += '<div class="wsa-recommendations">';
                html += '<strong>Recommendations:</strong>';
                html += '<ul>';
                data.recommendations.forEach(function(rec) {
                    html += '<li>' + rec + '</li>';
                });
                html += '</ul>';
                html += '</div>';
            }
            
            html += '</div>';
            
            $('#wsa-analysis-results').html(html);
        },
        
        getScoreClass: function(score) {
            if (score >= 80) return 'good';
            if (score >= 60) return 'average';
            return 'poor';
        },
        
        showMessage: function(message, type) {
            var $message = $('<div class="notice notice-' + type + '"><p>' + message + '</p></div>');
            $('#wsa-analysis-results').html($message);
        }
    };
    
    // Initialize if we're on a post edit screen
    if (window.wsaContentAnalysis && window.wsaContentAnalysis.post_id) {
        wsaContentAnalysis.init();
    }
});