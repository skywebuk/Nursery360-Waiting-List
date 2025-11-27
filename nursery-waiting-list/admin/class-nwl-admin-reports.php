<?php
/**
 * Admin reports functionality
 */

if (!defined('ABSPATH')) {
    exit;
}

class NWL_Admin_Reports {

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
     * Render reports page
     */
    public static function render_page() {
        if (!current_user_can('nwl_view_reports')) {
            wp_die(__('You do not have permission to access this page.', 'nursery-waiting-list'));
        }

        $stats = NWL_Stats::get_instance();
        $summary = $stats->get_dashboard_summary();
        $attention = $stats->get_entries_needing_attention();
        ?>
        <div class="wrap nwl-admin-wrap">
            <h1><?php esc_html_e('Waiting List Reports', 'nursery-waiting-list'); ?></h1>

            <!-- Overview Stats -->
            <div class="nwl-report-section">
                <h2><?php esc_html_e('Overview', 'nursery-waiting-list'); ?></h2>
                
                <div class="nwl-stats-grid">
                    <div class="nwl-stat-card">
                        <div class="nwl-stat-number"><?php echo number_format_i18n($summary['total_active']); ?></div>
                        <div class="nwl-stat-label"><?php esc_html_e('Active Entries', 'nursery-waiting-list'); ?></div>
                    </div>
                    <div class="nwl-stat-card">
                        <div class="nwl-stat-number"><?php echo number_format_i18n($summary['average_wait']); ?></div>
                        <div class="nwl-stat-label"><?php esc_html_e('Avg. Days Waiting', 'nursery-waiting-list'); ?></div>
                    </div>
                    <div class="nwl-stat-card">
                        <div class="nwl-stat-number"><?php echo $summary['conversion']['conversion_rate']; ?>%</div>
                        <div class="nwl-stat-label"><?php esc_html_e('Offer Acceptance Rate', 'nursery-waiting-list'); ?></div>
                    </div>
                    <div class="nwl-stat-card">
                        <div class="nwl-stat-number"><?php echo number_format_i18n($summary['by_status']['pending']['count']); ?></div>
                        <div class="nwl-stat-label"><?php esc_html_e('Pending Review', 'nursery-waiting-list'); ?></div>
                    </div>
                </div>
            </div>

            <!-- By Status -->
            <div class="nwl-report-section">
                <h2><?php esc_html_e('Entries by Status', 'nursery-waiting-list'); ?></h2>
                
                <div class="nwl-report-row">
                    <div class="nwl-report-chart">
                        <canvas id="nwl-status-chart" height="200"></canvas>
                    </div>
                    <div class="nwl-report-table">
                        <table class="widefat">
                            <thead>
                                <tr>
                                    <th><?php esc_html_e('Status', 'nursery-waiting-list'); ?></th>
                                    <th><?php esc_html_e('Count', 'nursery-waiting-list'); ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($summary['by_status'] as $status => $data) : ?>
                                    <tr>
                                        <td><?php echo NWL_Admin::get_status_badge($status); ?></td>
                                        <td><?php echo number_format_i18n($data['count']); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- By Room -->
            <div class="nwl-report-section">
                <h2><?php esc_html_e('Entries by Room', 'nursery-waiting-list'); ?></h2>
                
                <div class="nwl-report-row">
                    <div class="nwl-report-chart">
                        <canvas id="nwl-room-chart" height="200"></canvas>
                    </div>
                    <div class="nwl-report-table">
                        <table class="widefat">
                            <thead>
                                <tr>
                                    <th><?php esc_html_e('Room', 'nursery-waiting-list'); ?></th>
                                    <th><?php esc_html_e('Count', 'nursery-waiting-list'); ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($summary['by_room'] as $room => $data) : ?>
                                    <?php if ($data['count'] > 0) : ?>
                                        <tr>
                                            <td><?php echo esc_html($data['label']); ?></td>
                                            <td><?php echo number_format_i18n($data['count']); ?></td>
                                        </tr>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Monthly Trends -->
            <div class="nwl-report-section">
                <h2><?php esc_html_e('Monthly Trends', 'nursery-waiting-list'); ?></h2>
                
                <div class="nwl-report-chart nwl-chart-full">
                    <canvas id="nwl-trends-chart" height="100"></canvas>
                </div>
            </div>

            <!-- Needs Attention -->
            <div class="nwl-report-section">
                <h2><?php esc_html_e('Needs Attention', 'nursery-waiting-list'); ?></h2>
                
                <div class="nwl-attention-grid">
                    <!-- Overdue Offers -->
                    <div class="nwl-attention-card <?php echo !empty($attention['overdue_offers']) ? 'nwl-attention-urgent' : ''; ?>">
                        <h3>
                            <span class="dashicons dashicons-warning"></span>
                            <?php esc_html_e('Overdue Offers', 'nursery-waiting-list'); ?>
                        </h3>
                        <div class="nwl-attention-count"><?php echo count($attention['overdue_offers']); ?></div>
                        <?php if (!empty($attention['overdue_offers'])) : ?>
                            <ul>
                                <?php foreach (array_slice($attention['overdue_offers'], 0, 5) as $entry) : ?>
                                    <li>
                                        <a href="<?php echo esc_url(NWL_Admin::get_page_url('nwl-entries', array('action' => 'edit', 'id' => $entry->id))); ?>">
                                            <?php echo esc_html(NWL_Entry::get_instance()->get_child_name($entry)); ?>
                                        </a>
                                        <small><?php printf(esc_html__('Due: %s', 'nursery-waiting-list'), date_i18n(get_option('date_format'), strtotime($entry->offer_deadline))); ?></small>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        <?php else : ?>
                            <p class="nwl-no-items"><?php esc_html_e('No overdue offers.', 'nursery-waiting-list'); ?></p>
                        <?php endif; ?>
                    </div>

                    <!-- Long Waiting -->
                    <div class="nwl-attention-card">
                        <h3>
                            <span class="dashicons dashicons-clock"></span>
                            <?php esc_html_e('Waiting 6+ Months', 'nursery-waiting-list'); ?>
                        </h3>
                        <div class="nwl-attention-count"><?php echo count($attention['long_waiting']); ?></div>
                        <?php if (!empty($attention['long_waiting'])) : ?>
                            <ul>
                                <?php foreach (array_slice($attention['long_waiting'], 0, 5) as $entry) : ?>
                                    <li>
                                        <a href="<?php echo esc_url(NWL_Admin::get_page_url('nwl-entries', array('action' => 'edit', 'id' => $entry->id))); ?>">
                                            <?php echo esc_html(NWL_Entry::get_instance()->get_child_name($entry)); ?>
                                        </a>
                                        <small><?php printf(esc_html__('Added: %s', 'nursery-waiting-list'), date_i18n(get_option('date_format'), strtotime($entry->created_at))); ?></small>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        <?php else : ?>
                            <p class="nwl-no-items"><?php esc_html_e('No entries waiting over 6 months.', 'nursery-waiting-list'); ?></p>
                        <?php endif; ?>
                    </div>

                    <!-- High Priority -->
                    <div class="nwl-attention-card">
                        <h3>
                            <span class="dashicons dashicons-star-filled"></span>
                            <?php esc_html_e('High Priority', 'nursery-waiting-list'); ?>
                        </h3>
                        <div class="nwl-attention-count"><?php echo count($attention['high_priority']); ?></div>
                        <?php if (!empty($attention['high_priority'])) : ?>
                            <ul>
                                <?php foreach (array_slice($attention['high_priority'], 0, 5) as $entry) : ?>
                                    <li>
                                        <a href="<?php echo esc_url(NWL_Admin::get_page_url('nwl-entries', array('action' => 'edit', 'id' => $entry->id))); ?>">
                                            <?php echo esc_html(NWL_Entry::get_instance()->get_child_name($entry)); ?>
                                        </a>
                                        <?php echo NWL_Admin::get_priority_badge($entry->priority); ?>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        <?php else : ?>
                            <p class="nwl-no-items"><?php esc_html_e('No high priority entries.', 'nursery-waiting-list'); ?></p>
                        <?php endif; ?>
                    </div>

                    <!-- Deletion Requests -->
                    <div class="nwl-attention-card <?php echo !empty($attention['deletion_requests']) ? 'nwl-attention-warning' : ''; ?>">
                        <h3>
                            <span class="dashicons dashicons-trash"></span>
                            <?php esc_html_e('Deletion Requests', 'nursery-waiting-list'); ?>
                        </h3>
                        <div class="nwl-attention-count"><?php echo count($attention['deletion_requests']); ?></div>
                        <?php if (!empty($attention['deletion_requests'])) : ?>
                            <ul>
                                <?php foreach (array_slice($attention['deletion_requests'], 0, 5) as $entry) : ?>
                                    <li>
                                        <a href="<?php echo esc_url(NWL_Admin::get_page_url('nwl-entries', array('action' => 'edit', 'id' => $entry->id))); ?>">
                                            <?php echo esc_html(NWL_Entry::get_instance()->get_child_name($entry)); ?>
                                        </a>
                                        <small><?php printf(esc_html__('Requested: %s', 'nursery-waiting-list'), date_i18n(get_option('date_format'), strtotime($entry->deletion_requested_date))); ?></small>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        <?php else : ?>
                            <p class="nwl-no-items"><?php esc_html_e('No pending deletion requests.', 'nursery-waiting-list'); ?></p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Export -->
            <div class="nwl-report-section">
                <h2><?php esc_html_e('Export Data', 'nursery-waiting-list'); ?></h2>
                
                <p><?php esc_html_e('Export waiting list data to CSV for reporting and analysis.', 'nursery-waiting-list'); ?></p>
                
                <div class="nwl-export-options">
                    <a href="<?php echo esc_url(NWL_Export::get_instance()->get_export_url()); ?>" class="button button-primary">
                        <?php esc_html_e('Export All Entries', 'nursery-waiting-list'); ?>
                    </a>
                    <a href="<?php echo esc_url(NWL_Export::get_instance()->get_export_url(array('status' => 'pending'))); ?>" class="button">
                        <?php esc_html_e('Export Pending', 'nursery-waiting-list'); ?>
                    </a>
                    <a href="<?php echo esc_url(NWL_Export::get_instance()->get_export_url(array('status' => 'offered'))); ?>" class="button">
                        <?php esc_html_e('Export Offered', 'nursery-waiting-list'); ?>
                    </a>
                    <a href="<?php echo esc_url(NWL_Admin::get_page_url('nwl-entries')); ?>" class="button">
                        <?php esc_html_e('Custom Export (Use Filters)', 'nursery-waiting-list'); ?>
                    </a>
                </div>
            </div>
        </div>

        <!-- Charts Script -->
        <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
        <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Status Chart
            var statusCtx = document.getElementById('nwl-status-chart');
            if (statusCtx) {
                new Chart(statusCtx, {
                    type: 'doughnut',
                    data: {
                        labels: [
                            <?php 
                            $labels = array();
                            $data = array();
                            $colors = array(
                                'pending' => '#f0ad4e',
                                'contacted' => '#5bc0de',
                                'waitlisted' => '#428bca',
                                'offered' => '#5cb85c',
                                'accepted' => '#449d44',
                                'enrolled' => '#2e8b57',
                                'declined' => '#d9534f',
                                'removed' => '#999999',
                            );
                            $bgColors = array();
                            foreach ($summary['by_status'] as $status => $statusData) {
                                if ($statusData['count'] > 0) {
                                    $labels[] = "'" . esc_js($statusData['label']) . "'";
                                    $data[] = $statusData['count'];
                                    $bgColors[] = "'" . (isset($colors[$status]) ? $colors[$status] : '#cccccc') . "'";
                                }
                            }
                            echo implode(', ', $labels);
                            ?>
                        ],
                        datasets: [{
                            data: [<?php echo implode(', ', $data); ?>],
                            backgroundColor: [<?php echo implode(', ', $bgColors); ?>]
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: {
                                position: 'right'
                            }
                        }
                    }
                });
            }

            // Room Chart
            var roomCtx = document.getElementById('nwl-room-chart');
            if (roomCtx) {
                new Chart(roomCtx, {
                    type: 'bar',
                    data: {
                        labels: [
                            <?php 
                            $labels = array();
                            $data = array();
                            foreach ($summary['by_room'] as $room => $roomData) {
                                if ($roomData['count'] > 0) {
                                    $labels[] = "'" . esc_js($roomData['label']) . "'";
                                    $data[] = $roomData['count'];
                                }
                            }
                            echo implode(', ', $labels);
                            ?>
                        ],
                        datasets: [{
                            label: '<?php esc_attr_e('Entries', 'nursery-waiting-list'); ?>',
                            data: [<?php echo implode(', ', $data); ?>],
                            backgroundColor: '#4A90A4'
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: {
                                display: false
                            }
                        },
                        scales: {
                            y: {
                                beginAtZero: true,
                                ticks: {
                                    stepSize: 1
                                }
                            }
                        }
                    }
                });
            }

            // Trends Chart
            var trendsCtx = document.getElementById('nwl-trends-chart');
            if (trendsCtx) {
                new Chart(trendsCtx, {
                    type: 'line',
                    data: {
                        labels: [
                            <?php 
                            $labels = array();
                            $data = array();
                            foreach ($summary['monthly_trends'] as $trend) {
                                $labels[] = "'" . esc_js($trend['label']) . "'";
                                $data[] = $trend['count'];
                            }
                            echo implode(', ', $labels);
                            ?>
                        ],
                        datasets: [{
                            label: '<?php esc_attr_e('New Entries', 'nursery-waiting-list'); ?>',
                            data: [<?php echo implode(', ', $data); ?>],
                            borderColor: '#4A90A4',
                            backgroundColor: 'rgba(74, 144, 164, 0.1)',
                            fill: true,
                            tension: 0.4
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: {
                                display: false
                            }
                        },
                        scales: {
                            y: {
                                beginAtZero: true,
                                ticks: {
                                    stepSize: 1
                                }
                            }
                        }
                    }
                });
            }
        });
        </script>
        <?php
    }
}
