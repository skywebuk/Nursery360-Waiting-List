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

            -- Parent/Carer Information
            parent_first_name varchar(100) NOT NULL,
            parent_last_name varchar(100) NOT NULL,
            parent_email varchar(255) NOT NULL,
            parent_phone varchar(50) DEFAULT NULL,
            parent_mobile varchar(50) DEFAULT NULL,
            parent_address_line1 varchar(255) DEFAULT NULL,
            parent_address_line2 varchar(255) DEFAULT NULL,
            parent_city varchar(100) DEFAULT NULL,
            parent_postcode varchar(20) DEFAULT NULL,

            -- Waiting List Details
            age_group varchar(50) DEFAULT NULL,
            preferred_start_date date DEFAULT NULL,
            days_required varchar(255) DEFAULT NULL,
            sessions_required varchar(255) DEFAULT NULL,
            hours_per_week int(11) DEFAULT NULL,

            -- Status and Tracking
            status varchar(50) NOT NULL DEFAULT 'pending',
            priority enum('normal','high','urgent') DEFAULT 'normal',
            offer_deadline date DEFAULT NULL,
            offer_response varchar(50) DEFAULT NULL,

            -- Internal Notes (staff only)
            internal_notes text DEFAULT NULL,

            -- Public Notes (can be shared with parents/carers)
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
     * Default registration confirmation template - Modern UK Nursery Style
     */
    private static function get_default_registration_template() {
        return '<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Registration Confirmation</title>
</head>
<body style="margin: 0; padding: 0; font-family: -apple-system, BlinkMacSystemFont, \'Segoe UI\', Roboto, \'Helvetica Neue\', Arial, sans-serif; background-color: #f4f7fa; line-height: 1.6;">
    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="background-color: #f4f7fa;">
        <tr>
            <td align="center" style="padding: 40px 20px;">
                <table role="presentation" width="600" cellspacing="0" cellpadding="0" style="max-width: 600px; width: 100%;">
                    <!-- Header -->
                    <tr>
                        <td style="background: linear-gradient(135deg, #4A90A4 0%, #357a8f 100%); padding: 40px 30px; text-align: center; border-radius: 12px 12px 0 0;">
                            <h1 style="color: #ffffff; margin: 0; font-size: 28px; font-weight: 600;">Welcome to Our Nursery</h1>
                            <p style="color: rgba(255,255,255,0.9); margin: 10px 0 0; font-size: 16px;">Your waiting list application has been received</p>
                        </td>
                    </tr>
                    <!-- Content -->
                    <tr>
                        <td style="background: #ffffff; padding: 40px 30px; border-radius: 0 0 12px 12px; box-shadow: 0 4px 20px rgba(0,0,0,0.08);">
                            <p style="color: #333; font-size: 16px; margin: 0 0 20px;">Dear {{parent_name}},</p>

                            <p style="color: #555; font-size: 15px; margin: 0 0 25px;">Thank you for registering <strong style="color: #333;">{{child_name}}</strong> on our nursery waiting list. We are delighted to have received your application and will be in touch as soon as a suitable place becomes available.</p>

                            <!-- Details Card -->
                            <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="background: #f8fafc; border-radius: 8px; margin: 25px 0;">
                                <tr>
                                    <td style="padding: 25px; border-left: 4px solid #4A90A4;">
                                        <h3 style="color: #333; margin: 0 0 15px; font-size: 16px;">Your Registration Details</h3>
                                        <table role="presentation" width="100%" cellspacing="0" cellpadding="0">
                                            <tr>
                                                <td style="padding: 8px 0; color: #666; font-size: 14px;">Reference Number:</td>
                                                <td style="padding: 8px 0; color: #333; font-size: 14px; font-weight: 600;">{{waiting_list_number}}</td>
                                            </tr>
                                            <tr>
                                                <td style="padding: 8px 0; color: #666; font-size: 14px;">Child\'s Name:</td>
                                                <td style="padding: 8px 0; color: #333; font-size: 14px; font-weight: 600;">{{child_name}}</td>
                                            </tr>
                                            <tr>
                                                <td style="padding: 8px 0; color: #666; font-size: 14px;">Date Registered:</td>
                                                <td style="padding: 8px 0; color: #333; font-size: 14px; font-weight: 600;">{{date_added}}</td>
                                            </tr>
                                            {{#preferred_start_date}}
                                            <tr>
                                                <td style="padding: 8px 0; color: #666; font-size: 14px;">Preferred Start:</td>
                                                <td style="padding: 8px 0; color: #333; font-size: 14px; font-weight: 600;">{{preferred_start_date}}</td>
                                            </tr>
                                            {{/preferred_start_date}}
                                        </table>
                                    </td>
                                </tr>
                            </table>

                            {{#public_notes}}
                            <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="background: #fef3cd; border-radius: 8px; margin: 25px 0;">
                                <tr>
                                    <td style="padding: 20px;">
                                        <p style="color: #856404; margin: 0; font-size: 14px;"><strong>Important:</strong> {{public_notes}}</p>
                                    </td>
                                </tr>
                            </table>
                            {{/public_notes}}

                            <h3 style="color: #333; margin: 25px 0 15px; font-size: 16px;">What Happens Next?</h3>
                            <ul style="color: #555; font-size: 15px; margin: 0; padding-left: 20px;">
                                <li style="margin-bottom: 10px;">Your application is now on our waiting list</li>
                                <li style="margin-bottom: 10px;">We will contact you when a suitable place becomes available</li>
                                <li style="margin-bottom: 10px;">You can check your status online at any time</li>
                                <li style="margin-bottom: 10px;">Please keep your contact details up to date</li>
                            </ul>

                            <!-- CTA Button -->
                            <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="margin: 30px 0;">
                                <tr>
                                    <td align="center">
                                        <a href="{{stats_page_url}}" style="display: inline-block; background: linear-gradient(135deg, #4A90A4 0%, #357a8f 100%); color: #ffffff; text-decoration: none; padding: 14px 35px; border-radius: 8px; font-size: 15px; font-weight: 600;">Check Your Status</a>
                                    </td>
                                </tr>
                            </table>

                            <p style="color: #555; font-size: 15px; margin: 25px 0 0;">If you have any questions, please don\'t hesitate to get in touch.</p>
                            <p style="color: #333; font-size: 15px; margin: 20px 0 0;">Kind regards,<br><strong>{{nursery_name}}</strong></p>
                        </td>
                    </tr>
                    <!-- Footer -->
                    <tr>
                        <td style="padding: 30px; text-align: center;">
                            <p style="color: #888; font-size: 13px; margin: 0 0 5px;">{{nursery_name}}</p>
                            <p style="color: #888; font-size: 13px; margin: 0 0 5px;">{{nursery_address}}</p>
                            <p style="color: #888; font-size: 13px; margin: 0;">Tel: {{nursery_phone}} | Email: {{nursery_email}}</p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>';
    }

    /**
     * Default place offer template - Modern UK Nursery Style
     */
    private static function get_default_offer_template() {
        return '<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Place Available</title>
</head>
<body style="margin: 0; padding: 0; font-family: -apple-system, BlinkMacSystemFont, \'Segoe UI\', Roboto, \'Helvetica Neue\', Arial, sans-serif; background-color: #f4f7fa; line-height: 1.6;">
    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="background-color: #f4f7fa;">
        <tr>
            <td align="center" style="padding: 40px 20px;">
                <table role="presentation" width="600" cellspacing="0" cellpadding="0" style="max-width: 600px; width: 100%;">
                    <!-- Header -->
                    <tr>
                        <td style="background: linear-gradient(135deg, #28a745 0%, #1e7b34 100%); padding: 40px 30px; text-align: center; border-radius: 12px 12px 0 0;">
                            <h1 style="color: #ffffff; margin: 0; font-size: 28px; font-weight: 600;">Great News!</h1>
                            <p style="color: rgba(255,255,255,0.9); margin: 10px 0 0; font-size: 16px;">A place has become available at our nursery</p>
                        </td>
                    </tr>
                    <!-- Content -->
                    <tr>
                        <td style="background: #ffffff; padding: 40px 30px; border-radius: 0 0 12px 12px; box-shadow: 0 4px 20px rgba(0,0,0,0.08);">
                            <p style="color: #333; font-size: 16px; margin: 0 0 20px;">Dear {{parent_name}},</p>

                            <p style="color: #555; font-size: 15px; margin: 0 0 25px;">We are delighted to inform you that a place has become available for <strong style="color: #333;">{{child_name}}</strong> at our nursery.</p>

                            <!-- Details Card -->
                            <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="background: #f8fafc; border-radius: 8px; margin: 25px 0;">
                                <tr>
                                    <td style="padding: 25px; border-left: 4px solid #28a745;">
                                        <h3 style="color: #333; margin: 0 0 15px; font-size: 16px;">Place Details</h3>
                                        <table role="presentation" width="100%" cellspacing="0" cellpadding="0">
                                            <tr>
                                                <td style="padding: 8px 0; color: #666; font-size: 14px;">Reference:</td>
                                                <td style="padding: 8px 0; color: #333; font-size: 14px; font-weight: 600;">{{waiting_list_number}}</td>
                                            </tr>
                                            {{#preferred_start_date}}
                                            <tr>
                                                <td style="padding: 8px 0; color: #666; font-size: 14px;">Proposed Start Date:</td>
                                                <td style="padding: 8px 0; color: #333; font-size: 14px; font-weight: 600;">{{preferred_start_date}}</td>
                                            </tr>
                                            {{/preferred_start_date}}
                                        </table>
                                    </td>
                                </tr>
                            </table>

                            {{#offer_deadline}}
                            <!-- Deadline Warning -->
                            <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="background: #fff3cd; border-radius: 8px; margin: 25px 0; border-left: 4px solid #ffc107;">
                                <tr>
                                    <td style="padding: 20px;">
                                        <h3 style="color: #856404; margin: 0 0 10px; font-size: 15px;">Response Required</h3>
                                        <p style="color: #856404; margin: 0; font-size: 14px;">Please respond to this offer by <strong>{{offer_deadline}}</strong>. If we do not hear from you by this date, we may need to offer the place to another family.</p>
                                    </td>
                                </tr>
                            </table>
                            {{/offer_deadline}}

                            <h3 style="color: #333; margin: 25px 0 15px; font-size: 16px;">Next Steps</h3>
                            <ol style="color: #555; font-size: 15px; margin: 0; padding-left: 20px;">
                                <li style="margin-bottom: 10px;">Contact us to confirm you wish to accept this place</li>
                                <li style="margin-bottom: 10px;">We will arrange a settling-in session for {{child_name}}</li>
                                <li style="margin-bottom: 10px;">Complete any remaining registration paperwork</li>
                            </ol>

                            {{#public_notes}}
                            <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="background: #e8f5e9; border-radius: 8px; margin: 25px 0;">
                                <tr>
                                    <td style="padding: 20px;">
                                        <p style="color: #2e7d32; margin: 0; font-size: 14px;">{{public_notes}}</p>
                                    </td>
                                </tr>
                            </table>
                            {{/public_notes}}

                            <p style="color: #555; font-size: 15px; margin: 25px 0 0;">We very much look forward to welcoming {{child_name}} to our nursery family!</p>
                            <p style="color: #333; font-size: 15px; margin: 20px 0 0;">Kind regards,<br><strong>{{nursery_name}}</strong></p>
                        </td>
                    </tr>
                    <!-- Footer -->
                    <tr>
                        <td style="padding: 30px; text-align: center;">
                            <p style="color: #888; font-size: 13px; margin: 0 0 5px;">{{nursery_name}}</p>
                            <p style="color: #888; font-size: 13px; margin: 0 0 5px;">{{nursery_address}}</p>
                            <p style="color: #888; font-size: 13px; margin: 0;">Tel: {{nursery_phone}} | Email: {{nursery_email}}</p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>';
    }

    /**
     * Default general update template - Modern UK Nursery Style
     */
    private static function get_default_update_template() {
        return '<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Waiting List Update</title>
</head>
<body style="margin: 0; padding: 0; font-family: -apple-system, BlinkMacSystemFont, \'Segoe UI\', Roboto, \'Helvetica Neue\', Arial, sans-serif; background-color: #f4f7fa; line-height: 1.6;">
    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="background-color: #f4f7fa;">
        <tr>
            <td align="center" style="padding: 40px 20px;">
                <table role="presentation" width="600" cellspacing="0" cellpadding="0" style="max-width: 600px; width: 100%;">
                    <!-- Header -->
                    <tr>
                        <td style="background: linear-gradient(135deg, #4A90A4 0%, #357a8f 100%); padding: 40px 30px; text-align: center; border-radius: 12px 12px 0 0;">
                            <h1 style="color: #ffffff; margin: 0; font-size: 28px; font-weight: 600;">Waiting List Update</h1>
                        </td>
                    </tr>
                    <!-- Content -->
                    <tr>
                        <td style="background: #ffffff; padding: 40px 30px; border-radius: 0 0 12px 12px; box-shadow: 0 4px 20px rgba(0,0,0,0.08);">
                            <p style="color: #333; font-size: 16px; margin: 0 0 20px;">Dear {{parent_name}},</p>

                            <div style="color: #555; font-size: 15px; margin: 0 0 25px;">{{message_content}}</div>

                            <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="background: #f8fafc; border-radius: 8px; margin: 25px 0;">
                                <tr>
                                    <td style="padding: 20px; border-left: 4px solid #4A90A4;">
                                        <p style="color: #666; margin: 0; font-size: 14px;">Your reference number: <strong style="color: #333;">{{waiting_list_number}}</strong></p>
                                    </td>
                                </tr>
                            </table>

                            <!-- CTA Button -->
                            <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="margin: 30px 0;">
                                <tr>
                                    <td align="center">
                                        <a href="{{stats_page_url}}" style="display: inline-block; background: linear-gradient(135deg, #4A90A4 0%, #357a8f 100%); color: #ffffff; text-decoration: none; padding: 14px 35px; border-radius: 8px; font-size: 15px; font-weight: 600;">Check Your Status</a>
                                    </td>
                                </tr>
                            </table>

                            <p style="color: #333; font-size: 15px; margin: 20px 0 0;">Kind regards,<br><strong>{{nursery_name}}</strong></p>
                        </td>
                    </tr>
                    <!-- Footer -->
                    <tr>
                        <td style="padding: 30px; text-align: center;">
                            <p style="color: #888; font-size: 13px; margin: 0 0 5px;">{{nursery_name}}</p>
                            <p style="color: #888; font-size: 13px; margin: 0 0 5px;">{{nursery_address}}</p>
                            <p style="color: #888; font-size: 13px; margin: 0;">Tel: {{nursery_phone}} | Email: {{nursery_email}}</p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>';
    }

    /**
     * Default follow-up template - Modern UK Nursery Style
     */
    private static function get_default_followup_template() {
        return '<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Waiting List Follow-Up</title>
</head>
<body style="margin: 0; padding: 0; font-family: -apple-system, BlinkMacSystemFont, \'Segoe UI\', Roboto, \'Helvetica Neue\', Arial, sans-serif; background-color: #f4f7fa; line-height: 1.6;">
    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="background-color: #f4f7fa;">
        <tr>
            <td align="center" style="padding: 40px 20px;">
                <table role="presentation" width="600" cellspacing="0" cellpadding="0" style="max-width: 600px; width: 100%;">
                    <!-- Header -->
                    <tr>
                        <td style="background: linear-gradient(135deg, #6c757d 0%, #545b62 100%); padding: 40px 30px; text-align: center; border-radius: 12px 12px 0 0;">
                            <h1 style="color: #ffffff; margin: 0; font-size: 28px; font-weight: 600;">Keeping in Touch</h1>
                            <p style="color: rgba(255,255,255,0.9); margin: 10px 0 0; font-size: 16px;">A quick update on your waiting list application</p>
                        </td>
                    </tr>
                    <!-- Content -->
                    <tr>
                        <td style="background: #ffffff; padding: 40px 30px; border-radius: 0 0 12px 12px; box-shadow: 0 4px 20px rgba(0,0,0,0.08);">
                            <p style="color: #333; font-size: 16px; margin: 0 0 20px;">Dear {{parent_name}},</p>

                            <p style="color: #555; font-size: 15px; margin: 0 0 25px;">We are getting in touch regarding your waiting list application for <strong style="color: #333;">{{child_name}}</strong>.</p>

                            <!-- Details Card -->
                            <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="background: #f8fafc; border-radius: 8px; margin: 25px 0;">
                                <tr>
                                    <td style="padding: 25px; border-left: 4px solid #6c757d;">
                                        <table role="presentation" width="100%" cellspacing="0" cellpadding="0">
                                            <tr>
                                                <td style="padding: 8px 0; color: #666; font-size: 14px;">Reference:</td>
                                                <td style="padding: 8px 0; color: #333; font-size: 14px; font-weight: 600;">{{waiting_list_number}}</td>
                                            </tr>
                                            <tr>
                                                <td style="padding: 8px 0; color: #666; font-size: 14px;">Date Registered:</td>
                                                <td style="padding: 8px 0; color: #333; font-size: 14px; font-weight: 600;">{{date_added}}</td>
                                            </tr>
                                            <tr>
                                                <td style="padding: 8px 0; color: #666; font-size: 14px;">Current Status:</td>
                                                <td style="padding: 8px 0; color: #333; font-size: 14px; font-weight: 600;">{{status}}</td>
                                            </tr>
                                        </table>
                                    </td>
                                </tr>
                            </table>

                            <div style="color: #555; font-size: 15px; margin: 0 0 25px;">{{message_content}}</div>

                            <p style="color: #555; font-size: 15px; margin: 0 0 25px;">Please let us know if your circumstances have changed or if you wish to update any details.</p>

                            <p style="color: #333; font-size: 15px; margin: 20px 0 0;">Kind regards,<br><strong>{{nursery_name}}</strong></p>
                        </td>
                    </tr>
                    <!-- Footer -->
                    <tr>
                        <td style="padding: 30px; text-align: center;">
                            <p style="color: #888; font-size: 13px; margin: 0 0 5px;">{{nursery_name}}</p>
                            <p style="color: #888; font-size: 13px; margin: 0 0 5px;">{{nursery_address}}</p>
                            <p style="color: #888; font-size: 13px; margin: 0;">Tel: {{nursery_phone}} | Email: {{nursery_email}}</p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>';
    }

    /**
     * Default status change template - Modern UK Nursery Style
     */
    private static function get_default_status_template() {
        return '<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Status Update</title>
</head>
<body style="margin: 0; padding: 0; font-family: -apple-system, BlinkMacSystemFont, \'Segoe UI\', Roboto, \'Helvetica Neue\', Arial, sans-serif; background-color: #f4f7fa; line-height: 1.6;">
    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="background-color: #f4f7fa;">
        <tr>
            <td align="center" style="padding: 40px 20px;">
                <table role="presentation" width="600" cellspacing="0" cellpadding="0" style="max-width: 600px; width: 100%;">
                    <!-- Header -->
                    <tr>
                        <td style="background: linear-gradient(135deg, #4A90A4 0%, #357a8f 100%); padding: 40px 30px; text-align: center; border-radius: 12px 12px 0 0;">
                            <h1 style="color: #ffffff; margin: 0; font-size: 28px; font-weight: 600;">Status Update</h1>
                            <p style="color: rgba(255,255,255,0.9); margin: 10px 0 0; font-size: 16px;">Your waiting list application has been updated</p>
                        </td>
                    </tr>
                    <!-- Content -->
                    <tr>
                        <td style="background: #ffffff; padding: 40px 30px; border-radius: 0 0 12px 12px; box-shadow: 0 4px 20px rgba(0,0,0,0.08);">
                            <p style="color: #333; font-size: 16px; margin: 0 0 20px;">Dear {{parent_name}},</p>

                            <p style="color: #555; font-size: 15px; margin: 0 0 25px;">The status of your waiting list application for <strong style="color: #333;">{{child_name}}</strong> has been updated.</p>

                            <!-- Status Badge -->
                            <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="margin: 30px 0;">
                                <tr>
                                    <td align="center">
                                        <span style="display: inline-block; background: linear-gradient(135deg, #4A90A4 0%, #357a8f 100%); color: #ffffff; padding: 12px 30px; border-radius: 25px; font-size: 15px; font-weight: 600; text-transform: uppercase; letter-spacing: 1px;">{{status}}</span>
                                    </td>
                                </tr>
                            </table>

                            {{#public_notes}}
                            <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="background: #f8fafc; border-radius: 8px; margin: 25px 0;">
                                <tr>
                                    <td style="padding: 20px;">
                                        <p style="color: #555; margin: 0; font-size: 15px;">{{public_notes}}</p>
                                    </td>
                                </tr>
                            </table>
                            {{/public_notes}}

                            <p style="color: #666; font-size: 14px; margin: 25px 0 0;">Reference: <strong style="color: #333;">{{waiting_list_number}}</strong></p>

                            <p style="color: #555; font-size: 15px; margin: 25px 0 0;">If you have any questions about this update, please don\'t hesitate to contact us.</p>

                            <p style="color: #333; font-size: 15px; margin: 20px 0 0;">Kind regards,<br><strong>{{nursery_name}}</strong></p>
                        </td>
                    </tr>
                    <!-- Footer -->
                    <tr>
                        <td style="padding: 30px; text-align: center;">
                            <p style="color: #888; font-size: 13px; margin: 0 0 5px;">{{nursery_name}}</p>
                            <p style="color: #888; font-size: 13px; margin: 0 0 5px;">{{nursery_address}}</p>
                            <p style="color: #888; font-size: 13px; margin: 0;">Tel: {{nursery_phone}} | Email: {{nursery_email}}</p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
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
     * Get all statuses - UK Nursery terminology
     */
    public static function get_statuses() {
        return apply_filters('nwl_statuses', array(
            'pending' => __('Pending Review', 'nursery-waiting-list'),
            'contacted' => __('Contacted', 'nursery-waiting-list'),
            'waitlisted' => __('On Waiting List', 'nursery-waiting-list'),
            'offered' => __('Place Offered', 'nursery-waiting-list'),
            'accepted' => __('Place Accepted', 'nursery-waiting-list'),
            'declined' => __('Place Declined', 'nursery-waiting-list'),
            'enrolled' => __('Enrolled', 'nursery-waiting-list'),
            'removed' => __('Withdrawn', 'nursery-waiting-list'),
        ));
    }

    /**
     * Get all age groups - UK Early Years Foundation Stage (EYFS) aligned
     */
    public static function get_age_groups() {
        return apply_filters('nwl_age_groups', array(
            '0-12m' => __('Under 1 year', 'nursery-waiting-list'),
            '1-2y' => __('1-2 years', 'nursery-waiting-list'),
            '2-3y' => __('2-3 years', 'nursery-waiting-list'),
            '3-4y' => __('3-4 years (Pre-School)', 'nursery-waiting-list'),
            '4-5y' => __('4-5 years (Reception Ready)', 'nursery-waiting-list'),
        ));
    }

    /**
     * Get priority levels
     */
    public static function get_priorities() {
        return apply_filters('nwl_priorities', array(
            'normal' => __('Standard', 'nursery-waiting-list'),
            'high' => __('High Priority', 'nursery-waiting-list'),
            'urgent' => __('Urgent', 'nursery-waiting-list'),
        ));
    }
}
