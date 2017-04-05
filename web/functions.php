<?php
require_once(dirname(__FILE__) . '/lib/db.php');
require_once(dirname(__FILE__) . '/lib/braintree.php');
require_once(dirname(__FILE__) . "/lib/slack.php");
require_once(dirname(__FILE__) . "/lib/bttn.php");
require_once("../vendor/autoload.php");


class CustomerPostData
{
	public $brainTreeAccountId;
	public $chargeAmount;
	public $emailAddress;
}

$function = htmlspecialchars($_POST["function"]);

// A simple utility for accepting form POSTs from index.php

////////////////////////////////////////////////////////////////////////////
//////////////////////////////////////
//////////     REQUEST

if( $function == "REQUEST" )
{
	$email = htmlspecialchars($_POST["email"]);

	if( updateButtonStatusOnBraintree(getBrainTreeCustomerIdByEmail($email), "ONBOARDED") )
	{
		echo "success";
	}
	else
	{
		echo "failed to update button status on Braintree";
	}


	if( $settings["USE_DATABASE"] )
	{
		if( createUserRequest($email) )
		{
			echo "success";
			$msg = sprintf("User Request Completed! Get this customer a button! Email: %s", $email);
			sendSlackMessage($msg);
		}
		else
		{
			echo "failed to update button status in the database";
		}
	}
}


////////////////////////////////////////////////////////////////////////////
//////////////////////////////////////
//////////     REGISTER

else if ($function == "REGISTER")
{
	$email = htmlspecialchars($_POST["email"]);
	$button_code = htmlspecialchars($_POST["code"]); // e.g. 1234-1234-1234-1234

	if(empty($email))
	{
		die("fail: no email provided");
	}

	// Call to bttn to associate the button code - this will give us an association id that we use in later calls
	$buttonCodeAssociationResponse = associate_button($button_code);

	if( $buttonCodeAssociationResponse->error )
	{
		die("fail: ".$buttonCodeAssociationResponse->error_text );
	}

	$buttonAssocationId = $buttonCodeAssociationResponse->associationId;

	$braintreeCustomerId = getBrainTreeCustomerIdByEmail($email);
	updateButtonStatusOnBraintree($braintreeCustomerId, "ACTIVE");
	updateButtonCodeOnBraintree($braintreeCustomerId, $buttonAssocationId);

	$addDataResponse = add_merchant_data_to_button($buttonAssocationId, $email, $braintreeCustomerId, $settings["DEFAULT_ORDER_VALUE"]);

	if( $addDataResponse->error )
	{
		die("fail: ".$addDataResponse->error_text );
	}

	echo "success";

	$msg = sprintf("User Registration Completed! The customer can now use the button. Email: %s, Button Code: %s, Response: %s", $email, $button_code, $buttonAssocationId);
	sendSlackMessage($msg);

	// Add the user to our database - this is done so we can display the user in the merchant portion of the demo
	if( $settings["USE_DATABASE"] )
	{
			createUserRegistration($email, $buttonAssocationId);
	}
}

////////////////////////////////////////////////////////////////////////////
//////////////////////////////////////
//////////     PURCHASE

else if($function == "PURCHASE")
{
	$email = 	htmlspecialchars($_POST["email"]);
	$firstName = htmlspecialchars($_POST["firstName"]);
	$lastName = htmlspecialchars($_POST["lastName"]);
	$phone = htmlspecialchars($_POST["phone"]);
	$amount = htmlspecialchars($_POST["amount"]);
	$items = $_POST["items"];

	$chargeType = htmlspecialchars($_POST["charge_type"]);
	$nonce = htmlspecialchars($_POST['payment_method_nonce']);

	if(empty($nonce))
	{
		die("No nonce");
	}

	if( processWebsiteTransaction($email, $firstName, $lastName, $phone, $nonce, $amount, $items, $chargeType) )
	{
		echo "success";
		$msg = sprintf("Transaction Completed! First Name: `%s`, Last Name: `%s`, Email: `%s`", $firstName, $lastName, $email);
    sendSlackMessage($msg);
	}
	else
	{
		echo "Could not process transaction";
	}
}

else if($function == "RELEASE_BY_ASSOCID") {
	$assocationId = htmlspecialchars($_POST["associd"]);

	if(empty($assocationId))
	{
		die("fail: no associd");
	}

	$buttonData = get_button_data($assocationId);
	$braintreeCustomerId = $buttonData["data"]["pressed"]["http"]["json"]["bt_id"];

	$releaseResp = release_button($assocationId);

	updateButtonStatusOnBraintree($braintreeCustomerId, "UNREGISTERED");


	if($settings["USE_DATABASE"])
	{
		$user = getUserByCustomerId($braintreeCustomerId);

		if(empty($user))
		{
				die("fail: no user \"$email\" in database");
		}

		$releaseResp = release_button($user["button_id"]);
		updateConsumerStage($email, "UNREGISTERED");
	}


	// If we got a response back from the bttn API call
	if( $releaseResp->error == false )
	{
		echo "success";

		$msg = sprintf("Released button for Email: %s, Code: %s", $email, $releaseResp->assocationId);
    sendSlackMessage($msg);
	}
	else
	{
		echo $releaseResp->error_text;
	}
}

