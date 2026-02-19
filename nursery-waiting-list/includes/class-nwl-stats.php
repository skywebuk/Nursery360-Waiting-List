<?php
/**
 * Statistics and reporting class
 */

if (!defined('ABSPATH')) {
    exit;
}

class NWL_Stats {

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
     * Get total entries count
     */
    public function get_total_entries($exclude_removed = true) {
        global $wpdb;
        
        $table = $wpdb->prefix . NWL_TABLE_ENTRIES;
        
        $where = '1=1';
        if ($exclude_removed) {
            $where .= " AND status NOT IN ('removed', 'declined')";
        }
        
        return (int) $wpdb->get_var("SELECT COUNT(*) FROM $table WHERE $where");
    }

    /**
     * Get counts by status
     */
    public function get_counts_by_status() {
        global $wpdb;
        
        $table = $wpdb->prefix . NWL_TABLE_ENTRIES;
        
        $results = $wpdb->get_results(
            "SELECT status, COUNT(*) as count FROM $table GROUP BY status",
            OBJECT_K
        );
        
        $counts = array();
        $statuses = NWL_Database::get_statuses();
        
        foreach ($statuses as $key => $label) {
            $counts[$key] = array(
                'label' => $label,
                'count' => isset($results[$key]) ? (int) $results[$key]->count : 0,
            );
        }
        
        return $counts;
    }

    /**
     * Get counts by age group (legacy)
     */
    public function get_counts_by_age_group() {
        global $wpdb;

        $table = $wpdb->prefix . NWL_TABLE_ENTRIES;

        $results = $wpdb->get_results(
            "SELECT age_group, COUNT(*) as count FROM $table
            WHERE status NOT IN ('withdrawn')
            GROUP BY age_group",
            OBJECT_K
        );

        $counts = array();
        $age_groups = NWL_Database::get_age_groups();

        foreach ($age_groups as $key => $label) {
            $counts[$key] = array(
                'label' => $label,
                'count' => isset($results[$key]) ? (int) $results[$key]->count : 0,
            );
        }

        return $counts;
    }

    /**
     * Get counts by year group
     */
    public function get_counts_by_year_group() {
        global $wpdb;

        $table = $wpdb->prefix . NWL_TABLE_ENTRIES;

        $results = $wpdb->get_results(
            "SELECT year_group, COUNT(*) as count FROM $table
            WHERE status NOT IN ('withdrawn')
            GROUP BY year_group",
            OBJECT_K
        );

        $counts = array();
        $year_groups = NWL_Database::get_year_groups();

        foreach ($year_groups as $group) {
            $counts[$group['id']] = array(
                'label' => $group['name'],
                'count' => isset($results[$group['id']]) ? (int) $results[$group['id']]->count : 0,
            );
        }

        return $counts;
    }

    /**
     * Get average waiting time in days
     */
    public function get_average_waiting_time() {
        global $wpdb;
        
        $table = $wpdb->prefix . NWL_TABLE_ENTRIES;
        
        // Calculate average for active entries only
        $average = $wpdb->get_var(
            "SELECT AVG(DATEDIFF(NOW(), created_at)) FROM $table
            WHERE status IN ('pending', 'waitlisted', 'offered')"
        );
        
        return $average ? round($average, 1) : 0;
    }

    /**
     * Get entries added per month
     */
    public function get_monthly_trends($months = 12) {
        global $wpdb;
        
        $table = $wpdb->prefix . NWL_TABLE_ENTRIES;
        
        $results = $wpdb->get_results($wpdb->prepare(
            "SELECT 
                DATE_FORMAT(created_at, '%%Y-%%m') as month,
                COUNT(*) as count
            FROM $table 
            WHERE created_at >= DATE_SUB(NOW(), INTERVAL %d MONTH)
            GROUP BY DATE_FORMAT(created_at, '%%Y-%%m')
            ORDER BY month ASC",
            $months
        ));
        
        $trends = array();
        
        foreach ($results as $row) {
            $date = DateTime::createFromFormat('Y-m', $row->month);
            $trends[] = array(
                'month' => $row->month,
                'label' => $date ? $date->format('M Y') : $row->month,
                'count' => (int) $row->count,
            );
        }
        
        return $trends;
    }

