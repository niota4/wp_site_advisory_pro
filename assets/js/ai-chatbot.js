/**
 * AI Chatbot JavaScript
 * Handles the interactive chatbot functionality
 */

(function($) {
    'use strict';
    
    class WSAIoChatbot {
        constructor() {
            this.chatWidget = $('#wsa-ai-chatbot');
            this.chatToggle = $('#wsa-chatbot-toggle');
            this.messagesContainer = $('#wsa-chatbot-messages');
            this.messageInput = $('#wsa-chatbot-message');
            this.sendButton = $('#wsa-chatbot-send');
            this.closeButton = $('#wsa-chatbot-close');
            this.clearButton = $('#wsa-chatbot-clear');
            this.suggestionsButton = $('#wsa-chatbot-suggestions');
            this.suggestionsContainer = $('#wsa-suggested-questions');
            
            this.isOpen = false;
            this.isTyping = false;
            
            this.init();
        }
        
        init() {
            this.bindEvents();
            this.loadChatHistory();
        }
        
        bindEvents() {
            // Toggle chatbot
            this.chatToggle.on('click', (e) => {
                e.preventDefault();
                this.toggleChatbot();
            });
            
            // Close chatbot
            this.closeButton.on('click', (e) => {
                e.preventDefault();
                this.closeChatbot();
            });
            
            // Send message
            this.sendButton.on('click', (e) => {
                e.preventDefault();
                this.sendMessage();
            });
            
            // Send message on Enter (but allow Shift+Enter for new lines)
            this.messageInput.on('keydown', (e) => {
                if (e.key === 'Enter' && !e.shiftKey) {
                    e.preventDefault();
                    this.sendMessage();
                }
            });
            
            // Clear chat
            this.clearButton.on('click', (e) => {
                e.preventDefault();
                this.clearChat();
            });
            
            // Show suggestions
            this.suggestionsButton.on('click', (e) => {
                e.preventDefault();
                this.toggleSuggestions();
            });
            
            // Click on suggestion
            $(document).on('click', '.wsa-suggested-questions .suggestion', (e) => {
                const question = $(e.target).text();
                this.messageInput.val(question);
                this.sendMessage();
                this.hideSuggestions();
            });
        }
        
        toggleChatbot() {
            if (this.isOpen) {
                this.closeChatbot();
            } else {
                this.openChatbot();
            }
        }
        
        openChatbot() {
            this.chatWidget.show();
            this.chatToggle.hide();
            this.isOpen = true;
            this.messageInput.focus();
            this.scrollToBottom();
        }
        
        closeChatbot() {
            this.chatWidget.hide();
            this.chatToggle.show();
            this.isOpen = false;
            this.hideSuggestions();
        }
        
        sendMessage() {
            const message = this.messageInput.val().trim();
            
            if (!message || this.isTyping) {
                return;
            }
            
            // Add user message to chat
            this.addMessage(message, 'user');
            this.messageInput.val('');
            
            // Show typing indicator
            this.showTyping();
            
            // Send to server
            $.ajax({
                url: wsa_admin.ajax_url,
                type: 'POST',
                data: {
                    action: 'wsa_ai_chat',
                    message: message,
                    nonce: wsa_admin.nonce
                },
                success: (response) => {
                    this.hideTyping();
                    
                    if (response.success) {
                        this.addMessage(response.data.response, 'assistant');
                        
                        // Show token usage if available
                        if (response.data.tokens_used) {
                            this.addTokenInfo(response.data.tokens_used);
                        }
                    } else {
                        this.addMessage(response.data || 'An error occurred. Please try again.', 'error');
                    }
                },
                error: (xhr, status, error) => {
                    this.hideTyping();
                    this.addMessage('Failed to send message. Please check your connection and try again.', 'error');
                    console.error('Chat error:', error);
                }
            });
        }
        
        addMessage(content, type = 'assistant') {
            const messageDiv = $('<div>').addClass('wsa-chat-message').addClass(type);
            
            if (type === 'assistant') {
                // Process markdown-like formatting
                content = this.formatMessage(content);
            }
            
            messageDiv.html(content);
            
            // Remove welcome message if this is the first real message
            if (type === 'user') {
                this.messagesContainer.find('.wsa-chatbot-welcome').remove();
            }
            
            this.messagesContainer.append(messageDiv);
            this.scrollToBottom();
        }
        
        formatMessage(content) {
            // Convert **bold** to <strong>
            content = content.replace(/\*\*(.*?)\*\*/g, '<strong>$1</strong>');
            
            // Convert *italic* to <em>
            content = content.replace(/\*(.*?)\*/g, '<em>$1</em>');
            
            // Convert `code` to <code>
            content = content.replace(/`(.*?)`/g, '<code>$1</code>');
            
            // Convert line breaks to <br>
            content = content.replace(/\n/g, '<br>');
            
            // Convert numbered lists
            content = content.replace(/^\d+\.\s+(.+)$/gm, '<ol><li>$1</li></ol>');
            
            // Convert bullet lists
            content = content.replace(/^-\s+(.+)$/gm, '<ul><li>$1</li></ul>');
            
            return content;
        }
        
        showTyping() {
            this.isTyping = true;
            this.sendButton.prop('disabled', true).text('Sending...');
            
            const typingDiv = $('<div>').addClass('wsa-chat-typing');
            typingDiv.html('AI is typing... <div class="wsa-typing-dots"><span></span><span></span><span></span></div>');
            
            this.messagesContainer.append(typingDiv);
            this.scrollToBottom();
        }
        
        hideTyping() {
            this.isTyping = false;
            this.sendButton.prop('disabled', false).text('Send');
            this.messagesContainer.find('.wsa-chat-typing').remove();
        }
        
        addTokenInfo(tokens) {
            const tokenDiv = $('<div>').addClass('wsa-token-info').css({
                'font-size': '11px',
                'color': '#666',
                'text-align': 'right',
                'margin': '4px 12px 8px',
                'font-style': 'italic'
            });
            
            tokenDiv.text(`Tokens used: ${tokens}`);
            this.messagesContainer.append(tokenDiv);
        }
        
        loadChatHistory() {
            $.ajax({
                url: wsa_admin.ajax_url,
                type: 'POST',
                data: {
                    action: 'wsa_get_chat_history',
                    nonce: wsa_admin.nonce
                },
                success: (response) => {
                    if (response.success && response.data.length > 0) {
                        // Remove welcome message
                        this.messagesContainer.find('.wsa-chatbot-welcome').remove();
                        
                        // Add history messages
                        response.data.forEach((chat) => {
                            this.addMessage(chat.message, 'user');
                            this.addMessage(chat.response, 'assistant');
                        });
                    }
                }
            });
        }
        
        clearChat() {
            if (!confirm('Are you sure you want to clear the chat history?')) {
                return;
            }
            
            $.ajax({
                url: wsa_admin.ajax_url,
                type: 'POST',
                data: {
                    action: 'wsa_clear_chat_history',
                    nonce: wsa_admin.nonce
                },
                success: (response) => {
                    if (response.success) {
                        // Clear messages and restore welcome
                        this.messagesContainer.html(`
                            <div class="wsa-chatbot-welcome">
                                <p>Hi! I'm your WordPress AI assistant. I can help you with:</p>
                                <ul>
                                    <li>Security optimization</li>
                                    <li>Performance improvements</li>
                                    <li>Plugin conflicts</li>
                                    <li>General WordPress troubleshooting</li>
                                </ul>
                                <p>What would you like to know?</p>
                            </div>
                        `);
                    }
                }
            });
        }
        
        toggleSuggestions() {
            if (this.suggestionsContainer.is(':visible')) {
                this.hideSuggestions();
            } else {
                this.showSuggestions();
            }
        }
        
        showSuggestions() {
            $.ajax({
                url: wsa_admin.ajax_url,
                type: 'POST',
                data: {
                    action: 'wsa_get_suggested_questions',
                    nonce: wsa_admin.nonce
                },
                success: (response) => {
                    if (response.success) {
                        let html = '<p style="margin: 0 0 8px; font-size: 12px; color: #666;">Suggested questions:</p>';
                        
                        response.data.forEach((question) => {
                            html += `<span class="suggestion">${question}</span>`;
                        });
                        
                        this.suggestionsContainer.html(html).show();
                        this.suggestionsButton.text('Hide Suggestions');
                    }
                }
            });
        }
        
        hideSuggestions() {
            this.suggestionsContainer.hide();
            this.suggestionsButton.text('Show Suggestions');
        }
        
        scrollToBottom() {
            setTimeout(() => {
                this.messagesContainer.scrollTop(this.messagesContainer[0].scrollHeight);
            }, 100);
        }
    }
    
    // Initialize when document is ready
    $(document).ready(function() {
        // Only initialize if we're on a WP SiteAdvisor page
        if ($('#wsa-ai-chatbot').length) {
            new WSAIoChatbot();
        }
    });
    
})(jQuery);