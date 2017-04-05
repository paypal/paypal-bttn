<?php
require_once(dirname(__FILE__) . '/lib/db.php');
require_once(dirname(__FILE__) . '/lib/braintree.php');
require_once(dirname(__FILE__) . "/lib/cart.php");

$item = $_GET["item"];
$user = $_GET["user"];

/*
* selection.php requires a USE_DATABASE to be true as we are not able to perform all actions solely within the Braintree APIs
*
*/


function getMatchingUser($submittedUser)
{
  foreach( getUsers() as $user)
  {
    if( $submittedUser == sha1($user["email"]) )
    {
        return $user;
    }
  }
}


$dbUser = getMatchingUser($user);
if( empty($dbUser) )
{
  die("no user found");
}


if( $item == sha1("Your previous order") )
{
  $lastBtTxn = getLastTransaction($dbUser["customer_id"]);
  $amount = $lastBtTxn->amount;

  $lastTxnCartCsv = $lastBtTxn->customFields["cart_details"];
	$items = explode(",", $lastTxnCartCsv);
  $cart = getCartFromItemIdCsv($lastTxnCartCsv);

  $type = REORDER;
}
else
{
  $text = "No item specified.";
  foreach( $settings["CART_OPTIONS"] as $possible_items)
  {
    if( $item == sha1($possible_items["title"]) )
    {
      $amount = $possible_items["cost"];
      $items = array($possible_items["id"]);
      $cart = getCartFromItemIdCsv($possible_items["id"]);
      $type = SELECTION;
    }
  }
}

$cart_detail_text = "bttn pressed - selection";
$braintreeCustomerId = $dbUser["customer_id"];
$customerEmailAddress = $dbUser["email"];


$loader = new Twig_Loader_Filesystem( dirname(__FILE__).'/templates');
$twig = new Twig_Environment($loader);

$template = $twig->loadTemplate('selection_web.html');

echo $template->render(
  array(
    "amount" => $amount,
    "items" => $cart,
    "email" => $customerEmailAddress,
    "type" => $type
  )
);


chargeBraintreeCustomer($braintreeCustomerId, $customerEmailAddress, $amount, $cart_detail_text, $items, true);




?>
