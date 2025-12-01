<?php
/**
 * Gravity Forms integration class
 */

if (!defined('ABSPATH')) {
    exit;
}

class NWL_Gravity_Forms {

    private static $instance = null;

    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        // Hook into Gravity Forms submission
        add_action('gform_after_submission', array($this, 'process_submission'), 10, 2);
        
        // Add settings to Gravity Forms
        add_filter('gform_form_settings_menu', array($this, 'add_settings_menu'), 10, 2);
        add_action('gform_form_settings_page_nwl_settings', array($this, 'settings_page'));
        
        // Add merge tags
        add_filter('gform_custom_merge_tags', array($this, 'add_merge_tags'), 10, 4);
        add_filter('gform_replace_merge_tags', array($this, 'replace_merge_tags'), 10, 7);
    }

    /**
     * Process Gravity Forms submission
     */
    public function process_submission($entry, $form) {
        // Check if this form is linked to waiting list
        $form_settings = $this->get_form_settings($form['id']);
        
        if (empty($form_settings['enabled'])) {
            return;
        }

        // Map form fields to entry data
        $data = $this->map_fields($entry, $form, $form_settings);
        
        if (empty($data['parent_email'])) {
            // Email is required
            return;
        }

        // Add gravity forms reference
        $data['gravity_form_id'] = $form['id'];
        $data['gravity_entry_id'] = $entry['id'];

        // Create waiting list entry
        $entry_handler = NWL_Entry::get_instance();
        $wl_entry_id = $entry_handler->create($data);

        if (!is_wp_error($wl_entry_id)) {
            // Store the waiting list entry ID in the GF entry meta
            gform_update_meta($entry['id'], 'nwl_entry_id', $wl_entry_id);
            
            // Get the waiting list number for merge tags
            $wl_entry = $entry_handler->get($wl_entry_id);
            if ($wl_entry) {
                gform_update_meta($entry['id'], 'nwl_waiting_list_number', $wl_entry->waiting_list_number);
            }
        }
    }

    /**
     * Map Gravity Forms fields to waiting list entry fields
     */
    private function map_fields($entry, $form, $settings) {
        $field_map = isset($settings['field_map']) ? $settings['field_map'] : array();
        
        $data = array(
            'status' => isset($settings['default_status']) ? $settings['default_status'] : 'pending',
        );

        // Map each configured field
        foreach ($field_map as $wl_field => $gf_field_id) {
            if (empty($gf_field_id)) {
                continue;
            }

            // Ensure field ID is integer for GFAPI lookup
            $gf_field_id_int = absint($gf_field_id);

            // Handle name fields (which can have subfields)
            $field = GFAPI::get_field($form, $gf_field_id_int);
            
            if ($field && $field->type === 'name') {
                // Name field - get first and last name
                $first = rgar($entry, $gf_field_id . '.3');
                $last = rgar($entry, $gf_field_id . '.6');
                
                if (strpos($wl_field, 'child_') === 0) {
                    $data['child_first_name'] = $first;
                    $data['child_last_name'] = $last;
                } elseif (strpos($wl_field, 'parent_') === 0 && strpos($wl_field, 'parent2_') === false) {
                    $data['parent_first_name'] = $first;
                    $data['parent_last_name'] = $last;
                } elseif (strpos($wl_field, 'parent2_') === 0) {
                    $data['parent2_first_name'] = $first;
                    $data['parent2_last_name'] = $last;
                }
            } elseif ($field && $field->type === 'address') {
                // Handle different address field mappings
                if ($wl_field === 'child_place_of_birth') {
                    // Child place of birth - map city and country from address field
                    $data['child_place_of_birth_city'] = rgar($entry, $gf_field_id . '.3');
                    $data['child_place_of_birth_country'] = rgar($entry, $gf_field_id . '.6');
                } else {
                    // Parent address field - map to 3 fields only (address, city, postcode)
                    $data['parent_address_line1'] = rgar($entry, $gf_field_id . '.1');
                    $data['parent_city'] = rgar($entry, $gf_field_id . '.3');
                    $data['parent_postcode'] = rgar($entry, $gf_field_id . '.5');
                }
            } elseif ($field && $field->type === 'date') {
                // Date field - convert to MySQL format
                $date_value = rgar($entry, $gf_field_id_int);
                if ($date_value) {
                    $data[$wl_field] = date('Y-m-d', strtotime($date_value));
                }
            } elseif ($field && $field->type === 'checkbox') {
                // Checkbox field - get all selected values
                $values = array();
                foreach ($field->inputs as $input) {
                    $value = rgar($entry, $input['id']);
                    if ($value) {
                        $values[] = $value;
                    }
                }
                // Handle boolean fields (Yes/No answers)
                if (in_array($wl_field, array('child_attended_other_nursery', 'declaration'))) {
                    $data[$wl_field] = !empty($values) ? 1 : 0;
                } else {
                    $data[$wl_field] = implode(', ', $values);
                }
            } elseif ($field && $field->type === 'radio') {
                // Radio field - handle Yes/No type answers for boolean fields
                $value = rgar($entry, $gf_field_id_int);
                if (in_array($wl_field, array('child_attended_other_nursery', 'declaration'))) {
                    $yes_values = array('yes', 'true', '1', 'oui');
                    $data[$wl_field] = in_array(strtolower($value), $yes_values) ? 1 : 0;
                } else {
                    $data[$wl_field] = $value;
                }
            } elseif ($field && $field->type === 'select') {
                // Select/dropdown field - get selected value directly
                $value = rgar($entry, $gf_field_id_int);
                $data[$wl_field] = $value;
            } else {
                // Standard field (text, number, etc.) or field not found
                $value = rgar($entry, $gf_field_id_int);
                // Handle boolean fields that might come from text/select
                if (in_array($wl_field, array('child_attended_other_nursery', 'declaration'))) {
                    $yes_values = array('yes', 'true', '1', 'oui');
                    $data[$wl_field] = in_array(strtolower($value), $yes_values) ? 1 : 0;
                } else {
                    $data[$wl_field] = $value;
                }
            }
        }

        // Auto-calculate age group from child's date of birth
        if (!empty($data['child_dob'])) {
            $data['age_group'] = $this->calculate_age_group($data['child_dob']);
        }

        // Apply filters for custom mapping
        return apply_filters('nwl_gravity_forms_mapped_data', $data, $entry, $form, $settings);
    }

    /**
     * Calculate age group based on child's date of birth
     * Uses UK Early Years Foundation Stage (EYFS) aligned age groups
     */
    private function calculate_age_group($dob) {
        $birth_date = new DateTime($dob);
        $today = new DateTime();
        $age = $birth_date->diff($today);

        $years = $age->y;
        $months = $age->m;
        $total_months = ($years * 12) + $months;

        if ($total_months < 12) {
            return '0-12m'; // Under 1 year
        } elseif ($years >= 1 && $years < 2) {
            return '1-2y'; // 1-2 years
        } elseif ($years >= 2 && $years < 3) {
            return '2-3y'; // 2-3 years
        } elseif ($years >= 3 && $years < 4) {
            return '3-4y'; // 3-4 years (Pre-School)
        } else {
            return '4-5y'; // 4-5 years (Reception Ready)
        }
    }

    /**
     * Add settings menu to Gravity Forms
     */
    public function add_settings_menu($menu_items, $form_id) {
        $menu_items[] = array(
            'name' => 'nwl_settings',
            'label' => __('Waiting List', 'nursery-waiting-list'),
            'icon' => 'gform-icon--people',
        );
        return $menu_items;
    }

    /**
     * Settings page content
     */
    public function settings_page() {
        $form_id = absint(rgget('id'));
        $form = GFAPI::get_form($form_id);
        
        if (!$form) {
            echo '<div class="notice notice-error"><p>' . esc_html__('Form not found.', 'nursery-waiting-list') . '</p></div>';
            return;
        }

        // Save settings
        if (isset($_POST['nwl_settings_nonce']) && wp_verify_nonce($_POST['nwl_settings_nonce'], 'nwl_settings')) {
            $this->save_form_settings($form_id, $_POST);
            echo '<div class="notice notice-success"><p>' . esc_html__('Settings saved.', 'nursery-waiting-list') . '</p></div>';
        }

        $settings = $this->get_form_settings($form_id);
        $fields = $this->get_form_fields($form);
        
        ?>
        <div class="gform-settings-panel">
            <header class="gform-settings-panel__header">
                <h4 class="gform-settings-panel__title"><?php esc_html_e('Waiting List Integration', 'nursery-waiting-list'); ?></h4>
            </header>
            
            <form method="post" class="gform-settings-panel__content">
                <?php wp_nonce_field('nwl_settings', 'nwl_settings_nonce'); ?>
                
                <div class="gform-settings-field">
                    <label class="gform-settings-label">
                        <input type="checkbox" name="enabled" value="1" <?php checked(!empty($settings['enabled'])); ?>>
                        <?php esc_html_e('Enable waiting list integration for this form', 'nursery-waiting-list'); ?>
                    </label>
                </div>

                <div class="gform-settings-field">
                    <label class="gform-settings-label"><?php esc_html_e('Default Status', 'nursery-waiting-list'); ?></label>
                    <select name="default_status">
                        <?php foreach (NWL_Database::get_statuses() as $key => $label) : ?>
                            <option value="<?php echo esc_attr($key); ?>" <?php selected($settings['default_status'] ?? 'pending', $key); ?>>
                                <?php echo esc_html($label); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <h4><?php esc_html_e('Field Mapping', 'nursery-waiting-list'); ?></h4>
                <p class="description"><?php esc_html_e('Map your form fields to waiting list entry fields.', 'nursery-waiting-list'); ?></p>

                <table class="widefat" style="margin-top: 15px;">
                    <thead>
                        <tr>
                            <th><?php esc_html_e('Waiting List Field', 'nursery-waiting-list'); ?></th>
                            <th><?php esc_html_e('Form Field', 'nursery-waiting-list'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $mapping_fields = $this->get_mapping_fields();
                        $field_map = isset($settings['field_map']) ? $settings['field_map'] : array();
                        
                        foreach ($mapping_fields as $group_name => $group_fields) :
                            ?>
                            <tr>
                                <td colspan="2"><strong><?php echo esc_html($group_name); ?></strong></td>
                            </tr>
                            <?php foreach ($group_fields as $key => $label) : ?>
                                <tr>
                                    <td><?php echo esc_html($label); ?></td>
                                    <td>
                                        <select name="field_map[<?php echo esc_attr($key); ?>]" style="width: 100%;">
                                            <option value=""><?php esc_html_e('— Select —', 'nursery-waiting-list'); ?></option>
                                            <?php foreach ($fields as $field) : ?>
                                                <option value="<?php echo esc_attr($field['id']); ?>" <?php selected($field_map[$key] ?? '', $field['id']); ?>>
                                                    <?php echo esc_html($field['label']); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endforeach; ?>
                    </tbody>
                </table>

                <p style="margin-top: 20px;">
                    <input type="submit" class="button button-primary" value="<?php esc_attr_e('Save Settings', 'nursery-waiting-list'); ?>">
                </p>
            </form>
        </div>
        <?php
    }

    /**
     * Get mapping fields
     */
    private function get_mapping_fields() {
        return array(
            __('Child Information', 'nursery-waiting-list') => array(
                'child_name' => __('Child Name (Name field)', 'nursery-waiting-list'),
                'child_dob' => __('Child Date of Birth', 'nursery-waiting-list'),
                'child_place_of_birth' => __('Child Place of Birth (Address field - maps to City & Country)', 'nursery-waiting-list'),
                'child_first_language' => __('Child First Language', 'nursery-waiting-list'),
                'child_ethnicity' => __('Ethnicity', 'nursery-waiting-list'),
                'child_gender' => __('Child Gender', 'nursery-waiting-list'),
                'child_attended_other_nursery' => __('Has your child attended another nursery?', 'nursery-waiting-list'),
                'child_previous_nursery_name' => __('Enter name of nursery (shown if Yes above)', 'nursery-waiting-list'),
                'preferred_start_date' => __('What date would you like to start?', 'nursery-waiting-list'),
                'days_required' => __('Please select your preferred days', 'nursery-waiting-list'),
            ),
            __('Parent/Guardian Information', 'nursery-waiting-list') => array(
                'parent_name' => __('Parent Name (Name field)', 'nursery-waiting-list'),
                'parent_dob' => __('Parent Date of Birth', 'nursery-waiting-list'),
                'parent_national_insurance' => __('Parent National Insurance Number', 'nursery-waiting-list'),
                'share_code' => __('Share Code', 'nursery-waiting-list'),
                'parent_email' => __('Parent Email', 'nursery-waiting-list'),
                'parent_phone' => __('Parent Phone Number', 'nursery-waiting-list'),
                'parent_address' => __('Parent Address (Address field - maps to Address, City, Postcode)', 'nursery-waiting-list'),
                'parental_responsibility' => __('Parental Responsibility', 'nursery-waiting-list'),
                'relationship_to_child' => __('Relationship to Child', 'nursery-waiting-list'),
                'declaration' => __('Declaration', 'nursery-waiting-list'),
            ),
            __('Waiting List Details', 'nursery-waiting-list') => array(
                'sessions_required' => __('Sessions Required', 'nursery-waiting-list'),
                'hours_per_week' => __('Hours Per Week', 'nursery-waiting-list'),
            ),
        );
    }

    /**
     * Get form fields for dropdown
     */
    private function get_form_fields($form) {
        $fields = array();
        
        foreach ($form['fields'] as $field) {
            // Skip certain field types
            if (in_array($field->type, array('html', 'section', 'page', 'captcha'))) {
                continue;
            }
            
            $fields[] = array(
                'id' => $field->id,
                'label' => $field->label,
                'type' => $field->type,
            );
        }
        
        return $fields;
    }

    /**
     * Get form settings
     */
    public function get_form_settings($form_id) {
        $settings = get_option('nwl_gf_settings_' . $form_id, array());
        return wp_parse_args($settings, array(
            'enabled' => false,
            'default_status' => 'pending',
            'field_map' => array(),
        ));
    }

    /**
     * Save form settings
     */
    private function save_form_settings($form_id, $data) {
        $settings = array(
            'enabled' => !empty($data['enabled']),
            'default_status' => sanitize_text_field($data['default_status'] ?? 'pending'),
            'field_map' => array(),
        );

        if (isset($data['field_map']) && is_array($data['field_map'])) {
            foreach ($data['field_map'] as $key => $value) {
                $settings['field_map'][sanitize_key($key)] = sanitize_text_field($value);
            }
        }

        update_option('nwl_gf_settings_' . $form_id, $settings);
    }

    /**
     * Add custom merge tags
     */
    public function add_merge_tags($merge_tags, $form_id, $fields, $element_id) {
        $merge_tags[] = array(
            'label' => __('Waiting List Number', 'nursery-waiting-list'),
            'tag' => '{waiting_list_number}',
        );
        
        return $merge_tags;
    }

    /**
     * Replace custom merge tags
     */
    public function replace_merge_tags($text, $form, $entry, $url_encode, $esc_html, $nl2br, $format) {
        if (strpos($text, '{waiting_list_number}') !== false) {
            $wl_number = gform_get_meta($entry['id'], 'nwl_waiting_list_number');
            $text = str_replace('{waiting_list_number}', $wl_number ? $wl_number : '', $text);
        }
        
        return $text;
    }

    /**
     * Get linked forms
     */
    public function get_linked_forms() {
        $linked = array();
        
        if (!class_exists('GFAPI')) {
            return $linked;
        }

        $forms = GFAPI::get_forms();
        
        foreach ($forms as $form) {
            $settings = $this->get_form_settings($form['id']);
            if (!empty($settings['enabled'])) {
                $linked[] = array(
                    'id' => $form['id'],
                    'title' => $form['title'],
                );
            }
        }
        
        return $linked;
    }
}
