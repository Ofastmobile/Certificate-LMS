<?php

/**
 * Plugin Name: OFAST Certificate Management System
 * Plugin URI: https://ofastshop.com
 * Description: Complete certificate management system for WooCommerce courses with student/vendor requests, verification, and HTML certificate generation
 * Version: 2.0.0
 * Author: Ofastshop Digitals
 * Author URI: https://ofastshop.com
 * Text Domain: ofast-certificate
 * Requires at least: 5.8
 * Requires PHP: 7.4
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants  
define('OFST_CERT_VERSION', '2.0.0');
define('OFST_CERT_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('OFST_CERT_PLUGIN_URL', plugin_dir_url(__FILE__));

/**
 * =====================================================
 * DATABASE SETUP & CORE FUNCTIONS
 * =====================================================
 */

// Create custom database tables on activation
function ofst_cert_create_tables()
{
    global $wpdb;
    $charset_collate = $wpdb->get_charset_collate();
    $table_prefix = $wpdb->prefix . 'ofst_';

    // Table 1: Certificate Requests (Student & Vendor)
    $sql1 = "CREATE TABLE IF NOT EXISTS {$table_prefix}cert_requests (
        id bigint(20) NOT NULL AUTO_INCREMENT,
        user_id bigint(20) NOT NULL,
        certificate_id varchar(50) NOT NULL,
        request_type varchar(20) NOT NULL DEFAULT 'student',
        first_name varchar(100) NOT NULL,
        last_name varchar(100) NOT NULL,
        email varchar(100) NOT NULL,
        phone varchar(20) NOT NULL,
        product_id bigint(20) NOT NULL,
        product_name varchar(255) NOT NULL,
        project_link varchar(500) DEFAULT NULL,
        instructor_name varchar(200) DEFAULT NULL,
        vendor_id bigint(20) DEFAULT NULL,
        vendor_notes text DEFAULT NULL,
        completion_date date DEFAULT NULL,
        status varchar(20) NOT NULL DEFAULT 'pending',
        rejection_reason text DEFAULT NULL,
        requested_date datetime NOT NULL,
        processed_date datetime DEFAULT NULL,
        processed_by bigint(20) DEFAULT NULL,
        certificate_file varchar(500) DEFAULT NULL,
        PRIMARY KEY (id),
        UNIQUE KEY certificate_id (certificate_id),
        KEY user_product (user_id, product_id),
        KEY status (status),
        KEY request_type (request_type)
    ) $charset_collate;";

    // Table 2: Verification Log
    $sql2 = "CREATE TABLE IF NOT EXISTS {$table_prefix}cert_verifications (
        id bigint(20) NOT NULL AUTO_INCREMENT,
        certificate_id varchar(50) NOT NULL,
        search_method varchar(20) NOT NULL,
        search_query varchar(200) NOT NULL,
        verified_by_ip varchar(45) NOT NULL,
        verified_by_user bigint(20) DEFAULT NULL,
        result varchar(20) NOT NULL,
        verified_date datetime NOT NULL,
        PRIMARY KEY (id),
        KEY certificate_id (certificate_id),
        KEY verified_date (verified_date)
    ) $charset_collate;";

    // Table 3: System Settings
    $sql3 = "CREATE TABLE IF NOT EXISTS {$table_prefix}cert_settings (
        setting_key varchar(100) NOT NULL,
        setting_value longtext NOT NULL,
        PRIMARY KEY (setting_key)
    ) $charset_collate;";

    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql1);
    dbDelta($sql2);
    dbDelta($sql3);

    // Initialize default settings
    ofst_cert_init_settings();
}

