<?php

function check() {
	$ch = curl_init();
	curl_setopt($ch, CURLOPT_URL, "https://www.boston.gov/departments/inspectional-services/zoning-board-appeal");
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
	$file = curl_exec($ch);
	curl_close($ch);

	$regex = '/<meta class="swiftype" name="modified_at" data-type="date" content="(?<timestamp>.+?)" \/>/';

	if (!preg_match($regex, $file, $matches)) {
		exit(1);
	}

	$json = json_encode([
		"then_dt" => $matches['timestamp'],
		"then"    => strtotime($matches['timestamp']),
		"now"     => time(),
		"delta"   => strtotime($matches['timestamp']) > strtotime('-1 day')
	], JSON_PRETTY_PRINT);

	if (strtotime($matches['timestamp']) < strtotime('-1 day')) {
		exit(0);
	}

	$mail["to"] = "nat@nattaylor.com";
	$mail["subject"] = "New Zoning Minutes Available";
	$mail["message"] = "There are new minutes available at https://www.boston.gov/departments/inspectional-services/zoning-board-appeal";
	$mail["headers"][] = 'To: Nat Taylor <nat@nattaylor.com>';
	$mail["headers"][] = 'From: Nat Taylor <nat@nattaylor.com>';

	// Mail it
	mail($mail["to"], $mail["subject"], $mail["message"], implode("\r\n", $mail["headers"]));
}

check();
