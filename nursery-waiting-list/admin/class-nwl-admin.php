<?php
/**
 * Admin functionality
 */

if (!defined('ABSPATH')) {
    exit;
}

class NWL_Admin {

    private static $instance = null;

    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        add_action('admin_menu', array($this, 'add_menu'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_scripts'));
        add_action('admin_init', array($this, 'init'));
        
        // Initialize sub-pages
        NWL_Admin_Entries::get_instance();
        NWL_Admin_Settings::get_instance();
        NWL_Admin_Templates::get_instance();
        NWL_Admin_Messaging::get_instance();
        NWL_Admin_Reports::get_instance();
    }

    /**
     * Initialize admin
     */
    public function init() {
        // Check for Gravity Forms
        if (!class_exists('GFAPI')) {
            add_action('admin_notices', array($this, 'gravity_forms_notice'));
        }
    }

    /**
     * Gravity Forms notice
     */
    public function gravity_forms_notice() {
        $screen = get_current_screen();
        if (strpos($screen->id, 'nwl') === false) {
            return;
        }
        ?>
        <div class="notice notice-warning">
            <p>
                <strong><?php esc_html_e('Nursery Waiting List:', 'nursery-waiting-list'); ?></strong>
                <?php esc_html_e('Gravity Forms is not installed or activated. The waiting list integration with forms will not work until Gravity Forms is installed.', 'nursery-waiting-list'); ?>
            </p>
        </div>
        <?php
    }

    /**
     * Add admin menu
     */
    public function add_menu() {
        // Main menu
        add_menu_page(
            __('Waiting List', 'nursery-waiting-list'),
            __('Waiting List', 'nursery-waiting-list'),
            'nwl_view_entries',
            'nwl-entries',
            array('NWL_Admin_Entries', 'render_page'),
            'dashicons-groups',
            30
        );

        // Entries submenu
        add_submenu_page(
            'nwl-entries',
            __('All Entries', 'nursery-waiting-list'),
            __('All Entries', 'nursery-waiting-list'),
            'nwl_view_entries',
            'nwl-entries',
            array('NWL_Admin_Entries', 'render_page')
        );

        // Add new entry
        add_submenu_page(
            'nwl-entries',
            __('Add Entry', 'nursery-waiting-list'),
            __('Add Entry', 'nursery-waiting-list'),
            'nwl_edit_entries',
            'nwl-add-entry',
            array('NWL_Admin_Entries', 'render_add_page')
        );

        // Messaging
        add_submenu_page(
            'nwl-entries',
            __('Send Emails', 'nursery-waiting-list'),
            __('Send Emails', 'nursery-waiting-list'),
            'nwl_send_emails',
            'nwl-messaging',
            array('NWL_Admin_Messaging', 'render_page')
        );

        // Email templates
        add_submenu_page(
            'nwl-entries',
            __('Email Templates', 'nursery-waiting-list'),
            __('Email Templates', 'nursery-waiting-list'),
            'nwl_manage_settings',
            'nwl-templates',
            array('NWL_Admin_Templates', 'render_page')
        );

        // Reports
        add_submenu_page(
            'nwl-entries',
            __('Reports', 'nursery-waiting-list'),
            __('Reports', 'nursery-waiting-list'),
            'nwl_view_reports',
            'nwl-reports',
            array('NWL_Admin_Reports', 'render_page')
        );

        // Settings
        add_submenu_page(
            'nwl-entries',
            __('Settings', 'nursery-waiting-list'),
            __('Settings', 'nursery-waiting-list'),
            'nwl_manage_settings',
            'nwl-settings',
            array('NWL_Admin_Settings', 'render_page')
        );
    }

    /**
     * Enqueue admin scripts and styles
     */
    public function enqueue_scripts($hook) {
        if (strpos($hook, 'nwl') === false) {
            return;
        }

        wp_enqueue_style(
            'nwl-admin',
            NWL_PLUGIN_URL . 'assets/css/admin.css',
            array(),
            NWL_VERSION
        );

        wp_enqueue_script(
            'nwl-admin',
            NWL_PLUGIN_URL . 'assets/js/admin.js',
            array('jquery', 'wp-util'),
            NWL_VERSION,
            true
        );

        wp_localize_script('nwl-admin', 'nwlAdmin', array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('nwl_admin'),
            'strings' => array(
                'confirmDelete' => __('Are you sure you want to delete this entry? This action cannot be undone.', 'nursery-waiting-list'),
                'confirmBulkDelete' => __('Are you sure you want to delete the selected entries?', 'nursery-waiting-list'),
                'confirmSend' => __('Are you sure you want to send this email?', 'nursery-waiting-list'),
                'confirmBulkSend' => __('Are you sure you want to send emails to the selected entries?', 'nursery-waiting-list'),
                'saving' => __('Saving...', 'nursery-waiting-list'),
                'saved' => __('Saved!', 'nursery-waiting-list'),
                'error' => __('An error occurred.', 'nursery-waiting-list'),
                'selectEntries' => __('Please select at least one entry.', 'nursery-waiting-list'),
            ),
        ));

        // Date picker
        wp_enqueue_script('jquery-ui-datepicker');
        wp_enqueue_style('jquery-ui', 'https://code.jquery.com/ui/1.13.2/themes/base/jquery-ui.css');

        // WP Editor for templates
        if (strpos($hook, 'nwl-templates') !== false) {
            wp_enqueue_editor();
        }
    }

    /**
     * Get admin page URL
     */
    public static function get_page_url($page, $args = array()) {
        $base_url = admin_url('admin.php?page=' . $page);
        
        if (!empty($args)) {
            $base_url = add_query_arg($args, $base_url);
        }
        
        return $base_url;
    }

    /**
     * Render admin notice
     */
    public static function render_notice($message, $type = 'success', $dismissible = true) {
        $class = 'notice notice-' . $type;
        if ($dismissible) {
            $class .= ' is-dismissible';
        }
        
        printf('<div class="%s"><p>%s</p></div>', esc_attr($class), esc_html($message));
    }

    /**
     * Get status badge HTML
     */
    public static function get_status_badge($status) {
        $statuses = NWL_Database::get_statuses();
        $label = isset($statuses[$status]) ? $statuses[$status] : $status;
        
        $class = 'nwl-badge nwl-badge-' . sanitize_html_class($status);
        
        return sprintf('<span class="%s">%s</span>', $class, esc_html($label));
    }

    /**
     * Get priority badge HTML
     */
    public static function get_priority_badge($priority) {
        $priorities = NWL_Database::get_priorities();
        $label = isset($priorities[$priority]) ? $priorities[$priority] : $priority;
        
        $class = 'nwl-badge nwl-priority-' . sanitize_html_class($priority);
        
        return sprintf('<span class="%s">%s</span>', $class, esc_html($label));
    }

    /**
     * Render pagination
     */
    public static function render_pagination($total_pages, $current_page, $base_url) {
        if ($total_pages <= 1) {
            return;
        }

        $page_links = paginate_links(array(
            'base' => add_query_arg('paged', '%#%', $base_url),
            'format' => '',
            'prev_text' => __('&laquo; Previous', 'nursery-waiting-list'),
            'next_text' => __('Next &raquo;', 'nursery-waiting-list'),
            'total' => $total_pages,
            'current' => $current_page,
        ));

        if ($page_links) {
            echo '<div class="tablenav"><div class="tablenav-pages">' . $page_links . '</div></div>';
        }
    }
}