// Initialize default settings
function ofst_cert_init_settings()
{
    global $wpdb;
    $table = $wpdb->prefix . 'ofst_cert_settings';

    $defaults = array(
        'cert_prefix' => 'OFSHDG',
        'cert_counter' => '1',
        'min_days_after_purchase' => '3',
        'company_name' => 'Ofastshop Digitals',
        '  support_email' => 'support@ofastshop.com',
        'from_email' => 'support@ofastshop.com',
        'from_name' => 'Ofastshop Digitals',
        'logo_url' => 'YOUR_LOGO_URL_HERE',
        'seal_url' => 'YOUR_SEAL_URL_HERE',
        'signature_url' => 'YOUR_SIGNATURE_URL_HERE',
        'turnstile_site_key' => '',
        'turnstile_secret_key' => ''
    );

    foreach ($defaults as $key => $value) {
        $exists = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $table WHERE setting_key = %s",
            $key
        ));

        if (!$exists) {
            $wpdb->insert($table, array(
                'setting_key' => $key,
                'setting_value' => $value
            ));
        }
    }
}

// Plugin activation hook
register_activation_hook(__FILE__, 'ofst_cert_activate_plugin');

function ofst_cert_activate_plugin()
{
    ofst_cert_create_tables();
    ofst_cert_migrate_to_v2(); // V2.0 migration
    update_option('ofst_cert_db_version', '2.0');
    flush_rewrite_rules();
}

/**
 * V2.0 DATABASE MIGRATION
 * Adds tables for multi-template support (Ofastshop + Cromemart)
 */
function ofst_cert_migrate_to_v2()
{
    global $wpdb;
    $charset_collate = $wpdb->get_charset_collate();
    $prefix = $wpdb->prefix . 'ofst_';

    // ========== NEW TABLE 1: Institutions ==========
    $sql_institutions = "CREATE TABLE IF NOT EXISTS {$prefix}cert_institutions (
        id bigint(20) NOT NULL AUTO_INCREMENT,
        institution_name varchar(200) NOT NULL,
        institution_logo varchar(500) DEFAULT NULL,
        is_active tinyint(1) DEFAULT 1,
        created_date datetime NOT NULL,
        created_by bigint(20) NOT NULL,
        PRIMARY KEY (id),
        KEY idx_active (is_active)
    ) $charset_collate;";

    // ========== NEW TABLE 2: Event Dates ==========
    $sql_events = "CREATE TABLE IF NOT EXISTS {$prefix}cert_event_dates (
        id bigint(20) NOT NULL AUTO_INCREMENT,
        institution_id bigint(20) NOT NULL,
        event_name varchar(255) NOT NULL,
        event_date date NOT NULL,
        event_theme varchar(500) DEFAULT NULL,
        is_active tinyint(1) DEFAULT 1,
        created_date datetime NOT NULL,
        created_by bigint(20) NOT NULL,
        PRIMARY KEY (id),
        KEY idx_institution (institution_id),
        KEY idx_active (is_active)
    ) $charset_collate;";

    // ========== NEW TABLE 3: Rate Limits ==========
    $sql_rate_limits = "CREATE TABLE IF NOT EXISTS {$prefix}cert_rate_limits (
        id bigint(20) NOT NULL AUTO_INCREMENT,
        identifier varchar(100) NOT NULL,
        action_type varchar(50) NOT NULL,
        attempt_count int DEFAULT 1,
        first_attempt datetime NOT NULL,
        last_attempt datetime NOT NULL,
        PRIMARY KEY (id),
        KEY idx_identifier (identifier, action_type)
    ) $charset_collate;";

    // ========== NEW TABLE 4: Event Participants Roster ==========
    $sql_participants = "CREATE TABLE IF NOT EXISTS {$prefix}cert_event_participants (
        id bigint(20) NOT NULL AUTO_INCREMENT,
        event_date_id bigint(20) NOT NULL,
        full_name varchar(200) NOT NULL,
        added_date datetime NOT NULL,
        added_by bigint(20) NOT NULL,
        PRIMARY KEY (id),
        KEY idx_event (event_date_id),
        KEY idx_name (full_name)
    ) $charset_collate;";

    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql_institutions);
    dbDelta($sql_events);
    dbDelta($sql_rate_limits);
    dbDelta($sql_participants);

    // ========== ADD NEW COLUMNS TO cert_requests ==========
    $table = $prefix . 'cert_requests';

    // Check if template_type column exists
    $col_exists = $wpdb->get_results("SHOW COLUMNS FROM $table LIKE 'template_type'");
    if (empty($col_exists)) {
        $wpdb->query("ALTER TABLE $table ADD COLUMN template_type varchar(50) DEFAULT 'ofastshop' AFTER vendor_notes");
        $wpdb->query("ALTER TABLE $table ADD COLUMN institution_id bigint(20) DEFAULT NULL AFTER template_type");
        $wpdb->query("ALTER TABLE $table ADD COLUMN event_date_id bigint(20) DEFAULT NULL AFTER institution_id");
        $wpdb->query("ALTER TABLE $table ADD COLUMN pdf_file_path varchar(500) DEFAULT NULL AFTER certificate_file");

        // Make product_id nullable (for Cromemart certificates)
        $wpdb->query("ALTER TABLE $table MODIFY product_id bigint(20) DEFAULT NULL");
        $wpdb->query("ALTER TABLE $table MODIFY product_name varchar(255) DEFAULT NULL");
    }

    // V2.1: Add certificate_token column for secure access
    $token_col = $wpdb->get_results("SHOW COLUMNS FROM $table LIKE 'certificate_token'");
    if (empty($token_col)) {
        $wpdb->query("ALTER TABLE $table ADD COLUMN certificate_token varchar(64) DEFAULT NULL AFTER certificate_file");
        $wpdb->query("ALTER TABLE $table ADD INDEX idx_token (certificate_token)");
    }
}

