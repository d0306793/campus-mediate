<?php
// payment_config.php - Store payment gateway credentials
// IMPORTANT: Never commit this file to version control

// Flutterwave API Keys
define('FLUTTERWAVE_PUBLIC_KEY_TEST', 'FLWPUBK_TEST-xxxxxxxxxxxxxxxxxxxx');
define('FLUTTERWAVE_SECRET_KEY_TEST', 'FLWSECK_TEST-xxxxxxxxxxxxxxxxxxxx');

define('FLUTTERWAVE_PUBLIC_KEY_LIVE', 'FLWPUBK-xxxxxxxxxxxxxxxxxxxx');
define('FLUTTERWAVE_SECRET_KEY_LIVE', 'FLWSECK-xxxxxxxxxxxxxxxxxxxx');

// Set to true for test mode, false for live mode
define('FLUTTERWAVE_TEST_MODE', true);

// Get the appropriate keys based on mode
function getFlutterwavePublicKey() {
    return FLUTTERWAVE_TEST_MODE ? FLUTTERWAVE_PUBLIC_KEY_TEST : FLUTTERWAVE_PUBLIC_KEY_LIVE;
}

function getFlutterwaveSecretKey() {
    return FLUTTERWAVE_TEST_MODE ? FLUTTERWAVE_SECRET_KEY_TEST : FLUTTERWAVE_SECRET_KEY_LIVE;
}
?>
