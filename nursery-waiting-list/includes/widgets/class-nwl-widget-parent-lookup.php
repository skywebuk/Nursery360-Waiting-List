<?php
/**
 * Elementor Parent Lookup Widget
 */

if (!defined('ABSPATH')) {
    exit;
}

class NWL_Widget_Parent_Lookup extends \Elementor\Widget_Base {

    public function get_name() {
        return 'nwl_parent_lookup';
    }

    public function get_title() {
        return __('Waiting List Lookup', 'nursery-waiting-list');
    }

    public function get_icon() {
        return 'eicon-search';
    }

    public function get_categories() {
        return array('nursery-waiting-list', 'general');
    }

    public function get_keywords() {
        return array('nursery', 'waiting', 'list', 'lookup', 'search', 'status');
    }

    public function get_style_depends() {
        return array('nwl-public');
    }

    public function get_script_depends() {
        return array('nwl-public');
    }

    protected function register_controls() {

        // Content Section
        $this->start_controls_section(
            'content_section',
            array(
                'label' => __('Content', 'nursery-waiting-list'),
                'tab' => \Elementor\Controls_Manager::TAB_CONTENT,
            )
        );

        $this->add_control(
            'title',
            array(
                'label' => __('Title', 'nursery-waiting-list'),
                'type' => \Elementor\Controls_Manager::TEXT,
                'default' => __('Check Your Waiting List Status', 'nursery-waiting-list'),
                'placeholder' => __('Enter title', 'nursery-waiting-list'),
                'label_block' => true,
            )
        );

        $this->add_control(
            'description',
            array(
                'label' => __('Description', 'nursery-waiting-list'),
                'type' => \Elementor\Controls_Manager::TEXTAREA,
                'default' => __('Enter your email address or phone number to check the status of your waiting list application.', 'nursery-waiting-list'),
                'placeholder' => __('Enter description', 'nursery-waiting-list'),
                'rows' => 3,
            )
        );

        $this->add_control(
            'button_text',
            array(
                'label' => __('Button Text', 'nursery-waiting-list'),
                'type' => \Elementor\Controls_Manager::TEXT,
                'default' => __('Check Status', 'nursery-waiting-list'),
            )
        );

        $this->add_control(
            'show_email_option',
            array(
                'label' => __('Show Email Search', 'nursery-waiting-list'),
                'type' => \Elementor\Controls_Manager::SWITCHER,
                'label_on' => __('Yes', 'nursery-waiting-list'),
                'label_off' => __('No', 'nursery-waiting-list'),
                'return_value' => 'yes',
                'default' => 'yes',
            )
        );

        $this->add_control(
            'show_phone_option',
            array(
                'label' => __('Show Phone Search', 'nursery-waiting-list'),
                'type' => \Elementor\Controls_Manager::SWITCHER,
                'label_on' => __('Yes', 'nursery-waiting-list'),
                'label_off' => __('No', 'nursery-waiting-list'),
                'return_value' => 'yes',
                'default' => 'yes',
            )
        );

        $this->end_controls_section();

        // Container Style Section
        $this->start_controls_section(
            'container_style_section',
            array(
                'label' => __('Container', 'nursery-waiting-list'),
                'tab' => \Elementor\Controls_Manager::TAB_STYLE,
            )
        );

        $this->add_control(
            'container_background',
            array(
                'label' => __('Background Color', 'nursery-waiting-list'),
                'type' => \Elementor\Controls_Manager::COLOR,
                'selectors' => array(
                    '{{WRAPPER}} .nwl-lookup-form' => 'background-color: {{VALUE}};',
                ),
            )
        );

        $this->add_group_control(
            \Elementor\Group_Control_Border::get_type(),
            array(
                'name' => 'container_border',
                'label' => __('Border', 'nursery-waiting-list'),
                'selector' => '{{WRAPPER}} .nwl-lookup-form',
            )
        );

        $this->add_responsive_control(
            'container_border_radius',
            array(
                'label' => __('Border Radius', 'nursery-waiting-list'),
                'type' => \Elementor\Controls_Manager::DIMENSIONS,
                'size_units' => array('px', '%'),
                'selectors' => array(
                    '{{WRAPPER}} .nwl-lookup-form' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
                ),
            )
        );

        $this->add_group_control(
            \Elementor\Group_Control_Box_Shadow::get_type(),
            array(
                'name' => 'container_box_shadow',
                'label' => __('Box Shadow', 'nursery-waiting-list'),
                'selector' => '{{WRAPPER}} .nwl-lookup-form',
            )
        );

        $this->add_responsive_control(
            'container_padding',
            array(
                'label' => __('Padding', 'nursery-waiting-list'),
                'type' => \Elementor\Controls_Manager::DIMENSIONS,
                'size_units' => array('px', 'em', '%'),
                'selectors' => array(
                    '{{WRAPPER}} .nwl-lookup-form' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
                ),
            )
        );

        $this->add_responsive_control(
            'container_max_width',
            array(
                'label' => __('Max Width', 'nursery-waiting-list'),
                'type' => \Elementor\Controls_Manager::SLIDER,
                'size_units' => array('px', '%'),
                'range' => array(
                    'px' => array(
                        'min' => 200,
                        'max' => 1200,
                    ),
                    '%' => array(
                        'min' => 10,
                        'max' => 100,
                    ),
                ),
                'selectors' => array(
                    '{{WRAPPER}} .nwl-lookup-container' => 'max-width: {{SIZE}}{{UNIT}};',
                ),
            )
        );

        $this->end_controls_section();

        // Title Style Section
        $this->start_controls_section(
            'title_style_section',
            array(
                'label' => __('Title', 'nursery-waiting-list'),
                'tab' => \Elementor\Controls_Manager::TAB_STYLE,
            )
        );

        $this->add_control(
            'title_color',
            array(
                'label' => __('Color', 'nursery-waiting-list'),
                'type' => \Elementor\Controls_Manager::COLOR,
                'selectors' => array(
                    '{{WRAPPER}} .nwl-lookup-title' => 'color: {{VALUE}};',
                ),
            )
        );

        $this->add_group_control(
            \Elementor\Group_Control_Typography::get_type(),
            array(
                'name' => 'title_typography',
                'selector' => '{{WRAPPER}} .nwl-lookup-title',
            )
        );

        $this->add_responsive_control(
            'title_alignment',
            array(
                'label' => __('Alignment', 'nursery-waiting-list'),
                'type' => \Elementor\Controls_Manager::CHOOSE,
                'options' => array(
                    'left' => array(
                        'title' => __('Left', 'nursery-waiting-list'),
                        'icon' => 'eicon-text-align-left',
                    ),
                    'center' => array(
                        'title' => __('Center', 'nursery-waiting-list'),
                        'icon' => 'eicon-text-align-center',
                    ),
                    'right' => array(
                        'title' => __('Right', 'nursery-waiting-list'),
                        'icon' => 'eicon-text-align-right',
                    ),
                ),
                'selectors' => array(
                    '{{WRAPPER}} .nwl-lookup-header' => 'text-align: {{VALUE}};',
                ),
            )
        );

        $this->add_responsive_control(
            'title_margin',
            array(
                'label' => __('Margin', 'nursery-waiting-list'),
                'type' => \Elementor\Controls_Manager::DIMENSIONS,
                'size_units' => array('px', 'em'),
                'selectors' => array(
                    '{{WRAPPER}} .nwl-lookup-title' => 'margin: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
                ),
            )
        );

        $this->end_controls_section();

        // Description Style Section
        $this->start_controls_section(
            'description_style_section',
            array(
                'label' => __('Description', 'nursery-waiting-list'),
                'tab' => \Elementor\Controls_Manager::TAB_STYLE,
            )
        );

        $this->add_control(
            'description_color',
            array(
                'label' => __('Color', 'nursery-waiting-list'),
                'type' => \Elementor\Controls_Manager::COLOR,
                'selectors' => array(
                    '{{WRAPPER}} .nwl-lookup-description' => 'color: {{VALUE}};',
                ),
            )
        );

        $this->add_group_control(
            \Elementor\Group_Control_Typography::get_type(),
            array(
                'name' => 'description_typography',
                'selector' => '{{WRAPPER}} .nwl-lookup-description',
            )
        );

        $this->end_controls_section();

        // Toggle Buttons Style Section
        $this->start_controls_section(
            'toggle_style_section',
            array(
                'label' => __('Type Toggle Buttons', 'nursery-waiting-list'),
                'tab' => \Elementor\Controls_Manager::TAB_STYLE,
            )
        );

        $this->start_controls_tabs('toggle_tabs');

        $this->start_controls_tab(
            'toggle_normal_tab',
            array(
                'label' => __('Normal', 'nursery-waiting-list'),
            )
        );

        $this->add_control(
            'toggle_bg_color',
            array(
                'label' => __('Background Color', 'nursery-waiting-list'),
                'type' => \Elementor\Controls_Manager::COLOR,
                'selectors' => array(
                    '{{WRAPPER}} .nwl-type-btn' => 'background-color: {{VALUE}};',
                ),
            )
        );

        $this->add_control(
            'toggle_text_color',
            array(
                'label' => __('Text Color', 'nursery-waiting-list'),
                'type' => \Elementor\Controls_Manager::COLOR,
                'selectors' => array(
                    '{{WRAPPER}} .nwl-type-btn' => 'color: {{VALUE}};',
                ),
            )
        );

        $this->add_control(
            'toggle_border_color',
            array(
                'label' => __('Border Color', 'nursery-waiting-list'),
                'type' => \Elementor\Controls_Manager::COLOR,
                'selectors' => array(
                    '{{WRAPPER}} .nwl-type-btn' => 'border-color: {{VALUE}};',
                ),
            )
        );

        $this->end_controls_tab();

        $this->start_controls_tab(
            'toggle_active_tab',
            array(
                'label' => __('Active', 'nursery-waiting-list'),
            )
        );

        $this->add_control(
            'toggle_active_bg_color',
            array(
                'label' => __('Background Color', 'nursery-waiting-list'),
                'type' => \Elementor\Controls_Manager::COLOR,
                'selectors' => array(
                    '{{WRAPPER}} .nwl-type-btn.active' => 'background-color: {{VALUE}}; border-color: {{VALUE}};',
                ),
            )
        );

        $this->add_control(
            'toggle_active_text_color',
            array(
                'label' => __('Text Color', 'nursery-waiting-list'),
                'type' => \Elementor\Controls_Manager::COLOR,
                'selectors' => array(
                    '{{WRAPPER}} .nwl-type-btn.active' => 'color: {{VALUE}};',
                ),
            )
        );

        $this->end_controls_tab();

        $this->end_controls_tabs();

        $this->add_responsive_control(
            'toggle_border_radius',
            array(
                'label' => __('Border Radius', 'nursery-waiting-list'),
                'type' => \Elementor\Controls_Manager::DIMENSIONS,
                'size_units' => array('px', '%'),
                'selectors' => array(
                    '{{WRAPPER}} .nwl-type-btn' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
                ),
                'separator' => 'before',
            )
        );

        $this->end_controls_section();

        // Input Field Style Section
        $this->start_controls_section(
            'input_style_section',
            array(
                'label' => __('Input Field', 'nursery-waiting-list'),
                'tab' => \Elementor\Controls_Manager::TAB_STYLE,
            )
        );

        $this->add_control(
            'input_bg_color',
            array(
                'label' => __('Background Color', 'nursery-waiting-list'),
                'type' => \Elementor\Controls_Manager::COLOR,
                'selectors' => array(
                    '{{WRAPPER}} .nwl-input' => 'background-color: {{VALUE}};',
                ),
            )
        );

        $this->add_control(
            'input_text_color',
            array(
                'label' => __('Text Color', 'nursery-waiting-list'),
                'type' => \Elementor\Controls_Manager::COLOR,
                'selectors' => array(
                    '{{WRAPPER}} .nwl-input' => 'color: {{VALUE}};',
                ),
            )
        );

        $this->add_control(
            'input_placeholder_color',
            array(
                'label' => __('Placeholder Color', 'nursery-waiting-list'),
                'type' => \Elementor\Controls_Manager::COLOR,
                'selectors' => array(
                    '{{WRAPPER}} .nwl-input::placeholder' => 'color: {{VALUE}};',
                ),
            )
        );

        $this->add_group_control(
            \Elementor\Group_Control_Border::get_type(),
            array(
                'name' => 'input_border',
                'selector' => '{{WRAPPER}} .nwl-input',
            )
        );

        $this->add_control(
            'input_focus_border_color',
            array(
                'label' => __('Focus Border Color', 'nursery-waiting-list'),
                'type' => \Elementor\Controls_Manager::COLOR,
                'selectors' => array(
                    '{{WRAPPER}} .nwl-input:focus' => 'border-color: {{VALUE}}; box-shadow: 0 0 0 3px {{VALUE}}26;',
                ),
            )
        );

        $this->add_responsive_control(
            'input_border_radius',
            array(
                'label' => __('Border Radius', 'nursery-waiting-list'),
                'type' => \Elementor\Controls_Manager::DIMENSIONS,
                'size_units' => array('px', '%'),
                'selectors' => array(
                    '{{WRAPPER}} .nwl-input' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
                ),
            )
        );

        $this->add_responsive_control(
            'input_padding',
            array(
                'label' => __('Padding', 'nursery-waiting-list'),
                'type' => \Elementor\Controls_Manager::DIMENSIONS,
                'size_units' => array('px', 'em'),
                'selectors' => array(
                    '{{WRAPPER}} .nwl-input' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
                ),
            )
        );

        $this->add_group_control(
            \Elementor\Group_Control_Typography::get_type(),
            array(
                'name' => 'input_typography',
                'selector' => '{{WRAPPER}} .nwl-input',
            )
        );

        $this->end_controls_section();

        // Button Style Section
        $this->start_controls_section(
            'button_style_section',
            array(
                'label' => __('Submit Button', 'nursery-waiting-list'),
                'tab' => \Elementor\Controls_Manager::TAB_STYLE,
            )
        );

        $this->start_controls_tabs('button_tabs');

        $this->start_controls_tab(
            'button_normal_tab',
            array(
                'label' => __('Normal', 'nursery-waiting-list'),
            )
        );

        $this->add_control(
            'button_bg_color',
            array(
                'label' => __('Background Color', 'nursery-waiting-list'),
                'type' => \Elementor\Controls_Manager::COLOR,
                'selectors' => array(
                    '{{WRAPPER}} .nwl-submit-btn' => 'background: {{VALUE}};',
                ),
            )
        );

        $this->add_control(
            'button_text_color',
            array(
                'label' => __('Text Color', 'nursery-waiting-list'),
                'type' => \Elementor\Controls_Manager::COLOR,
                'selectors' => array(
                    '{{WRAPPER}} .nwl-submit-btn' => 'color: {{VALUE}};',
                ),
            )
        );

        $this->end_controls_tab();

        $this->start_controls_tab(
            'button_hover_tab',
            array(
                'label' => __('Hover', 'nursery-waiting-list'),
            )
        );

        $this->add_control(
            'button_hover_bg_color',
            array(
                'label' => __('Background Color', 'nursery-waiting-list'),
                'type' => \Elementor\Controls_Manager::COLOR,
                'selectors' => array(
                    '{{WRAPPER}} .nwl-submit-btn:hover' => 'background: {{VALUE}};',
                ),
            )
        );

        $this->add_control(
            'button_hover_text_color',
            array(
                'label' => __('Text Color', 'nursery-waiting-list'),
                'type' => \Elementor\Controls_Manager::COLOR,
                'selectors' => array(
                    '{{WRAPPER}} .nwl-submit-btn:hover' => 'color: {{VALUE}};',
                ),
            )
        );

        $this->end_controls_tab();

        $this->end_controls_tabs();

        $this->add_group_control(
            \Elementor\Group_Control_Typography::get_type(),
            array(
                'name' => 'button_typography',
                'selector' => '{{WRAPPER}} .nwl-submit-btn',
                'separator' => 'before',
            )
        );

        $this->add_responsive_control(
            'button_border_radius',
            array(
                'label' => __('Border Radius', 'nursery-waiting-list'),
                'type' => \Elementor\Controls_Manager::DIMENSIONS,
                'size_units' => array('px', '%'),
                'selectors' => array(
                    '{{WRAPPER}} .nwl-submit-btn' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
                ),
            )
        );

        $this->add_responsive_control(
            'button_padding',
            array(
                'label' => __('Padding', 'nursery-waiting-list'),
                'type' => \Elementor\Controls_Manager::DIMENSIONS,
                'size_units' => array('px', 'em'),
                'selectors' => array(
                    '{{WRAPPER}} .nwl-submit-btn' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
                ),
            )
        );

        $this->add_group_control(
            \Elementor\Group_Control_Box_Shadow::get_type(),
            array(
                'name' => 'button_box_shadow',
                'selector' => '{{WRAPPER}} .nwl-submit-btn',
            )
        );

        $this->end_controls_section();

        // Results Card Style Section
        $this->start_controls_section(
            'results_style_section',
            array(
                'label' => __('Results Cards', 'nursery-waiting-list'),
                'tab' => \Elementor\Controls_Manager::TAB_STYLE,
            )
        );

        $this->add_control(
            'results_bg_color',
            array(
                'label' => __('Background Color', 'nursery-waiting-list'),
                'type' => \Elementor\Controls_Manager::COLOR,
                'selectors' => array(
                    '{{WRAPPER}} .nwl-result-card' => 'background-color: {{VALUE}};',
                ),
            )
        );

        $this->add_group_control(
            \Elementor\Group_Control_Border::get_type(),
            array(
                'name' => 'results_border',
                'selector' => '{{WRAPPER}} .nwl-result-card',
            )
        );

        $this->add_responsive_control(
            'results_border_radius',
            array(
                'label' => __('Border Radius', 'nursery-waiting-list'),
                'type' => \Elementor\Controls_Manager::DIMENSIONS,
                'size_units' => array('px', '%'),
                'selectors' => array(
                    '{{WRAPPER}} .nwl-result-card' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
                ),
            )
        );

        $this->add_group_control(
            \Elementor\Group_Control_Box_Shadow::get_type(),
            array(
                'name' => 'results_box_shadow',
                'selector' => '{{WRAPPER}} .nwl-result-card',
            )
        );

        $this->add_control(
            'results_heading_color',
            array(
                'label' => __('Child Name Color', 'nursery-waiting-list'),
                'type' => \Elementor\Controls_Manager::COLOR,
                'selectors' => array(
                    '{{WRAPPER}} .nwl-child-name' => 'color: {{VALUE}};',
                ),
                'separator' => 'before',
            )
        );

        $this->add_control(
            'results_label_color',
            array(
                'label' => __('Label Color', 'nursery-waiting-list'),
                'type' => \Elementor\Controls_Manager::COLOR,
                'selectors' => array(
                    '{{WRAPPER}} .nwl-detail-label' => 'color: {{VALUE}};',
                ),
            )
        );

        $this->add_control(
            'results_value_color',
            array(
                'label' => __('Value Color', 'nursery-waiting-list'),
                'type' => \Elementor\Controls_Manager::COLOR,
                'selectors' => array(
                    '{{WRAPPER}} .nwl-detail-value' => 'color: {{VALUE}};',
                ),
            )
        );

        $this->end_controls_section();

        // Status Badge Style Section
        $this->start_controls_section(
            'status_badge_style_section',
            array(
                'label' => __('Status Badges', 'nursery-waiting-list'),
                'tab' => \Elementor\Controls_Manager::TAB_STYLE,
            )
        );

        $this->add_group_control(
            \Elementor\Group_Control_Typography::get_type(),
            array(
                'name' => 'badge_typography',
                'selector' => '{{WRAPPER}} .nwl-status-badge',
            )
        );

        $this->add_responsive_control(
            'badge_border_radius',
            array(
                'label' => __('Border Radius', 'nursery-waiting-list'),
                'type' => \Elementor\Controls_Manager::DIMENSIONS,
                'size_units' => array('px', '%'),
                'selectors' => array(
                    '{{WRAPPER}} .nwl-status-badge' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
                ),
            )
        );

        $this->add_responsive_control(
            'badge_padding',
            array(
                'label' => __('Padding', 'nursery-waiting-list'),
                'type' => \Elementor\Controls_Manager::DIMENSIONS,
                'size_units' => array('px', 'em'),
                'selectors' => array(
                    '{{WRAPPER}} .nwl-status-badge' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
                ),
            )
        );

        $this->end_controls_section();

        // Label Style Section
        $this->start_controls_section(
            'label_style_section',
            array(
                'label' => __('Labels', 'nursery-waiting-list'),
                'tab' => \Elementor\Controls_Manager::TAB_STYLE,
            )
        );

        $this->add_control(
            'label_color',
            array(
                'label' => __('Color', 'nursery-waiting-list'),
                'type' => \Elementor\Controls_Manager::COLOR,
                'selectors' => array(
                    '{{WRAPPER}} .nwl-label' => 'color: {{VALUE}};',
                ),
            )
        );

        $this->add_group_control(
            \Elementor\Group_Control_Typography::get_type(),
            array(
                'name' => 'label_typography',
                'selector' => '{{WRAPPER}} .nwl-label',
            )
        );

        $this->end_controls_section();
    }