// Plugin deactivation hook
register_deactivation_hook(__FILE__, 'ofst_cert_deactivate_plugin');

function ofst_cert_deactivate_plugin()
{
    // Clear scheduled cron events
    wp_clear_scheduled_hook('ofst_cert_daily_cleanup');
    wp_clear_scheduled_hook('ofst_cert_hourly_email_retry');
    flush_rewrite_rules();
}

/**
 * =====================================================
 * AUTOMATIC DATABASE MIGRATION ON ADMIN LOAD
 * Ensures live sites get updates even without re-activation
 * =====================================================
 */
add_action('admin_init', 'ofst_cert_check_db_version');
function ofst_cert_check_db_version()
{
    $current_db_version = get_option('ofst_cert_db_version', '1.0');
    $required_db_version = '2.5'; // Increment this when making DB changes

    if (version_compare($current_db_version, $required_db_version, '<')) {
        // Run all migrations
        ofst_cert_create_tables(); // Base tables
        ofst_cert_migrate_to_v2(); // V2.0 columns and tables
        ofst_cert_run_safe_migrations(); // Safe column additions

        // Update version
        update_option('ofst_cert_db_version', $required_db_version);

        // Log the update
        error_log('OFST Certificate: Database migrated from ' . $current_db_version . ' to ' . $required_db_version);
    }
}

/**
 * Safe database migrations - checks before adding columns
 * Prevents errors on live sites by verifying table/column existence
 */
