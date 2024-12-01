
jQuery(document).ready(function ($) {
    // ... (previous code remains unchanged)

    // Create question functionality (admin only)
    if (map_assessment_data.is_admin) {
        $('.create-question').on('click', function() {
            var questionText = prompt("Enter the question text:");
            var startPoint = prompt("Enter the start point (lat,lng):");
            var endPoint = prompt("Enter the end point (lat,lng):");

            if (questionText && startPoint && endPoint) {
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'map_assessment_create_question',
                        nonce: map_assessment_data.nonce,
                        question_text: questionText,
                        start_point: startPoint,
                        end_point: endPoint
                    },
                    success: function(response) {
                        if (response.success) {
                            alert('Question created successfully');
                        } else {
                            alert('Failed to create question: ' + response.data.message);
                        }
                    },
                    error: function() {
                        alert('An error occurred while creating the question.');
                    }
                });
            }
        });
    } else {
        $('.create-question').hide();
    }

    // Get submissions functionality (admin only)
    function getSubmissions(questionId) {
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'map_assessment_get_submissions',
                nonce: map_assessment_data.nonce,
                question_id: questionId
            },
            success: function(response) {
                if (response.success) {
                    displaySubmissions(response.data.submissions);
                } else {
                    alert('Failed to fetch submissions: ' + response.data.message);
                }
            },
            error: function() {
                alert('An error occurred while fetching submissions.');
            }
        });
    }

    function displaySubmissions(submissions) {
        var submissionList = $('<ul>');
        submissions.forEach(function(submission) {
            var listItem = $('<li>')
                .text(`User ${submission.user_id}: ${submission.answer_data}`)
                .append(
                    $('<button>')
                        .text('Edit')
                        .click(function() { editSubmission(submission); })
                );
            submissionList.append(listItem);
        });
        $('#submission-container').html(submissionList);
    }

    function editSubmission(submission) {
        // Clear existing route
        if (polyline) {
            map.removeLayer(polyline);
        }

        try {
            // Draw the user's submitted route
            var routePoints = JSON.parse(submission.answer_data);
            polyline = L.polyline(routePoints, {color: 'blue'}).addTo(map);
            map.fitBounds(polyline.getBounds());

            // Enable editing mode
            polyline.editing.enable();

            // Show save and cancel buttons
            $('#edit-controls').show();

            // Save button functionality
            $('#save-edit').off('click').on('click', function() {
                if (confirm('Are you sure you want to save these changes?')) {
                    var updatedRoute = polyline.getLatLngs();
                    saveEditedRoute(submission.id, JSON.stringify(updatedRoute));
                }
            });

            // Cancel button functionality
            $('#cancel-edit').off('click').on('click', function() {
                polyline.editing.disable();
                $('#edit-controls').hide();
            });
        } catch (error) {
            console.error('Error parsing submission data:', error);
            alert('Error: Unable to edit this submission due to invalid data.');
        }
    }

    function saveEditedRoute(submissionId, updatedRoute) {
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'map_assessment_update_submission',
                nonce: map_assessment_data.nonce,
                submission_id: submissionId,
                updated_path: updatedRoute
            },
            success: function(response) {
                if (response.success) {
                    alert('Route updated successfully');
                    $('#edit-controls').hide();
                    polyline.editing.disable();
                    // Prompt for feedback
                    var feedback = prompt("Please enter feedback for this submission:");
                    if (feedback) {
                        sendFeedback(submissionId, feedback);
                    }
                } else {
                    alert('Failed to update route: ' + response.data.message);
                }
            },
            error: function() {
                alert('An error occurred while updating the route.');
            }
        });
    }

    function sendFeedback(submissionId, feedback) {
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'map_assessment_send_feedback',
                nonce: map_assessment_data.nonce,
                submission_id: submissionId,
                feedback: feedback
            },
            success: function(response) {
                if (response.success) {
                    alert('Feedback sent successfully');
                } else {
                    alert('Failed to send feedback: ' + response.data.message);
                }
            },
            error: function() {
                alert('An error occurred while sending feedback.');
            }
        });
    }

    // Review button functionality (updated)
    $('.review').on('click', function() {
        if (map_assessment_data.is_admin) {
            getSubmissions(map_assessment_data.question_id);
        } else {
            alert('Only administrators can review submissions.');
        }
    });

    // Next button functionality
    $('.next').on('click', function() {
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'map_assessment_get_next_question',
                nonce: map_assessment_data.nonce,
                current_question_id: map_assessment_data.question_id
            },
            success: function(response) {
                if (response.success) {
                    // Update the map and question display with the new question data
                    updateQuestionDisplay(response.data.question);
                } else {
                    alert('No more questions available.');
                }
            },
            error: function() {
                alert('An error occurred while fetching the next question.');
            }
        });
    });

    // Previous button functionality
    $('.previous').on('click', function() {
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'map_assessment_get_previous_question',
                nonce: map_assessment_data.nonce,
                current_question_id: map_assessment_data.question_id
            },
            success: function(response) {
                if (response.success) {
                    // Update the map and question display with the new question data
                    updateQuestionDisplay(response.data.question);
                } else {
                    alert('No previous questions available.');
                }
            },
            error: function() {
                alert('An error occurred while fetching the previous question.');
            }
        });
    });

    // Function to show loading indicator
    function showLoading() {
        $('#loading-indicator').show();
    }

    // Function to hide loading indicator
    function hideLoading() {
        $('#loading-indicator').hide();
    }

    // Add loading indicator to all AJAX requests
    $(document).ajaxStart(showLoading).ajaxStop(hideLoading);

    /**
     * Updates the map markers for start and end points
     * @param {Array} startPoint - The start point coordinates [lat, lng]
     * @param {Array} endPoint - The end point coordinates [lat, lng]
     */
    function updateMapMarkers(startPoint, endPoint) {
        // Remove existing markers if any
        if (window.startMarker) {
            map.removeLayer(window.startMarker);
        }
        if (window.endMarker) {
            map.removeLayer(window.endMarker);
        }

        // Add new markers
        window.startMarker = L.marker(startPoint, {icon: L.divIcon({className: 'start-marker'})}).addTo(map);
        window.endMarker = L.marker(endPoint, {icon: L.divIcon({className: 'end-marker'})}).addTo(map);

        // Fit the map to show both markers
        var bounds = L.latLngBounds(startPoint, endPoint);
        map.fitBounds(bounds, {padding: [50, 50]});
    }

    /**
     * Updates the question display and map based on the provided question data
     * @param {Object} question - The question data object
     */
    function updateQuestionDisplay(question) {
        if (!question || !question.question_text) {
            console.error('Invalid question data:', question);
            alert('Error: Invalid question data received.');
            return;
        }

        // Update the question text
        $('#question-text').text(question.question_text);

        // Update the map with new start and end points
        if (question.start_point && question.end_point) {
            var startPoint = question.start_point.split(',').map(Number);
            var endPoint = question.end_point.split(',').map(Number);
            updateMapMarkers(startPoint, endPoint);
        }

        // Update the current question ID in the map_assessment_data
        map_assessment_data.question_id = question.id;

        // Clear any existing drawn routes
        if (polyline) {
            map.removeLayer(polyline);
        }
        polyline = null;
        firstClick = true;
    }

    // Submit button functionality (keeping existing AJAX logic)
    $('.submit').on('click', function() {
        if (!polyline || polyline.getLatLngs().length === 0) {
            alert('Please draw a route before submitting.');
            return;
        }

        var answerData = JSON.stringify(polyline.getLatLngs());
        $.ajax({
            url: mapAssessmentData.ajaxUrl,
            method: 'POST',
            data: {
                action: 'map_assessment_submit_answer',
                nonce: mapAssessmentData.nonce,
                question_id: mapAssessmentData.questionId,
                answer_data: answerData
            },
            success: function(response) {
                if (response.success) {
                    alert(response.data.message);
                    location.reload(); // Reload to show updated answer and feedback
                } else {
                    alert('Error: ' + response.data.message);
                }
            },
            error: function() {
                alert('An error occurred while submitting your answer. Please try again.');
            }
        });
    });

    // Load existing answer if available
    if (mapAssessmentData.existingAnswer) {
        var existingRoute = L.polyline(JSON.parse(mapAssessmentData.existingAnswer), {color: 'gray'}).addTo(map);
        map.fitBounds(existingRoute.getBounds());
    } else {
        map.fitBounds(L.latLngBounds(mapAssessmentData.startPoint, mapAssessmentData.endPoint));
    }
});