    protected function render() {
        $settings = $this->get_settings_for_display();

        // Enqueue styles and scripts
        wp_enqueue_style('nwl-public');
        wp_enqueue_script('nwl-public');

        $show_email = $settings['show_email_option'] === 'yes';
        $show_phone = $settings['show_phone_option'] === 'yes';
        $default_type = $show_email ? 'email' : 'phone';
        ?>
        <div class="nwl-lookup-container nwl-elementor-widget">
            <div class="nwl-lookup-header">
                <?php if (!empty($settings['title'])) : ?>
                    <h2 class="nwl-lookup-title"><?php echo esc_html($settings['title']); ?></h2>
                <?php endif; ?>
                <?php if (!empty($settings['description'])) : ?>
                    <p class="nwl-lookup-description"><?php echo esc_html($settings['description']); ?></p>
                <?php endif; ?>
            </div>

            <form class="nwl-lookup-form" id="nwl-lookup-form">
                <?php if ($show_email && $show_phone) : ?>
                    <div class="nwl-form-group">
                        <label for="nwl-lookup-type" class="nwl-label"><?php esc_html_e('Search by:', 'nursery-waiting-list'); ?></label>
                        <div class="nwl-lookup-type-toggle">
                            <button type="button" class="nwl-type-btn active" data-type="email">
                                <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"></path>
                                    <polyline points="22,6 12,13 2,6"></polyline>
                                </svg>
                                <?php esc_html_e('Email', 'nursery-waiting-list'); ?>
                            </button>
                            <button type="button" class="nwl-type-btn" data-type="phone">
                                <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"></path>
                                </svg>
                                <?php esc_html_e('Phone', 'nursery-waiting-list'); ?>
                            </button>
                        </div>
                    </div>
                <?php endif; ?>

                <div class="nwl-form-group">
                    <label for="nwl-lookup-value" class="nwl-label" id="nwl-value-label">
                        <?php echo $show_email ? esc_html__('Email Address', 'nursery-waiting-list') : esc_html__('Phone Number', 'nursery-waiting-list'); ?>
                    </label>
                    <input type="text"
                           id="nwl-lookup-value"
                           name="lookup_value"
                           class="nwl-input"
                           placeholder="<?php echo $show_email ? esc_attr__('Enter your email address', 'nursery-waiting-list') : esc_attr__('Enter your phone number', 'nursery-waiting-list'); ?>"
                           required>
                </div>

                <input type="hidden" name="lookup_type" id="nwl-lookup-type" value="<?php echo esc_attr($default_type); ?>">
                <?php wp_nonce_field('nwl_parent_lookup', 'nwl_nonce'); ?>

                <button type="submit" class="nwl-submit-btn" id="nwl-submit-btn">
                    <span class="nwl-btn-text"><?php echo esc_html($settings['button_text']); ?></span>
                    <span class="nwl-btn-loading" style="display: none;">
                        <svg class="nwl-spinner" xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <circle cx="12" cy="12" r="10" stroke-opacity="0.25"></circle>
                            <path d="M12 2a10 10 0 0 1 10 10" stroke-linecap="round">
                                <animateTransform attributeName="transform" type="rotate" from="0 12 12" to="360 12 12" dur="1s" repeatCount="indefinite"/>
                            </path>
                        </svg>
                        <?php esc_html_e('Searching...', 'nursery-waiting-list'); ?>
                    </span>
                </button>
            </form>

            <div class="nwl-lookup-results" id="nwl-lookup-results" style="display: none;"></div>
        </div>
        <?php
    }

