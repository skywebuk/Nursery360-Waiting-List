<?php
/**
 * Admin email templates management
 */

if (!defined('ABSPATH')) {
    exit;
}

class NWL_Admin_Templates {

    private static $instance = null;

    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        add_action('wp_ajax_nwl_save_template', array($this, 'ajax_save_template'));
        add_action('wp_ajax_nwl_delete_template', array($this, 'ajax_delete_template'));
        add_action('wp_ajax_nwl_preview_template', array($this, 'ajax_preview_template'));
    }

    /**
     * Render templates page
     */
    public static function render_page() {
        if (!current_user_can('nwl_manage_settings')) {
            wp_die(__('You do not have permission to access this page.', 'nursery-waiting-list'));
        }

        $email_handler = NWL_Email::get_instance();
        $templates = $email_handler->get_templates();
        $editing = isset($_GET['edit']) ? absint($_GET['edit']) : 0;
        $creating = isset($_GET['action']) && $_GET['action'] === 'new';

        $current_template = null;
        if ($editing) {
            foreach ($templates as $t) {
                if ($t->id == $editing) {
                    $current_template = $t;
                    break;
                }
            }
        }
        ?>
        <div class="wrap nwl-admin-wrap">
            <h1 class="wp-heading-inline"><?php esc_html_e('Email Templates', 'nursery-waiting-list'); ?></h1>
            <a href="<?php echo esc_url(add_query_arg('action', 'new')); ?>" class="page-title-action">
                <?php esc_html_e('Add New', 'nursery-waiting-list'); ?>
            </a>
            <hr class="wp-header-end">

            <div class="nwl-templates-container">
                <!-- Templates List -->
                <div class="nwl-templates-list">
                    <h3><?php esc_html_e('Available Templates', 'nursery-waiting-list'); ?></h3>
                    
                    <ul class="nwl-template-items">
                        <?php foreach ($templates as $template) : ?>
                            <li class="nwl-template-item <?php echo ($editing == $template->id) ? 'active' : ''; ?>">
                                <a href="<?php echo esc_url(add_query_arg('edit', $template->id)); ?>">
                                    <strong><?php echo esc_html($template->template_name); ?></strong>
                                    <?php if ($template->is_system) : ?>
                                        <span class="nwl-badge nwl-badge-info"><?php esc_html_e('System', 'nursery-waiting-list'); ?></span>
                                    <?php endif; ?>
                                    <?php if (!$template->is_active) : ?>
                                        <span class="nwl-badge nwl-badge-warning"><?php esc_html_e('Inactive', 'nursery-waiting-list'); ?></span>
                                    <?php endif; ?>
                                    <br>
                                    <small><?php echo esc_html($template->template_key); ?></small>
                                </a>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>

                <!-- Template Editor -->
                <div class="nwl-template-editor">
                    <?php if ($editing && $current_template) : ?>
                        <h3><?php printf(esc_html__('Edit: %s', 'nursery-waiting-list'), esc_html($current_template->template_name)); ?></h3>
                        
                        <form id="nwl-template-form" method="post">
                            <?php wp_nonce_field('nwl_save_template', 'nwl_template_nonce'); ?>
                            <input type="hidden" name="template_id" value="<?php echo esc_attr($current_template->id); ?>">
                            
                            <table class="form-table">
                                <tr>
                                    <th scope="row">
                                        <label for="template_name"><?php esc_html_e('Template Name', 'nursery-waiting-list'); ?></label>
                                    </th>
                                    <td>
                                        <input type="text" id="template_name" name="template_name" 
                                               value="<?php echo esc_attr($current_template->template_name); ?>" 
                                               class="regular-text" required>
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row">
                                        <label for="subject"><?php esc_html_e('Email Subject', 'nursery-waiting-list'); ?></label>
                                    </th>
                                    <td>
                                        <input type="text" id="subject" name="subject" 
                                               value="<?php echo esc_attr($current_template->subject); ?>" 
                                               class="large-text" required>
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row">
                                        <label for="body"><?php esc_html_e('Email Body', 'nursery-waiting-list'); ?></label>
                                    </th>
                                    <td>
                                        <?php 
                                        wp_editor($current_template->body, 'body', array(
                                            'textarea_name' => 'body',
                                            'textarea_rows' => 20,
                                            'media_buttons' => false,
                                            'teeny' => false,
                                            'quicktags' => true,
                                        )); 
                                        ?>
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row"><?php esc_html_e('Status', 'nursery-waiting-list'); ?></th>
                                    <td>
                                        <label>
                                            <input type="checkbox" name="is_active" value="1" <?php checked($current_template->is_active); ?>>
                                            <?php esc_html_e('Active', 'nursery-waiting-list'); ?>
                                        </label>
                                    </td>
                                </tr>
                            </table>

                            <p class="submit">
                                <button type="submit" class="button button-primary"><?php esc_html_e('Save Template', 'nursery-waiting-list'); ?></button>
                                <button type="button" class="button nwl-preview-template"><?php esc_html_e('Preview', 'nursery-waiting-list'); ?></button>
                                <?php if (!$current_template->is_system) : ?>
                                    <button type="button" class="button button-link-delete nwl-delete-template" data-id="<?php echo esc_attr($current_template->id); ?>">
                                        <?php esc_html_e('Delete Template', 'nursery-waiting-list'); ?>
                                    </button>
                                <?php endif; ?>
                            </p>
                        </form>

                    <?php elseif ($creating) : ?>
                        <h3><?php esc_html_e('Create New Template', 'nursery-waiting-list'); ?></h3>
                        
                        <form id="nwl-template-form" method="post">
                            <?php wp_nonce_field('nwl_save_template', 'nwl_template_nonce'); ?>
                            
                            <table class="form-table">
                                <tr>
                                    <th scope="row">
                                        <label for="template_key"><?php esc_html_e('Template Key', 'nursery-waiting-list'); ?></label>
                                    </th>
                                    <td>
                                        <input type="text" id="template_key" name="template_key" class="regular-text" required
                                               pattern="[a-z0-9_]+" placeholder="<?php esc_attr_e('e.g. custom_reminder', 'nursery-waiting-list'); ?>">
                                        <p class="description"><?php esc_html_e('Lowercase letters, numbers, and underscores only.', 'nursery-waiting-list'); ?></p>
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row">
                                        <label for="template_name"><?php esc_html_e('Template Name', 'nursery-waiting-list'); ?></label>
                                    </th>
                                    <td>
                                        <input type="text" id="template_name" name="template_name" class="regular-text" required>
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row">
                                        <label for="subject"><?php esc_html_e('Email Subject', 'nursery-waiting-list'); ?></label>
                                    </th>
                                    <td>
                                        <input type="text" id="subject" name="subject" class="large-text" required>
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row">
                                        <label for="body"><?php esc_html_e('Email Body', 'nursery-waiting-list'); ?></label>
                                    </th>
                                    <td>
                                        <?php 
                                        wp_editor('', 'body', array(
                                            'textarea_name' => 'body',
                                            'textarea_rows' => 20,
                                            'media_buttons' => false,
                                        )); 
                                        ?>
                                    </td>
                                </tr>
                            </table>

                            <p class="submit">
                                <button type="submit" class="button button-primary"><?php esc_html_e('Create Template', 'nursery-waiting-list'); ?></button>
                                <a href="<?php echo esc_url(NWL_Admin::get_page_url('nwl-templates')); ?>" class="button"><?php esc_html_e('Cancel', 'nursery-waiting-list'); ?></a>
                            </p>
                        </form>

                    <?php else : ?>
                        <div class="nwl-no-template-selected">
                            <p><?php esc_html_e('Select a template from the list to edit, or create a new one.', 'nursery-waiting-list'); ?></p>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Variables Reference -->
                <div class="nwl-template-variables">
                    <h3><?php esc_html_e('Available Variables', 'nursery-waiting-list'); ?></h3>
                    
                    <?php 
                    $variables = $email_handler->get_template_variables();
                    foreach ($variables as $group_name => $group_vars) : 
                    ?>
                        <h4><?php echo esc_html($group_name); ?></h4>
                        <ul>
                            <?php foreach ($group_vars as $var => $desc) : ?>
                                <li>
                                    <code class="nwl-copy-var" title="<?php esc_attr_e('Click to copy', 'nursery-waiting-list'); ?>"><?php echo esc_html($var); ?></code>
                                    <span class="nwl-var-desc"><?php echo esc_html($desc); ?></span>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endforeach; ?>

                    <h4><?php esc_html_e('Conditional Sections', 'nursery-waiting-list'); ?></h4>
                    <p class="description"><?php esc_html_e('Use these to show content only when a variable has a value:', 'nursery-waiting-list'); ?></p>
                    <ul>
                        <li><code>{{#variable}}Content{{/variable}}</code> - <?php esc_html_e('Show if variable has value', 'nursery-waiting-list'); ?></li>
                        <li><code>{{^variable}}Content{{/variable}}</code> - <?php esc_html_e('Show if variable is empty', 'nursery-waiting-list'); ?></li>
                    </ul>
                </div>
            </div>
        </div>

        <!-- Preview Modal -->
        <div id="nwl-preview-modal" class="nwl-modal" style="display: none;">
            <div class="nwl-modal-content nwl-modal-large">
                <span class="nwl-modal-close">&times;</span>
                <h2><?php esc_html_e('Email Preview', 'nursery-waiting-list'); ?></h2>
                <div class="nwl-preview-subject"></div>
                <iframe class="nwl-preview-frame" frameborder="0"></iframe>
            </div>
        </div>
        <?php
    }

    /**
     * AJAX: Save template
     */
    public function ajax_save_template() {
        check_ajax_referer('nwl_save_template', 'nwl_template_nonce');

        if (!current_user_can('nwl_manage_settings')) {
            wp_send_json_error(array('message' => __('Permission denied.', 'nursery-waiting-list')));
        }

        $email_handler = NWL_Email::get_instance();
        $template_id = isset($_POST['template_id']) ? absint($_POST['template_id']) : 0;

        $data = array(
            'template_name' => sanitize_text_field($_POST['template_name']),
            'subject' => sanitize_text_field($_POST['subject']),
            'body' => wp_kses_post($_POST['body']),
            'is_active' => isset($_POST['is_active']) ? 1 : 0,
        );

        if ($template_id) {
            // Update existing
            $result = $email_handler->update_template($template_id, $data);
            $message = __('Template updated successfully.', 'nursery-waiting-list');
        } else {
            // Create new
            $data['template_key'] = sanitize_key($_POST['template_key']);
            $result = $email_handler->create_template($data);
            $template_id = $result;
            $message = __('Template created successfully.', 'nursery-waiting-list');
        }

        if ($result === false) {
            wp_send_json_error(array('message' => __('Failed to save template.', 'nursery-waiting-list')));
        }

        wp_send_json_success(array(
            'message' => $message,
            'redirect' => NWL_Admin::get_page_url('nwl-templates', array('edit' => $template_id)),
        ));
    }

    /**
     * AJAX: Delete template
     */
    public function ajax_delete_template() {
        check_ajax_referer('nwl_admin', 'nonce');

        if (!current_user_can('nwl_manage_settings')) {
            wp_send_json_error(array('message' => __('Permission denied.', 'nursery-waiting-list')));
        }

        $template_id = absint($_POST['template_id']);
        $result = NWL_Email::get_instance()->delete_template($template_id);

        if (is_wp_error($result)) {
            wp_send_json_error(array('message' => $result->get_error_message()));
        }

        wp_send_json_success(array(
            'message' => __('Template deleted.', 'nursery-waiting-list'),
            'redirect' => NWL_Admin::get_page_url('nwl-templates'),
        ));
    }

    /**
     * AJAX: Preview template
     */
    public function ajax_preview_template() {
        check_ajax_referer('nwl_admin', 'nonce');

        if (!current_user_can('nwl_manage_settings')) {
            wp_send_json_error(array('message' => __('Permission denied.', 'nursery-waiting-list')));
        }

        $subject = sanitize_text_field($_POST['subject']);
        $body = wp_kses_post($_POST['body']);

        // Create sample entry for preview
        $sample_entry = (object) array(
            'id' => 1,
            'waiting_list_number' => 'WL2024001',
            'child_first_name' => 'Emma',
            'child_last_name' => 'Johnson',
            'child_dob' => '2022-03-15',
            'parent_first_name' => 'Sarah',
            'parent_last_name' => 'Johnson',
            'parent_email' => 'sarah.johnson@example.com',
            'parent_phone' => '01onal234 567890',
            'room_requested' => 'toddler',
            'age_group' => '12-24m',
            'preferred_start_date' => date('Y-m-d', strtotime('+2 months')),
            'status' => 'offered',
            'public_notes' => 'We look forward to welcoming Emma to our nursery family.',
            'offer_deadline' => date('Y-m-d', strtotime('+7 days')),
            'created_at' => date('Y-m-d H:i:s', strtotime('-1 month')),
        );

        $email_handler = NWL_Email::get_instance();
        $parsed_subject = $email_handler->parse_template($subject, $sample_entry);
        $parsed_body = $email_handler->parse_template($body, $sample_entry);

        wp_send_json_success(array(
            'subject' => $parsed_subject,
            'body' => $parsed_body,
        ));
    }
}
