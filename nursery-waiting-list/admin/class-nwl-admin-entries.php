<?php
/**
 * Admin entries management
 */

if (!defined('ABSPATH')) {
    exit;
}

class NWL_Admin_Entries {

    private static $instance = null;

    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        add_action('wp_ajax_nwl_update_status', array($this, 'ajax_update_status'));
        add_action('wp_ajax_nwl_add_note', array($this, 'ajax_add_note'));
        add_action('wp_ajax_nwl_delete_entry', array($this, 'ajax_delete_entry'));
        add_action('wp_ajax_nwl_bulk_action', array($this, 'ajax_bulk_action'));
        add_action('wp_ajax_nwl_save_entry', array($this, 'ajax_save_entry'));
        add_action('wp_ajax_nwl_cancel_deletion', array($this, 'ajax_cancel_deletion'));
    }

    /**
     * Render entries page
     */
    public static function render_page() {
        // Check for edit mode
        if (isset($_GET['action']) && $_GET['action'] === 'edit' && isset($_GET['id'])) {
            self::render_edit_page(absint($_GET['id']));
            return;
        }

        // Get filters
        $filters = array(
            'status' => isset($_GET['status']) ? sanitize_text_field($_GET['status']) : '',
            'age_group' => isset($_GET['age_group']) ? sanitize_text_field($_GET['age_group']) : '',
            'date_from' => isset($_GET['date_from']) ? sanitize_text_field($_GET['date_from']) : '',
            'date_to' => isset($_GET['date_to']) ? sanitize_text_field($_GET['date_to']) : '',
            'search' => isset($_GET['s']) ? sanitize_text_field($_GET['s']) : '',
            'page' => isset($_GET['paged']) ? absint($_GET['paged']) : 1,
            'per_page' => 20,
        );

        $result = NWL_Entry::get_instance()->get_entries($filters);
        $entries = $result['entries'];
        $total = $result['total'];
        $pages = $result['pages'];

        // Get stats
        $stats = NWL_Stats::get_instance();
        $status_counts = $stats->get_counts_by_status();
        
        ?>
        <div class="wrap nwl-admin-wrap">
            <h1 class="wp-heading-inline"><?php esc_html_e('Waiting List Entries', 'nursery-waiting-list'); ?></h1>
            <a href="<?php echo esc_url(NWL_Admin::get_page_url('nwl-add-entry')); ?>" class="page-title-action">
                <?php esc_html_e('Add New', 'nursery-waiting-list'); ?>
            </a>
            <hr class="wp-header-end">

            <!-- Quick Stats -->
            <div class="nwl-quick-stats">
                <?php foreach ($status_counts as $status => $data) : ?>
                    <?php if ($data['count'] > 0 || in_array($status, array('pending', 'offered', 'accepted'))) : ?>
                        <a href="<?php echo esc_url(add_query_arg('status', $status)); ?>" 
                           class="nwl-stat-box <?php echo $filters['status'] === $status ? 'active' : ''; ?>">
                            <span class="nwl-stat-count"><?php echo esc_html($data['count']); ?></span>
                            <span class="nwl-stat-label"><?php echo esc_html($data['label']); ?></span>
                        </a>
                    <?php endif; ?>
                <?php endforeach; ?>
            </div>

            <!-- Filters -->
            <form method="get" class="nwl-filters-form">
                <input type="hidden" name="page" value="nwl-entries">
                
                <div class="nwl-filters">
                    <div class="nwl-filter-group">
                        <label><?php esc_html_e('Status:', 'nursery-waiting-list'); ?></label>
                        <select name="status">
                            <option value=""><?php esc_html_e('All Statuses', 'nursery-waiting-list'); ?></option>
                            <?php foreach (NWL_Database::get_statuses() as $key => $label) : ?>
                                <option value="<?php echo esc_attr($key); ?>" <?php selected($filters['status'], $key); ?>>
                                    <?php echo esc_html($label); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="nwl-filter-group">
                        <label><?php esc_html_e('Age Group:', 'nursery-waiting-list'); ?></label>
                        <select name="age_group">
                            <option value=""><?php esc_html_e('All Age Groups', 'nursery-waiting-list'); ?></option>
                            <?php foreach (NWL_Database::get_age_groups() as $key => $label) : ?>
                                <option value="<?php echo esc_attr($key); ?>" <?php selected($filters['age_group'], $key); ?>>
                                    <?php echo esc_html($label); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="nwl-filter-group">
                        <label><?php esc_html_e('Date From:', 'nursery-waiting-list'); ?></label>
                        <input type="date" name="date_from" value="<?php echo esc_attr($filters['date_from']); ?>">
                    </div>

                    <div class="nwl-filter-group">
                        <label><?php esc_html_e('Date To:', 'nursery-waiting-list'); ?></label>
                        <input type="date" name="date_to" value="<?php echo esc_attr($filters['date_to']); ?>">
                    </div>

                    <div class="nwl-filter-group nwl-search-group">
                        <label><?php esc_html_e('Search:', 'nursery-waiting-list'); ?></label>
                        <input type="search" name="s" value="<?php echo esc_attr($filters['search']); ?>" 
                               placeholder="<?php esc_attr_e('Name, email, phone...', 'nursery-waiting-list'); ?>">
                    </div>

                    <div class="nwl-filter-buttons">
                        <button type="submit" class="button"><?php esc_html_e('Filter', 'nursery-waiting-list'); ?></button>
                        <a href="<?php echo esc_url(NWL_Admin::get_page_url('nwl-entries')); ?>" class="button">
                            <?php esc_html_e('Clear', 'nursery-waiting-list'); ?>
                        </a>
                        <?php if (current_user_can('nwl_export_data')) : ?>
                            <a href="<?php echo esc_url(NWL_Export::get_instance()->get_export_url($filters)); ?>" class="button">
                                <?php esc_html_e('Export CSV', 'nursery-waiting-list'); ?>
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
            </form>

            <!-- Results count -->
            <p class="nwl-results-count">
                <?php printf(
                    _n('%s entry found', '%s entries found', $total, 'nursery-waiting-list'),
                    number_format_i18n($total)
                ); ?>
            </p>

            <!-- Entries table -->
            <form id="nwl-entries-form" method="post">
                <?php wp_nonce_field('nwl_bulk_action', 'nwl_bulk_nonce'); ?>
                
                <div class="nwl-bulk-actions">
                    <select name="bulk_action" id="nwl-bulk-action">
                        <option value=""><?php esc_html_e('Bulk Actions', 'nursery-waiting-list'); ?></option>
                        <option value="change_status"><?php esc_html_e('Change Status', 'nursery-waiting-list'); ?></option>
                        <option value="send_email"><?php esc_html_e('Send Email', 'nursery-waiting-list'); ?></option>
                        <?php if (current_user_can('nwl_delete_entries')) : ?>
                            <option value="delete"><?php esc_html_e('Delete', 'nursery-waiting-list'); ?></option>
                        <?php endif; ?>
                    </select>
                    <select name="bulk_status" id="nwl-bulk-status" style="display: none;">
                        <?php foreach (NWL_Database::get_statuses() as $key => $label) : ?>
                            <option value="<?php echo esc_attr($key); ?>"><?php echo esc_html($label); ?></option>
                        <?php endforeach; ?>
                    </select>
                    <button type="button" id="nwl-apply-bulk" class="button"><?php esc_html_e('Apply', 'nursery-waiting-list'); ?></button>
                </div>

                <table class="wp-list-table widefat fixed striped nwl-entries-table">
                    <thead>
                        <tr>
                            <td class="check-column">
                                <input type="checkbox" id="nwl-select-all">
                            </td>
                            <th><?php esc_html_e('Reference', 'nursery-waiting-list'); ?></th>
                            <th><?php esc_html_e('Child', 'nursery-waiting-list'); ?></th>
                            <th><?php esc_html_e('Parent/Carer', 'nursery-waiting-list'); ?></th>
                            <th><?php esc_html_e('Status', 'nursery-waiting-list'); ?></th>
                            <th><?php esc_html_e('Priority', 'nursery-waiting-list'); ?></th>
                            <th><?php esc_html_e('Date Added', 'nursery-waiting-list'); ?></th>
                            <th><?php esc_html_e('Actions', 'nursery-waiting-list'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($entries)) : ?>
                            <tr>
                                <td colspan="8" class="nwl-no-entries">
                                    <?php esc_html_e('No entries found.', 'nursery-waiting-list'); ?>
                                </td>
                            </tr>
                        <?php else : ?>
                            <?php foreach ($entries as $entry) : ?>
                                <?php 
                                $entry_handler = NWL_Entry::get_instance();
                                $edit_url = NWL_Admin::get_page_url('nwl-entries', array('action' => 'edit', 'id' => $entry->id));
                                ?>
                                <tr data-entry-id="<?php echo esc_attr($entry->id); ?>"
                                    class="<?php echo $entry->deletion_requested ? 'nwl-deletion-requested' : ''; ?>">
                                    <th scope="row" class="check-column">
                                        <input type="checkbox" name="entry_ids[]" value="<?php echo esc_attr($entry->id); ?>">
                                    </th>
                                    <td>
                                        <a href="<?php echo esc_url($edit_url); ?>" class="nwl-wl-number">
                                            <?php echo esc_html($entry->waiting_list_number); ?>
                                        </a>
                                        <?php if ($entry->deletion_requested) : ?>
                                            <span class="nwl-badge nwl-badge-warning"><?php esc_html_e('Deletion Requested', 'nursery-waiting-list'); ?></span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <strong><?php echo esc_html($entry_handler->get_child_name($entry)); ?></strong>
                                        <?php if ($entry->child_dob) : ?>
                                            <br><small><?php echo esc_html(date_i18n(get_option('date_format'), strtotime($entry->child_dob))); ?></small>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php echo esc_html($entry_handler->get_parent_name($entry)); ?>
                                        <br><small><a href="mailto:<?php echo esc_attr($entry->parent_email); ?>"><?php echo esc_html($entry->parent_email); ?></a></small>
                                        <?php if ($entry->parent_mobile) : ?>
                                            <br><small><?php echo esc_html($entry->parent_mobile); ?></small>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php echo NWL_Admin::get_status_badge($entry->status); ?>
                                    </td>
                                    <td>
                                        <?php if ($entry->priority !== 'normal') : ?>
                                            <?php echo NWL_Admin::get_priority_badge($entry->priority); ?>
                                        <?php else : ?>
                                            —
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php echo esc_html(date_i18n(get_option('date_format'), strtotime($entry->created_at))); ?>
                                    </td>
                                    <td class="nwl-actions">
                                        <a href="<?php echo esc_url($edit_url); ?>" class="button button-small">
                                            <?php esc_html_e('View/Edit', 'nursery-waiting-list'); ?>
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </form>

            <?php NWL_Admin::render_pagination($pages, $filters['page'], NWL_Admin::get_page_url('nwl-entries', $filters)); ?>
        </div>
        <?php
    }

    /**
     * Render add entry page
     */
    public static function render_add_page() {
        self::render_entry_form(null);
    }

    /**
     * Render edit entry page
     */
    public static function render_edit_page($id) {
        $entry = NWL_Entry::get_instance()->get($id);
        
        if (!$entry) {
            NWL_Admin::render_notice(__('Entry not found.', 'nursery-waiting-list'), 'error');
            return;
        }

        self::render_entry_form($entry);
    }

    /**
     * Render entry form (add/edit)
     */
    private static function render_entry_form($entry) {
        $is_edit = !is_null($entry);
        $entry_handler = NWL_Entry::get_instance();
        
        // Get notes if editing
        $notes = $is_edit ? $entry_handler->get_notes($entry->id) : array();
        
        // Get email logs if editing
        $email_logs = $is_edit ? NWL_Email::get_instance()->get_logs_for_entry($entry->id) : array();
        
        ?>
        <div class="wrap nwl-admin-wrap">
            <h1>
                <?php if ($is_edit) : ?>
                    <?php printf(esc_html__('Edit Entry: %s', 'nursery-waiting-list'), $entry->waiting_list_number); ?>
                <?php else : ?>
                    <?php esc_html_e('Add New Entry', 'nursery-waiting-list'); ?>
                <?php endif; ?>
            </h1>

            <div class="nwl-edit-container">
                <div class="nwl-edit-main">
                    <form id="nwl-entry-form" method="post">
                        <?php wp_nonce_field('nwl_save_entry', 'nwl_entry_nonce'); ?>
                        <input type="hidden" name="entry_id" value="<?php echo $is_edit ? esc_attr($entry->id) : ''; ?>">

                        <!-- Child Information -->
                        <div class="nwl-form-section">
                            <h2><?php esc_html_e('Child Information', 'nursery-waiting-list'); ?></h2>

                            <div class="nwl-form-row">
                                <div class="nwl-form-field">
                                    <label for="child_first_name"><?php esc_html_e('First Name', 'nursery-waiting-list'); ?> <span class="required">*</span></label>
                                    <input type="text" id="child_first_name" name="child_first_name"
                                           value="<?php echo $is_edit ? esc_attr($entry->child_first_name) : ''; ?>" required>
                                </div>
                                <div class="nwl-form-field">
                                    <label for="child_last_name"><?php esc_html_e('Last Name', 'nursery-waiting-list'); ?> <span class="required">*</span></label>
                                    <input type="text" id="child_last_name" name="child_last_name"
                                           value="<?php echo $is_edit ? esc_attr($entry->child_last_name) : ''; ?>" required>
                                </div>
                            </div>

                            <div class="nwl-form-row">
                                <div class="nwl-form-field">
                                    <label for="child_dob"><?php esc_html_e('Date of Birth', 'nursery-waiting-list'); ?></label>
                                    <input type="date" id="child_dob" name="child_dob"
                                           value="<?php echo $is_edit ? esc_attr($entry->child_dob) : ''; ?>">
                                </div>
                                <div class="nwl-form-field">
                                    <label for="child_gender"><?php esc_html_e('Gender', 'nursery-waiting-list'); ?></label>
                                    <select id="child_gender" name="child_gender">
                                        <option value=""><?php esc_html_e('— Select —', 'nursery-waiting-list'); ?></option>
                                        <option value="male" <?php selected($is_edit ? $entry->child_gender : '', 'male'); ?>><?php esc_html_e('Male', 'nursery-waiting-list'); ?></option>
                                        <option value="female" <?php selected($is_edit ? $entry->child_gender : '', 'female'); ?>><?php esc_html_e('Female', 'nursery-waiting-list'); ?></option>
                                    </select>
                                </div>
                            </div>

                            <div class="nwl-form-row">
                                <div class="nwl-form-field">
                                    <label for="child_place_of_birth_city"><?php esc_html_e('Place of Birth (City)', 'nursery-waiting-list'); ?></label>
                                    <input type="text" id="child_place_of_birth_city" name="child_place_of_birth_city"
                                           value="<?php echo $is_edit ? esc_attr($entry->child_place_of_birth_city) : ''; ?>">
                                </div>
                                <div class="nwl-form-field">
                                    <label for="child_place_of_birth_country"><?php esc_html_e('Place of Birth (Country)', 'nursery-waiting-list'); ?></label>
                                    <input type="text" id="child_place_of_birth_country" name="child_place_of_birth_country"
                                           value="<?php echo $is_edit ? esc_attr($entry->child_place_of_birth_country) : ''; ?>">
                                </div>
                            </div>

                            <div class="nwl-form-row">
                                <div class="nwl-form-field">
                                    <label for="child_first_language"><?php esc_html_e('First Language', 'nursery-waiting-list'); ?></label>
                                    <input type="text" id="child_first_language" name="child_first_language"
                                           value="<?php echo $is_edit ? esc_attr($entry->child_first_language) : ''; ?>">
                                </div>
                                <div class="nwl-form-field">
                                    <label for="child_ethnicity"><?php esc_html_e('Ethnicity', 'nursery-waiting-list'); ?></label>
                                    <input type="text" id="child_ethnicity" name="child_ethnicity"
                                           value="<?php echo $is_edit ? esc_attr($entry->child_ethnicity) : ''; ?>">
                                </div>
                            </div>

                            <div class="nwl-form-row">
                                <div class="nwl-form-field">
                                    <label for="child_attended_other_nursery"><?php esc_html_e('Has your child attended another nursery?', 'nursery-waiting-list'); ?></label>
                                    <select id="child_attended_other_nursery" name="child_attended_other_nursery">
                                        <option value="0" <?php selected($is_edit ? $entry->child_attended_other_nursery : '', '0'); ?>><?php esc_html_e('No', 'nursery-waiting-list'); ?></option>
                                        <option value="1" <?php selected($is_edit ? $entry->child_attended_other_nursery : '', '1'); ?>><?php esc_html_e('Yes', 'nursery-waiting-list'); ?></option>
                                    </select>
                                </div>
                                <div class="nwl-form-field">
                                    <label for="child_previous_nursery_name"><?php esc_html_e('Name of Previous Nursery', 'nursery-waiting-list'); ?></label>
                                    <input type="text" id="child_previous_nursery_name" name="child_previous_nursery_name"
                                           value="<?php echo $is_edit ? esc_attr($entry->child_previous_nursery_name) : ''; ?>">
                                </div>
                            </div>
                        </div>

                        <!-- Parent/Carer Information -->
                        <div class="nwl-form-section">
                            <h2><?php esc_html_e('Parent/Carer Information', 'nursery-waiting-list'); ?></h2>

                            <div class="nwl-form-row">
                                <div class="nwl-form-field">
                                    <label for="parent_first_name"><?php esc_html_e('First Name', 'nursery-waiting-list'); ?> <span class="required">*</span></label>
                                    <input type="text" id="parent_first_name" name="parent_first_name"
                                           value="<?php echo $is_edit ? esc_attr($entry->parent_first_name) : ''; ?>" required>
                                </div>
                                <div class="nwl-form-field">
                                    <label for="parent_last_name"><?php esc_html_e('Last Name', 'nursery-waiting-list'); ?> <span class="required">*</span></label>
                                    <input type="text" id="parent_last_name" name="parent_last_name"
                                           value="<?php echo $is_edit ? esc_attr($entry->parent_last_name) : ''; ?>" required>
                                </div>
                            </div>

                            <div class="nwl-form-row">
                                <div class="nwl-form-field">
                                    <label for="parent_dob"><?php esc_html_e('Date of Birth', 'nursery-waiting-list'); ?></label>
                                    <input type="date" id="parent_dob" name="parent_dob"
                                           value="<?php echo $is_edit ? esc_attr($entry->parent_dob) : ''; ?>">
                                </div>
                                <div class="nwl-form-field">
                                    <label for="parent_national_insurance"><?php esc_html_e('National Insurance Number', 'nursery-waiting-list'); ?></label>
                                    <input type="text" id="parent_national_insurance" name="parent_national_insurance"
                                           value="<?php echo $is_edit ? esc_attr($entry->parent_national_insurance) : ''; ?>"
                                           placeholder="<?php esc_attr_e('e.g. AB123456C', 'nursery-waiting-list'); ?>">
                                </div>
                            </div>

                            <div class="nwl-form-row">
                                <div class="nwl-form-field">
                                    <label for="parent_email"><?php esc_html_e('Email', 'nursery-waiting-list'); ?> <span class="required">*</span></label>
                                    <input type="email" id="parent_email" name="parent_email"
                                           value="<?php echo $is_edit ? esc_attr($entry->parent_email) : ''; ?>" required>
                                </div>
                                <div class="nwl-form-field">
                                    <label for="parent_phone"><?php esc_html_e('Phone Number', 'nursery-waiting-list'); ?></label>
                                    <input type="tel" id="parent_phone" name="parent_phone"
                                           value="<?php echo $is_edit ? esc_attr($entry->parent_phone) : ''; ?>">
                                </div>
                            </div>

                            <div class="nwl-form-row">
                                <div class="nwl-form-field">
                                    <label for="parent_mobile"><?php esc_html_e('Mobile', 'nursery-waiting-list'); ?></label>
                                    <input type="tel" id="parent_mobile" name="parent_mobile"
                                           value="<?php echo $is_edit ? esc_attr($entry->parent_mobile) : ''; ?>">
                                </div>
                                <div class="nwl-form-field">
                                    <label for="relationship_to_child"><?php esc_html_e('Relationship to Child', 'nursery-waiting-list'); ?></label>
                                    <input type="text" id="relationship_to_child" name="relationship_to_child"
                                           value="<?php echo $is_edit ? esc_attr($entry->relationship_to_child) : ''; ?>"
                                           placeholder="<?php esc_attr_e('e.g. Mother, Father, Guardian', 'nursery-waiting-list'); ?>">
                                </div>
                            </div>

                            <div class="nwl-form-row">
                                <div class="nwl-form-field">
                                    <label for="parental_responsibility"><?php esc_html_e('Parental Responsibility', 'nursery-waiting-list'); ?></label>
                                    <input type="text" id="parental_responsibility" name="parental_responsibility"
                                           value="<?php echo $is_edit ? esc_attr($entry->parental_responsibility) : ''; ?>">
                                </div>
                                <div class="nwl-form-field">
                                    <label for="declaration"><?php esc_html_e('Declaration', 'nursery-waiting-list'); ?></label>
                                    <select id="declaration" name="declaration">
                                        <option value="0" <?php selected($is_edit ? $entry->declaration : '', '0'); ?>><?php esc_html_e('No', 'nursery-waiting-list'); ?></option>
                                        <option value="1" <?php selected($is_edit ? $entry->declaration : '', '1'); ?>><?php esc_html_e('Yes', 'nursery-waiting-list'); ?></option>
                                    </select>
                                </div>
                            </div>

                            <div class="nwl-form-row">
                                <div class="nwl-form-field nwl-full-width">
                                    <label for="parent_address_line1"><?php esc_html_e('Address', 'nursery-waiting-list'); ?></label>
                                    <input type="text" id="parent_address_line1" name="parent_address_line1"
                                           value="<?php echo $is_edit ? esc_attr($entry->parent_address_line1) : ''; ?>"
                                           placeholder="<?php esc_attr_e('Street address', 'nursery-waiting-list'); ?>">
                                </div>
                            </div>

                            <div class="nwl-form-row">
                                <div class="nwl-form-field">
                                    <label for="parent_city"><?php esc_html_e('City/Town', 'nursery-waiting-list'); ?></label>
                                    <input type="text" id="parent_city" name="parent_city"
                                           value="<?php echo $is_edit ? esc_attr($entry->parent_city) : ''; ?>">
                                </div>
                                <div class="nwl-form-field">
                                    <label for="parent_postcode"><?php esc_html_e('Postcode', 'nursery-waiting-list'); ?></label>
                                    <input type="text" id="parent_postcode" name="parent_postcode"
                                           value="<?php echo $is_edit ? esc_attr($entry->parent_postcode) : ''; ?>">
                                </div>
                            </div>
                        </div>

                        <!-- Waiting List Details -->
                        <div class="nwl-form-section">
                            <h2><?php esc_html_e('Waiting List Details', 'nursery-waiting-list'); ?></h2>

                            <div class="nwl-form-row">
                                <div class="nwl-form-field">
                                    <label for="age_group"><?php esc_html_e('Age Group', 'nursery-waiting-list'); ?></label>
                                    <select id="age_group" name="age_group">
                                        <option value=""><?php esc_html_e('— Select —', 'nursery-waiting-list'); ?></option>
                                        <?php foreach (NWL_Database::get_age_groups() as $key => $label) : ?>
                                            <option value="<?php echo esc_attr($key); ?>" <?php selected($is_edit ? $entry->age_group : '', $key); ?>>
                                                <?php echo esc_html($label); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="nwl-form-field">
                                    <label for="preferred_start_date"><?php esc_html_e('Preferred Start Date', 'nursery-waiting-list'); ?></label>
                                    <input type="date" id="preferred_start_date" name="preferred_start_date"
                                           value="<?php echo $is_edit ? esc_attr($entry->preferred_start_date) : ''; ?>">
                                </div>
                            </div>

                            <div class="nwl-form-row">
                                <div class="nwl-form-field">
                                    <label for="days_required"><?php esc_html_e('Days Required', 'nursery-waiting-list'); ?></label>
                                    <input type="text" id="days_required" name="days_required"
                                           value="<?php echo $is_edit ? esc_attr($entry->days_required) : ''; ?>"
                                           placeholder="<?php esc_attr_e('e.g. Monday, Wednesday, Friday', 'nursery-waiting-list'); ?>">
                                </div>
                                <div class="nwl-form-field">
                                    <label for="hours_per_week"><?php esc_html_e('Hours Per Week', 'nursery-waiting-list'); ?></label>
                                    <input type="number" id="hours_per_week" name="hours_per_week"
                                           value="<?php echo $is_edit ? esc_attr($entry->hours_per_week) : ''; ?>">
                                </div>
                            </div>
                        </div>

                        <!-- Notes -->
                        <div class="nwl-form-section">
                            <h2><?php esc_html_e('Notes', 'nursery-waiting-list'); ?></h2>
                            
                            <div class="nwl-form-row">
                                <div class="nwl-form-field nwl-full-width">
                                    <label for="internal_notes"><?php esc_html_e('Internal Notes (Staff Only)', 'nursery-waiting-list'); ?></label>
                                    <textarea id="internal_notes" name="internal_notes" rows="3"><?php echo $is_edit ? esc_textarea($entry->internal_notes) : ''; ?></textarea>
                                    <p class="description"><?php esc_html_e('These notes are only visible to staff and will not be shared with parents.', 'nursery-waiting-list'); ?></p>
                                </div>
                            </div>

                            <div class="nwl-form-row">
                                <div class="nwl-form-field nwl-full-width">
                                    <label for="public_notes"><?php esc_html_e('Public Notes (Visible to Parents/Carers)', 'nursery-waiting-list'); ?></label>
                                    <textarea id="public_notes" name="public_notes" rows="3"><?php echo $is_edit ? esc_textarea($entry->public_notes) : ''; ?></textarea>
                                    <p class="description"><?php esc_html_e('These notes can be included in emails and are visible when parents/carers check their status.', 'nursery-waiting-list'); ?></p>
                                </div>
                            </div>
                        </div>

                        <div class="nwl-form-actions">
                            <button type="submit" class="button button-primary button-large">
                                <?php echo $is_edit ? esc_html__('Update Entry', 'nursery-waiting-list') : esc_html__('Add Entry', 'nursery-waiting-list'); ?>
                            </button>
                            <a href="<?php echo esc_url(NWL_Admin::get_page_url('nwl-entries')); ?>" class="button button-large">
                                <?php esc_html_e('Cancel', 'nursery-waiting-list'); ?>
                            </a>
                        </div>
                    </form>
                </div>

                <?php if ($is_edit) : ?>
                    <!-- Sidebar -->
                    <div class="nwl-edit-sidebar">
                        <!-- Status & Priority -->
                        <div class="nwl-sidebar-box">
                            <h3><?php esc_html_e('Status & Priority', 'nursery-waiting-list'); ?></h3>
                            
                            <div class="nwl-sidebar-field">
                                <label for="status"><?php esc_html_e('Status', 'nursery-waiting-list'); ?></label>
                                <select id="status" name="status" class="nwl-status-select" data-entry-id="<?php echo esc_attr($entry->id); ?>">
                                    <?php foreach (NWL_Database::get_statuses() as $key => $label) : ?>
                                        <option value="<?php echo esc_attr($key); ?>" <?php selected($entry->status, $key); ?>>
                                            <?php echo esc_html($label); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="nwl-sidebar-field">
                                <label for="priority"><?php esc_html_e('Priority', 'nursery-waiting-list'); ?></label>
                                <select id="priority" name="priority">
                                    <?php foreach (NWL_Database::get_priorities() as $key => $label) : ?>
                                        <option value="<?php echo esc_attr($key); ?>" <?php selected($entry->priority, $key); ?>>
                                            <?php echo esc_html($label); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <?php if ($entry->status === 'offered') : ?>
                                <div class="nwl-sidebar-field">
                                    <label for="offer_deadline"><?php esc_html_e('Offer Deadline', 'nursery-waiting-list'); ?></label>
                                    <input type="date" id="offer_deadline" name="offer_deadline" 
                                           value="<?php echo esc_attr($entry->offer_deadline); ?>">
                                </div>
                            <?php endif; ?>
                        </div>

                        <!-- Quick Actions -->
                        <div class="nwl-sidebar-box">
                            <h3><?php esc_html_e('Quick Actions', 'nursery-waiting-list'); ?></h3>
                            
                            <div class="nwl-quick-actions">
                                <a href="<?php echo esc_url(NWL_Admin::get_page_url('nwl-messaging', array('entry_id' => $entry->id))); ?>" class="button">
                                    <?php esc_html_e('Send Email', 'nursery-waiting-list'); ?>
                                </a>
                                
                                <?php if (current_user_can('nwl_delete_entries')) : ?>
                                    <?php if ($entry->deletion_requested) : ?>
                                        <button type="button" class="button nwl-cancel-deletion" data-entry-id="<?php echo esc_attr($entry->id); ?>">
                                            <?php esc_html_e('Cancel Deletion Request', 'nursery-waiting-list'); ?>
                                        </button>
                                        <button type="button" class="button button-link-delete nwl-hard-delete" data-entry-id="<?php echo esc_attr($entry->id); ?>">
                                            <?php esc_html_e('Delete Permanently', 'nursery-waiting-list'); ?>
                                        </button>
                                    <?php else : ?>
                                        <button type="button" class="button button-link-delete nwl-delete-entry" data-entry-id="<?php echo esc_attr($entry->id); ?>">
                                            <?php esc_html_e('Delete Entry', 'nursery-waiting-list'); ?>
                                        </button>
                                    <?php endif; ?>
                                <?php endif; ?>
                            </div>
                        </div>

                        <!-- Entry Info -->
                        <div class="nwl-sidebar-box">
                            <h3><?php esc_html_e('Entry Information', 'nursery-waiting-list'); ?></h3>
                            
                            <p><strong><?php esc_html_e('WL Number:', 'nursery-waiting-list'); ?></strong><br>
                            <?php echo esc_html($entry->waiting_list_number); ?></p>
                            
                            <p><strong><?php esc_html_e('Date Added:', 'nursery-waiting-list'); ?></strong><br>
                            <?php echo esc_html(date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($entry->created_at))); ?></p>
                            
                            <p><strong><?php esc_html_e('Last Updated:', 'nursery-waiting-list'); ?></strong><br>
                            <?php echo esc_html(date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($entry->updated_at))); ?></p>
                            
                            <?php if ($entry->gravity_entry_id) : ?>
                                <p><strong><?php esc_html_e('Gravity Forms Entry:', 'nursery-waiting-list'); ?></strong><br>
                                <a href="<?php echo esc_url(admin_url('admin.php?page=gf_entries&view=entry&id=' . $entry->gravity_form_id . '&lid=' . $entry->gravity_entry_id)); ?>">
                                    <?php printf(esc_html__('View Entry #%d', 'nursery-waiting-list'), $entry->gravity_entry_id); ?>
                                </a></p>
                            <?php endif; ?>
                        </div>

                        <!-- Add Note -->
                        <div class="nwl-sidebar-box">
                            <h3><?php esc_html_e('Add Note', 'nursery-waiting-list'); ?></h3>
                            
                            <div class="nwl-add-note-form">
                                <select id="note-type" class="nwl-note-type">
                                    <option value="internal"><?php esc_html_e('Internal Note', 'nursery-waiting-list'); ?></option>
                                    <option value="public"><?php esc_html_e('Public Note', 'nursery-waiting-list'); ?></option>
                                </select>
                                <textarea id="note-content" class="nwl-note-content" rows="3" placeholder="<?php esc_attr_e('Enter note...', 'nursery-waiting-list'); ?>"></textarea>
                                <button type="button" class="button nwl-add-note-btn" data-entry-id="<?php echo esc_attr($entry->id); ?>">
                                    <?php esc_html_e('Add Note', 'nursery-waiting-list'); ?>
                                </button>
                            </div>
                        </div>

                        <!-- Notes History -->
                        <?php if (!empty($notes)) : ?>
                            <div class="nwl-sidebar-box">
                                <h3><?php esc_html_e('Notes History', 'nursery-waiting-list'); ?></h3>
                                
                                <div class="nwl-notes-list">
                                    <?php foreach ($notes as $note) : ?>
                                        <div class="nwl-note nwl-note-<?php echo esc_attr($note->note_type); ?>">
                                            <div class="nwl-note-meta">
                                                <span class="nwl-note-type-badge"><?php echo esc_html(ucfirst($note->note_type)); ?></span>
                                                <span class="nwl-note-date"><?php echo esc_html(date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($note->created_at))); ?></span>
                                                <?php if ($note->author_name) : ?>
                                                    <span class="nwl-note-author"><?php echo esc_html($note->author_name); ?></span>
                                                <?php endif; ?>
                                            </div>
                                            <div class="nwl-note-content">
                                                <?php echo esc_html($note->note_content); ?>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        <?php endif; ?>

                        <!-- Email History -->
                        <?php if (!empty($email_logs)) : ?>
                            <div class="nwl-sidebar-box">
                                <h3><?php esc_html_e('Email History', 'nursery-waiting-list'); ?></h3>
                                
                                <div class="nwl-email-list">
                                    <?php foreach (array_slice($email_logs, 0, 5) as $log) : ?>
                                        <div class="nwl-email-log">
                                            <div class="nwl-email-subject"><?php echo esc_html($log->subject); ?></div>
                                            <div class="nwl-email-meta">
                                                <span class="nwl-email-status nwl-email-<?php echo esc_attr($log->status); ?>"><?php echo esc_html(ucfirst($log->status)); ?></span>
                                                <span class="nwl-email-date"><?php echo esc_html(date_i18n(get_option('date_format'), strtotime($log->created_at))); ?></span>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        <?php
    }

    /**
     * AJAX: Update status
     */
    public function ajax_update_status() {
        check_ajax_referer('nwl_admin', 'nonce');
        
        if (!current_user_can('nwl_edit_entries')) {
            wp_send_json_error(array('message' => __('Permission denied.', 'nursery-waiting-list')));
        }

        $entry_id = absint($_POST['entry_id']);
        $status = sanitize_text_field($_POST['status']);

        $result = NWL_Entry::get_instance()->update($entry_id, array('status' => $status));

        if (is_wp_error($result)) {
            wp_send_json_error(array('message' => $result->get_error_message()));
        }

        wp_send_json_success(array(
            'message' => __('Status updated.', 'nursery-waiting-list'),
            'badge' => NWL_Admin::get_status_badge($status),
        ));
    }

    /**
     * AJAX: Add note
     */
    public function ajax_add_note() {
        check_ajax_referer('nwl_admin', 'nonce');
        
        if (!current_user_can('nwl_edit_entries')) {
            wp_send_json_error(array('message' => __('Permission denied.', 'nursery-waiting-list')));
        }

        $entry_id = absint($_POST['entry_id']);
        $note_type = sanitize_text_field($_POST['note_type']);
        $content = sanitize_textarea_field($_POST['content']);

        if (empty($content)) {
            wp_send_json_error(array('message' => __('Note content is required.', 'nursery-waiting-list')));
        }

        $note_id = NWL_Entry::get_instance()->add_note($entry_id, $note_type, $content);

        if ($note_id) {
            wp_send_json_success(array('message' => __('Note added.', 'nursery-waiting-list')));
        }

        wp_send_json_error(array('message' => __('Failed to add note.', 'nursery-waiting-list')));
    }

    /**
     * AJAX: Delete entry
     */
    public function ajax_delete_entry() {
        check_ajax_referer('nwl_admin', 'nonce');
        
        if (!current_user_can('nwl_delete_entries')) {
            wp_send_json_error(array('message' => __('Permission denied.', 'nursery-waiting-list')));
        }

        $entry_id = absint($_POST['entry_id']);
        $hard_delete = isset($_POST['hard_delete']) && $_POST['hard_delete'] === 'true';

        $entry_handler = NWL_Entry::get_instance();

        if ($hard_delete) {
            $result = $entry_handler->hard_delete($entry_id);
        } else {
            $result = $entry_handler->request_deletion($entry_id);
        }

        if (is_wp_error($result)) {
            wp_send_json_error(array('message' => $result->get_error_message()));
        }

        wp_send_json_success(array(
            'message' => $hard_delete ? __('Entry deleted.', 'nursery-waiting-list') : __('Entry marked for deletion.', 'nursery-waiting-list'),
            'redirect' => $hard_delete ? NWL_Admin::get_page_url('nwl-entries') : '',
        ));
    }

    /**
     * AJAX: Cancel deletion request
     */
    public function ajax_cancel_deletion() {
        check_ajax_referer('nwl_admin', 'nonce');

        if (!current_user_can('nwl_delete_entries')) {
            wp_send_json_error(array('message' => __('Permission denied.', 'nursery-waiting-list')));
        }

        $entry_id = absint($_POST['entry_id']);
        $result = NWL_Entry::get_instance()->cancel_deletion($entry_id);

        if (is_wp_error($result)) {
            wp_send_json_error(array('message' => $result->get_error_message()));
        }

        wp_send_json_success(array('message' => __('Deletion request cancelled.', 'nursery-waiting-list')));
    }

    /**
     * AJAX: Bulk action
     */
    public function ajax_bulk_action() {
        check_ajax_referer('nwl_admin', 'nonce');

        $action = sanitize_text_field($_POST['bulk_action']);
        $entry_ids = isset($_POST['entry_ids']) ? array_map('absint', $_POST['entry_ids']) : array();

        if (empty($entry_ids)) {
            wp_send_json_error(array('message' => __('No entries selected.', 'nursery-waiting-list')));
        }

        $entry_handler = NWL_Entry::get_instance();
        $count = 0;

        switch ($action) {
            case 'change_status':
                if (!current_user_can('nwl_edit_entries')) {
                    wp_send_json_error(array('message' => __('Permission denied.', 'nursery-waiting-list')));
                }
                $status = sanitize_text_field($_POST['bulk_status']);
                foreach ($entry_ids as $id) {
                    if (!is_wp_error($entry_handler->update($id, array('status' => $status)))) {
                        $count++;
                    }
                }
                $message = sprintf(_n('%d entry updated.', '%d entries updated.', $count, 'nursery-waiting-list'), $count);
                break;

            case 'delete':
                if (!current_user_can('nwl_delete_entries')) {
                    wp_send_json_error(array('message' => __('Permission denied.', 'nursery-waiting-list')));
                }
                foreach ($entry_ids as $id) {
                    if (!is_wp_error($entry_handler->request_deletion($id))) {
                        $count++;
                    }
                }
                $message = sprintf(_n('%d entry marked for deletion.', '%d entries marked for deletion.', $count, 'nursery-waiting-list'), $count);
                break;

            case 'send_email':
                wp_send_json_success(array(
                    'redirect' => NWL_Admin::get_page_url('nwl-messaging', array('entry_ids' => implode(',', $entry_ids))),
                ));
                break;

            default:
                wp_send_json_error(array('message' => __('Invalid action.', 'nursery-waiting-list')));
        }

        wp_send_json_success(array('message' => $message));
    }

    /**
     * AJAX: Save entry
     */
    public function ajax_save_entry() {
        // Verify nonce
        if (!check_ajax_referer('nwl_save_entry', 'nwl_entry_nonce', false)) {
            wp_send_json_error(array('message' => __('Security check failed. Please refresh the page and try again.', 'nursery-waiting-list')));
            return;
        }

        if (!current_user_can('nwl_edit_entries')) {
            wp_send_json_error(array('message' => __('Permission denied.', 'nursery-waiting-list')));
            return;
        }

        // Validate required fields
        $required_fields = array('child_first_name', 'child_last_name', 'parent_first_name', 'parent_last_name', 'parent_email');
        foreach ($required_fields as $field) {
            if (empty($_POST[$field])) {
                wp_send_json_error(array('message' => sprintf(__('The %s field is required.', 'nursery-waiting-list'), str_replace('_', ' ', $field))));
                return;
            }
        }

        // Validate email format
        if (!is_email($_POST['parent_email'])) {
            wp_send_json_error(array('message' => __('Please enter a valid email address.', 'nursery-waiting-list')));
            return;
        }

        $entry_id = isset($_POST['entry_id']) ? absint($_POST['entry_id']) : 0;

        $data = array(
            // Child Information
            'child_first_name' => sanitize_text_field($_POST['child_first_name'] ?? ''),
            'child_last_name' => sanitize_text_field($_POST['child_last_name'] ?? ''),
            'child_dob' => sanitize_text_field($_POST['child_dob'] ?? ''),
            'child_gender' => sanitize_text_field($_POST['child_gender'] ?? ''),
            'child_place_of_birth_city' => sanitize_text_field($_POST['child_place_of_birth_city'] ?? ''),
            'child_place_of_birth_country' => sanitize_text_field($_POST['child_place_of_birth_country'] ?? ''),
            'child_first_language' => sanitize_text_field($_POST['child_first_language'] ?? ''),
            'child_ethnicity' => sanitize_text_field($_POST['child_ethnicity'] ?? ''),
            'child_attended_other_nursery' => absint($_POST['child_attended_other_nursery'] ?? 0),
            'child_previous_nursery_name' => sanitize_text_field($_POST['child_previous_nursery_name'] ?? ''),
            // Parent Information
            'parent_first_name' => sanitize_text_field($_POST['parent_first_name'] ?? ''),
            'parent_last_name' => sanitize_text_field($_POST['parent_last_name'] ?? ''),
            'parent_dob' => sanitize_text_field($_POST['parent_dob'] ?? ''),
            'parent_national_insurance' => sanitize_text_field($_POST['parent_national_insurance'] ?? ''),
            'parent_email' => sanitize_email($_POST['parent_email'] ?? ''),
            'parent_phone' => sanitize_text_field($_POST['parent_phone'] ?? ''),
            'parent_mobile' => sanitize_text_field($_POST['parent_mobile'] ?? ''),
            'parent_address_line1' => sanitize_text_field($_POST['parent_address_line1'] ?? ''),
            'parent_city' => sanitize_text_field($_POST['parent_city'] ?? ''),
            'parent_postcode' => sanitize_text_field($_POST['parent_postcode'] ?? ''),
            'parental_responsibility' => sanitize_text_field($_POST['parental_responsibility'] ?? ''),
            'relationship_to_child' => sanitize_text_field($_POST['relationship_to_child'] ?? ''),
            'declaration' => absint($_POST['declaration'] ?? 0),
            // Waiting List Details
            'age_group' => sanitize_text_field($_POST['age_group'] ?? ''),
            'preferred_start_date' => sanitize_text_field($_POST['preferred_start_date'] ?? ''),
            'days_required' => sanitize_text_field($_POST['days_required'] ?? ''),
            'hours_per_week' => absint($_POST['hours_per_week'] ?? 0),
            // Notes
            'internal_notes' => sanitize_textarea_field($_POST['internal_notes'] ?? ''),
            'public_notes' => sanitize_textarea_field($_POST['public_notes'] ?? ''),
        );

        // Add status and priority if present
        if (isset($_POST['status'])) {
            $data['status'] = sanitize_text_field($_POST['status']);
        }
        if (isset($_POST['priority'])) {
            $data['priority'] = sanitize_text_field($_POST['priority']);
        }
        if (isset($_POST['offer_deadline'])) {
            $data['offer_deadline'] = sanitize_text_field($_POST['offer_deadline']);
        }

        $entry_handler = NWL_Entry::get_instance();

        if ($entry_id) {
            $result = $entry_handler->update($entry_id, $data);
            $message = __('Entry updated successfully.', 'nursery-waiting-list');
        } else {
            $result = $entry_handler->create($data);
            $entry_id = $result;
            $message = __('Entry created successfully.', 'nursery-waiting-list');
        }

        if (is_wp_error($result)) {
            wp_send_json_error(array('message' => $result->get_error_message()));
        }

        wp_send_json_success(array(
            'message' => $message,
            'entry_id' => $entry_id,
            'redirect' => NWL_Admin::get_page_url('nwl-entries', array('action' => 'edit', 'id' => $entry_id)),
        ));
    }
}
