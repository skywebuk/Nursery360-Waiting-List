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
                'referenceLabel' => __('Reference Number', 'nursery-waiting-list'),
                'emailPlaceholder' => __('Enter your email address', 'nursery-waiting-list'),
                'phonePlaceholder' => __('Enter your phone number', 'nursery-waiting-list'),
                'referencePlaceholder' => __('e.g. WL2024001', 'nursery-waiting-list'),
                'notFoundTitle' => __('No Results Found', 'nursery-waiting-list'),
                'tryAgain' => __('Try Again', 'nursery-waiting-list'),
                'copied' => __('Copied!', 'nursery-waiting-list'),
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
            'description' => __('Enter your details below to check the status of your waiting list application.', 'nursery-waiting-list'),
        ), $atts);

        $nursery_name = get_option('nwl_nursery_name', get_bloginfo('name'));

        ob_start();
        ?>
        <div class="nwl-lookup-container">
            <div class="nwl-lookup-header">
                <div class="nwl-lookup-icon">
                    <svg xmlns="http://www.w3.org/2000/svg" width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
                        <circle cx="11" cy="11" r="8"></circle>
                        <line x1="21" y1="21" x2="16.65" y2="16.65"></line>
                    </svg>
                </div>
                <?php if ($atts['title']) : ?>
                    <h2 class="nwl-lookup-title"><?php echo esc_html($atts['title']); ?></h2>
                <?php endif; ?>
                <?php if ($atts['description']) : ?>
                    <p class="nwl-lookup-description"><?php echo esc_html($atts['description']); ?></p>
                <?php endif; ?>
            </div>

            <form class="nwl-lookup-form" id="nwl-lookup-form">
                <div class="nwl-form-group">
                    <label class="nwl-label"><?php esc_html_e('Search by:', 'nursery-waiting-list'); ?></label>
                    <div class="nwl-lookup-type-toggle">
                        <button type="button" class="nwl-type-btn active" data-type="email">
                            <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"></path>
                                <polyline points="22,6 12,13 2,6"></polyline>
                            </svg>
                            <?php esc_html_e('Email', 'nursery-waiting-list'); ?>
                        </button>
                        <button type="button" class="nwl-type-btn" data-type="phone">
                            <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"></path>
                            </svg>
                            <?php esc_html_e('Phone', 'nursery-waiting-list'); ?>
                        </button>
                        <button type="button" class="nwl-type-btn" data-type="reference">
                            <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M4 7V4a2 2 0 0 1 2-2h8.5L20 7.5V20a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2v-3"></path>
                                <polyline points="14 2 14 8 20 8"></polyline>
                                <line x1="2" y1="15" x2="10" y2="15"></line>
                            </svg>
                            <?php esc_html_e('Reference', 'nursery-waiting-list'); ?>
                        </button>
                    </div>
                </div>

                <div class="nwl-form-group">
                    <label for="nwl-lookup-value" class="nwl-label" id="nwl-value-label">
                        <?php esc_html_e('Email Address', 'nursery-waiting-list'); ?>
                    </label>
                    <div class="nwl-input-wrap">
                        <input type="text"
                               id="nwl-lookup-value"
                               name="lookup_value"
                               class="nwl-input"
                               placeholder="<?php esc_attr_e('Enter your email address', 'nursery-waiting-list'); ?>"
                               autocomplete="email"
                               required>
                        <span class="nwl-input-icon">
                            <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <circle cx="11" cy="11" r="8"></circle>
                                <line x1="21" y1="21" x2="16.65" y2="16.65"></line>
                            </svg>
                        </span>
                    </div>
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

                <p class="nwl-privacy-note">
                    <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect>
                        <path d="M7 11V7a5 5 0 0 1 10 0v4"></path>
                    </svg>
                    <?php esc_html_e('Your information is secure and only used to look up your application.', 'nursery-waiting-list'); ?>
                </p>
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
        } elseif ($lookup_type === 'reference') {
            $entry = $entry_handler->get_by_wl_number($lookup_value);
            $entries = $entry ? array($entry) : array();
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

        $year_group_name = '';
        if (!empty($entry->year_group)) {
            $group = NWL_Database::get_year_group($entry->year_group);
            $year_group_name = $group ? $group['name'] : '';
        }

        return array(
            'waiting_list_number' => $entry->waiting_list_number,
            'child_name' => $entry_handler->get_child_name($entry),
            'date_added' => date_i18n(get_option('date_format'), strtotime($entry->created_at)),
            'preferred_start_date' => $entry->preferred_start_date ? date_i18n(get_option('date_format'), strtotime($entry->preferred_start_date)) : '',
            'status' => $entry->status,
            'status_label' => $entry_handler->get_status_label($entry->status),
            'status_class' => $this->get_status_class($entry->status),
            'status_step' => $this->get_status_step($entry->status),
            'year_group' => $year_group_name,
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
            'waitlisted' => 'status-waitlisted',
            'offered' => 'status-offered',
            'enrolled' => 'status-enrolled',
            'withdrawn' => 'status-withdrawn',
        );

        return isset($classes[$status]) ? $classes[$status] : 'status-default';
    }

    /**
     * Get progress step number for status (1-4 for the timeline)
     */
    private function get_status_step($status) {
        $steps = array(
            'pending' => 1,
            'waitlisted' => 2,
            'offered' => 3,
            'enrolled' => 4,
            'withdrawn' => 0,
        );
        return isset($steps[$status]) ? $steps[$status] : 0;
    }

    /**
     * Render results HTML
     */
    private function render_results_html($entries) {
        ob_start();
        ?>
        <div class="nwl-results-header">
            <h3 class="nwl-results-title">
                <?php
                printf(
                    esc_html(_n('Your Application', 'Your Applications (%d)', count($entries), 'nursery-waiting-list')),
                    count($entries)
                );
                ?>
            </h3>
        </div>

        <div class="nwl-results-list">
            <?php foreach ($entries as $entry) : ?>
                <div class="nwl-result-card <?php echo esc_attr($entry['status_class']); ?>">
                    <!-- Card Header -->
                    <div class="nwl-result-header">
                        <div class="nwl-child-info">
                            <div class="nwl-child-avatar">
                                <?php echo esc_html(mb_substr($entry['child_name'], 0, 1)); ?>
                            </div>
                            <div>
                                <h3 class="nwl-child-name"><?php echo esc_html($entry['child_name']); ?></h3>
                                <span class="nwl-wl-number" title="<?php esc_attr_e('Click to copy', 'nursery-waiting-list'); ?>" data-ref="<?php echo esc_attr($entry['waiting_list_number']); ?>">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="9" y="9" width="13" height="13" rx="2" ry="2"></rect><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"></path></svg>
                                    <?php echo esc_html($entry['waiting_list_number']); ?>
                                </span>
                            </div>
                        </div>
                        <span class="nwl-status-badge <?php echo esc_attr($entry['status_class']); ?>">
                            <?php echo esc_html($this->get_status_icon($entry['status'])); ?>
                            <?php echo esc_html($entry['status_label']); ?>
                        </span>
                    </div>

                    <?php if ($entry['status'] !== 'withdrawn' && $entry['status_step'] > 0) : ?>
                    <!-- Progress Timeline -->
                    <div class="nwl-progress-timeline">
                        <?php
                        $steps = array(
                            1 => __('Applied', 'nursery-waiting-list'),
                            2 => __('Waitlisted', 'nursery-waiting-list'),
                            3 => __('Offered', 'nursery-waiting-list'),
                            4 => __('Enrolled', 'nursery-waiting-list'),
                        );
                        $current_step = $entry['status_step'];
                        ?>
                        <div class="nwl-progress-bar">
                            <div class="nwl-progress-fill" style="width: <?php echo esc_attr(($current_step - 1) / 3 * 100); ?>%"></div>
                        </div>
                        <div class="nwl-progress-steps">
                            <?php foreach ($steps as $num => $label) : ?>
                                <div class="nwl-step <?php echo $num <= $current_step ? 'completed' : ''; ?> <?php echo $num === $current_step ? 'current' : ''; ?>">
                                    <div class="nwl-step-dot">
                                        <?php if ($num < $current_step) : ?>
                                            <svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"></polyline></svg>
                                        <?php endif; ?>
                                    </div>
                                    <span class="nwl-step-label"><?php echo esc_html($label); ?></span>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <?php endif; ?>

                    <!-- Details Grid -->
                    <div class="nwl-result-details">
                        <div class="nwl-detail-grid">
                            <div class="nwl-detail-item">
                                <span class="nwl-detail-label"><?php esc_html_e('Date Applied', 'nursery-waiting-list'); ?></span>
                                <span class="nwl-detail-value"><?php echo esc_html($entry['date_added']); ?></span>
                            </div>

                            <?php if ($entry['preferred_start_date']) : ?>
                                <div class="nwl-detail-item">
                                    <span class="nwl-detail-label"><?php esc_html_e('Preferred Start', 'nursery-waiting-list'); ?></span>
                                    <span class="nwl-detail-value"><?php echo esc_html($entry['preferred_start_date']); ?></span>
                                </div>
                            <?php endif; ?>

                            <?php if ($entry['year_group']) : ?>
                                <div class="nwl-detail-item">
                                    <span class="nwl-detail-label"><?php esc_html_e('Year Group', 'nursery-waiting-list'); ?></span>
                                    <span class="nwl-detail-value"><?php echo esc_html($entry['year_group']); ?></span>
                                </div>
                            <?php endif; ?>

                            <?php if ($entry['offer_deadline']) : ?>
                                <div class="nwl-detail-item nwl-deadline">
                                    <span class="nwl-detail-label"><?php esc_html_e('Respond By', 'nursery-waiting-list'); ?></span>
                                    <span class="nwl-detail-value nwl-deadline-date">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><polyline points="12 6 12 12 16 14"></polyline></svg>
                                        <?php echo esc_html($entry['offer_deadline']); ?>
                                    </span>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <?php if ($entry['public_notes']) : ?>
                        <div class="nwl-result-notes">
                            <div class="nwl-notes-icon">
                                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"></path></svg>
                            </div>
                            <div>
                                <strong><?php esc_html_e('Message from nursery', 'nursery-waiting-list'); ?></strong>
                                <p><?php echo esc_html($entry['public_notes']); ?></p>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>

        <div class="nwl-results-footer">
            <p><?php esc_html_e('If you have any questions about your application, please contact us directly.', 'nursery-waiting-list'); ?></p>
            <button type="button" class="nwl-new-search-btn" id="nwl-new-search">
                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="1 4 1 10 7 10"></polyline><path d="M3.51 15a9 9 0 1 0 2.13-9.36L1 10"></path></svg>
                <?php esc_html_e('Search Again', 'nursery-waiting-list'); ?>
            </button>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Get status icon character
     */
    private function get_status_icon($status) {
        $icons = array(
            'pending' => '',
            'waitlisted' => '',
            'offered' => '',
            'enrolled' => '',
            'withdrawn' => '',
        );
        return isset($icons[$status]) ? $icons[$status] : '';
    }
}
