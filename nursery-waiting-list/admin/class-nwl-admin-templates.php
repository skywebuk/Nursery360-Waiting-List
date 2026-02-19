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
                        <?php
                        $current_attachments = !empty($current_template->attachments) ? json_decode($current_template->attachments, true) : array();
                        if (!is_array($current_attachments)) $current_attachments = array();
                        ?>
                        <form id="nwl-template-form" method="post" class="nwl-template-form-improved">
                            <?php wp_nonce_field('nwl_save_template', 'nwl_template_nonce'); ?>
                            <input type="hidden" name="template_id" value="<?php echo esc_attr($current_template->id); ?>">

                            <!-- Template Header -->
                            <div class="nwl-template-header">
                                <div class="nwl-template-title-section">
                                    <input type="text" id="template_name" name="template_name"
                                           value="<?php echo esc_attr($current_template->template_name); ?>"
                                           class="nwl-template-title-input" required
                                           placeholder="<?php esc_attr_e('Template Name', 'nursery-waiting-list'); ?>">
                                    <?php if ($current_template->is_system) : ?>
                                        <span class="nwl-badge nwl-badge-info"><?php esc_html_e('System', 'nursery-waiting-list'); ?></span>
                                    <?php endif; ?>
                                </div>
                                <div class="nwl-template-actions-top">
                                    <label class="nwl-toggle-switch">
                                        <input type="checkbox" name="is_active" value="1" <?php checked($current_template->is_active); ?>>
                                        <span class="nwl-toggle-slider"></span>
                                        <span class="nwl-toggle-label"><?php esc_html_e('Active', 'nursery-waiting-list'); ?></span>
                                    </label>
                                </div>
                            </div>

                            <!-- Settings Row -->
                            <div class="nwl-template-settings-row">
                                <div class="nwl-template-setting">
                                    <label for="status_trigger"><?php esc_html_e('Auto-send when status changes to:', 'nursery-waiting-list'); ?></label>
                                    <select id="status_trigger" name="status_trigger">
                                        <option value=""><?php esc_html_e('— Manual send only —', 'nursery-waiting-list'); ?></option>
                                        <?php foreach (NWL_Database::get_statuses() as $key => $label) : ?>
                                            <option value="<?php echo esc_attr($key); ?>" <?php selected(isset($current_template->status_trigger) ? $current_template->status_trigger : '', $key); ?>>
                                                <?php echo esc_html($label); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>

                            <!-- Subject Line -->
                            <div class="nwl-template-subject-section">
                                <label for="subject"><?php esc_html_e('Subject Line', 'nursery-waiting-list'); ?></label>
                                <div class="nwl-subject-input-wrap">
                                    <input type="text" id="subject" name="subject"
                                           value="<?php echo esc_attr($current_template->subject); ?>"
                                           class="nwl-subject-input" required
                                           placeholder="<?php esc_attr_e('Enter email subject...', 'nursery-waiting-list'); ?>">
                                    <button type="button" class="button nwl-insert-var-btn" data-target="subject">
                                        <?php esc_html_e('+ Variable', 'nursery-waiting-list'); ?>
                                    </button>
                                </div>
                            </div>

                            <!-- Email Body Editor -->
                            <div class="nwl-template-body-section">
                                <div class="nwl-body-header">
                                    <label for="body"><?php esc_html_e('Email Content', 'nursery-waiting-list'); ?></label>
                                    <div class="nwl-body-tools">
                                        <button type="button" class="button nwl-insert-var-btn" data-target="body">
                                            <?php esc_html_e('+ Insert Variable', 'nursery-waiting-list'); ?>
                                        </button>
                                    </div>
                                </div>
                                <?php
                                wp_editor($current_template->body, 'body', array(
                                    'textarea_name' => 'body',
                                    'textarea_rows' => 25,
                                    'media_buttons' => true,
                                    'teeny' => false,
                                    'quicktags' => true,
                                    'tinymce' => array(
                                        'toolbar1' => 'formatselect,bold,italic,underline,|,bullist,numlist,|,link,unlink,|,alignleft,aligncenter,alignright,|,forecolor,backcolor,|,removeformat,|,fullscreen',
                                        'toolbar2' => '',
                                    ),
                                ));
                                ?>
                            </div>

                            <!-- Attachments Section -->
                            <div class="nwl-template-attachments-section">
                                <h4><?php esc_html_e('Document Attachments', 'nursery-waiting-list'); ?></h4>
                                <p class="description"><?php esc_html_e('Attach documents that will be sent with emails using this template.', 'nursery-waiting-list'); ?></p>
                                <div class="nwl-attachments-list" id="nwl-attachments-list">
                                    <?php if (!empty($current_attachments)) : ?>
                                        <?php foreach ($current_attachments as $attachment_id) :
                                            $attachment_url = wp_get_attachment_url($attachment_id);
                                            $attachment_name = basename(get_attached_file($attachment_id));
                                            if ($attachment_url) :
                                        ?>
                                            <div class="nwl-attachment-item" data-id="<?php echo esc_attr($attachment_id); ?>">
                                                <span class="dashicons dashicons-media-document"></span>
                                                <span class="nwl-attachment-name"><?php echo esc_html($attachment_name); ?></span>
                                                <button type="button" class="nwl-remove-attachment" title="<?php esc_attr_e('Remove', 'nursery-waiting-list'); ?>">&times;</button>
                                                <input type="hidden" name="attachments[]" value="<?php echo esc_attr($attachment_id); ?>">
                                            </div>
                                        <?php endif; endforeach; ?>
                                    <?php endif; ?>
                                </div>
                                <button type="button" class="button nwl-add-attachment" id="nwl-add-attachment">
                                    <span class="dashicons dashicons-paperclip"></span>
                                    <?php esc_html_e('Add Document', 'nursery-waiting-list'); ?>
                                </button>
                            </div>

                            <!-- Action Buttons -->
                            <div class="nwl-template-form-actions">
                                <div class="nwl-actions-left">
                                    <?php if (!$current_template->is_system) : ?>
                                        <button type="button" class="button button-link-delete nwl-delete-template" data-id="<?php echo esc_attr($current_template->id); ?>">
                                            <?php esc_html_e('Delete Template', 'nursery-waiting-list'); ?>
                                        </button>
                                    <?php endif; ?>
                                </div>
                                <div class="nwl-actions-right">
                                    <button type="button" class="button nwl-preview-template"><?php esc_html_e('Preview', 'nursery-waiting-list'); ?></button>
                                    <button type="submit" class="button button-primary button-large"><?php esc_html_e('Save Template', 'nursery-waiting-list'); ?></button>
                                </div>
                            </div>
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

        <script>
        jQuery(document).ready(function($) {
            // Template form submission
            $('#nwl-template-form').on('submit', function(e) {
                e.preventDefault();

                var $form = $(this);
                var $btn = $form.find('[type="submit"]');
                var originalText = $btn.text();

                $btn.prop('disabled', true).text('<?php esc_html_e('Saving...', 'nursery-waiting-list'); ?>');

                // Sync TinyMCE content to textarea before serializing
                if (typeof tinyMCE !== 'undefined' && tinyMCE.get('body')) {
                    tinyMCE.triggerSave();
                }

                var formData = $form.serialize() + '&action=nwl_save_template';

                $.post(nwlAdmin.ajaxUrl, formData, function(response) {
                    if (response.success) {
                        // For new templates, redirect to edit page
                        if (!$form.find('input[name="template_id"]').val() && response.data.redirect) {
                            window.location.href = response.data.redirect;
                            return;
                        }
                        // For existing templates, show inline success and stay on page
                        $btn.text('<?php esc_html_e('Saved!', 'nursery-waiting-list'); ?>');
                        // Remove any existing notices
                        $form.find('.nwl-save-notice').remove();
                        $form.prepend('<div class="notice notice-success nwl-save-notice" style="margin:0 0 15px;"><p>' + (response.data.message || '<?php esc_html_e('Template saved.', 'nursery-waiting-list'); ?>') + '</p></div>');
                        setTimeout(function() {
                            $btn.prop('disabled', false).text(originalText);
                            $form.find('.nwl-save-notice').fadeOut(300, function() { $(this).remove(); });
                        }, 2000);
                    } else {
                        alert(response.data.message || '<?php esc_html_e('Error saving template.', 'nursery-waiting-list'); ?>');
                        $btn.prop('disabled', false).text(originalText);
                    }
                }).fail(function() {
                    alert('<?php esc_html_e('Error saving template.', 'nursery-waiting-list'); ?>');
                    $btn.prop('disabled', false).text(originalText);
                });
            });

            // Preview template
            $('.nwl-preview-template').on('click', function() {
                var subject = $('#subject').val();
                var body = '';

                // Get editor content
                if (typeof tinyMCE !== 'undefined' && tinyMCE.get('body')) {
                    body = tinyMCE.get('body').getContent();
                } else {
                    body = $('#body').val();
                }

                $.post(nwlAdmin.ajaxUrl, {
                    action: 'nwl_preview_template',
                    nonce: nwlAdmin.nonce,
                    subject: subject,
                    body: body
                }, function(response) {
                    if (response.success) {
                        $('.nwl-preview-subject').text('<?php esc_html_e('Subject:', 'nursery-waiting-list'); ?> ' + response.data.subject);

                        var iframe = $('.nwl-preview-frame')[0];
                        var iframeDoc = iframe.contentDocument || iframe.contentWindow.document;
                        iframeDoc.open();
                        iframeDoc.write(response.data.body);
                        iframeDoc.close();

                        $('#nwl-preview-modal').fadeIn();
                    } else {
                        alert(response.data.message || '<?php esc_html_e('Error loading preview.', 'nursery-waiting-list'); ?>');
                    }
                });
            });

            // Delete template
            $('.nwl-delete-template').on('click', function() {
                if (!confirm('<?php esc_html_e('Are you sure you want to delete this template?', 'nursery-waiting-list'); ?>')) {
                    return;
                }

                var templateId = $(this).data('id');

                $.post(nwlAdmin.ajaxUrl, {
                    action: 'nwl_delete_template',
                    nonce: nwlAdmin.nonce,
                    template_id: templateId
                }, function(response) {
                    if (response.success && response.data.redirect) {
                        window.location.href = response.data.redirect;
                    } else {
                        alert(response.data.message || '<?php esc_html_e('Error deleting template.', 'nursery-waiting-list'); ?>');
                    }
                });
            });

            // Close modal
            $('.nwl-modal-close, .nwl-modal').on('click', function(e) {
                if (e.target === this) {
                    $('.nwl-modal').fadeOut();
                }
            });

            // Copy variable on click
            $('.nwl-copy-var').on('click', function() {
                var text = $(this).text();
                navigator.clipboard.writeText(text);

                var $this = $(this);
                $this.addClass('copied');
                setTimeout(function() {
                    $this.removeClass('copied');
                }, 1000);
            });

            // Insert variable into field
            $('.nwl-insert-var-btn').on('click', function() {
                var target = $(this).data('target');
                var variables = [
                    '{{child_name}}',
                    '{{parent_name}}',
                    '{{waiting_list_number}}',
                    '{{nursery_name}}',
                    '{{status}}',
                    '{{date_added}}'
                ];

                var selected = prompt('<?php esc_html_e('Enter variable name or choose from: child_name, parent_name, waiting_list_number, nursery_name, status, date_added', 'nursery-waiting-list'); ?>', 'child_name');
                if (selected) {
                    var varText = '{{' + selected.replace(/[{}]/g, '') + '}}';
                    if (target === 'subject') {
                        var $input = $('#subject');
                        var cursorPos = $input[0].selectionStart;
                        var v = $input.val();
                        $input.val(v.substring(0, cursorPos) + varText + v.substring(cursorPos));
                    } else if (target === 'body') {
                        if (typeof tinyMCE !== 'undefined' && tinyMCE.get('body')) {
                            tinyMCE.get('body').execCommand('mceInsertContent', false, varText);
                        } else {
                            var $textarea = $('#body');
                            var cursorPos = $textarea[0].selectionStart;
                            var v = $textarea.val();
                            $textarea.val(v.substring(0, cursorPos) + varText + v.substring(cursorPos));
                        }
                    }
                }
            });

            // Attachment upload
            var attachmentFrame;
            $('#nwl-add-attachment').on('click', function(e) {
                e.preventDefault();

                if (attachmentFrame) {
                    attachmentFrame.open();
                    return;
                }

                attachmentFrame = wp.media({
                    title: '<?php esc_html_e('Select Document to Attach', 'nursery-waiting-list'); ?>',
                    button: { text: '<?php esc_html_e('Attach Document', 'nursery-waiting-list'); ?>' },
                    multiple: true
                });

                attachmentFrame.on('select', function() {
                    var attachments = attachmentFrame.state().get('selection').toJSON();
                    attachments.forEach(function(attachment) {
                        if ($('#nwl-attachments-list').find('[data-id="' + attachment.id + '"]').length === 0) {
                            var html = '<div class="nwl-attachment-item" data-id="' + attachment.id + '">' +
                                '<span class="dashicons dashicons-media-document"></span>' +
                                '<span class="nwl-attachment-name">' + attachment.filename + '</span>' +
                                '<button type="button" class="nwl-remove-attachment" title="<?php esc_attr_e('Remove', 'nursery-waiting-list'); ?>">&times;</button>' +
                                '<input type="hidden" name="attachments[]" value="' + attachment.id + '">' +
                                '</div>';
                            $('#nwl-attachments-list').append(html);
                        }
                    });
                });

                attachmentFrame.open();
            });

            // Remove attachment
            $(document).on('click', '.nwl-remove-attachment', function() {
                $(this).closest('.nwl-attachment-item').remove();
            });
        });
        </script>
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

        // Process attachments
        $attachments = array();
        if (!empty($_POST['attachments']) && is_array($_POST['attachments'])) {
            $attachments = array_map('absint', $_POST['attachments']);
            $attachments = array_filter($attachments);
        }

        $data = array(
            'template_name' => sanitize_text_field($_POST['template_name']),
            'subject' => sanitize_text_field($_POST['subject']),
            'body' => wp_kses_post($_POST['body']),
            'status_trigger' => isset($_POST['status_trigger']) ? sanitize_text_field($_POST['status_trigger']) : '',
            'attachments' => wp_json_encode($attachments),
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
            'parent_phone' => '01onal 234 567890',
            'parent_mobile' => '07700 900000',
            'age_group' => '2-3 years',
            'year_group' => '',
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
