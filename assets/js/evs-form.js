/**
 * EVS Offerte Form Handler
 * Handles multi-step form navigation, validation, and submission
 */
class EVSOfferteForm {
    constructor() {
        this.currentStep = 1;
        this.$form = jQuery('#evs-offerte-formulier');
        this.$formSteps = jQuery('.evs-form-step');
        this.totalSteps = this.$formSteps.length;
        this.$formMessages = null;
        
        this.init();
    }
    
    init() {
        // Add message container
        this.$form.prepend('<div id="evs-form-messages"></div>');
        this.$formMessages = jQuery('#evs-form-messages');
        
        // Bind events
        this.bindEvents();
        
        // Show initial step
        this.showStep(this.currentStep);
        
        // Initialize conditional fields
        this.initializeConditionalFields();
    }
    
    bindEvents() {
        const self = this;
        
        // Navigation buttons
        this.$form.on('click', '.evs-next-btn', function() {
            self.handleNext();
        });
        
        this.$form.on('click', '.evs-prev-btn', function() {
            self.handlePrevious();
        });
        
        // Form submission
        this.$form.on('submit', function(e) {
            e.preventDefault();
            self.handleSubmit();
        });
        
        // Radio button visual selection
        this.$form.on('change', 'input[type="radio"]', function() {
            self.handleRadioSelection(jQuery(this));
        });
        
        // Conditional field handlers
        this.$form.on('change', 'input[name="verdieping"]', function() {
            self.handleConditionalField(jQuery(this), 'verdieping', 'anders', '#verdieping-anders-input');
        });
        
        this.$form.on('change', 'input[name="montagedatum_type"]', function() {
            self.handleConditionalField(jQuery(this), 'montagedatum_type', 'datum', '#datum-input');
        });
    }
    
    updateProgress(step) {
        jQuery('.evs-progress-item').removeClass('active');
        jQuery(`.evs-progress-item[data-step="${step}"]`).addClass('active');
        
        // Update progress bar width dynamically
        const progressPercentage = (step / this.totalSteps) * 100;
        jQuery('.evs-progress-bar').css('width', progressPercentage + '%');
    }
    
    showStep(step) {
        this.$formSteps.removeClass('active').hide();
        this.$formSteps.filter(`[data-step="${step}"]`).show().addClass('active');
        this.updateProgress(step);
    }
    
    validateCurrentStep() {
        const $currentStep = jQuery(`.evs-form-step[data-step="${this.currentStep}"]`);
        return this.validateStepElement($currentStep);
    }
    
    validateStepElement($stepElement) {
        let isValid = true;
        
        // Clear previous errors
        $stepElement.find('.evs-error-message').remove();
        
        $stepElement.find('input[required], select[required], textarea[required]').each((index, element) => {
            const $input = jQuery(element);
            
            if ($input.prop('disabled')) return;
            
            const validationResult = this.validateInput($input);
            if (!validationResult.isValid) {
                isValid = false;
                this.showValidationError($input, validationResult.message);
                $input.addClass('is-invalid');
            } else {
                $input.removeClass('is-invalid');
            }
        });
        
        return isValid;
    }
    
    validateInput($input) {
        if ($input.is(':radio')) {
            const name = $input.attr('name');
            if (jQuery(`input[name="${name}"]:checked`).length === 0) {
                return { isValid: false, message: 'Maak een keuze.' };
            }
        } else if ($input.is(':checkbox')) {
            if (!$input.is(':checked')) {
                return { isValid: false, message: 'Dit veld is verplicht.' };
            }
        } else if (!$input.val() || $input.val().trim() === '') {
            return { isValid: false, message: 'Dit veld is verplicht.' };
        } else if ($input.attr('type') === 'number') {
            // Validate number fields
            const value = parseFloat($input.val());
            const min = parseFloat($input.attr('min')) || 0;
            const max = parseFloat($input.attr('max')) || Infinity;
            
            if (isNaN(value)) {
                return { isValid: false, message: 'Voer een geldig getal in.' };
            }
            if (value < min) {
                return { isValid: false, message: `Waarde moet minimaal ${min} zijn.` };
            }
            if (value > max) {
                return { isValid: false, message: `Waarde mag maximaal ${max} zijn.` };
            }
        }
        
        return { isValid: true };
    }
    
    showValidationError($input, message) {
        const $container = $input.closest('.evs-input-group, .evs-options-container, .evs-checkbox-container, .evs-form-group');
        
        if ($container.find('.evs-error-message').length === 0) {
            $container.append(`<div class="evs-error-message">${message}</div>`);
        }
    }
    
    handleNext() {
        if (this.validateCurrentStep()) {
            if (this.currentStep < this.totalSteps) {
                this.currentStep++;
                this.showStep(this.currentStep);
            }
        }
    }
    
    handlePrevious() {
        if (this.currentStep > 1) {
            this.currentStep--;
            this.showStep(this.currentStep);
        }
    }
    
