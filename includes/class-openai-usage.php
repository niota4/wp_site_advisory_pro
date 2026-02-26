<?php
/**
 * OpenAI Usage API Handler for Pro Plugin
 * 
 * Handles fetching and caching OpenAI token usage and billing information
 * 
 * @package WP_Site_Advisory_Pro
 */

if (!defined('ABSPATH')) {
    exit;
}

class WP_Site_Advisory_Pro_OpenAI_Usage {
    
    /**
     * Cache key for usage data
     */
    const USAGE_CACHE_KEY = 'wsa_pro_openai_usage_data';
    
    /**
     * Cache expiration time (1 hour)
     */
    const CACHE_EXPIRATION = 3600;
    
    /**
     * OpenAI Usage API endpoint
     */
    const USAGE_API_URL = 'https://api.openai.com/v1/usage';
    
    /**
     * Get cached or fresh usage data
     * 
     * @return array|WP_Error Usage data array or error object
     */
    public function get_usage_data() {
        // Try to get cached data first
        $cached_data = get_transient(self::USAGE_CACHE_KEY);
        if ($cached_data !== false) {
            return $cached_data;
        }
        
        // Fetch fresh data from OpenAI API
        $fresh_data = $this->fetch_usage_from_api();
        
        // Cache the data if successful
        if (!is_wp_error($fresh_data)) {
            set_transient(self::USAGE_CACHE_KEY, $fresh_data, self::CACHE_EXPIRATION);
        }
        
        return $fresh_data;
    }
    
    /**
     * Force refresh usage data from API
     * 
     * @return array|WP_Error Fresh usage data array or error object
     */
    public function refresh_usage_data() {
        // Clear cached data
        delete_transient(self::USAGE_CACHE_KEY);
        
        // Fetch fresh data
        $fresh_data = $this->fetch_usage_from_api();
        
        // Cache the new data if successful
        if (!is_wp_error($fresh_data)) {
            set_transient(self::USAGE_CACHE_KEY, $fresh_data, self::CACHE_EXPIRATION);
        }
        
        return $fresh_data;
    }
    
    /**
     * Fetch usage data from OpenAI API
     * 
     * @return array|WP_Error API response data or error object
     */
    private function fetch_usage_from_api() {
        $api_key = $this->get_api_key();
        
        if (empty($api_key)) {
            return new WP_Error('no_api_key', 'OpenAI API key is not configured');
        }
        
        // Calculate date range (last 30 days)
        $end_date = date('Y-m-d');
        $start_date = date('Y-m-d', strtotime('-30 days'));
        
        // Prepare API request
        $url = self::USAGE_API_URL . '?' . http_build_query([
            'date' => $start_date,
            'end_date' => $end_date
        ]);
        
        $args = [
            'headers' => [
                'Authorization' => 'Bearer ' . $api_key,
                'Content-Type' => 'application/json',
            ],
            'timeout' => 30,
            'user-agent' => 'WP-Site-Advisory-Pro/' . (defined('WSA_PRO_VERSION') ? WSA_PRO_VERSION : '1.0.0')
        ];
        
        // Make API request
        $response = wp_remote_get($url, $args);
        
        // Handle request errors
        if (is_wp_error($response)) {
            return new WP_Error('api_request_failed', 
                'Failed to connect to OpenAI API: ' . $response->get_error_message()
            );
        }
        
        $response_code = wp_remote_retrieve_response_code($response);
        $response_body = wp_remote_retrieve_body($response);
        
        // Handle HTTP errors
        if ($response_code !== 200) {
            $error_data = json_decode($response_body, true);
            $error_message = isset($error_data['error']['message']) 
                ? $error_data['error']['message'] 
                : "HTTP {$response_code} error";
                
            return new WP_Error('api_error', $error_message, ['code' => $response_code]);
        }
        
        // Parse response
        $data = json_decode($response_body, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            return new WP_Error('json_parse_error', 'Failed to parse API response');
        }
        
        // Process and return formatted data
        return $this->process_usage_data($data);
    }
    
