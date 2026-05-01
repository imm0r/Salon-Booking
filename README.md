<div align="center">
  <img src="assets/svg.png" width="600">
  
  ![Version](https://img.shields.io/badge/version-v0.1.31.7-blue)
  ![Build](https://img.shields.io/badge/build-stable-green)
  ![License](https://img.shields.io/badge/license-MIT-lightgrey)
  ![Language](https://img.shields.io/badge/language-JavaScript-orange)
</div>

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