function ofst_cert_run_safe_migrations()
{
    global $wpdb;
    $prefix = $wpdb->prefix . 'ofst_';
    $table = $prefix . 'cert_requests';

    // Suppress errors temporarily for table checks
    $wpdb->suppress_errors(true);

    // Check if main table exists
    $table_exists = $wpdb->get_var("SHOW TABLES LIKE '$table'");
    if (!$table_exists) {
        $wpdb->suppress_errors(false);
        error_log('OFST Certificate: Main table does not exist, skipping column migrations');
        return;
    }

    // Define all required columns and their definitions
    $required_columns = array(
        'template_type' => "varchar(50) DEFAULT 'ofastshop' AFTER vendor_notes",
        'institution_id' => "bigint(20) DEFAULT NULL AFTER template_type",
        'event_date_id' => "bigint(20) DEFAULT NULL AFTER institution_id",
        'pdf_file_path' => "varchar(500) DEFAULT NULL AFTER certificate_file",
        'certificate_token' => "varchar(64) DEFAULT NULL AFTER certificate_file"
    );

    // Get existing columns
    $existing_columns = array();
    $columns = $wpdb->get_results("SHOW COLUMNS FROM $table");
    foreach ($columns as $col) {
        $existing_columns[] = $col->Field;
    }

    // Add missing columns
    foreach ($required_columns as $column => $definition) {
        if (!in_array($column, $existing_columns)) {
            $result = $wpdb->query("ALTER TABLE $table ADD COLUMN $column $definition");
            if ($result !== false) {
                error_log("OFST Certificate: Added column '$column' to $table");
            } else {
                error_log("OFST Certificate: Failed to add column '$column' - " . $wpdb->last_error);
            }
        }
    }

    // Add indexes safely
    $indexes = $wpdb->get_results("SHOW INDEX FROM $table");
    $existing_indexes = array();
    foreach ($indexes as $idx) {
        $existing_indexes[] = $idx->Key_name;
    }

    // Add certificate_token index if missing
    if (!in_array('idx_token', $existing_indexes) && in_array('certificate_token', $existing_columns)) {
        $wpdb->query("ALTER TABLE $table ADD INDEX idx_token (certificate_token)");
    }

    // Ensure product_id and product_name are nullable (for Cromemart)
    $wpdb->query("ALTER TABLE $table MODIFY product_id bigint(20) DEFAULT NULL");
    $wpdb->query("ALTER TABLE $table MODIFY product_name varchar(255) DEFAULT NULL");

    // V2.4: Add email column to participants table
    $participants_table = $prefix . 'cert_event_participants';
    $participant_cols = $wpdb->get_results("SHOW COLUMNS FROM $participants_table LIKE 'email'");
    if (empty($participant_cols)) {
        $wpdb->query("ALTER TABLE $participants_table ADD COLUMN email varchar(100) DEFAULT NULL AFTER full_name");
        error_log('OFST Certificate: Added email column to participants table');
    }

    $wpdb->suppress_errors(false);
}

/**
 * =====================================================
 * WP-CRON JOBS FOR MAINTENANCE
 * =====================================================
 */

// Schedule cron jobs on plugin activation
function ofst_cert_schedule_cron_jobs()
{
    // Daily cleanup cron
    if (!wp_next_scheduled('ofst_cert_daily_cleanup')) {
        wp_schedule_event(time(), 'daily', 'ofst_cert_daily_cleanup');
    }

    // Hourly email retry cron
    if (!wp_next_scheduled('ofst_cert_hourly_email_retry')) {
        wp_schedule_event(time(), 'hourly', 'ofst_cert_hourly_email_retry');
    }
}
add_action('init', 'ofst_cert_schedule_cron_jobs');

// Daily cleanup: old rate limit entries and old verification logs
add_action('ofst_cert_daily_cleanup', 'ofst_cert_run_daily_cleanup');
function ofst_cert_run_daily_cleanup()
{
    global $wpdb;

    // Delete rate limit entries older than 24 hours
    $wpdb->query(
        "DELETE FROM {$wpdb->prefix}ofst_cert_rate_limits 
         WHERE last_attempt < DATE_SUB(NOW(), INTERVAL 24 HOUR)"
    );

    // Delete verification logs older than 90 days (keep recent ones for audit)
    $wpdb->query(
        "DELETE FROM {$wpdb->prefix}ofst_cert_verifications 
         WHERE verified_date < DATE_SUB(NOW(), INTERVAL 90 DAY)"
    );

    error_log('OFST Certificate: Daily cleanup completed');
}

// Hourly retry failed emails
add_action('ofst_cert_hourly_email_retry', 'ofst_cert_run_email_retry');
function ofst_cert_run_email_retry()
{
    global $wpdb;
    $table = $wpdb->prefix . 'ofst_cert_requests';

    // Get failed emails from last 24 hours (max 5 per run to prevent overload)
    $failed = $wpdb->get_results(
        "SELECT * FROM $table 
         WHERE status = 'email_failed' 
         AND processed_date > DATE_SUB(NOW(), INTERVAL 24 HOUR)
         ORDER BY processed_date DESC
         LIMIT 5"
    );

    foreach ($failed as $request) {
        $result = ofst_cert_retry_email($request->id);
        if ($result['success']) {
            error_log("OFST Certificate: Email retry succeeded for request {$request->id}");
        }
        // Wait 2 seconds between emails to avoid spam triggers
        sleep(2);
    }
}

