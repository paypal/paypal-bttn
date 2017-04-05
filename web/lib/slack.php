<?php
include_once(dirname(__FILE__) . "/config.php");

function sendSlackMessage($text)
{
	global $settings;
	
	if( $settings["SLACK_ENABLE"] )
	{
		$payload = sprintf('payload={"text": "%s"}', $text);
		// use key 'http' even if you send the request to https://...
		$options = array(
		"ssl"=>array(
					"verify_peer"=>false,
					"verify_peer_name"=>false,
			),
			'http' => array(
				'method'  => 'POST',
				'content' => $payload,
			),
		);
		$context  = stream_context_create($options);
		$result = file_get_contents($settings["SLACK_URL"], false, $context);
	}
}


?>
