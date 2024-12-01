<?php
/*
Plugin Name: Map Assessment System
Description: A map-based assessment system for theseru.co.uk
Version: 1.0
Author: Your ray
Text Domain: map-assessment
*/

if (!defined('ABSPATH')) exit; // Exit if accessed directly

// Define constants
define('MAP_ASSESSMENT_PATH', plugin_dir_path(__FILE__));
define('MAP_ASSESSMENT_URL', plugin_dir_url(__FILE__));

// Include necessary files
if (file_exists(MAP_ASSESSMENT_PATH . 'admin-map.php')) {
    require_once MAP_ASSESSMENT_PATH . 'admin-map.php';
}
if (file_exists(MAP_ASSESSMENT_PATH . 'user-map.php')) {
    require_once MAP_ASSESSMENT_PATH . 'user-map.php';
}

// Helper function for logging
function map_assessment_log($message) {
    if (WP_DEBUG === true) {
        if (is_array($message) || is_object($message)) {
            error_log(print_r($message, true));
        } else {
            error_log($message);
        }
    }
}

// Updated AJAX handlers with user capability checks and improved error logging
function map_assessment_get_question() {
    check_ajax_referer('map_assessment_nonce', 'nonce');
    if (!current_user_can('manage_options')) {
        wp_send_json_error(['message' => 'Insufficient permissions.']);
        return;
    }
    $question_id = intval($_POST['question_id']);
    global $wpdb;
    $table_name = $wpdb->prefix . 'map_questions';
    $question = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_name WHERE id = %d", $question_id));
    if ($question) {
        wp_send_json_success($question);
    } else {
        map_assessment_log("Question not found: ID $question_id");
        wp_send_json_error(['message' => 'Question not found.']);
    }
}
add_action('wp_ajax_get_question', 'map_assessment_get_question');

function map_assessment_update_question() {
    check_ajax_referer('map_assessment_nonce', 'nonce');
    if (!current_user_can('manage_options')) {
        wp_send_json_error(['message' => 'Insufficient permissions.']);
        return;
    }
    $question_id = intval($_POST['question_id']);
    $question_text = sanitize_textarea_field($_POST['question_text']);
    $start_point = sanitize_text_field($_POST['start_point']);
    $end_point = sanitize_text_field($_POST['end_point']);
    
    global $wpdb;
    $table_name = $wpdb->prefix . 'map_questions';
    $result = $wpdb->update(
        $table_name,
        [
            'question_text' => $question_text,
            'start_point' => $start_point,
            'end_point' => $end_point,
        ],
        ['id' => $question_id],
        ['%s', '%s', '%s'],
        ['%d']
    );
    
    if ($result !== false) {
        wp_send_json_success(['message' => 'Question updated successfully.']);
    } else {
        map_assessment_log("Failed to update question: ID $question_id");
        wp_send_json_error(['message' => 'Failed to update question.']);
    }
}
add_action('wp_ajax_update_question', 'map_assessment_update_question');

function map_assessment_delete_question() {
    check_ajax_referer('map_assessment_nonce', 'nonce');
    if (!current_user_can('manage_options')) {
        wp_send_json_error(['message' => 'Insufficient permissions.']);
        return;
    }
    $question_id = intval($_POST['question_id']);
    
    global $wpdb;
    $table_name = $wpdb->prefix . 'map_questions';
    $result = $wpdb->delete($table_name, ['id' => $question_id], ['%d']);
    
    if ($result !== false) {
        wp_send_json_success(['message' => 'Question deleted successfully.']);
    } else {
        map_assessment_log("Failed to delete question: ID $question_id");
        wp_send_json_error(['message' => 'Failed to delete question.']);
    }
}
add_action('wp_ajax_delete_question', 'map_assessment_delete_question');

// New function to create questions
function map_assessment_create_question() {
    check_ajax_referer('map_assessment_nonce', 'nonce');
    if (!current_user_can('manage_options')) {
        wp_send_json_error(['message' => 'Insufficient permissions.']);
        return;
    }
    $question_text = sanitize_textarea_field($_POST['question_text']);
    $start_point = sanitize_text_field($_POST['start_point']);
    $end_point = sanitize_text_field($_POST['end_point']);
    
    global $wpdb;
    $table_name = $wpdb->prefix . 'map_questions';
    $result = $wpdb->insert(
        $table_name,
        [
            'question_text' => $question_text,
            'start_point' => $start_point,
            'end_point' => $end_point,
        ],
        ['%s', '%s', '%s']
    );
    
    if ($result !== false) {
        $new_question_id = $wpdb->insert_id;
        wp_send_json_success(['message' => 'Question created successfully.', 'question_id' => $new_question_id]);
    } else {
        map_assessment_log("Failed to create new question");
        wp_send_json_error(['message' => 'Failed to create question.']);
    }
}
add_action('wp_ajax_create_question', 'map_assessment_create_question');

