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

        // GDPR Settings
        register_setting('nwl_gdpr_settings', 'nwl_data_retention_days');
        register_setting('nwl_gdpr_settings', 'nwl_auto_delete_removed');
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
                <a href="<?php echo esc_url(add_query_arg('tab', 'email')); ?>"
                   class="nav-tab <?php echo $active_tab === 'email' ? 'nav-tab-active' : ''; ?>">
                    <?php esc_html_e('Email', 'nursery-waiting-list'); ?>
                </a>
                <a href="<?php echo esc_url(add_query_arg('tab', 'gdpr')); ?>"
                   class="nav-tab <?php echo $active_tab === 'gdpr' ? 'nav-tab-active' : ''; ?>">
                    <?php esc_html_e('GDPR & Privacy', 'nursery-waiting-list'); ?>
                </a>
                <a href="<?php echo esc_url(add_query_arg('tab', 'gravity-forms')); ?>"
                   class="nav-tab <?php echo $active_tab === 'gravity-forms' ? 'nav-tab-active' : ''; ?>">
                    <?php esc_html_e('Gravity Forms', 'nursery-waiting-list'); ?>
                </a>
            </nav>

            <div class="nwl-settings-content">
                <?php
                switch ($active_tab) {
                    case 'email':
                        self::render_email_settings();
                        break;
                    case 'gdpr':
                        self::render_gdpr_settings();
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
     * Render GDPR settings
     */
    private static function render_gdpr_settings() {
        if (isset($_POST['nwl_save_gdpr']) && wp_verify_nonce($_POST['nwl_gdpr_nonce'], 'nwl_save_gdpr')) {
            update_option('nwl_data_retention_days', absint($_POST['nwl_data_retention_days']));
            update_option('nwl_auto_delete_removed', isset($_POST['nwl_auto_delete_removed']) ? 1 : 0);
            
            echo '<div class="notice notice-success"><p>' . esc_html__('Settings saved.', 'nursery-waiting-list') . '</p></div>';
        }

        $gdpr_report = NWL_GDPR::get_instance()->get_retention_report();
        ?>
        <div class="nwl-gdpr-overview">
            <h3><?php esc_html_e('Data Overview', 'nursery-waiting-list'); ?></h3>
            <div class="nwl-gdpr-stats">
                <div class="nwl-gdpr-stat">
                    <span class="nwl-stat-number"><?php echo number_format_i18n($gdpr_report['total_entries']); ?></span>
                    <span class="nwl-stat-label"><?php esc_html_e('Total Entries', 'nursery-waiting-list'); ?></span>
                </div>
                <div class="nwl-gdpr-stat">
                    <span class="nwl-stat-number"><?php echo number_format_i18n($gdpr_report['deletion_requested']); ?></span>
                    <span class="nwl-stat-label"><?php esc_html_e('Pending Deletion', 'nursery-waiting-list'); ?></span>
                </div>
                <div class="nwl-gdpr-stat">
                    <span class="nwl-stat-number"><?php echo number_format_i18n($gdpr_report['past_retention']); ?></span>
                    <span class="nwl-stat-label"><?php esc_html_e('Past Retention Period', 'nursery-waiting-list'); ?></span>
                </div>
            </div>
        </div>

        <form method="post">
            <?php wp_nonce_field('nwl_save_gdpr', 'nwl_gdpr_nonce'); ?>
            
            <table class="form-table">
                <tr>
                    <th scope="row">
                        <label for="nwl_data_retention_days"><?php esc_html_e('Data Retention Period', 'nursery-waiting-list'); ?></label>
                    </th>
                    <td>
                        <input type="number" id="nwl_data_retention_days" name="nwl_data_retention_days" 
                               value="<?php echo esc_attr(get_option('nwl_data_retention_days', 365)); ?>" 
                               class="small-text" min="0">
                        <?php esc_html_e('days', 'nursery-waiting-list'); ?>
                        <p class="description">
                            <?php esc_html_e('How long to keep data for removed/declined entries before deletion. Set to 0 to disable automatic deletion.', 'nursery-waiting-list'); ?>
                        </p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><?php esc_html_e('Automatic Deletion', 'nursery-waiting-list'); ?></th>
                    <td>
                        <label>
                            <input type="checkbox" name="nwl_auto_delete_removed" value="1" 
                                   <?php checked(get_option('nwl_auto_delete_removed', 0)); ?>>
                            <?php esc_html_e('Automatically delete entries past the retention period', 'nursery-waiting-list'); ?>
                        </label>
                        <p class="description">
                            <?php esc_html_e('When enabled, entries marked as removed or declined will be permanently deleted after the retention period.', 'nursery-waiting-list'); ?>
                        </p>
                    </td>
                </tr>
            </table>

            <p class="submit">
                <input type="submit" name="nwl_save_gdpr" class="button button-primary" value="<?php esc_attr_e('Save Settings', 'nursery-waiting-list'); ?>">
            </p>
        </form>

        <hr>

        <h3><?php esc_html_e('GDPR Compliance Features', 'nursery-waiting-list'); ?></h3>
        <ul>
            <li>✓ <?php esc_html_e('Integrated with WordPress Personal Data Exporter', 'nursery-waiting-list'); ?></li>
            <li>✓ <?php esc_html_e('Integrated with WordPress Personal Data Eraser', 'nursery-waiting-list'); ?></li>
            <li>✓ <?php esc_html_e('Privacy policy content suggestion added', 'nursery-waiting-list'); ?></li>
            <li>✓ <?php esc_html_e('Consent tracking for entries', 'nursery-waiting-list'); ?></li>
            <li>✓ <?php esc_html_e('Data deletion request workflow', 'nursery-waiting-list'); ?></li>
        </ul>

        <p>
            <a href="<?php echo esc_url(admin_url('options-privacy.php')); ?>" class="button">
                <?php esc_html_e('Manage Privacy Tools', 'nursery-waiting-list'); ?>
            </a>
        </p>
        <?php
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
