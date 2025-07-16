jQuery(document).ready(function($) {
    let currentStep = 1;
    const $form = $('#evs-offerte-formulier');
    const $formSteps = $(".evs-form-step");
    const totalSteps = $formSteps.length;

    // Add a container for general form messages
    $form.prepend('<div id="evs-form-messages"></div>');
    const $formMessages = $('#evs-form-messages');

    function updateProgress(step) {
        $(".evs-progress-item").removeClass("active");
        $(".evs-progress-item[data-step='" + step + "']").addClass("active");
    }

    function showStep(step) {
        $formSteps.removeClass('active').hide();
        $formSteps.filter("[data-step='" + step + "']").show().addClass('active');
        updateProgress(step);
    }

    function validateStep(step) {
        let isValid = true;
        const $currentStep = $(".evs-form-step[data-step='" + step + "']");
        // Clear previous errors in the current step
        $currentStep.find('.evs-error-message').remove();

        $currentStep.find('input[required], select[required], textarea[required]').each(function () {
            const $input = $(this);
            if ($input.prop('disabled')) return; // Skip disabled inputs

            let hasError = false;
            if ($input.is(':radio')) {
                const name = $input.attr('name');
                if ($(`input[name='${name}']:checked`).length === 0) {
                    isValid = false;
                    hasError = true;
                    showError($input.closest('.evs-options-container'), 'Maak een keuze.');
                }
            } else if ($input.is(':checkbox')) {
                if (!$input.is(':checked')) {
                    isValid = false;
                    hasError = true;
                    showError($input.closest('.evs-checkbox-container'), 'Dit veld is verplicht.');
                }
            } else if (!$input.val() || $input.val().trim() === '') {
                isValid = false;
                hasError = true;
                showError($input, 'Dit veld is verplicht.');
            }

            if(hasError) {
                $input.addClass('is-invalid');
            } else {
                $input.removeClass('is-invalid');
            }
        });

        return isValid;
    }

    function showError($element, message) {
        // Prevent duplicate messages
        if ($element.closest('.evs-input-group, .evs-options-container, .evs-checkbox-container').find('.evs-error-message').length === 0) {
            $element.closest('.evs-input-group, .evs-options-container, .evs-checkbox-container').append(`<div class="evs-error-message">${message}</div>`);
        }
    }

    // --- Event Handlers ---

    // Next button
    $form.on('click', '.evs-next-btn', function () {
        if (validateStep(currentStep)) {
            if (currentStep < totalSteps) {
                currentStep++;
                showStep(currentStep);
            }
        }
    });

    // Previous button
    $form.on('click', '.evs-prev-btn', function () {
        if (currentStep > 1) {
            currentStep--;
            showStep(currentStep);
        }
    });

    // Conditional logic for 'Anders, namelijk...'
    $('input[name="floor_level"]').on('change', function () {
        const $otherWrapper = $('#floor_level_other_wrapper');
        const $otherInput = $('#floor_level_other');
        if ($(this).val() === 'anders') {
            $otherWrapper.removeClass('hidden');
            $otherInput.prop('required', true);
        } else {
            $otherWrapper.addClass('hidden');
            $otherInput.prop('required', false).val('').removeClass('is-invalid');
            $otherWrapper.find('.evs-error-message').remove();
        }
    });

    // Conditional logic for 'Vloer schoon'
    $('input[name="sealing"]').on('change', function () {
        const $cleanWrapper = $('#floor_clean_wrapper');
        const $cleanInputs = $('input[name="floor_clean"]');
        if ($(this).val() === 'yes') {
            $cleanWrapper.removeClass('hidden');
            $cleanInputs.prop('required', true);
        } else {
            $cleanWrapper.addClass('hidden');
            $cleanInputs.prop('required', false).prop('checked', false).removeClass('is-invalid');
            $cleanWrapper.find('.evs-error-message').remove();
        }
    });

    // Conditional logic for installation date
    $('#date_unknown_checkbox').on('change', function () {
        const $dateInput = $('#installation_date');
        if ($(this).is(':checked')) {
            $dateInput.prop('disabled', true).prop('required', false).val('').removeClass('is-invalid');
            $dateInput.closest('.evs-input-group').find('.evs-error-message').remove();
        } else {
            $dateInput.prop('disabled', false).prop('required', true);
        }
    });

    // AJAX form submission for WordPress
    $('#evs-offerte-formulier').on('submit', function(e) {
        e.preventDefault();

        if (!validateStep(currentStep)) {
            return; // Final validation
        }

        var $form = $(this);
        var $submitButton = $form.find('.evs-submit-btn');
        var $formMessages = $('#evs-form-messages');

        $submitButton.prop('disabled', true).text('Verzenden...');
        $formMessages.removeClass('success error').empty().hide();

        var formData = $form.serialize();
        formData += '&action=evs_vloerverwarming_offerte_submit';
        formData += '&nonce=' + (typeof evs_offerte_ajax_object !== 'undefined' ? evs_offerte_ajax_object.nonce : '');

        $.ajax({
            url: (typeof evs_offerte_ajax_object !== 'undefined' ? evs_offerte_ajax_object.ajax_url : ''),
            type: 'POST',
            data: formData,
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    // On success, hide form elements and display the success message from the server
                    $form.hide();
                    $('.evs-progress-sidebar').hide();
                    $('.evs-header').after(response.data.message); 
                } else {
                    // On failure, show specific error messages returned from the server
                    $formMessages.html(response.data.message).addClass('error').show();
                    $submitButton.prop('disabled', false).text('Offerte aanvragen');
                }
            },
            error: function(jqXHR, textStatus, errorThrown) {
                // Handle server/network errors
                var errorMessage = 'Er is een technisch probleem opgetreden. Probeer het later opnieuw.';
                $formMessages.html(errorMessage).addClass('error').show();
                $submitButton.prop('disabled', false).text('Offerte aanvragen');
                // Log the detailed error for debugging
                logJsError('AJAX Error: ' + textStatus + ' - ' + errorThrown, 'evs-form.js', 'submit-handler');
            }
        });
    });

    // Handle visual selection of option cards
    $form.on('change', 'input[type="radio"]', function() {
        const $this = $(this);
        const radioName = $this.attr('name');
        
        // Remove selected class from all cards in the same group
        $(`input[name="${radioName}"]`).closest('.evs-option-card').removeClass('selected');
        
        // Add selected class to the parent card of the checked radio
        if ($this.is(':checked')) {
            $this.closest('.evs-option-card').addClass('selected');
        }
    });

    // --- Initial Setup ---
    showStep(currentStep);
});
