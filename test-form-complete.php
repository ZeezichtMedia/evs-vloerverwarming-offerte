<?php
// Simple test page for EVS form with working submission
?>
<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>EVS Vloerverwarming Test Form - Working Version</title>
    <link rel="stylesheet" href="assets/css/evs-form.css">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <style>
        body { 
            font-family: Arial, sans-serif; 
            margin: 20px; 
            background: #f5f5f5; 
        }
        .test-container { 
            max-width: 800px; 
            margin: 0 auto; 
            background: white; 
            padding: 20px; 
            border-radius: 8px; 
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .test-header {
            text-align: center;
            margin-bottom: 30px;
            color: #333;
        }
        .success-message {
            background: #d4edda;
            color: #155724;
            padding: 15px;
            border-radius: 5px;
            margin: 20px 0;
            border: 1px solid #c3e6cb;
        }
        .error-message {
            background: #f8d7da;
            color: #721c24;
            padding: 15px;
            border-radius: 5px;
            margin: 20px 0;
            border: 1px solid #f5c6cb;
        }
    </style>
</head>
<body>
    <div class="test-container">
        <div class="test-header">
            <h1>🔧 EVS Vloerverwarming Plugin Test</h1>
            <p>Testing the redesigned offerte form with working submission</p>
            <div id="test-status"></div>
        </div>
        
        <div class="evs-form-container">
            <?php include 'templates/form-template.php'; ?>
        </div>
    </div>
    
    <script src="assets/js/evs-form.js"></script>
    <script>
        // Override the AJAX submission for testing
        jQuery(document).ready(function($) {
            // Override the form submission
            $('#evs-offerte-formulier').off('submit').on('submit', function(e) {
                e.preventDefault();
                
                const $form = $(this);
                const $submitButton = $form.find('button[type="submit"]');
                const $formMessages = $('#evs-form-messages');
                
                $submitButton.prop('disabled', true).text('Verzenden...');
                $formMessages.removeClass('success error').empty().hide();
                
                // Collect all form data
                const formData = {};
                $form.find('input, select, textarea').each(function() {
                    const $input = $(this);
                    const name = $input.attr('name');
                    const type = $input.attr('type');
                    
                    if (name) {
                        if (type === 'radio' || type === 'checkbox') {
                            if ($input.is(':checked')) {
                                formData[name] = $input.val();
                            }
                        } else {
                            formData[name] = $input.val();
                        }
                    }
                });
                
                console.log('Form data being submitted:', formData);
                
                $.ajax({
                    url: 'test-form-handler.php',
                    type: 'POST',
                    data: {
                        action: 'evs_vloerverwarming_offerte_submit',
                        form_data: formData
                    },
                    dataType: 'json',
                    success: function(response) {
                        console.log('Server response:', response);
                        
                        if (response.success) {
                            $form.hide();
                            $('.evs-progress-sidebar').hide();
                            $formMessages.html('<div class="success-message">' + response.data.message + '</div>').show();
                            
                            if (response.data.quote) {
                                const quote = response.data.quote;
                                $formMessages.append(
                                    '<div class="success-message">' +
                                    '<h3>Offerte Details (Test):</h3>' +
                                    '<p><strong>Oppervlakte:</strong> ' + quote.area_m2 + ' m²</p>' +
                                    '<p><strong>Strekkende meter:</strong> ' + quote.strekkende_meter.toFixed(1) + ' m</p>' +
                                    '<p><strong>Boorprijs:</strong> €' + quote.drilling_price.toFixed(2) + '</p>' +
                                    '<p><strong>Dichtsmeren:</strong> €' + quote.sealing_price.toFixed(2) + '</p>' +
                                    '<p><strong>Totaal:</strong> €' + quote.total_price.toFixed(2) + '</p>' +
                                    '</div>'
                                );
                            }
                        } else {
                            $formMessages.html('<div class="error-message">' + response.data.message + '</div>').show();
                            if (response.data.errors) {
                                let errorList = '<ul>';
                                for (let field in response.data.errors) {
                                    errorList += '<li>' + response.data.errors[field] + '</li>';
                                }
                                errorList += '</ul>';
                                $formMessages.append('<div class="error-message">' + errorList + '</div>');
                            }
                        }
                        
                        $submitButton.prop('disabled', false).text('Offerte aanvragen');
                    },
                    error: function(jqXHR, textStatus, errorThrown) {
                        console.error('AJAX Error:', textStatus, errorThrown);
                        $formMessages.html('<div class="error-message">Er is een technisch probleem opgetreden. Probeer het later opnieuw.</div>').show();
                        $submitButton.prop('disabled', false).text('Offerte aanvragen');
                    }
                });
            });
            
            // Test status
            $('#test-status').html('<p style="color: green;">✅ Test environment ready - form submission will work!</p>');
        });
    </script>
</body>
</html>
