# Salon Booking Plugin

Ein WordPress-Plugin zur Online-Terminbuchung für einen Frisörsalon.

## Installation

1. Kopiere das Plugin-Verzeichnis in `wp-content/plugins/salon-booking`
2. Aktiviere das Plugin im WordPress-Backend.
3. Verwende den Shortcode `[salon_booking_form]` auf einer Seite, um das Buchungsformular anzuzeigen.
4. Öffne im WordPress-Admin den Menüpunkt `Salon Booking` und wähle `Kalendersynchronisation`, um Google- oder Outlook-Einstellungen zu hinterlegen.

## Lokale Entwickler-Werkzeuge

- PHP: `tools/php/php.exe`
- Composer: `tools/composer/composer.phar`
- Die lokale PHP-Konfiguration befindet sich in `tools/php/php.ini`

## Features

- Terminbuchung für mehrere Friseure
- Service-Auswahl mit Dauer und Preis
- Admin-Ansicht für Buchungen
- Vorbereitung für Kalender- und Zahlungsintegration
- Responsive Frontend