// Show admin notice after activation
add_action('admin_notices', function () {
    if (get_transient('ofst_cert_activated')) {
        delete_transient('ofst_cert_activated');
?>
        <div class="notice notice-success is-dismissible">
            <p><strong>OFAST Certificate System activated!</strong> Database tables created successfully. Remaining features (forms, verification, etc.) will be added in the next step.</p>
        </div>
<?php
    }
});

// Set activation transient
add_action('activated_plugin', function ($plugin) {
    if ($plugin == plugin_basename(__FILE__)) {
        set_transient('ofst_cert_activated', true, 5);
    }
});

/**
 * =====================================================
 * CORE HELPER FUNCTIONS  
 * =====================================================
 */

// Get system setting
function ofst_cert_get_setting($key, $default = '')
{
    global $wpdb;
    $table = $wpdb->prefix . 'ofst_cert_settings';

    $value = $wpdb->get_var($wpdb->prepare(
        "SELECT setting_value FROM $table WHERE setting_key = %s",
        $key
    ));

    return $value !== null ? $value : $default;
}

// Update system setting
function ofst_cert_update_setting($key, $value)
{
    global $wpdb;
    $table = $wpdb->prefix . 'ofst_cert_settings';

    $exists = $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM $table WHERE setting_key = %s",
        $key
    ));

    if ($exists) {
        return $wpdb->update(
            $table,
            array('setting_value' => $value),
            array('setting_key' => $key)
        );
    } else {
        return $wpdb->insert($table, array(
            'setting_key' => $key,
            'setting_value' => $value
        ));
    }
}

// Generate unique certificate ID with race condition protection
function ofst_cert_generate_id()
{
    global $wpdb;
    $table = $wpdb->prefix . 'ofst_cert_settings';
    $prefix = ofst_cert_get_setting('cert_prefix', 'OFSHDG');
    $year = date('Y');

    // Use atomic UPDATE to prevent race conditions
    $current_counter = (int) $wpdb->get_var(
        $wpdb->prepare("SELECT setting_value FROM $table WHERE setting_key = %s FOR UPDATE", 'cert_counter')
    );

    if (!$current_counter) {
        $current_counter = 1;
    }

    // Atomic increment
    $updated = $wpdb->query(
        $wpdb->prepare(
            "UPDATE $table SET setting_value = setting_value + 1 WHERE setting_key = %s",
            'cert_counter'
        )
    );

    if (!$updated) {
        // Fallback: insert if doesn't exist
        $wpdb->insert($table, [
            'setting_key' => 'cert_counter',
            'setting_value' => 2
        ]);
        $current_counter = 1;
    }

    // Format: OFSHDG2024001
    $cert_id = $prefix . $year . str_pad($current_counter, 3, '0', STR_PAD_LEFT);

    // Verify uniqueness - regenerate if exists (very rare edge case)
    $exists = $wpdb->get_var(
        $wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}ofst_cert_requests WHERE certificate_id = %s",
            $cert_id
        )
    );

    if ($exists) {
        error_log("Certificate ID collision detected: $cert_id - regenerating");
        return ofst_cert_generate_id(); // Recursive call to get next ID
    }

    return $cert_id;
}

/**
 * Check rate limiting for form submissions
 * 
 * @param string $identifier IP address or user ID
 * @param string $action_type Type of action (student_request, vendor_request, verification)
 * @param int $max_attempts Maximum attempts allowed
 * @param int $timeframe_minutes Time window in minutes
 * @return array ['allowed' => bool, 'retry_after' => seconds]
 */
