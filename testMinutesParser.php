<?php
include('MinutesParser.php');
$minutesParser = new \Zoning\MinutesParser();
$minutes = $minutesParser->main();
//echo sprintf("Parsed %s cases".PHP_EOL, count(json_decode($minutes)));
echo $minutes;
