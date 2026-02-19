<?php
/**
 * Plugin Name: Nursery360 Waiting List
 * Plugin URI: https://skywebdesign.co.uk
 * Description: A comprehensive waiting list management system for nurseries with Gravity Forms integration, email notifications, parent portal, and admin management.
 * Version: 1.2.0
 * Author: Sky Web Design
 * Author URI: https://skywebdesign.co.uk
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: nursery-waiting-list
 * Domain Path: /languages
 * Requires at least: 5.8
 * Requires PHP: 7.4
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Plugin constants
define('NWL_VERSION', '1.2.0');
define('NWL_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('NWL_PLUGIN_URL', plugin_dir_url(__FILE__));
define('NWL_PLUGIN_BASENAME', plugin_basename(__FILE__));
define('NWL_TABLE_ENTRIES', 'nwl_entries');
define('NWL_TABLE_NOTES', 'nwl_notes');
define('NWL_TABLE_EMAILS', 'nwl_email_logs');
define('NWL_TABLE_TEMPLATES', 'nwl_email_templates');

/**
 * Main plugin class
 */
final class Nursery_Waiting_List {

    /**
     * Single instance
     */
    private static $instance = null;

    /**
     * Get instance
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Constructor
     */
    private function __construct() {
        $this->load_dependencies();
        $this->init_hooks();
    }

    /**
     * Load required files
     */
    private function load_dependencies() {
        // Core classes
        require_once NWL_PLUGIN_DIR . 'includes/class-nwl-database.php';
        require_once NWL_PLUGIN_DIR . 'includes/class-nwl-entry.php';
        require_once NWL_PLUGIN_DIR . 'includes/class-nwl-email.php';
        require_once NWL_PLUGIN_DIR . 'includes/class-nwl-gravity-forms.php';
        require_once NWL_PLUGIN_DIR . 'includes/class-nwl-export.php';
        require_once NWL_PLUGIN_DIR . 'includes/class-nwl-stats.php';

        // Elementor integration
        add_action('elementor/loaded', array($this, 'load_elementor_integration'));

        // Admin classes
        if (is_admin()) {
            require_once NWL_PLUGIN_DIR . 'admin/class-nwl-admin.php';
            require_once NWL_PLUGIN_DIR . 'admin/class-nwl-admin-entries.php';
            require_once NWL_PLUGIN_DIR . 'admin/class-nwl-admin-settings.php';
            require_once NWL_PLUGIN_DIR . 'admin/class-nwl-admin-templates.php';
            require_once NWL_PLUGIN_DIR . 'admin/class-nwl-admin-messaging.php';
            require_once NWL_PLUGIN_DIR . 'admin/class-nwl-admin-reports.php';
        }
        
        // Public classes
        require_once NWL_PLUGIN_DIR . 'public/class-nwl-public.php';
    }

    /**
     * Initialize hooks
     */
    private function init_hooks() {
        register_activation_hook(__FILE__, array($this, 'activate'));
        register_deactivation_hook(__FILE__, array($this, 'deactivate'));
        
        add_action('plugins_loaded', array($this, 'on_plugins_loaded'));
        add_action('init', array($this, 'init'));
    }

    /**
     * On plugins loaded
     */
    public function on_plugins_loaded() {
        load_plugin_textdomain('nursery-waiting-list', false, dirname(NWL_PLUGIN_BASENAME) . '/languages/');
    }

    /**
     * Initialize
     */
    public function init() {
        // Initialize components
        NWL_Database::get_instance();
        NWL_Entry::get_instance();
        NWL_Email::get_instance();
        NWL_Gravity_Forms::get_instance();
        NWL_Export::get_instance();
        NWL_Stats::get_instance();
        NWL_Public::get_instance();

        if (is_admin()) {
            NWL_Admin::get_instance();
        }
    }

    /**
     * Load Elementor integration
     */
    public function load_elementor_integration() {
        require_once NWL_PLUGIN_DIR . 'includes/class-nwl-elementor.php';
        NWL_Elementor::get_instance();
    }

    /**
     * Plugin activation
     */
    public function activate() {
        NWL_Database::create_tables();
        NWL_Database::insert_default_templates();
        
        // Add capabilities to admin and editor roles
        $this->add_capabilities();
        
        // Create public page
        $this->create_stats_page();
        
        // Flush rewrite rules
        flush_rewrite_rules();
    }

    /**
     * Plugin deactivation
     */
    public function deactivate() {
        flush_rewrite_rules();
    }

    /**
     * Add capabilities
     */
    private function add_capabilities() {
        $capabilities = array(
            'nwl_view_entries',
            'nwl_edit_entries',
            'nwl_delete_entries',
            'nwl_send_emails',
            'nwl_manage_settings',
            'nwl_export_data',
            'nwl_view_reports'
        );

        // Admin gets all capabilities
        $admin = get_role('administrator');
        if ($admin) {
            foreach ($capabilities as $cap) {
                $admin->add_cap($cap);
            }
        }

        // Create Manager role with waiting list capabilities
        $manager_caps = array(
            'read' => true,
            'nwl_view_entries' => true,
            'nwl_edit_entries' => true,
            'nwl_send_emails' => true,
            'nwl_view_reports' => true,
            'nwl_export_data' => true,
        );
        
        remove_role('nwl_manager');
        add_role('nwl_manager', __('Waiting List Manager', 'nursery-waiting-list'), $manager_caps);
    }

    /**
     * Create the stats page
     */
    private function create_stats_page() {
        $page_exists = get_page_by_path('waiting-list-stats');
        
        if (!$page_exists) {
            wp_insert_post(array(
                'post_title' => __('Check Your Waiting List Status', 'nursery-waiting-list'),
                'post_name' => 'waiting-list-stats',
                'post_content' => '[nwl_parent_lookup]',
                'post_status' => 'publish',
                'post_type' => 'page',
                'post_author' => 1,
            ));
        }
    }
}

/**
 * Initialize the plugin
 */
function nursery_waiting_list() {
    return Nursery_Waiting_List::get_instance();
}

// Start the plugin
nursery_waiting_list();