    /**
     * Process raw usage data from API
     * 
     * @param array $raw_data Raw API response data
     * @return array Processed usage data
     */
    private function process_usage_data($raw_data) {
        // Initialize processed data structure
        $processed = [
            'total_tokens' => 0,
            'total_cost' => 0.00,
            'by_model' => [],
            'by_date' => [],
            'summary' => [
                'requests' => 0,
                'input_tokens' => 0,
                'output_tokens' => 0,
                'cached_tokens' => 0
            ],
            'last_updated' => current_time('mysql'),
            'date_range' => [
                'start' => date('Y-m-d', strtotime('-30 days')),
                'end' => date('Y-m-d')
            ]
        ];
        
        // Process daily usage data
        if (isset($raw_data['data']) && is_array($raw_data['data'])) {
            foreach ($raw_data['data'] as $day_data) {
                $date = $day_data['aggregation_timestamp'] ?? '';
                
                if (!empty($date)) {
                    // Convert timestamp to date
                    $formatted_date = date('Y-m-d', $day_data['aggregation_timestamp']);
                    
                    // Process usage by operation type (chat completions, embeddings, etc.)
                    foreach ($day_data['results'] as $result) {
                        $operation = $result['operation'] ?? 'unknown';
                        $model = $result['snapshot_id'] ?? 'unknown';
                        
                        $tokens_used = $result['n_generated_tokens_total'] ?? 0;
                        $requests = $result['n_requests'] ?? 0;
                        
                        // Calculate approximate cost (this is an estimate based on GPT-4 pricing)
                        $estimated_cost = $this->estimate_cost($model, $tokens_used);
                        
                        // Update totals
                        $processed['total_tokens'] += $tokens_used;
                        $processed['total_cost'] += $estimated_cost;
                        $processed['summary']['requests'] += $requests;
                        
                        // Group by model
                        if (!isset($processed['by_model'][$model])) {
                            $processed['by_model'][$model] = [
                                'tokens' => 0,
                                'cost' => 0.00,
                                'requests' => 0
                            ];
                        }
                        
                        $processed['by_model'][$model]['tokens'] += $tokens_used;
                        $processed['by_model'][$model]['cost'] += $estimated_cost;
                        $processed['by_model'][$model]['requests'] += $requests;
                        
                        // Group by date
                        if (!isset($processed['by_date'][$formatted_date])) {
                            $processed['by_date'][$formatted_date] = [
                                'tokens' => 0,
                                'cost' => 0.00,
                                'requests' => 0
                            ];
                        }
                        
                        $processed['by_date'][$formatted_date]['tokens'] += $tokens_used;
                        $processed['by_date'][$formatted_date]['cost'] += $estimated_cost;
                        $processed['by_date'][$formatted_date]['requests'] += $requests;
                    }
                }
            }
        }
        
        return $processed;
    }
    
    /**
     * Estimate cost based on model and token usage
     * 
     * @param string $model Model identifier
     * @param int $tokens Number of tokens
     * @return float Estimated cost in USD
     */
    private function estimate_cost($model, $tokens) {
        // Pricing per 1K tokens (as of 2024, these are estimates)
        $pricing = [
            'gpt-4' => 0.03,           // $0.03 per 1K tokens
            'gpt-4-turbo' => 0.01,     // $0.01 per 1K tokens
            'gpt-3.5-turbo' => 0.002,  // $0.002 per 1K tokens
        ];
        
        // Default pricing if model not found
        $price_per_1k = $pricing['gpt-4'] ?? 0.03;
        
        // Check if model matches known patterns
        foreach ($pricing as $model_pattern => $price) {
            if (strpos($model, $model_pattern) !== false) {
                $price_per_1k = $price;
                break;
            }
        }
        
        return ($tokens / 1000) * $price_per_1k;
    }
    
    /**
     * Get OpenAI API key from unified settings or fallback
     * 
     * @return string API key or empty string
     */
    private function get_api_key() {
        // Try unified settings first
        if (class_exists('WP_Site_Advisory_Unified_Settings')) {
            $unified_settings = new WP_Site_Advisory_Unified_Settings();
            $api_key = $unified_settings->get_actual_api_key();
            if (!empty($api_key)) {
                return $api_key;
            }
        }
        
        // Fallback to individual plugin settings
        $pro_settings = get_option('wsa_pro_settings', array());
        if (!empty($pro_settings['openai_api_key'])) {
            return $pro_settings['openai_api_key'];
        }
        
        // Fallback to free plugin setting
        return get_option('wsa_openai_api_key', '');
    }
    
    /**
     * Format usage data for display
     * 
     * @param array $usage_data Usage data from API
     * @return array Formatted display data
     */
    public function format_for_display($usage_data) {
        if (is_wp_error($usage_data)) {
            return $usage_data;
        }
        
        return [
            'total_tokens' => number_format($usage_data['total_tokens']),
            'total_cost' => '$' . number_format($usage_data['total_cost'], 4),
            'requests' => number_format($usage_data['summary']['requests']),
            'models' => $usage_data['by_model'],
            'daily_average_tokens' => number_format($usage_data['total_tokens'] / 30),
            'daily_average_cost' => '$' . number_format($usage_data['total_cost'] / 30, 4),
            'last_updated' => $usage_data['last_updated'],
            'date_range' => $usage_data['date_range']
        ];
    }
    
    /**
     * Get usage analytics and insights
     * 
     * @param array $usage_data Usage data from API
     * @return array Analytics insights
     */
    public function get_usage_analytics($usage_data) {
        if (is_wp_error($usage_data)) {
            return [];
        }
        
        $insights = [];
        
        // Cost analysis
        $daily_cost = $usage_data['total_cost'] / 30;
        $monthly_projection = $daily_cost * 30;
        
        if ($monthly_projection > 50) {
            $insights[] = [
                'type' => 'warning',
                'title' => 'High Usage Alert',
                'message' => 'Your projected monthly cost is $' . number_format($monthly_projection, 2) . '. Consider optimizing your API usage.'
            ];
        } elseif ($monthly_projection < 5) {
            $insights[] = [
                'type' => 'info',
                'title' => 'Low Usage',
                'message' => 'You have light API usage. Consider exploring more AI features to get better value.'
            ];
        }
        
        // Model usage insights
        if (count($usage_data['by_model']) > 1) {
            $most_used = array_keys($usage_data['by_model'])[0];
            $insights[] = [
                'type' => 'info',
                'title' => 'Model Usage',
                'message' => "You're primarily using {$most_used}. Consider if a more cost-effective model meets your needs."
            ];
        }
        
        return $insights;
    }
}