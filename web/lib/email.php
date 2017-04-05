<?php
require_once(dirname(__FILE__) . "/../../vendor/autoload.php");
include_once(dirname(__FILE__) . "/config.php");
include_once(dirname(__FILE__) . "/db.php");
include_once(dirname(__FILE__) . "/cart.php");

//Create a new PHPMailer instance
$mail = new PHPMailer;


if( $settings["USE_SENDMAIL"] )
{
	$mail->isSendmail();
}
else
{
	$mail->IsSMTP();
	$mail->Host       = $settings["MAIL_SMTP_HOST"];
	$mail->Port       = $settings["MAIL_SMTP_PORT"];
}

//Set who the message is to be sent from
$mail->setFrom($settings["MAIL_FROM_EMAIL"], $settings["MAIL_FROM_NAME"] );
$mail->AddBCC($settings["MAIL_BCC"]);

$loader = new Twig_Loader_Filesystem( dirname(__FILE__).'/../templates');
$twig = new Twig_Environment($loader);




function generateRandomLink($item, $email)
{
	$hash = sha1( $item );
	$uhash = sha1( $email );
	$url = sprintf("%s://%s%s", $_SERVER["REQUEST_SCHEME"], $_SERVER["HTTP_HOST"], "/selection.php?item=$hash&user=$uhash");
	return $url;
}


function sendSelectionEmail($customerEmailAddress, $options)
{
	global $mail;
	$loader = new Twig_Loader_Filesystem( dirname(__FILE__).'/../templates');
	$twig = new Twig_Environment($loader);
	$template_email = $twig->loadTemplate('selection_email.html');

	$mail->addAddress($customerEmailAddress);


	foreach( $options as &$option )
	{
		$option["link"] = generateRandomLink($option["title"], $customerEmailAddress);
	}

	//Set the subject line
	$mail->Subject = "Order Created from Button Press - What would you like?";

	$mail->msgHTML(
	  $template_email->render(array(
	      'options' => $options
	      )
	    ),
	    dirname(__FILE__),
	    true);

	$mail->AltBody = 'This is a plain-text message body';
	//send the message, check for errors
	if (!$mail->send()) {
	    echo "An email could not be sent. Mailer Error: " . $mail->ErrorInfo;
	}
}



function sendPurchaseEmail($result, $amount, $customerEmailAddress)
{
	global $mail;

	$loader = new Twig_Loader_Filesystem( dirname(__FILE__).'/../templates');
	$twig = new Twig_Environment($loader);
	$template_email = $twig->loadTemplate('cart_checkout_email.html');

	$mail->addAddress($customerEmailAddress);

	//Set the subject line
	$transactionId = $result->transaction->id;
	$mail->Subject = "Order Created from Button Press #$transactionId";

	$cart_details_csv = $result->transaction->customFields["cart_details"];
	$cartToPrint = getCartFromItemIdCsv($cart_details_csv);

	// Handle fixed-price order type
	if( empty($cart_details_csv) )
	{
		$cartToPrint =   array(
		    "id" => "1",
		    "cost" => $amount,
		    "title" => "Fixed Price Charge",
		    "image" => "img/portfolio/5.jpg"
		  );
	}


	$date = date('D m-d-Y h:i:s a');

	$mail->msgHTML(
	  $template_email->render(array(
	      'items' => $cartToPrint,'total' => $amount, 'bt_details' => $result, 'txn_date' => $date
	      )
	    ),
	    dirname(__FILE__),
	    true);

	$mail->AltBody = 'This is a plain-text message body';

	//send the message, check for errors
	if (isset($mail) && !$mail->send()) {
		$error = htmlspecialchars($mail->ErrorInfo);

		// Best effort - don't echo anything
		/*
		if(isset($error))
		{
			echo "An email could not be sent. Mailer Error: $error" ;
		}
		else {
			echo "An email could not be sent. No error code was provided." ;
		}
		*/

	}
}

?>