    /**
     * Get recent activity
     */
    public function get_recent_activity($limit = 10) {
        global $wpdb;
        
        $table_entries = $wpdb->prefix . NWL_TABLE_ENTRIES;
        $table_notes = $wpdb->prefix . NWL_TABLE_NOTES;
        
        // Get recent entries
        $entries = $wpdb->get_results($wpdb->prepare(
            "SELECT id, waiting_list_number, child_first_name, child_last_name, 
                    status, created_at, 'new_entry' as activity_type
            FROM $table_entries 
            ORDER BY created_at DESC 
            LIMIT %d",
            $limit
        ));
        
        // Get recent status changes from notes
        $notes = $wpdb->get_results($wpdb->prepare(
            "SELECT n.entry_id, n.note_content, n.created_at, 
                    e.waiting_list_number, e.child_first_name, e.child_last_name,
                    'status_change' as activity_type
            FROM $table_notes n
            JOIN $table_entries e ON n.entry_id = e.id
            WHERE n.note_type = 'system' AND n.note_content LIKE '%%Status changed%%'
            ORDER BY n.created_at DESC 
            LIMIT %d",
            $limit
        ));
        
        // Combine and sort
        $activity = array_merge($entries, $notes);
        usort($activity, function($a, $b) {
            return strtotime($b->created_at) - strtotime($a->created_at);
        });
        
        return array_slice($activity, 0, $limit);
    }

    /**
     * Get conversion stats (offered to accepted ratio)
     */
    public function get_conversion_stats() {
        global $wpdb;
        
        $table = $wpdb->prefix . NWL_TABLE_ENTRIES;
        
        $offered = (int) $wpdb->get_var(
            "SELECT COUNT(*) FROM $table WHERE status = 'offered'"
        );

        $enrolled = (int) $wpdb->get_var(
            "SELECT COUNT(*) FROM $table WHERE status = 'enrolled'"
        );

        $withdrawn = (int) $wpdb->get_var(
            "SELECT COUNT(*) FROM $table WHERE status = 'withdrawn'"
        );

        $total_decisions = $enrolled + $withdrawn;
        $conversion_rate = $total_decisions > 0 ? round(($enrolled / $total_decisions) * 100, 1) : 0;

        return array(
            'offered' => $offered,
            'enrolled' => $enrolled,
            'withdrawn' => $withdrawn,
            'pending_response' => $offered,
            'conversion_rate' => $conversion_rate,
        );
    }

    /**
     * Get dashboard summary
     */
    public function get_dashboard_summary() {
        return array(
            'total_active' => $this->get_total_entries(true),
            'by_status' => $this->get_counts_by_status(),
            'by_age_group' => $this->get_counts_by_age_group(),
            'by_year_group' => $this->get_counts_by_year_group(),
            'average_wait' => $this->get_average_waiting_time(),
            'monthly_trends' => $this->get_monthly_trends(6),
            'conversion' => $this->get_conversion_stats(),
        );
    }

    /**
     * Get entries needing attention
     */
    public function get_entries_needing_attention() {
        global $wpdb;

        $table = $wpdb->prefix . NWL_TABLE_ENTRIES;

        $attention = array();

        // Overdue offers (deadline passed)
        $overdue = $wpdb->get_results(
            "SELECT * FROM $table
            WHERE status = 'offered'
            AND offer_deadline < CURDATE()
            ORDER BY offer_deadline ASC"
        );
        $attention['overdue_offers'] = $overdue;

        // Long waiting (over 6 months)
        $long_wait = $wpdb->get_results(
            "SELECT * FROM $table
            WHERE status IN ('pending', 'waitlisted')
            AND created_at < DATE_SUB(NOW(), INTERVAL 6 MONTH)
            ORDER BY created_at ASC"
        );
        $attention['long_waiting'] = $long_wait;

        // Pending deletion requests
        $deletion = $wpdb->get_results(
            "SELECT * FROM $table WHERE deletion_requested = 1"
        );
        $attention['deletion_requests'] = $deletion;

        // High priority entries
        $high_priority = $wpdb->get_results(
            "SELECT * FROM $table
            WHERE priority IN ('high', 'urgent')
            AND status NOT IN ('enrolled', 'withdrawn')
            ORDER BY FIELD(priority, 'urgent', 'high'), created_at ASC"
        );
        $attention['high_priority'] = $high_priority;

        return $attention;
    }

