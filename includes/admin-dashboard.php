<?php

/**
 * Admin Dashboard for Certificate Management
 * Handles all admin-facing functionality
 */

if (!defined('ABSPATH')) exit;

// Load certificate generator
require_once OFST_CERT_PLUGIN_DIR . 'includes/certificate-generator.php';

/**
 * Enqueue admin styles and toast notifications
 */
add_action('admin_enqueue_scripts', 'ofst_cert_admin_styles');
function ofst_cert_admin_styles($hook)
{
    // Only on our plugin pages
    if (strpos($hook, 'ofst-') === false && strpos($hook, 'certificates') === false) {
        return;
    }

    // Add inline styles
    $css = '
    /* Responsive Tables */
    .ofst-table-wrap {
        overflow-x: auto;
        margin: 0 0 20px 0;
        -webkit-overflow-scrolling: touch;
    }
    .ofst-table-wrap table {
        min-width: 600px;
    }
    
    /* Toast Notifications */
    .ofst-toast-container {
        position: fixed;
        top: 40px;
        right: 20px;
        z-index: 999999;
        display: flex;
        flex-direction: column;
        gap: 10px;
    }
    .ofst-toast {
        display: flex;
        align-items: center;
        gap: 12px;
        padding: 16px 20px;
        border-radius: 8px;
        box-shadow: 0 4px 20px rgba(0,0,0,0.15);
        animation: ofstSlideIn 0.3s ease-out;
        max-width: 400px;
        font-size: 14px;
    }
    .ofst-toast.success {
        background: linear-gradient(135deg, #10b981, #059669);
        color: white;
    }
    .ofst-toast.error {
        background: linear-gradient(135deg, #ef4444, #dc2626);
        color: white;
    }
    .ofst-toast.warning {
        background: linear-gradient(135deg, #f59e0b, #d97706);
        color: white;
    }
    .ofst-toast.info {
        background: linear-gradient(135deg, #3b82f6, #2563eb);
        color: white;
    }
    .ofst-toast-icon {
        font-size: 20px;
        flex-shrink: 0;
    }
    .ofst-toast-close {
        margin-left: auto;
        background: none;
        border: none;
        color: white;
        cursor: pointer;
        opacity: 0.7;
        font-size: 18px;
        padding: 0;
    }
    .ofst-toast-close:hover {
        opacity: 1;
    }
    @keyframes ofstSlideIn {
        from { transform: translateX(100%); opacity: 0; }
        to { transform: translateX(0); opacity: 1; }
    }
    @keyframes ofstSlideOut {
        from { transform: translateX(0); opacity: 1; }
        to { transform: translateX(100%); opacity: 0; }
    }
    .ofst-toast.hiding {
        animation: ofstSlideOut 0.3s ease-out forwards;
    }
    
    /* Edit Modal */
    .ofst-edit-modal {
        display: none;
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: rgba(0,0,0,0.5);
        z-index: 99999;
        justify-content: center;
        align-items: center;
    }
    .ofst-edit-modal.active {
        display: flex;
    }
    .ofst-edit-modal-content {
        background: white;
        padding: 30px;
        border-radius: 12px;
        max-width: 500px;
        width: 90%;
        max-height: 90vh;
        overflow-y: auto;
    }
    
    /* Participant Cards */
    .ofst-participant-grid {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 20px;
        margin: 20px 0;
    }
    .ofst-participant-grid .card {
        border-radius: 10px !important;
    }
    .ofst-participant-upload {
        border-radius: 10px !important;
    }
    @media (max-width: 782px) {
        .ofst-participant-grid {
            grid-template-columns: 1fr;
        }
    }
    
    /* Certificate Modal */
    .ofst-cert-modal {
        position: fixed;
        z-index: 100000;
        left: 0;
        top: 0;
        width: 100%;
        height: 100%;
        background-color: rgba(0, 0, 0, 0.5);
    }
    .ofst-cert-modal-content {
        background-color: #fff;
        margin: 2% auto;
        padding: 30px;
        border: 1px solid #888;
        width: 80%;
        max-width: 700px;
        max-height: 90vh;
        overflow-y: auto;
        border-radius: 8px;
    }
    .ofst-cert-modal-close {
        color: #aaa;
        float: right;
        font-size: 28px;
        font-weight: bold;
        cursor: pointer;
    }
    .ofst-cert-modal-close:hover {
        color: #000;
    }
    
    /* Certificate Type Badges */
    .cert-type-badge {
        padding: 3px 8px;
        border-radius: 3px;
        font-size: 11px;
        font-weight: 600;
        text-transform: uppercase;
    }
    .cert-type-badge.ofastshop {
        background: #e3f2fd;
        color: #1976d2;
    }
    .cert-type-badge.cromemart {
        background: #fff3e0;
        color: #f57c00;
    }
    ';
    wp_add_inline_style('wp-admin', $css);

    // Add toast JavaScript
    $js = '
    function ofstShowToast(message, type) {
        type = type || "success";
        var container = document.querySelector(".ofst-toast-container");
        if (!container) {
            container = document.createElement("div");
            container.className = "ofst-toast-container";
            document.body.appendChild(container);
        }
        
        var icons = {
            success: "✓",
            error: "✕",
            warning: "⚠",
            info: "ℹ"
        };
        
        var toast = document.createElement("div");
        toast.className = "ofst-toast " + type;
        toast.innerHTML = "<span class=\"ofst-toast-icon\">" + icons[type] + "</span>" +
                         "<span>" + message + "</span>" +
                         "<button class=\"ofst-toast-close\" onclick=\"this.parentElement.remove()\">×</button>";
        container.appendChild(toast);
        
        setTimeout(function() {
            toast.classList.add("hiding");
            setTimeout(function() { toast.remove(); }, 300);
        }, 5000);
    }
    ';
    wp_add_inline_script('jquery', $js);
}

/**
 * Show toast notification (PHP helper)
 * @param string $message The message to show
 * @param string $type success|error|warning|info
 */
function ofst_cert_toast($message, $type = 'success')
{
    $message = esc_js($message);
    echo "<script>jQuery(function(){ ofstShowToast('$message', '$type'); });</script>";
}

/**
 * Add admin menu
 */
add_action('admin_menu', 'ofst_cert_add_admin_menu');
function ofst_cert_add_admin_menu()
{
    add_menu_page(
        'Certificate Management',
        'Certificates',
        'manage_options',
        'ofst-certificates',
        'ofst_cert_admin_dashboard',
        'dashicons-awards',
        30
    );

    add_submenu_page(
        'ofst-certificates',
        'Pending Requests',
        'Pending Requests',
        'manage_options',
        'ofst-certificates',
        'ofst_cert_admin_dashboard'
    );

    add_submenu_page(
        'ofst-certificates',
        'Issued Certificates',
        'Issued Certificates',
        'manage_options',
        'ofst-certificates-issued',
        'ofst_cert_issued_page'
    );

    add_submenu_page(
        'ofst-certificates',
        'Failed Certificates',
        'Failed Certificates',
        'manage_options',
        'ofst-certificates-failed',
        'ofst_cert_failed_page'
    );

    // Rejected Certificates
    add_submenu_page(
        'ofst-certificates',
        'Rejected Certificates',
        'Rejected',
        'manage_options',
        'ofst-certificates-rejected',
        'ofst_cert_rejected_page'
    );

    add_submenu_page(
        'ofst-certificates',
        'Verification Log',
        'Verification Log',
        'manage_options',
        'ofst-certificates-log',
        'ofst_cert_verification_log'
    );

    // V2.0: Institution Management (for Cromemart)
    add_submenu_page(
        'ofst-certificates',
        'Institutions',
        'Institutions',
        'manage_options',
        'ofst-institutions',
        'ofst_cert_institutions_page'
    );

    // V2.0: Event Dates (for Cromemart)
    add_submenu_page(
        'ofst-certificates',
        'Event Dates',
        'Event Dates',
        'manage_options',
        'ofst-event-dates',
        'ofst_cert_event_dates_page'
    );

    // V2.3: Event Participants Roster
    add_submenu_page(
        'ofst-certificates',
        'Event Participants',
        'Participants',
        'manage_options',
        'ofst-participants',
        'ofst_cert_participants_page'
    );

    // Settings (moved to bottom)
    add_submenu_page(
        'ofst-certificates',
        'Settings',
        'Settings',
        'manage_options',
        'ofst-certificates-settings',
        'ofst_cert_settings_page'
    );
}

/**
 * Main Admin Dashboard - Pending Requests
 */
function ofst_cert_admin_dashboard()
{
    // Handle bulk actions
    if (isset($_POST['ofst_bulk_action'])) {
        check_admin_referer('ofst_bulk_action_nonce');

        $action = sanitize_text_field($_POST['ofst_bulk_action']);
        $cert_ids = isset($_POST['cert_ids']) ? array_map('absint', $_POST['cert_ids']) : [];

        if (empty($action)) {
            ofst_cert_toast('Please select a bulk action.', 'warning');
        } elseif (empty($cert_ids)) {
            ofst_cert_toast('Please select at least one certificate.', 'warning');
        } else {
            $success_count = 0;
            foreach ($cert_ids as $cert_id) {
                if ($action === 'approve') {
                    $result = ofst_cert_approve_request($cert_id);
                    if ($result['success']) $success_count++;
                } elseif ($action === 'reject') {
                    ofst_cert_reject_request($cert_id, 'Bulk rejection');
                    $success_count++;
                }
            }
            ofst_cert_toast($success_count . ' certificate(s) processed successfully!', 'success');
        }
    }

    // Handle single approval/rejection (not view)
    if (isset($_POST['ofst_approve_cert']) && isset($_POST['cert_id'])) {
        check_admin_referer('ofst_cert_approve_' . $_POST['cert_id']);

        $cert_id = absint($_POST['cert_id']);
        $completion_date = isset($_POST['completion_date']) ? sanitize_text_field($_POST['completion_date']) : date('Y-m-d');

        $result = ofst_cert_approve_request($cert_id, $completion_date);

        if ($result['success']) {
            // Redirect to avoid resubmission and close modal
            echo '<script>window.location.href="admin.php?page=ofst-certificates&message=approved";</script>';
            exit;
        } else {
            ofst_cert_toast($result['error'], 'error');
        }
    }

    if (isset($_GET['message']) && $_GET['message'] === 'approved') {
        ofst_cert_toast('Certificate generated and issued! Email sent to student.', 'success');
    }

    if (isset($_GET['action']) && isset($_GET['cert_id']) && $_GET['action'] !== 'view') {
        check_admin_referer('ofst_cert_action_' . $_GET['cert_id']);

        $cert_id = absint($_GET['cert_id']);
        $action = sanitize_text_field($_GET['action']);

        if ($action === 'approve-quick') {
            // Quick approve with today's date
            $result = ofst_cert_approve_request($cert_id, date('Y-m-d'));
            if ($result['success']) {
                ofst_cert_toast('Certificate generated and issued! Email sent to student.', 'success');
            } else {
                ofst_cert_toast($result['error'], 'error');
            }
        } elseif ($action === 'reject') {
            $reason = isset($_GET['reason']) ? sanitize_text_field($_GET['reason']) : 'Not specified';
            ofst_cert_reject_request($cert_id, $reason);

            // Send rejection email - V2.5 FIX: JOIN event data for Cromemart certificates
            global $wpdb;
            $table = $wpdb->prefix . 'ofst_cert_requests';
            $event_table = $wpdb->prefix . 'ofst_cert_event_dates';
            
            $request = $wpdb->get_row($wpdb->prepare("
                SELECT r.*, e.event_theme, e.event_name 
                FROM $table r 
                LEFT JOIN $event_table e ON r.event_date_id = e.id 
                WHERE r.id = %d
            ", $cert_id));
            
            if ($request) {
                ofst_cert_send_rejection_email($request, $reason);
            }

            ofst_cert_toast('Certificate request rejected. Email sent to student.', 'warning');
        }
    }

    global $wpdb;
    $table = $wpdb->prefix . 'ofst_cert_requests';
    $event_table = $wpdb->prefix . 'ofst_cert_event_dates';

    // Get all pending requests with event theme for Cromemart certificates
    $requests = $wpdb->get_results("
        SELECT r.*, e.event_theme, e.event_name, e.event_date, i.institution_name 
        FROM $table r 
        LEFT JOIN $event_table e ON r.event_date_id = e.id 
        LEFT JOIN {$wpdb->prefix}ofst_cert_institutions i ON r.institution_id = i.id
        WHERE r.status = 'pending' 
        ORDER BY r.requested_date DESC
    ");

?>
    <div class="wrap">
        <h1>Certificate Management - Pending Requests</h1>

        <?php if (empty($requests)): ?>
            <div class="notice notice-info">
                <p>No pending certificate requests.</p>
            </div>
        <?php else: ?>
            <form method="post">
                <?php wp_nonce_field('ofst_bulk_action_nonce'); ?>

                <div class="tablenav top">
                    <div class="alignleft actions bulkactions">
                        <select name="ofst_bulk_action">
                            <option value="">Bulk Actions</option>
                            <option value="approve">Approve</option>
                            <option value="reject">Reject</option>
                        </select>
                        <input type="submit" class="button action" value="Apply">
                    </div>

                    <table class="wp-list-table widefat fixed striped">
                        <thead>
                            <tr>
                                <th width="40"><input type="checkbox" id="select-all"></th>
                                <th>Certificate ID</th>
                                <th>Student Name</th>
                                <th>Email</th>
                                <th>Cert Type</th>
                                <th>Course/Event</th>
                                <th>Requested</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($requests as $req): ?>
                                <tr>
                                    <td><input type="checkbox" name="cert_ids[]" value="<?php echo $req->id; ?>" class="cert-checkbox"></td>
                                    <td><strong><?php echo esc_html($req->certificate_id); ?></strong></td>
                                    <td><?php echo esc_html($req->first_name . ' ' . $req->last_name); ?></td>
                                    <td><?php echo esc_html($req->email); ?></td>
                                    <td>
                                        <span class="cert-type-badge <?php echo esc_attr($req->template_type); ?>">
                                            <?php echo $req->template_type === 'cromemart' ? 'Cromemart' : 'Ofastshop'; ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php
                                        if ($req->template_type === 'cromemart') {
                                            // Show event theme for Cromemart certificates
                                            $display = $req->event_theme ?: ($req->event_name ?: '-');
                                            echo esc_html($display);
                                        } else {
                                            // Show product name for Ofastshop
                                            echo esc_html($req->product_name ?: '-');
                                        }
                                        ?>
                                    </td>
                                    <td><?php echo date('M d, Y', strtotime($req->requested_date)); ?></td>
                                    <td>
                                        <button type="button" class="button button-small" onclick="openPendingModal(<?php echo $req->id; ?>)">View</button>
                                        <a href="#"
                                            class="button button-small reject-btn"
                                            data-cert-id="<?php echo $req->id; ?>"
                                            data-nonce="<?php echo wp_create_nonce('ofst_cert_action_' . $req->id); ?>">Reject</a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
            </form>

            <!-- Hidden Modals for Each Pending Certificate -->
            <?php foreach ($requests as $req): 
                $instructor_name = ofst_cert_get_instructor_name($req->product_id, $req->vendor_id);
            ?>
                <div id="pending-modal-<?php echo $req->id; ?>" class="ofst-cert-modal" style="display:none;">
                    <div class="ofst-cert-modal-content">
                        <span class="ofst-cert-modal-close" onclick="closePendingModal(<?php echo $req->id; ?>)">&times;</span>
                        <h2>Certificate Request Details</h2>

                        <table class="form-table">
                            <tr>
                                <th>Certificate ID:</th>
                                <td><?php echo esc_html($req->certificate_id); ?></td>
                            </tr>
                            <tr>
                                <th>Request Type:</th>
                                <td><?php echo ucfirst($req->request_type); ?></td>
                            </tr>
                            <tr>
                                <th>Student Name:</th>
                                <td><?php echo esc_html($req->first_name . ' ' . $req->last_name); ?></td>
                            </tr>
                            <tr>
                                <th>Email:</th>
                                <td><?php echo esc_html($req->email); ?></td>
                            </tr>
                            <tr>
                                <th>Phone:</th>
                                <td><?php echo esc_html($req->phone); ?></td>
                            </tr>
                            <?php if ($req->template_type === 'cromemart'): ?>
                                <tr>
                                    <th>Institution:</th>
                                    <td><?php echo esc_html($req->institution_name ?? '-'); ?></td>
                                </tr>
                                <tr>
                                    <th>Event:</th>
                                    <td><?php echo esc_html($req->event_name ?: '-'); ?></td>
                                </tr>
                                <tr>
                                    <th>Course:</th>
                                    <td><?php echo esc_html($req->event_theme ?: '-'); ?></td>
                                </tr>
                            <?php else: ?>
                                <tr>
                                    <th>Course:</th>
                                    <td><?php echo esc_html($req->product_name ?: '-'); ?></td>
                                </tr>
                            <?php endif; ?>
                            <?php if ($req->instructor_name): ?>
                                <tr>
                                    <th>Instructor:</th>
                                    <td><?php echo esc_html($req->instructor_name); ?></td>
                                </tr>
                            <?php endif; ?>
                            <?php if ($req->project_link): ?>
                                <tr>
                                    <th>Project Link:</th>
                                    <td><a href="<?php echo esc_url($req->project_link); ?>" target="_blank"><?php echo esc_html($req->project_link); ?></a></td>
                                </tr>
                            <?php endif; ?>
                            <?php if ($req->vendor_notes): ?>
                                <tr>
                                    <th>Vendor Notes:</th>
                                    <td><?php echo esc_html($req->vendor_notes); ?></td>
                                </tr>
                            <?php endif; ?>
                            <tr>
                                <th>Requested Date:</th>
                                <td><?php echo date('F d, Y g:i A', strtotime($req->requested_date)); ?></td>
                            </tr>
                        </table>

                        <h3>🎨 Generate & Issue Certificate</h3>
                        <form method="post">
                            <?php wp_nonce_field('ofst_cert_approve_' . $req->id); ?>
                            <input type="hidden" name="ofst_approve_cert" value="1">
                            <input type="hidden" name="cert_id" value="<?php echo $req->id; ?>">

                            <table class="form-table">
                                <tr>
                                    <th>Completion Date:</th>
                                    <td>
                                        <input type="date" name="completion_date"
                                            value="<?php echo $req->completion_date ? esc_attr($req->completion_date) : date('Y-m-d'); ?>"
                                            required>
                                        <p class="description">Date to show on the certificate.</p>
                                    </td>
                                </tr>
                                <tr>
                                    <th>Instructor:</th>
                                    <td>
                                        <strong><?php echo esc_html($instructor_name); ?></strong>
                                        <p class="description">Auto-detected from course/product author.</p>
                                    </td>
                                </tr>
                            </table>

                            <p>
                                <button type="submit" name="ofst_approve_cert" class="button button-primary button-large"
                                    onclick="return confirm('Generate certificate and send to student?')">
                                    ✅ Generate & Issue Certificate
                                </button>
                                <a href="#"
                                    class="button button-large reject-btn"
                                    data-cert-id="<?php echo $req->id; ?>"
                                    data-nonce="<?php echo wp_create_nonce('ofst_cert_action_' . $req->id); ?>">Reject Request</a>
                                <button type="button" class="button button-large" onclick="closePendingModal(<?php echo $req->id; ?>)">Close</button>
                            </p>
                        </form>

                        <div style="margin-top: 20px; padding: 15px; background: #f0f0f1; border-left: 4px solid #2271b1;">
                            <strong>ℹ️ Auto-Generation:</strong> The certificate will be automatically generated using the template with the student's details overlaid.
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>

        <?php endif; ?>
    </div>

    <script>
        function openPendingModal(id) {
            document.getElementById('pending-modal-' + id).style.display = 'block';
        }
        function closePendingModal(id) {
            document.getElementById('pending-modal-' + id).style.display = 'none';
        }

        document.getElementById('select-all')?.addEventListener('change', function() {
            document.querySelectorAll('.cert-checkbox').forEach(cb => cb.checked = this.checked);
        });

        // Handle reject buttons with proper nonces
        document.querySelectorAll('.reject-btn').forEach(function(btn) {
            btn.addEventListener('click', function(e) {
                e.preventDefault();
                var certId = this.getAttribute('data-cert-id');
                var nonce = this.getAttribute('data-nonce');
                var reason = prompt('Rejection reason (optional):');
                if (reason !== null) {
                    window.location.href = '?page=ofst-certificates&action=reject&cert_id=' + certId +
                        '&reason=' + encodeURIComponent(reason) +
                        '&_wpnonce=' + nonce;
                }
            });
        });

        // Close modal when clicking outside
        document.querySelectorAll('.ofst-cert-modal').forEach(function(modal) {
            modal.addEventListener('click', function(e) {
                if (e.target === this) {
                    this.style.display = 'none';
                }
            });
        });
    </script>
<?php
}

/**
 * Approve certificate request - AUTO-GENERATES certificate
 * 
 * @param int $request_id The request ID to approve
 * @param string $completion_date The completion date (Y-m-d format)
 * @return array ['success' => bool, 'error' => string]
 */
function ofst_cert_approve_request($request_id, $completion_date = null)
{
    global $wpdb;
    $table = $wpdb->prefix . 'ofst_cert_requests';

    $request = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE id = %d", $request_id));

    if (!$request) {
        return ['success' => false, 'error' => 'Request not found'];
    }

    // Use today's date if not provided
    if (!$completion_date) {
        $completion_date = date('Y-m-d');
    }

    // Update completion date in the request
    $wpdb->update(
        $table,
        ['completion_date' => $completion_date],
        ['id' => $request_id]
    );

    // Refresh request data
    $request = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE id = %d", $request_id));

    // Generate certificate automatically
    $gen_result = ofst_cert_generate_certificate($request_id);

    if (!$gen_result['success']) {
        // Log generation failure
        ofst_cert_log_generation_failure($request_id, $gen_result['error']);
        return ['success' => false, 'error' => 'Certificate generation failed: ' . $gen_result['error']];
    }

    // Update status to issued with certificate file
    $wpdb->update(
        $table,
        [
            'status' => 'issued',
            'certificate_file' => $gen_result['file_url'],
            'processed_date' => current_time('mysql'),
            'processed_by' => get_current_user_id(),
            'rejection_reason' => null
        ],
        ['id' => $request_id]
    );

    // Get updated request data for email (join event table for Cromemart event_theme)
    $event_table = $wpdb->prefix . 'ofst_cert_event_dates';
    $request = $wpdb->get_row($wpdb->prepare("
        SELECT r.*, e.event_theme, e.event_name 
        FROM $table r 
        LEFT JOIN $event_table e ON r.event_date_id = e.id 
        WHERE r.id = %d
    ", $request_id));

    // V2.3: Auto-remove from participants roster (for Cromemart certificates)
    if ($request->template_type === 'cromemart' && !empty($request->event_date_id)) {
        $participants_table = $wpdb->prefix . 'ofst_cert_event_participants';
        $full_name = trim($request->first_name . ' ' . $request->last_name);

        // Find and remove matching participant (case-insensitive name match + same event)
        $wpdb->query($wpdb->prepare(
            "DELETE FROM $participants_table 
             WHERE event_date_id = %d 
             AND LOWER(TRIM(full_name)) = LOWER(%s)",
            $request->event_date_id,
            $full_name
        ));
    }

    // Send email to student with certificate
    $email_sent = ofst_cert_send_certificate_email($request);

    if (!$email_sent) {
        // Log email failure but don't fail the whole process
        ofst_cert_log_email_failure($request_id, 'Email sending failed');
        return ['success' => true, 'error' => 'Certificate generated but email failed. You can resend from Failed Certificates page.'];
    }

    // Notify vendor that certificate was issued (if vendor exists)
    if (!empty($request->vendor_id)) {
        ofst_cert_notify_vendor_certificate_issued($request);
    }

    return ['success' => true, 'error' => ''];
}


/**
 * Reject certificate request
 */
function ofst_cert_reject_request($request_id, $reason = '')
{
    global $wpdb;
    $table = $wpdb->prefix . 'ofst_cert_requests';

    $wpdb->update(
        $table,
        array(
            'status' => 'rejected',
            'rejection_reason' => $reason,
            'processed_date' => current_time('mysql'),
            'processed_by' => get_current_user_id()
        ),
        array('id' => $request_id)
    );

    return true;
}

/**
 * Issued Certificates Page
 */
function ofst_cert_issued_page()
{
    global $wpdb;
    $table = $wpdb->prefix . 'ofst_cert_requests';
    $event_table = $wpdb->prefix . 'ofst_cert_event_dates';

    // Handle resend email action
    if (isset($_GET['action']) && $_GET['action'] === 'resend' && isset($_GET['cert_id'])) {
        check_admin_referer('ofst_resend_email_' . $_GET['cert_id']);
        $cert_id = absint($_GET['cert_id']);
        $result = ofst_cert_retry_email($cert_id);
        if ($result['success']) {
            ofst_cert_toast('Email resent successfully!', 'success');
        } else {
            ofst_cert_toast($result['error'], 'error');
        }
    }

    // Get issued certificates with event theme for Cromemart
    $certificates = $wpdb->get_results("
        SELECT r.*, e.event_theme, e.event_name, e.event_date, i.institution_name 
        FROM $table r 
        LEFT JOIN $event_table e ON r.event_date_id = e.id 
        LEFT JOIN {$wpdb->prefix}ofst_cert_institutions i ON r.institution_id = i.id
        WHERE r.status = 'issued' 
        ORDER BY r.processed_date DESC 
        LIMIT 100
    ");

?>
    <div class="wrap">
        <h1>Issued Certificates</h1>
        <p><a href="?page=ofst-certificates" class="button">← Back to Dashboard</a></p>

        <?php if (empty($certificates)): ?>
            <div class="notice notice-info">
                <p>No certificates have been issued yet.</p>
            </div>
        <?php else: ?>
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th>Certificate ID</th>
                        <th>Student Name</th>
                        <th>Course</th>
                        <th>Issued Date</th>
                        <th>Issued By</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($certificates as $cert):
                        $issued_by = get_userdata($cert->processed_by);
                    ?>
                        <tr>
                            <td><strong><?php echo esc_html($cert->certificate_id); ?></strong></td>
                            <td><?php echo esc_html($cert->first_name . ' ' . $cert->last_name); ?></td>
                            <td>
                                <?php
                                if ($cert->template_type === 'cromemart') {
                                    // Show event theme for Cromemart certificates
                                    $display = $cert->event_theme ?: ($cert->event_name ?: '-');
                                    echo esc_html($display);
                                } else {
                                    echo esc_html($cert->product_name ?: '-');
                                }
                                ?>
                            </td>
                            <td><?php echo date('M d, Y', strtotime($cert->processed_date)); ?></td>
                            <td><?php echo $issued_by ? esc_html($issued_by->display_name) : 'System'; ?></td>
                            <td>
                                <?php if ($cert->certificate_file): ?>
                                    <a href="<?php echo esc_url($cert->certificate_file); ?>" class="button button-small" target="_blank">View</a>
                                <?php endif; ?>
                                <a href="?page=ofst-certificates-issued&action=resend&cert_id=<?php echo $cert->id; ?>&_wpnonce=<?php echo wp_create_nonce('ofst_resend_email_' . $cert->id); ?>"
                                    class="button button-small"
                                    onclick="return confirm('Resend certificate email to <?php echo esc_js($cert->email); ?>?')">
                                    📧 Resend Email
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
<?php
}

/**
 * Failed Certificates Page
 * Shows certificates that failed generation or email sending
 */
function ofst_cert_failed_page()
{
    global $wpdb;
    $table = $wpdb->prefix . 'ofst_cert_requests';
    $event_table = $wpdb->prefix . 'ofst_cert_event_dates';

    // Handle retry actions
    if (isset($_POST['retry_action']) && isset($_POST['cert_id'])) {
        check_admin_referer('ofst_retry_action_' . $_POST['cert_id']);
        $cert_id = absint($_POST['cert_id']);
        $action = sanitize_text_field($_POST['retry_action']);
        $completion_date = isset($_POST['completion_date']) ? sanitize_text_field($_POST['completion_date']) : date('Y-m-d');

        if ($action === 'regenerate') {
            $result = ofst_cert_retry_generation($cert_id, $completion_date);
            if ($result['success']) {
                // Try sending email after regeneration
                $email_result = ofst_cert_retry_email($cert_id);
                if ($email_result['success']) {
                    ofst_cert_toast('Certificate regenerated and email sent successfully!', 'success');
                } else {
                    ofst_cert_toast('Certificate regenerated but email failed. You can try resending.', 'warning');
                }
            } else {
                ofst_cert_toast($result['error'], 'error');
            }
        } elseif ($action === 'resend') {
            $result = ofst_cert_retry_email($cert_id);
            if ($result['success']) {
                ofst_cert_toast('Email resent successfully!', 'success');
            } else {
                ofst_cert_toast($result['error'], 'error');
            }
        }
    }

    // Get failed certificates with event theme for Cromemart
    $failed = $wpdb->get_results("
        SELECT r.*, e.event_theme, e.event_name 
        FROM $table r 
        LEFT JOIN $event_table e ON r.event_date_id = e.id 
        WHERE r.status IN ('generation_failed', 'email_failed') 
        ORDER BY r.processed_date DESC
    ");

?>
    <div class="wrap">
        <h1>⚠️ Failed Certificates</h1>
        <p><a href="?page=ofst-certificates" class="button">← Back to Dashboard</a></p>
        <p>Certificates that failed during generation or email sending. You can retry these actions below.</p>

        <?php if (empty($failed)): ?>
            <div class="notice notice-success">
                <p>🎉 No failed certificates! All certificates are working properly.</p>
            </div>
        <?php else: ?>
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th width="130">Certificate ID</th>
                        <th>Student</th>
                        <th>Email</th>
                        <th>Course</th>
                        <th width="120">Status</th>
                        <th width="200">Error</th>
                        <th width="280">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($failed as $cert): ?>
                        <tr>
                            <td><strong><?php echo esc_html($cert->certificate_id); ?></strong></td>
                            <td><?php echo esc_html($cert->first_name . ' ' . $cert->last_name); ?></td>
                            <td><?php echo esc_html($cert->email); ?></td>
                            <td>
                                <?php
                                if ($cert->template_type === 'cromemart') {
                                    $display = $cert->event_theme ?: ($cert->event_name ?: '-');
                                    echo esc_html($display);
                                } else {
                                    echo esc_html($cert->product_name ?: '-');
                                }
                                ?>
                            </td>
                            <td>
                                <?php if ($cert->status === 'generation_failed'): ?>
                                    <span style="background: #f8d7da; color: #721c24; padding: 3px 8px; border-radius: 3px; font-size: 11px;">
                                        Generation Failed
                                    </span>
                                <?php else: ?>
                                    <span style="background: #fff3cd; color: #856404; padding: 3px 8px; border-radius: 3px; font-size: 11px;">
                                        Email Failed
                                    </span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <small><?php echo esc_html(substr($cert->rejection_reason, 0, 100)); ?></small>
                            </td>
                            <td>
                                <form method="post" style="display: inline-flex; gap: 5px; align-items: center;">
                                    <?php wp_nonce_field('ofst_retry_action_' . $cert->id); ?>
                                    <input type="hidden" name="cert_id" value="<?php echo $cert->id; ?>">

                                    <?php if ($cert->status === 'generation_failed'): ?>
                                        <input type="date" name="completion_date" value="<?php echo date('Y-m-d'); ?>" style="width: 130px;">
                                        <button type="submit" name="retry_action" value="regenerate" class="button button-primary button-small">
                                            🔄 Regenerate
                                        </button>
                                    <?php else: ?>
                                        <button type="submit" name="retry_action" value="resend" class="button button-primary button-small">
                                            📧 Resend Email
                                        </button>
                                        <button type="submit" name="retry_action" value="regenerate" class="button button-small"
                                            onclick="return confirm('This will regenerate the certificate. Continue?')">
                                            🔄 Regenerate
                                        </button>
                                        <input type="hidden" name="completion_date" value="<?php echo $cert->completion_date ?: date('Y-m-d'); ?>">
                                    <?php endif; ?>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>

    <style>
        .ofst-failed-table td {
            vertical-align: middle;
        }
    </style>
<?php
}

/**
 * Rejected Certificates Page
 * Shows certificates that were rejected by admin
 */
function ofst_cert_rejected_page()
{
    global $wpdb;
    $table = $wpdb->prefix . 'ofst_cert_requests';
    $event_table = $wpdb->prefix . 'ofst_cert_event_dates';

    // Handle re-approval
    if (isset($_POST['reapprove_cert']) && isset($_POST['cert_id'])) {
        check_admin_referer('ofst_reapprove_' . $_POST['cert_id']);

        $cert_id = absint($_POST['cert_id']);
        $completion_date = isset($_POST['completion_date']) ? sanitize_text_field($_POST['completion_date']) : date('Y-m-d');

        $result = ofst_cert_approve_request($cert_id, $completion_date);

        if ($result['success']) {
            ofst_cert_toast('Certificate approved and generated successfully!', 'success');
        } else {
            ofst_cert_toast($result['error'], 'error');
        }
    }

    // Handle permanent deletion
    if (isset($_GET['delete_rejected']) && isset($_GET['_wpnonce'])) {
        $id = absint($_GET['delete_rejected']);
        if (wp_verify_nonce($_GET['_wpnonce'], 'delete_rejected_' . $id)) {
            $wpdb->delete($table, ['id' => $id]);
            ofst_cert_toast('Rejected certificate deleted permanently.', 'success');
        }
    }

    // Get rejected certificates with event data
    $rejected = $wpdb->get_results("
        SELECT r.*, e.event_theme, e.event_name, e.event_date, i.institution_name 
        FROM $table r 
        LEFT JOIN $event_table e ON r.event_date_id = e.id 
        LEFT JOIN {$wpdb->prefix}ofst_cert_institutions i ON r.institution_id = i.id
        WHERE r.status = 'rejected' 
        ORDER BY r.processed_date DESC
    ");

?>
    <div class="wrap">
        <h1>Rejected Certificates</h1>
        <p><a href="?page=ofst-certificates" class="button">← Back to Pending</a></p>
        <p>Certificates that were rejected. You can view the rejection reason or re-approve them.</p>

        <?php if (empty($rejected)): ?>
            <div class="notice notice-info">
                <p>No rejected certificates.</p>
            </div>
        <?php else: ?>
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th width="130">Certificate ID</th>
                        <th>Student</th>
                        <th>Email</th>
                        <th>Course/Event</th>
                        <th>Rejection Reason</th>
                        <th>Rejected Date</th>
                        <th width="220">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($rejected as $cert): ?>
                        <tr>
                            <td><strong><?php echo esc_html($cert->certificate_id); ?></strong></td>
                            <td><?php echo esc_html($cert->first_name . ' ' . $cert->last_name); ?></td>
                            <td><?php echo esc_html($cert->email); ?></td>
                            <td>
                                <?php
                                if ($cert->template_type === 'cromemart') {
                                    echo esc_html($cert->event_theme ?: ($cert->event_name ?: '-'));
                                } else {
                                    echo esc_html($cert->product_name ?: '-');
                                }
                                ?>
                            </td>
                            <td>
                                <span style="color: #856404; background: #fff3cd; padding: 3px 8px; border-radius: 4px; font-size: 12px;">
                                    <?php echo esc_html($cert->rejection_reason ?: 'Not specified'); ?>
                                </span>
                            </td>
                            <td><?php echo $cert->processed_date ? date('M d, Y', strtotime($cert->processed_date)) : '-'; ?></td>
                            <td>
                                <button type="button" class="button button-small" onclick="openRejectedModal(<?php echo $cert->id; ?>)">View Details</button>
                                <a href="<?php echo wp_nonce_url(admin_url('admin.php?page=ofst-certificates-rejected&delete_rejected=' . $cert->id), 'delete_rejected_' . $cert->id); ?>"
                                    onclick="return confirm('Permanently delete this rejected request?');"
                                    class="button button-small" style="color: #a00;">Delete</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

            <!-- Hidden Modals for Each Certificate -->
            <?php foreach ($rejected as $cert): ?>
                <div id="rejected-modal-<?php echo $cert->id; ?>" class="ofst-cert-modal" style="display:none;">
                    <div class="ofst-cert-modal-content">
                        <span class="ofst-cert-modal-close" onclick="closeRejectedModal(<?php echo $cert->id; ?>)">&times;</span>
                        <h2>Rejected Certificate Details</h2>

                        <div style="background: #f8d7da; border: 1px solid #f5c6cb; padding: 15px; border-radius: 8px; margin-bottom: 20px;">
                            <strong style="color: #721c24;">Rejection Reason:</strong>
                            <p style="color: #721c24; margin: 5px 0 0 0;"><?php echo esc_html($cert->rejection_reason ?: 'Not specified'); ?></p>
                        </div>

                        <table class="form-table">
                            <tr>
                                <th>Certificate ID</th>
                                <td><strong><?php echo esc_html($cert->certificate_id); ?></strong></td>
                            </tr>
                            <tr>
                                <th>Student Name</th>
                                <td><?php echo esc_html($cert->first_name . ' ' . $cert->last_name); ?></td>
                            </tr>
                            <tr>
                                <th>Email</th>
                                <td><?php echo esc_html($cert->email); ?></td>
                            </tr>
                            <tr>
                                <th>Certificate Type</th>
                                <td><?php echo $cert->template_type === 'cromemart' ? 'Cromemart (Event)' : 'Ofastshop (WooCommerce)'; ?></td>
                            </tr>
                            <?php if ($cert->template_type === 'cromemart'): ?>
                                <tr>
                                    <th>Institution</th>
                                    <td><?php echo esc_html($cert->institution_name ?: '-'); ?></td>
                                </tr>
                                <tr>
                                    <th>Event</th>
                                    <td><?php echo esc_html($cert->event_name ?: '-'); ?></td>
                                </tr>
                                <tr>
                                    <th>Event Theme</th>
                                    <td><?php echo esc_html($cert->event_theme ?: '-'); ?></td>
                                </tr>
                                <tr>
                                    <th>Event Date</th>
                                    <td><?php echo $cert->event_date ? date('M d, Y', strtotime($cert->event_date)) : '-'; ?></td>
                                </tr>
                            <?php else: ?>
                                <tr>
                                    <th>Product</th>
                                    <td><?php echo esc_html($cert->product_name ?: '-'); ?></td>
                                </tr>
                            <?php endif; ?>
                            <tr>
                                <th>Rejected On</th>
                                <td><?php echo $cert->processed_date ? date('M d, Y g:i A', strtotime($cert->processed_date)) : '-'; ?></td>
                            </tr>
                            <tr>
                                <th>Requested On</th>
                                <td><?php echo $cert->requested_date ? date('M d, Y g:i A', strtotime($cert->requested_date)) : '-'; ?></td>
                            </tr>
                        </table>

                        <hr style="margin: 20px 0;">

                        <h3>Re-approve This Certificate</h3>
                        <form method="post">
                            <?php wp_nonce_field('ofst_reapprove_' . $cert->id); ?>
                            <input type="hidden" name="cert_id" value="<?php echo $cert->id; ?>">
                            <table class="form-table">
                                <tr>
                                    <th>Completion Date</th>
                                    <td>
                                        <input type="date" name="completion_date" value="<?php echo date('Y-m-d'); ?>" required>
                                    </td>
                                </tr>
                            </table>
                            <p>
                                <button type="submit" name="reapprove_cert" class="button button-primary">
                                    ✓ Approve & Generate Certificate
                                </button>
                                <button type="button" class="button" onclick="closeRejectedModal(<?php echo $cert->id; ?>)">Cancel</button>
                            </p>
                        </form>
                    </div>
                </div>
            <?php endforeach; ?>

            <script>
            function openRejectedModal(id) {
                document.getElementById('rejected-modal-' + id).style.display = 'block';
            }
            function closeRejectedModal(id) {
                document.getElementById('rejected-modal-' + id).style.display = 'none';
            }
            // Close modal when clicking outside
            document.querySelectorAll('.ofst-cert-modal').forEach(function(modal) {
                modal.addEventListener('click', function(e) {
                    if (e.target === this) {
                        this.style.display = 'none';
                    }
                });
            });
            </script>
        <?php endif; ?>
    </div>
<?php
}


/**
 * Verification Log Page
 */
function ofst_cert_verification_log()
{
    global $wpdb;
    $table = $wpdb->prefix . 'ofst_cert_verifications';

    $logs = $wpdb->get_results("SELECT * FROM $table ORDER BY verified_date DESC LIMIT 100");

?>
    <div class="wrap">
        <h1>Certificate Verification Log</h1>
        <p><a href="?page=ofst-certificates" class="button">← Back to Dashboard</a></p>

        <?php if (empty($logs)): ?>
            <div class="notice notice-info">
                <p>No verification attempts logged yet.</p>
            </div>
        <?php else: ?>
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th>Date/Time</th>
                        <th>Certificate ID</th>
                        <th>Search Query</th>
                        <th>Result</th>
                        <th>IP Address</th>
                        <th>User</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($logs as $log):
                        $user = $log->verified_by_user ? get_userdata($log->verified_by_user) : null;
                    ?>
                        <tr>
                            <td><?php echo date('M d, Y g:i A', strtotime($log->verified_date)); ?></td>
                            <td><?php echo esc_html($log->certificate_id); ?></td>
                            <td><?php echo esc_html($log->search_query); ?></td>
                            <td>
                                <span class="result-badge <?php echo $log->result; ?>">
                                    <?php echo ucfirst($log->result); ?>
                                </span>
                            </td>
                            <td><?php echo esc_html($log->verified_by_ip); ?></td>
                            <td><?php echo $user ? esc_html($user->display_name) : 'Guest'; ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>

        <style>
            .result-badge {
                padding: 3px 8px;
                border-radius: 3px;
                font-size: 11px;
                font-weight: 600;
            }

            .result-badge.found {
                background: #d4edda;
                color: #155724;
            }

            .result-badge.not_found {
                background: #f8d7da;
                color: #721c24;
            }
        </style>
    <?php
}

/**
 * Settings Page
 */
function ofst_cert_settings_page()
{
    if (isset($_POST['ofst_save_settings'])) {
        check_admin_referer('ofst_settings_nonce');

        $settings = array(
            'support_email',
            'from_email',
            'from_name',
            'turnstile_site_key',
            'turnstile_secret_key'
        );

        foreach ($settings as $key) {
            if (isset($_POST[$key])) {
                ofst_cert_update_setting($key, sanitize_text_field($_POST[$key]));
            }
        }

        // Handle delete on uninstall checkbox
        $delete_on_uninstall = isset($_POST['delete_on_uninstall']) ? 'yes' : 'no';
        update_option('ofst_cert_delete_on_uninstall', $delete_on_uninstall);

        ofst_cert_toast('Settings saved successfully!', 'success');
    }

    ?>
        <div class="wrap">
            <h1>Certificate System Settings</h1>

            <form method="post">
                <?php wp_nonce_field('ofst_settings_nonce'); ?>

                <h2>Email Settings</h2>
                <table class="form-table">
                    <tr>
                        <th>From Email</th>
                        <td>
                            <input type="email" name="from_email" value="<?php echo esc_attr(ofst_cert_get_setting('from_email')); ?>" class="regular-text">
                            <p class="description">Email address used to send certificate emails</p>
                        </td>
                    </tr>
                    <tr>
                        <th>From Name</th>
                        <td>
                            <input type="text" name="from_name" value="<?php echo esc_attr(ofst_cert_get_setting('from_name')); ?>" class="regular-text">
                            <p class="description">Name shown in certificate emails</p>
                        </td>
                    </tr>
                    <tr>
                        <th>Support Email</th>
                        <td>
                            <input type="email" name="support_email" value="<?php echo esc_attr(ofst_cert_get_setting('support_email')); ?>" class="regular-text">
                            <p class="description">Email for student support inquiries</p>
                        </td>
                    </tr>
                </table>

                <h2>Security (Cloudflare Turnstile)</h2>
                <table class="form-table">
                    <tr>
                        <th>Turnstile Site Key</th>
                        <td>
                            <input type="text" name="turnstile_site_key" value="<?php echo esc_attr(ofst_cert_get_setting('turnstile_site_key')); ?>" class="large-text">
                        </td>
                    </tr>
                    <tr>
                        <th>Turnstile Secret Key</th>
                        <td>
                            <input type="text" name="turnstile_secret_key" value="<?php echo esc_attr(ofst_cert_get_setting('turnstile_secret_key')); ?>" class="large-text">
                        </td>
                    </tr>
                </table>

                <h2>Data Management</h2>
                <table class="form-table">
                    <tr>
                        <th>Delete Data on Uninstall</th>
                        <td>
                            <label>
                                <input type="checkbox" name="delete_on_uninstall" value="yes" <?php checked(get_option('ofst_cert_delete_on_uninstall', 'no'), 'yes'); ?>>
                                Delete all certificate data when plugin is deleted (not just deactivated)
                            </label>
                            <p class="description" style="color: #d63638;"><strong>Warning:</strong> This will permanently delete all certificates, requests, verification logs, and settings. This action cannot be undone!</p>
                        </td>
                    </tr>
                </table>

                <p class="submit">
                    <input type="submit" name="ofst_save_settings" class="button button-primary" value="Save Settings">
                </p>
            </form>
        </div>
    <?php
}

/**
 * =====================================================
 * V2.0: INSTITUTION MANAGEMENT PAGE
 * =====================================================
 */
function ofst_cert_institutions_page()
{
    global $wpdb;
    $table = $wpdb->prefix . 'ofst_cert_institutions';

    // Handle form submissions (Add/Edit)
    if (isset($_POST['ofst_add_institution']) && check_admin_referer('ofst_institution_action')) {
        $name = sanitize_text_field($_POST['institution_name']);
        $logo = esc_url_raw($_POST['institution_logo']);
        $edit_id = isset($_POST['edit_id']) ? absint($_POST['edit_id']) : 0;

        if (!empty($name)) {
            if ($edit_id) {
                // Update existing
                $wpdb->update($table, [
                    'institution_name' => $name,
                    'institution_logo' => $logo
                ], ['id' => $edit_id]);
                ofst_cert_toast('Institution updated successfully!', 'success');
            } else {
                // Insert new
                $wpdb->insert($table, [
                    'institution_name' => $name,
                    'institution_logo' => $logo,
                    'is_active' => 1,
                    'created_date' => current_time('mysql'),
                    'created_by' => get_current_user_id()
                ]);
                ofst_cert_toast('Institution added successfully!', 'success');
            }
        }
    }

    // Handle delete
    if (isset($_GET['delete_institution']) && check_admin_referer('delete_institution_' . $_GET['delete_institution'])) {
        $wpdb->delete($table, ['id' => absint($_GET['delete_institution'])]);
        ofst_cert_toast('Institution deleted!', 'success');
    }

    // Check if editing
    $editing = null;
    if (isset($_GET['edit_institution'])) {
        $editing = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE id = %d", absint($_GET['edit_institution'])));
    }

    // Get all institutions
    $institutions = $wpdb->get_results("SELECT * FROM $table ORDER BY institution_name ASC");
    ?>
        <div class="wrap">
            <h1>Institution Management</h1>
            <p>Manage institutions for Cromemart event certificates.</p>

            <!-- Add/Edit Form -->
            <div class="card" style="max-width: 500px; padding: 20px; margin-bottom: 20px;">
                <h2><?php echo $editing ? 'Edit Institution' : 'Add New Institution'; ?></h2>
                <form method="post">
                    <?php wp_nonce_field('ofst_institution_action'); ?>
                    <?php if ($editing): ?>
                        <input type="hidden" name="edit_id" value="<?php echo $editing->id; ?>">
                    <?php endif; ?>
                    <table class="form-table">
                        <tr>
                            <th><label for="institution_name">Institution Name *</label></th>
                            <td><input type="text" name="institution_name" id="institution_name" class="regular-text" required value="<?php echo $editing ? esc_attr($editing->institution_name) : ''; ?>"></td>
                        </tr>
                        <tr>
                            <th><label for="institution_logo">Logo URL</label></th>
                            <td><input type="url" name="institution_logo" id="institution_logo" class="regular-text" placeholder="https://..." value="<?php echo $editing ? esc_attr($editing->institution_logo) : ''; ?>"></td>
                        </tr>
                    </table>
                    <p>
                        <input type="submit" name="ofst_add_institution" class="button button-primary" value="<?php echo $editing ? 'Update Institution' : 'Add Institution'; ?>">
                        <?php if ($editing): ?>
                            <a href="<?php echo admin_url('admin.php?page=ofst-institutions'); ?>" class="button">Cancel</a>
                        <?php endif; ?>
                    </p>
                </form>
            </div>

            <!-- Institutions List -->
            <h2>All Institutions (<?php echo count($institutions); ?>)</h2>
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Name</th>
                        <th>Logo</th>
                        <th>Status</th>
                        <th>Created</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($institutions)): ?>
                        <tr>
                            <td colspan="6">No institutions yet. Add one above!</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($institutions as $inst): ?>
                            <tr>
                                <td><?php echo $inst->id; ?></td>
                                <td><strong><?php echo esc_html($inst->institution_name); ?></strong></td>
                                <td><?php echo $inst->institution_logo ? '<img src="' . esc_url($inst->institution_logo) . '" height="30">' : '-'; ?></td>
                                <td><?php echo $inst->is_active ? '<span style="color:green;">Active</span>' : '<span style="color:red;">Inactive</span>'; ?></td>
                                <td><?php echo date('M d, Y', strtotime($inst->created_date)); ?></td>
                                <td>
                                    <a href="<?php echo admin_url('admin.php?page=ofst-institutions&edit_institution=' . $inst->id); ?>" class="button button-small">Edit</a>
                                    <a href="<?php echo wp_nonce_url(admin_url('admin.php?page=ofst-institutions&delete_institution=' . $inst->id), 'delete_institution_' . $inst->id); ?>"
                                        onclick="return confirm('Delete this institution?');"
                                        style="color: red;">Delete</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    <?php
}

/**
 * =====================================================
 * V2.0: EVENT DATES MANAGEMENT PAGE
 * =====================================================
 */
function ofst_cert_event_dates_page()
{
    global $wpdb;
    $events_table = $wpdb->prefix . 'ofst_cert_event_dates';
    $inst_table = $wpdb->prefix . 'ofst_cert_institutions';

    // Handle add/edit event
    if (isset($_POST['ofst_add_event']) && check_admin_referer('ofst_event_action')) {
        $inst_id = absint($_POST['institution_id']);
        $name = sanitize_text_field($_POST['event_name']);
        $date = sanitize_text_field($_POST['event_date']);
        $theme = sanitize_text_field($_POST['event_theme']);
        $edit_id = isset($_POST['edit_id']) ? absint($_POST['edit_id']) : 0;

        if (!empty($inst_id) && !empty($name) && !empty($date)) {
            if ($edit_id) {
                // Update existing
                $wpdb->update($events_table, [
                    'institution_id' => $inst_id,
                    'event_name' => $name,
                    'event_date' => $date,
                    'event_theme' => $theme
                ], ['id' => $edit_id]);
                ofst_cert_toast('Event updated successfully!', 'success');
            } else {
                // Insert new
                $wpdb->insert($events_table, [
                    'institution_id' => $inst_id,
                    'event_name' => $name,
                    'event_date' => $date,
                    'event_theme' => $theme,
                    'is_active' => 1,
                    'created_date' => current_time('mysql'),
                    'created_by' => get_current_user_id()
                ]);
                ofst_cert_toast('Event added successfully!', 'success');
            }
        }
    }

    // Handle delete
    if (isset($_GET['delete_event']) && check_admin_referer('delete_event_' . $_GET['delete_event'])) {
        $wpdb->delete($events_table, ['id' => absint($_GET['delete_event'])]);
        ofst_cert_toast('Event deleted!', 'success');
    }

    // Check if editing
    $editing = null;
    if (isset($_GET['edit_event'])) {
        $editing = $wpdb->get_row($wpdb->prepare("SELECT * FROM $events_table WHERE id = %d", absint($_GET['edit_event'])));
    }

    // Get data
    $institutions = $wpdb->get_results("SELECT * FROM $inst_table WHERE is_active = 1 ORDER BY institution_name");
    $events = $wpdb->get_results("
        SELECT e.*, i.institution_name 
        FROM $events_table e 
        LEFT JOIN $inst_table i ON e.institution_id = i.id 
        ORDER BY e.event_date DESC
    ");
    ?>
        <div class="wrap">
            <h1>Event Dates Management</h1>
            <p>Manage event dates for Cromemart certificates.</p>

            <?php if (empty($institutions)): ?>
                <div class="notice notice-warning">
                    <p>No institutions found! <a href="<?php echo admin_url('admin.php?page=ofst-institutions'); ?>">Add an institution first</a>.</p>
                </div>
            <?php else: ?>
                <!-- Add/Edit Event Form -->
                <div class="card" style="max-width: 600px; padding: 20px; margin-bottom: 20px;">
                    <h2><?php echo $editing ? 'Edit Event' : 'Add New Event'; ?></h2>
                    <form method="post">
                        <?php wp_nonce_field('ofst_event_action'); ?>
                        <?php if ($editing): ?>
                            <input type="hidden" name="edit_id" value="<?php echo $editing->id; ?>">
                        <?php endif; ?>
                        <table class="form-table">
                            <tr>
                                <th><label for="institution_id">Institution *</label></th>
                                <td>
                                    <select name="institution_id" id="institution_id" required>
                                        <option value="">-- Select --</option>
                                        <?php foreach ($institutions as $inst): ?>
                                            <option value="<?php echo $inst->id; ?>" <?php echo ($editing && $editing->institution_id == $inst->id) ? 'selected' : ''; ?>><?php echo esc_html($inst->institution_name); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </td>
                            </tr>
                            <tr>
                                <th><label for="event_name">Event Name *</label></th>
                                <td><input type="text" name="event_name" id="event_name" class="regular-text" required value="<?php echo $editing ? esc_attr($editing->event_name) : ''; ?>"></td>
                            </tr>
                            <tr>
                                <th><label for="event_date">Event Date *</label></th>
                                <td><input type="date" name="event_date" id="event_date" required value="<?php echo $editing ? esc_attr($editing->event_date) : ''; ?>"></td>
                            </tr>
                            <tr>
                                <th><label for="event_theme">Theme (Optional)</label></th>
                                <td><input type="text" name="event_theme" id="event_theme" class="regular-text" placeholder="e.g. Annual Tech Conference 2024" value="<?php echo $editing ? esc_attr($editing->event_theme) : ''; ?>"></td>
                            </tr>
                        </table>
                        <p>
                            <input type="submit" name="ofst_add_event" class="button button-primary" value="<?php echo $editing ? 'Update Event' : 'Add Event'; ?>">
                            <?php if ($editing): ?>
                                <a href="<?php echo admin_url('admin.php?page=ofst-event-dates'); ?>" class="button">Cancel</a>
                            <?php endif; ?>
                        </p>
                    </form>
                </div>
            <?php endif; ?>

            <!-- Events List -->
            <h2>All Events (<?php echo count($events); ?>)</h2>
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Institution</th>
                        <th>Event Name</th>
                        <th>Date</th>
                        <th>Theme</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($events)): ?>
                        <tr>
                            <td colspan="7">No events yet. Add one above!</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($events as $evt): ?>
                            <tr>
                                <td><?php echo $evt->id; ?></td>
                                <td><?php echo esc_html($evt->institution_name); ?></td>
                                <td><strong><?php echo esc_html($evt->event_name); ?></strong></td>
                                <td><?php echo date('M d, Y', strtotime($evt->event_date)); ?></td>
                                <td><?php echo $evt->event_theme ? esc_html($evt->event_theme) : '-'; ?></td>
                                <td><?php echo $evt->is_active ? '<span style="color:green;">Active</span>' : '<span style="color:red;">Inactive</span>'; ?></td>
                                <td>
                                    <a href="<?php echo admin_url('admin.php?page=ofst-event-dates&edit_event=' . $evt->id); ?>" class="button button-small">Edit</a>
                                    <a href="<?php echo wp_nonce_url(admin_url('admin.php?page=ofst-event-dates&delete_event=' . $evt->id), 'delete_event_' . $evt->id); ?>"
                                        onclick="return confirm('Delete this event?');"
                                        style="color: red;">Delete</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    <?php
}

// =====================================================
// V2.3: EVENT PARTICIPANTS ROSTER PAGE
// =====================================================
function ofst_cert_participants_page()
{
    global $wpdb;
    $participants_table = $wpdb->prefix . 'ofst_cert_event_participants';
    $events_table = $wpdb->prefix . 'ofst_cert_event_dates';
    $institutions_table = $wpdb->prefix . 'ofst_cert_institutions';

    // Handle Add Single Participant
    if (isset($_POST['add_participant']) && check_admin_referer('add_participant_nonce')) {
        $event_id = absint($_POST['event_date_id']);
        $name = sanitize_text_field($_POST['participant_name']);
        $email = isset($_POST['participant_email']) ? sanitize_email($_POST['participant_email']) : '';

        if ($event_id && $name) {
            $wpdb->insert($participants_table, [
                'event_date_id' => $event_id,
                'full_name' => $name,
                'email' => $email ?: null,
                'added_date' => current_time('mysql'),
                'added_by' => get_current_user_id()
            ]);
            ofst_cert_toast('Participant added successfully!', 'success');
        }
    }

    // Handle Bulk Add (text paste) - supports "name" or "name,email" format
    if (isset($_POST['bulk_add_participants']) && check_admin_referer('bulk_add_nonce')) {
        $event_id = absint($_POST['bulk_event_id']);
        $names_raw = sanitize_textarea_field($_POST['bulk_names']);

        if ($event_id && $names_raw) {
            $lines = array_filter(array_map('trim', explode("\n", $names_raw)));
            $added = 0;

            foreach ($lines as $line) {
                // Check if line has comma (name,email format)
                if (strpos($line, ',') !== false) {
                    $parts = array_map('trim', explode(',', $line, 2));
                    $name = sanitize_text_field($parts[0]);
                    $email = isset($parts[1]) ? sanitize_email($parts[1]) : '';
                } else {
                    $name = sanitize_text_field($line);
                    $email = '';
                }

                if (!empty($name)) {
                    $wpdb->insert($participants_table, [
                        'event_date_id' => $event_id,
                        'full_name' => $name,
                        'email' => $email ?: null,
                        'added_date' => current_time('mysql'),
                        'added_by' => get_current_user_id()
                    ]);
                    $added++;
                }
            }
            ofst_cert_toast($added . ' participants added successfully!', 'success');
        }
    }

    // Handle File Upload - supports CSV, TXT, and XLSX (Excel)
    if (isset($_POST['csv_upload']) && check_admin_referer('csv_upload_nonce') && !empty($_FILES['csv_file']['tmp_name'])) {
        $event_id = absint($_POST['csv_event_id']);
        $file_path = $_FILES['csv_file']['tmp_name'];
        $file_name = $_FILES['csv_file']['name'];
        $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));

        if ($event_id && is_uploaded_file($file_path)) {
            $lines = [];

            // Handle XLSX (Excel) files
            if ($file_ext === 'xlsx' && class_exists('ZipArchive')) {
                $zip = new ZipArchive();
                if ($zip->open($file_path) === true) {
                    // Read shared strings (cell values)
                    $shared_strings = [];
                    $strings_xml = $zip->getFromName('xl/sharedStrings.xml');
                    if ($strings_xml) {
                        $xml = simplexml_load_string($strings_xml);
                        foreach ($xml->si as $si) {
                            $shared_strings[] = (string)$si->t;
                        }
                    }

                    // Read first worksheet
                    $sheet_xml = $zip->getFromName('xl/worksheets/sheet1.xml');
                    if ($sheet_xml) {
                        $xml = simplexml_load_string($sheet_xml);
                        foreach ($xml->sheetData->row as $row) {
                            $row_data = [];
                            foreach ($row->c as $cell) {
                                $value = '';
                                if (isset($cell->v)) {
                                    $value = (string)$cell->v;
                                    // Check if it's a shared string reference
                                    if (isset($cell['t']) && (string)$cell['t'] === 's') {
                                        $value = $shared_strings[(int)$value] ?? $value;
                                    }
                                }
                                $row_data[] = $value;
                            }
                            if (!empty($row_data[0])) {
                                // Format: name,email or just name
                                $lines[] = implode(',', array_slice($row_data, 0, 2));
                            }
                        }
                    }
                    $zip->close();
                }
            } else {
                // Handle CSV/TXT files
                $file_content = file_get_contents($file_path);
                $lines = array_filter(array_map('trim', explode("\n", $file_content)));
            }

            $added = 0;
            $skip_headers = ['name', 'full name', 'email', 'full_name', 'participant', 'attendee'];

            foreach ($lines as $line) {
                // Check if line has comma (name,email format)
                if (strpos($line, ',') !== false) {
                    $parts = array_map('trim', explode(',', $line, 2));
                    $name = sanitize_text_field($parts[0]);
                    $email = isset($parts[1]) ? sanitize_email($parts[1]) : '';
                } else {
                    $name = sanitize_text_field($line);
                    $email = '';
                }

                // Skip header rows
                if (!empty($name) && !in_array(strtolower($name), $skip_headers)) {
                    $wpdb->insert($participants_table, [
                        'event_date_id' => $event_id,
                        'full_name' => $name,
                        'email' => $email ?: null,
                        'added_date' => current_time('mysql'),
                        'added_by' => get_current_user_id()
                    ]);
                    $added++;
                }
            }
            ofst_cert_toast($added . ' participants imported!', 'success');
        }
    }

    // Handle Remove Participant
    if (isset($_GET['remove_participant']) && isset($_GET['_wpnonce'])) {
        $id = absint($_GET['remove_participant']);
        if (wp_verify_nonce($_GET['_wpnonce'], 'remove_participant_' . $id)) {
            $wpdb->delete($participants_table, ['id' => $id]);
            ofst_cert_toast('Participant removed.', 'success');
        }
    }

    // Get institutions for dropdown
    $institutions = $wpdb->get_results("SELECT * FROM $institutions_table WHERE is_active = 1 ORDER BY institution_name");

    // Get selected event filter
    $filter_event = isset($_GET['filter_event']) ? absint($_GET['filter_event']) : 0;

    // Get participants
    $where = $filter_event ? $wpdb->prepare("WHERE p.event_date_id = %d", $filter_event) : "";
    $participants = $wpdb->get_results("
        SELECT p.*, e.event_name, e.event_date, i.institution_name
        FROM $participants_table p
        LEFT JOIN $events_table e ON p.event_date_id = e.id
        LEFT JOIN $institutions_table i ON e.institution_id = i.id
        $where
        ORDER BY p.added_date DESC
    ");

    // Get events for dropdown
    $events = $wpdb->get_results("
        SELECT e.*, i.institution_name 
        FROM $events_table e
        LEFT JOIN $institutions_table i ON e.institution_id = i.id
        WHERE e.is_active = 1
        ORDER BY e.event_date DESC
    ");
    ?>
        <div class="wrap">
            <h1>Event Participants Roster</h1>
            <p>Upload participant names before the event. Names auto-remove when certificates are issued.</p>

            <div class="ofst-participant-grid">
                <!-- Single Add Form -->
                <div class="card" style="padding: 20px;">
                    <h3 style="margin-top: 0;">Add Single Participant</h3>
                    <form method="post">
                        <?php wp_nonce_field('add_participant_nonce'); ?>
                        <table class="form-table">
                            <tr>
                                <th><label>Event</label></th>
                                <td>
                                    <select name="event_date_id" required style="width: 100%;">
                                        <option value="">-- Select Event --</option>
                                        <?php foreach ($events as $evt): ?>
                                            <option value="<?php echo $evt->id; ?>">
                                                <?php echo esc_html($evt->institution_name . ' - ' . $evt->event_name . ' (' . date('M d, Y', strtotime($evt->event_date)) . ')'); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </td>
                            </tr>
                            <tr>
                                <th><label>Full Name</label></th>
                                <td>
                                    <input type="text" name="participant_name" required style="width: 100%;" placeholder="John Doe">
                                </td>
                            </tr>
                            <tr>
                                <th><label>Email (Optional)</label></th>
                                <td>
                                    <input type="email" name="participant_email" style="width: 100%;" placeholder="john@example.com">
                                </td>
                            </tr>
                        </table>
                        <button type="submit" name="add_participant" class="button button-primary">Add Participant</button>
                    </form>
                </div>

                <!-- Bulk Add Form -->
                <div class="card" style="padding: 20px;">
                    <h3 style="margin-top: 0;">Bulk Add (Paste Names)</h3>
                    <form method="post">
                        <?php wp_nonce_field('bulk_add_nonce'); ?>
                        <table class="form-table">
                            <tr>
                                <th><label>Event</label></th>
                                <td>
                                    <select name="bulk_event_id" required style="width: 100%;">
                                        <option value="">-- Select Event --</option>
                                        <?php foreach ($events as $evt): ?>
                                            <option value="<?php echo $evt->id; ?>">
                                                <?php echo esc_html($evt->institution_name . ' - ' . $evt->event_name . ' (' . date('M d, Y', strtotime($evt->event_date)) . ')'); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </td>
                            </tr>
                            <tr>
                                <th><label>Names (one per line)</label></th>
                                <td>
                                    <textarea name="bulk_names" rows="5" style="width: 100%;" placeholder="John Doe, john@example.com
Jane Smith, jane@example.com
Mike Johnson"></textarea>
                                    <small style="color: #666;">Format: <code>Name</code> or <code>Name, email</code></small>
                                </td>
                            </tr>
                        </table>
                        <button type="submit" name="bulk_add_participants" class="button button-primary">Add All Names</button>
                    </form>
                </div>
            </div>

            <!-- File Upload -->
            <div class="card ofst-participant-upload" style="padding: 20px; margin-bottom: 20px;">
                <h3 style="margin-top: 0;">Upload File (CSV, TXT, or Excel)</h3>
                <form method="post" enctype="multipart/form-data">
                    <?php wp_nonce_field('csv_upload_nonce'); ?>
                    <div style="display: flex; gap: 15px; align-items: center; flex-wrap: wrap;">
                        <select name="csv_event_id" required>
                            <option value="">-- Select Event --</option>
                            <?php foreach ($events as $evt): ?>
                                <option value="<?php echo $evt->id; ?>">
                                    <?php echo esc_html($evt->institution_name . ' - ' . $evt->event_name); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <input type="file" name="csv_file" accept=".csv,.txt,.xlsx" required>
                        <button type="submit" name="csv_upload" class="button button-secondary">Upload File</button>
                        <small>Supports: <code>.csv</code>, <code>.txt</code>, <code>.xlsx</code> | Columns: Name, Email (optional)</small>
                    </div>
                </form>
            </div>

            <!-- Filter -->
            <form method="get" style="margin-bottom: 15px;">
                <input type="hidden" name="page" value="ofst-participants">
                <select name="filter_event" onchange="this.form.submit()">
                    <option value="">-- All Events --</option>
                    <?php foreach ($events as $evt): ?>
                        <option value="<?php echo $evt->id; ?>" <?php selected($filter_event, $evt->id); ?>>
                            <?php echo esc_html($evt->institution_name . ' - ' . $evt->event_name); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </form>

            <!-- Participants Table -->
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th style="width: 25%;">Name</th>
                        <th style="width: 20%;">Email</th>
                        <th>Event</th>
                        <th>Institution</th>
                        <th style="width: 12%;">Added</th>
                        <th style="width: 80px;">Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($participants)): ?>
                        <tr>
                            <td colspan="6">No participants added yet.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($participants as $p): ?>
                            <tr>
                                <td><strong><?php echo esc_html($p->full_name); ?></strong></td>
                                <td><?php echo $p->email ? esc_html($p->email) : '<span style="color:#999;">-</span>'; ?></td>
                                <td><?php echo esc_html($p->event_name ?: '-'); ?></td>
                                <td><?php echo esc_html($p->institution_name ?: '-'); ?></td>
                                <td><?php echo date('M d, Y', strtotime($p->added_date)); ?></td>
                                <td>
                                    <a href="<?php echo wp_nonce_url(admin_url('admin.php?page=ofst-participants&remove_participant=' . $p->id), 'remove_participant_' . $p->id); ?>"
                                        onclick="return confirm('Remove this participant?');"
                                        style="color: red;">Remove</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>

            <?php if (!empty($participants)): ?>
                <p style="color: #666; margin-top: 10px;">
                    <strong><?php echo count($participants); ?></strong> participant(s) pending certificates.
                </p>
            <?php endif; ?>
        </div>
    <?php
}
