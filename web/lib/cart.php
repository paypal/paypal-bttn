<?php
include_once(dirname(__FILE__) . "/../lib/config.php");


function getCartFromItemIdCsv($item_csv)
{
	global $settings;

	// e.g. $item_csv = 1,2,3
	$items_csv_to_array = explode(",", $item_csv);

	$result = array();
	foreach( $settings["CART_OPTIONS"] as $item )
	{
		if( in_array($item["id"], $items_csv_to_array) )
		{
			array_push($result, $item);
		}
	}

	return $result;
}


function getRandomCart()
{
	global $settings;

	$possibilities = $settings["CART_OPTIONS"];

	$result = array();
	foreach ($possibilities as $item) {
		// Let a coin flip determine what is added to the cart
		if( rand(0,1) == 1 )
		{
			array_push($result, $item);
		}
	}

	// Make sure we have something in the cart
	if(sizeof($result) == 0)
	{
		return $possibilities[rand(0,sizeof($possibilities))];
	}

	return $result;
}

function getCartTotal($cart)
{
	$total = 0.0;
	foreach( $cart as $item )
	{
		$total += $item["cost"];
	}
	return $total;
}

?>
