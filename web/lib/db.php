<?php

/*
*
*  See db.sql for CREATE statements
*
*/

require_once(dirname(__FILE__) . "/../../vendor/autoload.php");
require_once(dirname(__FILE__) . "/../lib/braintree.php");
include_once(dirname(__FILE__) . "/../lib/config.php");



function createUserRequest($email)
{
	$brainTreePaymentId = getBrainTreeCustomerIdByEmail($email);

	if( empty($brainTreePaymentId) )
	{
		echo "Couldn't find a Braintree consumer in the vault with that email address.";
		return false;
	}


	$result = DB::insertUpdate('button_user', array(
		'email' => $email,
		'consumer_stage' => 'ONBOARDED'
	));

	return $result == 1;
}


function createUserRegistration($email, $button_id)
{
	$user = DB::queryFirstRow("SELECT * from button_user WHERE email=%s", $email);

	if(empty($button_id))
	{
		echo "No button code specified.";
		return false;
	}

	// We need to call Braintree to get their ID even when we're using the database as the SYSTEM_OF_RECORD
	$brainTreeCustomerId = getBrainTreeCustomerIdByEmail($email);
	if( empty($brainTreeCustomerId) )
	{
		echo "Couldn't find a Braintree consumer with that email address.";
		return false;
	}

	// If the user already onboarded, update them to active
	if( $user )
	{
		$user["consumer_stage"] = "ACTIVE";
		$user["button_id"] = $button_id;
		$user["customer_id"] = $brainTreeCustomerId;
		$user["amount_to_charge"] = $settings["DEFAULT_ORDER_VALUE"];

		$result = DB::update('button_user', $user, "email=%s", $email);

		return $result == 1;
	}
	else
	{
		// User didn't onboard, but they want to register their button
		$result = DB::insert('button_user', array(
			'email' => $email,
			'consumer_stage' => 'ACTIVE',
			'button_id' => $button_id,
			'amount_to_charge' => $settings["DEFAULT_ORDER_VALUE"],
			'customer_id' => $brainTreeCustomerId
		));
		return $result == 1;
	}

	return false;
}

function getUserByCustomerId($customerId)
{
	$user = DB::queryFirstRow("SELECT * from button_user WHERE customer_id=%s", $customerId);
	return $user;
}

function getUserByEmail($email)
{
	$user = DB::queryFirstRow("SELECT * from button_user WHERE email=%s", $email);
	return $user;
}

function getUserByButtonCode($button_code)
{
	$user = DB::queryFirstRow("SELECT * from button_user WHERE button_id=%s", $button_code);
	return $user;
}

function saveCustomerData($customerArray)
{
	foreach ($customerArray as $c) {
		if( !empty($c->chargeAmount) )
		{
				$user = getUserByCustomerId( $c->brainTreeAccountId );

				$result = DB::update(
				'button_user',
				array( "amount_to_charge" => $c->chargeAmount ),
				"customer_id=%s", $c->brainTreeAccountId
			);
		}
	}
}

function updateConsumerStage($email, $stage)
{
	$user = DB::queryFirstRow("SELECT * from button_user WHERE email=%s", $email);

	if ($user)
	{
		$result = DB::insertUpdate('button_user', array(
		'email' => $email,
		'consumer_stage' => $stage
		));
	}

	return $user;
}

function getUsers()
{
	$accounts = DB::query("SELECT * FROM button_user");
	return $accounts;
}

function getUser($id)
{
	$accounts = DB::query("SELECT * FROM button_user");
	foreach ($accounts as $account) {
	  print_r( $account ). "\n";
	}
}

?>
