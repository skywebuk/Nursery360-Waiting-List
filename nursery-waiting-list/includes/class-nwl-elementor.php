<?php
/**
 * Elementor Integration for Nursery Waiting List
 */

if (!defined('ABSPATH')) {
    exit;
}

class NWL_Elementor {

    private static $instance = null;

    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        add_action('elementor/widgets/register', array($this, 'register_widgets'));
        add_action('elementor/elements/categories_registered', array($this, 'add_elementor_category'));
    }

    /**
     * Add custom Elementor category
     */
    public function add_elementor_category($elements_manager) {
        $elements_manager->add_category(
            'nursery-waiting-list',
            array(
                'title' => __('Nursery Waiting List', 'nursery-waiting-list'),
                'icon' => 'fa fa-child',
            )
        );
    }

    /**
     * Register widgets
     */
    public function register_widgets($widgets_manager) {
        require_once NWL_PLUGIN_DIR . 'includes/widgets/class-nwl-widget-parent-lookup.php';
        $widgets_manager->register(new NWL_Widget_Parent_Lookup());
    }
}
