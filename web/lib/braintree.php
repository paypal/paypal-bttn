<?php
require_once(dirname(__FILE__) . "/../../vendor/autoload.php");
require_once(dirname(__FILE__) . "/../lib/email.php");
require_once(dirname(__FILE__) . "/db.php");
require_once(dirname(__FILE__) . "/bttn.php");
include_once(dirname(__FILE__) . "/config.php");

// Braintree SDK requires that a PHP timezone is set
date_default_timezone_set('US/Arizona');


function getClientToken()
{
  return Braintree_ClientToken::generate();
}

/* Get bttn data stored at Braintree
*
* Obtain button association code and status from Braintree, use that information to call bttn
*
*/
function get_user_button_data()
{
  $button_users = array();
  // First, call Braintree to get a list of user
  foreach(getRecentBraintreeCustomers() as $user)
  {
    $status = $user->customFields["bttn_status"];
    $assocationId = $user->customFields["bttn_code"];

    if(!empty($status))
    {
      $button_data = get_button_data($assocationId);
      $charge_type = $button_data["data"]["pressed"]["http"]["json"]["charge_type"];
      $email = $user->email;
      $braintreeCustomerId = $user->id;
      $button_user = array(
        "consumer_stage" => $status,
        "email" => $email,
        "amount_to_charge" => $charge_type,
        "customer_id" => $braintreeCustomerId
      );
      array_push($button_users, $button_user);
    }
  }

  return $button_users;
}

/* Get Default Payment Method
*
* Use the default payment method for a given customer
* Called by chargeBraintreeCustomer()
*
*/

function getDefaultPaymentMethod($customer)
{
  $paymentMethods = $customer->paymentMethods;
  foreach($paymentMethods as $paymentMethod){
	    if( $paymentMethod->isDefault() )
	    {
	      return $paymentMethod->token;
	    }
	}
}


/* Process Website Transaction
*
* Handle a simple Braintree purchase from a customer on the website
*/
function processWebsiteTransaction($email, $firstName, $lastName, $phone, $nonce, $amount, $items, $chargeType)
{
  global $settings;

  if( empty($items))
  {
    die("no items");
  }

  $item_csv = join(',', $items);
  $cart_detail_text = "Website purchase";

  $sale_array = array(
    "amount" => $amount,
    "channel" => $settings["BT_CHANNEL_NAME"],
    'customer' => array(
        "firstName" => $firstName,
        "lastName" => $lastName,
        "phone" => $phone,
        "email" => $email,
      ),
  "customFields" => array(
    "cart_details" => $item_csv,
    "cart_detail_text" => $cart_detail_text
  ),
  "options" => array(
      "submitForSettlement" => true,
      "storeInVault" => true
    )
  );
  $sale_array["paymentMethodNonce"] = $nonce;


  $brainTreeCustomer = getBrainTreeCustomerByEmail($email);

  // If we have the customer in our BT vault
  if(!empty($brainTreeCustomer))
  {
    $sale_array["customerId"] = $brainTreeCustomer->id;

    // Handle paying by credit card
    if( $chargeType == "credit" )
    {
      // Because the customer is in our vault, we'll handle adding of their information manually to avoid duplication
      unset($sale_array["options"]["storeInVault"]);

      // Attempt to create a new payment method, if it fails, we know that it already exists
      $result = Braintree_PaymentMethod::create([
          'customerId' => $brainTreeCustomer->id,
          'paymentMethodNonce' => $nonce,
          'options' => [
            'failOnDuplicatePaymentMethod' => true
          ]
      ]);
    }
    else
    {
      // If the user is paying with PayPal we want to avoid creating a duplicate account
      // In production, you'd do this by modifying the onPaymentMethodReceived JavaScript method in index.php
      //  to look for the PayPal email address that is passed to it,
      //  then use Braintree_Customer::search([Braintree_CustomerSearch::id()->paypalAccountEmail("paypal@email.address.com")]); '
      //  to verify if the user already has a vaulted PayPal account
    }
  }


  $result = Braintree_Transaction::sale($sale_array);

  if( $result->success )
  {
    // echo "Transaction Successful: " . $transactionId = $result->transaction->id;
    sendPurchaseEmail($result, $amount, $email);
    return true;
  }
  else
  {
    echo "Transaction Failed: " . $result->message;
    return false;
  }
}

