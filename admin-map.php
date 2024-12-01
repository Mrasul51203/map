<?php
if (!defined('ABSPATH')) exit;

function map_assessment_admin_page() {
    if (!current_user_can('manage_options')) {
        wp_die(__('You do not have sufficient permissions to access this page.'));
    }

    // Enqueue necessary scripts and styles
    wp_enqueue_style('leaflet-css', 'https://unpkg.com/leaflet@1.7.1/dist/leaflet.css');
    wp_enqueue_script('leaflet-js', 'https://unpkg.com/leaflet@1.7.1/dist/leaflet.js', [], null, true);
    wp_enqueue_script('leaflet-editable', 'https://cdnjs.cloudflare.com/ajax/libs/leaflet-editable/1.2.0/Leaflet.Editable.min.js', ['leaflet-js'], null, true);
    wp_enqueue_script('map-assessment-scripts', plugin_dir_url(__FILE__) . 'map-assessment-scripts.js', ['jquery', 'leaflet-js', 'leaflet-editable'], null, true);

    // Localize the script with new data
    $script_data = array(
        'ajaxurl' => admin_url('admin-ajax.php'),
        'nonce'   => wp_create_nonce('map_assessment_nonce'),
        'is_admin' => current_user_can('manage_options'),
    );
    wp_localize_script('map-assessment-scripts', 'map_assessment_data', $script_data);

    $active_tab = isset($_GET['tab']) ? $_GET['tab'] : 'create_question';
    ?>
    <div class="wrap">
        <h1><?php echo esc_html__('Map Assessment', 'map-assessment'); ?></h1>
        
        <h2 class="nav-tab-wrapper">
            <a href="?page=map-assessment&tab=create_question" class="nav-tab <?php echo $active_tab == 'create_question' ? 'nav-tab-active' : ''; ?>"><?php esc_html_e('Create Question', 'map-assessment'); ?></a>
            <a href="?page=map-assessment&tab=manage_questions" class="nav-tab <?php echo $active_tab == 'manage_questions' ? 'nav-tab-active' : ''; ?>"><?php esc_html_e('Manage Questions', 'map-assessment'); ?></a>
            <a href="?page=map-assessment&tab=view_submissions" class="nav-tab <?php echo $active_tab == 'view_submissions' ? 'nav-tab-active' : ''; ?>"><?php esc_html_e('View Submissions', 'map-assessment'); ?></a>
        </h2>

        <div class="tab-content">
            <?php if ($active_tab == 'create_question'): ?>
                <div id="create-question-map" style="height: 400px; margin-bottom: 20px;"></div>
                <div class="map-controls">
                    <button id="set-green-marker" class="button"><?php esc_html_e('Set Green Marker', 'map-assessment'); ?></button>
                    <button id="set-red-marker" class="button"><?php esc_html_e('Set Red Marker', 'map-assessment'); ?></button>
                    <button id="create-question" class="button button-primary"><?php esc_html_e('Create Question', 'map-assessment'); ?></button>
                </div>
            <?php elseif ($active_tab == 'manage_questions'): ?>
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th><?php esc_html_e('ID', 'map-assessment'); ?></th>
                            <th><?php esc_html_e('Question Text', 'map-assessment'); ?></th>
                            <th><?php esc_html_e('Actions', 'map-assessment'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        global $wpdb;
                        $table_name = $wpdb->prefix . 'map_questions';
                        $questions = $wpdb->get_results("SELECT * FROM $table_name");
                        foreach ($questions as $question): ?>
                            <tr>
                                <td><?php echo esc_html($question->id); ?></td>
                                <td><?php echo esc_html($question->question_text); ?></td>
                                <td>
                                    <button class="button edit-question" data-id="<?php echo esc_attr($question->id); ?>"><?php esc_html_e('Edit', 'map-assessment'); ?></button>
                                    <button class="button delete-question" data-id="<?php echo esc_attr($question->id); ?>"><?php esc_html_e('Delete', 'map-assessment'); ?></button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <div id="edit-question-modal" class="modal" style="display: none;">
                    <div class="modal-content">
                        <h3><?php esc_html_e('Edit Question', 'map-assessment'); ?></h3>
                        <input type="hidden" id="edit-question-id">
                        <textarea id="edit-question-text" rows="4" cols="50"></textarea>
                        <div id="edit-question-map" style="height: 400px; margin: 20px 0;"></div>
                        <button id="save-edited-question" class="button button-primary"><?php esc_html_e('Save Changes', 'map-assessment'); ?></button>
                        <button id="close-edit-modal" class="button"><?php esc_html_e('Cancel', 'map-assessment'); ?></button>
                    </div>
                </div>
            <?php elseif ($active_tab == 'view_submissions'): ?>
                <div id="view-submissions-map" style="height: 400px; margin-bottom: 20px;"></div>
                <div id="submissions-list"></div>
                <div id="submission-modal" class="modal" style="display: none;">
                    <div class="modal-content">
                        <h3><?php esc_html_e('Submission Details', 'map-assessment'); ?></h3>
                        <p id="user-id"></p>
                        <p id="submitted-at"></p>
                        <textarea id="feedback-text" rows="4" cols="50"></textarea>
                        <button id="save-feedback" class="button"><?php esc_html_e('Save Feedback', 'map-assessment'); ?></button>
                        <button id="edit-route" class="button"><?php esc_html_e('Edit Route', 'map-assessment'); ?></button>
                        <button id="redraw-route" class="button" style="display:none;"><?php esc_html_e('Redraw Route', 'map-assessment'); ?></button>
                        <button id="undo-edit" class="button" style="display:none;"><?php esc_html_e('Undo', 'map-assessment'); ?></button>
                        <button id="clear-edit" class="button" style="display:none;"><?php esc_html_e('Clear', 'map-assessment'); ?></button>
                        <select id="line-color" style="display:none;">
                            <option value="blue"><?php esc_html_e('Blue', 'map-assessment'); ?></option>
                            <option value="red"><?php esc_html_e('Red', 'map-assessment'); ?></option>
                            <option value="green"><?php esc_html_e('Green', 'map-assessment'); ?></option>
                        </select>
                        <button id="save-route" class="button" style="display:none;"><?php esc_html_e('Save Route', 'map-assessment'); ?></button>
                        <button id="cancel-edit" class="button" style="display:none;"><?php esc_html_e('Cancel Edit', 'map-assessment'); ?></button>
                        <button id="close-modal" class="button"><?php esc_html_e('Close', 'map-assessment'); ?></button>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
    <style>
    .modal {
        display: none;
        position: fixed;
        z-index: 1000;
        left: 0;
        top: 0;
        width: 100%;
        height: 100%;
        overflow: auto;
        background-color: rgba(0,0,0,0.4);
    }
    .modal-content {
        background-color: #fefefe;
        margin: 15% auto;
        padding: 20px;
        border: 1px solid #888;
        width: 80%;
        max-width: 600px;
    }
    .submission-marker div {
        background: white;
        border: 1px solid black;
        border-radius: 50%;
        text-align: center;
        width: 20px;
        height: 20px;
        line-height: 20px;
    }
    </style>
    <?php
}

function map_assessment_admin_menu() {
    add_menu_page(
        __('Map Assessment', 'map-assessment'),
        __('Map Assessment', 'map-assessment'),
        'manage_options',
        'map-assessment',
        'map_assessment_admin_page',
        'dashicons-location',
        30
    );
}
add_action('admin_menu', 'map_assessment_admin_menu');

// AJAX handler for getting submissions
function map_assessment_get_submissions() {
    check_ajax_referer('map_assessment_nonce', 'nonce');

    if (!current_user_can('manage_options')) {
        wp_send_json_error(['message' => __('You do not have permission to perform this action', 'map-assessment')]);
    }

    global $wpdb;
    $table_name = $wpdb->prefix . 'map_answers';

    $submissions = $wpdb->get_results("SELECT * FROM $table_name ORDER BY created_at DESC");

    $formatted_submissions = array_map(function($submission) {
        return [
            'id' => $submission->id,
            'user_id' => $submission->user_id,
            'route' => json_decode($submission->answer_data),
            'feedback' => $submission->feedback,
            'submitted_at' => $submission->created_at,
        ];
    }, $submissions);

    wp_send_json_success(['submissions' => $formatted_submissions]);
}
add_action('wp_ajax_get_submissions', 'map_assessment_get_submissions');

// AJAX handler for saving feedback
function map_assessment_save_feedback() {
    check_ajax_referer('map_assessment_nonce', 'nonce');

    if (!current_user_can('manage_options')) {
        wp_send_json_error(['message' => __('You do not have permission to perform this action', 'map-assessment')]);
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

    if ($result === false) {
        wp_send_json_error(['message' => __('Error saving feedback', 'map-assessment')]);
    } else {
        wp_send_json_success(['message' => __('Feedback saved successfully', 'map-assessment')]);
    }
}
add_action('wp_ajax_save_feedback', 'map_assessment_save_feedback');

// AJAX handler for saving edited route
function map_assessment_save_edited_route() {
    check_ajax_referer('map_assessment_nonce', 'nonce');

    if (!current_user_can('manage_options')) {
        wp_send_json_error(['message' => __('You do not have permission to perform this action', 'map-assessment')]);
    }

    $submission_id = intval($_POST['submission_id']);
    $route = sanitize_text_field($_POST['route']);

    global $wpdb;
    $table_name = $wpdb->prefix . 'map_answers';

    $result = $wpdb->update(
        $table_name,
        ['answer_data' => $route],
        ['id' => $submission_id],
        ['%s'],
        ['%d']
    );

    if ($result === false) {
        wp_send_json_error(['message' => __('Error saving edited route', 'map-assessment')]);
    } else {
        wp_send_json_success(['message' => __('Route updated successfully', 'map-assessment')]);
    }
}
add_action('wp_ajax_save_edited_route', 'map_assessment_save_edited_route');
