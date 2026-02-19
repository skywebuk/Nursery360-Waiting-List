=== Nursery Waiting List ===
Contributors: skywebdesign
Tags: nursery, waiting list, childcare, gravity forms, management
Requires at least: 5.8
Tested up to: 6.4
Requires PHP: 7.4
Stable tag: 1.2.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

A comprehensive waiting list management system for nurseries with Gravity Forms integration, email notifications, parent portal, and full admin management.

== Description ==

Nursery Waiting List is a production-ready WordPress plugin designed specifically for UK nurseries to manage their waiting lists efficiently. It integrates seamlessly with Gravity Forms and provides a complete solution for tracking applicants, communicating with parents, and managing the entire waiting list workflow.

= Key Features =

**Gravity Forms Integration**
* Automatic entry creation when forms are submitted
* Flexible field mapping for any form structure
* Merge tag support for waiting list numbers

**Automatic Communications**
* Unique waiting list numbers generated automatically
* Customizable email templates with variable placeholders
* Automatic confirmation emails on registration
* Status change notifications

**Parent Portal**
* Public status check page at /waiting-list-stats/
* Search by email or phone number
* Mobile-responsive modern design
* Rate limiting for security

**Admin Management**
* Comprehensive entry management dashboard
* Filter by status, room, age group, and date
* Edit all child and parent details
* Quick status changes with dropdown
* Internal notes (staff only) and public notes
* Bulk actions for efficiency

**Messaging System**
* Send emails to individuals or groups
* Filter recipients by status, room, etc.
* Custom message content
* Full email logging and history

**Reporting & Analytics**
* Dashboard with key statistics
* Entries by status and room breakdown
* Monthly registration trends
* Average waiting time calculations
* Conversion rate tracking
* Entries needing attention alerts

**GDPR Compliance**
* WordPress privacy tools integration
* Data export and erasure support
* Configurable retention periods
* Deletion request workflow
* Consent tracking

**Security & Permissions**
* Custom user role: Waiting List Manager
* Granular capability controls
* Rate limiting on public lookups
* Secure data handling

= Available Statuses =

* Pending
* Contacted
* On Waiting List
* Place Offered
* Accepted
* Declined
* Enrolled
* Removed

= Email Templates =

* Registration Confirmation
* Place Offered
* General Update
* Follow-up Reminder
* Status Change Notification
* Custom templates

== Installation ==

1. Upload the `nursery-waiting-list` folder to `/wp-content/plugins/`
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Go to Waiting List > Settings to configure your nursery details
4. If using Gravity Forms, go to Forms > Your Form > Settings > Waiting List to enable integration
5. The parent status check page is automatically created at /waiting-list-stats/

== Frequently Asked Questions ==

= Does this plugin require Gravity Forms? =

No, the plugin works independently. However, Gravity Forms integration allows automatic entry creation when parents submit your registration form.

= Can parents check their waiting list status online? =

Yes! A public page is automatically created at /waiting-list-stats/ where parents can search using their email or phone number.

= Is this plugin GDPR compliant? =

Yes, the plugin follows GDPR best practices for UK nurseries, including data export, erasure requests, configurable retention periods, and consent tracking.

= Can I customize the email templates? =

Absolutely. All email templates are fully editable from the admin area with support for variable placeholders.

= What user roles can access the waiting list? =

By default, Administrators have full access. The plugin also creates a "Waiting List Manager" role with appropriate permissions. You can customize capabilities as needed.

== Screenshots ==

1. Main entries list with filters and quick stats
2. Entry edit screen with notes and email history
3. Email templates management
4. Reports dashboard
5. Parent status lookup page
6. Settings configuration

== Changelog ==

= 1.0.0 =
* Initial release
* Full Gravity Forms integration
* Email template system
* Parent portal with status lookup
* Admin management dashboard
* Reporting and analytics
* GDPR compliance features
* Export to CSV functionality

== Upgrade Notice ==

= 1.0.0 =
Initial release of Nursery Waiting List plugin.
