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
     * Get counts by room
     */
    public function get_counts_by_room() {
        global $wpdb;
        
        $table = $wpdb->prefix . NWL_TABLE_ENTRIES;
        
        $results = $wpdb->get_results(
            "SELECT room_requested, COUNT(*) as count FROM $table 
            WHERE status NOT IN ('removed', 'declined')
            GROUP BY room_requested",
            OBJECT_K
        );
        
        $counts = array();
        $rooms = NWL_Database::get_rooms();
        
        foreach ($rooms as $key => $label) {
            $counts[$key] = array(
                'label' => $label,
                'count' => isset($results[$key]) ? (int) $results[$key]->count : 0,
            );
        }
        
        // Handle unassigned
        $counts['unassigned'] = array(
            'label' => __('Unassigned', 'nursery-waiting-list'),
            'count' => isset($results['']) ? (int) $results['']->count : 0,
        );
        
        return $counts;
    }

    /**
     * Get counts by age group
     */
    public function get_counts_by_age_group() {
        global $wpdb;
        
        $table = $wpdb->prefix . NWL_TABLE_ENTRIES;
        
        $results = $wpdb->get_results(
            "SELECT age_group, COUNT(*) as count FROM $table 
            WHERE status NOT IN ('removed', 'declined')
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
     * Get average waiting time in days
     */
    public function get_average_waiting_time() {
        global $wpdb;
        
        $table = $wpdb->prefix . NWL_TABLE_ENTRIES;
        
        // Calculate average for active entries
        $average = $wpdb->get_var(
            "SELECT AVG(DATEDIFF(NOW(), created_at)) FROM $table 
            WHERE status NOT IN ('removed', 'declined', 'enrolled')"
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
        
        $accepted = (int) $wpdb->get_var(
            "SELECT COUNT(*) FROM $table WHERE status IN ('accepted', 'enrolled')"
        );
        
        $declined = (int) $wpdb->get_var(
            "SELECT COUNT(*) FROM $table WHERE status = 'declined'"
        );
        
        $total_decisions = $accepted + $declined;
        $conversion_rate = $total_decisions > 0 ? round(($accepted / $total_decisions) * 100, 1) : 0;
        
        return array(
            'offered' => $offered,
            'accepted' => $accepted,
            'declined' => $declined,
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
            'by_room' => $this->get_counts_by_room(),
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
            AND status NOT IN ('accepted', 'enrolled', 'removed', 'declined')
            ORDER BY FIELD(priority, 'urgent', 'high'), created_at ASC"
        );
        $attention['high_priority'] = $high_priority;
        
        return $attention;
    }
}