    /**
     * Get enrolled counts by year group (custom year groups)
     */
    public function get_enrolled_by_year_group() {
        global $wpdb;

        $table = $wpdb->prefix . NWL_TABLE_ENTRIES;

        // Get enrolled students grouped by year_group field
        $results = $wpdb->get_results(
            "SELECT year_group, COUNT(*) as count FROM $table
            WHERE status = 'enrolled' AND year_group IS NOT NULL AND year_group != ''
            GROUP BY year_group",
            OBJECT_K
        );

        $counts = array();
        foreach ($results as $key => $row) {
            $counts[$key] = (int) $row->count;
        }

        return $counts;
    }

    /**
     * Get occupancy statistics for all year groups
     */
    public function get_year_group_occupancy() {
        $year_groups = NWL_Database::get_year_groups();
        $enrolled_counts = $this->get_enrolled_by_year_group();
        $occupancy = array();

        foreach ($year_groups as $group) {
            $enrolled = isset($enrolled_counts[$group['id']]) ? $enrolled_counts[$group['id']] : 0;
            $available = $group['capacity'] - $enrolled;

            $occupancy[$group['id']] = array(
                'id' => $group['id'],
                'name' => $group['name'],
                'capacity' => $group['capacity'],
                'min_age' => isset($group['min_age']) ? $group['min_age'] : 0,
                'max_age' => isset($group['max_age']) ? $group['max_age'] : 99,
                'enrolled' => $enrolled,
                'available' => max(0, $available),
                'is_full' => $available <= 0,
                'percentage' => $group['capacity'] > 0 ? round(($enrolled / $group['capacity']) * 100, 1) : 0,
            );
        }

        return $occupancy;
    }

    /**
     * Get total occupancy statistics
     */
    public function get_total_occupancy_stats() {
        $total_capacity = NWL_Database::get_total_occupancy();
        $year_group_occupancy = $this->get_year_group_occupancy();

        $total_enrolled = 0;
        $total_allocated = 0;

        foreach ($year_group_occupancy as $group) {
            $total_enrolled += $group['enrolled'];
            $total_allocated += $group['capacity'];
        }

        return array(
            'total_capacity' => $total_capacity,
            'total_allocated' => $total_allocated,
            'total_enrolled' => $total_enrolled,
            'total_available' => max(0, $total_capacity - $total_enrolled),
            'unallocated' => $total_capacity - $total_allocated,
            'percentage' => $total_capacity > 0 ? round(($total_enrolled / $total_capacity) * 100, 1) : 0,
            'year_groups' => $year_group_occupancy,
        );
    }

    /**
     * Get total occupancy summary (simple version for display)
     */
    public function get_total_occupancy_summary() {
        $year_group_occupancy = $this->get_year_group_occupancy();
        $total_occupancy = absint(get_option('nwl_total_occupancy', 0));

        $total_allocated = 0;
        $total_enrolled = 0;

        foreach ($year_group_occupancy as $group) {
            $total_allocated += $group['capacity'];
            $total_enrolled += $group['enrolled'];
        }

        // Use the configured total occupancy if set, otherwise fall back to sum of allocations
        $total_capacity = $total_occupancy > 0 ? $total_occupancy : $total_allocated;

        return array(
            'total_capacity' => $total_capacity,
            'total_enrolled' => $total_enrolled,
            'total_available' => max(0, $total_capacity - $total_enrolled),
        );
    }
}
