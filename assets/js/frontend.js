jQuery(document).ready(function($) {
    $('#salon-booking-form').on('submit', function(event) {
        event.preventDefault();

        var data = {
            action: 'salon_booking_submit',
            nonce: salonBooking.nonce,
            customer_name: $('#customer_name').val(),
            customer_email: $('#customer_email').val(),
            customer_phone: $('#customer_phone').val(),
            stylist_id: $('#stylist_id').val(),
            service_id: $('#service_id').val(),
            appointment_date: $('#appointment_date').val(),
            appointment_time: $('#appointment_time').val(),
            payment_method: $('#payment_method').val(),
            notes: $('#notes').val(),
            reminder_email: $('input[name="reminder_email"]').is(':checked') ? 1 : 0,
            reminder_sms: $('input[name="reminder_sms"]').is(':checked') ? 1 : 0,
            reminder_push: $('input[name="reminder_push"]').is(':checked') ? 1 : 0,
        };

        $.post(salonBooking.ajaxUrl, data, function(response) {
            if (response.success) {
                if (response.data.redirect_url) {
                    // PayPal Redirect
                    window.location.href = response.data.redirect_url;
                } else if (response.data.checkout_url) {
                    window.location.href = response.data.checkout_url;
                } else {
                    $('#salon-booking-message').html('<div class="success-message">' + response.data.message + '</div>');
                    $('#salon-booking-form')[0].reset();
                }
            } else {
                $('#salon-booking-message').html('<div class="error-message">' + (response.data.message || 'Ein unbekannter Fehler ist aufgetreten.') + '</div>');
            }
        }).fail(function(jqXHR, textStatus, errorThrown) {
            $('#salon-booking-message').html('<div class="error-message">Netzwerkfehler: ' + textStatus + ' - ' + errorThrown + '. Bitte versuchen Sie es später erneut.</div>');
        });
    });

    if ( salonBooking.oneSignalAppId ) {
        window.OneSignal = window.OneSignal || [];
        OneSignal.push(function() {
            OneSignal.init({
                appId: salonBooking.oneSignalAppId,
            });
        });

        $('input[name="reminder_push"]').on('change', function() {
            if ( $(this).is(':checked') ) {
                OneSignal.push(function() {
                    if ( typeof OneSignal.showNativePrompt === 'function' ) {
                        OneSignal.showNativePrompt();
                    }
                });
            }
        });
    }
});
