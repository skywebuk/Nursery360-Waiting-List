<?php
/**
 * Export functionality class
 */

if (!defined('ABSPATH')) {
    exit;
}

class NWL_Export {

    private static $instance = null;

    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        add_action('wp_ajax_nwl_export_csv', array($this, 'handle_export'));
    }

    /**
     * Handle export request
     */
    public function handle_export() {
        // Verify nonce
        if (!wp_verify_nonce($_GET['nonce'] ?? '', 'nwl_export')) {
            wp_die(__('Security check failed.', 'nursery-waiting-list'));
        }

        // Check permissions
        if (!current_user_can('nwl_export_data')) {
            wp_die(__('You do not have permission to export data.', 'nursery-waiting-list'));
        }

        // Get filters from request
        $args = array(
            'status' => isset($_GET['status']) ? sanitize_text_field($_GET['status']) : '',
            'age_group' => isset($_GET['age_group']) ? sanitize_text_field($_GET['age_group']) : '',
            'date_from' => isset($_GET['date_from']) ? sanitize_text_field($_GET['date_from']) : '',
            'date_to' => isset($_GET['date_to']) ? sanitize_text_field($_GET['date_to']) : '',
            'search' => isset($_GET['search']) ? sanitize_text_field($_GET['search']) : '',
            'per_page' => 10000, // Large number to get all
            'page' => 1,
        );

        $result = NWL_Entry::get_instance()->get_entries($args);
        $entries = $result['entries'];

        // Generate CSV
        $this->generate_csv($entries);
    }

    /**
     * Generate and output CSV
     */
    private function generate_csv($entries) {
        $filename = 'waiting-list-export-' . date('Y-m-d-His') . '.csv';
        
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename=' . $filename);
        header('Pragma: no-cache');
        header('Expires: 0');

        $output = fopen('php://output', 'w');

        // Add BOM for Excel compatibility
        fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));

        // Headers
        $headers = array(
            __('Waiting List Number', 'nursery-waiting-list'),
            __('Status', 'nursery-waiting-list'),
            __('Priority', 'nursery-waiting-list'),
            __('Child First Name', 'nursery-waiting-list'),
            __('Child Last Name', 'nursery-waiting-list'),
            __('Child DOB', 'nursery-waiting-list'),
            __('Child Gender', 'nursery-waiting-list'),
            __('Parent First Name', 'nursery-waiting-list'),
            __('Parent Last Name', 'nursery-waiting-list'),
            __('Parent Email', 'nursery-waiting-list'),
            __('Parent Mobile', 'nursery-waiting-list'),
            __('Address', 'nursery-waiting-list'),
            __('City/Town', 'nursery-waiting-list'),
            __('Postcode', 'nursery-waiting-list'),
            __('Age Group', 'nursery-waiting-list'),
            __('Preferred Start Date', 'nursery-waiting-list'),
            __('Days Required', 'nursery-waiting-list'),
            __('Sessions Required', 'nursery-waiting-list'),
            __('Hours Per Week', 'nursery-waiting-list'),
            __('Public Notes', 'nursery-waiting-list'),
            __('Date Added', 'nursery-waiting-list'),
            __('Last Updated', 'nursery-waiting-list'),
        );

        fputcsv($output, $headers);

        $entry_handler = NWL_Entry::get_instance();

        // Data rows
        foreach ($entries as $entry) {
            $row = array(
                $entry->waiting_list_number,
                $entry_handler->get_status_label($entry->status),
                ucfirst($entry->priority),
                $entry->child_first_name,
                $entry->child_last_name,
                $entry->child_dob,
                $entry->child_gender,
                $entry->parent_first_name,
                $entry->parent_last_name,
                $entry->parent_email,
                $entry->parent_mobile,
                $entry->parent_address_line1,
                $entry->parent_city,
                $entry->parent_postcode,
                $entry->age_group,
                $entry->preferred_start_date,
                $entry->days_required,
                $entry->sessions_required,
                $entry->hours_per_week,
                $entry->public_notes,
                $entry->created_at,
                $entry->updated_at,
            );

            fputcsv($output, $row);
        }

        fclose($output);
        exit;
    }

    /**
     * Get export URL with filters
     */
    public function get_export_url($args = array()) {
        $base_url = admin_url('admin-ajax.php');
        
        $params = array(
            'action' => 'nwl_export_csv',
            'nonce' => wp_create_nonce('nwl_export'),
        );

        foreach ($args as $key => $value) {
            if (!empty($value)) {
                $params[$key] = $value;
            }
        }

        return add_query_arg($params, $base_url);
    }

    /**
     * Export single entry to array (for API use)
     */
    public function entry_to_array($entry) {
        $entry_handler = NWL_Entry::get_instance();

        return array(
            'waiting_list_number' => $entry->waiting_list_number,
            'status' => $entry->status,
            'status_label' => $entry_handler->get_status_label($entry->status),
            'priority' => $entry->priority,
            'child' => array(
                'first_name' => $entry->child_first_name,
                'last_name' => $entry->child_last_name,
                'full_name' => $entry_handler->get_child_name($entry),
                'dob' => $entry->child_dob,
                'gender' => $entry->child_gender,
            ),
            'parent' => array(
                'first_name' => $entry->parent_first_name,
                'last_name' => $entry->parent_last_name,
                'full_name' => $entry_handler->get_parent_name($entry),
                'email' => $entry->parent_email,
                'mobile' => $entry->parent_mobile,
                'address' => array(
                    'address' => $entry->parent_address_line1,
                    'city' => $entry->parent_city,
                    'postcode' => $entry->parent_postcode,
                ),
            ),
            'preferences' => array(
                'age_group' => $entry->age_group,
                'preferred_start_date' => $entry->preferred_start_date,
                'days_required' => $entry->days_required,
                'sessions_required' => $entry->sessions_required,
                'hours_per_week' => $entry->hours_per_week,
            ),
            'notes' => array(
                'public' => $entry->public_notes,
            ),
            'offer' => array(
                'deadline' => $entry->offer_deadline,
                'response' => $entry->offer_response,
            ),
            'dates' => array(
                'created' => $entry->created_at,
                'updated' => $entry->updated_at,
            ),
        );
    }
}
