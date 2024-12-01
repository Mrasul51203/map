jQuery(document).ready(function($) {
    // Define Greater London bounds
    const greaterLondonBounds = L.latLngBounds(
        L.latLng(51.28676, -0.5103), // Southwest corner
        L.latLng(51.69131, 0.3340)   // Northeast corner
    );

    var map = L.map('map-submissions', {
        center: greaterLondonBounds.getCenter(),
        zoom: 11,
        minZoom: 11,
        maxZoom: 18,
        maxBounds: greaterLondonBounds,
        maxBoundsViscosity: 1.0
    });
    
    L.tileLayer('https://theseru.co.uk/idealmap/maptiles/{z}/{x}/{y}.png', {
        attribution: '© OpenStreetMap contributors',
    }).addTo(map);

    // Clear the map
    $('#clear-map').on('click', function() {
        map.eachLayer(function(layer) {
            if (layer instanceof L.Polyline || layer instanceof L.Marker) {
                map.removeLayer(layer);
            }
        });
    });

    // View a specific submission
    $('.view-submission').on('click', function() {
        var answerData = JSON.parse($(this).closest('li').data('answer'));

        map.eachLayer(function(layer) {
            if (layer instanceof L.Polyline) {
                map.removeLayer(layer);
            }
        });

        var polyline = L.polyline(answerData, { color: 'blue' }).addTo(map);
        map.fitBounds(polyline.getBounds());
    });

    // Provide feedback for a submission
    $('.provide-feedback').on('click', function() {
        var submissionId = $(this).closest('li').data('id');
        var feedback = $('#feedback-input').val();

        if (!feedback) {
            alert('Please enter feedback before saving.');
            return;
        }

        $.ajax({
            url: ajaxurl,
            method: 'POST',
            data: {
                action: 'map_assessment_save_feedback',
                nonce: map_assessment_data.nonce,
                submission_id: submissionId,
                feedback: feedback,
            },
            success: function(response) {
                if (response.success) {
                    alert('Feedback saved successfully');
                    $('#feedback-input').val(''); // Clear the input
                } else {
                    alert('Error saving feedback: ' + response.data.message);
                }
            },
            error: function() {
                alert('An error occurred while saving feedback.');
            },
        });
    });

    // Load all submissions (Example Data - replace with real API or database call)
    $('#view-all-submissions').on('click', function() {
        // Example of dynamically adding submissions to the list
        var submissions = [
            { id: 1, answer: '[[51.505, -0.09], [51.51, -0.1]]' },
            { id: 2, answer: '[[51.51, -0.1], [51.52, -0.12]]' },
        ];

        $('.submissions-list ul').empty(); // Clear existing submissions
        submissions.forEach(function(submission) {
            $('.submissions-list ul').append(
                `<li data-answer='${submission.answer}' data-id="${submission.id}">
                    <span>Submission #${submission.id}</span>
                    <button class="view-submission">View</button>
                    <button class="provide-feedback">Provide Feedback</button>
                </li>`
            );
        });
    });
});
