<?php

/**
 * Email Templates for Certificate System
 * Handles all email notifications
 */

if (!defined('ABSPATH')) exit;

/**
 * Send student confirmation email after request submission
 */
function ofst_cert_send_student_confirmation($email, $name, $course, $cert_id)
{
    $subject = 'Certificate Request Received - ' . ofst_cert_get_setting('company_name');

    $message = "
    <html>
    <body style='font-family: Arial, sans-serif; line-height: 1.6; color: #333;'>
        <div style='max-width: 600px; margin: 0 auto; padding: 20px; border: 1px solid #ddd;'>
            <h2 style='color: #070244;'>Certificate Request Received</h2>
            
            <p>Dear {$name},</p>
            
            <p>We have received your certificate request for:</p>
            
            <div style='background: #f5f5f5; padding: 15px; margin: 20px 0; border-left: 4px solid #070244;'>
                <strong>Course:</strong> {$course}<br>
                <strong>Certificate ID:</strong> {$cert_id}
            </div>
            
            <p>Your request is currently under review. You will receive your certificate via email once it has been approved by our team.</p>
            
            <p>If you have any questions, please contact us at " . ofst_cert_get_setting('support_email') . "</p>
            
            <p>Best regards,<br>
            " . ofst_cert_get_setting('company_name') . "</p>
        </div>
    </body>
    </html>
    ";

    $headers = array(
        'Content-Type: text/html; charset=UTF-8',
        'From: ' . ofst_cert_get_setting('from_name') . ' <' . ofst_cert_get_setting('from_email') . '>'
    );

    return wp_mail($email, $subject, $message, $headers);
}

/**
 * Send admin notification for new certificate request
 */
function ofst_cert_send_admin_notification($cert_id, $type, $student_name, $course, $vendor_name = '')
{
    $admin_email = get_option('admin_email');
    $subject = 'New Certificate Request - ' . $cert_id;

    $type_label = ucfirst($type);
    $submitted_by = $type === 'vendor' ? "Submitted by vendor: {$vendor_name}" : "Submitted by student";

    $message = "
    <html>
    <body style='font-family: Arial, sans-serif; line-height: 1.6; color: #333;'>
        <div style='max-width: 600px; margin: 0 auto; padding: 20px; border: 1px solid #ddd;'>
            <h2 style='color: #070244;'>New Certificate Request</h2>
            
            <div style='background: #f5f5f5; padding: 15px; margin: 20px 0;'>
                <strong>Certificate ID:</strong> {$cert_id}<br>
                <strong>Student:</strong> {$student_name}<br>
                <strong>Course:</strong> {$course}<br>
                <strong>Type:</strong> {$type_label} Request<br>
                <strong>Status:</strong> Pending Review
            </div>
            
            <p>{$submitted_by}</p>
            
            <p><a href='" . admin_url('admin.php?page=ofst-certificates&action=view&cert_id=' . $cert_id) . "' 
               style='display: inline-block; padding: 10px 20px; background: #070244; color: #fff; text-decoration: none; border-radius: 4px;'>
               Review Request
            </a></p>
        </div>
    </body>
    </html>
    ";

    $headers = array(
        'Content-Type: text/html; charset=UTF-8',
        'From: ' . ofst_cert_get_setting('from_name') . ' <' . ofst_cert_get_setting('from_email') . '>'
    );

    return wp_mail($admin_email, $subject, $message, $headers);
}

/**
 * Send vendor notification when student requests certificate for their course
 */
function ofst_cert_send_vendor_notification($vendor_id, $student_name, $course, $cert_id)
{
    $vendor = get_userdata($vendor_id);
    if (!$vendor) return false;

    $subject = 'Certificate Request for Your Course - ' . $cert_id;

    $message = "
    <html>
    <body style='font-family: Arial, sans-serif; line-height: 1.6; color: #333;'>
        <div style='max-width: 600px; margin: 0 auto; padding: 20px; border: 1px solid #ddd;'>
            <h2 style='color: #070244;'>Certificate Request Notification</h2>
            
            <p>Dear {$vendor->display_name},</p>
            
            <p>A student has requested a certificate for your course:</p>
            
            <div style='background: #f5f5f5; padding: 15px; margin: 20px 0; border-left: 4px solid #070244;'>
                <strong>Student:</strong> {$student_name}<br>
                <strong>Course:</strong> {$course}<br>
                <strong>Certificate ID:</strong> {$cert_id}
            </div>
            
            <p>The request is pending admin review. You will be notified once it's processed.</p>
            
            <p>Best regards,<br>
            " . ofst_cert_get_setting('company_name') . "</p>
        </div>
    </body>
    </html>
    ";

    $headers = array(
        'Content-Type: text/html; charset=UTF-8',
        'From: ' . ofst_cert_get_setting('from_name') . ' <' . ofst_cert_get_setting('from_email') . '>'
    );

    return wp_mail($vendor->user_email, $subject, $message, $headers);
}

/**
 * Send certificate to student after approval - FIXED VERSION
 * Simple pattern matching the working confirmation email
 */