function ofst_cert_check_rate_limit($identifier, $action_type, $max_attempts = 5, $timeframe_minutes = 60)
{
    global $wpdb;
    $table = $wpdb->prefix . 'ofst_cert_rate_limits';

    $cutoff_time = date('Y-m-d H:i:s', strtotime("-$timeframe_minutes minutes"));

    // Clean old entries
    $wpdb->query($wpdb->prepare(
        "DELETE FROM $table WHERE last_attempt < %s",
        $cutoff_time
    ));

    // Check current attempts
    $record = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM $table WHERE identifier = %s AND action_type = %s",
        $identifier,
        $action_type
    ));

    if ($record && $record->attempt_count >= $max_attempts) {
        $first_attempt_time = strtotime($record->first_attempt);
        $retry_after = ($first_attempt_time + ($timeframe_minutes * 60)) - time();
        return [
            'allowed' => false,
            'retry_after' => max(0, $retry_after),
            'message' => sprintf('Too many requests. Try again in %d minutes.', ceil($retry_after / 60))
        ];
    }

    // Update or insert
    if ($record) {
        $wpdb->update($table, [
            'attempt_count' => $record->attempt_count + 1,
            'last_attempt' => current_time('mysql')
        ], ['id' => $record->id]);
    } else {
        $wpdb->insert($table, [
            'identifier' => $identifier,
            'action_type' => $action_type,
            'attempt_count' => 1,
            'first_attempt' => current_time('mysql'),
            'last_attempt' => current_time('mysql')
        ]);
    }

    return ['allowed' => true];
}

/**
 * Log certificate generation failure
 */
function ofst_cert_log_generation_failure($request_id, $error)
{
    global $wpdb;
    $table = $wpdb->prefix . 'ofst_cert_requests';

    $wpdb->update($table, [
        'status' => 'generation_failed',
        'rejection_reason' => 'Generation Error: ' . sanitize_text_field($error),
        'processed_date' => current_time('mysql')
    ], ['id' => $request_id]);

    error_log("Certificate generation failed for request $request_id: $error");
}

/**
 * Log email sending failure
 */
function ofst_cert_log_email_failure($request_id, $error)
{
    global $wpdb;
    $table = $wpdb->prefix . 'ofst_cert_requests';

    $wpdb->update($table, [
        'status' => 'email_failed',
        'rejection_reason' => 'Email Error: ' . sanitize_text_field($error)
    ], ['id' => $request_id]);

    error_log("Certificate email failed for request $request_id: $error");
}

/**
 * Retry certificate generation
 */
function ofst_cert_retry_generation($request_id, $completion_date = null)
{
    global $wpdb;
    $table = $wpdb->prefix . 'ofst_cert_requests';

    // Reset status
    $update_data = [
        'status' => 'pending',
        'rejection_reason' => null
    ];

    if ($completion_date) {
        $update_data['completion_date'] = $completion_date;
    }

    $wpdb->update($table, $update_data, ['id' => $request_id]);

    // Retry approval
    return ofst_cert_approve_request($request_id, $completion_date);
}

/**
 * Retry email sending
 */
