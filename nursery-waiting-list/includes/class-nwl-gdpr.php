<?php
/**
 * GDPR compliance class
 */

if (!defined('ABSPATH')) {
    exit;
}

class NWL_GDPR {

    private static $instance = null;

    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        // WordPress privacy hooks
        add_action('admin_init', array($this, 'add_privacy_policy_content'));
        add_filter('wp_privacy_personal_data_exporters', array($this, 'register_data_exporter'));
        add_filter('wp_privacy_personal_data_erasers', array($this, 'register_data_eraser'));
        
        // Schedule cleanup
        add_action('nwl_daily_cleanup', array($this, 'process_scheduled_deletions'));
        
        if (!wp_next_scheduled('nwl_daily_cleanup')) {
            wp_schedule_event(time(), 'daily', 'nwl_daily_cleanup');
        }
    }

    /**
     * Add privacy policy content suggestion
     */
    public function add_privacy_policy_content() {
        if (!function_exists('wp_add_privacy_policy_content')) {
            return;
        }

        $content = sprintf(
            '<h3>%s</h3>
            <p>%s</p>
            <h4>%s</h4>
            <p>%s</p>
            <ul>
                <li>%s</li>
                <li>%s</li>
                <li>%s</li>
                <li>%s</li>
                <li>%s</li>
            </ul>
            <h4>%s</h4>
            <p>%s</p>
            <h4>%s</h4>
            <p>%s</p>
            <h4>%s</h4>
            <p>%s</p>',
            __('Nursery Waiting List', 'nursery-waiting-list'),
            __('When you submit an application to join our nursery waiting list, we collect and store personal information about you and your child.', 'nursery-waiting-list'),
            __('What data we collect', 'nursery-waiting-list'),
            __('We collect the following personal data:', 'nursery-waiting-list'),
            __('Parent/guardian names, email addresses, phone numbers, and postal addresses', 'nursery-waiting-list'),
            __('Child\'s name, date of birth, and gender', 'nursery-waiting-list'),
            __('Information about any additional needs, allergies, or medical conditions', 'nursery-waiting-list'),
            __('Your preferred start date, room/age group preferences, and session requirements', 'nursery-waiting-list'),
            __('Communication history including emails sent to you', 'nursery-waiting-list'),
            __('Why we collect this data', 'nursery-waiting-list'),
            __('We use this information to manage your waiting list application, contact you about available places, and prepare for your child\'s enrolment at our nursery.', 'nursery-waiting-list'),
            __('How long we keep your data', 'nursery-waiting-list'),
            __('We retain waiting list data for as long as your application is active. If you decline a place or withdraw your application, we will retain your data for 12 months before deletion unless you request earlier removal.', 'nursery-waiting-list'),
            __('Your rights', 'nursery-waiting-list'),
            __('You have the right to access, correct, or request deletion of your personal data. You can check your waiting list status online or contact us to update your information or request data removal.', 'nursery-waiting-list')
        );

        wp_add_privacy_policy_content(
            'Nursery Waiting List',
            wp_kses_post($content)
        );
    }

    /**
     * Register data exporter
     */
    public function register_data_exporter($exporters) {
        $exporters['nursery-waiting-list'] = array(
            'exporter_friendly_name' => __('Nursery Waiting List', 'nursery-waiting-list'),
            'callback' => array($this, 'export_personal_data'),
        );
        return $exporters;
    }

    /**
     * Register data eraser
     */
    public function register_data_eraser($erasers) {
        $erasers['nursery-waiting-list'] = array(
            'eraser_friendly_name' => __('Nursery Waiting List', 'nursery-waiting-list'),
            'callback' => array($this, 'erase_personal_data'),
        );
        return $erasers;
    }

    /**
     * Export personal data
     */
    public function export_personal_data($email_address, $page = 1) {
        $entry_handler = NWL_Entry::get_instance();
        $entries = $entry_handler->get_by_email($email_address);
        
        $export_items = array();
        
        foreach ($entries as $entry) {
            $data = array(
                array(
                    'name' => __('Waiting List Number', 'nursery-waiting-list'),
                    'value' => $entry->waiting_list_number,
                ),
                array(
                    'name' => __('Child Name', 'nursery-waiting-list'),
                    'value' => $entry_handler->get_child_name($entry),
                ),
                array(
                    'name' => __('Child Date of Birth', 'nursery-waiting-list'),
                    'value' => $entry->child_dob,
                ),
                array(
                    'name' => __('Parent Name', 'nursery-waiting-list'),
                    'value' => $entry_handler->get_parent_name($entry),
                ),
                array(
                    'name' => __('Parent Email', 'nursery-waiting-list'),
                    'value' => $entry->parent_email,
                ),
                array(
                    'name' => __('Parent Phone', 'nursery-waiting-list'),
                    'value' => $entry->parent_phone,
                ),
                array(
                    'name' => __('Parent Mobile', 'nursery-waiting-list'),
                    'value' => $entry->parent_mobile,
                ),
                array(
                    'name' => __('Address', 'nursery-waiting-list'),
                    'value' => implode(', ', array_filter(array(
                        $entry->parent_address_line1,
                        $entry->parent_address_line2,
                        $entry->parent_city,
                        $entry->parent_postcode,
                    ))),
                ),
                array(
                    'name' => __('Room Requested', 'nursery-waiting-list'),
                    'value' => $entry->room_requested,
                ),
                array(
                    'name' => __('Status', 'nursery-waiting-list'),
                    'value' => $entry_handler->get_status_label($entry->status),
                ),
                array(
                    'name' => __('Date Added', 'nursery-waiting-list'),
                    'value' => $entry->created_at,
                ),
                array(
                    'name' => __('Additional Needs', 'nursery-waiting-list'),
                    'value' => $entry->additional_needs,
                ),
                array(
                    'name' => __('Allergies', 'nursery-waiting-list'),
                    'value' => $entry->allergies,
                ),
                array(
                    'name' => __('Medical Conditions', 'nursery-waiting-list'),
                    'value' => $entry->medical_conditions,
                ),
            );

            // Remove empty values
            $data = array_filter($data, function($item) {
                return !empty($item['value']);
            });

            $export_items[] = array(
                'group_id' => 'nwl-entries',
                'group_label' => __('Waiting List Entries', 'nursery-waiting-list'),
                'item_id' => 'nwl-entry-' . $entry->id,
                'data' => array_values($data),
            );
        }

        return array(
            'data' => $export_items,
            'done' => true,
        );
    }

    /**
     * Erase personal data
     */
    public function erase_personal_data($email_address, $page = 1) {
        $entry_handler = NWL_Entry::get_instance();
        $entries = $entry_handler->get_by_email($email_address);
        
        $items_removed = 0;
        $items_retained = 0;
        $messages = array();
        
        foreach ($entries as $entry) {
            // Check if entry is in a state where deletion is allowed
            if (in_array($entry->status, array('pending', 'declined', 'removed'))) {
                $result = $entry_handler->hard_delete($entry->id);
                if (!is_wp_error($result)) {
                    $items_removed++;
                } else {
                    $items_retained++;
                    $messages[] = sprintf(
                        __('Could not delete entry %s: %s', 'nursery-waiting-list'),
                        $entry->waiting_list_number,
                        $result->get_error_message()
                    );
                }
            } else {
                // Entry is in active state - mark for deletion instead
                $entry_handler->request_deletion($entry->id);
                $items_retained++;
                $messages[] = sprintf(
                    __('Entry %s is in active state (%s) and has been marked for deletion. Please contact us to complete the removal.', 'nursery-waiting-list'),
                    $entry->waiting_list_number,
                    $entry_handler->get_status_label($entry->status)
                );
            }
        }

        return array(
            'items_removed' => $items_removed,
            'items_retained' => $items_retained,
            'messages' => $messages,
            'done' => true,
        );
    }

    /**
     * Process scheduled deletions
     */
    public function process_scheduled_deletions() {
        $entry_handler = NWL_Entry::get_instance();
        $retention_days = get_option('nwl_data_retention_days', 365);
        
        if ($retention_days <= 0) {
            return; // Retention disabled
        }

        global $wpdb;
        $table = $wpdb->prefix . NWL_TABLE_ENTRIES;
        
        // Find entries marked for deletion that have passed the retention period
        $cutoff_date = date('Y-m-d H:i:s', strtotime("-{$retention_days} days"));
        
        $entries = $wpdb->get_results($wpdb->prepare(
            "SELECT id FROM $table 
            WHERE deletion_requested = 1 
            AND deletion_requested_date < %s",
            $cutoff_date
        ));

        foreach ($entries as $entry) {
            $entry_handler->hard_delete($entry->id);
        }

        // Also find entries in 'removed' or 'declined' status past retention
        $entries = $wpdb->get_results($wpdb->prepare(
            "SELECT id FROM $table 
            WHERE status IN ('removed', 'declined') 
            AND updated_at < %s
            AND deletion_requested = 0",
            $cutoff_date
        ));

        foreach ($entries as $entry) {
            // Mark for deletion first, actual deletion happens next run
            $entry_handler->request_deletion($entry->id);
        }
    }

    /**
     * Get GDPR status for an entry
     */
    public function get_entry_gdpr_status($entry) {
        $status = array(
            'consent_given' => (bool) $entry->consent_given,
            'consent_date' => $entry->consent_date,
            'deletion_requested' => (bool) $entry->deletion_requested,
            'deletion_requested_date' => $entry->deletion_requested_date,
            'can_delete' => in_array($entry->status, array('pending', 'declined', 'removed')),
        );

        return $status;
    }

    /**
     * Anonymize entry data (alternative to deletion)
     */
    public function anonymize_entry($entry_id) {
        global $wpdb;
        
        $table = $wpdb->prefix . NWL_TABLE_ENTRIES;
        
        $anonymized_data = array(
            'child_first_name' => '[Anonymized]',
            'child_last_name' => '[Anonymized]',
            'parent_first_name' => '[Anonymized]',
            'parent_last_name' => '[Anonymized]',
            'parent_email' => 'anonymized-' . $entry_id . '@example.com',
            'parent_phone' => '',
            'parent_mobile' => '',
            'parent_address_line1' => '',
            'parent_address_line2' => '',
            'parent_city' => '',
            'parent_postcode' => '',
            'parent2_first_name' => '',
            'parent2_last_name' => '',
            'parent2_email' => '',
            'parent2_phone' => '',
            'additional_needs' => '',
            'allergies' => '',
            'medical_conditions' => '',
            'internal_notes' => '[Data anonymized]',
            'public_notes' => '',
        );

        $result = $wpdb->update($table, $anonymized_data, array('id' => $entry_id));

        if ($result !== false) {
            // Also anonymize notes
            $notes_table = $wpdb->prefix . NWL_TABLE_NOTES;
            $wpdb->update(
                $notes_table,
                array('note_content' => '[Content anonymized]'),
                array('entry_id' => $entry_id)
            );

            NWL_Entry::get_instance()->add_note($entry_id, 'system', __('Entry data anonymized.', 'nursery-waiting-list'));
            
            return true;
        }

        return false;
    }

    /**
     * Generate data retention report
     */
    public function get_retention_report() {
        global $wpdb;
        
        $table = $wpdb->prefix . NWL_TABLE_ENTRIES;
        $retention_days = get_option('nwl_data_retention_days', 365);
        
        $report = array(
            'total_entries' => $wpdb->get_var("SELECT COUNT(*) FROM $table"),
            'deletion_requested' => $wpdb->get_var("SELECT COUNT(*) FROM $table WHERE deletion_requested = 1"),
            'past_retention' => 0,
            'retention_days' => $retention_days,
        );

        if ($retention_days > 0) {
            $cutoff_date = date('Y-m-d H:i:s', strtotime("-{$retention_days} days"));
            $report['past_retention'] = $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM $table 
                WHERE status IN ('removed', 'declined') 
                AND updated_at < %s",
                $cutoff_date
            ));
        }

        return $report;
    }
}
