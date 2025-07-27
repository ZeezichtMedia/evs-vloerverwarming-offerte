/**
 * EVS Offerte Form JavaScript
 * Handles multi-step form navigation, validation, and submission
 */

(function($) {
    'use strict';

    let currentStep = 1;
    const totalSteps = 9;
    let formData = {};

    $(document).ready(function() {
        initializeForm();
        bindEvents();
        updateProgressBar();
    });

    function initializeForm() {
        // Hide all steps except the first
        $('.evs-step').hide();
        $('.evs-step-1').show().addClass('active');
        
        // Set initial progress
        updateProgressBar();
        
        // Handle 'Anders' option for verdieping
        $('input[name="verdieping"][value="anders"]').on('change', function() {
            if ($(this).is(':checked')) {
                $('input[name="verdieping_anders"]').focus();
            }
        });
        
        // Handle date input visibility
        $('input[name="montagedatum_type"][value="datum"]').on('change', function() {
            if ($(this).is(':checked')) {
                $('input[name="montagedatum"]').prop('required', true).focus();
            }
        });
        
        $('input[name="montagedatum_type"][value="onbekend"]').on('change', function() {
            if ($(this).is(':checked')) {
                $('input[name="montagedatum"]').prop('required', false);
            }
        });
    }

    function bindEvents() {
        // Next button click
        $('.evs-btn-next').on('click', function(e) {
            e.preventDefault();
            
            if (validateCurrentStep()) {
                saveCurrentStepData();
                goToStep(currentStep + 1);
            }
        });

        // Previous button click
        $('.evs-btn-prev').on('click', function(e) {
            e.preventDefault();
            goToStep(currentStep - 1);
        });

        // Form submission
        $('#evs-offerte-form').on('submit', function(e) {
            e.preventDefault();
            
            if (validateCurrentStep()) {
                saveCurrentStepData();
                submitForm();
            }
        });
        
        // Real-time calculation when area changes
        $('input[name="area_m2"]').on('input', function() {
            const area = parseFloat($(this).val()) || 0;
            if (area > 0) {
                const streckkendeMeters = area * 8.5;
                $('.strekkende-meter-preview').text(streckkendeMeters.toFixed(1) + ' meter');
                // Update form data immediately
                formData.area_m2 = area;
                updatePricePreview();
                // Show calculation preview
                $('.evs-calculation-preview').show();
            } else {
                $('.evs-calculation-preview').hide();
            }
        });
        
        // Radio button change handlers for price preview
        $('input[type="radio"]').on('change', function() {
            updatePricePreview();
        });
        
        // Vloer dichtsmeren toggle handler
        $('input[name="vloer_dichtsmeren"]').on('change', function() {
            const value = $(this).val();
            const extraSchurenGroup = $('.evs-extra-schuren-group');
            
            if (value === 'ja') {
                extraSchurenGroup.show();
                // Make extra_schuren required when sealing is selected
                $('input[name="extra_schuren"]').prop('required', true);
            } else {
                extraSchurenGroup.hide();
                // Clear extra_schuren selection and make it not required
                $('input[name="extra_schuren"]').prop('checked', false).prop('required', false);
            }
            updatePricePreview();
        });
        
        // Extra schuren change handler
        $('input[name="extra_schuren"]').on('change', function() {
            updatePricePreview();
        });
    }

    function validateCurrentStep() {
        const currentStepElement = $('.evs-step-' + currentStep);
        let isValid = true;
        
        // Clear previous errors
        currentStepElement.find('.evs-error').remove();
        currentStepElement.find('.has-error').removeClass('has-error');
        
        // Validate required fields in current step
        currentStepElement.find('input[required], select[required], textarea[required]').each(function() {
            const field = $(this);
            const fieldType = field.attr('type');
            const fieldName = field.attr('name');
            
            if (fieldType === 'radio') {
                // Check if any radio button with this name is checked
                if (!currentStepElement.find('input[name="' + fieldName + '"]:checked').length) {
                    showFieldError(field, 'Dit veld is verplicht');
                    isValid = false;
                }
            } else if (fieldType === 'checkbox') {
                if (!field.is(':checked')) {
                    showFieldError(field, 'Dit veld is verplicht');
                    isValid = false;
                }
            } else {
                if (!field.val().trim()) {
                    showFieldError(field, 'Dit veld is verplicht');
                    isValid = false;
                }
            }
        });
        
        // Custom validations
        if (currentStep === 3) {
            const area = parseFloat($('input[name="area_m2"]').val()) || 0;
            if (area <= 0) {
                showFieldError($('input[name="area_m2"]'), 'Oppervlakte moet groter zijn dan 0 m²');
                isValid = false;
            } else if (area > 10000) {
                showFieldError($('input[name="area_m2"]'), 'Oppervlakte lijkt onrealistisch groot');
                isValid = false;
            }
        }
        
        if (currentStep === 8) {
            const email = $('input[name="email"]').val();
            if (email && !isValidEmail(email)) {
                showFieldError($('input[name="email"]'), 'Voer een geldig e-mailadres in');
                isValid = false;
            }
        }
        
        return isValid;
    }

    function showFieldError(field, message) {
        const formGroup = field.closest('.evs-form-group');
        formGroup.addClass('has-error');
        
        if (!formGroup.find('.evs-error').length) {
            formGroup.append('<span class="evs-error">' + message + '</span>');
        }
        
        // Focus on first error field
        if (formGroup.is(':first-child') || !$('.has-error').length === 1) {
            field.focus();
        }
    }

    function isValidEmail(email) {
        const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        return emailRegex.test(email);
    }

    function saveCurrentStepData() {
        const currentStepElement = $('.evs-step-' + currentStep);
        
        // Save form data from current step
        currentStepElement.find('input, select, textarea').each(function() {
            const field = $(this);
            const fieldName = field.attr('name');
            const fieldType = field.attr('type');
            
            if (fieldType === 'radio' || fieldType === 'checkbox') {
                if (field.is(':checked')) {
                    formData[fieldName] = field.val();
                }
            } else {
                formData[fieldName] = field.val();
            }
        });
    }

    function goToStep(stepNumber) {
        if (stepNumber < 1 || stepNumber > totalSteps) {
            return;
        }
        
        // Hide current step
        $('.evs-step-' + currentStep).removeClass('active').hide();
        
        // Show new step
        currentStep = stepNumber;
        $('.evs-step-' + currentStep).addClass('active').show();
        
        // Update progress bar
        updateProgressBar();
        
        // Generate summary if on last step
        if (currentStep === totalSteps) {
            generateSummary();
        }
        
        // Scroll to top
        $('.evs-offerte-wrapper')[0].scrollIntoView({ behavior: 'smooth' });
    }

    function updateProgressBar() {
        const progressPercentage = (currentStep / totalSteps) * 100;
        $('.evs-progress-fill').css('width', progressPercentage + '%');
        $('.evs-current-step').text(currentStep);
        $('.evs-total-steps').text(totalSteps);
    }

    function generateSummary() {
        // Collect all form data
        saveCurrentStepData();
        
        // Calculate pricing
        const pricing = calculatePricing();
        
        let summaryHtml = '<h4>Samenvatting van uw aanvraag</h4>';
        
        // Project details
        summaryHtml += '<div class="evs-summary-section">';
        summaryHtml += '<div class="evs-summary-item"><span class="evs-summary-label">Verdieping:</span><span class="evs-summary-value">' + formatFieldValue('verdieping', formData.verdieping) + '</span></div>';
        summaryHtml += '<div class="evs-summary-item"><span class="evs-summary-label">Type vloer:</span><span class="evs-summary-value">' + formatFieldValue('type_vloer', formData.type_vloer) + '</span></div>';
        summaryHtml += '<div class="evs-summary-item"><span class="evs-summary-label">Oppervlakte:</span><span class="evs-summary-value">' + parseFloat(formData.area_m2).toFixed(1) + ' m²</span></div>';
        summaryHtml += '<div class="evs-summary-item"><span class="evs-summary-label">Strekkende meter:</span><span class="evs-summary-value">' + pricing.strekkende_meter.toFixed(1) + ' meter</span></div>';
        summaryHtml += '<div class="evs-summary-item"><span class="evs-summary-label">Warmtebron:</span><span class="evs-summary-value">' + formatFieldValue('warmtebron', formData.warmtebron) + '</span></div>';
        summaryHtml += '<div class="evs-summary-item"><span class="evs-summary-label">Verdeler aansluiten:</span><span class="evs-summary-value">' + (formData.verdeler_aansluiten === 'ja' ? 'Ja' : 'Nee') + '</span></div>';
        summaryHtml += '<div class="evs-summary-item"><span class="evs-summary-label">Vloer dichtsmeren:</span><span class="evs-summary-value">' + (formData.vloer_dichtsmeren === 'ja' ? 'Ja' : 'Nee') + '</span></div>';
        summaryHtml += '</div>';
        
        // Pricing breakdown
        summaryHtml += '<h4 style="margin-top: 20px;">Prijsberekening</h4>';
        summaryHtml += '<div class="evs-summary-item"><span class="evs-summary-label">Boorwerk:</span><span class="evs-summary-value">€' + (pricing.drilling_price - pricing.verdeler_price).toFixed(2) + '</span></div>';
        
        if (pricing.verdeler_price > 0) {
            summaryHtml += '<div class="evs-summary-item"><span class="evs-summary-label">Verdeler aansluiten:</span><span class="evs-summary-value">€' + pricing.verdeler_price.toFixed(2) + '</span></div>';
        }
        
        if (pricing.sealing_price > 0) {
            summaryHtml += '<div class="evs-summary-item"><span class="evs-summary-label">Vloer dichtsmeren:</span><span class="evs-summary-value">€' + pricing.sealing_price.toFixed(2) + '</span></div>';
        }
        
        summaryHtml += '<div class="evs-summary-item"><span class="evs-summary-label">Totaalprijs (incl. BTW):</span><span class="evs-summary-value">€' + pricing.total_price.toFixed(2) + '</span></div>';
        
        $('#evs-summary').html(summaryHtml);
    }

    function calculatePricing() {
        const area_m2 = parseFloat(formData.area_m2) || 0;
        const strekkende_meter = area_m2 * 8.5;
        
        let drilling_price = 0;
        
        // Calculate drilling price based on floor type
        switch (formData.type_vloer) {
            case 'cement_dekvloer':
            case 'fermacelvloer':
                drilling_price = calculateTieredPrice(strekkende_meter);
                break;
            case 'tegelvloer':
                drilling_price = strekkende_meter * 2.25;
                break;
            case 'betonvloer':
                drilling_price = strekkende_meter * 4.00;
                break;
        }
        
        const verdeler_price = (formData.verdeler_aansluiten === 'ja') ? 185.00 : 0;
        
        // Calculate sealing price (per m²) + extra schuren if applicable
        let sealing_base_price = 0;
        let extra_schuren_price = 0;
        
        if (formData.vloer_dichtsmeren === 'ja') {
            sealing_base_price = area_m2 * 12.75; // Correct: per m²
            
            // Add extra schuren cost if selected
            if (formData.extra_schuren === 'ja') {
                extra_schuren_price = area_m2 * 7.00; // €7 per m²
            }
        }
        
        const sealing_price = sealing_base_price + extra_schuren_price;
        const total_drilling_price = drilling_price + verdeler_price;
        const total_price = total_drilling_price + sealing_price;
        
        return {
            area_m2: area_m2,
            strekkende_meter: strekkende_meter,
            drilling_price: total_drilling_price,
            verdeler_price: verdeler_price,
            sealing_base_price: sealing_base_price,
            extra_schuren_price: extra_schuren_price,
            sealing_price: sealing_price,
            total_price: total_price
        };
    }

    function calculateTieredPrice(strekkende_meter) {
        let total_price = 0;
        let remaining_meters = strekkende_meter;
        
        // Tier 1: 0-250m = €1,77
        if (remaining_meters > 0) {
            const tier1_meters = Math.min(remaining_meters, 250);
            total_price += tier1_meters * 1.77;
            remaining_meters -= tier1_meters;
        }
        
        // Tier 2: 250-500m = €1,67
        if (remaining_meters > 0) {
            const tier2_meters = Math.min(remaining_meters, 250);
            total_price += tier2_meters * 1.67;
            remaining_meters -= tier2_meters;
        }
        
        // Tier 3: 500-750m = €1,57
        if (remaining_meters > 0) {
            const tier3_meters = Math.min(remaining_meters, 250);
            total_price += tier3_meters * 1.57;
            remaining_meters -= tier3_meters;
        }
        
        // Tier 4: 750+ = €1,47
        if (remaining_meters > 0) {
            total_price += remaining_meters * 1.47;
        }
        
        return total_price;
    }

    function updatePricePreview() {
        // Only show preview if we have enough data
        if (formData.type_vloer && formData.area_m2) {
            const pricing = calculatePricing();
            
            // Update any price preview elements
            $('.price-preview').text('€' + pricing.total_price.toFixed(2));
            $('.evs-calculation-preview').show();
        } else if (formData.area_m2) {
            // Show basic calculation if we only have area
            $('.price-preview').text('Selecteer vloertype voor prijs');
            $('.evs-calculation-preview').show();
        }
    }

    function formatFieldValue(fieldType, value) {
        const formats = {
            'verdieping': {
                'begaande_grond': 'Begaande grond',
                'eerste_verdieping': 'Eerste verdieping',
                'zolder': 'Zolder',
                'anders': 'Anders'
            },
            'type_vloer': {
                'cement_dekvloer': 'Cement dekvloer',
                'tegelvloer': 'Tegelvloer',
                'betonvloer': 'Betonvloer',
                'fermacelvloer': 'Fermacelvloer'
            },
            'warmtebron': {
                'cv_ketel': 'CV ketel',
                'hybride_warmtepomp': 'Hybride warmtepomp',
                'volledige_warmtepomp': 'Volledige warmtepomp',
                'stadsverwarming': 'Stadsverwarming',
                'toekomstige_warmtepomp': 'Toekomstige warmtepomp'
            }
        };
        
        return formats[fieldType] && formats[fieldType][value] ? formats[fieldType][value] : value;
    }

    function submitForm() {
        // Show loading state
        $('#evs-loading').show();
        $('.evs-btn-submit').prop('disabled', true);
        
        // Prepare form data for submission
        const submitData = $.extend({}, formData, {
            action: 'evs_vloerverwarming_offerte_submit',
            evs_nonce: $('#evs-offerte-form input[name="evs_nonce"]').val(),
            nonce: evs_ajax.nonce  // Also send the localized nonce as backup
        });
        
        // Submit via AJAX
        $.ajax({
            url: evs_ajax.ajax_url,
            type: 'POST',
            data: submitData,
            dataType: 'json',
            success: function(response) {
                $('#evs-loading').hide();
                
                if (response.success) {
                    showSuccessMessage(response.data.message);
                    $('#evs-offerte-form').hide();
                } else {
                    showErrorMessage(response.data.message);
                    if (response.data.errors) {
                        // Log validation errors for debugging if needed
                        // console.log('Validation errors:', response.data.errors);
                    }
                }
            },
            error: function(xhr, status, error) {
                $('#evs-loading').hide();
                $('.evs-btn-submit').prop('disabled', false);
                
                console.error('AJAX Error:', error);
                showErrorMessage('Er is een technische fout opgetreden. Probeer het later opnieuw.');
                
                // Log JavaScript errors to server
                logError('AJAX submission failed', {
                    status: status,
                    error: error,
                    responseText: xhr.responseText
                });
            }
        });
    }

    function showSuccessMessage(message) {
        const successHtml = '<div class="evs-success-message">' + message + '</div>';
        $('.evs-offerte-wrapper').prepend(successHtml);
        $('.evs-offerte-wrapper')[0].scrollIntoView({ behavior: 'smooth' });
    }

    function showErrorMessage(message) {
        const errorHtml = '<div class="evs-error-message">' + message + '</div>';
        $('.evs-step-' + currentStep).prepend(errorHtml);
        $('.evs-btn-submit').prop('disabled', false);
        $('.evs-offerte-wrapper')[0].scrollIntoView({ behavior: 'smooth' });
    }

    function logError(message, data) {
        $.ajax({
            url: evs_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'log_evs_form_error',
                nonce: evs_ajax.nonce,
                message: message,
                error: JSON.stringify(data),
                url: window.location.href
            }
        });
    }

    // Global error handler for JavaScript errors
    window.onerror = function(message, source, lineno, colno, error) {
        logError('JavaScript error: ' + message, {
            source: source,
            line: lineno,
            column: colno,
            error: error ? error.toString() : ''
        });
    };

})(jQuery);
