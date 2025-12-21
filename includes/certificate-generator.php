<?php

/**
 * Certificate Generator - V2.0 with Dompdf
 * Uses actual HTML template files from certficate-template-code folder
 */

if (!defined('ABSPATH')) exit;

/**
 * Main certificate generation function
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

    // Generate PDF
    $result = ofst_cert_generate_pdf($html, $request->certificate_id, $template_type);

    if (!$result['success']) {
        return $result;
    }

    // Update database
    $wpdb->update($table, [
        'pdf_file_path' => $result['file_path'],
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

    // Common placeholder replacements
    $html = str_replace('{{CERTIFICATE_ID}}', esc_html($certificate_id), $html);
    $html = str_replace('{{STUDENT_NAME}}', esc_html($student_name), $html);
    $html = str_replace('{{ISSUE_DATE}}', esc_html($issue_date), $html);

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

        // Replace placeholders if they exist
        $html = str_replace('{{EVENT_NAME}}', esc_html($event_name), $html);
        $html = str_replace('{{EVENT_THEME}}', esc_html($event_theme), $html);
        $html = str_replace('{{EVENT_DATE}}', esc_html($event_date), $html);
        $html = str_replace('{{INSTITUTION_NAME}}', esc_html($institution_name), $html);

        // Also replace the entire description paragraph using regex (handles curly quotes)
        $new_description = 'has actively participated in the ' . esc_html($event_name);
        if (!empty($event_theme)) {
            $new_description .= ' themed "' . esc_html($event_theme) . '",';
        }
        $new_description .= ' held on ' . esc_html($event_date) . ', at the ' . esc_html($institution_name) . '.';

        // Pattern to match the description paragraph content
        $pattern = '/<p class="description">\s*has actively participated in.*?<\/p>/s';
        $replacement = '<p class="description">' . "\n        " . $new_description . "\n      </p>";
        $html = preg_replace($pattern, $replacement, $html);
    }

    return $html;
}

/**
 * Generate PDF using Dompdf
 */
function ofst_cert_generate_pdf($html, $certificate_id, $template_type)
{
    $dompdf_path = OFST_CERT_PLUGIN_DIR . 'pdf/dompdf/';

    if (!file_exists($dompdf_path . 'src/Dompdf.php')) {
        return ['success' => false, 'error' => 'Dompdf not found at: ' . $dompdf_path];
    }

    require_once $dompdf_path . 'src/Autoloader.php';
    \Dompdf\Autoloader::register();

    $options = new \Dompdf\Options();
    $options->set('isRemoteEnabled', true);  // Allow loading images from URLs (R2 bucket)
    $options->set('defaultFont', 'Arial');
    $options->set('isHtml5ParserEnabled', true);

    $dompdf = new \Dompdf\Dompdf($options);
    $dompdf->loadHtml($html);
    $dompdf->setPaper('A4', 'landscape');
    $dompdf->render();

    // Create certificates directory
    $upload_dir = wp_upload_dir();
    $cert_dir = $upload_dir['basedir'] . '/certificates/';

    if (!file_exists($cert_dir)) {
        wp_mkdir_p($cert_dir);
        file_put_contents($cert_dir . '.htaccess', 'deny from all');
    }

    // Generate unique filename
    $hash = md5($certificate_id . time() . wp_rand());
    $filename = $template_type . '_' . $certificate_id . '_' . substr($hash, 0, 8) . '.pdf';
    $file_path = $cert_dir . $filename;

    // Save PDF
    file_put_contents($file_path, $dompdf->output());

    return [
        'success' => true,
        'file_path' => $file_path,
        'file_url' => $upload_dir['baseurl'] . '/certificates/' . $filename,
        'filename' => $filename
    ];
}
