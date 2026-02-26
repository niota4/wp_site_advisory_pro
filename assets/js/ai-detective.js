/**
 * AI Site Detective - Frontend JavaScript
 * 
 * Handles the top-bar AI finder functionality
 */

(function($) {
    'use strict';

    class WSADetective {
        constructor() {
            this.modal = null;
            this.isLoading = false;
            this.currentScan = null;
            
            this.init();
        }

        init() {
            this.bindEvents();
            this.setupModal();
        }

        bindEvents() {
            // Admin bar trigger
            $(document).on('click', '#wp-admin-bar-wsa-ai-detective a', (e) => {
                e.preventDefault();
                this.openModal();
            });

            // Modal close events
            $(document).on('click', '.wsa-detective-modal-close, .wsa-detective-modal-overlay', () => {
                this.closeModal();
            });

            // Quick Scan button
            $(document).on('click', '#wsa-detective-submit', () => {
                this.submitQuery('quick');
            });

            // Deep Search button  
            $(document).on('click', '#wsa-detective-deep-scan', () => {
                this.submitQuery('deep');
            });

            // Enter key in textarea (defaults to Quick Scan)
            $(document).on('keydown', '#wsa-detective-query', (e) => {
                if (e.ctrlKey && e.keyCode === 13) { // Ctrl+Enter for Quick Scan
                    this.submitQuery('quick');
                } else if (e.altKey && e.keyCode === 13) { // Alt+Enter for Deep Search
                    this.submitQuery('deep');
                }
            });

            // Escape key to close modal
            $(document).on('keydown', (e) => {
                if (e.keyCode === 27 && this.modal && this.modal.is(':visible')) {
                    this.closeModal();
                }
            });

            // Action buttons
            $(document).on('click', '.wsa-detective-action-btn', (e) => {
                const url = $(e.target).data('url');
                if (url) {
                    window.open(url, '_blank');
                }
            });

            // Suggestion buttons
            $(document).on('click', '.wsa-detective-suggestion-btn', (e) => {
                const query = $(e.target).data('query');
                if (query) {
                    $('#wsa-detective-query').val(query);
                    $('#wsa-detective-query').focus();
                }
            });

            // Export buttons
            $(document).on('click', '.wsa-export-btn', (e) => {
                this.handleExport(e);
            });
        }

        setupModal() {
            this.modal = $('#wsa-ai-detective-modal');
            
            // Add styles for responsive behavior
            this.addResponsiveStyles();
        }

        openModal() {
            if (this.modal) {
                this.modal.fadeIn(300);
                $('#wsa-detective-query').focus();
                
                // Pre-populate with current page info
                this.displayCurrentPageInfo();
            }
        }

        closeModal() {
            if (this.modal) {
                this.modal.fadeOut(300);
                this.resetModal();
            }
        }

        displayCurrentPageInfo() {
            const currentUrl = wsaAiDetective.currentUrl;
            const pageId = wsaAiDetective.pageId;
            
            // Show current page context in the modal
            let contextInfo = `<div class="wsa-detective-page-context">
                <h4><span class="dashicons dashicons-admin-page"></span> Current Page</h4>
                <p><strong>URL:</strong> ${currentUrl}</p>`;
            
            if (pageId) {
                contextInfo += `<p><strong>Page ID:</strong> ${pageId}</p>`;
            }
            
            if (wsaAiDetective.isAdmin) {
                contextInfo += `<p><strong>Context:</strong> WordPress Admin</p>`;
            }
            
            contextInfo += `</div>`;
            
            // Add to modal if doesn't exist
            if (!$('.wsa-detective-page-context').length) {
                $('.wsa-detective-query-section').after(contextInfo);
            }
        }

        submitQuery(scanType = 'quick') {
            if (this.isLoading) return;

            const query = $('#wsa-detective-query').val().trim();
            if (!query) {
                return;
            }

            this.isLoading = true;
            
            if (scanType === 'quick') {
                this.performQuickScan(query);
            } else {
                this.performDeepSearch(query);
            }
        }

        performQuickScan(query) {
            this.showQuickScanLoading();
            
            // DOM Analysis for Quick Scan
            this.analyzeDOMForQuery(query).then((domAnalysis) => {
                return this.submitQuickScanQuery(query, domAnalysis);
            }).then((response) => {
                this.displayQuickResults(response);
                this.showDeepSearchOption(query);
            }).catch((error) => {
                console.error('Quick scan error:', error);
                this.showError('Quick scan failed: ' + (error.message || 'Unknown error'));
            }).finally(() => {
                this.isLoading = false;
                this.hideLoading();
            });
        }

        performDeepSearch(query) {
            this.showDeepSearchLoading();
            
            this.analyzeDOMForQuery(query).then((domAnalysis) => {
                return this.submitDeepSearchQuery(query, domAnalysis);
            }).then((response) => {
                if (response.data.status === 'initiated') {
                    this.startDeepSearchTracking(response.data.scan_id);
                } else {
                    this.displayDeepSearchComplete(response.data);
                }
            }).catch((error) => {
                console.error('Deep search error:', error);
                this.showError('Deep search failed: ' + (error.message || 'Unknown error'));
            }).finally(() => {
                this.isLoading = false;
            });
        }

        analyzeDOMForQuery(query) {
            return new Promise((resolve) => {
                try {
                    const domAnalysis = {
                        matching_elements: this.findMatchingElements(query),
                        page_structure: this.analyzePageStructure(),
                        detected_frameworks: this.detectFrameworks(),
                        navigation_analysis: this.analyzeNavigation(),
                        content_analysis: this.analyzeContent()
                    };
                    
                    resolve(domAnalysis);
                } catch (error) {
                    resolve({ error: 'DOM analysis unavailable' });
                }
            });
        }

        findMatchingElements(query) {
            const matches = [];
            const searchTerms = this.extractSearchTerms(query);
            
            // Search for exact text matches
            searchTerms.forEach(term => {
                // Search in all text content
                $('*').contents().filter(function() {
                    return this.nodeType === 3 && 
                           $(this).text().toLowerCase().includes(term.toLowerCase());
                }).each(function() {
                    const element = $(this).parent();
                    const elementInfo = {
                        text: $(this).text().trim(),
                        tag: element.prop('tagName')?.toLowerCase(),
                        classes: element.attr('class') || '',
                        id: element.attr('id') || '',
                        selector: this.generateSelector(element[0]),
                        parent_info: this.getParentInfo(element),
                        attributes: this.getElementAttributes(element),
                        match_type: 'text_content',
                        match_term: term,
                        confidence: this.calculateMatchConfidence(term, $(this).text())
                    };
                    matches.push(elementInfo);
                }.bind(this));

                // Search in buttons, links, and form elements specifically
                const interactiveSelectors = [
                    'button', 'a', 'input[type="button"]', 'input[type="submit"]',
                    '[role="button"]', '.btn', '.button'
                ];
                
                interactiveSelectors.forEach(selector => {
                    $(selector).each(function() {
                        const element = $(this);
                        const text = element.text() || element.val() || element.attr('title') || element.attr('alt') || '';
                        
                        if (text.toLowerCase().includes(term.toLowerCase())) {
                            matches.push({
                                text: text.trim(),
                                tag: element.prop('tagName')?.toLowerCase(),
                                classes: element.attr('class') || '',
                                id: element.attr('id') || '',
                                selector: this.generateSelector(element[0]),
                                parent_info: this.getParentInfo(element),
                                attributes: this.getElementAttributes(element),
                                match_type: 'interactive_element',
                                match_term: term,
                                confidence: this.calculateMatchConfidence(term, text),
                                element_type: selector
                            });
                        }
                    }.bind(this));
                });
            });

            // Sort by confidence and remove duplicates
            return this.dedupAndSortMatches(matches);
        }

        extractSearchTerms(query) {
            // Extract meaningful terms from the query
            const commonWords = ['the', 'a', 'an', 'is', 'are', 'where', 'what', 'how', 'button', 'link', 'menu', 'coming', 'from'];
            
            // Extract quoted strings first
            const quotedMatches = query.match(/"([^"]+)"/g) || [];
            const quotedTerms = quotedMatches.map(match => match.replace(/"/g, ''));
            
            // Extract remaining words
            let remainingQuery = query.replace(/"[^"]+"/g, '');
            const words = remainingQuery.toLowerCase()
                .replace(/[^\w\s]/g, ' ')
                .split(/\s+/)
                .filter(word => word.length > 2 && !commonWords.includes(word));

            return [...quotedTerms, ...words];
        }

        generateSelector(element) {
            if (!element) return '';
            
            let selector = element.tagName.toLowerCase();
            
            if (element.id) {
                selector += '#' + element.id;
            } else if (element.className) {
                const classes = element.className.toString().split(' ').filter(c => c);
                if (classes.length > 0) {
                    selector += '.' + classes.slice(0, 3).join('.');
                }
            }
            
            return selector;
        }

        getParentInfo(element) {
            const parent = element.parent();
            return {
                tag: parent.prop('tagName')?.toLowerCase() || '',
                classes: parent.attr('class') || '',
                id: parent.attr('id') || '',
                selector: parent.length ? this.generateSelector(parent[0]) : ''
            };
        }

        getElementAttributes(element) {
            const attrs = {};
            const importantAttrs = ['href', 'src', 'data-*', 'aria-*', 'role', 'type', 'name'];
            
            if (element[0]) {
                Array.from(element[0].attributes).forEach(attr => {
                    if (importantAttrs.some(pattern => 
                        pattern.includes('*') ? attr.name.startsWith(pattern.replace('*', '')) : attr.name === pattern
                    )) {
                        attrs[attr.name] = attr.value;
                    }
                });
            }
            
            return attrs;
        }

        calculateMatchConfidence(term, text) {
            const lowerTerm = term.toLowerCase();
            const lowerText = text.toLowerCase();
            
            if (lowerText === lowerTerm) return 1.0; // Exact match
            if (lowerText.includes(lowerTerm)) {
                const ratio = lowerTerm.length / lowerText.length;
                return Math.min(0.9, 0.5 + ratio * 0.4); // Partial match
            }
            
            // Fuzzy matching for typos
            const distance = this.levenshteinDistance(lowerTerm, lowerText);
            const maxLen = Math.max(lowerTerm.length, lowerText.length);
            const similarity = 1 - (distance / maxLen);
            
            return similarity > 0.7 ? similarity * 0.6 : 0;
        }

        levenshteinDistance(str1, str2) {
            const matrix = Array(str2.length + 1).fill().map(() => Array(str1.length + 1).fill(0));
            
            for (let i = 0; i <= str1.length; i++) matrix[0][i] = i;
            for (let j = 0; j <= str2.length; j++) matrix[j][0] = j;
            
            for (let j = 1; j <= str2.length; j++) {
                for (let i = 1; i <= str1.length; i++) {
                    const cost = str1[i - 1] === str2[j - 1] ? 0 : 1;
                    matrix[j][i] = Math.min(
                        matrix[j - 1][i] + 1,
                        matrix[j][i - 1] + 1,
                        matrix[j - 1][i - 1] + cost
                    );
                }
            }
            
            return matrix[str2.length][str1.length];
        }

        dedupAndSortMatches(matches) {
            // Remove duplicate selectors
            const seen = new Set();
            const unique = matches.filter(match => {
                const key = match.selector + match.text;
                if (seen.has(key)) return false;
                seen.add(key);
                return true;
            });
            
            // Sort by confidence, then by match type
            return unique.sort((a, b) => {
                if (b.confidence !== a.confidence) return b.confidence - a.confidence;
                
                const typeOrder = { 'interactive_element': 1, 'text_content': 2 };
                return (typeOrder[a.match_type] || 3) - (typeOrder[b.match_type] || 3);
            }).slice(0, 10); // Limit to top 10 matches
        }

        extractQueryContext(query) {
            const context = {
                intent: 'unknown',
                element_type: 'unknown',
                action_needed: 'locate'
            };
            
            const lowerQuery = query.toLowerCase();
            
            // Determine intent
            if (lowerQuery.includes('where') || lowerQuery.includes('coming from') || lowerQuery.includes('source')) {
                context.intent = 'locate_source';
            } else if (lowerQuery.includes('how') && (lowerQuery.includes('edit') || lowerQuery.includes('change'))) {
                context.intent = 'edit_instructions';
                context.action_needed = 'edit';
            } else if (lowerQuery.includes('what') && lowerQuery.includes('control')) {
                context.intent = 'identify_controller';
            }
            
            // Determine element type
            const elementTypes = {
                'button': ['button', 'btn'],
                'menu': ['menu', 'navigation', 'nav'],
                'header': ['header', 'title'],
                'footer': ['footer'],
                'sidebar': ['sidebar', 'widget'],
                'form': ['form', 'input', 'field'],
                'image': ['image', 'img', 'photo'],
                'text': ['text', 'content', 'paragraph']
            };
            
            for (const [type, keywords] of Object.entries(elementTypes)) {
                if (keywords.some(keyword => lowerQuery.includes(keyword))) {
                    context.element_type = type;
                    break;
                }
            }
            
            return context;
        }

        analyzePageStructure() {
            return {
                has_header: $('header, .header, #header').length > 0,
                has_footer: $('footer, .footer, #footer').length > 0,
                has_sidebar: $('.sidebar, #sidebar, .widget-area').length > 0,
                has_navigation: $('nav, .nav, .navigation, .menu').length > 0,
                main_content_selector: this.detectMainContent(),
                framework_classes: this.detectCSSFramework()
            };
        }

        detectMainContent() {
            const selectors = ['main', '#main', '.main', '#content', '.content', '.entry-content', 'article'];
            for (const selector of selectors) {
                if ($(selector).length > 0) return selector;
            }
            return 'body';
        }

        detectCSSFramework() {
            const frameworks = {
                bootstrap: ['container', 'row', 'col-', 'btn-'],
                foundation: ['grid-container', 'grid-x', 'cell'],
                bulma: ['columns', 'column', 'button'],
                tailwind: ['flex', 'grid', 'text-']
            };
            
            const detected = [];
            for (const [framework, classes] of Object.entries(frameworks)) {
                if (classes.some(cls => $(`.${cls}, [class*="${cls}"]`).length > 0)) {
                    detected.push(framework);
                }
            }
            
            return detected;
        }

        detectFrameworks() {
            const frameworks = {};
            
            // jQuery detection
            frameworks.jquery = typeof window.jQuery !== 'undefined';
            
            // React detection
            frameworks.react = !!document.querySelector('[data-reactroot], [data-react-helmet]') || 
                              window.React !== undefined;
            
            // Vue detection  
            frameworks.vue = !!document.querySelector('[data-v-]') || window.Vue !== undefined;
            
            // WordPress detection
            frameworks.wordpress = !!document.querySelector('body[class*="wp-"], #wpadminbar') ||
                                  window.wp !== undefined;
            
            return frameworks;
        }

        analyzeNavigation() {
            const menus = [];
            
            $('nav, .nav, .navigation, .menu, ul.menu').each(function() {
                const menu = $(this);
                const menuInfo = {
                    selector: this.generateSelector(menu[0]),
                    items: [],
                    classes: menu.attr('class') || '',
                    id: menu.attr('id') || ''
                };
                
                menu.find('a, button').each(function() {
                    const item = $(this);
                    menuInfo.items.push({
                        text: item.text().trim(),
                        href: item.attr('href') || '',
                        classes: item.attr('class') || '',
                        id: item.attr('id') || ''
                    });
                });
                
                if (menuInfo.items.length > 0) {
                    menus.push(menuInfo);
                }
            }.bind(this));
            
            return menus;
        }

        analyzeContent() {
            return {
                headings: this.extractHeadings(),
                forms: this.analyzeForms(),
                images: this.analyzeImages(),
                widgets: this.analyzeWidgets()
            };
        }

        extractHeadings() {
            const headings = [];
            $('h1, h2, h3, h4, h5, h6').each(function() {
                const heading = $(this);
                headings.push({
                    level: parseInt(heading.prop('tagName').charAt(1)),
                    text: heading.text().trim(),
                    selector: this.generateSelector(heading[0]),
                    classes: heading.attr('class') || ''
                });
            }.bind(this));
            return headings;
        }

        analyzeForms() {
            const forms = [];
            $('form').each(function() {
                const form = $(this);
                const formInfo = {
                    action: form.attr('action') || '',
                    method: form.attr('method') || 'get',
                    selector: this.generateSelector(form[0]),
                    fields: []
                };
                
                form.find('input, textarea, select, button').each(function() {
                    const field = $(this);
                    formInfo.fields.push({
                        type: field.attr('type') || field.prop('tagName').toLowerCase(),
                        name: field.attr('name') || '',
                        placeholder: field.attr('placeholder') || '',
                        text: field.text() || field.val() || ''
                    });
                });
                
                forms.push(formInfo);
            }.bind(this));
            return forms;
        }

        analyzeImages() {
            const images = [];
            $('img').each(function() {
                const img = $(this);
                images.push({
                    src: img.attr('src') || '',
                    alt: img.attr('alt') || '',
                    classes: img.attr('class') || '',
                    selector: this.generateSelector(img[0])
                });
            }.bind(this));
            return images.slice(0, 20); // Limit to first 20 images
        }

        analyzeWidgets() {
            const widgets = [];
            $('.widget, [class*="widget"]').each(function() {
                const widget = $(this);
                widgets.push({
                    classes: widget.attr('class') || '',
                    id: widget.attr('id') || '',
                    title: widget.find('.widget-title, h3, h4').first().text().trim(),
                    selector: this.generateSelector(widget[0])
                });
            }.bind(this));
            return widgets;
        }

        scanPageSources() {
            return new Promise((resolve, reject) => {
                $.ajax({
                    url: wsaAiDetective.ajaxUrl,
                    type: 'POST',
                    data: {
                        action: 'wsa_ai_detective_scan',
                        nonce: wsaAiDetective.nonce,
                        pageUrl: wsaAiDetective.currentUrl,
                        pageId: wsaAiDetective.pageId
                    },
                    success: (response) => {
                        if (response.success) {
                            resolve(response.data);
                        } else {
                            reject(new Error(response.data.message || 'Scan failed'));
                        }
                    },
                    error: (xhr, status, error) => {
                        reject(new Error(`Network error: ${error}`));
                    }
                });
            });
        }

        /**
         * TWO-TIER SYSTEM AJAX METHODS
         */

        submitQuickScanQuery(query, domAnalysis) {
            return new Promise((resolve, reject) => {
                $.ajax({
                    url: wsaAiDetective.ajaxUrl,
                    type: 'POST',
                    data: {
                        action: 'wsa_ai_detective_query',
                        nonce: wsaAiDetective.nonce,
                        query: query,
                        pageUrl: wsaAiDetective.currentUrl,
                        pageId: wsaAiDetective.pageId,
                        scanType: 'quick',
                        domAnalysis: JSON.stringify(domAnalysis)
                    },
                    success: (response) => {
                        if (response.success) {
                            resolve(response);
                        } else {
                            reject(new Error(response.data.message || 'Quick scan failed'));
                        }
                    },
                    error: (xhr, status, error) => {
                        reject(new Error(`Network error: ${error}`));
                    }
                });
            });
        }

        submitDeepSearchQuery(query, domAnalysis) {
            return new Promise((resolve, reject) => {
                $.ajax({
                    url: wsaAiDetective.ajaxUrl,
                    type: 'POST',
                    data: {
                        action: 'wsa_ai_detective_query',
                        nonce: wsaAiDetective.nonce,
                        query: query,
                        pageUrl: wsaAiDetective.currentUrl,
                        pageId: wsaAiDetective.pageId,
                        scanType: 'deep',
                        domAnalysis: JSON.stringify(domAnalysis)
                    },
                    success: (response) => {
                        if (response.success) {
                            resolve(response);
                        } else {
                            reject(new Error(response.data.message || 'Deep search failed'));
                        }
                    },
                    error: (xhr, status, error) => {
                        reject(new Error(`Network error: ${error}`));
                    }
                });
            });
        }

        // Legacy method for backward compatibility
        submitAIQuery(query, scanResults) {
            return this.submitQuickScanQuery(query, scanResults);
        }

        /**
         * LOADING STATES FOR TWO-TIER SYSTEM
         */

        showQuickScanLoading() {
            $('#wsa-detective-results, #wsa-detective-error').hide();
            $('#wsa-detective-loading').show();
            $('.wsa-detective-loading p').text('Quick scan in progress... (<5s)');
            $('#wsa-detective-submit').prop('disabled', true);
        }

        showDeepSearchLoading() {
            $('#wsa-detective-loading').show();
            $('.wsa-detective-loading p').text('Initiating deep search... (5-10 minutes)');
            this.createDeepSearchControls();
        }

        // Legacy method
        showLoading() {
            this.showQuickScanLoading();
        }

        hideLoading() {
            $('#wsa-detective-loading').hide();
            $('#wsa-detective-submit').prop('disabled', false);
        }

        /**
         * DEEP SEARCH PROGRESS TRACKING
         */

        startDeepSearchTracking(scanId) {
            this.currentScan = scanId;
            this.createProgressInterface();
            this.pollDeepSearchProgress();
        }

        createProgressInterface() {
            const progressHTML = `
                <div class="wsa-deep-search-progress">
                    <h4><span class="dashicons dashicons-search"></span> Deep Search in Progress</h4>
                    <div class="wsa-progress-bar">
                        <div class="wsa-progress-fill" style="width: 0%"></div>
                        <span class="wsa-progress-text">0%</span>
                    </div>
                    <div class="wsa-current-task">Initializing...</div>
                    <div class="wsa-scan-controls">
                        <button class="button wsa-pause-scan">Pause</button>
                        <button class="button wsa-cancel-scan">Cancel</button>
                    </div>
                    <div class="wsa-partial-results">
                        <h5>Found So Far:</h5>
                        <div class="wsa-partial-results-list"></div>
                    </div>
                </div>
            `;
            
            $('#wsa-detective-results').html(progressHTML).show();
            
            // Bind control events
            $('.wsa-pause-scan').on('click', () => this.pauseDeepSearch());
            $('.wsa-cancel-scan').on('click', () => this.cancelDeepSearch());
        }

        pollDeepSearchProgress() {
            if (!this.currentScan) return;
            
            $.ajax({
                url: wsaAiDetective.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'wsa_ai_detective_deep_progress',
                    nonce: wsaAiDetective.nonce,
                    scanId: this.currentScan
                },
                success: (response) => {
                    if (response.success) {
                        this.updateProgressInterface(response.data);
                        
                        if (response.data.status === 'in_progress') {
                            setTimeout(() => this.pollDeepSearchProgress(), 3000); // Poll every 3s
                        } else if (response.data.status === 'completed') {
                            this.displayDeepSearchComplete(response.data);
                        } else if (response.data.status === 'error') {
                            this.showError('Deep search failed: ' + response.data.error);
                        }
                    }
                },
                error: (xhr, status, error) => {
                    console.error('Progress polling failed:', error);
                    setTimeout(() => this.pollDeepSearchProgress(), 5000); // Retry in 5s
                }
            });
        }

        updateProgressInterface(data) {
            $('.wsa-progress-fill').css('width', data.progress + '%');
            $('.wsa-progress-text').text(data.progress + '%');
            $('.wsa-current-task').text(data.current_task);
            
            // Update partial results
            if (data.results && data.results.length > 0) {
                this.updatePartialResults(data.results);
            }
        }

        updatePartialResults(results) {
            const $list = $('.wsa-partial-results-list');
            $list.empty();
            
            // Show latest 5 results
            const recentResults = results.slice(-5);
            
            recentResults.forEach(result => {
                const item = $(`
                    <div class="wsa-partial-result-item">
                        <span class="result-type">${this.getResultTypeIcon(result.type)}</span>
                        <span class="result-description">${this.formatResultDescription(result)}</span>
                        <span class="confidence-badge">${Math.round((result.confidence || 0) * 100)}%</span>
                    </div>
                `);
                $list.append(item);
            });
        }

        pauseDeepSearch() {
            this.controlDeepSearch('pause');
            $('.wsa-pause-scan').text('Resume').removeClass('wsa-pause-scan').addClass('wsa-resume-scan');
            $('.wsa-resume-scan').off('click').on('click', () => this.resumeDeepSearch());
        }

        resumeDeepSearch() {
            this.controlDeepSearch('resume');
            $('.wsa-resume-scan').text('Pause').removeClass('wsa-resume-scan').addClass('wsa-pause-scan');
            $('.wsa-pause-scan').off('click').on('click', () => this.pauseDeepSearch());
        }

        cancelDeepSearch() {
            if (confirm('Are you sure you want to cancel the deep search?')) {
                this.controlDeepSearch('cancel');
                this.currentScan = null;
                $('#wsa-detective-results').html('<p>Deep search cancelled.</p>');
            }
        }

        controlDeepSearch(action) {
            $.ajax({
                url: wsaAiDetective.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'wsa_ai_detective_deep_control',
                    nonce: wsaAiDetective.nonce,
                    scanId: this.currentScan,
                    action: action
                },
                success: (response) => {
                    if (response.success) {
                        if (action === 'resume') {
                            this.pollDeepSearchProgress();
                        }
                    }
                }
            });
        }

        displayResults(data) {
            $('#wsa-detective-error').hide();
            
            // Handle new structured response format
            const response = data.response || {};
            
            // Display AI response with confidence indicator
            if (response.text) {
                const confidenceClass = this.getConfidenceClass(response.confidence || 0);
                const confidenceText = this.getConfidenceText(response.confidence || 0);
                
                let responseHtml = `
                    <div class="wsa-detective-response-wrapper ${confidenceClass}">
                        <div class="wsa-detective-confidence-indicator">
                            <span class="confidence-score">Confidence: ${confidenceText}</span>
                        </div>
                        <div class="wsa-detective-response-text">
                            ${this.formatResponse(response.text)}
                        </div>
                    </div>
                `;
                
                $('#wsa-detective-ai-response').html(responseHtml);
            }

            // Display primary source with prominence
            if (response.primary_source) {
                this.displayPrimarySource(response.primary_source);
            }

            // Display detected elements
            if (response.detected_elements && response.detected_elements.length > 0) {
                this.displayDetectedElements(response.detected_elements);
            }

            // Display alternative sources
            if (response.alternative_sources && response.alternative_sources.length > 0) {
                this.displayAlternativeSources(response.alternative_sources);
            }

            // Display actions
            if (response.actions && response.actions.length > 0) {
                this.displayActions(response.actions);
            }

            // Fallback to old format for backward compatibility
            if (response.sources && !response.primary_source) {
                this.displaySources(response.sources);
            }

            $('#wsa-detective-results').show();
        }

        /**
         * DISPLAY METHODS FOR TWO-TIER SYSTEM
         */

        displayQuickResults(response) {
            $('#wsa-detective-error').hide();
            
            const data = response.data;
            const scanTime = data.scan_time || 'instant';
            
            // Store current results for export
            this.currentScan = data;
            
            // Quick scan header
            const quickHeader = `
                <div class="wsa-quick-scan-header">
                    <h4><span class="dashicons dashicons-performance"></span> Quick Scan Results</h4>
                    <div class="wsa-scan-meta">
                        <span class="scan-time">Completed in ${scanTime}s</span>
                        <span class="confidence-indicator confidence-${this.getConfidenceClass(data.confidence)}">
                            Confidence: ${Math.round((data.confidence || 0) * 100)}%
                        </span>
                    </div>
                </div>
            `;

            // Primary source from quick scan
            let resultsHTML = quickHeader;
            
            if (data.primary_source) {
                resultsHTML += this.formatPrimarySource(data.primary_source, 'quick');
            }

            // Quick scan results summary
            resultsHTML += this.formatQuickScanResults(data.results);

            // AI Analysis
            if (data.ai_analysis) {
                resultsHTML += `
                    <div class="wsa-ai-analysis">
                        <h5><span class="dashicons dashicons-admin-comments"></span> AI Analysis</h5>
                        <div class="ai-response">${this.formatResponse(data.ai_analysis.analysis)}</div>
                    </div>
                `;
            }

            // Add export options for quick scan
            resultsHTML += this.createExportOptions(data, 'quick');

            $('#wsa-detective-results').html(resultsHTML).show();
        }

        showDeepSearchOption(query) {
            if ($('.wsa-deep-search-option').length > 0) return; // Already shown
            
            const deepSearchHTML = `
                <div class="wsa-deep-search-option">
                    <div class="wsa-deep-search-prompt">
                        <h5><span class="dashicons dashicons-search"></span> Want More Detailed Results?</h5>
                        <p>Run a Deep Search for comprehensive analysis of all theme files, plugins, database, and page builders.</p>
                        <div class="wsa-deep-search-controls">
                            <button class="button button-primary wsa-start-deep-search">
                                <span class="dashicons dashicons-admin-tools"></span>
                                Start Deep Search (5-10 min)
                            </button>
                            <div class="wsa-deep-search-info">
                                <small>Comprehensive analysis • Live progress updates • Cancellable anytime</small>
                            </div>
                        </div>
                    </div>
                </div>
            `;
            
            $('#wsa-detective-results').append(deepSearchHTML);
            
            // Bind deep search button
            $('.wsa-start-deep-search').on('click', () => {
                $('.wsa-deep-search-option').fadeOut();
                this.performDeepSearch(query);
            });
        }

        displayDeepSearchComplete(data) {
            // Store current results for export
            this.currentScan = data;
            
            // Clear any existing progress display first
            $('.wsa-deep-search-progress').hide();
            
            // Count actual results (exclude AI analysis)
            const actualResults = data.results.filter(r => r.type !== 'ai_analysis');
            
            const completionHTML = `
                <div class="wsa-deep-search-complete">
                    <h4><span class="dashicons dashicons-yes-alt"></span> Deep Search Complete</h4>
                    <div class="wsa-scan-summary">
                        <span class="total-time">Completed in ${this.formatScanTime(data.scan_time)}</span>
                        <span class="total-results">${actualResults.length} sources analyzed</span>
                    </div>
                </div>
            `;

            let resultsHTML = completionHTML;

            // Comprehensive results display
            resultsHTML += this.formatDeepSearchResults(data.results);

            // Final AI analysis
            const aiAnalysisResult = data.results.find(r => r.type === 'ai_analysis');
            if (aiAnalysisResult && aiAnalysisResult.analysis) {
                resultsHTML += `
                    <div class="wsa-comprehensive-analysis">
                        <h5><span class="dashicons dashicons-admin-comments"></span> AI Recommendations</h5>
                        <div class="ai-response">${this.formatResponse(aiAnalysisResult.analysis)}</div>
                    </div>
                `;
            }

            // Export options
            resultsHTML += this.createExportOptions(data, 'deep');

            $('#wsa-detective-results').html(resultsHTML);
            
            // Scroll to results
            $('#wsa-detective-results')[0].scrollIntoView({ behavior: 'smooth' });
        }

        formatQuickScanResults(results) {
            let html = '<div class="wsa-quick-results-grid">';
            
            Object.keys(results).forEach(sourceType => {
                const sourceData = results[sourceType];
                if (sourceData.found) {
                    const matches = sourceData.matches || sourceData.elements || [];
                    
                    html += `
                        <div class="wsa-quick-result-card">
                            <h6><span class="dashicons dashicons-${this.getSourceIcon(sourceType)}"></span> 
                                ${this.getSourceTypeLabel(sourceType)}
                            </h6>
                            <div class="match-count">${matches.length} found</div>
                            <div class="wsa-quick-matches">
                    `;
                    
                    matches.slice(0, 3).forEach(match => {
                        html += `
                            <div class="quick-match-item">
                                <span class="match-text">${this.truncateText(this.getMatchText(match), 40)}</span>
                                <span class="confidence-badge confidence-${this.getConfidenceClass(match.confidence || 0)}">
                                    ${Math.round((match.confidence || 0) * 100)}%
                                </span>
                            </div>
                        `;
                    });
                    
                    if (matches.length > 3) {
                        html += `<div class="more-matches">+${matches.length - 3} more</div>`;
                    }
                    
                    html += '</div></div>';
                }
            });
            
            html += '</div>';
            return html;
        }

        formatDeepSearchResults(results) {
            // Use prioritized results if available
            let prioritizedResults = results;
            const aiAnalysisResult = results.find(r => r.type === 'ai_analysis');
            if (aiAnalysisResult && aiAnalysisResult.prioritized_results) {
                prioritizedResults = aiAnalysisResult.prioritized_results;
            }
            
            // Check for branding audit results
            const brandingResult = results.find(r => r.type === 'branding_audit');
            
            let html = '<div class="wsa-prioritized-results">';
            
            // Display branding audit if available
            if (brandingResult) {
                html += this.formatBrandingAudit(brandingResult);
            }
            
            // Filter out AI analysis and branding audit from display results 
            const displayResults = prioritizedResults.filter(r => r.type !== 'ai_analysis' && r.type !== 'branding_audit');
            
            if (displayResults.length === 0 && !brandingResult) {
                return '<div class="wsa-no-results">No relevant matches found.</div>';
            }
            
            if (displayResults.length === 0) {
                html += '</div>';
                return html;
            }
            
            // Show top 3 results prominently
            const topResults = displayResults.slice(0, 3);
            if (topResults.length > 0) {
                html += `
                    <div class="wsa-top-matches">
                        <h5><span class="dashicons dashicons-star-filled"></span> Best Matches</h5>
                `;
                
                topResults.forEach((result, index) => {
                    html += this.formatPrimaryResultItem(result, index + 1);
                });
                
                html += '</div>';
            }
            
            // Show remaining results grouped by type (if any)
            const remainingResults = displayResults.slice(3);
            if (remainingResults.length > 0) {
                const groupedResults = this.groupResultsByType(remainingResults);
                
                html += '<div class="wsa-additional-results">';
                html += `<h6 class="additional-header">Additional Sources (${remainingResults.length})</h6>`;
                
                Object.keys(groupedResults).forEach(type => {
                    const typeResults = groupedResults[type];
                    if (typeResults.length === 0) return;
                    
                    html += `<div class="wsa-result-group">`;
                    html += `<div class="group-header">${this.getSourceTypeLabel(type)} (${typeResults.length})</div>`;
                    
                    typeResults.forEach(result => {
                        html += this.formatCompactResultItem(result);
                    });
                    
                    html += '</div>';
                });
                
                html += '</div>';
            }
            
            html += '</div>';
            return html;
        }

        formatPrimaryResultItem(result, rank) {
            const fileName = this.getCleanFileName(result.file || result.table || result.builder || result.type);
            const confidence = Math.round((result.confidence || 0) * 100);
            
            let html = `
                <div class="wsa-primary-result-item">
                    <div class="primary-result-header">
                        <div class="result-rank">#${rank}</div>
                        <div class="result-info">
                            <div class="result-title">
                                <span class="result-icon">${this.getResultTypeIcon(result.type)}</span>
                                <strong>${fileName}</strong>
                            </div>
                            <div class="result-confidence">
                                <span class="confidence-badge confidence-${this.getConfidenceClass(result.confidence || 0)}">
                                    ${confidence}% Match
                                </span>
                            </div>
                        </div>
            `;

            if (result.edit_link) {
                html += `
                    <div class="result-actions">
                        <a href="${result.edit_link}" class="wsa-edit-button" target="_blank">
                            <span class="dashicons dashicons-edit"></span> Edit
                        </a>
                    </div>
                `;
            }

            html += '</div>';

            // Show relevant matches/content
            if (result.matches && result.matches.length > 0) {
                html += '<div class="result-details">';
                result.matches.slice(0, 2).forEach(match => {
                    html += `<div class="match-detail">Line ${match.line}: ${this.truncateText(match.content, 100)}</div>`;
                });
                html += '</div>';
            } else if (result.content_preview) {
                html += `<div class="result-details">${this.truncateText(result.content_preview, 120)}</div>`;
            }

            html += '</div>';
            return html;
        }

        formatBrandingAudit(brandingResult) {
            const audit = brandingResult;
            const consistencyScore = audit.consistency_score || 0;
            const consistencyClass = consistencyScore >= 80 ? 'excellent' : 
                                   consistencyScore >= 60 ? 'good' : 
                                   consistencyScore >= 40 ? 'fair' : 'poor';
            
            let html = `
                <div class="wsa-branding-audit">
                    <div class="branding-audit-header">
                        <h5><span class="dashicons dashicons-admin-appearance"></span> Brand & CSS Audit</h5>
                        <div class="consistency-score ${consistencyClass}">
                            <span class="score-label">Brand Consistency</span>
                            <span class="score-value">${consistencyScore}/100</span>
                        </div>
                    </div>
            `;

            // Color Palette Section
            if (audit.color_palette) {
                html += this.formatColorPalette(audit.color_palette);
            }

            // Typography Section
            if (audit.typography) {
                html += this.formatTypographyAnalysis(audit.typography);
            }

            // Recommendations Section
            if (audit.recommendations) {
                html += this.formatBrandingRecommendations(audit.recommendations);
            }

            // Brand Imagery Section
            if (audit.brand_imagery) {
                html += this.formatBrandImagery(audit.brand_imagery);
            }

            html += '</div>';
            return html;
        }

        formatColorPalette(colorPalette) {
            let html = `
                <div class="wsa-color-section">
                    <h6><span class="dashicons dashicons-art"></span> Color Palette</h6>
                    <div class="color-stats">
                        <span class="stat-item">
                            <strong>${colorPalette.color_statistics.total_unique_colors}</strong> unique colors
                        </span>
                        <span class="stat-item">
                            <strong>${Object.keys(colorPalette.primary_colors || {}).length}</strong> primary colors
                        </span>
                    </div>
            `;

            // Display primary colors
            if (colorPalette.primary_colors && Object.keys(colorPalette.primary_colors).length > 0) {
                html += '<div class="primary-colors">';
                
                Object.values(colorPalette.primary_colors).slice(0, 8).forEach(color => {
                    const usage = color.total_frequency || 0;
                    html += `
                        <div class="color-swatch" title="${color.color_name || color.hex}: Used ${usage} times">
                            <div class="color-preview" style="background-color: ${color.hex}"></div>
                            <div class="color-info">
                                <div class="color-hex">${color.hex}</div>
                                <div class="color-usage">${usage}x</div>
                            </div>
                        </div>
                    `;
                });
                
                html += '</div>';
            }

            html += '</div>';
            return html;
        }

        formatTypographyAnalysis(typography) {
            let html = `
                <div class="wsa-typography-section">
                    <h6><span class="dashicons dashicons-editor-textcolor"></span> Typography</h6>
                    <div class="typography-stats">
                        <span class="stat-item">
                            <strong>${Object.keys(typography.font_families || {}).length}</strong> font families
                        </span>
            `;

            // Count Google Fonts and Web Safe fonts
            const fontFamilies = typography.font_families || {};
            const googleFonts = Object.values(fontFamilies).filter(font => font.google_font).length;
            const webSafeFonts = Object.values(fontFamilies).filter(font => font.is_web_safe).length;

            html += `
                        <span class="stat-item">
                            <strong>${googleFonts}</strong> Google Fonts
                        </span>
                        <span class="stat-item">
                            <strong>${webSafeFonts}</strong> web-safe
                        </span>
                    </div>
            `;

            // Display primary fonts
            if (Object.keys(fontFamilies).length > 0) {
                html += '<div class="font-families">';
                
                Object.values(fontFamilies).slice(0, 5).forEach(font => {
                    const categoryIcon = font.font_category === 'Serif' ? 'editor-italic' : 'editor-paragraph';
                    const statusClass = font.is_web_safe ? 'web-safe' : (font.google_font ? 'google-font' : 'custom-font');
                    
                    html += `
                        <div class="font-family-item ${statusClass}">
                            <div class="font-header">
                                <span class="dashicons dashicons-${categoryIcon}"></span>
                                <strong>${font.primary_font}</strong>
                                <span class="font-category">${font.font_category}</span>
                            </div>
                            <div class="font-usage">Used ${font.usage_count} times</div>
                        </div>
                    `;
                });
                
                html += '</div>';
            }

            html += '</div>';
            return html;
        }

        formatBrandingRecommendations(recommendations) {
            const allRecommendations = [
                ...(recommendations.color_recommendations || []),
                ...(recommendations.typography_recommendations || []),
                ...(recommendations.design_recommendations || [])
            ];

            if (allRecommendations.length === 0) {
                return '<div class="wsa-recommendations-section"><div class="no-recommendations">✅ No major branding issues found!</div></div>';
            }

            let html = `
                <div class="wsa-recommendations-section">
                    <h6><span class="dashicons dashicons-lightbulb"></span> Recommendations</h6>
            `;

            // Priority actions first
            if (recommendations.priority_actions && recommendations.priority_actions.length > 0) {
                html += '<div class="priority-actions">';
                recommendations.priority_actions.forEach(action => {
                    const priorityIcon = action.priority === 'high' ? 'warning' : action.priority === 'medium' ? 'info' : 'flag';
                    html += `
                        <div class="recommendation-item priority-${action.priority}">
                            <div class="rec-icon"><span class="dashicons dashicons-${priorityIcon}"></span></div>
                            <div class="rec-content">
                                <strong>${action.title}</strong>
                                <p>${action.description}</p>
                                <div class="rec-action">${action.action}</div>
                            </div>
                        </div>
                    `;
                });
                html += '</div>';
            }

            html += '</div>';
            return html;
        }

        formatBrandImagery(brandImagery) {
            let html = `
                <div class="wsa-imagery-section">
                    <h6><span class="dashicons dashicons-format-image"></span> Brand Assets</h6>
            `;

            // Custom Logo
            if (brandImagery.custom_logo) {
                const logo = brandImagery.custom_logo;
                html += `
                    <div class="brand-asset">
                        <div class="asset-type">Custom Logo</div>
                        <div class="asset-info">
                            ${logo.width}×${logo.height}px
                            ${logo.file_size ? `• ${this.formatFileSize(logo.file_size)}` : ''}
                        </div>
                        <a href="${logo.edit_link}" class="asset-edit-link" target="_blank">
                            <span class="dashicons dashicons-edit"></span> Edit
                        </a>
                    </div>
                `;
            }

            // Favicons
            if (brandImagery.favicons && brandImagery.favicons.length > 0) {
                html += `
                    <div class="brand-asset">
                        <div class="asset-type">Favicons</div>
                        <div class="asset-info">${brandImagery.favicons.length} favicon files detected</div>
                    </div>
                `;
            }

            // Icon Fonts
            if (brandImagery.icon_fonts && brandImagery.icon_fonts.length > 0) {
                html += `
                    <div class="brand-asset">
                        <div class="asset-type">Icon Fonts</div>
                        <div class="asset-info">
                            ${brandImagery.icon_fonts.map(icon => icon.type).join(', ')}
                        </div>
                    </div>
                `;
            }

            html += '</div>';
            return html;
        }

        formatCompactResultItem(result) {
            const fileName = this.getCleanFileName(result.file || result.table || result.builder || result.type);
            const confidence = Math.round((result.confidence || 0) * 100);
            
            let html = `
                <div class="wsa-compact-result-item">
                    <div class="compact-result-info">
                        <span class="compact-title">${fileName}</span>
                        <span class="compact-confidence">${confidence}%</span>
            `;

            if (result.edit_link) {
                html += `<a href="${result.edit_link}" class="compact-edit-link" target="_blank">Edit</a>`;
            }

            html += '</div>';

            // Show brief match info
            if (result.matches && result.matches.length > 0) {
                const match = result.matches[0];
                html += `<div class="compact-match">Line ${match.line}: ${this.truncateText(match.content, 60)}</div>`;
            }

            html += '</div>';
            return html;
        }

        formatDeepResultItem(result) {
            // Legacy method - now uses compact format
            return this.formatCompactResultItem(result);
        }

        getCleanFileName(filePath) {
            if (!filePath) return 'Unknown Source';
            
            // Remove packaged theme paths
            let clean = filePath.replace(/.*\/packaged\/[^\/]+\//, '');
            
            // Remove theme prefix
            clean = clean.replace(/^theme\/\//, '');
            
            // Get just the filename if it's a long path
            if (clean.length > 40) {
                const parts = clean.split('/');
                return parts[parts.length - 1];
            }
            
            return clean;
        }

        createExportOptions(data, scanType = 'deep') {
            const query = $('#wsa-detective-query').val() || 'site-analysis';
            return `
                <div class="wsa-export-options">
                    <h5><span class="dashicons dashicons-download"></span> Export Results</h5>
                    <div class="export-buttons">
                        <button class="button wsa-export-btn" data-format="csv" data-scan-type="${scanType}" data-query="${query}">
                            <span class="dashicons dashicons-media-spreadsheet"></span> Export CSV
                        </button>
                        <button class="button wsa-export-btn" data-format="json" data-scan-type="${scanType}" data-query="${query}">
                            <span class="dashicons dashicons-media-code"></span> Export JSON
                        </button>
                    </div>
                    <div class="export-status" style="display: none;"></div>
                </div>
            `;
        }

        /**
         * HELPER METHODS FOR TWO-TIER SYSTEM
         */

        getResultTypeIcon(type) {
            const icons = {
                'theme_file': '📄',
                'plugin': '🔌',
                'database': '💾',
                'page_builder': '🎨',
                'menu_item': '📝',
                'widget': '🧩'
            };
            return icons[type] || '📄';
        }

        formatResultDescription(result) {
            switch (result.type) {
                case 'theme_file':
                    return `Found in ${result.file || 'theme file'}`;
                case 'plugin':
                    return `Found in ${result.plugin || 'plugin'}`;
                case 'database':
                    return `Found in ${result.table || 'database'}`;
                case 'page_builder':
                    return `Found in ${result.builder || 'page builder'}`;
                default:
                    return result.description || 'Match found';
            }
        }

        getSourceTypeLabel(type) {
            const labels = {
                'dom_matches': 'DOM Elements',
                'menu_matches': 'WordPress Menus', 
                'template_matches': 'Theme Templates',
                'widget_matches': 'Widgets',
                'shortcode_matches': 'Shortcodes',
                'theme_file': 'Theme Files',
                'plugin': 'Plugins',
                'database': 'Database',
                'page_builder': 'Page Builders'
            };
            return labels[type] || type.replace('_', ' ').replace(/\b\w/g, l => l.toUpperCase());
        }

        getMatchText(match) {
            return match.text || match.title || match.item_title || match.shortcode || match.content || 'Match found';
        }

        groupResultsByType(results) {
            const grouped = {};
            
            results.forEach(result => {
                const type = result.type || 'unknown';
                if (!grouped[type]) grouped[type] = [];
                grouped[type].push(result);
            });

            return grouped;
        }

        formatScanTime(seconds) {
            if (seconds > 60) {
                const mins = Math.floor(seconds / 60);
                const secs = Math.round(seconds % 60);
                return `${mins}m ${secs}s`;
            }
            return `${Math.round(seconds)}s`;
        }

        truncateText(text, maxLength) {
            if (text.length <= maxLength) return text;
            return text.substr(0, maxLength) + '...';
        }

        createDeepSearchControls() {
            // Implementation handled by createProgressInterface
            return true;
        }

        formatPrimarySource(primarySource, scanType = 'quick') {
            const confidenceClass = this.getConfidenceClass(primarySource.confidence || 0);
            const scanTypeLabel = scanType === 'quick' ? 'Quick Scan' : 'Deep Search';
            
            return `
                <div class="wsa-detective-primary-source ${scanType}-scan">
                    <h4 class="primary-source-header">
                        <span class="dashicons dashicons-star-filled"></span>
                        Primary Source Found (${scanTypeLabel})
                    </h4>
                    <div class="wsa-detective-source-item wsa-detective-source-${primarySource.type || 'unknown'} primary ${confidenceClass}">
                        <div class="wsa-detective-source-header">
                            <span class="dashicons dashicons-${this.getSourceIcon(primarySource.type || 'unknown')}"></span>
                            <strong>${primarySource.name || primarySource.type || 'Source Found'}</strong>
                            ${primarySource.edit_link ? `<a href="${primarySource.edit_link}" class="wsa-detective-edit-link button-primary" target="_blank">
                                <span class="dashicons dashicons-edit"></span> Edit Now
                            </a>` : ''}
                        </div>
                        <div class="wsa-detective-source-description">
                            ${primarySource.description || primarySource.location || 'Source identified with high confidence'}
                        </div>
                        ${primarySource.details ? `<div class="wsa-detective-source-details">${primarySource.details}</div>` : ''}
                    </div>
                </div>
            `;
        }

        // Add missing export functionality
        bindExportEvents() {
            $(document).on('click', '.wsa-export-csv', (e) => {
                const results = JSON.parse($(e.target).data('results'));
                this.exportToCsv(results);
            });

            $(document).on('click', '.wsa-export-json', (e) => {
                const results = JSON.parse($(e.target).data('results'));
                this.exportToJson(results);
            });
        }

        exportToCsv(results) {
            const headers = ['Type', 'Source', 'Confidence', 'Description'];
            const rows = [headers];

            results.forEach(result => {
                rows.push([
                    result.type || 'Unknown',
                    result.file || result.table || result.builder || result.source || 'N/A',
                    Math.round((result.confidence || 0) * 100) + '%',
                    this.formatResultDescription(result)
                ]);
            });

            const csvContent = rows.map(row => row.map(cell => `"${cell}"`).join(',')).join('\n');
            this.downloadFile(csvContent, 'site-detective-results.csv', 'text/csv');
        }

        exportToJson(results) {
            const jsonContent = JSON.stringify({
                export_date: new Date().toISOString(),
                site_url: window.location.href,
                results: results
            }, null, 2);
            
            this.downloadFile(jsonContent, 'site-detective-results.json', 'application/json');
        }

        downloadFile(content, filename, mimeType) {
            const blob = new Blob([content], { type: mimeType });
            const url = window.URL.createObjectURL(blob);
            const link = document.createElement('a');
            link.href = url;
            link.download = filename;
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
            window.URL.revokeObjectURL(url);
        }

        // Enhanced initialization with export events
        init() {
            this.bindEvents();
            this.bindExportEvents();
            this.setupModal();
        }

        displaySources(sources) {
            const sourcesList = $('#wsa-detective-sources-list');
            sourcesList.empty();

            if (!sources || sources.length === 0) {
                sourcesList.html('<p>' + wsaAiDetective.strings.noResults + '</p>');
                return;
            }

            sources.forEach(source => {
                const icon = this.getSourceIcon(source.type);
                const editLink = source.edit_link ? 
                    `<a href="${source.edit_link}" target="_blank" class="wsa-detective-edit-link">
                        <span class="dashicons dashicons-edit"></span> Edit
                    </a>` : '';

                const sourceHtml = `
                    <div class="wsa-detective-source-item wsa-detective-source-${source.type}">
                        <div class="wsa-detective-source-header">
                            <span class="dashicons dashicons-${icon}"></span>
                            <strong>${source.title}</strong>
                            ${editLink}
                        </div>
                        <div class="wsa-detective-source-description">
                            ${source.description}
                        </div>
                    </div>
                `;

                sourcesList.append(sourceHtml);
            });
        }

        displayActions(actions) {
            const actionsList = $('#wsa-detective-actions-list');
            actionsList.empty();

            if (!actions || actions.length === 0) {
                actionsList.html('<p>No quick actions available.</p>');
                return;
            }

            actions.forEach(action => {
                const icon = action.icon || 'admin-tools';
                const actionHtml = `
                    <button class="wsa-detective-action-btn button" data-url="${action.url}">
                        <span class="dashicons dashicons-${icon}"></span>
                        ${action.title}
                    </button>
                `;

                actionsList.append(actionHtml);
            });
        }

        /**
         * Display primary source with prominence
         */
        displayPrimarySource(primarySource) {
            const sourcesList = $('#wsa-detective-sources-list');
            
            if (!primarySource) {
                sourcesList.html('<p>No primary source identified.</p>');
                return;
            }

            const icon = this.getSourceIcon(primarySource.type);
            const editLink = primarySource.edit_link ? 
                `<a href="${primarySource.edit_link}" target="_blank" class="wsa-detective-edit-link button-primary">
                    <span class="dashicons dashicons-edit"></span> Edit Now
                </a>` : '';

            const primaryHtml = `
                <div class="wsa-detective-primary-source">
                    <h4 class="primary-source-header">
                        <span class="dashicons dashicons-star-filled"></span>
                        Primary Source Found
                    </h4>
                    <div class="wsa-detective-source-item wsa-detective-source-${primarySource.type} primary">
                        <div class="wsa-detective-source-header">
                            <span class="dashicons dashicons-${icon}"></span>
                            <strong>${primarySource.name || primarySource.type}</strong>
                            ${editLink}
                        </div>
                        <div class="wsa-detective-source-description">
                            ${primarySource.location || primarySource.description || 'Source identified with high confidence'}
                        </div>
                        ${primarySource.details ? `<div class="wsa-detective-source-details">${primarySource.details}</div>` : ''}
                    </div>
                </div>
            `;

            sourcesList.html(primaryHtml);
        }

        /**
         * Display detected DOM elements
         */
        displayDetectedElements(elements) {
            if (!elements || elements.length === 0) return;

            const elementsContainer = $('<div class="wsa-detective-detected-elements"></div>');
            elementsContainer.append('<h4><span class="dashicons dashicons-search"></span> Detected Elements</h4>');

            const elementsList = $('<div class="wsa-detective-elements-list"></div>');

            elements.forEach(element => {
                const confidenceClass = this.getConfidenceClass(element.confidence || 0);
                const elementHtml = `
                    <div class="wsa-detective-element ${confidenceClass}">
                        <div class="element-selector">
                            <code>${element.selector}</code>
                            <span class="confidence-badge">${Math.round((element.confidence || 0) * 100)}%</span>
                        </div>
                        ${element.text ? `<div class="element-text">"${element.text}"</div>` : ''}
                        ${element.type ? `<div class="element-type">Type: ${element.type}</div>` : ''}
                    </div>
                `;
                elementsList.append(elementHtml);
            });

            elementsContainer.append(elementsList);
            $('#wsa-detective-sources-list').append(elementsContainer);
        }

        /**
         * Display alternative sources
         */
        displayAlternativeSources(alternatives) {
            if (!alternatives || alternatives.length === 0) return;

            const alternativesContainer = $('<div class="wsa-detective-alternative-sources"></div>');
            alternativesContainer.append('<h4><span class="dashicons dashicons-list-view"></span> Alternative Sources</h4>');

            const alternativesList = $('<div class="wsa-detective-alternatives-list"></div>');

            alternatives.forEach(source => {
                const icon = this.getSourceIcon(source.type);
                const editLink = source.edit_link ? 
                    `<a href="${source.edit_link}" target="_blank" class="wsa-detective-edit-link button">
                        <span class="dashicons dashicons-edit"></span> Edit
                    </a>` : '';

                const alternativeHtml = `
                    <div class="wsa-detective-source-item wsa-detective-source-${source.type} alternative">
                        <div class="wsa-detective-source-header">
                            <span class="dashicons dashicons-${icon}"></span>
                            <strong>${source.name || source.type}</strong>
                            ${editLink}
                        </div>
                        <div class="wsa-detective-source-description">
                            ${source.location || source.description}
                        </div>
                    </div>
                `;
                alternativesList.append(alternativeHtml);
            });

            alternativesContainer.append(alternativesList);
            $('#wsa-detective-sources-list').append(alternativesContainer);
        }

        /**
         * Get confidence class for styling
         */
        getConfidenceClass(confidence) {
            if (confidence >= 0.8) return 'confidence-high';
            if (confidence >= 0.6) return 'confidence-medium';
            if (confidence >= 0.4) return 'confidence-low';
            return 'confidence-very-low';
        }

        /**
         * Get human-readable confidence text
         */
        getConfidenceText(confidence) {
            if (confidence >= 0.9) return 'Very High (90%+)';
            if (confidence >= 0.8) return 'High (80%+)';
            if (confidence >= 0.7) return 'Good (70%+)';
            if (confidence >= 0.6) return 'Moderate (60%+)';
            if (confidence >= 0.4) return 'Low (40%+)';
            return 'Very Low (' + Math.round(confidence * 100) + '%)';
        }

        showError(message) {
            $('#wsa-detective-results, #wsa-detective-loading').hide();
            $('#wsa-detective-error p').text(message);
            $('#wsa-detective-error').show();
        }

        resetModal() {
            $('#wsa-detective-query').val('');
            $('#wsa-detective-results, #wsa-detective-loading, #wsa-detective-error').hide();
            $('.wsa-detective-page-context').remove();
            this.isLoading = false;
        }

        formatResponse(text) {
            // Basic formatting for AI response
            let formatted = text.replace(/\n\n/g, '</p><p>');
            formatted = '<p>' + formatted + '</p>';
            
            // Make file paths and functions bold
            formatted = formatted.replace(/([a-zA-Z0-9_-]+\.php)/g, '<strong>$1</strong>');
            formatted = formatted.replace(/([a-zA-Z_][a-zA-Z0-9_]*\(\))/g, '<code>$1</code>');
            
            return formatted;
        }

        getSourceIcon(type) {
            const icons = {
                'theme': 'admin-appearance',
                'plugin': 'admin-plugins',
                'builder': 'layout',
                'meta': 'admin-settings',
                'template': 'admin-page'
            };

            return icons[type] || 'admin-generic';
        }

        addResponsiveStyles() {
            // Add responsive styles if not already present
            if (!$('#wsa-detective-responsive-styles').length) {
                $('<style id="wsa-detective-responsive-styles">')
                    .text(`
                        @media (max-width: 768px) {
                            .wsa-detective-modal-container {
                                width: 95%;
                                height: 90%;
                                margin: 2.5% auto;
                            }
                            
                            #wsa-detective-query {
                                font-size: 16px; /* Prevent zoom on iOS */
                            }
                            
                            .wsa-detective-source-item {
                                margin-bottom: 15px;
                            }
                            
                            .wsa-detective-action-btn {
                                display: block;
                                width: 100%;
                                margin-bottom: 10px;
                            }
                        }
                    `)
                    .appendTo('head');
            }
        }

        /**
         * Handle export button clicks
         */
        handleExport(e) {
            e.preventDefault();
            
            const $button = $(e.currentTarget);
            const format = $button.data('format');
            const scanType = $button.data('scan-type');
            const query = $button.data('query');
            const $statusDiv = $('.export-status');
            
            // Collect results from the current display
            const results = this.collectCurrentResults();
            
            if (!results || results.length === 0) {
                $statusDiv.html('<span class="error">No results to export</span>').show();
                return;
            }

            // Disable button and show loading
            $button.prop('disabled', true);
            $statusDiv.html('<span class="loading">Generating export file...</span>').show();
            
            // AJAX request to server for file generation
            $.ajax({
                url: wsaAiDetective.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'wsa_ai_detective_export',
                    nonce: wsaAiDetective.nonce,
                    results: results,
                    format: format,
                    scanType: scanType,
                    query: query
                },
                success: (response) => {
                    if (response.success) {
                        // Show download link
                        const downloadHtml = `
                            <span class="success">
                                Export completed! 
                                <a href="${response.data.download_url}" target="_blank" download="${response.data.filename}">
                                    Download ${response.data.filename} (${response.data.file_size})
                                </a>
                            </span>
                        `;
                        $statusDiv.html(downloadHtml);
                        
                        // Auto-download
                        window.open(response.data.download_url, '_blank');
                        
                    } else {
                        $statusDiv.html(`<span class="error">Export failed: ${response.data.message}</span>`);
                    }
                },
                error: (xhr, status, error) => {
                    $statusDiv.html(`<span class="error">Export failed: ${error}</span>`);
                },
                complete: () => {
                    $button.prop('disabled', false);
                    // Hide status after 10 seconds
                    setTimeout(() => {
                        $statusDiv.fadeOut();
                    }, 10000);
                }
            });
        }

        /**
         * Collect current results from the display for export
         */
        collectCurrentResults() {
            const results = [];
            
            // Check if we have results in the current scan
            if (this.currentScan && this.currentScan.results) {
                return this.currentScan.results;
            }
            
            // Fallback: parse from DOM (though this should not be needed with proper state management)
            $('.wsa-detective-source-item').each(function() {
                const $item = $(this);
                const result = {
                    type: $item.data('type') || 'unknown',
                    file: $item.find('.source-file').text() || '',
                    matches: [{
                        line: $item.find('.line-number').text() || '',
                        content: $item.find('.source-content').text() || '',
                        context: $item.find('.source-description').text() || ''
                    }],
                    confidence: parseFloat($item.data('confidence')) || 0,
                    edit_link: $item.find('.wsa-detective-action-btn').attr('href') || ''
                };
                results.push(result);
            });
            
            return results;
        }

        formatFileSize(bytes) {
            if (bytes === 0) return '0 Bytes';
            
            const k = 1024;
            const sizes = ['Bytes', 'KB', 'MB', 'GB'];
            const i = Math.floor(Math.log(bytes) / Math.log(k));
            
            return parseFloat((bytes / Math.pow(k, i)).toFixed(1)) + ' ' + sizes[i];
        }
    }

    // Initialize when document is ready
    $(document).ready(function() {
        // Only initialize if we have the necessary data
        if (typeof wsaAiDetective !== 'undefined') {
            window.wsaDetectiveInstance = new WSADetective();
        }
    });

    // Helper functions for external access
    window.WSADetective = {
        openModal: function() {
            if (window.wsaDetectiveInstance) {
                window.wsaDetectiveInstance.openModal();
            }
        },
        
        askQuestion: function(question) {
            if (window.wsaDetectiveInstance) {
                window.wsaDetectiveInstance.openModal();
                setTimeout(() => {
                    $('#wsa-detective-query').val(question);
                }, 300);
            }
        }
    };

})(jQuery);
