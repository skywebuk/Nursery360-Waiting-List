<?php
/**
 * Email handling class
 */

if (!defined('ABSPATH')) {
    exit;
}

class NWL_Email {

    private static $instance = null;

    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        add_action('nwl_entry_created', array($this, 'send_registration_email'), 10, 2);
        add_action('nwl_status_changed', array($this, 'on_status_change'), 10, 3);
    }

    /**
     * Send registration confirmation email
     */
    public function send_registration_email($entry_id, $data) {
        if (!get_option('nwl_send_registration_email', true)) {
            return;
        }

        $entry = NWL_Entry::get_instance()->get($entry_id);
        
        if (!$entry) {
            return;
        }

        $template = $this->get_template('registration_confirmation');
        
        if (!$template) {
            return;
        }

        $subject = $this->parse_template($template->subject, $entry);
        $body = $this->parse_template($template->body, $entry);

        $this->send($entry->parent_email, $subject, $body, 'registration_confirmation', $entry_id, $template->id);
    }

    /**
     * Handle status change notifications
     */
    public function on_status_change($entry_id, $old_status, $new_status) {
        if (!get_option('nwl_send_status_emails', true)) {
            return;
        }

        $entry = NWL_Entry::get_instance()->get($entry_id);
        
        if (!$entry) {
            return;
        }

        // Send offer email when status changes to 'offered'
        if ($new_status === 'offered') {
            $template = $this->get_template('place_offered');
        } else {
            $template = $this->get_template('status_change');
        }

        if (!$template) {
            return;
        }

        $subject = $this->parse_template($template->subject, $entry);
        $body = $this->parse_template($template->body, $entry);

        $this->send($entry->parent_email, $subject, $body, 'status_change', $entry_id, $template->id);
    }

    /**
     * Send email to a single entry
     */
    public function send_to_entry($entry_id, $template_key, $custom_message = '') {
        $entry = NWL_Entry::get_instance()->get($entry_id);
        
        if (!$entry) {
            return new WP_Error('not_found', __('Entry not found.', 'nursery-waiting-list'));
        }

        $template = $this->get_template($template_key);
        
        if (!$template) {
            return new WP_Error('template_not_found', __('Email template not found.', 'nursery-waiting-list'));
        }

        $subject = $this->parse_template($template->subject, $entry);
        $body = $this->parse_template($template->body, $entry, array('message_content' => $custom_message));

        return $this->send($entry->parent_email, $subject, $body, $template_key, $entry_id, $template->id);
    }

    /**
     * Send email to multiple entries
     */
    public function send_bulk($entry_ids, $template_key, $custom_message = '') {
        $results = array(
            'success' => 0,
            'failed' => 0,
            'errors' => array(),
        );

        foreach ($entry_ids as $entry_id) {
            $result = $this->send_to_entry($entry_id, $template_key, $custom_message);
            
            if (is_wp_error($result)) {
                $results['failed']++;
                $results['errors'][] = array(
                    'entry_id' => $entry_id,
                    'error' => $result->get_error_message(),
                );
            } else {
                $results['success']++;
            }
        }

        return $results;
    }

    /**
     * Send email with custom content
     */
    public function send_custom($entry_id, $subject, $body) {
        $entry = NWL_Entry::get_instance()->get($entry_id);
        
        if (!$entry) {
            return new WP_Error('not_found', __('Entry not found.', 'nursery-waiting-list'));
        }

        $subject = $this->parse_template($subject, $entry);
        $body = $this->parse_template($body, $entry);

        return $this->send($entry->parent_email, $subject, $body, 'custom', $entry_id);
    }

    /**
     * Send email
     */
    public function send($to, $subject, $body, $type, $entry_id = null, $template_id = null) {
        $from_name = get_option('nwl_email_from_name', get_bloginfo('name'));
        $from_email = get_option('nwl_email_from_address', get_option('admin_email'));

        $headers = array(
            'Content-Type: text/html; charset=UTF-8',
            'From: ' . $from_name . ' <' . $from_email . '>',
        );

        $reply_to = get_option('nwl_email_reply_to', '');
        if ($reply_to) {
            $headers[] = 'Reply-To: ' . $reply_to;
        }

        // Get attachments from template
        $attachments = array();
        if ($template_id) {
            $template = $this->get_template_by_id($template_id);
            if ($template && !empty($template->attachments)) {
                $attachment_ids = json_decode($template->attachments, true);
                if (is_array($attachment_ids)) {
                    foreach ($attachment_ids as $attachment_id) {
                        $file_path = get_attached_file($attachment_id);
                        if ($file_path && file_exists($file_path)) {
                            $attachments[] = $file_path;
                        }
                    }
                }
            }
        }

        // Apply filters for customization
        $headers = apply_filters('nwl_email_headers', $headers, $type, $entry_id);
        $body = apply_filters('nwl_email_body', $body, $type, $entry_id);
        $subject = apply_filters('nwl_email_subject', $subject, $type, $entry_id);
        $attachments = apply_filters('nwl_email_attachments', $attachments, $type, $entry_id, $template_id);

        $sent = wp_mail($to, $subject, $body, $headers, $attachments);

        // Log the email
        $this->log_email(array(
            'entry_id' => $entry_id,
            'email_type' => $type,
            'template_id' => $template_id,
            'recipient_email' => $to,
            'subject' => $subject,
            'body' => $body,
            'status' => $sent ? 'sent' : 'failed',
            'sent_at' => $sent ? current_time('mysql') : null,
        ));

        if (!$sent) {
            return new WP_Error('send_failed', __('Failed to send email.', 'nursery-waiting-list'));
        }

        // Add note to entry
        if ($entry_id) {
            $note_text = sprintf(__('Email sent: %s', 'nursery-waiting-list'), $subject);
            if (!empty($attachments)) {
                $note_text .= sprintf(__(' (with %d attachment(s))', 'nursery-waiting-list'), count($attachments));
            }
            NWL_Entry::get_instance()->add_note($entry_id, 'email', $note_text);
        }

        return true;
    }

    /**
     * Get email template
     */
    public function get_template($key) {
        global $wpdb;

        $table = $wpdb->prefix . NWL_TABLE_TEMPLATES;

        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table WHERE template_key = %s AND is_active = 1",
            $key
        ));
    }

    /**
     * Get email template by ID
     */
    public function get_template_by_id($id) {
        global $wpdb;

        $table = $wpdb->prefix . NWL_TABLE_TEMPLATES;

        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table WHERE id = %d AND is_active = 1",
            $id
        ));
    }

    /**
     * Get all templates
     */
    public function get_templates() {
        global $wpdb;
        
        $table = $wpdb->prefix . NWL_TABLE_TEMPLATES;
        
        return $wpdb->get_results("SELECT * FROM $table ORDER BY template_name ASC");
    }

    /**
     * Update template
     */
    public function update_template($id, $data) {
        global $wpdb;

        $table = $wpdb->prefix . NWL_TABLE_TEMPLATES;

        $allowed = array('template_name', 'subject', 'body', 'status_trigger', 'attachments', 'is_active');
        $update_data = array_intersect_key($data, array_flip($allowed));
        $update_data['updated_at'] = current_time('mysql');

        return $wpdb->update($table, $update_data, array('id' => $id));
    }

    /**
     * Create custom template
     */
    public function create_template($data) {
        global $wpdb;
        
        $table = $wpdb->prefix . NWL_TABLE_TEMPLATES;
        
        $insert_data = array(
            'template_key' => sanitize_key($data['template_key']),
            'template_name' => sanitize_text_field($data['template_name']),
            'subject' => sanitize_text_field($data['subject']),
            'body' => wp_kses_post($data['body']),
            'is_active' => isset($data['is_active']) ? (int) $data['is_active'] : 1,
            'is_system' => 0,
            'created_at' => current_time('mysql'),
        );
        
        $wpdb->insert($table, $insert_data);
        
        return $wpdb->insert_id;
    }

    /**
     * Delete custom template
     */
    public function delete_template($id) {
        global $wpdb;
        
        $table = $wpdb->prefix . NWL_TABLE_TEMPLATES;
        
        // Don't delete system templates
        $template = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE id = %d", $id));
        
        if ($template && $template->is_system) {
            return new WP_Error('system_template', __('Cannot delete system templates.', 'nursery-waiting-list'));
        }
        
        return $wpdb->delete($table, array('id' => $id));
    }

    /**
     * Parse template with entry data
     */
    public function parse_template($template, $entry, $extra = array()) {
        $entry_handler = NWL_Entry::get_instance();
        
        // Get settings
        $nursery_name = get_option('nwl_nursery_name', get_bloginfo('name'));
        $nursery_email = get_option('nwl_nursery_email', get_option('admin_email'));
        $nursery_phone = get_option('nwl_nursery_phone', '');
        $nursery_address = get_option('nwl_nursery_address', '');
        $stats_page = get_permalink(get_page_by_path('waiting-list-stats'));

        // Build replacement array
        $replacements = array(
            // Entry data
            '{{waiting_list_number}}' => $entry->waiting_list_number,
            '{{child_name}}' => $entry_handler->get_child_name($entry),
            '{{child_first_name}}' => $entry->child_first_name,
            '{{child_last_name}}' => $entry->child_last_name,
            '{{child_dob}}' => $entry->child_dob ? date_i18n(get_option('date_format'), strtotime($entry->child_dob)) : '',
            '{{parent_name}}' => $entry_handler->get_parent_name($entry),
            '{{parent_first_name}}' => $entry->parent_first_name,
            '{{parent_last_name}}' => $entry->parent_last_name,
            '{{parent_email}}' => $entry->parent_email,
            '{{parent_mobile}}' => $entry->parent_mobile,
            '{{date_added}}' => date_i18n(get_option('date_format'), strtotime($entry->created_at)),
            '{{age_group}}' => $entry->age_group,
            '{{preferred_start_date}}' => $entry->preferred_start_date ? date_i18n(get_option('date_format'), strtotime($entry->preferred_start_date)) : '',
            '{{status}}' => $entry_handler->get_status_label($entry->status),
            '{{public_notes}}' => $entry->public_notes,
            '{{offer_deadline}}' => $entry->offer_deadline ? date_i18n(get_option('date_format'), strtotime($entry->offer_deadline)) : '',

            // Nursery details
            '{{nursery_name}}' => $nursery_name,
            '{{nursery_email}}' => $nursery_email,
            '{{nursery_phone}}' => $nursery_phone,
            '{{nursery_address}}' => $nursery_address,
            '{{stats_page_url}}' => $stats_page,

            // Extra content
            '{{message_content}}' => isset($extra['message_content']) ? $extra['message_content'] : '',
        );

        // Allow filtering of replacements
        $replacements = apply_filters('nwl_email_replacements', $replacements, $entry, $extra);

        // Simple mustache-style conditionals
        $template = $this->parse_conditionals($template, $replacements);

        // Replace placeholders
        return str_replace(array_keys($replacements), array_values($replacements), $template);
    }

    /**
     * Parse simple mustache conditionals
     */
    private function parse_conditionals($template, $replacements) {
        // Match {{#variable}}content{{/variable}} and {{^variable}}content{{/variable}}
        $pattern = '/\{\{([#\^])(\w+)\}\}(.*?)\{\{\/\2\}\}/s';
        
        return preg_replace_callback($pattern, function($matches) use ($replacements) {
            $type = $matches[1];
            $variable = '{{' . $matches[2] . '}}';
            $content = $matches[3];
            
            $value = isset($replacements[$variable]) ? $replacements[$variable] : '';
            $has_value = !empty($value);
            
            // # means show if truthy, ^ means show if falsy
            if (($type === '#' && $has_value) || ($type === '^' && !$has_value)) {
                return $content;
            }
            
            return '';
        }, $template);
    }

    /**
     * Log email
     */
    private function log_email($data) {
        global $wpdb;
        
        $table = $wpdb->prefix . NWL_TABLE_EMAILS;
        
        $data['created_at'] = current_time('mysql');
        $data['created_by'] = is_user_logged_in() ? get_current_user_id() : null;
        
        $wpdb->insert($table, $data);
        
        return $wpdb->insert_id;
    }

    /**
     * Get email logs for entry
     */
    public function get_logs_for_entry($entry_id) {
        global $wpdb;
        
        $table = $wpdb->prefix . NWL_TABLE_EMAILS;
        
        return $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $table WHERE entry_id = %d ORDER BY created_at DESC",
            $entry_id
        ));
    }

    /**
     * Get all email logs
     */
    public function get_logs($args = array()) {
        global $wpdb;
        
        $defaults = array(
            'per_page' => 50,
            'page' => 1,
            'email_type' => '',
            'status' => '',
        );
        
        $args = wp_parse_args($args, $defaults);
        
        $table = $wpdb->prefix . NWL_TABLE_EMAILS;
        
        $where = array('1=1');
        $values = array();
        
        if (!empty($args['email_type'])) {
            $where[] = 'email_type = %s';
            $values[] = $args['email_type'];
        }
        
        if (!empty($args['status'])) {
            $where[] = 'status = %s';
            $values[] = $args['status'];
        }
        
        $where_clause = implode(' AND ', $where);
        $offset = ($args['page'] - 1) * $args['per_page'];
        
        $sql = "SELECT * FROM $table WHERE $where_clause ORDER BY created_at DESC LIMIT %d OFFSET %d";
        $values[] = $args['per_page'];
        $values[] = $offset;
        
        return $wpdb->get_results($wpdb->prepare($sql, $values));
    }

    /**
     * Get available template variables
     */
    public function get_template_variables() {
        return array(
            __('Entry Data', 'nursery-waiting-list') => array(
                '{{waiting_list_number}}' => __('Waiting list reference number', 'nursery-waiting-list'),
                '{{child_name}}' => __('Child\'s full name', 'nursery-waiting-list'),
                '{{child_first_name}}' => __('Child\'s first name', 'nursery-waiting-list'),
                '{{child_last_name}}' => __('Child\'s last name', 'nursery-waiting-list'),
                '{{child_dob}}' => __('Child\'s date of birth', 'nursery-waiting-list'),
                '{{parent_name}}' => __('Parent/carer\'s full name', 'nursery-waiting-list'),
                '{{parent_first_name}}' => __('Parent/carer\'s first name', 'nursery-waiting-list'),
                '{{parent_last_name}}' => __('Parent/carer\'s last name', 'nursery-waiting-list'),
                '{{parent_email}}' => __('Parent/carer\'s email', 'nursery-waiting-list'),
                '{{parent_mobile}}' => __('Parent/carer\'s mobile number', 'nursery-waiting-list'),
                '{{date_added}}' => __('Date added to waiting list', 'nursery-waiting-list'),
                '{{age_group}}' => __('Age group', 'nursery-waiting-list'),
                '{{preferred_start_date}}' => __('Preferred start date', 'nursery-waiting-list'),
                '{{status}}' => __('Current status', 'nursery-waiting-list'),
                '{{public_notes}}' => __('Public notes (visible to parents/carers)', 'nursery-waiting-list'),
                '{{offer_deadline}}' => __('Offer response deadline', 'nursery-waiting-list'),
            ),
            __('Nursery Details', 'nursery-waiting-list') => array(
                '{{nursery_name}}' => __('Nursery name', 'nursery-waiting-list'),
                '{{nursery_email}}' => __('Nursery email', 'nursery-waiting-list'),
                '{{nursery_phone}}' => __('Nursery phone', 'nursery-waiting-list'),
                '{{nursery_address}}' => __('Nursery address', 'nursery-waiting-list'),
                '{{stats_page_url}}' => __('Link to check status page', 'nursery-waiting-list'),
            ),
            __('Custom Content', 'nursery-waiting-list') => array(
                '{{message_content}}' => __('Custom message content (for bulk emails)', 'nursery-waiting-list'),
            ),
        );
    }
}
