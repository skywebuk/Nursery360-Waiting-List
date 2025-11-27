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
            'room' => isset($_GET['room']) ? sanitize_text_field($_GET['room']) : '',
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
            __('Parent Phone', 'nursery-waiting-list'),
            __('Parent Mobile', 'nursery-waiting-list'),
            __('Address Line 1', 'nursery-waiting-list'),
            __('Address Line 2', 'nursery-waiting-list'),
            __('City', 'nursery-waiting-list'),
            __('Postcode', 'nursery-waiting-list'),
            __('Second Parent Name', 'nursery-waiting-list'),
            __('Second Parent Email', 'nursery-waiting-list'),
            __('Second Parent Phone', 'nursery-waiting-list'),
            __('Room Requested', 'nursery-waiting-list'),
            __('Age Group', 'nursery-waiting-list'),
            __('Preferred Start Date', 'nursery-waiting-list'),
            __('Days Required', 'nursery-waiting-list'),
            __('Sessions Required', 'nursery-waiting-list'),
            __('Hours Per Week', 'nursery-waiting-list'),
            __('Funding Type', 'nursery-waiting-list'),
            __('30 Hours Eligible', 'nursery-waiting-list'),
            __('Additional Needs', 'nursery-waiting-list'),
            __('Allergies', 'nursery-waiting-list'),
            __('Medical Conditions', 'nursery-waiting-list'),
            __('How Heard', 'nursery-waiting-list'),
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
                $entry->parent_phone,
                $entry->parent_mobile,
                $entry->parent_address_line1,
                $entry->parent_address_line2,
                $entry->parent_city,
                $entry->parent_postcode,
                trim($entry->parent2_first_name . ' ' . $entry->parent2_last_name),
                $entry->parent2_email,
                $entry->parent2_phone,
                $entry_handler->get_room_label($entry->room_requested),
                $entry->age_group,
                $entry->preferred_start_date,
                $entry->days_required,
                $entry->sessions_required,
                $entry->hours_per_week,
                $entry->funding_type,
                $entry->eligible_for_30_hours,
                $entry->additional_needs,
                $entry->allergies,
                $entry->medical_conditions,
                $entry->how_heard,
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
                'phone' => $entry->parent_phone,
                'mobile' => $entry->parent_mobile,
                'address' => array(
                    'line1' => $entry->parent_address_line1,
                    'line2' => $entry->parent_address_line2,
                    'city' => $entry->parent_city,
                    'postcode' => $entry->parent_postcode,
                ),
            ),
            'parent2' => array(
                'first_name' => $entry->parent2_first_name,
                'last_name' => $entry->parent2_last_name,
                'email' => $entry->parent2_email,
                'phone' => $entry->parent2_phone,
            ),
            'preferences' => array(
                'room_requested' => $entry->room_requested,
                'room_label' => $entry_handler->get_room_label($entry->room_requested),
                'age_group' => $entry->age_group,
                'preferred_start_date' => $entry->preferred_start_date,
                'days_required' => $entry->days_required,
                'sessions_required' => $entry->sessions_required,
                'hours_per_week' => $entry->hours_per_week,
            ),
            'funding' => array(
                'type' => $entry->funding_type,
                'eligible_30_hours' => $entry->eligible_for_30_hours,
            ),
            'additional_info' => array(
                'needs' => $entry->additional_needs,
                'allergies' => $entry->allergies,
                'medical_conditions' => $entry->medical_conditions,
                'how_heard' => $entry->how_heard,
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
