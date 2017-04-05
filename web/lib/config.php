<?php

// Braintree Configs
Braintree_Configuration::environment(getenv("braintree_environment"));
Braintree_Configuration::merchantId(getenv("braintree_merchantId"));
Braintree_Configuration::publicKey(getenv("braintree_publicKey"));
Braintree_Configuration::privateKey(getenv("braintree_privateKey"));
$settings["BT_CHANNEL_NAME"] = getenv("BT_CHANNEL_NAME"); // A Braintree API value that is used to keep track of the channel that a transaction came from


// bttn Settings - these will be supplied to you by bttn
$settings["BTTN_API_KEY"] = getenv("BTTN_API_KEY");
$settings["BTTN_API_MERCHANT_NAME"] = getenv("BTTN_API_MERCHANT_NAME");
// Use the current server that this code is running on as the endpoint - when the button is pressed, this URL will be called
$settings["BTTN_PRESS_URI_ENDPOINT"] = sprintf("%s://%s%s", $_SERVER["REQUEST_SCHEME"], $_SERVER["HTTP_HOST"], "/process_button_push.php");



// Default order values
define("REORDER", "reorder");     // Lookup the previous Braintree order and charge the customer that amount
define("SELECTION", "selection"); // Send the customer an email with a list of possible selections - this will only work if $settings["USE_DATABASE"] is true.
define("FIXED", "1.21");          // Always change this fixed amount
$settings["DEFAULT_ORDER_VALUE"] = REORDER;

// DB Settings
$settings["USE_DATABASE"] = false; // Recommended: false - for demo purposes to show that this can be done without a database
DB::$user = 'button';
DB::$password = 'button1';
DB::$dbName = 'button';
DB::$host = '127.0.0.1';


// Slack Settings
$settings["SLACK_ENABLE"] = getenv("SLACK_ENABLE");
$settings["SLACK_URL"] = getenv("SLACK_URL");

// Email Settings
$settings["MAIL_FROM_NAME"] = "William's Widgets";
$settings["MAIL_FROM_EMAIL"] = "noreply@example.com";
$settings["MAIL_BCC"] = "person@example.com";
$settings["USE_SENDMAIL"] = true;
// If you are not using Sendmail, use the following settings
$settings["MAIL_SMTP_HOST"] = "example.com";
$settings["MAIL_SMTP_PORT"] = 25;

// These options are used to dynamically render a cart for the consumer
$settings["CART_OPTIONS"] = array(
  array(
    "id" => "1",
    "cost" => "12.00",
    "title" => "Gnocchi",
    "image" => "img/portfolio/gnocchi.jpg"
  ),
  array(
    "id" => "2",
    "cost" => "6.00",
    "title" => "Ice cream",
    "image" => "img/portfolio/ice_cream.jpg"
  ),
  array(
    "id" => "3",
    "cost" => "16.00",
    "title" => "Pork Belly Sandwich",
    "image" => "img/portfolio/pork_belly_sandwich.jpg"
  ),
  array(
    "id" => "4",
    "cost" => "9.00",
    "title" => "Lox and Cream Cheese Bagel",
    "image" => "img/portfolio/salmon_bagel.jpg"
  ),
  array(
    "id" => "5",
    "cost" => "4.00",
    "title" => "Cookies",
    "image" => "img/portfolio/cookies.jpg"
  )
);

?>
