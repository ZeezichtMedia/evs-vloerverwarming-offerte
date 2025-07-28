
jQuery(document).ready(function($) {
    // --- Helper function for currency formatting (DRY principle) ---
    function formatCurrency(value) {
        const number = parseFloat(value) || 0;
        return "€" + number.toFixed(2).replace(".", ",");
    }

    // --- Function to update the price display in the sidebar ---
    function updatePriceDisplay(data) {
        $("#boren-price").text(formatCurrency(data.drilling_price));
        $("#verdeler-price").text(formatCurrency(data.verdeler_price));
        $("#dichtsmeren-price").text(formatCurrency(data.sealing_price));
        $("#schuren-price").text(formatCurrency(data.sanding_price || 0));
        $("#total-price").text(formatCurrency(data.total_price));
        if (data.strekkende_meter) {
             $("#strekkende-meter").text(data.strekkende_meter.toFixed(2).replace(".", ","));
        }
    }

    // --- Function to calculate the price via AJAX ---
    function calculatePrice(showLoading = true) {
        const formData = $("#evs-edit-quote-form").serialize();
        const nonce = evs_admin_quote_data.nonce;

        $.ajax({
            url: evs_admin_quote_data.ajax_url,
            type: "POST",
            data: {
                action: "evs_calculate_admin_price",
                nonce: nonce,
                form_data: formData,
            },
            beforeSend: function() {
                if (showLoading) {
                    // Only show loading if explicitly requested (not on initial load)
                    $(".price-sidebar .price-value strong").addClass('calculating');
                }
            },
            success: function(response) {
                $(".price-sidebar .price-value strong").removeClass('calculating');
                if (response.success) {
                    updatePriceDisplay(response.data);
                } else {
                    console.error("Price calculation failed:", response.data.message);
                    // Don't reset to 0 on error, keep existing values
                }
            },
            error: function(jqXHR, textStatus, errorThrown) {
                $(".price-sidebar .price-value strong").removeClass('calculating');
                console.error("AJAX error:", textStatus, errorThrown);
                // Don't reset to 0 on error, keep existing values
            }
        });
    }

    // --- Event listeners for real-time price calculation ---
    const priceInputs = 'input[name="area_m2"], select[name="type_vloer"], select[name="verdeler_aansluiten"], select[name="vloer_dichtsmeren"], select[name="vloer_schuren"]';
    $(priceInputs).on("change keyup", function() {
        calculatePrice();
    });

    // --- Initial price calculation on page load (without loading indicator) ---
    // Don't calculate on initial load to preserve server-rendered values
    // calculatePrice(false);

    // --- Event listeners for action buttons ---
    // Corrected IDs to match HTML (-btn suffix)
    $('#save-quote-btn').on('click', function(e) {
        e.preventDefault();
        $('#action_type').val('save');
        $('#evs-edit-quote-form').submit();
    });

    $('#send-quote-btn').on('click', function(e) {
        e.preventDefault();
        $('#action_type').val('send_quote');
        $('#evs-edit-quote-form').submit();
    });

    $('#create-invoice-btn').on('click', function(e) {
        e.preventDefault();
        $('#action_type').val('create_invoice');
        $('#evs-edit-quote-form').submit();
    });
});
