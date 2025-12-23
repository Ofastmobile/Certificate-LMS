<?php

/**
 * OTP Email Verification for Cromemart Certificates
 * 
 * OPTIONAL ENHANCEMENT - Only add if you want extra security
 * Works alongside email whitelist verification
 * 
 * INSTALLATION:
 * 1. Save as: includes/otp-email-verification.php
 * 2. Add to lms-certificate.php: 
 *    require_once OFST_CERT_PLUGIN_DIR . 'includes/otp-email-verification.php';
 */

if (!defined('ABSPATH')) exit;

/**
 * Database table for OTP codes
 */
add_action('admin_init', 'ofst_cert_create_otp_table');
function ofst_cert_create_otp_table()
{
    global $wpdb;
    $table = $wpdb->prefix . 'ofst_cert_otp_codes';

    $charset_collate = $wpdb->get_charset_collate();
    $sql = "CREATE TABLE IF NOT EXISTS $table (
        id bigint(20) NOT NULL AUTO_INCREMENT,
        email varchar(100) NOT NULL,
        otp_code varchar(6) NOT NULL,
        event_date_id bigint(20) NOT NULL,
        created_at datetime NOT NULL,
        expires_at datetime NOT NULL,
        verified tinyint(1) DEFAULT 0,
        ip_address varchar(45),
        PRIMARY KEY (id),
        KEY idx_email_event (email, event_date_id),
        KEY idx_code (otp_code),
        KEY idx_expires (expires_at)
    ) $charset_collate;";

    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql);
}

/**
 * Generate and send OTP code
 * 
 * @param string $email Email address
 * @param int $event_date_id Event ID
 * @return array ['success' => bool, 'message' => string, 'otp_id' => int]
 */
function ofst_cert_send_otp($email, $event_date_id)
{
    global $wpdb;
    $table = $wpdb->prefix . 'ofst_cert_otp_codes';

    // Rate limiting - max 3 OTPs per email per hour
    $recent_otps = $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM $table 
         WHERE email = %s 
         AND created_at > DATE_SUB(NOW(), INTERVAL 1 HOUR)",
        $email
    ));

    if ($recent_otps >= 3) {
        return [
            'success' => false,
            'message' => 'Too many verification attempts. Please try again in 1 hour.'
        ];
    }

    // Generate 6-digit OTP
    $otp_code = sprintf('%06d', mt_rand(100000, 999999));

    // Store in database (expires in 10 minutes)
    $inserted = $wpdb->insert($table, [
        'email' => $email,
        'otp_code' => $otp_code,
        'event_date_id' => $event_date_id,
        'created_at' => current_time('mysql'),
        'expires_at' => date('Y-m-d H:i:s', strtotime('+10 minutes')),
        'verified' => 0,
        'ip_address' => $_SERVER['REMOTE_ADDR'] ?? ''
    ]);

    if (!$inserted) {
        return [
            'success' => false,
            'message' => 'Failed to generate verification code. Please try again.'
        ];
    }

    $otp_id = $wpdb->insert_id;

    // Get event details for email
    $event = $wpdb->get_row($wpdb->prepare(
        "SELECT e.event_name, i.institution_name 
         FROM {$wpdb->prefix}ofst_cert_event_dates e
         LEFT JOIN {$wpdb->prefix}ofst_cert_institutions i ON e.institution_id = i.id
         WHERE e.id = %d",
        $event_date_id
    ));

    // Send email
    $subject = 'Certificate Request Verification Code - ' . ofst_cert_get_setting('company_name');

    $message = "
    <html>
    <body style='font-family: Arial, sans-serif; line-height: 1.6; color: #333;'>
        <div style='max-width: 600px; margin: 0 auto; padding: 20px; border: 1px solid #ddd;'>
            <h2 style='color: #070244;'>Email Verification Required</h2>
            
            <p>You are requesting a certificate for:</p>
            
            <div style='background: #f5f5f5; padding: 15px; margin: 20px 0;'>
                <strong>Event:</strong> " . esc_html($event->event_name ?? 'Unknown Event') . "<br>
                <strong>Institution:</strong> " . esc_html($event->institution_name ?? '') . "
            </div>
            
            <p>Your verification code is:</p>
            
            <div style='background: #070244; color: white; padding: 20px; text-align: center; font-size: 32px; font-weight: bold; letter-spacing: 8px; margin: 20px 0; border-radius: 8px;'>
                " . esc_html($otp_code) . "
            </div>
            
            <p style='color: #d63638;'><strong>⏰ This code expires in 10 minutes.</strong></p>
            
            <p>If you did not request this verification code, please ignore this email.</p>
            
            <p>Best regards,<br>
            " . esc_html(ofst_cert_get_setting('company_name')) . "</p>
        </div>
    </body>
    </html>
    ";

    $headers = ofst_cert_get_safe_email_headers();
    $email_sent = wp_mail($email, $subject, $message, $headers);

    if (!$email_sent) {
        return [
            'success' => false,
            'message' => 'Failed to send verification email. Please check your email address.'
        ];
    }

    return [
        'success' => true,
        'message' => 'Verification code sent! Please check your email.',
        'otp_id' => $otp_id
    ];
}