function ofst_cert_send_certificate_email($request)
{
    $subject = 'Your Certificate is Ready - ' . ofst_cert_get_setting('company_name');

    $student_name = $request->first_name . ' ' . $request->last_name;
    
    // CRITICAL FIX: Get course/event name based on template type
    if ($request->template_type === 'cromemart') {
        $course_name = $request->event_theme ?: ($request->event_name ?: 'Event Participation');
    } else {
        $course_name = $request->product_name ?: 'Course Completion';
    }
    
    $dashboard_url = site_url('/my-certificates/');
    $company_name = ofst_cert_get_setting('company_name');

    $message = "
    <html>
    <body style='font-family: Arial, sans-serif; line-height: 1.6; color: #333;'>
        <div style='max-width: 600px; margin: 0 auto; padding: 20px; border: 1px solid #ddd;'>
            <h2 style='color: #070244;'>Congratulations!</h2>
            
            <p>Dear {$student_name},</p>
            
            <p>Your certificate has been approved and is now ready!</p>
            
            <div style='background: #f5f5f5; padding: 15px; margin: 20px 0; border-left: 4px solid #070244;'>
                <strong>Course:</strong> {$course_name}<br>
                <strong>Certificate ID:</strong> {$request->certificate_id}<br>
                <strong>Issued Date:</strong> " . date('F d, Y') . "
            </div>
            
            <p>You can view and download your certificate from your dashboard:</p>
            
            <p><a href='{$dashboard_url}' 
               style='display: inline-block; padding: 10px 20px; background: #070244; color: #fff; text-decoration: none; border-radius: 4px;'>
               View My Certificate
            </a></p>
            
            <p style='font-size: 13px; color: #666; margin-top: 15px;'><strong>Tip:</strong> For the best download experience, we recommend accessing your certificate from a PC or laptop.</p>
            
            <p>Keep this certificate safe for your records.</p>
            
            <p>Best regards,<br>
            {$company_name}</p>
        </div>
    </body>
    </html>
    ";

    $headers = array(
        'Content-Type: text/html; charset=UTF-8',
        'From: ' . ofst_cert_get_setting('from_name') . ' <' . ofst_cert_get_setting('from_email') . '>'
    );

    return wp_mail($request->email, $subject, $message, $headers);
}

/**
 * Send rejection email to student - FIXED VERSION
 * Simple pattern matching the working confirmation email
 */
function ofst_cert_send_rejection_email($request, $reason)
{
    $subject = 'Certificate Request Update - ' . ofst_cert_get_setting('company_name');

    $student_name = $request->first_name . ' ' . $request->last_name;
    
    // CRITICAL FIX: Get course/event name based on template type
    if ($request->template_type === 'cromemart') {
        $course_name = $request->event_theme ?: ($request->event_name ?: 'Event Participation');
    } else {
        $course_name = $request->product_name ?: 'Course Completion';
    }

    $support_email = ofst_cert_get_setting('support_email');
    $company_name = ofst_cert_get_setting('company_name');

    $message = "
    <html>
    <body style='font-family: Arial, sans-serif; line-height: 1.6; color: #333;'>
        <div style='max-width: 600px; margin: 0 auto; padding: 20px; border: 1px solid #ddd;'>
            <h2 style='color: #070244;'>Certificate Request Update</h2>
            
            <p>Dear {$student_name},</p>
            
            <p>We regret to inform you that your certificate request could not be approved at this time.</p>
            
            <div style='background: #f5f5f5; padding: 15px; margin: 20px 0; border-left: 4px solid #070244;'>
                <strong>Course:</strong> {$course_name}<br>
                <strong>Certificate ID:</strong> {$request->certificate_id}<br>
                <strong>Reason:</strong> {$reason}
            </div>
            
            <p>If you believe this is an error or have questions, please contact us at {$support_email}</p>
            
            <p>Best regards,<br>
            {$company_name}</p>
        </div>
    </body>
    </html>
    ";

    $headers = array(
        'Content-Type: text/html; charset=UTF-8',
        'From: ' . ofst_cert_get_setting('from_name') . ' <' . ofst_cert_get_setting('from_email') . '>'
    );

    return wp_mail($request->email, $subject, $message, $headers);
}

/**
 * Notify vendor that certificate was issued to their student
 */
function ofst_cert_notify_vendor_certificate_issued($request)
{
    $vendor = get_userdata($request->vendor_id);
    if (!$vendor) return false;

    $student_name = $request->first_name . ' ' . $request->last_name;
    $subject = 'Certificate Issued to Your Student - ' . $request->certificate_id;

    $message = "
    <html>
    <body style='font-family: Arial, sans-serif; line-height: 1.6; color: #333;'>
        <div style='max-width: 600px; margin: 0 auto; padding: 20px; border: 1px solid #ddd;'>
            <h2 style='color: #070244;'>Certificate Issued!</h2>
            
            <p>Dear {$vendor->display_name},</p>
            
            <p>Great news! A certificate has been issued to a student for your course:</p>
            
            <div style='background: #f5f5f5; padding: 15px; margin: 20px 0; border-left: 4px solid #070244;'>
                <strong>Student:</strong> {$student_name}<br>
                <strong>Course:</strong> {$request->product_name}<br>
                <strong>Certificate ID:</strong> {$request->certificate_id}<br>
                <strong>Issued Date:</strong> " . date('F d, Y') . "
            </div>
            
            <p>The student has received their certificate via email.</p>
            
            <p>Thank you for being part of our platform!</p>
            
            <p>Best regards,<br>
            " . ofst_cert_get_setting('company_name') . "</p>
        </div>
    </body>
    </html>
    ";

    $headers = array(
        'Content-Type: text/html; charset=UTF-8',
        'From: ' . ofst_cert_get_setting('from_name') . ' <' . ofst_cert_get_setting('from_email') . '>'
    );

    return wp_mail($vendor->user_email, $subject, $message, $headers);
}