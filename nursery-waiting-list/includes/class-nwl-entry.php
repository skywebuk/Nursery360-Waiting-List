<?php
/**
 * Entry management class
 */

if (!defined('ABSPATH')) {
    exit;
}

class NWL_Entry {

    private static $instance = null;

    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        // Constructor
    }

    /**
     * Create a new entry
     */
    public function create($data) {
        global $wpdb;
        
        $table = $wpdb->prefix . NWL_TABLE_ENTRIES;
        
        // Generate waiting list number
        $data['waiting_list_number'] = $this->generate_waiting_list_number();
        $data['created_at'] = current_time('mysql');
        $data['updated_at'] = current_time('mysql');
        $data['consent_date'] = current_time('mysql');
        
        if (is_user_logged_in()) {
            $data['created_by'] = get_current_user_id();
        }
        
        // Sanitize data
        $data = $this->sanitize_entry_data($data);
        
        $result = $wpdb->insert($table, $data);
        
        if ($result === false) {
            return new WP_Error('db_error', __('Failed to create entry.', 'nursery-waiting-list'));
        }
        
        $entry_id = $wpdb->insert_id;
        
        // Add system note
        $this->add_note($entry_id, 'system', __('Entry created.', 'nursery-waiting-list'));
        
        // Fire action for other integrations
        do_action('nwl_entry_created', $entry_id, $data);
        
        return $entry_id;
    }

    /**
     * Update an entry
     */
    public function update($id, $data) {
        global $wpdb;
        
        $table = $wpdb->prefix . NWL_TABLE_ENTRIES;
        
        // Get old data for comparison
        $old_data = $this->get($id);
        
        if (!$old_data) {
            return new WP_Error('not_found', __('Entry not found.', 'nursery-waiting-list'));
        }
        
        $data['updated_at'] = current_time('mysql');
        
        if (is_user_logged_in()) {
            $data['updated_by'] = get_current_user_id();
        }
        
        // Sanitize data
        $data = $this->sanitize_entry_data($data);
        
        $result = $wpdb->update($table, $data, array('id' => $id));
        
        if ($result === false) {
            return new WP_Error('db_error', __('Failed to update entry.', 'nursery-waiting-list'));
        }
        
        // Check if status changed
        if (isset($data['status']) && $old_data->status !== $data['status']) {
            $statuses = NWL_Database::get_statuses();
            $old_status = isset($statuses[$old_data->status]) ? $statuses[$old_data->status] : $old_data->status;
            $new_status = isset($statuses[$data['status']]) ? $statuses[$data['status']] : $data['status'];
            
            $this->add_note(
                $id, 
                'system', 
                sprintf(__('Status changed from "%s" to "%s".', 'nursery-waiting-list'), $old_status, $new_status)
            );
            
            do_action('nwl_status_changed', $id, $old_data->status, $data['status']);
        }
        
        do_action('nwl_entry_updated', $id, $data, $old_data);
        
        return true;
    }

    /**
     * Get a single entry
     */
    public function get($id) {
        global $wpdb;
        
        $table = $wpdb->prefix . NWL_TABLE_ENTRIES;
        
        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table WHERE id = %d",
            $id
        ));
    }

    /**
     * Get entry by waiting list number
     */
    public function get_by_number($number) {
        global $wpdb;
        
        $table = $wpdb->prefix . NWL_TABLE_ENTRIES;
        
        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table WHERE waiting_list_number = %s",
            $number
        ));
    }

    /**
     * Get entry by email
     */
    public function get_by_email($email) {
        global $wpdb;
        
        $table = $wpdb->prefix . NWL_TABLE_ENTRIES;
        
        return $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $table WHERE parent_email = %s OR parent2_email = %s ORDER BY created_at DESC",
            $email,
            $email
        ));
    }

    /**
     * Get entry by phone
     */
    public function get_by_phone($phone) {
        global $wpdb;
        
        $table = $wpdb->prefix . NWL_TABLE_ENTRIES;
        
        // Normalize phone number for search
        $phone_clean = preg_replace('/[^0-9]/', '', $phone);
        
        return $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $table 
            WHERE REPLACE(REPLACE(REPLACE(parent_phone, ' ', ''), '-', ''), '+', '') LIKE %s
            OR REPLACE(REPLACE(REPLACE(parent_mobile, ' ', ''), '-', ''), '+', '') LIKE %s
            OR REPLACE(REPLACE(REPLACE(parent2_phone, ' ', ''), '-', ''), '+', '') LIKE %s
            ORDER BY created_at DESC",
            '%' . $phone_clean . '%',
            '%' . $phone_clean . '%',
            '%' . $phone_clean . '%'
        ));
    }

    /**
     * Get entries with filters
     */
    public function get_entries($args = array()) {
        global $wpdb;
        
        $defaults = array(
            'status' => '',
            'room' => '',
            'age_group' => '',
            'priority' => '',
            'date_from' => '',
            'date_to' => '',
            'search' => '',
            'orderby' => 'created_at',
            'order' => 'DESC',
            'per_page' => 20,
            'page' => 1,
            'deletion_requested' => '',
        );
        
        $args = wp_parse_args($args, $defaults);
        
        $table = $wpdb->prefix . NWL_TABLE_ENTRIES;
        
        $where = array('1=1');
        $values = array();
        
        if (!empty($args['status'])) {
            if (is_array($args['status'])) {
                $placeholders = implode(',', array_fill(0, count($args['status']), '%s'));
                $where[] = "status IN ($placeholders)";
                $values = array_merge($values, $args['status']);
            } else {
                $where[] = 'status = %s';
                $values[] = $args['status'];
            }
        }
        
        if (!empty($args['room'])) {
            $where[] = 'room_requested = %s';
            $values[] = $args['room'];
        }
        
        if (!empty($args['age_group'])) {
            $where[] = 'age_group = %s';
            $values[] = $args['age_group'];
        }
        
        if (!empty($args['priority'])) {
            $where[] = 'priority = %s';
            $values[] = $args['priority'];
        }
        
        if (!empty($args['date_from'])) {
            $where[] = 'DATE(created_at) >= %s';
            $values[] = $args['date_from'];
        }
        
        if (!empty($args['date_to'])) {
            $where[] = 'DATE(created_at) <= %s';
            $values[] = $args['date_to'];
        }
        
        if ($args['deletion_requested'] !== '') {
            $where[] = 'deletion_requested = %d';
            $values[] = (int) $args['deletion_requested'];
        }
        
        if (!empty($args['search'])) {
            $search = '%' . $wpdb->esc_like($args['search']) . '%';
            $where[] = '(
                child_first_name LIKE %s 
                OR child_last_name LIKE %s 
                OR parent_first_name LIKE %s 
                OR parent_last_name LIKE %s 
                OR parent_email LIKE %s 
                OR parent_phone LIKE %s 
                OR parent_mobile LIKE %s
                OR waiting_list_number LIKE %s
            )';
            $values = array_merge($values, array_fill(0, 8, $search));
        }
        
        $where_clause = implode(' AND ', $where);
        
        // Validate orderby
        $allowed_orderby = array('created_at', 'updated_at', 'child_first_name', 'status', 'room_requested', 'priority');
        $orderby = in_array($args['orderby'], $allowed_orderby) ? $args['orderby'] : 'created_at';
        $order = strtoupper($args['order']) === 'ASC' ? 'ASC' : 'DESC';
        
        // Calculate offset
        $offset = ($args['page'] - 1) * $args['per_page'];
        
        // Get total count
        $count_sql = "SELECT COUNT(*) FROM $table WHERE $where_clause";
        if (!empty($values)) {
            $count_sql = $wpdb->prepare($count_sql, $values);
        }
        $total = $wpdb->get_var($count_sql);
        
        // Get results
        $sql = "SELECT * FROM $table WHERE $where_clause ORDER BY $orderby $order LIMIT %d OFFSET %d";
        $values[] = $args['per_page'];
        $values[] = $offset;
        
        $results = $wpdb->get_results($wpdb->prepare($sql, $values));
        
        return array(
            'entries' => $results,
            'total' => (int) $total,
            'pages' => ceil($total / $args['per_page']),
            'page' => $args['page'],
            'per_page' => $args['per_page'],
        );
    }

    /**
     * Delete an entry (soft delete by marking as removed)
     */
    public function soft_delete($id) {
        return $this->update($id, array('status' => 'removed'));
    }

    /**
     * Hard delete an entry (for GDPR)
     */
    public function hard_delete($id) {
        global $wpdb;
        
        $table_entries = $wpdb->prefix . NWL_TABLE_ENTRIES;
        $table_notes = $wpdb->prefix . NWL_TABLE_NOTES;
        $table_emails = $wpdb->prefix . NWL_TABLE_EMAILS;
        
        // Delete related notes
        $wpdb->delete($table_notes, array('entry_id' => $id));
        
        // Delete related email logs
        $wpdb->delete($table_emails, array('entry_id' => $id));
        
        // Delete entry
        $result = $wpdb->delete($table_entries, array('id' => $id));
        
        if ($result !== false) {
            do_action('nwl_entry_deleted', $id);
            return true;
        }
        
        return new WP_Error('db_error', __('Failed to delete entry.', 'nursery-waiting-list'));
    }

    /**
     * Generate unique waiting list number
     */
    private function generate_waiting_list_number() {
        global $wpdb;
        
        $table = $wpdb->prefix . NWL_TABLE_ENTRIES;
        $prefix = get_option('nwl_wl_number_prefix', 'WL');
        $year = date('Y');
        
        // Get the highest number for this year
        $last_number = $wpdb->get_var($wpdb->prepare(
            "SELECT MAX(CAST(SUBSTRING(waiting_list_number, %d) AS UNSIGNED)) 
            FROM $table 
            WHERE waiting_list_number LIKE %s",
            strlen($prefix) + 5,
            $prefix . $year . '%'
        ));
        
        $new_number = $last_number ? $last_number + 1 : 1;
        
        return $prefix . $year . str_pad($new_number, 4, '0', STR_PAD_LEFT);
    }

    /**
     * Add note to entry
     */
    public function add_note($entry_id, $type, $content, $user_id = null) {
        global $wpdb;
        
        $table = $wpdb->prefix . NWL_TABLE_NOTES;
        
        $data = array(
            'entry_id' => $entry_id,
            'note_type' => $type,
            'note_content' => sanitize_textarea_field($content),
            'created_at' => current_time('mysql'),
            'created_by' => $user_id ?: (is_user_logged_in() ? get_current_user_id() : null),
        );
        
        $wpdb->insert($table, $data);
        
        return $wpdb->insert_id;
    }

    /**
     * Get notes for entry
     */
    public function get_notes($entry_id, $type = null) {
        global $wpdb;
        
        $table = $wpdb->prefix . NWL_TABLE_NOTES;
        
        $sql = "SELECT n.*, u.display_name as author_name 
                FROM $table n 
                LEFT JOIN {$wpdb->users} u ON n.created_by = u.ID 
                WHERE n.entry_id = %d";
        
        $values = array($entry_id);
        
        if ($type) {
            $sql .= " AND n.note_type = %s";
            $values[] = $type;
        }
        
        $sql .= " ORDER BY n.created_at DESC";
        
        return $wpdb->get_results($wpdb->prepare($sql, $values));
    }

    /**
     * Sanitize entry data
     */
    private function sanitize_entry_data($data) {
        $text_fields = array(
            'child_first_name', 'child_last_name', 'child_gender',
            'parent_first_name', 'parent_last_name', 'parent_email',
            'parent_phone', 'parent_mobile', 'parent_address_line1',
            'parent_address_line2', 'parent_city', 'parent_postcode',
            'parent2_first_name', 'parent2_last_name', 'parent2_email', 'parent2_phone',
            'room_requested', 'age_group', 'days_required', 'sessions_required',
            'funding_type', 'how_heard', 'status', 'priority', 'offer_response',
            'waiting_list_number'
        );
        
        $textarea_fields = array(
            'additional_needs', 'allergies', 'medical_conditions',
            'internal_notes', 'public_notes'
        );
        
        $int_fields = array('hours_per_week', 'created_by', 'updated_by');
        
        foreach ($data as $key => $value) {
            if (in_array($key, $text_fields)) {
                $data[$key] = sanitize_text_field($value);
            } elseif (in_array($key, $textarea_fields)) {
                $data[$key] = sanitize_textarea_field($value);
            } elseif (in_array($key, $int_fields)) {
                $data[$key] = absint($value);
            } elseif ($key === 'parent_email' || $key === 'parent2_email') {
                $data[$key] = sanitize_email($value);
            }
        }
        
        return $data;
    }

    /**
     * Get child's full name
     */
    public function get_child_name($entry) {
        return trim($entry->child_first_name . ' ' . $entry->child_last_name);
    }

    /**
     * Get parent's full name
     */
    public function get_parent_name($entry) {
        return trim($entry->parent_first_name . ' ' . $entry->parent_last_name);
    }

    /**
     * Get formatted status
     */
    public function get_status_label($status) {
        $statuses = NWL_Database::get_statuses();
        return isset($statuses[$status]) ? $statuses[$status] : ucfirst($status);
    }

    /**
     * Get room label
     */
    public function get_room_label($room) {
        $rooms = NWL_Database::get_rooms();
        return isset($rooms[$room]) ? $rooms[$room] : $room;
    }

    /**
     * Mark entry for deletion
     */
    public function request_deletion($id) {
        return $this->update($id, array(
            'deletion_requested' => 1,
            'deletion_requested_date' => current_time('mysql'),
        ));
    }

    /**
     * Cancel deletion request
     */
    public function cancel_deletion($id) {
        return $this->update($id, array(
            'deletion_requested' => 0,
            'deletion_requested_date' => null,
        ));
    }

    /**
     * Get entries pending deletion
     */
    public function get_pending_deletions() {
        return $this->get_entries(array(
            'deletion_requested' => 1,
            'per_page' => 100,
        ));
    }
}
