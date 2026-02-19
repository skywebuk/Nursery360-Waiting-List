<?php
/**
 * Public-facing functionality
 */

if (!defined('ABSPATH')) {
    exit;
}

class NWL_Public {

    private static $instance = null;

    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        add_action('wp_enqueue_scripts', array($this, 'enqueue_scripts'));
        add_shortcode('nwl_parent_lookup', array($this, 'render_parent_lookup'));
        add_action('wp_ajax_nwl_parent_lookup', array($this, 'handle_lookup'));
        add_action('wp_ajax_nopriv_nwl_parent_lookup', array($this, 'handle_lookup'));
    }

    /**
     * Enqueue public scripts and styles
     */
    public function enqueue_scripts() {
        if (!$this->is_stats_page()) {
            return;
        }

        wp_enqueue_style(
            'nwl-public',
            NWL_PLUGIN_URL . 'assets/css/public.css',
            array(),
            NWL_VERSION
        );

        wp_enqueue_script(
            'nwl-public',
            NWL_PLUGIN_URL . 'assets/js/public.js',
            array('jquery'),
            NWL_VERSION,
            true
        );

        wp_localize_script('nwl-public', 'nwlPublic', array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('nwl_parent_lookup'),
            'strings' => array(
                'loading' => __('Searching...', 'nursery-waiting-list'),
                'error' => __('An error occurred. Please try again.', 'nursery-waiting-list'),
                'notFound' => __('No entries found with the provided information.', 'nursery-waiting-list'),
                'invalidInput' => __('Please enter a valid email address or phone number.', 'nursery-waiting-list'),
                'emailLabel' => __('Email Address', 'nursery-waiting-list'),
                'phoneLabel' => __('Phone Number', 'nursery-waiting-list'),
                'emailPlaceholder' => __('Enter your email address', 'nursery-waiting-list'),
                'phonePlaceholder' => __('Enter your phone number', 'nursery-waiting-list'),
                'notFoundTitle' => __('No Results Found', 'nursery-waiting-list'),
                'tryAgain' => __('Try Again', 'nursery-waiting-list'),
            ),
        ));
    }

    /**
     * Check if current page is stats page
     */
    private function is_stats_page() {
        global $post;
        
        if (!$post) {
            return false;
        }

        return has_shortcode($post->post_content, 'nwl_parent_lookup') || 
               $post->post_name === 'waiting-list-stats';
    }

    /**
     * Render parent lookup shortcode
     */
    public function render_parent_lookup($atts) {
        $atts = shortcode_atts(array(
            'title' => __('Check Your Waiting List Status', 'nursery-waiting-list'),
            'description' => __('Enter your email address or phone number to check the status of your waiting list application.', 'nursery-waiting-list'),
        ), $atts);

        ob_start();
        ?>
        <div class="nwl-lookup-container">
            <div class="nwl-lookup-header">
                <?php if ($atts['title']) : ?>
                    <h2 class="nwl-lookup-title"><?php echo esc_html($atts['title']); ?></h2>
                <?php endif; ?>
                <?php if ($atts['description']) : ?>
                    <p class="nwl-lookup-description"><?php echo esc_html($atts['description']); ?></p>
                <?php endif; ?>
            </div>

            <form class="nwl-lookup-form" id="nwl-lookup-form">
                <div class="nwl-form-group">
                    <label for="nwl-lookup-type" class="nwl-label"><?php esc_html_e('Search by:', 'nursery-waiting-list'); ?></label>
                    <div class="nwl-lookup-type-toggle">
                        <button type="button" class="nwl-type-btn active" data-type="email">
                            <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"></path>
                                <polyline points="22,6 12,13 2,6"></polyline>
                            </svg>
                            <?php esc_html_e('Email', 'nursery-waiting-list'); ?>
                        </button>
                        <button type="button" class="nwl-type-btn" data-type="phone">
                            <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"></path>
                            </svg>
                            <?php esc_html_e('Phone', 'nursery-waiting-list'); ?>
                        </button>
                    </div>
                </div>

                <div class="nwl-form-group">
                    <label for="nwl-lookup-value" class="nwl-label" id="nwl-value-label">
                        <?php esc_html_e('Email Address', 'nursery-waiting-list'); ?>
                    </label>
                    <input type="text" 
                           id="nwl-lookup-value" 
                           name="lookup_value" 
                           class="nwl-input" 
                           placeholder="<?php esc_attr_e('Enter your email address', 'nursery-waiting-list'); ?>"
                           required>
                </div>

                <input type="hidden" name="lookup_type" id="nwl-lookup-type" value="email">
                <?php wp_nonce_field('nwl_parent_lookup', 'nwl_nonce'); ?>

                <button type="submit" class="nwl-submit-btn" id="nwl-submit-btn">
                    <span class="nwl-btn-text"><?php esc_html_e('Check Status', 'nursery-waiting-list'); ?></span>
                    <span class="nwl-btn-loading" style="display: none;">
                        <svg class="nwl-spinner" xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <circle cx="12" cy="12" r="10" stroke-opacity="0.25"></circle>
                            <path d="M12 2a10 10 0 0 1 10 10" stroke-linecap="round">
                                <animateTransform attributeName="transform" type="rotate" from="0 12 12" to="360 12 12" dur="1s" repeatCount="indefinite"/>
                            </path>
                        </svg>
                        <?php esc_html_e('Searching...', 'nursery-waiting-list'); ?>
                    </span>
                </button>
            </form>

            <div class="nwl-lookup-results" id="nwl-lookup-results" style="display: none;"></div>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Handle AJAX lookup
     */
    public function handle_lookup() {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nwl_nonce'] ?? '', 'nwl_parent_lookup')) {
            wp_send_json_error(array('message' => __('Security check failed.', 'nursery-waiting-list')));
        }

        $lookup_type = sanitize_text_field($_POST['lookup_type'] ?? 'email');
        $lookup_value = sanitize_text_field($_POST['lookup_value'] ?? '');

        if (empty($lookup_value)) {
            wp_send_json_error(array('message' => __('Please enter a value to search.', 'nursery-waiting-list')));
        }

        $entry_handler = NWL_Entry::get_instance();

        // Rate limiting
        $ip = $_SERVER['REMOTE_ADDR'] ?? '';
        $rate_key = 'nwl_lookup_' . md5($ip);
        $attempts = get_transient($rate_key);
        
        if ($attempts && $attempts >= 10) {
            wp_send_json_error(array('message' => __('Too many requests. Please try again in a few minutes.', 'nursery-waiting-list')));
        }
        
        set_transient($rate_key, ($attempts ? $attempts + 1 : 1), 300);

        // Search
        if ($lookup_type === 'email') {
            if (!is_email($lookup_value)) {
                wp_send_json_error(array('message' => __('Please enter a valid email address.', 'nursery-waiting-list')));
            }
            $entries = $entry_handler->get_by_email($lookup_value);
        } else {
            $entries = $entry_handler->get_by_phone($lookup_value);
        }

        if (empty($entries)) {
            wp_send_json_error(array('message' => __('No entries found with the provided information.', 'nursery-waiting-list')));
        }

        // Filter out removed entries
        $entries = array_filter($entries, function($entry) {
            return $entry->status !== 'removed';
        });

        if (empty($entries)) {
            wp_send_json_error(array('message' => __('No active entries found.', 'nursery-waiting-list')));
        }

        // Format response
        $results = array();
        foreach ($entries as $entry) {
            $results[] = $this->format_entry_for_public($entry);
        }

        wp_send_json_success(array(
            'entries' => $results,
            'html' => $this->render_results_html($results),
        ));
    }

    /**
     * Format entry for public display
     */
    private function format_entry_for_public($entry) {
        $entry_handler = NWL_Entry::get_instance();

        return array(
            'waiting_list_number' => $entry->waiting_list_number,
            'child_name' => $entry_handler->get_child_name($entry),
            'date_added' => date_i18n(get_option('date_format'), strtotime($entry->created_at)),
            'preferred_start_date' => $entry->preferred_start_date ? date_i18n(get_option('date_format'), strtotime($entry->preferred_start_date)) : '',
            'status' => $entry->status,
            'status_label' => $entry_handler->get_status_label($entry->status),
            'status_class' => $this->get_status_class($entry->status),
            'public_notes' => $entry->public_notes,
            'offer_deadline' => $entry->offer_deadline ? date_i18n(get_option('date_format'), strtotime($entry->offer_deadline)) : '',
        );
    }

    /**
     * Get CSS class for status
     */
    private function get_status_class($status) {
        $classes = array(
            'pending' => 'status-pending',
            'waitlisted' => 'status-info',
            'offered' => 'status-success',
            'enrolled' => 'status-success',
            'withdrawn' => 'status-warning',
        );

        return isset($classes[$status]) ? $classes[$status] : 'status-default';
    }

    /**
     * Render results HTML
     */
    private function render_results_html($entries) {
        ob_start();
        ?>
        <div class="nwl-results-list">
            <?php foreach ($entries as $entry) : ?>
                <div class="nwl-result-card">
                    <div class="nwl-result-header">
                        <div class="nwl-child-info">
                            <h3 class="nwl-child-name"><?php echo esc_html($entry['child_name']); ?></h3>
                            <span class="nwl-wl-number"><?php echo esc_html($entry['waiting_list_number']); ?></span>
                        </div>
                        <span class="nwl-status-badge <?php echo esc_attr($entry['status_class']); ?>">
                            <?php echo esc_html($entry['status_label']); ?>
                        </span>
                    </div>
                    
                    <div class="nwl-result-details">
                        <div class="nwl-detail-row">
                            <span class="nwl-detail-label"><?php esc_html_e('Date Added:', 'nursery-waiting-list'); ?></span>
                            <span class="nwl-detail-value"><?php echo esc_html($entry['date_added']); ?></span>
                        </div>

                        <?php if ($entry['preferred_start_date']) : ?>
                            <div class="nwl-detail-row">
                                <span class="nwl-detail-label"><?php esc_html_e('Preferred Start Date:', 'nursery-waiting-list'); ?></span>
                                <span class="nwl-detail-value"><?php echo esc_html($entry['preferred_start_date']); ?></span>
                            </div>
                        <?php endif; ?>

                        <?php if ($entry['offer_deadline']) : ?>
                            <div class="nwl-detail-row nwl-deadline">
                                <span class="nwl-detail-label"><?php esc_html_e('Response Deadline:', 'nursery-waiting-list'); ?></span>
                                <span class="nwl-detail-value nwl-deadline-date"><?php echo esc_html($entry['offer_deadline']); ?></span>
                            </div>
                        <?php endif; ?>
                    </div>
                    
                    <?php if ($entry['public_notes']) : ?>
                        <div class="nwl-result-notes">
                            <strong><?php esc_html_e('Notes:', 'nursery-waiting-list'); ?></strong>
                            <p><?php echo esc_html($entry['public_notes']); ?></p>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>
        
        <div class="nwl-results-footer">
            <p><?php esc_html_e('If you have any questions about your application, please contact us.', 'nursery-waiting-list'); ?></p>
            <button type="button" class="nwl-new-search-btn" id="nwl-new-search">
                <?php esc_html_e('New Search', 'nursery-waiting-list'); ?>
            </button>
        </div>
        <?php
        return ob_get_clean();
    }
}
