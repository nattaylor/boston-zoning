<?php
/**
 * Build and write decisions and cases
 */
include('DecisionsParser.php');
include('MinutesParser.php');

echo "Starting build...".PHP_EOL;

echo "== Building Parsed Decisions ==".PHP_EOL;

$decisionParser = new DecisionParser();
$decisions = $decisionParser->main();
echo sprintf("Found %s cases; Expected about %s cases".PHP_EOL, count($decisions["data"]), $decisions["meta"]["input"]);
echo "Example: ".implode(", ", $decisions["data"][0]).PHP_EOL;

$date = strftime("%Y%m%d");
$csv = file_put_contents("dist/decisions_$date.csv", implode("\n", array_merge(
	[implode(",", $decisions["headers"])],
	array_map(function ($row) {
		return implode(",", $row);
	}, $decisions["data"])
)));
if ($csv) {
	echo "Wrote CSV.".PHP_EOL;
} else {
	die("Build failed.  Unable to write CSV.");
}

$html = file_put_contents("dist/decisions_$date.html", shell_exec("php index.php"));

if ($html) {
	echo "Wrote HTML.".PHP_EOL;
} else {
	die("Build failed.  Unable to write HTML.");
}

echo "Done with decisions.".PHP_EOL.PHP_EOL;

/*
echo "== Building Parsed Minutes ==".PHP_EOL;

$minutesParser = new \Zoning\MinutesParser();
$minutes = $minutesParser->main();
echo sprintf("Parsed %s cases".PHP_EOL, count(json_decode($minutes)));
*/