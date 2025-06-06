# Campus Mediate

A comprehensive hostel management system for university students and hostel managers in Uganda.

## Features

- Student registration and authentication
- Hostel listing and room booking
- Payment processing (UGX currency) with multiple payment methods
- Manager dashboard with payment tracking (Completed, Failed, Refunded)
- Real-time notifications and alerts system
- Booking management and status tracking
- Room inventory and availability management

## Tech Stack

- PHP 7.4+
- MySQL Database
- HTML/CSS/JavaScript
- FlutterWave Payment Integration
- Font Awesome icons

## Database Structure

The system uses the following main tables:
- users: Student and manager accounts
- hostels: Hostel properties and details
- rooms: Room inventory and types
- bookings: Student reservation information
- payments: Transaction records and status
- notifications: System alerts and messages

## Installation

1. Clone the repository
2. Configure your web server (Apache/XAMPP) to point to the project directory
3. Import the database schema from `config/schema.sql` (export from phpMyAdmin)
4. Copy `config/config.sample.php` to `config/config.php` and update settings
5. Access the application through your web browser

## Requirements

- PHP 7.4 or higher
- MySQL 5.7 or higher
- XAMPP/WAMP/LAMP stack

## License

Copyright © 2024 Campus Mediate