    protected function content_template() {
        ?>
        <#
        var showEmail = settings.show_email_option === 'yes';
        var showPhone = settings.show_phone_option === 'yes';
        #>
        <div class="nwl-lookup-container nwl-elementor-widget">
            <div class="nwl-lookup-header">
                <# if (settings.title) { #>
                    <h2 class="nwl-lookup-title">{{{ settings.title }}}</h2>
                <# } #>
                <# if (settings.description) { #>
                    <p class="nwl-lookup-description">{{{ settings.description }}}</p>
                <# } #>
            </div>

            <div class="nwl-lookup-form">
                <# if (showEmail && showPhone) { #>
                    <div class="nwl-form-group">
                        <label class="nwl-label"><?php esc_html_e('Search by:', 'nursery-waiting-list'); ?></label>
                        <div class="nwl-lookup-type-toggle">
                            <button type="button" class="nwl-type-btn active">
                                <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"></path>
                                    <polyline points="22,6 12,13 2,6"></polyline>
                                </svg>
                                <?php esc_html_e('Email', 'nursery-waiting-list'); ?>
                            </button>
                            <button type="button" class="nwl-type-btn">
                                <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"></path>
                                </svg>
                                <?php esc_html_e('Phone', 'nursery-waiting-list'); ?>
                            </button>
                        </div>
                    </div>
                <# } #>

                <div class="nwl-form-group">
                    <label class="nwl-label">
                        <# if (showEmail) { #>
                            <?php esc_html_e('Email Address', 'nursery-waiting-list'); ?>
                        <# } else { #>
                            <?php esc_html_e('Phone Number', 'nursery-waiting-list'); ?>
                        <# } #>
                    </label>
                    <input type="text" class="nwl-input" placeholder="<?php esc_attr_e('Enter your email address', 'nursery-waiting-list'); ?>">
                </div>

                <button type="button" class="nwl-submit-btn">
                    <span class="nwl-btn-text">{{{ settings.button_text }}}</span>
                </button>
            </div>
        </div>
        <?php
    }
}