    handleRadioSelection($input) {
        // Remove selected class from all cards in the same group
        const name = $input.attr('name');
        jQuery(`input[name="${name}"]`).closest('.evs-option-card').removeClass('selected');
        
        // Add selected class to current card
        $input.closest('.evs-option-card').addClass('selected');
        
        // Handle conditional fields
        this.handleConditionalFields($input);
    }
    
    handleConditionalField($input, fieldName, triggerValue, targetSelector) {
        const $target = jQuery(targetSelector);
        const $targetInput = $target.find('input, select, textarea').first();
        
        if ($input.val() === triggerValue) {
            $target.show();
            $targetInput.prop('required', true);
        } else {
            $target.hide();
            $targetInput.prop('required', false).val('').removeClass('is-invalid');
            $target.find('.evs-error-message').remove();
        }
    }
    
    handleConditionalFields($input) {
        const name = $input.attr('name');
        const value = $input.val();
        
        // Handle different conditional fields
        switch (name) {
            case 'verdieping':
                this.handleConditionalField($input, 'verdieping', 'anders', '#verdieping-anders-input');
                break;
            case 'montagedatum_type':
                this.handleConditionalField($input, 'montagedatum_type', 'datum', '#datum-input');
                break;
        }
    }
    
    initializeConditionalFields() {
        // Initialize all conditional fields on page load
        this.$form.find('input[type="radio"]:checked').each((index, element) => {
            this.handleConditionalFields(jQuery(element));
        });
    }
    
    collectFormData() {
        const formData = {};
        
        // Collect all form inputs, selects, and textareas from all steps
        this.$form.find('input, select, textarea').each(function() {
            const $input = jQuery(this);
            const name = $input.attr('name');
            const type = $input.attr('type');
            
            if (!name) return; // Skip inputs without names
            
            if (type === 'radio') {
                // For radio buttons, only get the checked value
                if ($input.is(':checked')) {
                    formData[name] = $input.val();
                }
            } else if (type === 'checkbox') {
                // For checkboxes, set true/false or use value if checked
                if ($input.is(':checked')) {
                    formData[name] = $input.val() || true;
                } else {
                    // Only set false if no value was set yet (avoid overwriting checked boxes)
                    if (!(name in formData)) {
                        formData[name] = false;
                    }
                }
            } else {
                // For text inputs, selects, textareas
                formData[name] = $input.val() || '';
            }
        });
        
        return formData;
    }
    
    handleSubmit() {
        if (!this.validateCurrentStep()) {
            return;
        }
        
        // Check if AJAX object is available
        if (typeof evs_offerte_ajax_object === 'undefined') {
            this.showFormMessage('Technische fout: AJAX configuratie ontbreekt.', 'error');
            return;
        }
        
        const $submitButton = this.$form.find('.evs-submit-btn');
        
        // Disable submit button and show loading state
        $submitButton.prop('disabled', true).text('Verzenden...');
        this.$formMessages.removeClass('success error').empty().hide();
        
        // Collect form data
        const formData = this.collectFormData();
        
        // Debug: Log collected form data to console
        console.log('Collected form data:', formData);
        
        // Prepare AJAX data
        const ajaxData = {
            action: 'evs_vloerverwarming_offerte_submit',
            nonce: evs_offerte_ajax_object.nonce,
            form_data: formData
        };
        
        // Submit via AJAX
        jQuery.ajax({
            url: evs_offerte_ajax_object.ajax_url,
            type: 'POST',
            data: ajaxData,
            dataType: 'json',
            success: (response) => {
                this.handleSubmitSuccess(response, $submitButton);
            },
            error: (jqXHR, textStatus, errorThrown) => {
                this.handleSubmitError(jqXHR, textStatus, errorThrown, $submitButton);
            }
        });
    }
    
    handleSubmitSuccess(response, $submitButton) {
        if (response.success) {
            // Hide form and show success message
            this.$form.hide();
            jQuery('.evs-progress-sidebar').hide();
            jQuery('.evs-header').after(response.data.message);
        } else {
            // Show error message from server
            let errorMessage = response.data.message || 'Er is een fout opgetreden';
            
            // If there are specific validation errors, show them
            if (response.data.errors && typeof response.data.errors === 'object') {
                const errorList = Object.values(response.data.errors);
                if (errorList.length > 0) {
                    errorMessage += ':<br>' + errorList.join('<br>');
                }
            }
            
            this.showFormMessage(errorMessage, 'error');
            $submitButton.prop('disabled', false).text('Offerte aanvragen');
        }
    }
    
    handleSubmitError(jqXHR, textStatus, errorThrown, $submitButton) {
        const errorMessage = 'Er is een technisch probleem opgetreden. Probeer het later opnieuw.';
        this.showFormMessage(errorMessage, 'error');
        $submitButton.prop('disabled', false).text('Offerte aanvragen');
    }
    
    showFormMessage(message, type) {
        this.$formMessages.html(message).removeClass('success error').addClass(type).show();
    }
}

// Initialize the form when DOM is ready
jQuery(document).ready(function($) {
    new EVSOfferteForm();
});
