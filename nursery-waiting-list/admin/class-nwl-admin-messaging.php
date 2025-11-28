<?php
/**
 * Admin messaging functionality
 */

if (!defined('ABSPATH')) {
    exit;
}

class NWL_Admin_Messaging {

    private static $instance = null;

    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        add_action('wp_ajax_nwl_send_email', array($this, 'ajax_send_email'));
        add_action('wp_ajax_nwl_get_filtered_count', array($this, 'ajax_get_filtered_count'));
    }

    /**
     * Render messaging page
     */
    public static function render_page() {
        if (!current_user_can('nwl_send_emails')) {
            wp_die(__('You do not have permission to access this page.', 'nursery-waiting-list'));
        }

        $email_handler = NWL_Email::get_instance();
        $templates = $email_handler->get_templates();
        
        // Check for pre-selected entries
        $entry_ids = isset($_GET['entry_ids']) ? sanitize_text_field($_GET['entry_ids']) : '';
        $single_entry_id = isset($_GET['entry_id']) ? absint($_GET['entry_id']) : 0;
        
        $preselected_entries = array();
        if ($single_entry_id) {
            $entry = NWL_Entry::get_instance()->get($single_entry_id);
            if ($entry) {
                $preselected_entries[] = $entry;
            }
        } elseif ($entry_ids) {
            $ids = array_map('absint', explode(',', $entry_ids));
            foreach ($ids as $id) {
                $entry = NWL_Entry::get_instance()->get($id);
                if ($entry) {
                    $preselected_entries[] = $entry;
                }
            }
        }
        ?>
        <div class="wrap nwl-admin-wrap">
            <h1><?php esc_html_e('Send Emails', 'nursery-waiting-list'); ?></h1>

            <div class="nwl-messaging-container">
                <div class="nwl-messaging-main">
                    <form id="nwl-send-email-form" method="post">
                        <?php wp_nonce_field('nwl_send_email', 'nwl_email_nonce'); ?>

                        <!-- Recipient Selection -->
                        <div class="nwl-form-section">
                            <h2><?php esc_html_e('Recipients', 'nursery-waiting-list'); ?></h2>

                            <?php if (!empty($preselected_entries)) : ?>
                                <div class="nwl-preselected-entries">
                                    <p><strong><?php esc_html_e('Sending to:', 'nursery-waiting-list'); ?></strong></p>
                                    <ul>
                                        <?php foreach ($preselected_entries as $entry) : ?>
                                            <li>
                                                <?php echo esc_html(NWL_Entry::get_instance()->get_child_name($entry)); ?> 
                                                (<?php echo esc_html($entry->parent_email); ?>)
                                                <input type="hidden" name="entry_ids[]" value="<?php echo esc_attr($entry->id); ?>">
                                            </li>
                                        <?php endforeach; ?>
                                    </ul>
                                    <input type="hidden" name="send_mode" value="selected">
                                </div>
                            <?php else : ?>
                                <div class="nwl-recipient-options">
                                    <label class="nwl-radio-option">
                                        <input type="radio" name="send_mode" value="filtered" checked>
                                        <?php esc_html_e('Send to filtered entries', 'nursery-waiting-list'); ?>
                                    </label>
                                    <label class="nwl-radio-option">
                                        <input type="radio" name="send_mode" value="all">
                                        <?php esc_html_e('Send to all entries', 'nursery-waiting-list'); ?>
                                    </label>
                                </div>

                                <!-- Filters -->
                                <div id="nwl-filter-options" class="nwl-filter-options">
                                    <div class="nwl-form-row">
                                        <div class="nwl-form-field">
                                            <label for="filter_status"><?php esc_html_e('Status', 'nursery-waiting-list'); ?></label>
                                            <select id="filter_status" name="filter_status">
                                                <option value=""><?php esc_html_e('All Statuses', 'nursery-waiting-list'); ?></option>
                                                <?php foreach (NWL_Database::get_statuses() as $key => $label) : ?>
                                                    <option value="<?php echo esc_attr($key); ?>"><?php echo esc_html($label); ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                        <div class="nwl-form-field">
                                            <label for="filter_priority"><?php esc_html_e('Priority', 'nursery-waiting-list'); ?></label>
                                            <select id="filter_priority" name="filter_priority">
                                                <option value=""><?php esc_html_e('All Priorities', 'nursery-waiting-list'); ?></option>
                                                <?php foreach (NWL_Database::get_priorities() as $key => $label) : ?>
                                                    <option value="<?php echo esc_attr($key); ?>"><?php echo esc_html($label); ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                    </div>

                                    <div class="nwl-recipient-count">
                                        <span id="nwl-count-display"><?php esc_html_e('Loading...', 'nursery-waiting-list'); ?></span>
                                        <button type="button" id="nwl-refresh-count" class="button button-small">
                                            <?php esc_html_e('Refresh Count', 'nursery-waiting-list'); ?>
                                        </button>
                                    </div>
                                </div>
                            <?php endif; ?>
                        </div>

                        <!-- Email Content -->
                        <div class="nwl-form-section">
                            <h2><?php esc_html_e('Email Content', 'nursery-waiting-list'); ?></h2>

                            <div class="nwl-form-row">
                                <div class="nwl-form-field nwl-full-width">
                                    <label for="template_id"><?php esc_html_e('Email Template', 'nursery-waiting-list'); ?></label>
                                    <select id="template_id" name="template_id">
                                        <option value=""><?php esc_html_e('— Select Template —', 'nursery-waiting-list'); ?></option>
                                        <?php foreach ($templates as $template) : ?>
                                            <?php if ($template->is_active) : ?>
                                                <option value="<?php echo esc_attr($template->id); ?>" 
                                                        data-subject="<?php echo esc_attr($template->subject); ?>">
                                                    <?php echo esc_html($template->template_name); ?>
                                                </option>
                                            <?php endif; ?>
                                        <?php endforeach; ?>
                                        <option value="custom"><?php esc_html_e('Custom Email', 'nursery-waiting-list'); ?></option>
                                    </select>
                                </div>
                            </div>

                            <div id="nwl-custom-email-fields" style="display: none;">
                                <div class="nwl-form-row">
                                    <div class="nwl-form-field nwl-full-width">
                                        <label for="custom_subject"><?php esc_html_e('Subject', 'nursery-waiting-list'); ?></label>
                                        <input type="text" id="custom_subject" name="custom_subject" class="large-text">
                                    </div>
                                </div>

                                <div class="nwl-form-row">
                                    <div class="nwl-form-field nwl-full-width">
                                        <label for="custom_body"><?php esc_html_e('Message', 'nursery-waiting-list'); ?></label>
                                        <?php 
                                        wp_editor('', 'custom_body', array(
                                            'textarea_name' => 'custom_body',
                                            'textarea_rows' => 15,
                                            'media_buttons' => false,
                                        )); 
                                        ?>
                                    </div>
                                </div>
                            </div>

                            <div id="nwl-template-message-field">
                                <div class="nwl-form-row">
                                    <div class="nwl-form-field nwl-full-width">
                                        <label for="message_content"><?php esc_html_e('Additional Message (Optional)', 'nursery-waiting-list'); ?></label>
                                        <textarea id="message_content" name="message_content" rows="5" class="large-text"
                                                  placeholder="<?php esc_attr_e('Add a custom message to include in the email. This will replace {{message_content}} in the template.', 'nursery-waiting-list'); ?>"></textarea>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Submit -->
                        <div class="nwl-form-actions">
                            <button type="submit" class="button button-primary button-large" id="nwl-send-btn">
                                <?php esc_html_e('Send Email', 'nursery-waiting-list'); ?>
                            </button>
                            <span class="spinner" id="nwl-send-spinner"></span>
                        </div>
                    </form>
                </div>

                <!-- Sidebar -->
                <div class="nwl-messaging-sidebar">
                    <div class="nwl-sidebar-box">
                        <h3><?php esc_html_e('Available Variables', 'nursery-waiting-list'); ?></h3>
                        <p class="description"><?php esc_html_e('Click to copy.', 'nursery-waiting-list'); ?></p>
                        
                        <ul class="nwl-variable-list">
                            <li><code class="nwl-copy-var">{{child_name}}</code></li>
                            <li><code class="nwl-copy-var">{{parent_name}}</code></li>
                            <li><code class="nwl-copy-var">{{waiting_list_number}}</code></li>
                            <li><code class="nwl-copy-var">{{date_added}}</code></li>
                            <li><code class="nwl-copy-var">{{status}}</code></li>
                            <li><code class="nwl-copy-var">{{nursery_name}}</code></li>
                            <li><code class="nwl-copy-var">{{stats_page_url}}</code></li>
                        </ul>
                        
                        <p>
                            <a href="<?php echo esc_url(NWL_Admin::get_page_url('nwl-templates')); ?>">
                                <?php esc_html_e('View all variables →', 'nursery-waiting-list'); ?>
                            </a>
                        </p>
                    </div>

                    <div class="nwl-sidebar-box">
                        <h3><?php esc_html_e('Email Tips', 'nursery-waiting-list'); ?></h3>
                        <ul>
                            <li><?php esc_html_e('Use templates for consistent formatting', 'nursery-waiting-list'); ?></li>
                            <li><?php esc_html_e('Personalize with merge tags', 'nursery-waiting-list'); ?></li>
                            <li><?php esc_html_e('All emails are logged for reference', 'nursery-waiting-list'); ?></li>
                            <li><?php esc_html_e('Include the status page URL so parents can check their status', 'nursery-waiting-list'); ?></li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>

        <script>
        jQuery(document).ready(function($) {
            // Template selection
            $('#template_id').on('change', function() {
                var val = $(this).val();
                if (val === 'custom') {
                    $('#nwl-custom-email-fields').show();
                    $('#nwl-template-message-field').hide();
                } else if (val) {
                    $('#nwl-custom-email-fields').hide();
                    $('#nwl-template-message-field').show();
                } else {
                    $('#nwl-custom-email-fields').hide();
                    $('#nwl-template-message-field').show();
                }
            });

            // Send mode change
            $('input[name="send_mode"]').on('change', function() {
                if ($(this).val() === 'filtered') {
                    $('#nwl-filter-options').show();
                    updateRecipientCount();
                } else {
                    $('#nwl-filter-options').hide();
                }
            });

            // Filter changes
            $('#filter_status, #filter_priority').on('change', function() {
                updateRecipientCount();
            });

            // Refresh count button
            $('#nwl-refresh-count').on('click', function() {
                updateRecipientCount();
            });

            // Update recipient count
            function updateRecipientCount() {
                $('#nwl-count-display').text('<?php esc_html_e('Loading...', 'nursery-waiting-list'); ?>');
                
                $.post(nwlAdmin.ajaxUrl, {
                    action: 'nwl_get_filtered_count',
                    nonce: nwlAdmin.nonce,
                    status: $('#filter_status').val(),
                    priority: $('#filter_priority').val()
                }, function(response) {
                    if (response.success) {
                        $('#nwl-count-display').text(response.data.message);
                    }
                });
            }

            // Initial count
            if ($('#nwl-filter-options').is(':visible')) {
                updateRecipientCount();
            }

            // Copy variable
            $(document).on('click', '.nwl-copy-var', function() {
                var text = $(this).text();
                navigator.clipboard.writeText(text);

                var $this = $(this);
                $this.addClass('copied');
                setTimeout(function() {
                    $this.removeClass('copied');
                }, 1000);
            });

            // Form submission handler
            $('#nwl-send-email-form').on('submit', function(e) {
                e.preventDefault();

                var $form = $(this);
                var $btn = $('#nwl-send-btn');
                var $spinner = $('#nwl-send-spinner');
                var templateId = $('#template_id').val();

                // Validation
                if (!templateId) {
                    alert('<?php esc_html_e('Please select an email template.', 'nursery-waiting-list'); ?>');
                    return false;
                }

                // Custom email validation
                if (templateId === 'custom') {
                    var customSubject = $('#custom_subject').val();
                    var customBody = '';

                    // Get content from TinyMCE editor if active
                    if (typeof tinyMCE !== 'undefined' && tinyMCE.get('custom_body')) {
                        customBody = tinyMCE.get('custom_body').getContent();
                    } else {
                        customBody = $('#custom_body').val();
                    }

                    if (!customSubject.trim()) {
                        alert('<?php esc_html_e('Please enter a subject for your custom email.', 'nursery-waiting-list'); ?>');
                        return false;
                    }

                    if (!customBody.trim()) {
                        alert('<?php esc_html_e('Please enter a message for your custom email.', 'nursery-waiting-list'); ?>');
                        return false;
                    }
                }

                // Show loading state
                $btn.prop('disabled', true);
                $spinner.addClass('is-active');

                // Prepare form data
                var formData = $form.serialize();

                // Sync TinyMCE content if using custom email
                if (templateId === 'custom' && typeof tinyMCE !== 'undefined' && tinyMCE.get('custom_body')) {
                    tinyMCE.triggerSave();
                    formData = $form.serialize();
                }

                // Send AJAX request
                $.ajax({
                    url: nwlAdmin.ajaxUrl,
                    type: 'POST',
                    data: formData + '&action=nwl_send_email',
                    success: function(response) {
                        if (response.success) {
                            alert(response.data.message);

                            // Show any errors
                            if (response.data.errors && response.data.errors.length > 0) {
                                console.log('Email errors:', response.data.errors);
                            }

                            // Redirect back to entries if was sending to selected entries
                            if ($('input[name="send_mode"][value="selected"]').length) {
                                window.location.href = '<?php echo esc_url(NWL_Admin::get_page_url('nwl-entries')); ?>';
                            }
                        } else {
                            alert(response.data.message || '<?php esc_html_e('An error occurred. Please try again.', 'nursery-waiting-list'); ?>');
                        }
                    },
                    error: function(xhr, status, error) {
                        alert('<?php esc_html_e('An error occurred. Please try again.', 'nursery-waiting-list'); ?>');
                        console.error('AJAX error:', error);
                    },
                    complete: function() {
                        $btn.prop('disabled', false);
                        $spinner.removeClass('is-active');
                    }
                });

                return false;
            });
        });
        </script>
        <?php
    }

    /**
     * AJAX: Send email
     */
    public function ajax_send_email() {
        // Verify nonce
        if (!check_ajax_referer('nwl_send_email', 'nwl_email_nonce', false)) {
            wp_send_json_error(array('message' => __('Security check failed. Please refresh the page and try again.', 'nursery-waiting-list')));
            return;
        }

        if (!current_user_can('nwl_send_emails')) {
            wp_send_json_error(array('message' => __('Permission denied.', 'nursery-waiting-list')));
            return;
        }

        $send_mode = isset($_POST['send_mode']) ? sanitize_text_field($_POST['send_mode']) : '';
        $template_id = isset($_POST['template_id']) ? sanitize_text_field($_POST['template_id']) : '';

        if (empty($template_id)) {
            wp_send_json_error(array('message' => __('Please select an email template.', 'nursery-waiting-list')));
            return;
        }
        $message_content = isset($_POST['message_content']) ? sanitize_textarea_field($_POST['message_content']) : '';

        $email_handler = NWL_Email::get_instance();
        $entry_handler = NWL_Entry::get_instance();

        // Get entries to send to
        $entries = array();
        
        if ($send_mode === 'selected' && isset($_POST['entry_ids'])) {
            $entry_ids = array_map('absint', $_POST['entry_ids']);
            foreach ($entry_ids as $id) {
                $entry = $entry_handler->get($id);
                if ($entry) {
                    $entries[] = $entry;
                }
            }
        } else {
            // Get by filter
            $args = array(
                'per_page' => 10000,
                'page' => 1,
            );
            
            if ($send_mode === 'filtered') {
                $args['status'] = isset($_POST['filter_status']) ? sanitize_text_field($_POST['filter_status']) : '';
                $args['priority'] = isset($_POST['filter_priority']) ? sanitize_text_field($_POST['filter_priority']) : '';
            }
            
            // Exclude removed entries
            if (empty($args['status'])) {
                $args['status'] = array('pending', 'contacted', 'waitlisted', 'offered', 'accepted', 'enrolled');
            }
            
            $result = $entry_handler->get_entries($args);
            $entries = $result['entries'];
        }

        if (empty($entries)) {
            wp_send_json_error(array('message' => __('No recipients found.', 'nursery-waiting-list')));
        }

        // Send emails
        $success = 0;
        $failed = 0;
        $errors = array();

        foreach ($entries as $entry) {
            if ($template_id === 'custom') {
                // Custom email
                $subject = sanitize_text_field($_POST['custom_subject']);
                $body = wp_kses_post($_POST['custom_body']);
                $result = $email_handler->send_custom($entry->id, $subject, $body);
            } else {
                // Template email
                $template = $email_handler->get_template_by_id($template_id);
                if (!$template) {
                    $template_key = $this->get_template_key_by_id($template_id);
                    $result = $email_handler->send_to_entry($entry->id, $template_key, $message_content);
                } else {
                    $result = $email_handler->send_to_entry($entry->id, $template->template_key, $message_content);
                }
            }

            if (is_wp_error($result)) {
                $failed++;
                $errors[] = sprintf(
                    __('%s: %s', 'nursery-waiting-list'),
                    $entry->parent_email,
                    $result->get_error_message()
                );
            } else {
                $success++;
            }
        }

        $message = sprintf(
            _n(
                '%d email sent successfully.',
                '%d emails sent successfully.',
                $success,
                'nursery-waiting-list'
            ),
            $success
        );

        if ($failed > 0) {
            $message .= ' ' . sprintf(
                _n(
                    '%d email failed to send.',
                    '%d emails failed to send.',
                    $failed,
                    'nursery-waiting-list'
                ),
                $failed
            );
        }

        wp_send_json_success(array(
            'message' => $message,
            'success' => $success,
            'failed' => $failed,
            'errors' => $errors,
        ));
    }

    /**
     * Get template key by ID
     */
    private function get_template_key_by_id($id) {
        global $wpdb;
        $table = $wpdb->prefix . NWL_TABLE_TEMPLATES;
        return $wpdb->get_var($wpdb->prepare("SELECT template_key FROM $table WHERE id = %d", $id));
    }

    /**
     * AJAX: Get filtered count
     */
    public function ajax_get_filtered_count() {
        check_ajax_referer('nwl_admin', 'nonce');

        $args = array(
            'status' => isset($_POST['status']) ? sanitize_text_field($_POST['status']) : '',
            'priority' => isset($_POST['priority']) ? sanitize_text_field($_POST['priority']) : '',
            'per_page' => 1,
            'page' => 1,
        );

        // Exclude removed entries if no status filter
        if (empty($args['status'])) {
            $args['status'] = array('pending', 'contacted', 'waitlisted', 'offered', 'accepted', 'enrolled');
        }

        $result = NWL_Entry::get_instance()->get_entries($args);

        wp_send_json_success(array(
            'count' => $result['total'],
            'message' => sprintf(
                _n('%d recipient', '%d recipients', $result['total'], 'nursery-waiting-list'),
                $result['total']
            ),
        ));
    }
}