function ofst_cert_retry_email($request_id)
{
    global $wpdb;
    $table = $wpdb->prefix . 'ofst_cert_requests';
    $event_table = $wpdb->prefix . 'ofst_cert_event_dates';

    // Join event table for Cromemart event_theme in email
    $request = $wpdb->get_row($wpdb->prepare("
        SELECT r.*, e.event_theme, e.event_name 
        FROM $table r 
        LEFT JOIN $event_table e ON r.event_date_id = e.id 
        WHERE r.id = %d
    ", $request_id));

    if (!$request || empty($request->certificate_file)) {
        return ['success' => false, 'error' => 'Certificate file not found'];
    }

    $email_sent = ofst_cert_send_certificate_email($request);

    if ($email_sent) {
        $wpdb->update($table, [
            'status' => 'issued',
            'rejection_reason' => null
        ], ['id' => $request_id]);

        return ['success' => true];
    }

    return ['success' => false, 'error' => 'Email sending failed'];
}

/**
 * Get instructor name from product or vendor
 */
function ofst_cert_get_instructor_name($product_id, $vendor_id = null)
{
    // Try to get from vendor first
    if ($vendor_id) {
        $vendor_user = get_userdata($vendor_id);
        if ($vendor_user) {
            return $vendor_user->display_name;
        }
    }

    // Try to get from product author (Dokan)
    if ($product_id && function_exists('dokan_get_vendor_by_product')) {
        $vendor = dokan_get_vendor_by_product($product_id);
        if ($vendor) {
            return $vendor->get_shop_name() ?: $vendor->get_name();
        }
    }

    // Try to get from product author
    if ($product_id) {
        $product = wc_get_product($product_id);
        if ($product) {
            $author_id = get_post_field('post_author', $product_id);
            $author = get_userdata($author_id);
            if ($author) {
                return $author->display_name;
            }
        }
    }

    return 'Instructor';
}

// Check if certificate already exists for user + product
function ofst_cert_check_duplicate($user_id, $product_id)
{
    global $wpdb;
    $table = $wpdb->prefix . 'ofst_cert_requests';

    $existing = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM $table 
        WHERE user_id = %d 
        AND product_id = %d 
        AND status IN ('pending', 'approved', 'issued')
        ORDER BY id DESC 
        LIMIT 1",
        $user_id,
        $product_id
    ));

    return $existing;
}

// Get user's purchased products (WooCommerce orders)
function ofst_cert_get_user_products($user_id, $min_days = null)
{
    if (!function_exists('wc_get_orders')) {
        return array();
    }

    if ($min_days === null) {
        $min_days = (int) ofst_cert_get_setting('min_days_after_purchase', 3);
    }

    $orders = wc_get_orders(array(
        'customer_id' => $user_id,
        'status' => array('wc-completed', 'wc-processing'),
        'limit' => -1
    ));

    $products = array();
    $min_date = date('Y-m-d', strtotime("-$min_days days"));

    foreach ($orders as $order) {
        $order_date = $order->get_date_created()->date('Y-m-d');

        // Only include products purchased before minimum days ago
        if ($order_date <= $min_date) {
            foreach ($order->get_items() as $item) {
                $product_id = $item->get_product_id();
                $product = wc_get_product($product_id);

                if ($product && !isset($products[$product_id])) {
                    // Check if already has certificate
                    $has_cert = ofst_cert_check_duplicate($user_id, $product_id);

                    if (!$has_cert) {
                        $products[$product_id] = array(
                            'id' => $product_id,
                            'name' => $product->get_name(),
                            'purchased_date' => $order_date
                        );
                    }
                }
            }
        }
    }

    return $products;
}

/**
 * Get ALL user's purchased products with eligibility status
 * Returns products regardless of min_days, but marks ineligible ones
 */
function ofst_cert_get_all_user_products($user_id)
{
    if (!function_exists('wc_get_orders')) {
        return array();
    }

    $min_days = (int) ofst_cert_get_setting('min_days_after_purchase', 3);
    $min_date = date('Y-m-d', strtotime("-$min_days days"));

    $orders = wc_get_orders(array(
        'customer_id' => $user_id,
        'status' => array('wc-completed', 'wc-processing'),
        'limit' => -1
    ));

    $products = array();

    foreach ($orders as $order) {
        $order_date = $order->get_date_created()->date('Y-m-d');

        foreach ($order->get_items() as $item) {
            $product_id = $item->get_product_id();
            $product = wc_get_product($product_id);

            if ($product && !isset($products[$product_id])) {
                // Check if already has certificate
                $has_cert = ofst_cert_check_duplicate($user_id, $product_id);

                if (!$has_cert) {
                    // Determine eligibility
                    $is_eligible = ($order_date <= $min_date);
                    $days_remaining = 0;

                    if (!$is_eligible) {
                        // Calculate days remaining
                        $purchase_timestamp = strtotime($order_date);
                        $eligible_timestamp = $purchase_timestamp + ($min_days * 86400);
                        $days_remaining = ceil(($eligible_timestamp - time()) / 86400);
                        if ($days_remaining < 0) $days_remaining = 0;
                    }

                    $products[$product_id] = array(
                        'id' => $product_id,
                        'name' => $product->get_name(),
                        'purchased_date' => $order_date,
                        'is_eligible' => $is_eligible,
                        'days_remaining' => $days_remaining
                    );
                }
            }
        }
    }

    return $products;
}

