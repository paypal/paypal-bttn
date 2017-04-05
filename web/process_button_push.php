<?php
#ini_set('display_errors', 1);
#ini_set('display_startup_errors', 1);
#error_reporting(E_ALL);
require_once(dirname(__FILE__) . '/lib/db.php');
require_once(dirname(__FILE__) . '/lib/slack.php');
require_once(dirname(__FILE__) . '/lib/braintree.php');
require_once(dirname(__FILE__) . '/lib/email.php');
require_once(dirname(__FILE__) . '/lib/bttn.php');
include_once(dirname(__FILE__) . "/lib/cart.php");


$apiKey = $_SERVER['HTTP_X_API_KEY'];
$postdata = file_get_contents("php://input");


$msg = sprintf( "Received Button Push API call apiKey: `%s`, contents: `%s`", $apiKey, addslashes($postdata) );
sendSlackMessage($msg);


if( empty($apiKey) )
{
	http_response_code(401);
	die("no");
}

$postJson = json_decode($postdata);
if (sizeof($postJson) > 0 )
{
	$associd = $postJson->associd;
	$type = $postJson->type;
	$braintreeCustomerId = $postJson->bt_id;
	$chargeType = $postJson->charge_type;
}
else
{
	die("bad json");
}


// Security measure
// Verify that the API key is the SHA1 of the user's email address
// This is configured with the bttn in the lib/bttn.php add_merchant_data_to_button() call
if ( sha1($associd) != $apiKey )
{
	http_response_code(403);
	die( "bad api key, use " . sha1($associd) );
}


// We'll use the braintree id that bttn called us with - preferred
if( $settings["USE_DATABASE"] == false )
{
	if( empty($braintreeCustomerId) )
	{
		http_response_code(404);
		die("no bt id supplied");
	}

	if( empty($chargeType))
	{
		http_response_code(404);
		die("no charge type supplied");
	}

	$lastBtTxn = getLastTransaction($braintreeCustomerId);
	$emailAddress = getBrainTreeCustomerById($braintreeCustomerId)->email;
}
// We'll lookup the bttn code association from our database (and not use the braintree customer id that bttn sent to us)
else
{
	// See if a button exists with this code
	$user = getUserByButtonCode($associd);
	if ( empty($user) )
	{
		http_response_code(404);
		die("no user");
	}

	$braintreeCustomerId = $user["customer_id"];
	$chargeType = $user["amount_to_charge"];
	$emailAddress = $user["email"];
}




$items = array(); // an array of item ids

$lastBtTxn = getLastTransaction($braintreeCustomerId);


if( $chargeType == REORDER )
{
  // Get the last transaction and re-charge them
  $amount = $lastBtTxn->amount;

	$lastTxnCartCsv = $lastBtTxn->customFields["cart_details"];
	$items = explode(",", $lastTxnCartCsv);

	if(!is_numeric($amount))
  {
    die("unable to get reorder amount");
  }
  $cart_detail_text = "bttn pressed - reorder";
}
else if( $chargeType == SELECTION )
{
  // Send the user an email to allow them to select from some options
	$previousOrder = array(
		"cost" => $lastBtTxn->amount,
		"title" => "Your previous order",
		"image" => "img/portfolio/6.jpg"
	);

	$lastTxnCartCsv = $lastBtTxn->customFields["cart_details"];
	$lastCart = getCartFromItemIdCsv($lastTxnCartCsv);

	$options = array();
	array_push( $options, $previousOrder);
	foreach( $settings["CART_OPTIONS"] as $option )
	{
			array_push( $options, $option);
	}


  sendSelectionEmail( $emailAddress,  $options);
  // We don't charge the consumer until they click on something in the email/sms
  echo "success";
  return;
}
else
{
	// If it's not REORDER or SELECTION, then we grab chargeType as the amount to charge (FIXED)
  $amount = $chargeType;

  if(is_numeric($amount))
  {
      $cart_detail_text = "bttn pressed - fixed price";
  }
  else {
    http_response_code(400);
    die("amount is not a number");
  }
}

$result = chargeBraintreeCustomer($braintreeCustomerId, $emailAddress, $amount, $cart_detail_text, $items, true);

if ($result->success) {
	echo "success";
  $msg = sprintf("Successfully charged consumer email: `%s`, amount: `%s`, apiKey: `%s`", $emailAddress, $amount, $apiKey);
  sendSlackMessage($msg);
}
else
{
	echo "fail " . $result->message;
  $msg = sprintf("Failed to charge consumer email: `%s`, amount: `%s`, apiKey: `%s`, error: `%s`", $emailAddress, $amount, $apiKey, $result->message);
  sendSlackMessage($msg);
}

?>
