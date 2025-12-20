<?php

/**
 * AJAX Handlers for V2.0 Certificate System
 * Handles dynamic event loading for Cromemart certificates
 */

if (!defined('ABSPATH')) exit;

/**
 * Load event dates based on selected institution
 */
add_action('wp_ajax_ofst_get_event_dates', 'ofst_ajax_get_event_dates');
add_action('wp_ajax_nopriv_ofst_get_event_dates', 'ofst_ajax_get_event_dates');

function ofst_ajax_get_event_dates()
{
    // Verify nonce
    if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'ofst_cert_ajax')) {
        wp_send_json_error(['message' => 'Security check failed']);
    }

    $institution_id = isset($_POST['institution_id']) ? absint($_POST['institution_id']) : 0;

    if (!$institution_id) {
        wp_send_json_error(['message' => 'Invalid institution']);
    }

    global $wpdb;
    $table = $wpdb->prefix . 'ofst_cert_event_dates';

    $events = $wpdb->get_results($wpdb->prepare(
        "SELECT id, event_name, event_date, event_theme 
         FROM $table 
         WHERE institution_id = %d AND is_active = 1 
         ORDER BY event_date DESC",
        $institution_id
    ));

    if (empty($events)) {
        wp_send_json_success(['events' => [], 'message' => 'No events found']);
    }

    // Format events for dropdown
    $formatted = array_map(function ($event) {
        return [
            'id' => $event->id,
            'display_text' => $event->event_name . ' (' . date('M d, Y', strtotime($event->event_date)) . ')'
        ];
    }, $events);

    wp_send_json_success(['events' => $formatted]);
}

/**
 * Enqueue AJAX script for frontend
 */
add_action('wp_enqueue_scripts', 'ofst_enqueue_ajax_script');
function ofst_enqueue_ajax_script()
{
    wp_localize_script('jquery', 'ofst_cert_ajax', [
        'ajax_url' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('ofst_cert_ajax')
    ]);
}
