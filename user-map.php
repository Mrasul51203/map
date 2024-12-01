<?php
if (!defined('ABSPATH')) exit; // Exit if accessed directly

function map_assessment_user_interface($atts) {
    $atts = shortcode_atts(array(
        'question_id' => 0,
    ), $atts, 'map_assessment');

    $question_id = intval($atts['question_id']);

    global $wpdb;
    $questions_table = $wpdb->prefix . 'map_questions';
    $answers_table = $wpdb->prefix . 'map_answers';

    $question = $wpdb->get_row($wpdb->prepare("SELECT * FROM $questions_table WHERE id = %d", $question_id));

    if (is_null($question)) {
        return esc_html__('Question not found or database error occurred.', 'map-assessment');
    }

    wp_enqueue_style('leaflet-css', 'https://unpkg.com/leaflet@1.7.1/dist/leaflet.css');
    wp_enqueue_script('leaflet-js', 'https://unpkg.com/leaflet@1.7.1/dist/leaflet.js', [], null, true);
    wp_enqueue_style('leaflet-draw-css', 'https://unpkg.com/leaflet-draw@1.0.4/dist/leaflet.draw.css');
    wp_enqueue_script('leaflet-draw-js', 'https://unpkg.com/leaflet-draw@1.0.4/dist/leaflet.draw.js', ['leaflet-js'], null, true);
    wp_enqueue_style('map-assessment-styles', MAP_ASSESSMENT_URL . 'map-assessment-styles.css');
    wp_enqueue_script('map-assessment-scripts', MAP_ASSESSMENT_URL . 'map-assessment-scripts.js', ['jquery', 'leaflet-js', 'leaflet-draw-js'], null, true);

    // Check if the user has already submitted an answer
    $user_id = get_current_user_id();
    $existing_answer = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM $answers_table WHERE question_id = %d AND user_id = %d",
        $question_id,
        $user_id
    ));

    ob_start();
    ?>
    <div class="map-assessment-container">
        <div class="top-controls">
            <button class="custom-button start-draw"><?php esc_html_e('Start Drawing', 'map-assessment'); ?></button>
            <button class="custom-button stop-draw" style="display: none;"><?php esc_html_e('Stop Drawing', 'map-assessment'); ?></button>
            <button class="custom-button undo"><?php esc_html_e('Undo', 'map-assessment'); ?></button>
            <button class="custom-button clear"><?php esc_html_e('Clear', 'map-assessment'); ?></button>
            <button class="custom-button home"><?php esc_html_e('Home', 'map-assessment'); ?></button>
        </div>

        <div id="user-map" style="height: 600px;"></div>

        <div class="bottom-controls">
            <button class="custom-button flag"><?php esc_html_e('Flag', 'map-assessment'); ?></button>
            <button class="custom-button review"><?php esc_html_e('Review', 'map-assessment'); ?></button>
        </div>
        
        <div class="bottom-central">
            <button class="custom-button previous"><?php esc_html_e('Previous', 'map-assessment'); ?></button>
            <button class="custom-button next"><?php esc_html_e('Next', 'map-assessment'); ?></button>
        </div>

        <div class="bottom-right">
            <button class="custom-button submit"><?php esc_html_e('Submit', 'map-assessment'); ?></button>
        </div>

        <div class="question-text">
            <?php echo esc_html($question->question_text); ?>
        </div>
        <?php if ($existing_answer): ?>
            <div class="existing-answer">
                <h3><?php esc_html_e('Your Previous Answer', 'map-assessment'); ?></h3>
                <div id="existing-route"></div>
                <?php if ($existing_answer->feedback): ?>
                    <div class="feedback">
                        <h4><?php esc_html_e('Feedback', 'map-assessment'); ?></h4>
                        <p><?php echo esc_html($existing_answer->feedback); ?></p>
                    </div>
                <?php endif; ?>
            </div>
        <?php endif; ?>
        <div id="loading-indicator" style="display: none;">
            <p><?php esc_html_e('Loading...', 'map-assessment'); ?></p>
        </div>
        <?php if (current_user_can('edit_posts')): ?>
            <div id="edit-controls" style="display: none;">
                <button id="save-edit"><?php esc_html_e('Save Edit', 'map-assessment'); ?></button>
                <button id="cancel-edit"><?php esc_html_e('Cancel Edit', 'map-assessment'); ?></button>
            </div>
        <?php endif; ?>
    </div>
    <script>
        var mapAssessmentData = {
            startPoint: <?php echo json_encode(explode(',', $question->start_point)); ?>,
            endPoint: <?php echo json_encode(explode(',', $question->end_point)); ?>,
            ajaxUrl: '<?php echo admin_url('admin-ajax.php'); ?>',
            nonce: '<?php echo wp_create_nonce('map_assessment_nonce'); ?>',
            questionId: <?php echo $question_id; ?>,
            existingAnswer: <?php echo $existing_answer ? json_encode($existing_answer->answer_data) : 'null'; ?>,
            isAdmin: <?php echo current_user_can('edit_posts') ? 'true' : 'false'; ?>
        };
    </script>
    <?php
    return ob_get_clean();
}
add_shortcode('map_assessment', 'map_assessment_user_interface');

// Keep the existing map_assessment_submit_answer function as it is
