<?php
require_once(dirname(__FILE__) . "/db.php");
include_once(dirname(__FILE__) . "/config.php");

class ButtonResponse {
	var $error = false;
	var $error_text;
	var $associationId;

	function populate_response($responseData)
	{
		if( sizeof($responseData) > 0 )
		{
			$this->associationId = $responseData[0]["associd"];

			$errText = $responseData[0]["error"];
			if( !empty($errText) )
			{
					$this->error_text = $errText;
					$this->error = true;
			}
		}
		else
		{
			$this->error_text = "no response provided.";
			$this->error = true;
		}
	}
}

function doGet($url)
{
	global $settings;

	// Setup cURL
	$ch = curl_init($url);
	curl_setopt_array($ch, array(
			CURLOPT_POST => FALSE,
			CURLOPT_RETURNTRANSFER => TRUE,
			CURLOPT_HTTPHEADER => array(
					'X-Api-Key: '.$settings["BTTN_API_KEY"],
					'Content-Type: application/json'
			)
	));

	// Send the request
	$response = curl_exec($ch);

	// Check for errors
	if($response === FALSE){
	    die(curl_error($ch));
	}

	$httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

	if($httpcode >= 500)
	{
		die("error response received - HTTP response status $httpcode");
	}

	// Decode the response
	$responseData = json_decode($response, TRUE);

	return $responseData;
}


function doPost($url, $payload)
{
	global $settings;

	// Setup cURL
	$ch = curl_init($url);
	curl_setopt_array($ch, array(
			CURLOPT_POST => TRUE,
			CURLOPT_RETURNTRANSFER => TRUE,
			CURLOPT_HTTPHEADER => array(
					'X-Api-Key: '.$settings["BTTN_API_KEY"],
					'Content-Type: application/json'
			),
			CURLOPT_POSTFIELDS => json_encode($payload)
	));

	// Send the request
	$response = curl_exec($ch);

	// Check for errors
	if($response === FALSE){
	    die(curl_error($ch));
	}

	$httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

	if($httpcode >= 400)
	{
		die("no response received - HTTP response status $httpcode");
	}

	// Decode the response
	$responseData = json_decode($response, TRUE);

	$resp = new ButtonResponse();
	$resp->populate_response($responseData);

	return $resp;
}


/*
* Associate Button
*
*	Given the $code from the button, call the bt.tn API to associate the button to the
*/
function associate_button($code)
{
	global $settings;

	$payload = array(array("code"=> $code));

	$url = sprintf('https://your.bt.tn/serves/%s/v1/associate', $settings["BTTN_API_MERCHANT_NAME"]);
	return doPost($url, $payload);
}

/*
* Release Button
*
*	Given the $associd for the button, unpair it.
*/
function release_button($associd)
{
	global $settings;

	$payload = array(array("associd"=> $associd));

	$url = sprintf('https://your.bt.tn/serves/%s/v1/release', $settings["BTTN_API_MERCHANT_NAME"]);
	return doPost($url, $payload);
}


/*
* Add Data to Button
*
*	Supply assocation data to the button that will be used each time the button is pressed
*/
function add_merchant_data_to_button($associd, $email_address, $customerBraintreeId=NULL, $chargeType=NULL)
{
	global $settings;

	$checkout_url = $settings["BTTN_PRESS_URI_ENDPOINT"];
	$x_api_key = sha1($associd);

	$payload = array(
			"associd" => $associd,
			"data" => array("pressed" =>
							array( "http" => array(
									"method" => "post",
									"url" => $checkout_url,
									"headers" => array(
										"X-Api-Key" => $x_api_key,
										"Accept" => "application/json"
										),
									"json" => array(
											"type" => "short",
											"associd" => $associd,
											"bt_id" => $customerBraintreeId,
											"charge_type" => $chargeType
										)
									)
								)
							),
			array("pressed_long" =>
							array( "http" => array(
									"method" => "post",
									"url" => $checkout_url,
									"headers" => array(
										"X-Api-Key" => $x_api_key,
										"Accept" => "application/json"
										),
									"json" => array(
											"type" => "long",
											"associd" => $associd,
											"bt_id" => $customerBraintreeId,
											"charge_type" => $chargeType
										)
									)
								)
							)
		);
	$payload = array($payload);

	$url = sprintf('https://your.bt.tn/serves/%s/v1/data', $settings["BTTN_API_MERCHANT_NAME"]);

	return doPost($url, $payload);
}


/*
*	Given a button association id, get the details about that button
*/
function get_button_data($assocationId)
{
	global $settings;
	$url = sprintf('https://your.bt.tn/serves/%s/v1/data/%s', $settings["BTTN_API_MERCHANT_NAME"], $assocationId);
	return doGet($url);
}

/*
*	This function is unused but was included for completeness
*/
function get_all_buttons()
{
	global $settings;
	$url = sprintf('https://your.bt.tn/serves/%s/v1/associate', $settings["BTTN_API_MERCHANT_NAME"]);
	return doGet($url);
}

function get_all_buttons_and_all_data()
{
	$bttns = get_all_buttons();

	$result = array();
	foreach( $bttns as $bttn )
	{
		$bttn_data = get_button_data($bttn["associd"]);
		array_push($result,$bttn_data);
	}

	return $result;
}

?>
