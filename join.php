<?php

include('DecisionsParser.php');
include('MinutesParser.php');

$decisionParser = new DecisionParser();
$decisions = $decisionParser->main();

$minutesParser = new MinutesParser();
$minutes = $minutesParser->main();
//$minutes = array_map('json_decode', explode("\n", $minutes));
$minutes = json_decode( $minutes, True );

$mergedDecisions = [];
foreach($decisions['data'] as $decision) {
	$matches = array_filter($minutes, function($case) use ($decision) {
		return $decision[2] == $case['appeal'];
	});
	$mergedDecisions[$decision[2]] = [$decision, $matches];
}

$matchCount = [];

foreach ($mergedDecisions as $key=>$value) {
	
}
