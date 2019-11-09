<?php

include('DecisionsParser.php');
$decisionParser = new DecisionParser();
$decisions = $decisionParser->main();

echo implode("\n", array_merge(
	[implode(",", $decisions["headers"])],
	array_map(function ($row) {
		return implode(",", $row);
	}, $decisions["data"])
));
