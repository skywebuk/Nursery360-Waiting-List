<?php
/**
 * Database handler class
 */

if (!defined('ABSPATH')) {
    exit;
}

class NWL_Database {

    private static $instance = null;

    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        add_action('plugins_loaded', array($this, 'check_db_version'));
    }

    /**
     * Check and update database version
     */
    public function check_db_version() {
        $installed_version = get_option('nwl_db_version', '0');
        if (version_compare($installed_version, NWL_VERSION, '<')) {
            self::create_tables();
            update_option('nwl_db_version', NWL_VERSION);
        }
    }

    /**
     * Create database tables
     */
    public static function create_tables() {
        global $wpdb;
        
        $charset_collate = $wpdb->get_charset_collate();
        
        // Main entries table
        $table_entries = $wpdb->prefix . NWL_TABLE_ENTRIES;
        $sql_entries = "CREATE TABLE $table_entries (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            waiting_list_number varchar(20) NOT NULL,
            gravity_form_id bigint(20) UNSIGNED DEFAULT NULL,
            gravity_entry_id bigint(20) UNSIGNED DEFAULT NULL,
            
            -- Child Information
            child_first_name varchar(100) NOT NULL,
            child_last_name varchar(100) NOT NULL,
            child_dob date DEFAULT NULL,
            child_gender varchar(20) DEFAULT NULL,
            
            -- Parent/Guardian Information
            parent_first_name varchar(100) NOT NULL,
            parent_last_name varchar(100) NOT NULL,
            parent_email varchar(255) NOT NULL,
            parent_phone varchar(50) DEFAULT NULL,
            parent_mobile varchar(50) DEFAULT NULL,
            parent_address_line1 varchar(255) DEFAULT NULL,
            parent_address_line2 varchar(255) DEFAULT NULL,
            parent_city varchar(100) DEFAULT NULL,
            parent_postcode varchar(20) DEFAULT NULL,
            
            -- Second Parent/Guardian (optional)
            parent2_first_name varchar(100) DEFAULT NULL,
            parent2_last_name varchar(100) DEFAULT NULL,
            parent2_email varchar(255) DEFAULT NULL,
            parent2_phone varchar(50) DEFAULT NULL,
            
            -- Waiting List Details
            room_requested varchar(100) DEFAULT NULL,
            age_group varchar(50) DEFAULT NULL,
            preferred_start_date date DEFAULT NULL,
            days_required varchar(255) DEFAULT NULL,
            sessions_required varchar(255) DEFAULT NULL,
            hours_per_week int(11) DEFAULT NULL,
            
            -- Funding
            funding_type varchar(100) DEFAULT NULL,
            eligible_for_30_hours enum('yes','no','unknown') DEFAULT 'unknown',
            
            -- Additional Information
            additional_needs text DEFAULT NULL,
            allergies text DEFAULT NULL,
            medical_conditions text DEFAULT NULL,
            how_heard varchar(255) DEFAULT NULL,
            
            -- Status and Tracking
            status varchar(50) NOT NULL DEFAULT 'pending',
            priority enum('normal','high','urgent') DEFAULT 'normal',
            offer_deadline date DEFAULT NULL,
            offer_response varchar(50) DEFAULT NULL,
            
            -- Internal Notes (staff only)
            internal_notes text DEFAULT NULL,
            
            -- Public Notes (can be shared with parents)
            public_notes text DEFAULT NULL,
            
            -- Meta
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            created_by bigint(20) UNSIGNED DEFAULT NULL,
            updated_by bigint(20) UNSIGNED DEFAULT NULL,
            
            -- GDPR
            consent_given tinyint(1) DEFAULT 1,
            consent_date datetime DEFAULT NULL,
            data_retention_date date DEFAULT NULL,
            deletion_requested tinyint(1) DEFAULT 0,
            deletion_requested_date datetime DEFAULT NULL,
            
            PRIMARY KEY (id),
            UNIQUE KEY waiting_list_number (waiting_list_number),
            KEY parent_email (parent_email),
            KEY parent_phone (parent_phone),
            KEY parent_mobile (parent_mobile),
            KEY status (status),
            KEY room_requested (room_requested),
            KEY age_group (age_group),
            KEY created_at (created_at),
            KEY gravity_entry_id (gravity_entry_id)
        ) $charset_collate;";

        // Notes table for detailed note history
        $table_notes = $wpdb->prefix . NWL_TABLE_NOTES;
        $sql_notes = "CREATE TABLE $table_notes (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            entry_id bigint(20) UNSIGNED NOT NULL,
            note_type enum('internal','public','system','email') DEFAULT 'internal',
            note_content text NOT NULL,
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            created_by bigint(20) UNSIGNED DEFAULT NULL,
            
            PRIMARY KEY (id),
            KEY entry_id (entry_id),
            KEY note_type (note_type),
            KEY created_at (created_at)
        ) $charset_collate;";

        // Email logs table
        $table_emails = $wpdb->prefix . NWL_TABLE_EMAILS;
        $sql_emails = "CREATE TABLE $table_emails (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            entry_id bigint(20) UNSIGNED DEFAULT NULL,
            email_type varchar(50) NOT NULL,
            template_id bigint(20) UNSIGNED DEFAULT NULL,
            recipient_email varchar(255) NOT NULL,
            subject varchar(500) NOT NULL,
            body longtext NOT NULL,
            status enum('sent','failed','queued') DEFAULT 'queued',
            error_message text DEFAULT NULL,
            sent_at datetime DEFAULT NULL,
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            created_by bigint(20) UNSIGNED DEFAULT NULL,
            
            PRIMARY KEY (id),
            KEY entry_id (entry_id),
            KEY email_type (email_type),
            KEY status (status),
            KEY created_at (created_at)
        ) $charset_collate;";

        // Email templates table
        $table_templates = $wpdb->prefix . NWL_TABLE_TEMPLATES;
        $sql_templates = "CREATE TABLE $table_templates (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            template_key varchar(100) NOT NULL,
            template_name varchar(255) NOT NULL,
            subject varchar(500) NOT NULL,
            body longtext NOT NULL,
            is_active tinyint(1) DEFAULT 1,
            is_system tinyint(1) DEFAULT 0,
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            
            PRIMARY KEY (id),
            UNIQUE KEY template_key (template_key)
        ) $charset_collate;";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql_entries);
        dbDelta($sql_notes);
        dbDelta($sql_emails);
        dbDelta($sql_templates);
    }

    /**
     * Insert default email templates
     */
    public static function insert_default_templates() {
        global $wpdb;
        
        $table = $wpdb->prefix . NWL_TABLE_TEMPLATES;
        
        $templates = array(
            array(
                'template_key' => 'registration_confirmation',
                'template_name' => 'Registration Confirmation',
                'subject' => 'Welcome to Our Waiting List - Reference: {{waiting_list_number}}',
                'body' => self::get_default_registration_template(),
                'is_system' => 1,
            ),
            array(
                'template_key' => 'place_offered',
                'template_name' => 'Place Offered',
                'subject' => 'A Place Has Become Available - {{child_name}}',
                'body' => self::get_default_offer_template(),
                'is_system' => 1,
            ),
            array(
                'template_key' => 'general_update',
                'template_name' => 'General Update',
                'subject' => 'Waiting List Update',
                'body' => self::get_default_update_template(),
                'is_system' => 1,
            ),
            array(
                'template_key' => 'follow_up',
                'template_name' => 'Follow Up Reminder',
                'subject' => 'Following Up On Your Waiting List Application',
                'body' => self::get_default_followup_template(),
                'is_system' => 1,
            ),
            array(
                'template_key' => 'status_change',
                'template_name' => 'Status Change Notification',
                'subject' => 'Update on Your Waiting List Application',
                'body' => self::get_default_status_template(),
                'is_system' => 1,
            ),
        );

        foreach ($templates as $template) {
            $exists = $wpdb->get_var($wpdb->prepare(
                "SELECT id FROM $table WHERE template_key = %s",
                $template['template_key']
            ));
            
            if (!$exists) {
                $wpdb->insert($table, $template);
            }
        }
    }

    /**
     * Default registration confirmation template
     */
    private static function get_default_registration_template() {
        return '<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
        .container { max-width: 600px; margin: 0 auto; padding: 20px; }
        .header { background: #4A90A4; color: white; padding: 20px; text-align: center; border-radius: 5px 5px 0 0; }
        .content { background: #f9f9f9; padding: 20px; border: 1px solid #ddd; }
        .details { background: white; padding: 15px; margin: 15px 0; border-left: 4px solid #4A90A4; }
        .footer { text-align: center; padding: 20px; font-size: 12px; color: #666; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Welcome to Our Waiting List</h1>
        </div>
        <div class="content">
            <p>Dear {{parent_name}},</p>
            
            <p>Thank you for registering <strong>{{child_name}}</strong> on our nursery waiting list. We have received your application and will be in touch as soon as a place becomes available.</p>
            
            <div class="details">
                <h3>Your Registration Details</h3>
                <p><strong>Waiting List Number:</strong> {{waiting_list_number}}</p>
                <p><strong>Child\'s Name:</strong> {{child_name}}</p>
                <p><strong>Date Added:</strong> {{date_added}}</p>
                <p><strong>Room/Age Group Requested:</strong> {{room_requested}}</p>
                {{#preferred_start_date}}
                <p><strong>Preferred Start Date:</strong> {{preferred_start_date}}</p>
                {{/preferred_start_date}}
            </div>
            
            {{#public_notes}}
            <div class="details">
                <h3>Important Information</h3>
                <p>{{public_notes}}</p>
            </div>
            {{/public_notes}}
            
            <h3>What Happens Next?</h3>
            <ul>
                <li>Your application is now on our waiting list</li>
                <li>We will contact you when a suitable place becomes available</li>
                <li>You can check your waiting list status anytime at: {{stats_page_url}}</li>
                <li>Please keep your contact details up to date</li>
            </ul>
            
            <p>If you have any questions, please don\'t hesitate to contact us.</p>
            
            <p>Kind regards,<br>{{nursery_name}}</p>
        </div>
        <div class="footer">
            <p>{{nursery_name}} | {{nursery_address}}</p>
            <p>Phone: {{nursery_phone}} | Email: {{nursery_email}}</p>
        </div>
    </div>
</body>
</html>';
    }

    /**
     * Default place offer template
     */
    private static function get_default_offer_template() {
        return '<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
        .container { max-width: 600px; margin: 0 auto; padding: 20px; }
        .header { background: #28a745; color: white; padding: 20px; text-align: center; border-radius: 5px 5px 0 0; }
        .content { background: #f9f9f9; padding: 20px; border: 1px solid #ddd; }
        .details { background: white; padding: 15px; margin: 15px 0; border-left: 4px solid #28a745; }
        .urgent { background: #fff3cd; padding: 15px; margin: 15px 0; border-left: 4px solid #ffc107; }
        .footer { text-align: center; padding: 20px; font-size: 12px; color: #666; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>🎉 Great News - A Place is Available!</h1>
        </div>
        <div class="content">
            <p>Dear {{parent_name}},</p>
            
            <p>We are delighted to inform you that a place has become available for <strong>{{child_name}}</strong> at our nursery.</p>
            
            <div class="details">
                <h3>Place Details</h3>
                <p><strong>Room:</strong> {{room_requested}}</p>
                <p><strong>Proposed Start Date:</strong> {{preferred_start_date}}</p>
            </div>
            
            {{#offer_deadline}}
            <div class="urgent">
                <h3>⚠️ Response Required</h3>
                <p>Please respond to this offer by <strong>{{offer_deadline}}</strong>.</p>
                <p>If we do not hear from you by this date, we may need to offer the place to another family.</p>
            </div>
            {{/offer_deadline}}
            
            <h3>Next Steps</h3>
            <ol>
                <li>Contact us to confirm you wish to accept this place</li>
                <li>We will arrange a settling-in visit for {{child_name}}</li>
                <li>Complete any remaining registration paperwork</li>
            </ol>
            
            {{#public_notes}}
            <div class="details">
                <p>{{public_notes}}</p>
            </div>
            {{/public_notes}}
            
            <p>We look forward to welcoming {{child_name}} to our nursery family!</p>
            
            <p>Kind regards,<br>{{nursery_name}}</p>
        </div>
        <div class="footer">
            <p>{{nursery_name}} | {{nursery_address}}</p>
            <p>Phone: {{nursery_phone}} | Email: {{nursery_email}}</p>
        </div>
    </div>
</body>
</html>';
    }

    /**
     * Default general update template
     */
    private static function get_default_update_template() {
        return '<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
        .container { max-width: 600px; margin: 0 auto; padding: 20px; }
        .header { background: #4A90A4; color: white; padding: 20px; text-align: center; border-radius: 5px 5px 0 0; }
        .content { background: #f9f9f9; padding: 20px; border: 1px solid #ddd; }
        .footer { text-align: center; padding: 20px; font-size: 12px; color: #666; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Waiting List Update</h1>
        </div>
        <div class="content">
            <p>Dear {{parent_name}},</p>
            
            {{message_content}}
            
            <p>Your waiting list reference: <strong>{{waiting_list_number}}</strong></p>
            
            <p>You can check your waiting list status at any time: {{stats_page_url}}</p>
            
            <p>Kind regards,<br>{{nursery_name}}</p>
        </div>
        <div class="footer">
            <p>{{nursery_name}} | {{nursery_address}}</p>
            <p>Phone: {{nursery_phone}} | Email: {{nursery_email}}</p>
        </div>
    </div>
</body>
</html>';
    }

    /**
     * Default follow-up template
     */
    private static function get_default_followup_template() {
        return '<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
        .container { max-width: 600px; margin: 0 auto; padding: 20px; }
        .header { background: #6c757d; color: white; padding: 20px; text-align: center; border-radius: 5px 5px 0 0; }
        .content { background: #f9f9f9; padding: 20px; border: 1px solid #ddd; }
        .details { background: white; padding: 15px; margin: 15px 0; border-left: 4px solid #6c757d; }
        .footer { text-align: center; padding: 20px; font-size: 12px; color: #666; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Waiting List Follow-Up</h1>
        </div>
        <div class="content">
            <p>Dear {{parent_name}},</p>
            
            <p>We are getting in touch regarding your waiting list application for <strong>{{child_name}}</strong>.</p>
            
            <div class="details">
                <p><strong>Waiting List Number:</strong> {{waiting_list_number}}</p>
                <p><strong>Date Added:</strong> {{date_added}}</p>
                <p><strong>Current Status:</strong> {{status}}</p>
            </div>
            
            {{message_content}}
            
            <p>Please let us know if your circumstances have changed or if you wish to update any details.</p>
            
            <p>Kind regards,<br>{{nursery_name}}</p>
        </div>
        <div class="footer">
            <p>{{nursery_name}} | {{nursery_address}}</p>
            <p>Phone: {{nursery_phone}} | Email: {{nursery_email}}</p>
        </div>
    </div>
</body>
</html>';
    }

    /**
     * Default status change template
     */
    private static function get_default_status_template() {
        return '<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
        .container { max-width: 600px; margin: 0 auto; padding: 20px; }
        .header { background: #4A90A4; color: white; padding: 20px; text-align: center; border-radius: 5px 5px 0 0; }
        .content { background: #f9f9f9; padding: 20px; border: 1px solid #ddd; }
        .status-badge { display: inline-block; padding: 5px 15px; border-radius: 15px; background: #4A90A4; color: white; font-weight: bold; }
        .footer { text-align: center; padding: 20px; font-size: 12px; color: #666; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Application Status Update</h1>
        </div>
        <div class="content">
            <p>Dear {{parent_name}},</p>
            
            <p>The status of your waiting list application for <strong>{{child_name}}</strong> has been updated.</p>
            
            <p style="text-align: center; margin: 20px 0;">
                <span class="status-badge">{{status}}</span>
            </p>
            
            {{#public_notes}}
            <p>{{public_notes}}</p>
            {{/public_notes}}
            
            <p>Your waiting list reference: <strong>{{waiting_list_number}}</strong></p>
            
            <p>If you have any questions about this update, please contact us.</p>
            
            <p>Kind regards,<br>{{nursery_name}}</p>
        </div>
        <div class="footer">
            <p>{{nursery_name}} | {{nursery_address}}</p>
            <p>Phone: {{nursery_phone}} | Email: {{nursery_email}}</p>
        </div>
    </div>
</body>
</html>';
    }

    /**
     * Get table name with prefix
     */
    public static function get_table_name($table) {
        global $wpdb;
        return $wpdb->prefix . $table;
    }

    /**
     * Get all statuses
     */
    public static function get_statuses() {
        return apply_filters('nwl_statuses', array(
            'pending' => __('Pending', 'nursery-waiting-list'),
            'contacted' => __('Contacted', 'nursery-waiting-list'),
            'waitlisted' => __('On Waiting List', 'nursery-waiting-list'),
            'offered' => __('Place Offered', 'nursery-waiting-list'),
            'accepted' => __('Accepted', 'nursery-waiting-list'),
            'declined' => __('Declined', 'nursery-waiting-list'),
            'enrolled' => __('Enrolled', 'nursery-waiting-list'),
            'removed' => __('Removed', 'nursery-waiting-list'),
        ));
    }

    /**
     * Get all rooms
     */
    public static function get_rooms() {
        $default_rooms = array(
            'baby' => __('Baby Room (0-12 months)', 'nursery-waiting-list'),
            'toddler' => __('Toddler Room (1-2 years)', 'nursery-waiting-list'),
            'tweenies' => __('Tweenies Room (2-3 years)', 'nursery-waiting-list'),
            'preschool' => __('Pre-School Room (3-4 years)', 'nursery-waiting-list'),
        );
        
        $custom_rooms = get_option('nwl_custom_rooms', array());
        
        return apply_filters('nwl_rooms', array_merge($default_rooms, $custom_rooms));
    }

    /**
     * Get all age groups
     */
    public static function get_age_groups() {
        return apply_filters('nwl_age_groups', array(
            '0-12m' => __('0-12 months', 'nursery-waiting-list'),
            '12-24m' => __('12-24 months', 'nursery-waiting-list'),
            '2-3y' => __('2-3 years', 'nursery-waiting-list'),
            '3-4y' => __('3-4 years', 'nursery-waiting-list'),
            '4-5y' => __('4-5 years', 'nursery-waiting-list'),
        ));
    }

    /**
     * Get priority levels
     */
    public static function get_priorities() {
        return apply_filters('nwl_priorities', array(
            'normal' => __('Normal', 'nursery-waiting-list'),
            'high' => __('High', 'nursery-waiting-list'),
            'urgent' => __('Urgent', 'nursery-waiting-list'),
        ));
    }
}