/* Change Braintree Customer
*
* Charge a customer after the button is pressed using the Braintree sale() API call
*/
function chargeBraintreeCustomer($braintreeCustomerId, $customerEmailAddress, $amount, $cart_detail_text, $items_id_array, $sendEmail=false)
{
  global $settings;

  if( empty($items_id_array))
  {
    $cart_detail_text = "bttn pressed - fixed price";
  }

  $customer = Braintree_Customer::find($braintreeCustomerId);
  $paymentMethodToken = getDefaultPaymentMethod($customer);
  $item_csv = join(',', $items_id_array);

  $sale_array = array(
      "amount" => $amount,
      "channel" => $settings["BT_CHANNEL_NAME"],
      "paymentMethodToken" => $paymentMethodToken,
    "options" => array(
        "submitForSettlement" => true
      ),
    "customFields" => array(
        "cart_details" => $item_csv,
        "cart_detail_text" => $cart_detail_text
      )
  );

  $result = Braintree_Transaction::sale($sale_array);

  if($sendEmail)
  {
    sendPurchaseEmail($result, $amount, $customerEmailAddress);
  }

  return $result;
}

/*  Update Button Status on Braintree
*
* This data is stored as a custom field called "bttn_status"
*/
function updateButtonStatusOnBraintree($braintreeCustomerId, $status)
{
  global $settings;

  $statuses = array("ACTIVE", "ONBOARDED", "UNREGISTERED");
  if( !in_array($status, $statuses) )
  {
    die("Status: $status not in list of valid statuses.");
  }

  $updateResult = Braintree_Customer::update(
    $braintreeCustomerId,
    [
      'customFields' => array(
        "bttn_status" => $status
      )
    ]
  );

  return $updateResult->success;

}

/*  Update Button Code on Braintree
*
* This data is stored as a custom field called "bttn_code"
*/
function updateButtonCodeOnBraintree($braintreeCustomerId, $buttonAssocationId)
{
  global $settings;

  $updateResult = Braintree_Customer::update(
    $braintreeCustomerId,
    [
      'customFields' => array(
        "bttn_code" => $buttonAssocationId
      )
    ]
  );
  return $updateResult->success;

}

/*  Get Braintree customer by their braintree customer id
*
* Use BT search API to get customer information
*/
function getBrainTreeCustomerById($braintreeCustomerId)
{
  $collection = Braintree_Customer::search(
      [Braintree_CustomerSearch::id()->is($braintreeCustomerId)]
    );

  foreach($collection as $customer) {
    return $customer;
  }
}

/*  Get Braintree customer by their email address
*
* Use BT search API to get customer information
*/
function getBrainTreeCustomerByEmail($email)
{
  $collection = Braintree_Customer::search(
      [Braintree_CustomerSearch::email()->is($email)]
    );

  foreach($collection as $customer) {
    return $customer;
  }
}

/*  Get all customers in Braintree
*
* Use BT search API to get the customer id
*/
function getRecentBraintreeCustomers()
{
  $collection = Braintree_Customer::search(
      [Braintree_CustomerSearch::createdAt()->between("12/17/2013 17:00", "12/17/2019 17:00")]
    );

  return $collection;
}

/*  Get only the BT customer id by their emai address
*
* Use BT search API to get the customer id
*/
function getBrainTreeCustomerIdByEmail($email)
{
  $collection = Braintree_Customer::search(
      [Braintree_CustomerSearch::email()->is($email)]
    );

  foreach($collection as $customer) {
    return $customer->id;
  }
}

/*  Get the last successful transaction from Braintree
*
* Use BT search API to the last transaction
*/
function getLastTransaction($customerId)
{
  $collection = Braintree_Transaction::search(
  [
    Braintree_TransactionSearch::customerId()->is($customerId),
    Braintree_TransactionSearch::status()->in(
      [
        Braintree_Transaction::SUBMITTED_FOR_SETTLEMENT,
        Braintree_Transaction::SETTLED,
        Braintree_Transaction::SETTLING
      ])
  ]
  );

  foreach($collection as $transaction) {
    // printf("date: %s, amt: %s<br>", $transaction->createdAt->format('Y-m-d H:i:s'), $transaction->amount);
    return $transaction;
  }
}

?>