////////////////////////////////////////////////////////////////////////////
//////////////////////////////////////
//////////     SHOW BTTN DATA
else if($function == "SHOW_BTTN_DATA")
{
	$loader = new Twig_Loader_Filesystem( dirname(__FILE__).'/templates');
	$twig = new Twig_Environment($loader);

	$bttns = get_all_buttons_and_all_data();
	$template = $twig->loadTemplate('bttn_data.html');

	echo $template->render(
		array(
			"button_data" => $bttns,
			"is_local" => preg_match('/localhost/',$_SERVER["HTTP_HOST"])
		)
	);
}

////////////////////////////////////////////////////////////////////////////
//////////////////////////////////////
//////////     SHOW BRAINTREE DATA
else if( $function == "SHOW_BRAINTREE_DATA")
{
	$loader = new Twig_Loader_Filesystem( dirname(__FILE__).'/templates');
	$twig = new Twig_Environment($loader);

	if($settings["USE_DATABASE"])
	{
			$button_users = getUsers();
	}
	else
	{
			$button_users = get_user_button_data();
	}

	$template = $twig->loadTemplate('braintree_data.html');
	echo $template->render(
		array( "users" => $button_users )
	);
}

////////////////////////////////////////////////////////////////////////////
//////////////////////////////////////
//////////     RELEASE
else if($function == "RELEASE") {
	$email = htmlspecialchars($_POST["email"]);

	if(empty($email))
	{
		die("fail: no email provided");
	}



	$customer = getBrainTreeCustomerByEmail($email);
	if(empty($customer))
	{
		die("fail: no user \"$email\" in Braintree");
	}

	$assocationId = $customer->customFields["bttn_code"];
	$releaseResp = release_button($assocationId);
	updateButtonStatusOnBraintree(getBrainTreeCustomerIdByEmail($email), "UNREGISTERED");


	if($settings["USE_DATABASE"])
	{
		$user = getUserByEmail($email);

		if(empty($user))
		{
				die("fail: no user \"$email\" in database");
		}

		$releaseResp = release_button($user["button_id"]);
		updateConsumerStage($email, "UNREGISTERED");
	}


	// If we got a response back from the bttn API call
	if( $releaseResp->error == false )
	{
		echo "success";

		$msg = sprintf("Released button for Email: %s, Code: %s", $email, $releaseResp->assocationId);
    sendSlackMessage($msg);
	}
	else
	{
		echo $releaseResp->error_text;
	}
}

////////////////////////////////////////////////////////////////////////////
//////////////////////////////////////
//////////     SAVE_USER - From Admin/Merchant
else if($function == "SAVE_USER")
{
	$customers = $_POST["customer_id"];
	$charge_amounts = $_POST["charge_amount"];

	$customerArray = array();

	// Process the posted data
	for($i=0; $i<sizeof($customers); $i++)
	{
			$brainTreeAccountId = $customers[$i];
			$charge_amount = $charge_amounts[$i];

			$c = new CustomerPostData();
			$c->chargeAmount = $charge_amount;
			$c->brainTreeAccountId = $brainTreeAccountId;

			array_push($customerArray, $c);
	}

	if( $settings["USE_DATABASE"])
	{
		// Save customer information in our local database (optional)
		saveCustomerData($customerArray);

		foreach ($customerArray as $c)
		{
			$charge_amount = $c->chargeAmount;
			$customerId = $c->brainTreeAccountId;


			if(!empty($charge_amount))
			{
				// Get the button code for the braintree customer
				$user = getUserByCustomerId($customerId);

				// Call the bttn API with the information from the vaulted braintree consumer
				$associd = $user["button_id"];
				$email_address = $user["email"];
				$customerBraintreeId = $user["customer_id"];
				$chargeType = $charge_amount;
				add_merchant_data_to_button($associd, $email_address, $customerBraintreeId, $chargeType);
			}
		}
	}

	foreach ($customerArray as $c)
	{
		$charge_amount = $c->chargeAmount;
		if( $charge_amount == SELECTION && !$settings["USE_DATABASE"])
		{
			die("fail: you are not able to use SELECTION if the USE_DATABASE setting is false.");
		}

		$customerId = $c->brainTreeAccountId;

		if(!empty($charge_amount))
		{
			// Get the button code for the braintree customer
			$btCustomer = getBrainTreeCustomerById($customerId);

			// Call the bttn API with the information from the vaulted braintree consumer
			$associd = $btCustomer->customFields["bttn_code"];
			$email_address = $btCustomer->email;
			$customerBraintreeId = $btCustomer->id;
			$chargeType = $charge_amount;
			add_merchant_data_to_button($associd, $email_address, $customerBraintreeId, $chargeType);
		}
	}


	echo "success";

} else {
	die("fail: no function. Got $function");
}

?>
