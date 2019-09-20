<?php
/**
 * [parseDecisions description]
 *
 * @todo handle wards that have leading zeroes and casing
 * //['bremen','cottage','frankfort','geneva','gove','lamson','lubec','maverick','orleans','porter','mckay'].map(x => `iferror(find("${x}",lower(D2)),FALSE)`).reduce((s, x) => s+=","+x)
 * 
 * @param  string $mode csv or html
 * @return string       results structured as csv or html
 */
function parseDecisions($mode = 'csv') {

	$file = file_get_contents("zoning-board-appeal-decisions");
	
	$lines = explode("\n", $file);

	// Eliminate the extraneous lines
	$filtered = array_filter($lines, function($item) {
		$date = preg_match('/^.*[A-Za-z]+ [0-9]{1,2}, [0-9]{4}.*$/', $item);
		$label = preg_match('/<address>(A|D).*<\/address>/', $item);
		$case = preg_match('/BOA/', $item);
		return $date || $label || $case;
	});

	$cleansed = array_map('parseItem', $filtered);
	$flattened = [];
	array_walk_recursive($cleansed, function($item) use (&$flattened) { $flattened[] = $item; });
	
	$structured = array_map('parseAppeal', $flattened);

	$splits = preg_split('/([0-9\/]{8})/', implode("\n", $structured), 0, PREG_SPLIT_NO_EMPTY | PREG_SPLIT_DELIM_CAPTURE );
	$dated = [];
	for($i = 0; $i<count($splits); $i+=2) {
		$dated[$splits[$i]] = $splits[$i+1];
	}
	
	$csv = implode(",", ["date","status","appeal", "address", "ward"]).PHP_EOL;
	
	foreach($dated as $date=>$split) {
		$parts = preg_split('/(APPROVED DECISIONS\n|Approved decisions\n|DENIED DECISIONS\n|Denied Decisions\n|Denied decisions\n|Denied Decision\n|DENIED DECISION\n|DENIED\n|Denied\n)/',trim($split), 0, PREG_SPLIT_NO_EMPTY | PREG_SPLIT_DELIM_CAPTURE);
		for($i=0; $i<count($parts); $i+=2) {
			foreach(explode("\n", trim($parts[$i+1])) as $part) {
				list($appeal, $address, $ward) = explode("\t", $part);
				$status = strpos(strtolower(trim($parts[$i])), "approved") !== False ? "APPROVED" : "DENIED";
				$csv .= implode(",", [$date,$status,$appeal,$address,$ward]).PHP_EOL;
			}
		}
	}
	
	if($mode == 'csv') {
		return $csv;
	} else if($mode == 'html') {
		$rows = explode("\n", $csv);
		$headers = array_shift($rows);
		$headers = "<tr><th>".implode("</th><th>",explode(",", $headers))."</th></tr>".PHP_EOL;
		$rows = array_map(function($row) {return "<tr><td>".implode("</td><td>",explode(",", $row))."</td></tr>";}, $rows);
		return "<table><thead>$headers</thead><tbody>".implode("\n", $rows)."</tbody></table>";
	}
}

function parseItem($item) {
	if(	preg_match('/^.*?([A-Za-z]+ [0-9]{1,2}, [0-9]{4}).*$/', $item, $match) ) {
		return strftime("%D",strtotime($match[1]));
	} else if ( preg_match('/<address>(.*?)<\/address>/', $item, $match) ) {
		return $match[1];
	} else if ( preg_match('/(BOA.*<br \/>)/', $item, $match) ) {
		$parsed = array_map(function($item) {
			return preg_replace('/^[0-9]+\.(\ | )+BOA/', 'BOA', $item);
		}, explode("<br />", $match[1]));
		return $parsed;
	} else if ( preg_match('/(BOA[^<]+)/', $item, $match) ) {
		return $match[1];
	} else {
		return $item;
	}
}

function parseAppeal($appeal) {
	$appeal = trim($appeal, " ");
	$appeal = preg_replace('/(BOA#|BOA_|BOA-#)/', 'BOA-', $appeal);
	$appeal = preg_replace('/BOA([0-9])/', 'BOA-$1', $appeal);
	$appeal = preg_replace('/ Wad /', ' Ward ', $appeal);
	$appeal = preg_replace('/, /', ',', $appeal);
	$special = specialCases($appeal);
	if($special) {
		return implode("\t", $special);
	} else if (preg_match('/^(BOA-[0-9]+)(?:\ | )+ ?(.*?), ((?:Ward|WARD) [0-9]+)$/',$appeal,$match)) {
		return implode("\t", [$match[1], $match[2], $match[3]]);
	} else {
		return $appeal;
	}
}

function specialCases($appeal) {
	$cases = [
		"BOA-885363             38 Englewood Avenue 1" => ["BOA-885363", "38 Englewood Avenue", "Ward 1"],
		"BOA-892286             38R Minot Street, 16" => ["BOA-892286", "38R Minot Street", "Ward 16"],
		"BOA-871895             215 West Street, Ward" => ["BOA-871895", "215 West Street", "Ward 18"],
		"BOA-779503             1156-1160 Washington Street, War 17" => ["BOA-779503", "1156-1160 Washington Street", "Ward 17"],
		"BOA-785812             86 Princeton Street, Ward" => ["BOA-785812", "86 Princeton Street", "Ward 1"],
		"BOA-700855 1432-1440 Commonwealth Ave, Wd 21" => ["BOA-700855", "1432-1440 Commonwealth Ave", "Ward 21"],
		"BOA-694839 89-95 Brighton Avenue" => ["BOA-694839", "89-95 Brighton Avenue", "Ward 21"],
		"BOA-640507 522 East Seventh St, Wd 7-Granted In Part &amp;amp; Denied In Part" => ["BOA-640507", "522 East Seventh St*", "Ward 7"],
		"BOA-640509 8 Salerno Pl, Ward 7-Granted In Part &amp;amp; Denied In Part" => ["BOA-640509", "8 Salerno Pl*", "Ward 7"],
		"BOA-640513 4 Salerno Pl, Ward 7-Granted In Part &amp;amp; Denied In Part" => ["BOA-640513", "4 Salerno Pl*", "Ward 7"],
		"BOA-634526 3193-3201 Washington Street, 11" => ["BOA-634526", "3193-3201 Washington Street", "Ward 11"],
		"BOA-639862 38A-38 South Russell Street" => ["BOA-639862", "38A-38 South Russell Street", "Ward 5"],
		"BOA-830941             28 Dix Street, Ward, Ward 16" => ["BOA-830941", "28 Dix Street", "Ward 16"]
	];
	if(array_key_exists($appeal, $cases)) {
		return $cases[$appeal];
	} else {
		return False;
	}
}
?>

<!DOCTYPE html>
<html>
<head>
	<title>Boston Zoning Board of Appeal Decisions</title>
</head>
<body>
<h1>Boston Zoning Board of Appeal Decisions</h1>
<p>This an aggregation of the information available at <a href="https://www.boston.gov/departments/inspectional-services/zoning-board-appeal-decisions">https://www.boston.gov/departments/inspectional-services/zoning-board-appeal-decisions</a>. A CSV of the data is available <a href="decisions_<?php echo strftime("%Y%m%d"); ?>.csv">here</a>. This document was last updated on <?php echo strftime("%D"); ?>. For more information, contact <a href="mailto:nattaylor@gmail.com">nattaylor@gmail.com</a>.</p>
<p>The previous version is available <a href="old.html">here</a>.</p>
<?php echo parseDecisions('html'); ?>
</body>
</html>