/**
 * Verify OTP code
 * 
 * @param string $email Email address
 * @param string $otp_code OTP code to verify
 * @param int $event_date_id Event ID
 * @return array ['verified' => bool, 'message' => string]
 */
function ofst_cert_verify_otp($email, $otp_code, $event_date_id)
{
    global $wpdb;
    $table = $wpdb->prefix . 'ofst_cert_otp_codes';

    // Find matching OTP
    $otp_record = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM $table 
         WHERE email = %s 
         AND otp_code = %s 
         AND event_date_id = %d 
         AND verified = 0
         AND expires_at > NOW()
         ORDER BY created_at DESC
         LIMIT 1",
        $email,
        $otp_code,
        $event_date_id
    ));

    if (!$otp_record) {
        // Check if code exists but expired
        $expired = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table 
             WHERE email = %s 
             AND otp_code = %s 
             AND event_date_id = %d
             ORDER BY created_at DESC
             LIMIT 1",
            $email,
            $otp_code,
            $event_date_id
        ));

        if ($expired) {
            return [
                'verified' => false,
                'message' => 'Verification code has expired. Please request a new code.'
            ];
        }

        return [
            'verified' => false,
            'message' => 'Invalid verification code. Please check and try again.'
        ];
    }

    // Mark as verified
    $wpdb->update(
        $table,
        ['verified' => 1],
        ['id' => $otp_record->id]
    );

    return [
        'verified' => true,
        'message' => 'Email verified successfully!'
    ];
}

/**
 * AJAX handler to send OTP
 */
add_action('wp_ajax_ofst_send_otp', 'ofst_cert_ajax_send_otp');
add_action('wp_ajax_nopriv_ofst_send_otp', 'ofst_cert_ajax_send_otp');
function ofst_cert_ajax_send_otp()
{
    // Verify nonce
    if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'ofst_cert_ajax')) {
        wp_send_json_error(['message' => 'Security check failed']);
    }

    $email = sanitize_email($_POST['email'] ?? '');
    $event_date_id = absint($_POST['event_date_id'] ?? 0);

    if (empty($email) || !is_email($email)) {
        wp_send_json_error(['message' => 'Valid email address is required']);
    }

    if ($event_date_id === 0) {
        wp_send_json_error(['message' => 'Please select an event first']);
    }

    $result = ofst_cert_send_otp($email, $event_date_id);

    if ($result['success']) {
        wp_send_json_success($result);
    } else {
        wp_send_json_error($result);
    }
}

/**
 * Add OTP field to certificate request form
 */
