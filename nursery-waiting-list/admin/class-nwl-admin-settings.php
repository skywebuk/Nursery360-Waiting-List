<?php
/**
 * Admin settings
 */

if (!defined('ABSPATH')) {
    exit;
}

class NWL_Admin_Settings {

    private static $instance = null;

    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        add_action('admin_init', array($this, 'register_settings'));
    }

    /**
     * Register settings
     */
    public function register_settings() {
        // General Settings
        register_setting('nwl_general_settings', 'nwl_nursery_name');
        register_setting('nwl_general_settings', 'nwl_nursery_email');
        register_setting('nwl_general_settings', 'nwl_nursery_phone');
        register_setting('nwl_general_settings', 'nwl_nursery_address');
        register_setting('nwl_general_settings', 'nwl_wl_number_prefix');

        // Email Settings
        register_setting('nwl_email_settings', 'nwl_email_from_name');
        register_setting('nwl_email_settings', 'nwl_email_from_address');
        register_setting('nwl_email_settings', 'nwl_email_reply_to');
        register_setting('nwl_email_settings', 'nwl_send_registration_email');
        register_setting('nwl_email_settings', 'nwl_send_status_emails');

        // Occupancy Settings
        register_setting('nwl_occupancy_settings', 'nwl_total_occupancy');
        register_setting('nwl_occupancy_settings', 'nwl_year_groups');
    }

    /**
     * Render settings page
     */
    public static function render_page() {
        if (!current_user_can('nwl_manage_settings')) {
            wp_die(__('You do not have permission to access this page.', 'nursery-waiting-list'));
        }

        $active_tab = isset($_GET['tab']) ? sanitize_text_field($_GET['tab']) : 'general';
        
        ?>
        <div class="wrap nwl-admin-wrap">
            <h1><?php esc_html_e('Waiting List Settings', 'nursery-waiting-list'); ?></h1>

            <nav class="nav-tab-wrapper">
                <a href="<?php echo esc_url(add_query_arg('tab', 'general')); ?>"
                   class="nav-tab <?php echo $active_tab === 'general' ? 'nav-tab-active' : ''; ?>">
                    <?php esc_html_e('General', 'nursery-waiting-list'); ?>
                </a>
                <a href="<?php echo esc_url(add_query_arg('tab', 'occupancy')); ?>"
                   class="nav-tab <?php echo $active_tab === 'occupancy' ? 'nav-tab-active' : ''; ?>">
                    <?php esc_html_e('Occupancy', 'nursery-waiting-list'); ?>
                </a>
                <a href="<?php echo esc_url(add_query_arg('tab', 'email')); ?>"
                   class="nav-tab <?php echo $active_tab === 'email' ? 'nav-tab-active' : ''; ?>">
                    <?php esc_html_e('Email', 'nursery-waiting-list'); ?>
                </a>
                <a href="<?php echo esc_url(add_query_arg('tab', 'gravity-forms')); ?>"
                   class="nav-tab <?php echo $active_tab === 'gravity-forms' ? 'nav-tab-active' : ''; ?>">
                    <?php esc_html_e('Gravity Forms', 'nursery-waiting-list'); ?>
                </a>
            </nav>

            <div class="nwl-settings-content">
                <?php
                switch ($active_tab) {
                    case 'occupancy':
                        self::render_occupancy_settings();
                        break;
                    case 'email':
                        self::render_email_settings();
                        break;
                    case 'gravity-forms':
                        self::render_gravity_forms_settings();
                        break;
                    default:
                        self::render_general_settings();
                }
                ?>
            </div>
        </div>
        <?php
    }

    /**
     * Render general settings
     */
    private static function render_general_settings() {
        if (isset($_POST['nwl_save_general']) && wp_verify_nonce($_POST['nwl_general_nonce'], 'nwl_save_general')) {
            update_option('nwl_nursery_name', sanitize_text_field($_POST['nwl_nursery_name']));
            update_option('nwl_nursery_email', sanitize_email($_POST['nwl_nursery_email']));
            update_option('nwl_nursery_phone', sanitize_text_field($_POST['nwl_nursery_phone']));
            update_option('nwl_nursery_address', sanitize_textarea_field($_POST['nwl_nursery_address']));
            update_option('nwl_wl_number_prefix', sanitize_text_field($_POST['nwl_wl_number_prefix']));
            
            echo '<div class="notice notice-success"><p>' . esc_html__('Settings saved.', 'nursery-waiting-list') . '</p></div>';
        }
        ?>
        <form method="post">
            <?php wp_nonce_field('nwl_save_general', 'nwl_general_nonce'); ?>
            
            <table class="form-table">
                <tr>
                    <th scope="row">
                        <label for="nwl_nursery_name"><?php esc_html_e('Nursery Name', 'nursery-waiting-list'); ?></label>
                    </th>
                    <td>
                        <input type="text" id="nwl_nursery_name" name="nwl_nursery_name" 
                               value="<?php echo esc_attr(get_option('nwl_nursery_name', get_bloginfo('name'))); ?>" 
                               class="regular-text">
                        <p class="description"><?php esc_html_e('This will be used in email templates.', 'nursery-waiting-list'); ?></p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="nwl_nursery_email"><?php esc_html_e('Nursery Email', 'nursery-waiting-list'); ?></label>
                    </th>
                    <td>
                        <input type="email" id="nwl_nursery_email" name="nwl_nursery_email" 
                               value="<?php echo esc_attr(get_option('nwl_nursery_email', get_option('admin_email'))); ?>" 
                               class="regular-text">
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="nwl_nursery_phone"><?php esc_html_e('Nursery Phone', 'nursery-waiting-list'); ?></label>
                    </th>
                    <td>
                        <input type="tel" id="nwl_nursery_phone" name="nwl_nursery_phone" 
                               value="<?php echo esc_attr(get_option('nwl_nursery_phone', '')); ?>" 
                               class="regular-text">
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="nwl_nursery_address"><?php esc_html_e('Nursery Address', 'nursery-waiting-list'); ?></label>
                    </th>
                    <td>
                        <textarea id="nwl_nursery_address" name="nwl_nursery_address" rows="3" class="large-text"><?php echo esc_textarea(get_option('nwl_nursery_address', '')); ?></textarea>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="nwl_wl_number_prefix"><?php esc_html_e('Waiting List Number Prefix', 'nursery-waiting-list'); ?></label>
                    </th>
                    <td>
                        <input type="text" id="nwl_wl_number_prefix" name="nwl_wl_number_prefix" 
                               value="<?php echo esc_attr(get_option('nwl_wl_number_prefix', 'WL')); ?>" 
                               class="small-text">
                        <p class="description"><?php esc_html_e('Prefix for waiting list numbers (e.g., WL2024001).', 'nursery-waiting-list'); ?></p>
                    </td>
                </tr>
            </table>

            <p class="submit">
                <input type="submit" name="nwl_save_general" class="button button-primary" value="<?php esc_attr_e('Save Settings', 'nursery-waiting-list'); ?>">
            </p>
        </form>
        <?php
    }

    /**
     * Render occupancy settings
     */
    private static function render_occupancy_settings() {
        // Handle form submission
        if (isset($_POST['nwl_save_occupancy']) && wp_verify_nonce($_POST['nwl_occupancy_nonce'], 'nwl_save_occupancy')) {
            $total_occupancy = absint($_POST['nwl_total_occupancy']);
            update_option('nwl_total_occupancy', $total_occupancy);

            // Process year groups
            $year_groups = array();
            if (isset($_POST['year_group_name']) && is_array($_POST['year_group_name'])) {
                $names = $_POST['year_group_name'];
                $capacities = isset($_POST['year_group_capacity']) ? $_POST['year_group_capacity'] : array();
                $min_ages = isset($_POST['year_group_min_age']) ? $_POST['year_group_min_age'] : array();
                $max_ages = isset($_POST['year_group_max_age']) ? $_POST['year_group_max_age'] : array();

                $total_allocated = 0;
                foreach ($names as $index => $name) {
                    $name = sanitize_text_field($name);
                    $capacity = isset($capacities[$index]) ? absint($capacities[$index]) : 0;
                    $min_age = isset($min_ages[$index]) ? absint($min_ages[$index]) : 0;
                    $max_age = isset($max_ages[$index]) ? absint($max_ages[$index]) : 0;

                    if (!empty($name) && $capacity > 0) {
                        $total_allocated += $capacity;
                        $year_groups[] = array(
                            'id' => sanitize_title($name) . '-' . $index,
                            'name' => $name,
                            'capacity' => $capacity,
                            'min_age' => $min_age,
                            'max_age' => $max_age,
                        );
                    }
                }

                // Sort year groups by min_age
                usort($year_groups, function($a, $b) {
                    return $a['min_age'] - $b['min_age'];
                });

                // Re-assign IDs after sorting
                foreach ($year_groups as $index => &$group) {
                    $group['id'] = sanitize_title($group['name']) . '-' . $index;
                }

                // Validate total doesn't exceed occupancy
                if ($total_allocated > $total_occupancy) {
                    echo '<div class="notice notice-error"><p>' .
                        sprintf(
                            esc_html__('Error: Total year group capacity (%d) exceeds total occupancy (%d). Please adjust the values.', 'nursery-waiting-list'),
                            $total_allocated,
                            $total_occupancy
                        ) . '</p></div>';
                } else {
                    update_option('nwl_year_groups', $year_groups);
                    echo '<div class="notice notice-success"><p>' . esc_html__('Occupancy settings saved.', 'nursery-waiting-list') . '</p></div>';
                }
            } else {
                update_option('nwl_year_groups', array());
                echo '<div class="notice notice-success"><p>' . esc_html__('Occupancy settings saved.', 'nursery-waiting-list') . '</p></div>';
            }
        }

        $total_occupancy = get_option('nwl_total_occupancy', 0);
        $year_groups = get_option('nwl_year_groups', array());

        // Calculate allocated and remaining
        $total_allocated = 0;
        foreach ($year_groups as $group) {
            $total_allocated += $group['capacity'];
        }
        $remaining = $total_occupancy - $total_allocated;

        // Get enrolled counts per year group
        $enrolled_counts = NWL_Stats::get_instance()->get_enrolled_by_year_group();
        ?>
        <form method="post">
            <?php wp_nonce_field('nwl_save_occupancy', 'nwl_occupancy_nonce'); ?>

            <h2><?php esc_html_e('Nursery Occupancy', 'nursery-waiting-list'); ?></h2>
            <p class="description"><?php esc_html_e('Set the total number of students your nursery can accommodate and allocate capacity across year groups.', 'nursery-waiting-list'); ?></p>

            <table class="form-table">
                <tr>
                    <th scope="row">
                        <label for="nwl_total_occupancy"><?php esc_html_e('Total Occupancy', 'nursery-waiting-list'); ?></label>
                    </th>
                    <td>
                        <input type="number" id="nwl_total_occupancy" name="nwl_total_occupancy"
                               value="<?php echo esc_attr($total_occupancy); ?>"
                               class="small-text" min="0" step="1">
                        <p class="description"><?php esc_html_e('The maximum number of students your nursery can hold.', 'nursery-waiting-list'); ?></p>
                    </td>
                </tr>
            </table>

            <!-- Occupancy Summary -->
            <?php if ($total_occupancy > 0) : ?>
                <div class="nwl-occupancy-summary" style="background: #f8f9fa; padding: 20px; border-radius: 8px; margin: 20px 0; border-left: 4px solid #4A90A4;">
                    <h3 style="margin-top: 0;"><?php esc_html_e('Occupancy Summary', 'nursery-waiting-list'); ?></h3>
                    <div style="display: flex; gap: 30px; flex-wrap: wrap;">
                        <div>
                            <strong><?php esc_html_e('Total Capacity:', 'nursery-waiting-list'); ?></strong>
                            <span style="font-size: 1.2em; color: #333;"><?php echo esc_html($total_occupancy); ?></span>
                        </div>
                        <div>
                            <strong><?php esc_html_e('Allocated to Year Groups:', 'nursery-waiting-list'); ?></strong>
                            <span style="font-size: 1.2em; color: #4A90A4;"><?php echo esc_html($total_allocated); ?></span>
                        </div>
                        <div>
                            <strong><?php esc_html_e('Unallocated:', 'nursery-waiting-list'); ?></strong>
                            <span style="font-size: 1.2em; color: <?php echo $remaining < 0 ? '#dc3545' : ($remaining > 0 ? '#ffc107' : '#28a745'); ?>;">
                                <?php echo esc_html($remaining); ?>
                            </span>
                            <?php if ($remaining < 0) : ?>
                                <span style="color: #dc3545; font-weight: bold;"> (<?php esc_html_e('Over-allocated!', 'nursery-waiting-list'); ?>)</span>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <h2 style="margin-top: 30px;"><?php esc_html_e('Year Groups', 'nursery-waiting-list'); ?></h2>
            <p class="description"><?php esc_html_e('Create year groups with age ranges. Children will be automatically assigned to a year group based on their date of birth.', 'nursery-waiting-list'); ?></p>

            <div id="nwl-year-groups-container">
                <table class="widefat nwl-year-groups-table" style="max-width: 1000px;">
                    <thead>
                        <tr>
                            <th style="width: 25%;"><?php esc_html_e('Year Group Name', 'nursery-waiting-list'); ?></th>
                            <th style="width: 15%;"><?php esc_html_e('Age Range', 'nursery-waiting-list'); ?></th>
                            <th style="width: 12%;"><?php esc_html_e('Capacity', 'nursery-waiting-list'); ?></th>
                            <th style="width: 10%;"><?php esc_html_e('Enrolled', 'nursery-waiting-list'); ?></th>
                            <th style="width: 18%;"><?php esc_html_e('Status', 'nursery-waiting-list'); ?></th>
                            <th style="width: 10%;"><?php esc_html_e('Actions', 'nursery-waiting-list'); ?></th>
                        </tr>
                    </thead>
                    <tbody id="nwl-year-groups-list">
                        <?php if (!empty($year_groups)) : ?>
                            <?php foreach ($year_groups as $index => $group) :
                                $enrolled = isset($enrolled_counts[$group['id']]) ? $enrolled_counts[$group['id']] : 0;
                                $available = $group['capacity'] - $enrolled;
                                $is_full = $available <= 0;
                                $min_age = isset($group['min_age']) ? $group['min_age'] : 0;
                                $max_age = isset($group['max_age']) ? $group['max_age'] : 0;
                            ?>
                                <tr class="nwl-year-group-row">
                                    <td>
                                        <input type="text" name="year_group_name[]"
                                               value="<?php echo esc_attr($group['name']); ?>"
                                               class="regular-text" required
                                               placeholder="<?php esc_attr_e('e.g., Babies', 'nursery-waiting-list'); ?>">
                                    </td>
                                    <td>
                                        <div style="display: flex; align-items: center; gap: 5px;">
                                            <input type="number" name="year_group_min_age[]"
                                                   value="<?php echo esc_attr($min_age); ?>"
                                                   class="small-text" min="0" max="10" style="width: 50px;" required
                                                   placeholder="0">
                                            <span>-</span>
                                            <input type="number" name="year_group_max_age[]"
                                                   value="<?php echo esc_attr($max_age); ?>"
                                                   class="small-text" min="0" max="10" style="width: 50px;" required
                                                   placeholder="2">
                                            <span style="color: #666; font-size: 12px;"><?php esc_html_e('yrs', 'nursery-waiting-list'); ?></span>
                                        </div>
                                    </td>
                                    <td>
                                        <input type="number" name="year_group_capacity[]"
                                               value="<?php echo esc_attr($group['capacity']); ?>"
                                               class="small-text nwl-capacity-input" min="1" required>
                                    </td>
                                    <td>
                                        <span class="nwl-enrolled-count"><?php echo esc_html($enrolled); ?></span>
                                    </td>
                                    <td>
                                        <?php if ($is_full) : ?>
                                            <span class="nwl-status-badge nwl-status-full" style="background: #dc3545; color: white; padding: 4px 12px; border-radius: 4px; font-weight: bold;">
                                                <?php esc_html_e('FULL', 'nursery-waiting-list'); ?>
                                            </span>
                                        <?php else : ?>
                                            <span class="nwl-status-badge nwl-status-available" style="background: #28a745; color: white; padding: 4px 12px; border-radius: 4px;">
                                                <?php printf(esc_html__('%d available', 'nursery-waiting-list'), $available); ?>
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <button type="button" class="button button-link-delete nwl-remove-year-group" title="<?php esc_attr_e('Remove', 'nursery-waiting-list'); ?>">
                                            <span class="dashicons dashicons-trash"></span>
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                    <tfoot>
                        <tr>
                            <td colspan="6">
                                <button type="button" id="nwl-add-year-group" class="button">
                                    <span class="dashicons dashicons-plus-alt2" style="vertical-align: middle;"></span>
                                    <?php esc_html_e('Add Year Group', 'nursery-waiting-list'); ?>
                                </button>
                            </td>
                        </tr>
                    </tfoot>
                </table>
            </div>

            <div style="margin-top: 15px; padding: 15px; background: #e8f4f8; border-radius: 4px; max-width: 1000px;">
                <strong><?php esc_html_e('Example Year Groups:', 'nursery-waiting-list'); ?></strong>
                <p style="margin: 10px 0 0; color: #666;">
                    <?php esc_html_e('Babies (0-2 yrs), Toddlers (2-3 yrs), Pre-School (3-4 yrs), Reception (4-5 yrs)', 'nursery-waiting-list'); ?>
                </p>
            </div>

            <!-- Total Allocated Display -->
            <div id="nwl-allocation-tracker" style="margin-top: 20px; padding: 15px; background: #fff; border: 1px solid #ddd; border-radius: 4px; max-width: 1000px;">
                <strong><?php esc_html_e('Total Allocated:', 'nursery-waiting-list'); ?></strong>
                <span id="nwl-total-allocated"><?php echo esc_html($total_allocated); ?></span> /
                <span id="nwl-total-capacity"><?php echo esc_html($total_occupancy); ?></span>
                <span id="nwl-allocation-status"></span>
            </div>

            <p class="submit">
                <input type="submit" name="nwl_save_occupancy" class="button button-primary" value="<?php esc_attr_e('Save Occupancy Settings', 'nursery-waiting-list'); ?>">
            </p>
        </form>

        <!-- Year Group Row Template -->
        <script type="text/template" id="nwl-year-group-template">
            <tr class="nwl-year-group-row">
                <td>
                    <input type="text" name="year_group_name[]"
                           class="regular-text" required
                           placeholder="<?php esc_attr_e('e.g., Babies', 'nursery-waiting-list'); ?>">
                </td>
                <td>
                    <div style="display: flex; align-items: center; gap: 5px;">
                        <input type="number" name="year_group_min_age[]"
                               class="small-text" min="0" max="10" style="width: 50px;" value="0" required
                               placeholder="0">
                        <span>-</span>
                        <input type="number" name="year_group_max_age[]"
                               class="small-text" min="0" max="10" style="width: 50px;" value="2" required
                               placeholder="2">
                        <span style="color: #666; font-size: 12px;"><?php esc_html_e('yrs', 'nursery-waiting-list'); ?></span>
                    </div>
                </td>
                <td>
                    <input type="number" name="year_group_capacity[]"
                           class="small-text nwl-capacity-input" min="1" value="1" required>
                </td>
                <td>
                    <span class="nwl-enrolled-count">0</span>
                </td>
                <td>
                    <span class="nwl-status-badge nwl-status-available" style="background: #28a745; color: white; padding: 4px 12px; border-radius: 4px;">
                        <?php esc_html_e('1 available', 'nursery-waiting-list'); ?>
                    </span>
                </td>
                <td>
                    <button type="button" class="button button-link-delete nwl-remove-year-group" title="<?php esc_attr_e('Remove', 'nursery-waiting-list'); ?>">
                        <span class="dashicons dashicons-trash"></span>
                    </button>
                </td>
            </tr>
        </script>

        <script>
        jQuery(document).ready(function($) {
            // Add year group
            $('#nwl-add-year-group').on('click', function() {
                var template = $('#nwl-year-group-template').html();
                $('#nwl-year-groups-list').append(template);
                updateAllocationTracker();
            });

            // Remove year group
            $(document).on('click', '.nwl-remove-year-group', function() {
                $(this).closest('tr').remove();
                updateAllocationTracker();
            });

            // Update tracker when capacity changes
            $(document).on('input', '.nwl-capacity-input, #nwl_total_occupancy', function() {
                updateAllocationTracker();
            });

            function updateAllocationTracker() {
                var totalCapacity = parseInt($('#nwl_total_occupancy').val()) || 0;
                var totalAllocated = 0;

                $('.nwl-capacity-input').each(function() {
                    totalAllocated += parseInt($(this).val()) || 0;
                });

                $('#nwl-total-allocated').text(totalAllocated);
                $('#nwl-total-capacity').text(totalCapacity);

                var remaining = totalCapacity - totalAllocated;
                var statusHtml = '';

                if (remaining < 0) {
                    statusHtml = '<span style="color: #dc3545; margin-left: 10px; font-weight: bold;"><?php esc_html_e('Over-allocated by', 'nursery-waiting-list'); ?> ' + Math.abs(remaining) + '</span>';
                } else if (remaining > 0) {
                    statusHtml = '<span style="color: #856404; margin-left: 10px;">' + remaining + ' <?php esc_html_e('unallocated', 'nursery-waiting-list'); ?></span>';
                } else {
                    statusHtml = '<span style="color: #28a745; margin-left: 10px; font-weight: bold;"><?php esc_html_e('Fully allocated', 'nursery-waiting-list'); ?></span>';
                }

                $('#nwl-allocation-status').html(statusHtml);
            }

            // Initial update
            updateAllocationTracker();
        });
        </script>
        <?php
    }

    /**
     * Render email settings
     */
    private static function render_email_settings() {
        if (isset($_POST['nwl_save_email']) && wp_verify_nonce($_POST['nwl_email_nonce'], 'nwl_save_email')) {
            update_option('nwl_email_from_name', sanitize_text_field($_POST['nwl_email_from_name']));
            update_option('nwl_email_from_address', sanitize_email($_POST['nwl_email_from_address']));
            update_option('nwl_email_reply_to', sanitize_email($_POST['nwl_email_reply_to']));
            update_option('nwl_send_registration_email', isset($_POST['nwl_send_registration_email']) ? 1 : 0);
            update_option('nwl_send_status_emails', isset($_POST['nwl_send_status_emails']) ? 1 : 0);
            
            echo '<div class="notice notice-success"><p>' . esc_html__('Settings saved.', 'nursery-waiting-list') . '</p></div>';
        }
        ?>
        <form method="post">
            <?php wp_nonce_field('nwl_save_email', 'nwl_email_nonce'); ?>
            
            <table class="form-table">
                <tr>
                    <th scope="row">
                        <label for="nwl_email_from_name"><?php esc_html_e('From Name', 'nursery-waiting-list'); ?></label>
                    </th>
                    <td>
                        <input type="text" id="nwl_email_from_name" name="nwl_email_from_name" 
                               value="<?php echo esc_attr(get_option('nwl_email_from_name', get_bloginfo('name'))); ?>" 
                               class="regular-text">
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="nwl_email_from_address"><?php esc_html_e('From Email Address', 'nursery-waiting-list'); ?></label>
                    </th>
                    <td>
                        <input type="email" id="nwl_email_from_address" name="nwl_email_from_address" 
                               value="<?php echo esc_attr(get_option('nwl_email_from_address', get_option('admin_email'))); ?>" 
                               class="regular-text">
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="nwl_email_reply_to"><?php esc_html_e('Reply-To Address', 'nursery-waiting-list'); ?></label>
                    </th>
                    <td>
                        <input type="email" id="nwl_email_reply_to" name="nwl_email_reply_to" 
                               value="<?php echo esc_attr(get_option('nwl_email_reply_to', '')); ?>" 
                               class="regular-text">
                        <p class="description"><?php esc_html_e('Optional. Leave blank to use the From address.', 'nursery-waiting-list'); ?></p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><?php esc_html_e('Automatic Emails', 'nursery-waiting-list'); ?></th>
                    <td>
                        <label>
                            <input type="checkbox" name="nwl_send_registration_email" value="1" 
                                   <?php checked(get_option('nwl_send_registration_email', 1)); ?>>
                            <?php esc_html_e('Send confirmation email when new entries are added', 'nursery-waiting-list'); ?>
                        </label>
                        <br><br>
                        <label>
                            <input type="checkbox" name="nwl_send_status_emails" value="1" 
                                   <?php checked(get_option('nwl_send_status_emails', 1)); ?>>
                            <?php esc_html_e('Send notification when status changes', 'nursery-waiting-list'); ?>
                        </label>
                    </td>
                </tr>
            </table>

            <p class="submit">
                <input type="submit" name="nwl_save_email" class="button button-primary" value="<?php esc_attr_e('Save Settings', 'nursery-waiting-list'); ?>">
            </p>
        </form>

        <hr>
        
        <h2><?php esc_html_e('Test Email', 'nursery-waiting-list'); ?></h2>
        <p><?php esc_html_e('Send a test email to verify your email settings are working correctly.', 'nursery-waiting-list'); ?></p>
        
        <form method="post" id="nwl-test-email-form">
            <?php wp_nonce_field('nwl_test_email', 'nwl_test_email_nonce'); ?>
            <input type="email" name="test_email" placeholder="<?php esc_attr_e('Enter email address', 'nursery-waiting-list'); ?>" class="regular-text">
            <button type="submit" name="nwl_send_test" class="button"><?php esc_html_e('Send Test Email', 'nursery-waiting-list'); ?></button>
        </form>
        
        <?php
        if (isset($_POST['nwl_send_test']) && wp_verify_nonce($_POST['nwl_test_email_nonce'], 'nwl_test_email')) {
            $test_email = sanitize_email($_POST['test_email']);
            if ($test_email) {
                $subject = __('Waiting List Test Email', 'nursery-waiting-list');
                $message = __('This is a test email from your Nursery Waiting List system. If you received this, your email settings are configured correctly.', 'nursery-waiting-list');
                
                $result = wp_mail($test_email, $subject, $message);
                
                if ($result) {
                    echo '<div class="notice notice-success"><p>' . sprintf(esc_html__('Test email sent to %s', 'nursery-waiting-list'), esc_html($test_email)) . '</p></div>';
                } else {
                    echo '<div class="notice notice-error"><p>' . esc_html__('Failed to send test email. Please check your email configuration.', 'nursery-waiting-list') . '</p></div>';
                }
            }
        }
    }

    /**
     * Render Gravity Forms settings
     */
    private static function render_gravity_forms_settings() {
        if (!class_exists('GFAPI')) {
            ?>
            <div class="notice notice-warning">
                <p><?php esc_html_e('Gravity Forms is not installed or activated. Please install and activate Gravity Forms to use this integration.', 'nursery-waiting-list'); ?></p>
            </div>
            <?php
            return;
        }

        $linked_forms = NWL_Gravity_Forms::get_instance()->get_linked_forms();
        ?>
        <h2><?php esc_html_e('Gravity Forms Integration', 'nursery-waiting-list'); ?></h2>
        
        <p><?php esc_html_e('Link your Gravity Forms to the waiting list system. When a form is submitted, entries will automatically be added to the waiting list.', 'nursery-waiting-list'); ?></p>

        <?php if (!empty($linked_forms)) : ?>
            <h3><?php esc_html_e('Linked Forms', 'nursery-waiting-list'); ?></h3>
            <table class="widefat" style="max-width: 600px;">
                <thead>
                    <tr>
                        <th><?php esc_html_e('Form', 'nursery-waiting-list'); ?></th>
                        <th><?php esc_html_e('Actions', 'nursery-waiting-list'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($linked_forms as $form) : ?>
                        <tr>
                            <td><?php echo esc_html($form['title']); ?></td>
                            <td>
                                <a href="<?php echo esc_url(admin_url('admin.php?page=gf_edit_forms&view=settings&subview=nwl_settings&id=' . $form['id'])); ?>" class="button button-small">
                                    <?php esc_html_e('Configure', 'nursery-waiting-list'); ?>
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php else : ?>
            <p><?php esc_html_e('No forms are currently linked to the waiting list.', 'nursery-waiting-list'); ?></p>
        <?php endif; ?>

        <h3 style="margin-top: 30px;"><?php esc_html_e('How to Link a Form', 'nursery-waiting-list'); ?></h3>
        <ol>
            <li><?php esc_html_e('Go to Forms > Edit a form', 'nursery-waiting-list'); ?></li>
            <li><?php esc_html_e('Click Settings > Waiting List', 'nursery-waiting-list'); ?></li>
            <li><?php esc_html_e('Enable the integration and map your form fields', 'nursery-waiting-list'); ?></li>
        </ol>

        <p>
            <a href="<?php echo esc_url(admin_url('admin.php?page=gf_edit_forms')); ?>" class="button">
                <?php esc_html_e('Go to Forms', 'nursery-waiting-list'); ?>
            </a>
        </p>
        <?php
    }
}
