<?php

/**
 * Certificate Generator - V2.5 HTML-Only Version
 * Generates HTML certificates (no PDF)
 * Uses template files from certficate-template-code folder
 */

if (!defined('ABSPATH')) exit;

/**
 * Main certificate generation function
 * Generates HTML certificate and stores it
 */
function ofst_cert_generate_certificate($request_id)
{
    global $wpdb;
    $table = $wpdb->prefix . 'ofst_cert_requests';

    $request = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE id = %d", $request_id));

    if (!$request) {
        return ['success' => false, 'error' => 'Certificate request not found'];
    }

    $template_type = $request->template_type ?? 'ofastshop';

    // Load the correct template file
    $html = ofst_cert_load_template($template_type, $request);

    if (!$html) {
        return ['success' => false, 'error' => 'Template file not found'];
    }

    // Generate HTML certificate file
    $result = ofst_cert_save_html_certificate($html, $request->certificate_id, $template_type);

    if (!$result['success']) {
        return $result;
    }

    // Update database
    $wpdb->update($table, [
        'certificate_file' => $result['file_url'],
        'status' => 'approved',
        'processed_date' => current_time('mysql'),
        'processed_by' => get_current_user_id()
    ], ['id' => $request_id]);

    return $result;
}

/**
 * Load template file and replace placeholders
 */
function ofst_cert_load_template($template_type, $request)
{
    global $wpdb;

    // Determine template file path
    $template_dir = OFST_CERT_PLUGIN_DIR . 'certficate-template-code/';

    if ($template_type === 'cromemart') {
        $template_file = $template_dir . 'cromemart-cert.html';
    } else {
        $template_file = $template_dir . 'ofastshop-certificate.html';
    }

    if (!file_exists($template_file)) {
        return false;
    }

    // Read template HTML
    $html = file_get_contents($template_file);

    // Prepare common data
    $student_name = $request->first_name . ' ' . $request->last_name;
    $certificate_id = $request->certificate_id;
    $completion_date = $request->completion_date ? date('F d, Y', strtotime($request->completion_date)) : date('F d, Y');
    $issue_date = date('F d, Y');

    // Generate QR code for verification
    $verification_url = site_url('/verify-certificate/?cert_id=' . urlencode($certificate_id));
    $qr_code_url = ofst_cert_generate_qr_code($verification_url, $certificate_id);

    // Common placeholder replacements
    $html = str_replace('{{CERTIFICATE_ID}}', esc_html($certificate_id), $html);
    $html = str_replace('{{STUDENT_NAME}}', esc_html($student_name), $html);
    $html = str_replace('{{ISSUE_DATE}}', esc_html($issue_date), $html);
    $html = str_replace('{{QR_CODE_PATH}}', esc_url($qr_code_url), $html);

    if ($template_type === 'ofastshop') {
        // Ofastshop specific replacements
        $course_name = $request->product_name ?? 'Course';
        $instructor_name = $request->instructor_name ?? 'Instructor';

        $html = str_replace('{{COURSE_NAME}}', esc_html($course_name), $html);
        $html = str_replace('{{COMPLETION_DATE}}', esc_html($completion_date), $html);
        $html = str_replace('{{INSTRUCTOR_NAME}}', esc_html($instructor_name), $html);
    } else {
        // Cromemart specific replacements
        $inst_table = $wpdb->prefix . 'ofst_cert_institutions';
        $event_table = $wpdb->prefix . 'ofst_cert_event_dates';

        $institution = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $inst_table WHERE id = %d",
            $request->institution_id
        ));

        $event = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $event_table WHERE id = %d",
            $request->event_date_id
        ));

        $institution_name = $institution ? $institution->institution_name : 'Institution';
        $event_name = $event ? $event->event_name : 'Event';
        $event_date = $event ? date('F d, Y', strtotime($event->event_date)) : date('F d, Y');
        $event_theme = $event && isset($event->event_theme) ? $event->event_theme : '';

        // Replace placeholders
        $html = str_replace('{{EVENT_NAME}}', esc_html($event_name), $html);
        $html = str_replace('{{EVENT_THEME}}', esc_html($event_theme), $html);
        $html = str_replace('{{EVENT_DATE}}', esc_html($event_date), $html);
        $html = str_replace('{{INSTITUTION_NAME}}', esc_html($institution_name), $html);
    }

    return $html;
}

/**
 * Save HTML certificate to file
 */
function ofst_cert_save_html_certificate($html, $certificate_id, $template_type)
{
    // Create certificates directory
    $upload_dir = wp_upload_dir();
    $cert_dir = $upload_dir['basedir'] . '/certificates/';

    if (!file_exists($cert_dir)) {
        wp_mkdir_p($cert_dir);
        // Add index.php for security
        file_put_contents($cert_dir . 'index.php', '<?php // Silence is golden');
    }

    // Generate unique filename
    $hash = md5($certificate_id . time() . wp_rand());
    $filename = $template_type . '_' . $certificate_id . '_' . substr($hash, 0, 8) . '.html';
    $file_path = $cert_dir . $filename;

    // Save HTML file
    $saved = file_put_contents($file_path, $html);

    if ($saved === false) {
        return [
            'success' => false,
            'error' => 'Failed to save certificate file'
        ];
    }

    return [
        'success' => true,
        'file_path' => $file_path,
        'file_url' => $upload_dir['baseurl'] . '/certificates/' . $filename,
        'filename' => $filename
    ];
}

/**
 * Generate QR code for certificate verification
 * Uses phpqrcode library with robust fallbacks
 */
function ofst_cert_generate_qr_code($url, $certificate_id)
{
    $upload_dir = wp_upload_dir();
    $qr_dir = $upload_dir['basedir'] . '/certificates/qr/';

    // Create QR code directory
    if (!file_exists($qr_dir)) {
        wp_mkdir_p($qr_dir);
    }

    $qr_filename = 'qr_' . $certificate_id . '.png';
    $qr_path = $qr_dir . $qr_filename;

    // If QR code already exists, return it
    if (file_exists($qr_path)) {
        return $upload_dir['baseurl'] . '/certificates/qr/' . $qr_filename;
    }

    // Try phpqrcode library first
    $qr_lib = OFST_CERT_PLUGIN_DIR . 'includes/phpqrcode/qrlib.php';

    if (file_exists($qr_lib)) {
        try {
            require_once $qr_lib;
            QRcode::png($url, $qr_path, QR_ECLEVEL_M, 4);

            if (file_exists($qr_path)) {
                return $upload_dir['baseurl'] . '/certificates/qr/' . $qr_filename;
            }
        } catch (Exception $e) {
            error_log('QR generation with phpqrcode failed: ' . $e->getMessage());
        }
    }

    // Fallback 1: Use qrserver.com API
    $api_url = 'https://api.qrserver.com/v1/create-qr-code/?size=150x150&data=' . urlencode($url);
    $response = wp_remote_get($api_url, ['timeout' => 15]);

    if (!is_wp_error($response) && wp_remote_retrieve_response_code($response) === 200) {
        $image_data = wp_remote_retrieve_body($response);
        if ($image_data && file_put_contents($qr_path, $image_data)) {
            return $upload_dir['baseurl'] . '/certificates/qr/' . $qr_filename;
        }
    }

    // Fallback 2: Return external URL directly (no caching)
    return 'https://api.qrserver.com/v1/create-qr-code/?size=100x100&data=' . urlencode($url);
}

/**
 * Get certificate view URL (for viewing HTML certificate)
 */
function ofst_cert_get_view_url($certificate_id)
{
    return site_url('/view-certificate/?cert_id=' . urlencode($certificate_id));
}