add_action('wp_footer', 'ofst_cert_add_otp_field_to_form');
function ofst_cert_add_otp_field_to_form()
{
    if (!is_page() || !has_shortcode(get_post()->post_content, 'cert_student_request')) {
        return;
    }
?>
    <style>
        .otp-verification-section {
            display: none;
            background: #f0f9ff;
            border: 2px solid #0ea5e9;
            border-radius: 12px;
            padding: 20px;
            margin: 20px 0;
        }

        .otp-input {
            text-align: center;
            font-size: 24px;
            letter-spacing: 10px;
            font-weight: bold;
            padding: 12px;
        }

        .otp-status {
            padding: 10px;
            border-radius: 8px;
            margin: 10px 0;
        }

        .otp-status.success {
            background: #dcfce7;
            color: #166534;
        }

        .otp-status.error {
            background: #fee2e2;
            color: #991b1b;
        }
    </style>

    <script>
        jQuery(document).ready(function($) {
            var otpVerified = false;
            var otpRequired = false;

            // Show OTP section when Cromemart + event selected
            $('#event_date_id').on('change', function() {
                var eventId = $(this).val();
                var templateType = $('#template_type').val();

                if (templateType === 'cromemart' && eventId) {
                    otpRequired = true;
                    showOtpSection();
                } else {
                    otpRequired = false;
                    hideOtpSection();
                }
            });

            function showOtpSection() {
                if ($('#otp-verification-section').length === 0) {
                    var html = '<div id="otp-verification-section" class="otp-verification-section ofst-form-group">' +
                        '<h4 style="margin-top: 0;">📧 Email Verification Required</h4>' +
                        '<p>To prevent fraud, we need to verify your email address.</p>' +
                        '<div style="display: flex; gap: 10px; margin: 15px 0;">' +
                        '<button type="button" id="send-otp-btn" class="ofst-btn ofst-btn-primary" style="padding: 10px 20px;">Send Verification Code</button>' +
                        '</div>' +
                        '<div id="otp-input-section" style="display: none;">' +
                        '<label for="otp_code">Enter 6-Digit Code</label>' +
                        '<input type="text" id="otp_code" name="otp_code" maxlength="6" pattern="[0-9]{6}" class="otp-input" placeholder="000000" style="width: 200px;">' +
                        '<button type="button" id="verify-otp-btn" class="ofst-btn" style="margin-left: 10px;">Verify Code</button>' +
                        '</div>' +
                        '<div id="otp-status"></div>' +
                        '</div>';

                    $('.ofst-event-fields').after(html);
                }
                $('#otp-verification-section').slideDown();
            }

            function hideOtpSection() {
                $('#otp-verification-section').slideUp();
                otpVerified = false;
            }

            // Send OTP
            $(document).on('click', '#send-otp-btn', function() {
                var email = $('#email').val();
                var eventId = $('#event_date_id').val();

                if (!email || !eventId) {
                    showStatus('error', 'Please enter your email and select an event first.');
                    return;
                }

                $(this).prop('disabled', true).text('Sending...');

                $.ajax({
                    url: ofst_cert_ajax.ajax_url,
                    type: 'POST',
                    data: {
                        action: 'ofst_send_otp',
                        email: email,
                        event_date_id: eventId,
                        nonce: ofst_cert_ajax.nonce
                    },
                    success: function(response) {
                        if (response.success) {
                            showStatus('success', response.data.message);
                            $('#otp-input-section').slideDown();
                            $('#send-otp-btn').text('Resend Code');
                        } else {
                            showStatus('error', response.data.message);
                        }
                        $('#send-otp-btn').prop('disabled', false);
                    },
                    error: function() {
                        showStatus('error', 'Failed to send verification code. Please try again.');
                        $('#send-otp-btn').prop('disabled', false).text('Send Verification Code');
                    }
                });
            });

            // Verify OTP (using email whitelist function)
            $(document).on('click', '#verify-otp-btn', function() {
                var code = $('#otp_code').val();
                var email = $('#email').val();
                var eventId = $('#event_date_id').val();

                if (!code || code.length !== 6) {
                    showStatus('error', 'Please enter a valid 6-digit code.');
                    return;
                }

                $(this).prop('disabled', true).text('Verifying...');

                $.ajax({
                    url: ofst_cert_ajax.ajax_url,
                    type: 'POST',
                    data: {
                        action: 'ofst_verify_otp',
                        email: email,
                        otp_code: code,
                        event_date_id: eventId,
                        nonce: ofst_cert_ajax.nonce
                    },
                    success: function(response) {
                        if (response.success) {
                            otpVerified = true;
                            showStatus('success', '✓ Email verified! You can now submit your request.');
                            $('#otp-input-section').slideUp();
                            $('#send-otp-btn').hide();
                        } else {
                            showStatus('error', response.data.message);
                            $('#verify-otp-btn').prop('disabled', false).text('Verify Code');
                        }
                    },
                    error: function() {
                        showStatus('error', 'Verification failed. Please try again.');
                        $('#verify-otp-btn').prop('disabled', false).text('Verify Code');
                    }
                });
            });

            function showStatus(type, message) {
                $('#otp-status').html('<div class="otp-status ' + type + '">' + message + '</div>');
            }

            // Prevent form submission if OTP not verified for Cromemart
            $('#ofst-student-cert-form').on('submit', function(e) {
                if (otpRequired && !otpVerified) {
                    e.preventDefault();
                    alert('Please verify your email address before submitting.');
                    $('#otp-verification-section')[0].scrollIntoView({
                        behavior: 'smooth'
                    });
                    return false;
                }
            });
        });
    </script>
<?php
}

/**
 * AJAX handler to verify OTP
 */
add_action('wp_ajax_ofst_verify_otp', 'ofst_cert_ajax_verify_otp');
add_action('wp_ajax_nopriv_ofst_verify_otp', 'ofst_cert_ajax_verify_otp');
function ofst_cert_ajax_verify_otp()
{
    if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'ofst_cert_ajax')) {
        wp_send_json_error(['message' => 'Security check failed']);
    }

    $email = sanitize_email($_POST['email'] ?? '');
    $otp_code = sanitize_text_field($_POST['otp_code'] ?? '');
    $event_date_id = absint($_POST['event_date_id'] ?? 0);

    $result = ofst_cert_verify_otp($email, $otp_code, $event_date_id);

    if ($result['verified']) {
        wp_send_json_success($result);
    } else {
        wp_send_json_error($result);
    }
}

/**
 * Cleanup expired OTP codes (run daily)
 */
add_action('wp_loaded', 'ofst_cert_schedule_otp_cleanup');
function ofst_cert_schedule_otp_cleanup()
{
    if (!wp_next_scheduled('ofst_cert_cleanup_otps')) {
        wp_schedule_event(time(), 'daily', 'ofst_cert_cleanup_otps');
    }
}

add_action('ofst_cert_cleanup_otps', 'ofst_cert_do_otp_cleanup');
function ofst_cert_do_otp_cleanup()
{
    global $wpdb;
    $table = $wpdb->prefix . 'ofst_cert_otp_codes';

    // Delete OTPs older than 24 hours
    $wpdb->query("DELETE FROM $table WHERE created_at < DATE_SUB(NOW(), INTERVAL 24 HOUR)");
}
