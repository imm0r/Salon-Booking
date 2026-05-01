document.addEventListener('DOMContentLoaded', function () {
    var calendarEl = document.getElementById('salon-booking-calendar');

    if (!calendarEl || typeof FullCalendar === 'undefined') {
        return;
    }

    var calendar = new FullCalendar.Calendar(calendarEl, {
        initialView: 'dayGridMonth',
        headerToolbar: {
            left: 'prev,next today',
            center: 'title',
            right: 'dayGridMonth,timeGridWeek,timeGridDay'
        },
        events: window.salonBookingAdmin ? window.salonBookingAdmin.events : [],
        eventDisplay: 'block',
        height: 'auto'
    });

    calendar.render();
});