// Function to handle user flags
function map_assessment_flag_question() {
    check_ajax_referer('map_assessment_nonce', 'nonce');
    if (!is_user_logged_in()) {
        wp_send_json_error(['message' => 'User must be logged in to flag a question.']);
        return;
    }
    $question_id = intval($_POST['question_id']);
    $user_id = get_current_user_id();
    $flag_reason = sanitize_text_field($_POST['flag_reason']);
    
    global $wpdb;
    $table_name = $wpdb->prefix . 'map_question_flags';
    $result = $wpdb->insert(
        $table_name,
        [
            'question_id' => $question_id,
            'user_id' => $user_id,
            'flag_reason' => $flag_reason,
            'flag_date' => current_time('mysql')
        ],
        ['%d', '%d', '%s', '%s']
    );
    
    if ($result !== false) {
        wp_send_json_success(['message' => 'Question flagged successfully.']);
    } else {
        map_assessment_log("Failed to flag question: ID $question_id");
        wp_send_json_error(['message' => 'Failed to flag question.']);
    }
}
add_action('wp_ajax_flag_question', 'map_assessment_flag_question');

// Function to fetch user submissions for admin review
function map_assessment_get_submissions() {
    check_ajax_referer('map_assessment_nonce', 'nonce');
    if (!current_user_can('manage_options')) {
        wp_send_json_error(['message' => 'Insufficient permissions.']);
        return;
    }
    $question_id = intval($_POST['question_id']);
    
    global $wpdb;
    $table_name = $wpdb->prefix . 'map_answers';
    $submissions = $wpdb->get_results($wpdb->prepare(
        "SELECT * FROM $table_name WHERE question_id = %d ORDER BY submission_date DESC",
        $question_id
    ));
    
    if ($submissions) {
        wp_send_json_success(['submissions' => $submissions]);
    } else {
        map_assessment_log("No submissions found for question: ID $question_id");
        wp_send_json_error(['message' => 'No submissions found.']);
    }
}
add_action('wp_ajax_get_submissions', 'map_assessment_get_submissions');

function map_assessment_update_submission() {
    check_ajax_referer('map_assessment_nonce', 'nonce');
    if (!current_user_can('manage_options')) {
        wp_send_json_error(['message' => 'Insufficient permissions.']);
        return;
    }
    $submission_id = intval($_POST['submission_id']);
    $updated_path = sanitize_text_field($_POST['updated_path']);
    
    global $wpdb;
    $table_name = $wpdb->prefix . 'map_answers';
    $result = $wpdb->update(
        $table_name,
        ['answer_data' => $updated_path],
        ['id' => $submission_id],
        ['%s'],
        ['%d']
    );
    
    if ($result !== false) {
        wp_send_json_success(['message' => 'Submission updated successfully.']);
    } else {
        map_assessment_log("Failed to update submission: ID $submission_id");
        wp_send_json_error(['message' => 'Failed to update submission.']);
    }
}
add_action('wp_ajax_update_submission', 'map_assessment_update_submission');

function map_assessment_send_feedback() {
    check_ajax_referer('map_assessment_nonce', 'nonce');
    if (!current_user_can('manage_options')) {
        wp_send_json_error(['message' => 'Insufficient permissions.']);
        return;
    }
    $submission_id = intval($_POST['submission_id']);
    $feedback = sanitize_textarea_field($_POST['feedback']);
    
    global $wpdb;
    $table_name = $wpdb->prefix . 'map_answers';
    $result = $wpdb->update(
        $table_name,
        ['feedback' => $feedback],
        ['id' => $submission_id],
        ['%s'],
        ['%d']
    );
    
    if ($result !== false) {
        wp_send_json_success(['message' => 'Feedback sent successfully.']);
    } else {
        map_assessment_log("Failed to send feedback: Submission ID $submission_id");
        wp_send_json_error(['message' => 'Failed to send feedback.']);
    }
}
add_action('wp_ajax_send_feedback', 'map_assessment_send_feedback');