// Get vendor's products (Dokan)
function ofst_cert_get_vendor_products($vendor_id)
{
    $args = array(
        'post_type' => 'product',
        'author' => $vendor_id,
        'posts_per_page' => -1,
        'post_status' => 'publish'
    );

    $products = array();
    $query = new WP_Query($args);

    if ($query->have_posts()) {
        while ($query->have_posts()) {
            $query->the_post();
            $product_id = get_the_ID();
            $products[$product_id] = get_the_title();
        }
        wp_reset_postdata();
    }

    return $products;
}

// Verify Cloudflare Turnstile token
function ofst_cert_verify_turnstile($token)
{
    $secret = ofst_cert_get_setting('turnstile_secret_key');

    // If Turnstile is not configured (no secret key), skip verification
    if (empty($secret)) {
        return true; // Allow if Turnstile not configured
    }

    // Turnstile IS configured - token is REQUIRED
    if (empty($token)) {
        return false; // Reject if no token provided but Turnstile is configured
    }

    $response = wp_remote_post('https://challenges.cloudflare.com/turnstile/v0/siteverify', array(
        'body' => array(
            'secret' => $secret,
            'response' => $token
        )
    ));

    if (is_wp_error($response)) {
        return false;
    }

    $body = json_decode(wp_remote_retrieve_body($response), true);
    return isset($body['success']) && $body['success'] === true;
}

// Sanitize phone number
function ofst_cert_sanitize_phone($phone)
{
    return preg_replace('/[^0-9+\-() ]/', '', $phone);
}

// Log verification attempt
function ofst_cert_log_verification($cert_id, $method, $query, $result)
{
    global $wpdb;
    $table = $wpdb->prefix . 'ofst_cert_verifications';

    $user_id = get_current_user_id();
    $ip = isset($_SERVER['REMOTE_ADDR']) ? sanitize_text_field($_SERVER['REMOTE_ADDR']) : '';

    $wpdb->insert($table, array(
        'certificate_id' => sanitize_text_field($cert_id),
        'search_method' => sanitize_text_field($method),
        'search_query' => sanitize_text_field($query),
        'verified_by_ip' => $ip,
        'verified_by_user' => $user_id > 0 ? $user_id : null,
        'result' => sanitize_text_field($result),
        'verified_date' => current_time('mysql')
    ));
}

/**
 * =====================================================
 * ENQUEUE STYLES AND SCRIPTS
 * =====================================================
 */
add_action('wp_enqueue_scripts', 'ofst_cert_enqueue_assets');
function ofst_cert_enqueue_assets()
{
    // Enqueue CSS
    wp_enqueue_style(
        'ofst-cert-styles',
        OFST_CERT_PLUGIN_URL . 'assets/css/styles.css',
        array(),
        OFST_CERT_VERSION
    );
}

/**
 * =====================================================
 * LOAD SHORTCODES
 * =====================================================
 */
require_once OFST_CERT_PLUGIN_DIR . 'includes/shortcodes.php';

/**
 * =====================================================
 * LOAD ADMIN DASHBOARD
 * =====================================================
 */
if (is_admin()) {
    require_once OFST_CERT_PLUGIN_DIR . 'includes/admin-dashboard.php';
}

/**
 * =====================================================
 * LOAD EMAIL TEMPLATES
 * =====================================================
 */
require_once OFST_CERT_PLUGIN_DIR . 'includes/email-templates.php';

/**
 * =====================================================
 * V2.0: AJAX HANDLERS
 * =====================================================
 */
require_once OFST_CERT_PLUGIN_DIR . 'includes/ajax-handlers.php';
